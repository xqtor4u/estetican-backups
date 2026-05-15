# Especificacion Tecnica: Mascotas Backoffice

Fecha de actualizacion: 2026-03-25
Alcance: modulo de mascotas y sus dependencias directas, pensado para desarrollo externo y continuidad funcional.

## Objetivo del modulo

Mascota es la entidad operativa principal dentro del flujo clientes/pets. Su alcance vigente incluye:

- datos base estables de la mascota
- acceso especializado por mascota seleccionada
- alertas medicas normalizadas en tabla propia
- fotos generales de mascota en tabla propia
- miniatura de catalogo para bloques de clientes

## Estado funcional actual

Implementado:

- alta y edicion embebida desde cliente
- modulo independiente `/pets` como recurso raiz
- listado raiz con cambio entre vistas `Bloques` y `Tabla`
- vista especializada de mascota seleccionada
- CRUD de `pet_medical_alerts`
- CRUD de `pet_photos`
- generacion de imagen principal optimizada y miniatura derivada
- prellenado de `taken_at` via EXIF en navegador y fallback en backend

No implementado aun:

- UI dedicada para vacunacion, estancias o servicios ejecutados desde mascota
- overlay definitivo de nombre/fechas sobre imagen derivada

## Rutas web actuales

Fuente: [apps/backoffice-laravel/routes/web.php](/home/tomas/EstetiCAN_2/apps/backoffice-laravel/routes/web.php)

- `GET /clients/{client}/pets/{pet}`
- `GET /pets`
- `GET /pets/{pet}`
- `POST /clients/{client}/pets/{pet}/medical-alerts`
- `PUT /clients/{client}/pets/{pet}/medical-alerts/{medicalAlert}`
- `DELETE /clients/{client}/pets/{pet}/medical-alerts/{medicalAlert}`
- `POST /clients/{client}/pets/{pet}/photos`
- `PUT /clients/{client}/pets/{pet}/photos/{photo}`
- `DELETE /clients/{client}/pets/{pet}/photos/{photo}`

## Modelo de datos vigente

### Tabla `pets`

Fuentes:

- [apps/backoffice-laravel/database/migrations/2026_03_18_040008_create_pets_table.php](/home/tomas/EstetiCAN_2/apps/backoffice-laravel/database/migrations/2026_03_18_040008_create_pets_table.php)
- [apps/backoffice-laravel/database/migrations/2026_03_20_000006_refactor_pets_core_table.php](/home/tomas/EstetiCAN_2/apps/backoffice-laravel/database/migrations/2026_03_20_000006_refactor_pets_core_table.php)
- [apps/backoffice-laravel/database/migrations/2026_03_20_000007_add_death_date_to_pets_table.php](/home/tomas/EstetiCAN_2/apps/backoffice-laravel/database/migrations/2026_03_20_000007_add_death_date_to_pets_table.php)
- [apps/backoffice-laravel/database/migrations/2026_03_20_000008_add_species_to_pets_table.php](/home/tomas/EstetiCAN_2/apps/backoffice-laravel/database/migrations/2026_03_20_000008_add_species_to_pets_table.php)

Campos canonicos actuales:

- `id`
- `client_id`
- `name`
- `species` nullable
- `breed` nullable
- `birth_date` nullable
- `death_date` nullable
- `microchip_code` nullable
- `tattoo_code` nullable
- `sex` nullable
- `coat_color` nullable
- `size` nullable
- `is_sterilized` boolean
- `notes` nullable
- timestamps

Campos retirados del core:

- `weight`
- `temperament`
- `medical_alerts` JSON

Nota funcional:

- `death_date` marca mascota fallecida y saca a la mascota de bloques vivos en cliente
- `species` soporta perro, gato, pajaro u otra especie libre

### Tabla `pet_medical_alerts`

Fuente: [apps/backoffice-laravel/database/migrations/2026_03_20_000004_create_pet_medical_alerts_table.php](/home/tomas/EstetiCAN_2/apps/backoffice-laravel/database/migrations/2026_03_20_000004_create_pet_medical_alerts_table.php)

Campos:

- `id`
- `pet_id`
- `category`
- `description`
- `severity` nullable
- `notes` nullable
- `is_active` boolean default true
- timestamps

Regla de negocio:

- sustituye el antiguo JSON `pets.medical_alerts`
- es la fuente canonica para alertas medicas de mascota

### Tabla `pet_photos`

Fuente: [apps/backoffice-laravel/database/migrations/2026_03_20_000005_create_pet_photos_table.php](/home/tomas/EstetiCAN_2/apps/backoffice-laravel/database/migrations/2026_03_20_000005_create_pet_photos_table.php)

Campos:

