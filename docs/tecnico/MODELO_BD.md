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
| **Módulo Clínico Veterinario** (independiente, apagado por defecto — BL-046) | `clinical_visits`, `pet_weights`, `pet_allergies`, `pet_conditions`, `clinical_diagnoses`, `clinical_prescriptions`, `clinical_prescription_items`, `clinical_attachments`, `items` (BL-050, fundación del futuro inventario) |
| **Ejecución** | `executed_services`, `executed_service_items` |
| **Hotel** | `hotel_reservations`, `stays` |
| **Recursos físicos** | `resources`, `resource_allocations`, `resource_photos`, `resource_events`, `resource_event_updates`, `resource_event_photos` |
| **Operadores — estructura** | `operator_role_assignments`, `operator_branch_assignments`, `operator_compensation_profiles`, `operator_checkins` |
| **Comunicaciones** | `whatsapp_templates`, `booking_messages`, `recurrence_messages` |
| **Mapa y cobertura espacial** | `vehicles` |
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
| `last_name` | string nullable | **Vestigial** desde BL-045 — accessor calculado (`apellido_paterno` + `apellido_materno`), la columna cruda ya no se escribe |
| `apellido_paterno` | string nullable | BL-045 — mismo patrón que `clients` (BL-044) |
| `apellido_materno` | string nullable | BL-045 — nunca obligatorio (convención mexicana) |
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
| `name` | string | Legado — a veces espeja el `name` (login) del `User` vinculado, a veces duplica el nombre completo si el operador se creó directo sin `User`. No se atomizó, sigue como texto libre |
| `full_name` | string nullable | **Vestigial** desde BL-045b — accessor calculado (`first_name` + `apellido_paterno` + `apellido_materno`). Nullable desde BL-045b (antes era `NOT NULL`, rompía altas nuevas tras dejar de escribirse directo) |
| `first_name` | string nullable | BL-045b — a diferencia de `clients`/`users`, `operators` no tenía ningún campo de nombre separado; se creó de cero junto con los apellidos |
| `apellido_paterno` | string nullable | BL-045b |
| `apellido_materno` | string nullable | BL-045b — nunca obligatorio (convención mexicana) |
| `role` | string nullable | Rol legacy |
| `operator_role_id` | FK → `operator_roles` nullable | Agregado 30/06/2026 |
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
| `last_name` | string nullable | **Vestigial (BL-044)** — ya no se escribe, columna congelada con el valor previo a la atomización. `Client::getLastNameAttribute()` la recalcula desde `apellido_paterno`+`apellido_materno`; pendiente `drop` en limpieza futura una vez confirmado que nada más la lee directo por SQL |
| `apellido_paterno` | string nullable | BL-044. Único apellido requerido en alta (web); origen real de `last_name`/`full_name` |
| `apellido_materno` | string nullable | BL-044. Siempre opcional (convención mexicana) |
| `phone` | string | Campo legacy; teléfonos reales en `phones` |
| `email` | string nullable | |
| `address` | string nullable | Campo legacy; direcciones reales en `addresses` |
| `city` | string nullable | Campo legacy |
| `state` | string nullable | Campo legacy |
| `zip_code` | string nullable | Campo legacy |
| `notes` | text nullable | |
| `receives_offers` | boolean, default true | Preferencias de comunicación (BL-041) — opt-out, no opt-in. Autogestionable por el cliente vía `/preferencias/{client}` (URL firmada, sin login) |
| `receives_service_reminders` | boolean, default true | Bloquea envío real (422) en Bandeja Diaria/Recurrencias, ambos canales |
| `receives_job_updates` | boolean, default true | Bloquea `ServiceSummaryMail` (junto con el switch `operational_auto_email_report`) |
| `receives_account_statements` | boolean, default true | Sin emisor todavía — solo se guarda la preferencia |
| `receives_other_notifications` | boolean, default true | Catch-all, sin emisor todavía |
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
| `lat` | decimal(10,8) nullable | Ubicación propia opcional (Mapa y cobertura, `AX-MAPZN`) — puede diferir de la dirección del cliente, ej. recolección en otro punto |
| `lng` | decimal(11,8) nullable | |
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

> **Cambiar dueño:** `PUT pets/{pet}/owner` (`pets.owner.update`, `PetController::updateOwner`) reasigna `client_id` desde un modal en `pets/show.blade.php` ("Cambiar dueño"). Auditado automáticamente vía Activitylog (`client_id` está en `logOnly`). `SpaBooking`/`HotelReservation`/`Quote` no tienen `client_id` propio (derivan el dueño vía `pet_id`), así que su historial "cambia de dueño" retroactivamente en cualquier vista — decisión intencional, no se reescribe nada. `ResourceEvent` sí tiene `client_id` propio como snapshot histórico y **no se actualiza** al reasignar (decisión explícita: preserva quién era el dueño cuando ocurrió cada incidente).

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
Registro de vacunas por mascota. **Ya no está huérfana** (BL-046) — tiene modelo `PetVaccination`, CRUD en el módulo clínico (`Clinical/PetVaccinationController`) y por fin cumple el propósito original del comentario "Crítico: Control de acceso al Hotel" vía `VaccinationEligibilityChecker` (advertencia, no bloqueo — ver sección Módulo Clínico Veterinario).

