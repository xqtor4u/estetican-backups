<?php

namespace App\Http\Controllers;

use App\Domain\Resources\Contracts\ResourceAllocationServiceInterface;
use App\Models\HotelReservation;
use App\Models\Pet;
use App\Support\Pages\HotelReservationsPage;
use App\Models\Resource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;

class HotelReservationController extends Controller
{
    public function __construct(
        private readonly ResourceAllocationServiceInterface $resourceAllocationService,
    ) {
    }

    public function index(): View
    {
        $page = HotelReservationsPage::index();

        $reservations = HotelReservation::query()
            ->with(['pet.client:id,first_name,last_name', 'resourceAllocations.resource:id,code,name'])
            ->orderBy('start_at')
            ->paginate(15);

        return view('hotel-reservations.index', compact('page', 'reservations'));
    }

    public function create(Request $request): View
    {
        $page = HotelReservationsPage::create();

        $pets = $this->loadPets();
        $resources = $this->loadHotelResources();
        $selectedPetId = (int) $request->query('pet_id');

        return view('hotel-reservations.create', compact('page', 'pets', 'resources', 'selectedPetId'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatedData($request);

        try {
            $reservation = DB::transaction(function () use ($validated): HotelReservation {
                $reservation = HotelReservation::create([
                    'pet_id' => (int) $validated['pet_id'],
                    'start_at' => $validated['start_at'],
                    'end_at' => $validated['end_at'],
                    'status' => 'scheduled',
                ]);

                if (!empty($validated['resource_id'])) {
                    $this->resourceAllocationService->assignResourceWindowToSource(
                        (int) $validated['resource_id'],
                        $reservation,
                        $reservation->pet_id,
                        (string) $validated['start_at'],
                        (string) $validated['end_at'],
                        'reserved',
                        0,
                        'Bloqueo de jaula desde reserva de hotel.',
                    );
                }

                return $reservation;
            });
        } catch (RuntimeException $exception) {
            return back()->withErrors(['resource_id' => $exception->getMessage()])->withInput();
        }

        return redirect()
            ->route('hotel-reservations.show', $reservation)
            ->with('success', 'Reserva de hotel creada.');
    }

    public function show(HotelReservation $hotelReservation): View
    {
        $hotelReservation->load([
            'pet.client:id,first_name,last_name',
            'resourceAllocations.resource:id,code,name,resource_type',
        ]);

        $page = HotelReservationsPage::show($hotelReservation);

        return view('hotel-reservations.show', compact('page', 'hotelReservation'));
    }

    public function edit(HotelReservation $hotelReservation): View
    {
        $hotelReservation->load('pet.client');
        $page = HotelReservationsPage::edit($hotelReservation);

        $pets = $this->loadPets();
        $resources = $this->loadHotelResources();
        $assignedResourceId = $hotelReservation->resourceAllocations()->firstWhere('allocation_type', 'reserved')?->resource_id;

        return view('hotel-reservations.edit', compact('page', 'hotelReservation', 'pets', 'resources', 'assignedResourceId'));
    }

    public function update(Request $request, HotelReservation $hotelReservation): RedirectResponse
    {
        if ($hotelReservation->status !== 'scheduled') {
            return back()->with('error', 'Solo se pueden editar reservas hotel en estado programado.');
        }

        $validated = $this->validatedData($request);

        try {
            DB::transaction(function () use ($hotelReservation, $validated): void {
                $hotelReservation->update([
                    'pet_id' => (int) $validated['pet_id'],
                    'start_at' => $validated['start_at'],
                    'end_at' => $validated['end_at'],
                ]);

                if (!empty($validated['resource_id'])) {
                    $this->resourceAllocationService->syncResourceWindowToSource(
                        (int) $validated['resource_id'],
                        $hotelReservation,
                        $hotelReservation->pet_id,
                        (string) $validated['start_at'],
                        (string) $validated['end_at'],
                        'reserved',
                        0,
                        'Reasignacion de jaula desde reserva de hotel.',
                    );
                } else {
                    $this->resourceAllocationService->releaseSourceAllocations($hotelReservation);
                }
            });
        } catch (RuntimeException $exception) {
            return back()->withErrors(['resource_id' => $exception->getMessage()])->withInput();
        }

        return redirect()
            ->route('hotel-reservations.show', $hotelReservation)
            ->with('success', 'Reserva de hotel actualizada.');
    }

    public function cancel(HotelReservation $hotelReservation): RedirectResponse
    {
        if ($hotelReservation->status !== 'scheduled') {
            return back()->with('error', 'Solo se pueden cancelar reservas programadas.');
        }

        DB::transaction(function () use ($hotelReservation): void {
            $hotelReservation->update(['status' => 'cancelled']);
            $this->resourceAllocationService->releaseSourceAllocations($hotelReservation);
        });

        return redirect()
            ->route('hotel-reservations.show', $hotelReservation)
            ->with('success', 'Reserva de hotel cancelada y jaula liberada.');
    }

    private function validatedData(Request $request): array
    {
        return $request->validate([
            'pet_id' => ['required', 'integer', 'exists:pets,id'],
            'resource_id' => ['nullable', 'integer', 'exists:resources,id'],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after:start_at'],
        ]);
    }

    private function loadPets()
    {
        return Pet::query()
            ->with('client:id,first_name,last_name')
            ->orderBy('name')
            ->get(['id', 'client_id', 'name']);
    }

    private function loadHotelResources()
    {
        return Resource::query()
            ->with('branch:id,name')
            ->where('resource_type', 'cage')
            ->where('administrative_status', 'active')
            ->orderBy('code')
            ->get(['id', 'branch_id', 'code', 'name']);
    }
}