<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_recovery_logs', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 100)->index();
            $table->string('action', 40);
            $table->string('status', 20);
            $table->text('message')->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('checkout_session_id')->nullable()->constrained('checkout_sessions')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['reference', 'created_at'], 'idx_payment_recovery_ref_created');
            $table->index(['action', 'status'], 'idx_payment_recovery_action_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_recovery_logs');
    }
};

