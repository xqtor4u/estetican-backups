<?php

// Last standard standardization refresh: 2026-04-21

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\BookingMessageController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ClientPreferencesController;
use App\Http\Controllers\Clinical\ClinicalController;
use App\Http\Controllers\Clinical\ClinicalDiagnosisController;
use App\Http\Controllers\Clinical\ClinicalPrescriptionController;
use App\Http\Controllers\Clinical\ClinicalVisitController;
use App\Http\Controllers\Clinical\PetAllergyController;
use App\Http\Controllers\Clinical\PetConditionController;
use App\Http\Controllers\Clinical\PetFolderController;
use App\Http\Controllers\Clinical\PetVaccinationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Finances\AccountController;
use App\Http\Controllers\Finances\CashMovementController;
use App\Http\Controllers\Finances\CashRegisterController;
use App\Http\Controllers\Finances\CashSessionController;
use App\Http\Controllers\Finances\DocumentSeriesController;
use App\Http\Controllers\Finances\PaymentMethodController;
use App\Http\Controllers\HotelReservationController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\MapaZonasController;
use App\Http\Controllers\OperatorController;
use App\Http\Controllers\OperatorRoleController;
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
Route::post('login', [LoginController::class, 'login']);
Route::post('logout', [LoginController::class, 'logout'])->name('logout');

