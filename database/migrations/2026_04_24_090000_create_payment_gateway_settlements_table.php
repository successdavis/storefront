<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_gateway_settlements', function (Blueprint $table) {
            $table->id();
            $table->string('settlement_number')->unique();
            $table->string('gateway', 50)->default('paystack');
            $table->date('settlement_date');
            $table->decimal('amount', 20, 4);
            $table->string('currency', 10)->default(config('accounting.currency', config('app.currency', 'NGN')));
            $table->foreignId('bank_account_id')->constrained('accounts');
            $table->foreignId('clearing_account_id')->constrained('accounts');
            $table->string('reference', 100)->nullable()->index();
            $table->string('status', 20)->default('posted')->index();
            $table->text('description');
            $table->text('note')->nullable();
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['settlement_date', 'gateway']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_gateway_settlements');
    }
};
