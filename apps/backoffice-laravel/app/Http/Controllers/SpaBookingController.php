<?php

namespace App\Http\Controllers;

use App\Domain\Accounting\Contracts\AccountingServiceInterface;
use App\Domain\Clinical\Services\VaccinationEligibilityChecker;
use App\Domain\Commercial\Contracts\QuoteServiceInterface;
use App\Domain\Inventory\Contracts\BookingStockConsumptionServiceInterface;
use App\Domain\Planning\Contracts\BookingServiceInterface;
use App\Domain\Planning\Services\OperatorAvailabilityChecker;
use App\Domain\Planning\Services\ServiceLineActionService;
use App\Domain\Resources\Contracts\ResourceAllocationServiceInterface;
use App\Mail\ServiceSummaryMail;
use App\Models\Client;
use App\Models\Group;
use App\Models\HotelReservation;
use App\Models\Item;
use App\Models\Operator;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Pet;
use App\Models\Quote;
use App\Models\Resource;
use App\Models\Service;
use App\Models\SpaBooking;
use App\Models\SpaBookingService;
use App\Support\Geo\CoverageChecker;
use App\Support\Pages\AgendaPage;
use App\Support\Search\TokenSearch;
use App\Support\SystemSettings\BusinessHours;
use App\Support\SystemSettings\SystemSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use RuntimeException;

class SpaBookingController extends Controller
{
    public function __construct(
        private BookingServiceInterface $bookingService,
        private ResourceAllocationServiceInterface $resourceAllocationService,
        private QuoteServiceInterface $quoteService,
        private BusinessHours $businessHours,
        private OperatorAvailabilityChecker $operatorAvailabilityChecker,
        private CoverageChecker $coverageChecker,
        private VaccinationEligibilityChecker $vaccinationChecker
    ) {}

    public function index(Request $request): View
    {
        $page = AgendaPage::index();
        $search = trim((string) $request->query('search', ''));
        $statusTouched = $request->has('status_touched');
        $rawStatuses = (array) $request->query('status', []);
        $validStatuses = ['scheduled', 'work_order', 'completed', 'cancelled', 'no_show', 'unfulfillable'];
        $statuses = ! $statusTouched
            ? ['scheduled', 'work_order', 'completed']
            : array_values(array_intersect($rawStatuses, $validStatuses));
        $dateScope = (string) $request->query('date_scope', 'today');
        $calView = (string) $request->query('cal_view', 'day');

        if (! in_array($dateScope, ['today', 'tomorrow', 'custom', 'all', 'full'], true)) {
            $dateScope = 'today';
        }

        if (! in_array($calView, ['day', 'week', 'month'], true)) {
            $calView = 'day';
        }

        $sort = $request->query('sort', 'date');
        $direction = $request->query('direction') === 'desc' ? 'desc' : 'asc';

        if ($calView !== 'day') {
            return $this->indexCalendarRange($request, $calView, $statuses, $statusTouched, $search, $sort, $direction);
        }

        $selectedDate = $this->resolveOperationalDate((string) $request->query('date', ''), $dateScope);
        $selectedDateInput = $selectedDate->format('Y-m-d');

        // 1. Fetch SPA Bookings (Paginated for the table)
        $bookingsQuery = SpaBooking::query()
            ->with([
                'pet.client',
                'services.service',
                'quotes' => fn ($q) => $q->where('status', 'accepted')
                    ->with(['cashLedgers', 'bankLedgers']),
                'payments',
            ]);

        $this->applyBookingFilters($bookingsQuery, $statuses, $search);

        if ($dateScope === 'all') {
            if ($statuses !== [] && array_intersect($statuses, ['scheduled', 'work_order']) !== []) {
                $bookingsQuery->where('scheduled_at', '>=', now()->startOfDay());
            }
        } elseif (in_array($dateScope, ['today', 'tomorrow', 'custom'], true)) {
            $bookingsQuery->whereDate('scheduled_at', $selectedDate);
        }

        $bookingsQuery->select('spa_bookings.*');

        if ($sort === 'pet') {
            $bookingsQuery->join('pets', 'pets.id', '=', 'spa_bookings.pet_id')
                ->orderBy('pets.name', $direction);
        } elseif ($sort === 'client') {
            $bookingsQuery->join('pets', 'pets.id', '=', 'spa_bookings.pet_id')
                ->join('clients', 'clients.id', '=', 'pets.client_id')
                ->orderBy('clients.first_name', $direction)
                ->orderBy('clients.apellido_paterno', $direction)
                ->orderBy('clients.apellido_materno', $direction);
        } elseif ($sort === 'status') {
            $bookingsQuery->orderBy('spa_bookings.status', $direction);
        } elseif ($sort === 'total') {
            $bookingsQuery->orderBy('spa_bookings.total_estimated_price', $direction);
        } else {
            $bookingsQuery->orderBy('spa_bookings.scheduled_at', $direction);
        }

        $bookings = $bookingsQuery->paginate(15)->appends($request->query())->through(fn ($b) => $this->decorateBooking($b));

        // 2. Fetch Unifed Timeline (SPA + Hotel)
        // Query aparte de $bookingsQuery de arriba (no pasa por applyBookingFilters) —
        // necesita su propio ->visibleTo(), si no un operador restringido vería la agenda
        // completa en esta vista aunque la tabla paginada ya estuviera acotada.
        $spaForTimeline = SpaBooking::visibleTo($request->user())
            ->whereDate('scheduled_at', $selectedDate)
            ->whereIn('status', ['scheduled', 'work_order'])
            ->with(['pet.client', 'services.service'])
            ->get()
            ->map(function ($b) {
                $b = $this->decorateBooking($b);
                $b->agenda_type = 'spa';

                return $b;
            });

        $hotelModuleEnabled = (bool) app(SystemSettings::class)->all()['hotel_module_enabled'];

        $hotelForTimeline = $hotelModuleEnabled
            ? HotelReservation::whereDate('start_at', '<=', $selectedDate)
                ->whereDate('end_at', '>=', $selectedDate)
                ->where('status', 'scheduled')
                ->with('pet.client')
                ->get()
                ->map(function ($h) {
                    $h->agenda_type = 'hotel';
                    $h->scheduled_at = $h->start_at;

                    return $h;
                })
            : collect();

        $timelineBookings = $spaForTimeline->concat($hotelForTimeline)->sortBy('scheduled_at');

        // 3. Calculate Summary Stats
        $totalEstimatedMinutes = $spaForTimeline->sum('estimated_duration_minutes');
        $scheduledCount = $spaForTimeline->count();
        $estimatedRevenue = $spaForTimeline->sum('total_estimated_price');
        $petsWithAgenda = $timelineBookings->pluck('pet_id')->unique()->count();

        $firstScheduledAt = $timelineBookings->min('scheduled_at');
        $lastScheduledEndAt = $timelineBookings->where('agenda_type', 'spa')->max('estimated_end_at');

        $operationalDateLabel = $this->formatOperationalDateLabel($selectedDate, $dateScope);
        $agendaOverviewCount = $bookings->total() + $timelineBookings->where('agenda_type', 'hotel')->count();

        $blockedToday = $this->operatorAvailabilityChecker->unavailabilityWindows(
            $selectedDate->copy()->startOfDay(),
            $selectedDate->copy()->endOfDay(),
            $this->unavailabilityOperatorScope($request->user())
        );

        // Para el pop-up de acciones por servicio (reasignar operador). Solo para quien puede
        // ver la agenda de todos — un operador restringido no reasigna citas (la acción exige
        // `editar agenda`) y no debe recibir el directorio de compañeros embebido en la página.
        $operators = ($request->user()->is_super_admin || $request->user()->can('agenda.ver_todas'))
            ? Operator::where('is_active', true)->orderBy('name')->get(['id', 'name'])
            : collect();

        return view('agenda.index', compact(
            'page', 'bookings', 'timelineBookings', 'statuses', 'statusTouched', 'dateScope', 'calView',
            'selectedDate', 'selectedDateInput', 'operationalDateLabel', 'search',
            'totalEstimatedMinutes', 'scheduledCount', 'estimatedRevenue', 'petsWithAgenda',
            'firstScheduledAt', 'lastScheduledEndAt', 'sort', 'direction', 'agendaOverviewCount', 'hotelModuleEnabled',
            'blockedToday', 'operators'
        ));
    }

