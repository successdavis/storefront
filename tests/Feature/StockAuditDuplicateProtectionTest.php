<?php

namespace Tests\Feature;

use App\Domain\Inventory\Audit\StockAuditService;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockAdjustment;
use App\Models\StockAuditItem;
use App\Models\StockAuditItemLock;
use App\Models\StockAuditSession;
use App\Models\User;
use App\Services\StockAdjustmentApprovalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StockAuditDuplicateProtectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_variant_counted_in_another_category_session_is_locked_and_cannot_be_counted_twice(): void
    {
        $variant = $this->createVariantInCategories();
        $service = app(StockAuditService::class);
        $user = User::factory()->create();

        $sessionA = $service->findOrCreateInProgressSession(
            startedBy: $user->id,
            scopeType: StockAuditSession::SCOPE_CATEGORY,
            categoryId: $variant->product->categories[0]->id,
        );

        $service->upsertSessionItems($sessionA, [[
            'variant_id' => $variant->id,
            'physical_quantity' => 8,
        ]]);

        $sessionB = $service->findOrCreateInProgressSession(
            startedBy: $user->id,
            scopeType: StockAuditSession::SCOPE_CATEGORY,
            categoryId: $variant->product->categories[1]->id,
        );

        $rows = $service->sessionRows($sessionB);
        $lockedRow = $rows->firstWhere('id', $variant->id);

        $this->assertTrue((bool) $lockedRow['locked_by_other_session']);
        $this->assertSame($sessionA->id, $lockedRow['locked_session_id']);

        $this->expectException(ValidationException::class);

        try {
            $service->upsertSessionItems($sessionB, [[
                'variant_id' => $variant->id,
                'physical_quantity' => 6,
            ]]);
        } finally {
            $this->assertDatabaseCount('stock_audit_items', 1);
        }
    }

    public function test_submitting_overlapping_category_audits_does_not_create_duplicate_pending_adjustments(): void
    {
        $variant = $this->createVariantInCategories();
        $service = app(StockAuditService::class);
        $user = User::factory()->create();

        $sessionA = $service->findOrCreateInProgressSession(
            startedBy: $user->id,
            scopeType: StockAuditSession::SCOPE_CATEGORY,
            categoryId: $variant->product->categories[0]->id,
        );

        $service->storeAudit(
            counts: [[
                'variant_id' => $variant->id,
                'physical_quantity' => 8,
            ]],
            employeeId: $user->id,
            sessionId: $sessionA->id,
            scopeType: StockAuditSession::SCOPE_CATEGORY,
            categoryId: $variant->product->categories[0]->id,
        );

        $this->assertDatabaseCount('stock_adjustments', 1);

        $sessionB = $service->findOrCreateInProgressSession(
            startedBy: $user->id,
            scopeType: StockAuditSession::SCOPE_CATEGORY,
            categoryId: $variant->product->categories[1]->id,
        );

        try {
            $service->storeAudit(
                counts: [[
                    'variant_id' => $variant->id,
                    'physical_quantity' => 6,
                ]],
                employeeId: $user->id,
                sessionId: $sessionB->id,
                scopeType: StockAuditSession::SCOPE_CATEGORY,
                categoryId: $variant->product->categories[1]->id,
            );

            $this->fail('Expected overlapping audit submission to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('already counted in session', collect($exception->errors())->flatten()->first());
        }

        $this->assertDatabaseCount('stock_adjustments', 1);
    }

    public function test_legacy_duplicate_pending_adjustment_cannot_be_approved_twice(): void
    {
        $variant = $this->createVariantInCategories(quantity: 10);
        $approver = User::factory()->create();
        $approvalService = app(StockAdjustmentApprovalService::class);

        $sessionA = StockAuditSession::query()->create([
            'scope_type' => StockAuditSession::SCOPE_CATEGORY,
            'category_id' => $variant->product->categories[0]->id,
            'status' => StockAuditSession::STATUS_SUBMITTED,
            'total_expected_items' => 1,
            'total_scanned_items' => 1,
            'coverage_percentage' => 100,
            'started_at' => now(),
            'submitted_at' => now(),
            'last_activity_at' => now(),
        ]);

        $sessionB = StockAuditSession::query()->create([
            'scope_type' => StockAuditSession::SCOPE_CATEGORY,
            'category_id' => $variant->product->categories[1]->id,
            'status' => StockAuditSession::STATUS_SUBMITTED,
            'total_expected_items' => 1,
            'total_scanned_items' => 1,
            'coverage_percentage' => 100,
            'started_at' => now(),
            'submitted_at' => now(),
            'last_activity_at' => now(),
        ]);

        $adjustmentA = StockAdjustment::query()->create([
            'variant_id' => $variant->id,
            'previous_quantity' => 10,
            'adjusted_quantity' => -2,
            'reason' => 'count_discrepancy',
            'employee_id' => $approver->id,
            'reference' => sprintf('AUDIT-%d-%d', $sessionA->id, $variant->id),
            'adjusted_at' => now(),
            'status' => StockAdjustment::STATUS_PENDING,
        ]);

        $adjustmentB = StockAdjustment::query()->create([
            'variant_id' => $variant->id,
            'previous_quantity' => 10,
            'adjusted_quantity' => -2,
            'reason' => 'count_discrepancy',
            'employee_id' => $approver->id,
            'reference' => sprintf('AUDIT-%d-%d', $sessionB->id, $variant->id),
            'adjusted_at' => now(),
            'status' => StockAdjustment::STATUS_PENDING,
        ]);

        $itemA = StockAuditItem::query()->create([
            'session_id' => $sessionA->id,
            'variant_id' => $variant->id,
            'system_quantity' => 10,
            'physical_quantity' => 8,
            'variance' => -2,
            'stock_adjustment_id' => $adjustmentA->id,
        ]);

        $itemB = StockAuditItem::query()->create([
            'session_id' => $sessionB->id,
            'variant_id' => $variant->id,
            'system_quantity' => 10,
            'physical_quantity' => 8,
            'variance' => -2,
            'stock_adjustment_id' => $adjustmentB->id,
            'conflict_reason' => sprintf('This item overlaps with audit session #%d for the same warehouse scope.', $sessionA->id),
            'conflicted_with_session_id' => $sessionA->id,
        ]);

        StockAuditItemLock::query()->create([
            'session_id' => $sessionA->id,
            'variant_id' => $variant->id,
            'warehouse_id' => null,
            'warehouse_scope_key' => 0,
        ]);

        $approvalService->approve($adjustmentA, $approver->id);

        $this->assertDatabaseCount('stock_entries', 1);

        try {
            $approvalService->approve($adjustmentB, $approver->id);
            $this->fail('Expected the duplicate pending adjustment to be rejected during approval.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('overlaps with audit session', collect($exception->errors())->flatten()->first());
        }

        $this->assertDatabaseCount('stock_entries', 1);
        $this->assertDatabaseHas('stock_adjustments', [
            'id' => $adjustmentA->id,
            'status' => StockAdjustment::STATUS_APPROVED,
        ]);
        $this->assertDatabaseHas('stock_adjustments', [
            'id' => $adjustmentB->id,
            'status' => StockAdjustment::STATUS_PENDING,
        ]);
    }

    public function test_approving_the_same_adjustment_twice_is_idempotent(): void
    {
        $variant = $this->createVariantInCategories(quantity: 10);
        $service = app(StockAuditService::class);
        $approvalService = app(StockAdjustmentApprovalService::class);
        $user = User::factory()->create();

        $session = $service->findOrCreateInProgressSession(
            startedBy: $user->id,
            scopeType: StockAuditSession::SCOPE_CATEGORY,
            categoryId: $variant->product->categories[0]->id,
        );

        $service->storeAudit(
            counts: [[
                'variant_id' => $variant->id,
                'physical_quantity' => 8,
            ]],
            employeeId: $user->id,
            sessionId: $session->id,
            scopeType: StockAuditSession::SCOPE_CATEGORY,
            categoryId: $variant->product->categories[0]->id,
        );

        $adjustment = StockAdjustment::query()->firstOrFail();

        $approvalService->approve($adjustment, $user->id);

        try {
            $approvalService->approve($adjustment->fresh(), $user->id);
            $this->fail('Expected second approval attempt to fail.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('Only pending adjustments can be approved', collect($exception->errors())->flatten()->first());
        }

        $this->assertDatabaseCount('stock_entries', 1);
        $this->assertSame(8, (int) $variant->fresh()->quantity);
    }

    protected function createVariantInCategories(int $quantity = 10): ProductVariant
    {
        $brand = Brand::factory()->create();
        $categories = Category::factory()->count(2)->create();

        $product = Product::factory()->create([
            'brand_id' => $brand->id,
            'name' => 'Laptop Charger',
        ]);

        $product->categories()->attach($categories->pluck('id'));

        return ProductVariant::factory()->create([
            'product_id' => $product->id,
            'quantity' => $quantity,
            'average_cost' => 100,
            'last_purchase_price' => 100,
            'regular_price' => 250,
        ]);
    }
}
