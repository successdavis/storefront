<?php

namespace App\Services;

use App\Support\MediaUrl;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;
use RuntimeException;
use Throwable;

class ImageOptimizationService
{
    public function __construct(
        private ImageManager $imageManager = new ImageManager(new Driver())
    ) {}

    /**
     * @return array{path:string, variants:array<string, array{path:string, url?:string, width:int, height:int, mime:string}>}
     */
    public function storeResponsiveImage(
        UploadedFile $file,
        string $dir,
        string $disk,
        ?array $variantConfig = null
    ): array {
        return $this->storeVariants(
            fn () => $this->imageManager->read($file),
            $dir,
            $disk,
            $variantConfig
        );
    }

    /**
     * @return array{path:string, variants:array<string, array{path:string, url?:string, width:int, height:int, mime:string}>}
     */
    public function storeResponsiveImageFromPath(
        string $sourcePath,
        string $dir,
        string $disk,
        ?array $variantConfig = null
    ): array {
        if ($this->isExternalPath($sourcePath)) {
            throw new RuntimeException("External media path [{$sourcePath}] cannot be optimized from disk [{$disk}].");
        }

        $storage = Storage::disk($disk);

        if (! $storage->exists($sourcePath)) {
            throw new RuntimeException("Source image [{$sourcePath}] was not found on disk [{$disk}].");
        }

        $contents = $storage->get($sourcePath);

        return $this->storeVariants(
            fn () => $this->imageManager->read($contents),
            $dir,
            $disk,
            $variantConfig
        );
    }

    /**
     * @param callable(): ImageInterface $imageFactory
     * @return array{path:string, variants:array<string, array{path:string, width:int, height:int, mime:string}>}
     */
    protected function storeVariants(
        callable $imageFactory,
        string $dir,
        string $disk,
        ?array $variantConfig = null
    ): array {
        $variantConfig ??= (array) config('media.image_variants', []);

        if ($variantConfig === []) {
            throw new RuntimeException('No responsive image variants are configured.');
        }

        $quality = max(1, min(100, (int) config('media.image_quality', 76)));
        $dir = trim($dir, '/');
        $baseName = Str::random(40);
        $writtenPaths = [];
        $variants = [];

        $source = $imageFactory();
        $sourceWidth = $source->width();

        try {
            foreach ($variantConfig as $name => $settings) {
                $name = Str::slug((string) $name, '_') ?: 'image';
                $targetWidth = $this->targetWidth($settings);
                $isOriginal = $name === 'original';

                if (! $isOriginal && $targetWidth !== null && $targetWidth > $sourceWidth) {
                    continue;
                }

                $image = $imageFactory();

                if ($targetWidth !== null) {
                    $image = $image->scaleDown(width: $targetWidth);
                }

                $path = ($dir !== '' ? "{$dir}/" : '') . "{$baseName}-{$name}.webp";
                $stored = Storage::disk($disk)->put($path, $image->toWebp($quality)->toString());

                if (! $stored) {
                    throw new RuntimeException("Unable to store responsive image [{$path}] on disk [{$disk}].");
                }

                $writtenPaths[] = $path;
                $variants[$name] = [
                    'path' => $path,
                    'width' => $image->width(),
                    'height' => $image->height(),
                    'mime' => 'image/webp',
                ];
            }
        } catch (Throwable $e) {
            $this->deletePaths($writtenPaths, $disk);

            throw new RuntimeException(
                "Unable to generate responsive image variants on disk [{$disk}]: {$e->getMessage()}",
                previous: $e
            );
        }

        if ($variants === []) {
            throw new RuntimeException("No responsive image variants were generated on disk [{$disk}].");
        }

        $primaryPath = $variants['original']['path']
            ?? collect($variants)->sortByDesc('width')->first()['path'];

        return [
            'path' => $primaryPath,
            'variants' => $variants,
        ];
    }

