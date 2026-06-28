<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class MediaUrl
{
    public static function make(?string $path, ?string $disk = null): ?string
    {
        if (! $path) {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://', '/'])) {
            return $path;
        }

        $disk ??= (string) config('filesystems.uploads_disk', 'public');

        if ($disk === 's3' && blank(config('filesystems.disks.s3.url'))) {
            try {
                return Storage::disk($disk)->temporaryUrl(
                    $path,
                    now()->addMinutes(self::temporaryUrlMinutes())
                );
            } catch (Throwable) {
                // Fall through to the normal URL builder for S3-compatible setups
                // that do not support temporaryUrl.
            }
        }

        return Storage::disk($disk)->url($path);
    }

    protected static function temporaryUrlMinutes(): int
    {
        return max(
            1,
            min(10080, (int) config('filesystems.temporary_url_minutes', 1440))
        );
    }
}
