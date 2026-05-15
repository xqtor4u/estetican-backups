# Especificacion Tecnica: Clientes Backoffice

Fecha de actualizacion: 2026-03-21
Alcance: modulo de clientes del backoffice Laravel, pensado para onboarding rapido de desarrollo externo.

## Objetivo del modulo

El modulo de clientes administra al dueño o responsable de una o varias mascotas. Su alcance actual incluye:

- alta, listado, detalle, edicion y eliminacion de clientes
- manejo de direcciones asociadas
- manejo de telefonos asociados
- alta y edicion embebida de mascotas desde la pantalla de cliente
- acceso a la gestion especializada de dependencias de mascota

## Estado funcional actual

Implementado:

- CRUD de clientes con vistas `index`, `create`, `show`, `edit`
- relaciones activas con `addresses`, `phones` y `pets`
- tarjetas de mascotas vivas en listado y detalle de cliente
- seleccion visual de mascota en edicion para entrar a dependencias directas

Restricciones actuales:

- en `clients.index` y `clients.show` solo se muestran mascotas vivas
- en `clients.edit` se muestran mascotas vivas y fallecidas para administracion
- el modulo no expone aun API REST separada; opera por rutas web Blade

## Rutas web actuales

Fuente: [apps/backoffice-laravel/routes/web.php](/home/tomas/EstetiCAN_2/apps/backoffice-laravel/routes/web.php)

- `GET /clients`
- `GET /clients/create`
- `POST /clients`
- `GET /clients/{client}`
- `GET /clients/{client}/edit`
- `PUT/PATCH /clients/{client}`
- `DELETE /clients/{client}`
- `GET /clients/{client}/pets/{pet}` como entrada a gestion especializada de mascota

## Modelo de datos relevante

### Tabla `clients`

Fuente base: [apps/backoffice-laravel/database/migrations/2026_03_18_040003_create_clients_table.php](/home/tomas/EstetiCAN_2/apps/backoffice-laravel/database/migrations/2026_03_18_040003_create_clients_table.php)

Campos de negocio vigentes:

- `id`
- `lead_id` nullable
- `first_name`
- `last_name`
- `email` nullable en base, pero requerido en formularios actuales
- `address` nullable
- `city` nullable
- `state` nullable
- `zip_code` nullable
- `notes` nullable
- timestamps

Nota importante:

- la migracion original creo `phone` en `clients`, pero luego fue eliminado por [apps/backoffice-laravel/database/migrations/2026_03_19_211000_drop_phone_from_clients_table.php](/home/tomas/EstetiCAN_2/apps/backoffice-laravel/database/migrations/2026_03_19_211000_drop_phone_from_clients_table.php)
- el modelo actual ya no considera `phone` como atributo propio del cliente

### Tabla `addresses`

Fuente: [apps/backoffice-laravel/database/migrations/2026_03_19_200742_create_addresses_table.php](/home/tomas/EstetiCAN_2/apps/backoffice-laravel/database/migrations/2026_03_19_200742_create_addresses_table.php)

Campos:

- `id`
- `client_id`
- `type` default `home`
- `street`
- `city`
- `state` nullable
- `zip` nullable
- `country` default `México`
- `lat` nullable
- `lng` nullable
- timestamps

Observacion:

- `colonia` si existe en la evolucion del esquema por [apps/backoffice-laravel/database/migrations/2026_03_19_210000_add_colonia_to_addresses_table.php](/home/tomas/EstetiCAN_2/apps/backoffice-laravel/database/migrations/2026_03_19_210000_add_colonia_to_addresses_table.php)
- los formularios y el modelo funcional ya son consistentes con ese agregado

### Tabla `phones`

Fuentes:

