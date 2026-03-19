<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('checkout_sessions', function (Blueprint $table) {
            if (!Schema::hasColumn('checkout_sessions', 'shipping_snapshot')) {
                $table->json('shipping_snapshot')->nullable()->after('discount_snapshot');
            }

            if (!Schema::hasColumn('checkout_sessions', 'payment_status')) {
                $table->string('payment_status', 40)->default('pending')->after('reference');
            }

            if (!Schema::hasColumn('checkout_sessions', 'payment_verified_at')) {
                $table->timestampTz('payment_verified_at')->nullable()->after('payment_status');
            }

            if (!Schema::hasColumn('checkout_sessions', 'payment_amount')) {
                $table->decimal('payment_amount', 14, 2)->nullable()->after('payment_verified_at');
            }

            if (!Schema::hasColumn('checkout_sessions', 'payment_currency')) {
                $table->char('payment_currency', 3)->nullable()->after('payment_amount');
            }

            if (!Schema::hasColumn('checkout_sessions', 'verification_payload')) {
                $table->json('verification_payload')->nullable()->after('payment_currency');
            }

            if (!Schema::hasColumn('checkout_sessions', 'order_id')) {
                $table->foreignId('order_id')->nullable()->after('verification_payload')->constrained('orders')->nullOnDelete();
            }

            if (!Schema::hasColumn('checkout_sessions', 'processed_at')) {
                $table->timestampTz('processed_at')->nullable()->after('order_id');
            }

            if (!Schema::hasColumn('checkout_sessions', 'processing_error')) {
                $table->text('processing_error')->nullable()->after('processed_at');
            }

            if (!Schema::hasColumn('checkout_sessions', 'retry_count')) {
                $table->unsignedInteger('retry_count')->default(0)->after('processing_error');
            }
        });

        Schema::table('checkout_sessions', function (Blueprint $table) {
            $table->index('payment_status', 'idx_checkout_sessions_payment_status');
            $table->index(['reference', 'used'], 'idx_checkout_sessions_reference_used');
            $table->unique('reference', 'uq_checkout_sessions_reference');
        });
    }

    public function down(): void
    {
        Schema::table('checkout_sessions', function (Blueprint $table) {
            $table->dropUnique('uq_checkout_sessions_reference');
            $table->dropIndex('idx_checkout_sessions_payment_status');
            $table->dropIndex('idx_checkout_sessions_reference_used');
        });

        Schema::table('checkout_sessions', function (Blueprint $table) {
            if (Schema::hasColumn('checkout_sessions', 'retry_count')) {
                $table->dropColumn('retry_count');
            }
            if (Schema::hasColumn('checkout_sessions', 'processing_error')) {
                $table->dropColumn('processing_error');
            }
            if (Schema::hasColumn('checkout_sessions', 'processed_at')) {
                $table->dropColumn('processed_at');
            }
            if (Schema::hasColumn('checkout_sessions', 'order_id')) {
                $table->dropConstrainedForeignId('order_id');
            }
            if (Schema::hasColumn('checkout_sessions', 'verification_payload')) {
                $table->dropColumn('verification_payload');
            }
            if (Schema::hasColumn('checkout_sessions', 'payment_currency')) {
                $table->dropColumn('payment_currency');
            }
            if (Schema::hasColumn('checkout_sessions', 'payment_amount')) {
                $table->dropColumn('payment_amount');
            }
            if (Schema::hasColumn('checkout_sessions', 'payment_verified_at')) {
                $table->dropColumn('payment_verified_at');
            }
            if (Schema::hasColumn('checkout_sessions', 'payment_status')) {
                $table->dropColumn('payment_status');
            }
            if (Schema::hasColumn('checkout_sessions', 'shipping_snapshot')) {
                $table->dropColumn('shipping_snapshot');
            }
        });
    }
};

