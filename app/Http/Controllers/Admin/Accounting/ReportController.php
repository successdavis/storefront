<?php

namespace App\Http\Controllers\Admin\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Services\Accounting\AccountService;
use App\Services\Accounting\FinancialStatementService;
use App\Services\Accounting\HistoricalAccountingSyncService;
use App\Services\Accounting\LedgerQueryService;
use App\Services\Accounting\TrialBalanceService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function __construct(
        protected AccountService $accountService,
        protected TrialBalanceService $trialBalanceService,
        protected LedgerQueryService $ledgerQueryService,
        protected FinancialStatementService $financialStatementService,
        protected HistoricalAccountingSyncService $historicalAccountingSyncService,
    ) {}

    public function overview(Request $request): Response
    {
        $trial = $this->trialBalanceService->report($request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]));

        $incomeStatement = $this->financialStatementService->incomeStatement($trial['filters']);

        return Inertia::render('Admin/Accounting/Index', [
            'filters' => $trial['filters'],
            'can_sync_history' => (bool) $request->user()?->can('admin.accounting.manage'),
            'history_sync' => [
                'pending' => $this->historicalAccountingSyncService->pendingSummary(),
            ],
            'summary_cards' => [
                ['key' => 'accounts', 'label' => 'Active accounts', 'value' => Account::query()->where('is_active', true)->count()],
                ['key' => 'journals', 'label' => 'Posted journals', 'value' => JournalEntry::query()->where('status', 'posted')->count()],
                ['key' => 'income', 'label' => 'Income', 'value' => round(collect($incomeStatement['income'])->sum('amount'), 2)],
                ['key' => 'expense', 'label' => 'Expenses', 'value' => round(collect($incomeStatement['expenses'])->sum('amount'), 2)],
            ],
            'recent_entries' => JournalEntry::query()
                ->latest('posting_date')
                ->latest('id')
                ->limit(8)
                ->get(['id', 'entry_number', 'description', 'posting_date', 'status', 'total_debit', 'total_credit'])
                ->map(fn (JournalEntry $entry) => [
                    'id' => $entry->id,
                    'entry_number' => $entry->entry_number,
                    'description' => $entry->description,
                    'posting_date' => optional($entry->posting_date)?->toDateString(),
                    'status' => $entry->status,
                    'total_debit' => (float) $entry->total_debit,
                    'total_credit' => (float) $entry->total_credit,
                ])
                ->values()
                ->all(),
        ]);
    }

    public function syncHistory(Request $request)
    {
        $result = $this->historicalAccountingSyncService->sync($request->user()?->id);

        return redirect()
            ->route('admin.accounting.index')
            ->with('success', "Historical accounting sync completed. {$result['total_posted']} journal workflows were synchronized.");
    }

    public function ledger(Request $request): Response
    {
        $filters = $request->validate([
            'account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $statement = null;
        if (!empty($filters['account_id'])) {
            $statement = $this->ledgerQueryService->statement(Account::query()->findOrFail((int) $filters['account_id']), $filters);
        }

        return Inertia::render('Admin/Accounting/Reports/Ledger', [
            'filters' => $filters,
            'account_options' => $this->accountService->manualPostingAccounts(),
            'statement' => $statement,
        ]);
    }

    public function trialBalance(Request $request): Response
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        return Inertia::render('Admin/Accounting/Reports/TrialBalance', [
            'report' => $this->trialBalanceService->report($filters),
        ]);
    }

    public function profitAndLoss(Request $request): Response
    {
        $filters = $this->resolveProfitAndLossFilters($request);
        $report = $this->financialStatementService->incomeStatement($filters);
        $report['filters']['period'] = $filters['period'];

        return Inertia::render('Admin/Accounting/Reports/ProfitAndLoss', [
            'report' => $report,
        ]);
    }

    public function balanceSheet(Request $request): Response
    {
        $filters = $request->validate([
            'as_of' => ['nullable', 'date'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $filters = [
            'as_of' => $filters['as_of'] ?? $filters['to'] ?? now()->toDateString(),
        ];

        return Inertia::render('Admin/Accounting/Reports/BalanceSheet', [
            'report' => $this->financialStatementService->balanceSheet($filters),
        ]);
    }

    protected function resolveProfitAndLossFilters(Request $request): array
    {
        $validated = $request->validate([
            'period' => ['nullable', 'string', Rule::in(['this_month', 'last_month', 'this_year', 'last_year', 'all_time', 'custom'])],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $period = $validated['period'] ?? null;
        $from = $validated['from'] ?? null;
        $to = $validated['to'] ?? null;

        if ($period === null) {
            $period = ($from !== null || $to !== null) ? 'custom' : 'this_month';
        }

        [$resolvedFrom, $resolvedTo] = match ($period) {
            'last_month' => [
                now()->subMonthNoOverflow()->startOfMonth()->toDateString(),
                now()->subMonthNoOverflow()->endOfMonth()->toDateString(),
            ],
            'this_year' => [
                now()->startOfYear()->toDateString(),
                now()->toDateString(),
            ],
            'last_year' => [
                now()->subYear()->startOfYear()->toDateString(),
                now()->subYear()->endOfYear()->toDateString(),
            ],
            'all_time' => $this->resolveAllTimePeriod(),
            'custom' => [
                $from ?? now()->startOfMonth()->toDateString(),
                $to ?? now()->toDateString(),
            ],
            default => [
                now()->startOfMonth()->toDateString(),
                now()->toDateString(),
            ],
        };

        return [
            'period' => $period,
            'from' => Carbon::parse($resolvedFrom)->toDateString(),
            'to' => Carbon::parse($resolvedTo)->toDateString(),
        ];
    }

    protected function resolveAllTimePeriod(): array
    {
        $firstPostingDate = JournalEntry::query()
            ->where('status', 'posted')
            ->min('posting_date');

        $lastPostingDate = JournalEntry::query()
            ->where('status', 'posted')
            ->max('posting_date');

        if ($firstPostingDate === null || $lastPostingDate === null) {
            return [
                now()->startOfMonth()->toDateString(),
                now()->toDateString(),
            ];
        }

        return [
            Carbon::parse($firstPostingDate)->toDateString(),
            Carbon::parse($lastPostingDate)->toDateString(),
        ];
    }
}
