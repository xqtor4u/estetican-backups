# Modelo de Base de Datos — EstetiCAN

> **Fuente de verdad:** las migraciones en `apps/backoffice-laravel/database/migrations/`.
> **Actualizar este documento** cada vez que se agrega o modifica una tabla o columna.

---

## Índice de tablas

| Grupo | Tablas |
|---|---|
| **Identidad** | `users`, `operators`, `operator_roles`, `branches` |
| **Clientes y mascotas** | `clients`, `addresses`, `phones`, `pets`, `pet_medical_alerts`, `pet_photos`, `pet_vaccinations` |
| **Catálogo** | `services` |
| **Agenda SPA** | `spa_bookings`, `spa_booking_services` |
| **Presupuestos y cobro** | `quotes`, `quote_items`, `payments`, `cash_ledgers`, `bank_ledgers` |
| **Módulo contable** | `accounts`, `payment_methods`, `document_series`, `documents`, `journal_entries`, `journal_entry_lines`, `cash_registers`, `cash_sessions`, `cash_movements` |
| **Ejecución** | `executed_services`, `executed_service_items` |
| **Hotel** | `hotel_reservations`, `stays` |
| **Recursos físicos** | `resources`, `resource_allocations`, `resource_photos`, `resource_events`, `resource_event_updates`, `resource_event_photos` |
| **Operadores — estructura** | `operator_role_assignments`, `operator_branch_assignments`, `operator_compensation_profiles`, `operator_checkins` |
| **Sistema** | `system_settings`, `api_tokens`, `activity_log` |
| **Spatie (permisos)** | `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions` |
| **Framework** | `cache`, `jobs`, `sessions`, `password_reset_tokens`, `media` |

---

## Identidad

### `users`
Usuarios del backoffice. También representan operadores cuando `is_operator = true`.

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `name` | string | Login único (no es email) |
| `email` | string unique | |
| `password` | string | bcrypt |
| `first_name` | string nullable | |
| `last_name` | string nullable | |
| `ine_number` | string nullable | |
| `imss_number` | string nullable | |
| `address` | text nullable | Dirección libre (no normalizada) |
| `phone` | string nullable | |
| `profile_photo_path` | string nullable | Ruta en disco público |
| `emergency_contact_name` | string nullable | |
| `emergency_contact_phone` | string nullable | |
| `hire_date` | date nullable | |
| `role` | string nullable | Campo legacy; roles reales vía Spatie |
| `is_active` | boolean | Empleado activo en operación |
| `is_operator` | boolean | Aparece en selectores de operador |
| `operator_code` | string unique nullable | Código operativo (ej. `GRO-JMP`) |
| `operator_role_id` | FK → `operator_roles` nullable | Tipo de operador principal |
| `notes` | text nullable | |
| `remember_token` | string nullable | |
| `email_verified_at` | timestamp nullable | |
| `timestamps` | | `created_at`, `updated_at` |

> **Regla:** `is_active` = empleado vigente; acceso al backoffice se controla vía roles Spatie.
> Login por campo `name`, no por `email`.

---

### `operators`
Catálogo legado de operadores (pre-fusión). Sigue siendo la FK usada en `spa_bookings.operator_id` y en la app móvil.

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `code` | string unique | Ej. `GRO-JMP` |
| `name` | string | |
| `full_name` | string | Nombre completo |
| `role` | string nullable | Rol legacy |
| `operator_role_id` | FK → `operator_roles` nullable | |
| `branch_id` | FK → `branches` nullable | Sucursal base |
| `ine_number` | string nullable | |
| `imss_number` | string nullable | |
| `address` | text nullable | |
| `phone` | string nullable | |
| `profile_photo_path` | string nullable | |
| `emergency_contact_name` | string nullable | |
| `emergency_contact_phone` | string nullable | |
| `hire_date` | date nullable | |
| `professional_license` | string nullable | Cédula profesional |
| `specialty` | string nullable | |
| `university` | string nullable | |
| `is_active` | boolean | |
| `notes` | text nullable | |
| `timestamps` | | |

---

### `operator_roles`
Tipos de operador (Groomer, Veterinario, Anestesista, etc.).

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `code` | string unique | Ej. `GRO`, `VET` |
| `name` | string unique | |
| `description` | text nullable | |
| `default_hourly_rate` | decimal(10,2) nullable | |
| `is_active` | boolean | |
| `can_login` | boolean | Heredado, no se usa actualmente |
| `notes` | text nullable | |
| `timestamps` | | |

