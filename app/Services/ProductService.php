<?php

namespace App\Services;

use App\Models\{Admin\ProductImage,
    Admin\VariantImage,
    OpeningBalance,
    OpeningBalanceItem,
    Product,
    ProductFaq,
    ProductVariant};
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