**BL-048:** cada vacuna es un `Service` del catálogo único (`type='vaccine'`) en vez de un catálogo aparte — ver `services.is_core_vaccine` más abajo y la nota en `recurrence_messages`. `vaccine_name` sigue existiendo pero ahora es un **snapshot automático** del nombre del servicio elegido (`Clinical/PetVaccinationController` lo llena solo) — ya no se teclea a mano, elimina el error de dedo.

**BL-050:** `service_id` responde "¿qué tipo de vacuna es?" (para la recurrencia/recordatorio); `item_id` responde "¿qué producto/marca/presentación específico se usó?" — son conceptos distintos y complementarios (la misma "Vacuna Rabia" puede aplicarse con marcas/lotes distintos con el tiempo). `is_external` marca que **esta aplicación puntual** fue fuera de EstetiCAN (otro veterinario, campaña antirrábica) — se registra igual para que la mascota quede protegida y `VaccinationEligibilityChecker` no la marque como faltante, pero no implica ningún descuento de inventario ni cargo a cuenta (esas consecuencias no existen todavía — quedan listas para cuando exista BL-049). Es un flag propio de `pet_vaccinations`, distinto de `clinical_visits.is_external` — se puede registrar sin pasar por crear una visita completa.

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `pet_id` | FK → `pets` | |
| `service_id` | FK → `services` nullable | BL-048 — el servicio tipo `vaccine` elegido; fuente real del nombre |
| `item_id` | FK → `items` nullable | BL-050 — producto/marca/presentación específico usado |
| `is_external` | boolean, default false | BL-050 — esta aplicación fue fuera de EstetiCAN (ver nota arriba) |
| `vaccine_name` | string | Snapshot automático de `service.name` (BL-048) — no editable directo |
| `applied_at` | date nullable | |
| `expires_at` | date nullable | Usado por `VaccinationEligibilityChecker` |
| `notes` | text nullable | |
| `lot_number` | string nullable | BL-046 — siempre a mano, el lote varía aunque el producto/marca sea el mismo |
| `manufacturer` | string nullable | BL-046 — BL-050: se auto-llena desde `item.brand` si hay `item_id` y no se capturó a mano |
| `administered_by_operator_id` | FK → `operators` nullable | BL-046 |
| `clinical_visit_id` | FK → `clinical_visits` nullable | BL-046 — si se aplicó durante una consulta |
| `dose_number` | integer nullable | BL-046 — ej. 1 de 3 en serie de cachorro |
| `route` | enum nullable | `subcutaneous`/`intramuscular`/`intranasal`/`oral` — BL-046 |
| `site` | string nullable | BL-046 |
| `reaction_notes` | text nullable | BL-046 |
| `timestamps` | | |

---

## Módulo Clínico Veterinario (BL-046 — independiente, apagado por defecto)

Expediente clínico veterinario formato SOAP. **Módulo de negocio separado** del spa/grooming/hotel — activable vía `SystemSettings` (sección `clinical`, campo `clinical_module_enabled`, default `false`). Mientras esté apagado no aparece en la navegación, sus rutas responden 404 (`EnsureClinicalModuleEnabled` middleware), y `VaccinationEligibilityChecker` no advierte nada. Comparte identidad atómica con `clients`/`pets` (nunca duplica esos datos) y con el pool de `operators` (el veterinario es un operador más, `operator_role` nuevo `veterinario`). Controllers en `app/Http/Controllers/Clinical/`, vistas en `resources/views/clinical/`, dominio en `App\Domain\Clinical`.

### `clinical_visits`
Encabezado SOAP — tabla central del expediente. Inmutable tras firmarse (`status`: `draft`→`signed`→`amended`); corrección solo vía nota aclaratoria enlazada (nunca edición in-place, guardia en `ClinicalVisit::booted()`).

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `pet_id` | FK → `pets` | |
| `operator_id` | FK → `operators` (restrict) | Quien atiende |
| `branch_id` | FK → `branches` nullable | |
| `visit_type` | enum | `consultation`/`follow_up`/`emergency`/`pre_grooming_check`/`vaccination` |
| `visited_at` | datetime | |
| `reason_for_visit` | text | |
| `subjective` | text nullable | Formato SOAP — Subjetivo |
| `weight_kg`, `temperature_celsius`, `heart_rate_bpm`, `respiratory_rate_bpm`, `mucous_membranes`, `hydration_status`, `body_condition_score`, `objective_notes` | varios | Formato SOAP — Objetivo (signos semi-estructurados, escala de condición corporal WSAVA 1-9) |
| `assessment` | text nullable | Formato SOAP — Evaluación |
| `plan`, `follow_up_at` | text/date nullable | Formato SOAP — Plan |
| `status` | enum | `draft`/`signed`/`amended` |
| `signed_by_operator_id` | FK → `operators` nullable | Puede diferir de `operator_id` (ej. auxiliar captura, veterinario firma) |
| `signed_at` | datetime nullable | |
| `professional_license_snapshot` | string nullable | Copia congelada de `operators.professional_license` al firmar |
| `amends_visit_id` | FK → `clinical_visits` (self) nullable | Enlaza la nota aclaratoria con la visita original |
| `amendment_reason` | text nullable | |
| `is_external` | boolean | Atención de un veterinario ajeno a EstetiCAN |
| `external_provider_name`, `external_provider_license`, `external_clinic_name` | string nullable | Solo si `is_external` |
| `external_status` | enum nullable | `pending_external_report`/`completed` — seguimiento manual, sin sync automática |
| `timestamps` | | |

