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
        protected SeoService $seoService,
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

        $pageTitle = $this->resolveHomeTitle($search, $categoryId);
        $pageDescription = $this->resolveHomeDescription($search, $categoryId);

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
            'pageTitle' => $pageTitle,
            'browsingLocation' => $browsingLocation,
            'seo' => $this->seoService->page(
                title: $pageTitle === 'All Products' ? 'Shop Products Online' : $pageTitle,
                description: $pageDescription,
                canonical: route('store.home'),
            ),
            'structuredData' => $this->storefrontStructuredData([
                $this->seoService->breadcrumbSchema([
                    ['name' => 'Store', 'url' => route('store.home')],
                ]),
                $this->seoService->itemListSchema($products->getCollection()->all(), $pageTitle),
            ]),
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
            canonical: route('store.catalog'),
            breadcrumbs: $this->collectionBreadcrumbs('Shop Catalog', route('store.catalog')),
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
            canonical: route('store.featured'),
            breadcrumbs: $this->collectionBreadcrumbs('Featured Products', route('store.featured')),
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
            canonical: route('store.latest'),
            breadcrumbs: $this->collectionBreadcrumbs('Latest Products', route('store.latest')),
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
            description: $category->meta_description ?: $category->description ?: "Products available in the {$category->name} category.",
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
            canonical: $this->categoryUrl($category),
            seoTitle: $category->meta_title ?: $category->name,
            image: $category->cover_image_url ?: $category->banner_url,
            breadcrumbs: $this->categoryBreadcrumbs($category),
        );
    }

    public function searchData(array $filters = []): array
    {
        $browsingLocation = $this->resolveBrowsingLocation();
        $searchData = $this->storefrontSearchService->search($filters);

        if (($searchData['results'] ?? null) instanceof LengthAwarePaginator) {
            $searchData['results'] = $this->enrichPaginatedCards($searchData['results'], $browsingLocation);
        }

        if (($searchData['results'] ?? null) instanceof LengthAwarePaginator) {
            $searchData['seo'] = $this->seoService->page(
                title: $searchData['pageTitle'] ?? 'Search Results',
                description: $this->searchDescription($searchData),
                canonical: request()->fullUrl(),
                options: [
                    'robots' => SeoService::NOINDEX_ROBOTS,
                    'pagination' => $this->seoService->paginationLinks($searchData['results']),
                ],
            );
            $searchData['structuredData'] = $this->storefrontStructuredData([
                $this->seoService->itemListSchema($searchData['results']->getCollection()->all(), $searchData['pageTitle'] ?? 'Search Results'),
            ]);
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
        $productPayload = $this->enrichProductPayload($this->productService->getProductDetails($product), $browsingLocation);
        $canonical = route('store.product', $productPayload['slug']);
        $primaryCategory = collect($productPayload['categories'] ?? [])->first();
        $breadcrumbs = [
            ['name' => 'Store', 'url' => route('store.home')],
        ];

        if (filled($primaryCategory['slug'] ?? null)) {
            $breadcrumbs[] = [
                'name' => $primaryCategory['name'],
                'url' => route('store.category', ['category' => $primaryCategory['slug']]),
            ];
        }

        $breadcrumbs[] = ['name' => $productPayload['name'], 'url' => $canonical];

        return array_merge([
            'product' => $productPayload,
            'relatedProducts' => $this->enrichCardPayloads($this->productService->getRelatedProducts($product, 8), $browsingLocation),
            'browsingLocation' => $browsingLocation,
            'seo' => $this->seoService->page(
                title: $productPayload['meta_title'] ?: $productPayload['name'],
                description: $productPayload['meta_description'] ?: $productPayload['description'] ?: "Buy {$productPayload['name']} online from {$this->seoService->siteName()}.",
                canonical: $canonical,
                options: [
                    'image' => $productPayload['image'] ?? data_get($productPayload, 'images.0.url'),
                    'type' => 'product',
                ],
            ),
            'structuredData' => $this->storefrontStructuredData([
                $this->seoService->breadcrumbSchema($breadcrumbs),
                $this->seoService->productSchema($productPayload, $canonical),
                $this->seoService->faqSchema($productPayload['faqs'] ?? []),
            ]),
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
            'seo' => $this->seoService->page(
                title: 'Shopping Cart',
                description: 'Review your cart and continue checkout.',
                canonical: route('store.cart'),
                options: [
                    'robots' => SeoService::NOINDEX_ROBOTS,
                ],
            ),
            'structuredData' => $this->storefrontStructuredData(),
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
                'images:id,product_id,path,responsive_paths,alt,is_primary,sort_order',
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
            'storefront' => [
                'siteName' => $this->seoService->siteName(),
                'tagline' => $this->seoService->tagline(),
            ],
        ];
    }

    protected function collectionData(
        LengthAwarePaginator $products,
        string $title,
        string $description,
        ?array $browsingLocation = null,
        array $filters = [],
        array $extra = [],
        ?string $canonical = null,
        ?string $seoTitle = null,
        ?string $image = null,
        array $breadcrumbs = [],
    ): array {
        return array_merge([
            'pageTitle' => $title,
            'pageDescription' => $description,
            'products' => $products,
            'filters' => $filters,
            'browsingLocation' => $browsingLocation,
            'seo' => $this->seoService->page(
                title: $seoTitle ?: $title,
                description: $description,
                canonical: $canonical ?: url()->current(),
                options: [
                    'image' => $image,
                    'robots' => $products->total() > 0 ? SeoService::INDEX_ROBOTS : SeoService::NOINDEX_ROBOTS,
                    'pagination' => $this->seoService->paginationLinks($products),
                ],
            ),
            'structuredData' => $this->storefrontStructuredData([
                $this->seoService->breadcrumbSchema($breadcrumbs ?: $this->collectionBreadcrumbs($title, $canonical ?: url()->current())),
                $this->seoService->itemListSchema($products->getCollection()->all(), $title),
            ]),
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

    protected function resolveHomeDescription(string $search, ?int $categoryId): string
    {
        if ($search !== '') {
            return "Find products matching {$search} in the storefront catalog.";
        }

        if ($categoryId) {
            $categoryName = Category::query()->whereKey($categoryId)->value('name');

            if ($categoryName) {
                return "Shop {$categoryName} products and compare available options.";
            }
        }

        return 'Shop featured, latest, and category-based products from the storefront catalog.';
    }

    protected function storefrontStructuredData(array $schemas = []): array
    {
        return collect([
            $this->seoService->organizationSchema(),
            $this->seoService->websiteSchema(),
            ...$schemas,
        ])->filter()->values()->all();
    }

    protected function collectionBreadcrumbs(string $title, string $url): array
    {
        return [
            ['name' => 'Store', 'url' => route('store.home')],
            ['name' => $title, 'url' => $url],
        ];
    }

    protected function categoryBreadcrumbs(Category $category): array
    {
        $breadcrumbs = [
            ['name' => 'Store', 'url' => route('store.home')],
        ];

        foreach ($category->getParentTree() as $ancestor) {
            if (filled($ancestor->slug)) {
                $breadcrumbs[] = [
                    'name' => $ancestor->name,
                    'url' => $this->categoryUrl($ancestor),
                ];
            }
        }

        $breadcrumbs[] = [
            'name' => $category->name,
            'url' => $this->categoryUrl($category),
        ];

        return $breadcrumbs;
    }

    protected function categoryUrl(Category $category): string
    {
        if (filled($category->slug)) {
            return route('store.category', ['category' => $category->slug]);
        }

        return route('store.category.legacy', $category);
    }

    protected function searchDescription(array $searchData): string
    {
        $query = trim((string) data_get($searchData, 'summary.query', ''));
        $total = (int) data_get($searchData, 'summary.total', 0);

        if ($query !== '') {
            return "Search results for {$query}, with {$total} matching products and filters for category, brand, price, and availability.";
        }

        return 'Search the storefront catalog by product name, category, brand, price, and availability.';
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
