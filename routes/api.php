<?php

use App\Http\Controllers\AdminProductController;
use App\Http\Controllers\AdminSourcingRequestController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProcurementListController;
use App\Http\Controllers\ProductFromLinkController;
use App\Http\Controllers\SourcingRequestController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});

Route::get('/categories', [CatalogController::class, 'categories']);
Route::get('/products', [CatalogController::class, 'products']);
Route::get('/products/{product}', [CatalogController::class, 'show']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/dashboard', DashboardController::class);

    Route::get('/procurement-list', [ProcurementListController::class, 'index']);
    Route::post('/procurement-list', [ProcurementListController::class, 'store']);
    Route::patch('/procurement-list/{procurementListItem}', [ProcurementListController::class, 'update']);
    Route::delete('/procurement-list/{procurementListItem}', [ProcurementListController::class, 'destroy']);

    Route::get('/sourcing-requests', [SourcingRequestController::class, 'index']);
    Route::post('/sourcing-requests', [SourcingRequestController::class, 'store']);

    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::get('/product-stats', [AdminProductController::class, 'stats']);
        Route::get('/products', [AdminProductController::class, 'index']);
        Route::get('/products/from-link', [ProductFromLinkController::class, 'show']);
        Route::post('/products', [AdminProductController::class, 'store']);
        Route::patch('/products/{product}', [AdminProductController::class, 'update']);
        Route::delete('/products/{product}', [AdminProductController::class, 'destroy']);

        Route::get('/sourcing-requests', [AdminSourcingRequestController::class, 'index']);
        Route::patch('/sourcing-requests/{sourcingRequest}', [AdminSourcingRequestController::class, 'update']);
    });
});
