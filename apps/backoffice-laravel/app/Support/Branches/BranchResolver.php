<?php

namespace App\Support\Branches;

use App\Models\Branch;
use App\Models\Operator;
use App\Models\Payment;
use App\Models\SpaBooking;
use App\Models\User;

/**
 * Sucursal de una cita o de un pago nuevo (B1, 27/09/2026) — un solo lugar para todos los
 * caminos que los crean (web, móvil, presupuesto, reembolso), vía los eventos `creating` de los
 * modelos. Nunca adivina: si nada lo decide, queda null.
 */
class BranchResolver
{
    public static function forBooking(SpaBooking $booking): ?int
    {
        return self::forNewBooking($booking->operator_id);
    }

    /**
     * Sucursal que tendrá una cita que todavía no existe — la misma regla que al crearla. B2 la
     * usa para validar contra el horario de esa sucursal antes de guardar.
     */
    public static function forNewBooking(?int $operatorId): ?int
    {
        return self::userBranch(auth()->user())
            ?? self::operatorBranch($operatorId)
            ?? self::onlyActiveBranch();
    }

    /** Sucursal de un operador si tiene exactamente una (horario base de su semana). */
    public static function forOperator(?int $operatorId): ?int
    {
        return self::operatorBranch($operatorId);
    }

    public static function forPayment(Payment $payment): ?int
    {
        if ($payment->payable_type === SpaBooking::class || $payment->payable_type === (new SpaBooking)->getMorphClass()) {
            $bookingBranch = SpaBooking::whereKey($payment->payable_id)->value('branch_id');

            if ($bookingBranch) {
                return (int) $bookingBranch;
            }
        }

        return self::userBranch(auth()->user()) ?? self::onlyActiveBranch();
    }

    private static function userBranch(mixed $user): ?int
    {
        return $user instanceof User && $user->branch_id ? (int) $user->branch_id : null;
    }

    /** La del operador, solo si tiene exactamente una. */
    private static function operatorBranch(?int $operatorId): ?int
    {
        if (! $operatorId) {
            return null;
        }

        $branchIds = Operator::find($operatorId)?->branches()->pluck('branches.id');

        return $branchIds && $branchIds->count() === 1 ? (int) $branchIds->first() : null;
    }

    /** La única sucursal activa, si solo hay una (el caso de hoy en producción). */
    private static function onlyActiveBranch(): ?int
    {
        $ids = Branch::where('is_active', true)->limit(2)->pluck('id');

        return $ids->count() === 1 ? (int) $ids->first() : null;
    }
}
