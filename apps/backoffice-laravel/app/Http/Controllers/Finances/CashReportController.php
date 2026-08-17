<?php

namespace App\Http\Controllers\Finances;

use App\Domain\Accounting\Contracts\CashReportServiceInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

/**
 * Versión web (backoffice) de los mismos 5 reportes de Caja que ya existen en el celular
 * (`Api\CashController`) — misma agregación (`CashReportService`, única fuente de verdad),
 * distinta interfaz: aquí una página HTML de escritorio en vez de JSON para React, y el
 * envío por correo redirige con un flash en vez de devolver JSON.
 */
class CashReportController extends Controller
{
    public function __construct(
        private readonly CashReportServiceInterface $reportService,
    ) {}

    public function resumen(Request $request): View
    {
        return view('finances.cash-reports.resumen', $this->reportService->buildResumenData($request));
    }

    public function resumenPdf(Request $request)
    {
        $data = $this->reportService->buildResumenData($request);
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('cash.resumen-pdf', $data)->setPaper('letter');

        return $pdf->download('resumen-caja-' . $data['dateFrom'] . '-a-' . $data['dateTo'] . '.pdf');
    }

    public function resumenEmail(Request $request): RedirectResponse
    {
        $validated = $request->validate(['email' => ['required', 'email']]);

        $data = $this->reportService->buildResumenData($request);
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('cash.resumen-pdf', $data)->setPaper('letter');
        $filename = 'resumen-caja-' . $data['dateFrom'] . '-a-' . $data['dateTo'] . '.pdf';

        Mail::to($validated['email'])->send(new \App\Mail\CashResumenMail($data, $pdf->output(), $filename));

        return back()->with('success', 'Reporte enviado a ' . $validated['email'] . '.');
    }

    public function metodosPago(Request $request): View
    {
        return view('finances.cash-reports.metodos-pago', $this->reportService->buildMetodosPagoData($request));
    }

    public function metodosPagoPdf(Request $request)
    {
        $data = $this->reportService->buildMetodosPagoData($request);
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('cash.metodos-pago-pdf', $data)->setPaper('letter');

        return $pdf->download('metodos-pago-' . $data['dateFrom'] . '-a-' . $data['dateTo'] . '.pdf');
    }

    public function metodosPagoEmail(Request $request): RedirectResponse
    {
        $validated = $request->validate(['email' => ['required', 'email']]);

        $data = $this->reportService->buildMetodosPagoData($request);
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('cash.metodos-pago-pdf', $data)->setPaper('letter');
        $filename = 'metodos-pago-' . $data['dateFrom'] . '-a-' . $data['dateTo'] . '.pdf';

        Mail::to($validated['email'])->send(new \App\Mail\CashMetodosPagoMail($data, $pdf->output(), $filename));

        return back()->with('success', 'Reporte enviado a ' . $validated['email'] . '.');
    }

    public function porOperador(Request $request): View
    {
        return view('finances.cash-reports.por-operador', $this->reportService->buildPorOperadorData($request));
    }

    public function porOperadorPdf(Request $request)
    {
        $data = $this->reportService->buildPorOperadorData($request);
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('cash.por-operador-pdf', $data)->setPaper('letter');

        return $pdf->download('por-operador-' . $data['dateFrom'] . '-a-' . $data['dateTo'] . '.pdf');
    }

    public function porOperadorEmail(Request $request): RedirectResponse
    {
        $validated = $request->validate(['email' => ['required', 'email']]);

        $data = $this->reportService->buildPorOperadorData($request);
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('cash.por-operador-pdf', $data)->setPaper('letter');
        $filename = 'por-operador-' . $data['dateFrom'] . '-a-' . $data['dateTo'] . '.pdf';

        Mail::to($validated['email'])->send(new \App\Mail\CashPorOperadorMail($data, $pdf->output(), $filename));

        return back()->with('success', 'Reporte enviado a ' . $validated['email'] . '.');
    }

    public function pendientes(): View
    {
        return view('finances.cash-reports.pendientes', $this->reportService->buildPendientesData());
    }

    public function pendientesPdf()
    {
        $data = $this->reportService->buildPendientesData();
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('cash.pendientes-pdf', $data)->setPaper('letter');

        return $pdf->download('pendientes-por-cobrar-' . now()->toDateString() . '.pdf');
    }

    public function pendientesEmail(Request $request): RedirectResponse
    {
        $validated = $request->validate(['email' => ['required', 'email']]);

        $data = $this->reportService->buildPendientesData();
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('cash.pendientes-pdf', $data)->setPaper('letter');
        $filename = 'pendientes-por-cobrar-' . now()->toDateString() . '.pdf';

        Mail::to($validated['email'])->send(new \App\Mail\CashPendientesMail($data, $pdf->output(), $filename));

        return back()->with('success', 'Reporte enviado a ' . $validated['email'] . '.');
    }

    public function cierres(Request $request): View
    {
        return view('finances.cash-reports.cierres', $this->reportService->buildCierresData($request));
    }

    public function cierresPdf(Request $request)
    {
        $data = $this->reportService->buildCierresData($request);
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('cash.cierres-pdf', $data)->setPaper('letter');

        return $pdf->download('cierres-de-turno-' . $data['dateFrom'] . '-a-' . $data['dateTo'] . '.pdf');
    }

    public function cierresEmail(Request $request): RedirectResponse
    {
        $validated = $request->validate(['email' => ['required', 'email']]);

        $data = $this->reportService->buildCierresData($request);
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('cash.cierres-pdf', $data)->setPaper('letter');
        $filename = 'cierres-de-turno-' . $data['dateFrom'] . '-a-' . $data['dateTo'] . '.pdf';

        Mail::to($validated['email'])->send(new \App\Mail\CashCierresMail($data, $pdf->output(), $filename));

        return back()->with('success', 'Reporte enviado a ' . $validated['email'] . '.');
    }
}
