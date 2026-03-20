<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Barcode Labels</title>
    <style>
        @page { margin: 8px; }
        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11px;
            color: #111827;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        td {
            border: 1px solid #d1d5db;
            padding: 6px;
            vertical-align: top;
            height: 125px;
        }
        .label-name {
            font-size: 11px;
            font-weight: 700;
            margin-bottom: 4px;
            line-height: 1.2;
            min-height: 24px;
        }
        .label-sku {
            font-size: 10px;
            margin-bottom: 6px;
            color: #374151;
        }
        .barcode-img {
            display: block;
            margin: 0 auto 4px auto;
            width: 100%;
            max-width: 200px;
            height: 44px;
        }
        .barcode-text {
            text-align: center;
            font-size: 10px;
            letter-spacing: 0.3px;
        }
        .empty {
            border: 0;
        }
    </style>
</head>
<body>
@php
    $rows = array_chunk($labels, max(1, (int) $columns));
    $columnWidth = round(100 / max(1, (int) $columns), 2);
@endphp

<table>
    @foreach ($rows as $row)
        <tr>
            @for ($i = 0; $i < $columns; $i++)
                @php $label = $row[$i] ?? null; @endphp
                <td style="width: {{ $columnWidth }}%;" class="{{ $label ? '' : 'empty' }}">
                    @if ($label)
                        <div class="label-name">{{ $label['name'] }}</div>
                        <div class="label-sku">SKU: {{ $label['sku'] }}</div>
                        <img
                            class="barcode-img"
                            src="data:image/png;base64,{{ DNS1D::getBarcodePNG($label['barcode'], 'C128', 2, 55) }}"
                            alt="Barcode"
                        />
                        <div class="barcode-text">{{ $label['barcode'] }}</div>
                    @endif
                </td>
            @endfor
        </tr>
    @endforeach
</table>
</body>
</html>
