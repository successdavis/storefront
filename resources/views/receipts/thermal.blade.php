<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Receipt</title>
    <style>
        @page { margin: 0; }
        body {
            margin: 0;
            padding: 3px;
            font-family: 'Courier New', Courier, monospace;
            font-size: 12px;
            width: 90%;
            line-height: 1.5;
        }
        .center { text-align: center; }
        .bold { font-weight: bold; }
        .line { border-top: 1px dashed #000; margin: 4px 0; }
        .flex { display: flex; justify-content: space-between; }
        .mt { margin-top: 8px; }
        table { width: 100%; border-collapse: collapse; }
        td, th { padding: 5px 5px; text-align: left; }
        th:nth-child(2), td:nth-child(2),
        th:nth-child(3), td:nth-child(3) { text-align: right; }
        html, body { height: auto; overflow: visible; }

        .receipt-barcode { width: 100%; max-width: 70%; display:block; }
        /* safety: handle if package outputs table/svg */
        .barcode-wrapper,
        .barcode-wrapper table,
        .barcode-wrapper svg,
        .barcode-wrapper img { margin: 0 auto; display: block; }
    </style>
</head>
<body>
<div class="center bold" style="font-size:13px">
    <div style="font-size: 15px">{{$business_name}}</div>
    <small>{{$business_website}}</small><br>
    <small>{{$business_address}}</small><br>
    <small>{{$business_phone}}</small><br>
</div>
<div class="line"></div>
<div class="center mt">CASH RECEIPT</div>
<div class="line"></div>

<div class="flex"><span>Order #:</span><span>{{ $order->order_number }}</span></div>
<div class="flex"><span>Date:</span><span>{{ $date }}</span></div>
<div class="flex"><span>Cashier:</span><span>{{ $sale->employee?->name ?? 'N/A' }}</span></div>
<div class="flex"><span>Customer:</span><span>{{ $order->user?->name ?? 'WalkInCustomer' }}</span></div>

<div class="line"></div>

<table>
    <thead>
    <tr class="bold">
        <th>Item</th>
        <th>Qty</th>
        <th>Price</th>
    </tr>
    </thead>
    <tbody>
    @foreach ($order->items as $item)
        @php
            $variant = $item->variant;
            $product = $variant->product ?? null;

            // Build variant label e.g. (Color: Red, Size: L)
            $variantLabel = '';
            if (!empty($variant->values)) {
                $variantLabel = '(' . $variant->values
                    ->map(fn($vv) => $vv->type->name . ': ' . $vv->value)
                    ->join(', ') . ')';
            }

            $itemName = trim(($product->name ?? 'Unnamed Product') . ' ' . $variantLabel);
        @endphp

        <tr>
            <td>{{ $itemName }}</td>
            <td>{{ $item->quantity }}</td>
            <td>{{ number_format($item->price, 2) }}</td>
        </tr>
    @endforeach
    </tbody>

</table>

<div class="line"></div>

<div class="flex bold"><span>Subtotal:</span><span>{{ number_format($order->subtotal, 2) }}</span></div>
<div class="flex"><span>Shipping:</span><span>{{ number_format($order->shipping_total, 2) }}</span></div>
<div class="flex"><span>Discount:</span><span>-{{ number_format($order->discount, 2) }}</span></div>
<div class="flex"><span>Tax:</span><span>{{ number_format($order->tax_total, 2) }}</span></div>

<div class="line"></div>

<div class="flex bold">
    <span>Total:</span>
    <span>{{ number_format($order->total_amount, 2) }} {{ $order->currency }}</span>
</div>

<div class="line"></div>

<div class="center mt">
    <p>{{$business_receipt_footer}}</p>
    <small>{{$business_receipt_footer_refund}}</small>

    <div class="barcode-wrapper" style="text-align:center;">
        <p style="margin:0 0 6px 0; font-size:12px;">Receipt ID: {{ $order->order_number }}</p>
        <img
            src="data:image/png;base64,{{ DNS1D::getBarcodePNG($order->order_number, 'C128', 2, 60) }}"
            alt="barcode"
            class="receipt-barcode"
        />
    </div>
</div>
</body>
</html>
