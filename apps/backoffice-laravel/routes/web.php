<?php
// Last standard standardization refresh: 2026-04-21

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\HotelReservationController;
use App\Http\Controllers\OperatorController;
use App\Http\Controllers\OperatorRoleController;
use App\Http\Controllers\PetController;
use App\Http\Controllers\PetMedicalAlertController;
use App\Http\Controllers\PetPhotoController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ResourceController;
use App\Http\Controllers\ResourceEventController;
use App\Http\Controllers\ResourceEventPhotoController;
use App\Http\Controllers\ResourceEventUpdateController;
use App\Http\Controllers\ResourcePhotoController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\SpaBookingController;
use App\Http\Controllers\SystemSettingController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\UserSettingsController;
use App\Http\Controllers\Finances\AccountController;
use App\Http\Controllers\Finances\PaymentMethodController;
use App\Http\Controllers\Finances\DocumentSeriesController;
use App\Http\Controllers\Finances\CashRegisterController;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard.index')
        : redirect()->route('login');
})->name('home');


Route::get('login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('login', [LoginController::class, 'login']);
Route::post('logout', [LoginController::class, 'logout'])->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard.index');

    // PETS CRUD & Shortcuts
    Route::get('pets', [PetController::class, 'index'])->name('pets.index');
    Route::get('pets/{pet}', [PetController::class, 'catalogShow'])->name('pets.show');
    Route::put('pets/{pet}', [PetController::class, 'update'])->name('pets.update');
    Route::post('pets/{pet}/profile-photo', [PetController::class, 'updateProfilePhoto'])->name('pets.profile-photo.update');
    Route::delete('pets/{pet}', [PetController::class, 'destroy'])->name('pets.destroy');

    Route::resource('clients', ClientController::class);
    Route::resource('hotel-reservations', HotelReservationController::class)->except(['destroy']);
    Route::resource('services', ServiceController::class);
    Route::resource('operators', OperatorController::class);
    Route::post('operators/{operator}/duplicate', [OperatorController::class, 'duplicate'])->name('operators.duplicate');
    Route::resource('branches', BranchController::class);
    
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

    // Rutas protegidas para administradores
    Route::middleware('role:admin|super-admin')->group(function () {
        Route::get('system-settings', [SystemSettingController::class, 'index'])->name('system-settings.index');
        Route::put('system-settings/{section}', [SystemSettingController::class, 'update'])->name('system-settings.update');
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
