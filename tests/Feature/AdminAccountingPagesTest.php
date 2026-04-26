<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\RoleNames;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminAccountingPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_director_can_access_accounting_pages(): void
    {
        $director = User::factory()->create();
        $director->syncRoles([RoleNames::DIRECTOR]);

        $this->actingAs($director)
            ->get(route('admin.accounting.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Accounting/Index')
                ->has('summary_cards', 4)
            );

        $this->actingAs($director)
            ->get(route('admin.accounting.accounts.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Accounting/Accounts/Index')
                ->has('parent_options')
            );

        $this->actingAs($director)
            ->get(route('admin.accounting.reports.trial-balance'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Accounting/Reports/TrialBalance')
            );

        $this->actingAs($director)
            ->get(route('admin.accounting.gateway-settlements.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Accounting/GatewaySettlements/Index')
                ->has('bank_account_options')
                ->has('gateway_clearing_options')
            );

        $this->actingAs($director)
            ->get(route('admin.accounting.cash-bank-transfers.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Accounting/CashBankTransfers/Index')
                ->has('cash_account_options')
                ->has('bank_account_options')
            );

        $this->actingAs($director)
            ->get(route('admin.accounting.charts'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Accounting/Charts')
                ->has('report.summary_cards', 7)
                ->has('report.cash_flow_chart.labels')
                ->has('report.expense_chart.segments')
                ->has('report.expense_chart.period_options')
                ->has('report.expense_chart.total')
                ->has('report.profit_loss_chart.rows')
                ->has('report.profit_loss_chart.period_options')
                ->has('report.profit_loss_chart.net_profit')
                ->has('report.bank_balances')
                ->has('report.cash_balances')
            );

        $this->actingAs($director)
            ->get(route('admin.accounting.reports.cash-summary'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Accounting/Reports/CashSummary')
                ->has('report.summary_cards', 6)
            );

        $this->actingAs($director)
            ->get(route('admin.accounting.reports.inventory-valuation'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Accounting/Reports/InventoryValuation')
                ->has('categories')
                ->has('report.summary')
                ->has('report.groups')
            );

        $this->actingAs($director)
            ->get(route('admin.accounting.reports.inventory-valuation.export'))
            ->assertOk();

        $this->actingAs($director)
            ->get(route('admin.accounting.reports.receivables'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Accounting/Reports/Receivables')
                ->has('report.summary_cards', 4)
                ->has('report.aging_buckets')
                ->has('report.invoices.data')
            );

        $this->actingAs($director)
            ->post(route('admin.accounting.sync-history'))
            ->assertRedirect(route('admin.accounting.index'));
    }

    public function test_customer_cannot_access_admin_accounting_pages(): void
    {
        $customer = User::factory()->create();
        $customer->syncRoles([RoleNames::CUSTOMER]);

        $this->actingAs($customer)
            ->get(route('admin.accounting.index'))
            ->assertForbidden();
    }

    public function test_profit_and_loss_supports_period_presets_and_custom_ranges(): void
    {
        Carbon::setTestNow('2026-04-23 10:00:00');

        try {
            $director = User::factory()->create();
            $director->syncRoles([RoleNames::DIRECTOR]);

            $this->actingAs($director)
                ->get(route('admin.accounting.reports.profit-loss', ['period' => 'last_year']))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('Admin/Accounting/Reports/ProfitAndLoss')
                    ->where('report.filters.period', 'last_year')
                    ->where('report.filters.from', '2025-01-01')
                    ->where('report.filters.to', '2025-12-31')
                );

            $this->actingAs($director)
                ->get(route('admin.accounting.reports.profit-loss', [
                    'period' => 'custom',
                    'from' => '2026-02-01',
                    'to' => '2026-02-28',
                ]))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('Admin/Accounting/Reports/ProfitAndLoss')
                    ->where('report.filters.period', 'custom')
                    ->where('report.filters.from', '2026-02-01')
                    ->where('report.filters.to', '2026-02-28')
                );
        } finally {
            Carbon::setTestNow();
        }
    }
}
