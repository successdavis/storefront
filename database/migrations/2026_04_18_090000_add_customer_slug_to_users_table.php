<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('users')) {
            return;
        }

        if (!Schema::hasColumn('users', 'customer_slug')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('customer_slug', 160)->nullable()->after('name');
                $table->unique('customer_slug');
            });
        }

        $users = DB::table('users')
            ->select('id', 'name', 'email', 'customer_slug')
            ->orderBy('id')
            ->get();

        foreach ($users as $user) {
            if (filled($user->customer_slug)) {
                continue;
            }

            $base = Str::slug((string) $user->name);

            if ($base === '') {
                $base = Str::slug(Str::before((string) $user->email, '@'));
            }

            if ($base === '') {
                $base = 'customer';
            }

            $candidate = Str::limit($base, 150, '');
            $suffix = 2;

            while (
                DB::table('users')
                    ->where('customer_slug', $candidate)
                    ->where('id', '!=', $user->id)
                    ->exists()
            ) {
                $candidate = Str::limit($base, 150 - strlen('-'.$suffix), '').'-'.$suffix;
                $suffix++;
            }

            DB::table('users')
                ->where('id', $user->id)
                ->update(['customer_slug' => $candidate]);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('users') || !Schema::hasColumn('users', 'customer_slug')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['customer_slug']);
            $table->dropColumn('customer_slug');
        });
    }
};
