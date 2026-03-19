<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CheckoutSession;
use App\Models\PaymentRecoveryLog;
use App\Models\StockReservation;
use App\Services\CheckoutService;
use App\Services\PaystackService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PaymentRecoveryController extends Controller
{
    public function __construct(
        protected CheckoutService $checkoutService,
        protected PaystackService $paystackService,
    ) {}

    public function index(Request $request): Response
    {
        $reference = trim((string) $request->query('reference', ''));

        $session = null;
        if ($reference !== '') {
            $session = CheckoutSession::query()
                ->with([
                    'order.user:id,name,email',
                    'order.payments',
                ])
                ->where('reference', $reference)
                ->first();
        }

        $logs = PaymentRecoveryLog::query()
            ->with('user:id,name,email')
            ->when($reference !== '', fn ($query) => $query->where('reference', $reference))
            ->latest()
            ->limit(100)
            ->get();

        return Inertia::render('Admin/PaymentRecovery/Index', [
            'reference' => $reference !== '' ? $reference : null,
            'checkout_session' => $session ? $this->toSessionPayload($session) : null,
            'logs' => $logs->map(fn (PaymentRecoveryLog $log) => [
                'id' => (int) $log->id,
                'reference' => $log->reference,
                'action' => $log->action,
                'status' => $log->status,
                'message' => $log->message,
                'request_payload' => $log->request_payload,
                'response_payload' => $log->response_payload,
                'created_at' => optional($log->created_at)?->toIso8601String(),
                'actor' => $log->user ? [
                    'id' => (int) $log->user->id,
                    'name' => $log->user->name,
                    'email' => $log->user->email,
                ] : null,
            ])->values()->all(),
        ]);
    }

    public function reverify(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'reference' => ['required', 'string', 'max:100'],
        ]);

        $reference = trim((string) $data['reference']);
        $session = CheckoutSession::query()->where('reference', $reference)->first();

        if (!$session) {
            $this->writeAudit(
                reference: $reference,
                action: 'reverify',
                status: 'failed',
                message: 'Checkout session not found for reference.',
                requestPayload: $data,
                responsePayload: null,
                userId: (int) $request->user()->id,
                session: null,
                orderId: null
            );

            return redirect()
                ->route('admin.payment-recovery.index', ['reference' => $reference])
                ->with('error', 'No checkout session found for this reference.');
        }

        try {
            $result = $this->checkoutService->reverifyPayment($reference);
            $freshSession = CheckoutSession::query()->where('reference', $reference)->first();

            $this->writeAudit(
                reference: $reference,
                action: 'reverify',
                status: 'success',
                message: $result['message'] ?? 'Reverification completed.',
                requestPayload: $data,
                responsePayload: [
                    'order_id' => $result['order']->id ?? null,
                    'success' => $result['success'] ?? true,
                    'message' => $result['message'] ?? null,
                ],
                userId: (int) $request->user()->id,
                session: $freshSession,
                orderId: $result['order']->id ?? null
            );

            return redirect()
                ->route('admin.payment-recovery.index', ['reference' => $reference])
                ->with('success', 'Reverification completed successfully.');
        } catch (ValidationException $exception) {
            $this->writeAudit(
                reference: $reference,
                action: 'reverify',
                status: 'failed',
                message: 'Validation failure during reverification.',
                requestPayload: $data,
                responsePayload: ['errors' => $exception->errors()],
                userId: (int) $request->user()->id,
                session: $session,
                orderId: $session->order_id
            );

            return redirect()
                ->route('admin.payment-recovery.index', ['reference' => $reference])
                ->withErrors($exception->errors())
                ->with('error', 'Reverification failed validation checks.');
        } catch (\Throwable $exception) {
            $this->writeAudit(
                reference: $reference,
                action: 'reverify',
                status: 'failed',
                message: $exception->getMessage(),
                requestPayload: $data,
                responsePayload: null,
                userId: (int) $request->user()->id,
                session: $session,
                orderId: $session->order_id
            );

            Log::error('Manual payment reverification failed', [
                'reference' => $reference,
                'error' => $exception->getMessage(),
            ]);

            report($exception);

            return redirect()
                ->route('admin.payment-recovery.index', ['reference' => $reference])
                ->with('error', 'Unable to reverify this payment right now.');
        }
    }

    public function refund(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'reference' => ['required', 'string', 'max:100'],
            'reason' => ['nullable', 'string', 'max:255'],
            'amount' => ['nullable', 'numeric', 'min:0.01'],
            'force' => ['nullable', 'boolean'],
        ]);

        $reference = trim((string) $data['reference']);
        $session = CheckoutSession::query()->where('reference', $reference)->first();

        if (!$session) {
            $this->writeAudit(
                reference: $reference,
                action: 'refund_reconcile',
                status: 'failed',
                message: 'Checkout session not found for refund reconciliation.',
                requestPayload: $data,
                responsePayload: null,
                userId: (int) $request->user()->id,
                session: null,
                orderId: null
            );

            return redirect()
                ->route('admin.payment-recovery.index', ['reference' => $reference])
                ->with('error', 'No checkout session found for this reference.');
        }

        $force = (bool) ($data['force'] ?? false);
        if ($session->order_id && !$force) {
            $message = 'Order already exists for this reference. Enable force to attempt a manual refund.';

            $this->writeAudit(
                reference: $reference,
                action: 'refund_reconcile',
                status: 'skipped',
                message: $message,
                requestPayload: $data,
                responsePayload: ['order_id' => $session->order_id],
                userId: (int) $request->user()->id,
                session: $session,
                orderId: $session->order_id
            );

            return redirect()
                ->route('admin.payment-recovery.index', ['reference' => $reference])
                ->with('warning', $message);
        }

        try {
            $amountKobo = !empty($data['amount']) ? (int) round(((float) $data['amount']) * 100) : null;
            $reason = trim((string) ($data['reason'] ?? 'Manual refund reconciliation'));

            $refundResponse = $this->paystackService->refundPayment(
                reference: $reference,
                amountKobo: $amountKobo,
                reason: $reason !== '' ? $reason : null
            );

            $payload = is_array($session->verification_payload) ? $session->verification_payload : [];
            $payload['manual_refund'] = [
                'by_user_id' => (int) $request->user()->id,
                'at' => now()->toIso8601String(),
                'reason' => $reason,
                'amount_kobo' => $amountKobo,
                'response' => $refundResponse,
            ];

            $session->payment_status = 'refund_initiated';
            $session->processing_error = 'Manual refund reconciliation initiated by admin.';
            $session->verification_payload = $payload;
            $session->save();

            $freshSession = CheckoutSession::query()->where('reference', $reference)->first();

            $this->writeAudit(
                reference: $reference,
                action: 'refund_reconcile',
                status: 'success',
                message: 'Refund reconciliation initiated successfully.',
                requestPayload: $data,
                responsePayload: $refundResponse,
                userId: (int) $request->user()->id,
                session: $freshSession,
                orderId: $freshSession?->order_id
            );

            return redirect()
                ->route('admin.payment-recovery.index', ['reference' => $reference])
                ->with('success', 'Refund reconciliation initiated successfully.');
        } catch (\Throwable $exception) {
            $this->writeAudit(
                reference: $reference,
                action: 'refund_reconcile',
                status: 'failed',
                message: $exception->getMessage(),
                requestPayload: $data,
                responsePayload: null,
                userId: (int) $request->user()->id,
                session: $session,
                orderId: $session->order_id
            );

            Log::error('Manual refund reconciliation failed', [
                'reference' => $reference,
                'error' => $exception->getMessage(),
            ]);

            report($exception);

            return redirect()
                ->route('admin.payment-recovery.index', ['reference' => $reference])
                ->with('error', 'Unable to initiate refund reconciliation right now.');
        }
    }

    protected function toSessionPayload(CheckoutSession $session): array
    {
        $reservationSummary = StockReservation::query()
            ->selectRaw('status, COUNT(*) as total, COALESCE(SUM(quantity), 0) as qty')
            ->where('checkout_session_id', $session->id)
            ->groupBy('status')
            ->get()
            ->mapWithKeys(fn ($row) => [
                $row->status => [
                    'rows' => (int) $row->total,
                    'quantity' => (int) $row->qty,
                ],
            ]);

        return [
            'id' => (int) $session->id,
            'token' => $session->token,
            'reference' => $session->reference,
            'channel' => $session->channel,
            'used' => (bool) $session->used,
            'payment_status' => $session->payment_status,
            'payment_amount' => $session->payment_amount !== null ? (float) $session->payment_amount : null,
            'payment_currency' => $session->payment_currency,
            'subtotal' => (float) $session->subtotal,
            'shipping_total' => (float) $session->shipping_total,
            'discount_amount' => (float) $session->discount_amount,
            'total' => (float) $session->total,
            'expires_at' => optional($session->expires_at)?->toIso8601String(),
            'processed_at' => optional($session->processed_at)?->toIso8601String(),
            'payment_verified_at' => optional($session->payment_verified_at)?->toIso8601String(),
            'retry_count' => (int) $session->retry_count,
            'processing_error' => $session->processing_error,
            'order' => $session->order ? [
                'id' => (int) $session->order->id,
                'order_number' => $session->order->order_number,
                'status' => $session->order->status,
                'total_amount' => (float) $session->order->total_amount,
                'channel' => $session->order->channel,
                'created_at' => optional($session->order->created_at)?->toIso8601String(),
                'user' => $session->order->user ? [
                    'id' => (int) $session->order->user->id,
                    'name' => $session->order->user->name,
                    'email' => $session->order->user->email,
                ] : null,
                'payments' => $session->order->payments->map(fn ($payment) => [
                    'id' => (int) $payment->id,
                    'method' => $payment->method,
                    'status' => $payment->status,
                    'amount' => (float) $payment->amount,
                    'transaction_reference' => $payment->transaction_reference,
                    'created_at' => optional($payment->created_at)?->toIso8601String(),
                ])->values()->all(),
            ] : null,
            'reservation_summary' => $reservationSummary,
        ];
    }

    protected function writeAudit(
        string $reference,
        string $action,
        string $status,
        ?string $message,
        ?array $requestPayload,
        mixed $responsePayload,
        int $userId,
        ?CheckoutSession $session,
        ?int $orderId
    ): void {
        PaymentRecoveryLog::query()->create([
            'reference' => $reference,
            'action' => $action,
            'status' => $status,
            'message' => $message,
            'request_payload' => $requestPayload,
            'response_payload' => is_array($responsePayload)
                ? $responsePayload
                : ($responsePayload === null ? null : ['value' => $responsePayload]),
            'user_id' => $userId,
            'checkout_session_id' => $session?->id,
            'order_id' => $orderId,
        ]);
    }
}
