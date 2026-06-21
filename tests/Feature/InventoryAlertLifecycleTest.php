<?php

namespace Tests\Feature;

use App\Domain\Inventory\Alerts\Detectors\OutOfStockDetector;
use App\Domain\Inventory\Alerts\InventoryAlertEngine;
use App\Events\InventoryAlertRaised;
use App\Models\InventoryAlert;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Models\User;
use App\Support\RoleNames;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
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
