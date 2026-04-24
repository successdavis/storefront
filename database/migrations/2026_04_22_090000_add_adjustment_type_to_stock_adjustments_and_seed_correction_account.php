<?php

use App\Enums\StockAdjustmentType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_adjustments', function (Blueprint $table) {
            $table->string('adjustment_type', 30)
                ->default(StockAdjustmentType::CORRECTION->value)
                ->after('adjusted_quantity');

            $table->index(['status', 'adjustment_type']);
        });

        DB::table('stock_adjustments')
            ->where('adjusted_quantity', '<', 0)
            ->update(['adjustment_type' => StockAdjustmentType::LOSS->value]);

        DB::table('stock_adjustments')
            ->where('adjusted_quantity', '>', 0)
            ->update(['adjustment_type' => StockAdjustmentType::GAIN->value]);

        $equityHeaderId = DB::table('accounts')->where('code', '3000')->value('id');

        $existingCorrectionAccountId = DB::table('accounts')->where('code', '3120')->value('id');

        if (!$existingCorrectionAccountId) {
            $existingCorrectionAccountId = DB::table('accounts')->insertGetId([
                'code' => '3120',
                'name' => 'Inventory Correction Reserve',
                'slug' => 'inventory-correction-reserve',
                'type' => 'equity',
                'subtype' => 'inventory_correction_reserve',
                'classification' => null,
                'parent_id' => $equityHeaderId,
                'is_active' => true,
                'is_system' => true,
                'allows_manual_entries' => true,
                'currency' => config('accounting.currency', config('app.currency', 'NGN')),
                'description' => 'Balance-sheet reserve used for administrative inventory corrections that should not distort profit and loss.',
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => null,
            ]);
        }

        DB::table('accounting_settings')->updateOrInsert(
            ['key' => 'inventory_correction_reserve'],
            [
                'account_id' => $existingCorrectionAccountId,
                'meta' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        DB::table('accounting_settings')->where('key', 'inventory_correction_reserve')->delete();
        DB::table('accounts')->where('code', '3120')->delete();

        Schema::table('stock_adjustments', function (Blueprint $table) {
            $table->dropIndex(['status', 'adjustment_type']);
            $table->dropColumn('adjustment_type');
        });
    }
};
