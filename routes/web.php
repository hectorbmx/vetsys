<?php

use App\Http\Controllers\Admin\ConfiguracionController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\NotificationController as AdminNotificationController;
use App\Http\Controllers\Admin\PlanesController;
use App\Http\Controllers\Admin\ReportesController;
use App\Http\Controllers\Admin\TenantsController;
use App\Http\Controllers\Auth\ActivationController;
use App\Http\Controllers\Auth\InvitationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Client\AnimalController;
use App\Http\Controllers\Client\AnimalReportController;
use App\Http\Controllers\Client\AnimalVideoController;
use App\Http\Controllers\Client\AppointmentConfigurationController;
use App\Http\Controllers\Client\AppointmentController;
use App\Http\Controllers\Client\CatalogItemController;
use App\Http\Controllers\Client\ClubController;
use App\Http\Controllers\Client\ConfiguracionController as ClientConfiguracionController;
use App\Http\Controllers\Client\CustomerController;
use App\Http\Controllers\Client\DashboardController as ClientDashboardController;
use App\Http\Controllers\Client\FacturacionController;
use App\Http\Controllers\Client\NoteController;
use App\Http\Controllers\Client\NotificationController;
use App\Http\Controllers\Client\PaymentController;
use App\Http\Controllers\Client\PaymentMethodController;
use App\Http\Controllers\Client\ProfileController;
use App\Http\Controllers\Client\RadiologyController;
use App\Http\Controllers\Client\StatementController;
use App\Http\Controllers\Client\StripeConnectController;
use App\Http\Controllers\Client\TelemedicineController;
use App\Http\Controllers\Client\VaccinationLetterController;
use App\Http\Controllers\PublicCustomerPaymentController;
use App\Http\Controllers\PublicNotePaymentController;
use App\Http\Controllers\StripeWebhookController;
use App\Services\Auth\TenantSessionGuard;
use App\Services\Auth\TenantHomeRouteResolver;
use Illuminate\Support\Facades\Route;

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

Route::view('/soporte', 'public.app-store.support')->name('public.support');
Route::view('/marketing', 'public.app-store.marketing')->name('public.marketing');
Route::view('/derechos-de-autor', 'public.app-store.copyright')->name('public.copyright');
Route::view('/politica-de-privacidad', 'public.app-store.privacy')->name('public.privacy');