---

### `branches`
Sucursales del negocio.

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `code` | string unique | |
| `name` | string unique | |
| `street` | string nullable | |
| `exterior_number` | string nullable | |
| `interior_number` | string nullable | |
| `colonia` | string nullable | |
| `city` | string nullable | |
| `state` | string nullable | |
| `zip` | string nullable | |
| `country` | string nullable | |
| `lat` | decimal(10,8) nullable | Coordenadas GPS |
| `lng` | decimal(11,8) nullable | |
| `is_active` | boolean | |
| `notes` | text nullable | |
| `timestamps` | | |

---

## Clientes y mascotas

### `clients`
Dueños o responsables de mascotas.

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `lead_id` | FK → `leads` nullable | Origen de captación |
| `first_name` | string | |
| `last_name` | string nullable | |
| `phone` | string | Campo legacy; teléfonos reales en `phones` |
| `email` | string nullable | |
| `address` | string nullable | Campo legacy; direcciones reales en `addresses` |
| `city` | string nullable | Campo legacy |
| `state` | string nullable | Campo legacy |
| `zip_code` | string nullable | Campo legacy |
| `notes` | text nullable | |
| `timestamps` | | |

---

### `addresses`
Direcciones normalizadas de clientes (polimórfico hacia `clients`).

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `client_id` | FK → `clients` | |
| `type` | string | `home`, `work`, etc. |
| `street` | string | |
| `exterior_number` | string nullable | |
| `interior_number` | string nullable | |
| `colonia` | string nullable | |
| `city` | string | |
| `state` | string nullable | |
| `zip` | string nullable | |
| `country` | string | default `México` |
| `lat` | decimal(10,8) nullable | |
| `lng` | decimal(11,8) nullable | |
| `timestamps` | | |

---

### `phones`
Teléfonos de clientes y operadores (polimórfico).

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `phoneable_id` | bigint | Polimórfico |
| `phoneable_type` | string | Clase del modelo dueño |
| `number` | string | |
| `type` | string | `mobile`, `fixed` |
| `timestamps` | | |

> **Nota NT:** La relación `Address::phones()` morphMany fue removida (migración 2026-03-20 eliminó esas columnas en `phones`). Los teléfonos apuntan directamente a `clients`.

---

### `pets`
Entidad operativa principal. Separa **identidad** (esta tabla) de **trazabilidad** (`pet_photos`).

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `client_id` | FK → `clients` | |
| `profile_photo_path` | string nullable | Foto de identidad (círculo de perfil) |
| `name` | string | |
| `species` | string nullable | Perro, gato, etc. |
| `breed` | string nullable | |
| `birth_date` | date nullable | |
| `death_date` | date nullable | Nulo = viva |
| `microchip_code` | string nullable | |
| `tattoo_code` | string nullable | |
| `sex` | string nullable | |
| `coat_color` | string nullable | |
| `size` | string nullable | |
| `is_sterilized` | boolean | |
| `flagged_for_deletion` | boolean | Marcada para eliminar desde app móvil |
| `notes` | text nullable | |
| `deleted_at` | timestamp nullable | Soft delete |
| `timestamps` | | |

> `profile_photo_path` se sincroniza automáticamente cuando se marca una foto de `pet_photos` como `perfil`.

---

### `pet_medical_alerts`
Alertas médicas normalizadas por mascota.

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `pet_id` | FK → `pets` | |
| `category` | string | `general`, `alergia`, `medicamento`, etc. |
| `description` | text | |
| `severity` | string nullable | Nivel de gravedad |
| `notes` | text nullable | |
| `is_active` | boolean | |
| `timestamps` | | |

---

### `pet_photos`
Galería cronológica de fotos por mascota (bitácora visual).

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `pet_id` | FK → `pets` | |
| `photo_url` | string | Ruta en storage público |
| `photo_type` | string | `ingreso`, `incidencia`, `resultado`, `perfil` |
| `taken_at` | timestamp nullable | Autocompletado desde EXIF |
| `description` | text nullable | |
| `is_primary` | boolean | Si true → sincroniza `pets.profile_photo_path` |
| `timestamps` | | |

---

### `pet_vaccinations`
Registro de vacunas por mascota.

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `pet_id` | FK → `pets` | |
| `vaccine_name` | string | Rabia, Múltiple, Bordetella, etc. |
| `applied_at` | date nullable | |
| `expires_at` | date nullable | Crítico para control de acceso al Hotel |
| `notes` | text nullable | |
| `timestamps` | | |

