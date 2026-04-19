<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (!Schema::hasColumn('users', 'status')) {
                    $table->string('status', 32)->default('active')->after('email_verified_at')->index();
                }

                if (!Schema::hasColumn('users', 'last_login_at')) {
                    $table->timestamp('last_login_at')->nullable()->after('remember_token')->index();
                }

                if (!Schema::hasColumn('users', 'last_login_ip')) {
                    $table->string('last_login_ip', 45)->nullable()->after('last_login_at');
                }

                if (!Schema::hasColumn('users', 'login_count')) {
                    $table->unsignedInteger('login_count')->default(0)->after('last_login_ip');
                }

                if (!Schema::hasColumn('users', 'is_vip')) {
                    $table->boolean('is_vip')->default(false)->after('login_count')->index();
                }

                if (!Schema::hasColumn('users', 'is_risky')) {
                    $table->boolean('is_risky')->default(false)->after('is_vip')->index();
                }
            });
        }

        if (!Schema::hasTable('customer_notes')) {
            Schema::create('customer_notes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
                $table->text('note');
                $table->timestamps();

                $table->index(['user_id', 'created_at']);
            });
        }

        if (!Schema::hasTable('customer_activity_logs')) {
            Schema::create('customer_activity_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('type', 64)->index();
                $table->string('action', 64)->index();
                $table->string('message');
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'type', 'created_at'], 'customer_activity_logs_user_type_created_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_activity_logs');
        Schema::dropIfExists('customer_notes');

        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                $columns = [
                    'status',
                    'last_login_at',
                    'last_login_ip',
                    'login_count',
                    'is_vip',
                    'is_risky',
                ];

                foreach ($columns as $column) {
                    if (Schema::hasColumn('users', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
