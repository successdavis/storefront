<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_audit_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')
                ->nullable()
                ->constrained('warehouses')
                ->nullOnDelete();
            $table->enum('scope_type', ['full', 'category'])->default('full');
            $table->foreignId('category_id')
                ->nullable()
                ->constrained('categories')
                ->nullOnDelete();
            $table->enum('status', ['in_progress', 'submitted', 'reviewed'])->default('in_progress');
            $table->unsignedInteger('total_expected_items')->default(0);
            $table->unsignedInteger('total_scanned_items')->default(0);
            $table->decimal('coverage_percentage', 5, 2)->default(0);
            $table->boolean('is_partial')->default(false);
            $table->foreignId('started_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('submitted_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'scope_type']);
            $table->index(['started_by', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_audit_sessions');
    }
};
