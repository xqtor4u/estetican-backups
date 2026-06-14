<?php

use App\Http\Controllers\Api\AgendaController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\CheckinController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\OperatorController;
use App\Http\Controllers\Api\PetController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Middleware\ApiAuthenticate;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware(ApiAuthenticate::class)->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me',      [AuthController::class, 'me']);

    Route::get('/pets',         [PetController::class, 'index']);
    Route::post('/pets',        [PetController::class, 'store']);
    Route::get('/pets/{pet}',   [PetController::class, 'show']);
    Route::patch('/pets/{pet}', [PetController::class, 'update']);

    Route::get('/clients',            [ClientController::class, 'index']);
    Route::post('/clients',           [ClientController::class, 'store']);
    Route::get('/clients/{client}',   [ClientController::class, 'show']);
    Route::patch('/clients/{client}', [ClientController::class, 'update']);

    Route::get('/agenda',    [AgendaController::class,  'index']);
    Route::get('/operators', [OperatorController::class, 'index']);
    Route::get('/branches',  [OperatorController::class, 'branches']);

    Route::get('/services',          [ServiceController::class, 'index']);
    Route::post('/bookings',         [BookingController::class, 'store']);
    Route::get('/bookings/{booking}',          [BookingController::class,  'show']);
    Route::patch('/bookings/{booking}',        [BookingController::class,  'update']);
    Route::get('/bookings/{booking}/payments', [PaymentController::class, 'index']);
    Route::post('/bookings/{booking}/payments',[PaymentController::class, 'store']);

    Route::get('/checkin/status', [CheckinController::class, 'status']);
    Route::post('/checkin',       [CheckinController::class, 'checkin']);
    Route::post('/checkout',      [CheckinController::class, 'checkout']);

    Route::get('/settings/booking', [SettingController::class, 'booking']);
});
