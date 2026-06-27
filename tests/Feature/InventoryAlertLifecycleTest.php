<?php

namespace Tests\Feature;

use App\Domain\Inventory\Alerts\Detectors\OutOfStockDetector;
use App\Domain\Inventory\Alerts\InventoryAlertEngine;
use App\Domain\Inventory\Alerts\InventoryAlertMailContext;
use App\Events\InventoryAlertRaised;
use App\Listeners\SendInventoryAlertNotification;
use App\Mail\InventoryAlertMail;
use App\Mail\InventoryAlertScanSummaryMail;
use App\Models\InventoryAlert;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Models\User;
use App\Support\RoleNames;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InventoryAlertLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_level_detectors_skip_paused_and_discontinued_variants(): void
    {
        $product = Product::factory()->create(['is_active' => true]);

        $reorderable = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'quantity' => 0,
            'reserved' => 0,
            'track_inventory' => true,
            'is_active' => true,
            'replenishment_status' => ProductVariant::REPLENISHMENT_REORDERABLE,
        ]);

        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'quantity' => 0,
            'reserved' => 0,
            'track_inventory' => true,
            'is_active' => true,
            'replenishment_status' => ProductVariant::REPLENISHMENT_PAUSED,
        ]);

        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'quantity' => 0,
            'reserved' => 0,
            'track_inventory' => true,
            'is_active' => true,
            'replenishment_status' => ProductVariant::REPLENISHMENT_DISCONTINUED,
        ]);

        $detectedIds = collect((new OutOfStockDetector())->detect())
            ->pluck('id')
            ->all();

        $this->assertSame([$reorderable->id], $detectedIds);
    }

    public function test_suppressed_open_alert_is_refreshed_without_creating_duplicates(): void
    {
        Event::fake([InventoryAlertRaised::class]);

        $variant = ProductVariant::factory()
            ->for(Product::factory()->create(['is_active' => true]))
            ->create([
                'track_inventory' => true,
                'is_active' => true,
                'replenishment_status' => ProductVariant::REPLENISHMENT_REORDERABLE,
            ]);

        $engine = app(InventoryAlertEngine::class);

        $engine->raise('out_of_stock', 'critical', $variant, null, 'Initial message');

        $alert = $variant->inventoryAlerts()->firstOrFail();
        $alert->update([
            'suppressed_at' => now(),
            'suppress_reason' => 'No restock planned this quarter.',
        ]);

        $engine->raise('out_of_stock', 'critical', $variant, null, 'Still out of stock');

        $this->assertSame(1, $variant->inventoryAlerts()->count());
        $this->assertDatabaseHas('inventory_alerts', [
            'id' => $alert->id,
            'message' => 'Still out of stock',
            'status' => 'open',
            'suppress_reason' => 'No restock planned this quarter.',
        ]);

        Event::assertDispatchedTimes(InventoryAlertRaised::class, 1);
    }

    public function test_out_of_stock_alert_is_auto_resolved_after_restock_on_next_scan(): void
    {
        Event::fake([InventoryAlertRaised::class]);
        Setting::set('slow_moving_min_age', 10000);

        $variant = ProductVariant::factory()
            ->for(Product::factory()->create(['is_active' => true]))
            ->create([
                'quantity' => 0,
                'reserved' => 0,
                'track_inventory' => true,
                'is_active' => true,
                'replenishment_status' => ProductVariant::REPLENISHMENT_REORDERABLE,
                'created_at' => now(),
            ]);

        $this->artisan('inventory:scan')->assertExitCode(0);

        $alert = $variant->inventoryAlerts()
            ->where('type', 'out_of_stock')
            ->firstOrFail();

        $this->assertSame('open', $alert->status);

        $variant->update([
            'quantity' => 5,
            'reserved' => 0,
        ]);

        $this->artisan('inventory:scan')->assertExitCode(0);

        $this->assertDatabaseHas('inventory_alerts', [
            'id' => $alert->id,
            'status' => 'resolved',
            'resolved_reason' => 'Stock condition recovered.',
        ]);
    }

    public function test_inventory_scan_sends_one_summary_email_for_detected_alerts(): void
    {
        Mail::fake();
        Setting::set('admin_email', 'admin@example.com');
        Setting::set('slow_moving_min_age', 10000);

        $firstProduct = Product::factory()->create([
            'name' => 'Alpha Laptop',
            'is_active' => true,
        ]);

        $secondProduct = Product::factory()->create([
            'name' => 'Beta Printer',
            'is_active' => true,
        ]);

        $firstVariant = ProductVariant::factory()
            ->for($firstProduct)
            ->create([
                'sku' => 'ALPHA-001',
                'quantity' => 0,
                'reserved' => 0,
                'reorder_point' => 5,
                'track_inventory' => true,
                'is_active' => true,
                'replenishment_status' => ProductVariant::REPLENISHMENT_REORDERABLE,
                'created_at' => now(),
            ]);

        $secondVariant = ProductVariant::factory()
            ->for($secondProduct)
            ->create([
                'sku' => 'BETA-001',
                'quantity' => 0,
                'reserved' => 0,
                'reorder_point' => 3,
                'track_inventory' => true,
                'is_active' => true,
                'replenishment_status' => ProductVariant::REPLENISHMENT_REORDERABLE,
                'created_at' => now(),
            ]);

        $this->artisan('inventory:scan')->assertExitCode(0);

        Mail::assertSent(InventoryAlertScanSummaryMail::class, 1);
        Mail::assertSent(InventoryAlertScanSummaryMail::class, function (InventoryAlertScanSummaryMail $mail) use ($firstVariant, $secondVariant): bool {
            $html = $mail->render();

            return $mail->hasTo('admin@example.com')
                && $mail->alerts->count() === 2
                && str_contains($html, 'Alpha Laptop')
                && str_contains($html, 'Beta Printer')
                && $mail->alerts->pluck('variant_id')->sort()->values()->all() === collect([
                    $firstVariant->id,
                    $secondVariant->id,
                ])->sort()->values()->all();
        });
        Mail::assertNotSent(InventoryAlertMail::class);
    }

    public function test_scan_mail_context_suppresses_immediate_critical_alert_email(): void
    {
        Mail::fake();
        Setting::set('admin_email', 'admin@example.com');

        $variant = ProductVariant::factory()
            ->for(Product::factory()->create(['is_active' => true]))
            ->create([
                'track_inventory' => true,
                'is_active' => true,
            ]);

        $alert = InventoryAlert::query()->create([
            'type' => 'out_of_stock',
            'severity' => 'critical',
            'variant_id' => $variant->id,
            'message' => 'Variant is out of stock.',
            'status' => 'open',
            'first_detected_at' => now(),
            'last_seen_at' => now(),
        ]);

        InventoryAlertMailContext::withoutImmediateMail(function () use ($alert): void {
            app(SendInventoryAlertNotification::class)->handle(new InventoryAlertRaised($alert));
        });

        Mail::assertNotSent(InventoryAlertMail::class);

        app(SendInventoryAlertNotification::class)->handle(new InventoryAlertRaised($alert));

        Mail::assertSent(InventoryAlertMail::class, 1);
    }

    public function test_staff_can_batch_suppress_alerts_and_audit_actor_is_exposed(): void
    {
        $director = User::factory()->create();
        $director->syncRoles([RoleNames::DIRECTOR]);

        $firstVariant = ProductVariant::factory()
            ->for(Product::factory()->create(['is_active' => true]))
            ->create(['sku' => 'ALERT-BULK-001']);

        $secondVariant = ProductVariant::factory()
            ->for(Product::factory()->create(['is_active' => true]))
            ->create(['sku' => 'ALERT-BULK-002']);

        $first = InventoryAlert::query()->create([
            'type' => 'out_of_stock',
            'severity' => 'critical',
            'variant_id' => $firstVariant->id,
            'message' => 'First variant is out of stock.',
            'status' => 'open',
            'first_detected_at' => now(),
            'last_seen_at' => now(),
        ]);

        $second = InventoryAlert::query()->create([
            'type' => 'low_stock',
            'severity' => 'high',
            'variant_id' => $secondVariant->id,
            'message' => 'Second variant is below threshold.',
            'status' => 'open',
            'first_detected_at' => now(),
            'last_seen_at' => now(),
        ]);

        $this->actingAs($director)
            ->post(route('admin.inventory-alerts.bulk'), [
                'ids' => [$first->id, $second->id],
                'action' => 'suppress',
                'reason' => 'No replenishment planned this month.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('inventory_alerts', [
            'id' => $first->id,
            'suppressed_by' => $director->id,
            'suppress_reason' => 'No replenishment planned this month.',
        ]);

        $this->assertDatabaseHas('inventory_alerts', [
            'id' => $second->id,
            'suppressed_by' => $director->id,
            'suppress_reason' => 'No replenishment planned this month.',
        ]);

        $this->actingAs($director)
            ->get(route('admin.inventory-alerts.index', ['state' => 'suppressed']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/InventoryAlerts/Index')
                ->where('alerts.data.0.audit.suppressed.name', $director->name)
            );
    }
}
