---
title: Referencia técnica del manual mob
tags: [manual, mob, tecnico]
verificado_contra: estetican@05c124f (main, 12/10/2026)
fuente: /opt/www/estetican — mob_apps/operador/ (React) + apps/backoffice-laravel/ (API)
---

# Referencia técnica del manual mob

Respaldo técnico de las notas de `mob/`. Ahí el manual va en lenguaje de piso; aquí está el **porqué en el código** de cada paso, los permisos reales y lo que no existe o funciona distinto.

**Convenciones**
- Componentes: `mob_apps/operador/src/…`.
- PHP: `apps/backoffice-laravel/…`.
- Rutas API: `routes/api.php`, todas bajo `ApiAuthenticate` salvo `/login`.
- Las banderas `can_*` que usa el móvil salen de `User::toApiArray()` (`app/Models/User.php`, l. ~211-225).

Reemplaza a la nota anterior «Flujo de recepción y agendado (mov)», cuyo contenido técnico quedó repartido aquí.

## Mapa de pantallas

| Nota | Ruta del móvil | Componente | `screenTag` |
|---|---|---|---|
| 01 | (sin sesión) / menú | `LoginScreen.tsx`, `CheckinWidget.tsx` en `App.tsx` → `MenuDrawer`, `LockScreen.tsx` | — |
| 02 | `/agenda`, `/mascotas`, `/buscar` | `admin/GlobalAgenda.tsx`, `admin/PetSearch.tsx`, `admin/UniversalSearch.tsx` | `MobAgGbl`, `MobPetSrch`, `MobBuscar` |
| 03 | `/clientes/seleccionar`, `/clientes/nuevo`, `/mascotas/nuevo` | `admin/ClientSearch.tsx`, `admin/ClientDetail.tsx` (`NewClientForm`), `admin/PetDetail.tsx` (`NewPetForm`) | `MobCliNew`, `MobPetNew` |
| 04-07 | `/mascotas/:id/cita/nueva` | `admin/MobCitaNueva.tsx`, `admin/ServicePickerSheet.tsx`, `admin/DraggableSlotGrid.tsx` | `MobCitaNueva` |
| 07-11 | `/citas/:id` | `admin/MobCitaDet.tsx` | `MobCitaDet` |
| 09 | (hoja) | `WhatsAppMessageSheet.tsx` | — |
| 10 | `/citas/:id/cobro` | `admin/MobCobro.tsx` | `MobCobro` |
| 12 | `/configuracion/disponibilidad` | `admin/MobUnavailability.tsx` (entrada desde `admin/MobUserConfig.tsx` y desde la hoja «Cita» de `GlobalAgenda`) | — |

---

## 01 Inicio del día

**Login**
- `LoginScreen.tsx` → `AuthContext.login()` → `POST /api/login` (`AuthController::login`, `throttle:login`, definido en `AppServiceProvider` l. ~97).
- Mensajes: `Credenciales incorrectas` (401), `Usuario inactivo` (403). Sin red, el cliente muestra «No se pudo conectar con el servidor».
- El token se guarda en `localStorage.api_token`.

**Check-in** (`CheckinWidget.tsx` → `doCheckin`)
- Rutas:
  - `GET /api/checkin/status`, `POST /api/checkin`, `POST /api/checkout` (`CheckinController`, l. 14/36/86): solo autenticación, sin `permission:`;
  - `GET /api/branches` (`OperatorController::branches`): `permission:ver sucursales`. Devuelve **todas** las sucursales, sin filtrar por activas ni por asignación.
- `checkin()`: con un check-in abierto en otra sucursal, lo cierra con `auto_checkout = true` y una `transgression_note`. En la misma sucursal responde `already`.
- El check-in solo se lee en dos lugares:
  - `OperatorController::index`: panel de equipo;
  - `ItemController`: stock de artículos por sucursal.

**Sucursal de una cita (no sale del check-in)**
- `app/Support/Branches/BranchResolver.php` → `forNewBooking()` (l. 27), en este orden: `users.branch_id` del usuario que agenda → la sucursal del operador, si tiene exactamente una → la única sucursal activa → `null`.
- La aplica `app/Observers/SpaBookingObserver.php` → `creating()` (l. 37).

