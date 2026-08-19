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
