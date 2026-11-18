<?php

use App\Http\Controllers\Api\PosApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes  (prefix: /api)
|--------------------------------------------------------------------------
| Authenticate with an API key:  X-API-KEY: gc_xxxxx
| Create one with:               php artisan api-key generate "name"
*/

// Public health check
Route::get('/v1/health', fn () => ['status' => 'ok', 'time' => now()->toIso8601String()]);

Route::prefix('v1')->middleware('api.key')->group(function () {
    Route::get('/summary', [PosApiController::class, 'summary']);

    Route::get('/products', [PosApiController::class, 'products']);
    Route::get('/products/{id}', [PosApiController::class, 'product'])->whereNumber('id');

    Route::get('/categories', [PosApiController::class, 'categories']);

    Route::get('/customers', [PosApiController::class, 'customers']);
    Route::get('/customers/{id}', [PosApiController::class, 'customer'])->whereNumber('id');

    Route::get('/sales', [PosApiController::class, 'sales']);
    Route::get('/sales/{id}', [PosApiController::class, 'sale'])->whereNumber('id');
});