### `pet_weights`
Peso histórico — no solo de consultas veterinarias, también de check-in de grooming.

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `pet_id` | FK → `pets` | |
| `clinical_visit_id` | FK → `clinical_visits` nullable | Si vino de una visita SOAP (espejado automático por `ClinicalVisitService`) |
| `weight_kg` | decimal(6,2) | |
| `measured_at` | datetime | |
| `recorded_by_operator_id` | FK → `operators` nullable | |
| `source` | enum | `clinical_visit`/`grooming_checkin`/`manual` |
| `notes` | text nullable | |
| `timestamps` | | |

### `pet_allergies`
Alergias estructuradas (reemplaza el uso de `pet_medical_alerts.category = 'alergia'` como texto libre, para el módulo clínico — `pet_medical_alerts` sigue viva para la alerta rápida operativa).

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `pet_id` | FK → `pets` | |
| `allergen` | string | |
| `allergen_type` | enum | `food`/`medication`/`environmental`/`flea_tick`/`other` |
| `reaction_description` | text nullable | |
| `severity` | enum | `mild`/`moderate`/`severe`/`anaphylaxis` |
| `diagnosed_at` | date nullable | |
| `is_active` | boolean | |
| `medical_alert_id` | FK → `pet_medical_alerts` nullable | Espejo opcional hacia la alerta rápida |
| `clinical_visit_id` | FK → `clinical_visits` nullable | |
| `recorded_by_operator_id` | FK → `operators` nullable | |
| `timestamps` | | |

### `pet_conditions`
Problem list / condiciones crónicas.

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `pet_id` | FK → `pets` | |
| `name` | string | |
| `icd_code` | string nullable | Reservado, sin catálogo poblado aún |
| `status` | enum | `active`/`controlled`/`resolved`/`chronic_monitoring` |
| `onset_date`, `resolved_date` | date nullable | |
| `promoted_from_diagnosis_id` | FK → `clinical_diagnoses` nullable | Si se creó promoviendo un diagnóstico puntual |
| `notes` | text nullable | |
| `medical_alert_id` | FK → `pet_medical_alerts` nullable | |
| `timestamps` | | |

### `clinical_diagnoses`
Diagnósticos puntuales por visita, promovibles a `pet_conditions`.

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `clinical_visit_id` | FK → `clinical_visits` | |
| `pet_id` | FK → `pets` | Denormalizado para queries directas |
| `diagnosis` | string | |
| `diagnosis_type` | enum | `presumptive`/`definitive`/`differential`/`ruled_out` |
| `icd_code` | string nullable | |
| `notes` | text nullable | |
| `promoted_to_condition_id` | FK → `pet_conditions` nullable | |
| `timestamps` | | |

### `clinical_prescriptions` / `clinical_prescription_items`
Recetas — encabezado/líneas, mismo patrón que `executed_services`/`executed_service_items`.

| Columna (`clinical_prescriptions`) | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `clinical_visit_id` | FK → `clinical_visits` | |
| `pet_id` | FK → `pets` | |
| `prescribed_by_operator_id` | FK → `operators` (restrict) | |
| `prescribed_at` | datetime | |
| `general_instructions` | text nullable | |

| Columna (`clinical_prescription_items`) | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `clinical_prescription_id` | FK → `clinical_prescriptions` | |
| `drug_name`, `concentration` | string | |
| `dose`, `frequency` | string | |
| `route` | enum | `oral`/`topical`/`subcutaneous`/`intramuscular`/`intravenous`/`ophthalmic`/`otic`/`other` |
| `duration_days` | integer nullable | |
| `special_instructions` | text nullable | |

### `clinical_attachments`
Adjuntos clínicos (laboratorio/imagenología) — tabla creada en Fase 1, **UI/upload real diferido a Fase 2** (generalizar `PetPhotoImageManager` para aceptar PDF).

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `clinical_visit_id` | FK → `clinical_visits` nullable | |
| `pet_id` | FK → `pets` | |
| `attachment_type` | enum | `lab_result`/`xray`/`ultrasound`/`other_imaging`/`referral_letter`/`other` |
| `file_path`, `file_mime_type` | string | |
| `description` | text nullable | |
| `performed_at` | date nullable | |
| `performed_by` | string nullable | Laboratorio/clínica externa |
| `uploaded_by_operator_id` | FK → `operators` nullable | |
| `timestamps` | | |

### `items` (BL-050, BL-051)
**Maestro de artículos — fundación atómica para el futuro módulo Tienda/Inventario (BL-049), sin funcionalidad de stock **transaccional** todavía.** A pedido explícito del usuario: en vez de esperar a diseñar el inventario completo para empezar a capturar productos, esta tabla ya existe con la identidad correcta (marca, presentación, departamento) para no tener que deshacer y rehacer el trabajo de vacunas cuando llegue el inventario real. **Deliberadamente sin movimientos/almacenes/histórico** — eso lo agrega BL-049 cuando se diseñe, referenciando `items.id`, sin tocar este esquema. Primer consumidor real: `pet_vaccinations.item_id`. CRUD propio en Catálogos → Artículos (`app/Http/Controllers/ItemController.php`, permisos `ver/crear/editar/eliminar catalogo_articulos`), independiente del módulo clínico — la ficha de mascota solo consume el `<select>` y el alta rápida.