---

## Catálogo

### `services`
Catálogo de servicios ofrecidos.

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `code` | string unique | Ej. `SPA-0001`, `HOT-0001` |
| `type` | string | `spa`, `hotel`, `extra`, `combo` |
| `operator_role_id` | FK → `operator_roles` nullable | Tipo de operador requerido |
| `name` | string | |
| `description` | text nullable | |
| `price` | decimal(10,2) | Precio base |
| `suggested_price` | decimal(10,2) nullable | Precio sugerido para cotización |
| `requires_advance` | boolean | Requiere anticipo |
| `advance_percentage` | decimal(5,2) nullable | % de anticipo requerido |
| `lead_time_hours` | int | Horas de anticipación necesarias |
| `duration_minutes` | int | Duración base |
| `suggested_duration_minutes` | int nullable | |
| `is_active` | boolean | |
| `account_id` | bigint FK nullable | Cuenta de ingreso contable para este servicio (módulo contable) |
| `timestamps` | | |

---

## Agenda SPA

### `spa_bookings`
Citas de servicio SPA. Ciclo de vida: `scheduled` → `work_order` → `completed`.

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `pet_id` | FK → `pets` | |
| `operator_id` | FK → `operators` nullable | Operador asignado (desde app móvil) |
| `scheduled_at` | datetime | Fecha y hora de la cita |
| `duration_minutes` | smallint nullable | Duración total en minutos |
| `total_estimated_price` | decimal(10,2) | Sincronizado desde quote aceptado |
| `status` | enum | `scheduled`, `work_order`, `completed`, `cancelled`, `no_show`, `unfulfillable` |
| `notes` | text nullable | |
| `cancellation_reason` | text nullable | |
| `timestamps` | | |

> `work_order` es el estado activo con orden de trabajo abierta. No existe `in_process`.

---

### `spa_booking_services`
Servicios incluidos en una cita (sincronizados desde el quote aceptado).

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `spa_booking_id` | FK → `spa_bookings` | |
| `service_id` | FK → `services` | |
| `current_price` | decimal(10,2) | Precio al momento de agendar |
| `timestamps` | | |

---

## Presupuestos y cobro

### `quotes`
Presupuestos por cita. Una cita puede tener múltiples versiones; solo una puede estar `accepted`.

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `spa_booking_id` | FK → `spa_bookings` | |
| `version_label` | string nullable | Ej. `v1`, `v2` |
| `status` | string | `draft`, `sent`, `accepted`, `rejected` |
| `total_amount` | decimal(10,2) | |
| `advance_amount` | decimal(10,2) | Anticipo requerido |
| `advance_payment_method` | string nullable | |
| `notes` | text nullable | |
| `timestamps` | | |

> Al aceptar un quote: sincroniza `spa_booking_services` + `total_estimated_price`, cambia booking a `work_order`, crea ledger de anticipo.

---

### `quote_items`
Líneas de servicio dentro de un presupuesto.

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `quote_id` | FK → `quotes` | |
| `service_id` | FK → `services` | |
| `operator_id` | FK → `operators` nullable | Especialista asignado |
| `is_external` | boolean | Proveedor externo |
| `price_override` | decimal(10,2) nullable | Precio específico para este ítem |
| `notes` | text nullable | |
| `timestamps` | | |

---

### `payments`
Tabla heredada de pagos (pre-ledgers). Mantener para compatibilidad.

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `client_id` | FK → `clients` | |
| `payable_id` | bigint nullable | Polimórfico |
| `payable_type` | string nullable | |
| `amount` | decimal(10,2) | |
| `processing_fee` | decimal(10,2) | |
| `payment_method` | string nullable | |
| `destination` | string | `caja` o `banco` |
| `external_reference` | string nullable | ID terminal o pasarela |
| `category` | string | `advance`, `partial`, `full`, `refund`, `misc` |
| `notes` | text nullable | |
| `cleared_at` | timestamp nullable | Fecha real de acreditación |
| `timestamps` | | |

---

### `cash_ledgers`
Ingresos a caja (efectivo). Fuente de verdad para contabilidad de efectivo.

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `client_id` | FK → `clients` | |
| `payable_id` | bigint | Polimórfico (normalmente → `Quote`) |
| `payable_type` | string | |
| `amount` | decimal(10,2) | |
| `payment_method` | string | Default `Efectivo` |
| `category` | string | `advance`, `liquidation`, `misc_charge` |
| `notes` | text nullable | |
| `timestamps` | | |

