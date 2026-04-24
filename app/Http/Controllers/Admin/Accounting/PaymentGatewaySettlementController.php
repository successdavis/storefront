<?php

namespace App\Http\Controllers\Admin\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Accounting\StorePaymentGatewaySettlementRequest;
use App\Services\Accounting\AccountService;
use App\Services\Accounting\PaymentGatewaySettlementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PaymentGatewaySettlementController extends Controller
{
    public function __construct(
        protected PaymentGatewaySettlementService $settlementService,
        protected AccountService $accountService,
    ) {}

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string'],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
        ]);

        return Inertia::render('Admin/Accounting/GatewaySettlements/Index', [
            'filters' => $filters,
            'settlements' => $this->settlementService->paginate($filters)->through(fn ($settlement) => [
                'id' => $settlement->id,
                'settlement_number' => $settlement->settlement_number,
                'gateway' => $settlement->gateway,
                'settlement_date' => optional($settlement->settlement_date)?->toDateString(),
                'amount' => (float) $settlement->amount,
                'currency' => $settlement->currency,
                'reference' => $settlement->reference,
                'description' => $settlement->description,
                'status' => $settlement->status,
                'bank_account' => $settlement->bankAccount ? "{$settlement->bankAccount->code} · {$settlement->bankAccount->name}" : null,
                'clearing_account' => $settlement->clearingAccount ? "{$settlement->clearingAccount->code} · {$settlement->clearingAccount->name}" : null,
                'journal_entry_number' => $settlement->journalEntry?->entry_number,
                'recorded_by' => $settlement->recorder?->name,
            ]),
            'bank_account_options' => $this->accountService->bankAccountOptions(),
            'gateway_clearing_options' => $this->accountService->gatewayClearingOptions(),
            'gateway_options' => [
                ['value' => 'paystack', 'label' => 'Paystack'],
                ['value' => 'stripe', 'label' => 'Stripe'],
                ['value' => 'paypal', 'label' => 'PayPal'],
                ['value' => 'manual', 'label' => 'Manual settlement'],
            ],
        ]);
    }

    public function store(StorePaymentGatewaySettlementRequest $request): RedirectResponse
    {
        $this->settlementService->createAndPost($request->validated(), (int) $request->user()->id);

        return back()->with('success', 'Gateway settlement recorded and posted successfully.');
    }
}
