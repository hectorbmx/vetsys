<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AnimalController;
use App\Http\Controllers\Api\V1\AnimalClinicalMediaController;
use App\Http\Controllers\Api\V1\AnimalTypeController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CatalogItemController;
use App\Http\Controllers\Api\V1\ClubController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\CustomerPortalController;
use App\Http\Controllers\Api\V1\MobileBootstrapController;
use App\Http\Controllers\Api\V1\NoteController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\PaymentMethodController;
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

    Route::middleware(['auth:sanctum', 'access.mobile', 'api.tenant'])->group(function () {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        Route::middleware('customer.portal')
            ->prefix('portal')
            ->name('api.v1.portal.')
            ->group(function () {
                Route::get('/bootstrap', [CustomerPortalController::class, 'bootstrap']);
                Route::get('/me', [CustomerPortalController::class, 'me']);
                Route::get('/patients', [CustomerPortalController::class, 'patients']);
                Route::get('/notes/{note}', [CustomerPortalController::class, 'note']);
                Route::get('/statements', [CustomerPortalController::class, 'statements']);
                Route::get('/statements/{statement}/pdf', [CustomerPortalController::class, 'statementPdf'])->name('statements.pdf');
                Route::get('/notifications', [CustomerPortalController::class, 'notifications']);
                Route::patch('/notifications/read-all', [CustomerPortalController::class, 'markAllNotificationsRead']);
                Route::patch('/notifications/{notification}/read', [CustomerPortalController::class, 'markNotificationRead']);
                Route::get('/patients/{patient}/notes', [CustomerPortalController::class, 'patientNotes']);
                Route::get('/patients/{patient}/history', [CustomerPortalController::class, 'patientHistory']);
                Route::get('/patients/{patient}/videos', [CustomerPortalController::class, 'patientVideos']);
                Route::get('/patients/{patient}/radiology', [CustomerPortalController::class, 'patientRadiology']);
                Route::get('/patients/{patient}/vaccines', [CustomerPortalController::class, 'patientVaccines']);
                Route::get('/vaccination-letters/{vaccinationLetter}/pdf', [CustomerPortalController::class, 'vaccinationLetterPdf']);
                Route::get('/patients/{patient}', [CustomerPortalController::class, 'patient']);
            });

        Route::get('/mobile/bootstrap', MobileBootstrapController::class);
        Route::apiResource('customers', CustomerController::class)
            ->except(['destroy']);
        Route::apiResource('animals', AnimalController::class)
            ->except(['destroy']);
        Route::get('/animal-types', [AnimalTypeController::class, 'index']);
        Route::get('/clubs', [ClubController::class, 'index']);
        Route::get('/payment-methods', [PaymentMethodController::class, 'index']);
        Route::get('/animals/{animal}/clinical-media', [AnimalClinicalMediaController::class, 'index']);
        Route::post('/animals/{animal}/microchip', [AnimalClinicalMediaController::class, 'storeMicrochip']);
        Route::post('/animals/{animal}/reports', [AnimalClinicalMediaController::class, 'storeReport']);
        Route::post('/animal-reports/{animalReport}', [AnimalClinicalMediaController::class, 'updateReport']);
        Route::delete('/animal-reports/{animalReport}', [AnimalClinicalMediaController::class, 'destroyReport']);
        Route::post('/animals/{animal}/vaccination-letters', [AnimalClinicalMediaController::class, 'storeVaccination']);
        Route::get('/vaccination-letters/{vaccinationLetter}/share-link', [AnimalClinicalMediaController::class, 'vaccinationShareLink']);
        Route::post('/animals/{animal}/videos', [AnimalClinicalMediaController::class, 'storeVideo']);
        Route::post('/animals/{animal}/radiology-studies', [AnimalClinicalMediaController::class, 'storeRadiologyStudy']);
        Route::post('/radiology-studies/{radiologyStudy}/images', [AnimalClinicalMediaController::class, 'storeRadiologyImages']);
        Route::get('/telemedicine/tenants', [AnimalClinicalMediaController::class, 'searchTenants']);
        Route::post('/animals/{animal}/telemedicine-shares', [AnimalClinicalMediaController::class, 'share']);
        Route::apiResource('catalog-items', CatalogItemController::class)
            ->only(['index', 'store', 'show', 'update']);
        Route::apiResource('notes', NoteController::class)
            ->only(['index', 'store', 'show']);
        Route::post('/notes/{note}/payment-links', [NoteController::class, 'createPaymentLink']);
        Route::post('/notes/{note}/manual-payment', [NoteController::class, 'storeManualPayment']);
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::patch('/notifications/read-all', [NotificationController::class, 'markAllRead']);
        Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markRead']);
        Route::get('/customers/{customer}/payments/preview', [PaymentController::class, 'preview']);
        Route::post('/customers/{customer}/payment-links', [PaymentController::class, 'createPaymentLink']);
        Route::apiResource('payments', PaymentController::class)
            ->only(['index', 'store', 'show']);
        Route::post('/sync/push', [SyncController::class, 'push']);
    });
});
