<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MigrateUploadsToS3CommandTest extends TestCase
{
    public function test_it_uploads_image_files_and_deletes_verified_local_copies(): void
    {
        Config::set('filesystems.uploads_disk', 's3');
        Storage::fake('public');
        Storage::fake('s3');

        Storage::disk('public')->put('products/101/photo.jpg', 'product image');
        Storage::disk('public')->put('brands/logo.webp', 'brand logo');
        Storage::disk('public')->put('receipt.txt', 'not an image');

        $this
            ->artisan('storage:migrate-uploads-to-s3', ['--delete' => true])
            ->assertExitCode(0);

        Storage::disk('s3')->assertExists('products/101/photo.jpg');
        Storage::disk('s3')->assertExists('brands/logo.webp');
        Storage::disk('s3')->assertMissing('receipt.txt');

        Storage::disk('public')->assertMissing('products/101/photo.jpg');
        Storage::disk('public')->assertMissing('brands/logo.webp');
        Storage::disk('public')->assertExists('receipt.txt');
    }

    public function test_dry_run_does_not_upload_or_delete_files(): void
    {
        Config::set('filesystems.uploads_disk', 's3');
        Storage::fake('public');
        Storage::fake('s3');

        Storage::disk('public')->put('categories/banner.png', 'category banner');

        $this
            ->artisan('storage:migrate-uploads-to-s3', ['--dry-run' => true])
            ->assertExitCode(0);

        Storage::disk('s3')->assertMissing('categories/banner.png');
        Storage::disk('public')->assertExists('categories/banner.png');
    }
}
