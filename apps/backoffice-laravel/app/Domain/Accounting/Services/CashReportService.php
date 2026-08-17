<?php

namespace App\Domain\Accounting\Services;

use App\Domain\Accounting\Contracts\CashReportServiceInterface;
use App\Models\Branch;
use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\Payment;
use App\Models\SpaBooking;
use App\Models\User;
use App\Support\SystemSettings\SystemSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CashReportService implements CashReportServiceInterface
{
    public function brandLogoPath(): ?string
    {
        $logo = app(SystemSettings::class)->all()['brand_logo_print']
            ?? app(SystemSettings::class)->all()['brand_logo_web']
            ?? null;

        if (! $logo || ! Storage::disk('public')->exists($logo)) {
            return null;
        }

        return Storage::disk('public')->path($logo);
    }

    private function businessName(): string
    {
        return app(SystemSettings::class)->all()['brand_business_name'] ?? 'EstetiCAN';
    }

    public function resolveMovementsItems(Request $request, User $user): array
    {
        $from  = $request->filled('date_from') ? $request->date_from . ' 00:00:00' : null;
        $until = $request->filled('date_to')   ? $request->date_to   . ' 23:59:59' : null;

        $typeFilter = $request->input('type');
        $movTypes   = ['retiro', 'deposito_banco', 'gasto', 'perdida', 'entrada'];

        // El scope de sucursal para CashMovement solo lo puede elegir un super-admin
        // (ver otras sucursales) — un operador normal siempre queda fijo a la suya,
        // sin importar qué branch_id mande el cliente (nunca confiar en el scope del request).
        $isSuperAdmin  = $user->is_super_admin;
        $requestedBranch = $request->filled('branch_id') ? (int) $request->input('branch_id') : null;
        $movementBranchId = $isSuperAdmin ? $requestedBranch : $user->branch_id;

        $items = collect();

        // ── Movimientos manuales de caja ──────────────────────
        $includeMovements = ! $typeFilter || in_array($typeFilter, $movTypes);
        if ($includeMovements) {
            $q = CashMovement::query()
                ->when($movementBranchId, fn ($q) => $q->whereHas('cashSession', fn ($q) => $q->where('branch_id', $movementBranchId)))
                ->with(['counterpartAccount:id,name', 'cashSession.branch:id,name', 'createdBy:id,name'])
                ->when($from,  fn ($q) => $q->where('created_at', '>=', $from))
                ->when($until, fn ($q) => $q->where('created_at', '<=', $until))
                ->when($typeFilter && in_array($typeFilter, $movTypes), fn ($q) => $q->where('type', $typeFilter));

            $items = $items->concat($q->get()->map(fn ($m) => [
                'id'                      => 'm-' . $m->id,
                'type'                    => $m->type,
                'direction'               => $m->direction,
                'amount'                  => (float) $m->amount,
                'concept'                 => $m->concept,
                'notes'                   => $m->notes,
                'account'                 => $m->counterpartAccount?->name,
                'branch_name'             => $m->cashSession?->branch?->name,
                'client_name'             => null,
                'created_by'              => $m->createdBy?->name,
                'created_at'              => $m->created_at->toISOString(),
                'is_reversed'             => $m->isReversed(),
                'reversal_of_movement_id' => $m->reversal_of_movement_id,
            ]));
        }

        // ── Cobros a clientes (payments — nuevo sistema) ──────
        $includeEfectivo = ! $typeFilter || $typeFilter === 'cobro_efectivo';
        $includeBanco    = ! $typeFilter || $typeFilter === 'cobro_banco';

        if ($includeEfectivo || $includeBanco) {
            $payments = Payment::with(['client', 'createdBy:id,name'])
                ->when($from,  fn ($q) => $q->where('created_at', '>=', $from))
                ->when($until, fn ($q) => $q->where('created_at', '<=', $until))
                ->when($includeEfectivo && ! $includeBanco, fn ($q) => $q->where('destination', 'caja'))
                ->when($includeBanco    && ! $includeEfectivo, fn ($q) => $q->where('destination', 'banco'))
                ->get();

            $items = $items->concat($payments->map(fn ($p) => [
                'id'          => 'p-' . $p->id,
                'type'        => $p->destination === 'caja' ? 'cobro_efectivo' : 'cobro_banco',
                'direction'   => 'entrada',
                'amount'      => (float) $p->amount,
                'concept'     => 'Cobro de servicio',
                'notes'       => $p->notes,
                'account'     => $p->payment_method,
                'branch_name' => null,
                'client_name' => $p->client?->full_name,
                'created_by'  => $p->createdBy?->name,
                'created_at'  => Carbon::parse($p->created_at)->toISOString(),
                'is_reversed' => false,
                'reversal_of_movement_id' => null,
            ]));
        }

        // ── Cobros legacy (cash_ledgers) ──────────────────────
        if ($includeEfectivo) {
            $cashRows = DB::table('cash_ledgers')
                ->leftJoin('clients', 'cash_ledgers.client_id', '=', 'clients.id')
                ->leftJoin('users', 'cash_ledgers.created_by_user_id', '=', 'users.id')
                ->when($from,  fn ($q) => $q->where('cash_ledgers.created_at', '>=', $from))
                ->when($until, fn ($q) => $q->where('cash_ledgers.created_at', '<=', $until))
                ->select('cash_ledgers.id', 'cash_ledgers.created_at', 'cash_ledgers.amount',
                         'cash_ledgers.payment_method', 'users.name as created_by_name',
                         DB::raw("CONCAT_WS(' ', clients.first_name, clients.apellido_paterno, clients.apellido_materno) as client_name"))
                ->get();

            $items = $items->concat($cashRows->map(fn ($r) => [
                'id'          => 'cl-' . $r->id,
                'type'        => 'cobro_efectivo',
                'direction'   => 'entrada',
                'amount'      => (float) $r->amount,
                'concept'     => 'Cobro de servicio',
                'notes'       => null,
                'account'     => $r->payment_method,
                'branch_name' => null,
                'client_name' => trim($r->client_name) ?: null,
                'created_by'  => $r->created_by_name,
                'created_at'  => Carbon::parse($r->created_at)->toISOString(),
                'is_reversed' => false,
                'reversal_of_movement_id' => null,
            ]));
        }

        // ── Cobros legacy (bank_ledgers) ──────────────────────
        if ($includeBanco) {
            $bankRows = DB::table('bank_ledgers')
                ->leftJoin('clients', 'bank_ledgers.client_id', '=', 'clients.id')
                ->leftJoin('users', 'bank_ledgers.created_by_user_id', '=', 'users.id')
                ->when($from,  fn ($q) => $q->where('bank_ledgers.created_at', '>=', $from))
                ->when($until, fn ($q) => $q->where('bank_ledgers.created_at', '<=', $until))
                ->select('bank_ledgers.id', 'bank_ledgers.created_at', 'bank_ledgers.amount',
                         'bank_ledgers.payment_method', 'users.name as created_by_name',
                         DB::raw("CONCAT_WS(' ', clients.first_name, clients.apellido_paterno, clients.apellido_materno) as client_name"))
                ->get();

            $items = $items->concat($bankRows->map(fn ($r) => [
                'id'          => 'bl-' . $r->id,
                'type'        => 'cobro_banco',
                'direction'   => 'entrada',
                'amount'      => (float) $r->amount,
                'concept'     => 'Cobro de servicio',
                'notes'       => null,
                'account'     => $r->payment_method,
                'branch_name' => null,
                'client_name' => trim($r->client_name) ?: null,
                'created_by'  => $r->created_by_name,
                'created_at'  => Carbon::parse($r->created_at)->toISOString(),
                'is_reversed' => false,
                'reversal_of_movement_id' => null,
            ]));
        }

        return [$items, $isSuperAdmin, $movementBranchId];
    }

    public function buildMetodosPagoData(Request $request): array
    {
        $user = auth()->user();

        $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to'   => ['nullable', 'date'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
        ]);

        [$items, $isSuperAdmin, $movementBranchId] = $this->resolveMovementsItems($request, $user);

        $cobros = $items->whereIn('type', ['cobro_efectivo', 'cobro_banco']);
        $totalCobrado = round($cobros->sum('amount'), 2);

        // `payment_method` viene de 3 fuentes distintas (Payment/cash_ledgers/bank_ledgers) sin
        // una sola fuente de verdad que garantice mayúsculas consistentes — hallazgo real
        // (16/08/2026): dos cobros reales de Payment quedaron guardados como "Efectivo" y
        // "efectivo", que MySQL ya trata como iguales por el collation por defecto pero un
        // `groupBy()` de PHP sobre la colección no — agrupar por el string crudo los separaba
        // en dos filas del mismo método. Normalizar a Título Case para agrupar Y mostrar.
        $byMethod = $cobros
            ->groupBy(fn ($m) => mb_strtolower(trim($m['account'] ?: 'Sin especificar')))
            ->map(fn ($group, $normalizedKey) => [
                'method' => mb_convert_case($normalizedKey, MB_CASE_TITLE, 'UTF-8'),
                'count'  => $group->count(),
                'amount' => round($group->sum('amount'), 2),
            ])
            ->sortByDesc('amount')
            ->values();

        return [
            'dateFrom'        => $request->input('date_from') ?: 'inicio',
            'dateTo'          => $request->input('date_to') ?: now()->toDateString(),
            'branchName'      => $movementBranchId ? Branch::find($movementBranchId)?->name : 'Todas las sucursales',
            'canSelectBranch' => $isSuperAdmin,
            'totalCobrado'    => $totalCobrado,
            'byMethod'        => $byMethod,
            'generatedBy'     => $user->name,
            'businessName'    => $this->businessName(),
            'logoPath'        => $this->brandLogoPath(),
        ];
    }

    /**
     * Arma los datos agregados del "Resumen de caja" (tarjetas + desglose por tipo) a partir
     * de la misma consulta que ya usa `movements()` — sin duplicar la lógica de las 4 fuentes
     * (CashMovement/Payment/cash_ledgers/bank_ledgers).
     */
    public function buildResumenData(Request $request): array
    {
        $user = auth()->user();

        $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to'   => ['nullable', 'date'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
        ]);

        [$items, $isSuperAdmin, $movementBranchId] = $this->resolveMovementsItems($request, $user);

        $cobroTypes = ['cobro_efectivo', 'cobro_banco'];
        $typeLabels = [
            'cobro_efectivo' => 'Cobros efectivo',
            'cobro_banco'    => 'Cobros banco/tarjeta',
            'entrada'        => 'Entrada manual',
            'retiro'         => 'Retiro',
            'deposito_banco' => 'Depósito banco',
            'gasto'          => 'Gasto',
            'perdida'        => 'Pérdida',
        ];

        $totalEntradas = round($items->where('direction', 'entrada')->sum('amount'), 2);
        $totalSalidas  = round($items->where('direction', 'salida')->sum('amount'), 2);
        $totalCobrado  = round($items->whereIn('type', $cobroTypes)->sum('amount'), 2);

        // Agrupado por tipo Y dirección — nunca solo por tipo. Una reversión conserva el
        // `type` del movimiento original pero invierte la `direction` (ver revertMovement()):
        // agrupar solo por tipo sumaría un "entrada" original junto con su reversión (salida)
        // en la misma fila, mostrando el doble del monto real en vez de que se cancelen.
        $byTypeFor = fn (string $direction) => collect($typeLabels)
            ->map(function ($label, $type) use ($items, $direction) {
                $group = $items->where('type', $type)->where('direction', $direction);

                return ['type' => $type, 'label' => $label, 'count' => $group->count(), 'amount' => round($group->sum('amount'), 2)];
            })
            ->filter(fn ($g) => $g['count'] > 0)
            ->values();

        return [
            'dateFrom'        => $request->input('date_from') ?: 'inicio',
            'dateTo'          => $request->input('date_to') ?: now()->toDateString(),
            'branchName'      => $movementBranchId ? Branch::find($movementBranchId)?->name : 'Todas las sucursales',
            'canSelectBranch' => $isSuperAdmin,
            'totalCobrado'    => $totalCobrado,
            'totalEntradas'   => $totalEntradas,
            'totalSalidas'    => $totalSalidas,
            'neto'            => round($totalEntradas - $totalSalidas, 2),
            'byTypeEntradas'  => $byTypeFor('entrada'),
            'byTypeSalidas'   => $byTypeFor('salida'),
            'generatedBy'     => $user->name,
            'businessName'    => $this->businessName(),
            'logoPath'        => $this->brandLogoPath(),
        ];
    }

    /**
     * "Por operador" — quién registró cada movimiento (manual o cobro), separado en
     * entradas/salidas por la misma razón que el desglose por tipo del Resumen: una reversión
     * la crea quien la revierte, no necesariamente el mismo operador del movimiento original,
     * así que mezclar ambos sentidos en un solo monto por operador podría verse raro (un
     * operador con una sola reversión grande se vería con "actividad" que en realidad es de
     * otra persona cancelándola) — mantenerlos separados es más honesto.
     */
    public function buildPorOperadorData(Request $request): array
    {
        $user = auth()->user();

        $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to'   => ['nullable', 'date'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
        ]);

        [$items, $isSuperAdmin, $movementBranchId] = $this->resolveMovementsItems($request, $user);

        $byOperator = $items
            ->groupBy(fn ($m) => $m['created_by'] ?: 'Sin registrar')
            ->map(function ($group, $name) {
                return [
                    'name'          => $name,
                    'count'         => $group->count(),
                    'totalEntradas' => round($group->where('direction', 'entrada')->sum('amount'), 2),
                    'totalSalidas'  => round($group->where('direction', 'salida')->sum('amount'), 2),
                ];
            })
            ->sortByDesc(fn ($g) => $g['totalEntradas'] + $g['totalSalidas'])
            ->values();

        return [
            'dateFrom'        => $request->input('date_from') ?: 'inicio',
            'dateTo'          => $request->input('date_to') ?: now()->toDateString(),
            'branchName'      => $movementBranchId ? Branch::find($movementBranchId)?->name : 'Todas las sucursales',
            'canSelectBranch' => $isSuperAdmin,
            'byOperator'      => $byOperator,
            'generatedBy'     => $user->name,
            'businessName'    => $this->businessName(),
            'logoPath'        => $this->brandLogoPath(),
        ];
    }

    /**
     * "Pendientes por cobrar" — a diferencia de los demás reportes de Caja, esto NO sale de
     * `resolveMovementsItems()` (esa consulta es de dinero que YA se movió) ni tiene selector de
     * período: es una lista viva de citas terminadas o en proceso con saldo pendiente real,
     * ordenadas por fecha (las más viejas primero, las más urgentes de cobrar). Reusa
     * `SpaBooking::unpaidBalance()`, el mismo método que ya usa el backoffice web para esto — no
     * se reimplementa el cálculo acá (ver el bug real de "Total" en $0 para citas cobradas desde
     * móvil sin Quote, ya corregido una vez en ese método, documentado en su propio docblock —
     * repetir la lógica acá arriesgaría el mismo bug de nuevo).
     * Sin filtro de sucursal: `SpaBooking` no tiene `branch_id` propio todavía (ver BL-078 de
     * EstetiCAN — hoy solo existe una sucursal real en producción, no es una limitación
     * práctica ahora mismo).
     */
    public function buildPendientesData(): array
    {
        $user = auth()->user();

        $items = SpaBooking::whereIn('status', ['work_order', 'completed'])
            ->with(['pet.client', 'quotes.cashLedgers', 'quotes.bankLedgers', 'payments'])
            ->orderBy('scheduled_at')
            ->get()
            ->map(fn (SpaBooking $b) => [
                'booking'  => $b,
                'unpaid'   => round($b->unpaidBalance(), 2),
            ])
            ->filter(fn ($x) => $x['unpaid'] > 0.01)
            ->map(fn ($x) => [
                'petName'     => $x['booking']->pet?->name ?? '—',
                'clientName'  => $x['booking']->pet?->client?->full_name ?? '—',
                'scheduledAt' => $x['booking']->scheduled_at?->format('Y-m-d H:i'),
                'status'      => $x['booking']->status === 'completed' ? 'Terminado' : 'En proceso',
                'unpaid'      => $x['unpaid'],
            ])
            ->values();

        return [
            'items'          => $items,
            'totalPendiente' => round($items->sum('unpaid'), 2),
            'count'          => $items->count(),
            'generatedAt'    => now()->format('Y-m-d H:i'),
            'generatedBy'    => $user->name,
            'businessName'   => $this->businessName(),
            'logoPath'       => $this->brandLogoPath(),
        ];
    }

    /**
     * "Cierre de turno" — a diferencia de los demás reportes, no recalcula nada: lee
     * directo `cash_sessions.{opening_amount,expected_amount,closing_amount,difference}`, los
     * mismos valores reales que `Finances\CashSessionController::doClose()` ya calculó y
     * guardó al momento del cierre real (a través de `CashSessionExpectedAmountService`, la
     * misma fuente que usa el "saldo esperado" en vivo del celular — ver esa interfaz para el
     * detalle de por qué hacía falta unificarlas). Recalcular "efectivo esperado" acá sería un
     * cuarto lugar con la misma cuenta.
     */
    public function buildCierresData(Request $request): array
    {
        $user = auth()->user();

        $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to'   => ['nullable', 'date'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
        ]);

        $isSuperAdmin = $user->is_super_admin;
        $requestedBranch = $request->filled('branch_id') ? (int) $request->input('branch_id') : null;
        $branchId = $isSuperAdmin ? $requestedBranch : $user->branch_id;

        $from  = $request->filled('date_from') ? $request->date_from . ' 00:00:00' : null;
        $until = $request->filled('date_to')   ? $request->date_to   . ' 23:59:59' : null;

        $sessions = CashSession::where('status', 'cerrada')
            ->with(['branch:id,name', 'closedBy:id,name'])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($from,  fn ($q) => $q->where('closed_at', '>=', $from))
            ->when($until, fn ($q) => $q->where('closed_at', '<=', $until))
            ->orderByDesc('closed_at')
            ->get();

        $items = $sessions->map(fn (CashSession $s) => [
            'branchName'     => $s->branch?->name ?? '—',
            'closedBy'       => $s->closedBy?->name ?? '—',
            'closedAt'       => $s->closed_at?->format('Y-m-d H:i'),
            'openingAmount'  => (float) $s->opening_amount,
            'expectedAmount' => (float) ($s->expected_amount ?? 0),
            'closingAmount'  => (float) ($s->closing_amount ?? 0),
            'difference'     => (float) ($s->difference ?? 0),
        ]);

        return [
            'dateFrom'        => $request->input('date_from') ?: 'inicio',
            'dateTo'          => $request->input('date_to') ?: now()->toDateString(),
            'branchName'      => $branchId ? Branch::find($branchId)?->name : 'Todas las sucursales',
            'canSelectBranch' => $isSuperAdmin,
            'items'           => $items,
            'totalDifference' => round($items->sum('difference'), 2),
            'count'           => $items->count(),
            'generatedBy'     => $user->name,
            'businessName'    => $this->businessName(),
            'logoPath'        => $this->brandLogoPath(),
        ];
    }
}
