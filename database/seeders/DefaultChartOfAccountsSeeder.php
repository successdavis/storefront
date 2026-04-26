<?php

namespace Database\Seeders;

use App\Models\Accounting\Account;
use App\Models\Accounting\AccountingSetting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DefaultChartOfAccountsSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $accounts = collect($this->definitions());
            $created = [];

            foreach ($accounts as $definition) {
                $parentCode = $definition['parent_code'] ?? null;
                $parentId = $parentCode ? Arr::get($created, $parentCode) : null;

                $account = Account::query()->updateOrCreate(
                    ['code' => $definition['code']],
                    [
                        'name' => $definition['name'],
                        'slug' => $definition['slug'] ?? Str::slug($definition['name']),
                        'type' => $definition['type'],
                        'subtype' => $definition['subtype'] ?? null,
                        'classification' => $definition['classification'] ?? null,
                        'parent_id' => $parentId,
                        'is_active' => $definition['is_active'] ?? true,
                        'is_system' => $definition['is_system'] ?? true,
                        'allows_manual_entries' => $definition['allows_manual_entries'] ?? true,
                        'currency' => $definition['currency'] ?? config('accounting.currency'),
                        'description' => $definition['description'] ?? null,
                    ],
                );

                $created[$definition['code']] = $account->id;
            }

            foreach (config('accounting.system_accounts', []) as $key => $code) {
                if (!isset($created[$code])) {
                    continue;
                }

                AccountingSetting::query()->updateOrCreate(
                    ['key' => $key],
                    ['account_id' => $created[$code]],
                );
            }
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function definitions(): array
    {
        return [
            ['code' => '1000', 'name' => 'Assets', 'type' => 'asset', 'classification' => 'header', 'allows_manual_entries' => false],
            ['code' => '1100', 'name' => 'Cash and Cash Equivalents', 'type' => 'asset', 'classification' => 'header', 'parent_code' => '1000', 'allows_manual_entries' => false],
            ['code' => '1110', 'name' => 'Cash on Hand', 'type' => 'asset', 'subtype' => 'cash', 'parent_code' => '1100'],
            ['code' => '1120', 'name' => 'Main Bank Account', 'type' => 'asset', 'subtype' => 'bank', 'parent_code' => '1100'],
            ['code' => '1130', 'name' => 'Payment Gateway Clearing', 'type' => 'asset', 'subtype' => 'clearing', 'parent_code' => '1100'],
            ['code' => '1200', 'name' => 'Receivables', 'type' => 'asset', 'classification' => 'header', 'parent_code' => '1000', 'allows_manual_entries' => false],
            ['code' => '1210', 'name' => 'Accounts Receivable', 'type' => 'asset', 'subtype' => 'receivable', 'parent_code' => '1200'],
            ['code' => '1300', 'name' => 'Inventory and Stock', 'type' => 'asset', 'classification' => 'header', 'parent_code' => '1000', 'allows_manual_entries' => false],
            ['code' => '1310', 'name' => 'Inventory Asset', 'type' => 'asset', 'subtype' => 'inventory', 'parent_code' => '1300'],
            ['code' => '1320', 'name' => 'Goods Received Not Invoiced', 'type' => 'asset', 'subtype' => 'inventory_clearing', 'parent_code' => '1300'],

            ['code' => '2000', 'name' => 'Liabilities', 'type' => 'liability', 'classification' => 'header', 'allows_manual_entries' => false],
            ['code' => '2100', 'name' => 'Payables and Accruals', 'type' => 'liability', 'classification' => 'header', 'parent_code' => '2000', 'allows_manual_entries' => false],
            ['code' => '2110', 'name' => 'Accounts Payable', 'type' => 'liability', 'subtype' => 'payable', 'parent_code' => '2100'],
            ['code' => '2120', 'name' => 'Customer Wallet Liability', 'type' => 'liability', 'subtype' => 'wallet_liability', 'parent_code' => '2100'],
            ['code' => '2130', 'name' => 'Refunds Payable', 'type' => 'liability', 'subtype' => 'refund_payable', 'parent_code' => '2100'],
            ['code' => '2140', 'name' => 'Shipping Liability', 'type' => 'liability', 'subtype' => 'shipping_liability', 'parent_code' => '2100'],
            ['code' => '2150', 'name' => 'Tax Payable', 'type' => 'liability', 'subtype' => 'tax_payable', 'parent_code' => '2100'],

            ['code' => '3000', 'name' => 'Equity', 'type' => 'equity', 'classification' => 'header', 'allows_manual_entries' => false],
            ['code' => '3110', 'name' => 'Owner Capital', 'type' => 'equity', 'subtype' => 'capital', 'parent_code' => '3000'],
            ['code' => '3120', 'name' => 'Inventory Correction Reserve', 'type' => 'equity', 'subtype' => 'inventory_correction_reserve', 'parent_code' => '3000'],
            ['code' => '3130', 'name' => 'Opening Balance Equity', 'type' => 'equity', 'subtype' => 'opening_balance_equity', 'parent_code' => '3000'],
            ['code' => '3210', 'name' => 'Retained Earnings', 'type' => 'equity', 'subtype' => 'retained_earnings', 'parent_code' => '3000'],

            ['code' => '4000', 'name' => 'Revenue', 'type' => 'revenue', 'classification' => 'header', 'allows_manual_entries' => false],
            ['code' => '4100', 'name' => 'Sales Revenue', 'type' => 'revenue', 'classification' => 'header', 'parent_code' => '4000', 'allows_manual_entries' => false],
            ['code' => '4110', 'name' => 'Product Sales Revenue', 'type' => 'revenue', 'subtype' => 'sales', 'parent_code' => '4100'],
            ['code' => '4120', 'name' => 'Shipping Revenue', 'type' => 'revenue', 'subtype' => 'shipping_income', 'parent_code' => '4100'],
            ['code' => '4130', 'name' => 'Service Fee Income', 'type' => 'revenue', 'subtype' => 'service_fee', 'parent_code' => '4100'],
            ['code' => '4140', 'name' => 'Other Income', 'type' => 'revenue', 'subtype' => 'other_income', 'parent_code' => '4100'],
            ['code' => '4150', 'name' => 'Sales Discount Contra Revenue', 'type' => 'revenue', 'subtype' => 'contra_revenue', 'parent_code' => '4100'],
            ['code' => '4160', 'name' => 'Inventory Adjustment Gain', 'type' => 'revenue', 'subtype' => 'inventory_gain', 'parent_code' => '4100'],

            ['code' => '5000', 'name' => 'Expenses', 'type' => 'expense', 'classification' => 'header', 'allows_manual_entries' => false],
            ['code' => '5100', 'name' => 'Cost of Sales', 'type' => 'cost_of_goods_sold', 'classification' => 'header', 'parent_code' => '5000', 'allows_manual_entries' => false],
            ['code' => '5110', 'name' => 'Cost of Goods Sold', 'type' => 'cost_of_goods_sold', 'subtype' => 'cogs', 'parent_code' => '5100'],
            ['code' => '5400', 'name' => 'Operating Expenses', 'type' => 'expense', 'classification' => 'header', 'parent_code' => '5000', 'allows_manual_entries' => false],
            ['code' => '5120', 'name' => 'Shipping Expense', 'type' => 'expense', 'subtype' => 'shipping_expense', 'parent_code' => '5400'],
            ['code' => '5130', 'name' => 'Payment Gateway Charges', 'type' => 'expense', 'subtype' => 'gateway_charge', 'parent_code' => '5400'],
            ['code' => '5410', 'name' => 'Operating Expense', 'type' => 'expense', 'subtype' => 'operating_expense', 'parent_code' => '5400'],
            ['code' => '5411', 'name' => 'Staff and Admin Expense', 'type' => 'expense', 'subtype' => 'staff_admin_expense', 'parent_code' => '5400'],
            ['code' => '5412', 'name' => 'Utilities Expense', 'type' => 'expense', 'subtype' => 'utilities_expense', 'parent_code' => '5400'],
            ['code' => '5413', 'name' => 'Logistics Expense', 'type' => 'expense', 'subtype' => 'logistics_expense', 'parent_code' => '5400'],
            ['code' => '5414', 'name' => 'Repairs Expense', 'type' => 'expense', 'subtype' => 'repairs_expense', 'parent_code' => '5400'],
            ['code' => '5419', 'name' => 'Miscellaneous Expense', 'type' => 'expense', 'subtype' => 'misc_expense', 'parent_code' => '5400'],
            ['code' => '5510', 'name' => 'Inventory Adjustment Loss', 'type' => 'expense', 'subtype' => 'inventory_loss', 'parent_code' => '5000'],
        ];
    }
}
