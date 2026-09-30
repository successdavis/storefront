<?php

namespace App\Http\Controllers;

use App\Domain\Inventory\Support\VariantNameFormatter;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\ProductService;
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
        $products = Product::query()
            ->with(['brand:id,name', 'images'])
            ->withSum(
                ['variants as total_available' => fn ($query) => $query->where('is_active', true)],
                'available'
            )
            ->where(fn ($query) => $query
                ->where('name', 'like', "%{$term}%")
                ->orWhere('slug', 'like', "%{$term}%"))
            ->orderByDesc('id')
            ->limit(6)
            ->get()
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

        $brands = Brand::query()
            ->withCount('products')
            ->where('name', 'like', "%{$term}%")
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
            ->where('name', 'like', "%{$term}%")
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

    protected function barcodeGroups(string $term): array
    {
        $variants = ProductVariant::query()
            ->with([
                'product:id,name',
                'product.images',
                'values:id,variant_type_id,value',
                'values.type:id,name',
            ])
            ->where(fn ($query) => $query
                ->where('sku', 'like', "%{$term}%")
                ->orWhere('barcode', 'like', "%{$term}%")
                ->orWhereHas('product', fn ($productQuery) => $productQuery->where('name', 'like', "%{$term}%")))
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
