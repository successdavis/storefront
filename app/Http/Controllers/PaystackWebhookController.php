<?php

namespace App\Http\Controllers;

use App\Services\CheckoutService;
use App\Services\PaystackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaystackWebhookController extends Controller
{
    public function __construct(
        protected CheckoutService $checkoutService,
        protected PaystackService $paystackService,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        $rawBody = (string) $request->getContent();
        $signature = (string) $request->header('x-paystack-signature');

        if (!$this->paystackService->isValidWebhookSignature($rawBody, $signature)) {
            return response()->json(['ok' => false], 401);
        }

        $payload = $request->json()->all();
        $event = (string) data_get($payload, 'event', '');
        $reference = (string) data_get($payload, 'data.reference', '');

        if ($event !== 'charge.success' || $reference === '') {
            return response()->json(['ok' => true], 200);
        }

        try {
            $this->checkoutService->processPaystackWebhook($reference, $payload);

            return response()->json(['ok' => true], 200);
        } catch (\Throwable $exception) {
            Log::error('Paystack webhook processing failed', [
                'event' => $event,
                'reference' => $reference,
                'error' => $exception->getMessage(),
            ]);

            return response()->json(['ok' => false], 500);
        }
    }
}

