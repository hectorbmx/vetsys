<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AnimalController;
use App\Http\Controllers\Api\V1\AnimalTypeController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CatalogItemController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\MobileBootstrapController;
use App\Http\Controllers\Api\V1\NoteController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\SyncController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::prefix('v1')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:5,1');

    Route::middleware(['auth:sanctum', 'api.tenant'])->group(function () {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/mobile/bootstrap', MobileBootstrapController::class);
        Route::apiResource('customers', CustomerController::class)
            ->except(['destroy']);
        Route::apiResource('animals', AnimalController::class)
            ->except(['destroy']);
        Route::get('/animal-types', [AnimalTypeController::class, 'index']);
        Route::apiResource('catalog-items', CatalogItemController::class)
            ->only(['index', 'store', 'show']);
        Route::apiResource('notes', NoteController::class)
            ->only(['index', 'store', 'show']);
        Route::post('/notes/{note}/payment-links', [NoteController::class, 'createPaymentLink']);
        Route::post('/notes/{note}/manual-payment', [NoteController::class, 'storeManualPayment']);
        Route::get('/customers/{customer}/payments/preview', [PaymentController::class, 'preview']);
        Route::apiResource('payments', PaymentController::class)
            ->only(['index', 'store', 'show']);
        Route::post('/sync/push', [SyncController::class, 'push']);
    });
});
