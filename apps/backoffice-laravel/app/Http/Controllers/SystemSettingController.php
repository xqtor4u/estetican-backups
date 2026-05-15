<?php

namespace App\Http\Controllers;

use App\Support\Pages\SystemSettingsPage;
use App\Support\SystemSettings\SystemSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class SystemSettingController extends Controller
{
    public function index(SystemSettings $systemSettings): View
    {
        $page = SystemSettingsPage::index();
        $sections = $systemSettings->sections();

        return view('system-settings.index', compact('page', 'sections'));
    }

    public function update(Request $request, string $section, SystemSettings $systemSettings): RedirectResponse
    {
        abort_unless($systemSettings->hasSection($section), 404);

        $rules = $systemSettings->rulesFor($section);
        
        // For image fields, we use specific rules
        foreach ($systemSettings->sections()[$section]['fields'] as $fieldKey => $field) {
            if ($field['type'] === 'image') {
                $rules[$fieldKey] = ['nullable', 'image', 'max:2048'];
            }
        }

        $validated = $request->validate($rules);

        // Process file uploads
        foreach ($systemSettings->sections()[$section]['fields'] as $fieldKey => $field) {
            if ($field['type'] === 'image') {
                if ($request->hasFile($fieldKey)) {
                    $path = $request->file($fieldKey)->store('branding', 'public');
                    $validated[$fieldKey] = $path;
                    
                    // Optional: Clean up old file
                    $oldPath = $field['value'];
                    if ($oldPath && Storage::disk('public')->exists($oldPath)) {
                        Storage::disk('public')->delete($oldPath);
                    }
                } else {
                    // Keep the old value if no new file was uploaded
                    $validated[$fieldKey] = $field['value'];
                }
            }
        }

        $systemSettings->save($section, $validated);

        return redirect()
            ->to(route('system-settings.index') . '#' . $section)
            ->with('success', 'Se actualizó la sección ' . mb_strtolower($systemSettings->labelFor($section)) . '.');
    }

    public function testSmtp(Request $request, SystemSettings $systemSettings): RedirectResponse
    {
        $settings = $systemSettings->all();

        try {
            // Dynamic override
            config([
                'mail.mailers.smtp.host' => $settings['mail_host'] ?? config('mail.mailers.smtp.host'),
                'mail.mailers.smtp.port' => $settings['mail_port'] ?? config('mail.mailers.smtp.port'),
                'mail.mailers.smtp.encryption' => $settings['mail_encryption'] ?? config('mail.mailers.smtp.encryption'),
                'mail.mailers.smtp.username' => $settings['mail_username'] ?? config('mail.mailers.smtp.username'),
                'mail.mailers.smtp.password' => $settings['mail_password'] ?? config('mail.mailers.smtp.password'),
                'mail.from.address' => $settings['mail_from_address'] ?? config('mail.from.address'),
                'mail.from.name' => $settings['mail_from_name'] ?? config('mail.from.name'),
            ]);

            \Illuminate\Support\Facades\Mail::raw('Esta es una prueba de conexión SMTP de EstetiCAN.', function ($message) use ($settings) {
                $recipient = $settings['mail_from_address'] ?: 'test@example.com';
                $message->to($recipient)
                    ->subject('Prueba de Conexión SMTP - EstetiCAN');
            });

            return redirect()->back()->with('success', '¡Conexión SMTP exitosa! Se envió un correo de prueba a ' . ($settings['mail_from_address'] ?: 'la dirección configurada') . '.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Error de conexión SMTP: ' . $e->getMessage());
        }
    }
}