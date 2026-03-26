<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Reports\CategoryPriceListExportRequest;
use App\Http\Requests\Admin\Reports\CategoryPriceListPreviewRequest;
use App\Services\Reports\CategoryPriceListReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class CategoryPriceListReportController extends Controller
{
    public function __construct(
        protected CategoryPriceListReportService $categoryPriceListReportService,
    ) {}

    public function index(CategoryPriceListPreviewRequest $request): Response
    {
        $filters = [
            'category_id' => $request->validated('category_id'),
            'in_stock_only' => $request->boolean('in_stock_only'),
            'sort' => $request->validated('sort') ?? 'alphabetical',
        ];

        $preview = $this->categoryPriceListReportService->preview($filters);

        return Inertia::render('Admin/Reports/CategoryPriceList', [
            'categories' => $this->categoryPriceListReportService->listCategories(),
            'filters' => $filters,
            'report' => [
                'summary' => $this->categoryPriceListReportService->previewSummary($filters, $preview),
                'preview' => $preview,
            ],
        ]);
    }

    public function export(CategoryPriceListExportRequest $request): HttpResponse
    {
        $filters = [
            'category_id' => $request->validated('category_id'),
            'in_stock_only' => $request->boolean('in_stock_only'),
            'sort' => $request->validated('sort') ?? 'alphabetical',
        ];

        $payload = $this->categoryPriceListReportService->exportPayload($filters, $request->user());
        $categoryName = data_get($payload, 'category.name', 'category');
        $filename = sprintf(
            'price-list-%s-%s.pdf',
            Str::slug($categoryName),
            now()->format('YmdHis'),
        );

        $pdf = Pdf::loadView('reports.category-price-list', $payload)
            ->setPaper('a4', 'landscape');

        return $pdf->stream($filename);
    }
}
