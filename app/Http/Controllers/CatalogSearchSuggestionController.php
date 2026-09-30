<?php

namespace App\Http\Controllers;

use App\Domain\Inventory\Support\VariantNameFormatter;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\ProductService;
use App\Support\SearchTerms;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Live suggestions for the admin/sales catalog search bars (products & barcode pages),
 * mirroring the storefront search bar's grouped payload shape.
 */
class CatalogSearchSuggestionController extends Controller
{
    public function __construct(
        protected ProductService $productService,
        protected VariantNameFormatter $variantNameFormatter,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'context' => ['required', Rule::in(['products', 'barcodes'])],
        ]);

        $term = trim((string) ($validated['q'] ?? ''));

        if (mb_strlen($term) < 2) {
            return response()->json(['groups' => []]);
        }

        $groups = $validated['context'] === 'products'
            ? $this->productGroups($term)
            : $this->barcodeGroups($term);

        return response()->json(['groups' => array_values(array_filter(
            $groups,
            fn (array $group): bool => $group['items'] !== []
        ))]);
    }

    protected function productGroups(string $term): array
    {
        $termGroups = SearchTerms::groups($term);

        $products = $this->productQuery($termGroups, requireAllTerms: true)->get();

        // Same fallback as the storefront: if no product matches every word,
        // retry matching any word so near-misses still surface.
        if ($products->isEmpty() && count($termGroups) > 1) {
            $products = $this->productQuery($termGroups, requireAllTerms: false)->get();
        }

        $products = $products
            ->map(fn (Product $product): array => [
                'id' => "product:{$product->id}",
                'type' => 'product',
                'label' => $product->name,
                'meta' => collect([
                    $product->brand?->name,
                    $product->is_active ? null : 'Draft',
                    sprintf('%d in stock', max(0, (int) $product->total_available)),
                ])->filter()->implode(' · '),
                'image' => $this->productService->resolveProductImage($product),
                'href' => route('admin.products.show', $product),
            ])
            ->all();

        $tokenPatterns = collect($termGroups)
            ->flatten()
            ->map(fn (string $token): string => "%{$token}%")
            ->values()
            ->all();

        $brands = Brand::query()
            ->withCount('products')
            ->where(function (Builder $query) use ($tokenPatterns) {
                foreach ($tokenPatterns as $pattern) {
                    $query->orWhere('name', 'like', $pattern);
                }
            })
            ->orderBy('name')
            ->limit(4)
            ->get()
            ->map(fn (Brand $brand): array => [
                'id' => "brand:{$brand->id}",
                'type' => 'brand',
                'label' => $brand->name,
                'meta' => sprintf('Brand · %d product%s', $brand->products_count, $brand->products_count === 1 ? '' : 's'),
                'image' => null,
                'href' => route('admin.products.index', ['brand_id' => $brand->id]),
            ])
            ->all();

        $categories = Category::query()
            ->withCount('products')
            ->where(function (Builder $query) use ($tokenPatterns) {
                foreach ($tokenPatterns as $pattern) {
                    $query->orWhere('name', 'like', $pattern);
                }
            })
            ->orderBy('name')
            ->limit(4)
            ->get()
            ->map(fn (Category $category): array => [
                'id' => "category:{$category->id}",
                'type' => 'category',
                'label' => $category->name,
                'meta' => sprintf('Category · %d product%s', $category->products_count, $category->products_count === 1 ? '' : 's'),
                'image' => null,
                'href' => route('admin.products.index', ['category_id' => $category->id]),
            ])
            ->all();

        return [
            ['key' => 'products', 'label' => 'Products', 'items' => $products],
            ['key' => 'brands', 'label' => 'Brands', 'items' => $brands],
            ['key' => 'categories', 'label' => 'Categories', 'items' => $categories],
        ];
    }

    /**
     * Candidate products where each word group matches the name, slug, brand,
     * or a variant SKU/barcode (any word when $requireAllTerms is false).
     *
     * @param array<int, array<int, string>> $termGroups
     */
    protected function productQuery(array $termGroups, bool $requireAllTerms): Builder
    {
        return Product::query()
            ->with(['brand:id,name', 'images'])
            ->withSum(
                ['variants as total_available' => fn ($query) => $query->where('is_active', true)],
                'available'
            )
            ->where(function (Builder $query) use ($termGroups, $requireAllTerms) {
                foreach ($termGroups as $index => $expansions) {
                    $method = ($requireAllTerms || $index === 0) ? 'where' : 'orWhere';

                    $query->{$method}(function (Builder $termQuery) use ($expansions) {
                        foreach ($expansions as $expansion) {
                            $pattern = "%{$expansion}%";
                            $termQuery
                                ->orWhere('name', 'like', $pattern)
                                ->orWhere('slug', 'like', $pattern)
                                ->orWhereHas('brand', fn (Builder $brandQuery) => $brandQuery->where('name', 'like', $pattern))
                                ->orWhereHas('variants', fn (Builder $variantQuery) => $variantQuery
                                    ->where('sku', 'like', $pattern)
                                    ->orWhere('barcode', 'like', $pattern));
                        }
                    });
                }
            })
            ->orderByDesc('id')
            ->limit(6);
    }

    protected function barcodeGroups(string $term): array
    {
        $termGroups = SearchTerms::groups($term);

        $variants = ProductVariant::query()
            ->with([
                'product:id,name',
                'product.images',
                'values:id,variant_type_id,value',
                'values.type:id,name',
            ])
            ->where(function (Builder $query) use ($termGroups) {
                foreach ($termGroups as $expansions) {
                    $query->where(function (Builder $termQuery) use ($expansions) {
                        foreach ($expansions as $expansion) {
                            $pattern = "%{$expansion}%";
                            $termQuery
                                ->orWhere('sku', 'like', $pattern)
                                ->orWhere('barcode', 'like', $pattern)
                                ->orWhereHas('product', fn (Builder $productQuery) => $productQuery->where('name', 'like', $pattern));
                        }
                    });
                }
            })
            ->orderBy('id')
            ->limit(8)
            ->get()
            ->map(fn (ProductVariant $variant): array => [
                'id' => "variant:{$variant->id}",
                'type' => 'variant',
                'label' => $this->variantNameFormatter->format($variant),
                'meta' => collect([
                    $variant->sku ? "SKU {$variant->sku}" : null,
                    $variant->isDropshipping()
                        ? 'Dropshipping'
                        : sprintf('%d available', max(0, (int) $variant->available)),
                ])->filter()->implode(' · '),
                'image' => $variant->product
                    ? $this->productService->resolveProductImage($variant->product, $variant)
                    : null,
                'href' => null,
                // The barcode page filters its table by this instead of navigating away.
                'apply_search' => $variant->sku ?: $variant->barcode,
            ])
            ->all();

        return [
            ['key' => 'variants', 'label' => 'Product Variants', 'items' => $variants],
        ];
    }
}
