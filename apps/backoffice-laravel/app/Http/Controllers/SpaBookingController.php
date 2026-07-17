<?php

namespace App\Http\Controllers;

use App\Domain\Accounting\Contracts\AccountingServiceInterface;
use App\Domain\Clinical\Services\VaccinationEligibilityChecker;
use App\Domain\Commercial\Contracts\QuoteServiceInterface;
use App\Domain\Planning\Contracts\BookingServiceInterface;
use App\Domain\Planning\Services\OperatorAvailabilityChecker;
use App\Domain\Resources\Contracts\ResourceAllocationServiceInterface;
use App\Mail\ServiceSummaryMail;
use App\Models\Client;
use App\Models\HotelReservation;
use App\Models\Operator;
use App\Models\Pet;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\Resource;
use App\Models\Group;
use App\Models\Item;
use App\Models\Service;
use App\Models\SpaBooking;
use App\Support\Geo\CoverageChecker;
use App\Support\Pages\AgendaPage;
use App\Support\SystemSettings\BusinessHours;
use App\Support\SystemSettings\SystemSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

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
            ? ['scheduled', 'work_order']
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
        $direction = $request->query('direction') === 'asc' ? 'asc' : 'desc';

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
            ]);

        $this->applyBookingFilters($bookingsQuery, $statuses, $search);

        if ($dateScope === 'all') {
            if ($statuses !== [] && array_intersect($statuses, ['scheduled', 'work_order']) !== []) {
                $bookingsQuery->where('scheduled_at', '>=', now()->startOfDay());
            }
        } elseif ($dateScope === 'custom') {
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
        $spaForTimeline = SpaBooking::whereDate('scheduled_at', $selectedDate)
            ->whereIn('status', ['scheduled', 'work_order'])
            ->with(['pet.client', 'services.service'])
            ->get()
            ->map(function ($b) {
                $b = $this->decorateBooking($b);
                $b->agenda_type = 'spa';

                return $b;
            });

        $hotelForTimeline = HotelReservation::whereDate('start_at', '<=', $selectedDate)
            ->whereDate('end_at', '>=', $selectedDate)
            ->where('status', 'active')
            ->with('pet.client')
            ->get()
            ->map(function ($h) {
                $h->agenda_type = 'hotel';
                $h->scheduled_at = $h->start_at;

                return $h;
            });

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

        return view('agenda.index', compact(
            'page', 'bookings', 'timelineBookings', 'statuses', 'statusTouched', 'dateScope', 'calView',
            'selectedDate', 'selectedDateInput', 'operationalDateLabel', 'search',
            'totalEstimatedMinutes', 'scheduledCount', 'estimatedRevenue', 'petsWithAgenda',
            'firstScheduledAt', 'lastScheduledEndAt', 'sort', 'direction', 'agendaOverviewCount'
        ));
    }

    private function applyBookingFilters($query, array $statuses, string $search): void
    {
        if ($statuses !== []) {
            $query->whereIn('status', $statuses);
        }

        if ($search !== '') {
            $query->whereHas('pet', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhereHas('client', function ($q2) use ($search) {
                        $q2->where('first_name', 'like', "%{$search}%")
                            ->orWhere('apellido_paterno', 'like', "%{$search}%")
                            ->orWhere('apellido_materno', 'like', "%{$search}%")
                            ->orWhereRaw("CONCAT(first_name, ' ', apellido_paterno, ' ', apellido_materno) LIKE ?", ["%{$search}%"]);
                    });
            });
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

        $hotelQuery = HotelReservation::query()
            ->whereDate('start_at', '<=', $rangeEnd)
            ->whereDate('end_at', '>=', $rangeStart)
            ->where('status', 'active')
            ->with('pet.client');

        if ($search !== '') {
            $hotelQuery->whereHas('pet', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhereHas('client', function ($q2) use ($search) {
                        $q2->where('first_name', 'like', "%{$search}%")
                            ->orWhere('apellido_paterno', 'like', "%{$search}%")
                            ->orWhere('apellido_materno', 'like', "%{$search}%")
                            ->orWhereRaw("CONCAT(first_name, ' ', apellido_paterno, ' ', apellido_materno) LIKE ?", ["%{$search}%"]);
                    });
            });
        }

        $hotelReservations = $hotelQuery->get()->map(function ($h) {
            $h->agenda_type = 'hotel';
            $h->scheduled_at = $h->start_at;

            return $h;
        });

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

        $calendarDays = [];
        $cursor = $rangeStart->copy()->startOfDay();
        $stop = $rangeEnd->copy()->startOfDay();
        while ($cursor->lte($stop)) {
            $key = $cursor->format('Y-m-d');
            $calendarDays[] = [
                'date' => $cursor->copy(),
                'items' => $itemsByDate[$key] ?? collect(),
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

    public function show(SpaBooking $booking): View
    {
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

        return view('agenda.show', compact('page', 'booking', 'assignableResources', 'operators', 'services', 'items', 'groups', 'groupsForQuoteManager', 'storeModuleEnabled'));
    }

    public function globalCreate(Request $request): View
    {
        $page = AgendaPage::create(null, null);

        $pets = Pet::with('client')
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

        return view('agenda.global-create', compact('page', 'pets'));
    }

    public function edit(SpaBooking $booking): View
    {
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
        // Handle simple status updates (e.g. from Work Order)
        if ($request->has('status') && $request->input('status') === 'completed') {
            $booking->update(['status' => 'completed']);

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

            return redirect()->route('agenda.show', $booking)->with('success', 'Sesión finalizada con éxito. Se ha generado el estado de cuenta y enviado el reporte al cliente.');
        }

        $validated = $request->validate([
            'scheduled_at' => 'required|date',
            'operator_id' => 'required|exists:operators,id',
            'resource_id' => 'nullable|exists:resources,id',
            'notes' => 'nullable|string',
            'services' => 'nullable|array',
            'services.*' => 'exists:services,id',
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

    public function cancel(Request $request, SpaBooking $booking): RedirectResponse
    {
        $validated = $request->validate([
            'cancellation_reason' => 'required|string|max:500',
        ]);

        $this->bookingService->cancelBooking($booking->id, $validated['cancellation_reason']);

        return redirect()->route('agenda.index')->with('success', 'Sesión cancelada.');
    }

    public function markNoShow(SpaBooking $booking): RedirectResponse
    {
        $this->bookingService->markNoShow($booking->id);

        return redirect()->route('agenda.index')->with('success', 'Sesión marcada como No se presentó.');
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
            'operator_id' => 'required|exists:operators,id',
            'resource_id' => 'nullable|exists:resources,id',
            'notes' => 'nullable|string',
            'services' => 'required|array',
            'services.*' => 'exists:services,id',
        ]);

        $servicesWithPrices = [];
        $servicesData = Service::whereIn('id', $validated['services'])->get();
        foreach ($servicesData as $service) {
            $servicesWithPrices[$service->id] = $service->suggested_price ?? $service->price ?? 0;
        }

        $scheduledAt = Carbon::parse($validated['scheduled_at']);
        $durationMinutes = (int) $servicesData->sum(fn ($s) => $s->suggested_duration_minutes ?? $s->duration_minutes ?? 0);

        if (! $this->businessHours->isWithin($scheduledAt)) {
            return redirect()->back()->withInput()->with('error', "La hora elegida está fuera del horario operativo ({$this->businessHours->openingTime()}–{$this->businessHours->closingTime()}).");
        }

        if ($this->operatorAvailabilityChecker->hasConflict((int) $validated['operator_id'], $scheduledAt, $durationMinutes)) {
            return redirect()->back()->withInput()->with('error', 'El operador seleccionado ya tiene una cita en ese horario.');
        }

        $booking = $this->bookingService->scheduleSpaSession(
            $pet->id,
            $validated['scheduled_at'],
            $servicesWithPrices,
            $validated['notes'] ?? null,
            (int) $validated['operator_id']
        );

        if (! empty($validated['resource_id'])) {
            $durationMinutes = (int) $servicesData->sum(fn ($s) => $s->suggested_duration_minutes ?? $s->duration_minutes ?? 0);
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

        return $booking;
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
        abort_unless($quote->spa_booking_id === $booking->id, 404);

        $validated = $request->validate([
            'advance_amount' => 'nullable|numeric|min:0',
            'advance_payment_method' => 'nullable|string|max:50',
        ]);

        $this->quoteService->acceptQuote($quote, $validated);

        // Asignar folio de orden al convertirse en work_order
        try {
            app(AccountingServiceInterface::class)->assignOrderFolio($booking->fresh());
        } catch (\Throwable) {
            // No interrumpir el flujo si falla la asignación del folio
        }

        return redirect()->route('agenda.show', $booking)->with('success', 'Presupuesto aceptado. La sesión ahora es una Orden de Trabajo activa.');
    }

    public function assignProfessional(Request $request, SpaBooking $booking, QuoteItem $item): RedirectResponse
    {
        abort_unless($item->quote->spa_booking_id === $booking->id, 404);

        $validated = $request->validate([
            'operator_id' => 'required|exists:operators,id',
            'is_external' => 'boolean',
        ]);

        $item->update($validated);

        return redirect()->back()->with('success', 'Profesional asignado correctamente.');
    }

    public function registerPayment(Request $request, SpaBooking $booking, Quote $quote): RedirectResponse
    {
        abort_unless($quote->spa_booking_id === $booking->id, 404);

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|string|max:50',
            'destination' => 'required|in:caja,banco',
            'notes' => 'nullable|string|max:500',
            'category' => 'required|string|max:50',
        ]);

        $this->quoteService->registerPayment($booking->pet->client_id, $validated['amount'], [
            'payable_type' => Quote::class,
            'payable_id' => $quote->id,
            'payment_method' => $validated['payment_method'],
            'destination' => $validated['destination'],
            'category' => $validated['category'],
            'notes' => $validated['notes'],
        ]);

        return redirect()->back()->with('success', 'Pago registrado exitosamente.');
    }
}