BL-051 agregó `price`, `ai_visible` y `stock_quantity` — campos mínimos para que el asistente IA (BL-042) pueda mencionar artículos (accesorios, medicinas) en venta, sin construir el módulo de inventario real. `stock_quantity` es un **contador simple editable a mano** (sin movimientos/histórico); cuando se diseñe BL-049 con tablas de movimientos reales, este campo puede pasar a ser calculado o eliminarse a favor de esas tablas — es intencionalmente el mínimo necesario para no complicarse ahora.

**Módulo Tienda activable (BL-058, 16/07/2026):** todo este maestro (además de `groups`/`group_components` abajo) es alcanzable solo si `SystemSettings` → sección `store`, campo `store_module_enabled` está prendido (**default `true`** — a diferencia de Clínica, ya estaba en uso real en producción cuando se agregó el toggle). Apagado: rutas `items.*`/`groups.*`/`item-movements.*` responden 404 (`EnsureStoreModuleEnabled` middleware), desaparecen de navegación, y el armador de cotizaciones de Spa oculta "Agregar grupo completo"/"Agregar artículo suelto" (Servicios sigue siendo el flujo core, no se ve afectado).

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `name` | string | |
| `department` | string nullable | "Farmacia", "Accesorios", etc. — texto libre con sugerencias, sin enum |
| `brand` | string nullable | Marca |
| `presentation` | string nullable | Ej. "Frasco 1 dosis", "Multidosis 10ml" |
| `cost_price` | decimal(10,2) nullable | BL-057 — costo de compra. Base para sugerir `price` según el margen configurable (`SystemSettings` → sección `store`, campo `store_profit_margin_percentage`, default 30%): `price sugerido = cost_price × (1 + margen/100)`. Solo sugerencia (botón "usar sugerido" en el form) — `price` se puede editar libre, sin forzar el cálculo |
| `price` | decimal(10,2) nullable | BL-051 — si es null, el asistente IA dice "precio a consultar" |
| `is_active` | boolean, default true | |
| `ai_visible` | boolean, default false | BL-051 — controla si el asistente IA puede mencionar el artículo. Default `false`: nada se expone hasta marcarlo a mano |
| `stock_quantity` | int con signo, default 0 | BL-049 — caché mantenido por `ItemMovementService` (ver `item_movements` abajo), **no editable a mano** desde el form. Puede quedar negativo (consumo sin entrada previa capturada) — es información real, no un error. El asistente IA solo menciona artículos con `stock_quantity > 0` |
| `account_id` | bigint FK nullable → `accounts` | BL-051b (Grupos) — mismo patrón que `services.account_id`, para que `AccountingService` clasifique el ingreso por artículos vendidos dentro de un Grupo |
| `photo_path` | string nullable | BL-051c — foto del producto (`ItemPhotoImageManager`, mismo patrón que `Operator`/`Pet`), usada en el listado de Artículos y en la tabla de componentes de Grupos |
| `notes` | text nullable | |
| `timestamps` | | |

### `item_movements` (BL-049 "IM sencillo")
Ledger append-only de movimientos de inventario — mismo espíritu que `cash_ledgers`/`bank_ledgers` (sin saldo cacheado por renglón; `items.stock_quantity` es el único caché, mantenido transaccionalmente con `lockForUpdate()` por `ItemMovementService::record()`).

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `item_id` | FK → `items` nullable, nullOnDelete | |
| `item_name_snapshot` | string | Nombre del artículo al momento del movimiento — sobrevive aunque se borre el artículo |
| `branch_id` | FK → `branches` nullable, nullOnDelete | Nullable a propósito: no se fuerza sucursal hoy, pero el campo ya existe para cuando el inventario sea multi-sucursal |
| `type` | string | `entrada`, `salida`, `consumo_servicio`, `ajuste`, `perdida` — texto libre, sin enum en BD |
| `quantity` | int con signo | Delta: + entrada/ajuste positivo, - salida/consumo/pérdida/ajuste negativo |
| `reference_type`/`reference_id` | morphs nullable | Origen del movimiento — hoy usado por `PetVaccinationController` (consumo automático al aplicar una vacuna con `item_id` no externa) |
| `notes` | text nullable | |
| `created_by_user_id` | FK → `users` nullable, nullOnDelete | Null en movimientos automáticos (ej. consumo de vacuna) |
| `timestamps` | | |

### `item_branch_stocks` (BL-049, multi-sucursal real — 17/07/2026)
Saldo cacheado por (artículo, sucursal), mantenido por `ItemMovementService::record()` junto con el caché global `items.stock_quantity` — recalculado con `SUM(quantity)` de `item_movements` filtrado por `branch_id`, dentro de la misma transacción con `lockForUpdate()` sobre el `Item` (mismo lock, no uno nuevo). Solo se llena cuando el movimiento trae `branch_id` — los automáticos de hoy (consumo de vacuna) siguen sin sucursal y no generan fila aquí, solo cuentan en el total global.

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `item_id` | FK → `items`, **cascadeOnDelete** | A diferencia de `item_movements` (nullOnDelete, es ledger histórico), esta tabla es un caché derivado sin valor propio |
| `branch_id` | FK → `branches`, **NOT NULL**, cascadeOnDelete | A diferencia de `item_movements.branch_id` (nullable): MySQL trata cada NULL como distinto en un índice único compuesto, así que `unique(item_id, branch_id)` no protegería contra duplicados de "sin sucursal" si se permitiera NULL aquí. El saldo "sin sucursal" se deriva por resta (`items.stock_quantity - SUM(item_branch_stocks.quantity)`) donde se necesita mostrar, sin persistirse |
| `quantity` | int con signo, default 0 | Puede quedar negativo, mismo criterio que `items.stock_quantity` |
| `timestamps` | | |

