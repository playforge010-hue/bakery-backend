<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Serve approved public uploads independently of a public/storage symlink. */
class PublicImageController extends Controller
{
    public function show(string $path): StreamedResponse
    {
        $publicDirectories = [
            config('imagepath.directories.logo'),
            config('imagepath.business.cover'),
            config('imagepath.directories.banners'),
            config('imagepath.directories.categories'),
            config('imagepath.directories.products'),
            config('imagepath.directories.reviews'),
            config('imagepath.directories.user_avatars'),
        ];

        $isPublicImage = Str::startsWith(
            $path,
            array_map(
                fn (string $directory): string => trim($directory, '/').'/',
                $publicDirectories
            )
        );

        if (! $isPublicImage || ! preg_match('/\A[a-zA-Z0-9_\/-]+\.(?:jpe?g|png|webp|gif)\z/i', $path)) {
            abort(404);
        }

        $disk = Storage::disk(config('imagepath.disk', 'public'));

        if (! $disk->exists($path)) {
            abort(404);
        }

        return $disk->response($path, null, [
            'Cache-Control' => 'public, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
