<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Sale;
use App\Models\User;
use App\Services\CustomerInvoiceService;
use App\Support\RoleNames;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class PosReceiptPartialPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function createPartiallyPaidSale(): Sale
    {
        $customer = User::factory()->create(['name' => 'Credit Customer']);
        $employee = User::factory()->create();

        $order = Order::query()->create([
            'user_id' => $customer->id,
            'subtotal' => 200000,
            'tax_total' => 0,
            'discount' => 0,
            'currency' => 'NGN',
            'channel' => 'pos',
            'order_number' => 'POS-RCPT-001',
            'status' => 'completed',
            'total_amount' => 200000,
            'shipping_total' => 0,
        ]);

        $order->addPayment([
            'type' => 'inflow',
            'method' => 'cash',
            'amount' => 40000,
            'status' => 'paid',
            'paid_at' => '2026-07-01 10:00:00',
            'employee_id' => $employee->id,
        ]);

        $order->addPayment([
            'type' => 'inflow',
            'method' => 'transfer',
            'amount' => 30000,
            'status' => 'paid',
            'paid_at' => '2026-07-01 10:00:00',
            'employee_id' => $employee->id,
        ]);

        $sale = Sale::create([
            'employee_id' => $employee->id,
            'customer_id' => $customer->id,
            'total_amount' => $order->total_amount,
            'order_id' => $order->id,
        ]);

        $invoice = app(CustomerInvoiceService::class)->createFromOrder($order, [
            'due_date' => '2026-08-15',
            'repayment_terms' => 'Weekly installments',
        ], $employee->id);

        app(CustomerInvoiceService::class)->recordRepayment($invoice, [[
            'method' => 'cash',
            'amount' => 50000,
        ]], $employee->id);

        return $sale;
    }

    protected function captureReceiptViewData(Sale $sale): array
    {
        $captured = [];

        $pdfMock = Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $pdfMock->shouldReceive('setPaper')->andReturnSelf();
        $pdfMock->shouldReceive('stream')->andReturn(response('pdf'));

        Pdf::shouldReceive('loadView')
            ->once()
            ->withArgs(function ($view, $data) use (&$captured) {
                $captured = ['view' => $view, 'data' => $data];

                return true;
            })
            ->andReturn($pdfMock);

        $director = User::factory()->create();
        $director->syncRoles([RoleNames::DIRECTOR]);

        $this->actingAs($director)
            ->get(route('admin.pos.print', $sale->id))
            ->assertOk();

        return $captured;
    }

    public function test_receipt_data_includes_installments_and_outstanding_balance(): void
    {
        $sale = $this->createPartiallyPaidSale();

        $captured = $this->captureReceiptViewData($sale);
        $data = $captured['data'];

        $this->assertSame(
            [40000.0, 30000.0, 50000.0],
            collect($data['payments'])->pluck('amount')->all()
        );
        $this->assertSame(['Cash', 'Transfer', 'Cash'], collect($data['payments'])->pluck('method')->all());
        $this->assertSame(120000.0, $data['amount_paid']);
        $this->assertSame(80000.0, $data['balance_due']);
        $this->assertSame('15/08/2026', $data['invoice_due_date']);

        foreach (['receipts.thermal', 'receipts.a4'] as $template) {
            $html = view($template, $data)->render();

            $this->assertStringContainsString('PARTIAL PAYMENT', $html, $template);
            $this->assertStringContainsString('PAYMENT DETAILS', $html, $template);
            $this->assertStringContainsString('BALANCE DUE', $html, $template);
            $this->assertStringContainsString('120,000.00', $html, $template); // amount paid
            $this->assertStringContainsString('80,000.00', $html, $template);  // outstanding balance
            $this->assertStringContainsString('50,000.00', $html, $template);  // repayment installment
            $this->assertStringContainsString('15/08/2026', $html, $template); // due date
            $this->assertStringNotContainsString('PAID IN FULL', $html, $template);
        }
    }

    public function test_fully_paid_receipt_shows_paid_in_full_without_balance(): void
    {
        $customer = User::factory()->create();
        $employee = User::factory()->create();

        $order = Order::query()->create([
            'user_id' => $customer->id,
            'subtotal' => 50000,
            'tax_total' => 0,
            'discount' => 0,
            'currency' => 'NGN',
            'channel' => 'pos',
            'order_number' => 'POS-RCPT-002',
            'status' => 'completed',
            'total_amount' => 50000,
            'shipping_total' => 0,
        ]);

        $order->addPayment([
            'type' => 'inflow',
            'method' => 'cash',
            'amount' => 50000,
            'status' => 'paid',
            'paid_at' => now(),
            'employee_id' => $employee->id,
        ]);

        $sale = Sale::create([
            'employee_id' => $employee->id,
            'customer_id' => $customer->id,
            'total_amount' => $order->total_amount,
            'order_id' => $order->id,
        ]);

        $captured = $this->captureReceiptViewData($sale);
        $data = $captured['data'];

        $this->assertSame(50000.0, $data['amount_paid']);
        $this->assertSame(0.0, $data['balance_due']);
        $this->assertNull($data['invoice_due_date']);

        foreach (['receipts.thermal', 'receipts.a4'] as $template) {
            $html = view($template, $data)->render();

            $this->assertStringContainsString('PAID IN FULL', $html, $template);
            $this->assertStringNotContainsString('PARTIAL PAYMENT', $html, $template);
            $this->assertStringNotContainsString('BALANCE DUE', $html, $template);
        }
    }

    public function test_print_endpoint_streams_pdf_for_partially_paid_sale(): void
    {
        $sale = $this->createPartiallyPaidSale();

        $director = User::factory()->create();
        $director->syncRoles([RoleNames::DIRECTOR]);

        $response = $this->actingAs($director)->get(route('admin.pos.print', $sale->id));

        $response->assertOk();
        $this->assertStringContainsString(
            'application/pdf',
            (string) $response->headers->get('content-type')
        );
    }
}
