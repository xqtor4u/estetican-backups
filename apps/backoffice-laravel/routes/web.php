<?php

// Last standard standardization refresh: 2026-04-21

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\Api\ClientWhatsAppController;
use App\Http\Controllers\BookingMessageController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ClientPreferencesController;
use App\Http\Controllers\Clinical\ClinicalAttachmentController;
use App\Http\Controllers\Clinical\ClinicalController;
use App\Http\Controllers\Clinical\ClinicalDiagnosisController;
use App\Http\Controllers\Clinical\ClinicalPrescriptionController;
use App\Http\Controllers\Clinical\ClinicalRecordPdfController;
use App\Http\Controllers\Clinical\ClinicalVisitController;
use App\Http\Controllers\Clinical\PetAllergyController;
use App\Http\Controllers\Clinical\PetConditionController;
use App\Http\Controllers\Clinical\PetFolderController;
use App\Http\Controllers\Clinical\PetVaccinationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Finances\AccountController;
use App\Http\Controllers\Finances\CashMovementController;
use App\Http\Controllers\Finances\CashRegisterController;
use App\Http\Controllers\Finances\CashReportController;
use App\Http\Controllers\Finances\CashSessionController;
use App\Http\Controllers\Finances\DocumentController;
use App\Http\Controllers\Finances\DocumentSeriesController;
use App\Http\Controllers\Finances\PaymentMethodController;
use App\Http\Controllers\GroupComponentController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\HotelReservationController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\ItemMovementController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\MapaZonasController;
use App\Http\Controllers\MetaCatalogSyncController;
use App\Http\Controllers\OperatorController;
use App\Http\Controllers\OperatorGoogleCalendarController;
use App\Http\Controllers\OperatorRoleController;
use App\Http\Controllers\OperatorUnavailabilityController;
use App\Http\Controllers\PetController;
use App\Http\Controllers\PetMedicalAlertController;
use App\Http\Controllers\PetPhotoController;
use App\Http\Controllers\RecurrenceMessageController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ResourceController;
use App\Http\Controllers\ResourceEventController;
use App\Http\Controllers\ResourceEventPhotoController;
use App\Http\Controllers\ResourceEventUpdateController;
use App\Http\Controllers\ResourcePhotoController;
use App\Http\Controllers\ScreenLockController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\SpaBookingController;
use App\Http\Controllers\SystemSettingController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserSettingsController;
use App\Http\Controllers\WhatsAppTemplateController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard.index')
        : redirect()->route('login');
})->name('home');

