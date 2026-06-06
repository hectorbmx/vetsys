<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\TenantsController;
use App\Http\Controllers\Admin\ConfiguracionController;
use App\Http\Controllers\Admin\PlanesController;
use App\Http\Controllers\Admin\ReportesController;
use App\Http\Controllers\Admin\NotificationController as AdminNotificationController;


use App\Http\Controllers\Auth\InvitationController;

use App\Http\Controllers\Auth\ActivationController;

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\PublicNotePaymentController;

use App\Http\Controllers\Client\DashboardController as ClientDashboardController;
use App\Http\Controllers\Client\CustomerController;
use App\Http\Controllers\Client\AnimalController;
use App\Http\Controllers\Client\ConfiguracionController as ClientConfiguracionController;
use App\Http\Controllers\Client\PaymentMethodController;
use App\Http\Controllers\Client\CatalogItemController;
use App\Http\Controllers\Client\NoteController;
use App\Http\Controllers\Client\ProfileController;

use App\Http\Controllers\Client\PaymentController;
use App\Http\Controllers\Client\StatementController;
use App\Http\Controllers\Client\ClubController;
use App\Http\Controllers\Client\VaccinationLetterController;
use App\Http\Controllers\Client\AnimalVideoController;
use App\Http\Controllers\Client\RadiologyController;
use App\Http\Controllers\Client\TelemedicineController;
use App\Http\Controllers\Client\NotificationController;
/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/
Route::get('/invitation/{token}', [InvitationController::class, 'show'])->name('invitation.accept');

Route::post('/invitation/{token}', [InvitationController::class, 'store'])->name('invitation.store');

Route::get('/activar-cuenta', [ActivationController::class, 'show'])->name('activation.show');

Route::get('/activar-cuenta/{token}', [ActivationController::class, 'show'])->name('activation.link');

Route::post('/activar-cuenta', [ActivationController::class, 'store'])->name('activation.store');

$redirectAuthenticatedUser = function () {
    $user = auth()->user();

    if ($user->hasRole('super-admin')) {
        return redirect()->route('admin.dashboard');
    }

    if ($user->tenant_id) {
        return redirect()->route('client.dashboard');
    }

    auth()->logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return redirect()->route('login');
};

Route::get('/', function () use ($redirectAuthenticatedUser) {
    return auth()->check()
        ? $redirectAuthenticatedUser()
        : redirect()->route('login');
});

Route::get('/login', function () use ($redirectAuthenticatedUser) {
    return auth()->check()
        ? $redirectAuthenticatedUser()
        : view('login');
})->name('login');

Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
Route::post('/login', [LoginController::class, 'store'])->name('login.store');
Route::post('/stripe/webhook', StripeWebhookController::class)->name('stripe.webhook');
Route::get('/pagar/{token}', [PublicNotePaymentController::class, 'show'])->name('public.payments.show');
Route::post('/pagar/{token}/stripe', [PublicNotePaymentController::class, 'checkout'])->name('public.payments.checkout');
Route::get('/cartas-vacunacion/{vaccinationLetter}/pdf', [VaccinationLetterController::class, 'signedPrint'])
    ->name('public.vaccination-letters.print');