- [apps/backoffice-laravel/database/migrations/2026_03_19_201000_create_phones_table.php](/home/tomas/EstetiCAN_2/apps/backoffice-laravel/database/migrations/2026_03_19_201000_create_phones_table.php)
- [apps/backoffice-laravel/database/migrations/2026_03_20_000001_add_client_id_to_phones_table.php](/home/tomas/EstetiCAN_2/apps/backoffice-laravel/database/migrations/2026_03_20_000001_add_client_id_to_phones_table.php)
- [apps/backoffice-laravel/database/migrations/2026_03_20_000002_migrate_phones_to_client_id.php](/home/tomas/EstetiCAN_2/apps/backoffice-laravel/database/migrations/2026_03_20_000002_migrate_phones_to_client_id.php)
- [apps/backoffice-laravel/database/migrations/2026_03_20_000003_cleanup_phones_table.php](/home/tomas/EstetiCAN_2/apps/backoffice-laravel/database/migrations/2026_03_20_000003_cleanup_phones_table.php)

Esquema vigente esperado:

- `id`
- `client_id`
- `number`
- `type`
- timestamps

Nota importante para externo:

- el modulo migro desde un esquema polimorfico `phoneable_*` a una relacion directa con cliente
- cualquier desarrollo nuevo debe asumir `client_id` como la referencia canonica

## Modelo Laravel actual

Fuente: [apps/backoffice-laravel/app/Models/Client.php](/home/tomas/EstetiCAN_2/apps/backoffice-laravel/app/Models/Client.php)

Relaciones activas:

- `addresses(): hasMany(Address::class)`
- `phones(): hasMany(Phone::class)`
- `pets(): hasMany(Pet::class)`
- `primaryPetPhotos(): hasManyThrough(PetPhoto::class, Pet::class)->where(is_primary = true)`

## Controlador y reglas de negocio actuales

Fuente: [apps/backoffice-laravel/app/Http/Controllers/ClientController.php](/home/tomas/EstetiCAN_2/apps/backoffice-laravel/app/Http/Controllers/ClientController.php)

### `index()`

- pagina clientes con `paginate(10)`
- eager loading de direcciones, telefonos y mascotas vivas
- las mascotas vivas cargan `primaryPhoto` y `latestPhoto` para UI compacta

### `show()`

- muestra detalle de cliente
- filtra mascotas con `whereNull('death_date')`

### `edit()`

- carga todas las mascotas del cliente
- ordena vivas primero y fallecidas despues
- permite acceder a gestion especializada de dependencias

### `store()` y `update()`

Validan y manipulan en una sola solicitud:

- datos base del cliente
- addresses[]
- phones[]
- pets[]

Observacion tecnica:

- el flujo actual de update procesa arrays anidados manualmente
- no existe aun capa de servicios ni form requests especializados

## Vistas relacionadas

- [apps/backoffice-laravel/resources/views/clients/index.blade.php](/home/tomas/EstetiCAN_2/apps/backoffice-laravel/resources/views/clients/index.blade.php)
- [apps/backoffice-laravel/resources/views/clients/show.blade.php](/home/tomas/EstetiCAN_2/apps/backoffice-laravel/resources/views/clients/show.blade.php)
- [apps/backoffice-laravel/resources/views/clients/edit.blade.php](/home/tomas/EstetiCAN_2/apps/backoffice-laravel/resources/views/clients/edit.blade.php)
- [apps/backoffice-laravel/resources/views/clients/create.blade.php](/home/tomas/EstetiCAN_2/apps/backoffice-laravel/resources/views/clients/create.blade.php)
- [apps/backoffice-laravel/resources/views/clients/partials/live-pets-grid.blade.php](/home/tomas/EstetiCAN_2/apps/backoffice-laravel/resources/views/clients/partials/live-pets-grid.blade.php)

## Decisiones funcionales vigentes

- cliente es la entidad comercial contenedora de mascotas
- mascotas fallecidas no deben contaminar los bloques operativos principales de cliente
- las dependencias complejas de mascota se gestionan desde la pantalla de mascota seleccionada, no dentro del formulario general de cliente

## Riesgos y observaciones para desarrollo externo

- existe desfase historico entre algunas migraciones originales y el estado real esperado del dominio
- `email` es nullable en base pero requerido en formularios actuales
- conviene no introducir cambios grandes en cliente sin revisar primero integridad entre migraciones, modelo y Blade

## Recomendaciones para siguiente iteracion

- extraer validaciones a Form Requests
- separar persistencia de addresses, phones y pets a servicios de aplicacion
- normalizar definitivamente discrepancias entre base y formulario
- documentar contrato de datos para integraciones externas