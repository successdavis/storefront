<?php

namespace App\Services\Accounting;

use App\Models\Accounting\Expense;
use App\Models\Accounting\JournalEntry;
use App\Models\ItemReceipt;
use App\Models\OpeningBalance;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\StockAdjustment;
use App\Models\VendorBill;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class HistoricalAccountingSyncService
{
    public function __construct(
        protected AccountingService $accountingService,
    ) {}

    public function sync(?int $actorId = null): array
    {
        $summary = [
            'opening_balances' => 0,
            'orders' => 0,
            'sales' => 0,
            'item_receipts' => 0,
            'vendor_bills' => 0,
            'vendor_payments' => 0,
            'expenses' => 0,
            'stock_adjustments' => 0,
        ];

        DB::transaction(function () use (&$summary, $actorId) {
            OpeningBalance::query()
                ->with('items')
                ->orderBy('effective_at')
                ->orderBy('id')
                ->chunkById(100, function ($balances) use (&$summary, $actorId) {
                    foreach ($balances as $balance) {
                        if ($this->alreadySynced("opening_balance:{$balance->id}:posted")) {
                            continue;
                        }

                        if ($this->accountingService->postOpeningBalance($balance, $actorId ?: $balance->employee_id)) {
                            $summary['opening_balances']++;
                        }
                    }
                });

            Order::query()
                ->with(['items', 'payments'])
                ->whereIn('status', ['paid', 'completed'])
                ->orderBy('created_at')
                ->orderBy('id')
                ->chunkById(100, function ($orders) use (&$summary, $actorId) {
                    foreach ($orders as $order) {
                        if ($this->alreadySynced("order:{$order->id}:sale_posted")) {
                            continue;
                        }

                        if ($this->accountingService->postOrder($order, $this->resolveOrderPaymentMethod($order), $actorId)) {
                            $summary['orders']++;
                        }
                    }
                });

            Sale::query()
                ->with(['items', 'payments'])
                ->whereNull('order_id')
                ->orderBy('created_at')
                ->orderBy('id')
                ->chunkById(100, function ($sales) use (&$summary, $actorId) {
                    foreach ($sales as $sale) {
                        if ($this->alreadySynced("sale:{$sale->id}:sale_posted")) {
                            continue;
                        }

                        if ($this->accountingService->postSale($sale, $this->resolveSalePaymentMethod($sale), $actorId)) {
                            $summary['sales']++;
                        }
                    }
                });

            ItemReceipt::query()
                ->with('items')
                ->where('status', 'received')
                ->orderBy('received_date')
                ->orderBy('id')
                ->chunkById(100, function ($receipts) use (&$summary, $actorId) {
                    foreach ($receipts as $receipt) {
                        if ($this->alreadySynced("item_receipt:{$receipt->id}:inventory_recognized")) {
                            continue;
                        }

                        if ($this->accountingService->postInventoryReceipt($receipt, $actorId)) {
                            $summary['item_receipts']++;
                        }
                    }
                });

            VendorBill::query()
                ->with('items')
                ->whereNotIn('status', ['void'])
                ->orderBy('bill_date')
                ->orderBy('id')
                ->chunkById(100, function ($bills) use (&$summary, $actorId) {
                    foreach ($bills as $bill) {
                        if ($this->alreadySynced("vendor_bill:{$bill->id}:recognized")) {
                            continue;
                        }

                        if ($this->accountingService->postVendorBill($bill, $actorId)) {
                            $summary['vendor_bills']++;
                        }
                    }
                });

            Payment::query()
                ->where('payable_type', VendorBill::class)
                ->where('status', 'paid')
                ->orderBy('created_at')
                ->orderBy('id')
                ->chunkById(100, function ($payments) use (&$summary, $actorId) {
                    foreach ($payments as $payment) {
                        if ($this->alreadySynced("payment:{$payment->id}:vendor_bill_payment")) {
                            continue;
                        }

                        if ($this->accountingService->postVendorBillPayment($payment, $actorId)) {
                            $summary['vendor_payments']++;
                        }
                    }
                });

            Expense::query()
                ->where('status', 'posted')
                ->orderBy('expense_date')
                ->orderBy('id')
                ->chunkById(100, function ($expenses) use (&$summary, $actorId) {
                    foreach ($expenses as $expense) {
                        if ($this->alreadySynced("expense:{$expense->id}:posted")) {
                            continue;
                        }

                        if ($this->accountingService->postExpense($expense, $actorId)) {
                            $summary['expenses']++;
                        }
                    }
                });

            StockAdjustment::query()
                ->where('status', 'approved')
                ->orderBy('approved_at')
                ->orderBy('id')
                ->chunkById(100, function ($adjustments) use (&$summary, $actorId) {
                    foreach ($adjustments as $adjustment) {
                        if ($this->alreadySynced("stock_adjustment:{$adjustment->id}:posted")) {
                            continue;
                        }

                        if ($this->accountingService->postStockAdjustment($adjustment, $actorId)) {
                            $summary['stock_adjustments']++;
                        }
                    }
                });
        }, 3);

        return [
            'synced' => $summary,
            'total_posted' => array_sum($summary),
        ];
    }

    public function pendingSummary(): array
    {
        return [
            'opening_balances' => OpeningBalance::query()->get()->filter(fn ($balance) => !$this->alreadySynced("opening_balance:{$balance->id}:posted"))->count(),
            'orders' => Order::query()->whereIn('status', ['paid', 'completed'])->get()->filter(fn ($order) => !$this->alreadySynced("order:{$order->id}:sale_posted"))->count(),
            'sales' => Sale::query()->whereNull('order_id')->get()->filter(fn ($sale) => !$this->alreadySynced("sale:{$sale->id}:sale_posted"))->count(),
            'item_receipts' => ItemReceipt::query()->where('status', 'received')->get()->filter(fn ($receipt) => !$this->alreadySynced("item_receipt:{$receipt->id}:inventory_recognized"))->count(),
            'vendor_bills' => VendorBill::query()->whereNotIn('status', ['void'])->get()->filter(fn ($bill) => !$this->alreadySynced("vendor_bill:{$bill->id}:recognized"))->count(),
            'vendor_payments' => Payment::query()->where('payable_type', VendorBill::class)->where('status', 'paid')->get()->filter(fn ($payment) => !$this->alreadySynced("payment:{$payment->id}:vendor_bill_payment"))->count(),
            'expenses' => Expense::query()->where('status', 'posted')->get()->filter(fn ($expense) => !$this->alreadySynced("expense:{$expense->id}:posted"))->count(),
            'stock_adjustments' => StockAdjustment::query()->where('status', 'approved')->get()->filter(fn ($adjustment) => !$this->alreadySynced("stock_adjustment:{$adjustment->id}:posted"))->count(),
        ];
    }

    protected function resolveOrderPaymentMethod(Order $order): ?string
    {
        return $order->payments
            ->firstWhere('status', 'paid')
            ?->method;
    }

    protected function resolveSalePaymentMethod(Sale $sale): ?string
    {
        return $sale->payments
            ->firstWhere('status', 'paid')
            ?->method;
    }

    protected function alreadySynced(string $eventKey): bool
    {
        return JournalEntry::query()->where('event_key', $eventKey)->exists();
    }
}
