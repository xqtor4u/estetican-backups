<?php

namespace App\Support\Images;

/**
 * Tamaño máximo de una foto subida — A4 (27/09/2026). Antes estaba fijo y era distinto en cada
 * lugar: 10 MB en el navegador (image-upload.js), 15 MB en el servidor para mascotas/operadores/
 * recursos, 10 MB para artículos y 5 MB para usuarios. Ahora es un solo valor configurable en
 * Configuración → Fotografías (`photo_max_upload_mb` → `backoffice.images.max_upload_mb`).
 */
class UploadLimit
{
    public static function megabytes(): int
    {
        return max(1, (int) config('backoffice.images.max_upload_mb', 15));
    }

    /** Regla de validación de Laravel (`max:` va en KB para archivos). */
    public static function rule(): string
    {
        return 'max:'.(self::megabytes() * 1024);
    }
}
