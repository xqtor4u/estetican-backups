<?php

namespace App\Http\Controllers;

use App\Domain\Accounting\Contracts\AccountingServiceInterface;
use App\Models\Payment;
use App\Models\SpaBooking;
use App\Models\Quote;
use App\Support\SystemSettings\SystemSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ReportController extends Controller
{
    protected SystemSettings $settings;

    public function __construct(
        SystemSettings $settings,
        private readonly AccountingServiceInterface $accountingService,
    ) {
        $this->settings = $settings;
    }

    /**
     * Ver presupuesto (Cotización)
     */
    public function quote(Quote $quote)
    {
        $quote->load(['spaBooking.pet.client.phones', 'items.service', 'items.item']);
        $booking = $quote->spaBooking;
        $this->ensureOrderFolio($booking);
        $settings = $this->getReportSettings();

        return view('reports.quote', compact('quote', 'booking', 'settings'));
    }

    /**
     * Ver orden de trabajo (Interna)
     */
    public function workOrder(SpaBooking $booking)
    {
        $booking->load([
            'pet.client',
            'pet.medicalAlerts',
            'quotes.items.service',
            'quotes.items.item',
            'quotes.items.operator',
            'items.item',
            'resourceAllocations.resource',
            'executedServices.service',
            'services.service',
            'operator',
            'processNotes.user:id,name',
        ]);
        $this->ensureOrderFolio($booking);
        $settings = $this->getReportSettings();

        $acceptedQuote = $booking->quotes->firstWhere('status', 'accepted');

        return view('reports.work-order', compact('booking', 'acceptedQuote', 'settings'));
    }

    /**
     * Ver recibo / factura (Liquidación)
     */
    public function invoice(SpaBooking $booking)
    {
        $booking->load([
            'pet.client.phones',
            'quotes.items.service',
            'quotes.items.item',
            'quotes.cashLedgers',
            'quotes.bankLedgers',
            'services.service',
            'processNotes.user:id,name',
        ]);
        $this->ensureOrderFolio($booking);
        $settings = $this->getReportSettings();

        $acceptedQuote = $booking->quotes->firstWhere('status', 'accepted');

        // Cobros registrados directamente sobre el booking (app móvil) — no pasan por Quote/CashLedger/BankLedger.
        $directPayments = Payment::where('payable_type', SpaBooking::class)
            ->where('payable_id', $booking->id)
            ->get();

        return view('reports.invoice', compact('booking', 'acceptedQuote', 'settings', 'directPayments'));
    }

    /**
     * Presupuesto, orden de trabajo y recibo comparten el mismo folio (order_folio) —
     * el que se imprima primero lo asigna, y los demás lo heredan (assignOrderFolio()
     * ya es idempotente si el booking ya tiene uno).
     */
    private function ensureOrderFolio(?SpaBooking $booking): void
    {
        if ($booking && ! $booking->order_folio) {
            $this->accountingService->assignOrderFolio($booking);
            $booking->refresh();
        }
    }

    /**
     * Obtener configuración agrupada para reportes
     */
    private function getReportSettings(): array
    {
        $all = $this->settings->all();
        
        return [
            'branding' => [
                'brand_business_name' => $all['brand_business_name'] ?? 'EstetiCAN',
                'brand_logo_print' => $all['brand_logo_print'] ?? $all['brand_logo_web'] ?? null,
                'brand_url' => $all['mail_signature_url'] ?? '',
            ],
            'fiscal' => [
                'fiscal_legal_name' => $all['fiscal_legal_name'] ?? '',
                'fiscal_id' => $all['fiscal_id'] ?? '',
                'fiscal_address' => $all['fiscal_address'] ?? '',
                'fiscal_report_footer' => $all['fiscal_report_footer'] ?? 'Gracias por su confianza.',
            ],
            'system' => [
                'currency_code' => $all['system_currency_code'] ?? 'MXN',
                'date_format' => $all['system_date_format'] ?? 'd/m/Y',
            ]
        ];
    }
}