**Bloqueo**
- «Bloquear ahora» → `AppLockContext`. El desbloqueo valida la contraseña con `POST /api/me/verify-password`, o con WebAuthn (`lib/webauthnLock.ts`).

## 02 Buscar mascota
- **Entrada:** `GlobalAgenda.tsx` → `startNewCita()` (l. 436) guarda la fecha vista y el operador filtrado (`setCitaPresetDate` / `setCitaPresetOperator`, `navState.ts`) y navega a `/mascotas`. La hoja «Nueva cita / Bloquear mi horario» solo sale si `user.operator_role`. El botón «Cita» exige `can_view_pets`.
- **Lista de mascotas:** `PetSearch.tsx` → `fetchPets` (debounce de 300 ms) → `GET /api/pets?search=` (`PetController::index`, l. 184, `permission:ver mascotas`).
  - `TokenSearch` busca en `name`, `breed` y `species` de la mascota, y en `client.first_name`, `apellido_paterno`, `apellido_materno` y `email`. **No** busca en el teléfono.
  - Solo mascotas `Pet::visible()`.
  - `goToPet` salta a `/mascotas/:id/cita/nueva` si hay fecha preseleccionada (`peekCitaPresetDate`).
- **Búsqueda universal:** `ScreenHeader.tsx` (lupa, `aria-label="Buscar"`) → `UniversalSearch.tsx` → `GET /api/search` (`SearchController`, `permission:ver mascotas|ver clientes|ver agenda`).
  - `searchPets` sí busca en `client.phones.number`.
  - Lleva a la ficha (`/mascotas/:id`); desde ahí se agenda con el ícono `event_add`, título «Agregar cita» (`PetDetail.tsx` l. 515).

## 03 Alta de mascota y dueño
- `PetSearch` «Nueva» → `/clientes/seleccionar` con `state.returnTo = '/mascotas/nuevo'`.
- `PetDetail.tsx` (`isNew`, l. 453): sin `selectedClient`, muestra «Primero selecciona al dueño».
- **Cliente:** `ClientSearch.tsx` (`GET /api/clients?search=`, `permission:ver clientes`; busca también en `phones.number`) → «Nuevo» → `ClientDetail.tsx` → `NewClientForm` (l. 115) → `save` (l. 130) → `POST /api/clients` (`ClientController::store`, l. 80, `permission:crear clientes`).
  - Validación: `first_name` requerido; `phones` (array, mínimo 1) o `phone` legado; cada número pasa por `ValidPhoneNumber::fromSettings`; `type` ∈ mobile/home/work/other (por defecto `mobile`); orden en `sort_order`.
  - **Sin control de duplicados.**
  - Formato de teléfono: `GET /api/settings/phone-format`.
- **Mascota:** `NewPetForm` (l. 109) → `POST /api/pets` (`PetController::store`, l. 102, `permission:crear mascotas`).
  - Requeridos: `client_id` (exists) y `name`.
  - Valida `sex` ∈ male/female, `size` ∈ small/medium/large/giant y `weight_kg` entre 0.1 y 200.
  - El peso se registra en `pet_weights`; la foto se guarda como perfil.
  - Al guardar navega a `/mascotas/{id}`, no a la cita.

## 04 Servicio y precio
- `MobCitaNueva.tsx`, carga inicial (l. 186-206):
  - `GET /api/pets/{id}` (`ver mascotas`);
  - `GET /api/services` (`ServiceController::index`, `ver catalogo_servicios`: activos, con `price`, `duration_minutes` y `recurrence_days`);
  - `GET /api/operators` (`ver operadores`);
  - `GET /api/resources` y `GET /api/settings/booking` (`ver agenda`);
  - por cada servicio, `GET /api/services/{id}/operators` (l. 216, `ver agenda`) → `OperatorServiceResolver::operatorsFor`: plantilla del rol, más concesiones directas, menos revocaciones; o todos si `open_to_all_operators`.
- `addLine` (l. 418): precio y duración del catálogo, ambos editables. Duración de 5 a 480 min en pasos de 5.
- `defaultOperatorFor` (l. 410): operador preseleccionado o el propio usuario si está calificado; si no, el único calificado.
- **Precio:** `BookingController::store` usa `services.*.price` y, si no viene, `services.price`. `total_estimated_price` = suma de las líneas. **No hay precio por tamaño, raza ni peso.**
- **Calificación** revalidada al guardar con `OperatorServiceResolver::canPerform` → «{Operador} no está calificado para {Servicio}.»