Índice único `(item_id, branch_id)`.

**Formulario manual de movimientos** (Artículos → editar → "Movimientos de inventario"): `branch_id` pasó de opcional a **obligatorio** en esta pieza — consistente con `cash_registers`/`cash_sessions`/`resources`, que ya tratan sucursal como campo estructural. Los movimientos automáticos (consumo de vacunas) no pasan por este formulario y no se vieron afectados.

**Descuento por venta cobrada — implementado el 18/07/2026:** `App\Domain\Inventory\Services\BookingStockConsumptionService` (`BookingStockConsumptionServiceInterface`) descuenta las líneas `spa_booking_items` de una cita al completarse (`SpaBooking.status = completed`), en la sucursal `Operator::primaryBranch()` del operador asignado (`null` si no tiene sucursal primaria — el consumo sigue siendo real en el caché global, solo no genera fila en esta tabla). Llamado inline (mismo patrón que `AccountingServiceInterface` en `PaymentController`) en los 3 puntos reales donde una cita pasa a `completed` — no hay Observer/evento de dominio en este código (ver NT-020) — `SpaBookingController::update()` (web, "Finalizar sesión"), `Api\PaymentController::store()` (cobro móvil con `mark_completed`), `Api\BookingController::update()` (edición genérica móvil). Idempotente por diseño: antes de descontar una línea verifica si ya existe un `item_movements` con `reference` = ese `SpaBookingItem`; si existe, la omite — cubre el caso real de que ninguno de los 3 endpoints impide re-enviar `status=completed` sobre una cita ya completada. `item_movements.quantity` es `integer` pero `spa_booking_items.quantity` es `decimal:2` (permite fracciones de servicio) — se redondea con `(int) round()` al descontar; una fracción de unidad de producto se trunca (limitación aceptada, no resuelta).

**Fuera de alcance (queda para BL-049 fase siguiente):** transferencias entre sucursales (decidido reusar `branch_id` como unidad de ubicación, sin tabla `warehouses` nueva), y el filtro `stock_quantity > 0` del asistente IA (`ServiceCatalogPromptBuilder.php`) sigue leyendo el total global sin cambios.

### `groups` y `group_components` (Grupos — combos de Servicios + Artículos)
"Grupo" agrupa Servicios (mano de obra) y Artículos (insumos) con cantidad, para agregarlos todos a una cotización con un clic, facturados desglosados. Ej. "Corte de cola de perro" = 0.5 hrs de Veterinario (Service) + 5 vendas (Item). Precio del Grupo **no se cachea** — se calcula al vuelo (`Group::calculatedPrice()`, `SUM(quantity × precio vigente del catálogo)`), igual que `Account::balance()`, para que un cambio de precio en el catálogo se refleje sin invalidar nada.

**`groups`**: `id`, `name`, `description` nullable, `is_active` default true, `notes` nullable, `timestamps`.

**`group_components`**: `id`, `group_id` (FK, cascadeOnDelete), `service_id` (FK nullable, **restrictOnDelete**), `item_id` (FK nullable, **restrictOnDelete**), `quantity` (decimal 8,2, default 1) + CHECK constraint (exactamente uno de `service_id`/`item_id` no nulo). `restrictOnDelete` en vez de `nullOnDelete`: con el CHECK exigiendo uno no-nulo, dejar ambos en NULL rompería la restricción — en su lugar, `ItemController`/`ServiceController::destroy()` verifican primero si el registro es componente de algún Grupo y devuelven un error amigable.

---

## Catálogo

### `services`
Catálogo **único** de servicios ofrecidos — deliberadamente uno solo para todo (spa, hotel, vacunas, y lo que se agregue después: recogida a domicilio, revisión de salud, etc.), decisión explícita del usuario en BL-048 en vez de catálogos separados por tipo de negocio.

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `code` | string unique | Ej. `SPA-0001`, `HOT-0001` |
| `type` | string | `spa`, `hotel`, `extra`, `combo`, `vaccine` (BL-048) — sin enum en BD, solo convención de UI |
| `department` | string nullable | BL-050 — "Farmacia", "Accesorios", etc., mismo espíritu que `items.department`, pensando en agrupar de cara al futuro inventario |
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
| `recurrence_days` | unsigned smallint nullable | Días de recurrencia esperada (ej. 30 en "Baño", 365 en una vacuna anual). Null = no aplica recordatorio periódico. Usado por la pantalla Recurrencias (BL-029/BL-048) para detectar mascotas vencidas |
| `is_core_vaccine` | boolean, default false | BL-048 — solo relevante si `type='vaccine'`. Marca cuáles vacunas exige `VaccinationEligibilityChecker` al agendar spa/hotel (advertencia, no bloqueo) |
| `ai_visible` | boolean, default false | BL-051 — controla si el asistente IA (BL-042) puede mencionar el servicio. Default `false`: nada se expone hasta marcarlo a mano, ver `ServiceCatalogPromptBuilder` |
| `is_generic` | boolean, default false | BL-051 — el asistente IA confirma que el servicio existe pero **no da precio**; invita a agendar cita de evaluación. Pensado para servicios como "Cirugía" sin costo fijo |
| `is_emergency` | boolean, default false | BL-051 — el asistente IA invita al visitante a usar el botón de WhatsApp de inmediato (mismo CTA de `ai_assistant_cta_url`) en vez de seguir en el chat |
| `timestamps` | | |

