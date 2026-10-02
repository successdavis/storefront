<?php

namespace Tests\Feature;

use App\Domain\Inventory\Alerts\InventoryAlertEngine;
use App\Domain\Inventory\Audit\StockAuditService;
use App\Models\InventoryAlert;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockAuditSession;
use App\Models\User;
use App\Support\RoleNames;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StockAuditRetiredVariantsTest extends TestCase
{
    use RefreshDatabase;

    public function test_paused_or_discontinued_variants_with_no_stock_are_never_flagged_as_not_scanned(): void
    {
        $service = app(StockAuditService::class);
        $user = User::factory()->create();

        $counted = $this->variant('Counted Charger', 5);
        $activeUncounted = $this->variant('Shelf Battery', 5);
        $pausedEmpty = $this->variant('Paused Router', 0, ProductVariant::REPLENISHMENT_PAUSED);
        $discontinuedEmpty = $this->variant('Old Phone', 0, ProductVariant::REPLENISHMENT_DISCONTINUED);
        // Still has units on record, so the shelf should hold them.
        $discontinuedStocked = $this->variant('Clearance Laptop', 4, ProductVariant::REPLENISHMENT_DISCONTINUED);

        $session = $service->findOrCreateInProgressSession(startedBy: $user->id);
        $this->assertSame(3, (int) $session->total_expected_items);

        $summary = $service->storeAudit(
            counts: [['variant_id' => $counted->id, 'physical_quantity' => 5]],
            employeeId: $user->id,
            sessionId: $session->id,
            submitAnyway: true,
            source: 'mobile',
        );

        $this->assertSame(2, $summary['missing_count']);
        $this->assertTrue($this->hasUnscannedAlert($activeUncounted));
        $this->assertTrue($this->hasUnscannedAlert($discontinuedStocked));
        $this->assertFalse($this->hasUnscannedAlert($pausedEmpty));
        $this->assertFalse($this->hasUnscannedAlert($discontinuedEmpty));
    }

    public function test_found_units_of_a_discontinued_variant_are_recorded_without_inflating_coverage(): void
    {
        $service = app(StockAuditService::class);
        $user = User::factory()->create();

        $shelfItem = $this->variant('Shelf Battery', 5);
        $discontinued = $this->variant('Old Phone', 0, ProductVariant::REPLENISHMENT_DISCONTINUED);

        $session = $service->findOrCreateInProgressSession(startedBy: $user->id);

        $this->assertNotNull($service->findByBarcode($discontinued->barcode, $session));

        $service->upsertSessionItems($session, [['variant_id' => $shelfItem->id, 'physical_quantity' => 5]], 'mobile');
        $service->upsertSessionItems($session, [['variant_id' => $discontinued->id, 'physical_quantity' => 2]], 'mobile');

        $this->assertDatabaseHas('stock_audit_sessions', [
            'id' => $session->id,
            'total_expected_items' => 1,
            'total_scanned_items' => 1,
        ]);

        $summary = $service->storeAudit(
            counts: [['variant_id' => $shelfItem->id, 'physical_quantity' => 5]],
            employeeId: $user->id,
            sessionId: $session->id,
            source: 'mobile',
        );

        $this->assertSame(0, $summary['missing_count']);
        $this->assertSame(100.0, (float) $session->fresh()->coverage_percentage);
        $this->assertSame([2], collect($summary['discrepancies'])->pluck('variance')->all());
    }

    public function test_manual_audit_list_leaves_out_paused_or_discontinued_variants_with_no_stock(): void
    {
        $director = User::factory()->create();
        $director->syncRoles([RoleNames::DIRECTOR]);

        $active = $this->variant('Shelf Battery', 5);
        $this->variant('Paused Router', 0, ProductVariant::REPLENISHMENT_PAUSED);
        $clearance = $this->variant('Clearance Laptop', 4, ProductVariant::REPLENISHMENT_DISCONTINUED);

        $this->actingAs($director)
            ->get(route('admin.inventory.stock-audit.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('InventoryStockAudit')
                ->has('variants', 2)
                ->where('variants.0.id', $active->id)
                ->where('variants.1.id', $clearance->id)
            );
    }

    public function test_not_scanned_alerts_clear_once_a_variant_is_retired_with_nothing_left(): void
    {
        $outOfStock = $this->variant('Out Of Stock Router', 0);
        $outOfStockAlert = $this->unscannedAlert($outOfStock);

        $outOfStock->update(['replenishment_status' => ProductVariant::REPLENISHMENT_PAUSED]);

        $this->assertSame('resolved', $outOfStockAlert->fresh()->status);
        $this->assertStringContainsString('paused or discontinued', $outOfStockAlert->fresh()->resolved_reason);

        $sellingOff = $this->variant('Clearance Laptop', 2, ProductVariant::REPLENISHMENT_DISCONTINUED);
        $sellingOffAlert = $this->unscannedAlert($sellingOff);

        $sellingOff->update(['quantity' => 1]);
        $this->assertSame('open', $sellingOffAlert->fresh()->status);

        $sellingOff->update(['quantity' => 0]);
        $this->assertSame('resolved', $sellingOffAlert->fresh()->status);
    }

    public function test_scan_only_resolves_not_scanned_alerts_that_no_longer_apply(): void
    {
        $retiredEmpty = $this->unscannedAlert($this->variant('Old Phone', 0, ProductVariant::REPLENISHMENT_DISCONTINUED));
        $retiredStocked = $this->unscannedAlert($this->variant('Clearance Laptop', 4, ProductVariant::REPLENISHMENT_DISCONTINUED));
        $activeEmpty = $this->unscannedAlert($this->variant('Out Of Stock Router', 0));

        $resolved = app(InventoryAlertEngine::class)->resolveUnscannedAuditAlertsNotExpectedOnShelf();

        $this->assertSame(1, $resolved);
        $this->assertSame('resolved', $retiredEmpty->fresh()->status);
        $this->assertSame('open', $retiredStocked->fresh()->status);
        $this->assertSame('open', $activeEmpty->fresh()->status);
    }

    protected function variant(string $productName, int $quantity, string $replenishment = ProductVariant::REPLENISHMENT_REORDERABLE): ProductVariant
    {
        $product = Product::factory()->create(['name' => $productName]);

        return ProductVariant::factory()->create([
            'product_id' => $product->id,
            'quantity' => $quantity,
            'replenishment_status' => $replenishment,
        ]);
    }

    protected function unscannedAlert(ProductVariant $variant): InventoryAlert
    {
        return InventoryAlert::query()->create([
            'type' => 'discrepancy',
            'severity' => 'medium',
            'variant_id' => $variant->id,
            'message' => 'Item not found during audit',
            'status' => 'open',
            'meta' => ['audit_session_id' => 7, 'source' => 'mobile', 'unknown_state' => true, 'missing_item' => true],
            'first_detected_at' => now(),
        ]);
    }

    protected function hasUnscannedAlert(ProductVariant $variant): bool
    {
        return InventoryAlert::query()
            ->where('variant_id', $variant->id)
            ->where('meta->missing_item', true)
            ->exists();
    }
}
