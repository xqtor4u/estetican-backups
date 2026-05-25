<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

class ActivityLogController extends Controller
{
    private const LOG_NAMES = [
        'citas-spa'    => 'Citas SPA',
        'citas-hotel'  => 'Citas Hotel',
        'pagos'        => 'Pagos',
        'presupuestos' => 'Presupuestos',
        'mascotas'     => 'Mascotas',
        'usuarios'     => 'Usuarios',
        'configuracion' => 'Configuración',
    ];

    public function index(Request $request)
    {
        $query = Activity::with('causer')
            ->latest();

        if ($request->filled('log')) {
            $query->where('log_name', $request->input('log'));
        }

        if ($request->filled('event')) {
            $query->where('event', $request->input('event'));
        }

        if ($request->filled('causer')) {
            $query->where('causer_id', $request->input('causer'))
                  ->where('causer_type', \App\Models\User::class);
        }

        if ($request->filled('date')) {
            $query->whereDate('created_at', $request->input('date'));
        }

        $activities = $query->paginate(50)->withQueryString();

        $users = \App\Models\User::orderBy('name')->get(['id', 'name', 'email']);

        return view('activity-log.index', [
            'activities' => $activities,
            'logNames'   => self::LOG_NAMES,
            'users'      => $users,
            'filters'    => $request->only(['log', 'event', 'causer', 'date']),
        ]);
    }
}
