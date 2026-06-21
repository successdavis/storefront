<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('product_variants', 'replenishment_status')) {
            Schema::table('product_variants', function (Blueprint $table): void {
                $table->string('replenishment_status', 32)
                    ->default('reorderable')
                    ->after('reorder_point')
                    ->index();
                $table->text('replenishment_note')->nullable()->after('replenishment_status');
            });
        }

        Schema::table('inventory_alerts', function (Blueprint $table): void {
            if (! Schema::hasColumn('inventory_alerts', 'acknowledged_at')) {
                $table->timestamp('acknowledged_at')->nullable()->after('last_seen_at');
            }

            if (! Schema::hasColumn('inventory_alerts', 'acknowledged_by')) {
                $table->foreignId('acknowledged_by')
                    ->nullable()
                    ->after('acknowledged_at')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('inventory_alerts', 'snoozed_until')) {
                $table->timestamp('snoozed_until')->nullable()->after('acknowledged_by')->index();
            }

            if (! Schema::hasColumn('inventory_alerts', 'snoozed_by')) {
                $table->foreignId('snoozed_by')
                    ->nullable()
                    ->after('snoozed_until')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('inventory_alerts', 'snooze_reason')) {
                $table->string('snooze_reason')->nullable()->after('snoozed_by');
            }

            if (! Schema::hasColumn('inventory_alerts', 'suppressed_at')) {
                $table->timestamp('suppressed_at')->nullable()->after('snooze_reason')->index();
            }

            if (! Schema::hasColumn('inventory_alerts', 'suppressed_by')) {
                $table->foreignId('suppressed_by')
                    ->nullable()
                    ->after('suppressed_at')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('inventory_alerts', 'suppress_reason')) {
                $table->string('suppress_reason')->nullable()->after('suppressed_by');
            }

            if (! Schema::hasColumn('inventory_alerts', 'resolved_reason')) {
                $table->string('resolved_reason')->nullable()->after('resolved_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('inventory_alerts', function (Blueprint $table): void {
            if (Schema::hasColumn('inventory_alerts', 'acknowledged_by')) {
                $table->dropConstrainedForeignId('acknowledged_by');
            }

            if (Schema::hasColumn('inventory_alerts', 'snoozed_by')) {
                $table->dropConstrainedForeignId('snoozed_by');
            }

            if (Schema::hasColumn('inventory_alerts', 'suppressed_by')) {
                $table->dropConstrainedForeignId('suppressed_by');
            }

            $columns = [
                'acknowledged_at',
                'snoozed_until',
                'snooze_reason',
                'suppressed_at',
                'suppress_reason',
                'resolved_reason',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('inventory_alerts', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        if (Schema::hasColumn('product_variants', 'replenishment_status')) {
            Schema::table('product_variants', function (Blueprint $table): void {
                $table->dropColumn(['replenishment_status', 'replenishment_note']);
            });
        }
    }
};