Route::middleware(['auth', 'role:super-admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {

        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/notificaciones', [AdminNotificationController::class, 'index'])->name('notifications.index');
        Route::get('/notificaciones/{notification}', [AdminNotificationController::class, 'open'])->name('notifications.open');
        Route::patch('/notificaciones/{notification}/leer', [AdminNotificationController::class, 'markRead'])->name('notifications.mark-read');
        Route::resource('tenants', TenantsController::class);
        Route::post('/tenants/{tenant}/resend-activation-code', [TenantsController::class, 'resendTenantActivationCode'])->name('tenants.resend-activation-code');
        Route::post('/tenants/{tenant}/users', [TenantsController::class, 'storeUser'])->name('tenants.users.store');
        Route::post('/tenants/{tenant}/users/{user}/resend-activation-code', [TenantsController::class, 'resendActivationCode'])->name('tenants.users.resend-activation-code');
        Route::post('/planes/{plan}/sync-stripe', [PlanesController::class, 'syncStripe'])->name('planes.sync-stripe');
        Route::resource('planes', PlanesController::class);
        Route::get('/reportes', [ReportesController::class, 'index'])->name('reportes.index');
        Route::get('/configuracion', [ConfiguracionController::class, 'index'])->name('configuracion.index');
        //asigna plan a tenant
        Route::post('/tenants/{tenant}/assign-plan', [TenantsController::class, 'assignPlan'])->name('tenants.assign-plan');
        Route::post('/tenants/{tenant}/stripe-checkout-link', [TenantsController::class, 'stripeCheckoutLink'])->name('tenants.stripe-checkout-link');

    });

    Route::middleware(['auth','tenant.plan', 'check.tenant.subscription'])
        ->prefix('client')
        ->name('client.')
        ->group(function () {
            Route::get('/dashboard', [ClientDashboardController::class, 'index'])->name('dashboard');
            Route::get('/profile', [ProfileController::class, 'index'])->name('profile.index');
            Route::get('/notificaciones', [NotificationController::class, 'index'])->name('notifications.index');
            Route::get('/notificaciones/{notification}', [NotificationController::class, 'open'])->name('notifications.open');
            Route::patch('/notificaciones/{notification}/leer', [NotificationController::class, 'markRead'])->name('notifications.mark-read');
            /*
            |--------------------------------------------------------------------------
            | CUSTOMERS / ANIMALS
            |--------------------------------------------------------------------------
            |*/
            Route::resource('customers', CustomerController::class);
            Route::get('vaccination-letters/{vaccinationLetter}', [VaccinationLetterController::class, 'show'])->name('vaccination-letters.show');
            Route::get('vaccination-letters/{vaccinationLetter}/print', [VaccinationLetterController::class, 'print'])->name('vaccination-letters.print');
            Route::post('animals/{animal}/vaccination-letters', [VaccinationLetterController::class, 'store'])->name('animals.vaccination-letters.store');
            Route::post('animals/{animal}/videos', [AnimalVideoController::class, 'store'])->name('animals.videos.store');
            Route::get('animal-videos/{animalVideo}', [AnimalVideoController::class, 'show'])->name('animal-videos.show');
            Route::delete('animal-videos/{animalVideo}', [AnimalVideoController::class, 'destroy'])->name('animal-videos.destroy');
            Route::post('animals/{animal}/radiology-studies', [RadiologyController::class, 'storeStudy'])->name('animals.radiology-studies.store');
            Route::post('radiology-studies/{radiologyStudy}/images', [RadiologyController::class, 'storeImages'])->name('radiology-studies.images.store');
            Route::delete('radiology-studies/{radiologyStudy}', [RadiologyController::class, 'destroyStudy'])->name('radiology-studies.destroy');
            Route::get('radiology-images/{radiologyImage}', [RadiologyController::class, 'showImage'])->name('radiology-images.show');
            Route::delete('radiology-images/{radiologyImage}', [RadiologyController::class, 'destroyImage'])->name('radiology-images.destroy');
            Route::post('animals/{animal}/telemedicine-shares', [TelemedicineController::class, 'store'])->name('animals.telemedicine-shares.store');
            Route::delete('telemedicine-shares/{animalShare}', [TelemedicineController::class, 'destroy'])->name('telemedicine-shares.destroy');
            Route::get('api/telemedicina/tenants', [TelemedicineController::class, 'searchTenants'])->name('api.telemedicine.tenants');
            Route::get('telemedicina/expedientes/{token}', [TelemedicineController::class, 'show'])->name('telemedicine.animals.show');
            Route::get('telemedicina/expedientes/{token}/cartas/{vaccinationLetter}', [TelemedicineController::class, 'letter'])->name('telemedicine.vaccination-letters.show');
            Route::get('telemedicina/expedientes/{token}/videos/{animalVideo}', [TelemedicineController::class, 'video'])->name('telemedicine.animal-videos.show');
            Route::get('telemedicina/expedientes/{token}/radiologia/{radiologyImage}', [TelemedicineController::class, 'radiologyImage'])->name('telemedicine.radiology-images.show');
            Route::resource('animals', AnimalController::class);
            Route::patch('clubes/{club}/members', [ClubController::class, 'updateMembers'])->name('clubes.members.update');
            Route::resource('clubes', ClubController::class);

            /*
            |--------------------------------------------------------------------------
            | SERVICIOS Y PRODUCTOS (CATÁLOGO)
            |--------------------------------------------------------------------------
            |*/
            Route::get('servicios', [CatalogItemController::class, 'index'])->name('servicios.index');
            Route::post('servicios', [CatalogItemController::class, 'store'])->name('servicios.store');
            Route::put('servicios/{catalogItem}', [CatalogItemController::class, 'update'])->name('servicios.update');
            Route::patch('servicios/{catalogItem}/price', [CatalogItemController::class, 'updatePrice'])->name('servicios.update-price');
            Route::patch('servicios/{catalogItem}/toggle', [CatalogItemController::class, 'toggleStatus'])->name('servicios.toggle');

            /*
            |--------------------------------------------------------------------------
            | MI CONFIGURACION
            |--------------------------------------------------------------------------
            |*/
            Route::get('mi-configuracion', [ClientConfiguracionController::class, 'index'])->name('mi-configuracion.index');
            Route::post('mi-configuracion', [ClientConfiguracionController::class, 'store'])->name('mi-configuracion.store');
            Route::post('mi-configuracion/users', [ClientConfiguracionController::class, 'storeUser'])->name('mi-configuracion.users.store');
            Route::post('mi-configuracion/plan', [ClientConfiguracionController::class, 'requestPlanChange'])->name('mi-configuracion.plan.request');
            Route::post('mi-configuracion/importar-clientes', [ClientConfiguracionController::class, 'importCustomers'])->name('mi-configuracion.import-customers');
            Route::post('mi-configuracion/importar-servicios', [ClientConfiguracionController::class, 'importServices'])->name('mi-configuracion.import-services');
            Route::post('mi-configuracion/importar-caballos', [ClientConfiguracionController::class, 'importHorses'])->name('mi-configuracion.import-horses');
            Route::post('mi-configuracion/plan/stripe-checkout', [ClientConfiguracionController::class, 'stripeCheckout']) ->name('mi-configuracion.plan.stripe-checkout');
            /*
            |--------------------------------------------------------------------------
            | MI CONFIGURACION > FIELDS (CAMPOS DINÁMICOS)
            |--------------------------------------------------------------------------
            |*/
            Route::get('mi-configuracion/{animalType}/campos', [ClientConfiguracionController::class, 'fieldsIndex'])->name('mi-configuracion.fields.index');
            Route::post('mi-configuracion/{animalType}/campos', [ClientConfiguracionController::class, 'fieldsStore'])->name('mi-configuracion.fields.store');

            /*
            |--------------------------------------------------------------------------
            | CONFIGURACION > METODOS DE PAGO
            |--------------------------------------------------------------------------
            |*/
            Route::post('/payment-methods', [PaymentMethodController::class, 'store'])->name('payment-methods.store');
            Route::patch('/payment-methods/{paymentMethod}/toggle', [PaymentMethodController::class, 'toggleStatus'])->name('payment-methods.toggle');

            /*
            |--------------------------------------------------------------------------
            | VENTAS Y PUNTOS DE VENTA (POS)
            |--------------------------------------------------------------------------
            |*/
            Route::get('ventas', [NoteController::class, 'index'])->name('ventas.index');
            Route::get('ventas/crear', [NoteController::class, 'create'])->name('ventas.create');
            Route::post('ventas', [NoteController::class, 'store'])->name('ventas.store');
            Route::get('ventas/{note}', [NoteController::class, 'show'])->name('ventas.show');
            Route::post('ventas/{note}/stripe-payment-link', [NoteController::class, 'createStripePaymentLink'])->name('ventas.stripe-payment-link');
            Route::post('ventas/{note}/manual-payment', [NoteController::class, 'storeManualPayment'])->name('ventas.manual-payment');

            // Endpoints de búsqueda predictiva para Alpine.js (Buscadores)
            Route::get('api/buscar-clientes', [NoteController::class, 'searchCustomers'])->name('api.buscar-clientes');
            Route::get('api/buscar-articulos', [NoteController::class, 'searchItems'])->name('api.buscar-articulos');

            //PAGOS A NMOTAS DE CLIENTES
            
            Route::post('customers/{customer}/payments', [PaymentController::class, 'store'])->name('customers.payments.store');
            Route::get('customers/{customer}/payments/preview', [PaymentController::class, 'preview'])->name('customers.payments.preview');
            Route::get('customers/{customer}/statement', [StatementController::class, 'generate'])->name('customers.statement.generate');
            Route::patch('customers/{customer}/account-settings', [CustomerController::class, 'updateAccountSettings'])->name('customers.account-settings.update');
            Route::post('customers/{customer}/statements', [StatementController::class, 'storeGenerated'])->name('customers.statements.store');
            Route::get('customers/{customer}/statements/{statement}/pdf', [StatementController::class, 'showStored'])->name('customers.statements.pdf');
        });
