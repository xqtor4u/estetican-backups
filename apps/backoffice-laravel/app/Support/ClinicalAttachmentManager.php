<?php

namespace App\Support;

use App\Models\ClinicalAttachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Image\Enums\Fit;
use Spatie\Image\Image;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClinicalAttachmentManager
{
    /**
     * Disco privado (storage/app/private): no se sirve por /storage. Antes del 27/09/2026 los
     * adjuntos clínicos (laboratorio, radiografías, PDF) iban al disco público y cualquiera con la
     * URL podía abrirlos sin sesión. Ahora solo se entregan por
     * ClinicalAttachmentController::show (sesión + permiso `ver clinico` + link firmado que caduca).
     */
    public const DISK = 'local';

    /**
     * A diferencia de los ImageManagers de fotos de identidad/galería, un adjunto
     * clínico puede ser una imagen (radiografía, foto de resultado) o un PDF
     * (informe de laboratorio) — nunca se recorta, solo se optimiza si es imagen.
     *
     * @return array{file_path: string, file_mime_type: string}
     */
    public function store(UploadedFile $file): array
    {
        $directory = 'clinical-attachments/'.now()->format('Y/m');
        $disk = Storage::disk(self::DISK);
        $disk->makeDirectory($directory);

        if ($this->isImage($file)) {
            $config = config('backoffice.images.clinical_attachments');
            $path = $directory.'/'.Str::uuid().'_opt.jpg';

            Image::load($file->getRealPath())
                ->orientation()
                ->fit(Fit::Max, (int) $config['main_max_size'], (int) $config['main_max_size'])
                ->format('jpg')
                ->quality((int) $config['main_quality'])
                ->optimize()
                ->save($disk->path($path));

            return ['file_path' => $path, 'file_mime_type' => 'image/jpeg'];
        }

        $filename = Str::uuid().'.'.$file->getClientOriginalExtension();
        $path = $file->storeAs($directory, $filename, self::DISK);

        return ['file_path' => $path, 'file_mime_type' => $file->getClientMimeType()];
    }

    /** Entrega el archivo en línea (el navegador lo muestra), sin caché compartida. */
    public function response(ClinicalAttachment $attachment): StreamedResponse
    {
        $disk = Storage::disk(self::DISK);
        abort_unless($attachment->file_path && $disk->exists($attachment->file_path), 404);

        return $disk->response($attachment->file_path, basename($attachment->file_path), [
            'Content-Type' => $attachment->file_mime_type ?: $disk->mimeType($attachment->file_path),
            'Cache-Control' => 'private, no-store, max-age=0',
        ], 'inline');
    }

    /** Mueve al disco privado un adjunto que quedó en el público (anterior al 27/09/2026). */
    public function moveToPrivate(ClinicalAttachment $attachment): bool
    {
        $path = $attachment->file_path;

        if (! $path || ! Storage::disk('public')->exists($path)) {
            return false;
        }

        if (! Storage::disk(self::DISK)->exists($path)) {
            Storage::disk(self::DISK)->put($path, Storage::disk('public')->get($path));
        }

        Storage::disk('public')->delete($path);

        return true;
    }

    public function delete(?string $path): void
    {
        if (! $path) {
            return;
        }

        Storage::disk(self::DISK)->delete($path);
        Storage::disk('public')->delete($path); // por si era un adjunto anterior al cambio
    }

    private function isImage(UploadedFile $file): bool
    {
        return str_starts_with((string) $file->getClientMimeType(), 'image/');
    }
}