---

### `bank_ledgers`
Ingresos a banco (tarjeta, transferencia). Fuente de verdad para contabilidad bancaria.

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `client_id` | FK → `clients` | |
| `payable_id` | bigint | Polimórfico |
| `payable_type` | string | |
| `amount` | decimal(10,2) | |
| `payment_method` | string | Tarjeta, Transferencia, etc. |
| `external_reference` | string nullable | ID de terminal o Mercado Pago |
| `processing_fee` | decimal(10,2) | Comisión bancaria |
| `category` | string | |
| `notes` | text nullable | |
| `cleared_at` | timestamp nullable | Fecha de depósito real |
| `timestamps` | | |

> **Balance de una cita:** `$quote->cashLedgers->sum('amount') + $quote->bankLedgers->sum('amount')`. No usar `client->payments()`.

---

## Ejecución

### `executed_services`
Cabecera del trabajo ejecutado sobre una mascota.

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `spa_booking_id` | FK → `spa_bookings` nullable | |
| `pet_id` | FK → `pets` | |
| `final_price` | decimal(10,2) | |
| `notes` | text nullable | |
| `executed_at` | timestamp | |
| `timestamps` | | |

---

### `executed_service_items`
Detalle congelado de los servicios ejecutados (snapshot histórico).

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `executed_service_id` | FK → `executed_services` | |
| `service_id` | FK → `services` | |
| `operator_id` | FK → `operators` nullable | Quién lo ejecutó |
| `is_external` | boolean | Proveedor externo |
| `charged_price` | decimal(10,2) | |
| `timestamps` | | |

---

## Hotel

### `hotel_reservations`
Reservas de hospedaje por rango de fechas.

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `pet_id` | FK → `pets` | |
| `start_at` | datetime | |
| `end_at` | datetime | |
| `status` | enum | `scheduled`, `cancelled`, `fulfilled` |
| `timestamps` | | |

> Al crear/editar, puede asignar recurso (jaula) vía `resource_allocations`. Cancelar libera la jaula.

---

### `stays`
Ocupación real: check-in / check-out efectivos.

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `pet_id` | FK → `pets` | |
| `hotel_reservation_id` | FK → `hotel_reservations` nullable | |
| `check_in_at` | datetime | |
| `check_out_at` | datetime nullable | Nulo = aún hospedada |
| `notes` | text nullable | |
| `timestamps` | | |

---

## Recursos físicos

### `resources`
Activos físicos: jaulas, mesas de grooming, consultorios, etc.

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `branch_id` | FK → `branches` | |
| `profile_photo_path` | string nullable | Foto de identidad del activo |
| `resource_type` | string | `cage`, `grooming_table`, `consult_room`, etc. |
| `code` | string | Único por sucursal |
| `name` | string | |
| `capacity_label` | string nullable | Ej. `Talla M` |
| `administrative_status` | string | `active`, `inactive`, `retired` |
| `operational_status` | string | `available`, `occupied`, `maintenance` |
| `notes` | text nullable | |
| `deleted_at` | timestamp nullable | Soft delete |
| `timestamps` | | |

---

### `resource_allocations`
Bloqueos temporales de recursos (reserva, uso, limpieza, mantenimiento, bloqueo manual).

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `resource_id` | FK → `resources` | |
| `parent_allocation_id` | FK → self nullable | Para buffer de limpieza post-uso |
| `pet_id` | FK → `pets` nullable | |
| `allocation_type` | string | `reserved`, `occupied`, `cleaning`, `maintenance`, `manual_block` |
| `starts_at` | datetime | |
| `ends_at` | datetime | |
| `source_id` | bigint nullable | Polimórfico — origen del bloqueo |
| `source_type` | string nullable | `SpaBooking`, `HotelReservation`, etc. |
| `notes` | text nullable | |
| `timestamps` | | |

> Regla de disponibilidad: una jaula está libre si no hay traslape con ninguna fila activa en este intervalo.

---

### `resource_photos`
Galería de fotos del activo físico (evidencia, estado, historial visual).

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `resource_id` | FK → `resources` | |
| `photo_url` | string | |
| `photo_type` | string | `evidencia`, `estado`, `ingreso`, etc. |
| `taken_at` | datetime nullable | Autocompletado desde EXIF |
| `description` | text nullable | |
| `is_primary` | boolean | Sincroniza `resources.profile_photo_path` |
| `timestamps` | | |

