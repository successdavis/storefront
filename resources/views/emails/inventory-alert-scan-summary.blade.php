<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Inventory Alert Scan Summary</title>
    <style>
        body {
            color: #111827;
            font-family: Arial, sans-serif;
            line-height: 1.5;
        }

        table {
            border-collapse: collapse;
            margin: 12px 0 24px;
            width: 100%;
        }

        th,
        td {
            border: 1px solid #d1d5db;
            padding: 8px;
            text-align: left;
            vertical-align: top;
        }

        th {
            background: #f3f4f6;
        }
    </style>
</head>
<body>
    <h2>Inventory Alert Scan Summary</h2>

    <p>
        The inventory scan completed at {{ $scanCompletedAt }} and found
        {{ $alerts->count() }} active alert{{ $alerts->count() === 1 ? '' : 's' }}.
    </p>

    @foreach ($groupedAlerts as $type => $items)
        <h3>{{ str($type)->replace('_', ' ')->title() }} ({{ $items->count() }})</h3>

        <table>
            <thead>
                <tr>
                    <th>Product</th>
                    <th>SKU</th>
                    <th>Severity</th>
                    <th>Quantity</th>
                    <th>Available</th>
                    <th>Message</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($items as $alert)
                    @php
                        $variant = $alert->variant;
                        $values = $variant ? $variant->values->pluck('value')->filter()->implode(', ') : '';
                        $product = $variant?->product?->name ?? 'Unknown product';
                        $productName = trim($product . ($values !== '' ? ' - ' . $values : ''));
                        $quantity = $variant ? (int) $variant->quantity : null;
                        $reserved = $variant ? (int) ($variant->reserved ?? 0) : 0;
                        $available = $variant ? max($quantity - $reserved, 0) : null;
                    @endphp
                    <tr>
                        <td>{{ $productName }}</td>
                        <td>{{ $variant?->sku ?? 'N/A' }}</td>
                        <td>{{ str($alert->severity)->title() }}</td>
                        <td>{{ $quantity ?? 'N/A' }}</td>
                        <td>{{ $available ?? 'N/A' }}</td>
                        <td>{{ $alert->message }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endforeach
</body>
</html>
