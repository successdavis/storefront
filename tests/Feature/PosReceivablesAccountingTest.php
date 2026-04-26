<?php

namespace Tests\Feature;

use App\Models\CustomerInvoice;
use App\Models\Order;
use App\Models\User;
use App\Services\Accounting\AccountingService;
use App\Services\Accounting\CashSummaryService;
use App\Services\CustomerInvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosReceivablesAccountingTest extends TestCase
{
    use RefreshDatabase;

    public function test_split_payment_sale_posts_cash_bank_and_receivable_correctly(): void
    {
        $customer = User::factory()->create();
        $actor = User::factory()->create();

        $order = Order::query()->create([
            'user_id' => $customer->id,
            'subtotal' => 200000,
            'tax_total' => 0,
            'discount' => 0,
            'currency' => 'NGN',
            'channel' => 'pos',
            'order_number' => 'POS-AR-001',
            'status' => 'completed',
            'total_amount' => 200000,
            'shipping_total' => 0,
            'created_at' => '2026-04-20 10:00:00',
            'updated_at' => '2026-04-20 10:00:00',
        ]);

        $order->addPayment([
            'type' => 'inflow',
            'method' => 'cash',
            'amount' => 40000,
            'status' => 'paid',
            'paid_at' => '2026-04-20 10:00:00',
            'employee_id' => $actor->id,
        ]);

        $order->addPayment([
            'type' => 'inflow',
            'method' => 'transfer',
            'amount' => 30000,
            'status' => 'paid',
            'paid_at' => '2026-04-20 10:00:00',
            'employee_id' => $actor->id,
        ]);

        app(CustomerInvoiceService::class)->createFromOrder($order, [
            'due_date' => '2026-04-30',
            'repayment_terms' => 'Balance due in 10 days',
        ], $actor->id);

        $entry = app(AccountingService::class)->postOrder($order, null, $actor->id);
        $lines = $entry->lines()->with('account:id,code')->get()->keyBy('account.code');

        $this->assertSame(40000.0, (float) $lines['1110']->debit);
        $this->assertSame(30000.0, (float) $lines['1120']->debit);
        $this->assertSame(130000.0, (float) $lines['1210']->debit);
        $this->assertSame(200000.0, (float) $lines['4110']->credit);
        $this->assertArrayNotHasKey('5110', $lines->toArray());
    }

    public function test_invoice_repayment_reduces_receivable_without_reposting_revenue(): void
    {
        $customer = User::factory()->create();
        $actor = User::factory()->create();

        $order = Order::query()->create([
            'user_id' => $customer->id,
            'subtotal' => 200000,
            'tax_total' => 0,
            'discount' => 0,
            'currency' => 'NGN',
            'channel' => 'pos',
            'order_number' => 'POS-AR-002',
            'status' => 'completed',
            'total_amount' => 200000,
            'shipping_total' => 0,
        ]);

        $order->addPayment([
            'type' => 'inflow',
            'method' => 'cash',
            'amount' => 70000,
            'status' => 'paid',
            'paid_at' => now(),
            'employee_id' => $actor->id,
        ]);

        $invoice = app(CustomerInvoiceService::class)->createFromOrder($order, [
            'due_date' => now()->addWeek()->toDateString(),
            'repayment_terms' => 'Balance due next week',
        ], $actor->id);

        $invoice = app(CustomerInvoiceService::class)->recordRepayment($invoice, [[
            'method' => 'transfer',
            'amount' => 50000,
            'transaction_reference' => 'INV-PAY-001',
        ]], $actor->id);

        $this->assertSame(80000.0, (float) $invoice->outstanding_balance);

        $payment = $invoice->payments()->latest('id')->firstOrFail();
        $entry = app(AccountingService::class)->postCustomerInvoicePayment($invoice, $payment, $actor->id);
        $lines = $entry->lines()->with('account:id,code')->get()->keyBy('account.code');

        $this->assertSame(50000.0, (float) $lines['1120']->debit);
        $this->assertSame(50000.0, (float) $lines['1210']->credit);
        $this->assertArrayNotHasKey('4110', $lines->toArray());
    }

    public function test_daily_cash_summary_separates_credit_sales_and_debt_recovery(): void
    {
        $customer = User::factory()->create();
        $actor = User::factory()->create();

        $order = Order::query()->create([
            'user_id' => $customer->id,
            'subtotal' => 200000,
            'tax_total' => 0,
            'discount' => 0,
            'currency' => 'NGN',
            'channel' => 'pos',
            'order_number' => 'POS-AR-003',
            'status' => 'completed',
            'total_amount' => 200000,
            'shipping_total' => 0,
            'created_at' => '2026-04-21 09:15:00',
            'updated_at' => '2026-04-21 09:15:00',
        ]);

        $order->addPayment([
            'type' => 'inflow',
            'method' => 'cash',
            'amount' => 40000,
            'status' => 'paid',
            'paid_at' => '2026-04-21 09:15:00',
            'employee_id' => $actor->id,
        ]);

        $order->addPayment([
            'type' => 'inflow',
            'method' => 'transfer',
            'amount' => 30000,
            'status' => 'paid',
            'paid_at' => '2026-04-21 09:15:00',
            'employee_id' => $actor->id,
        ]);

        $invoice = app(CustomerInvoiceService::class)->createFromOrder($order, [
            'due_date' => '2026-04-30',
            'repayment_terms' => 'Balance due in 10 days',
        ], $actor->id);

        app(CustomerInvoiceService::class)->recordRepayment($invoice, [[
            'method' => 'cash',
            'amount' => 50000,
            'transaction_reference' => 'REC-001',
        ]], $actor->id);

        $report = app(CashSummaryService::class)->dailySummary([
            'date' => '2026-04-21',
        ]);

        $cards = collect($report['summary_cards'])->keyBy('key');

        $this->assertSame(200000.0, (float) $cards['sales']['value']);
        $this->assertSame(90000.0, (float) $cards['cash']['value']);
        $this->assertSame(30000.0, (float) $cards['bank']['value']);
        $this->assertSame(130000.0, (float) $cards['credit_sales']['value']);
        $this->assertSame(50000.0, (float) $cards['debt_recovered']['value']);
    }
}
