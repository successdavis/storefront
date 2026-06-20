<?php

namespace App\Services\Reports;

use App\Models\Category;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\ProductService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator as PaginationLengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CategoryPriceListReportService
{
    public function __construct(
        protected ProductService $productService,
    ) {}

    public function listCategories(): array
    {
        $categories = Category::query()
            ->select('categories.id', 'categories.name', 'categories.parent_id')
            ->orderBy('categories.name')
            ->get();
        $childrenByParent = $this->childrenByParent($categories);
        $activeProductIdsByCategory = $this->activeProductIdsByCategory();

        return $categories
            ->map(function (Category $category) use ($childrenByParent, $activeProductIdsByCategory): array {
                $categoryIds = $this->reportCategoryIds($category, $childrenByParent);
                $activeProductsCount = collect($categoryIds)
                    ->flatMap(fn (int $categoryId) => $activeProductIdsByCategory[$categoryId] ?? [])
                    ->unique()
                    ->count();

                return [
                    'id' => (int) $category->id,
                    'name' => $category->name,
                    'active_products_count' => $activeProductsCount,
                ];
            })
            ->filter(fn (array $category): bool => $category['active_products_count'] > 0)
            ->values()
            ->all();
    }

    public function preview(array $filters, int $perPage = 24): ?LengthAwarePaginator
    {
        $category = $this->selectedCategory($filters);

        if (!$category) {
            return null;
        }

        $rows = $this->rows($category, $filters, false);
        $page = PaginationLengthAwarePaginator::resolveCurrentPage();
        $items = $rows->forPage($page, $perPage)->values();

        return new PaginationLengthAwarePaginator(
            $items,
            $rows->count(),
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'query' => request()->query(),
            ],
        );
    }

    public function previewSummary(array $filters, ?LengthAwarePaginator $preview = null): array
    {
        $category = $this->selectedCategory($filters);
        $normalized = $this->normalizeFilters($filters);

        return [
            'selected_category' => $category ? [
                'id' => (int) $category->id,
                'name' => $category->name,
            ] : null,
            'total_rows' => $preview ? (int) $preview->total() : 0,
            'in_stock_only' => $normalized['in_stock_only'],
            'sort' => $normalized['sort'],
            'sort_label' => $this->sortLabel($normalized['sort']),
        ];
    }

    public function exportPayload(array $filters, ?User $generatedBy = null): array
    {
        $category = $this->selectedCategory($filters);
        $normalized = $this->normalizeFilters($filters);

        $rows = $category
            ? $this->rows($category, $filters, true)
            : collect();

        return [
            'category' => $category ? [
                'id' => (int) $category->id,
                'name' => $category->name,
            ] : null,
            'rows' => $rows,
            'summary' => [
                'total_rows' => $rows->count(),
                'in_stock_only' => $normalized['in_stock_only'],
                'sort' => $normalized['sort'],
                'sort_label' => $this->sortLabel($normalized['sort']),
            ],
            'generated_at' => now(),
            'generated_by' => $generatedBy?->name,
        ];
    }

    protected function rowsQuery(Category $category, array $filters): Builder
    {
        $normalized = $this->normalizeFilters($filters);
        $categoryIds = $this->reportCategoryIds($category);

        return ProductVariant::query()
            ->select('product_variants.*')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->whereExists(function ($query) use ($categoryIds) {
                $query
                    ->selectRaw('1')
                    ->from('category_product')
                    ->whereColumn('category_product.product_id', 'products.id')
                    ->whereIn('category_product.category_id', $categoryIds);
            })
            ->where('products.is_active', true)
            ->whereNull('products.deleted_at')
            ->when($normalized['in_stock_only'], function (Builder $query) {
                $query->whereRaw('(COALESCE(product_variants.quantity, 0) - COALESCE(product_variants.reserved, 0)) > 0');
            })
            ->with([
                'product:id,name',
                'product.images:id,product_id,path,alt,is_primary,sort_order',
                'images:id,product_variant_id,path,alt,is_primary,sort_order',
                'values:id,variant_type_id,value',
                'values.type:id,name',
            ])
            ->when(in_array($normalized['sort'], ['default', 'alphabetical'], true), function (Builder $query) {
                $query
                    ->orderBy('products.name')
                    ->orderBy('product_variants.id');
            }, function (Builder $query) {
                $query->orderByDesc('product_variants.id');
            });
    }

    protected function rows(Category $category, array $filters, bool $includePdfImage): Collection
    {
        $normalized = $this->normalizeFilters($filters);

        $rows = $this->rowsQuery($category, $filters)
            ->get()
            ->map(fn (ProductVariant $variant): array => $this->mapRow($variant, $includePdfImage))
            ->values();

        return match ($normalized['sort']) {
            'price_asc' => $rows
                ->sortBy([
                    ['final_price', 'asc'],
                    ['product_name', 'asc'],
                    ['variant_id', 'asc'],
                ])
                ->values(),
            'price_desc' => $rows
                ->sortBy([
                    ['final_price', 'desc'],
                    ['product_name', 'asc'],
                    ['variant_id', 'asc'],
                ])
                ->values(),
            default => $rows,
        };
    }

    protected function mapRow(ProductVariant $variant, bool $includePdfImage): array
    {
        $product = $variant->product;
        $stock = $this->productService->resolveVariantStock($variant);
        $pricing = $this->productService->resolveVariantPricing($variant, null, $product, false);
        $imageUrl = $product ? $this->productService->resolveProductImage($product, $variant) : null;

        return [
            'variant_id' => (int) $variant->id,
            'product_name' => $product?->name ?? 'Unknown Product',
            'variant_name' => $this->productService->describeVariant($variant),
            'sku' => $variant->sku,
            'quantity_available' => (int) ($stock['available'] ?? 0),
            'original_price' => (float) ($pricing['regular'] ?? 0),
            'final_price' => (float) ($pricing['current'] ?? 0),
            'sales_price' => (float) ($pricing['current'] ?? 0),
            'has_active_discount' => (bool) ($pricing['has_discount'] ?? false),
            'discount_label' => $pricing['discount_label'] ?? null,
            'discount_display_label' => $pricing['discount_display_label'] ?? null,
            'image_url' => $imageUrl,
            'image_pdf_src' => $includePdfImage ? $this->pdfImageSource($imageUrl) : null,
        ];
    }

    protected function pdfImageSource(?string $imageUrl): string
    {
        if ($imageUrl && Str::startsWith($imageUrl, '/storage/')) {
            $path = public_path(ltrim($imageUrl, '/'));

            if (File::exists($path)) {
                $mime = File::mimeType($path) ?: 'image/png';
                $contents = base64_encode((string) File::get($path));

                return sprintf('data:%s;base64,%s', $mime, $contents);
            }
        }

        return $this->placeholderImageSource();
    }

    protected function placeholderImageSource(): string
    {
        $svg = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" width="72" height="72" viewBox="0 0 72 72">
  <rect width="72" height="72" rx="10" fill="#E5E7EB"/>
  <rect x="10" y="14" width="52" height="44" rx="6" fill="#F8FAFC" stroke="#CBD5E1"/>
  <circle cx="26" cy="28" r="6" fill="#CBD5E1"/>
  <path d="M16 52 30 38l10 10 8-8 8 12H16Z" fill="#94A3B8"/>
  <text x="36" y="66" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-size="9" fill="#64748B">No Image</text>
</svg>
SVG;

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    protected function selectedCategory(array $filters): ?Category
    {
        $categoryId = (int) ($filters['category_id'] ?? 0);

        return $categoryId > 0
            ? Category::query()->select('id', 'name', 'parent_id')->find($categoryId)
            : null;
    }

    /**
     * Categories with children include their descendants. Leaf categories stay scoped to themselves.
     *
     * @param Collection<int, Category>|null $categories
     * @param array<int, list<int>>|null $childrenByParent
     * @return list<int>
     */
    protected function reportCategoryIds(
        Category $category,
        ?array $childrenByParent = null,
        ?Collection $categories = null,
    ): array {
        $categoryId = (int) $category->id;

        $categories ??= Category::query()
            ->select('id', 'parent_id')
            ->get();
        $childrenByParent ??= $this->childrenByParent($categories);

        $ids = [$categoryId];
        $pending = $childrenByParent[$categoryId] ?? [];

        while ($pending) {
            $childId = array_shift($pending);
            $ids[] = $childId;

            foreach ($childrenByParent[$childId] ?? [] as $descendantId) {
                $pending[] = $descendantId;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param Collection<int, Category> $categories
     * @return array<int, list<int>>
     */
    protected function childrenByParent(Collection $categories): array
    {
        $childrenByParent = [];

        foreach ($categories as $category) {
            if ($category->parent_id === null) {
                continue;
            }

            $childrenByParent[(int) $category->parent_id][] = (int) $category->id;
        }

        return $childrenByParent;
    }

    /**
     * @return array<int, list<int>>
     */
    protected function activeProductIdsByCategory(): array
    {
        $rows = DB::table('category_product')
            ->join('products', 'products.id', '=', 'category_product.product_id')
            ->where('products.is_active', true)
            ->whereNull('products.deleted_at')
            ->select('category_product.category_id', 'category_product.product_id')
            ->get();

        $productIdsByCategory = [];

        foreach ($rows as $row) {
            $productIdsByCategory[(int) $row->category_id][] = (int) $row->product_id;
        }

        return $productIdsByCategory;
    }

    protected function normalizeFilters(array $filters): array
    {
        return [
            'category_id' => isset($filters['category_id']) ? (int) $filters['category_id'] : null,
            'in_stock_only' => filter_var($filters['in_stock_only'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'sort' => in_array(($filters['sort'] ?? 'default'), ['default', 'price_asc', 'price_desc', 'alphabetical', 'latest'], true)
                ? $filters['sort']
                : 'default',
        ];
    }

    protected function sortLabel(string $sort): string
    {
        return match ($sort) {
            'price_asc' => 'Price: Low to High',
            'price_desc' => 'Price: High to Low',
            default => 'Default',
        };
    }
}
