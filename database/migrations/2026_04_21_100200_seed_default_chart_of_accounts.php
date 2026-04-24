<?php

use Database\Seeders\DefaultChartOfAccountsSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(DefaultChartOfAccountsSeeder::class)->run();
    }

    public function down(): void
    {
        // Leave seeded accounts in place to preserve posted journal history.
    }
};