    /**
     * Único caso de disponibilidad que se puede forzar: el operador fuera de su horario
     * declarado. Conflicto de agenda, fuera de horario del negocio y vacaciones/permiso
     * siguen siendo bloqueos duros — no tiene sentido "forzar" una doble cita o un permiso.
     */
    private function canOverrideSchedule(): bool
    {
        $user = auth()->user();

        return (bool) ($user?->can('agenda.forzar_horario') || $user?->is_super_admin);
    }

    public function checkAvailability(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'operator_id' => 'required|integer|exists:operators,id',
            'scheduled_at' => 'required|date',
            'duration_minutes' => 'nullable|integer|min:1',
            'exclude_booking_id' => 'nullable|integer',
        ]);

        $scheduledAt = Carbon::parse($validated['scheduled_at']);
        $duration = (int) ($validated['duration_minutes'] ?? 30);
        $operatorId = (int) $validated['operator_id'];
        $excludeId = $validated['exclude_booking_id'] ?? null;

        $hardBlockReason = match (true) {
            ! $this->businessHours->isWithin($scheduledAt) => 'Fuera del horario operativo del negocio ('.$this->businessHours->openingTime().'–'.$this->businessHours->closingTime().').',
            $this->operatorAvailabilityChecker->hasConflict($operatorId, $scheduledAt, $duration, $excludeId) => 'El operador ya tiene una cita en ese horario.',
            $this->operatorAvailabilityChecker->hasTimeOff($operatorId, $scheduledAt, $duration) => 'El operador no está disponible en ese periodo (vacaciones/permiso).',
            default => null,
        };

        $outsideSchedule = $hardBlockReason === null
            && $this->operatorAvailabilityChecker->isOutsideWorkingHours($operatorId, $scheduledAt, $duration);

        $reason = $hardBlockReason ?? ($outsideSchedule ? 'El operador no labora en el horario indicado.' : null);

        return response()->json([
            'available' => $reason === null,
            'reason' => $reason,
            'overridable' => $outsideSchedule,
            'can_override' => $outsideSchedule && $this->canOverrideSchedule(),
            'operator_name' => Operator::whereKey($operatorId)->value('name'),
            'day_summary' => $this->operatorAvailabilityChecker->daySummaryFor($operatorId, $scheduledAt),
        ]);
    }

    private function applyBookingFilters($query, array $statuses, string $search): void
    {
        // Único punto usado tanto por la vista de tabla (index) como por la de
        // calendario semana/mes (buildCalendarRange) — acota acá para no repetirlo.
        $query->visibleTo(auth()->user());

        if ($statuses !== []) {
            $query->whereIn('status', $statuses);
        }

        if ($search !== '') {
            TokenSearch::apply($query, $search, [
                'pet.name', 'pet.client.first_name', 'pet.client.apellido_paterno', 'pet.client.apellido_materno',
            ]);
        }
    }

    private function indexCalendarRange(Request $request, string $calView, array $statuses, bool $statusTouched, string $search, string $sort, string $direction): View
    {
        $page = AgendaPage::index();
        $anchorDate = $this->parseDateOrToday((string) $request->query('date', ''));
        $monthAnchor = null;

        if ($calView === 'week') {
            $rangeStart = $anchorDate->copy()->startOfWeek(Carbon::MONDAY);
            $rangeEnd = $rangeStart->copy()->addDays(6)->endOfDay();
        } else {
            $monthAnchor = $anchorDate->copy()->startOfMonth();
            $rangeStart = $monthAnchor->copy()->startOfWeek(Carbon::MONDAY);
            $rangeEnd = $monthAnchor->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY)->endOfDay();
        }

        [$calendarDays, $rangeStats] = $this->buildCalendarRange($rangeStart, $rangeEnd, $statuses, $search, $monthAnchor);

        $selectedDate = $anchorDate;
        $selectedDateInput = $anchorDate->format('Y-m-d');
        $operationalDateLabel = $calView === 'week'
            ? 'Semana del '.$rangeStart->translatedFormat('d M').' al '.$rangeEnd->translatedFormat('d M Y')
            : $anchorDate->translatedFormat('F Y');

        $bookings = null;
        $timelineBookings = collect();
        $agendaOverviewCount = $rangeStats['scheduledCount'] + $rangeStats['hotelCount'];
        $hotelModuleEnabled = (bool) app(SystemSettings::class)->all()['hotel_module_enabled'];

        return view('agenda.index', [
            'page' => $page, 'calView' => $calView, 'statuses' => $statuses, 'statusTouched' => $statusTouched, 'dateScope' => 'custom',
            'search' => $search, 'selectedDate' => $selectedDate, 'selectedDateInput' => $selectedDateInput,
            'operationalDateLabel' => $operationalDateLabel,
            'calendarDays' => $calendarDays, 'rangeStart' => $rangeStart, 'rangeEnd' => $rangeEnd,
            'bookings' => $bookings, 'timelineBookings' => $timelineBookings,
            'totalEstimatedMinutes' => $rangeStats['totalEstimatedMinutes'],
            'scheduledCount' => $rangeStats['scheduledCount'],
            'estimatedRevenue' => $rangeStats['estimatedRevenue'],
            'petsWithAgenda' => $rangeStats['petsWithAgenda'],
            'firstScheduledAt' => $rangeStats['firstScheduledAt'],
            'lastScheduledEndAt' => $rangeStats['lastScheduledEndAt'],
            'agendaOverviewCount' => $agendaOverviewCount,
            'sort' => $sort, 'direction' => $direction,
            'hotelModuleEnabled' => $hotelModuleEnabled,
        ]);
    }

    /**
     * @return array{0: array<int, array{date: Carbon, items: Collection, is_today: bool, is_outside_month: bool}>, 1: array<string, mixed>}
     */
    private function buildCalendarRange(Carbon $rangeStart, Carbon $rangeEnd, array $statuses, string $search, ?Carbon $monthAnchor): array
    {
        $spaQuery = SpaBooking::query()
            ->whereBetween('scheduled_at', [$rangeStart, $rangeEnd])
            ->with(['pet.client', 'services.service']);
        $this->applyBookingFilters($spaQuery, $statuses, $search);

        $spaBookings = $spaQuery->get()->map(function ($b) {
            $b = $this->decorateBooking($b);
            $b->agenda_type = 'spa';

            return $b;
        });

        $hotelModuleEnabled = (bool) app(SystemSettings::class)->all()['hotel_module_enabled'];
        $hotelReservations = collect();

        if ($hotelModuleEnabled) {
            $hotelQuery = HotelReservation::query()
                ->whereDate('start_at', '<=', $rangeEnd)
                ->whereDate('end_at', '>=', $rangeStart)
                ->where('status', 'scheduled')
                ->with('pet.client');

            if ($search !== '') {
                TokenSearch::apply($hotelQuery, $search, [
                    'pet.name', 'pet.client.first_name', 'pet.client.apellido_paterno', 'pet.client.apellido_materno',
                ]);
            }

            $hotelReservations = $hotelQuery->get()->map(function ($h) {
                $h->agenda_type = 'hotel';
                $h->scheduled_at = $h->start_at;

                return $h;
            });
        }

        $itemsByDate = [];
        foreach ($spaBookings as $b) {
            $itemsByDate[$b->scheduled_at->format('Y-m-d')][] = $b;
        }
        foreach ($hotelReservations as $h) {
            $cursor = $h->start_at->copy()->startOfDay()->max($rangeStart->copy()->startOfDay());
            $stop = $h->end_at->copy()->startOfDay()->min($rangeEnd->copy()->startOfDay());
            while ($cursor->lte($stop)) {
                $itemsByDate[$cursor->format('Y-m-d')][] = $h;
                $cursor->addDay();
            }
        }
        foreach ($itemsByDate as $key => $items) {
            usort($items, fn ($a, $b) => $a->scheduled_at <=> $b->scheduled_at);
            $itemsByDate[$key] = collect($items);
        }

        $blockedWindows = $this->operatorAvailabilityChecker->unavailabilityWindows($rangeStart, $rangeEnd, $this->unavailabilityOperatorScope(auth()->user()));
        $blockedByDate = [];
        foreach ($blockedWindows as $w) {
            $cursor = $w->starts_at->copy()->startOfDay()->max($rangeStart->copy()->startOfDay());
            $stop = $w->ends_at->copy()->startOfDay()->min($rangeEnd->copy()->startOfDay());
            while ($cursor->lte($stop)) {
                $blockedByDate[$cursor->format('Y-m-d')][] = $w;
                $cursor->addDay();
            }
        }

        $calendarDays = [];
        $cursor = $rangeStart->copy()->startOfDay();
        $stop = $rangeEnd->copy()->startOfDay();
        while ($cursor->lte($stop)) {
            $key = $cursor->format('Y-m-d');
            $calendarDays[] = [
                'date' => $cursor->copy(),
                'items' => $itemsByDate[$key] ?? collect(),
                'blocked' => collect($blockedByDate[$key] ?? []),
                'is_today' => $cursor->isToday(),
                'is_outside_month' => $monthAnchor ? ! $cursor->isSameMonth($monthAnchor) : false,
            ];
            $cursor->addDay();
        }

        $allItems = $spaBookings->concat($hotelReservations);
        $stats = [
            'totalEstimatedMinutes' => $spaBookings->sum('estimated_duration_minutes'),
            'scheduledCount' => $spaBookings->count(),
            'hotelCount' => $hotelReservations->count(),
            'estimatedRevenue' => $spaBookings->sum('total_estimated_price'),
            'petsWithAgenda' => $allItems->pluck('pet_id')->unique()->count(),
            'firstScheduledAt' => $allItems->min('scheduled_at'),
            'lastScheduledEndAt' => $spaBookings->max('estimated_end_at'),
        ];

        return [$calendarDays, $stats];
    }

    /**
     * `operator_id` a forzar en `unavailabilityWindows()` para un usuario sin
     * `agenda.ver_todas`: el suyo, o un id que nunca existe (-1) si no tiene operador
     * vinculado — sin esto vería los bloqueos de disponibilidad de todos los operadores en
     * la vista de agenda, aunque las citas mismas ya estén acotadas.
     */
    private function unavailabilityOperatorScope($user): ?int
    {
        if ($user->is_super_admin || $user->can('agenda.ver_todas')) {
            return null;
        }

        return $user->operator_id ?: -1;
    }

    /**
     * 404 (no 403 — no confirmar existencia de una cita ajena, mismo criterio que la
     * auditoría IDOR de agosto) si el usuario no tiene `agenda.ver_todas`/no es super admin
     * y la cita no es suya. El binding implícito de ruta ya resolvió `$booking` sin scope,
     * así que se revalida acá antes de leer/tocar nada. Reusa el mismo scope que
     * `Api\BookingController`/`applyBookingFilters()`.
     */
    private function ensureVisible(SpaBooking $booking): void
    {
        abort_unless(SpaBooking::visibleTo(auth()->user())->whereKey($booking->id)->exists(), 404);
    }

    public function show(SpaBooking $booking): View
    {
        $this->ensureVisible($booking);
        $booking = $this->loadBookingContext($booking);
        $booking = $this->decorateBooking($booking);
        $page = AgendaPage::show($booking);

        $assignableResources = $this->loadAssignableResources();
        $operators = Operator::where('is_active', true)->orderBy('name')->get();
        $services = Service::where('is_active', true)->orderBy('name')->get();
        $items = Item::where('is_active', true)->orderBy('name')->get(['id', 'name', 'price']);
        $groups = Group::where('is_active', true)->with('components.service', 'components.item')->orderBy('name')->get();
        $groupsForQuoteManager = $groups->map(fn (Group $g) => [
            'id' => $g->id,
            'name' => $g->name,
            'components' => $g->components->map(fn ($c) => [
                'service_id' => $c->service_id,
                'item_id' => $c->item_id,
                'name' => $c->name(),
                'price' => $c->unitPrice(),
                'quantity' => (float) $c->quantity,
            ]),
        ]);
        $storeModuleEnabled = (bool) app(SystemSettings::class)->all()['store_module_enabled'];
        $paymentMethods = PaymentMethod::where('is_active', true)->orderBy('name')->get(['id', 'code', 'name', 'type']);

        return view('agenda.show', compact('page', 'booking', 'assignableResources', 'operators', 'services', 'items', 'groups', 'groupsForQuoteManager', 'storeModuleEnabled', 'paymentMethods'));
    }

    public function globalCreate(Request $request): View
    {
        $page = AgendaPage::create(null, null);

        $pets = Pet::visible()
            ->with('client')
            ->orderBy('name')
            ->get()
            ->map(fn ($pet) => [
                'id' => $pet->id,
                'name' => $pet->name,
                'species' => $pet->species ?? '',
                'client' => [
                    'id' => $pet->client?->id,
                    'first_name' => $pet->client?->first_name ?? '',
                    'last_name' => $pet->client?->last_name ?? '',
                ],
            ]);

        $hotelModuleEnabled = (bool) app(SystemSettings::class)->all()['hotel_module_enabled'];

        return view('agenda.global-create', compact('page', 'pets', 'hotelModuleEnabled'));
    }

    public function edit(SpaBooking $booking): View
    {
        $this->ensureVisible($booking);
        $page = AgendaPage::edit($booking);
        $booking->load(['services', 'pet.client', 'resourceAllocations']);

        $pet = $booking->pet;
        $client = $pet?->client;
        $services = Service::where('is_active', true)->orderBy('name')->get();
        $resources = Resource::whereIn('administrative_status', ['active', 'inactive'])->orderBy('code')->get();
        $assignedResourceId = $booking->resourceAllocations->firstWhere('allocation_type', 'reserved')?->resource_id;
        $operators = Operator::where('is_active', true)->orderBy('name')->get();
        $openingTime = $this->businessHours->openingTime();
        $closingTime = $this->businessHours->closingTime();

        return view('agenda.edit', compact('page', 'booking', 'services', 'resources', 'assignedResourceId', 'pet', 'client', 'operators', 'openingTime', 'closingTime'));
    }

    public function update(Request $request, SpaBooking $booking): RedirectResponse
    {
        $this->ensureVisible($booking);

        // Handle simple status updates (e.g. from Work Order)
        if ($request->has('status') && $request->input('status') === 'completed') {
            $booking->update(['status' => 'completed']);
            app(BookingStockConsumptionServiceInterface::class)->consume($booking, auth()->id());

            // SMTP Automated Messaging
            $settings = app(SystemSettings::class)->all();
            if (($settings['operational_auto_email_report'] ?? false) && $booking->pet->client->email && $booking->pet->client->receives_job_updates) {
                try {
                    Mail::to($booking->pet->client->email)
                        ->send(new ServiceSummaryMail($booking));
                } catch (\Exception $e) {
                    Log::error('Error sending service report email: '.$e->getMessage());
                }
            }

            // `cobrar=1` viene del pop-up de acciones rápidas ("Terminar y cobrar") de la agenda —
            // la ficha de destino abre directo el modal de liquidación.
            $showParams = $request->boolean('cobrar') ? ['booking' => $booking, 'cobrar' => 1] : $booking;

            return redirect()->route('agenda.show', $showParams)->with('success', 'Sesión finalizada con éxito. Se ha generado el estado de cuenta y enviado el reporte al cliente.');
        }

        // Mismo límite que el dominio (BookingService::rescheduleBooking): una cita ya
        // iniciada no se "reprograma" — antes esto fallaba en silencio (rescheduleBooking
        // devolvía false sin avisar) y la vista igual reportaba éxito.
        if ($booking->status !== 'scheduled') {
            return redirect()->back()->with('error', 'Solo se puede reprogramar una cita mientras está Programada.');
        }

        $validated = $request->validate([
            'scheduled_at' => 'required|date',
            'operator_id' => 'required|exists:operators,id',
            'resource_id' => 'nullable|exists:resources,id',
            'notes' => 'nullable|string',
            'services' => 'nullable|array',
            'services.*' => 'exists:services,id',
            'override_availability' => 'nullable|boolean',
        ]);

        $scheduledAt = Carbon::parse($validated['scheduled_at']);
        $durationMinutes = (int) $booking->services()->with('service')->get()
            ->sum(fn ($s) => $s->service?->suggested_duration_minutes ?? $s->service?->duration_minutes ?? 0);

        if (! $this->businessHours->isWithin($scheduledAt)) {
            return redirect()->back()->withInput()->with('error', "La hora elegida está fuera del horario operativo ({$this->businessHours->openingTime()}–{$this->businessHours->closingTime()}).");
        }

        if ($this->operatorAvailabilityChecker->hasConflict((int) $validated['operator_id'], $scheduledAt, $durationMinutes, $booking->id)) {
            return redirect()->back()->withInput()->with('error', 'El operador seleccionado ya tiene una cita en ese horario.');
        }

        $overrideSchedule = ! empty($validated['override_availability']) && $this->canOverrideSchedule();
        if (! $overrideSchedule && $this->operatorAvailabilityChecker->isOutsideWorkingHours((int) $validated['operator_id'], $scheduledAt, $durationMinutes)) {
            return redirect()->back()->withInput()->with('error', 'El operador seleccionado no labora en el horario indicado.');
        }

        if ($this->operatorAvailabilityChecker->hasTimeOff((int) $validated['operator_id'], $scheduledAt, $durationMinutes)) {
            return redirect()->back()->withInput()->with('error', 'El operador seleccionado no está disponible en ese periodo (vacaciones/permiso).');
        }

        $this->bookingService->rescheduleBooking($booking->id, $validated['scheduled_at'], $validated['notes'] ?? null, (int) $validated['operator_id']);

        // Sync services only when still in scheduled state (not yet a work order)
        if ($booking->status === 'scheduled' && $request->has('services')) {
            $serviceIds = array_filter((array) ($validated['services'] ?? []));
            $prices = Service::whereIn('id', $serviceIds)->pluck('price', 'id');
            $booking->services()->delete();
            foreach ($serviceIds as $serviceId) {
                $booking->services()->create([
                    'service_id' => $serviceId,
                    'current_price' => $prices[$serviceId] ?? 0,
                ]);
            }
        }

        if (array_key_exists('resource_id', $validated)) {
            if (empty($validated['resource_id'])) {
                $this->resourceAllocationService->releaseSourceAllocations($booking);
            } else {
                $durationMinutes = (int) $booking->services->sum(fn ($s) => $s->service?->suggested_duration_minutes ?? $s->service?->duration_minutes ?? 0);
                $cleanupBuffer = (int) config('backoffice.resources.cleaning_buffer_minutes', 30);

                $this->resourceAllocationService->syncResourceToSource(
                    (int) $validated['resource_id'],
                    $booking,
                    $booking->pet_id,
                    $validated['scheduled_at'],
                    $durationMinutes,
                    $cleanupBuffer
                );
            }
        }

        return redirect()->route('agenda.show', $booking)->with('success', 'Sesión actualizada correctamente.');
    }

    /**
     * "Iniciar cita" — promueve una cita Programada directo a Orden de Trabajo, sin pasar
     * por un presupuesto (paridad con la app móvil `MobCitaDet`). Abre el work_order:
     * genera el folio de orden si falta y arranca todas las líneas de servicio pendientes.
     */
    public function start(Request $request, SpaBooking $booking): RedirectResponse
    {
        $this->ensureVisible($booking);

        if ($booking->status !== 'scheduled') {
            return redirect()->route('agenda.show', $booking)->with('error', 'Solo se puede iniciar una cita que está Programada.');
        }

        if ($booking->services()->count() === 0) {
            return redirect()->route('agenda.show', $booking)->with('error', 'La cita no tiene servicios — agrega al menos uno antes de iniciarla.');
        }

        // `service_line_ids` (opcional): arranca solo esas líneas. Sin él, arranca todas las
        // pendientes — cuando la cita tiene varios servicios, el pop-up de la agenda deja
        // elegir cuál(es), en vez de forzar a que arranquen todos juntos (paridad con
        // `SYNC-042` del móvil). Las líneas no elegidas quedan pendientes en la orden de trabajo.
        $lineIds = array_values(array_filter((array) $request->input('service_line_ids', []), 'is_numeric'));

        $pendingLines = $booking->services()->whereNull('started_at');
        if ($lineIds !== []) {
            $pendingLines->whereIn('id', $lineIds);
        }

        if ($lineIds !== [] && (clone $pendingLines)->count() === 0) {
            return redirect()->route('agenda.show', $booking)->with('error', 'No seleccionaste ningún servicio para iniciar.');
        }

        $booking->update(['status' => 'work_order']);

        if (! $booking->order_folio) {
            try {
                app(AccountingServiceInterface::class)->assignOrderFolio($booking);
            } catch (\Throwable) {
                // No interrumpir el cambio de estado si falla la asignación del folio.
            }
        }

        $pendingLines->update(['started_at' => now()]);

        return redirect()->route('agenda.show', $booking)->with('success', 'Cita iniciada — orden de trabajo abierta.');
    }

    public function cancel(Request $request, SpaBooking $booking): RedirectResponse
    {
        $this->ensureVisible($booking);
        $validated = $request->validate([
            'cancellation_reason' => 'required|string|max:500',
        ]);

        $this->bookingService->cancelBooking($booking->id, $validated['cancellation_reason']);

        return redirect()->route('agenda.index')->with('success', 'Sesión cancelada.');
    }

    public function markNoShow(Request $request, SpaBooking $booking): RedirectResponse
    {
        $this->ensureVisible($booking);
        $validated = $request->validate(['reason' => 'nullable|string|max:500']);

        $this->bookingService->markNoShow($booking->id, $validated['reason'] ?? null);

        return redirect()->route('agenda.index')->with('success', 'Sesión marcada como No se presentó.');
    }

    public function markUnfulfillable(Request $request, SpaBooking $booking): RedirectResponse
    {
        $this->ensureVisible($booking);
        $validated = $request->validate(['reason' => 'nullable|string|max:500']);

        $ok = $this->bookingService->markUnfulfillable($booking->id, $validated['reason'] ?? null);

        if (! $ok) {
            return redirect()->route('agenda.show', $booking)->with('error', 'No se pudo marcar la sesión como no realizada.');
        }

        return redirect()->route('agenda.show', $booking)->with('success', 'Sesión marcada como No realizada.');
    }

    public function createForClientPet(Client $client, Pet $pet): View
    {
        return $this->createForPet($pet, $client);
    }

    public function storeForClientPet(Request $request, Client $client, Pet $pet): RedirectResponse
    {
        return $this->storeForPet($request, $pet);
    }

    public function createForPet(Pet $pet, ?Client $client = null): View
    {
        $pet->load('client');
        $client = $client ?? $pet->client;

        $page = AgendaPage::create($pet, $client);

        $services = Service::where('is_active', true)->orderBy('name')->get();

        $resources = Resource::whereIn('administrative_status', ['active', 'inactive'])
            ->where('resource_type', 'cage')
            ->with('branch:id,name')
            ->orderBy('code')
            ->get();

        $upcomingBookings = SpaBooking::where('pet_id', $pet->id)
            ->where('scheduled_at', '>=', now())
            ->whereIn('status', ['scheduled', 'work_order'])
            ->with('services.service')
            ->orderBy('scheduled_at')
            ->get();

        $resourceCleaningBufferMinutes = (int) config('backoffice.resources.cleaning_buffer_minutes', 30);
        $operators = Operator::where('is_active', true)->orderBy('name')->get();
        $openingTime = $this->businessHours->openingTime();
        $closingTime = $this->businessHours->closingTime();

        // isRootView = true si viene de /pets/{pet}/... sin cliente en la URL
        $isRootView = ! request()->route('client');
        $returnViewMode = 'bookings';

        return view('agenda.create', compact(
            'page', 'pet', 'client', 'services', 'resources',
            'upcomingBookings', 'resourceCleaningBufferMinutes',
            'isRootView', 'returnViewMode', 'operators', 'openingTime', 'closingTime'
        ));
    }

    public function storeForPet(Request $request, Pet $pet): RedirectResponse
    {
        $validated = $request->validate([
            'scheduled_at' => 'required|date',
            // El operador se elige por servicio; `operator_id` (el "responsable") es opcional —
            // si no viene, se toma el del primer servicio que tenga uno asignado.
            'operator_id' => 'nullable|exists:operators,id',
            'resource_id' => 'nullable|exists:resources,id',
            'notes' => 'nullable|string',
            'services' => 'required|array',
            'services.*' => 'exists:services,id',
            'service_prices' => 'nullable|array',
            'service_prices.*' => 'nullable|numeric|min:0',
            'service_operators' => 'nullable|array',
            'service_operators.*' => 'nullable|exists:operators,id',
            'service_durations' => 'nullable|array',
            'service_durations.*' => 'nullable|integer|min:5|max:480',
            'override_availability' => 'nullable|boolean',
        ]);

        // El precio sugerido del catálogo es editable en el formulario (service_prices[]);
        // si no viene uno explícito para un servicio, cae al precio del catálogo.
        $servicesWithPrices = [];
        $servicesData = Service::whereIn('id', $validated['services'])->get();
        $enteredPrices = $validated['service_prices'] ?? [];
        foreach ($servicesData as $service) {
            $entered = $enteredPrices[$service->id] ?? null;
            $servicesWithPrices[$service->id] = $entered !== null && $entered !== ''
                ? (float) $entered
                : ($service->suggested_price ?? $service->price ?? 0);
        }

        // Cada servicio resuelve su propio operador; el "responsable" de la cita es el
        // `operator_id` que mandó el formulario o, si no vino, el del primer servicio con
        // uno asignado. Se valida la disponibilidad en secuencia — mismo criterio que el
        // agendado móvil (Api\BookingController::store).
        $svcOperators = $validated['service_operators'] ?? [];
        $svcDurations = $validated['service_durations'] ?? [];

        $globalOperatorId = (int) ($validated['operator_id']
            ?? collect($validated['services'])
                ->map(fn ($serviceId) => $svcOperators[$serviceId] ?? null)
                ->first(fn ($op) => ! empty($op)));

        if ($globalOperatorId === 0) {
            return redirect()->back()->withInput()->with('error', 'Asigna un operador a al menos un servicio.');
        }

        $lineOperators = [];
        $lines = [];
        foreach ($servicesData as $service) {
            $lineOperators[$service->id] = (int) ($svcOperators[$service->id] ?? $globalOperatorId);
            $lines[] = [
                'operator_id' => $lineOperators[$service->id],
                'duration_minutes' => (int) ($svcDurations[$service->id] ?? $service->suggested_duration_minutes ?? $service->duration_minutes ?? 30),
                'service_name' => $service->name,
            ];
        }
        $durationMinutes = (int) array_sum(array_column($lines, 'duration_minutes'));

        $scheduledAt = Carbon::parse($validated['scheduled_at']);

        if (! $this->businessHours->isWithin($scheduledAt)) {
            return redirect()->back()->withInput()->with('error', "La hora elegida está fuera del horario operativo ({$this->businessHours->openingTime()}–{$this->businessHours->closingTime()}).");
        }

        // Guard de calificación: el operador de cada línea debe tener el rol que exige su servicio.
        if ($servicesData->contains(fn ($s) => $s->operator_role_id !== null)) {
            $operatorsById = Operator::whereIn('id', array_values(array_unique($lineOperators)))->with('roles')->get()->keyBy('id');
            foreach ($servicesData as $service) {
                if ($service->operator_role_id === null) {
                    continue;
                }
                $operator = $operatorsById->get($lineOperators[$service->id]);
                if (! $operator || ! $operator->activeRoles()->contains('id', $service->operator_role_id)) {
                    $operatorName = $operator?->full_name ?? 'El operador';

                    return redirect()->back()->withInput()->with('error', "{$operatorName} no está calificado para {$service->name}.");
                }
            }
        }

        $overrideSchedule = ! empty($validated['override_availability']) && $this->canOverrideSchedule();

        if ($error = $this->operatorAvailabilityChecker->validateSequentialAssignments($scheduledAt, $lines, $overrideSchedule)) {
            return redirect()->back()->withInput()->with('error', $error);
        }

        $booking = $this->bookingService->scheduleSpaSession(
            $pet->id,
            $validated['scheduled_at'],
            $servicesWithPrices,
            $validated['notes'] ?? null,
            $globalOperatorId
        );

        // Operador por línea + duración total real de la cita.
        foreach ($booking->services as $line) {
            if (isset($lineOperators[$line->service_id])) {
                $line->forceFill(['operator_id' => $lineOperators[$line->service_id]])->save();
            }
        }
        $booking->forceFill(['duration_minutes' => $durationMinutes])->save();

        if (! empty($validated['resource_id'])) {
            $cleanupBuffer = (int) config('backoffice.resources.cleaning_buffer_minutes', 30);

            $this->resourceAllocationService->assignResourceToSource(
                (int) $validated['resource_id'],
                $booking,
                $pet->id,
                $validated['scheduled_at'],
                $durationMinutes,
                $cleanupBuffer
            );
        }

        $coverageWarning = $this->coverageChecker->checkPet($pet);
        $vaccinationWarning = $this->vaccinationChecker->check($pet);

        $warnings = [];

        if ($coverageWarning) {
            $warnings[] = "Esta mascota está a {$coverageWarning['distance_km']} km de {$coverageWarning['branch_name']}, fuera del radio de cobertura de {$coverageWarning['radius_km']} km.";
        }

        if ($vaccinationWarning) {
            $vaccineList = implode(', ', $vaccinationWarning['missing_vaccines']);
            $warnings[] = "Esta mascota no tiene vigente: {$vaccineList}.";
        }

        if ($warnings !== []) {
            return redirect()->route('agenda.index')->with('success', 'Sesión programada correctamente.')->with(
                'warning',
                implode(' ', $warnings)
            );
        }

        return redirect()->route('agenda.index')->with('success', 'Sesión programada correctamente.');
    }

    private function resolveOperationalDate(string $dateParam, string $dateScope): Carbon
    {
        if ($dateScope === 'today') {
            return now()->startOfDay();
        }
        if ($dateScope === 'tomorrow') {
            return now()->addDay()->startOfDay();
        }
        if ($dateScope === 'all') {
            return now()->startOfDay();
        }
        if ($dateScope === 'full') {
            return now()->subYears(10)->startOfDay();
        } // Far past

        return $this->parseDateOrToday($dateParam);
    }

    private function parseDateOrToday(string $dateInput): Carbon
    {
        try {
            return Carbon::parse($dateInput)->startOfDay();
        } catch (\Throwable) {
            return now()->startOfDay();
        }
    }

    private function decorateBooking(SpaBooking $booking): SpaBooking
    {
        $estimatedDurationMinutes = (int) $booking->services->sum(function ($bookingService) {
            return (int) ($bookingService->service?->suggested_duration_minutes
                ?? $bookingService->service?->duration_minutes
                ?? 0);
        });

        $estimatedEndAt = $booking->scheduled_at?->copy()->addMinutes($estimatedDurationMinutes);

        $booking->setAttribute('estimated_duration_minutes', $estimatedDurationMinutes);
        $booking->setAttribute('estimated_end_at', $estimatedEndAt);
        $booking->setAttribute(
            'time_window_label',
            $booking->scheduled_at
                ? $booking->scheduled_at->format('H:i').($estimatedEndAt ? ' - '.$estimatedEndAt->format('H:i') : '')
                : null
        );
        $booking->setAttribute('alert_reason', $booking->alertReason($this->bookingGraceMinutes()));

        return $booking;
    }

    private ?int $bookingGraceMinutesCache = null;

    private function bookingGraceMinutes(): int
    {
        return $this->bookingGraceMinutesCache ??= (int) (app(SystemSettings::class)->all()['booking_grace_minutes'] ?? 15);
    }

    private function formatOperationalDateLabel(Carbon $selectedDate, string $dateScope): string
    {
        return match ($dateScope) {
            'today' => 'Hoy · '.$selectedDate->translatedFormat('d M Y'),
            'tomorrow' => 'Mañana · '.$selectedDate->translatedFormat('d M Y'),
            'all' => 'Próximas sesiones vigentes',
            'full' => 'Todo el historial operativo',
            default => 'Fecha elegida · '.$selectedDate->translatedFormat('d M Y'),
        };
    }

    private function loadBookingContext(SpaBooking $booking): SpaBooking
    {
        return tap($booking)->load([
            'pet.client',
            'pet.photos',
            'pet.primaryPhoto',
            'services.service',
            'services.group',
            'services.operator',
            'items.item',
            'items.group',
            'resourceAllocations.resource:id,branch_id,code,name,resource_type',
            'resourceAllocations.childAllocations',
            'executedServices:id,spa_booking_id,executed_at,final_price,operator_id,service_summary',
            'executedServices.operator:id,first_name,apellido_paterno,apellido_materno,name',
            'quotes.items.service',
            'quotes.items.item',
            'quotes.items.operator',
            'quotes.cashLedgers',
            'quotes.bankLedgers',
            'payments',
        ])->loadCount('quotes');
    }

    private function loadAssignableResources()
    {
        return Resource::query()
            ->select(['id', 'branch_id', 'resource_type', 'code', 'name', 'capacity_label', 'administrative_status', 'operational_status'])
            ->with('branch:id,name')
            ->where('resource_type', 'cage')
            ->whereIn('administrative_status', ['active', 'inactive'])
            ->orderBy('branch_id')
            ->orderBy('code')
            ->get();
    }

    private function calculateServiceDurationMinutes(iterable $services): int
    {
        $minutes = 0;

        foreach ($services as $service) {
            $minutes += (int) ($service?->suggested_duration_minutes ?? $service?->duration_minutes ?? 0);
        }

        return $minutes;
    }

    public function storeQuote(Request $request, SpaBooking $booking): RedirectResponse
    {
        $this->ensureVisible($booking);
        $validated = $request->validate([
            'version_label' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.service_id' => 'nullable|required_without:items.*.item_id|exists:services,id',
            'items.*.item_id' => 'nullable|required_without:items.*.service_id|exists:items,id',
            'items.*.group_id' => 'nullable|exists:groups,id',
            'items.*.quantity' => 'nullable|numeric|min:0.01',
            'items.*.price' => 'nullable|numeric|min:0',
            'items.*.notes' => 'nullable|string',
        ]);

        $storeModuleEnabled = (bool) app(SystemSettings::class)->all()['store_module_enabled'];
        if (! $storeModuleEnabled) {
            $hasStoreLine = collect($validated['items'])->contains(fn ($line) => ! empty($line['item_id']) || ! empty($line['group_id']));
            abort_if($hasStoreLine, 422, 'El módulo de Tienda está desactivado — no se pueden agregar artículos ni grupos a la cotización.');
        }

        $this->quoteService->createQuoteFromBooking($booking, $validated);

        return redirect()->route('agenda.show', $booking)->with('success', 'Nueva opción de presupuesto guardada.');
    }

    public function acceptQuote(Request $request, SpaBooking $booking, Quote $quote): RedirectResponse
    {
        $this->ensureVisible($booking);
        abort_unless($quote->spa_booking_id === $booking->id, 404);

        $validated = $request->validate([
            'advance_amount' => 'nullable|numeric|min:0',
            'advance_payment_method_code' => 'nullable|string|exists:payment_methods,code',
        ]);

        try {
            $this->quoteService->acceptQuote($quote, $validated);
        } catch (RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        // Asignar folio de orden al convertirse en work_order
        try {
            app(AccountingServiceInterface::class)->assignOrderFolio($booking->fresh());
        } catch (\Throwable) {
            // No interrumpir el flujo si falla la asignación del folio
        }

        return redirect()->route('agenda.show', $booking)->with('success', 'Presupuesto aceptado. La sesión ahora es una Orden de Trabajo activa.');
    }

    /**
     * Acción sobre una línea de servicio desde la web (pop-up de la agenda / ficha): iniciar,
     * completar, "realizada", "no se realizó", cancelar, reactivar, reasignar operador. Misma
     * lógica y guardas que el endpoint móvil (`Api\BookingController::assignServiceProfessional`)
     * vía `ServiceLineActionService`.
     */
    public function updateServiceLine(Request $request, SpaBooking $booking, SpaBookingService $line): RedirectResponse|JsonResponse
    {
        $this->ensureVisible($booking);
        abort_unless($line->spa_booking_id === $booking->id, 404);

        if (in_array($booking->status, ['cancelled', 'no_show', 'completed'], true)) {
            $closed = 'La cita ya está cerrada — no se pueden cambiar sus servicios.';

            return $request->wantsJson()
                ? response()->json(['ok' => false, 'message' => $closed], 422)
                : redirect()->back()->with('error', $closed);
        }

        $validated = $request->validate([
            'operator_id' => 'sometimes|exists:operators,id',
            'is_external' => 'sometimes|boolean',
            'external_cost' => 'nullable|numeric|min:0',
            'current_price' => 'sometimes|numeric|min:0',
            'mark_started' => 'sometimes|boolean',
            'mark_completed' => 'sometimes|boolean',
            'mark_realizada' => 'sometimes|boolean',
            'mark_not_performed' => 'sometimes|boolean',
            'not_performed_reason' => 'nullable|string|max:255',
            'mark_cancelled' => 'sometimes|boolean',
            'cancellation_reason' => 'nullable|string|max:255',
            'mark_reactivate' => 'sometimes|boolean',
        ]);

        $error = app(ServiceLineActionService::class)->apply($booking, $line, $validated);

        // El pop-up de acciones rápidas de la agenda (SYNC-082) manda esto por fetch()
        // para no cerrarse: devuelve el estado fresco de todas las líneas y re-pinta el
        // panel en su lugar, así se pueden accionar 2+ servicios sin reabrirlo.
        if ($request->wantsJson()) {
            if ($error !== null) {
                return response()->json(['ok' => false, 'message' => $error], 422);
            }

            $booking->load('services.service');

            return response()->json([
                'ok' => true,
                'message' => 'Servicio actualizado.',
                'booking_status' => $booking->status,
                'lines' => $booking->services->map(fn ($l) => [
                    'id' => $l->id,
                    'name' => $l->service?->name ?? 'Servicio',
                    'state' => $l->cancelled_at ? 'cancelled'
                        : ($l->not_performed_at ? 'not_performed'
                        : ($l->completed_at ? 'completed'
                        : ($l->started_at ? 'in_progress' : 'pending'))),
                ])->values(),
            ]);
        }

        if ($error !== null) {
            return redirect()->back()->with('error', $error);
        }

        return redirect()->back()->with('success', 'Servicio actualizado.');
    }

    public function assignProfessional(Request $request, SpaBooking $booking, SpaBookingService $item): RedirectResponse
    {
        $this->ensureVisible($booking);
        abort_unless($item->spa_booking_id === $booking->id, 404);

        $validated = $request->validate([
            'operator_id' => 'required|exists:operators,id',
            'external_cost' => 'nullable|numeric|min:0',
            'current_price' => 'nullable|numeric|min:0',
        ]);

        $item->update([
            'operator_id' => $validated['operator_id'],
            'is_external' => $request->boolean('is_external'),
            'external_cost' => $validated['external_cost'] ?? null,
            'current_price' => $validated['current_price'] ?? $item->current_price,
        ]);

        return redirect()->back()->with('success', 'Profesional asignado correctamente.');
    }

    public function registerPayment(Request $request, SpaBooking $booking, Quote $quote): RedirectResponse|JsonResponse
    {
        $this->ensureVisible($booking);
        abort_unless($quote->spa_booking_id === $booking->id, 404);

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_method_code' => 'required|string|exists:payment_methods,code',
            'notes' => 'nullable|string|max:500',
            'category' => 'required|string|max:50',
        ]);

        try {
            $this->quoteService->registerPayment($booking->pet->client_id, $validated['amount'], [
                'payable_type' => Quote::class,
                'payable_id' => $quote->id,
                'payment_method_code' => $validated['payment_method_code'],
                'category' => $validated['category'],
                'notes' => $validated['notes'] ?? null,
                'booking' => $booking,
            ]);
        } catch (RuntimeException $e) {
            if ($request->wantsJson()) {
                return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
            }

            return redirect()->back()->with('error', $e->getMessage());
        }

        if ($request->wantsJson()) {
            return response()->json([
                'ok' => true,
                'message' => 'Pago registrado exitosamente.',
                'receipt_url' => route('reports.invoice', $booking),
            ]);
        }

        return redirect()->back()->with('success', 'Pago registrado exitosamente.');
    }

    /**
     * Liquidación directa contra la cita, sin presupuesto de por medio — el camino que
     * necesitan las citas que llegaron a `completed` por "Iniciar cita" + "Terminar y cobrar"
     * (SYNC-052/053), que nunca tuvieron un Quote. Registra un Payment ligado al SpaBooking
     * (misma forma que el cobro móvil, `Api\PaymentController::store`) + su recibo/asiento.
     */
    public function registerDirectPayment(Request $request, SpaBooking $booking): RedirectResponse|JsonResponse
    {
        $this->ensureVisible($booking);

        if ($booking->status === 'cancelled') {
            if ($request->wantsJson()) {
                return response()->json(['ok' => false, 'message' => 'No se puede cobrar una cita cancelada.'], 422);
            }

            return redirect()->back()->with('error', 'No se puede cobrar una cita cancelada.');
        }

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_method_code' => 'required|string|exists:payment_methods,code',
            'notes' => 'nullable|string|max:500',
        ]);

        $paymentMethod = PaymentMethod::where('code', $validated['payment_method_code'])->firstOrFail();
        $destination = $paymentMethod->type === 'cash' ? 'caja' : 'banco';

        try {
            DB::transaction(function () use ($request, $booking, $validated, $paymentMethod, $destination) {
                $payment = Payment::create([
                    'client_id' => $booking->pet->client_id,
                    'payable_type' => SpaBooking::class,
                    'payable_id' => $booking->id,
                    'amount' => $validated['amount'],
                    'payment_method' => $paymentMethod->name,
                    'destination' => $destination,
                    'category' => 'liquidacion',
                    'notes' => $validated['notes'] ?? null,
                    'created_by_user_id' => $request->user()->id,
                ]);

                app(AccountingServiceInterface::class)->recordBookingPayment(
                    $booking,
                    $payment,
                    $paymentMethod,
                    (float) $validated['amount'],
                    null,
                    $validated['notes'] ?? null
                );
            });
        } catch (RuntimeException $e) {
            if ($request->wantsJson()) {
                return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
            }

            return redirect()->back()->with('error', $e->getMessage());
        }

        if ($request->wantsJson()) {
            return response()->json([
                'ok' => true,
                'message' => 'Pago registrado exitosamente.',
                'receipt_url' => route('reports.invoice', $booking),
            ]);
        }

        return redirect()->back()->with('success', 'Pago registrado exitosamente.');
    }
}
