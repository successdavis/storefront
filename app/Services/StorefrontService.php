<?php

namespace App\Services;

use App\Models\Category;
use App\Models\CustomerSavedItem;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class StorefrontService
{
    public function __construct(
        protected ProductService $productService,
        protected StorefrontSearchService $storefrontSearchService,
        protected CartService $cartService,
        protected CustomerSavedItemService $savedItemService,
        protected CustomerLocationResolver $customerLocationResolver,
        protected DeliveryEstimateService $deliveryEstimateService,
    ) {}

    public function homeData(array $filters = []): array
    {
        $search = trim((string) ($filters['q'] ?? ''));
        $categoryId = isset($filters['category']) && $filters['category'] !== ''
            ? (int) $filters['category']
            : null;
        $perPage = isset($filters['per_page']) ? max(1, min((int) $filters['per_page'], 48)) : 12;
        $browsingLocation = $this->resolveBrowsingLocation();

        Log::info($browsingLocation);

        $products = $this->productService->paginateStorefrontProducts(
            perPage: $perPage,
            search: $search !== '' ? $search : null,
            categoryId: $categoryId,
        );
        $products = $this->enrichPaginatedCards($products, $browsingLocation);

        return array_merge([
            'filters' => [
                'q' => $search,
                'category' => $categoryId,
                'per_page' => $perPage,
            ],
            'products' => $products,
            'featuredProducts' => $this->enrichCardPayloads($this->productService->getFeaturedProducts(8), $browsingLocation),
            'latestProducts' => $this->enrichCardPayloads($this->productService->getLatestProducts(8), $browsingLocation),
            'categoryPreviews' => $this->buildCategoryPreviews(4, 4, $browsingLocation),
            'pageTitle' => $this->resolveHomeTitle($search, $categoryId),
            'browsingLocation' => $browsingLocation,
        ], $this->sharedData($browsingLocation));
    }

    public function catalogData(array $filters = []): array
    {
        $perPage = isset($filters['per_page']) ? max(1, min((int) $filters['per_page'], 48)) : 12;
        $browsingLocation = $this->resolveBrowsingLocation();
        $products = $this->enrichPaginatedCards(
            $this->productService->paginateStorefrontProducts($perPage),
            $browsingLocation
        );

        return $this->collectionData(
            products: $products,
            title: 'Shop Catalog',
            description: 'Browse the full storefront catalog in one place.',
            browsingLocation: $browsingLocation,
            filters: ['per_page' => $perPage],
        );
    }

    public function featuredData(array $filters = []): array
    {
        $perPage = isset($filters['per_page']) ? max(1, min((int) $filters['per_page'], 48)) : 12;
        $browsingLocation = $this->resolveBrowsingLocation();
        $products = $this->enrichPaginatedCards(
            $this->productService->paginateFeaturedProducts($perPage),
            $browsingLocation
        );

        return $this->collectionData(
            products: $products,
            title: 'Featured Products',
            description: 'A curated list of highlighted products from the storefront.',
            browsingLocation: $browsingLocation,
            filters: ['per_page' => $perPage],
        );
    }

    public function latestData(array $filters = []): array
    {
        $perPage = isset($filters['per_page']) ? max(1, min((int) $filters['per_page'], 48)) : 12;
        $browsingLocation = $this->resolveBrowsingLocation();
        $products = $this->enrichPaginatedCards(
            $this->productService->paginateLatestProducts($perPage),
            $browsingLocation
        );

        return $this->collectionData(
            products: $products,
            title: 'Latest Products',
            description: 'See the newest products added to the storefront.',
            browsingLocation: $browsingLocation,
            filters: ['per_page' => $perPage],
            extra: [
                'infiniteScroll' => true,
            ],
        );
    }

    public function categoryData(Category $category, array $filters = []): array
    {
        $perPage = isset($filters['per_page']) ? max(1, min((int) $filters['per_page'], 48)) : 12;
        $browsingLocation = $this->resolveBrowsingLocation();
        $products = $this->enrichPaginatedCards(
            $this->productService->getProductsByCategory($category, $perPage),
            $browsingLocation
        );

        return $this->collectionData(
            products: $products,
            title: $category->name,
            description: "Products available in the {$category->name} category.",
            browsingLocation: $browsingLocation,
            filters: ['per_page' => $perPage],
            extra: [
                'infiniteScroll' => true,
                'activeCategory' => [
                    'id' => (int) $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                ],
            ],
        );
    }

    public function searchData(array $filters = []): array
    {
        $browsingLocation = $this->resolveBrowsingLocation();
        $searchData = $this->storefrontSearchService->search($filters);

        if (($searchData['results'] ?? null) instanceof LengthAwarePaginator) {
            $searchData['results'] = $this->enrichPaginatedCards($searchData['results'], $browsingLocation);
        }

        return array_merge(
            $searchData,
            [
                'browsingLocation' => $browsingLocation,
            ],
            $this->sharedData($browsingLocation),
        );
    }

    public function searchSuggestions(string $query): array
    {
        return $this->storefrontSearchService->suggestions($query);
    }

    public function productData(Product $product): array
    {
        $browsingLocation = $this->resolveBrowsingLocation();

        return array_merge([
            'product' => $this->enrichProductPayload($this->productService->getProductDetails($product), $browsingLocation),
            'relatedProducts' => $this->enrichCardPayloads($this->productService->getRelatedProducts($product, 8), $browsingLocation),
            'browsingLocation' => $browsingLocation,
        ], $this->sharedData($browsingLocation));
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
        $browsingLocation = $this->resolveBrowsingLocation();

        if (Auth::user()) {
            $savedForLater = $this->savedItemService
                ->paginate(Auth::user(), CustomerSavedItem::TYPE_SAVED_FOR_LATER, 6)
                ->items();
            $savedItemCounts = $this->savedItemService->counts(Auth::user());
        }

        return array_merge($cartData, [
            'savedForLater' => $savedForLater,
            'savedItemCounts' => $savedItemCounts,
            'browsingLocation' => $browsingLocation,
        ], $this->sharedData($browsingLocation));
    }

    public function productDeliveryEstimate(Product $product, ?int $variantId = null, ?array $destination = null): ?array
    {
        $resolvedDestination = $this->normalizeEstimateDestination($destination ?? $this->resolveBrowsingLocation());
        if (!$resolvedDestination) {
            return null;
        }

        $resolvedVariantId = $this->resolveProductVariantId($product, $variantId);
        if (!$resolvedVariantId) {
            return null;
        }

        $estimate = $this->deliveryEstimateService->estimateForVariantId($resolvedVariantId, $resolvedDestination, [
            'scope' => 'storefront',
        ]);

        return ($estimate['available'] ?? false) ? $estimate : null;
    }

    protected function buildCategoryPreviews(int $limit, int $productsPerCategory, ?array $browsingLocation = null): array
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
                    ->select('id', 'product_id', 'sku', 'quantity', 'reserved', 'regular_price', 'sale_starts_at', 'sale_ends_at', 'is_active'),
            ])
            ->latest('id')
            ->get();

        return $categories->map(function (Category $category) use ($products, $productsPerCategory, $browsingLocation) {
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
                'products' => $this->enrichCardPayloads($categoryProducts, $browsingLocation),
            ];
        })->values()->all();
    }

    protected function sharedData(?array $browsingLocation = null): array
    {
        return [
            'cartCount' => $this->cartService->getCartCount(),
            'categories' => $this->productService->listStoreCategories(),
            'browsingLocation' => $browsingLocation,
        ];
    }

    protected function collectionData(
        LengthAwarePaginator $products,
        string $title,
        string $description,
        ?array $browsingLocation = null,
        array $filters = [],
        array $extra = [],
    ): array {
        return array_merge([
            'pageTitle' => $title,
            'pageDescription' => $description,
            'products' => $products,
            'filters' => $filters,
            'browsingLocation' => $browsingLocation,
        ], $this->sharedData($browsingLocation), $extra);
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

    protected function resolveBrowsingLocation(): ?array
    {
        return $this->customerLocationResolver->resolveForRequest(request());
    }

    protected function enrichPaginatedCards(LengthAwarePaginator $paginator, ?array $browsingLocation = null): LengthAwarePaginator
    {
        $paginator->setCollection(
            collect($this->enrichCardPayloads($paginator->getCollection()->all(), $browsingLocation))
        );

        return $paginator;
    }

    protected function enrichCardPayloads(array $cards, ?array $browsingLocation = null): array
    {
        if (empty($cards)) {
            return [];
        }

        $destination = $this->normalizeEstimateDestination($browsingLocation);
        $variantIds = collect($cards)
            ->pluck('default_variant_id')
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();
        $estimates = $this->deliveryEstimateService->estimateManyForVariantIds($variantIds, $destination, [
            'scope' => 'storefront',
        ]);

        return collect($cards)->map(function (array $card) use ($estimates) {
            $variantId = (int) ($card['default_variant_id'] ?? 0);

            return array_merge($card, [
                'delivery_estimate' => $variantId > 0 ? ($estimates[$variantId] ?? null) : null,
            ]);
        })->all();
    }

    protected function enrichProductPayload(array $productPayload, ?array $browsingLocation = null): array
    {
        $destination = $this->normalizeEstimateDestination($browsingLocation);
        $variantIds = collect($productPayload['variants'] ?? [])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values()
            ->all();
        $estimates = $this->deliveryEstimateService->estimateManyForVariantIds($variantIds, $destination, [
            'scope' => 'storefront',
        ]);
        $defaultVariantId = (int) ($productPayload['default_variant_id'] ?? 0);

        $productPayload['variants'] = collect($productPayload['variants'] ?? [])
            ->map(function (array $variant) use ($estimates) {
                $variantId = (int) ($variant['id'] ?? 0);

                return array_merge($variant, [
                    'delivery_estimate' => $variantId > 0 ? ($estimates[$variantId] ?? null) : null,
                ]);
            })
            ->all();

        $productPayload['delivery_estimate'] = $defaultVariantId > 0
            ? ($estimates[$defaultVariantId] ?? null)
            : null;

        return $productPayload;
    }

    protected function normalizeEstimateDestination(?array $browsingLocation = null): ?array
    {
        if (!$browsingLocation) {
            return null;
        }

        if (empty($browsingLocation['state_id']) && empty($browsingLocation['lga_id']) && empty($browsingLocation['shipping_zone_id'])) {
            return null;
        }

        return [
            'country_id' => $browsingLocation['country_id'] ?? null,
            'state_id' => $browsingLocation['state_id'] ?? null,
            'lga_id' => $browsingLocation['lga_id'] ?? null,
            'state_name' => $browsingLocation['state_name'] ?? null,
            'city_name' => $browsingLocation['city_name'] ?? null,
            'destination_label' => $browsingLocation['destination_label'] ?? null,
        ];
    }

    protected function resolveProductVariantId(Product $product, ?int $variantId = null): ?int
    {
        $query = $product->variants()->active()->orderBy('id');

        if ($variantId) {
            return $query->whereKey($variantId)->value('id');
        }

        return $query->value('id');
    }
}
