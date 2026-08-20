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

        $this->artisan('inventory:scan')->assertExitCode(0);

        Mail::assertSent(InventoryAlertScanSummaryMail::class, 1);
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

    public function test_stock_level_detectors_skip_dropshipping_variants(): void
    {
        $product = Product::factory()->create(['is_active' => true]);

        $stockedOut = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'quantity' => 0,
            'reserved' => 0,
            'track_inventory' => true,
            'is_active' => true,
            'replenishment_status' => ProductVariant::REPLENISHMENT_REORDERABLE,
            'fulfillment_type' => ProductVariant::FULFILLMENT_STOCKED,
        ]);

        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'quantity' => 0,
            'reserved' => 0,
            'track_inventory' => true,
            'is_active' => true,
            'replenishment_status' => ProductVariant::REPLENISHMENT_REORDERABLE,
            'fulfillment_type' => ProductVariant::FULFILLMENT_DROPSHIPPING,
        ]);

        $detectedIds = collect((new OutOfStockDetector())->detect())
            ->pluck('id')
            ->all();

        $this->assertSame([$stockedOut->id], $detectedIds);
    }

    public function test_scan_resolves_existing_stock_alerts_for_dropshipping_variants(): void
    {
        Setting::set('slow_moving_min_age', 10000);

        $variant = ProductVariant::factory()
            ->for(Product::factory()->create(['is_active' => true]))
            ->create([
                'quantity' => 0,
                'reserved' => 0,
                'track_inventory' => true,
                'is_active' => true,
                'replenishment_status' => ProductVariant::REPLENISHMENT_REORDERABLE,
                'fulfillment_type' => ProductVariant::FULFILLMENT_STOCKED,
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

        // The variant becomes dropshipping after the alert was raised.
        $variant->update(['fulfillment_type' => ProductVariant::FULFILLMENT_DROPSHIPPING]);

        $this->artisan('inventory:scan')->assertExitCode(0);

        $this->assertDatabaseHas('inventory_alerts', [
            'id' => $alert->id,
            'status' => 'resolved',
            'resolved_reason' => 'Variant is fulfilled by dropshipping; no local stock is expected.',
        ]);

        // And it must not be re-raised on the next scan.
        $this->artisan('inventory:scan')->assertExitCode(0);
        $this->assertSame(1, $variant->inventoryAlerts()->count());
    }

    public function test_switching_a_variant_to_dropshipping_on_product_save_resolves_its_stock_alerts(): void
    {
        $product = Product::factory()->create(['is_active' => true]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'quantity' => 0,
            'reserved' => 0,
            'track_inventory' => true,
            'is_active' => true,
            'replenishment_status' => ProductVariant::REPLENISHMENT_REORDERABLE,
            'fulfillment_type' => ProductVariant::FULFILLMENT_STOCKED,
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

        app(\App\Services\ProductService::class)->update($product, [
            'name' => $product->name,
            'description' => $product->description,
            'variants' => [
                [
                    'id' => $variant->id,
                    'regular_price' => (float) $variant->regular_price,
                    'fulfillment_type' => ProductVariant::FULFILLMENT_DROPSHIPPING,
                    'value_ids' => [],
                ],
            ],
        ]);

        $this->assertSame(
            ProductVariant::FULFILLMENT_DROPSHIPPING,
            $variant->fresh()->fulfillment_type
        );

        $this->assertDatabaseHas('inventory_alerts', [
            'id' => $alert->id,
            'status' => 'resolved',
            'resolved_reason' => 'Variant switched to dropshipping fulfillment; no local stock is expected.',
        ]);
    }

    public function test_staff_can_set_replenishment_status_from_alert_and_stock_alerts_resolve(): void
    {
        $director = User::factory()->create();
        $director->syncRoles([RoleNames::DIRECTOR]);

        $variant = ProductVariant::factory()
            ->for(Product::factory()->create(['is_active' => true]))
            ->create([
                'sku' => 'ALERT-REPL-001',
                'replenishment_status' => ProductVariant::REPLENISHMENT_REORDERABLE,
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

        $this->actingAs($director)
            ->post(route('admin.inventory-alerts.replenishment', $alert), [
                'replenishment_status' => ProductVariant::REPLENISHMENT_DISCONTINUED,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('product_variants', [
            'id' => $variant->id,
            'replenishment_status' => ProductVariant::REPLENISHMENT_DISCONTINUED,
        ]);

        // Marking a variant paused/discontinued closes its open stock-level alerts.
        $this->assertDatabaseHas('inventory_alerts', [
            'id' => $alert->id,
            'status' => 'resolved',
            'resolved_by' => $director->id,
            'resolved_reason' => 'Variant replenishment status changed to Discontinued.',
        ]);
    }

    public function test_setting_replenishment_back_to_reorderable_keeps_alert_open(): void
    {
        $director = User::factory()->create();
        $director->syncRoles([RoleNames::DIRECTOR]);

        $variant = ProductVariant::factory()
            ->for(Product::factory()->create(['is_active' => true]))
            ->create([
                'sku' => 'ALERT-REPL-002',
                'replenishment_status' => ProductVariant::REPLENISHMENT_PAUSED,
            ]);

        $alert = InventoryAlert::query()->create([
            'type' => 'low_stock',
            'severity' => 'high',
            'variant_id' => $variant->id,
            'message' => 'Variant is below threshold.',
            'status' => 'open',
            'first_detected_at' => now(),
            'last_seen_at' => now(),
        ]);

        $this->actingAs($director)
            ->post(route('admin.inventory-alerts.replenishment', $alert), [
                'replenishment_status' => ProductVariant::REPLENISHMENT_REORDERABLE,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('product_variants', [
            'id' => $variant->id,
            'replenishment_status' => ProductVariant::REPLENISHMENT_REORDERABLE,
        ]);

        $this->assertDatabaseHas('inventory_alerts', [
            'id' => $alert->id,
            'status' => 'open',
        ]);
    }

    public function test_replenishment_endpoint_rejects_unknown_status(): void
    {
        $director = User::factory()->create();
        $director->syncRoles([RoleNames::DIRECTOR]);

        $variant = ProductVariant::factory()
            ->for(Product::factory()->create(['is_active' => true]))
            ->create(['replenishment_status' => ProductVariant::REPLENISHMENT_REORDERABLE]);

        $alert = InventoryAlert::query()->create([
            'type' => 'out_of_stock',
            'severity' => 'critical',
            'variant_id' => $variant->id,
            'message' => 'Variant is out of stock.',
            'status' => 'open',
            'first_detected_at' => now(),
            'last_seen_at' => now(),
        ]);

        $this->actingAs($director)
            ->from(route('admin.inventory-alerts.index'))
            ->post(route('admin.inventory-alerts.replenishment', $alert), [
                'replenishment_status' => 'retired',
            ])
            ->assertSessionHasErrors('replenishment_status');

        $this->assertDatabaseHas('product_variants', [
            'id' => $variant->id,
            'replenishment_status' => ProductVariant::REPLENISHMENT_REORDERABLE,
        ]);
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