$redirectAuthenticatedUser = function () {
    $user = auth()->user();

    if ($user->hasRole('super-admin')) {
        return redirect()->route('admin.dashboard');
    }

    if ($user->hasRole('customer')) {
        auth()->logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('login')->withErrors([
            'email' => 'Tu acceso es exclusivo para la app movil del cliente.',
        ]);
    }

    if ($user->tenant_id) {
        $access = app(TenantSessionGuard::class)->canEnterBillingArea($user);

        if (($access['billing_limited'] ?? false) === true) {
            return redirect()->route('client.profile.index');
        }

        return redirect()->route(app(TenantHomeRouteResolver::class)->routeNameFor($user));
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
Route::get('/ticket/{token}', [NoteController::class, 'publicTicket'])->name('public.ventas.ticket');
Route::post('/pagar/{token}/stripe', [PublicNotePaymentController::class, 'checkout'])->name('public.payments.checkout');
Route::get('/pagar-cuenta/{token}', [PublicCustomerPaymentController::class, 'show'])->name('public.customer-payments.show');
Route::post('/pagar-cuenta/{token}/stripe', [PublicCustomerPaymentController::class, 'checkout'])->name('public.customer-payments.checkout');
Route::get('/cartas-vacunacion/{vaccinationLetter}/pdf', [VaccinationLetterController::class, 'signedPrint'])
    ->name('public.vaccination-letters.print');
Route::get('/cartas-vacunacion-publicas/{token}', [VaccinationLetterController::class, 'publicPrint'])
    ->where('token', '[A-Za-z0-9]{48}')
    ->name('public.vaccination-letters.share');
Route::get('/cartas-microchip/{token}', [AnimalController::class, 'publicMicrochipLetter'])
    ->whereUuid('token')
    ->name('public.microchip-letters.print');
Route::get('/reportes-clinicos/{token}', [AnimalReportController::class, 'publicPdf'])
    ->where('token', '[A-Za-z0-9]{48}')
    ->name('public.animal-reports.pdf');

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
        Route::patch('/tenants/{tenant}/users/{user}', [TenantsController::class, 'updateUser'])->name('tenants.users.update');
        Route::delete('/tenants/{tenant}/users/{user}', [TenantsController::class, 'destroyUser'])->name('tenants.users.destroy');
        Route::post('/tenants/{tenant}/users/{user}/resend-activation-code', [TenantsController::class, 'resendActivationCode'])->name('tenants.users.resend-activation-code');
        Route::post('/planes/{plan}/sync-stripe', [PlanesController::class, 'syncStripe'])->name('planes.sync-stripe');
        Route::resource('planes', PlanesController::class);
        Route::get('/reportes', [ReportesController::class, 'index'])->name('reportes.index');
        Route::get('/configuracion', [ConfiguracionController::class, 'index'])->name('configuracion.index');
        // asigna plan a tenant
        Route::post('/tenants/{tenant}/assign-plan', [TenantsController::class, 'assignPlan'])->name('tenants.assign-plan');
        Route::post('/tenants/{tenant}/stripe-checkout-link', [TenantsController::class, 'stripeCheckoutLink'])->name('tenants.stripe-checkout-link');
        Route::delete('/tenants/{tenant}/payments/clear-cancelled', [TenantsController::class, 'clearCancelledPayments'])->name('tenants.payments.clear-cancelled');
        Route::delete('/tenants/{tenant}/payments/{payment}', [TenantsController::class, 'destroyPayment'])->name('tenants.payments.destroy');
    });

Route::middleware(['auth', 'access.web', 'tenant.plan'])
    ->prefix('client')
    ->name('client.')
    ->group(function () {
        Route::get('/profile', [ProfileController::class, 'index'])->name('profile.index');
        Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::post('mi-configuracion/plan/stripe-checkout', [ClientConfiguracionController::class, 'stripeCheckout'])->name('mi-configuracion.plan.stripe-checkout');
    });

