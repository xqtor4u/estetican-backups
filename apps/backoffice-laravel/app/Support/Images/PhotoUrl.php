<?php

namespace App\Support\Images;

use Illuminate\Support\Facades\Storage;

/**
 * URLs de fotos de perfil (mascota, operador, usuario, recurso). Los *PhotoImageManager guardan
 * cada foto como `…/original/x.jpg` (hasta 1200 px, ~240 KB) más una miniatura `…/thumbs/x.jpg`
 * (160 px, ~4 KB). A4 (27/09/2026): en listas y avatares se sirve la miniatura — antes la API
 * móvil mandaba la foto completa aunque se mostrara a 48 px.
 */
class PhotoUrl
{
    public static function main(?string $path): ?string
    {
        return $path ? Storage::disk('public')->url($path) : null;
    }

    /** Miniatura si existe; si no (foto anterior al formato con miniatura), la principal. */
    public static function thumb(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (str_contains($path, '/original/')) {
            $thumb = str_replace('/original/', '/thumbs/', $path);

            if (Storage::disk('public')->exists($thumb)) {
                return Storage::disk('public')->url($thumb);
            }
        }

        return Storage::disk('public')->url($path);
    }
}