- `id`
- `pet_id`
- `photo_url`
- `photo_type`
- `taken_at` nullable
- `description` nullable
- `is_primary` boolean
- timestamps

Notas importantes:

- la migracion original define `photo_type` default `profile`, pero la UI y el backend actuales normalizan a `perfil`
- `photo_url` hoy guarda una ruta local optimizada; no la imagen binaria en base

## Modelo Laravel actual

Fuentes:

- [apps/backoffice-laravel/app/Models/Pet.php](/home/tomas/EstetiCAN_2/apps/backoffice-laravel/app/Models/Pet.php)
- [apps/backoffice-laravel/app/Models/PetMedicalAlert.php](/home/tomas/EstetiCAN_2/apps/backoffice-laravel/app/Models/PetMedicalAlert.php)
- [apps/backoffice-laravel/app/Models/PetPhoto.php](/home/tomas/EstetiCAN_2/apps/backoffice-laravel/app/Models/PetPhoto.php)

Relaciones activas:

- `client(): belongsTo(Client::class)`
- `medicalAlerts(): hasMany(PetMedicalAlert::class)`
- `photos(): hasMany(PetPhoto::class)`
- `primaryPhoto(): hasOne(PetPhoto::class)->where(is_primary = true)`
- `latestPhoto(): hasOne(PetPhoto::class)->latestOfMany()`

Accesores utiles:

- `age_description`
- `species_label`
- `catalog_photo`
- `catalog_thumbnail_url`
- `catalog_photo_url`

## Pipeline actual de fotos

Fuentes:

- [apps/backoffice-laravel/app/Support/PetPhotoImageManager.php](/home/tomas/EstetiCAN_2/apps/backoffice-laravel/app/Support/PetPhotoImageManager.php)
- [apps/backoffice-laravel/app/Http/Controllers/PetPhotoController.php](/home/tomas/EstetiCAN_2/apps/backoffice-laravel/app/Http/Controllers/PetPhotoController.php)

Flujo vigente:

1. el usuario sube una imagen desde `pets.show`
2. se genera una imagen principal optimizada en `storage/app/public/pet-photos/.../original/`
3. se genera una miniatura derivada en `storage/app/public/pet-photos/.../thumbs/`
4. se guarda la ruta principal en `pet_photos.photo_url`
5. la miniatura se resuelve por convension de ruta desde el modelo

Detalles actuales:

- imagen principal con maximo de 1600 px
- miniatura derivada actual de 160 x 120 px exactos en formato 4:3
- si se reemplaza la foto, se eliminan archivo principal y miniatura previos
- si se elimina la foto, tambien se eliminan sus archivos fisicos
- `taken_at` puede completarse manualmente o leerse desde EXIF

## Pantalla especializada: Mascota seleccionada

Fuente: [apps/backoffice-laravel/resources/views/pets/show.blade.php](/home/tomas/EstetiCAN_2/apps/backoffice-laravel/resources/views/pets/show.blade.php)

Responsabilidades:

- mostrar un listado raiz de mascotas en bloques o tabla
- permitir abrir detalle de mascota desde navegacion raiz sin perder acceso al cliente
- mostrar resumen de la mascota seleccionada
- cambiar entre mascotas del mismo cliente
- CRUD de alertas medicas
- CRUD de fotos
- prellenar `taken_at` desde EXIF en navegador cuando sea posible

## Reglas funcionales vigentes

- las mascotas fallecidas se muestran en gris en varias vistas
- en bloques de cliente solo deben aparecer mascotas vivas
- `pet_photos` se usa para fotos generales de mascota, no para evidencias de servicio
- `service_photos` debe seguir separada y ligada a ejecucion o estancia

## Dependencias detectadas alrededor de mascota

Detectadas en analisis funcional y notas del proyecto:

- `pet_vaccinations`
- `spa_bookings`
- `hotel_reservations`
- `stays`
- `executed_services`

Estas tablas no forman parte del CRUD especializado implementado hoy en `pets.show`, pero deben tratarse como dependencias naturales del dominio de mascota.

## Riesgos y observaciones para desarrollo externo

- la migracion base de `pet_photos` aun refleja `profile`; el flujo actual usa `perfil`
- parte del conocimiento funcional de mascota vive en la bitacora y no solo en migraciones
- cualquier cambio en fotos debe considerar original optimizado, miniatura derivada y URLs publicas dependientes de `APP_URL`
- para localhost con Sail, `APP_URL` debe incluir `:8000`

## Recomendaciones para siguiente iteracion

- formalizar tamaño final de miniatura de catalogo
- decidir si se agregara overlay derivado con nombre y fechas
- separar procesamiento de fotos y persistencia a una capa de servicios mas estable
- ampliar la documentacion de dependencias futuras: vacunas, reservas, estancias y servicios ejecutados