---

### `resource_events`
Incidentes, mantenimientos y observaciones sobre un activo.

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `resource_id` | FK → `resources` | |
| `client_id` | FK → `clients` nullable | |
| `pet_id` | FK → `pets` nullable | |
| `service_id` | FK → `services` nullable | |
| `detected_by_user_id` | FK → `users` nullable | |
| `responsible_user_id` | FK → `users` nullable | |
| `closed_by_user_id` | FK → `users` nullable | |
| `source_id` | bigint nullable | Polimórfico |
| `source_type` | string nullable | |
| `event_type` | string | `incident`, `maintenance`, `observation`, `sanitary_block` |
| `event_status` | string | `open`, `in_progress`, `resolved`, `closed` |
| `severity` | string | `low`, `medium`, `high`, `critical` |
| `title` | string | |
| `description` | text nullable | |
| `occurred_at` | datetime nullable | |
| `detected_at` | datetime | |
| `resolved_at` | datetime nullable | |
| `timestamps` | | |

---

### `resource_event_updates`
Seguimiento por etapas de un evento de recurso.

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `resource_event_id` | FK → `resource_events` | |
| `created_by_user_id` | FK → `users` nullable | |
| `update_type` | string | `note`, `status_change`, `assignment` |
| `from_status` | string nullable | |
| `to_status` | string nullable | |
| `notes` | text nullable | |
| `timestamps` | | |

---

### `resource_event_photos`
Fotos de evidencia ligadas a un evento o a un seguimiento puntual.

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `resource_event_id` | FK → `resource_events` | |
| `resource_event_update_id` | FK → `resource_event_updates` nullable | |
| `photo_url` | string | |
| `photo_type` | string | `evidencia`, `apertura`, `cierre`, etc. |
| `taken_at` | datetime nullable | |
| `description` | text nullable | |
| `is_primary` | boolean | |
| `timestamps` | | |

---

## Operadores — estructura

### `operator_role_assignments`
Asignación de roles a operadores (uno puede tener varios).

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `operator_id` | FK → `operators` | |
| `operator_role_id` | FK → `operator_roles` | |
| `proficiency_level` | string nullable | |
| `is_primary` | boolean | |
| `starts_at` | timestamp nullable | |
| `ends_at` | timestamp nullable | |
| `timestamps` | | |

---

### `operator_branch_assignments`
Asignación de sucursales a operadores.

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `operator_id` | FK → `operators` | |
| `branch_id` | FK → `branches` | |
| `is_primary` | boolean | |
| `starts_at` | timestamp nullable | |
| `ends_at` | timestamp nullable | |
| `timestamps` | | |

---

### `operator_compensation_profiles`
Historial de perfiles salariales del operador.

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `operator_id` | FK → `operators` | |
| `compensation_type` | string | `hourly`, `fixed`, `commission` |
| `hourly_rate` | decimal(10,2) nullable | |
| `effective_from` | date | |
| `effective_to` | date nullable | Nulo = vigente |
| `notes` | text nullable | |
| `timestamps` | | |

---

### `operator_checkins`
Registro de entradas y salidas de operadores por sucursal (app móvil).

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `user_id` | FK → `users` | |
| `branch_id` | FK → `branches` | |
| `checked_in_at` | timestamp | |
| `checked_out_at` | timestamp nullable | Nulo = aún dentro |
| `auto_checkout` | boolean | True = fue por cambio de sucursal |
| `transgression_note` | text nullable | Nota si cambió de sucursal sin checkout |
| `timestamps` | | |

---

## Sistema

### `system_settings`
Toda la configuración del negocio (branding, SMTP, reglas, etc.). Sin config en código.

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `key` | string unique | Clave namespaceada: `branding.name`, `clinical.booking_grace_minutes` |
| `value` | text nullable | |
| `type` | string | `string`, `boolean`, `integer`, `image` |
| `timestamps` | | |

> Acceder siempre via `SystemSettings` service, nunca directamente. Ver `app/Support/SystemSettings/`.

---

### `api_tokens`
Tokens de autenticación para la app móvil (Bearer token SHA-256).

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `user_id` | FK → `users` | |
| `token` | string(64) unique | Hash SHA-256 |
| `name` | string | Default `mobile` |
| `last_used_at` | timestamp nullable | |
| `timestamps` | | |

