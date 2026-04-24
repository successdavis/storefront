<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $equityParentId = DB::table('accounts')->where('code', '3000')->value('id');

            DB::table('accounts')->updateOrInsert(
                ['code' => '3130'],
                [
                    'name' => 'Opening Balance Equity',
                    'slug' => Str::slug('Opening Balance Equity'),
                    'type' => 'equity',
                    'subtype' => 'opening_balance_equity',
                    'classification' => null,
                    'parent_id' => $equityParentId,
                    'is_active' => true,
                    'is_system' => true,
                    'allows_manual_entries' => true,
                    'currency' => config('accounting.currency', config('app.currency', 'NGN')),
                    'description' => 'Balancing equity account for historical opening balances and setup stock values.',
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );

            $openingBalanceEquityId = DB::table('accounts')->where('code', '3130')->value('id');
            $ownerCapitalId = DB::table('accounts')->where('code', '3110')->value('id');

            if ($openingBalanceEquityId) {
                DB::table('accounting_settings')->updateOrInsert(
                    ['key' => 'opening_balance_equity'],
                    ['account_id' => $openingBalanceEquityId, 'updated_at' => now(), 'created_at' => now()]
                );
            }

            if (!$openingBalanceEquityId || !$ownerCapitalId) {
                return;
            }

            DB::table('journal_entry_lines')
                ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
                ->where('journal_entry_lines.account_id', $ownerCapitalId)
                ->where(function ($query) {
                    $query->where('journal_entries.source_event', 'opening_balance_posted')
                        ->orWhere('journal_entries.source_type', 'App\\Models\\OpeningBalance')
                        ->orWhere('journal_entries.event_key', 'like', 'opening_balance:%:posted');
                })
                ->update([
                    'journal_entry_lines.account_id' => $openingBalanceEquityId,
                    'journal_entry_lines.updated_at' => now(),
                ]);
        });
    }

    public function down(): void
    {
        DB::transaction(function () {
            $openingBalanceEquityId = DB::table('accounts')->where('code', '3130')->value('id');
            $ownerCapitalId = DB::table('accounts')->where('code', '3110')->value('id');

            if ($openingBalanceEquityId && $ownerCapitalId) {
                DB::table('journal_entry_lines')
                    ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
                    ->where('journal_entry_lines.account_id', $openingBalanceEquityId)
                    ->where(function ($query) {
                        $query->where('journal_entries.source_event', 'opening_balance_posted')
                            ->orWhere('journal_entries.source_type', 'App\\Models\\OpeningBalance')
                            ->orWhere('journal_entries.event_key', 'like', 'opening_balance:%:posted');
                    })
                    ->update([
                        'journal_entry_lines.account_id' => $ownerCapitalId,
                        'journal_entry_lines.updated_at' => now(),
                    ]);
            }

            if ($ownerCapitalId) {
                DB::table('accounting_settings')->updateOrInsert(
                    ['key' => 'opening_balance_equity'],
                    ['account_id' => $ownerCapitalId, 'updated_at' => now(), 'created_at' => now()]
                );
            }
        });
    }
};
