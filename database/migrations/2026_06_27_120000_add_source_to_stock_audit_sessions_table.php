<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_audit_sessions', function (Blueprint $table): void {
            if (! Schema::hasColumn('stock_audit_sessions', 'source')) {
                $table->string('source', 20)
                    ->nullable()
                    ->after('status')
                    ->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('stock_audit_sessions', function (Blueprint $table): void {
            if (Schema::hasColumn('stock_audit_sessions', 'source')) {
                $table->dropIndex(['source']);
                $table->dropColumn('source');
            }
        });
    }
};
