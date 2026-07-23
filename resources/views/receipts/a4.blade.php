<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Invoice - {{ $order->order_number }}</title>
    <style>
        @page { margin: 25px; }
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 13px;
            margin: 0;
            padding: 0;
            color: #000;
        }
        .header { text-align: center; margin-bottom: 25px; }
        .header h1 { font-size: 22px; margin-bottom: 4px; }
        .header small { display: block; }
        .section { margin-bottom: 28px; }
        .line { border-top: 1px dashed #000; margin: 4px 0; }
        .flex { display: flex; justify-content: space-between; margin-bottom: 6px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { padding: 8px; }
        th { background: #f3f4f6; border-bottom: 2px solid #ddd; text-align: left; }
        td { border-bottom: 1px solid #eee; }
        td:nth-child(2), td:nth-child(3) { text-align: right; }
        .totals td { padding: 6px 8px; }
        .totals .label { text-align: right; font-weight: bold; }
        .totals .value { text-align: right; width: 160px; }
        .grand-total { font-size: 16px; font-weight: bold; border-top: 2px solid #000; }
        .barcode { text-align: center; margin-top: 30px; }
        .footer { margin-top: 40px; text-align: center; font-size: 12px; opacity: 0.8; }
    </style>
</head>
<body>

<div class="header">
    <h1>{{ $business_name }}</h1>
    @if(!empty($business_address)) <small>{{ $business_address }}</small> @endif
    @if(!empty($business_phone)) <small>{{ $business_phone }}</small> @endif
    @if(!empty($business_website)) <small>{{ $business_website }}</small> @endif
</div>

<div class="section">
    <div class="line"></div>
    <div class="flex" style="text-align: center; font-size: 13px; font-weight: bolder; "><span>CASH RECEIPT{{ ($balance_due ?? 0) > 0 ? ' (PARTIAL PAYMENT)' : '' }}</span></div>
    <div class="line"></div>
    <div class="flex"><strong>Order Number:</strong> <span>{{ $order->order_number }}</span></div>
    <div class="flex"><strong>Date:</strong> <span>{{ $date }}</span></div>
    <div class="flex"><strong>Cashier:</strong> <span>{{ $sale->employee?->name ?? 'N/A' }}</span></div>
    <div class="flex"><strong>Customer:</strong> <span>{{ $order->user?->name ?? 'Walk-In Customer' }}</span></div>
</div>

<table>
    <thead>
        <tr>
            <th style="width: 60%">Item</th>
            <th style="width: 10%">Qty</th>
            <th style="width: 30%">Unit Price ({{ $order->currency }})</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($order->items as $item)
            @php
                $variant = $item->variant ?? null;
                $product = $variant?->product;
                $values  = $variant?->values ?? collect();

                $variantLabel = $values instanceof \Illuminate\Support\Collection && $values->isNotEmpty()
                    ? '(' . $values->map(fn($vv) => ($vv->type->name ?? 'Attr') . ': ' . ($vv->value ?? 'N/A'))->join(', ') . ')'
                    : '';

                $itemName = trim(($product->name ?? 'Product') . ' ' . $variantLabel);
            @endphp
            <tr>
                <td>{{ $itemName }}</td>
                <td>{{ (int) ($item->quantity ?? 0) }}</td>
                <td>{{ number_format((float) ($item->price ?? 0), 2) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<table class="totals" style="margin-top: 20px;">
    <tr>
        <td class="label">Subtotal:</td>
        <td class="value">{{ number_format((float) ($order->subtotal ?? 0), 2) }}</td>
    </tr>
    <tr>
        <td class="label">Shipping:</td>
        <td class="value">{{ number_format((float) ($order->shipping_total ?? 0), 2) }}</td>
    </tr>
    <tr>
        <td class="label">Discount:</td>
        <td class="value">-{{ number_format((float) ($order->discount ?? 0), 2) }}</td>
    </tr>
    <tr>
        <td class="label">Tax:</td>
        <td class="value">{{ number_format((float) ($order->tax_total ?? 0), 2) }}</td>
    </tr>
    <tr class="grand-total">
        <td class="label">TOTAL:</td>
        <td class="value">{{ number_format((float) ($order->total_amount ?? 0), 2) }} {{ $order->currency }}</td>
    </tr>
</table>

@if(!empty($payments) || ($balance_due ?? 0) > 0)
    <div class="section" style="margin-top: 25px;">
        <div class="line"></div>
        <div class="flex" style="font-weight: bold;">
            <span>PAYMENT DETAILS</span>
            <span>{{ ($balance_due ?? 0) > 0 ? 'PARTIAL PAYMENT' : 'PAID IN FULL' }}</span>
        </div>
        <div class="line"></div>

        <table>
            <thead>
                <tr>
                    <th style="width: 10%">#</th>
                    <th style="width: 30%; text-align: left;">Date</th>
                    <th style="width: 30%; text-align: left;">Method</th>
                    <th style="width: 30%; text-align: right;">Amount ({{ $order->currency }})</th>
                </tr>
            </thead>
            <tbody>
                @foreach($payments ?? [] as $payment)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td style="text-align: left;">{{ $payment['date'] }}</td>
                        <td style="text-align: left;">{{ $payment['method'] }}</td>
                        <td style="text-align: right;">{{ number_format($payment['amount'], 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <table class="totals" style="margin-top: 10px;">
            <tr>
                <td class="label">Amount Paid:</td>
                <td class="value">{{ number_format($amount_paid ?? 0, 2) }}</td>
            </tr>
            @if(($balance_due ?? 0) > 0)
                <tr class="grand-total">
                    <td class="label">BALANCE DUE:</td>
                    <td class="value">{{ number_format($balance_due, 2) }} {{ $order->currency }}</td>
                </tr>
                @if(!empty($invoice_due_date))
                    <tr>
                        <td class="label">Payment Due Date:</td>
                        <td class="value">{{ $invoice_due_date }}</td>
                    </tr>
                @endif
            @endif
        </table>
    </div>
@endif

<div class="barcode" style="display: flex; justify-content: center">
    <p>Receipt ID: {{ $order->order_number }}</p>
    <img style="width: 50%"
        src="data:image/png;base64,{{ DNS1D::getBarcodePNG($order->order_number, 'C128', 2, 70) }}"
        alt="barcode">
</div>

<div class="footer">
    @if(!empty($business_receipt_footer)) <p>{{ $business_receipt_footer }}</p> @endif
    @if(!empty($business_receipt_footer_refund)) <small>{{ $business_receipt_footer_refund }}</small> @endif
</div>

</body>
</html>
