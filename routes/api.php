<?php

use App\Http\Controllers\Api\ApiController;
use App\Http\Controllers\Api\ClientApiController;
use App\Http\Controllers\Api\InvoiceApiController;
use App\Http\Controllers\Api\ProductApiController;
use App\Http\Controllers\Api\StockApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — A3 ERP
|--------------------------------------------------------------------------
|
| Base URL  : /api
| Auth      : Bearer token (Laravel Sanctum)
|             POST /api/auth/token  { email, password, device_name }
|
| Rate limit: 60 req/min per token (Laravel default)
|
*/

// ── Authentication ────────────────────────────────────────────────────────────
// [SEC-API] Rate-limited à 5 tentatives/min par IP — protection brute-force.
Route::post('/auth/token', [ApiController::class, 'token'])
    ->middleware('throttle:api_auth')
    ->name('api.token');

// ── Protected endpoints ───────────────────────────────────────────────────────
// [SEC-API] throttle:api = 60 req/min/user (défini dans AppServiceProvider).
Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {

    Route::delete('/auth/token', [ApiController::class, 'revoke'])->name('api.revoke');

    // [SEC-API] Application stricte des permissions : le token hérite des
    // droits de son utilisateur — un opérateur sans clients.view ne lit pas
    // les clients via l'API. `can:` s'appuie sur Gate (permissions Spatie +
    // bypass super_admin), compatible auth:sanctum.

    // Produits
    Route::middleware('can:products.view')->group(function () {
        Route::get('/products',           [ProductApiController::class, 'index'])->name('api.products.index');
        // /search MUST be declared before /{product} — otherwise "search" is treated as a product ID.
        Route::get('/products/search',    [ProductApiController::class, 'search'])->name('api.products.search');
        Route::get('/products/{product}', [ProductApiController::class, 'show'])->name('api.products.show');
    });

    // Clients
    Route::middleware('can:clients.view')->group(function () {
        Route::get('/clients',            [ClientApiController::class, 'index'])->name('api.clients.index');
        Route::get('/clients/{client}',   [ClientApiController::class, 'show'])->name('api.clients.show');
    });

    // Factures clients
    Route::middleware('can:invoices.view')->group(function () {
        Route::get('/invoices',           [InvoiceApiController::class, 'index'])->name('api.invoices.index');
        Route::get('/invoices/{invoice}', [InvoiceApiController::class, 'show'])->name('api.invoices.show');
    });

    // Stock
    Route::middleware('can:stocks.view')->group(function () {
        Route::get('/stock',              [StockApiController::class, 'index'])->name('api.stock.index');
        Route::get('/stock/movements',    [StockApiController::class, 'movements'])->name('api.stock.movements');
    });
});
