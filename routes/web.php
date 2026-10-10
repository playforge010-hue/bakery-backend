<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json(['service' => 'Dwarkesh Bakers API', 'status' => 'ready']);
});
