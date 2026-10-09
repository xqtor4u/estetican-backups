<?php

namespace App\Http\Controllers;

use App\Domain\Planning\Series\BookingSeriesService;
use App\Domain\Planning\Series\SeriesLifecycleService;
use App\Models\SpaBooking;
use App\Models\SpaBookingSeries;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * ZEUS-047 Fase 3: Agenda → Series recurrentes. Cola de series con pestañas Activas / En pausa /
 * Terminan este mes / Terminadas, y por serie: pausar por rango, cancelar de aquí en adelante y
 * extender la vigencia. Todo detrás de `agenda.series_recurrentes`.
 */
class SpaBookingSeriesController extends Controller
{
    public const TABS = [
        'activas' => 'Activas',
        'pausa' => 'En pausa',
        'terminan' => 'Terminan este mes',
        'terminadas' => 'Terminadas',
    ];

    public function __construct(
        private readonly SeriesLifecycleService $lifecycle,
        private readonly BookingSeriesService $seriesService,
    ) {}

    public function index(Request $request): View
    {
        $tab = array_key_exists($request->query('tab'), self::TABS) ? $request->query('tab') : 'activas';

        $series = SpaBookingSeries::query()
            ->tab($tab)
            ->with(['pet:id,name,client_id', 'pet.client:id,first_name,apellido_paterno,apellido_materno', 'branch:id,name'])
            ->withCount(['bookings as remaining_count' => fn ($q) => $q->where('scheduled_at', '>=', now())
                ->whereNotIn('status', [...SpaBooking::NOT_PERFORMED_STATUSES, 'completed'])])
            ->withCount(['bookings as pending_pin_count' => fn ($q) => $q->seriesTentative()])
            ->orderBy($tab === 'terminadas' ? 'updated_at' : 'ends_on', $tab === 'terminadas' ? 'desc' : 'asc')
            ->paginate(30)
            ->withQueryString();

        $counts = collect(self::TABS)->map(fn ($label, $key) => SpaBookingSeries::query()->tab($key)->count());

        return view('agenda.series.index', [
            'series' => $series,
            'tab' => $tab,
            'tabs' => self::TABS,
            'counts' => $counts,
        ]);
    }

    public function show(SpaBookingSeries $series): View
    {
        $series->load(['pet.client', 'branch:id,name', 'createdBy:id,name,first_name,apellido_paterno', 'events.user:id,name,first_name,apellido_paterno']);

        $upcoming = $series->bookings()
            ->with(['operator:id,name,first_name,apellido_paterno', 'seriesConfirmedBy:id,name,first_name,apellido_paterno'])
            ->where('scheduled_at', '>=', today())
            ->get();

        $past = $series->bookings()
            ->where('scheduled_at', '<', today())
            ->reorder('scheduled_at', 'desc')
            ->limit(20)
            ->get();

        return view('agenda.series.show', compact('series', 'upcoming', 'past'));
    }

    public function pause(Request $request, SpaBookingSeries $series): RedirectResponse
    {
        $validated = $request->validate([
            'paused_from' => 'required|date|after_or_equal:today',
            'paused_until' => 'required|date|after_or_equal:paused_from',
        ], [], ['paused_from' => 'desde', 'paused_until' => 'hasta']);

        if (! $series->isOpen()) {
            return back()->with('error', 'Solo se puede pausar una serie activa.');
        }

        $kept = $this->lifecycle->pause($series, Carbon::parse($validated['paused_from']), Carbon::parse($validated['paused_until']), $request->user());

        return $this->done($series, 'Serie en pausa del '.Carbon::parse($validated['paused_from'])->format('d/m/Y').' al '.Carbon::parse($validated['paused_until'])->format('d/m/Y').'.', $kept);
    }

    public function cancel(Request $request, SpaBookingSeries $series): RedirectResponse
    {
        $validated = $request->validate([
            'cancel_from' => 'required|date|after_or_equal:today',
        ], [], ['cancel_from' => 'a partir del']);

        if ($series->status === SpaBookingSeries::STATUS_CANCELLED) {
            return back()->with('error', 'La serie ya está cancelada.');
        }

        $from = Carbon::parse($validated['cancel_from']);
        $kept = $this->lifecycle->cancelFrom($series, $from, $request->user());

        return $this->done($series, 'Serie cancelada a partir del '.$from->format('d/m/Y').'.', $kept);
    }

    public function extend(Request $request, SpaBookingSeries $series): RedirectResponse
    {
        $validated = $request->validate([
            'ends_on' => ['required', 'date', 'after:'.$series->ends_on->toDateString(), 'before_or_equal:'.today()->addYears(2)->toDateString()],
        ], [
            'ends_on.after' => 'La nueva vigencia debe ser posterior al '.$series->ends_on->format('d/m/Y').'.',
        ], ['ends_on' => 'nueva vigencia']);

        if ($series->status === SpaBookingSeries::STATUS_CANCELLED) {
            return back()->with('error', 'Una serie cancelada no se puede extender.');
        }

        $oldEndsOn = $series->ends_on->copy();
        try {
            $created = $this->seriesService->extend($series, Carbon::parse($validated['ends_on']));
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }
        $series->refresh();
        $this->lifecycle->logExtension($series, $request->user(), $oldEndsOn, $created);

        return redirect()->route('agenda.series.show', $series)
            ->with('success', "Vigencia extendida al {$series->ends_on->format('d/m/Y')}: {$created} citas nuevas, todas por fijar.");
    }

    /** @param Collection<int, SpaBooking> $kept */
    private function done(SpaBookingSeries $series, string $message, Collection $kept): RedirectResponse
    {
        $redirect = redirect()->route('agenda.series.show', $series)->with('success', $message);

        if ($kept->isNotEmpty()) {
            $list = $kept->map(fn (SpaBooking $b) => $b->scheduled_at->format('d/m/Y H:i'))->implode(', ');
            $redirect->with('warning', "No se tocaron {$kept->count()} citas con presupuesto, pago o ya en proceso ({$list}). Revísalas a mano.");
        }

        return $redirect;
    }
}