## 05 Agendar día y hora
**Rango de la cuadrícula**
- `GET /api/settings/booking` → `SettingController::booking` (l. 15) → `BusinessHours::for(BranchResolver::forNewBooking(null))`: la sucursal del usuario que agenda; por defecto 09:00-19:00.
- El móvil **ignora** `operating_days` de esa respuesta.

**Qué marca la cuadrícula** (`loadOccupied`, l. 226)
- `GET /api/agenda?date&operator_id` (`AgendaController::index`, l. 35, `ver agenda`), con ventanas `busy` por línea.
- `GET /api/agenda/unavailabilities` (l. 166).
- `slotWouldConflict` (l. 380): cierre del negocio y choque con otra línea de la cita con el mismo operador.
- Los usuarios **sin** `agenda.ver_todas` reciben solo su propia agenda: `visibleTo()` y `activeOperatorId()` ignoran el `operator_id` pedido.

**Guardar:** `save` (l. 465) → `POST /api/bookings` → `BookingController::store` (l. 218, `permission:crear agenda`).
1. Requeridos: `pet_id`, `operator_id` (el de la 1ª línea) y `scheduled_at` (`Y-m-d H:i:s`).
   Opcionales: `services[]` (`id`, `price`, `operator_id`, `duration_minutes` 5-480, `offset_minutes` 0-960), `notes` ≤ 1000, `resource_id`, `override_availability` y los campos `RepeatInput`.
2. `BusinessHours::isWithin()` sobre la sucursal de `forNewBooking(operator_id)`. Mensajes de `rejectionFor()`: «El negocio no abre los {día}s.» / «La hora elegida está fuera del horario operativo (…).» Solo se compara la hora de **inicio**.
3. Calificación por línea (`canPerform`) y traslape entre líneas de la misma cita con el mismo operador.
4. `OperatorAvailabilityChecker::validateSequentialAssignments`, por tramo de cada línea:
   - `hasConflict` ignora `cancelled`/`no_show` y las líneas canceladas o no realizadas;
   - `isOutsideWorkingHours` usa `operator_weekly_schedules` y es opcional: sin filas, no restringe;
   - `hasTimeOff` usa `operator_unavailabilities`.
   Las líneas sin operador no se validan.
5. **Forzar:** `canOverrideSchedule()` (`agenda.forzar_horario` o super admin) solo salta `isOutsideWorkingHours`. El móvil ofrece «Agendar de todas formas» si el mensaje coincide con `SCHEDULE_OVERRIDE_MESSAGE` y `can_override_schedule` está activo.
6. Cita y líneas se crean en `DB::transaction`. La jaula se asigna fuera de la transacción, con `assignResourceWindowToSource`; si choca → `resource_warning`.
7. Avisos: `CoverageChecker::checkPet` → `coverage_warning`; `VaccinationEligibilityChecker::check` → `vaccination_warning`.
8. Al terminar: `navigate('/agenda?date=…')`, aunque el texto diga «Regresando a la ficha…».

## 06 Cita recurrente
- **Permiso:** `agenda.series_recurrentes` (`can_create_series`); revalidado en `store`, con 403.
- **Parámetros** (`app/Domain/Planning/Series/RepeatInput.php`):
  - `repeat_mode` 7/15/30/custom → `RecurrenceRule::everyNDays`;
  - `monthly` → `monthlyWeekday`. El móvil no manda `repeat_week_of_month`: se deduce con `weekOfMonth()`, y la 5ª ocurrencia cuenta como «último»;
  - `repeat_until` 6m / 1y / date.
- **Fechas:** `RecurrenceRule::occurrences()` (l. 89), con tope `MAX_OCCURRENCES = 60`.
- **Creación:** `BookingSeriesService::create()` → `spa_booking_series` (`STATUS_PENDING_REVIEW`) y una `SpaBooking` por fecha, todo en una transacción. La 1ª fecha ya pasó las validaciones de `store`.
- **Validación de cada fecha** (`rejectReason`, en orden):
  1. fecha pasada;
  2. `NonWorkingDay::covering` (general o de la sucursal);
  3. `BusinessHours::rejectionFor`;
  4. choque con la propia serie;
  5. la mascota encimada consigo misma;
  6. `validateSequentialAssignments` **sin** forzar;
  7. jaula.