Route::middleware(['auth', 'access.web', 'tenant.plan', 'check.tenant.subscription'])
    ->prefix('client')
    ->name('client.')
    ->group(function () {
        Route::get('/dashboard', [ClientDashboardController::class, 'index'])->name('dashboard');
        Route::patch('/dashboard/onboarding-banner/dismiss', [ClientDashboardController::class, 'dismissOnboardingBanner'])
            ->name('dashboard.onboarding-banner.dismiss');
        Route::get('/notificaciones', [NotificationController::class, 'index'])->name('notifications.index');
        Route::get('/notificaciones/{notification}', [NotificationController::class, 'open'])->name('notifications.open');
        Route::patch('/notificaciones/{notification}/leer', [NotificationController::class, 'markRead'])->name('notifications.mark-read');
        Route::delete('/notificaciones/{notification}', [NotificationController::class, 'destroy'])->name('notifications.destroy');
        /*
        |--------------------------------------------------------------------------
        | CUSTOMERS / ANIMALS
        |--------------------------------------------------------------------------
        |*/
        Route::resource('customers', CustomerController::class);
        Route::patch('customers/{customer}/toggle', [CustomerController::class, 'toggleStatus'])->name('customers.toggle');
        Route::patch('customers/{customer}/portal-access', [CustomerController::class, 'togglePortalAccess'])->name('customers.portal-access.toggle');
        Route::patch('customers/{customer}/portal-animals', [CustomerController::class, 'updateAnimalPortalVisibility'])->name('customers.portal-animals.update');
        Route::get('vaccination-letters/{vaccinationLetter}', [VaccinationLetterController::class, 'show'])->name('vaccination-letters.show');
        Route::get('vaccination-letters/{vaccinationLetter}/print', [VaccinationLetterController::class, 'print'])->name('vaccination-letters.print');
        Route::post('animals/{animal}/vaccination-letters', [VaccinationLetterController::class, 'store'])->name('animals.vaccination-letters.store');
        Route::post('animals/{animal}/reports', [AnimalReportController::class, 'store'])->name('animals.reports.store');
        Route::get('animal-reports/{animalReport}/edit', [AnimalReportController::class, 'edit'])->name('animal-reports.edit');
        Route::put('animal-reports/{animalReport}', [AnimalReportController::class, 'update'])->name('animal-reports.update');
        Route::get('animal-reports/{animalReport}/pdf', [AnimalReportController::class, 'pdf'])->name('animal-reports.pdf');
        Route::delete('animal-reports/{animalReport}', [AnimalReportController::class, 'destroy'])->name('animal-reports.destroy');
        Route::get('animal-report-images/{animalReportImage}', [AnimalReportController::class, 'image'])->name('animal-report-images.show');
        Route::delete('animal-report-images/{animalReportImage}', [AnimalReportController::class, 'destroyImage'])->name('animal-report-images.destroy');
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
        Route::delete('animals/{animal}/microchip-image', [AnimalController::class, 'destroyMicrochipImage'])->name('animals.microchip-image.destroy');
        Route::patch('animals/{animal}/toggle', [AnimalController::class, 'toggleStatus'])->name('animals.toggle');
        Route::patch('animals/{animal}/portal-visibility', [AnimalController::class, 'togglePortalVisibility'])->name('animals.portal-visibility.toggle');
        Route::get('api/buscar-animales', [ClubController::class, 'searchAnimals'])->name('api.buscar-animales');
        Route::patch('clubes/{club}/members', [ClubController::class, 'updateMembers'])->name('clubes.members.update');
        Route::post('clubes/{club}/coggins', [ClubController::class, 'storeCoggin'])->name('clubes.coggins.store');
        Route::delete('clubes/{club}/coggins/{coggin}', [ClubController::class, 'destroyCoggin'])->name('clubes.coggins.destroy');
        Route::resource('clubes', ClubController::class);
        Route::patch('clubes/{club}/toggle', [ClubController::class, 'toggleStatus'])->name('clubes.toggle');

        /*
        |--------------------------------------------------------------------------
        | SERVICIOS Y PRODUCTOS (CATÁLOGO)
        |--------------------------------------------------------------------------
        |*/
        Route::get('servicios', [CatalogItemController::class, 'index'])->name('servicios.index');
        Route::post('servicios', [CatalogItemController::class, 'store'])->name('servicios.store');
        Route::get('servicios/inventario', [CatalogItemController::class, 'inventoryIndex'])->name('servicios.inventory');
        Route::get('servicios/{catalogItem}', [CatalogItemController::class, 'show'])->name('servicios.show');
        Route::put('servicios/{catalogItem}', [CatalogItemController::class, 'update'])->name('servicios.update');
        Route::patch('servicios/{catalogItem}/price', [CatalogItemController::class, 'updatePrice'])->name('servicios.update-price');
        Route::post('servicios/{catalogItem}/movements', [CatalogItemController::class, 'storeMovement'])->name('servicios.movements.store');
        Route::patch('servicios/{catalogItem}/toggle', [CatalogItemController::class, 'toggleStatus'])->name('servicios.toggle');
        Route::patch('servicios/{catalogItem}/negative-stock', [CatalogItemController::class, 'toggleNegativeStock'])->name('servicios.toggle-negative-stock');

        /*
        |--------------------------------------------------------------------------
        | MI CONFIGURACION
        |--------------------------------------------------------------------------
        |*/
        Route::get('mi-configuracion', [ClientConfiguracionController::class, 'index'])->name('mi-configuracion.index');
        Route::patch('mi-configuracion/agenda', [AppointmentConfigurationController::class, 'updateSettings'])->name('mi-configuracion.agenda.update');
        Route::post('mi-configuracion/agenda/horarios', [AppointmentConfigurationController::class, 'storeSchedule'])->name('mi-configuracion.agenda.schedules.store');
        Route::delete('mi-configuracion/agenda/horarios/{doctorSchedule}', [AppointmentConfigurationController::class, 'destroySchedule'])->name('mi-configuracion.agenda.schedules.destroy');
        Route::post('mi-configuracion/agenda/bloqueos', [AppointmentConfigurationController::class, 'storeBlock'])->name('mi-configuracion.agenda.blocks.store');
        Route::delete('mi-configuracion/agenda/bloqueos/{scheduleBlock}', [AppointmentConfigurationController::class, 'destroyBlock'])->name('mi-configuracion.agenda.blocks.destroy');
        Route::patch('mi-configuracion/agenda/servicios/{catalogItem}', [AppointmentConfigurationController::class, 'updateService'])->name('mi-configuracion.agenda.services.update');

        Route::prefix('agenda')->name('agenda.')->group(function () {
            Route::get('/', [AppointmentController::class, 'index'])->name('index');
            Route::get('/disponibilidad', [AppointmentController::class, 'availability'])->name('availability');
            Route::post('/manual', [AppointmentController::class, 'storeManual'])->name('manual.store');
            Route::get('/{appointment}', [AppointmentController::class, 'show'])->name('show');
            Route::post('/{appointment}/confirmar', [AppointmentController::class, 'confirm'])->name('confirm');
            Route::post('/{appointment}/rechazar', [AppointmentController::class, 'reject'])->name('reject');
            Route::post('/{appointment}/contrapropuesta', [AppointmentController::class, 'propose'])->name('propose');
            Route::post('/{appointment}/cancelar', [AppointmentController::class, 'cancel'])->name('cancel');
            Route::post('/{appointment}/completar', [AppointmentController::class, 'complete'])->name('complete');
            Route::post('/{appointment}/no-asistio', [AppointmentController::class, 'noShow'])->name('no-show');
        });
        Route::post('mi-configuracion', [ClientConfiguracionController::class, 'store'])->name('mi-configuracion.store');
        Route::post('mi-configuracion/stripe-connect', [StripeConnectController::class, 'connect'])->name('stripe-connect.connect');
        Route::get('mi-configuracion/stripe-connect/return', [StripeConnectController::class, 'return'])->name('stripe-connect.return');
        Route::patch('mi-configuracion', [ClientConfiguracionController::class, 'update'])->name('mi-configuracion.update');
        Route::patch('mi-configuracion/apariencia', [ClientConfiguracionController::class, 'updateThemePalette'])->name('mi-configuracion.appearance.update');
        Route::patch('mi-configuracion/pantalla-inicio', [ClientConfiguracionController::class, 'updateHomeRoute'])->name('mi-configuracion.home-route.update');
        Route::patch('mi-configuracion/modulos-menu', [ClientConfiguracionController::class, 'updateMenuModules'])->name('mi-configuracion.menu-modules.update');
        Route::patch('mi-configuracion/{animalType}/toggle', [ClientConfiguracionController::class, 'toggleStatus'])->name('mi-configuracion.toggle');
        Route::post('mi-configuracion/users', [ClientConfiguracionController::class, 'storeUser'])->name('mi-configuracion.users.store');
        Route::put('mi-configuracion/users/{teamUser}/veterinarian-profile', [ClientConfiguracionController::class, 'updateVeterinarianProfile'])->name('mi-configuracion.veterinarian-profiles.update');
        Route::get('mi-configuracion/veterinarian-profiles/{veterinarianProfile}/signature', [ClientConfiguracionController::class, 'veterinarianSignature'])->name('mi-configuracion.veterinarian-profiles.signature');
        Route::delete('mi-configuracion/veterinarian-profiles/{veterinarianProfile}/signature', [ClientConfiguracionController::class, 'destroyVeterinarianSignature'])->name('mi-configuracion.veterinarian-profiles.signature.destroy');
        Route::put('mi-configuracion/documentos', [ClientConfiguracionController::class, 'updateDocumentSettings'])->name('mi-configuracion.documents.update');
        Route::get('mi-configuracion/documentos/{tenantDocumentSetting}/membrete', [ClientConfiguracionController::class, 'letterhead'])->name('mi-configuracion.documents.letterhead');
        Route::delete('mi-configuracion/documentos/{tenantDocumentSetting}/membrete', [ClientConfiguracionController::class, 'destroyLetterhead'])->name('mi-configuracion.documents.letterhead.destroy');
        Route::put('mi-configuracion/documentos/plantillas/{type}', [ClientConfiguracionController::class, 'updateDocumentTemplate'])->name('mi-configuracion.document-templates.update');
        Route::delete('mi-configuracion/documentos/plantillas/{type}', [ClientConfiguracionController::class, 'restoreDocumentTemplate'])->name('mi-configuracion.document-templates.restore');
        Route::post('mi-configuracion/plan', [ClientConfiguracionController::class, 'requestPlanChange'])->name('mi-configuracion.plan.request');
        Route::post('mi-configuracion/importar-clientes', [ClientConfiguracionController::class, 'importCustomers'])->name('mi-configuracion.import-customers');
        Route::post('mi-configuracion/importar-servicios', [ClientConfiguracionController::class, 'importServices'])->name('mi-configuracion.import-services');
        Route::post('mi-configuracion/importar-caballos', [ClientConfiguracionController::class, 'importHorses'])->name('mi-configuracion.import-horses');

        /*
a           |--------------------------------------------------------------------------
        | MI CONFIGURACION > facturacion
        */
        Route::post('mi-configuracion/facturacion', [ClientConfiguracionController::class, 'guardarFacturacion'])->name('mi-configuracion.facturacion.store');

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
        Route::get('ventas/{note}/ticket', [NoteController::class, 'ticket'])->name('ventas.ticket');
        Route::post('ventas/{note}/stripe-payment-link', [NoteController::class, 'createStripePaymentLink'])->name('ventas.stripe-payment-link');
        Route::post('ventas/{note}/manual-payment', [NoteController::class, 'storeManualPayment'])->name('ventas.manual-payment');

        // Endpoints de búsqueda predictiva para Alpine.js (Buscadores)
        Route::get('api/buscar-clientes', [NoteController::class, 'searchCustomers'])->name('api.buscar-clientes');
        Route::get('api/buscar-articulos', [NoteController::class, 'searchItems'])->name('api.buscar-articulos');

        // PAGOS A NMOTAS DE CLIENTES

        Route::post('customers/{customer}/payments', [PaymentController::class, 'store'])->name('customers.payments.store');
        Route::post('customers/{customer}/stripe-payment-link', [PaymentController::class, 'createStripePaymentLink'])->name('customers.stripe-payment-link');
        Route::get('customers/{customer}/payments/preview', [PaymentController::class, 'preview'])->name('customers.payments.preview');
        Route::get('customers/{customer}/statement', [StatementController::class, 'generate'])->name('customers.statement.generate');
        Route::patch('customers/{customer}/account-settings', [CustomerController::class, 'updateAccountSettings'])->name('customers.account-settings.update');
        Route::post('customers/{customer}/statements', [StatementController::class, 'storeGenerated'])->name('customers.statements.store');
        Route::get('customers/{customer}/statements/{statement}/pdf', [StatementController::class, 'showStored'])->name('customers.statements.pdf');

        /*
            |--------------------------------------------------------------------------
            | FACTURACIÓN
            |--------------------------------------------------------------------------
            */
        Route::prefix('facturacion')
            ->name('facturacion.')
            ->group(function () {
                Route::get('/', [FacturacionController::class, 'index'])->name('index');
                Route::get('/notas', [FacturacionController::class, 'notas'])->name('notas');
                Route::get('/notas/{note}/facturar', [FacturacionController::class, 'create'])->name('create');
                Route::post('/notas/{note}/facturar', [FacturacionController::class, 'store'])->name('store');
                Route::get('/facturas/{invoice}', [FacturacionController::class, 'show'])->name('show');
            });

    });
require __DIR__.'/auth.php';
