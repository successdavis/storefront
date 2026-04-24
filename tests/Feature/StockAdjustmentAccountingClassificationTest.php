<?php

namespace Tests\Feature;

use App\Enums\StockAdjustmentType;
use App\Models\Accounting\JournalEntry;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockAdjustment;
use App\Models\User;
use App\Services\Accounting\FinancialStatementService;
use App\Services\StockAdjustmentApprovalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StockAdjustmentAccountingClassificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_negative_loss_adjustment_posts_inventory_adjustment_loss(): void
    {
        $user = User::factory()->create();
        $variant = $this->makeVariant([
            'quantity' => 10,
            'reserved' => 0,
            'average_cost' => 100,
            'last_purchase_price' => 100,
            'total_cost_on_hand' => 1000,
        ]);

        $adjustment = app(StockAdjustmentApprovalService::class)->submit([
            'variant_id' => $variant->id,
            'adjusted_quantity' => -2,
            'adjustment_type' => StockAdjustmentType::LOSS->value,
            'reason' => 'loss',
            'adjusted_at' => '2026-04-05 10:00:00',
        ], $user->id);

        app(StockAdjustmentApprovalService::class)->approve($adjustment, $user->id);

        $entry = JournalEntry::query()
            ->where('event_key', "stock_adjustment:{$adjustment->id}:posted")
            ->with('lines.account')
            ->firstOrFail();

        $this->assertSame(200.0, (float) $entry->total_debit);
        $this->assertSame(['1310', '5510'], $entry->lines->pluck('account.code')->sort()->values()->all());
        $this->assertDatabaseHas('journal_entry_lines', [
            'journal_entry_id' => $entry->id,
            'account_id' => $entry->lines->firstWhere('account.code', '5510')->account_id,
            'debit' => 200.0000,
            'credit' => 0.0000,
        ]);
    }

    public function test_negative_correction_adjustment_posts_to_correction_reserve_not_loss(): void
    {
        $user = User::factory()->create();
        $variant = $this->makeVariant([
            'quantity' => 11,
            'reserved' => 0,
            'average_cost' => 250,
            'last_purchase_price' => 250,
            'total_cost_on_hand' => 2750,
        ]);

        $adjustment = app(StockAdjustmentApprovalService::class)->submit([
            'variant_id' => $variant->id,
            'adjusted_quantity' => -1,
            'adjustment_type' => StockAdjustmentType::CORRECTION->value,
            'reason' => 'manual_correction',
            'adjusted_at' => '2026-04-05 10:00:00',
        ], $user->id);

        app(StockAdjustmentApprovalService::class)->approve($adjustment, $user->id);

        $entry = JournalEntry::query()
            ->where('event_key', "stock_adjustment:{$adjustment->id}:posted")
            ->with('lines.account')
            ->firstOrFail();

        $this->assertSame(['1310', '3120'], $entry->lines->pluck('account.code')->sort()->values()->all());
        $this->assertFalse($entry->lines->contains(fn ($line) => $line->account->code === '5510'));
    }

    public function test_positive_correction_adjustment_increases_inventory_without_hitting_gain(): void
    {
        $user = User::factory()->create();
        $variant = $this->makeVariant([
            'quantity' => 4,
            'reserved' => 0,
            'average_cost' => 90,
            'last_purchase_price' => 90,
            'total_cost_on_hand' => 360,
        ]);

        $adjustment = app(StockAdjustmentApprovalService::class)->submit([
            'variant_id' => $variant->id,
            'adjusted_quantity' => 2,
            'adjustment_type' => StockAdjustmentType::CORRECTION->value,
            'reason' => 'manual_correction',
            'adjusted_at' => '2026-04-05 10:00:00',
        ], $user->id);

        app(StockAdjustmentApprovalService::class)->approve($adjustment, $user->id);

        $entry = JournalEntry::query()
            ->where('event_key', "stock_adjustment:{$adjustment->id}:posted")
            ->with('lines.account')
            ->firstOrFail();

        $this->assertSame(['1310', '3120'], $entry->lines->pluck('account.code')->sort()->values()->all());
        $this->assertFalse($entry->lines->contains(fn ($line) => $line->account->code === '4160'));
    }

    public function test_invalid_positive_loss_adjustment_is_rejected(): void
    {
        $user = User::factory()->create();
        $variant = $this->makeVariant([
            'quantity' => 4,
            'reserved' => 0,
            'average_cost' => 90,
            'last_purchase_price' => 90,
            'total_cost_on_hand' => 360,
        ]);

        try {
            app(StockAdjustmentApprovalService::class)->submit([
                'variant_id' => $variant->id,
                'adjusted_quantity' => 2,
                'adjustment_type' => StockAdjustmentType::LOSS->value,
                'reason' => 'loss',
                'adjusted_at' => '2026-04-05 10:00:00',
            ], $user->id);

            $this->fail('Expected positive loss adjustment to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('Loss adjustments must reduce stock.', collect($exception->errors())->flatten()->first());
        }
    }

    public function test_profit_and_loss_excludes_correction_adjustments_from_expenses(): void
    {
        $user = User::factory()->create();
        $variant = $this->makeVariant([
            'quantity' => 10,
            'reserved' => 0,
            'average_cost' => 100,
            'last_purchase_price' => 100,
            'total_cost_on_hand' => 1000,
        ]);

        $lossAdjustment = app(StockAdjustmentApprovalService::class)->submit([
            'variant_id' => $variant->id,
            'adjusted_quantity' => -2,
            'adjustment_type' => StockAdjustmentType::LOSS->value,
            'reason' => 'damage',
            'adjusted_at' => '2026-04-06 10:00:00',
        ], $user->id);

        app(StockAdjustmentApprovalService::class)->approve($lossAdjustment, $user->id);

        $correctionAdjustment = app(StockAdjustmentApprovalService::class)->submit([
            'variant_id' => $variant->id,
            'adjusted_quantity' => -1,
            'adjustment_type' => StockAdjustmentType::CORRECTION->value,
            'reason' => 'manual_correction',
            'adjusted_at' => '2026-04-07 10:00:00',
        ], $user->id);

        app(StockAdjustmentApprovalService::class)->approve($correctionAdjustment, $user->id);

        $report = app(FinancialStatementService::class)->incomeStatement([
            'from' => '2026-04-01',
            'to' => '2026-04-30',
        ]);

        $lossRow = collect($report['expenses'])->firstWhere('code', '5510');

        $this->assertNotNull($lossRow);
        $this->assertSame(200.0, (float) $lossRow['amount']);
    }

    protected function makeVariant(array $overrides = []): ProductVariant
    {
        $product = Product::factory()->create();

        return ProductVariant::factory()->create(array_merge([
            'product_id' => $product->id,
        ], $overrides));
    }
}
