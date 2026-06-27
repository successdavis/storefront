<?php

namespace Tests\Feature;

use App\Domain\Inventory\Audit\StockAuditService;
use App\Models\InventoryAlert;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockAuditSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockAuditPartialSubmissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_submit_anyway_creates_missing_item_alerts_for_unscanned_variants(): void
    {
        $service = app(StockAuditService::class);
        $user = User::factory()->create();

        $scannedVariant = $this->createVariant('Scanned Item', 5);
        $firstMissingVariant = $this->createVariant('First Missing Item', 7);
        $secondMissingVariant = $this->createVariant('Second Missing Item', 9);

        $session = $service->findOrCreateInProgressSession(
            startedBy: $user->id,
            scopeType: StockAuditSession::SCOPE_FULL,
        );

        $summary = $service->storeAudit(
            counts: [[
                'variant_id' => $scannedVariant->id,
                'physical_quantity' => 5,
            ]],
            employeeId: $user->id,
            sessionId: $session->id,
            scopeType: StockAuditSession::SCOPE_FULL,
            submitAnyway: true,
            source: 'mobile',
        );

        $this->assertSame(2, $summary['missing_count']);
        $this->assertSame(2, $summary['alerts_raised']);
        $this->assertDatabaseCount('stock_adjustments', 0);

        $this->assertDatabaseHas('stock_audit_sessions', [
            'id' => $session->id,
            'source' => 'mobile',
            'total_expected_items' => 3,
            'total_scanned_items' => 1,
            'is_partial' => true,
        ]);

        $alerts = InventoryAlert::query()
            ->whereIn('variant_id', [$firstMissingVariant->id, $secondMissingVariant->id])
            ->get()
            ->keyBy('variant_id');

        $this->assertCount(2, $alerts);

        foreach ([$firstMissingVariant, $secondMissingVariant] as $variant) {
            $alert = $alerts->get($variant->id);

            $this->assertNotNull($alert);
            $this->assertSame('discrepancy', $alert->type);
            $this->assertSame('medium', $alert->severity);
            $this->assertSame('open', $alert->status);
            $this->assertStringStartsWith('Item not found during audit:', $alert->message);
            $this->assertSame($session->id, (int) data_get($alert->meta, 'audit_session_id'));
            $this->assertSame('mobile', data_get($alert->meta, 'source'));
            $this->assertTrue((bool) data_get($alert->meta, 'unknown_state'));
            $this->assertTrue((bool) data_get($alert->meta, 'missing_item'));
        }
    }

    protected function createVariant(string $productName, int $quantity): ProductVariant
    {
        $product = Product::factory()->create([
            'name' => $productName,
        ]);

        return ProductVariant::factory()
            ->for($product)
            ->create([
                'quantity' => $quantity,
            ]);
    }
}
