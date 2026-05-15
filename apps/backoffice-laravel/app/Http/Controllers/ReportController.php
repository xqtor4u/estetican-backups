<?php

namespace App\Http\Controllers;

use App\Models\SpaBooking;
use App\Models\Quote;
use App\Support\SystemSettings\SystemSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ReportController extends Controller
{
    protected SystemSettings $settings;

    public function __construct(SystemSettings $settings)
    {
        $this->settings = $settings;
    }

    /**
     * Ver presupuesto (Cotización)
     */
    public function quote(Quote $quote)
    {
        $quote->load(['booking.pet.client', 'items.service']);
        $booking = $quote->booking;
        $settings = $this->getReportSettings();

        return view('reports.quote', compact('quote', 'booking', 'settings'));
    }

    /**
     * Ver orden de trabajo (Interna)
     */
    public function workOrder(SpaBooking $booking)
    {
        $booking->load(['pet.client', 'quotes.items.service', 'executedServices.service']);
        $settings = $this->getReportSettings();
        
        $acceptedQuote = $booking->quotes->firstWhere('status', 'accepted');

        return view('reports.work-order', compact('booking', 'acceptedQuote', 'settings'));
    }

    /**
     * Ver recibo / factura (Liquidación)
     */
    public function invoice(SpaBooking $booking)
    {
        $booking->load(['pet.client', 'quotes.items.service', 'quotes.cashLedgers', 'quotes.bankLedgers']);
        $settings = $this->getReportSettings();
        
        $acceptedQuote = $booking->quotes->firstWhere('status', 'accepted');

        return view('reports.invoice', compact('booking', 'acceptedQuote', 'settings'));
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
