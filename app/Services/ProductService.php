<?php

namespace App\Services;

use App\Models\{Admin\ProductImage,
    Admin\VariantImage,
    OpeningBalance,
    OpeningBalanceItem,
    Product,
    ProductFaq,
    ProductVariant,
    User};
use App\Models\Category;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use App\Services\SkuGenerator;
use Illuminate\Http\UploadedFile;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class ProductService
{
    public function __construct(
        private SkuGenerator $skuGen,
        private InventoryService $inventoryService,
        private DiscountService $discountService,
        private ImageManager $imageManager = new ImageManager(new Driver())
    ) {}

    public function create(array $data): Product
    {
        return DB::transaction(function () use ($data) {
            $product = Product::create(Arr::except($data, ['quantity','images','faqs','variants','category_ids']));

            $this->syncCats($product, $data['category_ids']);
            $this->syncFaqs($product, $data['faqs'] ?? []);
            $this->syncVariants($product, $data['variants'] ?? []);

            return $product->fresh(['images','faqs','variants.values','variants.images']);
        });
    }

    public function update(Product $product, array $data): Product
    {
        return DB::transaction(function () use ($product, $data) {
            $product->update(Arr::except($data, ['images','faqs','variants','slug','quantity']));
            $this->syncFaqs($product, $data['faqs'] ?? []);
            $this->syncVariants($product, $data['variants'] ?? []);

            return $product->fresh(['images','faqs','variants.values','variants.images']);
        });
    }

    public function syncImages(Product $product, array $images): void
    {
        DB::transaction(function () use ($product, $images) {
            $disk = 'public';
            $dir  = "products/{$product->id}";
            $seen = [];
            $primaryRequested = false;

            foreach ($images as $payload) {
                $id   = data_get($payload, 'id');
                $file = data_get($payload, 'file');

                // Only create a new row when an actual file is present
                if (!$id && !$file) {
                    continue;
                }

                /** @var ProductImage|null $imgModel */
                $imgModel = $id
                    ? $product->images()->find($id)
                    : new ProductImage(['product_id' => $product->id]);

                if (!$imgModel) {
                    continue;
                }

                // If a new file is provided, store it and replace any previous file
                if ($file instanceof \Illuminate\Http\UploadedFile) {
                    // --- START MODIFICATION ---

                    // 1. Delete the OLD file if it exists
                    if ($imgModel->path && Storage::disk($disk)->exists($imgModel->path)) {
                        Storage::disk($disk)->delete($imgModel->path);
                    }

                    // 2. Convert and store the NEW file as WebP
                    // If the conversion fails, it will throw an exception and rollback the transaction.
                    $imgModel->path = $this->convertToWebpAndStore($file, $dir, $disk);

                    // --- END MODIFICATION ---

                    // IMPORTANT: You might want to strip the file key from the input array
                    // if you are passing the entire payload back on subsequent saves.
                }

                // Fill simple attributes
                $imgModel->alt        = data_get($payload, 'alt', $imgModel->alt);
                $imgModel->sort_order = (int) data_get($payload, 'sort_order', $imgModel->sort_order ?? 0);

                $isPrimary = (bool) data_get($payload, 'is_primary', false);
                if ($isPrimary) $primaryRequested = true;
                $imgModel->is_primary = $isPrimary;

                $imgModel->save();
                $seen[] = $imgModel->id;
            }

            // Remove images not present in the payload and delete their files
            $toDelete = $product->images()->when($seen, fn($q) => $q->whereNotIn('id', $seen))->get();
            foreach ($toDelete as $del) {
                // The file stored on disk is now guaranteed to be a WebP file.
                if ($del->path && Storage::disk($disk)->exists($del->path)) {
                    Storage::disk($disk)->delete($del->path);
                }
                $del->delete();
            }

            // ... (rest of the logic for ensuring one primary and normalizing sort_order remains the same)
            // Ensure exactly one primary
            if ($primaryRequested) {
                $firstPrimary = $product->images()->where('is_primary', true)->orderBy('sort_order')->first();
                if ($firstPrimary) {
                    $product->images()->where('id', '<>', $firstPrimary->id)->update(['is_primary' => false]);
                }
            } else {
                $first = $product->images()->orderBy('sort_order')->orderBy('id')->first();
                if ($first) {
                    $product->images()->update(['is_primary' => false]);
                    $first->is_primary = true;
                    $first->save();
                }
            }

            // Normalize sort_order to 0..n in current order
            $ordered = $product->images()->orderBy('sort_order')->orderBy('id')->get();
            foreach ($ordered as $idx => $img) {
                if ((int) $img->sort_order !== $idx) {
                    $img->sort_order = $idx;
                    $img->save();
                }
            }
        });
    }

    protected function syncFaqs(Product $product, array $faqs): void
    {
        $seen = [];
        foreach ($faqs as $faq) {
            $model = isset($faq['id'])
                ? $product->faqs()->find($faq['id'])
                : new ProductFaq(['product_id' => $product->id]);

            if (!$model) continue;

            $model->fill(Arr::only($faq, [
                'product_variant_id','question','answer','is_active','position','slug','locale'
            ]))->save();

            $seen[] = $model->id;
        }
        $product->faqs()->whereNotIn('id', $seen)->delete();
    }

    protected function syncVariants(Product $product, array $variants): void
    {
        $seen    = [];
        $storeId = $product->store_id ?? null;

        foreach ($variants as $v) {
            $variant = isset($v['id'])
                ? $product->variants()->find($v['id'])
                : new ProductVariant(['product_id' => $product->id]);

            if (!$variant) continue;


            // Build a readable stem from product + selected attributes
            $valueIds = $v['value_ids'] ?? [];
            $attrLabels = [];
            try {
                // Use the relation's related model to fetch labels without saving the variant yet
                $related = $variant->values()->getRelated(); // BelongsToMany related model
                if (!empty($valueIds)) {
                    $attrLabels = $related->newQuery()
                        ->whereIn('id', $valueIds)
                        ->pluck('value')      // assumes column name 'value'
                        ->all();
                }
            } catch (\Throwable $e) {
                // If relation or column differs, fall back silently
                $attrLabels = [];
            }

            $brandCode = data_get($product, 'brand.code', data_get($product, 'brand.name', ''));
            $stem = $this->skuGen->makeStem((string)$brandCode, (string)$product->name, $attrLabels);

            // Decide final SKU
            $incoming = $v['sku'] ?? '';
            if ($incoming) {
                $result = $this->skuGen->acceptOrSuggest($storeId, $incoming, $variant->id ?? null);
                $finalSku = $result['sku']; // accepted or suggested unique
            } else {
                $finalSku = $this->skuGen->uniqueFromStem($storeId, $stem);
            }

            // Fill and save (keep sku authoritative from generator)
            $variant->fill(Arr::only($v, [
                'barcode', 'sale_starts_at','regular_price', 'sale_ends_at', 'weight', 'length', 'width', 'height'
            ]));
            $variant->sku = $finalSku;
            $variant->save();

            $seen[] = $variant->id;

            // Attach variant values & Images
            $variant->values()->sync($valueIds);
            $this->syncVariantImages($variant, $v['images'] ?? []);

            if(isset($v['id']) && OpeningBalanceItem::where('variant_id', $v['id'])->exists()) {
                continue;
            }
            // --- NEW: Handle Opening Balance if quantity > 0 ---
            $quantity = (int)($v['quantity'] ?? 0);
            $unitCost = (float)($v['last_purchase_price'] ?? 0);
            if ($quantity > 0) {
                // Create or find existing Opening Balance for this session
                $openingBalance = OpeningBalance::firstOrCreate(
                    ['reference' => 'AUTO-' . now()->format('Ymd-His')],
                    [
                        'warehouse_id' => $v['warehouse_id'] ?? null,
                        'vendor_id' => null,
                        'employee_id' => null,
                        'effective_at' => now(),
                        'note' => 'Auto created from product creation',
                    ]
                );

                // Create Opening Balance Item
                $item = OpeningBalanceItem::create([
                    'opening_balance_id' => $openingBalance->id,
                    'variant_id' => $variant->id,
                    'quantity' => $quantity,
                    'unit_cost' => $unitCost,
                ]);


                // Record in Inventory
                $this->inventoryService->stockIn([
                    'variant_id' => $variant->id,
                    'quantity' => $quantity,
                    'unit_cost' => $unitCost,
                    'warehouse_id' => $v['warehouse_id'] ?? null,
                    'reason' => 'Opening Balance',
                    'employee_id' => null,
                    'source_type' => OpeningBalanceItem::class,
                    'source_id' => $item->id,
                    'effective_at' => now(),
                    'note' => 'Auto stock-in from opening balance',
                ]);
            }
        }


        // Delete removed variants (cascade their images and pivot)
        $product->variants()->whereNotIn('id', $seen)->get()->each(function ($var) {
            $var->images()->delete();
            $var->values()->detach();
            $var->delete();
        });
    }

    protected function syncVariantImages(ProductVariant $variant, array $images): void
    {
        $seen = [];
        $disk = 'public';
        // Use a variant-specific directory for better organization
        $dir  = "variants/{$variant->id}";

        foreach ($images as $img) {
            if ($img instanceof \Illuminate\Http\UploadedFile) {
                // Handle new uploaded file: Convert to WebP using the new method
                // and store it in a variant-specific folder.
                $path = $this->convertToWebpAndStore($img, $dir, $disk);

                $imgModel = new VariantImage([
                    'product_variant_id' => $variant->id,
                    'path' => $path, // path is now guaranteed to be webp
                    'is_primary' => false,
                    'sort_order' => 0,
                ]);
                $imgModel->save();
                $seen[] = $imgModel->id;
            } elseif (is_array($img)) {
                // Handle existing image array
                $imgModel = isset($img['id'])
                    ? $variant->images()->find($img['id'])
                    : new VariantImage(['product_variant_id' => $variant->id]);

                if (!$imgModel) continue;

                $imgModel->fill(Arr::only($img, ['path','alt','is_primary','sort_order']))->save();
                $seen[] = $imgModel->id;
            }
        }

        // Delete images not in seen and their corresponding files (corrected logic to include file deletion)
        $toDelete = $variant->images()->whereNotIn('id', $seen)->get();
        foreach ($toDelete as $del) {
            if ($del->path && Storage::disk($disk)->exists($del->path)) {
                Storage::disk($disk)->delete($del->path);
            }
            $del->delete();
        }
    }

    public function duplicate(Product $source): Product
    {
        return DB::transaction(function () use ($source) {
            // product copy
            $new = $source->replicate([
                'slug', 'is_active', 'featured', 'created_at', 'updated_at'
            ]);
            $new->name = $source->name.' (Copy)';
            $new->is_active = false;
            $new->featured = false;

            // unique slug
            $base = Str::slug($new->name);
            $slug = $base;
            $i = 1;
            while (Product::where('slug', $slug)->exists()) {
                $slug = $base.'-'.$i++;
            }
            $new->slug = $slug;
            $new->save();

            // images
            $source->images()->get()->each(function ($img) use ($new) {
                $new->images()->create($img->replicate(['product_id','created_at','updated_at'])->toArray());
            });

            // variants and mapping
            $valuePivot = [];
            $oldToNewVariant = [];

            $source->variants()->with(['values', 'images'])->get()->each(function ($variant) use ($new, &$oldToNewVariant, &$valuePivot, $source) {
                $v = $variant->replicate(['product_id','sku','created_at','updated_at']);

                // Build a readable stem and generate a unique SKU for the duplicated variant
                $attrLabels = $variant->values->pluck('value')->all(); // assumes column name 'value'
                $brandCode  = data_get($source, 'brand.code', data_get($source, 'brand.name', ''));
                $stem       = $this->skuGen->makeStem((string) $brandCode, (string) $new->name, $attrLabels);
                $v->sku     = $this->skuGen->uniqueFromStem($new->store_id ?? null, $stem);

                $v->product_id = $new->id;
                $v->save();

                $oldToNewVariant[$variant->id] = $v->id;
                $valuePivot[$v->id] = $variant->values->pluck('id')->all();

                // variant images
                $variant->images->each(function ($img) use ($v) {
                    $v->images()->create($img->replicate(['product_variant_id','created_at','updated_at'])->toArray());
                });
            });

            // attach variant values
            foreach ($valuePivot as $newVariantId => $ids) {
                ProductVariant::find($newVariantId)?->values()->sync($ids);
            }

            // FAQs
            $source->faqs()->get()->each(function ($faq) use ($new, $oldToNewVariant) {
                $new->faqs()->create([
                    'product_variant_id' => $faq->product_variant_id
                        ? ($oldToNewVariant[$faq->product_variant_id] ?? null)
                        : null,
                    'question'    => $faq->question,
                    'answer'      => $faq->answer,
                    'is_active'   => false,
                    'position'    => $faq->position,
                    'helpful_yes' => 0,
                    'helpful_no'  => 0,
                    'slug'        => null,
                    'locale'      => $faq->locale,
                ]);
            });

            return $new->fresh();
        });
    }

    public function paginateStorefrontProducts(int $perPage = 12, ?string $search = null, ?int $categoryId = null, ?User $user = null): LengthAwarePaginator
    {
        $paginator = $this->storeListQuery($search, $categoryId)
            ->paginate(max(1, min($perPage, 48)))
            ->withQueryString();

        $paginator->setCollection(
            $paginator->getCollection()->map(fn (Product $product) => $this->toStorefrontCard($product, $user))
        );

        return $paginator;
    }

    public function getFeaturedProducts(int $limit = 8, ?User $user = null): array
    {
        return $this->storeListQuery()
            ->where('featured', true)
            ->limit(max(1, $limit))
            ->get()
            ->map(fn (Product $product) => $this->toStorefrontCard($product, $user))
            ->values()
            ->all();
    }

    public function getLatestProducts(int $limit = 8, ?User $user = null): array
    {
        return Product::query()
            ->active()
            ->with($this->storeCardRelations())
            ->latest('created_at')
            ->limit(max(1, $limit))
            ->get()
            ->map(fn (Product $product) => $this->toStorefrontCard($product, $user))
            ->values()
            ->all();
    }

    public function getProductsByCategory(Category|int $category, int $perPage = 12, ?User $user = null): LengthAwarePaginator
    {
        $categoryId = $category instanceof Category ? (int) $category->id : (int) $category;

        return $this->paginateStorefrontProducts($perPage, null, $categoryId, $user);
    }

    public function searchProducts(string $term, int $perPage = 12, ?User $user = null): LengthAwarePaginator
    {
        return $this->paginateStorefrontProducts($perPage, $term, null, $user);
    }

    public function getProductDetails(Product $product, ?User $user = null): array
    {
        $product->loadMissing([
            'brand:id,name',
            'categories:id,name,slug',
            'images:id,product_id,path,alt,is_primary,sort_order',
            'faqs:id,product_id,product_variant_id,question,answer,is_active,position',
            'variants' => fn ($query) => $query
                ->select([
                    'id',
                    'product_id',
                    'sku',
                    'quantity',
                    'reserved',
                    'regular_price',
                    'sale_price',
                    'sale_starts_at',
                    'sale_ends_at',
                ])
                ->with([
                    'values:id,variant_type_id,value',
                    'values.type:id,name',
                    'images:id,product_variant_id,path,alt,is_primary,sort_order',
                ])
                ->orderBy('regular_price')
                ->orderBy('id'),
        ]);

        $card = $this->toStorefrontCard($product, $user);
        $variants = $product->variants
            ->map(fn (ProductVariant $variant) => $this->toVariantPayload($variant, $user, $product))
            ->values();

        $selectedVariant = $product->variants
            ->first(fn (ProductVariant $variant) => ($variant->quantity - ($variant->reserved ?? 0)) > 0)
            ?? $product->variants->first();

        $gallerySource = $product->images;
        if ($gallerySource->isEmpty() && $selectedVariant) {
            $gallerySource = $selectedVariant->images;
        }

        return [
            ...$card,
            'description' => $product->description,
            'brand' => [
                'name' => $product->brand?->name,
            ],
            'categories' => $product->categories->map(fn (Category $category) => [
                'id' => (int) $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
            ])->values()->all(),
            'images' => $gallerySource
                ->sortBy('sort_order')
                ->map(fn ($image) => $this->toStorefrontImagePayload($image))
                ->values()
                ->all(),
            'variants' => $variants->all(),
            'default_variant_id' => $selectedVariant?->id,
            'faqs' => $product->faqs
                ->where('is_active', true)
                ->sortBy('position')
                ->map(fn (ProductFaq $faq) => [
                    'id' => (int) $faq->id,
                    'question' => $faq->question,
                    'answer' => $faq->answer,
                ])
                ->values()
                ->all(),
        ];
    }

    public function getRelatedProducts(Product $product, int $limit = 8, ?User $user = null): array
    {
        $product->loadMissing('categories:id');

        $categoryIds = $product->categories->pluck('id')->all();

        $query = Product::query()
            ->active()
            ->with($this->storeCardRelations())
            ->where('id', '!=', $product->id)
            ->when(!empty($categoryIds), function (Builder $builder) use ($categoryIds) {
                $builder->whereHas('categories', fn (Builder $categoryQuery) => $categoryQuery->whereIn('categories.id', $categoryIds));
            }, function (Builder $builder) use ($product) {
                $builder->where('brand_id', $product->brand_id);
            })
            ->orderByDesc('featured')
            ->latest('id')
            ->limit(max(1, $limit));

        return $query
            ->get()
            ->map(fn (Product $related) => $this->toStorefrontCard($related, $user))
            ->values()
            ->all();
    }

    public function toStorefrontCard(Product $product, ?User $user = null): array
    {
        $product->loadMissing($this->storeCardRelations());

        $primaryVariant = $this->pickPrimaryVariant($product->variants);
        $stock = $this->resolveProductStock($product);
        $pricing = $this->toStorefrontPricingFromVariants($product->variants, $primaryVariant, $user, $product);
        $badges = $this->cardBadges($product, $pricing);

        return [
            'id' => (int) $product->id,
            'slug' => $product->slug,
            'name' => $product->name,
            'description' => $product->description,
            'image' => $this->resolveProductImage($product, $primaryVariant),
            'price' => $pricing,
            'stock' => $stock,
            'categories' => $product->categories->map(fn (Category $category) => [
                'id' => (int) $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
            ])->values()->all(),
            'featured' => (bool) $product->featured,
            'is_new' => $product->created_at?->gt(now()->subDays(14)) ?? false,
            'default_variant_id' => $primaryVariant?->id,
            'badges' => $badges,
        ];
    }

    public function makeImageUrl(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://', '/'])) {
            return $path;
        }

        return Storage::url($path);
    }

    public function resolveProductImage(Product $product, ?ProductVariant $variant = null): ?string
    {
        $product->loadMissing('images');

        $primaryProductImage = $product->images->firstWhere('is_primary', true) ?? $product->images->first();

        if ($primaryProductImage) {
            return $this->makeImageUrl($primaryProductImage->path);
        }

        if ($variant) {
            $variant->loadMissing('images');
            $primaryVariantImage = $variant->images->firstWhere('is_primary', true) ?? $variant->images->first();

            return $this->makeImageUrl($primaryVariantImage?->path);
        }

        return null;
    }

    public function resolveVariantPricing(ProductVariant $variant, ?User $user = null, ?Product $product = null, bool $allowAuthenticatedFallback = true): array
    {
        if ($allowAuthenticatedFallback && $user === null) {
            $user = auth()->user();
        }

        $product ??= $variant->product;

        if (!$product) {
            $variant->loadMissing('product.categories:id');
            $product = $variant->product;
        }

        $legacyPricing = $this->resolveLegacyVariantPricing($variant);
        $regular = (float) $legacyPricing['regular'];
        $categoryIds = $product?->categories?->pluck('id')->map(fn ($id) => (int) $id)->all() ?? [];

        $lineItemDiscount = $this->discountService->resolveLineItemDiscount(
            variant: $variant,
            regularPrice: $regular,
            user: $user,
            categoryIds: $categoryIds,
        );

        $current = (float) $legacyPricing['current'];
        $discountSource = $legacyPricing['has_discount'] ? 'sale' : null;
        $discountLabel = $legacyPricing['has_discount'] ? 'Sale' : null;
        $discountId = null;

        if (($lineItemDiscount['discount_id'] ?? null) && (float) $lineItemDiscount['current'] < $current) {
            $current = (float) $lineItemDiscount['current'];
            $discountSource = 'automatic';
            $discountLabel = $lineItemDiscount['label'];
            $discountId = $lineItemDiscount['discount_id'];
        }

        $hasDiscount = round($current, 2) < round($regular, 2);
        $percentage = $hasDiscount && $regular > 0
            ? (int) round((($regular - $current) / $regular) * 100)
            : 0;

        return [
            'regular' => round($regular, 2),
            'sale' => $legacyPricing['sale'] !== null ? round((float) $legacyPricing['sale'], 2) : null,
            'current' => round($current, 2),
            'has_discount' => $hasDiscount,
            'discount_percentage' => $percentage,
            'discount_label' => $discountLabel,
            'discount_source' => $discountSource,
            'discount_id' => $discountId,
        ];
    }

    public function resolveProductStock(Product $product): array
    {
        $product->loadMissing('variants:id,product_id,quantity,reserved');

        $onHand = (int) $product->variants->sum('quantity');
        $reserved = (int) $product->variants->sum('reserved');
        $available = max($onHand - $reserved, 0);

        return [
            'on_hand' => $onHand,
            'reserved' => $reserved,
            'available' => $available,
            'is_in_stock' => $available > 0,
        ];
    }

    public function resolveVariantStock(ProductVariant $variant): array
    {
        $onHand = (int) $variant->quantity;
        $reserved = (int) ($variant->reserved ?? 0);
        $available = max($onHand - $reserved, 0);

        return [
            'on_hand' => $onHand,
            'reserved' => $reserved,
            'available' => $available,
            'is_in_stock' => $available > 0,
        ];
    }

    public function describeVariant(ProductVariant $variant): string
    {
        $variant->loadMissing(['values.type']);

        $label = $variant->values
            ->map(fn ($value) => trim(($value->type?->name ? $value->type->name . ': ' : '') . $value->value))
            ->implode(' / ');

        return $label ?: $variant->sku;
    }

    public function listStoreCategories(): array
    {
        return Category::query()
            ->select('id', 'name', 'slug', 'featured', 'order')
            ->whereHas('products', fn (Builder $query) => $query->where('is_active', true))
            ->orderByDesc('featured')
            ->orderBy('order')
            ->orderBy('name')
            ->get()
            ->map(fn (Category $category) => [
                'id' => (int) $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
            ])
            ->values()
            ->all();
    }

    protected function storeListQuery(?string $search = null, ?int $categoryId = null): Builder
    {
        return Product::query()
            ->active()
            ->with($this->storeCardRelations())
            ->when($search, function (Builder $query, string $term) {
                $query->where(function (Builder $searchQuery) use ($term) {
                    $searchQuery
                        ->where('name', 'like', "%{$term}%")
                        ->orWhere('slug', 'like', "%{$term}%")
                        ->orWhere('description', 'like', "%{$term}%");
                });
            })
            ->when($categoryId, fn (Builder $query, int $id) => $query
                ->whereHas('categories', fn (Builder $categoryQuery) => $categoryQuery->where('categories.id', $id)))
            ->orderByDesc('featured')
            ->latest('id');
    }

    protected function storeCardRelations(): array
    {
        return [
            'categories:id,name,slug',
            'images:id,product_id,path,alt,is_primary,sort_order',
            'variants' => fn ($query) => $query
                ->select([
                    'id',
                    'product_id',
                    'sku',
                    'quantity',
                    'reserved',
                    'regular_price',
                    'sale_price',
                    'sale_starts_at',
                    'sale_ends_at',
                ])
                ->orderBy('regular_price')
                ->orderBy('id'),
        ];
    }

    protected function cardBadges(Product $product, array $pricing): array
    {
        $badges = [];

        if (($pricing['has_discount'] ?? false) === true) {
            $badges[] = 'On Sale';
        }

        if ($product->created_at?->gt(now()->subDays(14))) {
            $badges[] = 'New';
        }

        if ((bool) $product->featured) {
            $badges[] = 'Featured';
        }

        return $badges;
    }

    protected function toStorefrontImagePayload($image): array
    {
        return [
            'id' => (int) $image->id,
            'url' => $this->makeImageUrl($image->path),
            'alt' => $image->alt,
            'is_primary' => (bool) $image->is_primary,
            'sort_order' => (int) $image->sort_order,
        ];
    }

    protected function toVariantPayload(ProductVariant $variant, ?User $user = null, ?Product $product = null): array
    {
        $variant->loadMissing(['values.type', 'images']);

        $stock = $this->resolveVariantStock($variant);
        $price = $this->resolveVariantPricing($variant, $user, $product);

        return [
            'id' => (int) $variant->id,
            'sku' => $variant->sku,
            'label' => $this->describeVariant($variant),
            'stock' => $stock,
            'price' => $price,
            'images' => $variant->images
                ->sortBy('sort_order')
                ->map(fn ($image) => $this->toStorefrontImagePayload($image))
                ->values()
                ->all(),
            'values' => $variant->values
                ->map(fn ($value) => [
                    'id' => (int) $value->id,
                    'type' => $value->type?->name,
                    'value' => $value->value,
                ])
                ->values()
                ->all(),
        ];
    }

    protected function pickPrimaryVariant(Collection $variants): ?ProductVariant
    {
        if ($variants->isEmpty()) {
            return null;
        }

        $inStockVariant = $variants->first(fn (ProductVariant $variant) => ($variant->quantity - ($variant->reserved ?? 0)) > 0);

        return $inStockVariant ?? $variants->first();
    }

    protected function toStorefrontPricingFromVariants(Collection $variants, ?ProductVariant $primaryVariant, ?User $user = null, ?Product $product = null): array
    {
        if ($variants->isEmpty()) {
            return [
                'regular' => 0.0,
                'sale' => null,
                'current' => 0.0,
                'has_discount' => false,
                'discount_percentage' => 0,
                'discount_label' => null,
                'discount_source' => null,
                'from' => false,
            ];
        }

        $resolvedVariants = $variants->map(fn (ProductVariant $variant) => [
            'variant' => $variant,
            'pricing' => $this->resolveVariantPricing($variant, $user, $product),
        ]);

        $displayVariant = $resolvedVariants
            ->sortBy(fn (array $resolved) => [(float) $resolved['pricing']['current'], (int) $resolved['variant']->id])
            ->first();

        $minimumCurrent = (float) $resolvedVariants->min(fn (array $resolved) => $resolved['pricing']['current']);
        $maximumCurrent = (float) $resolvedVariants->max(fn (array $resolved) => $resolved['pricing']['current']);
        $displayPricing = $displayVariant['pricing'] ?? ($primaryVariant
            ? $this->resolveVariantPricing($primaryVariant, $user, $product)
            : $resolvedVariants->first()['pricing']);

        return [
            'regular' => (float) $displayPricing['regular'],
            'sale' => $displayPricing['sale'] !== null ? (float) $displayPricing['sale'] : null,
            'current' => round($minimumCurrent, 2),
            'has_discount' => (bool) ($displayPricing['has_discount'] ?? false),
            'discount_percentage' => (int) ($displayPricing['discount_percentage'] ?? 0),
            'discount_label' => $displayPricing['discount_label'] ?? null,
            'discount_source' => $displayPricing['discount_source'] ?? null,
            'from' => round($minimumCurrent, 2) !== round($maximumCurrent, 2),
        ];
    }

    protected function resolveLegacyVariantPricing(ProductVariant $variant): array
    {
        $now = now();

        $hasSaleWindow = (!$variant->sale_starts_at || $variant->sale_starts_at->lte($now))
            && (!$variant->sale_ends_at || $variant->sale_ends_at->gte($now));

        $hasDiscount = $variant->sale_price !== null
            && (float) $variant->sale_price < (float) $variant->regular_price
            && $hasSaleWindow;

        $regular = (float) $variant->regular_price;
        $sale = $variant->sale_price !== null ? (float) $variant->sale_price : null;
        $current = $hasDiscount ? (float) $variant->sale_price : $regular;

        return [
            'regular' => round($regular, 2),
            'sale' => $sale !== null ? round($sale, 2) : null,
            'current' => round($current, 2),
            'has_discount' => $hasDiscount,
        ];
    }
    private function syncCats($product, mixed $category_ids)
    {
        $product->categories()->sync($category_ids);
    }

    protected function convertToWebpAndStore(UploadedFile $file, string $dir, string $disk): string
    {
        // Use the injected ImageManager to read the image
        $image = $this->imageManager->read($file);

        // Generate a unique filename and path (using .webp extension)
        $filename = Str::random(40) . '.webp';
        $fullPath = $dir . '/' . $filename;

        // Get the full system path for saving
        $diskPath = Storage::disk($disk)->path($fullPath);

        Storage::disk($disk)->makeDirectory($dir);

        $image->toWebp(70)->save($diskPath);

        // Return the path *relative* to the disk root
        return $fullPath; // e.g., products/{id}/abc.webp
    }
}

