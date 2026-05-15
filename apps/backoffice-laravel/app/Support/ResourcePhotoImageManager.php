<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Image\Enums\Fit;
use Spatie\Image\Image;

class ResourcePhotoImageManager
{
    public function store(UploadedFile $file): string
    {
        $directory = 'resource-photos/' . now()->format('Y/m');
        $filename = Str::uuid() . '_opt.jpg';
        $mainPath = $directory . '/original/' . $filename;
        $thumbnailPath = $this->thumbnailPathFor($mainPath);
        $disk = Storage::disk('public');
        $config = config('backoffice.images.resources');

        $disk->makeDirectory(dirname($mainPath));
        $disk->makeDirectory(dirname($thumbnailPath));

        Image::load($file->getRealPath())
            ->orientation()
            ->fit(Fit::Max, (int) $config['main_max_size'], (int) $config['main_max_size'])
            ->format('jpg')
            ->quality((int) $config['main_quality'])
            ->optimize()
            ->save($disk->path($mainPath));

        Image::load($disk->path($mainPath))
            ->fit(Fit::Crop, (int) $config['thumbnail_width'], (int) $config['thumbnail_height'])
            ->format('jpg')
            ->quality((int) $config['thumbnail_quality'])
            ->optimize()
            ->save($disk->path($thumbnailPath));

        return $mainPath;
    }

    public function extractTakenAt(UploadedFile $file): ?CarbonImmutable
    {
        $metadata = @exif_read_data($file->getRealPath());

        if (!is_array($metadata)) {
            return null;
        }

        foreach (['DateTimeOriginal', 'DateTimeDigitized', 'DateTime'] as $key) {
            $value = $metadata[$key] ?? null;

            if (!is_string($value) || trim($value) === '') {
                continue;
            }

            $takenAt = CarbonImmutable::createFromFormat('Y:m:d H:i:s', trim($value), config('app.timezone'));

            if ($takenAt !== false) {
                return $takenAt;
            }
        }

        return null;
    }

    public function deleteFiles(?string $mainPath): void
    {
        if (!$mainPath) {
            return;
        }

        $paths = array_filter([$mainPath, $this->thumbnailPathFor($mainPath)]);

        if ($paths === []) {
            return;
        }

        Storage::disk('public')->delete($paths);
    }

    public function thumbnailPathFor(?string $mainPath): ?string
    {
        if (!$mainPath || !str_contains($mainPath, '/original/')) {
            return null;
        }

        return str_replace('/original/', '/thumbs/', $mainPath);
    }
}