- **Recorrido** (`locate`): mismo día más tarde, en pasos de `STEP_MINUTES = 30`; luego `SEARCH_DAYS = 14` días, primero a la hora original y luego desde la apertura, saltando días cerrados e inhábiles. Si no encuentra lugar, la fecha va a `skipped_occurrences`. Las citas movidas guardan `series_original_at` y `series_move_reason`.
- **Fijar / descartar:**
  - `POST /api/bookings/{id}/fijar` (`BookingController::pin`) y `/descartar` (`discard`), los dos con `permission:editar agenda`, usando `SeriesLifecycleService`.
  - `discard` solo funciona si la cita está `scheduled`, sin presupuesto y sin pago.
  - `series:cierre-diario` (23:50) borra las no fijadas que ya vencieron.

## 07 Operador y jaula
- **Jaula al agendar:** `<select>` «Sin jaula» en `MobCitaNueva`. Solo aparece si `GET /api/resources` trae filas y la línea activa tiene operador. Se pinta con `GET /api/resources/{id}/availability` (`day_busy`).
- **Reasignar una línea:** `MobCitaDet.tsx` → `saveAssign` (l. ~461) → `PATCH /api/bookings/{id}/services/{line}` (`BookingController::assignServiceProfessional`, l. 691, `permission:editar agenda`) → `ServiceLineActionService::apply`. Campos: `operator_id`, `is_external`, `external_cost`, `current_price`.
- **Editar la cita** (modo edición): `PATCH /api/bookings/{id}` con `operator_id` («Operador (opcional)», que permite «Sin asignar»).

## 08 Durante el servicio
- **Desde la Agenda:** `GlobalAgenda.tsx` → `startCita()` (l. 398) → `PATCH /api/bookings/{id}` con `{status: 'work_order'}`, para toda la cita. `update()` además pone `started_at` a todas las líneas pendientes y genera el folio de la orden (`assignOrderFolio`). El botón «Iniciar» se muestra si `status = scheduled` y `|ahora − hora| ≤ grace_minutes` (`booking_grace_minutes`, por defecto 15).
- **Por línea** (`MobCitaDet.tsx`): `startSingleLine`, `completeSingleLine`, `realizadaSingleLine`, `reactivateLine` y `confirmVoidLine` → `PATCH …/services/{line}` con `mark_started`, `mark_completed`, `mark_realizada`, `mark_reactivate`, `mark_not_performed` (+ `not_performed_reason`) o `mark_cancelled` (+ `cancellation_reason`).
  - `ServiceLineActionService` promueve la cita a `work_order` al arrancar la 1ª línea (l. ~135).
  - Mensajes (l. 35-48): «No se puede iniciar ni terminar un servicio no realizado o cancelado. Reactívalo primero.», «Asigna un operador a este servicio antes de iniciarlo.», «El servicio ya está completado.», «No se puede completar un servicio que no ha iniciado.»
- **Acciones de toda la cita:** `NEXT_STATUSES` (l. ~108). `acciones` (l. 308) **quita «Iniciar cita»** cuando la cita tiene líneas de servicio.
- **Notas de proceso:** `GET/POST/PATCH /api/bookings/{id}/process-notes` (`BookingProcessNoteController`, `ver agenda` / `editar agenda`).

## 09 Avisar por WhatsApp
- `WhatsAppMessageSheet.tsx`. Se muestra si `booking.client.phone` y `can_view_clients`.
- Rutas, todas con `permission:ver clientes`:
  - `GET /api/whatsapp-templates?booking_id=` (`ClientWhatsAppController::templates`): plantillas activas por `context`;
  - `GET /api/clients/{id}/whatsapp-link` (`link`): arma el enlace `wa.me` con el mensaje resuelto;
  - `GET /api/clients/{id}/whatsapp-live-pets`.
- El cliente abre el enlace con `window.open`. **No hay envío automático**: el operador envía desde WhatsApp.
- **Teléfono usado:** `PhoneNormalizer::bestPhoneFor()` toma el primer `type = mobile` y, si no hay, el **primer teléfono de cualquier tipo**.
- **Plantillas en producción** (12/10/2026, `whatsapp_templates`): «Recordatorio cita 01» (cita), «👋🏻 ya estoy listo 🐶» (cita), «Dscto 5» (general), «Test Recurrencia» y «Recordatorio de servicio proximo» (recurrencia).

