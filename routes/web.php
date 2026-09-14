<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

// The counter screen: a thin client over the JSON API in routes/api.php. Every
// total, tax and change figure it shows is computed by the server.
Route::view('/', 'billing');
