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

        $labels = $variants->map(function (ProductVariant $variant): array {
            return [
                'name' => $this->variantNameFormatter->format($variant),
                'sku' => $variant->sku,
                'barcode' => $variant->barcode,
            ];
        })->values()->all();

        $paperSize = strtoupper((string) Setting::get('receipt_paper_size', '80MM'));
        $paperConfig = $this->resolvePaperConfig($paperSize, count($labels));

        $pdf = Pdf::loadView('barcode-labels', [
            'labels' => $labels,
            'columns' => $paperConfig['columns'],
            'paper_size' => $paperSize,
        ])->setPaper($paperConfig['paper']);

        return $pdf->stream(sprintf('barcodes-%s.pdf', now()->format('YmdHis')));
    }

    protected function resolvePaperConfig(string $paperSize, int $labelCount): array
    {
        $widthPt = match ($paperSize) {
            'A4' => 595.28,
            '58MM' => 164.41,
            default => 226.77,
        };

        $margin = $paperSize === 'A4' ? 20 : 8;
        $columns = max(1, (int) floor(max($widthPt - ($margin * 2), 120) / 170));

        if ($paperSize === 'A4') {
            return [
                'paper' => 'A4',
                'columns' => $columns,
            ];
        }

        $rows = (int) ceil(max($labelCount, 1) / $columns);
        $pageHeight = max(320, ($rows * 130) + ($margin * 2));

        return [
            'paper' => [0, 0, $widthPt, $pageHeight],
            'columns' => $columns,
        ];
    }
}
