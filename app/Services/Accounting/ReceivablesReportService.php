<?php

namespace App\Services\Accounting;

use App\Models\CustomerInvoice;
use App\Models\Payment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class ReceivablesReportService
{
    public function indexReport(array $filters): array
    {
        $filters = $this->normalizeFilters($filters);
        $invoices = $this->invoiceQuery($filters)
            ->paginate((int) $filters['per_page'])
            ->withQueryString()
            ->through(fn (CustomerInvoice $invoice) => $this->mapInvoice($invoice));

        return [
            'filters' => $filters,
            'summary_cards' => $this->summaryCards($filters),
            'aging_buckets' => $this->agingBuckets($filters['as_of']),
            'invoices' => $invoices,
            'recent_recoveries' => $this->recentRecoveries($filters),
        ];
    }

    public function normalizeFilters(array $filters): array
    {
        return [
            'search' => trim((string) ($filters['search'] ?? '')),
            'status' => trim((string) ($filters['status'] ?? '')),
            'customer_id' => !empty($filters['customer_id']) ? (int) $filters['customer_id'] : null,
            'from' => $filters['from'] ?? null,
            'to' => $filters['to'] ?? null,
            'as_of' => Carbon::parse($filters['as_of'] ?? now()->toDateString())->toDateString(),
            'per_page' => max(10, min(100, (int) ($filters['per_page'] ?? 15))),
        ];
    }

    protected function invoiceQuery(array $filters): Builder
    {
        return CustomerInvoice::query()
            ->with(['customer:id,name,email,phone', 'order:id,order_number,total_amount', 'payments.employee:id,name,email'])
            ->when($filters['search'] !== '', function (Builder $query) use ($filters) {
                $search = $filters['search'];
                $query->where(function (Builder $inner) use ($search) {
                    $inner->where('invoice_number', 'like', "%{$search}%")
                        ->orWhereHas('order', fn (Builder $orderQuery) => $orderQuery->where('order_number', 'like', "%{$search}%"))
                        ->orWhereHas('customer', function (Builder $customerQuery) use ($search) {
                            $customerQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%")
                                ->orWhere('phone', 'like', "%{$search}%");
                        });
                });
            })
            ->when($filters['status'] !== '', fn (Builder $query) => $query->where('status', $filters['status']))
            ->when($filters['customer_id'], fn (Builder $query, int $customerId) => $query->where('customer_id', $customerId))
            ->when($filters['from'], fn (Builder $query, string $from) => $query->whereDate('issued_at', '>=', $from))
            ->when($filters['to'], fn (Builder $query, string $to) => $query->whereDate('issued_at', '<=', $to))
            ->latest('issued_at')
            ->latest('id');
    }

    protected function summaryCards(array $filters): array
    {
        $base = $this->invoiceQuery($filters)->toBase();
        $outstanding = round((float) CustomerInvoice::query()
            ->when($filters['status'] !== '', fn (Builder $query) => $query->where('status', $filters['status']))
            ->when($filters['customer_id'], fn (Builder $query, int $customerId) => $query->where('customer_id', $customerId))
            ->sum('outstanding_balance'), 2);

        $overdue = round((float) CustomerInvoice::query()
            ->where('outstanding_balance', '>', 0)
            ->whereDate('due_date', '<', $filters['as_of'])
            ->when($filters['customer_id'], fn (Builder $query, int $customerId) => $query->where('customer_id', $customerId))
            ->sum('outstanding_balance'), 2);

        $creditSales = round((float) CustomerInvoice::query()
            ->when($filters['from'], fn (Builder $query, string $from) => $query->whereDate('issued_at', '>=', $from))
            ->when($filters['to'], fn (Builder $query, string $to) => $query->whereDate('issued_at', '<=', $to))
            ->when($filters['customer_id'], fn (Builder $query, int $customerId) => $query->where('customer_id', $customerId))
            ->sum('total_amount'), 2);

        $debtRecovered = round((float) Payment::query()
            ->join('customer_invoices', function ($join) {
                $join->on('customer_invoices.id', '=', 'payments.payable_id')
                    ->where('payments.payable_type', CustomerInvoice::class);
            })
            ->where('payments.status', 'paid')
            ->when($filters['from'], fn ($query, string $from) => $query->whereDate('payments.paid_at', '>=', $from))
            ->when($filters['to'], fn ($query, string $to) => $query->whereDate('payments.paid_at', '<=', $to))
            ->when($filters['customer_id'], fn ($query, int $customerId) => $query->where('customer_invoices.customer_id', $customerId))
            ->sum('payments.amount'), 2);

        return [
            ['key' => 'outstanding', 'label' => 'Outstanding balance', 'value' => $outstanding],
            ['key' => 'overdue', 'label' => 'Overdue balance', 'value' => $overdue],
            ['key' => 'credit_sales', 'label' => 'Credit sales', 'value' => $creditSales],
            ['key' => 'debt_recovered', 'label' => 'Debt recovered', 'value' => $debtRecovered],
        ];
    }

    protected function agingBuckets(string $asOf): array
    {
        $asOfDate = Carbon::parse($asOf);
        $buckets = [
            'current' => 0,
            '1_30' => 0,
            '31_60' => 0,
            '61_90' => 0,
            '91_plus' => 0,
        ];

        CustomerInvoice::query()
            ->where('outstanding_balance', '>', 0)
            ->get(['due_date', 'outstanding_balance'])
            ->each(function (CustomerInvoice $invoice) use (&$buckets, $asOfDate) {
                if (!$invoice->due_date || $invoice->due_date->gte($asOfDate)) {
                    $buckets['current'] += (float) $invoice->outstanding_balance;

                    return;
                }

                $days = (int) $invoice->due_date->diffInDays($asOfDate);

                match (true) {
                    $days <= 30 => $buckets['1_30'] += (float) $invoice->outstanding_balance,
                    $days <= 60 => $buckets['31_60'] += (float) $invoice->outstanding_balance,
                    $days <= 90 => $buckets['61_90'] += (float) $invoice->outstanding_balance,
                    default => $buckets['91_plus'] += (float) $invoice->outstanding_balance,
                };
            });

        return collect($buckets)
            ->map(fn (float $amount, string $key) => [
                'key' => $key,
                'label' => match ($key) {
                    'current' => 'Current',
                    '1_30' => '1 - 30 days',
                    '31_60' => '31 - 60 days',
                    '61_90' => '61 - 90 days',
                    default => '91+ days',
                },
                'amount' => round($amount, 2),
            ])
            ->values()
            ->all();
    }

    protected function recentRecoveries(array $filters): array
    {
        return Payment::query()
            ->join('customer_invoices', function ($join) {
                $join->on('customer_invoices.id', '=', 'payments.payable_id')
                    ->where('payments.payable_type', CustomerInvoice::class);
            })
            ->leftJoin('users as customers', 'customers.id', '=', 'customer_invoices.customer_id')
            ->where('payments.status', 'paid')
            ->when($filters['from'], fn ($query, string $from) => $query->whereDate('payments.paid_at', '>=', $from))
            ->when($filters['to'], fn ($query, string $to) => $query->whereDate('payments.paid_at', '<=', $to))
            ->when($filters['customer_id'], fn ($query, int $customerId) => $query->where('customer_invoices.customer_id', $customerId))
            ->orderByDesc('payments.paid_at')
            ->limit(20)
            ->get([
                'payments.id',
                'payments.method',
                'payments.amount',
                'payments.transaction_reference',
                'payments.paid_at',
                'customer_invoices.invoice_number',
                'customers.name as customer_name',
            ])
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'invoice_number' => $row->invoice_number,
                'customer_name' => $row->customer_name,
                'method' => $row->method,
                'amount' => round((float) $row->amount, 2),
                'transaction_reference' => $row->transaction_reference,
                'paid_at' => $row->paid_at ? Carbon::parse($row->paid_at)->toIso8601String() : null,
            ])
            ->values()
            ->all();
    }

    protected function mapInvoice(CustomerInvoice $invoice): array
    {
        return [
            'id' => (int) $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'customer' => $invoice->customer ? [
                'id' => (int) $invoice->customer->id,
                'name' => $invoice->customer->name,
                'email' => $invoice->customer->email,
                'phone' => $invoice->customer->phone,
            ] : null,
            'order' => $invoice->order ? [
                'id' => (int) $invoice->order->id,
                'order_number' => $invoice->order->order_number,
                'total_amount' => (float) $invoice->order->total_amount,
            ] : null,
            'currency' => $invoice->currency,
            'total_amount' => (float) $invoice->total_amount,
            'amount_paid' => (float) $invoice->amount_paid,
            'outstanding_balance' => (float) $invoice->outstanding_balance,
            'due_date' => optional($invoice->due_date)?->toDateString(),
            'repayment_terms' => $invoice->repayment_terms,
            'status' => $invoice->status,
            'issued_at' => optional($invoice->issued_at)?->toIso8601String(),
            'closed_at' => optional($invoice->closed_at)?->toIso8601String(),
        ];
    }
}
