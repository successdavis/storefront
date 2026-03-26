<?php

namespace App\Services;

use App\Models\Category;
use App\Models\CustomerSavedItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class StorefrontService
{
    public function __construct(
        protected ProductService $productService,
        protected CartService $cartService,
        protected CustomerSavedItemService $savedItemService,
    ) {}

    public function homeData(array $filters = []): array
    {
        $search = trim((string) ($filters['q'] ?? ''));
        $categoryId = isset($filters['category']) && $filters['category'] !== ''
            ? (int) $filters['category']
            : null;
        $perPage = isset($filters['per_page']) ? max(1, min((int) $filters['per_page'], 48)) : 12;

        $products = $this->productService->paginateStorefrontProducts(
            perPage: $perPage,
            search: $search !== '' ? $search : null,
            categoryId: $categoryId,
        );

        return array_merge([
            'filters' => [
                'q' => $search,
                'category' => $categoryId,
                'per_page' => $perPage,
            ],
            'products' => $products,
            'featuredProducts' => $this->productService->getFeaturedProducts(8),
            'latestProducts' => $this->productService->getLatestProducts(8),
            'categoryPreviews' => $this->buildCategoryPreviews(4, 4),
            'pageTitle' => $this->resolveHomeTitle($search, $categoryId),
        ], $this->sharedData());
    }

    public function categoryData(Category $category, array $filters = []): array
    {
        $data = $this->homeData(array_merge($filters, [
            'category' => $category->id,
        ]));

        $data['pageTitle'] = $category->name;
        $data['activeCategory'] = [
            'id' => (int) $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
        ];

        return $data;
    }

    public function productData(Product $product): array
    {
        return array_merge([
            'product' => $this->productService->getProductDetails($product),
            'relatedProducts' => $this->productService->getRelatedProducts($product, 8),
        ], $this->sharedData());
    }

    public function cartData(?string $couponCode = null): array
    {
        if ($couponCode !== null) {
            $this->cartService->setCoupon($couponCode);
        }

        $cartData = $this->cartService->getDetailedCart($couponCode);
        $savedForLater = [];
        $savedItemCounts = [
            CustomerSavedItem::TYPE_WISHLIST => 0,
            CustomerSavedItem::TYPE_SAVED_FOR_LATER => 0,
        ];

        if (Auth::user()) {
            $savedForLater = $this->savedItemService
                ->paginate(Auth::user(), CustomerSavedItem::TYPE_SAVED_FOR_LATER, 6)
                ->items();
            $savedItemCounts = $this->savedItemService->counts(Auth::user());
        }

        return array_merge($cartData, [
            'savedForLater' => $savedForLater,
            'savedItemCounts' => $savedItemCounts,
        ], $this->sharedData());
    }

    protected function buildCategoryPreviews(int $limit, int $productsPerCategory): array
    {
        $categories = Category::query()
            ->select(['id', 'name', 'slug', 'featured', 'order'])
            ->whereHas('products', fn (Builder $query) => $query
                ->where('is_active', true)
                ->whereHas('variants', fn (Builder $variantQuery) => $variantQuery->where('is_active', true)))
            ->withCount([
                'products as active_products_count' => fn (Builder $query) => $query
                    ->where('is_active', true)
                    ->whereHas('variants', fn (Builder $variantQuery) => $variantQuery->where('is_active', true)),
            ])
            ->orderByDesc('featured')
            ->orderBy('order')
            ->orderBy('name')
            ->limit($limit)
            ->get();

        if ($categories->isEmpty()) {
            return [];
        }

        $categoryIds = $categories->pluck('id')->all();

        $products = Product::query()
            ->active()
            ->whereHas('variants', fn (Builder $query) => $query->where('is_active', true))
            ->whereHas('categories', fn (Builder $query) => $query->whereIn('categories.id', $categoryIds))
            ->with([
                'categories:id,name,slug',
                'images:id,product_id,path,alt,is_primary,sort_order',
                'variants' => fn ($query) => $query
                    ->where('is_active', true)
                    ->select('id', 'product_id', 'sku', 'quantity', 'reserved', 'regular_price', 'sale_price', 'sale_starts_at', 'sale_ends_at', 'is_active'),
            ])
            ->latest('id')
            ->get();

        return $categories->map(function (Category $category) use ($products, $productsPerCategory) {
            $categoryProducts = $products
                ->filter(fn (Product $product) => $product->categories->contains('id', $category->id))
                ->take($productsPerCategory)
                ->map(fn (Product $product) => $this->productService->toStorefrontCard($product))
                ->values()
                ->all();

            return [
                'id' => (int) $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'active_products_count' => (int) $category->active_products_count,
                'products' => $categoryProducts,
            ];
        })->values()->all();
    }

    protected function sharedData(): array
    {
        return [
            'cartCount' => $this->cartService->getCartCount(),
            'categories' => $this->productService->listStoreCategories(),
        ];
    }

    protected function resolveHomeTitle(string $search, ?int $categoryId): string
    {
        if ($search !== '') {
            return "Search results for '{$search}'";
        }

        if ($categoryId) {
            $categoryName = Category::query()->whereKey($categoryId)->value('name');
            return $categoryName ?: 'Category Products';
        }

        return 'All Products';
    }
}