// Autogestión pública de preferencias de comunicación — sin login, acceso vía
// enlace firmado que llega en los correos que se le mandan al cliente.
Route::middleware('signed')->group(function () {
    Route::get('preferencias/{client}', [ClientPreferencesController::class, 'show'])->name('client-preferences.show');
    Route::post('preferencias/{client}', [ClientPreferencesController::class, 'update'])->name('client-preferences.update');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard.index');

    // PETS CRUD & Shortcuts
    Route::get('pets', [PetController::class, 'index'])->name('pets.index');
    Route::get('pets/{pet}', [PetController::class, 'catalogShow'])->name('pets.show');
    Route::put('pets/{pet}', [PetController::class, 'update'])->name('pets.update');
    Route::put('pets/{pet}/owner', [PetController::class, 'updateOwner'])->name('pets.owner.update');
    Route::post('pets/{pet}/profile-photo', [PetController::class, 'updateProfilePhoto'])->name('pets.profile-photo.update');
    Route::delete('pets/{pet}', [PetController::class, 'destroy'])->name('pets.destroy');

    Route::resource('clients', ClientController::class);
    Route::resource('hotel-reservations', HotelReservationController::class)->except(['destroy']);
    Route::resource('services', ServiceController::class);
    Route::resource('items', ItemController::class)->except(['show'])
        ->middlewareFor('index', 'permission:ver catalogo_articulos')
        ->middlewareFor(['create', 'store'], 'permission:crear catalogo_articulos')
        ->middlewareFor(['edit', 'update'], 'permission:editar catalogo_articulos')
        ->middlewareFor('destroy', 'permission:eliminar catalogo_articulos');
    Route::resource('operators', OperatorController::class);
    Route::post('operators/{operator}/duplicate', [OperatorController::class, 'duplicate'])->name('operators.duplicate');
    Route::resource('branches', BranchController::class);

    Route::get('mapa-zonas', [MapaZonasController::class, 'index'])->name('mapa-zonas.index');
    Route::patch('mapa-zonas/mascotas/{pet}/ubicacion', [MapaZonasController::class, 'updatePetLocation'])->name('mapa-zonas.pets.ubicacion');
    Route::post('mapa-zonas/vehiculos', [MapaZonasController::class, 'storeVehicle'])->name('mapa-zonas.vehiculos.store');
    Route::patch('mapa-zonas/vehiculos/{vehicle}', [MapaZonasController::class, 'updateVehicle'])->name('mapa-zonas.vehiculos.update');
    Route::delete('mapa-zonas/vehiculos/{vehicle}', [MapaZonasController::class, 'destroyVehicle'])->name('mapa-zonas.vehiculos.destroy');

    // RESOURCES CRUD & Shortcuts
    Route::resource('resources', ResourceController::class);
    Route::post('resources/{resource}/profile-photo', [ResourceController::class, 'updateProfilePhoto'])->name('resources.profile-photo.update');

    Route::resource('operator-roles', OperatorRoleController::class);
    Route::post('operator-roles/{operatorRole}/duplicate', [OperatorRoleController::class, 'duplicate'])->name('operator-roles.duplicate');
    Route::post('resources/{resource}/duplicate', [ResourceController::class, 'duplicate'])->name('resources.duplicate');
    Route::post('resources/{resource}/photos', [ResourcePhotoController::class, 'store'])->name('resources.photos.store');
    Route::put('resources/{resource}/photos/{photo}', [ResourcePhotoController::class, 'update'])->name('resources.photos.update');
    Route::delete('resources/{resource}/photos/{photo}', [ResourcePhotoController::class, 'destroy'])->name('resources.photos.destroy');

    Route::get('agenda/create', [SpaBookingController::class, 'globalCreate'])->name('agenda.create');
    Route::get('agenda', [SpaBookingController::class, 'index'])->name('agenda.index');
    Route::get('agenda/{booking}', [SpaBookingController::class, 'show'])->name('agenda.show');
    Route::get('agenda/{booking}/edit', [SpaBookingController::class, 'edit'])->name('agenda.edit');
    Route::put('agenda/{booking}', [SpaBookingController::class, 'update'])->name('agenda.update');
    Route::post('agenda/{booking}/quotes', [SpaBookingController::class, 'storeQuote'])->name('agenda.quotes.store');
    Route::post('agenda/{booking}/quotes/{quote}/accept', [SpaBookingController::class, 'acceptQuote'])->name('agenda.quotes.accept');
    Route::post('agenda/{booking}/quotes/{quote}/payments', [SpaBookingController::class, 'registerPayment'])->name('agenda.quotes.register-payment');
    Route::post('agenda/{booking}/items/{item}/assign', [SpaBookingController::class, 'assignProfessional'])->name('agenda.items.assign');
    Route::post('agenda/{booking}/cancel', [SpaBookingController::class, 'cancel'])->name('agenda.cancel');
    Route::post('agenda/{booking}/no-show', [SpaBookingController::class, 'markNoShow'])->name('agenda.no-show');
    Route::post('services/{service}/duplicate', [ServiceController::class, 'duplicate'])->name('services.duplicate');
    Route::post('hotel-reservations/{hotelReservation}/cancel', [HotelReservationController::class, 'cancel'])->name('hotel-reservations.cancel');

    Route::get('pets/{pet}/bookings/create', [SpaBookingController::class, 'createForPet'])->name('pets.bookings.create');
    Route::post('pets/{pet}/bookings', [SpaBookingController::class, 'storeForPet'])->name('pets.bookings.store');

    // Recordatorios WhatsApp (BL-024 Fase 1)
    Route::get('whatsapp/bandeja', [BookingMessageController::class, 'index'])->name('whatsapp.bandeja');
    Route::post('whatsapp/bandeja/{booking}/preview', [BookingMessageController::class, 'preview'])->name('whatsapp.bandeja.preview');
    Route::post('whatsapp/bandeja/{booking}/enviar', [BookingMessageController::class, 'store'])->name('whatsapp.bandeja.enviar');
    Route::get('whatsapp/recurrencias', [RecurrenceMessageController::class, 'index'])->name('whatsapp.recurrencias');
    Route::post('whatsapp/recurrencias/{key}/preview', [RecurrenceMessageController::class, 'preview'])
        ->where('key', '[0-9]+:[0-9]+')
        ->name('whatsapp.recurrencias.preview');
    Route::post('whatsapp/recurrencias/{key}/enviar', [RecurrenceMessageController::class, 'store'])
        ->where('key', '[0-9]+:[0-9]+')
        ->name('whatsapp.recurrencias.enviar');
    Route::resource('whatsapp/plantillas', WhatsAppTemplateController::class)
        ->parameters(['plantillas' => 'template'])
        ->names('whatsapp.plantillas');

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
            Route::resource('cash-registers', CashRegisterController::class)->except(['show']);
            Route::get('cash-sessions', [CashSessionController::class, 'index'])->name('cash-sessions.index');
            Route::get('cash-registers/{cashRegister}/open', [CashSessionController::class, 'open'])->name('cash-sessions.open');
            Route::post('cash-registers/{cashRegister}/open', [CashSessionController::class, 'store'])->name('cash-sessions.store');
            Route::get('cash-sessions/{cashSession}', [CashSessionController::class, 'show'])->name('cash-sessions.show');
            Route::get('cash-sessions/{cashSession}/close', [CashSessionController::class, 'close'])->name('cash-sessions.close');
            Route::post('cash-sessions/{cashSession}/close', [CashSessionController::class, 'doClose'])->name('cash-sessions.do-close');
            Route::post('cash-sessions/{cashSession}/movements', [CashMovementController::class, 'store'])->name('cash-sessions.movements.store');
            Route::delete('cash-sessions/{cashSession}/movements/{cashMovement}', [CashMovementController::class, 'destroy'])->name('cash-sessions.movements.destroy');
        });
    });

    // Configuración personal del usuario
    Route::controller(UserSettingsController::class)->group(function () {
        Route::get('user/settings', 'index')->name('user.settings');
        Route::put('user/settings', 'update')->name('user.settings.update');
        Route::put('user/settings/password', 'updatePassword')->name('user.settings.password');
    });

    // Reportes de Impresión
    Route::get('reports/quote/{quote}', [ReportController::class, 'quote'])->name('reports.quote');
    Route::get('reports/work-order/{booking}', [ReportController::class, 'workOrder'])->name('reports.work-order');
    Route::get('reports/invoice/{booking}', [ReportController::class, 'invoice'])->name('reports.invoice');

    // Veterinaria (Expediente Clínico) — módulo independiente, apagado por defecto (SystemSettings)
    Route::middleware('clinical.module')->prefix('clinico')->name('clinical.')->group(function () {
        Route::get('/', [ClinicalController::class, 'index'])->middleware('permission:ver clinico')->name('index');

        Route::get('mascotas/{pet}', [ClinicalVisitController::class, 'showPet'])->middleware('permission:ver clinico')->name('pets.show');
        Route::get('mascotas/{pet}/ficha', [PetFolderController::class, 'print'])->middleware('permission:ver clinico')->name('pets.folder');

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

    Route::scopeBindings()->group(function () {
        Route::post('resources/{resource}/events', [ResourceEventController::class, 'store'])->name('resources.events.store');
        Route::get('resources/{resource}/events/{event}', [ResourceEventController::class, 'show'])->name('resources.events.show');
        Route::post('resources/{resource}/events/{event}/updates', [ResourceEventUpdateController::class, 'store'])->name('resources.events.updates.store');
        Route::post('resources/{resource}/events/{event}/photos', [ResourceEventPhotoController::class, 'store'])->name('resources.events.photos.store');
        Route::put('resources/{resource}/events/{event}/photos/{photo}', [ResourceEventPhotoController::class, 'update'])->name('resources.events.photos.update');
        Route::delete('resources/{resource}/events/{event}/photos/{photo}', [ResourceEventPhotoController::class, 'destroy'])->name('resources.events.photos.destroy');

        Route::get('clients/{client}/pets/{pet}', [PetController::class, 'show'])->name('clients.pets.show');
        Route::put('clients/{client}/pets/{pet}', [PetController::class, 'updateFromClient'])->name('clients.pets.update');
        Route::post('clients/{client}/pets/{pet}/profile-photo', [PetController::class, 'updateProfilePhotoFromClient'])->name('clients.pets.profile-photo.update');
        Route::delete('clients/{client}/pets/{pet}', [PetController::class, 'destroyFromClient'])->name('clients.pets.destroy');

        Route::get('clients/{client}/pets/{pet}/bookings/create', [SpaBookingController::class, 'createForClientPet'])->name('clients.pets.bookings.create');
        Route::post('clients/{client}/pets/{pet}/bookings', [SpaBookingController::class, 'storeForClientPet'])->name('clients.pets.bookings.store');

        Route::post('clients/{client}/pets/{pet}/medical-alerts', [PetMedicalAlertController::class, 'store'])->name('clients.pets.medical-alerts.store');
        Route::put('clients/{client}/pets/{pet}/medical-alerts/{medicalAlert}', [PetMedicalAlertController::class, 'update'])->name('clients.pets.medical-alerts.update');
        Route::delete('clients/{client}/pets/{pet}/medical-alerts/{medicalAlert}', [PetMedicalAlertController::class, 'destroy'])->name('clients.pets.medical-alerts.destroy');

        Route::post('clients/{client}/pets/{pet}/photos', [PetPhotoController::class, 'store'])->name('clients.pets.photos.store');
        Route::put('clients/{client}/pets/{pet}/photos/{photo}', [PetPhotoController::class, 'update'])->name('clients.pets.photos.update');
        Route::delete('clients/{client}/pets/{pet}/photos/{photo}', [PetPhotoController::class, 'destroy'])->name('clients.pets.photos.destroy');
    });
});
