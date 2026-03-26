<?php

namespace App\Services;

use App\Models\{Admin\ProductImage,
    Admin\VariantImage,
    Discount,
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
use Illuminate\Validation\ValidationException;
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
        /*
         * Editing a product used to behave like delete-and-recreate:
         * the frontend recomputed combinations, missing rows disappeared from the payload,
         * and the backend removed everything not in the submitted list. That broke
         * inventory/order history because stock ledgers and transactions point at durable
         * `product_variants.id` values. We now reconcile edited combinations against the
         * existing variants, preserve IDs whenever the mapping is still one-to-one, create
         * only truly new combinations, and archive removed variants by setting `is_active`
         * to false instead of replacing them.
         */
        $payloads = collect($variants)
            ->filter(fn ($variant) => ! (bool) data_get($variant, 'archived', false))
            ->map(function (array $variant) {
                $variant['value_ids'] = $this->normalizeVariantValueIds($variant['value_ids'] ?? []);

                return $variant;
            })
            ->values();

        $existingVariants = $product->variants()
            ->withTrashed()
            ->with(['values.type:id,name', 'images'])
            ->get()
            ->keyBy('id');

        $this->assertUniqueIncomingVariantIds($payloads, $existingVariants);
        $this->assertUniqueIncomingVariantSignatures($payloads);

        $assigned = [];
        $matchedExistingIds = [];

        foreach ($payloads as $index => $payload) {
            $variantId = data_get($payload, 'id');
            if (!$variantId) {
                continue;
            }

            $variant = $existingVariants->get((int) $variantId);
            if (!$variant) {
                throw ValidationException::withMessages([
                    "variants.{$index}.id" => 'This variant no longer belongs to the product being edited.',
                ]);
            }

            if (isset($matchedExistingIds[$variant->id])) {
                throw ValidationException::withMessages([
                    "variants.{$index}.id" => 'Each existing variant can only be submitted once.',
                ]);
            }

            $assigned[$index] = [
                'variant' => $variant,
                'preserve_existing_defaults' => false,
            ];
            $matchedExistingIds[$variant->id] = true;
        }

        foreach ($payloads as $index => $payload) {
            if (isset($assigned[$index])) {
                continue;
            }

            $variant = $this->findExactVariantMatch($existingVariants, $matchedExistingIds, $payload['value_ids']);
            if (!$variant) {
                continue;
            }

            $assigned[$index] = [
                'variant' => $variant,
                'preserve_existing_defaults' => true,
            ];
            $matchedExistingIds[$variant->id] = true;
        }

        $compatibility = $this->buildCompatibleVariantMatches($existingVariants, $payloads, $assigned, $matchedExistingIds);
        $this->assertCompatibleHistoryMappingsAreSafe($compatibility, $payloads);

        foreach ($compatibility['unique'] as $index => $variant) {
            $assigned[$index] = [
                'variant' => $variant,
                'preserve_existing_defaults' => true,
            ];
            $matchedExistingIds[$variant->id] = true;
        }

        $preservedVariantIds = [];

        foreach ($payloads as $index => $payload) {
            $match = $assigned[$index] ?? null;
            $variant = $match['variant'] ?? new ProductVariant(['product_id' => $product->id]);
            $wasNewVariant = ! $variant->exists;

            $this->persistVariantReconciliation(
                product: $product,
                variant: $variant,
                payload: $payload,
                preserveExistingDefaults: (bool) ($match['preserve_existing_defaults'] ?? false),
            );

            if ($wasNewVariant) {
                $this->recordOpeningBalanceForNewVariant($variant, $payload);
            }

            $preservedVariantIds[$variant->id] = true;
        }

        $existingVariants
            ->filter(fn (ProductVariant $variant) => !$variant->trashed() && $variant->is_active)
            ->reject(fn (ProductVariant $variant) => isset($preservedVariantIds[$variant->id]))
            ->each(function (ProductVariant $variant) {
                $variant->update(['is_active' => false]);
            });
    }

    protected function persistVariantReconciliation(
        Product $product,
        ProductVariant $variant,
        array $payload,
        bool $preserveExistingDefaults = false
    ): void {
        $valueIds = $this->normalizeVariantValueIds($payload['value_ids'] ?? []);
        $finalSku = $this->resolveVariantSku($product, $variant, $payload, $preserveExistingDefaults, $valueIds);

        if ($variant->trashed()) {
            $variant->restore();
        }

        $attributes = Arr::only($payload, [
            'barcode',
            'sale_starts_at',
            'regular_price',
            'sale_price',
            'sale_ends_at',
            'weight',
            'length',
            'width',
            'height',
        ]);

        if ($preserveExistingDefaults && blank(data_get($payload, 'barcode')) && filled($variant->barcode)) {
            unset($attributes['barcode']);
        }

        $variant->fill($attributes);
        $variant->sku = $finalSku;
        $variant->is_active = true;
        $variant->save();

        $variant->values()->sync($valueIds);

        $hasIncomingImages = !empty($payload['images'] ?? []);
        if (!$preserveExistingDefaults || $hasIncomingImages || !$variant->images()->exists()) {
            $this->syncVariantImages($variant, $payload['images'] ?? []);
        }
    }

    protected function recordOpeningBalanceForNewVariant(ProductVariant $variant, array $payload): void
    {
        $quantity = (int) ($payload['quantity'] ?? 0);
        $unitCost = (float) ($payload['last_purchase_price'] ?? 0);

        if ($quantity <= 0) {
            return;
        }

        $openingBalance = OpeningBalance::firstOrCreate(
            ['reference' => 'AUTO-' . now()->format('Ymd-His')],
            [
                'warehouse_id' => $payload['warehouse_id'] ?? null,
                'vendor_id' => null,
                'employee_id' => null,
                'effective_at' => now(),
                'note' => 'Auto created from product creation',
            ]
        );

        $item = OpeningBalanceItem::create([
            'opening_balance_id' => $openingBalance->id,
            'variant_id' => $variant->id,
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
        ]);

        $this->inventoryService->stockIn([
            'variant_id' => $variant->id,
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'warehouse_id' => $payload['warehouse_id'] ?? null,
            'reason' => 'Opening Balance',
            'employee_id' => null,
            'source_type' => OpeningBalanceItem::class,
            'source_id' => $item->id,
            'effective_at' => now(),
            'note' => 'Auto stock-in from opening balance',
        ]);
    }

    protected function resolveVariantSku(
        Product $product,
        ProductVariant $variant,
        array $payload,
        bool $preserveExistingDefaults,
        array $valueIds
    ): string {
        $incomingSku = trim((string) ($payload['sku'] ?? ''));
        if ($preserveExistingDefaults && $variant->exists && $incomingSku === '' && filled($variant->sku)) {
            return $variant->sku;
        }

        $storeId = $product->store_id ?? null;
        $attrLabels = $this->resolveVariantValueLabels($valueIds);
        $brandCode = data_get($product, 'brand.code', data_get($product, 'brand.name', ''));
        $stem = $this->skuGen->makeStem((string) $brandCode, (string) $product->name, $attrLabels);

        if ($incomingSku !== '') {
            return $this->skuGen->acceptOrSuggest($storeId, $incomingSku, $variant->id ?: null)['sku'];
        }

        if ($variant->exists && filled($variant->sku)) {
            return $variant->sku;
        }

        return $this->skuGen->uniqueFromStem($storeId, $stem);
    }

    protected function resolveVariantValueLabels(array $valueIds): array
    {
        if (empty($valueIds)) {
            return [];
        }

        return ProductVariant::query()
            ->getModel()
            ->values()
            ->getRelated()
            ->newQuery()
            ->whereIn('id', $valueIds)
            ->orderBy('variant_type_id')
            ->orderBy('value')
            ->pluck('value')
            ->all();
    }

    protected function findExactVariantMatch(Collection $existingVariants, array $matchedExistingIds, array $valueIds): ?ProductVariant
    {
        $signature = $this->variantSignature($valueIds);

        return $existingVariants
            ->reject(fn (ProductVariant $variant) => isset($matchedExistingIds[$variant->id]))
            ->filter(fn (ProductVariant $variant) => $this->variantSignature($variant->values->modelKeys()) === $signature)
            ->sortBy(fn (ProductVariant $variant) => sprintf(
                '%d-%010d',
                $variant->trashed() ? 2 : ($variant->is_active ? 0 : 1),
                $variant->id
            ))
            ->first();
    }

    protected function buildCompatibleVariantMatches(
        Collection $existingVariants,
        Collection $payloads,
        array $assigned,
        array $matchedExistingIds
    ): array {
        $incomingCandidates = [];
        $candidateCountByVariant = [];
        $protectedCandidateCountByVariant = [];

        foreach ($payloads as $index => $payload) {
            if (isset($assigned[$index])) {
                continue;
            }

            $candidates = $existingVariants
                ->reject(fn (ProductVariant $variant) => isset($matchedExistingIds[$variant->id]))
                ->filter(fn (ProductVariant $variant) => $this->variantSetsAreCompatible(
                    $variant->values->modelKeys(),
                    $payload['value_ids']
                ))
                ->sortBy(fn (ProductVariant $variant) => sprintf(
                    '%d-%03d-%010d',
                    $variant->hasDurableHistory() ? 0 : 1,
                    $this->variantDifferenceSize($variant->values->modelKeys(), $payload['value_ids']),
                    $variant->id
                ))
                ->values();

            $incomingCandidates[$index] = $candidates;

            foreach ($candidates as $candidate) {
                $candidateCountByVariant[$candidate->id] = ($candidateCountByVariant[$candidate->id] ?? 0) + 1;
                if ($candidate->hasDurableHistory()) {
                    $protectedCandidateCountByVariant[$candidate->id] = ($protectedCandidateCountByVariant[$candidate->id] ?? 0) + 1;
                }
            }
        }

        $unique = [];
        foreach ($incomingCandidates as $index => $candidates) {
            $protectedCandidates = $candidates
                ->filter(fn (ProductVariant $variant) => $variant->hasDurableHistory())
                ->values();

            if ($protectedCandidates->count() === 1) {
                $candidate = $protectedCandidates->first();
                if (($protectedCandidateCountByVariant[$candidate->id] ?? 0) === 1) {
                    $unique[$index] = $candidate;
                    continue;
                }
            }

            if ($candidates->count() !== 1) {
                continue;
            }

            $candidate = $candidates->first();
            if (($candidateCountByVariant[$candidate->id] ?? 0) !== 1) {
                continue;
            }

            $unique[$index] = $candidate;
        }

        return [
            'incoming' => $incomingCandidates,
            'counts' => $candidateCountByVariant,
            'unique' => $unique,
        ];
    }

    protected function assertCompatibleHistoryMappingsAreSafe(array $compatibility, Collection $payloads): void
    {
        $errors = [];
        $protectedIncomingByVariant = [];

        foreach ($compatibility['incoming'] as $index => $candidates) {
            $protectedCandidates = $candidates
                ->filter(fn (ProductVariant $variant) => $variant->hasDurableHistory())
                ->values();

            if ($protectedCandidates->count() > 1) {
                $errors["variants.{$index}.value_ids"] = 'This edited combination would merge multiple variants that already have inventory or transaction history.';
            }

            foreach ($protectedCandidates as $candidate) {
                $protectedIncomingByVariant[$candidate->id][] = $index;
            }
        }

        foreach ($protectedIncomingByVariant as $variantId => $indexes) {
            if (count($indexes) <= 1) {
                continue;
            }

            $payloadLabel = $this->describeVariantCompatibilityConflict($compatibility['incoming'][$indexes[0]]->firstWhere('id', $variantId));
            $errors['variants'] = "Variant {$payloadLabel} already has inventory or transaction history and cannot be split into multiple edited combinations.";
            break;
        }

        if (!empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }

    protected function describeVariantCompatibilityConflict(?ProductVariant $variant): string
    {
        if (!$variant) {
            return 'identity';
        }

        $label = $variant->values
            ->map(fn ($value) => trim(($value->type?->name ? $value->type->name . ': ' : '') . $value->value))
            ->implode(' / ');

        return $label !== '' ? "\"{$label}\"" : "\"{$variant->sku}\"";
    }

    protected function assertUniqueIncomingVariantIds(Collection $payloads, Collection $existingVariants): void
    {
        $duplicateIds = $payloads
            ->pluck('id')
            ->filter()
            ->groupBy(fn ($id) => (int) $id)
            ->filter(fn (Collection $rows) => $rows->count() > 1)
            ->keys()
            ->all();

        if (empty($duplicateIds)) {
            return;
        }

        $firstDuplicate = (int) $duplicateIds[0];
        throw ValidationException::withMessages([
            'variants' => "Variant #{$firstDuplicate} was submitted more than once.",
        ]);
    }

    protected function assertUniqueIncomingVariantSignatures(Collection $payloads): void
    {
        $duplicates = $payloads
            ->map(fn (array $payload) => $this->variantSignature($payload['value_ids']))
            ->groupBy(fn (string $signature) => $signature)
            ->filter(fn (Collection $group) => $group->count() > 1);

        if ($duplicates->isEmpty()) {
            return;
        }

        throw ValidationException::withMessages([
            'variants' => 'Each edited variant combination must be unique.',
        ]);
    }

    protected function normalizeVariantValueIds(array $valueIds): array
    {
        return collect($valueIds)
            ->map(fn ($valueId) => (int) $valueId)
            ->filter(fn ($valueId) => $valueId > 0)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    protected function variantSignature(array $valueIds): string
    {
        return json_encode($this->normalizeVariantValueIds($valueIds));
    }

    protected function variantSetsAreCompatible(array $existingValueIds, array $incomingValueIds): bool
    {
        $existing = $this->normalizeVariantValueIds($existingValueIds);
        $incoming = $this->normalizeVariantValueIds($incomingValueIds);

        $existingDiff = array_diff($existing, $incoming);
        $incomingDiff = array_diff($incoming, $existing);

        return empty($existingDiff) || empty($incomingDiff);
    }

    protected function variantDifferenceSize(array $left, array $right): int
    {
        $left = $this->normalizeVariantValueIds($left);
        $right = $this->normalizeVariantValueIds($right);

        return count(array_diff($left, $right)) + count(array_diff($right, $left));
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

            $source->variants()->active()->with(['values', 'images'])->get()->each(function ($variant) use ($new, &$oldToNewVariant, &$valuePivot, $source) {
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
                ->active()
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
        $appliedLineItemDiscount = null;

        if (($lineItemDiscount['discount_id'] ?? null) && (float) $lineItemDiscount['current'] < $current) {
            $current = (float) $lineItemDiscount['current'];
            $discountSource = 'automatic';
            $discountLabel = $lineItemDiscount['label'];
            $discountId = $lineItemDiscount['discount_id'];
            $appliedLineItemDiscount = $lineItemDiscount;
        }

        $hasDiscount = round($current, 2) < round($regular, 2);
        $discountAmount = $hasDiscount ? round(max($regular - $current, 0), 2) : 0.0;
        $percentage = $hasDiscount && $regular > 0
            ? (int) round((($regular - $current) / $regular) * 100)
            : 0;

        return [
            'regular' => round($regular, 2),
            'sale' => $legacyPricing['sale'] !== null ? round((float) $legacyPricing['sale'], 2) : null,
            'current' => round($current, 2),
            'has_discount' => $hasDiscount,
            'discount_amount' => $discountAmount,
            'discount_percentage' => $percentage,
            'discount_label' => $discountLabel,
            'discount_display_label' => $this->resolveDiscountDisplayLabel(
                regular: $regular,
                current: $current,
                hasDiscount: $hasDiscount,
                percentage: $percentage,
                appliedLineItemDiscount: $appliedLineItemDiscount,
            ),
            'discount_source' => $discountSource,
            'discount_id' => $discountId,
        ];
    }

    public function resolveProductStock(Product $product): array
    {
        $variants = $product->relationLoaded('variants')
            ? $product->variants->where('is_active', true)
            : $product->variants()->active()->get(['id', 'product_id', 'quantity', 'reserved', 'is_active']);

        $onHand = (int) $variants->sum('quantity');
        $reserved = (int) $variants->sum('reserved');
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
            ->whereHas('variants', fn (Builder $query) => $query->where('is_active', true))
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
                ->active()
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
                'discount_amount' => 0.0,
                'discount_percentage' => 0,
                'discount_label' => null,
                'discount_display_label' => null,
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
            'discount_amount' => (float) ($displayPricing['discount_amount'] ?? 0),
            'discount_percentage' => (int) ($displayPricing['discount_percentage'] ?? 0),
            'discount_label' => $displayPricing['discount_label'] ?? null,
            'discount_display_label' => $displayPricing['discount_display_label'] ?? null,
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

    protected function resolveDiscountDisplayLabel(
        float $regular,
        float $current,
        bool $hasDiscount,
        int $percentage,
        ?array $appliedLineItemDiscount = null
    ): ?string {
        if (!$hasDiscount) {
            return null;
        }

        if (($appliedLineItemDiscount['discount_id'] ?? null) !== null) {
            return match ($appliedLineItemDiscount['type'] ?? null) {
                Discount::TYPE_PERCENTAGE => '-' . ((int) ($appliedLineItemDiscount['percentage'] ?? $percentage)) . '%',
                Discount::TYPE_FIXED_AMOUNT => '-' . $this->formatMoneyLabel((float) ($appliedLineItemDiscount['amount'] ?? max($regular - $current, 0))),
                default => null,
            };
        }

        if ($percentage > 0) {
            return '-' . $percentage . '%';
        }

        $amount = max(round($regular - $current, 2), 0.0);

        return $amount > 0 ? '-' . $this->formatMoneyLabel($amount) : null;
    }

    protected function formatMoneyLabel(float $amount): string
    {
        $amount = round(max($amount, 0), 2);
        $decimals = abs($amount - round($amount)) < 0.01 ? 0 : 2;

        return '₦' . number_format($amount, $decimals);
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

