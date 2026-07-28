<?php

use App\Http\Controllers\Api\AgendaController;
use App\Http\Controllers\Api\AssistantChatController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\BookingProcessNoteController;
use App\Http\Controllers\Api\CashController;
use App\Http\Controllers\Api\CheckinController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\OperatorController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PetController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\UnavailabilityController;
use App\Http\Middleware\ApiAuthenticate;
use App\Http\Middleware\VerifyAssistantSiteToken;
use App\Models\DocumentSeries;
use App\Models\HotelReservation;
use App\Models\PaymentMethod;
use App\Models\Pet;
use App\Models\SpaBooking;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

// Público — widget de chat con IA del sitio WordPress, sin conexión a CRM/clientes.
// El CORS de estas rutas se maneja en middleware global (HandleAssistantCors,
// ver bootstrap/app.php) porque el preflight OPTIONS nunca llegaría a un
// middleware de ruta.
Route::middleware(VerifyAssistantSiteToken::class)->group(function () {
    Route::get('/assistant/config', [AssistantChatController::class, 'config']);
    Route::post('/assistant/chat', [AssistantChatController::class, 'send'])->middleware('throttle:15,1');
});

Route::middleware(ApiAuthenticate::class)->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::patch('/me', [ProfileController::class, 'update']);
    Route::put('/me/password', [ProfileController::class, 'updatePassword']);
    Route::post('/me/verify-password', [ProfileController::class, 'verifyPassword']);
    Route::post('/me/photo', [ProfileController::class, 'updatePhoto']);
    Route::delete('/me/photo', [ProfileController::class, 'deletePhoto']);

    Route::get('/me/unavailabilities', [UnavailabilityController::class, 'index'])->middleware('permission:ver disponibilidad_propia');
    Route::post('/me/unavailabilities', [UnavailabilityController::class, 'store'])->middleware('permission:crear disponibilidad_propia');
    Route::delete('/me/unavailabilities/{unavailability}', [UnavailabilityController::class, 'destroy'])->middleware('permission:eliminar disponibilidad_propia');

    Route::get('/pets', [PetController::class, 'index']);
    Route::post('/pets', [PetController::class, 'store']);
    Route::get('/pets/{pet}', [PetController::class, 'show']);
    Route::patch('/pets/{pet}', [PetController::class, 'update']);
    Route::post('/pets/{pet}/photo', [PetController::class, 'updatePhoto']);
    Route::delete('/pets/{pet}/photo', [PetController::class, 'deletePhoto']);

    Route::get('/clients', [ClientController::class, 'index']);
    Route::post('/clients', [ClientController::class, 'store']);
    Route::get('/clients/{client}', [ClientController::class, 'show']);
    Route::patch('/clients/{client}', [ClientController::class, 'update']);

    Route::get('/agenda', [AgendaController::class, 'index']);
    Route::get('/agenda/vencidas', [AgendaController::class, 'vencidas']);
    Route::get('/agenda/unavailabilities', [AgendaController::class, 'unavailabilities']);
    Route::get('/operators', [OperatorController::class, 'index']);
    Route::get('/team', [OperatorController::class, 'team']);
    Route::get('/branches', [OperatorController::class, 'branches']);

    Route::get('/services', [ServiceController::class, 'index']);
    Route::post('/bookings', [BookingController::class, 'store']);
    Route::get('/bookings/{booking}', [BookingController::class,  'show']);
    Route::patch('/bookings/{booking}', [BookingController::class,  'update']);
    Route::get('/bookings/{booking}/payments', [PaymentController::class, 'index']);
    Route::post('/bookings/{booking}/payments', [PaymentController::class, 'store']);
    Route::get('/bookings/{booking}/process-notes', [BookingProcessNoteController::class, 'index']);
    Route::post('/bookings/{booking}/process-notes', [BookingProcessNoteController::class, 'store']);
    Route::patch('/bookings/{booking}/process-notes/{note}', [BookingProcessNoteController::class, 'update']);

    Route::get('/checkin/status', [CheckinController::class, 'status']);
    Route::post('/checkin', [CheckinController::class, 'checkin']);
    Route::post('/checkout', [CheckinController::class, 'checkout']);

    Route::get('/settings/booking', [SettingController::class, 'booking']);
    Route::get('/settings/photos', [SettingController::class, 'photos']);

    Route::get('/cash/session', [CashController::class, 'session']);
    Route::get('/cash/movement-types', [CashController::class, 'movementTypes']);
    Route::get('/cash/movements', [CashController::class, 'movements']);
    Route::post('/cash/sessions/{cashSession}/movements', [CashController::class, 'storeMovement']);

    // Tipos de orden activos — fuente de verdad desde DocumentSeries
    Route::get('/work-order-types', function () {
        static $map = [
            'orden_spa' => ['code' => 'spa',   'label' => 'SPA',         'icon' => 'content_cut'],
            'orden_hotel' => ['code' => 'hotel', 'label' => 'Hotel',       'icon' => 'hotel'],
            'orden_vet' => ['code' => 'vet',   'label' => 'Veterinaria', 'icon' => 'medical_services'],
        ];

        return response()->json(
            DocumentSeries::where('is_active', true)
                ->where('document_type', 'LIKE', 'orden_%')
                ->orderBy('document_type')
                ->get()
                ->map(fn ($s) => $map[$s->document_type] ?? [
                    'code' => str_replace('orden_', '', $s->document_type),
                    'label' => ucfirst(str_replace('orden_', '', $s->document_type)),
                    'icon' => 'work',
                ])
                ->unique('code')
                ->values()
        );
    });

    // Historial de citas/reservaciones de una mascota (todos los modelos de negocio)
    Route::get('/pets/{pet}/bookings', function (Pet $pet) {
        // SPA
        $spa = SpaBooking::where('pet_id', $pet->id)
            ->with(['services.service'])
            ->orderByDesc('scheduled_at')
            ->get()
            ->map(fn ($b) => [
                'id' => $b->id,
                'model_type' => 'spa',
                'fecha' => $b->scheduled_at->format('Y-m-d'),
                'fecha_iso' => $b->scheduled_at->toISOString(),
                'status' => $b->status,
                'order_folio' => $b->order_folio,
                'descripcion' => $b->services->map(fn ($s) => $s->service?->name ?? '—')->filter()->join(' · ') ?: '—',
                'total' => (float) ($b->total_estimated_price ?? $b->services->sum('current_price')),
            ]);

        // Hotel
        $hotel = HotelReservation::where('pet_id', $pet->id)
            ->orderByDesc('start_at')
            ->get()
            ->map(fn ($h) => [
                'id' => $h->id,
                'model_type' => 'hotel',
                'fecha' => $h->start_at->format('Y-m-d'),
                'fecha_iso' => $h->start_at->toISOString(),
                'status' => $h->status,
                'order_folio' => $h->order_folio,
                'descripcion' => 'Entrada '.$h->start_at->format('d/m').
                                 ($h->end_at ? ' → Salida '.$h->end_at->format('d/m') : ''),
                'total' => 0,
            ]);

        return response()->json(
            $spa->concat($hotel)->sortByDesc('fecha_iso')->values()
        );
    });

    Route::get('/payment-methods', function () {
        return PaymentMethod::where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn ($m) => [
                'code' => $m->code,
                'name' => $m->name,
                'type' => $m->type,
                'requires_reference' => (bool) $m->requires_reference,
                'icon' => match ($m->type) {
                    'cash' => 'payments',
                    'card' => 'credit_card',
                    'transfer' => 'account_balance',
                    'crypto' => 'currency_bitcoin',
                    'gateway' => 'link',
                    default => 'payment',
                },
                'dest' => $m->type === 'cash' ? 'caja' : 'banco',
            ]);
    });
});
