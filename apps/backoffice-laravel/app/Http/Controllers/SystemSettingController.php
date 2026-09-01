<?php

namespace App\Http\Controllers;

use App\Support\Pages\SystemSettingsPage;
use App\Support\SystemSettings\SystemSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
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
                    // El recorte del navegador ya entrega un PNG chico; esto es la red de
                    // seguridad para el envío sin JS (o pegándole directo al endpoint):
                    // reescala al tope del campo y normaliza a PNG (conserva transparencia,
                    // seguro en dompdf para los PDFs que reusan brand_logo_web).
                    $path = $this->normalizeBrandingImage(
                        $path,
                        (int) ($field['image_max_width'] ?? 640),
                        (int) ($field['image_max_height'] ?? 240),
                    );
                    $validated[$fieldKey] = $path;

                    // Optional: Clean up old file
                    $oldPath = $field['value'];
                    if ($oldPath && $oldPath !== $path && Storage::disk('public')->exists($oldPath)) {
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
            ->to(route('system-settings.index').'#'.$section)
            ->with('success', 'Se actualizó la sección '.mb_strtolower($systemSettings->labelFor($section)).'.');
    }

    /**
     * Reescala una imagen de branding recién subida a los topes del campo y la
     * normaliza a PNG (conserva transparencia). Devuelve la ruta relativa final
     * en el disco `public`. Si algo falla, deja el archivo original intacto y
     * devuelve la ruta recibida — guardar la configuración nunca debe romperse
     * por un problema al procesar la imagen.
     */
    private function normalizeBrandingImage(string $path, int $maxWidth, int $maxHeight): string
    {
        try {
            $disk = Storage::disk('public');
            $absolute = $disk->path($path);

            $info = @getimagesize($absolute);
            if ($info === false) {
                return $path;
            }

            [$width, $height, $type] = $info;

            $source = match ($type) {
                IMAGETYPE_JPEG => imagecreatefromjpeg($absolute),
                IMAGETYPE_PNG => imagecreatefrompng($absolute),
                IMAGETYPE_GIF => imagecreatefromgif($absolute),
                IMAGETYPE_BMP => function_exists('imagecreatefrombmp') ? imagecreatefrombmp($absolute) : false,
                IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? imagecreatefromwebp($absolute) : false,
                default => false,
            };

            if (! $source) {
                return $path;
            }

            $scale = min(1, $maxWidth / $width, $maxHeight / $height);
            $targetWidth = max(1, (int) round($width * $scale));
            $targetHeight = max(1, (int) round($height * $scale));

            $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
            imagefilledrectangle($canvas, 0, 0, $targetWidth, $targetHeight, $transparent);
            imagecopyresampled($canvas, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

            $pngPath = preg_match('/\.[^.\/]+$/', $path)
                ? preg_replace('/\.[^.\/]+$/', '.png', $path)
                : $path.'.png';

            imagepng($canvas, $disk->path($pngPath), 6);

            imagedestroy($source);
            imagedestroy($canvas);

            if ($pngPath !== $path && $disk->exists($path)) {
                $disk->delete($path);
            }

            return $pngPath;
        } catch (\Throwable $e) {
            report($e);

            return $path;
        }
    }

    public function testSmtp(Request $request, SystemSettings $systemSettings): RedirectResponse
    {
        $settings = $systemSettings->all();

        try {
            // Dynamic override
            config([
                'mail.mailers.smtp.host' => $settings['mail_host'] ?? config('mail.mailers.smtp.host'),
                'mail.mailers.smtp.port' => $settings['mail_port'] ?? config('mail.mailers.smtp.port'),
                'mail.mailers.smtp.scheme' => $settings['mail_encryption'] ?? config('mail.mailers.smtp.scheme'),
                'mail.mailers.smtp.username' => $settings['mail_username'] ?? config('mail.mailers.smtp.username'),
                'mail.mailers.smtp.password' => $settings['mail_password'] ?? config('mail.mailers.smtp.password'),
                'mail.from.address' => $settings['mail_from_address'] ?? config('mail.from.address'),
                'mail.from.name' => $settings['mail_from_name'] ?? config('mail.from.name'),
            ]);

            Mail::raw('Esta es una prueba de conexión SMTP de EstetiCAN.', function ($message) use ($settings) {
                $recipient = $settings['mail_from_address'] ?: 'test@example.com';
                $message->to($recipient)
                    ->subject('Prueba de Conexión SMTP - EstetiCAN');
            });

            return redirect()->back()->with('success', '¡Conexión SMTP exitosa! Se envió un correo de prueba a '.($settings['mail_from_address'] ?: 'la dirección configurada').'.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Error de conexión SMTP: '.$e->getMessage());
        }
    }

    /**
     * Guarda un subconjunto de campos de una sección (llamada AJAX).
     * Solo persiste los campos presentes en el request; los demás no se tocan.
     */
    public function patchField(Request $request, string $section, SystemSettings $systemSettings): JsonResponse
    {
        abort_unless($systemSettings->hasSection($section), 404);

        $allRules = $systemSettings->rulesFor($section);
        $presentKeys = array_keys(array_intersect_key($allRules, $request->all()));

        if (empty($presentKeys)) {
            return response()->json(['ok' => false, 'error' => 'No se recibió ningún campo válido.'], 422);
        }

        $rules = array_intersect_key($allRules, array_flip($presentKeys));

        try {
            $validated = $request->validate($rules);
        } catch (ValidationException $e) {
            return response()->json(['ok' => false, 'errors' => $e->errors()], 422);
        }

        $systemSettings->saveFields($section, $validated);

        return response()->json(['ok' => true]);
    }
}
