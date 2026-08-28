<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BookingProcessNote;
use App\Models\SpaBooking;
use Illuminate\Http\Request;

class BookingProcessNoteController extends Controller
{
    /** Mismo criterio que `BookingController::ensureVisible()` — 404, no confirmar existencia. */
    private function ensureVisible(SpaBooking $booking): void
    {
        abort_unless(SpaBooking::visibleTo(auth()->user())->whereKey($booking->id)->exists(), 404);
    }

    public function index(SpaBooking $booking)
    {
        $this->ensureVisible($booking);

        return response()->json(
            $booking->processNotes()->with('user:id,name')->get()->map(fn ($n) => $this->serialize($n))
        );
    }

    public function store(Request $request, SpaBooking $booking)
    {
        $this->ensureVisible($booking);

        if (in_array($booking->status, ['completed', 'cancelled'])) {
            return response()->json(['message' => 'No se pueden agregar notas a una cita '.$booking->status.'.'], 422);
        }

        $data = $request->validate([
            'note' => 'required|string|max:2000',
        ]);

        $note = $booking->processNotes()->create([
            'user_id' => auth()->id(),
            'note' => $data['note'],
        ]);

        return response()->json($this->serialize($note->fresh('user')), 201);
    }

    public function update(Request $request, SpaBooking $booking, BookingProcessNote $note)
    {
        $this->ensureVisible($booking);

        // A diferencia de store(), editar una nota ya existente no reabre nada
        // operativo de la cita — se permite aunque ya esté completed/cancelled,
        // para poder corregir/completar una nota sobre un servicio ya cerrado.
        abort_unless($note->spa_booking_id === $booking->id, 404);

        $data = $request->validate([
            'note' => 'required|string|max:2000',
        ]);

        $note->update(['note' => $data['note']]);

        return response()->json($this->serialize($note->fresh('user')));
    }

    private function serialize(BookingProcessNote $note): array
    {
        return [
            'id' => $note->id,
            'note' => $note->note,
            'author' => $note->user?->name,
            'created_at' => $note->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $note->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
