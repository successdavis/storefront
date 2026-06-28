<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class MigrateUploadsToS3 extends Command
{
    protected $signature = 'storage:migrate-uploads-to-s3
        {--source=public : Source filesystem disk containing current server uploads}
        {--target= : Target filesystem disk, defaults to filesystems.uploads_disk}
        {--path=* : Optional path prefix to limit migration, for example products or brands}
        {--delete : Delete each local source file after S3 upload and verification}
        {--dry-run : Show what would be migrated without writing or deleting files}
        {--force : Required for non-dry production runs}';

    protected $description = 'Move existing local upload images and logos to the configured S3 bucket.';

    /** @var list<string> */
    protected array $imageExtensions = [
        'avif',
        'bmp',
        'gif',
        'jpeg',
        'jpg',
        'png',
        'svg',
        'webp',
    ];

    public function handle(): int
    {
        $source = (string) $this->option('source');
        $target = (string) ($this->option('target') ?: config('filesystems.uploads_disk', 's3'));
        $delete = (bool) $this->option('delete');
        $dryRun = (bool) $this->option('dry-run');

        if ($source === $target) {
            $this->error('Source and target disks must be different.');

            return self::FAILURE;
        }

        if (! $dryRun && app()->environment('production') && ! $this->option('force')) {
            $this->error('Production migrations require --force. Run with --dry-run first to preview.');

            return self::FAILURE;
        }

        $sourceDisk = Storage::disk($source);
        $targetDisk = Storage::disk($target);
        $paths = $this->candidatePaths($source);
        $referencedPaths = $this->referencedMediaPaths();

        if ($paths->isEmpty()) {
            $this->info("No image files found on the [{$source}] disk.");

            return self::SUCCESS;
        }

        $this->info(sprintf(
            '%s %d image file(s) from [%s] to [%s]%s.',
            $dryRun ? 'Would migrate' : 'Migrating',
            $paths->count(),
            $source,
            $target,
            $delete ? ' and delete local copies after verification' : ''
        ));

        $stats = [
            'uploaded' => 0,
            'deleted' => 0,
            'failed' => 0,
            'referenced' => 0,
        ];

        $bar = $this->output->createProgressBar($paths->count());
        $bar->start();

        foreach ($paths as $path) {
            if ($referencedPaths->contains($path)) {
                $stats['referenced']++;
            }

            if ($dryRun) {
                $bar->advance();
                continue;
            }

            try {
                $this->copyAndVerify($sourceDisk, $targetDisk, $path);
                $stats['uploaded']++;

                if ($delete) {
                    $sourceDisk->delete($path);

                    if ($sourceDisk->exists($path)) {
                        throw new \RuntimeException("Uploaded but could not delete local file [{$path}].");
                    }

                    $stats['deleted']++;
                }
            } catch (Throwable $e) {
                $stats['failed']++;
                $this->newLine();
                $this->error("{$path}: {$e->getMessage()}");
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->line("Referenced by media tables/settings: {$stats['referenced']}");
        $this->line("Uploaded or verified on target: {$stats['uploaded']}");
        $this->line("Deleted from source: {$stats['deleted']}");
        $this->line("Failures: {$stats['failed']}");

        if ($dryRun) {
            $this->warn('Dry run only. Re-run without --dry-run to upload files.');
        } elseif (! $delete) {
            $this->warn('Local files were kept. Re-run with --delete to remove them after verification.');
        }

        return $stats['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    protected function copyAndVerify($sourceDisk, $targetDisk, string $path): void
    {
        $stream = $sourceDisk->readStream($path);

        if ($stream === false) {
            throw new \RuntimeException("Unable to read local file [{$path}].");
        }

        try {
            if (! $targetDisk->put($path, $stream)) {
                throw new \RuntimeException("Unable to upload [{$path}] to target disk.");
            }
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        if (! $targetDisk->exists($path)) {
            throw new \RuntimeException("Target object [{$path}] was not found after upload.");
        }

        $sourceSize = $this->size($sourceDisk, $path);
        $targetSize = $this->size($targetDisk, $path);

        if ($sourceSize !== null && $targetSize !== null && $sourceSize !== $targetSize) {
            throw new \RuntimeException("Size mismatch after uploading [{$path}].");
        }
    }

    protected function candidatePaths(string $source): Collection
    {
        $prefixes = collect((array) $this->option('path'))
            ->map(fn (string $path): string => trim($path, '/'))
            ->filter()
            ->values();

        $paths = $prefixes->isEmpty()
            ? collect(Storage::disk($source)->allFiles())
            : $prefixes->flatMap(fn (string $prefix): array => Storage::disk($source)->allFiles($prefix));

        return $paths
            ->map(fn (string $path): string => $this->normalizePath($path))
            ->filter(fn (string $path): bool => $this->isImagePath($path))
            ->unique()
            ->sort()
            ->values();
    }

    protected function referencedMediaPaths(): Collection
    {
        return collect()
            ->merge($this->tableColumnPaths('product_images', 'path'))
            ->merge($this->tableColumnPaths('variant_images', 'path'))
            ->merge($this->tableColumnPaths('brands', 'logo'))
            ->merge($this->tableColumnPaths('categories', 'banner'))
            ->merge($this->tableColumnPaths('categories', 'icon'))
            ->merge($this->tableColumnPaths('categories', 'cover_image'))
            ->merge($this->businessLogoPaths())
            ->map(fn (string $path): string => $this->normalizePath($path))
            ->filter(fn (string $path): bool => $this->isImagePath($path))
            ->unique()
            ->values();
    }

    protected function tableColumnPaths(string $table, string $column): Collection
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return collect();
        }

        return DB::table($table)
            ->whereNotNull($column)
            ->where($column, '<>', '')
            ->pluck($column);
    }

    protected function businessLogoPaths(): Collection
    {
        if (! Schema::hasTable('settings')) {
            return collect();
        }

        return DB::table('settings')
            ->where('key', 'business_logo')
            ->whereNotNull('value')
            ->where('value', '<>', '')
            ->pluck('value');
    }

    protected function normalizePath(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));

        foreach (['/storage/', 'storage/', 'public/'] as $prefix) {
            if (Str::startsWith($path, $prefix)) {
                return ltrim(Str::after($path, $prefix), '/');
            }
        }

        return ltrim($path, '/');
    }

    protected function isImagePath(string $path): bool
    {
        if ($path === '' || Str::startsWith($path, ['http://', 'https://', 'data:'])) {
            return false;
        }

        return in_array(Str::lower(pathinfo($path, PATHINFO_EXTENSION)), $this->imageExtensions, true);
    }

    protected function size($disk, string $path): ?int
    {
        try {
            return (int) $disk->size($path);
        } catch (Throwable) {
            return null;
        }
    }
}
