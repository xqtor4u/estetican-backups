<?php

namespace App\Http\Controllers;

use App\Models\Operator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OperatorGoogleCalendarController extends Controller
{
    public function update(Request $request, Operator $operator): RedirectResponse
    {
        $validated = $request->validate([
            'google_personal_email' => 'nullable|email|max:255',
            'google_calendar_share_enabled' => 'nullable|boolean',
        ]);

        $validated['google_calendar_share_enabled'] = (bool) ($validated['google_calendar_share_enabled'] ?? false);
        $emailChanged = $validated['google_personal_email'] !== $operator->google_personal_email;

        $operator->update($validated);

        // Si el email cambió, hay que volver a compartir el calendario con el nuevo
        // destinatario en la siguiente corrida — no es un campo del formulario (Fillable
        // a propósito no lo incluye), el comando lo vuelve a setear cuando el ACL insert
        // real tenga éxito.
        if ($emailChanged) {
            $operator->forceFill(['google_calendar_shared_at' => null])->save();
        }

        return redirect()->route('operators.edit', $operator)->with('success', 'Configuración de Google Calendar actualizada.');
    }
}
