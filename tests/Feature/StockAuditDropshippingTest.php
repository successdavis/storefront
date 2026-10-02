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

class StockAuditDropshippingTest extends TestCase
{
    use RefreshDatabase;

    public function test_submitting_an_audit_never_flags_dropshipping_variants_as_not_scanned(): void
    {
        $service = app(StockAuditService::class);
        $user = User::factory()->create();

        $counted = $this->variant('Counted Charger');
        $uncountedStocked = $this->variant('Shelf Battery');
        $dropshipped = $this->variant('Starlink Kit', ProductVariant::FULFILLMENT_DROPSHIPPING);

        $session = $service->findOrCreateInProgressSession(
            startedBy: $user->id,
            scopeType: StockAuditSession::SCOPE_FULL,
        );

        $summary = $service->storeAudit(
            counts: [['variant_id' => $counted->id, 'physical_quantity' => 4]],
            employeeId: $user->id,
            sessionId: $session->id,
            scopeType: StockAuditSession::SCOPE_FULL,
            submitAnyway: true,
            source: 'mobile',
        );

        $this->assertSame(1, $summary['missing_count']);
        $this->assertDatabaseHas('stock_audit_sessions', [
            'id' => $session->id,
            'total_expected_items' => 2,
            'total_scanned_items' => 1,
        ]);
        $this->assertTrue(InventoryAlert::query()->where('variant_id', $uncountedStocked->id)->exists());
        $this->assertFalse(InventoryAlert::query()->where('variant_id', $dropshipped->id)->exists());
    }

    public function test_dropshipping_variants_are_left_out_of_audit_lists_and_counts(): void
    {
        $director = User::factory()->create();
        $director->syncRoles([RoleNames::DIRECTOR]);

        $stocked = $this->variant('Shelf Battery');
        $dropshipped = $this->variant('Starlink Kit', ProductVariant::FULFILLMENT_DROPSHIPPING);

        $this->actingAs($director)
            ->get(route('admin.inventory.stock-audit.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('InventoryStockAudit')
                ->has('variants', 1)
                ->where('variants.0.id', $stocked->id)
                ->where('session.total_expected_items', 1)
            );

        $session = StockAuditSession::query()->latest('id')->firstOrFail();

        $this->actingAs($director)
            ->getJson(route('admin.inventory.stock-audit.lookup', [
                'barcode' => $dropshipped->barcode,
                'session_id' => $session->id,
            ]))
            ->assertNotFound()
            ->assertJsonPath('reason', 'dropshipping');

        $this->actingAs($director)
            ->postJson(route('admin.inventory.stock-audit.items.upsert'), [
                'session_id' => $session->id,
                'variant_id' => $dropshipped->id,
                'physical_quantity' => 1,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('counts');
    }

    public function test_scan_resolves_only_unscanned_audit_alerts_on_dropshipping_variants(): void
    {
        $dropshipped = $this->variant('Starlink Kit', ProductVariant::FULFILLMENT_DROPSHIPPING);
        $stocked = $this->variant('Shelf Battery');

        $dropshippedUnscanned = $this->unscannedAlert($dropshipped);
        $stockedUnscanned = $this->unscannedAlert($stocked);
        // A person recorded a real count here, so it stays for review.
        $dropshippedCounted = InventoryAlert::query()->create([
            'type' => 'discrepancy',
            'severity' => 'medium',
            'variant_id' => $dropshipped->id,
            'message' => 'Stock mismatch detected',
            'status' => 'open',
            'meta' => ['audit_session_id' => 7, 'system_quantity' => 0, 'physical_quantity' => 2, 'variance' => 2],
            'first_detected_at' => now(),
        ]);

        $resolved = app(InventoryAlertEngine::class)->resolveUnscannedAuditAlertsForDropshippingVariants();

        $this->assertSame(1, $resolved);
        $this->assertSame('resolved', $dropshippedUnscanned->fresh()->status);
        $this->assertStringContainsString('dropshipping', $dropshippedUnscanned->fresh()->resolved_reason);
        $this->assertSame('open', $stockedUnscanned->fresh()->status);
        $this->assertSame('open', $dropshippedCounted->fresh()->status);
    }

    public function test_switching_a_variant_to_dropshipping_resolves_its_unscanned_audit_alert(): void
    {
        $variant = $this->variant('Solar Battery');
        $alert = $this->unscannedAlert($variant);

        $variant->update(['quantity' => 3]);
        $this->assertSame('open', $alert->fresh()->status);

        $variant->update(['fulfillment_type' => ProductVariant::FULFILLMENT_DROPSHIPPING]);

        $this->assertSame('resolved', $alert->fresh()->status);
    }

    protected function variant(string $productName, string $fulfillment = ProductVariant::FULFILLMENT_STOCKED): ProductVariant
    {
        $product = Product::factory()->create(['name' => $productName]);

        return ProductVariant::factory()->create([
            'product_id' => $product->id,
            'quantity' => $fulfillment === ProductVariant::FULFILLMENT_DROPSHIPPING ? 0 : 5,
            'fulfillment_type' => $fulfillment,
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
}
