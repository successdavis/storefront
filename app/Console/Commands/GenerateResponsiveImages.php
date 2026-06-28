<?php

namespace App\Console\Commands;

use App\Models\Admin\ProductImage;
use App\Models\Admin\VariantImage;
use App\Models\Brand;
use App\Models\Category;
use App\Services\ImageOptimizationService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Throwable;

class GenerateResponsiveImages extends Command
{
    protected $signature = 'media:generate-responsive-images
        {--disk= : Filesystem disk containing the current upload files}
        {--only=all : all, products, variants, brands, or categories}
        {--regenerate : Replace images that already have responsive variants}
        {--dry-run : Show what would be processed without writing files}
        {--force : Required for production writes}';

    protected $description = 'Generate responsive WebP image variants for existing uploaded media.';

    public function __construct(private ImageOptimizationService $images)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $disk = (string) ($this->option('disk') ?: config('filesystems.uploads_disk', 'public'));
        $only = Str::lower((string) $this->option('only'));
        $dryRun = (bool) $this->option('dry-run');
        $regenerate = (bool) $this->option('regenerate');

        if (! in_array($only, ['all', 'products', 'variants', 'brands', 'categories'], true)) {
            $this->error('--only must be one of: all, products, variants, brands, categories.');

            return self::FAILURE;
        }

        if (! $dryRun && app()->environment('production') && ! $this->option('force')) {
            $this->error('Production image generation requires --force. Run with --dry-run first to preview.');

            return self::FAILURE;
        }

        $stats = [
            'processed' => 0,
            'generated' => 0,
            'skipped' => 0,
            'failed' => 0,
        ];

        foreach ($this->targets($only) as $target) {
            $this->line("Processing {$target['label']}...");

            $target['model']::query()
                ->orderBy('id')
                ->chunkById(100, function ($models) use ($target, $disk, $dryRun, $regenerate, &$stats) {
                    foreach ($models as $model) {
                        $stats['processed']++;
                        $result = $this->processModelImage(
                            $model,
                            $target['path'],
                            $target['variants'],
                            $disk,
                            $dryRun,
                            $regenerate,
                            $target['config'] ?? null
                        );

                        $stats[$result]++;
                    }
                });
        }

        $this->newLine();
        $this->info(sprintf(
            '%s %d media record(s). Generated: %d. Skipped: %d. Failed: %d.',
            $dryRun ? 'Would inspect' : 'Inspected',
            $stats['processed'],
            $stats['generated'],
            $stats['skipped'],
            $stats['failed']
        ));

        if ($dryRun) {
            $this->line('Dry run only. Re-run without --dry-run to write variants.');
        }

        return $stats['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return list<array{label:string, model:class-string<Model>, path:string, variants:string, config?:array|null}>
     */
    protected function targets(string $only): array
    {
        $targets = [
            'products' => [
                'label' => 'product images',
                'model' => ProductImage::class,
                'path' => 'path',
                'variants' => 'responsive_paths',
                'config' => config('media.image_variants'),
            ],
            'variants' => [
                'label' => 'variant images',
                'model' => VariantImage::class,
                'path' => 'path',
                'variants' => 'responsive_paths',
                'config' => config('media.image_variants'),
            ],
            'brands' => [
                'label' => 'brand logos',
                'model' => Brand::class,
                'path' => 'logo',
                'variants' => 'logo_responsive_paths',
                'config' => config('media.logo_variants'),
            ],
            'categories' => [
                'label' => 'category images',
                'model' => Category::class,
                'path' => 'banner',
                'variants' => 'banner_responsive_paths',
                'config' => config('media.image_variants'),
            ],
        ];

        if ($only === 'categories') {
            return [
                $targets['categories'],
                [
                    'label' => 'category icons',
                    'model' => Category::class,
                    'path' => 'icon',
                    'variants' => 'icon_responsive_paths',
                    'config' => config('media.logo_variants'),
                ],
                [
                    'label' => 'category cover images',
                    'model' => Category::class,
                    'path' => 'cover_image',
                    'variants' => 'cover_image_responsive_paths',
                    'config' => config('media.image_variants'),
                ],
            ];
        }

        if ($only !== 'all') {
            return [$targets[$only]];
        }

        return [
            $targets['products'],
            $targets['variants'],
            $targets['brands'],
            $targets['categories'],
            [
                'label' => 'category icons',
                'model' => Category::class,
                'path' => 'icon',
                'variants' => 'icon_responsive_paths',
                'config' => config('media.logo_variants'),
            ],
            [
                'label' => 'category cover images',
                'model' => Category::class,
                'path' => 'cover_image',
                'variants' => 'cover_image_responsive_paths',
                'config' => config('media.image_variants'),
            ],
        ];
    }

    protected function processModelImage(
        Model $model,
        string $pathColumn,
        string $variantsColumn,
        string $disk,
        bool $dryRun,
        bool $regenerate,
        ?array $variantConfig
    ): string {
        $path = $model->{$pathColumn};
        $variants = $model->{$variantsColumn};

        if (! is_string($path) || $path === '' || $this->images->isExternalPath($path)) {
            return 'skipped';
        }

        if (! $regenerate && is_array($variants) && $variants !== []) {
            return 'skipped';
        }

        if ($dryRun) {
            return 'generated';
        }

        try {
            $oldPaths = $this->images->storedPaths($path, is_array($variants) ? $variants : null);
            $optimized = $this->images->storeResponsiveImageFromPath(
                $path,
                $this->directoryFor($path),
                $disk,
                $variantConfig
            );

            try {
                $model->forceFill([
                    $pathColumn => $optimized['path'],
                    $variantsColumn => $optimized['variants'],
                ])->save();
            } catch (Throwable $e) {
                $this->images->deleteResponsiveImage($optimized['path'], $optimized['variants'], $disk);

                throw $e;
            }

            $this->images->deletePaths(
                $oldPaths,
                $disk,
                $this->images->storedPaths($optimized['path'], $optimized['variants'])
            );

            return 'generated';
        } catch (Throwable $e) {
            $this->warn(sprintf(
                '%s #%s [%s]: %s',
                class_basename($model),
                $model->getKey(),
                $path,
                $e->getMessage()
            ));

            return 'failed';
        }
    }

    protected function directoryFor(string $path): string
    {
        return Str::contains($path, '/') ? Str::beforeLast($path, '/') : 'uploads';
    }
}
