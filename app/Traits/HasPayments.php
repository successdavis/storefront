<?php

namespace App\Traits;

use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasPayments
{
    /**
     * Define a polymorphic relationship to payments.
     */
    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable');
    }

    /**
     * Resolve the payment service for this model.
     */
    public function paymentService(): PaymentService
    {
        return new PaymentService($this);
    }

    /**
     * Shortcut: directly add payment from the model.
     *
     * @param array $data
     * @return \App\Models\Payment
     */
    public function addPayment(array $data): Payment
    {
        return $this->paymentService()->store($data);
    }


    /**
     * Get the total payments made for this record.
     *
     * @return float
     */
    public function totalPayments(): float
    {
        return (float) $this->payments()
            ->where('status', 'paid')
            ->sum('amount');
    }

    /**
     * Get the outstanding balance (assuming model has a `total_amount` field).
     *
     * @return float
     */
    public function outstandingBalance(): float
    {
        if (! $this->total_amount) {
            return 0.0; // prevent errors if model doesn't have `total_amount`
        }

        return max(0, (float) $this->total_amount - $this->totalPayments());
    }

    /**
     * Check if this record is fully paid.
     *
     * @return bool
     */
    public function isPaid(): bool
    {
        if (! $this->total_amount) {
            return false;
        }

        return $this->outstandingBalance() <= 0;
    }

    /**
     * Check if this record is partially paid.
     *
     * @return bool
     */
    public function isPartiallyPaid(): bool
    {
        return $this->totalPayments() > 0 && $this->outstandingBalance() > 0;
    }

    /**
     * Check if this record has no payments.
     *
     * @return bool
     */
    public function isUnpaid(): bool
    {
        return $this->totalPayments() === 0;
    }
}
