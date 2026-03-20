<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_audit_sessions', function (Blueprint $table) {
            $table->timestamp('last_activity_at')
                ->nullable()
                ->after('submitted_at')
                ->index();
        });
    }

    public function down(): void
    {
        Schema::table('stock_audit_sessions', function (Blueprint $table) {
            $table->dropIndex(['last_activity_at']);
            $table->dropColumn('last_activity_at');
        });
    }
};
