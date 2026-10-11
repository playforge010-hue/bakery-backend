<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class ImagePath
{
    public static function url(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (Str::startsWith($path, ['https://', 'http://', 'data:'])) {
            return $path;
        }

        $bundledCatalog = config('imagepath.bundled_catalog');

        if (Str::startsWith($path, $bundledCatalog['prefix'])) {
            $filename = Str::after($path, $bundledCatalog['prefix']);

            return rtrim($bundledCatalog['base_url'], '/').'/'.$filename;
        }

        $disk = config('imagepath.disk');
        $configuredBaseUrl = config('imagepath.public_base_url');
        $appUrl = rtrim(config('app.url'), '/');
        $isLocalDisk = config("filesystems.disks.{$disk}.driver") === 'local';

        if ($isLocalDisk && (! $configuredBaseUrl || rtrim($configuredBaseUrl, '/') === $appUrl.'/storage')) {
            return $appUrl.'/media/'.ltrim($path, '/');
        }

        if ($configuredBaseUrl) {
            return rtrim($configuredBaseUrl, '/').'/'.ltrim($path, '/');
        }

        return Storage::disk($disk)->url($path);
    }
}
