<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_bank_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('transfer_number')->unique();
            $table->date('transfer_date');
            $table->decimal('amount', 20, 4);
            $table->string('currency', 10)->default('NGN');
            $table->foreignId('cash_account_id')->constrained('accounts');
            $table->foreignId('bank_account_id')->constrained('accounts');
            $table->string('reference', 100)->nullable();
            $table->string('description');
            $table->text('note')->nullable();
            $table->string('status', 30)->default('posted');
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['transfer_date', 'status']);
            $table->index(['cash_account_id', 'bank_account_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_bank_transfers');
    }
};
