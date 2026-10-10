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

        $configuredBaseUrl = config('imagepath.public_base_url');

        if ($configuredBaseUrl) {
            return rtrim($configuredBaseUrl, '/').'/'.ltrim($path, '/');
        }

        $disk = config('imagepath.disk');

        if (config("filesystems.disks.{$disk}.driver") === 'local' && ! app()->runningInConsole()) {
            $relativePath = Str::startsWith($path, 'storage/')
                ? Str::after($path, 'storage/')
                : ltrim($path, '/');

            return rtrim(request()->getSchemeAndHttpHost(), '/').'/storage/'.$relativePath;
        }

        return Storage::disk($disk)->url($path);
    }
}
