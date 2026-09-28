<?php

namespace App\Http\Controllers;

use App\Domain\Accounting\Contracts\AccountingServiceInterface;
use App\Mail\ReportDocumentMail;
use App\Models\Quote;
use App\Models\SpaBooking;
use App\Support\SystemSettings\SystemSettings;
use App\Support\WhatsApp\PhoneNormalizer;
use App\Support\WhatsApp\WhatsAppLink;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

/**
 * Documentos de una cita: presupuesto, orden de trabajo y recibo — para imprimir (HTML) y, desde
 * A1 (27/09/2026), en PDF y enviados al cliente por correo (adjunto) o WhatsApp (link firmado
 * que caduca: un link de WhatsApp solo lleva texto, no archivos).
 *
 * La orden de trabajo es interna (lleva notas del proceso): se imprime y se descarga en PDF,
 * pero nunca se envía al cliente — ver SHAREABLE.
 */
class ReportController extends Controller
{
    /** Documentos que se pueden enviar al cliente. */
    public const SHAREABLE = ['quote', 'invoice'];

    /** Días que dura el link del PDF enviado por WhatsApp. */
    public const PUBLIC_LINK_DAYS = 7;

    private const TITLES = [
        'quote' => 'Presupuesto',
        'work-order' => 'Orden de trabajo',
        'invoice' => 'Recibo de pago',
    ];

    protected SystemSettings $settings;

    public function __construct(
        SystemSettings $settings,
        private readonly AccountingServiceInterface $accountingService,
    ) {
        $this->settings = $settings;
    }

    /** Ver presupuesto (Cotización) */
    public function quote(Quote $quote): View
    {
        return $this->renderHtml('quote', $quote->id);
    }

    /** Ver orden de trabajo (Interna) */
    public function workOrder(SpaBooking $booking): View
    {
        return $this->renderHtml('work-order', $booking->id);
    }

    /** Ver recibo / factura (Liquidación) */
    public function invoice(SpaBooking $booking): View
    {
        return $this->renderHtml('invoice', $booking->id);
    }

    /** Descargar el documento en PDF. */
    public function pdf(string $document, int $id): Response
    {
        $doc = $this->document($document, $id, asPdf: true);
        $this->ensureVisible($doc['booking']);

        return $this->pdfFor($doc)->download($doc['filename']);
    }

    /** Enviar el PDF adjunto por correo (por defecto al correo del cliente). */
    public function email(Request $request, string $document, int $id): RedirectResponse
    {
        abort_unless(in_array($document, self::SHAREABLE, true), 404);
        $validated = $request->validate(['email' => ['required', 'email']]);

        $doc = $this->document($document, $id, asPdf: true);
        $this->ensureVisible($doc['booking']);

        Mail::to($validated['email'])->send(new ReportDocumentMail(
            title: $doc['title'],
            businessName: $doc['data']['settings']['branding']['brand_business_name'],
            clientName: (string) ($doc['booking']->pet?->client?->full_name ?? ''),
            petName: (string) ($doc['booking']->pet?->name ?? ''),
            pdfContent: $this->pdfFor($doc)->output(),
            filename: $doc['filename'],
        ));

        return back()->with('success', $doc['title'].' enviado a '.$validated['email'].'.');
    }

    /** Abrir WhatsApp con el mensaje y el link firmado al PDF, al teléfono del cliente. */
    public function whatsapp(string $document, int $id): RedirectResponse
    {
        abort_unless(in_array($document, self::SHAREABLE, true), 404);

        $doc = $this->document($document, $id, asPdf: false);
        $booking = $doc['booking'];
        $this->ensureVisible($booking);

        $client = $booking->pet?->client;
        $waNumber = $client ? PhoneNormalizer::toWhatsAppNumber((string) PhoneNormalizer::bestPhoneFor($client)) : null;

        if (! $waNumber) {
            return back()->with('error', 'El cliente no tiene un celular válido para WhatsApp.');
        }

        $url = URL::temporarySignedRoute('documents.public', now()->addDays(self::PUBLIC_LINK_DAYS), [
            'document' => $document,
            'id' => $id,
        ]);

        $message = 'Hola '.($client->full_name ?: '').', te compartimos '
            .($document === 'quote' ? 'el presupuesto' : 'el recibo de pago')
            .' de '.($booking->pet?->name ?: 'tu mascota').': '.$url
            ."\n(El enlace es válido por ".self::PUBLIC_LINK_DAYS.' días.)';

        return redirect()->away(WhatsAppLink::to($waNumber, $message));
    }

    /**
     * PDF para el cliente, sin sesión — solo con el link firmado que caduca (middleware
     * `signed`) y solo de documentos compartibles. Ruta pública a propósito.
     */
    public function publicPdf(string $document, int $id): Response
    {
        abort_unless(in_array($document, self::SHAREABLE, true), 404);

        $doc = $this->document($document, $id, asPdf: true);

        return $this->pdfFor($doc)->stream($doc['filename']);
    }

    private function renderHtml(string $document, int $id): View
    {
        $doc = $this->document($document, $id, asPdf: false);
        $this->ensureVisible($doc['booking']);

        return view($doc['view'], $doc['data']);
    }

