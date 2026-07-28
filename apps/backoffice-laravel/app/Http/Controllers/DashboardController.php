<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\HotelReservation;
use App\Models\Pet;
use App\Models\SpaBooking;
use App\Models\CashLedger;
use App\Models\BankLedger;
use App\Support\SystemSettings\SystemSettings;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        $today = Carbon::today();

        // Etiquetas de fecha para la vista
        $dayName       = $today->locale('es')->isoFormat('dddd');
        $dateFormatted = $today->locale('es')->isoFormat('D [de] MMMM [de] YYYY');

        // Citas SPA de hoy
        $spaToday = SpaBooking::whereDate('scheduled_at', $today)
            ->with('pet.client')
            ->orderBy('scheduled_at')
            ->get();

        $spaCounts = [
            'total'      => $spaToday->count(),
            'scheduled'  => $spaToday->where('status', 'scheduled')->count(),
            'work_order' => $spaToday->where('status', 'work_order')->count(),
            'completed'  => $spaToday->where('status', 'completed')->count(),
            'cancelled'  => $spaToday->where('status', 'cancelled')->count(),
        ];

        // Hotel: reservaciones activas hoy
        $hotelModuleEnabled = (bool) app(SystemSettings::class)->all()['hotel_module_enabled'];
        $hotelActive = $hotelModuleEnabled
            ? HotelReservation::where('status', 'scheduled')
                ->whereDate('start_at', '<=', $today)
                ->whereDate('end_at', '>=', $today)
                ->count()
            : 0;

        // Clientes y mascotas totales
        $totalClients = Client::count();
        $totalPets    = Pet::visible()->count();

        // Ingresos del día (ambos ledgers si existen)
        $cashToday = 0;
        $bankToday = 0;

        if (class_exists(\App\Models\CashLedger::class)) {
            $cashToday = \App\Models\CashLedger::whereDate('created_at', $today)->sum('amount');
        }
        if (class_exists(\App\Models\BankLedger::class)) {
            $bankToday = \App\Models\BankLedger::whereDate('created_at', $today)->sum('amount');
        }

        $incomeToday = $cashToday + $bankToday;

        // Próximas citas SPA (las 5 siguientes)
        $upcomingBookings = SpaBooking::where('scheduled_at', '>=', now())
            ->whereIn('status', ['scheduled', 'work_order'])
            ->with('pet.client')
            ->orderBy('scheduled_at')
            ->limit(5)
            ->get();

        return view('dashboard.index', compact(
            'spaCounts',
            'hotelActive',
            'hotelModuleEnabled',
            'totalClients',
            'totalPets',
            'incomeToday',
            'cashToday',
            'bankToday',
            'upcomingBookings',
            'today',
            'dayName',
            'dateFormatted'
        ));
    }
}
