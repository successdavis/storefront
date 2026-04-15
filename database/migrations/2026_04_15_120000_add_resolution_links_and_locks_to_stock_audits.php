<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_audit_items', function (Blueprint $table) {
            $table->foreignId('stock_adjustment_id')
                ->nullable()
                ->after('variance')
                ->constrained('stock_adjustments')
                ->nullOnDelete();

            $table->text('conflict_reason')
                ->nullable()
                ->after('stock_adjustment_id');

            $table->foreignId('conflicted_with_session_id')
                ->nullable()
                ->after('conflict_reason')
                ->constrained('stock_audit_sessions')
                ->nullOnDelete();

            $table->unique('stock_adjustment_id');
        });

        Schema::create('stock_audit_item_locks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')
                ->constrained('stock_audit_sessions')
                ->cascadeOnDelete();
            $table->foreignId('variant_id')
                ->constrained('product_variants')
                ->cascadeOnDelete();
            $table->foreignId('warehouse_id')
                ->nullable()
                ->constrained('warehouses')
                ->nullOnDelete();
            $table->unsignedBigInteger('warehouse_scope_key')->default(0);
            $table->timestamps();

            $table->unique(['variant_id', 'warehouse_scope_key'], 'stock_audit_item_locks_variant_scope_unique');
            $table->unique(['session_id', 'variant_id'], 'stock_audit_item_locks_session_variant_unique');
        });

        $this->backfillAuditItemAdjustmentLinks();
        $this->backfillActiveAuditLocks();
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_audit_item_locks');

        Schema::table('stock_audit_items', function (Blueprint $table) {
            $table->dropUnique('stock_audit_items_stock_adjustment_id_unique');
            $table->dropConstrainedForeignId('stock_adjustment_id');
            $table->dropConstrainedForeignId('conflicted_with_session_id');
            $table->dropColumn('conflict_reason');
        });
    }

    protected function backfillAuditItemAdjustmentLinks(): void
    {
        DB::table('stock_adjustments')
            ->select(['id', 'variant_id', 'reference'])
            ->where('reason', 'count_discrepancy')
            ->whereNotNull('reference')
            ->orderBy('id')
            ->get()
            ->each(function (object $adjustment): void {
                if (!preg_match('/^AUDIT-(\d+)-(\d+)$/', (string) $adjustment->reference, $matches)) {
                    return;
                }

                $sessionId = (int) $matches[1];
                $variantId = (int) $matches[2];

                $itemId = DB::table('stock_audit_items')
                    ->where('session_id', $sessionId)
                    ->where('variant_id', $variantId)
                    ->whereNull('stock_adjustment_id')
                    ->value('id');

                if (!$itemId) {
                    return;
                }

                DB::table('stock_audit_items')
                    ->where('id', $itemId)
                    ->update([
                        'stock_adjustment_id' => $adjustment->id,
                        'updated_at' => now(),
                    ]);
            });
    }

    protected function backfillActiveAuditLocks(): void
    {
        $sessions = DB::table('stock_audit_sessions')
            ->select(['id', 'warehouse_id'])
            ->whereIn('status', ['in_progress', 'submitted'])
            ->orderBy('id')
            ->get();

        foreach ($sessions as $session) {
            $warehouseId = $session->warehouse_id ? (int) $session->warehouse_id : null;
            $warehouseScopeKey = $warehouseId ?? 0;

            $items = DB::table('stock_audit_items')
                ->select(['id', 'variant_id'])
                ->where('session_id', $session->id)
                ->orderBy('id')
                ->get();

            foreach ($items as $item) {
                try {
                    DB::table('stock_audit_item_locks')->insert([
                        'session_id' => $session->id,
                        'variant_id' => $item->variant_id,
                        'warehouse_id' => $warehouseId,
                        'warehouse_scope_key' => $warehouseScopeKey,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } catch (QueryException) {
                    $conflictingSessionId = DB::table('stock_audit_item_locks')
                        ->where('variant_id', $item->variant_id)
                        ->where('warehouse_scope_key', $warehouseScopeKey)
                        ->value('session_id');

                    DB::table('stock_audit_items')
                        ->where('id', $item->id)
                        ->update([
                            'conflict_reason' => sprintf(
                                'This item overlaps with audit session #%d for the same warehouse scope.',
                                (int) $conflictingSessionId
                            ),
                            'conflicted_with_session_id' => $conflictingSessionId,
                            'updated_at' => now(),
                        ]);
                }
            }
        }
    }
};
