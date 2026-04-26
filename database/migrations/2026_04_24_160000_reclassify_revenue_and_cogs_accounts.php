<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            DB::table('accounts')
                ->where('type', 'income')
                ->update(['type' => 'revenue', 'updated_at' => now()]);

            DB::table('accounts')
                ->where(function ($query) {
                    $query->whereIn('code', ['5100', '5110'])
                        ->orWhere('subtype', 'cogs')
                        ->orWhereRaw('LOWER(name) in (?, ?, ?, ?, ?)', [
                            'cost of goods sold',
                            'cogs',
                            'inventory cost',
                            'product cost',
                            'cost of sales',
                        ]);
                })
                ->update(['type' => 'cost_of_goods_sold', 'updated_at' => now()]);

            $operatingExpenseParentId = DB::table('accounts')->where('code', '5400')->value('id');

            if ($operatingExpenseParentId) {
                DB::table('accounts')
                    ->whereIn('code', ['5120', '5130'])
                    ->update([
                        'parent_id' => $operatingExpenseParentId,
                        'type' => 'expense',
                        'updated_at' => now(),
                    ]);
            }
        });
    }

    public function down(): void
    {
        DB::transaction(function () {
            DB::table('accounts')
                ->where('type', 'revenue')
                ->update(['type' => 'income', 'updated_at' => now()]);

            DB::table('accounts')
                ->where('type', 'cost_of_goods_sold')
                ->update(['type' => 'expense', 'updated_at' => now()]);

            $costOfSalesParentId = DB::table('accounts')->where('code', '5100')->value('id');

            if ($costOfSalesParentId) {
                DB::table('accounts')
                    ->whereIn('code', ['5120', '5130'])
                    ->update([
                        'parent_id' => $costOfSalesParentId,
                        'updated_at' => now(),
                    ]);
            }
        });
    }
};
