<?php

namespace App\Services\Accounting;

use App\Enums\StockAdjustmentType;
use App\Models\Accounting\Expense;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\PaymentGatewaySettlement;
use App\Models\ItemReceipt;
use App\Models\OpeningBalance;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\StockAdjustment;
use App\Models\StockEntry;
use App\Models\VendorBill;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class AccountingService
{
    public function __construct(
        protected JournalPostingService $journalPostingService,
        protected SystemAccountResolver $accountResolver,
    ) {}

    public function postOrder(Order $order, ?string $paymentMethod = null, ?int $postedBy = null): JournalEntry
    {
        $order->loadMissing('items');

        $receiptAccount = $this->resolveOrderReceiptAccount($order, $paymentMethod);
        $salesAccount = $this->accountResolver->resolve('product_sales_revenue');
        $shippingRevenueAccount = $this->accountResolver->resolve('shipping_revenue');
        $discountAccount = $this->accountResolver->resolve('sales_discount_contra');
        $taxPayableAccount = $this->accountResolver->resolve('tax_payable');
        $cogsAccount = $this->accountResolver->resolve('cost_of_goods_sold');
        $inventoryAccount = $this->accountResolver->resolve('inventory_asset');

        $builder = JournalBuilder::make()
            ->debit($receiptAccount, (float) $order->total_amount, 'Order receipt')
            ->credit($salesAccount, (float) $order->subtotal, 'Product sales revenue');

        if ((float) $order->discount > 0) {
            $builder->debit($discountAccount, (float) $order->discount, 'Sales discount');
        }

        if ((float) $order->shipping_total > 0) {
            $builder->credit($shippingRevenueAccount, (float) $order->shipping_total, 'Shipping revenue');
        }

        if ((float) $order->tax_total > 0) {
            $builder->credit($taxPayableAccount, (float) $order->tax_total, 'Tax payable');
        }

        $cogsAmount = $this->orderCostAmount($order);
        if ($cogsAmount > 0) {
            $builder
                ->debit($cogsAccount, $cogsAmount, 'Cost of goods sold')
                ->credit($inventoryAccount, $cogsAmount, 'Inventory asset reduction');
        }

        return $this->journalPostingService->post([
            'event_key' => "order:{$order->id}:sale_posted",
            'source_event' => 'order_sale_posted',
            'entry_date' => optional($order->created_at)?->toDateString() ?? now()->toDateString(),
            'posting_date' => optional($order->created_at)?->toDateString() ?? now()->toDateString(),
            'description' => "Sales posting for order {$order->order_number}",
            'source' => $order,
            'status' => JournalEntry::STATUS_POSTED,
            'currency' => $order->currency ?: config('accounting.currency', 'NGN'),
            'posted_by' => $postedBy,
            'meta' => [
                'channel' => $order->channel,
                'payment_method' => $paymentMethod,
                'subtotal' => (float) $order->subtotal,
                'discount' => (float) $order->discount,
                'shipping_total' => (float) $order->shipping_total,
                'tax_total' => (float) $order->tax_total,
                'cogs_amount' => $cogsAmount,
            ],
        ], $builder->lines());
    }

    public function postSale(Sale $sale, ?string $paymentMethod = null, ?int $postedBy = null): ?JournalEntry
    {
        if ($sale->order_id) {
            return null;
        }

        $sale->loadMissing('items', 'payments');

        $totalAmount = round((float) $sale->total_amount, 4);
        if ($totalAmount <= 0) {
            return null;
        }

        $lineRevenue = round((float) $sale->items->sum(fn ($item) => (float) $item->quantity * (float) $item->price), 4);
        $discountAmount = round(max(0, $lineRevenue - $totalAmount), 4);
        $receiptAccount = $this->resolvePaymentMethodAccount(
            $paymentMethod
            ?: (string) ($sale->payments->firstWhere('status', 'paid')->method ?? $sale->payment_method ?? 'cash')
        );
        $salesAccount = $this->accountResolver->resolve('product_sales_revenue');
        $discountAccount = $this->accountResolver->resolve('sales_discount_contra');
        $cogsAccount = $this->accountResolver->resolve('cost_of_goods_sold');
        $inventoryAccount = $this->accountResolver->resolve('inventory_asset');

        $builder = JournalBuilder::make()
            ->debit($receiptAccount, $totalAmount, 'POS sale receipt')
            ->credit($salesAccount, $lineRevenue, 'Product sales revenue');

        if ($discountAmount > 0) {
            $builder->debit($discountAccount, $discountAmount, 'Sales discount');
        }

        $cogsAmount = $this->saleCostAmount($sale);
        if ($cogsAmount > 0) {
            $builder
                ->debit($cogsAccount, $cogsAmount, 'Cost of goods sold')
                ->credit($inventoryAccount, $cogsAmount, 'Inventory asset reduction');
        }

        return $this->journalPostingService->post([
            'event_key' => "sale:{$sale->id}:sale_posted",
            'source_event' => 'sale_posted',
            'entry_date' => optional($sale->created_at)?->toDateString() ?? now()->toDateString(),
            'posting_date' => optional($sale->created_at)?->toDateString() ?? now()->toDateString(),
            'description' => "POS sale #{$sale->id}",
            'source' => $sale,
            'status' => JournalEntry::STATUS_POSTED,
            'currency' => config('accounting.currency', 'NGN'),
            'posted_by' => $postedBy,
            'meta' => [
                'payment_method' => $paymentMethod ?: $sale->payment_method,
                'line_revenue' => $lineRevenue,
                'discount' => $discountAmount,
                'cogs_amount' => $cogsAmount,
            ],
        ], $builder->lines());
    }

    public function postInventoryReceipt(ItemReceipt $receipt, ?int $postedBy = null): ?JournalEntry
    {
        $total = round((float) $receipt->items()->sum(DB::raw('quantity_received * unit_cost')), 4);
        if ($total <= 0) {
            return null;
        }

        $inventoryAsset = $this->accountResolver->resolve('inventory_asset');
        $grni = $this->accountResolver->resolve('goods_received_not_invoiced');

        return $this->journalPostingService->post([
            'event_key' => "item_receipt:{$receipt->id}:inventory_recognized",
            'source_event' => 'inventory_receipt_posted',
            'entry_date' => optional($receipt->received_date)?->toDateString() ?? now()->toDateString(),
            'posting_date' => optional($receipt->received_date)?->toDateString() ?? now()->toDateString(),
            'description' => "Inventory receipt {$receipt->receipt_number}",
            'source' => $receipt,
            'posted_by' => $postedBy,
            'meta' => [
                'warehouse_id' => $receipt->warehouse_id,
            ],
        ], JournalBuilder::make()
            ->debit($inventoryAsset, $total, 'Inventory received')
            ->credit($grni, $total, 'Goods received not invoiced')
            ->lines());
    }

    public function postVendorBill(VendorBill $bill, ?int $postedBy = null): ?JournalEntry
    {
        $bill->loadMissing('items');

        $productTotal = round((float) $bill->items->where('type', 'product')->sum(fn ($item) => ((float) $item->quantity * (float) $item->unit_cost) - (float) ($item->discount_amount ?? 0)), 4);
        $miscTotal = round((float) $bill->items->where('type', '!=', 'product')->sum(fn ($item) => ((float) $item->quantity * (float) $item->unit_cost) - (float) ($item->discount_amount ?? 0)), 4);
        $total = round($productTotal + $miscTotal, 4);

        if ($total <= 0) {
            return null;
        }

        $grni = $this->accountResolver->resolve('goods_received_not_invoiced');
        $accountsPayable = $this->accountResolver->resolve('accounts_payable');
        $operatingExpense = $this->accountResolver->resolve('operating_expense');

        $builder = JournalBuilder::make();

        if ($productTotal > 0) {
            $builder->debit($grni, $productTotal, 'Clear received inventory accrual');
        }

        if ($miscTotal > 0) {
            $builder->debit($operatingExpense, $miscTotal, 'Non-inventory billable expense');
        }

        $builder->credit($accountsPayable, $total, 'Vendor bill recognized');

        return $this->journalPostingService->post([
            'event_key' => "vendor_bill:{$bill->id}:recognized",
            'source_event' => 'vendor_bill_posted',
            'entry_date' => optional($bill->bill_date)?->toDateString() ?? now()->toDateString(),
            'posting_date' => optional($bill->bill_date)?->toDateString() ?? now()->toDateString(),
            'description' => "Vendor bill {$bill->bill_number}",
            'source' => $bill,
            'posted_by' => $postedBy,
            'meta' => [
                'vendor_id' => $bill->vendor_id,
                'purchase_order_id' => $bill->purchase_order_id,
                'product_total' => $productTotal,
                'misc_total' => $miscTotal,
            ],
        ], $builder->lines());
    }

    public function postVendorBillPayment(Payment $payment, ?int $postedBy = null): ?JournalEntry
    {
        if ($payment->payable_type !== VendorBill::class || (float) $payment->amount <= 0) {
            return null;
        }

        $accountsPayable = $this->accountResolver->resolve('accounts_payable');
        $paymentAccount = $this->resolvePaymentMethodAccount((string) $payment->method);

        return $this->journalPostingService->post([
            'event_key' => "payment:{$payment->id}:vendor_bill_payment",
            'source_event' => 'vendor_bill_payment_posted',
            'entry_date' => optional($payment->paid_at)?->toDateString() ?? optional($payment->created_at)?->toDateString() ?? now()->toDateString(),
            'posting_date' => optional($payment->paid_at)?->toDateString() ?? optional($payment->created_at)?->toDateString() ?? now()->toDateString(),
            'description' => "Vendor bill payment #{$payment->id}",
            'source' => $payment,
            'posted_by' => $postedBy,
            'meta' => [
                'payable_type' => $payment->payable_type,
                'payable_id' => $payment->payable_id,
                'method' => $payment->method,
            ],
        ], JournalBuilder::make()
            ->debit($accountsPayable, (float) $payment->amount, 'Vendor bill settlement')
            ->credit($paymentAccount, (float) $payment->amount, 'Cash / bank reduction')
            ->lines());
    }

    public function postExpense(Expense $expense, ?int $postedBy = null): JournalEntry
    {
        $expense->loadMissing('expenseAccount', 'paymentAccount');

        $entry = $this->journalPostingService->post([
            'event_key' => "expense:{$expense->id}:posted",
            'source_event' => 'expense_posted',
            'entry_date' => optional($expense->expense_date)?->toDateString() ?? now()->toDateString(),
            'posting_date' => optional($expense->expense_date)?->toDateString() ?? now()->toDateString(),
            'description' => $expense->description,
            'source' => $expense,
            'posted_by' => $postedBy,
            'currency' => $expense->currency,
            'meta' => [
                'reference' => $expense->reference,
            ],
        ], JournalBuilder::make()
            ->debit($expense->expenseAccount, (float) $expense->amount, 'Expense recognition')
            ->credit($expense->paymentAccount, (float) $expense->amount, 'Expense payment')
            ->lines());

        if (!$expense->journal_entry_id) {
            $expense->forceFill(['journal_entry_id' => $entry->id])->save();
        }

        return $entry;
    }

    public function postOpeningBalance(OpeningBalance $openingBalance, ?int $postedBy = null): ?JournalEntry
    {
        $openingBalance->loadMissing('items');

        $total = round((float) $openingBalance->items->sum(fn ($item) => (float) $item->quantity * (float) ($item->unit_cost ?? 0)), 4);
        if ($total <= 0) {
            return null;
        }

        $inventoryAsset = $this->accountResolver->resolve('inventory_asset');
        $openingBalanceEquity = $this->accountResolver->resolve('opening_balance_equity');

        return $this->journalPostingService->post([
            'event_key' => "opening_balance:{$openingBalance->id}:posted",
            'source_event' => 'opening_balance_posted',
            'entry_date' => optional($openingBalance->effective_at)?->toDateString() ?? now()->toDateString(),
            'posting_date' => optional($openingBalance->effective_at)?->toDateString() ?? now()->toDateString(),
            'description' => $openingBalance->reference
                ? "Opening balance {$openingBalance->reference}"
                : "Opening balance #{$openingBalance->id}",
            'source' => $openingBalance,
            'status' => JournalEntry::STATUS_POSTED,
            'currency' => config('accounting.currency', 'NGN'),
            'posted_by' => $postedBy ?: $openingBalance->employee_id,
            'meta' => [
                'warehouse_id' => $openingBalance->warehouse_id,
                'vendor_id' => $openingBalance->vendor_id,
                'item_count' => (int) $openingBalance->items->count(),
            ],
        ], JournalBuilder::make()
            ->debit($inventoryAsset, $total, 'Opening inventory asset')
            ->credit($openingBalanceEquity, $total, 'Opening balance equity')
            ->lines());
    }

    public function postStockAdjustment(StockAdjustment $adjustment, ?int $postedBy = null): ?JournalEntry
    {
        $stockValue = round((float) StockEntry::query()
            ->where('source_type', StockAdjustment::class)
            ->where('source_id', $adjustment->id)
            ->selectRaw('COALESCE(SUM(quantity * unit_cost), 0) as total')
            ->value('total'), 4);

        if ($stockValue <= 0) {
            return null;
        }

        $inventory = $this->accountResolver->resolve('inventory_asset');
        $loss = $this->accountResolver->resolve('inventory_adjustment_loss');
        $gain = $this->accountResolver->resolve('inventory_adjustment_gain');
        $correctionReserve = $this->accountResolver->resolve('inventory_correction_reserve');
        $adjustmentType = $adjustment->adjustment_type instanceof StockAdjustmentType
            ? $adjustment->adjustment_type
            : StockAdjustmentType::tryFrom((string) $adjustment->adjustment_type) ?? $this->legacyStockAdjustmentType($adjustment);

        $builder = JournalBuilder::make();

        match ($adjustmentType) {
            StockAdjustmentType::GAIN => $builder
                ->debit($inventory, $stockValue, 'Inventory adjustment increase')
                ->credit($gain, $stockValue, 'Inventory adjustment gain'),
            StockAdjustmentType::LOSS => $builder
                ->debit($loss, $stockValue, 'Inventory adjustment loss')
                ->credit($inventory, $stockValue, 'Inventory adjustment decrease'),
            StockAdjustmentType::CORRECTION => ((int) $adjustment->adjusted_quantity > 0)
                ? $builder
                    ->debit($inventory, $stockValue, 'Inventory correction increase')
                    ->credit($correctionReserve, $stockValue, 'Inventory correction reserve')
                : $builder
                    ->debit($correctionReserve, $stockValue, 'Inventory correction reserve')
                    ->credit($inventory, $stockValue, 'Inventory correction decrease'),
        };

        return $this->journalPostingService->post([
            'event_key' => "stock_adjustment:{$adjustment->id}:posted",
            'source_event' => 'stock_adjustment_posted',
            'entry_date' => optional($adjustment->approved_at)?->toDateString() ?? now()->toDateString(),
            'posting_date' => optional($adjustment->approved_at)?->toDateString() ?? now()->toDateString(),
            'description' => "Stock adjustment #{$adjustment->id}",
            'source' => $adjustment,
            'posted_by' => $postedBy,
            'meta' => [
                'reason' => $adjustment->reason,
                'quantity_delta' => (int) $adjustment->adjusted_quantity,
                'adjustment_type' => $adjustmentType->value,
                'warehouse_id' => $adjustment->warehouse_id,
            ],
        ], $builder->lines());
    }

    public function postWalletFunding(Model $source, float $amount, ?int $postedBy = null): JournalEntry
    {
        $cash = $this->accountResolver->resolve('main_bank_account');
        $walletLiability = $this->accountResolver->resolve('customer_wallet_liability');

        return $this->journalPostingService->post([
            'event_key' => class_basename($source).":{$source->getKey()}:wallet_funding",
            'source_event' => 'wallet_funding_posted',
            'entry_date' => now()->toDateString(),
            'posting_date' => now()->toDateString(),
            'description' => 'Customer wallet funding',
            'source' => $source,
            'posted_by' => $postedBy,
        ], JournalBuilder::make()
            ->debit($cash, $amount, 'Wallet funding cash receipt')
            ->credit($walletLiability, $amount, 'Wallet liability increase')
            ->lines());
    }

    public function postGatewaySettlement(PaymentGatewaySettlement $settlement, ?int $postedBy = null): JournalEntry
    {
        $settlement->loadMissing('bankAccount', 'clearingAccount');

        $entry = $this->journalPostingService->post([
            'event_key' => "gateway_settlement:{$settlement->id}:posted",
            'source_event' => 'gateway_settlement_posted',
            'entry_date' => optional($settlement->settlement_date)?->toDateString() ?? now()->toDateString(),
            'posting_date' => optional($settlement->settlement_date)?->toDateString() ?? now()->toDateString(),
            'description' => $settlement->description,
            'source' => $settlement,
            'posted_by' => $postedBy ?: $settlement->recorded_by,
            'currency' => $settlement->currency,
            'meta' => [
                'gateway' => $settlement->gateway,
                'reference' => $settlement->reference,
                'bank_account_id' => $settlement->bank_account_id,
                'clearing_account_id' => $settlement->clearing_account_id,
            ],
        ], JournalBuilder::make()
            ->debit($settlement->bankAccount, (float) $settlement->amount, 'Gateway settlement received into bank')
            ->credit($settlement->clearingAccount, (float) $settlement->amount, 'Gateway clearing reduced')
            ->lines());

        if (!$settlement->journal_entry_id) {
            $settlement->forceFill(['journal_entry_id' => $entry->id])->save();
        }

        return $entry;
    }

    public function postRefund(Order $order, float $amount, bool $includeShipping = false, ?int $postedBy = null): JournalEntry
    {
        $refundsPayable = $this->accountResolver->resolve('refunds_payable');
        $salesRevenue = $this->accountResolver->resolve('product_sales_revenue');
        $shippingRevenue = $this->accountResolver->resolve('shipping_revenue');

        $builder = JournalBuilder::make()
            ->debit($salesRevenue, $amount, 'Sales refund')
            ->credit($refundsPayable, $amount, 'Refund liability');

        if ($includeShipping && (float) $order->shipping_total > 0) {
            $builder
                ->debit($shippingRevenue, (float) $order->shipping_total, 'Shipping refund')
                ->credit($refundsPayable, (float) $order->shipping_total, 'Shipping refund liability');
        }

        return $this->journalPostingService->post([
            'event_key' => "order:{$order->id}:refund",
            'source_event' => 'order_refund_posted',
            'entry_date' => now()->toDateString(),
            'posting_date' => now()->toDateString(),
            'description' => "Refund recognized for order {$order->order_number}",
            'source' => $order,
            'posted_by' => $postedBy,
            'currency' => $order->currency,
            'meta' => [
                'refund_amount' => $amount,
                'include_shipping' => $includeShipping,
            ],
        ], $builder->lines());
    }

    protected function resolveOrderReceiptAccount(Order $order, ?string $paymentMethod): \App\Models\Accounting\Account
    {
        if ($order->channel === 'online') {
            return $this->accountResolver->resolve('payment_gateway_clearing');
        }

        if (($paymentMethod ?? '') === 'wallet') {
            return $this->accountResolver->resolve('customer_wallet_liability');
        }

        return $this->resolvePaymentMethodAccount($paymentMethod ?: 'cash');
    }

    protected function resolvePaymentMethodAccount(string $paymentMethod): \App\Models\Accounting\Account
    {
        $key = config("accounting.payment_method_accounts.{$paymentMethod}", 'main_bank_account');

        return $this->accountResolver->resolve($key);
    }

    protected function orderCostAmount(Order $order): float
    {
        return round((float) StockEntry::query()
            ->where('source_type', Order::class)
            ->where('source_id', $order->id)
            ->where('type', 'stock_out')
            ->selectRaw('COALESCE(SUM(quantity * unit_cost), 0) as total')
            ->value('total'), 4);
    }

    protected function saleCostAmount(Sale $sale): float
    {
        return round((float) StockEntry::query()
            ->where('source_type', Sale::class)
            ->where('source_id', $sale->id)
            ->where('type', 'stock_out')
            ->selectRaw('COALESCE(SUM(quantity * unit_cost), 0) as total')
            ->value('total'), 4);
    }

    protected function legacyStockAdjustmentType(StockAdjustment $adjustment): StockAdjustmentType
    {
        return (int) $adjustment->adjusted_quantity > 0
            ? StockAdjustmentType::GAIN
            : StockAdjustmentType::LOSS;
    }
}
