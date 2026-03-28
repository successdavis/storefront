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
        Schema::table('variant_types', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('name');
        });

        $existing = [];

        DB::table('variant_types')
            ->orderBy('id')
            ->select(['id', 'name'])
            ->get()
            ->each(function ($type) use (&$existing) {
                $base = Str::slug((string) $type->name, '_');
                $base = $base !== '' ? $base : 'filter';
                $slug = $base;
                $suffix = 2;

                while (in_array($slug, $existing, true)) {
                    $slug = "{$base}_{$suffix}";
                    $suffix++;
                }

                $existing[] = $slug;

                DB::table('variant_types')
                    ->where('id', $type->id)
                    ->update(['slug' => $slug]);
            });

        Schema::table('variant_types', function (Blueprint $table) {
            $table->unique('slug');
        });
    }

    public function down(): void
    {
        Schema::table('variant_types', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }
};