    /**
     * Datos de un documento — un solo lugar para imprimir, PDF y envíos.
     *
     * @return array{view: string, data: array, booking: SpaBooking, title: string, filename: string}
     */
    private function document(string $document, int $id, bool $asPdf): array
    {
        abort_unless(array_key_exists($document, self::TITLES), 404);

        $settings = $this->getReportSettings($asPdf);

        if ($document === 'quote') {
            $quote = Quote::with(['spaBooking.pet.client.phones', 'items.service', 'items.item'])->findOrFail($id);
            $booking = $quote->spaBooking;
            $this->ensureOrderFolio($booking);
            $data = compact('quote', 'booking', 'settings');
        } else {
            $booking = SpaBooking::with($document === 'work-order' ? [
                'pet.client',
                'pet.medicalAlerts',
                'quotes.items.service',
                'quotes.items.item',
                'quotes.items.operator',
                'items.item',
                'resourceAllocations.resource',
                'services.service',
                'operator',
                'processNotes.user:id,name',
            ] : [
                'pet.client.phones',
                'quotes.items.service',
                'quotes.items.item',
                'services.service',
                'items',
                'payments',
                'processNotes.user:id,name',
            ])->findOrFail($id);
            $this->ensureOrderFolio($booking);
            $acceptedQuote = $booking->quotes->firstWhere('status', 'accepted');
            // SYNC-098: todo cobro (móvil y web) vive en `payments`, ligado al SpaBooking.
            $directPayments = $booking->payments;
            $data = compact('booking', 'acceptedQuote', 'settings', 'directPayments');
        }

        $folio = $booking->order_folio ?: (string) $booking->id;

        return [
            'view' => 'reports.'.$document,
            'data' => $data + ['asPdf' => $asPdf],
            'booking' => $booking,
            'title' => self::TITLES[$document],
            'filename' => str($document === 'quote' ? 'presupuesto' : ($document === 'invoice' ? 'recibo' : 'orden-de-trabajo'))
                ->append('-', $folio)->slug()->append('.pdf')->toString(),
        ];
    }

    private function pdfFor(array $doc): \Barryvdh\DomPDF\PDF
    {
        // Subconjunto de fuente: solo los caracteres usados. Sin esto cada PDF pesaba ~880 KB
        // (fuente completa incrustada) — mucho para mandarlo por correo o abrirlo en el celular.
        return Pdf::loadView($doc['view'], $doc['data'])
            ->setPaper('letter')
            ->setOption('isFontSubsettingEnabled', true);
    }

    /** Operador restringido: solo documentos de citas que puede ver (mismo criterio que la agenda). */
    private function ensureVisible(SpaBooking $booking): void
    {
        abort_unless(SpaBooking::visibleTo(auth()->user())->whereKey($booking->id)->exists(), 404);
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
     * Obtener configuración agrupada para reportes. `logo_src`: URL pública para imprimir en
     * pantalla; en PDF el logo va embebido (data URI), porque dompdf no descarga URLs remotas.
     */
    private function getReportSettings(bool $asPdf = false): array
    {
        $all = $this->settings->all();
        $logo = $all['brand_logo_print'] ?? $all['brand_logo_web'] ?? null;

        return [
            'branding' => [
                'brand_business_name' => $all['brand_business_name'] ?? 'EstetiCAN',
                'brand_logo_print' => $logo,
                'logo_src' => $this->logoSrc($logo, $asPdf),
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
            ],
        ];
    }

    private function logoSrc(?string $logo, bool $asPdf): ?string
    {
        if (! $logo || ! Storage::disk('public')->exists($logo)) {
            return null;
        }

        if (! $asPdf) {
            return Storage::disk('public')->url($logo);
        }

        $mime = Storage::disk('public')->mimeType($logo);

        if (! in_array($mime, ['image/png', 'image/jpeg', 'image/gif'], true)) {
            return null; // p. ej. webp: dompdf no lo garantiza; mejor sin logo que un PDF roto
        }

        return 'data:'.$mime.';base64,'.base64_encode($this->shrinkForPdf(Storage::disk('public')->get($logo), $mime));
    }

    /**
     * El logo de impresión puede ser un PNG de cientos de KB; en el PDF se ve a ~60 px de alto.
     * Se reescala a 400 px de ancho (conservando transparencia) para que el PDF que se manda al
     * cliente no pese 300 KB solo por el logo. Si GD no puede, se usa el original.
     */
    private function shrinkForPdf(string $bytes, string $mime): string
    {
        $image = @imagecreatefromstring($bytes);

        if (! $image || imagesx($image) <= 400) {
            return $bytes;
        }

        $scaled = imagescale($image, 400);
        imagealphablending($scaled, false);
        imagesavealpha($scaled, true);

        ob_start();
        $mime === 'image/jpeg' ? imagejpeg($scaled, null, 85) : imagepng($scaled, null, 9);
        $out = (string) ob_get_clean();

        return $out !== '' && strlen($out) < strlen($bytes) ? $out : $bytes;
    }
}
