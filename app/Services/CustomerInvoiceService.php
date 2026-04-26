<?php

namespace App\Services;

use App\Models\CustomerInvoice;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Accounting\AccountingService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CustomerInvoiceService
{
    public function __construct(
        protected AccountingService $accountingService,
    ) {}

    public function createFromOrder(Order $order, array $attributes = [], ?int $actorId = null): ?CustomerInvoice
    {
        $order->loadMissing('sale', 'payments');

        $receivableAmount = round(max(0, (float) $order->total_amount - (float) $order->totalPayments()), 2);
        if ($receivableAmount <= 0) {
            return null;
        }

        return DB::transaction(function () use ($order, $attributes, $receivableAmount, $actorId): CustomerInvoice {
            $invoice = CustomerInvoice::query()->firstOrNew([
                'order_id' => $order->id,
            ]);

            $invoice->fill([
                'sale_id' => $order->sale?->id,
                'customer_id' => $order->user_id,
                'employee_id' => $actorId,
                'pos_terminal_id' => $order->sale?->pos_terminal_id,
                'currency' => $order->currency ?: config('accounting.currency', 'NGN'),
                'total_amount' => $receivableAmount,
                'amount_paid' => $invoice->exists ? $invoice->amount_paid : 0,
                'outstanding_balance' => $invoice->exists ? $invoice->outstanding_balance : $receivableAmount,
                'due_date' => $attributes['due_date'] ?? $invoice->due_date,
                'repayment_terms' => $attributes['repayment_terms'] ?? $invoice->repayment_terms,
                'status' => $invoice->exists ? $invoice->status : CustomerInvoice::STATUS_UNPAID,
                'issued_at' => $invoice->issued_at ?? ($order->created_at ?? now()),
                'meta' => array_filter([
                    'order_number' => $order->order_number,
                    'order_total_amount' => (float) $order->total_amount,
                    'initial_paid_amount' => (float) $order->totalPayments(),
                    'payment_breakdown' => $attributes['payment_breakdown'] ?? null,
                ], fn ($value) => $value !== null && $value !== []),
            ]);

            if (!$invoice->exists) {
                $invoice->invoice_number = $this->generateInvoiceNumber();
            }

            $invoice->save();
            $invoice->refreshPaymentStatus();

            return $invoice->fresh(['customer:id,name,email,phone', 'order:id,order_number,total_amount', 'sale:id,pos_terminal_id']);
        });
    }

    /**
     * @param list<array<string, mixed>> $paymentLines
     */
    public function recordRepayment(CustomerInvoice $invoice, array $paymentLines, int $actorId): CustomerInvoice
    {
        if ($paymentLines === []) {
            throw ValidationException::withMessages([
                'payment_lines' => 'At least one repayment line is required.',
            ]);
        }

        return DB::transaction(function () use ($invoice, $paymentLines, $actorId): CustomerInvoice {
            $invoice = CustomerInvoice::query()
                ->with('payments')
                ->lockForUpdate()
                ->findOrFail($invoice->id);

            $outstanding = round((float) $invoice->outstanding_balance, 2);
            if ($outstanding <= 0) {
                throw ValidationException::withMessages([
                    'payment_lines' => 'This invoice has already been fully settled.',
                ]);
            }

            $payments = collect($paymentLines)->values()->map(function (array $line, int $index) {
                $amount = round((float) ($line['amount'] ?? 0), 2);
                $method = (string) ($line['method'] ?? '');

                if ($amount <= 0) {
                    throw ValidationException::withMessages([
                        "payment_lines.{$index}.amount" => 'Repayment amounts must be greater than zero.',
                    ]);
                }

                if (!in_array($method, OrderManagementService::PAYMENT_METHODS, true)) {
                    throw ValidationException::withMessages([
                        "payment_lines.{$index}.method" => 'Unsupported repayment method.',
                    ]);
                }

                return [
                    'amount' => $amount,
                    'method' => $method,
                    'transaction_reference' => $line['transaction_reference'] ?? null,
                ];
            });

            $total = round((float) $payments->sum('amount'), 2);
            if ($total > $outstanding + 0.01) {
                throw ValidationException::withMessages([
                    'payment_lines' => 'Repayment total cannot exceed the invoice outstanding balance.',
                ]);
            }

            /** @var Collection<int, Payment> $createdPayments */
            $createdPayments = collect();

            foreach ($payments as $line) {
                $payment = $invoice->addPayment([
                    'type' => 'inflow',
                    'method' => $line['method'],
                    'amount' => $line['amount'],
                    'status' => 'paid',
                    'paid_at' => now(),
                    'employee_id' => $actorId,
                    'transaction_reference' => $line['transaction_reference'],
                    'meta' => [
                        'source' => 'customer_invoice_repayment',
                        'invoice_number' => $invoice->invoice_number,
                    ],
                ]);

                $this->accountingService->postCustomerInvoicePayment($invoice, $payment, $actorId);
                $createdPayments->push($payment);
            }

            $invoice->refresh();
            $invoice->refreshPaymentStatus();

            return $invoice->fresh(['payments.employee:id,name,email', 'customer:id,name,email,phone', 'order:id,order_number,total_amount']);
        });
    }

    protected function generateInvoiceNumber(): string
    {
        do {
            $candidate = 'INV-'.now()->format('Ymd').'-'.Str::upper(Str::random(8));
        } while (CustomerInvoice::query()->where('invoice_number', $candidate)->exists());

        return $candidate;
    }
}
