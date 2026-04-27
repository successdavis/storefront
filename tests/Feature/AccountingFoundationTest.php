<?php

namespace Tests\Feature;

use App\Models\Accounting\Account;
use App\Models\Accounting\CashBankTransfer;
use App\Models\Accounting\Expense;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\PaymentGatewaySettlement;
use App\Models\Category;
use App\Models\OpeningBalance;
use App\Models\OpeningBalanceItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockEntry;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorBill;
use App\Services\Accounting\AccountingService;
use App\Services\Accounting\AccountingDashboardService;
use App\Services\Accounting\HistoricalAccountingSyncService;
use App\Services\Accounting\JournalBuilder;
use App\Services\Accounting\FinancialStatementService;
use App\Services\Accounting\LedgerQueryService;
use App\Services\Accounting\JournalPostingService;
use App\Services\Accounting\SystemAccountResolver;
use App\Services\Reports\InventoryValuationReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AccountingFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_chart_of_accounts_and_system_mappings_are_seeded(): void
    {
        $this->assertDatabaseHas('accounts', ['code' => '1110', 'name' => 'Cash on Hand']);
        $this->assertDatabaseHas('accounts', ['code' => '1310', 'name' => 'Inventory Asset']);
        $this->assertDatabaseHas('accounts', ['code' => '4110', 'type' => 'revenue']);
        $this->assertDatabaseHas('accounts', ['code' => '5110', 'type' => 'cost_of_goods_sold']);
        $this->assertDatabaseHas('accounting_settings', ['key' => 'product_sales_revenue']);

        $resolver = app(SystemAccountResolver::class);

        $this->assertSame('1110', $resolver->resolve('cash_on_hand')->code);
        $this->assertSame('4110', $resolver->resolve('product_sales_revenue')->code);
        $this->assertSame('3130', $resolver->resolve('opening_balance_equity')->code);
    }

    public function test_journal_posting_service_rejects_unbalanced_entries(): void
    {
        $cash = app(SystemAccountResolver::class)->resolve('cash_on_hand');
        $sales = app(SystemAccountResolver::class)->resolve('product_sales_revenue');

        $this->expectException(ValidationException::class);

        app(JournalPostingService::class)->post([
            'description' => 'Broken entry',
            'entry_date' => now()->toDateString(),
            'posting_date' => now()->toDateString(),
            'status' => JournalEntry::STATUS_POSTED,
        ], JournalBuilder::make()
            ->debit($cash, 100)
            ->credit($sales, 90)
            ->lines());
    }

    public function test_posting_an_order_creates_a_balanced_and_idempotent_sales_journal(): void
    {
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->for($product)->create([
            'average_cost' => 400,
            'last_purchase_price' => 400,
        ]);
        $customer = User::factory()->create();

        $order = Order::query()->create([
            'user_id' => $customer->id,
            'subtotal' => 1000,
            'tax_total' => 0,
            'discount' => 100,
            'currency' => 'NGN',
            'channel' => 'pos',
            'order_number' => 'POS-TEST-001',
            'status' => 'completed',
            'total_amount' => 950,
            'shipping_total' => 50,
        ]);

        OrderItem::query()->create([
            'order_id' => $order->id,
            'variant_id' => $variant->id,
            'quantity' => 1,
            'price' => 1000,
        ]);

        StockEntry::query()->create([
            'variant_id' => $variant->id,
            'quantity' => 1,
            'unit_cost' => 400,
            'type' => 'stock_out',
            'effective_at' => now(),
            'reason' => 'Order fulfillment',
            'source_type' => Order::class,
            'source_id' => $order->id,
        ]);

        $first = app(AccountingService::class)->postOrder($order, 'cash');
        $second = app(AccountingService::class)->postOrder($order, 'cash');

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('journal_entries', 1);
        $this->assertEquals((float) $first->total_debit, (float) $first->total_credit);
        $this->assertDatabaseHas('journal_entries', [
            'event_key' => "order:{$order->id}:sale_posted",
            'source_type' => Order::class,
            'source_id' => $order->id,
        ]);

        $codes = $first->lines->load('account')->pluck('account.code')->all();
        $this->assertContains('1110', $codes);
        $this->assertContains('4110', $codes);
        $this->assertContains('4120', $codes);
        $this->assertContains('4150', $codes);
        $this->assertContains('5110', $codes);
        $this->assertContains('1310', $codes);
    }

    public function test_posting_a_manual_expense_creates_a_balanced_journal(): void
    {
        $expenseAccount = app(SystemAccountResolver::class)->resolve('miscellaneous_expense');
        $paymentAccount = app(SystemAccountResolver::class)->resolve('cash_on_hand');

        $expense = Expense::query()->create([
            'expense_number' => 'EXP-TEST-001',
            'expense_date' => now()->toDateString(),
            'amount' => 2500,
            'currency' => 'NGN',
            'expense_account_id' => $expenseAccount->id,
            'payment_account_id' => $paymentAccount->id,
            'status' => 'posted',
            'description' => 'Internet subscription',
        ]);

        $entry = app(AccountingService::class)->postExpense($expense);

        $this->assertEquals((float) $entry->total_debit, (float) $entry->total_credit);
        $this->assertDatabaseHas('journal_entries', [
            'event_key' => "expense:{$expense->id}:posted",
        ]);
        $this->assertDatabaseHas('expenses', [
            'id' => $expense->id,
            'journal_entry_id' => $entry->id,
        ]);
    }

    public function test_vendor_bill_payments_refresh_status_without_legacy_method_errors(): void
    {
        $employee = User::factory()->create();
        $vendor = Vendor::query()->create([
            'name' => 'Acme Supplies',
            'email' => 'vendor@example.com',
            'phone' => '08000000000',
            'address' => '12 Marina, Lagos',
            'active' => true,
        ]);

        $bill = VendorBill::query()->create([
            'vendor_id' => $vendor->id,
            'bill_number' => 'VB-TEST-001',
            'bill_date' => '2026-04-27',
            'due_date' => '2026-05-10',
            'status' => 'unpaid',
            'total_amount' => 100000,
        ]);

        $bill->addPayment([
            'type' => 'outflow',
            'method' => 'transfer',
            'amount' => 40000,
            'status' => 'paid',
            'paid_at' => '2026-04-27 10:00:00',
            'employee_id' => $employee->id,
            'transaction_reference' => 'VBP-TEST-001',
        ]);

        $this->assertSame('partially_paid', $bill->fresh()->status);
        $this->assertSame(40000.0, $bill->fresh()->totalPayments());

        $bill->addPayment([
            'type' => 'outflow',
            'method' => 'transfer',
            'amount' => 60000,
            'status' => 'paid',
            'paid_at' => '2026-04-27 12:00:00',
            'employee_id' => $employee->id,
            'transaction_reference' => 'VBP-TEST-002',
        ]);

        $this->assertSame('paid', $bill->fresh()->status);
        $this->assertSame(100000.0, $bill->fresh()->totalPayments());
        $this->assertSame(0.0, $bill->fresh()->outstandingBalance());
    }

    public function test_ledger_statement_filters_by_posting_date_without_sql_errors(): void
    {
        $expenseAccount = app(SystemAccountResolver::class)->resolve('miscellaneous_expense');
        $paymentAccount = app(SystemAccountResolver::class)->resolve('cash_on_hand');

        $expense = Expense::query()->create([
            'expense_number' => 'EXP-TEST-LEDGER-001',
            'expense_date' => '2026-04-02',
            'amount' => 1500,
            'currency' => 'NGN',
            'expense_account_id' => $expenseAccount->id,
            'payment_account_id' => $paymentAccount->id,
            'status' => 'posted',
            'description' => 'Ledger query regression',
        ]);

        app(AccountingService::class)->postExpense($expense);

        $statement = app(LedgerQueryService::class)->statement($paymentAccount, [
            'from' => '2026-04-01',
            'to' => '2026-04-30',
            'per_page' => 20,
        ]);

        $this->assertSame($paymentAccount->id, $statement['account']['id']);
        $this->assertCount(1, $statement['movements']->items());
        $this->assertSame('2026-04-02', $statement['movements']->items()[0]['posting_date']);
    }

    public function test_historical_sync_imports_opening_balances_and_past_orders_idempotently(): void
    {
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->for($product)->create([
            'average_cost' => 300,
            'last_purchase_price' => 300,
        ]);
        $customer = User::factory()->create();

        $openingBalance = OpeningBalance::query()->create([
            'reference' => 'OB-001',
            'effective_at' => '2026-03-01 09:00:00',
        ]);

        OpeningBalanceItem::query()->create([
            'opening_balance_id' => $openingBalance->id,
            'variant_id' => $variant->id,
            'quantity' => 10,
            'unit_cost' => 250,
        ]);

        $order = Order::query()->create([
            'user_id' => $customer->id,
            'subtotal' => 1000,
            'tax_total' => 0,
            'discount' => 0,
            'currency' => 'NGN',
            'channel' => 'pos',
            'order_number' => 'POS-HISTORY-001',
            'status' => 'completed',
            'total_amount' => 1000,
            'shipping_total' => 0,
            'created_at' => '2026-03-03 12:00:00',
            'updated_at' => '2026-03-03 12:00:00',
        ]);

        OrderItem::query()->create([
            'order_id' => $order->id,
            'variant_id' => $variant->id,
            'quantity' => 1,
            'price' => 1000,
        ]);

        StockEntry::query()->create([
            'variant_id' => $variant->id,
            'quantity' => 1,
            'unit_cost' => 300,
            'type' => 'stock_out',
            'effective_at' => '2026-03-03 12:00:00',
            'reason' => 'Historical sale',
            'source_type' => Order::class,
            'source_id' => $order->id,
        ]);

        $sync = app(HistoricalAccountingSyncService::class);

        $first = $sync->sync();
        $second = $sync->sync();

        $this->assertSame(2, $first['total_posted']);
        $this->assertSame(0, $second['total_posted']);
        $this->assertDatabaseHas('journal_entries', [
            'event_key' => "opening_balance:{$openingBalance->id}:posted",
        ]);
        $this->assertDatabaseHas('journal_entries', [
            'event_key' => "order:{$order->id}:sale_posted",
        ]);
    }

    public function test_balance_sheet_uses_cumulative_balances_as_of_selected_date(): void
    {
        $inventoryAsset = app(SystemAccountResolver::class)->resolve('inventory_asset');
        $openingEquity = app(SystemAccountResolver::class)->resolve('opening_balance_equity');

        app(JournalPostingService::class)->post([
            'event_key' => 'test:balance-sheet:inventory-opening',
            'description' => 'Inventory opening balance',
            'entry_date' => '2026-03-01',
            'posting_date' => '2026-03-01',
            'status' => JournalEntry::STATUS_POSTED,
        ], JournalBuilder::make()
            ->debit($inventoryAsset, 573950)
            ->credit($openingEquity, 573950)
            ->lines());

        $report = app(FinancialStatementService::class)->balanceSheet([
            'as_of' => '2026-04-23',
        ]);

        $inventoryRow = collect($report['assets'])->firstWhere('code', $inventoryAsset->code);
        $openingEquityRow = collect($report['equity'])->firstWhere('code', $openingEquity->code);

        $this->assertNotNull($inventoryRow);
        $this->assertNotNull($openingEquityRow);
        $this->assertSame('2026-04-23', $report['filters']['as_of']);
        $this->assertSame(573950.0, (float) $inventoryRow['amount']);
        $this->assertSame(573950.0, (float) $openingEquityRow['amount']);
        $this->assertFalse((bool) $openingEquityRow['is_negative']);
    }

    public function test_balance_sheet_includes_derived_accumulated_earnings_in_equity(): void
    {
        $cash = app(SystemAccountResolver::class)->resolve('cash_on_hand');
        $sales = app(SystemAccountResolver::class)->resolve('product_sales_revenue');

        app(JournalPostingService::class)->post([
            'event_key' => 'test:balance-sheet:earnings',
            'description' => 'Historical profitable sale',
            'entry_date' => '2026-03-10',
            'posting_date' => '2026-03-10',
            'status' => JournalEntry::STATUS_POSTED,
        ], JournalBuilder::make()
            ->debit($cash, 250000)
            ->credit($sales, 250000)
            ->lines());

        $report = app(FinancialStatementService::class)->balanceSheet([
            'as_of' => '2026-04-23',
        ]);

        $earningsRow = collect($report['equity'])->firstWhere('code', '3210-DERIVED');

        $this->assertNotNull($earningsRow);
        $this->assertSame('Accumulated Earnings', $earningsRow['name']);
        $this->assertSame(250000.0, (float) $earningsRow['amount']);
        $this->assertFalse((bool) $earningsRow['is_negative']);
    }

    public function test_gateway_settlement_moves_value_from_clearing_to_bank_account(): void
    {
        $bankAccount = app(SystemAccountResolver::class)->resolve('main_bank_account');
        $clearingAccount = app(SystemAccountResolver::class)->resolve('payment_gateway_clearing');
        $actor = User::factory()->create();

        $settlement = app(\App\Services\Accounting\PaymentGatewaySettlementService::class)->createAndPost([
            'gateway' => 'paystack',
            'settlement_date' => '2026-04-24',
            'amount' => 500000,
            'currency' => 'NGN',
            'bank_account_id' => $bankAccount->id,
            'clearing_account_id' => $clearingAccount->id,
            'reference' => 'PST-SETTLE-001',
            'description' => 'Paystack settlement for prior payouts',
            'note' => 'Automated feature test settlement',
        ], $actor->id);

        $entry = JournalEntry::query()
            ->where('source_type', PaymentGatewaySettlement::class)
            ->where('source_id', $settlement->id)
            ->first();

        $this->assertNotNull($entry);
        $this->assertSame($entry->id, $settlement->journal_entry_id);
        $this->assertEquals((float) $entry->total_debit, (float) $entry->total_credit);

        $lines = $entry->lines()->with('account:id,code')->get()->keyBy('account.code');

        $this->assertSame(500000.0, (float) $lines['1120']->debit);
        $this->assertSame(0.0, (float) $lines['1120']->credit);
        $this->assertSame(0.0, (float) $lines['1130']->debit);
        $this->assertSame(500000.0, (float) $lines['1130']->credit);
    }

    public function test_cash_bank_transfer_moves_value_from_cash_to_bank(): void
    {
        $cashAccount = app(SystemAccountResolver::class)->resolve('cash_on_hand');
        $bankAccount = app(SystemAccountResolver::class)->resolve('main_bank_account');
        $actor = User::factory()->create();

        $transfer = app(\App\Services\Accounting\CashBankTransferService::class)->createAndPost([
            'transfer_date' => '2026-04-26',
            'amount' => 125000,
            'currency' => 'NGN',
            'cash_account_id' => $cashAccount->id,
            'bank_account_id' => $bankAccount->id,
            'reference' => 'DEP-001',
            'description' => 'Cash deposited from the till into main bank',
            'note' => 'Feature test transfer',
        ], $actor->id);

        $entry = JournalEntry::query()
            ->where('source_type', CashBankTransfer::class)
            ->where('source_id', $transfer->id)
            ->first();

        $this->assertNotNull($entry);
        $this->assertSame($entry->id, $transfer->journal_entry_id);
        $this->assertEquals((float) $entry->total_debit, (float) $entry->total_credit);

        $lines = $entry->lines()->with('account:id,code')->get()->keyBy('account.code');

        $this->assertSame(125000.0, (float) $lines['1120']->debit);
        $this->assertSame(0.0, (float) $lines['1120']->credit);
        $this->assertSame(0.0, (float) $lines['1110']->debit);
        $this->assertSame(125000.0, (float) $lines['1110']->credit);
    }

    public function test_accounting_dashboard_report_builds_monthly_charts_and_liquidity_balances(): void
    {
        $cash = app(SystemAccountResolver::class)->resolve('cash_on_hand');
        $bank = app(SystemAccountResolver::class)->resolve('main_bank_account');
        $sales = app(SystemAccountResolver::class)->resolve('product_sales_revenue');
        $cogs = app(SystemAccountResolver::class)->resolve('cost_of_goods_sold');
        $expense = app(SystemAccountResolver::class)->resolve('miscellaneous_expense');
        $actor = User::factory()->create();

        app(JournalPostingService::class)->post([
            'event_key' => 'test:dashboard:jan-sale',
            'description' => 'January sale',
            'entry_date' => '2026-01-10',
            'posting_date' => '2026-01-10',
            'status' => JournalEntry::STATUS_POSTED,
        ], JournalBuilder::make()
            ->debit($cash, 1000)
            ->credit($sales, 1000)
            ->lines());

        app(JournalPostingService::class)->post([
            'event_key' => 'test:dashboard:jan-cogs',
            'description' => 'January cost of goods sold',
            'entry_date' => '2026-01-10',
            'posting_date' => '2026-01-10',
            'status' => JournalEntry::STATUS_POSTED,
        ], JournalBuilder::make()
            ->debit($cogs, 400)
            ->credit(app(SystemAccountResolver::class)->resolve('inventory_asset'), 400)
            ->lines());

        app(JournalPostingService::class)->post([
            'event_key' => 'test:dashboard:jan-expense',
            'description' => 'January expense',
            'entry_date' => '2026-01-14',
            'posting_date' => '2026-01-14',
            'status' => JournalEntry::STATUS_POSTED,
        ], JournalBuilder::make()
            ->debit($expense, 300)
            ->credit($cash, 300)
            ->lines());

        app(\App\Services\Accounting\CashBankTransferService::class)->createAndPost([
            'transfer_date' => '2026-01-20',
            'amount' => 200,
            'currency' => 'NGN',
            'cash_account_id' => $cash->id,
            'bank_account_id' => $bank->id,
            'reference' => 'CASH-DEP-001',
            'description' => 'Cash deposited into bank',
        ], $actor->id);

        app(JournalPostingService::class)->post([
            'event_key' => 'test:dashboard:feb-sale',
            'description' => 'February sale',
            'entry_date' => '2026-02-08',
            'posting_date' => '2026-02-08',
            'status' => JournalEntry::STATUS_POSTED,
        ], JournalBuilder::make()
            ->debit($bank, 1500)
            ->credit($sales, 1500)
            ->lines());

        app(JournalPostingService::class)->post([
            'event_key' => 'test:dashboard:feb-cogs',
            'description' => 'February cost of goods sold',
            'entry_date' => '2026-02-08',
            'posting_date' => '2026-02-08',
            'status' => JournalEntry::STATUS_POSTED,
        ], JournalBuilder::make()
            ->debit($cogs, 500)
            ->credit(app(SystemAccountResolver::class)->resolve('inventory_asset'), 500)
            ->lines());

        $report = app(AccountingDashboardService::class)->report([
            'period' => 'custom',
            'from' => '2026-01-01',
            'to' => '2026-02-28',
            'expense_period' => 'selected_range',
        ]);

        $this->assertSame(['Jan 2026', 'Feb 2026'], $report['cash_flow_chart']['labels']);
        $this->assertSame([1000.0, 1500.0], $report['cash_flow_chart']['inflow']);
        $this->assertSame([300.0, 0.0], $report['cash_flow_chart']['outflow']);
        $this->assertSame([700.0, 1500.0], $report['cash_flow_chart']['net']);
        $this->assertSame(300.0, (float) $report['expense_chart']['total']);
        $this->assertSame('selected_range', $report['expense_chart']['filters']['period']);
        $this->assertCount(1, $report['expense_chart']['segments']);
        $this->assertSame('Miscellaneous Expense', $report['expense_chart']['segments'][0]['name']);
        $this->assertSame(300.0, (float) $report['expense_chart']['segments'][0]['amount']);
        $this->assertSame('selected_range', $report['profit_loss_chart']['filters']['period']);
        $this->assertSame(1300.0, (float) $report['profit_loss_chart']['net_profit']);
        $this->assertSame('Revenue', $report['profit_loss_chart']['rows'][0]['label']);
        $this->assertSame(2500.0, (float) $report['profit_loss_chart']['rows'][0]['amount']);
        $this->assertSame('COGS', $report['profit_loss_chart']['rows'][1]['label']);
        $this->assertSame(900.0, (float) $report['profit_loss_chart']['rows'][1]['amount']);
        $this->assertSame('Expenses', $report['profit_loss_chart']['rows'][2]['label']);
        $this->assertSame(300.0, (float) $report['profit_loss_chart']['rows'][2]['amount']);
        $this->assertSame([300.0, 1000.0], $report['sales_profit_chart']['profit']);
        $this->assertSame([1000.0, 1500.0], $report['sales_profit_chart']['sales']);
        $this->assertSame(1700.0, collect($report['bank_balances'])->firstWhere('code', '1120')['balance']);
        $this->assertSame(500.0, collect($report['cash_balances'])->firstWhere('code', '1110')['balance']);
    }

    public function test_profit_and_loss_separates_revenue_cogs_and_operating_expenses(): void
    {
        $cash = app(SystemAccountResolver::class)->resolve('cash_on_hand');
        $revenue = app(SystemAccountResolver::class)->resolve('product_sales_revenue');
        $cogs = app(SystemAccountResolver::class)->resolve('cost_of_goods_sold');
        $rent = app(SystemAccountResolver::class)->resolve('operating_expense');
        $salary = app(SystemAccountResolver::class)->resolve('staff_admin_expense');
        $utilities = app(SystemAccountResolver::class)->resolve('utilities_expense');
        $inventory = app(SystemAccountResolver::class)->resolve('inventory_asset');

        app(JournalPostingService::class)->post([
            'event_key' => 'test:pnl:revenue',
            'description' => 'Revenue posting',
            'entry_date' => '2026-04-01',
            'posting_date' => '2026-04-01',
            'status' => JournalEntry::STATUS_POSTED,
        ], JournalBuilder::make()
            ->debit($cash, 2000000)
            ->credit($revenue, 2000000)
            ->lines());

        app(JournalPostingService::class)->post([
            'event_key' => 'test:pnl:cogs',
            'description' => 'COGS posting',
            'entry_date' => '2026-04-02',
            'posting_date' => '2026-04-02',
            'status' => JournalEntry::STATUS_POSTED,
        ], JournalBuilder::make()
            ->debit($cogs, 900000)
            ->credit($inventory, 900000)
            ->lines());

        app(JournalPostingService::class)->post([
            'event_key' => 'test:pnl:opex-rent',
            'description' => 'Rent expense',
            'entry_date' => '2026-04-03',
            'posting_date' => '2026-04-03',
            'status' => JournalEntry::STATUS_POSTED,
        ], JournalBuilder::make()
            ->debit($rent, 100000)
            ->credit($cash, 100000)
            ->lines());

        app(JournalPostingService::class)->post([
            'event_key' => 'test:pnl:opex-salary',
            'description' => 'Salary expense',
            'entry_date' => '2026-04-04',
            'posting_date' => '2026-04-04',
            'status' => JournalEntry::STATUS_POSTED,
        ], JournalBuilder::make()
            ->debit($salary, 200000)
            ->credit($cash, 200000)
            ->lines());

        app(JournalPostingService::class)->post([
            'event_key' => 'test:pnl:opex-utilities',
            'description' => 'Utilities expense',
            'entry_date' => '2026-04-05',
            'posting_date' => '2026-04-05',
            'status' => JournalEntry::STATUS_POSTED,
        ], JournalBuilder::make()
            ->debit($utilities, 55900)
            ->credit($cash, 55900)
            ->lines());

        $report = app(FinancialStatementService::class)->incomeStatement([
            'from' => '2026-04-01',
            'to' => '2026-04-30',
        ]);

        $this->assertSame(2000000.0, (float) $report['revenue_total']);
        $this->assertSame(900000.0, (float) $report['cost_of_goods_sold_total']);
        $this->assertSame(1100000.0, (float) $report['gross_profit']);
        $this->assertSame(355900.0, (float) $report['operating_expenses_total']);
        $this->assertSame(744100.0, (float) $report['net_profit']);
        $this->assertSame(55.0, (float) $report['gross_margin_percent']);
        $this->assertSame(37.21, (float) $report['net_margin_percent']);
    }

    public function test_inventory_valuation_summary_groups_stocked_variants_and_totals_asset_values(): void
    {
        $laptops = Category::query()->create([
            'name' => 'Laptop',
            'description' => 'Laptop devices',
        ]);

        $mice = Category::query()->create([
            'name' => 'Mouse',
            'description' => 'Mouse devices',
        ]);

        $laptopProduct = Product::factory()->create(['name' => 'Asus ZenBook 14']);
        $laptopProduct->categories()->attach($laptops->id);

        $mouseProduct = Product::factory()->create(['name' => 'Rechargeable Mouse']);
        $mouseProduct->categories()->attach($mice->id);

        ProductVariant::factory()->for($laptopProduct)->create([
            'sku' => 'ASUS-ZB14-16GB',
            'quantity' => 45,
            'average_cost' => 89444.44,
            'regular_price' => 180000,
        ]);

        ProductVariant::factory()->for($mouseProduct)->create([
            'sku' => 'MOUSE-RG-01',
            'quantity' => 30,
            'average_cost' => 5000,
            'regular_price' => 7500,
        ]);

        $report = app(InventoryValuationReportService::class)->report([
            'as_of' => now()->toDateString(),
        ]);

        $this->assertSame(75, $report['summary']['total_on_hand']);
        $this->assertSame(4174999.8, (float) $report['summary']['total_asset_value']);
        $this->assertSame(8325000.0, (float) $report['summary']['total_retail_value']);
        $this->assertCount(2, $report['groups']);

        $laptopGroup = collect($report['groups'])->firstWhere('category_name', 'Laptop');
        $mouseGroup = collect($report['groups'])->firstWhere('category_name', 'Mouse');

        $this->assertNotNull($laptopGroup);
        $this->assertNotNull($mouseGroup);
        $this->assertSame(45, $laptopGroup['totals']['on_hand']);
        $this->assertSame(4024999.8, (float) $laptopGroup['totals']['asset_value']);
        $this->assertSame(8100000.0, (float) $laptopGroup['totals']['retail_value']);
        $this->assertSame(30, $mouseGroup['totals']['on_hand']);
        $this->assertSame(150000.0, (float) $mouseGroup['totals']['asset_value']);
        $this->assertSame(225000.0, (float) $mouseGroup['totals']['retail_value']);
    }
}
