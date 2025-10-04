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
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'nullable|string|size:3',
            'transaction_reference' => 'nullable|string|max:255',
            'status' => 'nullable|in:pending,paid,failed,refunded',
            'paid_at' => 'nullable|date',
            'employee_id' => 'nullable|exists:employees,id',
            'meta' => 'nullable|array',
        ];

        $validator = validator($data, $rules);
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return DB::transaction(function () use ($data) {
            $payment = new Payment();

            $payment->fill([
                'type' => $data['type'],
                'method' => $data['method'],
                'amount' => $data['amount'],
                'currency' => $data['currency'] ?? 'NGN',
                'transaction_reference' => $data['transaction_reference'] ?? $this->generateReference(),
                'status' => $data['status'] ?? 'pending',
                'paid_at' => $data['paid_at'] ?? now(),
                'employee_id' => $data['employee_id'] ?? null,
                'meta' => isset($data['meta']) ? json_encode($data['meta']) : null,
            ]);

            $this->payable->payments()->save($payment);

            // 🔹 Auto-update payable status if applicable
            if (method_exists($this->payable, 'outstandingBalance')) {
                if ($this->payable->outstandingBalance() <= 0) {
                    $this->payable->update(['status' => 'paid']);
                } elseif ($this->payable->totalPayments() > 0) {
                    $this->payable->update(['status' => 'partially_paid']);
                }
            }

            return $payment;
        });
    }

    /**
     * Generate a unique transaction reference.
     */
    protected function generateReference(): string
    {
        // Example format: PAY-20251002-XYZ123
        return 'PAY-' . now()->format('YmdHis') . '-' . strtoupper(Str::random(6));
    }
}