**Nota BL-048 (modelo operativo — servicios que intersectan módulos):** un servicio tipo `vaccine` se **aplica** vía el módulo de Veterinaria (`pet_vaccinations`, con `administered_by_operator_id`/`clinical_visit_id` — el evento completo, incluyendo quién la aplicó), no vía `spa_bookings`. Para que la misma pantalla de Recurrencias (pensada originalmente solo para servicios de spa) también cubra vacunas, `RecurrenceMessageController::lastServiceDatesByPet()` tiene una rama: si `service.type === 'vaccine'`, la "última vez" sale de `MAX(pet_vaccinations.applied_at)` en vez de `spa_bookings` completados. El resto del flujo (plantillas, `recurrence_messages`, envío wa.me/correo) es 100% el mismo, sin tabla ni pantalla nueva. La ficha de mascota (`pets/show.blade.php`) también refleja esto **informativamente** en spa/hotel (sección "Vacunación": nombre de vacuna + fechas + vigente/vencida) — sin mostrar quién la aplicó, ese detalle queda exclusivo de las pantallas `clinical/*`.

---

## Agenda SPA

### `spa_bookings`
Citas de servicio SPA. Ciclo de vida: `scheduled` → `work_order` → `completed`.

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `pet_id` | FK → `pets` | |
| `operator_id` | FK → `operators` nullable | Operador asignado (desde app móvil) |
| `created_by_user_id` | FK → `users` nullable, nullOnDelete | Usuario (dueño de la sesión, web o móvil) que agendó la cita — `auth()->id()` capturado en `BookingService::scheduleSpaSession()` (web) y `Api\BookingController::store()` (móvil). Relación `SpaBooking::createdBy()` |
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
| `quantity` | decimal(8,2), default 1 | Grupos — permite cantidades fraccionarias (ej. 0.5 hrs) |
| `group_id` | FK → `groups` nullable, nullOnDelete | Trazabilidad: de qué Grupo vino esta línea (si vino de uno) |
| `current_price` | decimal(10,2) | Precio total de la línea al momento de agendar (`quantity` ya aplicada) |
| `timestamps` | | |

### `spa_booking_items`
Paralela a `spa_booking_services` — artículos congelados al aceptar una cotización (Grupos).

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `spa_booking_id` | FK → `spa_bookings`, cascadeOnDelete | |
| `item_id` | FK → `items`, cascadeOnDelete | |
| `group_id` | FK → `groups` nullable, nullOnDelete | |
| `quantity` | decimal(8,2), default 1 | |
| `current_price` | decimal(10,2) | Total de la línea, `quantity` ya aplicada |
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

> Al aceptar un quote: sincroniza `spa_booking_services` + `spa_booking_items` (Grupos) + `total_estimated_price`, cambia booking a `work_order`, crea ledger de anticipo.

---

### `quote_items`
Líneas de servicio **o artículo** dentro de un presupuesto (Grupos).

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `quote_id` | FK → `quotes` | |
| `service_id` | FK → `services` nullable | Exactamente uno de `service_id`/`item_id` (CHECK constraint) |
| `item_id` | FK → `items` nullable | |
| `group_id` | FK → `groups` nullable, nullOnDelete | De qué Grupo vino esta línea, si vino de uno — trazabilidad, no vuelve la línea rígida (sigue siendo editable/borrable individualmente) |
| `quantity` | decimal(8,2), default 1 | |
| `operator_id` | FK → `operators` nullable | Especialista asignado (solo aplica a líneas de servicio) |
| `is_external` | boolean | Proveedor externo |
| `price_override` | decimal(10,2) nullable | Precio unitario específico para este ítem |
| `notes` | text nullable | |
| `timestamps` | | |

> `QuoteItem::lineTotal()` = `quantity × (price_override ?? precio vigente del servicio/artículo)`. `QuoteItem::name()` = `service->name ?? item->name`.

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

**⚠️ Huérfanas (ver NT-020):** `executed_services`/`executed_service_items` y `App\Domain\Execution\Services\ExecutedServiceService::convertFromBooking()` existen pero ningún flujo real las llena — 0 filas en producción pese a haber citas `completed`. El historial real de qué servicio recibió cada mascota y cuándo vive en `spa_bookings` (`status = 'completed'`) + `spa_booking_services`. No construir features nuevas sobre estas tablas sin antes verificar que tienen datos.

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
| `status` | enum | `scheduled`, `cancelled`, `fulfilled` — **no existe `'active'`** (ver NT-039: dos consultas reales del dashboard/Agenda filtraban por ese valor inexistente y nunca encontraban nada, corregido a `scheduled` + rango de fecha) |
| `timestamps` | | |

> Al crear/editar, puede asignar recurso (jaula) vía `resource_allocations`. Cancelar libera la jaula.

**Módulo Hotel activable (BL-059, 16/07/2026):** alcanzable solo si `SystemSettings` → sección `hotel`, campo `hotel_module_enabled` está prendido (**default `true`**, ya en uso real). Apagado: rutas `hotel-reservations.*` responden 404 (`EnsureHotelModuleEnabled` middleware), desaparece del dashboard (KPI "Huéspedes en Hotel" y acceso rápido) y del selector "¿qué tipo de servicio?" al crear una cita nueva, y deja de sumarse a la Agenda unificada (`SpaBookingController::index()`/`buildCalendarRange()`) — Spa no se ve afectado. A diferencia de Clínica/Tienda, Hotel no tiene sección propia de navegación: está fusionado dentro del mismo calendario compartido de Agenda que usa Spa.

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

