<?php

namespace App\Http\Controllers\Admin\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Accounting\StoreCashBankTransferRequest;
use App\Services\Accounting\AccountService;
use App\Services\Accounting\CashBankTransferService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CashBankTransferController extends Controller
{
    public function __construct(
        protected CashBankTransferService $transferService,
        protected AccountService $accountService,
    ) {}

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string'],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
        ]);

        return Inertia::render('Admin/Accounting/CashBankTransfers/Index', [
            'filters' => $filters,
            'transfers' => $this->transferService->paginate($filters)->through(fn ($transfer) => [
                'id' => $transfer->id,
                'transfer_number' => $transfer->transfer_number,
                'transfer_date' => optional($transfer->transfer_date)?->toDateString(),
                'amount' => (float) $transfer->amount,
                'currency' => $transfer->currency,
                'reference' => $transfer->reference,
                'description' => $transfer->description,
                'status' => $transfer->status,
                'cash_account' => $transfer->cashAccount ? "{$transfer->cashAccount->code} · {$transfer->cashAccount->name}" : null,
                'bank_account' => $transfer->bankAccount ? "{$transfer->bankAccount->code} · {$transfer->bankAccount->name}" : null,
                'journal_entry_number' => $transfer->journalEntry?->entry_number,
                'recorded_by' => $transfer->recorder?->name,
            ]),
            'cash_account_options' => $this->accountService->cashAccountOptions(),
            'bank_account_options' => $this->accountService->bankAccountOptions(),
        ]);
    }

    public function store(StoreCashBankTransferRequest $request): RedirectResponse
    {
        $this->transferService->createAndPost($request->validated(), (int) $request->user()->id);

        return back()->with('success', 'Cash deposit transfer recorded and posted successfully.');
    }
}
