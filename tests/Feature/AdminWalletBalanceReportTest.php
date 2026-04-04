<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\RoleNames;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminWalletBalanceReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.connections.loan_mysql', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        DB::purge('loan_mysql');
        DB::reconnect('loan_mysql');

        $this->createLoanTables();
    }

    public function test_director_can_preview_wallet_balance_report_and_filter_by_branch(): void
    {
        $director = User::factory()->create();
        $director->syncRoles([RoleNames::DIRECTOR]);

        $this->seedLoanFixtures();

        $this->actingAs($director)
            ->get(route('admin.reports.wallet-balances.index', ['branch_id' => 1]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Reports/WalletBalances')
                ->where('report.summary.selected_branch.name', 'Obudu')
                ->where('report.summary.total_active_accounts', 6)
                ->where('report.summary.total_balance', 2950)
                ->where('report.rows.0.key', 'saving')
                ->where('report.rows.0.active_accounts', 2)
                ->where('report.rows.0.total_balance', 800)
                ->where('report.rows.1.key', 'daily_saving')
                ->where('report.rows.1.active_accounts', 1)
                ->where('report.rows.1.total_balance', 1000)
                ->where('report.rows.2.key', 'easy_flex')
                ->where('report.rows.2.active_accounts', 2)
                ->where('report.rows.2.total_balance', 900)
                ->where('report.rows.3.key', 'contribution')
                ->where('report.rows.3.active_accounts', 1)
                ->where('report.rows.3.total_balance', 250)
            );
    }

    public function test_pdf_export_returns_a_pdf_response(): void
    {
        $director = User::factory()->create();
        $director->syncRoles([RoleNames::DIRECTOR]);

        $this->seedLoanFixtures();

        $response = $this->actingAs($director)
            ->get(route('admin.reports.wallet-balances.export-pdf', ['branch_id' => 1]));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('wallet-balances-obudu-', (string) $response->headers->get('content-disposition'));
    }

    public function test_category_export_returns_excel_friendly_response_for_filtered_active_accounts(): void
    {
        $director = User::factory()->create();
        $director->syncRoles([RoleNames::DIRECTOR]);

        $this->seedLoanFixtures();

        $response = $this->actingAs($director)
            ->get(route('admin.reports.wallet-balances.export-category-accounts', [
                'wallet_type' => 'easy_flex',
                'branch_id' => 1,
            ]));

        $response->assertOk();
        $this->assertStringContainsString('application/vnd.ms-excel', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('Flex One', $response->getContent());
        $this->assertStringContainsString('Flex Two', $response->getContent());
        $this->assertStringNotContainsString('Contributor Branch Two', $response->getContent());
        $this->assertStringNotContainsString('Daily Inactive', $response->getContent());
    }

    public function test_customers_cannot_access_wallet_balance_report_routes(): void
    {
        $customer = User::factory()->create();
        $customer->syncRoles([RoleNames::CUSTOMER]);

        $this->actingAs($customer)
            ->get(route('admin.reports.wallet-balances.index'))
            ->assertForbidden();

        $this->actingAs($customer)
            ->get(route('admin.reports.wallet-balances.export-pdf'))
            ->assertForbidden();

        $this->actingAs($customer)
            ->get(route('admin.reports.wallet-balances.export-category-accounts', ['wallet_type' => 'saving']))
            ->assertForbidden();
    }

    protected function createLoanTables(): void
    {
        Schema::connection('loan_mysql')->create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code');
            $table->timestamps();
        });

        Schema::connection('loan_mysql')->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('mobile')->nullable();
            $table->string('account_number')->nullable();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->timestamps();
        });

        Schema::connection('loan_mysql')->create('accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->decimal('amount', 15, 2)->default(0);
            $table->boolean('status')->default(false);
            $table->boolean('locked')->default(false);
            $table->string('account_type');
            $table->string('account_number')->nullable();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->timestamps();
        });
    }

    protected function seedLoanFixtures(): void
    {
        DB::connection('loan_mysql')->table('branches')->insert([
            ['id' => 1, 'name' => 'Obudu', 'code' => '1001001', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'Ogoja', 'code' => '1001002', 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::connection('loan_mysql')->table('users')->insert([
            ['id' => 1, 'name' => 'Saving Alpha', 'email' => 'saving-alpha@example.com', 'mobile' => '08000000001', 'branch_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'Saving Beta', 'email' => 'saving-beta@example.com', 'mobile' => '08000000002', 'branch_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'name' => 'Daily Active', 'email' => 'daily-active@example.com', 'mobile' => '08000000003', 'branch_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 4, 'name' => 'Daily Inactive', 'email' => 'daily-inactive@example.com', 'mobile' => '08000000004', 'branch_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 5, 'name' => 'Flex One', 'email' => 'flex-one@example.com', 'mobile' => '08000000005', 'branch_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 6, 'name' => 'Flex Two', 'email' => 'flex-two@example.com', 'mobile' => '08000000006', 'branch_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 7, 'name' => 'Contributor Branch One', 'email' => 'contrib-one@example.com', 'mobile' => '08000000007', 'branch_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 8, 'name' => 'Contributor Branch Two', 'email' => 'contrib-two@example.com', 'mobile' => '08000000008', 'branch_id' => 2, 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::connection('loan_mysql')->table('accounts')->insert([
            ['user_id' => 1, 'amount' => 500, 'status' => 1, 'locked' => 0, 'account_type' => 'Saving', 'account_number' => 'SAV-001', 'branch_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => 2, 'amount' => 300, 'status' => 1, 'locked' => 0, 'account_type' => 'Saving', 'account_number' => 'SAV-002', 'branch_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => 3, 'amount' => 1000, 'status' => 1, 'locked' => 0, 'account_type' => 'Daily Saving', 'account_number' => 'DAY-001', 'branch_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => 4, 'amount' => 700, 'status' => 0, 'locked' => 0, 'account_type' => 'Daily Saving', 'account_number' => 'DAY-002', 'branch_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => 5, 'amount' => 700, 'status' => 1, 'locked' => 0, 'account_type' => 'EasyFlex', 'account_number' => 'FLEX-001', 'branch_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => 6, 'amount' => 200, 'status' => 1, 'locked' => 1, 'account_type' => 'Easy Flex', 'account_number' => 'FLEX-002', 'branch_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => 7, 'amount' => 250, 'status' => 1, 'locked' => 0, 'account_type' => 'Contribution', 'account_number' => 'CON-001', 'branch_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => 8, 'amount' => 1500, 'status' => 1, 'locked' => 0, 'account_type' => 'Contribution', 'account_number' => 'CON-002', 'branch_id' => 2, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}

