<?php

namespace App\Domain\Accounting\Contracts;

use App\Models\CashSession;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Calcula el "efectivo esperado" de una sesión de caja — fondo inicial + cobros reales a
 * clientes (Payment/cash_ledgers, destino "caja") + movimientos manuales de CashMovement.
 *
 * Extraído el 16/08/2026 de `Finances\CashSessionController` (donde vivía duplicado como
 * métodos privados) tras encontrar que `Api\CashController::session()` (el "saldo esperado"
 * que ve un operador en el celular con el turno todavía abierto) usaba una fórmula
 * incompleta — nunca sumaba los cobros reales, solo `CashMovement` — mientras que el cierre
 * real del backoffice web sí los sumaba. Una sola fuente de verdad para los dos.
 *
 * Nota de diseño heredada: `Payment`/`cash_ledgers` no tienen `cash_session_id` — no hay forma
 * directa de saber "este cobro es de este turno". Se resuelve con una ventana de tiempo: desde
 * que cerró la sesión anterior de la misma caja registradora (`periodStart()`) hasta ahora.
 */
interface CashSessionExpectedAmountServiceInterface
{
    /** Cuándo cerró la sesión anterior de la misma caja registradora, o null si es la primera. */
    public function periodStart(CashSession $cashSession): ?Carbon;

    /**
     * Colección unificada de cobros del período (Payment + cash_ledgers + bank_ledgers legacy),
     * para mostrar el detalle línea por línea (ver `finances.cash-sessions.show`).
     */
    public function paymentsForPeriod(?Carbon $from, mixed $until): Collection;

    /**
     * Efectivo esperado de la sesión: fondo inicial + cobros en efectivo del período +
     * entradas manuales - salidas manuales. `$until` es null para una sesión todavía abierta
     * (cuenta hasta "ahora"); pasar `closed_at` para recalcular una sesión ya cerrada.
     */
    public function expectedAmount(CashSession $cashSession, mixed $until = null): float;
}