    public function deleteResponsiveImage(?string $primaryPath, ?array $variants, string $disk): void
    {
        $this->deletePaths($this->storedPaths($primaryPath, $variants), $disk);
    }

    /**
     * @return array<string, array{url:string, width:int|null, height:int|null, mime:string|null}>
     */
    public function toResponsiveUrls(?array $variants, ?string $fallbackPath = null, ?string $disk = null): array
    {
        $disk ??= (string) config('filesystems.uploads_disk', 'public');
        $normalized = $this->normalizeVariants($variants);

        if ($normalized === [] && $fallbackPath) {
            $normalized['original'] = [
                'path' => $fallbackPath,
                'width' => null,
                'height' => null,
                'mime' => null,
            ];
        }

        $urls = [];

        foreach ($normalized as $name => $variant) {
            $path = $variant['path'] ?? null;

            if (! is_string($path) || $path === '') {
                continue;
            }

            $urls[$name] = [
                'url' => MediaUrl::make($path, $disk),
                'width' => isset($variant['width']) ? (int) $variant['width'] : null,
                'height' => isset($variant['height']) ? (int) $variant['height'] : null,
                'mime' => $variant['mime'] ?? null,
            ];
        }

        return $urls;
    }

    public function srcset(?array $responsiveUrls): ?string
    {
        if (! $responsiveUrls) {
            return null;
        }

        $candidates = collect($responsiveUrls)
            ->filter(fn (array $variant) => filled($variant['url'] ?? null) && filled($variant['width'] ?? null))
            ->sortBy('width')
            ->map(fn (array $variant) => $variant['url'] . ' ' . (int) $variant['width'] . 'w')
            ->values()
            ->all();

        return $candidates === [] ? null : implode(', ', $candidates);
    }

    /**
     * @return list<string>
     */
    public function storedPaths(?string $primaryPath, ?array $variants): array
    {
        $paths = [];

        if ($primaryPath) {
            $paths[] = $primaryPath;
        }

        foreach ($this->normalizeVariants($variants) as $variant) {
            $path = $variant['path'] ?? null;

            if (is_string($path) && $path !== '') {
                $paths[] = $path;
            }
        }

        return collect($paths)
            ->filter(fn (string $path) => ! $this->isExternalPath($path))
            ->unique()
            ->values()
            ->all();
    }

    public function isExternalPath(?string $path): bool
    {
        return $path !== null && Str::startsWith($path, ['http://', 'https://', '/']);
    }

    protected function targetWidth(mixed $settings): ?int
    {
        if (is_numeric($settings)) {
            $width = (int) $settings;
        } elseif (is_array($settings) && isset($settings['width']) && is_numeric($settings['width'])) {
            $width = (int) $settings['width'];
        } else {
            return null;
        }

        return $width > 0 ? $width : null;
    }

    /**
     * @return array<string, array{path?:string, width?:int|null, height?:int|null, mime?:string|null}>
     */
    protected function normalizeVariants(?array $variants): array
    {
        if (! $variants) {
            return [];
        }

        $normalized = [];

        foreach ($variants as $name => $variant) {
            if (is_string($variant)) {
                $normalized[(string) $name] = ['path' => $variant];
                continue;
            }

            if (is_array($variant)) {
                $normalized[(string) $name] = $variant;
            }
        }

        return $normalized;
    }

    /**
     * @param list<string> $paths
     * @param list<string> $except
     */
    public function deletePaths(array $paths, string $disk, array $except = []): void
    {
        $except = array_flip($except);

        foreach (array_values(array_unique($paths)) as $path) {
            if ($path === '' || isset($except[$path]) || $this->isExternalPath($path)) {
                continue;
            }

            try {
                $storage = Storage::disk($disk);

                if ($storage->exists($path)) {
                    $storage->delete($path);
                }
            } catch (Throwable) {
                // Deleting an old derivative should not fail the parent save.
            }
        }
    }
}
