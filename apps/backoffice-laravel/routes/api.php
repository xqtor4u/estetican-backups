<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\PetController;
use App\Http\Middleware\ApiAuthenticate;
use Illuminate\Support\Facades\Route;

// Rutas públicas
Route::post('/login',  [AuthController::class, 'login']);

// Rutas protegidas
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
});
