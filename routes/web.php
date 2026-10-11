<?php

use App\Http\Controllers\PublicImageController;
use Illuminate\Support\Facades\Route;

Route::get('/media/{path}', [PublicImageController::class, 'show'])
    ->where('path', '.*');

Route::get('/', function () {
    return response()->json(['service' => 'Dwarkesh Bakers API', 'status' => 'ready']);
});
