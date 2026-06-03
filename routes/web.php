<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\TenantsController;
use App\Http\Controllers\Admin\ConfiguracionController;
use App\Http\Controllers\Admin\PlanesController;
use App\Http\Controllers\Admin\ReportesController;


use App\Http\Controllers\Auth\InvitationController;

use App\Http\Controllers\Auth\LoginController;

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

Route::middleware(['auth', 'role:super-admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {

        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::resource('tenants', TenantsController::class);
        Route::post('/tenants/{tenant}/users', [TenantsController::class, 'storeUser'])->name('tenants.users.store');
        Route::post('/planes/{plan}/sync-stripe', [PlanesController::class, 'syncStripe'])->name('planes.sync-stripe');
        Route::resource('planes', PlanesController::class);
        Route::get('/reportes', [ReportesController::class, 'index'])->name('reportes.index');
        Route::get('/configuracion', [ConfiguracionController::class, 'index'])->name('configuracion.index');
        //asigna plan a tenant
        Route::post('/tenants/{tenant}/assign-plan', [TenantsController::class, 'assignPlan'])->name('tenants.assign-plan');

    });

    Route::middleware(['auth','tenant.plan', 'check.tenant.subscription'])
        ->prefix('client')
        ->name('client.')
        ->group(function () {
            Route::get('/dashboard', [ClientDashboardController::class, 'index'])->name('dashboard');
            Route::get('/profile', [ProfileController::class, 'index'])->name('profile.index');
            /*
            |--------------------------------------------------------------------------
            | CUSTOMERS / ANIMALS
            |--------------------------------------------------------------------------
            |*/
            Route::resource('customers', CustomerController::class);
            Route::resource('animals', AnimalController::class);

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
