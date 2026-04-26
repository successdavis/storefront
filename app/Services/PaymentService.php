<?php

namespace App\Services;

use App\Models\Payment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    protected Model $payable;

    public function __construct(Model $payable)
    {
        $this->payable = $payable;
    }

    /**
     * Store a payment for the current payable model.
     */
    public function store(array $data): Payment
    {
        $rules = [
            'type' => 'required|in:inflow,outflow',
            'method' => 'required|in:cash,card,transfer,wallet,paypal,stripe,cheque',
            'amount' => 'required|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'transaction_reference' => 'nullable|string|max:255',
            'status' => 'nullable|in:pending,paid,failed,refunded',
            'paid_at' => 'nullable|date',
            'employee_id' => 'nullable|exists:users,id',
            'meta' => 'nullable|array',
        ];

        $validator = validator($data, $rules);
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return DB::transaction(function () use ($data) {
            $status = $data['status'] ?? 'pending';
            $reference = isset($data['transaction_reference']) && $data['transaction_reference'] !== ''
                ? (string) $data['transaction_reference']
                : $this->generateReference();

            $existing = $this->payable->payments()
                ->where('transaction_reference', $reference)
                ->where('type', $data['type'])
                ->first();

            if ($existing) {
                return $existing;
            }

            $payment = new Payment();
            $payment->fill([
                'type' => $data['type'],
                'method' => $data['method'],
                'amount' => $data['amount'],
                'currency' => $data['currency'] ?? 'NGN',
                'transaction_reference' => $reference,
                'status' => $status,
                'paid_at' => $data['paid_at'] ?? ($status === 'paid' ? now() : null),
                'employee_id' => $data['employee_id'] ?? null,
                'meta' => $data['meta'] ?? null,
            ]);

            $this->payable->payments()->save($payment);

            // Only payables with explicit payment-status logic should mutate themselves after a payment.
            if (method_exists($this->payable, 'refreshPaymentStatus')) {
                $this->payable->refreshPaymentStatus();
            }

            return $payment;
        });
    }

    /**
     * Generate a unique transaction reference.
     */
    protected function generateReference(): string
    {
        return 'PAY-' . now()->format('YmdHis') . '-' . strtoupper(Str::random(6));
    }
}
