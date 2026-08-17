<?php

namespace App\Domain\Accounting\Contracts;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * Arma los datos agregados de los 5 reportes de Caja (Resumen, Métodos de pago, Por operador,
 * Pendientes por cobrar, Cierre de turno) — una sola fuente de verdad usada tanto por
 * `Api\CashController` (celular, JSON/PDF/email) como por `Finances\CashReportController`
 * (backoffice web, HTML/PDF/email). Extraído el 16/08/2026 de `Api\CashController` (donde vivía
 * como métodos privados) al construir la versión web de estos mismos reportes — mismo principio
 * ya aplicado con `CashSessionExpectedAmountServiceInterface`: evitar que dos implementaciones
 * independientes de la misma agregación diverjan (ya pasó una vez, ver el bug de
 * agrupar-por-type-sin-direction documentado en `buildResumenData()`).
 */
interface CashReportServiceInterface
{
    /**
     * Colección unificada de movimientos de caja del período (CashMovement manual + cobros de
     * Payment/cash_ledgers/bank_ledgers), con el scope de sucursal ya resuelto.
     *
     * @return array{0: \Illuminate\Support\Collection, 1: bool, 2: int|null} [items, isSuperAdmin, movementBranchId]
     */
    public function resolveMovementsItems(Request $request, User $user): array;

    public function brandLogoPath(): ?string;

    public function buildResumenData(Request $request): array;

    public function buildMetodosPagoData(Request $request): array;

    public function buildPorOperadorData(Request $request): array;

    public function buildPendientesData(): array;

    public function buildCierresData(Request $request): array;
}
