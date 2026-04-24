<?php

namespace App\Http\Controllers\Admin\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Accounting\StoreAccountRequest;
use App\Http\Requests\Admin\Accounting\UpdateAccountRequest;
use App\Models\Accounting\Account;
use App\Services\Accounting\AccountService;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function __construct(
        protected AccountService $accountService,
    ) {}

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string'],
            'type' => ['nullable', 'string'],
            'status' => ['nullable', 'string'],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
        ]);

        return Inertia::render('Admin/Accounting/Accounts/Index', [
            'filters' => $filters,
            'accounts' => $this->accountService->paginate($filters)->through(fn (Account $account) => [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'slug' => $account->slug,
                'type' => $account->type,
                'subtype' => $account->subtype,
                'classification' => $account->classification,
                'is_active' => (bool) $account->is_active,
                'is_system' => (bool) $account->is_system,
                'allows_manual_entries' => (bool) $account->allows_manual_entries,
                'currency' => $account->currency,
                'description' => $account->description,
                'parent' => $account->parent ? [
                    'id' => $account->parent->id,
                    'code' => $account->parent->code,
                    'name' => $account->parent->name,
                ] : null,
            ]),
            'parent_options' => $this->accountService->hierarchyOptions(),
            'type_options' => [
                ['value' => '', 'label' => 'All account types'],
                ['value' => 'asset', 'label' => 'Asset'],
                ['value' => 'liability', 'label' => 'Liability'],
                ['value' => 'equity', 'label' => 'Equity'],
                ['value' => 'income', 'label' => 'Income'],
                ['value' => 'expense', 'label' => 'Expense'],
            ],
            'status_options' => [
                ['value' => '', 'label' => 'All statuses'],
                ['value' => 'active', 'label' => 'Active'],
                ['value' => 'inactive', 'label' => 'Inactive'],
            ],
        ]);
    }

    public function store(StoreAccountRequest $request): RedirectResponse
    {
        $this->accountService->create([
            ...$request->validated(),
            'is_active' => $request->boolean('is_active', true),
            'is_system' => $request->boolean('is_system', false),
            'allows_manual_entries' => $request->boolean('allows_manual_entries', true),
        ]);

        return back()->with('success', 'Account created successfully.');
    }

    public function update(UpdateAccountRequest $request, Account $account): RedirectResponse
    {
        $this->accountService->update($account, $request->validated());

        return back()->with('success', 'Account updated successfully.');
    }

    public function toggle(Account $account): RedirectResponse
    {
        $this->accountService->toggleActive($account);

        return back()->with('success', 'Account status updated.');
    }
}