## Comunicaciones

### `whatsapp_templates`
Mensajes predefinidos con variables, reutilizables desde la bandeja diaria (BL-024 Fase 1) y desde Recurrencias (BL-029). Desde BL-040 también se envían por correo, no solo WhatsApp — el nombre de la tabla quedó desactualizado a propósito (no se renombró, para no ampliar el alcance del cambio).

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `name` | string | |
| `subject` | string nullable | Asunto usado solo al enviar por correo (BL-040); WhatsApp lo ignora |
| `body` | text | Placeholders dependen de `context` — ver `App\Support\WhatsApp\TemplateResolver::availableVariables()`. Mismo cuerpo para ambos canales |
| `context` | string default `cita` | `cita` (bandeja diaria, placeholders `{cliente}`,`{mascota}`,`{servicio}`,`{fecha}`,`{hora}`) o `recurrencia` (placeholders `{cliente}`,`{mascota}`,`{servicio}`,`{ultima_fecha}`,`{dias_vencido}`) |
| `is_active` | boolean default true | Solo activas aparecen en su bandeja correspondiente |
| `created_by_user_id` | FK → `users` nullable | |
| `timestamps` | | |

### `booking_messages`
Log de recordatorios (WhatsApp manual vía `wa.me`, o correo real desde BL-040) enviados desde la bandeja diaria. Solo cubre `spa_bookings` — Hotel maneja sus mensajes con su propia lógica (unidades de negocio distintas, decisión explícita).

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `spa_booking_id` | FK → `spa_bookings` cascadeOnDelete | |
| `whatsapp_template_id` | FK → `whatsapp_templates` nullOnDelete | Nulo si la plantilla se borra; conserva el log |
| `channel` | string default `whatsapp` | `whatsapp` o `email` (BL-040) |
| `phone_number` | string nullable | Número ya normalizado para wa.me (ej. `525512345678`) — solo canal `whatsapp` |
| `email_address` | string nullable | Solo canal `email` (BL-040) |
| `message_body` | text | Snapshot del mensaje ya resuelto (placeholders reemplazados) |
| `wa_link` | text nullable | URL `https://wa.me/...` generada — solo canal `whatsapp` |
| `sent_by_user_id` | FK → `users` nullOnDelete | |
| `sent_at` | datetime | Para WhatsApp marca cuándo se generó el link (no garantiza que el staff lo haya mandado); para correo marca el envío real |
| `timestamps` | | |

### `recurrence_messages`
Log de recordatorios (WhatsApp manual o correo real desde BL-040) enviados desde la pantalla **Recurrencias** (BL-029) — mascotas cuyo servicio periódico (`services.recurrence_days`) ya se cumplió desde su última ejecución. La "última ejecución" se calcula de `spa_bookings.scheduled_at` (`status = 'completed'`) vía `spa_booking_services.service_id` para servicios normales, **o** de `MAX(pet_vaccinations.applied_at)` para servicios `type='vaccine'` (BL-048, ver nota en `services`) — **no** de `executed_services`/`executed_service_items` — esas tablas están huérfanas, ningún flujo real las llena (ver NT-020). Mismo patrón que `booking_messages` pero sin `spa_booking_id` (no hay cita asociada, es proactivo).

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `pet_id` | FK → `pets` cascadeOnDelete | |
| `service_id` | FK → `services` cascadeOnDelete | |
| `whatsapp_template_id` | FK → `whatsapp_templates` nullOnDelete | |
| `channel` | string default `whatsapp` | `whatsapp` o `email` (BL-040) |
| `phone_number` | string nullable | Normalizado para wa.me — solo canal `whatsapp` |
| `email_address` | string nullable | Solo canal `email` (BL-040) |
| `message_body` | text | Snapshot ya resuelto |
| `wa_link` | text nullable | Solo canal `whatsapp` |
| `sent_by_user_id` | FK → `users` nullOnDelete | |
| `sent_at` | datetime | Se usa para marcar "ya enviado hoy" en la UI y evitar doble envío el mismo día; no suprime el recordatorio en días siguientes si la mascota sigue sin recibir el servicio |
| `timestamps` | | |

**Nota técnica:** El teléfono se guarda en `phones.number` sin lada país (10 dígitos MX asumidos). `App\Support\WhatsApp\PhoneNormalizer::toWhatsAppNumber()` prefija `52` solo si son exactamente 10 dígitos; cualquier otra longitud se considera no reconocible como MX y la fila queda deshabilitada en la bandeja (no se envía). No hay automatización de envío — el operador confirma manualmente en WhatsApp Web/App vía `wa.me`. El barrido de Recurrencias se calcula bajo demanda al abrir la pantalla (no hay cron/scheduler de Laravel configurado en la OPi).

### `service_ai_chats` / `service_ai_messages`
Chatbot de IA (Claude) para el widget de chat del sitio público de WordPress (BL-042). **Informativo, sin conexión a CRM** — no se relaciona con `clients`/`leads`, es tráfico anónimo identificado solo por `session_uuid` (generado por el widget, persistido en `localStorage` del visitante). Responde preguntas sobre el catálogo de servicios (`services`, vía `ServiceCatalogService::getActiveServices()`) y siempre termina invitando a un botón fijo de CTA (texto/URL configurables en `SystemSettings`, sección `ai_assistant` — hoy apunta a WhatsApp/contacto porque no existe todavía una app de clientes real, ver BL-012).

