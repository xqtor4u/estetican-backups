<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Image\Enums\Fit;
use Spatie\Image\Image;

class ClinicalAttachmentManager
{
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
        $disk = Storage::disk('public');
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
        $path = $file->storeAs($directory, $filename, 'public');

        return ['file_path' => $path, 'file_mime_type' => $file->getClientMimeType()];
    }

    public function delete(?string $path): void
    {
        if (! $path) {
            return;
        }

        Storage::disk('public')->delete($path);
    }

    private function isImage(UploadedFile $file): bool
    {
        return str_starts_with((string) $file->getClientMimeType(), 'image/');
    }
}
