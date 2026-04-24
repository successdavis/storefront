<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('type', 20);
            $table->string('subtype', 50)->nullable();
            $table->string('classification', 50)->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false);
            $table->boolean('allows_manual_entries')->default(true);
            $table->string('currency', 10)->nullable();
            $table->text('description')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['type', 'is_active']);
            $table->index(['parent_id', 'code']);
            $table->index(['subtype']);
        });

        Schema::create('accounting_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->foreignId('account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->string('entry_number')->unique();
            $table->string('event_key')->nullable()->unique();
            $table->string('source_event', 80)->nullable();
            $table->date('entry_date');
            $table->date('posting_date')->nullable();
            $table->string('description');
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('status', 20)->default('posted');
            $table->string('currency', 10)->default('NGN');
            $table->decimal('total_debit', 20, 4)->default(0);
            $table->decimal('total_credit', 20, 4)->default(0);
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reversal_of_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['source_type', 'source_id']);
            $table->index(['status', 'entry_date']);
            $table->index(['source_event']);
        });

        Schema::create('journal_entry_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained('journal_entries')->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('accounts')->restrictOnDelete();
            $table->unsignedInteger('line_number')->default(1);
            $table->decimal('debit', 20, 4)->default(0);
            $table->decimal('credit', 20, 4)->default(0);
            $table->string('description')->nullable();
            $table->string('entity_type')->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['journal_entry_id', 'line_number']);
            $table->index(['account_id', 'created_at']);
            $table->index(['entity_type', 'entity_id']);
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('expense_number')->unique();
            $table->date('expense_date');
            $table->decimal('amount', 20, 4);
            $table->string('currency', 10)->default('NGN');
            $table->foreignId('expense_account_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignId('payment_account_id')->constrained('accounts')->restrictOnDelete();
            $table->string('status', 20)->default('posted');
            $table->string('reference')->nullable();
            $table->string('description');
            $table->text('note')->nullable();
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['expense_date', 'status']);
            $table->index(['expense_account_id']);
            $table->index(['payment_account_id']);
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE journal_entry_lines ADD CONSTRAINT chk_journal_line_positive CHECK (debit >= 0 AND credit >= 0)");
            DB::statement("ALTER TABLE journal_entry_lines ADD CONSTRAINT chk_journal_line_one_side CHECK ((debit > 0 AND credit = 0) OR (credit > 0 AND debit = 0))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('journal_entry_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('accounting_settings');
        Schema::dropIfExists('accounts');
    }
};
