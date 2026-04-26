<?php

namespace App\Http\Controllers\Admin\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Account;
use App\Models\CustomerInvoice;
use App\Models\Accounting\JournalEntry;
use App\Services\Accounting\AccountService;
use App\Services\Accounting\AccountingDashboardService;
use App\Services\Accounting\CashSummaryService;
use App\Services\Accounting\FinancialStatementService;
use App\Services\Accounting\HistoricalAccountingSyncService;
use App\Services\Accounting\LedgerQueryService;
use App\Services\Accounting\ReceivablesReportService;
use App\Services\Accounting\TrialBalanceService;
use App\Services\Reports\InventoryValuationReportService;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response as HttpResponse;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Str;

class ReportController extends Controller
{
    public function __construct(
        protected AccountService $accountService,
        protected AccountingDashboardService $accountingDashboardService,
        protected CashSummaryService $cashSummaryService,
        protected TrialBalanceService $trialBalanceService,
        protected LedgerQueryService $ledgerQueryService,
        protected ReceivablesReportService $receivablesReportService,
        protected FinancialStatementService $financialStatementService,
        protected HistoricalAccountingSyncService $historicalAccountingSyncService,
        protected InventoryValuationReportService $inventoryValuationReportService,
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
                ['key' => 'revenue', 'label' => 'Revenue', 'value' => round((float) $incomeStatement['revenue_total'], 2)],
                ['key' => 'operating_expense', 'label' => 'Operating Expenses', 'value' => round((float) $incomeStatement['operating_expenses_total'], 2)],
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

    public function charts(Request $request): Response
    {
        $filters = $this->resolveChartFilters($request);

        return Inertia::render('Admin/Accounting/Charts', [
            'report' => $this->accountingDashboardService->report($filters),
        ]);
    }

    public function cashSummary(Request $request): Response
    {
        $filters = $request->validate([
            'date' => ['nullable', 'date'],
        ]);

        return Inertia::render('Admin/Accounting/Reports/CashSummary', [
            'report' => $this->cashSummaryService->dailySummary($filters),
        ]);
    }

    public function receivables(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in([
                CustomerInvoice::STATUS_UNPAID,
                CustomerInvoice::STATUS_PARTIALLY_PAID,
                CustomerInvoice::STATUS_PAID,
                CustomerInvoice::STATUS_OVERDUE,
            ])],
            'customer_id' => ['nullable', 'integer', 'exists:users,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'as_of' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
        ]);

        return Inertia::render('Admin/Accounting/Reports/Receivables', [
            'report' => $this->receivablesReportService->indexReport($filters),
            'customer_options' => User::query()
                ->role(\App\Support\RoleNames::CUSTOMER)
                ->select('id', 'name', 'email', 'phone')
                ->orderBy('name')
                ->limit(200)
                ->get()
                ->map(fn (User $customer) => [
                    'id' => (int) $customer->id,
                    'name' => $customer->name,
                    'email' => $customer->email,
                    'phone' => $customer->phone,
                ])
                ->values()
                ->all(),
            'payment_method_options' => collect(\App\Services\OrderManagementService::PAYMENT_METHODS)
                ->map(fn (string $method) => [
                    'value' => $method,
                    'label' => str($method)->headline()->value(),
                ])
                ->values()
                ->all(),
        ]);
    }

    public function inventoryValuation(Request $request): Response
    {
        $filters = $request->validate([
            'as_of' => ['nullable', 'date'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
        ]);

        return Inertia::render('Admin/Accounting/Reports/InventoryValuation', [
            'categories' => $this->inventoryValuationReportService->categoryOptions(),
            'report' => $this->inventoryValuationReportService->report($filters),
        ]);
    }

    public function exportInventoryValuation(Request $request): HttpResponse
    {
        $filters = $request->validate([
            'as_of' => ['nullable', 'date'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
        ]);

        $payload = $this->inventoryValuationReportService->exportPayload($filters, $request->user());
        $filename = sprintf(
            'inventory-valuation-%s-%s.pdf',
            Str::slug(data_get($payload, 'selected_category.name', 'all-categories')),
            now()->format('YmdHis'),
        );

        $pdf = Pdf::loadView('reports.inventory-valuation', $payload)
            ->setPaper('a4', 'landscape');

        return $pdf->stream($filename);
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

    protected function resolveChartFilters(Request $request): array
    {
        $validated = $request->validate([
            'period' => ['nullable', 'string', Rule::in(['this_month', 'last_month', 'this_year', 'last_year', 'all_time', 'custom'])],
            'expense_period' => ['nullable', 'string', Rule::in(['selected_range', 'last_3_months', 'last_6_months', 'last_12_months', 'this_year', 'all_time'])],
            'profit_loss_period' => ['nullable', 'string', Rule::in(['selected_range', 'this_month', 'last_month', 'this_year', 'last_year', 'all_time'])],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $period = $validated['period'] ?? null;
        $from = $validated['from'] ?? null;
        $to = $validated['to'] ?? null;

        if ($period === null) {
            $period = ($from !== null || $to !== null) ? 'custom' : 'this_year';
        }

        [$resolvedFrom, $resolvedTo] = match ($period) {
            'this_month' => [
                now()->startOfMonth()->toDateString(),
                now()->toDateString(),
            ],
            'last_month' => [
                now()->subMonthNoOverflow()->startOfMonth()->toDateString(),
                now()->subMonthNoOverflow()->endOfMonth()->toDateString(),
            ],
            'last_year' => [
                now()->subYear()->startOfYear()->toDateString(),
                now()->subYear()->endOfYear()->toDateString(),
            ],
            'all_time' => $this->resolveAllTimePeriod(),
            'custom' => [
                $from ?? now()->startOfYear()->toDateString(),
                $to ?? now()->toDateString(),
            ],
            default => [
                now()->startOfYear()->toDateString(),
                now()->toDateString(),
            ],
        };

        return [
            'period' => $period,
            'expense_period' => $validated['expense_period'] ?? 'last_6_months',
            'profit_loss_period' => $validated['profit_loss_period'] ?? 'selected_range',
            'from' => Carbon::parse($resolvedFrom)->toDateString(),
            'to' => Carbon::parse($resolvedTo)->toDateString(),
        ];
    }
}
