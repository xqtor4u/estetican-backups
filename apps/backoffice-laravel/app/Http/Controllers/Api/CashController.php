<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Branch;
use App\Models\CashMovement;
use App\Models\CashRegister;
use App\Models\CashSession;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\SpaBooking;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CashController extends Controller
{
    private const CAJA_ACCOUNT_ID = 6;
    private const SALIDA_TYPES = ['retiro', 'deposito_banco', 'gasto', 'perdida'];

    public function __construct(
        private readonly \App\Domain\Accounting\Contracts\CashSessionExpectedAmountServiceInterface $expectedAmountService,
    ) {}

    /**
     * Resuelve a qué sucursal se refiere Caja para este request — NUNCA a partir del check-in
     * (eso es solo asistencia/RH, sin relación con autorización). Para un usuario normal es su
     * `branch_id` asignado (dato organizacional persistente); para un super-admin, la sucursal
     * que elija explícitamente vía `branch_id` en la query/body, porque un super-admin puede
     * operar cualquier caja de cualquier sucursal.
     *
     * @return array{0: int|null, 1: bool} [branchId, needsSelection] — needsSelection=true solo
     *         para super-admin sin `branch_id` en el request (hace falta que elija una).
     */
    private function resolveBranchId(Request $request, User $user): array
    {
        if ($user->is_super_admin) {
            $branchId = $request->filled('branch_id') ? (int) $request->input('branch_id') : null;

            return [$branchId, $branchId === null];
        }

        return [$user->branch_id, false];
    }

    private function serializeMovement(CashMovement $m): array
    {
        return [
            'id'                      => $m->id,
            'type'                    => $m->type,
            'direction'               => $m->direction,
            'amount'                  => $m->amount,
            'concept'                 => $m->concept,
            'notes'                   => $m->notes,
            'account'                 => $m->counterpartAccount?->name,
            'created_at'              => $m->created_at,
            'is_reversed'             => $m->isReversed(),
            'reversal_of_movement_id' => $m->reversal_of_movement_id,
        ];
    }

    public function session(Request $request): JsonResponse
    {
        $user = auth()->user();
        [$branchId, $needsSelection] = $this->resolveBranchId($request, $user);

        if ($needsSelection) {
            return response()->json([
                'status'   => 'select_branch',
                'branches' => Branch::orderBy('name')->get(['id', 'name']),
            ]);
        }

        if (! $branchId) {
            return response()->json(['status' => 'no_branch']);
        }

        $cashSession = CashSession::where('branch_id', $branchId)
            ->where('status', 'abierta')
            ->with(['cashRegister:id,name', 'branch:id,name', 'openedBy:id,name'])
            ->first();

        if (! $cashSession) {
            return response()->json([
                'status' => 'no_session',
                'branch' => ['id' => $branchId, 'name' => Branch::find($branchId)?->name],
            ]);
        }

        $movements = CashMovement::where('cash_session_id', $cashSession->id)
            ->with('counterpartAccount:id,name')
            ->orderByDesc('created_at')
            ->get();

        $totalEntradasManual = $movements->where('direction', 'entrada')->sum('amount');
        $totalSalidas        = $movements->where('direction', 'salida')->sum('amount');

        // "Entradas" tiene que incluir los cobros reales en efectivo, no solo los movimientos
        // manuales — si no, "saldo esperado" (fondo inicial + entradas - salidas) deja de
        // cuadrar con lo que se muestra arriba. Hallazgo real (16/08/2026): esta pantalla nunca
        // sumó cobros, a diferencia del cierre real del backoffice web — ver
        // CashSessionExpectedAmountServiceInterface para el detalle completo.
        $periodStart   = $this->expectedAmountService->periodStart($cashSession);
        $totalCobros   = $this->expectedAmountService->paymentsForPeriod($periodStart, null)
            ->where('destination', 'caja')
            ->sum('amount');
        $totalEntradas = $totalEntradasManual + $totalCobros;

        return response()->json([
            'status'  => 'active',
            'session' => [
                'id'             => $cashSession->id,
                'register_name'  => $cashSession->cashRegister->name,
                'branch_name'    => $cashSession->branch->name,
                'opened_by'      => $cashSession->openedBy?->name,
                'opened_at'      => $cashSession->opened_at,
                'opening_amount' => $cashSession->opening_amount,
                'notes'          => $cashSession->notes,
            ],
            'totals' => [
                'opening_amount' => $cashSession->opening_amount,
                'total_entradas' => round($totalEntradas, 2),
                'total_salidas'  => round($totalSalidas, 2),
                'saldo_esperado' => round($cashSession->opening_amount + $totalEntradas - $totalSalidas, 2),
            ],
            'movements' => $movements->map(fn ($m) => $this->serializeMovement($m))->values(),
        ]);
    }

    /**
     * Abrir sesión de caja directo desde la app móvil — antes esto solo era posible desde el
     * backoffice web (Finanzas → Cajas → Abrir), la pantalla de Caja del móvil solo te decía
     * "Abre la sesión desde el backoffice de finanzas" sin ninguna forma de resolverlo ahí.
     *
     * La sucursal se resuelve por `branch_id` asignado al usuario (o elegido explícitamente si
     * es super-admin) — nunca por check-in, ver `resolveBranchId()`.
     */
    public function openSession(Request $request): JsonResponse
    {
        $user = auth()->user();
        [$branchId] = $this->resolveBranchId($request, $user);

        if (! $branchId) {
            $message = $user->is_super_admin
                ? 'Selecciona una sucursal.'
                : 'No tienes una sucursal asignada. Contacta a un administrador.';

            return response()->json(['message' => $message], 422);
        }

        $cashRegister = CashRegister::where('branch_id', $branchId)->first();

        if (! $cashRegister) {
            return response()->json(['message' => 'Esta sucursal no tiene ninguna caja configurada.'], 422);
        }

        $validated = $request->validate([
            'opening_amount' => ['required', 'numeric', 'min:0'],
            'notes'          => ['nullable', 'string', 'max:500'],
            'branch_id'      => ['nullable', 'integer'],
        ]);

        // Mismo bloqueo que CashSessionController::store() del lado web (SYNC-011) — evita que
        // dos requests casi simultáneas (doble tap, dos operadores) pasen el chequeo "¿hay
        // sesión abierta?" antes de que exista la primera y terminen creando dos sesiones
        // abiertas para la misma caja.
        $session = DB::transaction(function () use ($cashRegister, $validated, $user) {
            $lockedRegister = CashRegister::lockForUpdate()->findOrFail($cashRegister->id);

            if ($lockedRegister->activeSession) {
                return null;
            }

            return CashSession::create([
                'cash_register_id'   => $lockedRegister->id,
                'branch_id'          => $lockedRegister->branch_id,
                'opened_by_user_id'  => $user->id,
                'opened_at'          => now(),
                'opening_amount'     => $validated['opening_amount'],
                'notes'              => $validated['notes'] ?? null,
                'status'             => 'abierta',
            ]);
        });

        if ($session === null) {
            return response()->json(['message' => 'La caja ya tiene una sesión abierta.'], 409);
        }

        // Reusa la misma forma de respuesta que ya consume el frontend (status: 'active', ...).
        return $this->session($request);
    }

    public function movementTypes(): JsonResponse
    {
        $gastoAccounts = Account::where('type', 'gasto')
            ->whereNotIn('id', [5])
            ->orderBy('code')
            ->get(['id', 'name'])
            ->map(fn ($a) => ['id' => $a->id, 'name' => $a->name])
            ->values();

        $bankAccounts = Account::whereBetween('id', [7, 8])
            ->orderBy('code')
            ->get(['id', 'name'])
            ->map(fn ($a) => ['id' => $a->id, 'name' => $a->name])
            ->values();

        $capAccounts = Account::where('type', 'capital')
            ->whereNotIn('id', [3])
            ->orderBy('code')
            ->get(['id', 'name'])
            ->map(fn ($a) => ['id' => $a->id, 'name' => $a->name])
            ->values();

        return response()->json([
            [
                'type'      => 'retiro',
                'label'     => 'Retiro / disposición',
                'direction' => 'salida',
                'icon'      => 'account_balance_wallet',
                'accounts'  => $gastoAccounts,
            ],
            [
                'type'      => 'deposito_banco',
                'label'     => 'Depósito a banco',
                'direction' => 'salida',
                'icon'      => 'account_balance',
                'accounts'  => $bankAccounts,
            ],
            [
                'type'      => 'gasto',
                'label'     => 'Gasto de caja',
                'direction' => 'salida',
                'icon'      => 'receipt_long',
                'accounts'  => $gastoAccounts,
            ],
            [
                'type'      => 'perdida',
                'label'     => 'Pérdida / faltante',
                'direction' => 'salida',
                'icon'      => 'money_off',
                'accounts'  => [['id' => 24, 'name' => 'Gastos generales']],
            ],
            [
                'type'      => 'entrada',
                'label'     => 'Entrada de efectivo',
                'direction' => 'entrada',
                'icon'      => 'add_circle',
                'accounts'  => $capAccounts,
            ],
        ]);
    }

    public function movements(Request $request): JsonResponse
    {
        $user = auth()->user();

        if (! $user->is_super_admin && ! $user->branch_id) {
            return response()->json(['error' => 'No tienes sucursal asignada'], 422);
        }

        $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to'   => ['nullable', 'date'],
            'type'      => ['nullable', 'string'],
            'direction' => ['nullable', 'in:entrada,salida'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
        ]);

        [$items, $isSuperAdmin, $movementBranchId] = $this->resolveMovementsItems($request, $user);

        $sorted = $items->sortByDesc('created_at')->values();

        return response()->json([
            'movements'      => $sorted,
            'totals'         => [
                'total_entradas' => round($sorted->where('direction', 'entrada')->sum('amount'), 2),
                'total_salidas'  => round($sorted->where('direction', 'salida')->sum('amount'), 2),
                'count'          => $sorted->count(),
            ],
            'branch_filter' => [
                'can_select_branch' => $isSuperAdmin,
                'selected_branch_id' => $movementBranchId,
                'own_branch_id' => $user->branch_id,
            ],
        ]);
    }

    /**
     * Mismos datos agregados que `resumenPdf()`, en JSON — el frontend consume esto en vez de
     * volver a agregar `movements()` crudo por su cuenta (era lo que hacía `MobCajaResumen.tsx`
     * al principio): dos implementaciones independientes de la misma agregación divergieron de
     * verdad en la práctica (bug de agrupar por `type` sin `direction`, encontrado en ambos
     * lados por separado el mismo día) — una sola fuente de verdad la evita de raíz.
     */
    public function resumen(Request $request): JsonResponse
    {
        return response()->json($this->buildResumenData($request));
    }

    /**
     * Reporte "Resumen de caja" en PDF — mismos datos que `movements()`, reempaquetados.
     * Deliberadamente sin preset de "turno actual": esta consulta mezcla `CashMovement` con
     * cobros (`Payment`/`cash_ledgers`/`bank_ledgers`), que `/api/cash/session` nunca incluye —
     * ver la nota en `MobCajaResumen.tsx` del lado móvil.
     */
    public function resumenPdf(Request $request)
    {
        $data = $this->buildResumenData($request);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('cash.resumen-pdf', $data)->setPaper('letter');

        return $pdf->download('resumen-caja-' . $data['dateFrom'] . '-a-' . $data['dateTo'] . '.pdf');
    }

    /** Mismo reporte que `resumenPdf()`, mandado por correo en vez de descargado. */
    public function resumenEmail(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $data = $this->buildResumenData($request);
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('cash.resumen-pdf', $data)->setPaper('letter');
        $filename = 'resumen-caja-' . $data['dateFrom'] . '-a-' . $data['dateTo'] . '.pdf';

        \Illuminate\Support\Facades\Mail::to($validated['email'])
            ->send(new \App\Mail\CashResumenMail($data, $pdf->output(), $filename));

        return response()->json(['message' => 'Reporte enviado a ' . $validated['email'] . '.']);
    }

    /**
     * Reporte "Métodos de pago" — desglosa solo los cobros (cobro_efectivo/cobro_banco) por el
     * `payment_method` real que quedó registrado (Payment/cash_ledgers/bank_ledgers), nunca los
     * movimientos manuales de CashMovement: esos no tienen un "método de pago" en ese sentido,
     * lo que tienen es una cuenta contable contraparte (gasto, banco, capital), un concepto
     * distinto que ya cubre el desglose por tipo del Resumen de caja.
     */
    /** Mismos datos agregados que `metodosPagoPdf()`, en JSON — ver nota en `resumen()`. */
    public function metodosPago(Request $request): JsonResponse
    {
        return response()->json($this->buildMetodosPagoData($request));
    }

    public function metodosPagoPdf(Request $request)
    {
        $data = $this->buildMetodosPagoData($request);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('cash.metodos-pago-pdf', $data)->setPaper('letter');

        return $pdf->download('metodos-pago-' . $data['dateFrom'] . '-a-' . $data['dateTo'] . '.pdf');
    }

    public function metodosPagoEmail(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $data = $this->buildMetodosPagoData($request);
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('cash.metodos-pago-pdf', $data)->setPaper('letter');
        $filename = 'metodos-pago-' . $data['dateFrom'] . '-a-' . $data['dateTo'] . '.pdf';

        \Illuminate\Support\Facades\Mail::to($validated['email'])
            ->send(new \App\Mail\CashMetodosPagoMail($data, $pdf->output(), $filename));

        return response()->json(['message' => 'Reporte enviado a ' . $validated['email'] . '.']);
    }

    private function buildMetodosPagoData(Request $request): array
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
            'businessName'    => app(\App\Support\SystemSettings\SystemSettings::class)->all()['brand_business_name'] ?? 'EstetiCAN',
            'logoPath'        => $this->brandLogoPath(),
        ];
    }

    /**
     * Arma los datos agregados del "Resumen de caja" (tarjetas + desglose por tipo) a partir
     * de la misma consulta que ya usa `movements()` — sin duplicar la lógica de las 4 fuentes
     * (CashMovement/Payment/cash_ledgers/bank_ledgers).
     */
    private function buildResumenData(Request $request): array
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
            'businessName'    => app(\App\Support\SystemSettings\SystemSettings::class)->all()['brand_business_name'] ?? 'EstetiCAN',
            'logoPath'        => $this->brandLogoPath(),
        ];
    }

    // ── Por operador ────────────────────────────────────────────────────────

    public function porOperador(Request $request): JsonResponse
    {
        return response()->json($this->buildPorOperadorData($request));
    }

    public function porOperadorPdf(Request $request)
    {
        $data = $this->buildPorOperadorData($request);
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('cash.por-operador-pdf', $data)->setPaper('letter');

        return $pdf->download('por-operador-' . $data['dateFrom'] . '-a-' . $data['dateTo'] . '.pdf');
    }

    public function porOperadorEmail(Request $request): JsonResponse
    {
        $validated = $request->validate(['email' => ['required', 'email']]);

        $data = $this->buildPorOperadorData($request);
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('cash.por-operador-pdf', $data)->setPaper('letter');
        $filename = 'por-operador-' . $data['dateFrom'] . '-a-' . $data['dateTo'] . '.pdf';

        \Illuminate\Support\Facades\Mail::to($validated['email'])
            ->send(new \App\Mail\CashPorOperadorMail($data, $pdf->output(), $filename));

        return response()->json(['message' => 'Reporte enviado a ' . $validated['email'] . '.']);
    }

    /**
     * "Por operador" — quién registró cada movimiento (manual o cobro), separado en
     * entradas/salidas por la misma razón que el desglose por tipo del Resumen: una reversión
     * la crea quien la revierte, no necesariamente el mismo operador del movimiento original,
     * así que mezclar ambos sentidos en un solo monto por operador podría verse raro (un
     * operador con una sola reversión grande se vería con "actividad" que en realidad es de
     * otra persona cancelándola) — mantenerlos separados es más honesto.
     */
    private function buildPorOperadorData(Request $request): array
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
            'businessName'    => app(\App\Support\SystemSettings\SystemSettings::class)->all()['brand_business_name'] ?? 'EstetiCAN',
            'logoPath'        => $this->brandLogoPath(),
        ];
    }

    // ── Pendientes por cobrar ───────────────────────────────────────────────

    public function pendientes(Request $request): JsonResponse
    {
        return response()->json($this->buildPendientesData());
    }

    public function pendientesPdf(Request $request)
    {
        $data = $this->buildPendientesData();
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('cash.pendientes-pdf', $data)->setPaper('letter');

        return $pdf->download('pendientes-por-cobrar-' . now()->toDateString() . '.pdf');
    }

    public function pendientesEmail(Request $request): JsonResponse
    {
        $validated = $request->validate(['email' => ['required', 'email']]);

        $data = $this->buildPendientesData();
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('cash.pendientes-pdf', $data)->setPaper('letter');
        $filename = 'pendientes-por-cobrar-' . now()->toDateString() . '.pdf';

        \Illuminate\Support\Facades\Mail::to($validated['email'])
            ->send(new \App\Mail\CashPendientesMail($data, $pdf->output(), $filename));

        return response()->json(['message' => 'Reporte enviado a ' . $validated['email'] . '.']);
    }

    /**
     * "Pendientes por cobrar" — a diferencia de los demás reportes de Caja, esto NO sale de
     * `movements()`/`resolveMovementsItems()` (esa consulta es de dinero que YA se movió) ni
     * tiene selector de período: es una lista viva de citas terminadas o en proceso con saldo
     * pendiente real, ordenadas por fecha (las más viejas primero, las más urgentes de
     * cobrar). Reusa `SpaBooking::unpaidBalance()`, el mismo método que ya usa el backoffice
     * web para esto — no se reimplementa el cálculo acá (ver el bug real de "Total" en $0 para
     * citas cobradas desde móvil sin Quote, ya corregido una vez en ese método, documentado en
     * su propio docblock — repetir la lógica acá arriesgaría el mismo bug de nuevo).
     * Sin filtro de sucursal: `SpaBooking` no tiene `branch_id` propio todavía (ver BL-078 de
     * EstetiCAN — hoy solo existe una sucursal real en producción, no es una limitación
     * práctica ahora mismo).
     */
    private function buildPendientesData(): array
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
            'businessName'   => app(\App\Support\SystemSettings\SystemSettings::class)->all()['brand_business_name'] ?? 'EstetiCAN',
            'logoPath'       => $this->brandLogoPath(),
        ];
    }

    // ── Cierre de turno ─────────────────────────────────────────────────────

    public function cierres(Request $request): JsonResponse
    {
        return response()->json($this->buildCierresData($request));
    }

    public function cierresPdf(Request $request)
    {
        $data = $this->buildCierresData($request);
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('cash.cierres-pdf', $data)->setPaper('letter');

        return $pdf->download('cierres-de-turno-' . $data['dateFrom'] . '-a-' . $data['dateTo'] . '.pdf');
    }

    public function cierresEmail(Request $request): JsonResponse
    {
        $validated = $request->validate(['email' => ['required', 'email']]);

        $data = $this->buildCierresData($request);
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('cash.cierres-pdf', $data)->setPaper('letter');
        $filename = 'cierres-de-turno-' . $data['dateFrom'] . '-a-' . $data['dateTo'] . '.pdf';

        \Illuminate\Support\Facades\Mail::to($validated['email'])
            ->send(new \App\Mail\CashCierresMail($data, $pdf->output(), $filename));

        return response()->json(['message' => 'Reporte enviado a ' . $validated['email'] . '.']);
    }

    /**
     * "Cierre de turno" — a diferencia de los demás reportes, no recalcula nada: lee
     * directo `cash_sessions.{opening_amount,expected_amount,closing_amount,difference}`, los
     * mismos valores reales que `Finances\CashSessionController::doClose()` ya calculó y
     * guardó al momento del cierre real. Recalcular "efectivo esperado" acá con la misma
     * fórmula de `resolveMovementsItems()` sería un tercer lugar con la cuenta — y esa fórmula
     * ni siquiera es la misma: `doClose()` sí suma `Payment` con `destination=caja` al
     * esperado, cosa que `CashController::session()` (el "saldo esperado" que ve un operador
     * con el turno todavía abierto) no hace todavía. No se toca esa discrepancia acá, queda
     * anotada para revisar aparte — este reporte solo lee lo que ya quedó guardado en el
     * cierre real, that's la fuente de verdad para un turno YA cerrado.
     */
    private function buildCierresData(Request $request): array
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
            'businessName'    => app(\App\Support\SystemSettings\SystemSettings::class)->all()['brand_business_name'] ?? 'EstetiCAN',
            'logoPath'        => $this->brandLogoPath(),
        ];
    }

    private function brandLogoPath(): ?string
    {
        $logo = app(\App\Support\SystemSettings\SystemSettings::class)->all()['brand_logo_print']
            ?? app(\App\Support\SystemSettings\SystemSettings::class)->all()['brand_logo_web']
            ?? null;

        if (! $logo || ! \Illuminate\Support\Facades\Storage::disk('public')->exists($logo)) {
            return null;
        }

        return \Illuminate\Support\Facades\Storage::disk('public')->path($logo);
    }

    /**
     * @return array{0: \Illuminate\Support\Collection, 1: bool, 2: int|null} [items, isSuperAdmin, movementBranchId]
     */
    private function resolveMovementsItems(Request $request, User $user): array
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
            $payments = \App\Models\Payment::with(['client', 'createdBy:id,name'])
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
                'created_at'  => \Illuminate\Support\Carbon::parse($p->created_at)->toISOString(),
                'is_reversed' => false,
                'reversal_of_movement_id' => null,
            ]));
        }

        // ── Cobros legacy (cash_ledgers) ──────────────────────
        if ($includeEfectivo) {
            $cashRows = \Illuminate\Support\Facades\DB::table('cash_ledgers')
                ->leftJoin('clients', 'cash_ledgers.client_id', '=', 'clients.id')
                ->leftJoin('users', 'cash_ledgers.created_by_user_id', '=', 'users.id')
                ->when($from,  fn ($q) => $q->where('cash_ledgers.created_at', '>=', $from))
                ->when($until, fn ($q) => $q->where('cash_ledgers.created_at', '<=', $until))
                ->select('cash_ledgers.id', 'cash_ledgers.created_at', 'cash_ledgers.amount',
                         'cash_ledgers.payment_method', 'users.name as created_by_name',
                         \Illuminate\Support\Facades\DB::raw("CONCAT_WS(' ', clients.first_name, clients.apellido_paterno, clients.apellido_materno) as client_name"))
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
                'created_at'  => \Illuminate\Support\Carbon::parse($r->created_at)->toISOString(),
                'is_reversed' => false,
                'reversal_of_movement_id' => null,
            ]));
        }

        // ── Cobros legacy (bank_ledgers) ──────────────────────
        if ($includeBanco) {
            $bankRows = \Illuminate\Support\Facades\DB::table('bank_ledgers')
                ->leftJoin('clients', 'bank_ledgers.client_id', '=', 'clients.id')
                ->leftJoin('users', 'bank_ledgers.created_by_user_id', '=', 'users.id')
                ->when($from,  fn ($q) => $q->where('bank_ledgers.created_at', '>=', $from))
                ->when($until, fn ($q) => $q->where('bank_ledgers.created_at', '<=', $until))
                ->select('bank_ledgers.id', 'bank_ledgers.created_at', 'bank_ledgers.amount',
                         'bank_ledgers.payment_method', 'users.name as created_by_name',
                         \Illuminate\Support\Facades\DB::raw("CONCAT_WS(' ', clients.first_name, clients.apellido_paterno, clients.apellido_materno) as client_name"))
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
                'created_at'  => \Illuminate\Support\Carbon::parse($r->created_at)->toISOString(),
                'is_reversed' => false,
                'reversal_of_movement_id' => null,
            ]));
        }

        return [$items, $isSuperAdmin, $movementBranchId];
    }

    public function storeMovement(Request $request, CashSession $cashSession): JsonResponse
    {
        $user = $request->user();

        if (! $user->is_super_admin && $user->branch_id !== $cashSession->branch_id) {
            return response()->json(['message' => 'Esta sesión de caja no corresponde a tu sucursal asignada.'], 403);
        }

        if (! $cashSession->isOpen()) {
            return response()->json(['message' => 'La sesión ya está cerrada.'], 422);
        }

        $validated = $request->validate([
            'type'                   => ['required', 'in:retiro,deposito_banco,gasto,perdida,entrada'],
            'amount'                 => ['required', 'numeric', 'min:0.01'],
            'concept'                => ['required', 'string', 'max:255'],
            'notes'                  => ['nullable', 'string', 'max:1000'],
            'counterpart_account_id' => ['required', 'exists:accounts,id'],
        ]);

        $direction = in_array($validated['type'], self::SALIDA_TYPES) ? 'salida' : 'entrada';
        $amount    = (float) $validated['amount'];

        $labels = [
            'retiro'         => 'Retiro de caja',
            'deposito_banco' => 'Depósito a banco',
            'gasto'          => 'Gasto de caja',
            'perdida'        => 'Pérdida / faltante',
            'entrada'        => 'Entrada de efectivo',
        ];

        $entry = JournalEntry::create([
            'entry_date'         => now()->toDateString(),
            'description'        => ($labels[$validated['type']] ?? 'Movimiento de caja') . ' — ' . $validated['concept'],
            'status'             => 'aplicado',
            'branch_id'          => $cashSession->branch_id,
            'created_by_user_id' => auth()->id(),
            'posted_by_user_id'  => auth()->id(),
            'posted_at'          => now(),
            'reference_type'     => CashSession::class,
            'reference_id'       => $cashSession->id,
        ]);

        if ($direction === 'salida') {
            JournalEntryLine::create(['journal_entry_id' => $entry->id, 'account_id' => $validated['counterpart_account_id'], 'debit' => $amount, 'credit' => 0, 'description' => $validated['concept']]);
            JournalEntryLine::create(['journal_entry_id' => $entry->id, 'account_id' => self::CAJA_ACCOUNT_ID, 'debit' => 0, 'credit' => $amount, 'description' => $validated['concept']]);
        } else {
            JournalEntryLine::create(['journal_entry_id' => $entry->id, 'account_id' => self::CAJA_ACCOUNT_ID, 'debit' => $amount, 'credit' => 0, 'description' => $validated['concept']]);
            JournalEntryLine::create(['journal_entry_id' => $entry->id, 'account_id' => $validated['counterpart_account_id'], 'debit' => 0, 'credit' => $amount, 'description' => $validated['concept']]);
        }

        $movement = CashMovement::create([
            'cash_session_id'        => $cashSession->id,
            'type'                   => $validated['type'],
            'direction'              => $direction,
            'amount'                 => $amount,
            'concept'                => $validated['concept'],
            'notes'                  => $validated['notes'] ?? null,
            'counterpart_account_id' => $validated['counterpart_account_id'],
            'journal_entry_id'       => $entry->id,
            'created_by_user_id'     => auth()->id(),
        ]);

        $movement->load('counterpartAccount:id,name');

        return response()->json($this->serializeMovement($movement), 201);
    }

    /**
     * Editar un movimiento ya registrado — deliberadamente limitado a concepto/notas (texto).
     * Nunca se edita monto ni tipo: cada movimiento ya tiene un asiento contable real posteado
     * (`JournalEntry`), cambiar esos campos sin tocar el asiento rompería la contabilidad. Si el
     * monto estaba mal, el flujo correcto es revertir (`revertMovement`) y registrar uno nuevo.
     */
    public function updateMovement(Request $request, CashMovement $movement): JsonResponse
    {
        $user = $request->user();
        $movement->loadMissing('cashSession');

        if (! $user->is_super_admin && $user->branch_id !== $movement->cashSession->branch_id) {
            return response()->json(['message' => 'Este movimiento no corresponde a tu sucursal asignada.'], 403);
        }

        if (! $movement->cashSession->isOpen()) {
            return response()->json(['message' => 'La caja de este movimiento ya está cerrada.'], 422);
        }

        $validated = $request->validate([
            'concept' => ['required', 'string', 'max:255'],
            'notes'   => ['nullable', 'string', 'max:1000'],
        ]);

        $movement->update($validated);
        $movement->load('counterpartAccount:id,name');

        return response()->json($this->serializeMovement($movement));
    }

    /**
     * Revertir un movimiento — nunca se borra: se crea un movimiento contrario (mismo monto,
     * dirección opuesta) con su propio asiento contable espejo, y el original queda marcado
     * `reversed_at` sin desaparecer. Mantiene el rastro de auditoría y la contabilidad cuadrada
     * (el efecto neto de original + reversión es cero).
     */
    public function revertMovement(Request $request, CashMovement $movement): JsonResponse
    {
        $user = $request->user();
        $movement->loadMissing('cashSession');

        if (! $user->is_super_admin && $user->branch_id !== $movement->cashSession->branch_id) {
            return response()->json(['message' => 'Este movimiento no corresponde a tu sucursal asignada.'], 403);
        }

        if ($movement->reversal_of_movement_id) {
            return response()->json(['message' => 'No se puede revertir una reversión.'], 422);
        }

        if (! $movement->cashSession->isOpen()) {
            return response()->json(['message' => 'La caja de este movimiento ya está cerrada.'], 422);
        }

        $reversal = DB::transaction(function () use ($movement, $user) {
            $locked = CashMovement::lockForUpdate()->findOrFail($movement->id);

            if ($locked->isReversed()) {
                return null;
            }

            $oppositeDirection = $locked->direction === 'entrada' ? 'salida' : 'entrada';
            $description       = 'Reversión — ' . $locked->concept;

            $entry = JournalEntry::create([
                'entry_date'         => now()->toDateString(),
                'description'        => $description,
                'status'             => 'aplicado',
                'branch_id'          => $locked->cashSession->branch_id,
                'created_by_user_id' => $user->id,
                'posted_by_user_id'  => $user->id,
                'posted_at'          => now(),
                'reference_type'     => CashSession::class,
                'reference_id'       => $locked->cash_session_id,
            ]);

            // Espejo exacto e invertido del asiento original (mismas cuentas, débito/crédito al revés).
            if ($oppositeDirection === 'salida') {
                JournalEntryLine::create(['journal_entry_id' => $entry->id, 'account_id' => $locked->counterpart_account_id, 'debit' => $locked->amount, 'credit' => 0, 'description' => $description]);
                JournalEntryLine::create(['journal_entry_id' => $entry->id, 'account_id' => self::CAJA_ACCOUNT_ID, 'debit' => 0, 'credit' => $locked->amount, 'description' => $description]);
            } else {
                JournalEntryLine::create(['journal_entry_id' => $entry->id, 'account_id' => self::CAJA_ACCOUNT_ID, 'debit' => $locked->amount, 'credit' => 0, 'description' => $description]);
                JournalEntryLine::create(['journal_entry_id' => $entry->id, 'account_id' => $locked->counterpart_account_id, 'debit' => 0, 'credit' => $locked->amount, 'description' => $description]);
            }

            $newReversal = CashMovement::create([
                'cash_session_id'         => $locked->cash_session_id,
                'type'                    => $locked->type,
                'direction'               => $oppositeDirection,
                'amount'                  => $locked->amount,
                'concept'                 => 'Reversión: ' . $locked->concept,
                'notes'                   => $locked->notes,
                'counterpart_account_id'  => $locked->counterpart_account_id,
                'journal_entry_id'        => $entry->id,
                'created_by_user_id'      => $user->id,
                'reversal_of_movement_id' => $locked->id,
            ]);

            $locked->update(['reversed_at' => now()]);

            return $newReversal;
        });

        if ($reversal === null) {
            return response()->json(['message' => 'Este movimiento ya fue revertido.'], 409);
        }

        $reversal->load('counterpartAccount:id,name');

        return response()->json($this->serializeMovement($reversal), 201);
    }
}