Route::get('login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('login', [LoginController::class, 'login'])->middleware('throttle:login');
Route::post('logout', [LoginController::class, 'logout'])->name('logout');

// Autogestión pública de preferencias de comunicación — sin login, acceso vía
// enlace firmado que llega en los correos que se le mandan al cliente.
Route::middleware('signed')->group(function () {
    Route::get('preferencias/{client}', [ClientPreferencesController::class, 'show'])->name('client-preferences.show');
    Route::post('preferencias/{client}', [ClientPreferencesController::class, 'update'])->name('client-preferences.update');
});

// Bloqueo de pantalla — deliberadamente fuera de `screen.lock` para no auto-bloquearse.
Route::middleware('auth')->group(function () {
    Route::get('bloqueo', [ScreenLockController::class, 'show'])->name('screen-lock.show');
    Route::post('bloqueo', [ScreenLockController::class, 'lock'])->name('screen-lock.lock');
    Route::post('bloqueo/desbloquear', [ScreenLockController::class, 'unlock'])
        ->middleware('throttle:5,1')
        ->name('screen-lock.unlock');
});

Route::middleware(['auth', 'screen.lock'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard.index')->middleware('permission:ver dashboard');

    // PETS CRUD & Shortcuts
    Route::get('pets', [PetController::class, 'index'])->name('pets.index')->middleware('permission:ver mascotas');
    Route::get('pets/{pet}', [PetController::class, 'catalogShow'])->name('pets.show')->middleware('permission:ver mascotas');
    Route::put('pets/{pet}', [PetController::class, 'update'])->name('pets.update')->middleware('permission:editar mascotas');
    Route::put('pets/{pet}/owner', [PetController::class, 'updateOwner'])->name('pets.owner.update')->middleware('permission:editar mascotas');
    Route::post('pets/{pet}/profile-photo', [PetController::class, 'updateProfilePhoto'])->name('pets.profile-photo.update')->middleware('permission:editar mascotas');
    Route::delete('pets/{pet}', [PetController::class, 'destroy'])->name('pets.destroy')->middleware('permission:eliminar mascotas');

    Route::resource('clients', ClientController::class)
        ->middlewareFor('index', 'permission:ver clientes')
        ->middlewareFor('show', 'permission:ver clientes')
        ->middlewareFor(['create', 'store'], 'permission:crear clientes')
        ->middlewareFor(['edit', 'update'], 'permission:editar clientes')
        ->middlewareFor('destroy', 'permission:eliminar clientes');
    Route::get('whatsapp-templates', [ClientWhatsAppController::class, 'templates'])->name('clients.whatsapp.templates')->middleware('permission:ver clientes');
    Route::get('clients/{client}/whatsapp-link', [ClientWhatsAppController::class, 'link'])->name('clients.whatsapp.link')->middleware('permission:ver clientes');
    Route::get('clients/{client}/whatsapp-live-pets', [ClientWhatsAppController::class, 'livePets'])->name('clients.whatsapp.live-pets')->middleware('permission:ver clientes');
    Route::middleware('hotel.module')->group(function () {
        Route::resource('hotel-reservations', HotelReservationController::class)->except(['destroy'])
            ->middlewareFor('index', 'permission:ver hotel')
            ->middlewareFor('show', 'permission:ver hotel')
            ->middlewareFor(['create', 'store'], 'permission:crear hotel')
            ->middlewareFor(['edit', 'update'], 'permission:editar hotel');
    });
    Route::resource('services', ServiceController::class)
        ->middlewareFor('index', 'permission:ver catalogo_servicios')
        ->middlewareFor('show', 'permission:ver catalogo_servicios')
        ->middlewareFor(['create', 'store'], 'permission:crear catalogo_servicios')
        ->middlewareFor(['edit', 'update'], 'permission:editar catalogo_servicios')
        ->middlewareFor('destroy', 'permission:eliminar catalogo_servicios');
    Route::middleware('store.module')->group(function () {
        Route::resource('items', ItemController::class)->except(['show'])
            ->middlewareFor('index', 'permission:ver catalogo_articulos')
            ->middlewareFor(['create', 'store'], 'permission:crear catalogo_articulos')
            ->middlewareFor(['edit', 'update'], 'permission:editar catalogo_articulos')
            ->middlewareFor('destroy', 'permission:eliminar catalogo_articulos');
        Route::post('items/{item}/movements', [ItemMovementController::class, 'store'])
            ->name('items.movements.store')
            ->middleware('permission:editar catalogo_articulos');
        Route::post('items/{item}/movements/transfer', [ItemMovementController::class, 'transfer'])
            ->name('items.movements.transfer')
            ->middleware('permission:editar catalogo_articulos');
        Route::post('items-catalog-sync', [MetaCatalogSyncController::class, 'store'])
            ->name('items.catalog-sync')
            ->middleware('permission:editar catalogo_articulos');
        Route::resource('groups', GroupController::class)->except(['show'])
            ->middlewareFor('index', 'permission:ver catalogo_grupos')
            ->middlewareFor(['create', 'store'], 'permission:crear catalogo_grupos')
            ->middlewareFor(['edit', 'update'], 'permission:editar catalogo_grupos')
            ->middlewareFor('destroy', 'permission:eliminar catalogo_grupos');
        Route::post('groups/{group}/components', [GroupComponentController::class, 'store'])
            ->name('groups.components.store')
            ->middleware('permission:editar catalogo_grupos');
        Route::delete('groups/{group}/components/{component}', [GroupComponentController::class, 'destroy'])
            ->name('groups.components.destroy')
            ->middleware('permission:editar catalogo_grupos');
    });
    Route::resource('operators', OperatorController::class)
        ->middlewareFor('index', 'permission:ver operadores')
        ->middlewareFor('show', 'permission:ver operadores')
        ->middlewareFor(['create', 'store'], 'permission:crear operadores')
        ->middlewareFor(['edit', 'update'], 'permission:editar operadores')
        ->middlewareFor('destroy', 'permission:eliminar operadores');
    Route::post('operators/{operator}/duplicate', [OperatorController::class, 'duplicate'])->name('operators.duplicate')->middleware('permission:crear operadores');
    Route::post('operators/{operator}/unavailabilities', [OperatorUnavailabilityController::class, 'store'])->name('operators.unavailabilities.store')->middleware('permission:editar operadores');
    Route::delete('operators/{operator}/unavailabilities/{unavailability}', [OperatorUnavailabilityController::class, 'destroy'])->name('operators.unavailabilities.destroy')->middleware('permission:editar operadores');
    Route::put('operators/{operator}/google-calendar', [OperatorGoogleCalendarController::class, 'update'])->name('operators.google-calendar.update')->middleware('permission:editar operadores');
    Route::resource('branches', BranchController::class)
        ->middlewareFor('index', 'permission:ver sucursales')
        ->middlewareFor('show', 'permission:ver sucursales')
        ->middlewareFor(['create', 'store'], 'permission:crear sucursales')
        ->middlewareFor(['edit', 'update'], 'permission:editar sucursales')
        ->middlewareFor('destroy', 'permission:eliminar sucursales');

    Route::get('mapa-zonas', [MapaZonasController::class, 'index'])->name('mapa-zonas.index')->middleware('permission:ver mascotas');
    Route::patch('mapa-zonas/mascotas/{pet}/ubicacion', [MapaZonasController::class, 'updatePetLocation'])->name('mapa-zonas.pets.ubicacion')->middleware('permission:editar mascotas');
    Route::post('mapa-zonas/vehiculos', [MapaZonasController::class, 'storeVehicle'])->name('mapa-zonas.vehiculos.store')->middleware('permission:editar mascotas');
    Route::patch('mapa-zonas/vehiculos/{vehicle}', [MapaZonasController::class, 'updateVehicle'])->name('mapa-zonas.vehiculos.update')->middleware('permission:editar mascotas');
    Route::delete('mapa-zonas/vehiculos/{vehicle}', [MapaZonasController::class, 'destroyVehicle'])->name('mapa-zonas.vehiculos.destroy')->middleware('permission:editar mascotas');

    // RESOURCES CRUD & Shortcuts
    Route::resource('resources', ResourceController::class)
        ->middlewareFor('index', 'permission:ver sucursales')
        ->middlewareFor('show', 'permission:ver sucursales')
        ->middlewareFor(['create', 'store'], 'permission:crear sucursales')
        ->middlewareFor(['edit', 'update'], 'permission:editar sucursales')
        ->middlewareFor('destroy', 'permission:eliminar sucursales');
    Route::post('resources/{resource}/profile-photo', [ResourceController::class, 'updateProfilePhoto'])->name('resources.profile-photo.update')->middleware('permission:editar sucursales');

    Route::resource('operator-roles', OperatorRoleController::class)
        ->middlewareFor('index', 'permission:ver operadores')
        ->middlewareFor('show', 'permission:ver operadores')
        ->middlewareFor(['create', 'store'], 'permission:crear operadores')
        ->middlewareFor(['edit', 'update'], 'permission:editar operadores')
        ->middlewareFor('destroy', 'permission:eliminar operadores');
    Route::post('operator-roles/{operatorRole}/duplicate', [OperatorRoleController::class, 'duplicate'])->name('operator-roles.duplicate')->middleware('permission:crear operadores');
    Route::post('resources/{resource}/duplicate', [ResourceController::class, 'duplicate'])->name('resources.duplicate')->middleware('permission:crear sucursales');
    Route::post('resources/{resource}/photos', [ResourcePhotoController::class, 'store'])->name('resources.photos.store')->middleware('permission:editar sucursales');
    Route::put('resources/{resource}/photos/{photo}', [ResourcePhotoController::class, 'update'])->name('resources.photos.update')->middleware('permission:editar sucursales');
    Route::delete('resources/{resource}/photos/{photo}', [ResourcePhotoController::class, 'destroy'])->name('resources.photos.destroy')->middleware('permission:editar sucursales');

    Route::get('agenda/create', [SpaBookingController::class, 'globalCreate'])->name('agenda.create')->middleware('permission:crear agenda');
    Route::get('agenda', [SpaBookingController::class, 'index'])->name('agenda.index')->middleware('permission:ver agenda');
    Route::get('agenda/check-availability', [SpaBookingController::class, 'checkAvailability'])->name('agenda.check-availability')->middleware('permission:ver agenda');
    Route::get('agenda/{booking}', [SpaBookingController::class, 'show'])->name('agenda.show')->middleware('permission:ver agenda');
    Route::get('agenda/{booking}/edit', [SpaBookingController::class, 'edit'])->name('agenda.edit')->middleware('permission:editar agenda');
    Route::put('agenda/{booking}', [SpaBookingController::class, 'update'])->name('agenda.update')->middleware('permission:editar agenda');
    Route::post('agenda/{booking}/iniciar', [SpaBookingController::class, 'start'])->name('agenda.start')->middleware('permission:editar agenda');
    Route::post('agenda/{booking}/quotes', [SpaBookingController::class, 'storeQuote'])->name('agenda.quotes.store')->middleware('permission:editar agenda');
    Route::post('agenda/{booking}/quotes/{quote}/accept', [SpaBookingController::class, 'acceptQuote'])->name('agenda.quotes.accept')->middleware('permission:editar agenda');
    Route::post('agenda/{booking}/quotes/{quote}/payments', [SpaBookingController::class, 'registerPayment'])->name('agenda.quotes.register-payment')->middleware('permission:cobros.registrar');
    Route::post('agenda/{booking}/payments', [SpaBookingController::class, 'registerDirectPayment'])->name('agenda.payments.store')->middleware('permission:cobros.registrar');
    Route::post('agenda/{booking}/items/{item}/assign', [SpaBookingController::class, 'assignProfessional'])->name('agenda.items.assign')->middleware('permission:editar agenda');
    Route::patch('agenda/{booking}/services/{line}', [SpaBookingController::class, 'updateServiceLine'])->name('agenda.services.update')->middleware('permission:editar agenda');
    Route::post('agenda/{booking}/cancel', [SpaBookingController::class, 'cancel'])->name('agenda.cancel')->middleware('permission:editar agenda');
    Route::post('agenda/{booking}/no-show', [SpaBookingController::class, 'markNoShow'])->name('agenda.no-show')->middleware('permission:editar agenda');
    Route::post('agenda/{booking}/unfulfillable', [SpaBookingController::class, 'markUnfulfillable'])->name('agenda.unfulfillable')->middleware('permission:editar agenda');
    Route::post('services/{service}/duplicate', [ServiceController::class, 'duplicate'])->name('services.duplicate')->middleware('permission:crear catalogo_servicios');
    Route::post('hotel-reservations/{hotelReservation}/cancel', [HotelReservationController::class, 'cancel'])->name('hotel-reservations.cancel')->middleware(['hotel.module', 'permission:editar hotel']);

    Route::get('pets/{pet}/bookings/create', [SpaBookingController::class, 'createForPet'])->name('pets.bookings.create')->middleware('permission:crear agenda');
    Route::post('pets/{pet}/bookings', [SpaBookingController::class, 'storeForPet'])->name('pets.bookings.store')->middleware('permission:crear agenda');

    // Recordatorios WhatsApp (BL-024 Fase 1)
    Route::get('whatsapp/bandeja', [BookingMessageController::class, 'index'])->name('whatsapp.bandeja')->middleware('permission:ver whatsapp');
    Route::post('whatsapp/bandeja/{booking}/preview', [BookingMessageController::class, 'preview'])->name('whatsapp.bandeja.preview')->middleware('permission:ver whatsapp');
    Route::post('whatsapp/bandeja/{booking}/enviar', [BookingMessageController::class, 'store'])->name('whatsapp.bandeja.enviar')->middleware('permission:crear whatsapp');
    Route::get('whatsapp/recurrencias', [RecurrenceMessageController::class, 'index'])->name('whatsapp.recurrencias')->middleware('permission:ver whatsapp');
    Route::post('whatsapp/recurrencias/{key}/preview', [RecurrenceMessageController::class, 'preview'])
        ->where('key', '[0-9]+:[0-9]+')
        ->name('whatsapp.recurrencias.preview')
        ->middleware('permission:ver whatsapp');
    Route::post('whatsapp/recurrencias/{key}/enviar', [RecurrenceMessageController::class, 'store'])
        ->where('key', '[0-9]+:[0-9]+')
        ->name('whatsapp.recurrencias.enviar')
        ->middleware('permission:crear whatsapp');
    Route::resource('whatsapp/plantillas', WhatsAppTemplateController::class)
        ->parameters(['plantillas' => 'template'])
        ->names('whatsapp.plantillas')
        ->middlewareFor('index', 'permission:ver whatsapp')
        ->middlewareFor('show', 'permission:ver whatsapp')
        ->middlewareFor(['create', 'store'], 'permission:crear whatsapp')
        ->middlewareFor(['edit', 'update'], 'permission:editar whatsapp')
        ->middlewareFor('destroy', 'permission:eliminar whatsapp');

    // Rutas protegidas para administradores
    Route::middleware('role:admin|super-admin')->group(function () {
        Route::get('system-settings', [SystemSettingController::class, 'index'])->name('system-settings.index');
        Route::put('system-settings/{section}', [SystemSettingController::class, 'update'])->name('system-settings.update');
        Route::patch('system-settings/{section}/field', [SystemSettingController::class, 'patchField'])->name('system-settings.patch-field');
        Route::post('system-settings/smtp-test', [SystemSettingController::class, 'testSmtp'])->name('system-settings.smtp-test');

        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::get('users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('users', [UserController::class, 'store'])->name('users.store');
        Route::get('users/{user}', [UserController::class, 'show'])->name('users.show');
        Route::get('users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');

        Route::get('activity-log', [ActivityLogController::class, 'index'])->name('activity-log.index');

        // Módulo de Finanzas
        Route::prefix('finances')->name('finances.')->group(function () {
            Route::resource('accounts', AccountController::class)->except(['show']);
            Route::resource('payment-methods', PaymentMethodController::class)->except(['show']);
            Route::resource('document-series', DocumentSeriesController::class)->except(['show']);
            Route::post('documents/{document}/cancel', [DocumentController::class, 'cancel'])->name('documents.cancel');
            Route::post('documents/{document}/reissue', [DocumentController::class, 'reissue'])->name('documents.reissue');
            Route::resource('cash-registers', CashRegisterController::class)->except(['show']);
            Route::get('cash-sessions', [CashSessionController::class, 'index'])->name('cash-sessions.index');
            Route::get('cash-registers/{cashRegister}/open', [CashSessionController::class, 'open'])->name('cash-sessions.open');
            Route::post('cash-registers/{cashRegister}/open', [CashSessionController::class, 'store'])->name('cash-sessions.store');
            Route::get('cash-sessions/{cashSession}', [CashSessionController::class, 'show'])->name('cash-sessions.show');
            Route::get('cash-sessions/{cashSession}/close', [CashSessionController::class, 'close'])->name('cash-sessions.close');
            Route::post('cash-sessions/{cashSession}/close', [CashSessionController::class, 'doClose'])->name('cash-sessions.do-close');
            Route::post('cash-sessions/{cashSession}/movements', [CashMovementController::class, 'store'])->name('cash-sessions.movements.store');
            Route::delete('cash-sessions/{cashSession}/movements/{cashMovement}', [CashMovementController::class, 'destroy'])->name('cash-sessions.movements.destroy');

            Route::prefix('cash-reports')->name('cash-reports.')->group(function () {
                Route::get('resumen', [CashReportController::class, 'resumen'])->name('resumen');
                Route::get('resumen/pdf', [CashReportController::class, 'resumenPdf'])->name('resumen.pdf');
                Route::post('resumen/email', [CashReportController::class, 'resumenEmail'])->name('resumen.email');
                Route::get('metodos-pago', [CashReportController::class, 'metodosPago'])->name('metodos-pago');
                Route::get('metodos-pago/pdf', [CashReportController::class, 'metodosPagoPdf'])->name('metodos-pago.pdf');
                Route::post('metodos-pago/email', [CashReportController::class, 'metodosPagoEmail'])->name('metodos-pago.email');
                Route::get('por-operador', [CashReportController::class, 'porOperador'])->name('por-operador');
                Route::get('por-operador/pdf', [CashReportController::class, 'porOperadorPdf'])->name('por-operador.pdf');
                Route::post('por-operador/email', [CashReportController::class, 'porOperadorEmail'])->name('por-operador.email');
                Route::get('pendientes', [CashReportController::class, 'pendientes'])->name('pendientes');
                Route::get('pendientes/pdf', [CashReportController::class, 'pendientesPdf'])->name('pendientes.pdf');
                Route::post('pendientes/email', [CashReportController::class, 'pendientesEmail'])->name('pendientes.email');
                Route::get('cierres', [CashReportController::class, 'cierres'])->name('cierres');
                Route::get('cierres/pdf', [CashReportController::class, 'cierresPdf'])->name('cierres.pdf');
                Route::post('cierres/email', [CashReportController::class, 'cierresEmail'])->name('cierres.email');
            });
        });
    });

    // Configuración personal del usuario
    Route::controller(UserSettingsController::class)->group(function () {
        Route::get('user/settings', 'index')->name('user.settings');
        Route::put('user/settings', 'update')->name('user.settings.update');
        Route::put('user/settings/password', 'updatePassword')->name('user.settings.password');
        Route::put('user/settings/preferences', 'updatePreferences')->name('user.settings.preferences');
    });

    // Reportes de Impresión
    Route::get('reports/quote/{quote}', [ReportController::class, 'quote'])->name('reports.quote')->middleware('permission:ver agenda');
    Route::get('reports/work-order/{booking}', [ReportController::class, 'workOrder'])->name('reports.work-order')->middleware('permission:ver agenda');
    Route::get('reports/invoice/{booking}', [ReportController::class, 'invoice'])->name('reports.invoice')->middleware('permission:ver agenda');

    // Veterinaria (Expediente Clínico) — módulo independiente, apagado por defecto (SystemSettings)
    Route::middleware('clinical.module')->prefix('clinico')->name('clinical.')->group(function () {
        Route::get('/', [ClinicalController::class, 'index'])->middleware('permission:ver clinico')->name('index');

        Route::get('mascotas/{pet}', [ClinicalVisitController::class, 'showPet'])->middleware('permission:ver clinico')->name('pets.show');
        Route::get('mascotas/{pet}/ficha', [PetFolderController::class, 'print'])->middleware('permission:ver clinico')->name('pets.folder');
        Route::get('mascotas/{pet}/expediente.pdf', [ClinicalRecordPdfController::class, 'pet'])->middleware('permission:ver clinico')->name('pets.record.pdf');

        Route::get('mascotas/{pet}/visitas/crear', [ClinicalVisitController::class, 'create'])->middleware('permission:crear clinico')->name('visits.create');
        Route::post('mascotas/{pet}/visitas', [ClinicalVisitController::class, 'store'])->middleware('permission:crear clinico')->name('visits.store');
        Route::get('visitas/{visit}', [ClinicalVisitController::class, 'show'])->middleware('permission:ver clinico')->name('visits.show');
        Route::get('visitas/{visit}/editar', [ClinicalVisitController::class, 'edit'])->middleware('permission:editar clinico')->name('visits.edit');
        Route::put('visitas/{visit}', [ClinicalVisitController::class, 'update'])->middleware('permission:editar clinico')->name('visits.update');
        Route::post('visitas/{visit}/firmar', [ClinicalVisitController::class, 'sign'])->middleware('permission:clinico.firmar')->name('visits.sign');
        Route::get('visitas/{visit}/enmendar', [ClinicalVisitController::class, 'createAmendment'])->middleware('permission:crear clinico')->name('visits.amend.create');
        Route::post('visitas/{visit}/enmendar', [ClinicalVisitController::class, 'storeAmendment'])->middleware('permission:crear clinico')->name('visits.amend.store');

        Route::post('visitas/{visit}/diagnosticos', [ClinicalDiagnosisController::class, 'store'])->middleware('permission:editar clinico')->name('diagnoses.store');
        Route::post('diagnosticos/{diagnosis}/promover', [ClinicalDiagnosisController::class, 'promote'])->middleware('permission:editar clinico')->name('diagnoses.promote');

        Route::post('visitas/{visit}/recetas', [ClinicalPrescriptionController::class, 'store'])->middleware('permission:editar clinico')->name('prescriptions.store');
        Route::get('recetas/{prescription}/pdf', [ClinicalRecordPdfController::class, 'prescription'])->middleware('permission:ver clinico')->name('prescriptions.pdf');

        // scopeBindings(): {vaccination}/{allergy}/{condition} se resuelven SIEMPRE contra la
        // relación del {pet} de la URL (Pet::vaccinations()/allergies()/conditions()), no por ID
        // suelto — sin esto, cualquier combinación de {pet}+{id} que no correspondan entre sí
        // igual resolvía y editaba/borraba el registro de OTRA mascota (IDOR real, dato clínico).
        // Mismo patrón que ya usa resources.events.* más abajo.
        Route::scopeBindings()->group(function () {
            Route::post('mascotas/{pet}/vacunas', [PetVaccinationController::class, 'store'])->middleware('permission:alergias.administrar')->name('vaccinations.store');
            Route::put('mascotas/{pet}/vacunas/{vaccination}', [PetVaccinationController::class, 'update'])->middleware('permission:alergias.administrar')->name('vaccinations.update');
            Route::delete('mascotas/{pet}/vacunas/{vaccination}', [PetVaccinationController::class, 'destroy'])->middleware('permission:alergias.administrar')->name('vaccinations.destroy');

            Route::post('mascotas/{pet}/alergias', [PetAllergyController::class, 'store'])->middleware('permission:alergias.administrar')->name('allergies.store');
            Route::put('mascotas/{pet}/alergias/{allergy}', [PetAllergyController::class, 'update'])->middleware('permission:alergias.administrar')->name('allergies.update');
            Route::delete('mascotas/{pet}/alergias/{allergy}', [PetAllergyController::class, 'destroy'])->middleware('permission:alergias.administrar')->name('allergies.destroy');

            Route::post('mascotas/{pet}/condiciones', [PetConditionController::class, 'store'])->middleware('permission:alergias.administrar')->name('conditions.store');
            Route::put('mascotas/{pet}/condiciones/{condition}', [PetConditionController::class, 'update'])->middleware('permission:alergias.administrar')->name('conditions.update');
            Route::delete('mascotas/{pet}/condiciones/{condition}', [PetConditionController::class, 'destroy'])->middleware('permission:alergias.administrar')->name('conditions.destroy');
        });

        Route::post('mascotas/{pet}/adjuntos', [ClinicalAttachmentController::class, 'store'])->middleware('permission:crear clinico')->name('attachments.store');
        Route::delete('mascotas/{pet}/adjuntos/{attachment}', [ClinicalAttachmentController::class, 'destroy'])->middleware('permission:editar clinico')->name('attachments.destroy');
    });

    Route::scopeBindings()->group(function () {
        Route::post('resources/{resource}/events', [ResourceEventController::class, 'store'])->name('resources.events.store')->middleware('permission:editar sucursales');
        Route::get('resources/{resource}/events/{event}', [ResourceEventController::class, 'show'])->name('resources.events.show')->middleware('permission:ver sucursales');
        Route::post('resources/{resource}/events/{event}/updates', [ResourceEventUpdateController::class, 'store'])->name('resources.events.updates.store')->middleware('permission:editar sucursales');
        Route::post('resources/{resource}/events/{event}/photos', [ResourceEventPhotoController::class, 'store'])->name('resources.events.photos.store')->middleware('permission:editar sucursales');
        Route::put('resources/{resource}/events/{event}/photos/{photo}', [ResourceEventPhotoController::class, 'update'])->name('resources.events.photos.update')->middleware('permission:editar sucursales');
        Route::delete('resources/{resource}/events/{event}/photos/{photo}', [ResourceEventPhotoController::class, 'destroy'])->name('resources.events.photos.destroy')->middleware('permission:editar sucursales');

        Route::get('clients/{client}/pets/{pet}', [PetController::class, 'show'])->name('clients.pets.show')->middleware('permission:ver mascotas');
        Route::put('clients/{client}/pets/{pet}', [PetController::class, 'updateFromClient'])->name('clients.pets.update')->middleware('permission:editar mascotas');
        Route::post('clients/{client}/pets/{pet}/profile-photo', [PetController::class, 'updateProfilePhotoFromClient'])->name('clients.pets.profile-photo.update')->middleware('permission:editar mascotas');
        Route::delete('clients/{client}/pets/{pet}', [PetController::class, 'destroyFromClient'])->name('clients.pets.destroy')->middleware('permission:eliminar mascotas');

        Route::get('clients/{client}/pets/{pet}/bookings/create', [SpaBookingController::class, 'createForClientPet'])->name('clients.pets.bookings.create')->middleware('permission:crear agenda');
        Route::post('clients/{client}/pets/{pet}/bookings', [SpaBookingController::class, 'storeForClientPet'])->name('clients.pets.bookings.store')->middleware('permission:crear agenda');

        Route::post('clients/{client}/pets/{pet}/medical-alerts', [PetMedicalAlertController::class, 'store'])->name('clients.pets.medical-alerts.store')->middleware('permission:editar mascotas');
        Route::put('clients/{client}/pets/{pet}/medical-alerts/{medicalAlert}', [PetMedicalAlertController::class, 'update'])->name('clients.pets.medical-alerts.update')->middleware('permission:editar mascotas');
        Route::delete('clients/{client}/pets/{pet}/medical-alerts/{medicalAlert}', [PetMedicalAlertController::class, 'destroy'])->name('clients.pets.medical-alerts.destroy')->middleware('permission:editar mascotas');

        Route::post('clients/{client}/pets/{pet}/photos', [PetPhotoController::class, 'store'])->name('clients.pets.photos.store')->middleware('permission:editar mascotas');
        Route::put('clients/{client}/pets/{pet}/photos/{photo}', [PetPhotoController::class, 'update'])->name('clients.pets.photos.update')->middleware('permission:editar mascotas');
        Route::delete('clients/{client}/pets/{pet}/photos/{photo}', [PetPhotoController::class, 'destroy'])->name('clients.pets.photos.destroy')->middleware('permission:editar mascotas');
    });
});
