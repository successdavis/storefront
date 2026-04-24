<?php

namespace App\Http\Controllers\Admin\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Accounting\StoreJournalEntryRequest;
use App\Models\Accounting\JournalEntry;
use App\Services\Accounting\AccountService;
use App\Services\Accounting\JournalPostingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class JournalEntryController extends Controller
{
    public function __construct(
        protected JournalPostingService $journalPostingService,
        protected AccountService $accountService,
    ) {}

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string'],
            'status' => ['nullable', 'in:draft,posted,reversed'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $entries = JournalEntry::query()
            ->with(['lines.account:id,code,name', 'postedBy:id,name'])
            ->when(filled($filters['search'] ?? null), function ($query) use ($filters) {
                $search = trim((string) $filters['search']);
                $query->where(function ($builder) use ($search) {
                    $builder->where('entry_number', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('source_type', 'like', "%{$search}%");
                });
            })
            ->when(filled($filters['status'] ?? null), fn ($query) => $query->where('status', $filters['status']))
            ->when(filled($filters['from'] ?? null), fn ($query) => $query->whereDate('posting_date', '>=', $filters['from']))
            ->when(filled($filters['to'] ?? null), fn ($query) => $query->whereDate('posting_date', '<=', $filters['to']))
            ->latest('posting_date')
            ->latest('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (JournalEntry $entry) => [
                'id' => $entry->id,
                'entry_number' => $entry->entry_number,
                'entry_date' => optional($entry->entry_date)?->toDateString(),
                'posting_date' => optional($entry->posting_date)?->toDateString(),
                'description' => $entry->description,
                'source_type' => $entry->source_type,
                'source_id' => $entry->source_id,
                'source_event' => $entry->source_event,
                'status' => $entry->status,
                'currency' => $entry->currency,
                'total_debit' => (float) $entry->total_debit,
                'total_credit' => (float) $entry->total_credit,
                'posted_by' => $entry->postedBy?->name,
                'lines_preview' => $entry->lines->take(3)->map(fn ($line) => [
                    'account' => "{$line->account?->code} - {$line->account?->name}",
                    'debit' => (float) $line->debit,
                    'credit' => (float) $line->credit,
                ])->values()->all(),
            ]);

        return Inertia::render('Admin/Accounting/JournalEntries/Index', [
            'filters' => $filters,
            'entries' => $entries,
            'account_options' => $this->accountService->manualPostingAccounts(),
        ]);
    }

    public function show(JournalEntry $journalEntry): Response
    {
        $journalEntry->load([
            'lines.account:id,code,name,type',
            'postedBy:id,name',
            'reversedBy:id,name',
            'reversalOf:id,entry_number',
        ]);

        return Inertia::render('Admin/Accounting/JournalEntries/Show', [
            'entry' => [
                'id' => $journalEntry->id,
                'entry_number' => $journalEntry->entry_number,
                'entry_date' => optional($journalEntry->entry_date)?->toDateString(),
                'posting_date' => optional($journalEntry->posting_date)?->toDateString(),
                'description' => $journalEntry->description,
                'status' => $journalEntry->status,
                'currency' => $journalEntry->currency,
                'source_type' => $journalEntry->source_type,
                'source_id' => $journalEntry->source_id,
                'source_event' => $journalEntry->source_event,
                'total_debit' => (float) $journalEntry->total_debit,
                'total_credit' => (float) $journalEntry->total_credit,
                'posted_by' => $journalEntry->postedBy?->name,
                'reversed_by' => $journalEntry->reversedBy?->name,
                'reversal_of' => $journalEntry->reversalOf ? [
                    'id' => $journalEntry->reversalOf->id,
                    'entry_number' => $journalEntry->reversalOf->entry_number,
                ] : null,
                'meta' => $journalEntry->meta,
                'lines' => $journalEntry->lines->map(fn ($line) => [
                    'id' => $line->id,
                    'line_number' => $line->line_number,
                    'account_code' => $line->account?->code,
                    'account_name' => $line->account?->name,
                    'account_type' => $line->account?->type,
                    'description' => $line->description,
                    'debit' => (float) $line->debit,
                    'credit' => (float) $line->credit,
                    'meta' => $line->meta,
                ])->values()->all(),
            ],
        ]);
    }

    public function store(StoreJournalEntryRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $this->journalPostingService->post([
            'entry_date' => $validated['entry_date'],
            'posting_date' => $validated['entry_date'],
            'description' => $validated['description'],
            'status' => $validated['status'] ?? JournalEntry::STATUS_POSTED,
            'currency' => $validated['currency'] ?? config('accounting.currency', 'NGN'),
            'posted_by' => $request->user()->id,
            'source_event' => 'manual_journal',
            'meta' => ['manual' => true],
        ], $validated['lines']);

        return back()->with('success', 'Journal entry posted successfully.');
    }
}
