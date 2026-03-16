<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class PaystackService
{
    public function initializePayment(array $payload): array
    {
        $this->assertConfigured();

        $response = Http::baseUrl(config('paystack.payment_url'))
            ->withToken(config('paystack.secret_key'))
            ->acceptJson()
            ->timeout(15)
            ->retry(2, 300)
            ->post('/transaction/initialize', [
                'amount' => (int) $payload['amount'],
                'email' => (string) $payload['email'],
                'reference' => (string) $payload['reference'],
                'callback_url' => (string) $payload['callback_url'],
                'metadata' => $payload['metadata'] ?? [],
            ]);

        if ($response->failed()) {
            throw ValidationException::withMessages([
                'payment' => 'Unable to initialize Paystack transaction. Please try again.',
            ]);
        }

        $body = $response->json();
        if (!($body['status'] ?? false) || empty($body['data']['authorization_url'])) {
            throw ValidationException::withMessages([
                'payment' => $body['message'] ?? 'Paystack transaction initialization failed.',
            ]);
        }

        return $body['data'];
    }

    public function verifyPayment(string $reference): array
    {
        $this->assertConfigured();

        $response = Http::baseUrl(config('paystack.payment_url'))
            ->withToken(config('paystack.secret_key'))
            ->acceptJson()
            ->timeout(15)
            ->retry(2, 300)
            ->get('/transaction/verify/' . urlencode($reference));

        if ($response->failed()) {
            throw ValidationException::withMessages([
                'payment' => 'Unable to verify payment with Paystack.',
            ]);
        }

        $body = $response->json();
        if (!($body['status'] ?? false) || empty($body['data'])) {
            throw ValidationException::withMessages([
                'payment' => $body['message'] ?? 'Payment verification failed.',
            ]);
        }

        return $body['data'];
    }

    protected function assertConfigured(): void
    {
        if (!config('paystack.secret_key')) {
            throw ValidationException::withMessages([
                'payment' => 'Paystack is not configured. Missing secret key.',
            ]);
        }

        if (!config('paystack.payment_url')) {
            throw ValidationException::withMessages([
                'payment' => 'Paystack is not configured. Missing payment URL.',
            ]);
        }
    }
}
