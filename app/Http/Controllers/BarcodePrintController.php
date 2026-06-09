<?php

namespace App\Http\Controllers;

use App\Domain\Inventory\Barcode\BarcodeService;
use App\Domain\Inventory\Support\VariantNameFormatter;
use App\Models\ProductVariant;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BarcodePrintController extends Controller
{
    public function __construct(
        protected BarcodeService $barcodeService,
        protected VariantNameFormatter $variantNameFormatter,
    ) {}

    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search', ''));

        $variants = ProductVariant::query()
            ->with([
                'product:id,name',
                'values:id,variant_type_id,value',
                'values.type:id,name',
            ])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($variantQuery) use ($search) {
                    $variantQuery
                        ->where('sku', 'like', "%{$search}%")
                        ->orWhere('barcode', 'like', "%{$search}%")
                        ->orWhereHas('product', fn ($productQuery) => $productQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->orderBy('id')
            ->paginate(50)
            ->withQueryString()
            ->through(function (ProductVariant $variant): array {
                return [
                    'id' => (int) $variant->id,
                    'sku' => $variant->sku,
                    'barcode' => $variant->barcode,
                    'display_name' => $this->variantNameFormatter->format($variant),
                ];
            });

        return Inertia::render('InventoryBarcodes', [
            'filters' => [
                'search' => $search,
            ],
            'variants' => $variants,
        ]);
    }

    public function print(Request $request)
    {
        $validated = $request->validate([
            'variant_ids' => ['required', 'array', 'min:1'],
            'variant_ids.*' => ['required', 'integer', 'exists:product_variants,id'],
        ]);

        $variants = ProductVariant::query()
            ->with([
                'product:id,name',
                'values:id,variant_type_id,value',
                'values.type:id,name',
            ])
            ->whereIn('id', $validated['variant_ids'])
            ->orderBy('id')
            ->get();

        foreach ($variants as $variant) {
            $this->barcodeService->assignToVariant($variant);
        }

        $currency = (string) Setting::get('business_currency', 'NGN');
        $labels = $variants->map(function (ProductVariant $variant) use ($currency): array {
            return [
                'name' => $this->variantNameFormatter->format($variant),
                'sku' => $variant->sku,
                'barcode' => $variant->barcode,
                'price' => $this->formatPrice((float) $variant->regular_price, $currency),
            ];
        })->values()->all();

        $paperSize = $this->normalizePaperSize((string) Setting::get('barcode_paper_size', '50mm'));
        $orientation = $this->normalizeOrientation((string) Setting::get('barcode_label_orientation', 'portrait'));
        $labelHeightMm = $this->normalizeLabelHeight((string) Setting::get('barcode_label_height_mm', '25'));
        $paperConfig = $this->resolvePaperConfig($paperSize, $labelHeightMm, $orientation);

        $pdf = Pdf::loadView('barcode-labels', [
            'labels' => $labels,
            'columns' => $paperConfig['columns'],
            'paper_size' => $paperSize,
            'orientation' => $orientation,
            'label_height_mm' => $labelHeightMm,
        ])->setPaper($paperConfig['paper'], $paperConfig['dompdf_orientation']);

        return $pdf->stream(sprintf('barcodes-%s.pdf', now()->format('YmdHis')));
    }

    protected function resolvePaperConfig(string $paperSize, float $labelHeightMm, string $orientation): array
    {
        $normalizedPaperSize = strtoupper($paperSize);
        $widthPt = match ($normalizedPaperSize) {
            'A4' => 595.28,
            '50MM' => 141.73,
            '58MM' => 164.41,
            default => 226.77,
        };

        $margin = $normalizedPaperSize === 'A4' ? 20 : 8;
        $columns = max(1, (int) floor(max($widthPt - ($margin * 2), 120) / 170));

        if ($normalizedPaperSize === 'A4') {
            return [
                'paper' => 'A4',
                'columns' => $columns,
                'dompdf_orientation' => $orientation,
            ];
        }

        $labelHeightPt = $this->millimetersToPoints($labelHeightMm);
        $pageHeight = $labelHeightPt;
        $paper = $orientation === 'landscape'
            ? [0, 0, $pageHeight, $widthPt]
            : [0, 0, $widthPt, $pageHeight];

        return [
            'paper' => $paper,
            'columns' => $columns,
            'dompdf_orientation' => $orientation,
        ];
    }

    protected function normalizePaperSize(string $paperSize): string
    {
        $normalized = strtoupper(str_replace(' ', '', trim($paperSize)));

        return match ($normalized) {
            'A4' => 'A4',
            '50MM' => '50mm',
            '58MM' => '58mm',
            '80MM' => '80mm',
            default => '50mm',
        };
    }

    protected function normalizeOrientation(string $orientation): string
    {
        return strtolower(trim($orientation)) === 'landscape' ? 'landscape' : 'portrait';
    }

    protected function normalizeLabelHeight(string $height): float
    {
        $height = (float) $height;

        return min(500, max(10, $height ?: 25));
    }

    protected function millimetersToPoints(float $millimeters): float
    {
        return round($millimeters * 72 / 25.4, 2);
    }

    protected function formatPrice(float $price, string $currency): string
    {
        $currency = $this->normalizeCurrency($currency);
        $formattedPrice = number_format($price, 2);

        if ($currency === '') {
            return $formattedPrice;
        }

        $separator = ctype_alpha($currency) ? ' ' : '';

        return "{$currency}{$separator}{$formattedPrice}";
    }

    protected function normalizeCurrency(string $currency): string
    {
        $normalized = strtoupper(trim($currency));

        return match ($normalized) {
            'NGN', 'N', 'NAIRA', '?', 'â‚¦' => '₦',
            default => trim($currency),
        };
    }
}
