<?php

return [
    'disk' => env('IMAGE_DISK', 'public'),
    'base_url' => env('IMAGE_BASE_URL', env('APP_URL').'/storage'),
    'public_base_url' => env('IMAGE_BASE_URL'),
    'bundled_catalog' => [
        'prefix' => 'demo-catalog/',
        'base_url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/demo-catalog',
    ],
    'business' => [
        'logo' => 'bakery/logo',
        'cover' => 'bakery/cover',
    ],
    'directories' => [
        'logo' => 'bakery/logo',
        'banners' => 'bakery/banners',
        'categories' => 'catalog/categories',
        'products' => 'catalog/products',
        'product_gallery' => 'catalog/products/gallery',
        'custom_order_uploads' => 'orders/customizations',
        'product_custom_uploads' => 'orders/customizations',
        'reviews' => 'reviews',
        'user_avatars' => 'users/avatars',
        'support' => 'support/messages',
    ],
    'support' => [
        'disk' => env('SUPPORT_IMAGE_DISK', 'local'),
        'directory' => 'support/messages',
    ],
    'defaults' => [
        'business' => ['logo' => null, 'cover' => null],
        'category' => ['image' => null],
        'product' => ['main_image' => null],
        'banner' => ['image' => null],
        'review' => ['image' => null],
        'payment' => ['proof' => null],
        'user' => ['avatar' => null],
    ],
];
