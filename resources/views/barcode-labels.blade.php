<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Barcode Labels</title>
    @php
        $labelHeightMm = max(10, min(500, (float) ($label_height_mm ?? 25)));
        $labelOrientation = ($orientation ?? 'portrait') === 'landscape' ? 'landscape' : 'portrait';
        $normalizedPaperSize = strtoupper(str_replace(' ', '', (string) ($paper_size ?? '50mm')));
        $isSheetPaper = $normalizedPaperSize === 'A4';
        $pageMargin = $isSheetPaper ? '8px' : '0 3mm 0 0';
        $thermalBoxHeightMm = max(1, $labelHeightMm - 1.8);
    @endphp
    <style>
        @page { margin: {{ $pageMargin }}; }
        * {
            box-sizing: border-box;
        }
        html,
        body {
            margin: 0;
            padding: 0;
        }
        body {
            font-family: "DejaVu Sans", sans-serif;
            font-size: 7.5px;
            color: #111827;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        td {
            border: 1px solid #d1d5db;
            padding: 1.2mm;
            vertical-align: top;
            height: {{ $labelHeightMm }}mm;
        }
        .barcode-label {
            width: 100%;
            overflow: hidden;
        }
        .thermal-label {
            display: block;
            height: {{ $thermalBoxHeightMm }}mm;
            padding: 0.8mm 1.2mm 0.6mm;
            font-size: 7.5px;
            line-height: 1;
            page-break-inside: avoid;
            break-inside: avoid;
        }
        .thermal-label:not(.is-last) {
            page-break-after: always;
            break-after: page;
        }
        .roll-labels {
            font-size: 0;
            line-height: 0;
        }
        .sheet-label {
            height: {{ $labelHeightMm }}mm;
        }
        .label-name {
            display: block;
            width: 100%;
            font-size: 7.5px;
            font-weight: 700;
            line-height: 1.15;
            margin: 0 0 0.6mm;
            max-height: 6.5mm;
            overflow: hidden;
        }
        .label-price {
            font-size: 7.5px;
            font-weight: 700;
            line-height: 1.1;
            margin: 0 0 0.8mm;
            color: #111827;
        }
        .barcode-block {
            width: 100%;
            text-align: center;
        }
        .barcode-img {
            display: block;
            margin: 0 auto 0.35mm;
            width: 100%;
            max-width: 46mm;
            height: 9mm;
        }
        .barcode-text {
            text-align: center;
            font-size: 6.6px;
            line-height: 1;
            letter-spacing: 0;
        }
        .empty {
            border: 0;
        }
    </style>
</head>
<body class="is-{{ $labelOrientation }}">
@php
    $rows = array_chunk($labels, max(1, (int) $columns));
    $columnWidth = round(100 / max(1, (int) $columns), 2);
@endphp

@if ($isSheetPaper)
    <table>
        @foreach ($rows as $row)
            <tr>
                @for ($i = 0; $i < $columns; $i++)
                    @php $label = $row[$i] ?? null; @endphp
                    <td style="width: {{ $columnWidth }}%;" class="{{ $label ? '' : 'empty' }}">
                        @if ($label)
                            <div class="barcode-label sheet-label">
                                <div class="label-name">{{ $label['name'] }}</div>
                                <div class="label-price">Price: {{ $label['price'] }}</div>
                                <div class="barcode-block">
                                    <img
                                        class="barcode-img"
                                        src="data:image/png;base64,{{ DNS1D::getBarcodePNG($label['barcode'], 'C128', 2, 55) }}"
                                        alt="Barcode"
                                    />
                                    <div class="barcode-text">{{ $label['barcode'] }}</div>
                                </div>
                            </div>
                        @endif
                    </td>
                @endfor
            </tr>
        @endforeach
    </table>
@else
    <div class="roll-labels">
        @foreach ($labels as $label)
            <section class="barcode-label thermal-label {{ $loop->last ? 'is-last' : '' }}">
                <div class="label-name">{{ $label['name'] }}</div>
                <div class="label-price">Price: {{ $label['price'] }}</div>
                <div class="barcode-block">
                    <img
                        class="barcode-img"
                        src="data:image/png;base64,{{ DNS1D::getBarcodePNG($label['barcode'], 'C128', 2, 55) }}"
                        alt="Barcode"
                    />
                    <div class="barcode-text">{{ $label['barcode'] }}</div>
                </div>
            </section>
        @endforeach
    </div>
@endif
</body>
</html>
