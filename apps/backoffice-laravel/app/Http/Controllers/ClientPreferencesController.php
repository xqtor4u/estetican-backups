<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Support\SystemSettings\SystemSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

/**
 * Autogestión pública de preferencias de comunicación — sin login, accesible
 * solo vía enlace firmado (ver `preferencias/{client}` en routes/web.php,
 * protegida con el middleware `signed`).
 */
class ClientPreferencesController extends Controller
{
    public function show(Client $client): View
    {
        return view('client-preferences.show', [
            'client' => $client,
            'branding' => app(SystemSettings::class)->all(),
            'updateUrl' => URL::temporarySignedRoute('client-preferences.update', now()->addYear(), ['client' => $client->id]),
        ]);
    }

    public function update(Request $request, Client $client): RedirectResponse
    {
        $validated = $request->validate([
            'receives_offers' => ['nullable', 'boolean'],
            'receives_service_reminders' => ['nullable', 'boolean'],
            'receives_job_updates' => ['nullable', 'boolean'],
            'receives_account_statements' => ['nullable', 'boolean'],
            'receives_other_notifications' => ['nullable', 'boolean'],
        ]);

        $client->update([
            'receives_offers' => (bool) ($validated['receives_offers'] ?? false),
            'receives_service_reminders' => (bool) ($validated['receives_service_reminders'] ?? false),
            'receives_job_updates' => (bool) ($validated['receives_job_updates'] ?? false),
            'receives_account_statements' => (bool) ($validated['receives_account_statements'] ?? false),
            'receives_other_notifications' => (bool) ($validated['receives_other_notifications'] ?? false),
        ]);

        return redirect(URL::temporarySignedRoute('client-preferences.show', now()->addYear(), ['client' => $client->id]))
            ->with('success', 'Tus preferencias se guardaron correctamente.');
    }
}
