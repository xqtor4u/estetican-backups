<?php

namespace App\Http\Controllers\Finances;

use App\Domain\Accounting\Contracts\AccountingServiceInterface;
use App\Http\Controllers\Controller;
use App\Models\Document;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use RuntimeException;

class DocumentController extends Controller
{
    public function cancel(Request $request, Document $document): RedirectResponse
    {
        $validated = $request->validate([
            'cancellation_type' => ['required', Rule::in([
                Document::CANCELLATION_TYPE_CORRECTION,
                Document::CANCELLATION_TYPE_REFUND,
            ])],
            'cancellation_reason' => ['required', 'string', 'max:500'],
        ]);

        try {
            app(AccountingServiceInterface::class)->cancelDocument(
                $document,
                auth()->user(),
                $validated['cancellation_type'],
                $validated['cancellation_reason']
            );
        } catch (RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', "Recibo {$document->folio_display} cancelado.");
    }

    /**
     * Reemite un documento cancelado por corrección de datos — genera un Document nuevo
     * (folio nuevo, snapshot de línea fresco desde el estado actual de la OT) y re-apunta
     * el asiento contable y el pago originales (que nunca se tocaron, siguen siendo válidos)
     * al documento nuevo. No aplica a cancelaciones por reembolso: ahí el dinero ya salió,
     * cualquier cobro nuevo es un pago nuevo (se registra como tal, no como "reemisión").
     */
    public function reissue(Document $document): RedirectResponse
    {
        if ($document->status !== 'cancelado') {
            return redirect()->back()->with('error', 'Solo se puede reemitir un documento cancelado.');
        }

        if ($document->cancellation_type !== Document::CANCELLATION_TYPE_CORRECTION) {
            return redirect()->back()->with('error', 'Solo se puede reemitir automáticamente una cancelación por corrección de datos. Para un reembolso, registra un cobro nuevo.');
        }

        if ($document->replacement()->exists()) {
            return redirect()->back()->with('error', 'Este documento ya fue reemitido.');
        }

        $booking = $document->documentable;

        if (! $booking) {
            return redirect()->back()->with('error', 'No se encontró la cita de origen de este documento.');
        }

        $accounting = app(AccountingServiceInterface::class);

        $newDocument = DB::transaction(function () use ($document, $booking, $accounting) {
            $folio = $accounting->getNextFolio($document->document_series_id);

            $new = Document::create([
                'document_series_id' => $document->document_series_id,
                'document_type' => $document->document_type,
                'folio_number' => $folio['number'],
                'folio_display' => $folio['display'],
                'status' => 'emitido',
                'client_id' => $document->client_id,
                'branch_id' => $document->branch_id,
                'issued_by_user_id' => auth()->id(),
                'subtotal' => $document->subtotal,
                'tax_amount' => $document->tax_amount,
                'total' => $document->total,
                'documentable_id' => $document->documentable_id,
                'documentable_type' => $document->documentable_type,
                'line_items_snapshot' => $accounting->snapshotBookingLineItems($booking),
                'supersedes_document_id' => $document->id,
            ]);

            // El asiento y el pago originales nunca se tocaron en una cancelación por
            // corrección (el dinero sigue siendo correcto) — se re-apuntan al documento nuevo.
            $document->journalEntry?->update(['document_id' => $new->id]);
            $document->payment?->update(['document_id' => $new->id]);

            return $new;
        });

        return redirect()->back()->with('success', "Nuevo recibo emitido: {$newDocument->folio_display}.");
    }
}
