# Especificacion Tecnica: Fotos de Mascota Backoffice

Fecha de actualizacion: 2026-03-21
Alcance: submodulo de fotos generales de mascota y sus derivados visuales.

## Objetivo del submodulo

Este submodulo administra la foto general de la mascota para uso comercial y operativo ligero.

Responsabilidades actuales:

- alta, edicion y eliminacion de fotos generales de mascota
- definicion de foto principal
- almacenamiento de foto principal optimizada
- generacion de miniatura derivada para UI compacta
- lectura opcional de `taken_at` desde EXIF

No es responsabilidad de este submodulo:

- evidencias de servicio o estancia
- fotos clinicas especializadas
- versionado historico de cambios visuales con overlay de negocio

## Fuente de verdad del dominio

Tabla canonica:

- `pet_photos`

Fuente: [apps/backoffice-laravel/database/migrations/2026_03_20_000005_create_pet_photos_table.php](/home/tomas/EstetiCAN_2/apps/backoffice-laravel/database/migrations/2026_03_20_000005_create_pet_photos_table.php)

Campos:

- `id`
- `pet_id`
- `photo_url`
- `photo_type`
- `taken_at`
- `description`
- `is_primary`
- timestamps

## Separacion respecto a otras fotos

Las fotos generales de mascota viven en `pet_photos`.

Las fotos de servicio o estancia viven en `service_photos`.

Fuente de `service_photos`: [apps/backoffice-laravel/database/migrations/2026_03_18_040047_create_service_photos_table.php](/home/tomas/EstetiCAN_2/apps/backoffice-laravel/database/migrations/2026_03_18_040047_create_service_photos_table.php)

Decision de arquitectura:

- `pet_photos` = identidad visual general de la mascota
- `service_photos` = evidencia operativa de servicio o hotel

## Flujo actual de upload

Fuentes:

- [apps/backoffice-laravel/app/Http/Controllers/PetPhotoController.php](/home/tomas/EstetiCAN_2/apps/backoffice-laravel/app/Http/Controllers/PetPhotoController.php)
- [apps/backoffice-laravel/app/Support/PetPhotoImageManager.php](/home/tomas/EstetiCAN_2/apps/backoffice-laravel/app/Support/PetPhotoImageManager.php)

Pasos actuales:

1. usuario selecciona un archivo en `pets.show`
2. si `taken_at` esta vacio, la UI intenta leer EXIF y prellenarlo
3. backend valida archivo y metadatos
4. se genera archivo principal optimizado en `storage/app/public/pet-photos/{Y}/{m}/original/`
5. se genera miniatura derivada en `storage/app/public/pet-photos/{Y}/{m}/thumbs/`
6. se guarda en tabla la ruta del archivo principal

## Tamaños y politica de imagen vigentes

Estado actual documentado en codigo:

- imagen principal: maximo 1600 px
- miniatura derivada: 160 x 120 px exactos en formato 4:3

Justificacion:

- se usa como recurso visual compacto de catalogo y bloques de cliente
- no esta pensada para detalle visual fino sino para identificacion rapida

## EXIF y fecha de toma

Regla actual:

- si el usuario captura `taken_at`, ese valor prevalece
- si lo deja vacio y la imagen trae EXIF, se usa en este orden:
  - `DateTimeOriginal`
  - `DateTimeDigitized`
  - `DateTime`

Implementacion:

- prellenado en navegador desde [apps/backoffice-laravel/resources/views/pets/show.blade.php](/home/tomas/EstetiCAN_2/apps/backoffice-laravel/resources/views/pets/show.blade.php)
- fallback en backend desde [apps/backoffice-laravel/app/Support/PetPhotoImageManager.php](/home/tomas/EstetiCAN_2/apps/backoffice-laravel/app/Support/PetPhotoImageManager.php)

## Resolucion de imagenes en modelo

Fuente: [apps/backoffice-laravel/app/Models/PetPhoto.php](/home/tomas/EstetiCAN_2/apps/backoffice-laravel/app/Models/PetPhoto.php)

Accesores relevantes:

- `photo_file_url`
- `thumbnail_storage_path`
- `photo_thumbnail_url`
- `storesExternalUrl()`

Regla:

- para rutas locales, la miniatura se obtiene por convension sustituyendo `/original/` por `/thumbs/`
- para URLs externas, se devuelve la misma URL y no se genera derivado local

## Reglas de reemplazo y borrado

- si se sube nueva imagen sobre una foto existente, se elimina principal previo y miniatura previa
- si se borra una foto, se eliminan sus archivos fisicos asociados
- si falla la transaccion despues de generar nuevos archivos, se intenta limpieza compensatoria

## Uso actual en UI

Clientes:

- `clients.index`
- `clients.show`
- `clients.edit`

Mascota seleccionada:

- `pets.show`

Regla de presentacion:

- en clientes debe usarse la miniatura
- en la gestion de mascota se puede usar la imagen principal para detalle y edicion

## Riesgos y notas para desarrollo externo

- `APP_URL` incorrecto rompe las URLs publicas aunque los archivos existan
- el valor historico `profile` en `photo_type` convive con la normalizacion actual a `perfil`
- cualquier cambio en naming de rutas debe mantener compatibilidad con la derivacion `original/` -> `thumbs/`
- si se introduce overlay visual sobre imagen, conviene no destruir la imagen base

## Recomendaciones de evolucion

- formalizar miniatura exacta 160x120 para UI compacta
- agregar derivado etiquetado separado si se requiere nombre/fechas sobre imagen
- evaluar uso de WebP o AVIF para reducir espacio adicionalmente
- documentar politicas de retencion y reprocesamiento de imagenes