## 10 Cobrar y entregar
- `MobCobro.tsx`.
- Carga:
  - `GET /api/bookings/{id}`;
  - `GET /api/bookings/{id}/payments` (`ver agenda`);
  - `GET /api/payment-methods`: closure en `routes/api.php` l. ~199, **solo autenticación**, sin `permission:`;
  - las notas de proceso.
- **Cobro:** `confirm` → `POST /api/bookings/{id}/payments` (`PaymentController::store`, `permission:cobros.registrar`) con `amount` (≥ 0.01), `payment_method_code`, `reference`, `notes` y `mark_completed: true`.
  - Rechaza las citas canceladas: «No se puede cobrar una cita cancelada.»
  - Con `mark_completed`, la cita pasa a `completed` **aunque el pago sea parcial**.
  - El destino caja/banco sale del método de pago.
  - **No requiere sesión de caja abierta** (comentario en `MobCobro.tsx`).
- **Pendiente de cobro:** `PATCH /api/bookings/{id}` con `{status: 'completed'}`, sin pago.
- **Precio por línea / no cobrar:** `PATCH …/services/{line}` (`current_price`, `mark_not_performed` con el motivo «No cobrado en el cierre»).
- **Métodos activos en producción** (12/10/2026): Efectivo, Tarjeta de débito, Tarjeta de crédito, Transferencia SPEI.

## 11 Mover, cancelar o reagendar
**Modo edición** (`MobCitaDet.tsx` → `saveEdit`) → `PATCH /api/bookings/{id}` (`BookingController::update`, `permission:editar agenda`)
- `scheduled_at` solo se manda si la cita está `scheduled`.
- Mensajes: «Solo se puede reprogramar una cita mientras está Programada.», «Debes asignar un operador para poder reprogramar esta cita.», más las mismas validaciones de horario y disponibilidad que en el alta.
- Una cita `completed`/`cancelled` solo admite notas: «No se puede editar una cita …».
- **La cuadrícula de edición es más pobre que la de alta:**
  - `loadOccupied` usa `GET /api/agenda?date` **sin `operator_id`** y marca como ocupado solo el **slot de inicio** de **cualquier** cita del día (de cualquier operador);
  - las horas son fijas, 09:00-19:00 (`START_H`/`END_H`), no el horario de la sucursal;
  - no muestra ausencias.
- La edición manda `services` como lista de ids, sin precio, operador ni offset por línea.

**Reagendar desde pendientes**
- `GlobalAgenda.tsx`: modal de reagendar (l. ~85) → `PATCH` con `scheduled_at`.
- Lista de vencidas: `GET /api/agenda/vencidas` (`AgendaController::vencidas`).

**Cancelar / no presentado**
- `changeStatus` con `cancelled` + `cancellation_reason`, o con `no_show` / `unfulfillable`.

## 12 Horarios no disponibles
- **Rutas** (`UnavailabilityController`): `GET/POST/DELETE /api/me/unavailabilities`, con los permisos `ver disponibilidad_propia`, `crear disponibilidad_propia` y `eliminar disponibilidad_propia`.
  - `store` exige `starts_at`, `ends_at` (posterior a `starts_at`) y `reason` opcional.
  - Sin operador ligado: «Tu usuario no tiene un operador vinculado.» (403).
  - Antes de crear, el cliente pide la contraseña con `POST /api/me/verify-password`.
- **En la Agenda:** `GET /api/agenda/unavailabilities` → banner «Operadores no disponibles hoy» (vista Día) y `blockedDates` (Semana/Mes, `AgendaCalendarGrid.tsx`).
- **Días inhábiles:** `NonWorkingDay` (`app/Models/NonWorkingDay.php`; general si `branch_id` es nulo). Se capturan en el backoffice web (`dias-inhabiles`, `NonWorkingDayController`). **Ninguna pantalla del móvil los lee.**

---

## Permisos que usa el ciclo (nombres reales)