---

### `activity_log`
Auditoría de cambios (spatie/laravel-activitylog). Modelos instrumentados: `SpaBooking`, `HotelReservation`, `Payment`, `Quote`, `Pet`, `User`, `SystemSetting`.

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `log_name` | string nullable | `citas-spa`, `pagos`, `mascotas`, etc. |
| `description` | string | `created`, `updated`, `deleted` |
| `subject_type` | string nullable | Modelo afectado |
| `subject_id` | bigint nullable | |
| `causer_type` | string nullable | Quién hizo el cambio |
| `causer_id` | bigint nullable | |
| `properties` | json nullable | `{old: {...}, attributes: {...}}` para `updated` |
| `timestamps` | | |

---

## Módulo contable

### `accounts`
Catálogo de cuentas contables. Estructura jerárquica para doble entrada. Gestionado en Backoffice → Finanzas → Catálogo de cuentas.

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `parent_id` | bigint FK nullable | Auto-referencia a `accounts.id` |
| `code` | string unique | Código estándar mexicano: 1000–5900 |
| `name` | string | Nombre de la cuenta |
| `type` | enum | `activo`, `pasivo`, `capital`, `ingreso`, `gasto` |
| `description` | text nullable | |
| `is_active` | boolean | default true |
| `allows_entries` | boolean | false en cuentas padre (agrupadora) |
| `timestamps` | | |

**Cuentas raíz del seeder inicial:** 1000 Activos, 2000 Pasivos, 3000 Capital, 4000 Ingresos, 5000 Gastos.
**Subcuentas clave:** 1100 Caja, 1210 Banco, 1310 Clientes, 4100 Grooming/SPA, 4500 Hospedaje, 4900 Otros ingresos.

---

### `payment_methods`
Catálogo de métodos de pago. Cada método apunta a la cuenta contable que se abona al cobrar.

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `code` | string unique | `EFECT`, `TARJ_DEB`, `TARJ_CRED`, `SPEI`, `CRYPTO` |
| `name` | string | Nombre legible |
| `type` | enum | `cash`, `card`, `transfer`, `crypto`, `gateway` |
| `account_id` | bigint FK nullable | Cuenta que se acredita al usar este método |
| `gateway_config` | json nullable | Reservado para pasarela futura |
| `requires_reference` | boolean | true = pide folio/autorización al registrar |
| `is_active` | boolean | default true |
| `timestamps` | | |

---

### `document_series`
Series de foliado para documentos. Permite series por sucursal o globales (`branch_id = null`). Gestionado en Backoffice → Finanzas → Series de documentos.

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `document_type` | enum | `recibo`, `factura`, `sin_documento` |
| `name` | string | Ej. "Recibos de pago" |
| `prefix` | string | Ej. `R-` |
| `suffix` | string | default `''` |
| `next_number` | integer | Siguiente número a asignar; se incrementa con `lockForUpdate` |
| `padding` | integer | Ceros a la izquierda, default 4 |
| `branch_id` | bigint FK nullable | null = global |
| `is_active` | boolean | |
| `timestamps` | | |

---

### `documents`
Documentos emitidos (recibos, facturas, notas). Cada documento tiene un folio único dentro de su serie.

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `document_series_id` | bigint FK | Serie a la que pertenece |
| `document_type` | enum | `recibo`, `factura`, `sin_documento` |
| `folio_number` | integer | Número correlativo dentro de la serie |
| `folio_display` | string | Folio formateado: `R-0001` |
| `status` | enum | `borrador`, `emitido`, `cancelado` |
| `client_id` | bigint FK nullable | |
| `branch_id` | bigint FK | |
| `issued_by_user_id` | bigint FK | Usuario que emite |
| `subtotal` | decimal(12,2) | |
| `tax_amount` | decimal(12,2) | default 0 |
| `total` | decimal(12,2) | |
| `email_to` | string nullable | Correo de envío |
| `email_sent_at` | timestamp nullable | |
| `fiscal_uuid` | string nullable | Reservado: UUID del CFDI SAT |
| `gateway_reference` | string nullable | Reservado: referencia de pasarela |
| `documentable_type` | string nullable | Morph: tipo del origen (`SpaBooking`, etc.) |
| `documentable_id` | bigint nullable | Morph: ID del origen |
| `timestamps` | | |

