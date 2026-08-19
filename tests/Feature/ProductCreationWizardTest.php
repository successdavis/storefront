<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Models\VariantType;
use App\Models\VariantValue;
use App\Support\RoleNames;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProductCreationWizardTest extends TestCase
{
    use RefreshDatabase;

    protected function actingAsDirector(): User
    {
        $director = User::factory()->create();
        $director->syncRoles([RoleNames::DIRECTOR]);
        $this->actingAs($director);

        return $director;
    }

    public function test_create_page_renders_the_wizard(): void
    {
        $this->actingAsDirector();

        $this->get(route('admin.products.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Products/Create')
                ->has('categories')
                ->has('brands')
                ->has('variantTypes'));
    }

    public function test_simple_product_is_created_in_one_request_with_images(): void
    {
        Config::set('filesystems.uploads_disk', 'public');
        Storage::fake('public');

        $this->actingAsDirector();

        $brand = Brand::factory()->create();
        $category = Category::factory()->create();

        $response = $this->post(route('admin.products.store'), [
            'name' => 'Wizard Test Printer',
            'brand_id' => $brand->id,
            'category_ids' => [$category->id],
            'description' => 'A printer created through the guided wizard.',
            'is_active' => true,
            'meta_title' => 'Wizard Test Printer',
            'variants' => [
                [
                    'sku' => 'WIZ-PRINTER-001',
                    'quantity' => 5,
                    'last_purchase_price' => 100,
                    'regular_price' => 150,
                    'replenishment_status' => 'reorderable',
                    'fulfillment_type' => 'stocked',
                    'value_ids' => [],
                ],
            ],
            'images' => [
                [
                    'file' => UploadedFile::fake()->image('front.jpg', 800, 800),
                    'alt' => 'Front view',
                    'is_primary' => 1,
                    'sort_order' => 0,
                ],
            ],
        ]);

        $product = Product::query()->where('name', 'Wizard Test Printer')->firstOrFail();

        $response->assertRedirect(route('admin.products.show', $product));

        $this->assertTrue($product->is_active);
        $this->assertSame($brand->id, $product->brand_id);
        $this->assertTrue($product->categories()->whereKey($category->id)->exists());

        $variant = $product->variants()->firstOrFail();
        $this->assertSame('WIZ-PRINTER-001', $variant->sku);
        $this->assertSame(5, (int) $variant->quantity);

        $image = $product->images()->firstOrFail();
        $this->assertTrue((bool) $image->is_primary);
        $this->assertSame('Front view', $image->alt);
        Storage::disk('public')->assertExists($image->path);
    }

    public function test_product_with_option_variants_and_variant_photo_is_created(): void
    {
        Config::set('filesystems.uploads_disk', 'public');
        Storage::fake('public');

        $this->actingAsDirector();

        $brand = Brand::factory()->create();
        $category = Category::factory()->create();
        $type = VariantType::factory()->create(['name' => 'Colour']);
        $red = VariantValue::factory()->create(['variant_type_id' => $type->id, 'value' => 'Red']);
        $blue = VariantValue::factory()->create(['variant_type_id' => $type->id, 'value' => 'Blue']);

        $this->post(route('admin.products.store'), [
            'name' => 'Wizard Test Mouse',
            'brand_id' => $brand->id,
            'category_ids' => [$category->id],
            'description' => 'A mouse in two colours.',
            'is_active' => false,
            'variants' => [
                [
                    'quantity' => 3,
                    'last_purchase_price' => 10,
                    'regular_price' => 25,
                    'value_ids' => [$red->id],
                    'images' => [UploadedFile::fake()->image('red.jpg', 400, 400)],
                ],
                [
                    'quantity' => 4,
                    'last_purchase_price' => 10,
                    'regular_price' => 25,
                    'value_ids' => [$blue->id],
                ],
            ],
        ])->assertRedirect();

        $product = Product::query()->where('name', 'Wizard Test Mouse')->firstOrFail();
        $this->assertFalse($product->is_active);
        $this->assertSame(2, $product->variants()->count());

        $redVariant = $product->variants()
            ->whereHas('values', fn ($query) => $query->whereKey($red->id))
            ->firstOrFail();
        $this->assertSame(1, $redVariant->images()->count());
        Storage::disk('public')->assertExists($redVariant->images()->first()->path);
    }

    public function test_brand_can_be_quick_created_inline(): void
    {
        $this->actingAsDirector();

        $response = $this->postJson(route('admin.brands.quick-store'), ['name' => 'Logitech'])
            ->assertOk()
            ->assertJsonPath('brand.name', 'Logitech');

        $this->assertDatabaseHas('brands', ['name' => 'Logitech', 'slug' => 'logitech']);
        $this->assertContains('Logitech', array_column($response->json('brands'), 'name'));

        // Same name again is rejected with a validation error.
        $this->postJson(route('admin.brands.quick-store'), ['name' => 'Logitech'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_category_can_be_quick_created_inline_under_a_parent(): void
    {
        $this->actingAsDirector();

        $parent = Category::factory()->create(['name' => 'Electronics', 'parent_id' => null]);

        $response = $this->postJson(route('admin.categories.quick-store'), [
            'name' => 'Gaming Consoles',
            'parent_id' => $parent->id,
        ])
            ->assertOk()
            ->assertJsonPath('category.name', 'Gaming Consoles')
            ->assertJsonPath('category.parent_id', $parent->id);

        $this->assertDatabaseHas('categories', [
            'name' => 'Gaming Consoles',
            'parent_id' => $parent->id,
            'slug' => 'gaming-consoles',
        ]);

        // The refreshed tree nests the new category under its parent.
        $tree = collect($response->json('categories'));
        $parentNode = $tree->firstWhere('id', $parent->id);
        $this->assertNotNull($parentNode);
        $this->assertContains('Gaming Consoles', array_column($parentNode['children'], 'name'));
    }

    public function test_wizard_autosaves_a_draft_and_can_resume_it(): void
    {
        $this->actingAsDirector();

        $brand = Brand::factory()->create();
        $category = Category::factory()->create();

        $draftId = $this->postJson(route('admin.products.draft.store'), [
            'name' => 'Autosaved Router',
            'brand_id' => $brand->id,
            'category_ids' => [$category->id],
            'description' => 'Draft in progress.',
            'faqs' => [],
        ])
            ->assertOk()
            ->json('id');

        $this->assertDatabaseHas('products', [
            'id' => $draftId,
            'name' => 'Autosaved Router',
            'is_active' => false,
        ]);

        // Subsequent auto-saves refresh the same draft.
        $this->patchJson(route('admin.products.draft.update', $draftId), [
            'name' => 'Autosaved Router X2',
            'brand_id' => $brand->id,
            'category_ids' => [$category->id],
            'description' => 'Draft in progress, updated.',
            'meta_title' => 'Autosaved Router X2',
            'faqs' => [
                ['question' => 'Does it support mesh?', 'answer' => 'Yes.', 'is_active' => true, 'position' => 0],
            ],
        ])->assertOk();

        $this->assertDatabaseHas('products', ['id' => $draftId, 'name' => 'Autosaved Router X2', 'is_active' => false]);
        $this->assertDatabaseHas('product_faqs', ['product_id' => $draftId, 'question' => 'Does it support mesh?']);

        // Reloading the create page with ?draft= restores the saved fields.
        $this->get(route('admin.products.create', ['draft' => $draftId]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Products/Create')
                ->where('draft.id', $draftId)
                ->where('draft.name', 'Autosaved Router X2')
                ->where('draft.category_ids.0', $category->id)
                ->count('draft.faqs', 1));
    }

    public function test_finalizing_a_draft_publishes_it_with_variants_images_and_a_fresh_slug(): void
    {
        Config::set('filesystems.uploads_disk', 'public');
        Storage::fake('public');

        $this->actingAsDirector();

        $brand = Brand::factory()->create();
        $category = Category::factory()->create();

        $draftId = $this->postJson(route('admin.products.draft.store'), [
            'name' => 'Wizard Draft Phone',
            'brand_id' => $brand->id,
            'category_ids' => [$category->id],
            'description' => 'Early draft.',
        ])->json('id');

        $response = $this->post(route('admin.products.draft.finalize', $draftId), [
            'name' => 'Wizard Draft Phone Pro',
            'brand_id' => $brand->id,
            'category_ids' => [$category->id],
            'description' => 'Finished description.',
            'is_active' => true,
            'variants' => [
                [
                    'sku' => 'WIZ-DRAFT-PHONE-001',
                    'quantity' => 7,
                    'last_purchase_price' => 200,
                    'regular_price' => 300,
                    'value_ids' => [],
                ],
            ],
            'images' => [
                [
                    'file' => UploadedFile::fake()->image('phone.jpg', 600, 600),
                    'is_primary' => 1,
                    'sort_order' => 0,
                ],
            ],
        ]);

        $product = Product::findOrFail($draftId);
        $response->assertRedirect(route('admin.products.show', $product));

        $this->assertTrue($product->is_active);
        $this->assertSame('Wizard Draft Phone Pro', $product->name);
        $this->assertSame('wizard-draft-phone-pro', $product->slug);

        $variant = $product->variants()->firstOrFail();
        $this->assertSame('WIZ-DRAFT-PHONE-001', $variant->sku);
        $this->assertSame(7, (int) $variant->quantity);

        $image = $product->images()->firstOrFail();
        Storage::disk('public')->assertExists($image->path);
    }

    public function test_draft_endpoints_reject_products_that_are_not_unfinished_drafts(): void
    {
        $this->actingAsDirector();

        $brand = Brand::factory()->create();
        $category = Category::factory()->create();

        $published = Product::factory()->create(['is_active' => true, 'brand_id' => $brand->id]);

        $payload = [
            'name' => 'Should Not Save',
            'brand_id' => $brand->id,
            'category_ids' => [$category->id],
            'description' => 'Nope.',
        ];

        $this->patchJson(route('admin.products.draft.update', $published), $payload)->assertStatus(409);
        $this->post(route('admin.products.draft.finalize', $published), $payload + [
            'variants' => [['quantity' => 1, 'last_purchase_price' => 1, 'regular_price' => 2, 'value_ids' => []]],
        ])->assertStatus(409);

        // A published product id is not offered for resume either.
        $this->get(route('admin.products.create', ['draft' => $published->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('draft', null));
    }

    public function test_edit_page_renders_the_wizard_with_the_product_loaded(): void
    {
        Config::set('filesystems.uploads_disk', 'public');
        Storage::fake('public');

        $this->actingAsDirector();

        $brand = Brand::factory()->create();
        $category = Category::factory()->create();

        $this->post(route('admin.products.store'), [
            'name' => 'Editable Widget',
            'brand_id' => $brand->id,
            'category_ids' => [$category->id],
            'description' => 'A widget to edit.',
            'is_active' => true,
            'variants' => [
                ['sku' => 'EDIT-WIDGET-001', 'quantity' => 2, 'last_purchase_price' => 10, 'regular_price' => 20, 'value_ids' => []],
            ],
            'images' => [
                ['file' => UploadedFile::fake()->image('widget.jpg', 400, 400), 'is_primary' => 1, 'sort_order' => 0],
            ],
        ]);

        $product = Product::query()->where('name', 'Editable Widget')->firstOrFail();

        $this->get(route('admin.products.edit', $product))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Products/Create')
                ->where('product.data.id', $product->id)
                ->where('product.data.name', 'Editable Widget')
                ->where('product.data.variants.0.sku', 'EDIT-WIDGET-001')
                ->has('product.data.images.0.url')
                ->has('variantTypes'));
    }

    public function test_wizard_update_syncs_name_images_and_variant_price(): void
    {
        Config::set('filesystems.uploads_disk', 'public');
        Storage::fake('public');

        $this->actingAsDirector();

        $brand = Brand::factory()->create();
        $category = Category::factory()->create();

        $this->post(route('admin.products.store'), [
            'name' => 'Widget Before Edit',
            'brand_id' => $brand->id,
            'category_ids' => [$category->id],
            'description' => 'Before.',
            'is_active' => true,
            'variants' => [
                ['sku' => 'EDIT-SYNC-001', 'quantity' => 4, 'last_purchase_price' => 10, 'regular_price' => 20, 'value_ids' => []],
            ],
            'images' => [
                ['file' => UploadedFile::fake()->image('old.jpg', 400, 400), 'alt' => 'Old photo', 'is_primary' => 1, 'sort_order' => 0],
            ],
        ]);

        $product = Product::query()->where('name', 'Widget Before Edit')->firstOrFail();
        $variant = $product->variants()->firstOrFail();
        $existingImage = $product->images()->firstOrFail();

        // What the wizard submits on save: keep the old image, add a new one, bump the price.
        $this->put(route('admin.products.update', $product), [
            'name' => 'Widget After Edit',
            'brand_id' => $brand->id,
            'category_ids' => [$category->id],
            'description' => 'After.',
            'is_active' => true,
            'variants' => [
                [
                    'id' => $variant->id,
                    'sku' => $variant->sku,
                    'quantity' => 4,
                    'regular_price' => 25,
                    'value_ids' => [],
                    'images' => [],
                ],
            ],
            'images' => [
                ['id' => $existingImage->id, 'path' => $existingImage->path, 'alt' => 'Old photo kept', 'is_primary' => 0, 'sort_order' => 0],
                ['file' => UploadedFile::fake()->image('new.jpg', 500, 500), 'alt' => 'New photo', 'is_primary' => 1, 'sort_order' => 1],
            ],
        ])->assertRedirect(route('admin.products.show', $product));

        $product->refresh();
        $this->assertSame('Widget After Edit', $product->name);
        $this->assertSame(25.0, (float) $product->variants()->first()->regular_price);

        $images = $product->images()->orderBy('sort_order')->get();
        $this->assertCount(2, $images);
        $this->assertSame('Old photo kept', $images[0]->alt);
        $this->assertTrue((bool) $images[1]->is_primary);
        Storage::disk('public')->assertExists($images[1]->path);
    }

    public function test_wizard_update_accepts_new_variant_photo_uploads(): void
    {
        Config::set('filesystems.uploads_disk', 'public');
        Storage::fake('public');

        $this->actingAsDirector();

        $brand = Brand::factory()->create();
        $category = Category::factory()->create();

        $this->post(route('admin.products.store'), [
            'name' => 'Variant Photo Product',
            'brand_id' => $brand->id,
            'category_ids' => [$category->id],
            'description' => 'Testing variant photos on update.',
            'variants' => [
                ['sku' => 'VAR-PHOTO-001', 'quantity' => 1, 'last_purchase_price' => 5, 'regular_price' => 9, 'value_ids' => []],
            ],
        ]);

        $product = Product::query()->where('name', 'Variant Photo Product')->firstOrFail();
        $variant = $product->variants()->firstOrFail();

        $this->put(route('admin.products.update', $product), [
            'name' => 'Variant Photo Product',
            'brand_id' => $brand->id,
            'category_ids' => [$category->id],
            'description' => 'Testing variant photos on update.',
            'is_active' => true,
            'variants' => [
                [
                    'id' => $variant->id,
                    'regular_price' => 9,
                    'quantity' => 1,
                    'value_ids' => [],
                    'images' => [UploadedFile::fake()->image('variant-photo.jpg', 300, 300)],
                ],
            ],
        ])->assertRedirect();

        $this->assertSame(1, $variant->images()->count());
        Storage::disk('public')->assertExists($variant->images()->first()->path);
    }

    public function test_update_without_images_key_leaves_gallery_untouched(): void
    {
        Config::set('filesystems.uploads_disk', 'public');
        Storage::fake('public');

        $this->actingAsDirector();

        $brand = Brand::factory()->create();
        $category = Category::factory()->create();

        $this->post(route('admin.products.store'), [
            'name' => 'Gallery Guard Product',
            'brand_id' => $brand->id,
            'category_ids' => [$category->id],
            'description' => 'Guard test.',
            'variants' => [
                ['quantity' => 1, 'last_purchase_price' => 5, 'regular_price' => 9, 'value_ids' => []],
            ],
            'images' => [
                ['file' => UploadedFile::fake()->image('keep.jpg', 300, 300), 'is_primary' => 1, 'sort_order' => 0],
            ],
        ]);

        $product = Product::query()->where('name', 'Gallery Guard Product')->firstOrFail();
        $variant = $product->variants()->firstOrFail();

        $this->put(route('admin.products.update', $product), [
            'name' => 'Gallery Guard Product',
            'brand_id' => $brand->id,
            'category_ids' => [$category->id],
            'description' => 'Guard test, updated.',
            'is_active' => true,
            'variants' => [
                ['id' => $variant->id, 'regular_price' => 9, 'quantity' => 1, 'value_ids' => []],
            ],
        ])->assertRedirect();

        $this->assertSame(1, $product->images()->count());
    }

    public function test_store_rejects_missing_required_fields(): void
    {
        $this->actingAsDirector();

        $this->from(route('admin.products.create'))
            ->post(route('admin.products.store'), [])
            ->assertSessionHasErrors(['name', 'brand_id', 'category_ids', 'description']);
    }

    public function test_store_rejects_non_image_uploads(): void
    {
        Config::set('filesystems.uploads_disk', 'public');
        Storage::fake('public');

        $this->actingAsDirector();

        $brand = Brand::factory()->create();
        $category = Category::factory()->create();

        $this->from(route('admin.products.create'))
            ->post(route('admin.products.store'), [
                'name' => 'Bad Upload Product',
                'brand_id' => $brand->id,
                'category_ids' => [$category->id],
                'description' => 'Testing invalid uploads.',
                'variants' => [
                    [
                        'quantity' => 1,
                        'last_purchase_price' => 5,
                        'regular_price' => 9,
                        'value_ids' => [],
                    ],
                ],
                'images' => [
                    [
                        'file' => UploadedFile::fake()->create('malware.pdf', 100, 'application/pdf'),
                        'is_primary' => 1,
                        'sort_order' => 0,
                    ],
                ],
            ])
            ->assertSessionHasErrors(['images.0.file']);

        $this->assertDatabaseMissing('products', ['name' => 'Bad Upload Product']);
    }
}