| Acción | Permiso |
|---|---|
| Ver / buscar mascotas | `ver mascotas` |
| Alta de mascota | `crear mascotas` |
| Ver / buscar clientes, WhatsApp | `ver clientes` |
| Alta de cliente | `crear clientes` |
| Lista de sucursales para el check-in | `ver sucursales` |
| Lista de operadores | `ver operadores` |
| Catálogo de servicios | `ver catalogo_servicios` |
| Ver agenda, horarios, jaulas, operadores por servicio | `ver agenda` |
| Ver la agenda de todos | `agenda.ver_todas` |
| Crear cita | `crear agenda` |
| Editar, iniciar, terminar, asignar, fijar o descartar cita | `editar agenda` |
| Crear serie recurrente | `agenda.series_recurrentes` |
| Forzar «no labora en el horario» | `agenda.forzar_horario` |
| Registrar cobro | `cobros.registrar` |
| Mis ausencias | `ver/crear/eliminar disponibilidad_propia` |
| Check-in, métodos de pago | solo autenticación |

---

## Diferencias y huecos

### Pasos del ciclo que no existen en el móvil
- **Entregar la mascota:** no hay acción. La cita se cierra al cobrar o al dejarla «Pendiente de cobro».
- **Cambiar la jaula después de agendar:** ni el detalle ni el modo edición tocan `resource_id`, aunque `BookingController::update` sí lo acepta.
- **Vista previa de una serie:** solo existe en la web (`pets.bookings.series-preview`).
- **Ver los festivos y días inhábiles:** ninguna pantalla los lee.
- **Envío automático de WhatsApp:** solo se arma el enlace; el envío es manual.

### Funciona distinto a lo esperado
1. **El check-in no decide la sucursal de la cita** (ver 01). El comentario de `CheckinWidget.tsx` que dice que también se usa en Caja quedó desactualizado: solo se monta en `App.tsx`.
2. **El orden real es dueño → mascota → servicio.** No se puede crear una mascota sin `client_id`, y Nueva cita cuelga de `/mascotas/:id`.
3. **No se exige celular:** basta un teléfono de cualquier tipo, sin control de duplicados. `bestPhoneFor` cae a un fijo si no hay móvil.
4. **El precio es plano por servicio**, editable a mano. Nada depende del tamaño ni del peso.
5. **La cita suelta no valida festivos, fechas pasadas ni mascota encimada.** Las series sí validan las tres cosas. El servidor revisa solo la hora de **inicio** contra el horario de la sucursal; el fin lo frena solo el cliente.
6. **La cuadrícula de alta ignora los días cerrados y el horario semanal del operador;** los dos se rechazan hasta guardar.
7. **Un usuario sin `agenda.ver_todas` ve su propia ocupación** aunque agende para otro operador.
8. **La cuadrícula de edición** (11) ignora operador, ausencias y horario de la sucursal, y marca solo los inicios de cita. Puede mostrar libre lo que el servidor rechaza, o bloquear lo que sí cabe.
9. **Un pago parcial cierra la cita como `completed`**; el saldo queda pendiente en la ficha.
10. **«Iniciar cita» con checklist** (`confirmStartChecklist`, «¿Qué servicio(s) inician ahora?») es **inalcanzable**: `acciones` quita `work_order` cuando hay líneas, que es justo cuando se usaría el checklist. En la práctica se inicia por línea, o desde la tarjeta de la Agenda (toda la cita).
11. **Iniciar desde la Agenda** marca toda la cita como `work_order` y pone `started_at` a todas las líneas pendientes (`BookingController::update`, l. ~669). Para **terminarlas** una por una hay que entrar al detalle; si no, se cierran en bloque con «Completar y cobrar».
12. **`GET /api/payment-methods` y las rutas de check-in** no llevan `permission:`; solo autenticación.
13. **Falla real para no-admins, confirmada leyendo el código y no reproducida** (corregido el 12/10/2026, ver `referencia-tecnica-app.md` §20): `MobCitaNueva` pide `GET /api/operators` (`ver operadores`) y `GET /api/services` sin revisar `r.ok`.
    - El `fetch` global manda `Accept: application/json`, así que un 403 llega como objeto y `operators.find(...)` en el resumen truena al elegir la hora.
    - `ver operadores` **no se puede asignar desde la interfaz de Usuarios**: afecta a **todo** usuario no-admin con `crear agenda`.
    - Hoy no se dispara solo porque el personal real es admin.
    - `MobCitaDet` ya está protegido.
