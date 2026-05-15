<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Image\Enums\Fit;
use Spatie\Image\Image;

class UserPhotoImageManager
{
    /**
     * Process and store the uploaded profile photo.
     */
    public function store(UploadedFile $file): string
    {
        $directory = 'user-photos/' . now()->format('Y/m');
        $filename = Str::uuid() . '_opt.jpg';
        $mainPath = $directory . '/original/' . $filename;
        $thumbnailPath = $this->thumbnailPathFor($mainPath);
        $disk = Storage::disk('public');
        $config = config('backoffice.images.users');

        // Ensure directories exist
        $disk->makeDirectory(dirname($mainPath));
        $disk->makeDirectory(dirname($thumbnailPath));

        // Process Main Image (Resize & Optimize)
        Image::load($file->getRealPath())
            ->orientation()
            ->fit(Fit::Max, (int) $config['main_max_size'], (int) $config['main_max_size'])
            ->format('jpg')
            ->quality((int) $config['main_quality'])
            ->optimize()
            ->save($disk->path($mainPath));

        // Process Thumbnail (Crop & Optimize)
        Image::load($disk->path($mainPath))
            ->fit(Fit::Crop, (int) $config['thumbnail_size'], (int) $config['thumbnail_size'])
            ->format('jpg')
            ->quality((int) $config['thumbnail_quality'])
            ->optimize()
            ->save($disk->path($thumbnailPath));

        return $mainPath;
    }

    /**
     * Delete existing photo files from storage.
     */
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

    /**
     * Get the relative path for the thumbnail version of a photo.
     */
    public function thumbnailPathFor(?string $mainPath): ?string
    {
        if (!$mainPath || !str_contains($mainPath, '/original/')) {
            return null;
        }

        return str_replace('/original/', '/thumbs/', $mainPath);
    }
}
