<?php

namespace App\Services\Accounting;

use App\Models\CustomerInvoice;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CashSummaryService
{
    public function dailySummary(array $filters): array
    {
        $date = Carbon::parse($filters['date'] ?? now()->toDateString())->toDateString();

        $orderPayments = $this->orderPaymentsForDate($date);
        $invoicePayments = $this->invoicePaymentsForDate($date);
        $combinedReceipts = $orderPayments->concat($invoicePayments)->values();

        return [
            'filters' => ['date' => $date],
            'summary_cards' => [
                ['key' => 'sales', 'label' => 'Total sales', 'value' => $this->totalSalesForDate($date)],
                ['key' => 'cash', 'label' => 'Cash received', 'value' => $this->sumByMethods($combinedReceipts, ['cash'])],
                ['key' => 'bank', 'label' => 'Bank / transfer received', 'value' => $this->sumByMethods($combinedReceipts, ['transfer', 'card', 'paypal', 'stripe', 'cheque'])],
                ['key' => 'wallet', 'label' => 'Wallet received', 'value' => $this->sumByMethods($combinedReceipts, ['wallet'])],
                ['key' => 'credit_sales', 'label' => 'Credit sales created', 'value' => $this->creditSalesCreatedForDate($date)],
                ['key' => 'debt_recovered', 'label' => 'Debt recovered', 'value' => round((float) $invoicePayments->sum('amount'), 2)],
            ],
            'payment_method_breakdown' => $combinedReceipts
                ->groupBy('method')
                ->map(fn (Collection $rows, string $method) => [
                    'method' => $method,
                    'label' => str($method)->replace('_', ' ')->headline()->value(),
                    'amount' => round((float) $rows->sum('amount'), 2),
                    'count' => (int) $rows->count(),
                ])
                ->sortByDesc('amount')
                ->values()
                ->all(),
            'employee_breakdown' => $this->breakdownBy($combinedReceipts, 'employee_id', 'employee_name'),
            'terminal_breakdown' => $this->breakdownBy($combinedReceipts, 'pos_terminal_id', 'pos_terminal_name'),
            'receipts' => $combinedReceipts
                ->sortByDesc('paid_at')
                ->values()
                ->map(fn (array $row) => [
                    'id' => $row['id'],
                    'source_type' => $row['source_type'],
                    'reference' => $row['reference'],
                    'customer_name' => $row['customer_name'],
                    'method' => $row['method'],
                    'amount' => $row['amount'],
                    'employee_name' => $row['employee_name'],
                    'pos_terminal_name' => $row['pos_terminal_name'],
                    'paid_at' => optional($row['paid_at'])?->toIso8601String(),
                ])
                ->all(),
        ];
    }

    protected function orderPaymentsForDate(string $date): Collection
    {
        return Payment::query()
            ->join('orders', function ($join) {
                $join->on('orders.id', '=', 'payments.payable_id')
                    ->where('payments.payable_type', Order::class);
            })
            ->leftJoin('users as customers', 'customers.id', '=', 'orders.user_id')
            ->leftJoin('sales', 'sales.order_id', '=', 'orders.id')
            ->leftJoin('pos_terminals', 'pos_terminals.id', '=', 'sales.pos_terminal_id')
            ->leftJoin('users as employees', 'employees.id', '=', 'payments.employee_id')
            ->whereDate('payments.paid_at', $date)
            ->where('payments.status', 'paid')
            ->where('orders.channel', 'pos')
            ->selectRaw("
                payments.id,
                payments.method,
                payments.amount,
                payments.paid_at,
                payments.employee_id,
                employees.name as employee_name,
                sales.pos_terminal_id,
                pos_terminals.name as pos_terminal_name,
                orders.order_number as reference,
                customers.name as customer_name
            ")
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'source_type' => 'order',
                'method' => (string) $row->method,
                'amount' => round((float) $row->amount, 2),
                'paid_at' => $row->paid_at ? Carbon::parse($row->paid_at) : null,
                'employee_id' => $row->employee_id ? (int) $row->employee_id : null,
                'employee_name' => $row->employee_name,
                'pos_terminal_id' => $row->pos_terminal_id ? (int) $row->pos_terminal_id : null,
                'pos_terminal_name' => $row->pos_terminal_name,
                'reference' => $row->reference,
                'customer_name' => $row->customer_name,
            ]);
    }

    protected function invoicePaymentsForDate(string $date): Collection
    {
        return Payment::query()
            ->join('customer_invoices', function ($join) {
                $join->on('customer_invoices.id', '=', 'payments.payable_id')
                    ->where('payments.payable_type', CustomerInvoice::class);
            })
            ->leftJoin('users as customers', 'customers.id', '=', 'customer_invoices.customer_id')
            ->leftJoin('pos_terminals', 'pos_terminals.id', '=', 'customer_invoices.pos_terminal_id')
            ->leftJoin('users as employees', 'employees.id', '=', 'payments.employee_id')
            ->whereDate('payments.paid_at', $date)
            ->where('payments.status', 'paid')
            ->selectRaw("
                payments.id,
                payments.method,
                payments.amount,
                payments.paid_at,
                payments.employee_id,
                employees.name as employee_name,
                customer_invoices.pos_terminal_id,
                pos_terminals.name as pos_terminal_name,
                customer_invoices.invoice_number as reference,
                customers.name as customer_name
            ")
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'source_type' => 'invoice',
                'method' => (string) $row->method,
                'amount' => round((float) $row->amount, 2),
                'paid_at' => $row->paid_at ? Carbon::parse($row->paid_at) : null,
                'employee_id' => $row->employee_id ? (int) $row->employee_id : null,
                'employee_name' => $row->employee_name,
                'pos_terminal_id' => $row->pos_terminal_id ? (int) $row->pos_terminal_id : null,
                'pos_terminal_name' => $row->pos_terminal_name,
                'reference' => $row->reference,
                'customer_name' => $row->customer_name,
            ]);
    }

    protected function totalSalesForDate(string $date): float
    {
        return round((float) Order::query()
            ->where('channel', 'pos')
            ->whereDate('created_at', $date)
            ->sum('total_amount'), 2);
    }

    protected function creditSalesCreatedForDate(string $date): float
    {
        return round((float) CustomerInvoice::query()
            ->whereDate('issued_at', $date)
            ->sum('total_amount'), 2);
    }

    protected function sumByMethods(Collection $rows, array $methods): float
    {
        return round((float) $rows
            ->whereIn('method', $methods)
            ->sum('amount'), 2);
    }

    protected function breakdownBy(Collection $rows, string $idKey, string $nameKey): array
    {
        return $rows
            ->groupBy(fn (array $row) => $row[$idKey] ?: 'unknown')
            ->map(function (Collection $group) use ($idKey, $nameKey) {
                $first = $group->first();

                return [
                    'id' => $first[$idKey],
                    'name' => $first[$nameKey] ?: 'Unassigned',
                    'amount' => round((float) $group->sum('amount'), 2),
                    'transactions' => (int) $group->count(),
                ];
            })
            ->sortByDesc('amount')
            ->values()
            ->all();
    }
}
