<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Operator;
use App\Models\OperatorCompensationProfile;
use App\Models\OperatorRole;
use App\Support\OperatorPhotoImageManager;
use App\Support\SystemSettings\BusinessHours;
use App\Support\SystemSettings\SystemSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class OperatorController extends Controller
{
    public function __construct(
        private readonly OperatorPhotoImageManager $imageManager,
        private readonly BusinessHours $businessHours,
    ) {
    }

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $status = $request->query('status');
        $sort = $request->query('sort');
        $direction = $request->query('direction') === 'desc' ? 'desc' : 'asc';

        if (!in_array($status, ['all', 'active', 'inactive'], true)) {
            $status = 'all';
        }

        if (!in_array($sort, ['name', 'code', 'jobs', 'status'], true)) {
            $sort = null;
        }

        $operators = Operator::query()
            ->with(['roles', 'branches', 'compensationProfiles'])
            ->withCount('executedServices');

        if ($search !== '') {
            $operators->where(function ($query) use ($search) {
                $query->where('first_name', 'like', "%{$search}%")
                    ->orWhere('apellido_paterno', 'like', "%{$search}%")
                    ->orWhere('apellido_materno', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhereHas('roles', function ($roleQuery) use ($search) {
                        $roleQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%");
                    })
                    ->orWhereHas('branches', function ($branchQuery) use ($search) {
                        $branchQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%");
                    });
            });
        }

        if ($status === 'active') {
            $operators->where('is_active', true);
        } elseif ($status === 'inactive') {
            $operators->where('is_active', false);
        }

        if ($sort === 'name') {
            $operators->orderBy('apellido_paterno', $direction)
                ->orderBy('apellido_materno', $direction)
                ->orderBy('first_name', $direction)
                ->orderBy('code');
        } elseif ($sort === 'code') {
            $operators->orderBy('code', $direction)
                ->orderBy('apellido_paterno')
                ->orderBy('apellido_materno')
                ->orderBy('first_name');
        } elseif ($sort === 'jobs') {
            $operators->orderBy('executed_services_count', $direction)
                ->orderBy('apellido_paterno')
                ->orderBy('apellido_materno')
                ->orderBy('first_name');
        } elseif ($sort === 'status') {
            $operators->orderBy('is_active', $direction)
                ->orderBy('apellido_paterno')
                ->orderBy('apellido_materno')
                ->orderBy('first_name');
        } else {
            $operators->orderByDesc('is_active')
                ->orderBy('apellido_paterno')
                ->orderBy('apellido_materno')
                ->orderBy('first_name');
        }

        $operators = $operators
            ->paginate(15);

        return view('operators.index', compact('operators', 'search', 'status', 'sort', 'direction'));
    }

    public function create(Request $request, SystemSettings $systemSettings): View
    {
        $copySourceId = (int) $request->query('copy_from');
        $copySource = $copySourceId ? Operator::with(['roles', 'branches'])->find($copySourceId) : null;

        $availableRoles = OperatorRole::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $availableBranches = Branch::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $existingOperators = Operator::orderBy('name')->get(['id', 'name', 'code']);

        return view('operators.create', [
            'availableRoles' => $availableRoles,
            'availableBranches' => $availableBranches,
            'existingOperators' => $existingOperators,
            'copySource' => $copySource,
            'suggestAreaCode' => $systemSettings->all()['commercial_clients_suggest_area_code'] ?? false,
            'defaultAreaCode' => $systemSettings->all()['commercial_clients_default_area_code'] ?? '',
            'defaultScheduleStartTime' => $this->businessHours->openingTime(),
            'defaultScheduleEndTime' => $this->businessHours->closingTime(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->rules());
        $this->validateWeeklyScheduleRanges($validated);
        $newPhotoPath = null;

        if ($request->hasFile('profile_photo')) {
            $newPhotoPath = $this->imageManager->store($request->file('profile_photo'));
            $validated['profile_photo_path'] = $newPhotoPath;
        }

        try {
            DB::transaction(function () use ($validated): void {
                $operator = Operator::create($this->preparePayload($validated));

                $this->syncRoles($operator, $validated['role_ids'] ?? []);
                $this->syncPrimaryBranch($operator, $validated['branch_id'] ?? null);
                $this->syncCompensation($operator, $validated['hourly_rate'] ?? null);
                $this->syncWeeklySchedule($operator, $validated['weekly_schedule'] ?? []);
            });
        } catch (Throwable $exception) {
            $this->imageManager->deleteFiles($newPhotoPath);

            throw $exception;
        }

        return redirect()->route('operators.index')->with('success', 'Operador creado.');
    }

    public function show(Operator $operator): View
    {
        $operator->load([
            'roles',
            'branches',
            'compensationProfiles',
        ])->loadCount('executedServices');

        $executedServices = $operator->executedServices()
            ->latest('executed_at')
            ->limit(10)
            ->get();

        return view('operators.show', compact('operator', 'executedServices'));
    }

    public function edit(Operator $operator, SystemSettings $systemSettings): View
    {
        $operator->load(['roles', 'branches', 'compensationProfiles', 'weeklySchedules', 'unavailabilities']);

        $availableRoles = OperatorRole::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $currentBranchId = $operator->primaryBranch()?->id;

        $availableBranches = Branch::query()
            ->where(function ($query) use ($currentBranchId) {
                $query->where('is_active', true);

                if ($currentBranchId) {
                    $query->orWhere('id', $currentBranchId);
                }
            })
            ->orderBy('name')
            ->get();

        return view('operators.edit', [
            'operator' => $operator,
            'availableRoles' => $availableRoles,
            'availableBranches' => $availableBranches,
            'suggestAreaCode' => $systemSettings->all()['commercial_clients_suggest_area_code'] ?? false,
            'defaultAreaCode' => $systemSettings->all()['commercial_clients_default_area_code'] ?? '',
            'defaultScheduleStartTime' => $this->businessHours->openingTime(),
            'defaultScheduleEndTime' => $this->businessHours->closingTime(),
        ]);
    }

    public function update(Request $request, Operator $operator): RedirectResponse
    {
        $validated = $request->validate($this->rules($operator));
        $this->validateWeeklyScheduleRanges($validated);
        $oldPhotoPath = $operator->profile_photo_path;
        $newPhotoPath = null;

        if ($request->boolean('remove_profile_photo')) {
            $validated['profile_photo_path'] = null;
        }

        if ($request->hasFile('profile_photo')) {
            $newPhotoPath = $this->imageManager->store($request->file('profile_photo'));
            $validated['profile_photo_path'] = $newPhotoPath;
        }

        try {
            DB::transaction(function () use ($operator, $validated): void {
                $operator->update($this->preparePayload($validated, $operator));

                $this->syncRoles($operator, $validated['role_ids'] ?? []);
                $this->syncPrimaryBranch($operator, $validated['branch_id'] ?? null);
                $this->syncCompensation($operator, $validated['hourly_rate'] ?? null);
                $this->syncWeeklySchedule($operator, $validated['weekly_schedule'] ?? []);
            });
        } catch (Throwable $exception) {
            $this->imageManager->deleteFiles($newPhotoPath);

            throw $exception;
        }

        $operator->refresh();

        if ($oldPhotoPath && $oldPhotoPath !== $operator->profile_photo_path) {
            $this->imageManager->deleteFiles($oldPhotoPath);
        }

        return redirect()->route('operators.edit', $operator)->with('success', 'Operador actualizado.');
    }

    public function duplicate(Operator $operator): RedirectResponse
    {
        $duplicate = $operator->replicate();
        $duplicate->code = $this->buildDuplicateCode($operator->code);
        $duplicate->first_name = $this->buildDuplicateName($operator->first_name ?: $operator->name);
        $duplicate->name = $this->buildDuplicateName($operator->name);
        $duplicate->is_active = false;
        $duplicate->save();

        // Optional: Clone roles and branches if needed, but for now let's keep it simple
        // like services and resources.

        return redirect()->route('operators.edit', $duplicate)->with('success', 'Operador duplicado. Revisa clave, nombre y detalles antes de activarlo.');
    }

    private function buildDuplicateCode(string $code): string
    {
        $baseCode = strtoupper(trim($code));
        $candidate = $baseCode . '-COPY';
        $suffix = 2;

        while (Operator::where('code', $candidate)->exists()) {
            $candidate = $baseCode . '-COPY-' . $suffix;
            $suffix++;
        }

        return $candidate;
    }

    private function buildDuplicateName(string $name): string
    {
        $baseName = Str::of($name)->replaceLast(' (copia)', '')->toString();
        $candidate = $baseName . ' (copia)';
        $suffix = 2;

        while (Operator::where('first_name', $candidate)->orWhere('name', $candidate)->exists()) {
            $candidate = $baseName . ' (copia ' . $suffix . ')';
            $suffix++;
        }

        return $candidate;
    }

    public function destroy(Operator $operator): RedirectResponse
    {
        $this->imageManager->deleteFiles($operator->profile_photo_path);
        $operator->delete();

        return redirect()->route('operators.index')->with('success', 'Operador eliminado.');
    }

    private function rules(?Operator $operator = null): array
    {
        $uniqueRule = 'unique:operators,code';

        if ($operator) {
            $uniqueRule .= ',' . $operator->id;
        }

        return [
            'code' => ['required', 'string', 'max:255', $uniqueRule],
            'first_name' => 'required|string|max:255',
            'apellido_paterno' => 'nullable|string|max:255',
            'apellido_materno' => 'nullable|string|max:255',
            'name' => 'nullable|string|max:255',
            'ine_number' => 'nullable|string|max:255',
            'imss_number' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:255',
            'profile_photo' => 'nullable|file|image|max:15360',
            'remove_profile_photo' => 'nullable|boolean',
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_phone' => 'nullable|string|max:255',
            'hire_date' => 'nullable|date',
            'role_ids' => 'nullable|array',
            'role_ids.*' => 'integer|exists:operator_roles,id',
            'branch_id' => 'nullable|integer|exists:branches,id',
            'hourly_rate' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'is_active' => 'nullable|boolean',
            'weekly_schedule' => 'nullable|array',
            'weekly_schedule.*.enabled' => 'nullable|boolean',
            'weekly_schedule.*.start_time' => 'nullable|date_format:H:i',
            'weekly_schedule.*.end_time' => 'nullable|date_format:H:i',
        ];
    }

    private function validateWeeklyScheduleRanges(array $validated): void
    {
        $errors = [];

        foreach (($validated['weekly_schedule'] ?? []) as $dayOfWeek => $day) {
            if (empty($day['enabled'])) {
                continue;
            }

            if (blank($day['start_time'] ?? null) || blank($day['end_time'] ?? null)) {
                $errors["weekly_schedule.$dayOfWeek.start_time"] = 'Debes capturar hora de inicio y fin para el día seleccionado.';

                continue;
            }

            if ($day['end_time'] <= $day['start_time']) {
                $errors["weekly_schedule.$dayOfWeek.end_time"] = 'La hora de fin debe ser posterior a la hora de inicio.';
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function preparePayload(array $validated, ?Operator $operator = null): array
    {
        $computedFullName = implode(' ', array_filter([
            $validated['first_name'],
            $validated['apellido_paterno'] ?? null,
            $validated['apellido_materno'] ?? null,
        ], fn ($part) => filled($part)));

        return [
            'code' => strtoupper(trim($validated['code'])),
            'first_name' => $validated['first_name'],
            'apellido_paterno' => $validated['apellido_paterno'] ?? null,
            'apellido_materno' => $validated['apellido_materno'] ?? null,
            'name' => $validated['name'] ?? $computedFullName,
            'ine_number' => $validated['ine_number'] ?? null,
            'imss_number' => $validated['imss_number'] ?? null,
            'address' => $validated['address'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'profile_photo_path' => array_key_exists('profile_photo_path', $validated)
                ? $validated['profile_photo_path']
                : $operator?->profile_photo_path,
            'emergency_contact_name' => $validated['emergency_contact_name'] ?? null,
            'emergency_contact_phone' => $validated['emergency_contact_phone'] ?? null,
            'hire_date' => $validated['hire_date'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'is_active' => !empty($validated['is_active']),
        ];
    }

    private function syncRoles(Operator $operator, array $roleIds): void
    {
        $normalizedRoleIds = collect($roleIds)
            ->map(fn (mixed $roleId) => (int) $roleId)
            ->filter()
            ->unique()
            ->values();

        $operator->roleAssignments()->delete();

        foreach ($normalizedRoleIds as $index => $roleId) {
            $operator->roleAssignments()->create([
                'operator_role_id' => $roleId,
                'is_primary' => $index === 0,
                'starts_at' => now(),
            ]);
        }
    }

    private function syncPrimaryBranch(Operator $operator, mixed $branchId): void
    {
        $normalizedBranchId = $branchId ? (int) $branchId : null;

        $operator->branchAssignments()->delete();

        if (!$normalizedBranchId) {
            return;
        }

        $operator->branchAssignments()->create([
            'branch_id' => $normalizedBranchId,
            'is_primary' => true,
            'starts_at' => now(),
        ]);
    }

    private function syncCompensation(Operator $operator, mixed $hourlyRate): void
    {
        $normalizedRate = $hourlyRate === null || $hourlyRate === ''
            ? null
            : number_format((float) $hourlyRate, 2, '.', '');

        $currentProfile = $operator->compensationProfiles()
            ->whereNull('effective_to')
            ->latest('effective_from')
            ->first();

        if ($normalizedRate === null) {
            if ($currentProfile) {
                $currentProfile->update(['effective_to' => now()->toDateString()]);
            }

            return;
        }

        if ($currentProfile && (float) $currentProfile->hourly_rate === (float) $normalizedRate && $currentProfile->compensation_type === 'hourly') {
            return;
        }

        if ($currentProfile) {
            $currentProfile->update(['effective_to' => now()->toDateString()]);
        }

        $operator->compensationProfiles()->create([
            'compensation_type' => 'hourly',
            'hourly_rate' => $normalizedRate,
            'effective_from' => now()->toDateString(),
        ]);
    }

    private function syncWeeklySchedule(Operator $operator, array $weeklySchedule): void
    {
        $operator->weeklySchedules()->delete();

        foreach (range(0, 6) as $dayOfWeek) {
            $day = $weeklySchedule[$dayOfWeek] ?? null;

            if (! $day || empty($day['enabled']) || blank($day['start_time'] ?? null) || blank($day['end_time'] ?? null)) {
                continue;
            }

            $operator->weeklySchedules()->create([
                'day_of_week' => $dayOfWeek,
                'start_time' => $day['start_time'],
                'end_time' => $day['end_time'],
            ]);
        }
    }

}