| Tabla | Columna | Tipo | Notas |
|---|---|---|---|
| `service_ai_chats` | `id` | bigint PK | |
| | `session_uuid` | string unique | Generado por el widget, `localStorage` |
| | `message_count` | unsignedInteger default 0 | Se incrementa por turno completo (usuario+asistente); tope de 30 corta la sesión (`AssistantChatController::MAX_MESSAGES_PER_SESSION`) |
| | `timestamps` | | |
| `service_ai_messages` | `id` | bigint PK | |
| | `service_ai_chat_id` | FK → `service_ai_chats` cascadeOnDelete | |
| | `role` | enum `user`/`assistant` | |
| | `content` | text | |
| | `created_at` | timestamp | Sin `updated_at` (`$timestamps = false` en el modelo) |

**Nota técnica:** Primera integración HTTP saliente del proyecto (`Illuminate\Support\Facades\Http` no se usaba en ningún lado antes) y primer uso de un LLM. El CORS de las rutas `/api/assistant/*` **no** usa `config/cors.php` — se resuelve con middleware global a medida (`App\Http\Middleware\HandleAssistantCors`, registrado con `prepend()` en `bootstrap/app.php` para ejecutar antes que el `HandleCors` por defecto de Laravel) porque el origen permitido vive en `SystemSettings` (base de datos), y un archivo de config estático no puede leerla de forma segura al cargarse antes de que Laravel registre los proveedores de BD/caché. El token del widget (`ai_assistant_site_token`) es ofuscación, no seguridad real — cualquiera puede leerlo desde el JS público; la defensa real es CORS + `throttle:15,1`.

---

## Mapa y cobertura espacial

Pantalla `AX-MAPZN` (`mapa-zonas.index`, `App\Http\Controllers\MapaZonasController`, nav en "Operación"). Visualiza en un mapa (Leaflet + OpenStreetMap, sin API key) sucursales, direcciones de clientes, mascotas y vehículos de reparto.

**Decisión de alcance (07/07/2026):** esta es una versión mínima exploratoria — el usuario pidió expresamente "necesito esos campos... para navegar y pensar en ideas", no la arquitectura final. `pets.lat`/`lng` y la tabla `vehicles` son columnas/tabla directas simples, **no** la entidad espacial genérica polimórfica (ligada muchos-a-muchos a personas/objetos/documentos) que el usuario describió y quedó documentada como BL-031 en `docs/tecnico/BACKLOG.md` y `docs/architecture/IDEAS_FUTURO.md`. No dar por hecho que este modelo es el definitivo — puede reemplazarse o convivir con esa entidad genérica el día que se decida construirla.

Sucursales (`branches.lat/lng`) y direcciones de clientes (`addresses.lat/lng`) ya existían y solo se leen aquí (de solo lectura en esta pantalla — se siguen editando desde sus propias pantallas). Mascotas y vehículos son de escritura desde el mapa mismo (clic en el mapa → ubicar mascota existente o crear vehículo).

**Advertencia de cobertura geográfica (BL-043, 14/07/2026):** `App\Support\Geo\CoverageChecker` (+ `DistanceCalculator`, fórmula de Haversine) usa estas mismas coordenadas para avisar — **no bloquear** — cuando se agenda una cita para una mascota fuera del radio configurado (`coverage_radius_km`, sección `coverage` de `SystemSettings`, default 15 km) respecto a la sucursal activa más cercana. Prioriza `pets.lat/lng`; si la mascota no tiene coordenadas, cae a la primera dirección del cliente con `lat/lng`. Si no hay coordenadas de ningún lado, o ninguna sucursal activa tiene `lat/lng`, no se evalúa nada (sin falso positivo). Conectado en los dos únicos puntos de creación de citas SPA (`SpaBookingController::storeForPet()` — web, flash `session('warning')` — y `Api\BookingController::store()` — móvil, campo `coverage_warning` en la respuesta JSON, mismo patrón que `created_by_user_id` de BL-039).

### `vehicles`
Vehículos de reparto — modelo deliberadamente mínimo, sin placa/capacidad/conductor (no definidos todavía, ver BL-031).

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `name` | string | |
| `lat` | decimal(10,8) nullable | |
| `lng` | decimal(11,8) nullable | |
| `notes` | text nullable | |
| `is_active` | boolean default true | |
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

**Horario de operación (sección `clinical`):** `booking_opening_time` / `booking_closing_time` (default `09:00`/`19:00`, formato `HH:MM`, un solo horario fijo para todos los días — no varía por día de la semana). Consumido vía `App\Support\SystemSettings\BusinessHours::isWithin()` para validar que las citas SPA se agenden dentro de horario, tanto en backoffice web (`SpaBookingController`) como en la API móvil (`Api\BookingController`) y expuesto a la app móvil vía `GET /api/settings/booking`.

**Traslape de horario por operador:** `App\Domain\Planning\Services\OperatorAvailabilityChecker::hasConflict()` — consulta directa sobre `spa_bookings.operator_id` + `scheduled_at` + `duration_minutes` (excluye `cancelled`/`no_show`). Alcance intencional: solo SPA, `operator_id` no existe en `hotel_reservations`. A partir de esta validación, elegir operador es **obligatorio** para agendar una cita SPA (antes era opcional/inexistente en el flujo web).

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
