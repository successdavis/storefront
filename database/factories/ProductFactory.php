<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->words(3, true);

        // Weight and dimensions
        $hasWeight = $this->faker->boolean(70);
        $weight    = $hasWeight ? $this->faker->randomFloat(3, 0.20, 8.00) : null;
        $unit      = $hasWeight ? 'kg' : null;

        $hasDims = $this->faker->boolean(60);
        $length  = $hasDims ? $this->faker->randomFloat(2, 10, 60) : null;
        $width   = $hasDims ? $this->faker->randomFloat(2, 10, 60) : null;
        $height  = $hasDims ? $this->faker->randomFloat(2, 2, 30)  : null;

        $hasVideo = $this->faker->boolean(15);

        return [
            'brand_id'            => null, // set later via state/seeder
            'name'                => $name,
            'slug'                => Str::slug($name) . '-' . Str::lower(Str::random(6)),

            'meta_title'          => Str::limit($name, 60, ''),
            'meta_description'    => $this->faker->sentences(3, true),

            'youtube_video_url'   => $hasVideo
                ? 'https://www.youtube.com/watch?v=' . $this->faker->bothify('###########')
                : null,

            'cash_on_delivery'    => $this->faker->boolean(85),
            'featured'            => $this->faker->boolean(10),

            'weight'              => $weight,
            'weight_unit'         => $unit,

            'description'         => $this->faker->paragraphs(2, true),
            'is_active'           => true,

            'length'              => $length,
            'width'               => $width,
            'height'              => $height,
        ];
    }

    /**
     * Attach existing brands if present, otherwise create one.
     */
    public function withBrand(): self
    {
        return $this->state(function () {
            return [
                'brand_id' => Brand::query()->inRandomOrder()->value('id') ?? Brand::factory(),
            ];
        });
    }

    public function inactive(): self   { return $this->state(fn() => ['is_active' => false]); }
    public function featured(): self   { return $this->state(fn() => ['featured' => true]); }
    public function codOnly(): self    { return $this->state(fn() => ['cash_on_delivery' => true]); }
}