**Índice único:** `(document_series_id, folio_number)` — evita duplicados incluso en concurrencia.

---

### `journal_entries`
Asientos contables. Cada asiento debe estar balanceado (suma débitos = suma créditos). Estado `aplicado` equivale a "libro cerrado".

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `entry_date` | date | Fecha contable del asiento |
| `description` | string | Descripción del movimiento |
| `status` | enum | `borrador`, `aplicado`, `cancelado` |
| `document_id` | bigint FK nullable | Documento de soporte |
| `branch_id` | bigint FK nullable | |
| `created_by_user_id` | bigint FK | |
| `posted_by_user_id` | bigint FK nullable | Usuario que aplica el asiento |
| `posted_at` | timestamp nullable | |
| `reference_type` | string nullable | Morph: origen del asiento (`Quote`, `SpaBooking`, etc.) |
| `reference_id` | bigint nullable | |
| `notes` | text nullable | |
| `timestamps` | | |

---

### `journal_entry_lines`
Líneas de débito/crédito de cada asiento. Una línea tiene débito O crédito, nunca ambos.

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `journal_entry_id` | bigint FK | |
| `account_id` | bigint FK | Cuenta contable afectada |
| `debit` | decimal(12,2) | default 0 |
| `credit` | decimal(12,2) | default 0 |
| `description` | string nullable | Concepto de la línea |
| `timestamps` | | |

---

### `cash_registers`
Cajas físicas por sucursal. Una sucursal puede tener varias cajas.

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `branch_id` | bigint FK | |
| `name` | string | Ej. "Caja principal" |
| `is_active` | boolean | |
| `timestamps` | | |

---

### `cash_sessions`
Sesiones de apertura y corte de caja. Registra quién abrió, cuánto había, quién cerró y la diferencia.

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `cash_register_id` | bigint FK | |
| `branch_id` | bigint FK | |
| `opened_by_user_id` | bigint FK | |
| `opened_at` | timestamp | |
| `opening_amount` | decimal(12,2) | Monto declarado al abrir |
| `closed_by_user_id` | bigint FK nullable | |
| `closed_at` | timestamp nullable | |
| `closing_amount` | decimal(12,2) nullable | Monto contado al cerrar |
| `expected_amount` | decimal(12,2) nullable | Calculado por el sistema |
| `difference` | decimal(12,2) nullable | `closing_amount - expected_amount` |
| `status` | enum | `abierta`, `cerrada` |
| `notes` | text nullable | |
| `timestamps` | | |

---

### `cash_movements`
Movimientos manuales dentro de una sesión de caja (retiros, depósitos a banco, gastos, pérdidas, entradas de efectivo). Cada movimiento genera automáticamente un asiento contable de doble entrada.

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `cash_session_id` | bigint FK → `cash_sessions` | Sesión a la que pertenece |
| `type` | enum | `retiro`, `deposito_banco`, `gasto`, `perdida`, `entrada` |
| `direction` | enum | `salida` (retiro/depósito/gasto/pérdida) o `entrada` |
| `amount` | decimal(12,2) | Siempre positivo |
| `concept` | string | Descripción breve del movimiento |
| `notes` | text nullable | Notas adicionales |
| `counterpart_account_id` | bigint FK → `accounts` | Cuenta contable contrapartida de Caja |
| `journal_entry_id` | bigint FK → `journal_entries` | Póliza generada automáticamente |
| `created_by_user_id` | bigint FK → `users` | Quién registró el movimiento |
| `timestamps` | | |

**Doble entrada automática:**
- Salidas (retiro/deposito_banco/gasto/perdida): DR contrapartida / CR Caja (id=6, código 1100)
- Entradas: DR Caja / CR contrapartida

**`expected_amount` de sesión:** `opening_amount + cobros_efectivo + total_entradas - total_salidas`

---

## Tablas de framework (no editar)

| Tabla | Uso |
|---|---|
| `cache` | Laravel cache driver |
| `jobs` | Queue jobs |
| `sessions` | Sesiones web |
| `password_reset_tokens` | Reset de contraseñas |
| `media` | spatie/laravel-medialibrary |
| `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions` | spatie/laravel-permission |
| `leads`, `campaign_sources` | Módulo de captación (prototipo, no activo en UI) |
| `audit_logs` | Log de auditoría heredado (reemplazado por `activity_log`) |
| `service_status_logs`, `service_photos`, `notification_queues` | Módulos en prototipo, sin UI activa |
