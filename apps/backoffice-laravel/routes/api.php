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

    Route::get('/pets/{pet}/bookings', function (\App\Models\Pet $pet) {
        $bookings = \App\Models\SpaBooking::where('pet_id', $pet->id)
            ->with(['services.service'])
            ->orderByDesc('scheduled_at')
            ->get();

        return response()->json($bookings->map(function ($b) {
            $payments = \App\Models\Payment::where('payable_type', \App\Models\SpaBooking::class)
                ->where('payable_id', $b->id)
                ->orderBy('created_at')
                ->get();

            return [
                'id'          => $b->id,
                'fecha'       => $b->scheduled_at->format('Y-m-d'),
                'fecha_iso'   => $b->scheduled_at->toISOString(),
                'status'      => $b->status,
                'order_folio' => $b->order_folio,
                'services'    => $b->services->map(fn ($s) => [
                    'name'             => $s->service?->name ?? '—',
                    'price'            => (float) ($s->current_price ?? 0),
                    'duration_minutes' => $s->service?->duration_minutes,
                ])->values(),
                'payments'    => $payments->map(fn ($p) => [
                    'id'             => $p->id,
                    'amount'         => (float) $p->amount,
                    'payment_method' => $p->payment_method ?? 'N/A',
                    'category'       => $p->category,
                    'destination'    => $p->destination,
                    'created_at'     => $p->created_at->format('Y-m-d H:i:s'),
                ])->values(),
            ];
        }));
    });

    Route::get('/payment-methods', function () {
        return \App\Models\PaymentMethod::where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn ($m) => [
                'code'               => $m->code,
                'name'               => $m->name,
                'type'               => $m->type,
                'requires_reference' => (bool) $m->requires_reference,
                'icon'               => match ($m->type) {
                    'cash'     => 'payments',
                    'card'     => 'credit_card',
                    'transfer' => 'account_balance',
                    'crypto'   => 'currency_bitcoin',
                    'gateway'  => 'link',
                    default    => 'payment',
                },
                'dest' => $m->type === 'cash' ? 'caja' : 'banco',
            ]);
    });
});
