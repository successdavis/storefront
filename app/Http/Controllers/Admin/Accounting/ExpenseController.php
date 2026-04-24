<?php

namespace App\Http\Controllers\Admin\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Accounting\StoreExpenseRequest;
use App\Services\Accounting\AccountService;
use App\Services\Accounting\AccountingService;
use App\Services\Accounting\ExpenseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseController extends Controller
{
    public function __construct(
        protected ExpenseService $expenseService,
        protected AccountService $accountService,
        protected AccountingService $accountingService,
    ) {}

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string'],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
        ]);

        return Inertia::render('Admin/Accounting/Expenses/Index', [
            'filters' => $filters,
            'expenses' => $this->expenseService->paginate($filters)->through(fn ($expense) => [
                'id' => $expense->id,
                'expense_number' => $expense->expense_number,
                'expense_date' => optional($expense->expense_date)?->toDateString(),
                'amount' => (float) $expense->amount,
                'currency' => $expense->currency,
                'description' => $expense->description,
                'reference' => $expense->reference,
                'status' => $expense->status,
                'expense_account' => $expense->expenseAccount ? "{$expense->expenseAccount->code} · {$expense->expenseAccount->name}" : null,
                'payment_account' => $expense->paymentAccount ? "{$expense->paymentAccount->code} · {$expense->paymentAccount->name}" : null,
                'recorded_by' => $expense->recorder?->name,
            ]),
            'account_options' => $this->accountService->manualPostingAccounts(),
        ]);
    }

    public function store(StoreExpenseRequest $request): RedirectResponse
    {
        $expense = $this->expenseService->create($request->validated(), (int) $request->user()->id);
        $this->accountingService->postExpense($expense, (int) $request->user()->id);

        return back()->with('success', 'Expense recorded and posted successfully.');
    }
}
