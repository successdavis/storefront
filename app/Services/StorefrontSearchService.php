<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class StorefrontSearchService
{
    protected const DEFAULT_PER_PAGE = 24;

    protected const MAX_PER_PAGE = 48;

    protected const RESERVED_FILTER_KEYS = [
        'q',
        'sort',
        'page',
        'per_page',
        'category',
        'brand',
        'min_price',
        'max_price',
        'in_stock',
        'on_sale',
    ];

    public function __construct(
        protected ProductService $productService,
    ) {}

    public function search(array $input = [], ?User $user = null): array
    {
        $filters = $this->normalizeFilters($input);
        $user ??= auth()->user();

        $contextProducts = $this->buildContextQuery($filters)
            ->with($this->searchRelations())
            ->get();

        // Amazon-style relaxation: results normally require every search word
        // to match somewhere; when that yields nothing, fall back to partial
        // matches ranked by how many words they cover.
        if ($contextProducts->isEmpty() && $filters['q'] !== '' && count($this->parseSearchTerms($filters['q'])) > 1) {
            $contextProducts = $this->buildContextQuery($filters, false)
                ->with($this->searchRelations())
                ->get();
        }

        $items = $this->buildSearchItems($contextProducts, $user, $filters['q']);
        $facetedItems = $this->applyCollectionFilters($items, $filters, false);
        $filterData = $this->buildFilterData($facetedItems, $filters);
        $resultItems = $this->applyCollectionFilters($facetedItems, $filters, true);
        $sortedItems = $this->sortItems($resultItems, $filters['sort']);
        $paginator = $this->paginateItems($sortedItems, $filters);

        return [
            'pageTitle' => $this->resolvePageTitle($filters),
            'filters' => $this->serializeFilters($filters),
            'results' => $paginator,
            'summary' => [
                'query' => $filters['q'],
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
            'availableSorts' => $this->availableSorts($filters['q'] !== ''),
            'priceRange' => $filterData['price'],
            'toggleFilters' => $filterData['toggles'],
            'filterGroups' => $filterData['groups'],
            'activeFilters' => $this->buildActiveFilters($filters, $filterData),
        ];
    }

    public function suggestions(string $query, ?User $user = null): array
    {
        $query = trim($query);
        $user ??= auth()->user();

        if (Str::length($query) < 2) {
            return [
                'query' => $query,
                'groups' => [],
            ];
        }

        $candidates = $this->buildSearchQuery($query)
            ->with($this->searchRelations())
            ->limit(40)
            ->get();

        if ($candidates->isEmpty() && count($this->parseSearchTerms($query)) > 1) {
            $candidates = $this->buildSearchQuery($query, false)
                ->with($this->searchRelations())
                ->limit(40)
                ->get();
        }

        $products = $candidates
            ->sortByDesc(fn (Product $product) => $this->scoreProduct($product, $query))
            ->take(5)
            ->map(function (Product $product) use ($user) {
                $card = $this->productService->toStorefrontCard($product, $user, true);

                return [
                    'id' => 'product:' . $product->id,
                    'type' => 'product',
                    'label' => $product->name,
                    'meta' => trim(implode(' | ', array_filter([
                        $product->brand?->name,
                        $this->formatCurrency((float) ($card['price']['current'] ?? 0)),
                    ]))),
                    'href' => route('store.product', $product->slug),
                    'image' => $card['image'],
                ];
            })
            ->values();

        $termPatterns = collect($this->parseSearchTerms($query))
            ->flatten()
            ->map(fn (string $term) => '%' . $term . '%')
            ->values()
            ->all();
        if (empty($termPatterns)) {
            $termPatterns = ['%' . $query . '%'];
        }

        $categories = Category::query()
            ->select(['id', 'name', 'slug'])
            ->where(function (Builder $queryBuilder) use ($termPatterns) {
                foreach ($termPatterns as $pattern) {
                    $queryBuilder
                        ->orWhere('name', 'like', $pattern)
                        ->orWhere('slug', 'like', $pattern);
                }
            })
            ->whereHas('products', fn (Builder $productQuery) => $productQuery
                ->where('is_active', true)
                ->whereHas('variants', fn (Builder $variantQuery) => $variantQuery->where('is_active', true)))
            ->withCount([
                'products as active_products_count' => fn (Builder $productQuery) => $productQuery
                    ->where('is_active', true)
                    ->whereHas('variants', fn (Builder $variantQuery) => $variantQuery->where('is_active', true)),
            ])
            ->orderByDesc('active_products_count')
            ->orderBy('name')
            ->limit(4)
            ->get()
            ->map(fn (Category $category) => [
                'id' => 'category:' . $category->id,
                'type' => 'category',
                'label' => $category->name,
                'meta' => (int) $category->active_products_count . ' products',
                'href' => route('store.search', [
                    'q' => $query,
                    'category' => $this->categoryToken($category),
                ]),
            ])
            ->values();

        $brands = Brand::query()
            ->select(['id', 'name', 'slug'])
            ->where(function (Builder $queryBuilder) use ($termPatterns) {
                foreach ($termPatterns as $pattern) {
                    $queryBuilder->orWhere('name', 'like', $pattern);
                }
            })
            ->whereHas('products', fn (Builder $productQuery) => $productQuery
                ->where('is_active', true)
                ->whereHas('variants', fn (Builder $variantQuery) => $variantQuery->where('is_active', true)))
            ->withCount([
                'products as active_products_count' => fn (Builder $productQuery) => $productQuery
                    ->where('is_active', true)
                    ->whereHas('variants', fn (Builder $variantQuery) => $variantQuery->where('is_active', true)),
            ])
            ->orderByDesc('active_products_count')
            ->orderBy('name')
            ->limit(4)
            ->get()
            ->map(fn (Brand $brand) => [
                'id' => 'brand:' . $brand->id,
                'type' => 'brand',
                'label' => $brand->name,
                'meta' => (int) $brand->active_products_count . ' products',
                'href' => route('store.search', [
                    'q' => $query,
                    'brand' => $this->brandToken($brand),
                ]),
            ])
            ->values();

        $querySuggestions = collect([
            [
                'id' => 'query:' . Str::lower($query),
                'type' => 'query',
                'label' => $query,
                'meta' => 'Search the full catalog',
                'href' => route('store.search', ['q' => $query]),
            ],
        ]);

        if ($categories->isNotEmpty()) {
            $topCategory = $categories->first();
            $querySuggestions->push([
                'id' => 'query:category:' . Str::lower($query),
                'type' => 'query',
                'label' => $query . ' in ' . $topCategory['label'],
                'meta' => 'Narrow to category results',
                'href' => $topCategory['href'],
            ]);
        }

        if ($brands->isNotEmpty()) {
            $topBrand = $brands->first();
            $querySuggestions->push([
                'id' => 'query:brand:' . Str::lower($query),
                'type' => 'query',
                'label' => $topBrand['label'] . ' ' . $query,
                'meta' => 'Search with brand context',
                'href' => route('store.search', ['q' => $topBrand['label'] . ' ' . $query]),
            ]);
        }

        $groups = collect([
            [
                'key' => 'queries',
                'label' => 'Suggestions',
                'items' => $querySuggestions
                    ->unique('label')
                    ->values()
                    ->all(),
            ],
            [
                'key' => 'products',
                'label' => 'Products',
                'items' => $products->all(),
            ],
            [
                'key' => 'categories',
                'label' => 'Categories',
                'items' => $categories->all(),
            ],
            [
                'key' => 'brands',
                'label' => 'Brands',
                'items' => $brands->all(),
            ],
        ])->filter(fn (array $group) => !empty($group['items']))->values();

        return [
            'query' => $query,
            'groups' => $groups->all(),
        ];
    }

    protected function normalizeFilters(array $input): array
    {
        $query = trim((string) ($input['q'] ?? ''));
        $perPage = isset($input['per_page'])
            ? max(1, min((int) $input['per_page'], self::MAX_PER_PAGE))
            : self::DEFAULT_PER_PAGE;
        $page = isset($input['page']) ? max(1, (int) $input['page']) : 1;

        $filters = [
            'q' => $query,
            'sort' => $this->normalizeSort($input['sort'] ?? null, $query !== ''),
            'page' => $page,
            'per_page' => $perPage,
            'category' => $this->normalizeCsvFilter($input['category'] ?? null),
            'brand' => $this->normalizeCsvFilter($input['brand'] ?? null),
            'min_price' => $this->normalizeNullableFloat($input['min_price'] ?? null),
            'max_price' => $this->normalizeNullableFloat($input['max_price'] ?? null),
            'in_stock' => $this->normalizeBoolean($input['in_stock'] ?? false),
            'on_sale' => $this->normalizeBoolean($input['on_sale'] ?? false),
            'attributes' => [],
        ];

        foreach ($input as $key => $value) {
            if (in_array($key, self::RESERVED_FILTER_KEYS, true)) {
                continue;
            }

            $normalized = $this->normalizeCsvFilter($value);
            if (!empty($normalized)) {
                $filters['attributes'][$key] = $normalized;
            }
        }

        return $filters;
    }

    protected function buildContextQuery(array $filters, bool $requireAllTerms = true): Builder
    {
        return $this->buildSearchQuery($filters['q'], $requireAllTerms)
            ->when(!empty($filters['category']), function (Builder $query) use ($filters) {
                $tokens = $filters['category'];

                $query->whereHas('categories', function (Builder $categoryQuery) use ($tokens) {
                    $categoryQuery->where(function (Builder $nested) use ($tokens) {
                        foreach ($tokens as $token) {
                            if (is_numeric($token)) {
                                $nested->orWhere('categories.id', (int) $token);
                            }

                            $nested->orWhere('categories.slug', $token)
                                ->orWhere('categories.name', $token);
                        }
                    });
                });
            })
            ->when(!empty($filters['brand']), function (Builder $query) use ($filters) {
                $tokens = $filters['brand'];

                $query->whereHas('brand', function (Builder $brandQuery) use ($tokens) {
                    $brandQuery->where(function (Builder $nested) use ($tokens) {
                        foreach ($tokens as $token) {
                            if (is_numeric($token)) {
                                $nested->orWhere('brands.id', (int) $token);
                            }

                            $nested->orWhere('brands.slug', $token)
                                ->orWhere('brands.name', $token);
                        }
                    });
                });
            })
            ->when($filters['in_stock'], function (Builder $query) {
                $query->whereHas('variants', fn (Builder $variantQuery) => $variantQuery
                    ->where('is_active', true)
                    ->whereColumn('quantity', '>', 'reserved'));
            });
    }

    protected function buildSearchQuery(?string $query, bool $requireAllTerms = true): Builder
    {
        $search = trim((string) $query);
        $builder = Product::query()
            ->select('products.*')
            ->where('products.is_active', true)
            ->whereHas('variants', fn (Builder $variantQuery) => $variantQuery->where('is_active', true));

        if ($search === '') {
            return $builder
                ->orderByDesc('products.featured')
                ->latest('products.created_at');
        }

        $termGroups = $this->parseSearchTerms($search);
        if (empty($termGroups)) {
            $termGroups = [[mb_strtolower($search)]];
        }

        // Each word must match at least one field (name, brand, category,
        // variant data, description). Relevance itself is scored in PHP —
        // see scoreProduct() — so ordering here is only a candidate tiebreak.
        $builder->where(function (Builder $queryBuilder) use ($termGroups, $requireAllTerms) {
            foreach ($termGroups as $index => $expansions) {
                $method = ($requireAllTerms || $index === 0) ? 'where' : 'orWhere';

                $queryBuilder->{$method}(function (Builder $termQuery) use ($expansions) {
                    $this->applyTermConstraint($termQuery, $expansions);
                });
            }
        });

        return $builder
            ->orderByDesc('products.featured')
            ->latest('products.created_at');
    }

    /**
     * Split a query into lowercase word groups, each with its singular form
     * so "laptops" also matches "laptop" (and vice versa via substring).
     * Stopwords and one-character noise are dropped.
     *
     * @return array<int, array<int, string>>
     */
    protected function parseSearchTerms(string $search): array
    {
        $stopwords = ['the', 'a', 'an', 'and', 'or', 'for', 'with', 'of', 'in', 'on', 'to', 'by', 'from'];

        $tokens = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower(trim($search)), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return collect($tokens)
            ->filter(fn (string $token) => mb_strlen($token) >= 2 && !in_array($token, $stopwords, true))
            ->unique()
            ->take(6)
            ->map(fn (string $token) => collect([$token, Str::singular($token)])
                ->filter(fn (string $term) => mb_strlen($term) >= 2)
                ->unique()
                ->values()
                ->all())
            ->filter()
            ->values()
            ->all();
    }

    protected function applyTermConstraint(Builder $query, array $expansions): void
    {
        $patterns = array_map(fn (string $term) => '%' . $term . '%', $expansions);

        $query->where(function (Builder $fieldQuery) use ($patterns) {
            foreach ($patterns as $pattern) {
                $fieldQuery
                    ->orWhere('products.name', 'like', $pattern)
                    ->orWhere('products.slug', 'like', $pattern)
                    ->orWhere('products.description', 'like', $pattern);
            }

            $fieldQuery
                ->orWhereHas('brand', function (Builder $brandQuery) use ($patterns) {
                    $brandQuery->where(function (Builder $nested) use ($patterns) {
                        foreach ($patterns as $pattern) {
                            $nested->orWhere('name', 'like', $pattern);
                        }
                    });
                })
                ->orWhereHas('categories', function (Builder $categoryQuery) use ($patterns) {
                    $categoryQuery->where(function (Builder $nested) use ($patterns) {
                        foreach ($patterns as $pattern) {
                            $nested
                                ->orWhere('name', 'like', $pattern)
                                ->orWhere('slug', 'like', $pattern);
                        }
                    });
                })
                ->orWhereHas('variants', function (Builder $variantQuery) use ($patterns) {
                    $variantQuery
                        ->where('is_active', true)
                        ->where(function (Builder $nested) use ($patterns) {
                            foreach ($patterns as $pattern) {
                                $nested
                                    ->orWhere('sku', 'like', $pattern)
                                    ->orWhere('barcode', 'like', $pattern);
                            }

                            $nested->orWhereHas('values', function (Builder $valueQuery) use ($patterns) {
                                $valueQuery->where(function (Builder $valueNested) use ($patterns) {
                                    foreach ($patterns as $pattern) {
                                        $valueNested
                                            ->orWhere('variant_values.value', 'like', $pattern)
                                            ->orWhereHas('type', fn (Builder $typeQuery) => $typeQuery->where('name', 'like', $pattern));
                                    }
                                });
                            });
                        });
                });
        });
    }

    /**
     * Field-weighted relevance with phrase, coverage, and brand+category
     * bonuses. Runs against eager-loaded relations, so no extra queries.
     */
    protected function scoreProduct(Product $product, string $search): float
    {
        $phrase = mb_strtolower(trim($search));
        if ($phrase === '') {
            return 0.0;
        }

        $termGroups = $this->parseSearchTerms($search);
        if (empty($termGroups)) {
            $termGroups = [[$phrase]];
        }

        $name = mb_strtolower((string) $product->name);
        $slug = mb_strtolower((string) $product->slug);
        $description = mb_strtolower((string) ($product->description ?? ''));
        $brand = mb_strtolower((string) ($product->brand?->name ?? ''));

        $categoryHaystacks = $product->categories
            ->flatMap(fn (Category $category) => [$category->name, $category->slug])
            ->filter()
            ->map(fn ($value) => mb_strtolower((string) $value))
            ->all();

        $skuHaystacks = [];
        $attributeHaystacks = [];
        foreach ($product->variants->where('is_active', true) as $variant) {
            foreach ([$variant->sku, $variant->barcode] as $code) {
                if (filled($code)) {
                    $skuHaystacks[] = mb_strtolower((string) $code);
                }
            }

            foreach ($variant->values as $value) {
                foreach ([$value->value, $value->type?->name] as $attribute) {
                    if (filled($attribute)) {
                        $attributeHaystacks[] = mb_strtolower((string) $attribute);
                    }
                }
            }
        }

        $score = 0.0;

        if ($name === $phrase) {
            $score += 500;
        } elseif (str_starts_with($name, $phrase)) {
            $score += 300;
        } elseif (str_contains($name, $phrase)) {
            $score += 200;
        }

        $matchedTerms = 0;
        $brandTermHit = false;
        $categoryTermHit = false;

        foreach ($termGroups as $expansions) {
            $termMatched = false;

            if ($this->haystackMatches($name, $expansions)) {
                $score += 60;
                $termMatched = true;

                foreach ($expansions as $term) {
                    if (str_starts_with($name, $term)) {
                        $score += 20;
                        break;
                    }
                }
            }

            if ($brand !== '' && $this->haystackMatches($brand, $expansions)) {
                $score += 50;
                $termMatched = true;
                $brandTermHit = true;
            }

            if ($this->anyHaystackMatches($categoryHaystacks, $expansions)) {
                $score += 45;
                $termMatched = true;
                $categoryTermHit = true;
            }

            if ($this->anyHaystackMatches($skuHaystacks, $expansions)) {
                $score += 40;
                $termMatched = true;
            }

            if ($this->anyHaystackMatches($attributeHaystacks, $expansions)) {
                $score += 30;
                $termMatched = true;
            }

            if ($slug !== '' && $this->haystackMatches($slug, $expansions)) {
                $score += 25;
                $termMatched = true;
            }

            if ($description !== '' && $this->haystackMatches($description, $expansions)) {
                $score += 10;
                $termMatched = true;
            }

            if ($termMatched) {
                $matchedTerms++;
            }
        }

        $termCount = count($termGroups);
        if ($termCount > 0) {
            $score += ($matchedTerms / $termCount) * 120;
        }

        // Query understanding: when one word names the brand and another the
        // category ("dell laptops"), products actually in that brand AND
        // category outrank ones that merely mention the words in text.
        if ($termCount >= 2 && $brandTermHit && $categoryTermHit) {
            $score += 150;
        }

        return round($score, 2);
    }

    protected function haystackMatches(string $haystack, array $terms): bool
    {
        foreach ($terms as $term) {
            if ($term !== '' && str_contains($haystack, $term)) {
                return true;
            }
        }

        return false;
    }

    protected function anyHaystackMatches(array $haystacks, array $terms): bool
    {
        foreach ($haystacks as $haystack) {
            if ($this->haystackMatches($haystack, $terms)) {
                return true;
            }
        }

        return false;
    }

    protected function searchRelations(): array
    {
        return [
            'brand:id,name,slug',
            'categories:id,name,slug',
            'images:id,product_id,path,responsive_paths,alt,is_primary,sort_order',
            'variants' => fn ($query) => $query
                ->where('is_active', true)
                ->select([
                    'id',
                    'product_id',
                    'sku',
                    'barcode',
                    'quantity',
                    'reserved',
                    'regular_price',
                    'sale_starts_at',
                    'sale_ends_at',
                    'is_active',
                ])
                ->with([
                    'values:id,variant_type_id,value',
                    'values.type:id,name,slug',
                ])
                ->orderBy('regular_price')
                ->orderBy('id'),
        ];
    }

    protected function buildSearchItems(Collection $products, ?User $user = null, string $query = ''): Collection
    {
        return $products->map(function (Product $product) use ($user, $query) {
            return [
                'product' => $product,
                'card' => $this->productService->toStorefrontCard($product, $user, true),
                'relevance' => $query !== '' ? $this->scoreProduct($product, $query) : 0.0,
                'created_at' => $product->created_at?->getTimestamp() ?? 0,
            ];
        })->values();
    }

    protected function applyCollectionFilters(Collection $items, array $filters, bool $includeAttributes): Collection
    {
        return $items
            ->filter(function (array $item) use ($filters, $includeAttributes) {
                $price = (float) ($item['card']['price']['current'] ?? 0);

                if ($filters['min_price'] !== null && $price < $filters['min_price']) {
                    return false;
                }

                if ($filters['max_price'] !== null && $price > $filters['max_price']) {
                    return false;
                }

                if ($filters['on_sale'] && !($item['card']['price']['has_discount'] ?? false)) {
                    return false;
                }

                if ($includeAttributes && !$this->matchesAttributeFilters($item['product'], $filters['attributes'])) {
                    return false;
                }

                return true;
            })
            ->values();
    }

    protected function matchesAttributeFilters(Product $product, array $attributeFilters): bool
    {
        if (empty($attributeFilters)) {
            return true;
        }

        return $product->variants
            ->where('is_active', true)
            ->contains(function (ProductVariant $variant) use ($attributeFilters) {
                $normalizedValues = $variant->values
                    ->groupBy(fn ($value) => $this->attributeKey($value->type?->slug, $value->type?->name))
                    ->map(fn (Collection $values) => $values
                        ->pluck('value')
                        ->filter()
                        ->map(fn ($value) => $this->normalizeLabel($value))
                        ->all())
                    ->all();

                foreach ($attributeFilters as $key => $selectedValues) {
                    $variantValues = $normalizedValues[$key] ?? [];
                    $selected = collect($selectedValues)
                        ->map(fn ($value) => $this->normalizeLabel($value))
                        ->all();

                    if (empty(array_intersect($variantValues, $selected))) {
                        return false;
                    }
                }

                return true;
            });
    }

    protected function buildFilterData(Collection $items, array $filters): array
    {
        $categoryBuckets = [];
        $brandBuckets = [];
        $attributeBuckets = [];
        $minimumPrice = null;
        $maximumPrice = null;
        $inStockCount = 0;
        $onSaleCount = 0;

        foreach ($items as $item) {
            $product = $item['product'];
            $card = $item['card'];
            $price = (float) ($card['price']['current'] ?? 0);

            $minimumPrice = $minimumPrice === null ? $price : min($minimumPrice, $price);
            $maximumPrice = $maximumPrice === null ? $price : max($maximumPrice, $price);

            if ($card['stock']['is_in_stock'] ?? false) {
                $inStockCount++;
            }

            if ($card['price']['has_discount'] ?? false) {
                $onSaleCount++;
            }

            foreach ($product->categories as $category) {
                $token = $this->categoryToken($category);
                $categoryBuckets[$token] = [
                    'value' => $token,
                    'label' => $category->name,
                    'count' => ($categoryBuckets[$token]['count'] ?? 0) + 1,
                    'selected' => in_array($token, $filters['category'], true),
                    'type' => 'category',
                ];
            }

            if ($product->brand) {
                $token = $this->brandToken($product->brand);
                $brandBuckets[$token] = [
                    'value' => $token,
                    'label' => $product->brand->name,
                    'count' => ($brandBuckets[$token]['count'] ?? 0) + 1,
                    'selected' => in_array($token, $filters['brand'], true),
                    'type' => 'brand',
                ];
            }

            $productAttributes = [];

            foreach ($product->variants->where('is_active', true) as $variant) {
                foreach ($variant->values as $value) {
                    $groupKey = $this->attributeKey($value->type?->slug, $value->type?->name);
                    if ($groupKey === '') {
                        continue;
                    }

                    $groupLabel = $value->type?->name ?: Str::headline(str_replace('_', ' ', $groupKey));
                    $productAttributes[$groupKey]['label'] = $groupLabel;
                    $productAttributes[$groupKey]['values'][$value->value] = true;
                }
            }

            foreach ($productAttributes as $groupKey => $payload) {
                foreach (array_keys($payload['values']) as $valueLabel) {
                    $attributeBuckets[$groupKey]['label'] = $payload['label'];
                    $attributeBuckets[$groupKey]['options'][$valueLabel] = [
                        'value' => $valueLabel,
                        'label' => $valueLabel,
                        'count' => ($attributeBuckets[$groupKey]['options'][$valueLabel]['count'] ?? 0) + 1,
                        'selected' => in_array($valueLabel, $filters['attributes'][$groupKey] ?? [], true),
                        'type' => 'attribute',
                        'key' => $groupKey,
                    ];
                }
            }
        }

        $groups = [];

        if (!empty($categoryBuckets)) {
            $groups[] = [
                'key' => 'category',
                'label' => 'Category',
                'type' => 'category',
                'options' => collect($categoryBuckets)
                    ->sortBy([
                        ['count', 'desc'],
                        ['label', 'asc'],
                    ])
                    ->values()
                    ->all(),
            ];
        }

        if (!empty($brandBuckets)) {
            $groups[] = [
                'key' => 'brand',
                'label' => 'Brand',
                'type' => 'brand',
                'options' => collect($brandBuckets)
                    ->sortBy([
                        ['count', 'desc'],
                        ['label', 'asc'],
                    ])
                    ->values()
                    ->all(),
            ];
        }

        collect($attributeBuckets)
            ->sortBy(fn (array $group, string $key) => [$group['label'] ?? Str::headline($key), $key])
            ->each(function (array $group, string $key) use (&$groups) {
                $options = collect($group['options'] ?? [])
                    ->sortBy([
                        ['count', 'desc'],
                        ['label', 'asc'],
                    ])
                    ->values()
                    ->all();

                if (empty($options)) {
                    return;
                }

                $groups[] = [
                    'key' => $key,
                    'label' => $group['label'] ?? Str::headline(str_replace('_', ' ', $key)),
                    'type' => 'attribute',
                    'options' => $options,
                ];
            });

        return [
            'price' => [
                'min' => $minimumPrice !== null ? (float) round($minimumPrice, 2) : 0.0,
                'max' => $maximumPrice !== null ? (float) round($maximumPrice, 2) : 0.0,
                'selected_min' => $filters['min_price'],
                'selected_max' => $filters['max_price'],
            ],
            'toggles' => [
                [
                    'key' => 'in_stock',
                    'label' => 'In stock',
                    'count' => $inStockCount,
                    'selected' => $filters['in_stock'],
                ],
                [
                    'key' => 'on_sale',
                    'label' => 'On sale',
                    'count' => $onSaleCount,
                    'selected' => $filters['on_sale'],
                ],
            ],
            'groups' => $groups,
        ];
    }

    protected function buildActiveFilters(array $filters, array $filterData): array
    {
        $active = [];
        $groupLookup = collect($filterData['groups'])->keyBy('key');

        foreach ($filters['category'] as $value) {
            $active[] = [
                'type' => 'category',
                'key' => 'category',
                'value' => $value,
                'label' => 'Category: ' . $this->resolveOptionLabel($groupLookup->get('category'), $value),
            ];
        }

        foreach ($filters['brand'] as $value) {
            $active[] = [
                'type' => 'brand',
                'key' => 'brand',
                'value' => $value,
                'label' => 'Brand: ' . $this->resolveOptionLabel($groupLookup->get('brand'), $value),
            ];
        }

        foreach ($filters['attributes'] as $key => $values) {
            $group = $groupLookup->get($key);
            $groupLabel = $group['label'] ?? Str::headline(str_replace('_', ' ', $key));

            foreach ($values as $value) {
                $active[] = [
                    'type' => 'attribute',
                    'key' => $key,
                    'value' => $value,
                    'label' => $groupLabel . ': ' . $value,
                ];
            }
        }

        if ($filters['min_price'] !== null) {
            $active[] = [
                'type' => 'min_price',
                'key' => 'min_price',
                'value' => (string) $filters['min_price'],
                'label' => 'Min: ' . $this->formatCurrency($filters['min_price']),
            ];
        }

        if ($filters['max_price'] !== null) {
            $active[] = [
                'type' => 'max_price',
                'key' => 'max_price',
                'value' => (string) $filters['max_price'],
                'label' => 'Max: ' . $this->formatCurrency($filters['max_price']),
            ];
        }

        if ($filters['in_stock']) {
            $active[] = [
                'type' => 'toggle',
                'key' => 'in_stock',
                'value' => '1',
                'label' => 'In stock',
            ];
        }

        if ($filters['on_sale']) {
            $active[] = [
                'type' => 'toggle',
                'key' => 'on_sale',
                'value' => '1',
                'label' => 'On sale',
            ];
        }

        return $active;
    }

    protected function sortItems(Collection $items, string $sort): Collection
    {
        return match ($sort) {
            'price_asc' => $items->sortBy(fn (array $item) => [
                (float) ($item['card']['price']['current'] ?? 0),
                Str::lower($item['card']['name'] ?? ''),
            ])->values(),
            'price_desc' => $items->sortByDesc(fn (array $item) => [
                (float) ($item['card']['price']['current'] ?? 0),
                (float) ($item['card']['price']['discount_percentage'] ?? 0),
            ])->values(),
            'newest' => $items->sortByDesc(fn (array $item) => $item['created_at'])->values(),
            'discount_desc' => $items->sortByDesc(fn (array $item) => [
                (float) ($item['card']['price']['discount_percentage'] ?? 0),
                (float) ($item['card']['price']['discount_amount'] ?? 0),
            ])->values(),
            'featured' => $items->sortByDesc(fn (array $item) => [
                (bool) ($item['card']['featured'] ?? false),
                $item['created_at'],
            ])->values(),
            default => $items->sortByDesc(fn (array $item) => [
                $item['relevance'],
                (bool) ($item['card']['featured'] ?? false),
                $item['created_at'],
            ])->values(),
        };
    }

    protected function paginateItems(Collection $items, array $filters): LengthAwarePaginator
    {
        $page = $filters['page'];
        $perPage = $filters['per_page'];
        $offset = ($page - 1) * $perPage;
        $pageItems = $items
            ->slice($offset, $perPage)
            ->map(fn (array $item) => $item['card'])
            ->values();

        return new LengthAwarePaginator(
            items: $pageItems,
            total: $items->count(),
            perPage: $perPage,
            currentPage: $page,
            options: [
                'path' => route('store.search'),
                'query' => $this->toQueryArray($filters),
            ],
        );
    }

    protected function serializeFilters(array $filters): array
    {
        return [
            'q' => $filters['q'],
            'sort' => $filters['sort'],
            'page' => $filters['page'],
            'per_page' => $filters['per_page'],
            'category' => array_values($filters['category']),
            'brand' => array_values($filters['brand']),
            'min_price' => $filters['min_price'],
            'max_price' => $filters['max_price'],
            'in_stock' => $filters['in_stock'],
            'on_sale' => $filters['on_sale'],
            'attributes' => $filters['attributes'],
        ];
    }

    protected function toQueryArray(array $filters): array
    {
        $query = [
            'q' => $filters['q'] !== '' ? $filters['q'] : null,
            'sort' => $filters['sort'] !== $this->defaultSort($filters['q'] !== '') ? $filters['sort'] : null,
            'category' => !empty($filters['category']) ? implode(',', $filters['category']) : null,
            'brand' => !empty($filters['brand']) ? implode(',', $filters['brand']) : null,
            'min_price' => $filters['min_price'],
            'max_price' => $filters['max_price'],
            'in_stock' => $filters['in_stock'] ? 1 : null,
            'on_sale' => $filters['on_sale'] ? 1 : null,
            'page' => $filters['page'] > 1 ? $filters['page'] : null,
            'per_page' => $filters['per_page'] !== self::DEFAULT_PER_PAGE ? $filters['per_page'] : null,
        ];

        foreach ($filters['attributes'] as $key => $values) {
            $query[$key] = !empty($values) ? implode(',', $values) : null;
        }

        return array_filter($query, fn ($value) => $value !== null && $value !== '');
    }

    protected function availableSorts(bool $hasQuery): array
    {
        return collect([
            [
                'value' => $hasQuery ? 'relevance' : 'featured',
                'label' => $hasQuery ? 'Relevance' : 'Featured',
            ],
            ['value' => 'price_asc', 'label' => 'Price: Low to High'],
            ['value' => 'price_desc', 'label' => 'Price: High to Low'],
            ['value' => 'newest', 'label' => 'Newest Arrivals'],
            ['value' => 'discount_desc', 'label' => 'Biggest Discount'],
            ['value' => 'featured', 'label' => 'Featured'],
        ])->unique('value')->values()->all();
    }

    protected function normalizeSort(mixed $value, bool $hasQuery): string
    {
        $allowed = collect($this->availableSorts($hasQuery))
            ->pluck('value')
            ->all();
        $sort = trim((string) $value);

        return in_array($sort, $allowed, true)
            ? $sort
            : $this->defaultSort($hasQuery);
    }

    protected function defaultSort(bool $hasQuery): string
    {
        return $hasQuery ? 'relevance' : 'featured';
    }

    protected function normalizeCsvFilter(mixed $value): array
    {
        if (is_array($value)) {
            return collect($value)
                ->flatMap(fn ($item) => explode(',', (string) $item))
                ->map(fn ($item) => trim((string) $item))
                ->filter()
                ->unique()
                ->values()
                ->all();
        }

        return collect(explode(',', (string) $value))
            ->map(fn ($item) => trim((string) $item))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function normalizeNullableFloat(mixed $value): ?float
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        return round(max((float) $value, 0), 2);
    }

    protected function normalizeBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(Str::lower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
    }

    protected function resolvePageTitle(array $filters): string
    {
        if ($filters['q'] !== '') {
            return "Search results for '{$filters['q']}'";
        }

        return 'Search Results';
    }

    protected function resolveOptionLabel(?array $group, string $value): string
    {
        if (!$group) {
            return $value;
        }

        $option = collect($group['options'] ?? [])
            ->firstWhere('value', $value);

        return $option['label'] ?? $value;
    }

    protected function categoryToken(Category $category): string
    {
        return $category->slug ?: (string) $category->id;
    }

    protected function brandToken(Brand $brand): string
    {
        return $brand->slug ?: (string) $brand->id;
    }

    protected function attributeKey(?string $slug, ?string $fallbackName): string
    {
        if (filled($slug)) {
            return (string) $slug;
        }

        return Str::slug((string) $fallbackName, '_');
    }

    protected function normalizeLabel(mixed $value): string
    {
        return Str::lower(trim((string) $value));
    }

    protected function formatCurrency(float $amount): string
    {
        return 'NGN ' . number_format(round($amount, 2), 2);
    }
}
