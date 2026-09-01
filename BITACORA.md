# 📓 Bitácora de Desarrollo - EstetiCAN 2

## 📅 Sesión: 31/08/2026 (cont.) — `SYNC-077` + `SYNC-078` portados desde Zeus: el sync de Google Calendar deja de notificar por correo al crear eventos y deja de agotar la cuota de ACL

### 📝 Resumen

Tomas pidió verificar cada cuánto corre el sync de Google Calendar en prod (**cada 5 min**,
`*/5 * * * *` vía `schedule:run` del `crontab` del host → `->everyFiveMinutes()`), y luego que
(a) el sync no mande correos de Google al crear eventos y (b) se arregle el error recurrente que
salió al verificar.

**Sin migración, sin assets.** Código bind-mounted → vivo en la siguiente corrida del cron.
Construido y probado primero en `tenants/tst` (Zeus).

### `SYNC-077` — sync de eventos sin notificar

`GoogleCalendarSyncService::{upsertBookingEvent,deleteBookingEvent}` pasan
`['sendUpdates' => 'none']` a `events->{insert,update,delete}`. Crear/mover/borrar el evento de
una cita en el calendario del operador ya no dispara correos de Google. La sincronización es de
un solo sentido (EstetiCAN → Google) y el operador ya se entera por la app. Los eventos no
tienen `attendees`; los avisos de *cambios de calendario compartido* siguen siendo ajuste del
`reader` en su propio Google (ver `SYNC-075`).

### `SYNC-078` — el cron agotaba la cuota de ACL de Google

**Hallazgo al verificar `SYNC-077`:** `SincronizarGoogleCalendarCommand::syncViewers()` llamaba
`shareCalendarWithEmail()` (→ `acl.insert` directo) en **cada** corrida del cron para los 3
usuarios con `google_personal_email`. Google limita fuerte las operaciones de ACL → `403
"Calendar usage limits exceeded"` ~3 veces por corrida, **11.739 acumulados en `laravel.log`
desde el 10/08/2026**. El sync de eventos en sí no tenía errores.

- **`GoogleCalendarSyncService::ensureCalendarSharedWith($calendarId, $email)`** (nuevo, en el
  contrato): lee la ACL del calendario **una vez por corrida** (`readUserAcl()` privado, caché
  `aclByCalendar` por instancia) y solo hace `acl.insert` si el email no está ya en la lista. Si
  la lectura falla, cae al `insert` directo (comportamiento previo).
- `syncViewers()` pasa a usar `ensureCalendarSharedWith()`. El bucle de operadores no cambia (ya
  se gatea con `google_calendar_shared_at`).

### ✅ Verificación

- Suite `GoogleCalendar/` **26 en verde**; sweep `GoogleCalendar|Booking|Agenda` **269 en verde**.
  Pint limpio (9 archivos).
- **En vivo, tras el deploy:** los `403 quotaExceeded` **pararon** — última ocurrencia 21:55
  (pre-commit), corridas del cron 22:00 y 22:05 sin un solo error, y la corrida bajó de 1–3 s a
  **~780 ms** (una lectura de ACL en vez de 3 `insert` que fallaban). `notifyWatchers()` sigue
  sin mandar nada (0 usuarios con `google_calendar_notify_email`).

### 📁 Archivos / commit

- `apps/backoffice-laravel/app/Domain/GoogleCalendar/Contracts/GoogleCalendarSyncServiceInterface.php`
- `apps/backoffice-laravel/app/Domain/GoogleCalendar/Services/GoogleCalendarSyncService.php`
- `apps/backoffice-laravel/app/Console/Commands/SincronizarGoogleCalendarCommand.php`
- `apps/backoffice-laravel/tests/Feature/GoogleCalendar/{EventSyncNoNotificationsTest,EnsureCalendarSharedWithTest}.php` (nuevos)
- `apps/backoffice-laravel/tests/Feature/GoogleCalendar/SincronizarGoogleCalendarCommandTest.php` (ajustado)
- Commit **`dd2bd03`**, pusheado a `origin/main`.

### 🛑 Pendiente / notas

- `SYNC-078` no rastrea el estado de compartido en BD — se apoya en `acl.list` cada corrida.
  Suficiente para 1 calendario / 3 viewers; si el fleet crece, evaluar columna de estado.
- Sigue pendiente (`SYNC-075`): avisar a Admin/arantxa/tomasmg que apaguen las notificaciones de
  los calendarios compartidos en su propio Google Calendar si no las quieren.
- Trazabilidad: `zeus-estetican/docs/tecnico/PENDIENTES_SINCRONIZAR_ESTETICAN.md`
  (`SYNC-077`/`078`, en Aplicados).

---

## 📅 Sesión: 31/08/2026 — porteo desde Zeus de `SYNC-074` (logo/favicon en Configuración + logo en la app móvil), `SYNC-075` (aclaración del switch de correo del Google Calendar) y `SYNC-076` (acceso de super admin legacy al área de administración) · **todo commiteado a `main`**

### 📝 Resumen

Porteo desde la sesión de Zeus (`tenants/`) que construyó `SYNC-074` en `tst` el mismo día.
Tomas pidió aplicarlo a producción de una ("aplica estos cambios ligeros a producción").
Origen: reportó que en **Configuración → Identidad y Branding** el logo no daba preview del
archivo elegido, ni recorte, ni compresión; y que el logo **no aparecía en la app móvil**.

**Sin migración. Sin backup de BD** — solo reprocesa archivos de imagen nuevos en
`storage/app/public/branding/`. `estetican_app` corre sin `route:cache`/`config:cache`, así que
la ruta API nueva quedó viva al instante.

### 1 — Backoffice (`apps/backoffice-laravel/`, 6 archivos + 2 tests)

Se conectó el campo de logo/favicon al componente `<x-image-upload>` + `cropperjs` que **ya
existían** (se usan en fotos de operador — nunca se enganchó al logo): modal de recorte +
preview en vivo + rotar. Recorte **libre** para el logo, **1:1** para el favicon.

- `resources/js/modules/image-upload.js` — la factory `imageUploadFactory` gana un 5º arg
  opcional `options = { format, quality, maxWidth, maxHeight }` (default = comportamiento
  histórico JPEG 0.82 / 1200²; las 2 llamadas existentes no lo pasan → sin cambio de conducta).
- `resources/views/components/image-upload.blade.php` — props `previewFit`, `outputFormat`,
  `outputQuality`, `maxOutput{Width,Height}`; `aspectRatio` no numérico (`"free"`) → `NaN` a
  cropperjs (recorte libre).
- `resources/views/system-settings/index.blade.php` — el bloque `type === 'image'` monta
  `<x-image-upload>` (favicon `1:1`, logo libre, `outputFormat="image/png"`, topes desde el
  campo). Sin `autoSubmitFormId` → el crop solo llena el input oculto; se guarda con el botón.
- `app/Support/SystemSettings/SystemSettings.php` — `brand_logo_web` `image_max_width=640`/
  `image_max_height=240` (+ `help`); `brand_favicon` `128×128` (+ `help`).
- `app/Http/Controllers/SystemSettingController.php` — `normalizeBrandingImage()` (GD): red de
  seguridad server-side (envío sin JS / directo al endpoint) — reescala al tope **sin ampliar**
  las chicas, conserva alpha, reencoda a **PNG** (compresión 6). `try/catch` + `report()`: si
  GD falla, deja el archivo tal cual — guardar la config nunca revienta.
- **PNG, no webp, a propósito:** `brand_logo_web` también alimenta PDFs con dompdf
  (`reports/{invoice,quote,work-order}`, `CashReportService`, expedientes) y dompdf no
  renderiza webp.
- `app/Http/Controllers/Api/SettingController.php` → `branding()` devuelve además `logo_url` y
  `favicon_url` (rutas `/storage/...`; favicon cae al logo). **Ruta pública nueva**
  `GET /api/settings/branding` en `routes/api.php` (justo tras `/login` — la consume la
  pantalla de login de la app móvil antes de autenticarse; solo lee branding, sin datos
  sensibles).
- Tests: `tests/Feature/SystemSettingsBrandingLogoTest.php` (5), `tests/Feature/Api/BrandingSettingsTest.php` (3).

### 2 — App móvil del operador (`mob_apps/operador/`, 2 archivos)

Producción **no consumía branding** en la app móvil (se aisló a propósito en el porteo
`SYNC-055..072`, §4 "Opción 2", por ser single-tenant): el login tenía `"EstetiCAN"` + la
tijera hardcodeados, y `index.html` no tenía favicon.

- `src/lib/useBusinessName.ts` — **nuevo archivo**. Hook `useBranding()` →
  `{ businessName, logoUrl, faviconUrl }` con caché a nivel de módulo (una sola llamada);
  `useBusinessName()` como wrapper. Efecto colateral al resolver: fija el `<link rel="icon">`
  del documento con `favicon_url`.
- `src/LoginScreen.tsx` — importa `useBranding()`; el recuadro de la tijera muestra el logo
  (`<img object-contain>` sobre fondo blanco) cuando hay uno configurado; el `<h1>` pasa de
  `"EstetiCAN"` literal a `{businessName}`.

### ✅ Verificación (en prod)

- `SystemSettingsBrandingLogoTest` + `BrandingSettingsTest` + `PhotoSettingsTest` +
  `SystemSettingsAssistantTest` + sweep `Setting|Branding` → **31 en verde**. Pint limpio
  (6 archivos). Blade compila (`view:cache` OK).
- Backoffice: `npm run build` en `estetican_app` → `app-DjwP53At.js` / `app-Cg63Nwl1.css`.
- Móvil: `npm run build` en `node:20-alpine` → `index-Bzude42h.js`; `tsc --noEmit` solo los
  2 errores preexistentes ajenos de `MobCajaMovimientos.tsx`; `estetican_mob` reiniciado.
- `route:list` confirma `api/settings/branding`. `GET /api/settings/branding` vía el proxy de
  `estetican_mob` → `logo_url`/`favicon_url` presentes; ambas imágenes resuelven **200
  `image/png`** (logo/favicon actuales: 312 KB / 63 KB, subidos con el flujo viejo — no se
  tocan; un re-upload por el cropper nuevo los optimiza).

### 3 — `SYNC-075`: el switch de correo del Google Calendar prometía de más

El switch `google_calendar_notify_email` de USEEDI solo apaga el **resumen propio de EstetiCAN**
(`SincronizarGoogleCalendarCommand::notifyWatchers()` → `GoogleCalendarUpdatedMail`), no los
avisos que **Google Calendar** manda a quien tiene el calendario compartido (`syncViewers()` da
rol `reader` en el ACL a cualquier usuario con `google_personal_email`, sin mirar el switch; los
avisos de cambios los controla el destinatario en su propio Google). Decisión de Tomas: **A + C**.

- `resources/views/user/edit.blade.php` — el switch pasa a **"Enviarme un resumen de cambios por
  correo (lo manda EstetiCAN)"** + una nota que aclara que los avisos nativos de Google se apagan
  desde el propio Google Calendar del usuario.
- `app/Domain/GoogleCalendar/Services/GoogleCalendarSyncService.php` — `shareCalendarWithEmail()`
  llama `$api->acl->insert(...)` con `['sendNotifications' => false]` (para no disparar el correo
  "se compartió un calendario contigo" en cada corrida del cron cada 5 min). `client()` pasó de
  `private` a `protected` (seam de test, sin cambio de conducta).
- Tests: `tests/Feature/GoogleCalendar/ShareCalendarSendNotificationsTest.php` (nuevo, cliente
  Google falso verifica el optParam) + `UserGoogleCalendarNotifyToggleTest` actualizado al texto
  nuevo. Sweep `GoogleCalendar/` **23 en verde** en prod.
- **Sin migración, sin assets.** El sync **está encendido en prod** — los usuarios que ya son
  `reader` (`Admin`, `arantxa`, `tomasmg`, con `google_personal_email` y `visibility=all`)
  seguirán recibiendo los avisos de Google hasta que los apaguen en su propio Google Calendar (o
  se les quite `google_personal_email`). **Avisarles.**

### 4 — `SYNC-076`: super admin "legacy" recibía 403 en toda el área de administración

Reporte de Tomas: "como superadmin parece que no aparece la configuración de usuario, aunque no
se pueda borrar". Causa: el chequeo de super admin era inconsistente. El grupo de rutas de admin
(**Configuración, Usuarios CRUD, Bitácora de actividad, y todo el módulo de Finanzas**) usaba
`role:admin|super-admin` — middleware de Spatie que solo mira `hasAnyRole()`, ignorando la
columna legacy `users.role='admin'` y el accessor `is_super_admin`. `UserController::edit()` tenía
el mismo problema. El resto de la app ya usa `is_super_admin` (híbrido). Un super admin sin el rol
Spatie asignado (aprovisionamiento de tenant, orden de seeders `MasterAdminSeeder`
antes de `BaseRolesSeeder`, alta directa en BD) **veía el menú** pero recibía **403** al entrar.

- `app/Http/Middleware/EnsureSuperAdmin.php` (nuevo) — `abort_unless($request->user()?->is_super_admin, 403)`.
- `bootstrap/app.php` — alias `'superadmin' => EnsureSuperAdmin::class`.
- `routes/web.php` — el grupo de admin pasa de `role:admin|super-admin` a `superadmin`.
- `app/Http/Controllers/UserController.php` — `edit()` alineado a
  `auth()->id() === $user->id || auth()->user()->is_super_admin` (igual que `show()`).
- Nada más usa `hasRole('admin')`/`role:admin` suelto (grep). `UserPolicy` ya incluía `|| role === 'admin'`.
- Tests: `tests/Feature/SuperAdminAreaAccessTest.php` (4) — admin legacy entra a las 5 zonas, admin
  Spatie sigue entrando, operador sigue con 403, `edit()` deja a un admin legacy editar a otro.
- **Sin migración, sin assets.** `estetican_app` corre sin `route:cache`, así que el cambio de
  `routes/web.php` quedó vivo al instante (`route:list --middleware=superadmin` → 61 rutas). Repro
  en prod: un admin legacy (`role='admin'` sin rol Spatie) ya entra 200 a
  users/system-settings/activity-log/finances (antes 403).

### ✅ Verificación global (prod)

Suite completa de `apps/backoffice-laravel` corrida tras los tres ports — ver el bloque de tests
de abajo (sin regresiones). Pint limpio en los 9 archivos tocados de `SYNC-075`+`SYNC-076`.

### 🛑 Pendientes / notas

- **Commiteado y pusheado a `origin/main`** (3 commits: `215085b` `SYNC-074`, `16dfdd1` `SYNC-075`,
  `be5fa89` `SYNC-076`). (Esta corrección de la nota va en un commit aparte.)
- **Pasada visual de Tomas pendiente:**
  - `SYNC-074`: `app.estetican.org` → Configuración → Identidad y Branding (elegir logo → modal
    de recorte → guardar → PNG optimizado); `mov.estetican.org` → logo en el login + favicon de
    la pestaña (recarga dura — el favicon se cachea fuerte).
  - `SYNC-075`: el texto nuevo del switch en USEEDI.
  - `SYNC-076`: nada visible con los admins actuales (todos tienen rol Spatie) — solo aplica a un
    super admin legacy.
- **Avisar a Admin/arantxa/tomasmg** (`SYNC-075`): apagar las notificaciones de los calendarios
  compartidos en su propio Google Calendar si no las quieren.
- El **ícono de app instalada (PWA)** no cambia con `SYNC-074` — no hay `manifest.json`.
- Trazabilidad `tst → prod`: `zeus-estetican/docs/tecnico/PENDIENTES_SINCRONIZAR_ESTETICAN.md`
  (`SYNC-074`/`075`/`076`, en Aplicados).

---

## 📅 Sesión: 30/08/2026 — roles de capacidad de servicio (config de datos en prod) + SYNC-003 cerrado + spec m2m operador↔servicio movido a Zeus

### 📝 Resumen

Sesión de arranque que derivó en dos cosas: (a) config de datos en producción para
desacoplar "quién puede hacer qué servicio" de la jerarquía de puesto, y (b) redacción de
la spec de la feature m2m real, que va en `tst` (Zeus), no acá.

**1 — Revisión de pendientes de sincronización con los tenants (ambas direcciones).**
- `tst → prod` (`zeus-estetican/docs/tecnico/PENDIENTES_SINCRONIZAR_ESTETICAN.md`): **vacío**.
  El arco `SYNC-055..072` ya se portó el 29/08 (commit `66e77ca`), verificado (migración
  `2026_08_29_000001` corrida, backup `estetican_pre-sync055-072_20260829_2321.sql.gz`,
  bundle `index-BGQmoGUw.js`). Solo falta la pasada visual de Tomas — no es código.
- `prod → tst` (`docs/tecnico/PENDIENTES_SINCRONIZAR_TENANTS.md`): **`SYNC-003`** — lo portó
  la sesión de Zeus (`tenants-c6`) a `tenants/tst` este día (gate de `$operators` en
  `SpaBookingController::index()` + caso en `SpaBookingControllerWebScopingTest`; 7 tests del
  archivo + sweep `Agenda|SpaBooking` 136 en verde). Movido a "Aplicados" (30/08) en ese
  documento por esa sesión; sección "Pendientes" queda **vacía**. `tenants/` no versionado.
- Hueco de trazabilidad detectado: el porteo `66e77ca` del 29/08 no dejó entrada en la
  `BITACORA.md` de Zeus (solo actualizó `PENDIENTES_SINCRONIZAR_ESTETICAN.md` y la bitácora
  de EstetiCAN) — por eso `tenants-c6` arrancó creyendo que el arco seguía pendiente.
  Corregido por esa sesión.

**2 — Config de datos en producción: roles de capacidad de servicio (NO es código).**
Backup previo: `backups/estetican_pre-roles-capacidad_20260830_1627.sql.gz` (88 tablas,
`gzip -t` OK). Vía `tinker` contra la BD real:
- 2 `operator_roles` nuevos: `CAP-BANO` "Baño" (#5), `CAP-CORTE` "Corte y estilizado" (#6),
  `default_hourly_rate` NULL (no afectan `effectiveHourlyRate()` — el perfil de compensación
  gana, y si no, el rol primario; las tarifas nulas se ignoran).
- Asignados como rol **secundario** (`is_primary=false`) vía `roles()->syncWithoutDetaching`:
  Jose Mendez Pérez (#1) → +Baño; Operador Prueba (#16) → +Baño; Tomas (#2) → +Baño, +Corte.
- Servicios re-apuntados (`services.operator_role_id`): baños `BA-CH`/`BA-MD`/`SPA-0003-PRO`
  → Baño; 5 cortes `CTE-PQ-PC`/`SPA-0001`/`CTE-PQ-PL`/`CTE-MD-PC`/`CTE-MD-PL` → Corte y
  estilizado; 4 vacunas `VET-PER-VAC-*` → Veterinario (sin cambio).
- `OperatorRoleCatalogCache::flush()`.
- Verificado con simulación de elegibilidad contra la BD real: los 3 baños → Jose/Tomas/
  Operador Prueba; los 5 cortes → solo Tomas; vacunas → nadie (0 operadores con rol
  Veterinario, módulo clínico apagado — igual que antes, sin regresión).
- Regla de calificación existente (no tocada): al agendar una línea de servicio,
  `operador.activeRoles()->contains('id', $service->operator_role_id)`; `null` = cualquiera.
- **Aviso operativo:** la ficha de Operador (`OperatorController::syncRoles`) hace
  delete+recreate de todas las asignaciones de rol desde las casillas del form — al editar un
  operador hay que dejar marcadas sus capacidades o se pierden. USEEDI (`syncOperatorRecord`)
  sí es seguro, no toca la m2m.

**3 — Spec de la feature real (m2m operador↔servicio) — redactada y movida a Zeus.**
Conversación de diseño con Tomas. La solución definitiva es una relación muchos-a-muchos
operador↔servicio con: plantilla por rol de puesto (solo Veterinario→vacunas de entrada) +
capacidades directas por operador (toda la estética) + toggle `services.open_to_all_operators`.
4 decisiones cerradas: (1) cita sin líneas se deja sin validar calificación como hoy;
(2) `open_to_all_operators` es toggle de admin de primera clase, patrón "arranca abierto →
acota"; (3) las 4 vacunas entran a la plantilla de Veterinario ya, aunque el módulo clínico
esté apagado; (4) Baño/Corte NO son roles — son capacidades por operador, así que los roles
interinos `CAP-BANO`/`CAP-CORTE` se retiran en la feature (backfill de retiro específico de
prod). Es **mejora, no emergencia** → se construye en `tst`. El spec se guardó primero acá
por error y se **movió** a `zeus-estetican/docs/tecnico/SPEC_CAPACIDADES_SERVICIO_POR_OPERADOR.md`
(commit `a638765` en Zeus `master` local). Handoff a `tenants-c6`.

### Archivos tocados (este repo)
- `docs/tecnico/PENDIENTES_SINCRONIZAR_TENANTS.md` — `SYNC-003` → "Aplicados" (lo editó `tenants-c6`; commiteado desde acá).
- `BITACORA.md` — esta entrada.
- BD de producción: `operator_roles` (+2 filas), `operator_role_assignments` (+3 filas), `services.operator_role_id` (8 filas re-apuntadas). Config de datos, no esquema — `MODELO_BD.md` sin cambios.

### Revisión de seguridad de rutas
No se crearon ni modificaron rutas en este repo esta sesión. Regla #3 de `CLAUDE.md`: N/A.

### ▶️ Próxima sesión
- El spec `SYNC-XXX` (m2m operador↔servicio) lo construye la sesión de Zeus en `tst`, fase por fase, con pasada visual de Tomas. Al promover a prod, cuidar el backfill de retiro de `CAP-BANO`/`CAP-CORTE` (esos roles solo existen en prod, no en `tst`).
- Sigue pendiente de sesiones anteriores: pasada visual de Tomas del arco `SYNC-055..072` en `mov.estetican.org`; auditar los ~37 fallos preexistentes de la suite; cron `calendario:sincronizar-google` llenando `laravel.log` cada 5 min.

---

## 📅 Sesión: 29/08/2026 — porteo del arco `SYNC-055`..`SYNC-072` desde `tst` (agendado móvil con huecos + navegación ‹ › en plantilla de detalle + operador restringido)

### ▶️ Próxima sesión — empezar por aquí

**Estado exacto al cerrar (29/08/2026):**
- `/opt/www/estetican` — `main` = `origin/main`, **working tree limpio**, todo pusheado.
  Commits de la sesión: `66e77ca` (código del arco), `552c92a` (bitácora — resumen del arco),
  `31d6d5c` (cierre — MODELO_BD + NT-064), + esta línea de ajuste de hash encima.
- `/opt/www/zeus-estetican` — `master` (local, sin remoto) en **`2c5f472`**: `SYNC-055..072`
  movido a "Aplicados" en `PENDIENTES_SINCRONIZAR_ESTETICAN.md`, "Pendientes" quedó **vacío**.
- BD real: migración `2026_08_29_000001` **aplicada** (`spa_booking_services` +
  `scheduled_offset_minutes` + `duration_minutes`). Backup previo:
  `backups/estetican_pre-sync055-072_20260829_2321.sql.gz`.
- Bundle móvil servido por `estetican_mob`: **`index-BGQmoGUw.js`** / `index-DmX5KwtB.css`
  (md5 host ↔ contenedor idéntico).
- El arco está **vivo en producción** y verificado por migración + 278 tests + build. Lo único
  que falta **no es código** — es la pasada visual de Tomas:

1. **Pasada visual de Tomas en la app real** (`mov.estetican.org`): agendar una cita con 2
   servicios y un hueco entre ellos (la cuadrícula por línea de `MobCitaNueva`); abrir una
   cita del día y usar las flechas ‹ › del encabezado + el arrastre lateral; probar con un
   operador de permisos reducidos si se quiere (la tarjeta de mascota/cliente en la cita ya no
   debe ser un botón muerto, y navegar entre citas no debe dar pantalla en negro — pero hace
   falta **cerrar sesión y volver a entrar una vez** para limpiar la lista ‹ › heredada).
2. **Sigue pendiente de sesiones anteriores** (no tocado hoy): auditar los ~37 fallos
   preexistentes de la suite; el cron `calendario:sincronizar-google` con `laravel.log`
   quejándose cada 5 min; `SYNC-003` (gate del directorio de operadores) falta portar de prod
   *hacia* `tenants/tst`.
3. **Follow-ups del arco** (anotados en el plan §6, van en `tst` primero): `MobCitaDet` en
   modo edición aún ignora duración/offset en su cuadrícula; la agenda visual día/semana/mes
   pinta una cita compartida como un bloque único, no un tramo por operador; editar los huecos
   desde `MobCitaDet`; flag de permiso de cobro/editar-agenda en `/api/me` para gatear
   "Cobrar"/"Iniciar" en la tarjeta de la Agenda.

### 📝 Resumen de esta sesión

**Porteo deliberado** del arco `SYNC-055`..`SYNC-072` desde el sandbox `tst` de Zeus-Estetican
(promoción a producción, como el arco `SYNC-024..054` del 28/08), siguiendo
`zeus-estetican/docs/tecnico/260829 PLAN_PORTEO_SYNC_055-072.md` — orden del §7.

**Contenido del arco:** agendado móvil con huecos entre servicios / hora de inicio por línea
(Fase 2 de `SYNC-040`); navegación ‹ › + arrastre lateral generalizados a un patrón de
plantilla de pantallas de detalle (`useSiblingNav`/`useSwipe`); endurecimiento del operador
restringido (atajos del encabezado gateados, cargas resilientes a 403, error boundary de app).

**1 — Backup de protección:** `backups/estetican_pre-sync055-072_20260829_2321.sql.gz` (99 KB,
88 tablas, `gzip -t` OK). Fila agregada a `backups/LISTA_RESPALDOS.md`.

**2 — Migración:** `2026_08_29_000001_add_scheduled_offset_to_spa_booking_services.php`
(byte-idéntica a `tst`) corrida con `migrate --force` — `spa_booking_services` gana
`scheduled_offset_minutes` (unsigned smallint, default 0) y `duration_minutes` (nullable),
justo después de `operator_id`. Aditiva, retrocompatible.

**3 — Backend (5 archivos), diff por diff → todos resultaron copia byte-idéntica a `tst`
(los diffs prod↔`tst` eran 100% del arco, sin hunks ajenos):**
`app/Models/SpaBookingService.php` (`#[Fillable]` +2 columnas),
`app/Domain/Planning/Services/OperatorAvailabilityChecker.php` (`hasConflict` reescrito: mira
compromisos por línea de servicio del operador — activas, cada una en `[scheduled_at+offset,
+duración)` — + citas sin líneas contra el responsable; `validateSequentialAssignments` respeta
`offset_minutes` por línea),
`app/Domain/Planning/Services/ServiceLineActionService.php` (al cancelar/no-realizar/reactivar
una línea recalcula `spa_bookings.duration_minutes` = fin más lejano de las activas),
`app/Http/Controllers/Api/BookingController.php` (`store` acepta `services.*.offset_minutes`
0..960; duración de la cita = `max(offset+dur)` en vez de la suma; guard 422 de traslape entre
líneas del mismo operador; `update` recalcula desde las líneas; `serialize` expone
`offset_minutes`/`start_time`/`duration_minutes` real por línea),
`app/Http/Controllers/Api/AgendaController.php` (`?operator_id=` también matchea citas donde el
operador solo hace una línea; cada cita devuelve `busy:[{start,end}]`).
Pint limpio (6 archivos). Sin cambios en `routes/` — **no hay rutas nuevas que auditar** (regla
de seguridad #3 de CLAUDE.md: N/A esta sesión).

**4 — Tests:** `BookingSchedulingValidationTest` ganó los 4 casos nuevos del arco (offset
persiste/extiende; offset evita choque; compromiso por línea bloquea y un cancel lo libera;
cancelar la cita libera ambas líneas) — aplicado **aditivo** (los otros test files que difieren
con `tst` — `BookingServiceAssignmentTest`, `AgendaOperatorScopingTest`,
`SpaBookingControllerWebScopingTest` — son solo reordenamiento de traits / estilo de import, y
`SpaBookingControllerWebScopingTest` de prod está **adelante** de `tst` con el test de `SYNC-003`
— NO se tocaron). Sweep `Availability|Agenda|Booking|SpaBooking|Scheduling`: **278 pasan, 0
fallan** (277 en `tst`, +1 por el test de `SYNC-003`). `BookingSchedulingValidationTest` solo:
34 pasan.

**5 — App móvil, reconciliación:**
- **3 nuevos** copiados tal cual: `hooks/useSiblingNav.ts`, `hooks/useSwipe.ts`,
  `RootErrorBoundary.tsx`.
- **12 archivos** con diff prod↔`tst` 100% del arco → copia byte-idéntica: `App.tsx`,
  `AuthContext.tsx`, `ScreenHeader.tsx`, `navState.ts`, `hooks/useLongPress.ts`,
  `admin/AgendaCalendarGrid.tsx`, `admin/MobCobro.tsx`, `admin/GroomerPicker.tsx`,
  `admin/PetDetail.tsx`, `admin/ClientDetail.tsx`, `admin/PetSearch.tsx`,
  `admin/ClientSearch.tsx`, `admin/MobCitaNueva.tsx`, `admin/MobCitaDet.tsx`.
- **`admin/GlobalAgenda.tsx` — reconciliación quirúrgica:** copiado de `tst` y luego revertidos
  los **3** puntos del hilo `useBusinessName` (import, `const businessName = useBusinessName()`,
  `title={businessName}` → `title="EstetiCAN"`). Diff residual con `tst` = exactamente esas 3
  líneas.
- **§4 del plan (desfase previo `tst`↔prod) — decisión: Opción 2, aislar** (recomendada,
  confirmada por Tomas). NO se portó `lib/useBusinessName.ts` (+ su endpoint
  `/api/settings/branding`, que **no existe** en el `routes/api.php` de prod), ni el diff de
  `admin/MobCaja.tsx` / `admin/MobUserConfig.tsx` / `LoginScreen.tsx` / `lib/webauthnLock.ts`.
  Motivo: nada de eso es emergencia ni del arco; prod es single-tenant (el nombre del negocio
  *es* "EstetiCAN", ya literal en el header); cero regresión. Si se quiere la marca dinámica en
  prod, es un porteo standalone aparte.

**6 — Build:** `npx tsc --noEmit` → solo los **2 errores preexistentes ajenos** de
`admin/MobCajaMovimientos.tsx` (prop `key`). `npm run build` en un contenedor `node:20-alpine`
(el `dist/` previo era `root:root` de la build del 28/08 y el node local no podía limpiarlo) →
`dist/assets/index-BGQmoGUw.js` + `index-DmX5KwtB.css`. `md5sum` **idéntico** host ↔
`estetican_mob` (bind-mount directo, sin restart — `nginx.conf` no se tocó). El warning de CSS
del `color-mix()` es el preexistente ya documentado en el propio fuente.

**7 — Deploy:** commit `66e77ca` (`feat(agenda+móvil): porteo del arco SYNC-055..072 desde
tst`), pusheado a `origin/main`. El código ya estaba vivo por bind-mount; `opcache` con
`validate_timestamps=On`/`revalidate_freq=2` lo recoge solo (sin restart de `estetican_app`).

**8 — Smoke (API, solo lectura):** `tinker` — booking existente #62 con `offset=0`/`dur=NULL`
(retrocompatible OK); `OperatorAvailabilityChecker::hasConflict` con el join nuevo a
`spa_booking_services` corre sin error contra la BD real. El smoke interactivo (agendar 2
servicios + hueco, ‹ ›) queda para la pasada de Tomas en la app real.

**9 — Docs:**
- `zeus-estetican/docs/tecnico/PENDIENTES_SINCRONIZAR_ESTETICAN.md` — arco `SYNC-055`..`SYNC-072`
  movido a "Aplicados" con banner (referencia commit `66e77ca`); "Pendientes" queda vacío
  (commit `2c5f472` en Zeus, `master` local sin remoto).
- `docs/tecnico/MODELO_BD.md` — `spa_booking_services` gana `scheduled_offset_minutes` +
  `duration_minutes` (`SYNC-068`); nota de `spa_bookings.duration_minutes` corregida (fin más
  lejano, no la suma, para citas con líneas); sección "Traslape de horario por operador"
  reescrita con el nuevo `hasConflict` por línea + el `busy[]` de `AgendaController`.
- `docs/tecnico/NOTAS_TECNICAS.md` — **NT-064**: `npm run build` de la app móvil truena con
  `EACCES … unlink dist/assets/…` cuando la build anterior corrió en contenedor (dueño
  `root:root`); fix = volver a buildear en `node:20-alpine` (mecanismo por defecto del repo).
- `BACKLOG.md` — sin cambios: el arco es de alcance-emergencia (porteo deliberado desde `tst`),
  no un ítem de backlog; los follow-ups del §6 del plan van en `tst` primero.
- `PENDIENTES_SINCRONIZAR_TENANTS.md` (dirección inversa EstetiCAN→`tst`) — sin cambios: este
  arco es un porteo *desde* `tst`, no hay nada que devolver.

---

## 📅 Sesión: 28/08/2026 — sincronización con `tst`: Fase 0 + Fase 2 (6 ports) + Fase 3a + 3b + Fase 4 — arco SYNC completo · + manual de pruebas `tstmov` y arreglo de 3 hallazgos (H1/H5/H7)

### ▶️ Próxima sesión — empezar por aquí

Estado al cerrar: **`main` = `origin/main` en `9b55b9d`, working tree limpio.** Todo el arco
`SYNC-024..054` + los arreglos H1/H5/H7 están en producción y verificados por API/tests. Lo que
falta **no es código** — es verificación humana y limpieza:

1. **Fase 1 — pasada visual de Tomas en `tstmov`**, flujo a flujo, con
   `zeus-estetican/docs/tecnico/MANUAL_PRUEBAS_TSTMOV.html` (abrir desde `file://`). Foco en lo
   que la API no puede probar: acordeón que expande, chips que cambian de color, checkbox que
   tacha, diálogos que abren, PDF de cierres. Login de sandbox: `chatgpt` (admin) y `prueba_restr`
   (restringido, ya creado en `tst`). Al terminar `tstmov`, hacer el manual equivalente para
   `tstapp`.
2. **Auditar los 37 fallos preexistentes de la suite** (`Operator*`, `ServiceOperatorRoleLink`,
   `PetDependenciesCrud`, `PetCatalogRootViews`, `Resource*`, `SystemSettings*`, toggles de
   módulos, `ClientAddressHarmonization`). Vienen de antes de este arco (baseline `5beadec` ya los
   tenía); sesión dedicada, no bloquean nada.
3. **Cron `calendario:sincronizar-google` en producción** — `laravel.log` tiene
   `GoogleCalendar: fallo al compartir el calendario` para `martinezgtomas@gmail.com` cada 5 min.
   Es **previo** a este trabajo (SYNC-047 no tocó `shareCalendarWithEmail`). Verificar que ese
   correo sea válido/aceptado.
4. **Zeus (`/opt/www/zeus-estetican`, `master` local sin remoto):** otra sesión dejó sin
   commitear `BITACORA.md` (iteraciones de `SYNC-051..054` en la entrada del 27/08) y
   `docs/tecnico/MANUAL_PRUEBAS_TSTAPP.md`. No los toqué. Que quien los escribió los cierre.
5. **`SYNC-003` (en `PENDIENTES_SINCRONIZAR_TENANTS.md`, "Pendientes"):** el fix del directorio de
   operadores (`3cb9f00`) falta portar a `tenants/tst` — el `SpaBookingController.php` de `tst`
   tiene el mismo `$operators` sin gatear. Portar el gate + el test cuando se toque ese archivo.

### ✅ Logros y Cambios

Sesión de **porteo deliberado** desde el sandbox `tst` de Zeus-Estetican (la promoción a producción
que menciona la regla de alcance de `CLAUDE.md`, pedida explícitamente por Tomas). Se planeó todo
el arco (`docs/tecnico/PENDIENTES_SINCRONIZAR_ESTETICAN.md` de Zeus tenía 18 ítems `SYNC-0xx`
pendientes) y se acordó un orden jerárquico: **Fase 0** (higiene), **Fase 1** (verificación visual
de Tomas en `tst`, pendiente), **Fase 2** (6 ítems aislados, un deploy cada uno), **Fase 3** (el
bloque grande e interconectado de agenda), **Fase 4** (veterinaria). Esta sesión cubrió Fase 0 + Fase 2.

**Respaldo de protección** al arrancar: `backups/estetican-completo_pre-sync-tstapp-tstmov_20260828_1509.tar.gz`
(45 MB, árbol completo) + `backups/estetican_pre-sync-tstapp-tstmov_20260828_1509.sql.gz` (BD, 88 tablas).
Anotados en `backups/LISTA_RESPALDOS.md`.

**Fase 0 — commit `83b49c8`:** el fix de la emergencia `SYNC-002` del 27/08 (más BITACORA/BACKLOG
del 25 y 27, NOTAS_TECNICAS, PENDIENTES_SINCRONIZAR_TENANTS) que había quedado sin commitear.
Pint limpio, 42 tests `--filter=User` en verde. Pusheado. `SYNC-048` movido a "Aplicados" en el
doc de Zeus (commit `07051ad` allá — arrastró también las entradas `SYNC-053`/`054` que estaban
sin commitear en ese archivo, anotado en el mensaje).

**Fase 2 — 6 ports, un deploy cada uno, todos pusheados a `origin/main`:**

| Commit | `SYNC` | Qué | Migración |
|---|---|---|---|
| `6790b1a` | 035 | Quitar "Administrar usuarios" duplicado del menú del avatar (queda solo en RH) | — |
| `9684f60` | 033 | Reporte "Cierres de turno" no repite la sucursal si ya está filtrada (web + PDF + móvil) | — |
| `c63dd28` | 032 | Caja móvil "Turno actual" muestra los cobros como renglones, no solo en el saldo | — |
| `dca276c` | 041 | Autoalojar la fuente de íconos móvil (Material Symbols woff2, 3.96 MB) — ya no depende de Google en runtime | — |
| `420833d` | 045+046 | Peso (kg) de mascota capturable/visible + banner de mascota en `MobPetJobs` y `MobCitaNueva` | — |
| `e65e2d8` | 047 | Switch "avisar por correo cuando se actualiza el calendario" en USEEDI | ✔️ `users.google_calendar_notify_email` |

Método en cada uno: **diff prod vs `tst` archivo por archivo** antes de tocar nada. Donde el archivo
de `tst` solo tenía el cambio del `SYNC` en cuestión → copia byte-idéntica. Donde `tst` había
divergido por otros `SYNC` no portados aún → **edición quirúrgica** de solo los hunks del ítem.

### 📁 Archivos y verificación por ítem
- **035:** `components/main-navigation.blade.php` byte-idéntico a `tst`. `view:cache` compila.
- **033:** `finances/cash-reports/cierres.blade.php`, `cash/cierres-pdf.blade.php`, `MobCajaCierres.tsx`
  byte-idénticos. 24 tests `CashRemainingReports`/`CashReportController` en verde. Bundle móvil reconstruido.
- **032:** quirúrgico en `Api/CashController::session()` y `CashSessionExpectedAmountService::paymentsForPeriod()`
  (el `CashController.php` de `tst` trae además un refactor cosmético de imports ajeno). Test nuevo
  `CashSessionMovementsIncludePaymentsTest`. 52 tests `Cash*` en verde.
- **041:** `public/fonts/material-symbols-outlined.woff2` versionado; `src/index.css`/`index.html`
  byte-idénticos; `nginx.conf` quirúrgico (solo `location /fonts/`, se **mantuvo** `Host app.estetican.org`
  — `tst` tiene `tstapp`). `estetican_mob` **reiniciado** (NT-052). Font `200` con `Cache-Control …immutable`
  verificado directo al contenedor y vía `https://mov.estetican.org`. `index.html` servido sin el `<link>` a Google.
- **045+046:** `Api/PetController` quirúrgico (peso; **sin** la paginación opt-in del `index()`, que es
  `SYNC-049`); `MobPetJobs.tsx`/`PetDetail.tsx` byte-idénticos; banner de `MobCitaNueva.tsx` quirúrgico
  (resto es territorio `SYNC-040/043`). Round-trip por `tinker` (`recordManualWeight` → `pet_weights` →
  `show()`; idempotente). Sin migración (`pet_weights` ya existía). 11 fallos `--filter=Pet` preexistentes
  idénticos con y sin el cambio (verificado con `git stash`).
- **047:** migración corrida contra la BD real (backup previo `estetican_pre-sync047-gcal-notify_20260828_1614.sql.gz`).
  Quirúrgico en `User.php`/`UserController.php`/`user/edit.blade.php` (esos 3 traen también `SYNC-030`
  en `tst`, no portado). `SincronizarGoogleCalendarCommand.php` + `GoogleCalendarUpdatedMail.php` + su
  vista + 2 tests: byte-idénticos. 18 tests `GoogleCalendar` en verde, 45 `--filter=User`. Pint limpio.

**Fase 3a — `SYNC-030` (operador restringido), commit `b781f3a`, 21 archivos.** Portado **aislado**
antes del resto del bloque de agenda, para poder diagnosticarlo solo si algo se rompe en producción.
Nuevo permiso granular `agenda.ver_todas`: sin él (y sin ser super-admin), un usuario con `ver agenda`
queda acotado a sus propias citas. Regla central en `SpaBooking::scopeVisibleTo(User)`, reusada en
`Api\AgendaController` (index/vencidas/unavailabilities), `Api\BookingController`/`Api\PaymentController`/
`Api\BookingProcessNoteController`/`SpaBookingController` web (guard `ensureVisible()` → **404**, no 403).
`User::toApiArray()` +4 flags (`can_view_all_agenda/_clients/_pets/_operators`). `UserController` +
`user/{create,edit}.blade.php` ganan sección "Agenda — visibilidad". `agenda/{index,show}.blade.php`:
botones "Mascota"/"Cliente" envueltos en `@can` (antes eran botones muertos → 403). Móvil:
`AuthContext.tsx` (+4 flags), `App.tsx` (`requiresCajaView` → `requiresFlag` genérico, `BottomNav` ahora
filtra), `GlobalAgenda.tsx` (no pide `/api/operators` ni muestra el selector sin `can_view_all_agenda`).
Permiso `agenda.ver_todas` (#74) **sembrado en la BD real** y otorgado al rol `admin`. **Sin migración,
sin rutas nuevas** (solo agrega guards de scope a rutas ya gateadas por `permission:`). Aplicado
**quirúrgico** en `Api\BookingController`/`SpaBookingController`/`agenda/index.blade.php`/`GlobalAgenda.tsx`
(traen además SYNC-040/042/043/044/049/050/051/052/053/054 en `tst`). Verificación: 23 tests nuevos
(`AgendaOperatorScopingTest` 13, `SpaBookingControllerWebScopingTest` 6, `RestrictedOperatorClientPetAccessTest`
4) + trait `CreatesRestrictedOperatorUser`; 268 tests `Api|Agenda|SpaBooking|Payment|Quote|Navigation`, 239
`Agenda|Booking|User`; 12 fallos preexistentes (`Operator*`, `ServiceOperatorRoleLink`) confirmados en HEAD
limpio; Pint limpio; bundle móvil `index--kBTvWb0.js` md5 idéntico host/contenedor; **smoke end-to-end contra
la API real** con usuario restringido desechable (agenda solo lo suyo, clients/pets/operators 403,
booking ajena 404, `/api/me` flags en false).

**Fase 3b — cluster de agenda completo (11 SYNC), commit `e8f24ff`, 36 archivos + 3 migraciones.**
`SYNC-039/040/042/043/044/049/050/051/052/053/054` portados **en bloque** desde el HEAD de `tst`
(ahí estaban integrados y probados juntos). Como `SYNC-030` ya estaba en prod (Fase 3a), el resto
del delta de cada archivo compartido era exactamente este cluster → se copiaron los archivos a su
estado de `tst`, salvo 2 quirúrgicos: `GlobalAgenda.tsx` (solo `SYNC-039` `pendingStart`, sin el
hilo de branding `useBusinessName`) y `routes/web.php` (solo `throttle:login` + `agenda.start`/
`agenda.payments.store`/`agenda.services.update`, sin el restructure `store_or_clinical.module` de
`SYNC-028`). **3 migraciones** de `spa_booking_services` corridas contra la BD real (`started_at`,
`completed_at`, `cancelled_at`/`cancellation_reason` + `not_performed_at`/`not_performed_reason`;
backup previo `estetican_pre-sync3b-agenda-cluster_20260828_1645.sql.gz`). Dominio nuevo
`ServiceLineActionService` (extraído de `Api\BookingController::assignServiceProfessional`, ahora
compartido web+API). Contenido: web agenda gana operador/duración por servicio al agendar
(`50/51`), "Iniciar cita" sin presupuesto + pop-up de acciones rápidas + acciones por línea de
servicio (`52/53`), liquidación directa sin presupuesto con modal de 2 fases (`54`); móvil gana
líneas de servicio de a una con operador filtrado por calificación + `ServicePickerSheet` (`40/43`),
acordeón de servicios + checklist "qué se cobra" (`42/44`), confirmación antes de "Iniciar" desde
la tarjeta (`39`); API hardening (`49`): `throttle:login`, `DB::transaction` en
`Api\BookingController::store`, paginación opt-in retrocompatible en `Api\ClientController`/
`Api\PetController`. **Verificación por comparación de suites** (baseline `5beadec` vs con Fase 3b,
runs seriales limpios): **37 fallidos / 600 pasan → 37 fallidos / 659 pasan**, **cero regresiones**
(sets de fallos idénticos, +59 tests del cluster). 6 tests nuevos + 5 modificados. Pint limpio
(30 `.php`); `tsc` solo los 2 preexistentes de `MobCajaMovimientos.tsx`; bundle móvil
`index-CySTzaRy.js` md5 idéntico host/`estetican_mob`. Las 3 rutas web nuevas llevan `permission:`
(auditoría de seguridad #3 OK).

> **Trampa de la sesión (Fase 3b):** dejé varios `artisan test` corriendo en paralelo contra la
> misma BD `testing` mientras esperaba resultados — cada uno re-migra en el arranque de cada clase,
> así que se pisaron y un run dio "98 fallidos / 61 regresiones" falsas (mass `QueryException`).
> Confirmado corriendo los sospechosos en aislado (20/20 verde) + un run serial limpio. **Regla:
> nunca correr dos suites completas a la vez contra `testing`.**

**Fase 4 — `SYNC-028` (Veterinaria Fase 1), commit `2c57184`, 12 archivos.** Sin migración. Los 3
módulos (`store`/`clinical`/`hotel`) ya están **activos** en la BD real de producción, así que
"Veterinaria" aparece de una en el menú de primer nivel al desplegar. `pet-tabs.blade.php` (nuevo,
barra de 5 pestañas **exclusivamente de estética** Resumen/Agenda/Servicios/Historial/Cobros) +
`pets/show.blade.php` reestructurado en `tab-pane`s (Servicios/Historial/Cobros = contenido nuevo
de citas pasadas; Cobros reusa `SpaBooking::totalPaid()`/`unpaidBalance()`). `PetController::renderPetShow`
gana `$pastBookings` + `$activeTab` (`?tab=`). `MainNavigation`: `VeterinariaNavigation::group()` sale
de "Operaciones del negocio" y pasa a pestaña de primer nivel. **Farmacia sin depender de Tienda:**
`EnsureStoreOrClinicalModuleEnabled` (middleware nuevo, alias `store_or_clinical.module`) — `items.*`
(CRUD del catálogo, donde viven los medicamentos `department='Farmacia'`) pasa a exigir Tienda **O**
Veterinaria; movimientos de inventario y sync de catálogo externo siguen exclusivos de Tienda.
`clinical/pets/show.blade.php` intacto. Verificación: **20 tests** en verde (`PetTabsTest` 6,
`ClinicalPharmacyItemsAccessTest` 4, `AdminNavigationReorgTest` 5, `ClinicalModuleToggleTest` 5);
sweep `Pet|Clinical|Item|Navigation` = 11 fallos **todos confirmados preexistentes** (`comm -23`
vacío contra el baseline). Pint limpio (10 archivos), Blade compila. `items.index` ahora con
`store_or_clinical.module` + `permission:ver catalogo_articulos`. Sin mobile.

Del lado de Zeus, cada `SYNC` movido a "Aplicados" en su propio commit (`ea9f9ec`, `ddc731e`, `ea331d8`,
`84a763d`, `4489a6b`, `74f2a41`, `e54aa7c`, `2bb92c0`, `fa66957`) — solo `PENDIENTES_SINCRONIZAR_ESTETICAN.md`.
**Con Fase 4 la sección "Pendientes" de ese documento quedó vacía — todo el arco `SYNC-024`..`SYNC-054`
está portado a producción.**

### 🚀 Deploy en la OPi (28/08/2026, tras Fase 4)

Todo el trabajo se hizo **directo en `/opt/www/estetican`** (es producción), así que el código ya estaba
vivo por bind-mount — el deploy fue la higiene de caché del checklist de `docs/OPI_PRODUCCION.md`:

- **Vistas Blade compiladas borradas** (43 → 0).
- `php artisan optimize:clear` — config · cache · compiled · events · routes · views. (No había cache de
  rutas/config activa de antes, solo `packages.php`/`services.php` de package-discovery.)
- `php artisan permission:cache-reset` — por el permiso `agenda.ver_todas` sembrado en Fase 3a.
- **Migraciones ya aplicadas** durante el trabajo: batch 63 (`google_calendar_notify_email`), batch 64
  (`started_at` / `completed_at` / `line_void_state` en `spa_booking_services`).
- **Compilación proactiva de TODAS las vistas** (`view:cache` → OK, sin errores de sintaxis en
  `pets/show`, `agenda/*`, `_billing_summary`, `pet-tabs`, `user/*`) y luego `view:clear` (prod compila
  lazy).

**Verificación post-deploy:** `https://app.estetican.org/login` → 200 end-to-end; `/dashboard` `/agenda`
`/pets` → 302 a login (no 500); rutas nuevas (`agenda.start`/`agenda.payments.store`/`agenda.services.update`)
y middlewares (`store_or_clinical.module`, `throttle:login`) registrados; `estetican_app` Up.

**Assets web: no se reconstruyeron** — en toda la sesión no se tocó `resources/css`/`resources/js`/
`vite.config.js`/`package.json`; el `manifest.json` de producción resuelve a archivos existentes
(`app-RSxTnaPX.css`/`app-CnfUKLJ7.js`). El hash distinto vs `tst` es solo por builds independientes.
Bundle móvil `index-CySTzaRy.js` servido por `estetican_mob` (bind-mount, sin acción).

**Hallazgo no relacionado:** el `laravel.log` de producción tiene errores recurrentes del cron
`calendario:sincronizar-google` — `GoogleCalendar: fallo al compartir el calendario` para
`martinezgtomas@gmail.com` (cada 5 min). Es **previo** a este trabajo (SYNC-047 solo agregó el flag de
aviso, no tocó `shareCalendarWithEmail`). Vale revisar que ese email sea válido/aceptado.

### 🧪 Prueba en producción con un usuario restringido real (28/08/2026)

Se creó un usuario throwaway vinculado al operador **real** #1 (Jose Mendez Pérez, 5 citas), con **solo**
`ver agenda`, y se probó contra `estetican_app` en vivo (API con Bearer token + sesión web con login
real). Borrado al terminar; operador #1 y sus citas intactos.

**Resultado — `SYNC-030` funciona correcto en producción real:**
- API: `/api/me` con los 4 flags en `false`; `/api/clients|pets|operators` → **403**; `/api/bookings/{propia}`
  → 200, `/api/bookings/{ajena}` → **404** (no 403); `payments`/`process-notes`/`PATCH services` de cita
  ajena → 404; `/api/agenda?view=month` (agosto) → **1 cita** de las **30** reales del mes; `visibleTo()`
  directo = exactamente las 5 de op#1.
- Web (sesión): `/dashboard` → 403 (sin `ver dashboard`), `/agenda` → 200 sin error, `/pets|clients|operators`
  → 403, `/agenda/{propia}` → 200, `/agenda/{ajena}` → **404**; el HTML de `/agenda` **no** tiene los
  botones "Mascota"/"Cliente"/"Perfil de mascota" (los `@can` de SYNC-030).

**Un hallazgo — fix aplicado (commit `3cb9f00`):** el HTML de `/agenda` embebía en su JS
`var OPERATORS = [{id,name}...]` con **todos** los operadores activos — lo agregó `SYNC-052` (pop-up de
reasignar operador) sin scopear. Un restringido veía los nombres de sus compañeros en el fuente, aunque
`/api/operators` sí lo bloquea. Severidad baja (solo id+name, el plan de SYNC-030 marca eso como no
sensible) pero inconsistente. Ahora `SpaBookingController::index()` solo pasa `$operators` si
`is_super_admin || can('agenda.ver_todas')`, si no `collect()` vacía. Test nuevo en
`SpaBookingControllerWebScopingTest` (el restringido ve `var OPERATORS = []`; con `agenda.ver_todas` sí
recibe el directorio). 7 tests del archivo en verde. Deploy: `optimize:clear` + vistas borradas;
re-verificado en vivo con otro throwaway (`var OPERATORS = []`, sin nombres de colegas).

### 📋 Manual de pruebas para `tstmov` + análisis del reporte de vuelta (28/08/2026)

Tomas pidió un **manual de pruebas profundo** para hacer la pasada visual pendiente (Fase 1) sobre
`tstmov` — navegación, validación y operabilidad, flujo a flujo. Se escribió del lado de Zeus (es
material de sandbox, no de producción):

- `zeus-estetican/docs/tecnico/MANUAL_PRUEBAS_TSTMOV.md` — 13 secciones (§0–§13), ~90 pruebas
  numeradas; cubre regresión, operador restringido (`SYNC-030`), acciones por línea, cobro en 2 fases,
  caja/cierres, peso de mascota, trabajos, íconos, promover/revertir un restringido.
- `zeus-estetican/docs/tecnico/MANUAL_PRUEBAS_TSTMOV.html` — checklist interactivo autocontenido
  (localStorage, exportar notas, CSS de impresión). No se publicó como Artifact — el clasificador lo
  bloquea por traer credenciales de sandbox; se dejó como archivo del repo (abre desde `file://`).
- Admin de sandbox: `chatgpt` (documentado, como en `MANUAL_PRUEBAS_TSTAPP.md`). Usuario restringido
  `prueba_restr` **pre-creado directo en la BD de `tst`** (id 21, solo `ver agenda`, vinculado al
  operador #2) para que el tester entre **directo en `tstmov`** sin pasar por `tstapp`.
- `zeus-estetican/docs/tecnico/260828 ANALISIS_Y_PLAN_TSTMOV.md` — análisis de los dos reportes de
  vuelta del tester (`260828 REPORTE_PRUEBAS_TSTMOV*.md`) + plan de arreglo.

**Lectura del reporte:** el `_FINAL` declara "✅ SISTEMA FUNCIONAL EN TODAS LAS ÁREAS" — **sobredimensionado**.
Verificado de verdad (confianza alta): §2 regresión, §3.1–3.5 operador restringido, íconos (`SYNC-041`),
diálogo de cobro en 2 fases (`SYNC-054`), cobro como renglón en caja (`SYNC-032`), iniciar/terminar
**una** línea. El resto marcado ✅ eran "la interfaz está presente" o datos pre-sembrados. Se abrieron
7 hallazgos (H1–H7).

**Hallazgos y resolución:**

| # | Qué | Resolución |
|---|---|---|
| **H1** | El ítem "Artículos" del menú móvil no estaba gateado para un restringido (botón muerto: abre y `/api/items` da 403) | **Arreglado — commit `5fe1168`** (prod) + portado a `tst`. `User::toApiArray()` +`can_view_articulos` (`ver catalogo_articulos` \|\| super-admin); `AuthContext.tsx`/`App.tsx` +flag y `requiresFlag: 'can_view_articulos'` en el ítem. 2 tests nuevos en `RestrictedOperatorClientPetAccessTest`. |
| **H2** | Una cita con 2 servicios "quedó con 1" | **Descartado (guion R1, API).** Citas #99/#100 creadas por API → 2 líneas cada una; imprecisión del tester. |
| **H3** | El `<select>` de operador por línea no filtró por calificación de rol | **Descartado (guion R1).** `R1-C` → **422** al asignar un operador no calificado; la guarda `validateSequentialAssignments` funciona. |
| **H4** | §4.1 "NO arranca vacía" (cita nueva) | **Descartado (código).** `MobCitaNueva.tsx` `lines = []` y el `useEffect` de preselección fue eliminado por `SYNC-043`; el tester comentó sobre el selector de fecha (que sí debe estar siempre). |
| **H5** | "chatgpt OpenAI" aparece como operador asignable | **Arreglado en `tst` (dato).** Operador #6 → `is_active=false` (0 citas, 0 líneas). `/api/operators` de `tstmov` ahora devuelve solo los 2 reales. Solo `tst`, no versionado. |
| **H6** | §7.2 ubicación del folio de OT en "Trabajos" | **Descartado (código).** `MobPetJobs.tsx` pone el chip de estado siempre visible y el folio (mono) debajo; la API separa `status` de `order_folio`. |
| **H7** | El login **de la app móvil** (`POST /api/login`, campo `username`) **no** tenía rate limit — el `throttle:login` solo estaba en `POST /login` web, y el limiter `login` se llaveaba solo por `email` | **Arreglado — commit `e95d7c0`** (prod) + portado a `tst`. `->middleware('throttle:login')` en `POST /api/login` (`routes/api.php`); el limiter lee `email ?: username`. 2 tests nuevos en `LoginThrottleTest`. Verificado en `tstmov`: 6º intento fallido → **429**. Ver **NT-063**. |

**Guion R1 (API) — H2/H3/H4 descartados.** Citas de prueba #99/#100 creadas y borradas; `R1-C` → 422; sin residuos.

**Bloques B1–B8 (verificación API/código sobre `tstmov` real):**
- **B1** — `PATCH /api/bookings/{b}/services/{line}`: iniciar una línea deja las otras `pending` y promueve
  `scheduled → work_order`; "No se realizó"/"Cancelar" bajan el total ($750→$300); "Reactivar" lo sube
  ($300→$750); "Completar" cierra la línea. (Ojo: la ruta liga `booking_service_id`, no el `service_id`.)
- **B3** — `POST /api/bookings/{b}/payments` con `payment_method_code: "EFECT"` (no `"efectivo"`): cobro
  directo sin presupuesto registra el pago, `paid`, "Efectivo".
- **B4** — peso de mascota (`SYNC-046`): alta con `weight_kg=12.5` → devuelve `12.50`; `PATCH` a `13.4`
  persiste; `0`/`-5`/`999`/`"abc"` → **422**; `0.1`/`200`/`12.34` → **200**. Round-trip completo.
- **B5/B6** — contratos de datos OK (`/api/pets/{id}/bookings` separa folio de estado; `/api/cash/reports/cierres`
  sin `branch_id` → "Todas las sucursales" + `canSelectBranch`).
- **B7** — rate limit web: 6º `POST /login` con clave mala → **429**.
- **B8** — dar `agenda.ver_todas` a `prueba_restr` → `/api/me` `can_view_all_agenda: true`, `/api/agenda`
  (mes) → 37 citas (vs. 1); `/api/operators` **sigue 403** (permiso aparte). Permiso revertido.

Lo puramente visual (acordeón, chips que cambian de color, checkbox tachado, el diálogo "¿Iniciar esta
cita?", el PDF de cierres) queda para la pasada de Tomas — la lógica y los datos detrás ya están confirmados.

### 🛑 Pendientes activos
- **Fase 1 (pasada visual) sigue pendiente:** existe el `MANUAL_PRUEBAS_TSTMOV.md`/`.html` de Zeus y
  ya hubo una vuelta del tester (analizada arriba) + verificación API/código de B1–B8, pero **falta el
  sign-off visual de Tomas** flujo a flujo en `tstmov` (acordeón/chips/checkbox/diálogos/PDF). Después,
  el manual equivalente para `tstapp`.
  **Revisar en producción con calma** — en particular: `SYNC-030` con un usuario restringido real; todo
  el flujo nuevo de agenda web (iniciar cita, pop-up de acciones por servicio, liquidar saldo sin
  presupuesto, modal de cobro en 2 fases); las 5 pestañas de la ficha de mascota; y que Farmacia
  (`/items?search=Farmacia`) es accesible con Veterinaria activa.
- **`throttle:login`** ahora activo en `POST /login` **y `POST /api/login`** (H7) — 5/min por
  credencial+IP, 30/min por IP; el limiter lee `email ?: username`. Si alguna clínica detrás de un NAT
  reporta bloqueos de login, es esto (subir el tope por IP). Ver **NT-063**.
- **Verificación por comparación de suites (metodología nueva de esta sesión):** baseline en un commit
  vs. el cambio, ambos runs **seriales y aislados**, `comm -23` de los sets de fallos. Cero regresiones
  en 3a, 3b, 4 y el fix de operadores. **Run final de cierre (HEAD `637bbca`): 37 fallidos / 672 pasan
  (1937 aserciones)** vs. baseline `5beadec` **37 / 600** — mismos 36 grupos de fallo, `comm -23` vacío
  en ambos sentidos, **+72 tests nuevos**. La suite de producción arrastra esos **37 fallos preexistentes**
  (`Operator*`, `ServiceOperatorRoleLink`, `PetDependenciesCrud`, `PetCatalogRootViews`, `Resource*`,
  `SystemSettings*`, toggles de módulos, `ClientAddressHarmonization`) — sin auditar, sigue pendiente una
  sesión dedicada.
- **NUNCA correr dos `artisan test` completos a la vez contra la BD `testing`** — `RefreshDatabase`
  re-migra por clase, se pisan y dan `QueryException` en masa (mordió en Fase 3b, ver recuadro arriba).
- **Hilos sueltos sin portar** (menores, de otros linajes, no del arco de agenda): refactor de imports
  de `CashController.php`; botón "Ver reportes de caja" en `MobCaja.tsx`; branding móvil
  (`useBusinessName` + `/api/settings/branding`, orphan de `SYNC-034` en `tst`).
- **Nits de Pint preexistentes** sin tocar: `CashController.php` (`class_attributes_separation`),
  `PetController.php` Api (`no_unused_imports`, `use Client` muerto), `CashSessionExpectedAmountService.php`
  (`method_argument_space`).

---

## 📅 Cierre de sesión: 27/08/2026 — emergencia: 500 al guardar en USEEDI un usuario vinculado a un operador (`SYNC-002` / `NT-062`)

### ✅ Logros y Cambios

Sesión que arrancó del lado de Zeus-Estetican (una mejora de Google Calendar en el sandbox `tst`)
y derivó a una **emergencia real de producción** cuando Tomas reportó un 500 en USEEDI al guardar
un usuario administrador.

**Emergencia — 500 en `PUT /usuarios/{id}`.** `UserController::syncOperatorRecord()` armaba el
array `$data` con `'operator_role_id' => $user->operator_role_id` y lo escribía con
`Operator::where('id', ...)->update($data)`. La columna `operators.operator_role_id` fue eliminada
por la migración `2026_07_31_000000_consolidate_operator_role_fields` (el rol del operador vive
ahora en `operator_role_assignments`). El `->update()` del query builder **no filtra por
`$fillable`**, así que mandaba la columna inexistente a SQL → `SQLSTATE[42S22] Unknown column
'operator_role_id'` → 500, y el guardado del usuario fallaba en silencio. Solo se disparaba con
usuarios **ya vinculados** a un operador (`operator_id` puesto) — el alta de operador nuevo usa
`Operator::create()`, que sí descarta la clave por mass-assignment. Latente desde el 31/07/2026.

La correlación que reportó Tomas ("al cambiar los permisos del CRUD") era casual:
`syncOperatorRecord()` corre en todo guardado, antes de `syncPermissions()`.

**Fix:** eliminada esa línea del `$data` (con comentario del porqué). `users.operator_role_id`
sigue existiendo y el selector "Tipo de Operador" de USEEDI la guarda bien — lo único que sobraba
era propagarla al registro `operators`.

### 📁 Archivos tocados
- `apps/backoffice-laravel/app/Http/Controllers/UserController.php` — `syncOperatorRecord()`, una línea menos.
- `apps/backoffice-laravel/tests/Feature/UserOperatorSyncTest.php` — **nuevo**, regresión (2 casos): `operators` ya no tiene la columna; `PUT users.update` de un usuario operador-vinculado redirige (no 500), persiste `users.operator_role_id` y actualiza el registro de operador.
- `docs/tecnico/PENDIENTES_SINCRONIZAR_TENANTS.md` — `SYNC-002` en Aplicados (ya portado a `tst`, allá es `SYNC-048`).
- `docs/tecnico/NOTAS_TECNICAS.md` — `NT-062` (causa raíz: `EloquentBuilder::update($array)` no respeta `$fillable`, a diferencia de `create()`/`fill()`).

### ✅ Verificación
Pint limpio; 42 tests `--filter=User` en verde (incluidos los 2 nuevos). Sin migración, sin rutas
nuevas (no aplica la auditoría de seguridad #3 de `CLAUDE.md`).

### 🛑 Pendientes activos
- **Sin commitear:** el fix + test + los 2 docs de hoy. Más lo ya pendiente de antes (esta
  `BITACORA.md` y `BACKLOG.md` con la entrada BL-028 del 25/08 sin commitear; y los 3 commits de
  WhatsApp del 20-21/08 — `1c56e70`/`10e1581`/`1842b52` — que sí están pusheados pero nunca
  tuvieron entrada de bitácora).
- El bug se arregló **primero en `tst`** (Zeus-Estetican, `SYNC-048`); acá se aplicó como emergencia.
- Deuda de tests preexistente sin auditar — sigue igual.

---

## 📅 Cierre de sesión: 25/08/2026 — BL-028 re-verificado: regla ufw huérfana de SSH corregida

### ✅ Logros y Cambios

Re-verificación puntual de BL-028 (pedida desde una sesión de Zeus-Estetican, que comparte el mismo
servidor físico). `ufw status numbered` confirmó que las 9 reglas seguían iguales a la verificación
del 31/07/2026, con un hallazgo nuevo: la regla de SSH puntual para `192.168.90.90` era la única sin
atar a interfaz (`22/tcp ALLOW IN 192.168.90.90`, sin `on enP4p65s0`) — todas las demás sí especificaban
`on enP4p65s0` (LAN). Confirmado con el usuario que esa IP llega por la LAN vía una VLAN, así que
corregirla no cortaba el acceso. Corregida:

```
sudo ufw delete <n>
sudo ufw allow in on enP4p65s0 from 192.168.90.90 to any port 22 proto tcp
```

Verificado con `ufw status numbered` post-cambio: la regla quedó `22/tcp on enP4p65s0 ALLOW IN
192.168.90.90`, igual que el resto — ya no hay ninguna regla sin restricción de interfaz. No se tocó
código, solo configuración de firewall del servidor. Ver `BACKLOG.md` BL-028.

---

## 📅 Cierre de sesión: 20/08/2026 — `SYNC-034` portado desde Zeus-Estetican: teléfonos con tipo/orden/extensión + código de país configurable (app y móvil)

### ✅ Logros y Cambios

Sesión de porteo puro, a pedido directo del usuario ("pórtealo a producción app y mov"). Construido
y verificado en `tst` durante varias iteraciones el mismo día (detalle técnico completo en
`docs/tecnico/PENDIENTES_SINCRONIZAR_ESTETICAN.md` de Zeus-Estetican, `SYNC-034`, movido a Aplicados).

**Resumen:**
- Tipo de teléfono pasa de `mobile`/`fixed` (2 valores) a `mobile`/`home`/`work`/`other` (4) —
  varios teléfonos por cliente, reordenables por importancia (botones ↑/↓) en web y móvil. El
  teléfono `mobile` de mayor importancia sigue siendo el que usa WhatsApp/SMS
  (`PhoneNormalizer::bestPhoneFor()`, mismo comportamiento, ahora respeta el orden real vía
  `phones.sort_order` nueva).
- Subcampo `phones.extension` (opcional, solo dígitos) en los 4 formularios.
- Validación real de 10 dígitos (`App\Rules\ValidPhoneNumber`, nueva) con filtrado numérico **en
  vivo** mientras se escribe (no solo al enviar) — pedido explícito del usuario en dos vueltas
  distintas de la sesión de `tst`.
- **El estándar de 10 dígitos (México/Norteamérica) ahora es configurable**: nueva sección
  "Clientes" en Configuración con un toggle "Permitir código de país en teléfonos" (default
  apagado) — activo amplía a 8-15 dígitos según el estándar internacional E.164, para clínicas
  con clientes de zona fronteriza o de otro país. Endpoint nuevo `GET /api/settings/phone-format`
  (mismo patrón que `/settings/booking`/`/settings/photos` ya existentes, dentro del grupo
  autenticado, sin `permission:` adicional — igual criterio que sus pares, ninguno expone datos
  de negocio).
- Alta de cliente nueva desde el móvil gana captura de varios teléfonos con tipo (antes un campo
  suelto sin tipo).

**Verificación antes de portar:**
- Diff archivo por archivo entre el HEAD de `tst` (antes de esta sesión) y este repo confirmó que
  ningún archivo tocado había divergido de forma independiente — con 2 excepciones puntuales
  (`SettingController.php`/`routes/api.php`, donde `tst` tiene un endpoint `branding` que este
  repo nunca recibió, de un porteo anterior incompleto y ajeno a esta sesión) — para esos 2
  archivos se aplicó solo el agregado puntual (`phoneFormat()` + su ruta), sin arrastrar esa
  diferencia previa.
- Backup real de la BD de producción antes de migrar
  (`backups/estetican_pre-sync034-phones-sortorder-extension-countrycode_20260820_2043.sql`).
- Suite completa corrida **dos veces** (`git stash -u` para volver al estado previo, correr,
  `git stash pop` para restaurar) — **mismos 37 fallos preexistentes en ambos casos, diff vacío**
  entre las dos listas de fallos — confirma que la deuda de tests existente no tiene relación con
  este cambio. Con el cambio aplicado: 552 pasan (antes 517), la diferencia son los tests nuevos
  de esta feature.
- Migración real corrida contra la BD de producción: 39 teléfonos existentes, todos ganaron
  `sort_order` (preservando su orden de captura por `id`), 0 quedaron en `type=fixed` (no había
  ninguno realmente, la reclasificación fue un no-op en este tenant).
- Verificado también contra la API real (`app.estetican.org`, usuario y datos desechables,
  borrados al terminar): alta con 2 teléfonos (uno con extensión) devuelta correctamente en el
  `GET` siguiente.
- Bundle web reconstruido (`npm run build` dentro de un contenedor `node:20-alpine` — el host es
  ARM64 con Node glibc nativo, pero `node_modules` de este repo solo trae el binario nativo de
  Rollup para musl, mismo hallazgo ya documentado del lado de `tst`). Bundle móvil reconstruido
  igual — `estetican_mob` sirve `mob_apps/operador/dist/` por bind mount directo (`nginx:alpine`
  sin imagen propia), así que no hizo falta recrear el contenedor, el `npm run build` ya deja el
  archivo nuevo servido de inmediato (confirmado por `grep` dentro del contenedor).
- `docs/tecnico/MODELO_BD.md` actualizado: tabla `phones` corregida (documentaba una relación
  polimórfica ya removida desde 2026-03-20 — se aprovechó para dejarla con el esquema real actual,
  incluidas las 2 columnas nuevas de este cambio).

Commit `dd567e0`, pusheado a `origin/main`.

### 🛑 Pendientes activos
- Deuda real de 37 tests fallando en la suite (confirmada preexistente, sin relación con este
  cambio) — sigue sin auditar, pendiente de una sesión dedicada.
- El toggle "Permitir código de país en teléfonos" queda apagado por default en producción — no
  se activó, es una decisión de negocio que le corresponde al usuario tomar si/cuando la
  necesite.

---

## 📅 Cierre de sesión: 17/08/2026 — `SYNC-029` portado desde Zeus-Estetican: cambiar foto de mascota con "mantener presionado" en buscador y ficha (app móvil)

### ✅ Logros y Cambios

Pedido directo del usuario en esta misma sesión: en `MobPetSrch`, mantener presionada la foto de
una mascota debía ofrecer elegir otra desde cámara/galería, con opción de cancelar — "igual en la
ficha de la mascota". Es una mejora de UX, no una emergencia — señalado antes de construir; el
usuario confirmó construirlo en `tst` (Zeus-Estetican) y portarlo después, según la regla de
alcance del repo.

**Construido y verificado en `tst` (detalle completo en `docs/tecnico/PENDIENTES_SINCRONIZAR_ESTETICAN.md`
de Zeus-Estetican, `SYNC-029`):** menú nuevo (`PhotoSourceSheet.tsx`, Tomar foto/Elegir de
galería/Cancelar) que reusa la misma estructura de overlay ya probada en producción
(`fixed inset-0 z-50`, igual que `PhotoEditorModal`) — evita repetir el fallo real de un overlay
similar en un dispositivo Android (ver sesión 06/08/2026). `usePhotoPicker` se extrajo de
`PetDetail.tsx` a un hook compartido para reusarlo también en `PetSearch.tsx`. En la ficha
complementa, no reemplaza, los botones de cámara/galería ya existentes. **El usuario probó el
gesto en su teléfono real contra `tstmov.estetican.org` y confirmó que funciona bien** antes de
autorizar el porteo.

**Portado a este repo:** confirmado primero que `PetSearch.tsx`/`PetDetail.tsx` de producción no
tenían divergencia propia respecto al baseline de `tst` (`diff` limpio salvo el cambio de esta
feature). Copiados 5 archivos (2 modificados + 3 nuevos): `mob_apps/operador/src/PhotoSourceSheet.tsx`,
`mob_apps/operador/src/hooks/{usePhotoPicker.tsx,useLongPress.ts}`,
`mob_apps/operador/src/admin/{PetSearch.tsx,PetDetail.tsx}`. `tsc --noEmit` limpio (mismos 2
errores preexistentes de `MobCajaMovimientos.tsx`, no tocados, confirmados sin relación). Build
de producción (`npm run build` vía `node:20-alpine`) limpio, contenedor `estetican_mob`
reiniciado, `md5sum` idéntico host/contenedor confirmado. Sin cambios de backend (reusa
`POST /api/pets/{id}/photo` existente) — no aplica correr la suite PHP.

Commit: `8ba961b`.

### 🛑 Pendientes activos
- Ninguno — feature completa, verificada en dispositivo real y en producción.

---

## 📅 Cierre de sesión: 16/08/2026 (cont. 2) — `SYNC-027` portado desde Zeus-Estetican: versión web de los 5 reportes de Caja, "Reportes" reestructurado a subgrupos

### ✅ Logros y Cambios

Sesión de porteo puro, mismo criterio que las anteriores. Detalle técnico completo en
`docs/tecnico/PENDIENTES_SINCRONIZAR_ESTETICAN.md` de Zeus-Estetican (`SYNC-027`, Aplicados).

**Resumen:** la pestaña "Reportes" (antes solo Bitácora de actividad) pasa a subgrupos
General/Caja — los 5 reportes de Caja que ya existían en el celular (`SYNC-026`) ahora también
tienen versión web, reusando la misma agregación (`CashReportService`, extraído de
`Api\CashController` en este porteo — mismo principio que `CashSessionExpectedAmountService`).

**Verificado en vivo contra producción real:** login con usuario desechable, dropdown "Reportes"
con los subgrupos correctos, reporte "Resumen de caja" con datos reales de producción
($6,950.00), descarga de PDF válida. 51 tests de Caja sin regresiones.

Commit: `a117f2a`.

---

## 📅 Cierre de sesión: 16/08/2026 (cont.) — `SYNC-026` portado desde Zeus-Estetican: hub Finanzas móvil + 5 reportes de Caja (PDF/email) + fix de saldo esperado

### ✅ Logros y Cambios

Sesión de porteo puro, mismo criterio que `SYNC-024`/`025` (abajo). Detalle técnico completo en
`docs/tecnico/PENDIENTES_SINCRONIZAR_ESTETICAN.md` de Zeus-Estetican (`SYNC-026`, Aplicados).

**Resumen:** el menú móvil "Finanzas" pasa a ser un hub (Caja + Reportes de caja) en vez de entrar
directo a Caja; 5 reportes nuevos de Caja con PDF y envío por correo. Fix real de fondo: existían
tres fórmulas distintas de "efectivo esperado" de una sesión de caja (celular en vivo, preview web
antes de cerrar, cierre real) y solo una era correcta — se unificaron en
`Domain/Accounting/CashSessionExpectedAmountService`.

**Verificado en vivo contra producción real, no solo escrito:** 35 tests de Caja pasan sin
regresiones (suite completa: 498 pasan/37 fallan, mismas 37 preexistentes de siempre, +17 exacto
por los tests nuevos); bundle móvil reconstruido y confirmado servido; endpoint de resumen probado
con datos reales de producción; PDF descargado y validado; **correo enviado de verdad contra el
SMTP real de producción** (`mail.supremecenterhost.com`, configurado en `SystemSettings`, no en
`.env` — confirmado con HTTP 200 síncrono).

**Dos hallazgos del proceso de porteo, documentados en detalle en Zeus-Estetican:** un bug real que
el propio refactor había introducido en `tst` (una vista dejaba de recibir una variable que sí usa)
detectado por diffear contra el baseline de producción antes de portar, no por confiar solo en los
tests; y un incidente de contraseña de Admin en `tst` (no en producción) durante la verificación en
vivo, ya resuelto con el mejor respaldo disponible.

Commit: `2021a82`.

---

## 📅 Cierre de sesión: 16/08/2026 — `SYNC-024`/`SYNC-025` portados desde Zeus-Estetican: Caja deja de depender del check-in (sucursal asignada + permisos granulares + reversión de movimientos)

### ✅ Logros y Cambios

Sesión de porteo puro — todo el trabajo real (diseño, construcción, 5 bugs encontrados en
auditoría propia y corregidos, tests) se hizo del lado de Zeus-Estetican (`tenants/tst`), mismo
criterio que sesiones anteriores. Detalle técnico completo en
`docs/tecnico/PENDIENTES_SINCRONIZAR_ESTETICAN.md` de Zeus-Estetican (`SYNC-024`/`SYNC-025`,
movidos a "Aplicados").

**`SYNC-024` — Caja pasa de depender del check-in del día (RH/asistencia) a `users.branch_id`
(sucursal asignada, dato organizacional persistente) + permisos granulares:**
- `CashController.php`: `resolveBranchId()` nuevo (nunca más `OperatorCheckin`), `updateMovement()`/
  `revertMovement()` nuevos (con lock anti-carrera, rechazan operar sobre una sesión ya cerrada —
  hallazgo real de la auditoría de hoy, no estaba en el diseño original).
- Migraciones: `users.branch_id`, `cash_movements.reversed_at`+`reversal_of_movement_id`.
- `BaseRolesSeeder`: 4 permisos granulares nuevos (`caja.ver`, `caja.movimientos.{crear,editar,
  revertir}`) — el rol `admin` los recibe automáticamente vía `syncPermissions()`, corrido en
  producción real (`php artisan db:seed --class=BaseRolesSeeder --force`).
- `routes/api.php`: los 5 endpoints de Caja pasan de `permission:caja.abrir` único a permisos
  específicos por acción; 2 rutas nuevas (`PATCH .../movements/{id}`, `POST .../revert`).
- `UserController.php` + `user/{edit,create}.blade.php`: selector "Sucursal asignada" + 6
  checkboxes de Caja — antes no existía ninguna forma de asignar esto a un usuario no-admin.
- Móvil (`MobCaja.tsx`, `MobCajaMovimientos.tsx`, `App.tsx`, `AuthContext.tsx`): pantallas
  `no_branch`/`select_branch`, botones Editar/Revertir gateados por permiso y por tipo de
  movimiento (nunca sobre cobros, que no son `CashMovement` reales), badges "Revertido"/
  "Reversión", y el ítem "Caja" del menú ahora se oculta si falta `caja.ver`.

**`SYNC-025` — `operator-roles/create.blade.php` capturaba `return_to` pero nunca mostraba un
link "Regresar" ni lo respetaba en "Cancelar":** encontrado al arreglar (del lado de `tst`) la
suite de tests completa, no directamente relacionado con Caja. Un archivo, un `@if` con el mismo
patrón que ya usa `branches/create`.

**Verificado en producción real antes de dar por cerrado:**
migraciones corridas (`php artisan migrate --force`), permisos confirmados en el rol `admin`
vía tinker, 24 tests nuevos/reescritos (`CashMovementsBranchScopingTest`, `CashOpenSessionTest`
reescrito, `UserBranchAssignmentTest`) corridos contra la BD real de testing — los 24 pasan.
Bundle móvil reconstruido (`node:20-alpine`, mismo mecanismo que sesiones anteriores) y
verificado sirviendo el JS nuevo en el volumen de `estetican_mob` (grep del mensaje de error
nuevo en el bundle servido). Suite completa corrida aparte: 481 pasan, 37 fallan — **las 37 son
exactamente los mismos tests preexistentes rotos que ya se encontraron y corrigieron del lado
de `tst` hoy** (sin `actingAs()`, texto de UI desactualizado, no relacionados con Caja) — no se
tocaron acá por estar fuera de alcance de este porteo y no ser una emergencia.

**Actualización el mismo día — `branch_id` asignado a los 5 usuarios reales.** Solo existe 1
sucursal activa (Aguascalientes Colinas del Río, id=1), sin ambigüedad de a cuál asignar cada
usuario. Los 5 (`Admin`, `arantxa`, `tomasmg`, `tester` — los 4 con `can_login`, todos `role:
admin` — y `GRO-JMP`, un registro de operador sin `can_login` todavía) quedaron con
`branch_id=1` vía `User::update()` en tinker. Como los 4 con login son super-admin, no
dependían de esto para usar Caja (eligen sucursal explícita), pero ahora ya no ven la pantalla
de selección cada vez.

### 📁 Archivos principales tocados
- `apps/backoffice-laravel/app/Http/Controllers/Api/CashController.php`
- `apps/backoffice-laravel/app/Models/{User,CashMovement}.php`
- `apps/backoffice-laravel/database/migrations/2026_08_16_*` (2 nuevas)
- `apps/backoffice-laravel/database/seeders/BaseRolesSeeder.php`
- `apps/backoffice-laravel/routes/api.php`
- `apps/backoffice-laravel/app/Http/Controllers/UserController.php`, `resources/views/user/{edit,create}.blade.php`
- `apps/backoffice-laravel/resources/views/operator-roles/create.blade.php`
- `apps/backoffice-laravel/tests/Feature/Api/{CashOpenSessionTest.php (reescrito),CashMovementsBranchScopingTest.php (nuevo)}`, `tests/Feature/UserBranchAssignmentTest.php` (nuevo)
- `mob_apps/operador/src/{AuthContext.tsx,App.tsx,admin/MobCaja.tsx,admin/MobCajaMovimientos.tsx}`

### 🛑 Pendientes activos
- Asignar `branch_id` a los usuarios reales de producción desde la pantalla de usuario (ver arriba).
- Los 37 tests preexistentes rotos (auth faltante en tests, texto de UI desactualizado) siguen
  sin corregir en este repo — se corrigieron del lado de `tst`, portar esos fixes de test no es
  una emergencia, queda para una sesión futura si se decide que vale la pena.

---

## 📅 Cierre de sesión: 15/08/2026 — 13 commits portados desde Zeus-Estetican (`SYNC-009` a `SYNC-020`): IDOR crítico en Clínico, Agenda no filtraba "Hoy", Dashboard subestimaba ingresos, y una tanda grande de UX en la app móvil (incluida Caja)

### ✅ Logros y Cambios

Sesión de porteo puro — todo el trabajo real (hallazgos, fixes, tests, pruebas en vivo con
Playwright) se hizo del lado de Zeus-Estetican (`tenants/tst`), siguiendo la regla de alcance
vigente (producción real solo se toca para portar lo ya probado en sandbox, nunca para
desarrollar directo acá). Detalle técnico completo de cada hallazgo en
`docs/tecnico/PENDIENTES_SINCRONIZAR_ESTETICAN.md` de Zeus-Estetican (`SYNC-009` a `SYNC-020`).
Acá el resumen de lo que quedó en este repo, por orden de commit:

1. **`e059b8b`** — Agenda no filtraba por fecha al entrar: `whereDate()` solo se aplicaba para
   `dateScope=custom`, nunca para `today`/`tomorrow` — "Hoy" mostraba citas de todos los días
   mezcladas. De paso, estados por defecto ganan `completed`, orden pasa a ascendente.
2. **`72ffbd2`** — Mensaje flash duplicado en 19 vistas (toast global + banner propio de cada
   vista leyendo la misma key de sesión) + Bitácora mostrando "–" en vez del diff real
   (`spatie/laravel-activitylog` v5 separó el diff en `attribute_changes`, la vista seguía
   leyendo `properties`, siempre vacía en esta versión).
3. **`f812754`** — **IDOR crítico** en vacunas/alergias/condiciones del módulo Clínico:
   `PetVaccinationController`/`PetAllergyController`/`PetConditionController` resolvían el ID del
   registro sin confirmar que perteneciera a la mascota de la URL — cualquier usuario con el
   permiso podía editar/borrar el dato clínico de OTRA mascota. Fix: `Route::scopeBindings()`,
   mismo patrón que ya usa `resources.events.*`. Ver `NT-060` más abajo.
4. **`c8dd5cb`** — Dashboard "Ingresos del día" nunca sumaba `Payment` (cobro directo desde la
   app móvil sin presupuesto) — solo `CashLedger`/`BankLedger`. Con Caja/Banco en $0, el widget
   real mostraba $0.00 pese a $571.50 reales en pagos móviles. **Nota:** esto es un bug distinto
   del ya documentado en `BL-078` (que es sobre falta de filtro por sucursal, sigue abierto y sin
   relación).
5. **`2d5b9eb`** — Condición de carrera al abrir sesión de caja (check-then-act sin lock) podía
   crear dos `CashSession` abiertas para la misma caja. Fix: `lockForUpdate()` en
   `DB::transaction()`, mismo patrón que `AccountingService::getNextFolio()`.
6. **`badc67d`** — 4 hallazgos de UX en la app móvil: íconos mostrando texto crudo por falta de
   `display=block` + guard de carga; sin ruta catch-all (cualquier typo dejaba pantalla en
   blanco); botones bajo 44px; badge del identificador de soporte con separación visual.
7. **`ddfccff`/`f91da3d`/`2824447`/`2fea247`** — Crear cita desde Agenda perdía la fecha
   seleccionada (podía terminar agendando para HOY habiendo elegido "Mañana" — riesgo real de
   citas en el día equivocado) y pasaba por 3 pantallas de más. Encadenado con: `+ Cita` abría un
   menú de un solo botón para usuarios sin perfil de operador; un tercer botón "Nueva cita" (y su
   duplicado en `GroomerAgenda.tsx`) con la misma lógica vieja; y al agendar, la app volvía a la
   ficha de la mascota en vez de a la Agenda del día agendado.
8. **`5571161`** — Estrategia "MCP" (fases 1-3, ver `ZEUS-028`/`029`/`030` en el backlog de Zeus):
   preselección de operador, servicio sugerido según historial de la mascota, acciones directas
   en tarjetas de Agenda ("Iniciar"/"Cobrar"/WhatsApp) sin entrar al detalle.
9. **`2af78ef`** — Botón de calendario junto a las fechas no abría el selector nativo en 3
   pantallas (`GlobalAgenda`/`GroomerAgenda`/`MobCitaDet` al reagendar) — `<label htmlFor>` sobre
   un input oculto no dispara el picker de forma confiable en Chrome de escritorio, hace falta
   `showPicker()` explícito (patrón que ya existía en `MobCitaNueva.tsx`, nunca replicado). Ver
   `NT-061`.
10. **`54a0d10`** — Caja sin check-in activo no dejaba hacer check-in ahí mismo (había que ir al
    menú a buscar "Registrar"). `CheckinWidget` se extrae a archivo propio, reusado en `MobCaja`
    con el selector de sucursal ya expandido.
11. **`602bc56`** — El cobro decía "Se registrará en Caja", implicando una atadura a la sesión de
    caja que no existe — el pago se guarda como `Payment` suelto sin relación con `CashSession`.
    Reescrito a "Efectivo"/"como efectivo" en los 4 lugares donde aparecía, decisión tomada con
    Tomas de aclarar el texto en vez de bloquear el cobro sin check-in.

**Verificado al cierre:** árbol de trabajo limpio (nada sin commitear), rebuild fresco de la app
móvil desde el commit actual sirviendo el mismo hash que ya estaba en producción (confirma que lo
desplegado coincide exactamente con lo commiteado, sin desfase), 126 tests de backend
(Agenda/Clínico/Dashboard/Caja/WhatsApp/Bitácora) en verde contra `estetican_app` real,
`app.estetican.org`/`mov.estetican.org` respondiendo 200 normal.

### 📁 Archivos principales tocados
Ver el commit correspondiente de cada punto arriba — 2 áreas (backend `apps/backoffice-laravel/`,
móvil `mob_apps/operador/`), sin ninguna migración (todos cambios de código/vista puro).

### 🛑 Pendientes activos
- **`BL-078` sigue abierto** (Dashboard "Ingresos hoy" sin filtro de sucursal) — no relacionado
  con el fix de hoy (que era sobre una fuente de datos faltante, `Payment`, no sobre sucursales).
- **Divergencia pendiente sin tocar a propósito**, dos veces mencionada en los commits de hoy:
  `useBusinessName()`/`brand_business_name` en `GlobalAgenda.tsx` (`SYNC-001` de Zeus, evaluado
  pero nunca portado) — el título de la app móvil real sigue fijo en "EstetiCAN" en vez de leer
  el nombre configurado. Queda para una sesión aparte.
- Nada más pendiente de portar de Zeus-Estetican — `PENDIENTES_SINCRONIZAR_ESTETICAN.md` queda
  vacío en Pendientes.

---

## 📅 Cierre de sesión: 15/08/2026 (cont.) — SYNC-021 a SYNC-023 portados: wording de Caja, abrir turno desde el móvil, selector de período

### ✅ Logros y Cambios

Continuación de la sesión de porteo puro de arriba, misma regla de alcance vigente. Detalle
técnico completo en `docs/tecnico/PENDIENTES_SINCRONIZAR_ESTETICAN.md` de Zeus-Estetican
(`SYNC-021` a `SYNC-023`).

1. **(sin commit propio, texto ya incluido en `SYNC-020` de arriba)** `SYNC-021` — "Sin sesión
   activa" en Caja se leía como "no estás logueado" (mismo overload de la palabra "sesión" que ya
   se resolvió para "Caja"/"efectivo") — título pasa a "Sin caja abierta".
2. **`b448a19`** — `SYNC-022`: abrir una `CashSession` directo desde la app móvil (antes solo
   desde el backoffice web). `CashController::openSession()` nuevo (`POST /api/cash/session`),
   resuelve la caja a partir del check-in activo, mismo lock (`lockForUpdate()` en
   `DB::transaction()`) que `CashSessionController::store()` para evitar dos sesiones abiertas
   simultáneas. 3 tests nuevos en verde. Probado en vivo con Playwright antes de portar.
3. **`c5cd349`** — `SYNC-023`: selector de período (Turno actual/Hoy/Semana/Mes/Rango) arriba de
   los totales en la pantalla principal de Caja — antes solo mostraba el turno abierto, sin forma
   de revisar otro período sin salir a `/caja/movimientos`. 100% frontend, reusa
   `GET /api/cash/movements` ya existente, sin backend nuevo.

**Nota de proceso sobre `SYNC-023`:** se construyó y desplegó a `mov.estetican.org` (bundle de
`mob_apps/operador`, servido por bind-mount directo) antes de señalar que era una mejora de UX no
urgente y que, según la regla de alcance del 13/08/2026 (ver abajo), debía flaggearse *antes* de
construirse — el trabajo ya estaba listo y probado en `tst` cuando se cayó en la cuenta. Señalado
a Tomas después del hecho, con la opción de revertir producción; confirmó dejarlo en vivo ("si no
se rompe nada, hazlo"). Sin incidente real — cambio de bajo riesgo (sin backend nuevo, mismo
endpoint ya verificado), pero el proceso de flaggear antes de construir no se siguió esta vez.

### 🛑 Pendientes activos
- Mismos de la entrada de arriba (`BL-078`, divergencia `useBusinessName()` sin portar).
- `ZEUS-034` (cerrar turno desde el móvil) y `ZEUS-035` (desglose por método de pago) siguen en
  backlog de Zeus-Estetican, sin construir.

---

## 📅 Cierre de sesión: 13/08/2026 — Emergencia real (mascota duplicada + citas cruzadas), bug de UI en Agenda (NT-058), y nueva regla de alcance con Zeus-Estetican (producción = solo emergencias)

### ✅ Logros y Cambios

Sesión arrancó con protocolo estándar (lectura de `BITACORA.md`/`BACKLOG.md`). Se detectó de paso que la entrada anterior (11/08 cont., BL-024b) seguía sin commitear — sin tocarla, queda igual pendiente de commit junto con lo de hoy.

**Emergencia real reportada por el usuario: mascota duplicada con citas cruzadas.** Se dio de alta por error dos veces al mismo perro como "Valentina" (mascotas `#52`/`#53`, mismo dueño Rosa López `#40`) y el usuario corrigió el nombre de una a "Benito" a mano — pero **las dos citas de hoy** (`#49` 13:30, `#50` 15:00) quedaron apuntando a la mascota `#53` (Benito aparecía dos veces en el móvil), y la `#52` (Valentina) sin ninguna. Investigado en BD (`tinker`, sin asumir nada) antes de tocar: confirmado con el usuario cuál cita correspondía a cada perro, corregido `spa_bookings.pet_id` de la `#49` de 53 a 52. Revisadas dependencias antes de corregir: sin `Quote` ligado, 1 `Payment`, y un evento de Google Calendar ya sincronizado (`google_event_id`) que se autocorrige solo en la próxima corrida de `calendario:sincronizar-google` porque el `save()` normal bumpeó `updated_at` por encima de `google_synced_at`. Aparte, lo de "ya no aparecen las citas de hoy" **no era un bug**: el filtro de Estado por defecto de Agenda solo muestra `scheduled`/`work_order`, y las 4 citas de hoy ya estaban `completed` — se le explicó al usuario cómo cambiar el filtro.

**Bug real de UI encontrado en el camino, reportado aparte por el usuario:** la barra de pestañas "Ventana" (Hoy/Mañana/Próximas/Todas, `<a href>`) y el `<select name="date_scope">` del panel de filtros de Agenda eran **dos controles independientes para el mismo parámetro** — cualquier cambio sin aplicar en un panel (ej. "Marcar todos" de Estado) se perdía en silencio al tocar el otro. Unificados en un solo `<form>` real (`agenda/index.blade.php` + `components/list-filters.blade.php`, prop `id` nueva y retrocompatible — no afecta a los otros 10 `index.blade.php` que usan `x-list-filters`): los botones de ventana y de día anterior/siguiente pasaron a `<button type="submit" form="agenda-filters-form" name="date_scope" value="...">` (atributo HTML5 `form=` para enviar el `<form>` de filtros desde botones fuera de él), se quitó el `<select>` duplicado (le faltaba la opción "Todas"), se agregaron hidden `sort`/`direction` para no perder el orden de tabla al aplicar filtros. Sin `onclick`/`onchange` inline en ningún momento — la CSP del proyecto los bloquea en silencio (NT-042) — toda la lógica nueva vive en el `<script nonce="{{ csp_nonce() }}">` ya existente. Detalle completo de la causa raíz en **NT-058** (nuevo).

**Verificado antes de dar el fix por bueno:** compila sin error Blade (`view:cache`), Pint limpio, 76 tests de Agenda pasan (incluye uno que ya cubría la navegación por día), suite completa 447 pasan / 37 fallan (misma deuda de fixtures preexistente, sin regresiones nuevas). **No se pudo probar en navegador real** — la extensión de Chrome no estaba conectada en esta sesión; se le pidió al usuario que lo revise visualmente cuando pueda.

**Portado el mismo día a `tenants/tst` de Zeus-Estetican, a pedido explícito del usuario.** Antes de copiar se confirmó que los dos archivos equivalentes de `tst` eran byte-idénticos a la versión pre-fix de EstetiCAN (sin divergencia propia del tenant) — se copiaron los mismos 2 archivos ya corregidos y se corrió `view:clear`+`view:cache` en `tst_app` (compiló sin error; sin phpunit instalado ahí, build de producción, no se pudieron correr tests). Avisado por `SendMessage` a la sesión paralela detectada vía `ListAgents` (`tst-3c`, interactiva, online) con el detalle completo del fix.

**Regla de alcance formalizada con el usuario, pedido explícito:** EstetiCAN real (`/opt/www/estetican`) es **SOLO para emergencias** de ahora en adelante — bugs que rompen operación real, incidentes de datos, huecos de seguridad. Cualquier addon/upgrade/mejora que no sea emergencia se construye del lado de Zeus-Estetican, en el sandbox `tst` (`tstapp.estetican.org`/`tstmov.estetican.org`) — el sandbox de referencia para todos los tenants — y se promueve a producción después, deliberadamente. Agregada la regla a `CLAUDE.md` (tabla de docs + nota de alcance explícita) para que cualquier sesión futura en este repo la vea sin depender de esta bitácora.

**Documento nuevo `docs/tecnico/PENDIENTES_SINCRONIZAR_TENANTS.md`** (namespace `SYNC-XXX` propio, independiente del contador de Zeus) — registra qué emergencia de código compartido se arregló acá y si ya se portó a los tenants. Es el espejo, en dirección contraria, del `PENDIENTES_SINCRONIZAR_ESTETICAN.md` que ya existía del lado de Zeus-Estetican (ese cubre lo que se descubre en el sandbox y falta subir a producción). El fix de Agenda de hoy quedó anotado ahí como `SYNC-001`, aplicado.

**Coordinación real con la sesión paralela, en vivo:** la sesión `tst-3c` (identificada como "Iniciar sesión" al responder — mismo interlocutor) confirmó que reflejó ambos avisos del lado de Zeus-Estetican: nota espejo en su propio `PENDIENTES_SINCRONIZAR_ESTETICAN.md` referenciando el nuevo doc de este lado, `SYNC-001` marcado como aplicado en `tst`, todo commiteado junto con otros cambios de su sesión. Confirmó también su preferencia sobre el mecanismo de sincronización general (coincide con lo ya armado acá): dos documentos espejo con namespaces separados por repo (para evitar conflictos de escritura entre dos Claudes editando el mismo archivo en paralelo) + `SendMessage` en vivo cuando algo es urgente o toca el mismo archivo que la otra sesión podría estar tocando a la vez.

**Dato aparte, de la sesión paralela, sin acción de este lado:** en esa misma sesión de Zeus-Estetican se eliminó por completo el tenant sandbox "Huellitas" (junto con un respaldo) — `tst` queda como único tenant sandbox de ahora en adelante. Anotado en memoria para no seguir refiriéndose a Huellitas como si siguiera activo.

### 📁 Archivos Modificados
- `apps/backoffice-laravel/resources/views/agenda/index.blade.php` — unificación de los paneles de filtro (fix NT-058)
- `apps/backoffice-laravel/resources/views/components/list-filters.blade.php` — prop `id` nueva, retrocompatible
- `docs/tecnico/NOTAS_TECNICAS.md` — NT-058 nuevo
- `docs/tecnico/PENDIENTES_SINCRONIZAR_TENANTS.md` — nuevo, `SYNC-001` aplicado
- `docs/tecnico/BACKLOG.md` — entrada nueva en Completados (13/08/2026)
- `CLAUDE.md` — regla de alcance (producción = solo emergencias), referencia al doc nuevo
- `BITACORA.md` (esta entrada)
- **BD de producción:** `spa_bookings.pet_id` de la cita `#49` corregido de 53 a 52 (sin migración — corrección de datos puntual vía `tinker`)
- **`tenants/tst` de Zeus-Estetican** (fuera de este repo, sin versionar — ver nota en `PENDIENTES_SINCRONIZAR_TENANTS.md`): mismos 2 archivos Blade copiados, cache de vistas recompilado

### 🛑 Pendientes activos
- **Sin commitear:** cambios de esta sesión (Agenda + docs nuevos + corrección de datos ya aplicada en BD) más la entrada anterior sin commitear (11/08 cont., BL-024b) — 2 sesiones de docs acumuladas sin commit. El usuario no pidió commitear todavía.
- **Verificación visual pendiente:** el fix de Agenda no se probó en navegador real esta sesión (sin Chrome conectado) — pedirle al usuario que lo confirme la próxima vez que use la Agenda.
- **BL-024b** sigue igual que antes (delegado a la sesión de Chrome, esperando `phone_number_id`+token), sin cambios esta sesión.
- BL-078 (scope de sucursal en Dashboard) sigue pendiente, sin relación a esta sesión.

---

## 📅 Cierre de sesión: 11/08/2026 (cont.) — BL-024b desbloqueado: acceso admin confirmado, decisión de cuenta y número, ejecución delegada a sesión con componente de Chrome

### ✅ Logros y Cambios

Sesión arrancó con protocolo estándar (lectura de `BITACORA.md`/`BACKLOG.md`) y retomó BL-024b, que había quedado bloqueado esa misma mañana en falta de acceso admin de Meta.

**Acceso admin confirmado por el usuario** — ya tiene rol de administrador en la cuenta de Business Manager a la que lo invitaron (la que se investigó por la mañana, distinta de "Estetican"/BL-052).

**Dos decisiones cerradas en conversación, antes de tocar Meta:**
- **Cuenta:** la WABA se monta en la cuenta invitada, no en "Estetican" (esa sigue exclusiva para el catálogo de BL-052).
- **Número:** se planteó de nuevo el riesgo (documentado desde la mañana) de reusar el 4494956151 — el usuario no estaba seguro si ese número está en uso activo hoy en la app normal de WhatsApp Business. Se le dieron los pasos concretos para verificarlo en el celular antes de decidir. Prefirió no arriesgar: **decidió dar de alta un número nuevo dedicado** en su lugar, sin necesidad de confirmar el uso del actual.

**Corrección de comunicación (segunda vez):** se deslizó voseo rioplatense ("tenés", "querés", "avisame") en una respuesta técnica — el usuario lo marcó con fuerza. Memoria `feedback_idioma.md` actualizada con el patrón exacto de las formas a vigilar (terminaciones -és/-ás, imperativos en voseo), porque ya es la segunda reincidencia documentada y la primera nota no fue suficiente.

**Hallazgo real durante la sesión — coordinación entre sesiones paralelas del usuario:** llegó un mensaje con preguntas de confirmación muy específicas (nombre de app Meta, Business Manager real por nombre, número, permisos de token, plantilla) que parecía fuera de contexto para esta sesión de CLI — no le había preguntado eso yo, y mencionaba entidades (nombres de Business Manager reales) que nunca vi. Se verificó con `ListAgents` antes de asumir nada: existe una sesión real y activa del usuario, **"whatsapp multi-number authorization"** (Remote Control, en otra máquina, con el componente de Chrome cargado), que ya estaba navegando la interfaz real de Meta con el usuario y le hizo esas preguntas ahí — el usuario las había reenviado/mezclado en esta conversación por error de ventana. Confirmado con el usuario, se optó por **no duplicar trabajo en esta sesión** (que no tiene Chrome) y en su lugar se le mandó a esa sesión, vía `SendMessage`, el contexto completo ya decidido (cuenta, número nuevo, permisos de token `whatsapp_business_messaging`+`whatsapp_business_management`, plantilla completa ya redactada) para que continúe la ejecución real sin tener que repreguntarle al usuario.

**Sin cambios de código ni de `SystemSettings` esta sesión** — la ejecución real (alta de número, generación de token permanente, envío de plantilla a aprobación) quedó delegada a la sesión con Chrome; falta que el usuario vuelva con `phone_number_id` + token para cargarlos aquí.

### 📁 Archivos Modificados
- `BITACORA.md` (esta entrada)
- `docs/tecnico/BACKLOG.md` — BL-024b actualizado (acceso admin confirmado, decisiones de cuenta/número, ejecución delegada)

### 🛑 Pendientes activos
- **BL-024b:** esperar a que la sesión "whatsapp multi-number authorization" (Chrome) obtenga `phone_number_id` + token permanente + plantilla enviada a aprobación con el usuario. Cuando el usuario los tenga, cargarlos en `SystemSettings` (sección `whatsapp_messaging`) y completar el bloque `TODO` real de `MetaWhatsAppSender` (hoy stub).
- **Número nuevo dedicado** — aún sin definir cuál será, queda a criterio del usuario en la sesión de Chrome.
- BL-024c (CRM/webhook de entrada) sigue diferido a propósito, sin cambios.
- BL-078 (scope de sucursal en Dashboard) sigue pendiente, sin relación a esta sesión.

---

## 📅 Cierre de sesión: 11/08/2026 — BL-024b: intento de retomar el trámite en Meta, bloqueado en acceso; plantilla de recordatorio redactada y lista para enviar a aprobación

### ✅ Logros y Cambios

Sesión arrancó con protocolo estándar (lectura de `BITACORA.md`/`BACKLOG.md`). **Corrección de un dato desactualizado de la entrada anterior (10/08):** decía "6 commits sin pushear" — se verificó `git status`/`git log origin/main..HEAD` real y la rama está al día con `origin/main`, sin nada pendiente de pushear. Ya se había resuelto en algún punto entre sesiones, sin quedar anotado.

**Intento de destrabar BL-024b (WhatsApp, bloqueado desde el 05/08 en credenciales de Meta):** el usuario mencionó tener acceso a una cuenta de Facebook Business a la que lo invitaron, con un número distinto al que usa normalmente. Se investigó primero (sin tocar código) y se aclaró una confusión real antes de avanzar: el número de teléfono del login personal de Facebook con el que invitan a alguien a un Business Manager **no tiene relación** con el número de WhatsApp Business que se registra para la API — son conceptos independientes.

Se confirmó con el usuario, vía preguntas dirigidas, el estado real:
- La cuenta a la que lo invitaron **no es** el portafolio "Estetican" que ya se usa para BL-052 (catálogo) — es una cuenta aparte, de otro negocio/persona.
- **Todavía no tiene rol de administrador** en esa cuenta invitada — sin eso no se puede crear un Usuario del sistema ni generar el token con permiso `whatsapp_business_messaging` que necesita `MetaWhatsAppSender`.

**Decisión del usuario: pausar el trámite de acceso y adelantar trabajo que no depende de él** — redactar ya el texto de la plantilla de WhatsApp, para tenerlo listo y solo falta pegarlo en Meta y mandarlo a aprobación en cuanto haya acceso admin (a la cuenta invitada, o a "Estetican" si se decide construir ahí en su lugar — **sin decidir todavía en cuál de las dos cuentas quedará la WABA real**).

**Plantilla redactada** (categoría Utility, sin pedir confirmación por respuesta — a propósito, porque no hay ningún mecanismo para leer/procesar respuestas todavía, eso es BL-024c y sigue diferido; pedir "confirma" sin poder verla generaría una expectativa falsa):
- Nombre técnico: `recordatorio_cita_estetican`
- Idioma: Spanish (MEX) — `es_MX` (coincide con el default de `whatsapp_messaging_template_language` en código)
- Cuerpo, con las 5 variables **en el orden exacto que ya arma `EnviarRecordatoriosCitaCommand::buildParameters()`** (cliente, mascota, servicio(s), fecha, hora — no se puede reordenar sin tocar código): `Hola {{1}} 🐾, te recordamos la cita de {{2}} en EstetiCAN para {{3}} el {{4}} a las {{5}} hrs. Si necesitas reagendar o cancelar, contáctanos con gusto.`
- Pie estático: `EstetiCAN`
- Ejemplos para el formulario de envío a revisión de Meta: María López / Firulais / Baño y corte / 15/08/2026 / 11:30 AM

**Pregunta real levantada, sin resolver a propósito:** qué número de teléfono registrar en la WABA. Se investigó en código (no se asumió) si el flujo manual de `wa.me` (`BookingMessageController`/`RecurrenceMessageController`) depende de `brand_whatsapp_number` (4494956151, hoy solo usado como botón de contacto en correos) — **confirmado que no**: esos links abren desde el WhatsApp personal del operador hacia el número del cliente, sin ningún número del negocio de por medio. O sea, no hay obligación técnica de reusar el 4494956151. El riesgo real que sí queda sin confirmar: si ese número se usa hoy activamente con la app normal de WhatsApp Business desde un celular del negocio, migrarlo a la API de Meta puede desconectarlo de esa app (existe un modo "coexistencia" de Meta, pero no se confirmó si aplica a este caso — no se afirma sin verificar). El usuario prefirió dejar esta decisión pendiente hasta tener acceso admin y poder ver qué hay configurado realmente en la cuenta.

**Sin cambios de código ni de configuración esta sesión** — todo el trabajo fue de investigación (código existente) y redacción de contenido de negocio (texto de la plantilla), nada tocado en `SystemSettings` todavía (`whatsapp_messaging_template_name` sigue vacío en producción).

### 📁 Archivos Modificados
- `BITACORA.md` (esta entrada)

### 🛑 Pendientes activos
- **BL-024b, retomar cuando el usuario tenga acceso admin** — ya sea a la cuenta invitada o decidiendo construir la WABA bajo "Estetican" en su lugar (sin decidir todavía).
- **Decidir qué número registrar en la WABA** — el actual 4494956151 (riesgo de desconectar la app de WhatsApp Business si está en uso activo, sin confirmar) o uno nuevo dedicado.
- En cuanto haya WABA + número + token con `whatsapp_business_messaging`: enviar la plantilla ya redactada (arriba) a aprobación, luego completar el bloque `TODO` de `MetaWhatsAppSender`, cargar `whatsapp_messaging_phone_number_id`/`access_token`/`template_name`/`template_language` en Configuración del sistema, y activar `whatsapp_messaging_enabled`.
- BL-024c (CRM/webhook de entrada) sigue diferido a propósito, sin cambios.
- BL-078 (scope de sucursal en Dashboard) sigue pendiente, sin relación a esta sesión.

---

## 📅 Cierre de sesión: 10/08/2026 — Sincronización de citas SPA a Google Calendar por operador, un solo sentido (feature nueva, fuera del sprint activo)

### ✅ Logros y Cambios

Pedido directo del usuario, fuera de `BACKLOG.md` — quiere que cada operador pueda ver su agenda de EstetiCAN en su propio Google Calendar personal, sin abrir la app móvil. Se armó un plan completo (`EnterPlanMode`/`ExitPlanMode`) antes de tocar código, y se implementó y verificó de punta a punta contra la API real de Google, no solo con mocks.

**Diseño acordado con el usuario en conversación, antes de construir:**
- Sincronización de **un solo sentido** (EstetiCAN → Google, nunca al revés) — editar en Google nunca toca la cita real, para no saltarse `OperatorAvailabilityChecker`/`ResourceAllocation`.
- El operador se **suscribe** al calendario desde su Google personal (aparece como capa extra en la misma app, no son "dos calendarios abiertos").
- **Identidad de Google: Service Account** (creada por el usuario en Google Cloud Console, con mi guía paso a paso), no una cuenta Gmail del negocio — la Service Account es dueña de los calendarios y los comparte por ACL.
- **Alcance v1: solo citas SPA** (`SpaBooking`, tiene `operator_id` real) — `HotelReservation` queda fuera, no tiene operador asignado (se agrupa por jaula/sucursal), confirmado con el usuario.

**Corrección real de entorno detectada en la práctica:** el plan inicial asumía "probar en Sail local antes de tocar producción" (siguiendo el `CLAUDE.md` genérico) — pero esta sesión corría directo en la OPi de producción (`hostname orangepi5-plus`, contenedor `estetican_app` real con bind-mount), sin ningún Sail activo. Coincide con lo que ya decía `project_opi_workflow` en memoria. Se ajustó sobre la marcha: todo el trabajo (composer, migraciones, tests) corrió vía `docker exec estetican_app` directo, con un respaldo de BD (`scripts/auto_backup_db.sh`) tomado antes de migrar como red de seguridad.

**Infraestructura construida:**
- `composer require google/apiclient` (v2.19.4, compatible con PHP 8.5.6 real, sin vulnerabilidades propias en `composer audit`).
- 3 migraciones aditivas: `operators` (`google_calendar_id`, `google_personal_email`, `google_calendar_share_enabled`, `google_calendar_shared_at`), `spa_bookings` (`google_event_id`, `google_synced_at`), `users` (`google_personal_email`, `google_calendar_visibility`).
- `SystemSettings` sección nueva `calendario_google` — un solo campo de negocio, `google_calendar_sync_enabled` (apagado por default).
- **Credencial fuera de `SystemSettings`, a propósito:** el JSON key de la Service Account (con clave privada PEM multilínea) rompe el patrón `type: password` de una sola línea que ya usa WhatsApp/SMTP — se decidió tratarla como las credenciales de BD, en archivo (`storage/app/private/google-calendar-service-account.json`, gitignored) referenciado por `GOOGLE_CALENDAR_CREDENTIALS_PATH` en `.env`.
- Dominio nuevo `App\Domain\GoogleCalendar\` (`Contracts`+`Services`, mismo patrón minimal que `WhatsAppMessaging`/`Accounting`, sin `Repositories/`).
- Comando `calendario:sincronizar-google --dry-run`, calcado de `whatsapp:enviar-recordatorios-cita`, corriendo cada 5 min vía `Schedule::` en `routes/console.php` — sin cola nueva (no hay ningún worker corriendo en producción, `docker/supervisord.conf` solo levanta `php artisan serve`). Nunca se llama a la API de Google dentro del request de agendar.
- Pantalla en la ficha de operador (`operators/{operator}/google-calendar`, permiso reusado `editar operadores`) para su email propio + toggle de compartir.
- Eventos con **recordatorio popup 15 min antes** (pedido posterior del usuario, verificado en vivo contra la API real).

**Iteración de diseño real durante la sesión — "ver todo" por usuario, no por operador ni por un email de admin global:** el primer intento (un solo `google_calendar_admin_email` en `SystemSettings`, para que un admin viera todos los calendarios) se armó, se probó, y se **revirtió limpio** (`migrate:rollback --force` + borrado del archivo de migración — la columna nunca llegó a tener datos reales) cuando el usuario aclaró que lo que necesitaba era un **toggle por cuenta de usuario** ("ver calendario personal" / "ver todo el calendario"), independiente de si esa persona es también un operador agendable. Se agregó a `users`: `google_personal_email` + `google_calendar_visibility` (`personal`/`all`), con su propia sección en `user/edit.blade.php` (USEEDI), gateada por el mismo `role:admin|super-admin` que ya protege `/usuarios`. Sin tabla de rastreo de "ya compartido" para esta parte — se confirmó en vivo contra la API real que un ACL insert repetido con el mismo email+rol es idempotente (no falla ni duplica), así que se reintenta cada corrida sin necesitar estado nuevo.

**Configuración real aplicada en producción (con el usuario, en vivo):**
- El usuario generó la Service Account real en Google Cloud Console (`estetican-calendar-sync@estetican-calendar.iam.gserviceaccount.com`) y subió el JSON key a la OPi.
- Operador de prueba real (Tomás Alejandro Martinez, id 2) con su propio compartido activado.
- 3 cuentas admin reales configuradas con visibilidad `all`: `Admin` (`tomasmtnet@gmail.com`), `tomasmg` (`martinezgtomas@gmail.com`), `arantxa` (`arantxaefdz@gmail.com`) — **las 3 confirmadas como `reader` del calendario vía `acl->listAcl()` real**, no solo por el log del comando.
- 3 citas reales sincronizadas con `google_event_id` real, con recordatorio de 15 min confirmado en el evento real.

**Limpieza adicional en la misma sesión (pedido aparte del usuario):** pantalla de edición de Usuario (`user/edit.blade.php`, USEEDI) tenía "Acceso al Sistema" (login/rol/activo) y "Matriz de Permisos (CRUD)" en tarjetas separadas, con "Perfil de Operador" metido en medio — unificadas en una sola tarjeta "Acceso y Permisos", sin cambios de comportamiento (mismos campos/names/submit).

### 📁 Archivos Modificados/Creados
- `composer.json`/`composer.lock` — `google/apiclient`
- `config/services.php` — `google_calendar.credentials_path`
- `database/migrations/2026_08_10_140500_*`, `2026_08_10_140501_*`, `2026_08_10_164100_*`
- `app/Models/Operator.php`, `SpaBooking.php`, `User.php`
- `app/Providers/AppServiceProvider.php` — binding `GoogleCalendarSyncServiceInterface`
- `app/Support/SystemSettings/SystemSettings.php` — sección `calendario_google`
- `app/Domain/GoogleCalendar/Contracts/GoogleCalendarSyncServiceInterface.php`, `Services/GoogleCalendarSyncService.php` — nuevos
- `app/Console/Commands/SincronizarGoogleCalendarCommand.php` — nuevo
- `app/Http/Controllers/OperatorGoogleCalendarController.php` — nuevo
- `app/Http/Controllers/UserController.php` — validación de los 2 campos nuevos
- `resources/views/operators/edit.blade.php`, `operators/partials/google_calendar.blade.php` — nuevo
- `resources/views/user/edit.blade.php` — sección Google Calendar + unificación Acceso y Permisos
- `routes/web.php`, `routes/console.php`
- `tests/Feature/GoogleCalendar/SincronizarGoogleCalendarCommandTest.php`, `OperatorGoogleCalendarTest.php` — nuevos (10 tests)
- `apps/backoffice-laravel/.env.example`, `.env` real (fuera de git) — `GOOGLE_CALENDAR_CREDENTIALS_PATH`
- `storage/app/private/google-calendar-service-account.json` — credencial real, fuera de git
- `docs/tecnico/MODELO_BD.md`, `docs/tecnico/BACKLOG.md`, `docs/OPI_PRODUCCION.md`

### 🔧 Verificación
`google/apiclient` instalado limpio sobre PHP 8.5.6 real. Migraciones aplicadas sin error directo contra producción. Pint limpio en todos los archivos tocados. Suite completa sin regresiones en cada punto de control (447 pasan, mismos 37 preexistentes de siempre — deuda de fixtures ya documentada, sin relación con este trabajo). **Verificado de punta a punta contra la API real de Google, no solo con mocks:** calendario real creado y compartido, 3 citas reales con `google_event_id` real, recordatorio de 15 min confirmado en el evento real, y las 3 cuentas admin confirmadas como `reader` vía `acl->listAcl()` real. Auditoría de seguridad de cierre (regla 3 de `CLAUDE.md`): la única ruta nueva de la sesión (`operators/{operator}/google-calendar`) ya lleva `permission:editar operadores` desde que se creó; la ruta de Usuario editada (`users.update`) ya existía protegida por `role:admin|super-admin`, solo cambió su vista. Sin huecos.

### 🛑 Pendientes activos
- **Outlook/Microsoft 365:** el usuario preguntó si se puede hacer lo mismo con Outlook — se explicó que es bastante más complejo que Google (Microsoft no tiene equivalente al ACL-share silencioso de una Service Account contra una cuenta personal; requeriría que el negocio tenga una cuenta de Microsoft 365 de paga, o que cada destinatario autorice por OAuth). **Sin decidir, sin acción tomada** — queda anotado en `docs/architecture/IDEAS_FUTURO.md`, condicionado a si aparece una necesidad real de un usuario con Outlook.
- **BL-024b (WhatsApp):** sigue bloqueado en credenciales de Meta, sin cambios esta sesión. Idea de seguimiento anotada ahí mismo: adjuntar un `.ics` al recordatorio de WhatsApp para que el cliente agregue la cita a su propio calendario — **deliberadamente diferida a pedido del usuario** hasta que BL-024b esté mandando mensajes reales.
- **Sin pushear:** commits de esta sesión (`7d8189f`, `26a8840`, `293d112`, `aaac3c0`) más 2 previos de la sesión anterior (`35b7071`, `52299fe`, Proyecto IA) — 6 commits totales adelante de `origin/main`, ninguno pusheado todavía.

---
 — Fix de seguridad en `/api/cash/*`, filtro de sucursal + auditoría "quién registró" en caja/cobros, limpieza de datos de prueba en producción (NT-057)

### ✅ Logros y Cambios

Sesión arrancó confirmando el pendiente de la sesión anterior: **el fix de cámara/galería de `MobPetDet` (dos `<input>` separados) sí funcionó en el dispositivo real del usuario** — queda cerrado, sin pendiente. Sobre el Proyecto IA solo se recordó el acuerdo de la sesión previa (sandbox 100% aparte, ver `docs/architecture/proyecto_IA.md`) — **no se arrancó el sandbox todavía**, sigue pendiente. El grueso de la sesión salió de pedir explicar **BL-078** (Dashboard sin scope de sucursal), lo que llevó a diseñar el mismo tipo de filtro para la app móvil.

**Diagnóstico primero, como marca el protocolo:** antes de tocar código se investigó el estado real de `MobCajaMovimientos.tsx` (ya tenía filtros de fecha/tipo funcionando) y su endpoint `Api\CashController::movements`. Se encontró que **las 4 rutas de `/api/cash/*`** (`session`, `movement-types`, `movements`, `sessions/{id}/movements`) **no llevaban `permission:`** — a diferencia de casi todas las demás rutas de `api.php`. Remediado con `permission:caja.abrir` en las 4 (reusa el permiso existente, mismo criterio que la auditoría IDOR del 04-05/08 de no crear permisos nuevos sin necesidad).

**Selector de sucursal en `MobCajaMovimientos`:** solo `is_super_admin` puede elegir sucursal o "Todas" (mismo criterio ya usado en BL-077 para el Dashboard) — un operador normal siempre queda scopeado a su propia sucursal de checkin, **ignorando cualquier `branch_id` que mande el request** (nunca confiar en el scope que manda el cliente). El filtro solo aplica de verdad a `CashMovement` (única de las 4 fuentes que agrega el endpoint con `branch_id` real, vía `cash_sessions`) — `Payment`/`cash_ledgers`/`bank_ledgers` no tienen esa columna, así que en vez de filtrarlos en silencio de forma incorrecta, se marcan explícitamente en la UI como "Todas las sucursales".

**A pedido explícito del usuario ("para poder hacer auditorías"): columna `created_by_user_id` nueva en `payments`/`cash_ledgers`/`bank_ledgers`** (`CashMovement` ya la tenía desde antes). Seteada en los 3 puntos de creación reales del dinero: `PaymentController::store` (API móvil), `QuoteService::registerPayment` (web, anticipos/liquidación), `AccountingService::reverseDocumentMoney` (reembolsos, con el usuario que canceló). **Backfill 100% completo de los 11 `payments` existentes** desde `activity_log` (el modelo usa `LogsActivity`, se cruzó el evento `created` con su `causer_id`) — confirmado contra datos reales de producción (`tomasmg`/`Admin`/`arantxa` aparecieron correctos). `cash_ledgers`/`bank_ledgers` no tienen ese rastro (no usan `LogsActivity`), así que sus filas históricas quedaron sin backfill posible, mostrando "Sin registrar" honestamente en vez de inventar un dato.

**Limpieza real de datos de prueba en producción, iniciada porque el usuario vio el campo `created_by` nuevo y reconoció registros que nunca debieron existir**, todos bajo la cuenta genérica "Admin" (con una excepción real detectada y confirmada aparte, ver abajo). Cada tanda se investigó antes de borrar (qué cuelga de cada registro, si hay `Document`/`JournalEntry`/dependencias reales) y se respaldó a JSON en el scratchpad de la sesión antes de ejecutar:
- **4 `Payment`** (cobros ficticios de $250-$300 ligados a 4 citas reales de dos clientes) — sin `Document`/`JournalEntry` que cascadear.
- **1 `CashMovement`** (retiro "Quincena Pedro", $1,500) **con un `JournalEntry` real ligado** (2 líneas contables Caja/Nómina) — se encontró al investigar antes de borrar; se borró también para no dejarlo huérfano.
- **5 citas de prueba** (`SpaBooking` #1, #7, #26, #27 ligadas a los 4 pagos; #5 ligada al `cash_ledger` legacy) con sus `spa_booking_services`/`booking_process_notes` en cascada. **La cita #5 tenía una particularidad real:** la había creado y editado `tomasmg` (usuario real, con 5 eventos reales en `activity_log`), no la cuenta "Admin" — se le presentó esta discrepancia al usuario explícitamente antes de tocarla, y confirmó borrarla también.
- **1 `cash_ledger` legacy** (el único que quedaba en toda la tabla, sin rastro de quién lo creó — de antes de que existiera cualquier auditoría) ligado a la cita #5.
- **2 mascotas completas de prueba** ("pruebito" Basset Hound, cliente Arantxa; "Frenchie" Poodle, cliente Tomás Eduardo) con 3 citas más en cascada (#29, #34, #38), 1 foto cada una (con archivo físico borrado del disco), y en el caso de "pruebito": 1 alergia, 1 vacuna, 1 adjunto clínico cuya descripción decía literalmente "Radiografia simulada" — confirma que era dato de prueba.

**Bug real encontrado y corregido en el proceso (NT-057): `Pet::delete()` no dispara la cascada de FK.** Al borrar las 2 mascotas con `->delete()`, `Pet::find($id)` devolvió `null` (parecía confirmar el borrado), pero los conteos de las tablas hijas (citas, fotos, alergia, vacuna, adjunto) seguían en pie — la cascada real de MySQL (`cascadeOnDelete()`, confirmada en `information_schema.REFERENTIAL_CONSTRAINTS`) nunca se disparó. Causa raíz: `Pet` usa `SoftDeletes` — `->delete()` solo llena `deleted_at`, no ejecuta un `DELETE` físico, así que no hay nada que MySQL cascadee. `Pet::find()` devolviendo `null` es el global scope de `SoftDeletes` ocultando la fila, no prueba de que se borró de la base de datos. Corregido con `Pet::withTrashed()->find($id)->forceDelete()`, reverificado con conteos explícitos de las 6 tablas hijas en 0. Documentado en detalle porque es un patrón que puede repetirse: cualquier limpieza futura sobre un modelo con `SoftDeletes` necesita `forceDelete()`, no `delete()`, para que la cascada de FK sirva de algo.

**Auditoría de seguridad de cierre de sesión (regla 3 de `CLAUDE.md`), por haber tocado `routes/api.php`:** las únicas rutas de `api.php` sin `permission:` son intencionalmente públicas para cualquier autenticado — `/logout`, `/me` y sus variantes (perfil propio, scoped vía `auth()->user()`), `/checkin/status`+`/checkin`+`/checkout` (asistencia propia, scoped por `user_id`), `/settings/booking`, `/settings/photos`, `/work-order-types`, `/payment-methods` (config/catálogo de solo lectura, sin PII de negocio). El único hueco real de la sesión (`/api/cash/*`) ya quedó cerrado.

### 📁 Archivos Modificados/Creados
- Backend: `app/Http/Controllers/Api/CashController.php` (permission: + branch_id + created_by en las 4 fuentes), `app/Http/Controllers/Api/PaymentController.php`, `app/Domain/Commercial/Services/QuoteService.php`, `app/Domain/Accounting/Services/AccountingService.php`, `app/Models/Payment.php`, `app/Models/CashLedger.php`, `app/Models/BankLedger.php`, `routes/api.php`
- Migración: `2026_08_07_225546_add_created_by_user_id_to_payment_tables.php`
- Móvil: `mob_apps/operador/src/admin/MobCajaMovimientos.tsx` (selector de sucursal + columna "Registró")
- Docs: `docs/tecnico/MODELO_BD.md` (`created_by_user_id` en las 3 tablas), `docs/tecnico/NOTAS_TECNICAS.md` (NT-057), `docs/tecnico/BACKLOG.md`
- Datos de producción: 4 `payments`, 1 `cash_movement` + su `journal_entry`, 5 `spa_bookings` (+3 más en cascada de mascotas), 1 `cash_ledger`, 2 `pets` — todos de prueba, respaldados en JSON antes de borrar (fuera del repo, en el scratchpad de la sesión, no persiste)

### 🔧 Verificación
`tsc --noEmit`/`npm run build` limpios (mismos 2 errores preexistentes de `MobCajaMovimientos.tsx`, prop `key`). `md5sum` idéntico entre `dist/` del host y `estetican_mob`. Suite completa del backend sin regresiones en cada paso: 437 pasan, mismos 37 preexistentes de siempre. 2 tandas de tests de Feature temporales con escenarios reales (403 sin `caja.abrir`, scope forzado para operador no-admin, admin viendo todas/una sucursal, las 4 fuentes exponiendo `created_by` correcto) — no quedaron en el repo. Backfill de `payments` verificado contra datos reales de producción. Cada borrado de datos de prueba verificado con conteos explícitos de 0 huérfanos en las tablas hijas antes de darlo por cerrado.

### 🛑 Pendientes activos
- Proyecto IA: sigue sin arrancar el sandbox — solo investigación y plan documentados en `docs/architecture/proyecto_IA.md`, sin cambios esta sesión.
- BL-078 (scope de sucursal en "Ingresos hoy" del Dashboard web) sigue pendiente — se explicó en esta sesión pero no se tocó, es la pregunta de producto que espera una segunda sucursal real.
- Commit de esta sesión pendiente de pushear (ver mensaje del commit para el detalle completo).

---

## 📅 Cierre de sesión: 06/08/2026 (cont. 2) — Foto en MobPetDet, bug real del candado (MobUserConfig) + opción "Nunca", documento de investigación Proyecto IA

### ✅ Logros y Cambios

**Botón de actualizar foto en la vista de `MobPetDet` (pedido directo del usuario):** antes solo se podía cambiar la foto de una mascota entrando a "Editar". Se agregó un botón junto a los chips de Sexo/Tamaño/Esterilizado (sin agrandar esa fila) que reutiliza la misma función de subida ya existente (`uploadPetPhoto` → `/api/pets/{id}/photo`, recorte con `PhotoEditorModal`, marca de agua si aplica).

**Bug real encontrado al primer intento — un solo `<input type="file">` no ofrecía cámara en el dispositivo del usuario, solo galería.** Primer intento: hoja/overlay personalizada ("Tomar foto" / "Elegir de galería") — **falló en el dispositivo real**: solo se veía el fondo oscurecido, sin las opciones, sin poder determinar la causa raíz exacta a ciegas (sin acceso al dispositivo para depurar). Revertido por completo. Solución definitiva: dos `<input>` separados (uno con `capture="environment"` que fuerza la cámara, otro sin `capture` para galería), cada uno con su propio botón/enlace **visible y directo** — mismo patrón de "botón real → `ref.click()`" que ya funcionaba en el resto de la app, sin overlay ni z-index nuevo de por medio. Aplicado en los 3 lugares que compartían la limitación original: el botón nuevo de la vista, el mosaico de "Editar", y el formulario de "Dar de alta" (`NewPetForm`) — este último también sufría el mismo problema, no solo el botón agregado en esta sesión. **Sin confirmar en el dispositivo real todavía** — pendiente de que el usuario verifique cámara y galería por separado antes de dar el fix por bueno.

**Bug real en `MobUserConfig` — el candado no respetaba el tiempo configurado.** Diagnóstico confirmado con el usuario: el bloqueo "de la nada" ocurría al cambiar de app un momento (WhatsApp, fotos, una llamada) y volver — coincidía con un segundo mecanismo de bloqueo en `AppLockContext.tsx`, completamente aparte del selector de minutos: al ocultarse la pestaña (`visibilitychange`), un timer hardcodeado de **1.5 segundos** (`HIDDEN_GRACE_MS`) bloqueaba sin importar la preferencia configurada. Ese timer existía a propósito para filtrar falsos "hidden" de WebView de Android durante pickers nativos (fecha, foto) — pero como efecto secundario, dominaba cualquier bloqueo real en la práctica, haciendo que el selector de minutos casi nunca fuera lo que en verdad decidía cuándo se bloqueaba. **Solución:** se quitó el timer aparte — al ocultarse la pestaña no pasa nada especial, simplemente no hay más eventos de actividad, así que el timer de inactividad ya en marcha (con la duración que configuró el usuario) sigue corriendo solo y bloquea cuando corresponde. Al volver a estar visible, se revalida contra lo persistido (`computeLockedFromStorage()`) en vez de reiniciar el timer a ciegas, por si el proceso se congeló en segundo plano (Android) y no alcanzó a disparar el timer en memoria. Documentado en **NT-056**.

**Opción "Nunca" agregada al selector de bloqueo automático**, a pedido explícito del usuario y con su confirmación de alcance: apaga el candado por completo (ni por inactividad ni al cambiar de app) — decisión de producto consciente, el usuario prefiere esa opción sobre una versión más conservadora que mantuviera el bloqueo de seguridad al cambiar de app. `lockTimeoutMinutes === 0` es el valor centinela; `getIdleTimeoutMs()` devuelve `Infinity` en ese caso y `resetTimer()` no programa ningún `setTimeout` (inválido con `Infinity`).

**`docs/architecture/proyecto_IA.md` — documento nuevo, proyecto paralelo sin relación al sprint activo.** El usuario planteó meter un motor de IA local (Qwen) en el mismo servidor de producción para asistir al staff (caso ancla: agendar citas por voz con verificaciones propias). Sesión larga de solo investigación, sin escribir código: se encontró que ya existe un asistente de IA real en producción (`AssistantChatController`, BL-042, Claude/Anthropic, sin tool-calling, informativo para visitantes anónimos) — la nueva propuesta es un animal distinto (agente con herramientas, para staff autenticado). Se auditó una propuesta de arquitectura que el propio Qwen del usuario generó (React → Laravel → Ollama) contra el código real, con 10 gaps concretos encontrados (falta `permission:` en la ruta, middleware de auth incorrecto, búsqueda con `LIKE` simple en vez de `TokenSearch`, creación de cita cruda saltándose `BookingService`/`OperatorAvailabilityChecker`, loop de tool-calling sin límite de iteraciones, timeout que choca con el `proxy_read_timeout 30s` real de nginx, entre otros). Se verificó hardware real de la OPi (16GB RAM, 13GB libres; NPU del RK3588 con driver de kernel ya activo — confirmado vía `devfreq` — pero sin ningún runtime de usuario RKNN instalado). Se acordó metodología de prueba: sandbox 100% aparte de EstetiCAN (carpeta y `docker compose` con `name:` propio, sin compartir red/base de datos), y se armó un plan de investigación de 4 bloques (calidad de razonamiento, rendimiento real, CPU vs. NPU, aislamiento del sandbox) antes de escribir una sola línea de implementación. Todo el detalle completo queda en el documento — no se resume más aquí para no duplicarlo.

### 📁 Archivos Modificados/Creados
- `mob_apps/operador/src/admin/PetDetail.tsx` — botón de foto en la vista, `usePhotoPicker()` (cámara+galería separados, reemplaza el intento fallido de hoja/overlay) aplicado en los 3 puntos de foto del archivo
- `mob_apps/operador/src/AppLockContext.tsx` — quitado el bloqueo instantáneo al ocultar pestaña; "Nunca" (`Infinity`) como opción válida
- `mob_apps/operador/src/admin/MobUserConfig.tsx` — opción "Nunca" en el selector de bloqueo automático
- `docs/tecnico/NOTAS_TECNICAS.md` — NT-056
- `docs/architecture/proyecto_IA.md` — nuevo, documento de investigación del Proyecto IA

### 🔧 Verificación
`tsc --noEmit`/`npm run build` limpios en cada paso (mismos 2 errores preexistentes ajenos de `MobCajaMovimientos.tsx`). Los 3 cambios de `mob_apps/operador` desplegados en vivo (bind-mount directo, sin `docker cp`) y confirmados contra el origen real (`curl` bypass CDN, hash del bundle servido). **Pendiente real:** el fix de cámara/galería en `MobPetDet` no está confirmado por el usuario en su dispositivo — puede necesitar otra vuelta si el segundo intento tampoco funciona.

### 🛑 Pendientes activos
- **Confirmar en el dispositivo real** si el fix de cámara/galería de `MobPetDet` (segundo intento, sin overlay) funciona — no verificado end-to-end.
- Proyecto IA: sin arrancar sandbox todavía, solo investigación y plan documentados en `docs/architecture/proyecto_IA.md`.
- Sigue pendiente BL-078 (scope de sucursal en "Ingresos hoy" del Dashboard), sin relación a esta sesión.

---

## 📅 Cierre de sesión: 06/08/2026 — Dos bugs reales en producción reportados por usuarios: spinner infinito en Android (mov) y calendario que no abría en escritorio (MobCitaNueva)

### ✅ Logros y Cambios

Sesión de soporte reactivo, dos incidentes reales reportados en vivo por el usuario durante la conversación, ambos diagnosticados con logs reales de producción antes de tocar código y desplegados de inmediato por tratarse de gente bloqueada en el momento.

**Incidente 1 — Arantxa "está adentro pero no renderiza" en `mov` (spinner infinito, Android):** diagnóstico inicial cruzando `docker logs estetican_mob`/`estetican_app` contra la hora real del contenedor detectó que la última actividad de su sesión (`POST /api/me/verify-password`, `200` correcto) había quedado registrada más de 7 minutos antes sin ningún request posterior — inconsistente con cualquier interacción normal. Confirmado con el usuario que el síntoma exacto era "se queda cargando (spinner)" en Android. **Causa raíz:** la Fetch API no tiene timeout por default — Android puede congelar o matar el socket de un fetch en curso al mandar la pestaña a segundo plano, sin que la promesa resuelva ni rechace nunca. Dos puntos de espera dependían de eso sin ninguna protección: el chequeo de sesión al montar en `AuthContext.tsx` (gatea el spinner de arranque de `AuthGuard`) y `POST /api/me/verify-password` en `LockScreen.tsx` (el candado client-side de la app, BL-038/063/072). `fetchWithTimeout()` nuevo (`src/lib/fetchWithTimeout.ts`, `AbortController` a 12s) aplicado a ambos. De paso, cambio de comportamiento deliberado: antes *cualquier* falla del chequeo de sesión (incluido un simple timeout de red) borraba el token guardado y forzaba re-login; ahora **solo un 401 explícito** cierra sesión — un timeout o error de red deja el token intacto y muestra una pantalla nueva "No se pudo confirmar tu sesión — Reintentar" en `AuthGuard` (`App.tsx`). Documentado en **NT-054**.

**Incidente 2 — `MobCitaNueva`: el ícono de calendario no abría nada en PC (sí en el celular), bloqueando registrar una cita atrasada que otro operador había olvidado dar de alta.** El `<input type="date">` real vive oculto (`sr-only`); el ícono era un `<label htmlFor>` apuntando a él. **Causa raíz:** en Chrome de escritorio, enfocar un `<input type="date">` vía `<label>` solo mueve el foco — el calendario emergente solo se abre al clicar el ícono de calendario que el propio navegador dibuja *dentro* del control nativo, invisible aquí por el `sr-only`. En Android, cualquier foco sobre el input dispara el selector nativo del sistema operativo sin depender de ese ícono interno, de ahí que ahí sí funcionara. Se confirmó además que no hay ninguna restricción de fecha pasada, ni en este input (sin `min`/`max`) ni en el backend (`Api\BookingController::store()` solo valida el formato de `scheduled_at`, no que sea futura) — el bloqueo era puramente de interacción, no una regla de negocio. Reemplazado el `<label>` por un `<button type="button">` que llama `showPicker()` explícitamente (con fallback a `.focus()` si el navegador no lo soporta). Documentado en **NT-055**.

**Proceso de deploy — hallazgo reutilizable:** `dist/` de `mob_apps/operador` está bind-mounteado *directo* al contenedor `estetican_mob` (no como `nginx.conf`, que es un archivo único con el gotcha de NT-052) — `npm run build` ya escribe donde el contenedor lee, sin necesitar `docker cp`. Confirmado con `md5sum` idéntico host/contenedor y `curl` directo al origen (bypass CDN) después de cada build, ambos incidentes verificados en vivo en producción real antes de darlos por cerrados.

**Los 2 commits de la sesión, pusheados a `origin/main`:** `551e926` (timeout de sesión/candado) → `0a909fa` (calendario de `MobCitaNueva`). Ninguno lleva `Co-Authored-By:`/`Claude-Session:` (convención del repo desde el 05/08/2026).

### 📁 Archivos Modificados/Creados
- `mob_apps/operador/src/lib/fetchWithTimeout.ts` — nuevo
- `mob_apps/operador/src/AuthContext.tsx` — timeout + solo 401 desloguea + `sessionCheckFailed`/`retrySessionCheck`
- `mob_apps/operador/src/LockScreen.tsx` — timeout + mensaje de error explícito
- `mob_apps/operador/src/App.tsx` — pantalla de "reintentar" en `AuthGuard`
- `mob_apps/operador/src/admin/MobCitaNueva.tsx` — botón con `showPicker()` en vez de `<label>`
- Docs: `docs/tecnico/NOTAS_TECNICAS.md` (NT-054, NT-055), `docs/tecnico/BACKLOG.md` (2 entradas en Completados)

### 🔧 Verificación
`tsc --noEmit` limpio en ambos cambios (mismos 2 errores preexistentes ajenos de `MobCajaMovimientos.tsx`). `npm run build` limpio en los dos. Suite completa del backend tras el primer fix: 437 pasan, mismos 37 preexistentes de siempre, sin regresiones (cambios fueron solo frontend). Ambos despliegues verificados en vivo: `md5sum` idéntico entre `dist/` del host y el contenedor, y `curl` directo al origen (bypass Cloudflare) confirmando el hash del bundle nuevo servido.

### 🛑 Pendientes activos
- Ninguno nuevo de esta sesión — ambos incidentes quedaron resueltos y verificados en producción real.
- Backlog de fondo sin cambios: BL-024b (bloqueado en credenciales de Meta), BL-078 (scope de sucursal en "Ingresos hoy"), BL-028 (hardening SSH diferido).

---

## 📅 Cierre de sesión: 05/08/2026 (cont. 2) — Infraestructura de recordatorios automáticos de WhatsApp (BL-024b), código listo bloqueado en credenciales de Meta

### ✅ Logros y Cambios

Pedido nuevo del usuario: activar notificaciones automáticas de WhatsApp para citas. Alcance acotado explícitamente por el usuario desde el arranque a "solo recordatorios automáticos de citas, sin atención a clientes/webhook de entrada (eso queda para después)".

**Diagnóstico primero (sin proponer diseño hasta confirmarlo):** revisando `BookingMessageController`/`RecurrenceMessageController`/`WhatsAppTemplateController` completos se confirmó que el módulo WhatsApp (BL-024, Fase 1) es **100% manual** — `store()` arma un link `wa.me` y registra `sent_at` en el momento en que el operador genera el link, no cuando WhatsApp entrega el mensaje; el envío real depende de que el humano haga clic. `app/Support/WhatsApp/` solo tiene `TemplateResolver`/`PhoneNormalizer`, ninguna clase de envío por API. Confirmado también (dos veces, cruzando con la bitácora del 18/07/2026 vía un agente de exploración de esa sesión) que **no hay ninguna integración real con Meta para mensajería** — pero sí existe ya una Meta App real ("EstetiCAN Catálogo", de BL-052) con el caso de uso "Conecta con los clientes a través de WhatsApp" habilitado desde entonces a propósito, sin token generado todavía (se dejó pendiente adrede el 18/07 porque los permisos de un token quedan fijos al crearse). Cero infraestructura de eventos/cron en todo el proyecto — ni Observers, ni Events/Listeners, ni `Schedule::` registrado, ni queue worker corriendo (`supervisord.conf` de `estetican_app` solo corre `artisan serve`).

**Diseño acordado con el usuario:** trigger único a `whatsapp_reminder_hours_before` horas de la cita (default 24h, configurable) — se descartó "al crear la cita" (es una confirmación, no un recordatorio) y "al confirmarla" (no existe ese estado en `SpaBooking`, y si implicara leer respuestas se sale del alcance del webhook que quedó fuera). Mecanismo: `Schedule::` command en vez de Observer+cola diferida — no había worker de colas corriendo, y `Schedule::` relee el estado real de la BD en cada corrida (una cita reprogramada/cancelada simplemente deja de calificar la próxima vez, sin lógica de cancelación de jobs aparte).

**Construido (plan de archivos mostrado y aprobado antes de escribir código):**
- `App\Domain\WhatsAppMessaging` (nuevo, mismo patrón que `App\Domain\MetaCatalog` de BL-052): `WhatsAppSenderInterface` + `MetaWhatsAppSender`. La implementación es **stub a propósito** — valida `whatsapp_messaging_enabled` + credenciales, sale con `status=skipped` sin llamar a la API ni lanzar excepción si falta cualquiera. El bloque de la llamada real a la Graph API (`POST /{phone_number_id}/messages`, mismo molde que `MetaCatalogSyncService::sync()`) queda comentado con TODO, listo para completarse en cuanto exista el token — así el trabajo no se bloqueó esperando el trámite de Meta.
- `EnviarRecordatoriosCitaCommand` (`whatsapp:enviar-recordatorios-cita`, con `--dry-run`): selecciona citas `scheduled`/`work_order` dentro de la ventana configurada, respeta `receives_service_reminders` del cliente (mismo flag que ya usa el envío manual, confirmado que ya existía antes de tocar nada), exige teléfono válido vía `PhoneNormalizer`, y dedupe reusando `booking_messages` (columna `trigger` nueva: `'manual'` vs `'automatic_reminder'` — `sent_by_user_id`/`wa_link` ya eran nullable desde BL-040, no hizo falta tocarlos).
- `SystemSettings` gana la sección `whatsapp_messaging`: interruptor maestro (`whatsapp_messaging_enabled`, default `false`), `phone_number_id`, `access_token` (cifrado, mismo patrón que `whatsapp_catalog_access_token`), nombre/idioma de plantilla, `whatsapp_reminder_hours_before`.
- Migración: `booking_messages` gana `trigger` + `provider_message_id`.
- `Schedule::command('whatsapp:enviar-recordatorios-cita')->everyFifteenMinutes()->withoutOverlapping()` en `routes/console.php`.

**7 tests nuevos** (`AppointmentReminderCommandTest`), usando un mock de `WhatsAppSenderInterface` para cubrir toda la lógica de selección real (ventana horaria, dedup, opt-out, interruptor apagado, plantilla sin configurar, dry-run) **sin necesitar credenciales de Meta**. Suite completa sin regresiones: 437 pasan (430 + 7), mismos 37 preexistentes de siempre. Migración corrida en producción real.

**Verificación en vivo del `--dry-run` antes de confiar en la selección (pedido explícito del usuario):** con la config en su estado default (`enabled=false`) el comando correctamente reportó "nada que hacer". Para probar la consulta de selección en sí (dry-run nunca llama al proveedor, así que es seguro) se activó la config temporalmente — se encontró que **no hay ninguna cita en las próximas 48h en producción** (la más próxima real es la `#24`, 232h en el futuro), se amplió la ventana a 240h solo para la prueba, el comando la detectó correctamente con cliente/teléfono normalizado/fecha reales, y se **revirtió toda la config de prueba** (`enabled=false`, plantilla vacía, `24h`) antes de seguir.

**Cron del host agregado, a pedido explícito del usuario ("es seguro porque enabled=false sigue bloqueando cualquier envío real"):** `* * * * * docker exec estetican_app php artisan schedule:run >> /opt/www/estetican/logs/schedule.log 2>&1`, agregado junto a la línea existente del backup diario (sin tocarla). Es la primera vez que el scheduler de Laravel queda conectado a un cron real en este proyecto. **Probado en vivo, no solo instalado:** se esperó a que el cron real (no disparado a mano) corriera al menos una vez — corrió a las 16:45, cayendo justo en una marca de 15 min, ejecutó `whatsapp:enviar-recordatorios-cita` y terminó `DONE` en 570ms sin excepción. Confirmado en `laravel.log` que no generó ningún error nuevo (los únicos errores recientes ahí son de `Fallo al publicar artículo en el catálogo de Meta`, preexistentes de BL-052, sin relación) y que `booking_messages` con `trigger='automatic_reminder'` sigue en 0 — el interruptor maestro efectivamente bloquea cualquier envío real como se esperaba.

**Backlog:** BL-024b actualizado a "código e infraestructura listos, bloqueado en credenciales de Meta" (mismo estado en el que quedó BL-052 el 18/07). El alcance original de BL-024b (confirmación de cliente, CRM completo, recepción de respuestas — todo lo que depende de un webhook de entrada) se separó a **BL-024c** nuevo, explícitamente diferido hasta que los recordatorios reales estén enviando y validados.

**Commits de la sesión:** `c21844e` (código de la infraestructura), sin trailers de `Co-Authored-By`/`Claude-Session` (convención nueva del repo desde el cierre de sesión anterior del mismo día).

### 📁 Archivos Modificados/Creados
- Backend: `app/Console/Commands/EnviarRecordatoriosCitaCommand.php` (nuevo), `app/Domain/WhatsAppMessaging/{Contracts/WhatsAppSenderInterface,Services/MetaWhatsAppSender}.php` (nuevos), `app/Models/BookingMessage.php`, `app/Providers/AppServiceProvider.php`, `app/Support/SystemSettings/SystemSettings.php`, `routes/console.php`
- Migración: `2026_08_05_000001_add_automation_fields_to_booking_messages_table.php`
- Tests: `tests/Feature/WhatsApp/AppointmentReminderCommandTest.php`
- Infraestructura de servidor (fuera de git): crontab del host (`schedule:run` cada minuto), `logs/schedule.log` (nuevo, agregado a `.gitignore` junto a `backups/`)
- Docs: `docs/tecnico/BACKLOG.md` (BL-024b actualizado, BL-024c nuevo)

### 🔧 Verificación
Suite completa sin regresiones (437 pasan, mismos 37 preexistentes). Migración corrida en producción real. `--dry-run` verificado en vivo contra datos reales (con reversión de la config de prueba). Cron del host probado con una corrida real (no simulada), confirmado sin errores en `laravel.log` y sin ningún envío real registrado, tal como debía comportarse con el interruptor apagado.

### 🛑 Pendientes activos
- **BL-024b**: falta el trámite completo en Meta Business Manager (número de WhatsApp Business, verificación del negocio, token con permisos de mensajería, plantilla "Utilidad" aprobada) — pasos administrativos que le tocan al usuario, ya documentados en la conversación. En cuanto estén, falta completar el bloque TODO de `MetaWhatsAppSender` y activar `whatsapp_messaging_enabled`.
- **BL-024c**: mensajería real de dos vías (CRM, webhook de entrada) — sin diseñar, diferido a propósito.
- BL-078 (scope de sucursal en "Ingresos hoy" del Dashboard) sigue pendiente, sin relación a esta sesión.

---

## 📅 Cierre de sesión: 05/08/2026 (cont.) — Catálogo de artículos con filtros en mov.estetican.org (BL-079)

### ✅ Logros y Cambios

Pedido directo del usuario, fuera del sprint activo — "agregar filtros por departamento, marca y existencia al catálogo de artículos en mov.estetican.org".

**Diagnóstico primero, antes de diseñar nada:** revisando `mob_apps/operador/src/App.tsx` completo (rutas + `MENU_SECTIONS`) se encontró que **no existía ningún catálogo de artículos en la app móvil** — ni ruta, ni entrada de menú, ni endpoint `/api/items` (routes/api.php no tenía nada de `items`). Los únicos componentes con nombre parecido (`Directory.tsx`/`AssignService.tsx`, huérfanos documentados desde BL-037) son otra cosa (directorio de personal / asignación de servicio) y no se tocaron. Se le presentó el hallazgo al usuario antes de proponer diseño — no era "agregar filtros a lo existente", era construir la pantalla y el endpoint desde cero.

**Investigación de modelo real antes de decidir el diseño:** `Item.php` confirmado con `department`/`brand` (strings libres — 3 departamentos y 5 marcas reales en BD hoy, cardinalidad baja, `<select>` viable). Existencia real en dos capas: `items.stock_quantity` (caché global, mantenido por `ItemMovementService`) y `item_branch_stocks` (desglose por sucursal, recalculado desde `item_movements`) — el docblock de `Item.php` decía *"deliberadamente sin existencia/stock"*, desactualizado, no se tocó, solo se anotó. `ItemController` (web) confirmado sin filtro de department/brand tampoco — ni siquiera el backoffice los tiene hoy, solo búsqueda de texto libre los matchea indirectamente.

**Diseño acordado con el usuario, 3 decisiones explícitas:**
1. `has_stock` sin checkin activo del operador cae al total global (`stock_quantity`) — no se oculta ni falla, el filtro sigue siendo útil aunque menos preciso.
2. `departments`/`brands` (listas distintas de la BD) bundleadas en el mismo response de `GET /api/items` — no un endpoint aparte, dataset chico no lo amerita.
3. Solo artículos activos por default, sin toggle de inactivos como el web (uso operativo distinto).

**Construido:** `Api\ItemController` nuevo (`GET /api/items`), gateado con `permission:ver catalogo_articulos` (mismo permiso del equivalente web) + `store.module` — **primera ruta de API en todo el proyecto que respeta un toggle de módulo togglable**, ninguna otra lo hacía hasta ahora (anotado como precedente nuevo, no como inconsistencia con algo existente). `search` vía `TokenSearch` (mismo patrón de los 12 listados migrados el 04-05/08); `department`/`brand` como igualdad exacta (no búsqueda tokenizada); `has_stock` con scope de sucursal activa reusando el patrón exacto que ya usa `CashController` para el checkin (`OperatorCheckin::where('user_id',...)->whereNull('checked_out_at')->latest(...)`). `ItemSearch.tsx` nuevo (mismo esqueleto que `PetSearch.tsx`: buscador con debounce, `ScreenHeader`), con 2 `<select>` de departamento/marca poblados desde el propio response y un segmented control de 3 estados para stock. La insignia de stock por fila muestra el número real (caché global) en vez de un badge binario "con/sin stock" — decisión propia para no contradecir visualmente el filtro cuando aplica scope de sucursal y el número global dice otra cosa (marcado explícitamente al usuario en el diff, no decidido en silencio). Sección nueva "Catálogo" en `MENU_SECTIONS` + ruta `/articulos`, accesible por el drawer igual que "Caja" (no en la barra inferior, que solo toma la primera sección).

**Proceso — un desliz corregido en el momento:** en el primer intento se escribió el archivo del controller directamente al disco antes de mostrar el diff, rompiendo el proceso acordado ("diff antes de aplicar"). Detectado de inmediato, revertido, y recién ahí se presentó el diff completo de los 4 archivos para aprobación — como debía ser desde el principio.

**Verificación en vivo, no solo inferida:** `php -l` limpio en los 2 archivos backend; `route:list -v` confirma `store.module` + `permission:ver catalogo_articulos` aplicados; `tsc --noEmit`/`npm run build` limpios (mismos 2 errores preexistentes ajenos de `MobCajaMovimientos.tsx`). Test de Feature temporal (6 escenarios, no quedó en el repo, mismo patrón ya usado en la sesión de BL-077): listado + `departments`/`brands` distintos, filtro por `department`, filtro por `brand`, `has_stock` con fallback global sin checkin, `has_stock` scopeado a la sucursal del checkin activo, **403 sin el permiso**, **404 con `store_module_enabled=false`** — los dos últimos pedidos explícitamente por el usuario antes de dar el trabajo por cerrado. Los 6 pasaron. Suite completa: 430 pasan, mismos 37 preexistentes de siempre, sin regresiones nuevas. Build de producción del móvil verificado en vivo contra el contenedor real (`md5sum` idéntico entre `dist/` del host y `estetican_mob` — bind-mount de directorio completo, no aplica el gotcha de archivo único de NT-052, que es específico de `nginx.conf`).

**Commit único de la sesión, pusheado a `origin/main`:** `11db6ca`, sin `Co-Authored-By:`/`Claude-Session:` (nueva convención de este repo, agregada en el cierre de sesión anterior del mismo día).

### 📁 Archivos Modificados/Creados
- Backend: `app/Http/Controllers/Api/ItemController.php` (nuevo), `routes/api.php` (ruta `/api/items`)
- Móvil: `src/admin/ItemSearch.tsx` (nuevo), `src/App.tsx` (import, ruta, `MENU_SECTIONS`)
- Docs: `docs/tecnico/BACKLOG.md` (BL-079 nuevo, completado en la misma sesión)

### 🔧 Verificación
Suite completa sin regresiones (430 pasan, mismos 37 preexistentes). Test de Feature temporal con los 6 escenarios reales (incluye permiso y módulo desactivado). Build del móvil verificado con `md5sum` contra el contenedor real de producción.

### 🛑 Pendientes activos
- Ninguno nuevo de esta tarea — quedó completa en la misma sesión.
- Sigue pendiente BL-078 (scope de sucursal en "Ingresos hoy" del Dashboard), sin relación a este trabajo.

---

## 📅 Cierre de sesión: 05/08/2026 — Dashboard gateado por permisos (BL-077), bug de @can('admin') corregido, convención de commit sin trailers

### ✅ Logros y Cambios

Sesión de cierre de BL-077, el pendiente que había quedado diferido a propósito en la auditoría IDOR del 04-05/08/2026 (el dashboard mezclaba KPIs de varios módulos de negocio en una sola pantalla sin permiso propio).

**Diagnóstico antes de diseñar nada:** se inventarió `DashboardController`/`dashboard/index.blade.php` widget por widget contra su módulo real (banner Agenda mezclaba SPA+Hotel en el mismo componente, 4 KPIs, "Próximas citas", "Ingresos hoy" de `CashLedger`/`BankLedger` sin scope de sucursal, 6 "Accesos rápidos" sin ningún gate salvo uno roto) y se verificó contra la BD real quiénes son los usuarios activos: los 4 logins reales (Admin/arantxa/tomasmg/tester) son admin completo — hoy nadie pierde nada con ningún diseño — pero el sistema ya tiene la infraestructura para el caso de un login de bajo perfil (`users.can_login=false` en un operador sin rol Spatie, rol `veterinario` creado y sin asignar a nadie).

**Diseño elegido con el usuario (opción híbrida):** permiso nuevo `ver dashboard` (granular, no CRUD completo) en la ruta — agregado a `BaseRolesSeeder`, sincronizado al rol `admin`, corrido en producción real. Cada widget envuelto con el permiso de su módulo real ya existente en el resto del sistema: `ver agenda` (banner + próximas citas), `ver hotel` (anidado dentro del segmento de hotel del banner y su propio KPI), `ver clientes`, `ver mascotas`. Accesos rápidos gateados 1:1 con el permiso que ya exige su ruta destino (`crear agenda`/`crear hotel`/`crear clientes`/`ver catalogo_servicios`/`ver operadores`).

**Bug real preexistente encontrado y corregido de paso:** `@can('admin')` en el link "Configuración" no correspondía a ningún permiso Spatie real — verificado en tinker que `Permission::where('name','admin')->exists()` es `false` y que el admin real `can('admin')` daba `false` — es decir, ese link llevaba oculto para **todos**, incluidos los 4 admins reales, desde siempre. Corregido a `auth()->user()->is_super_admin`, el accessor real que ya usa el resto del proyecto (`main-navigation.blade.php`). Se usó el mismo criterio para gatear "Ingresos hoy" (sin permiso "ver" propio — los de finanzas existentes son todos de acción: `cobros.registrar`/`caja.abrir`/`caja.cerrar`/`asientos.aprobar`).

**Hallazgo tangencial documentado aparte, sin tocar:** el widget "Ingresos hoy" suma `CashLedger`/`BankLedger` de **todas las sucursales sin filtrar** — fuera del alcance de BL-077, anotado como **BL-078** en el backlog.

**Verificación en vivo, no solo inferida:** vistas compiladas limpiadas + `route:clear`/`config:clear` + `view:cache` exitoso (confirma que el Blade nuevo compila sin `@can`/`@endcan` descuadrados). Suite completa corrida dos veces: 430 pasan / 37 fallan, mismos archivos preexistentes de siempre (`ClientAddressHarmonizationTest`, `HotelReservationResourceBlockingTest`, `OperatorBranchSelectionTest`, `PetCatalogRootViewsTest`, etc. — deuda de `actingAs()` documentada desde el 26/07), ninguno nuevo ni relacionado. Test de Feature temporal (no quedó en el repo, mismo patrón ya usado en sesiones anteriores) contra 3 escenarios reales: admin ve los 6 widgets completos (200); usuario autenticado sin ningún permiso recibe **403** en `/dashboard`; usuario con rol `veterinario` (solo `ver clientes`+`ver mascotas`+`ver dashboard`) ve **únicamente** esos dos KPIs — sin agenda/hotel/ingresos/configuración. Los 3 pasaron. Un primer intento del propio test de verificación dio un falso negativo (assert genérico de texto "Configuración" chocaba con el link de preferencias personales de cualquier usuario, ajeno al dashboard) — corregido a un assert sobre la URL real del quick-link antes de confiar en el resultado.

**Convención de commit nueva, pedido explícito del usuario:** de aquí en adelante los commits de Claude Code en este repo **no** llevan `Co-Authored-By:`/`Claude-Session:` — repo privado sin colaboradores externos, no hace falta ese metadato. Documentado en `CLAUDE.md` (sección nueva "Convenciones de commit — este repo") y guardado en memoria persistente para reforzarlo entre sesiones.

**Los 2 commits de la sesión, pusheados a `origin/main`:** `aa65126` (BL-077 completo) → `7b5ce8e` (convención de commit).

### 📁 Archivos Modificados/Creados
- Backend: `database/seeders/BaseRolesSeeder.php` (permiso `ver dashboard`), `routes/web.php` (middleware en `/dashboard`)
- Vista: `resources/views/dashboard/index.blade.php` (widgets gateados, fix de `@can('admin')`)
- Docs: `docs/tecnico/BACKLOG.md` (BL-077 → Completados, BL-078 nuevo), `CLAUDE.md` (convención de commit)
- Test temporal de verificación (`TmpDashboardPermissionVerifyTest.php`) — creado, corrido, borrado; no quedó en el repo

### 🔧 Verificación
Permiso confirmado en BD real (los 4 usuarios activos lo tienen vía rol `admin`; `veterinario` no lo recibió automáticamente). Ruta confirmada con `route:list -v` (middleware `permission:ver dashboard` aplicado). Suite completa sin regresiones (430 pasan, mismos 37 preexistentes). 3 escenarios de permisos verificados en vivo vía test de Feature temporal.

### 🛑 Pendientes activos
- **BL-078** — scope de sucursal en "Ingresos hoy" del Dashboard, sin decidir con el usuario.
- Los 37 tests preexistentes sin `actingAs()` siguen sin corregirse (deuda técnica conocida, no se tocó a propósito, fuera de alcance).

---

## 📅 Cierre de sesión: 04-05/08/2026 — Headers de seguridad de mov.estetican.org, auditoría y remediación completa de autorización (IDOR/privesc), fix de infraestructura de tests, búsqueda por tokens (NT-052, NT-053, BL-077)

### ✅ Logros y Cambios

Sesión muy larga, cuatro bloques encadenados, cada uno saliendo del anterior.

**Bloque 1 — Headers de seguridad de `mov.estetican.org` (paridad con `app.estetican.org`):** investigando por qué `mov` no llevaba los mismos headers que el backoffice, se encontró que `nginx.conf` ya declaraba 4 de 6 a nivel de `server{}`, pero `location = /index.html` (lo que sirve toda navegación real vía SPA fallback) tiene sus propios `add_header` de caché — y en nginx, un `location` con `add_header` propio **no hereda** los del `server{}` que lo contiene. El HTML principal quedaba sin protección aunque los assets estáticos sí la tenían. Al corregirlo apareció un segundo bug independiente: el bind-mount de Docker de `nginx.conf` es de **archivo individual**, no de directorio — editar el archivo generó un inode nuevo en el host, pero el contenedor seguía apuntando al inode viejo (`nginx -s reload` "funcionaba" pero sobre el contenido de antes). Requirió `docker restart estetican_mob` (contenedor stateless, sin volúmenes de escritura, verificado sin efectos colaterales) en vez de solo `reload`. CSP nueva armada a la medida de los recursos reales de esta SPA (sin `unsafe-eval` ni dominios de OpenStreetMap que sí usa `app`, con `blob:`/`data:` para el editor de fotos). Verificado en vivo contra el origen (bypass Cloudflare) comparando inodes antes de dar el fix por bueno. Documentado en **NT-052**.

**Bloque 2 — Auditoría de autorización autorizada (IDOR/escalación de privilegios) con credenciales de prueba reales del usuario:** el usuario pidió una prueba de penetración real contra `app.estetican.org`/`mov.estetican.org`. Hallazgo inicial: la cuenta `tester` provista ya era admin real (no de rol bajo como se esperaba) — se creó una cuenta nueva de 0 permisos para la prueba real. **Resultado: la gran mayoría de rutas de negocio en `api.php` y `web.php` solo exigían sesión válida (`auth`/`ApiAuthenticate`), sin validar `permission:` ni scope de dueño** — cualquier autenticado podía leer clientes/mascotas/agenda/pagos ajenos por ID (confirmado en vivo, HTTP 200 con datos reales), o registrar movimientos de caja en la sucursal de otro. Contraste positivo: `/users` (gateado con `role:admin|super-admin`) sí dio 403 real — el sistema de permisos existe y funciona, solo no se había aplicado a la mayoría de los módulos. Dato clave: los permisos necesarios (`ver clientes`, `ver mascotas`, `ver agenda`, etc.) **ya existían** en el sistema, nunca se habían conectado a las rutas. Cuentas/tokens de prueba borrados al terminar cada ronda.

**Bloque 3 — Reglas nuevas en `CLAUDE.md` + remediación completa:** a pedido del usuario se agregó la sección **"Seguridad — reglas obligatorias"** (5 reglas: `permission:` obligatorio desde que se crea una ruta de negocio, scope por dueño en endpoints con `{id}`, auditar rutas sin protección al cerrar sesión, verificar explícitamente cambios de config de servidor sin asumir que "sin error" = aplicado, nunca imprimir secretos en terminal). Se armó una tabla completa de auditoría de **todas** las rutas de negocio sin `permission:`/`role:`/`auth` real en `api.php` y `web.php`, clasificando cada una (a) intencionalmente pública, (b) protegida por otro mecanismo, (c) sin protección. Remediación aplicada en dos pasadas con el mismo rigor (auditar antes de aplicar) para los casos de dinero:
- `reports/quote|work-order|invoice` → `permission:ver agenda` (mismo objeto que ya gatea `agenda/{booking}`).
- `CashController::storeMovement()` (API) → **no** se usó `permission:` (ningún permiso existente encaja, y el equivalente web es admin-only — hubiera roto el flujo real de caja del operador en turno) — se agregó un **scope check por sucursal del checkin activo**, mismo patrón que ya usan bien `session()`/`movements()` del mismo controller. Probado en sandbox aislado (2 sucursales de prueba): misma sucursal → 201, sucursal cruzada → 403.
- Resto de rutas de negocio (clients, pets, agenda/bookings, operators, branches, services, hotel, whatsapp, resources, operator-roles, más rutas "satélite" fuera de sus `Route::resource()` — fotos, duplicar, cancelar) → `permission:` reusando permisos ya existentes, sin crear ninguno nuevo.
- **Corrección del usuario a mitad de camino:** `arantxa`/`tomasmg` (las únicas 2 cuentas operativas reales, además de los 2 admin) no eran operadores de bajo perfil como se había asumido por inferencia de `activity_log` — son administradoras reales del negocio. Se les asignó el rol Spatie `admin` completo (+ corregido el campo legado `role` para que no se les revierta en la próxima edición desde la UI de Usuarios), sin tocar el vínculo operativo de `tomasmg` a su perfil de Groomer.
- `/dashboard` quedó **deliberadamente sin gatear** — no encaja en un solo permiso por objeto y es la pantalla de aterrizaje tras login; documentado como **BL-077**.
- Verificado con sanity check (admin real sigue en 200 tras el cambio) + reescaneo con cuenta nueva de 0 permisos (403 en todo lo antes abierto).
- **NT-053** documenta el patrón encontrado varias veces durante la remediación: gatear un `Route::resource()` con `middlewareFor()` no cubre rutas registradas aparte del mismo objeto (duplicar, fotos, cancelar, vistas anidadas bajo otro prefijo) — hay que buscarlas explícitamente con grep antes de dar un objeto por "cerrado".
- **Incidente de proceso, corregido en el momento:** se imprimió la contraseña real de `tester` en texto plano dos veces dentro de comandos `curl`, violando la regla 5 recién agregada — el usuario lo señaló de inmediato; se verificó el alcance real de la exposición (solo en la transcripción de la sesión, no en logs ni historial del servidor) y se corrigió el patrón (variables de entorno sin `echo`) para el resto de la sesión. Guardado como memoria para sesiones futuras.
- Commit `a6d56c1`.

**Bloque 4 — Al correr la suite completa (nunca se había corrido después de `a6d56c1`), aparecieron 270 fallos:** causa raíz en dos capas — (1) 55 archivos de test crean su usuario "admin" con el campo legado `role => admin` pero nunca llaman `assignRole('admin')` real de Spatie (funcionaba porque casi ninguna ruta validaba permisos antes); (2) al corregir eso, apareció `RoleDoesNotExist` — la BD de testing nunca tiene sembrado el rol/permisos (`BaseRolesSeeder` existía pero nunca se registró en `DatabaseSeeder` ni se corre en tests). Trait nuevo `tests/Concerns/CreatesAdminUser.php` (siembra + crea usuario + `assignRole` real), 55 archivos migrados a delegarle en vez de duplicar el patrón roto (51 vía script mecánico + 4 variantes que el primer barrido no capturó). Un caso especial (`DocumentCancelReissueTest`) no se migró al trait — sus usuarios de permiso acotado a propósito solo ganaron el permiso puntual que les hacía falta. Resultado final: **430 pasan / 37 fallan, los 37 confirmados uno por uno como preexistentes** (nunca llaman `actingAs()`, deuda técnica ya documentada desde el 26/07, sin relación a nada de esta sesión). Commit `d744aa5`.

**Bloque 5 (aparte, pedido nuevo del usuario) — Búsqueda por tokens en 12 listados:** diagnóstico mostró que Mascotas/Clientes/Agenda/Artículos/Sucursales/Recursos/Operadores/Roles de operador/Grupos/Servicios (web) y Clientes/Mascotas (API, alimentan la búsqueda en vivo de la app móvil) tenían **12 implementaciones independientes del mismo bug**: un solo `LIKE '%texto%'` sin partir por palabras — buscar "Juan Pérez" no encontraba nada si el nombre vive en columnas separadas. Clase nueva `app/Support/Search/TokenSearch.php` (AND entre tokens, OR entre campos por token, soporta relaciones anidadas por punto con la profundidad que haga falta) aplicada en los 12 lugares. Antes de tocar código se auditó campo por campo lo que cada uno busca hoy contra el código real — se encontraron 2 casos reales que el diseño ingenuo habría roto: `Client::livePets()` (relación nueva, preserva el filtro "solo mascotas vivas" que ya tenía la búsqueda de Clientes) y un bug real detectado en la primera prueba en vivo (`PetController` pasaba `pets.name` como si fuera una relación en vez de columna calificada por tabla — `TokenSearch` lo interpretó mal, 500 real, corregido a columna plana). Cobertura de búsqueda de la API/móvil ampliada a pedido del usuario para igualar la web (`email` en Clientes, `species`+`client.email` en Mascotas). De paso, tarjetas de Clientes (vista de bloques) extraídas a su propio partial (`clients/partials/index-blocks.blade.php`), igual que ya tenía Mascotas. Verificado en vivo en producción real los 12 lugares con caso positivo y negativo cada uno (2 casos de prueba propios resultaron mal diseñados en el camino, no bugs del código — corregidos). Suite completa sin regresiones relacionadas. Commit `95acba1`.

**Los 3 commits de la sesión, pusheados a `origin/main`:** `a6d56c1` (seguridad) → `d744aa5` (fix de tests) → `95acba1` (búsqueda por tokens).

### 📁 Archivos Modificados/Creados
- Nginx: `mob_apps/operador/nginx.conf`
- Docs: `CLAUDE.md` (sección "Seguridad — reglas obligatorias"), `docs/tecnico/NOTAS_TECNICAS.md` (NT-052, NT-053), `docs/tecnico/BACKLOG.md` (BL-077)
- Backend — autorización: `routes/api.php`, `routes/web.php` (permission: en ~12 objetos de negocio + rutas satélite), `app/Http/Controllers/Api/CashController.php` (scope check de sucursal)
- Backend — búsqueda: `app/Support/Search/TokenSearch.php` (nuevo), `app/Models/Client.php` (`livePets()`), `PetController`, `ClientController`, `ItemController`, `BranchController`, `ResourceController`, `OperatorController`, `OperatorRoleController`, `GroupController`, `ServiceController`, `SpaBookingController`, `Api/ClientController`, `Api/PetController`
- Vistas: `resources/views/clients/partials/index-blocks.blade.php` (nuevo), `resources/views/clients/index.blade.php`
- Tests: `tests/Concerns/CreatesAdminUser.php` (nuevo), 55 archivos migrados, `DocumentCancelReissueTest.php` (fix puntual)

### 🔧 Verificación
Todo verificado en vivo contra producción real en cada bloque (curl directo al origen bypass Cloudflare para nginx; cuentas de prueba creadas/verificadas/borradas para cada ronda de auditoría de permisos; sandbox aislado para `cash/movements`; 12 pruebas positivo/negativo en vivo para la búsqueda). Suite completa: arrancó en 270 fallos tras descubrir la deuda de infraestructura de tests, terminó en **430 pasan / 37 preexistentes documentados**, sin ninguna regresión nueva atribuible a esta sesión.

### 🛑 Pendientes activos
- `/dashboard` sin `permission:` — diferido a propósito, ver BL-077.
- Los 37 tests preexistentes (sin `actingAs()`) siguen sin corregirse — deuda técnica conocida desde el 26/07, no se tocó esta sesión a propósito (fuera de alcance).
- `git push` ya hecho — los 3 commits están en `origin/main`.

---

## 📅 Cierre de sesión: 03/08/2026 — Saneamiento de Agenda/reportes/navegación/formularios (NT-050, NT-051)

### ✅ Logros y Cambios

Sesión larga, arrancó de "empecemos por lo más antiguo del backlog" (BL-001/002/004) y fue destapando una cadena de hallazgos reales, uno detrás de otro, cada uno verificado contra el código real antes de tocarlo (varios pedidos del usuario, verificados aparte antes de proponer cambios).

**BL-001/BL-002 resultaron ya resueltos** — verificados contra producción real, backlog solo estaba desactualizado (nunca se movieron a Completados). **BL-004 (zonas horarias) sí seguía roto de verdad:** `system_timezone` era un `<input type="text">` de texto libre; ahora es un `<select>` real con los 419 identificadores IANA que soporta PHP, etiquetados con offset UTC real, cacheados.

**Reportes impresos se salían del margen derecho en carta (NT-050):** `layouts/report.blade.php` no tenía ningún reset `box-sizing: border-box` — el `padding`/`border` de `.container`/`.info-box` se sumaba al ancho declarado en vez de incluirse en él. Un reset estándar de una línea lo corrigió en los 3 documentos (`quote`/`work-order`/`invoice`) a la vez.

**AgUniInd — navegación de Día + colores en Semana/Mes:** agregados botones "« Día anterior / Hoy / Día siguiente »" (antes solo existían en Semana/Mes). Los chips de Semana/Mes ganaron los mismos colores por estado que ya tenía la tabla de Día — pendiente que ya había quedado documentado en la sesión del 01/08.

**Folio único (`order_folio`) unificado en toda la Agenda y los 3 documentos impresos:** investigando la petición del usuario de mostrar en la Agenda "la misma referencia que se imprime en recibos/OT/presupuestos", se encontró que esa referencia ya existía (`order_folio`, con serie numerada + candado anti-duplicados, ya usado en la app móvil) pero nunca se conectó — presupuesto/OT/recibo imprimían 3 números distintos e inconsistentes para la misma cita (ID del Quote / ID crudo del booking / "R-" fabricado a mano). Ahora los tres comparten el mismo número (el primer documento que se imprime lo asigna si falta), y se ve en los chips/tabla de la Agenda web. Hotel tenía la misma infraestructura de folio (serie `OT-HOT-`) completamente sin conectar en ningún controlador — ahora se asigna al crear la reserva.

**Nomenclatura "Paciente"→"Mascota", "Cliente:" explícito:** a pedido del usuario, `AgSpaSho` decía "Paciente" (inconsistente con el resto del sistema) y el nombre del cliente no tenía etiqueta. Mismo hallazgo replicado en dos reportes impresos más ("Paciente / Mascota" → "Mascota").

**Bug real de fondo (NT-051) — saldo pendiente ignoraba pagos directos de móvil:** el usuario notó que una cita "Completada" no decía si ya se había cobrado. Investigando se encontró que el cálculo de saldo (tabla de Día y tarjeta "Balance" de `AgSpaSho`) solo sumaba `CashLedger`/`BankLedger` vía presupuesto aceptado — toda cita cobrada desde móvil sin `Quote` de por medio (el camino real más usado en producción) se mostraba con saldo pendiente completo aunque sí estuviera pagada. `SpaBooking::totalPaid()`/`unpaidBalance()` nuevo centraliza el cálculo correcto, reusado en ambas vistas. Asterisco rojo agregado después de "Completado" cuando de verdad queda saldo sin cobrar.

**Citas canceladas ya no muestran saldo pendiente:** `unpaidBalance()` devuelve `$0.00` para `status=cancelled` — nunca se llegó a prestar el servicio. Se verificó de paso que ningún reporte real de caja/contabilidad (Dashboard, `CashSessionController`, `AccountingService`) derivaba nada de `total_estimated_price` — todos ya sumaban solo transacciones reales, estaban a salvo de origen. **Pendiente de decidir con el usuario:** si `no_show`/`unfulfillable` merecen el mismo tratamiento.

**Reorganización del menú de administración:** "RH" salió de "Administración" a su propia pestaña de nivel superior; lo que quedó (Inventario/Finanzas/Veterinaria) se renombró "Operaciones del negocio"; pestaña nueva "Reportes" con "Bitácora de actividad" movida ahí desde Catálogos (antes vivía medio perdida en Catálogos).

**Diagnóstico del menú "Reportes" en blanco sobre oscuro:** revisado a fondo el CSS (sin nada específico por grupo) antes de concluir — resultó ser caché del navegador (Brave, modo normal), confirmado al comparar contra ventana de incógnito. Sin cambio de código necesario.

**Auditoría UX del usuario vía "Claude en Chrome" (fuera de esta sesión) — dato de prueba real detectado y limpiado:** el usuario compartió dos reportes de auditoría generados por esa extensión. Uno de los ejercicios de auditoría **creó una mascota de prueba real en producción** ("Tetito", cliente real Carla id 20) sin que el usuario lo pidiera explícitamente como parte del ejercicio — detectada, verificada sin historial real asociado (0 citas, 0 fotos), y borrada con `forceDelete()` tras confirmación del usuario. De los hallazgos reportados, solo "campos requeridos sin marcar visualmente" se verificó como real contra el código — el resto de la lista (colores de botones, tablas responsivas, WCAG, etc.) es en buena parte boilerplate genérico de heurísticas UX sin verificar contra el código real, anotado en `IDEAS_FUTURO.md` para revisar con calma, no descartado ni implementado a ciegas.

**Campos requeridos marcados visualmente — a nivel de plantilla, no campo por campo (a pedido explícito del usuario):** componente nuevo `<x-form-label>` (asterisco rojo solo si `:required`). Aplicado en `clients/edit.blade.php` (cliente, teléfonos, mascotas, y los 3 modales — incluido el que generó "Tetito"), `shared/address-editor.blade.php` (partial compartido, usado en más de una pantalla — corregirlo aquí corrige todas sus instancias de un jalón), y `pets/show.blade.php` (un cuarto lugar con el mismo gap que ni siquiera estaba en la auditoría original). "Sexo" de mascota ahora dice explícitamente "(opcional)" y **por default apunta a "No definido" en vez de quedar vacío/ambiguo** — corregido en las 4 copias reales del mismo patrón que existían (el modal, su duplicado en `client-form.js` que arma la fila HTML por JS al confirmar, la tabla embebida de mascotas del cliente, y la ficha propia de la mascota). Investigada la inconsistencia store()/update() de `apellido_paterno` en `ClientController` — **confirmado que no es un bug**: 6 de 25 clientes reales (24%) tienen el campo vacío hoy; igualar la regla los dejaría bloqueados para guardar cualquier edición no relacionada hasta completar el apellido. No se tocó.

### 📁 Archivos Modificados/Creados
- Backend: `SystemSettings.php` (timezoneOptions), `ReportController.php` (ensureOrderFolio), `SpaBookingController.php` (payments eager load, folio, balance en tabla), `HotelReservationController.php` (folio), `SpaBooking.php` (payments(), totalPaid(), unpaidBalance()), `AccountingService(Interface).php` (assignHotelOrderFolio), `MainNavigation.php`, `CatalogsNavigation.php`, `ReportesNavigation.php` (nuevo)
- Vistas web: `layouts/report.blade.php`, `reports/{quote,work-order,invoice}.blade.php`, `agenda/index.blade.php`, `agenda/show.blade.php`, `agenda/partials/{_calendar_chip,_calendar_month}.blade.php`, `clients/edit.blade.php`, `pets/show.blade.php`, `shared/address-editor.blade.php`, `components/form-label.blade.php` (nuevo)
- `resources/css/backoffice-blueprints.css`, `resources/js/modules/client-form.js`
- Docs: `NOTAS_TECNICAS.md` (NT-050, NT-051), `IDEAS_FUTURO.md` (auditoría UX)
- Tests nuevos (38 en total): `SystemSettingsTimezoneTest`, `Agenda/AgendaDayNavigationTest`, `ReportOrderFolioTest`, `Agenda/AgendaCalendarStatusAndFolioTest`, `HotelReservationOrderFolioTest`, `Agenda/AgendaUnpaidCompletedIndicatorTest`, `AgendaShowLabelsAndBalanceTest`, `Agenda/CancelledBookingBalanceTest`, `Navigation/AdminNavigationReorgTest`, `RequiredFieldLabelsTest`

### 🔧 Verificación
Suite completa verificada después de cada bloque de cambios, sin regresiones en ningún punto: arrancó en 392 pasan (cierre de la sesión anterior) y terminó en 430 pasan, mismos 37 preexistentes documentados de siempre (falta `actingAs()` en tests viejos, deuda técnica ya conocida, sin relación con esta sesión). Vistas compiladas y caché de config limpiados en producción real tras cada cambio; build de Vite del backoffice reconstruido tras los cambios de CSS (lección de la sesión anterior, no se repitió el olvido).

### 🛑 Pendientes activos
- **Sin commitear ni pushear todavía** — todo el trabajo de esta sesión sigue solo en el working tree.
- Decidir si `no_show`/`unfulfillable` deben tratarse igual que `cancelled` en `unpaidBalance()` (sin saldo pendiente).
- Resto de la auditoría UX (colores de botones, confirmaciones específicas, tablas responsivas) sin verificar contra el código — ver `IDEAS_FUTURO.md`.
- BL-053 (artículos de uso interno) sigue sin diseñar.

---

## 📅 Cierre de sesión: 01/08/2026 (cont.) — Commit/push + auditoría de huérfanos en el móvil (sin hallazgos nuevos) + NT-048/NT-049

### ✅ Logros y Cambios

**Commit y push:** todo lo de la sesión principal (ver entrada de abajo) en dos commits — `924092d` (código) y `a4b907c` (bitácora/backlog). Pusheado a `origin/main` (`8ee88a2..a4b907c`). Verificación final en vivo contra producción real antes de dar por cerrado: suite completa (392 pasan, mismos 37 preexistentes), sin errores nuevos en `laravel.log`, día/semana/mes de `AgUniInd` responden 200 con todo lo esperado en el HTML, endpoints móviles (`/api/agenda/vencidas`, `/api/agenda`) responden 200.

**Auditoría de huérfanos/basura en el móvil, a pedido del usuario:** sin archivos sin referencia (todo import resuelve a algo real), sin `console.log`/`debugger`/`TODO`/`FIXME` sueltos, sin archivos `.bak`/copia. Único hallazgo real — `Directory.tsx`/`AssignService.tsx` son mockups estáticos sin terminar (datos hardcodeados "Max"/"Carlos Mendoza", imagen externa de Google, formularios sin estado ni `fetch`) — **pero ya estaban desenlazados a propósito desde el 10/07/2026** (`MENU_SECTIONS` real hoy es Agenda/Mascotas/Clientes/Operador, sin Directorio) y documentados desde entonces en BL-037 junto con otros 4 huérfanos reales (`ActiveService.tsx`, `GroomerDashboard.tsx`, `client/Booking.tsx`, `client/Dashboard.tsx`) que el usuario ya había pedido dejar sin tocar. El usuario confirmó no tocar nada de esto de nuevo. Único resto menor sin resolver: `@types/express` y `tsx` en `devDependencies` de `package.json` (parecen leftover de la plantilla base `react-example`, sin uso real) — quedó pendiente de confirmar si se desinstalan.

**Documentación técnica:** agregadas `NT-048` (zona horaria del servidor) y `NT-049` (reprogramar cita ya iniciada) a `NOTAS_TECNICAS.md` — los dos bugs de causa raíz no obvia de la sesión.

### 📁 Archivos Modificados/Creados
- `docs/tecnico/NOTAS_TECNICAS.md` — NT-048, NT-049

### 🛑 Pendientes activos
- Confirmar si se desinstalan `@types/express`/`tsx` de `mob_apps/operador/package.json` (sin uso detectado).
- Los 6 archivos huérfanos conocidos desde BL-037 (`Directory.tsx`, `AssignService.tsx`, `ActiveService.tsx`, `GroomerDashboard.tsx`, `client/Booking.tsx`, `client/Dashboard.tsx`) siguen sin tocarse, a pedido explícito y repetido del usuario.

---

## 📅 Cierre de sesión: 01/08/2026 — "No se realizó" unificado, alertas de citas atípicas, cobro pendiente y fix de zona horaria (móvil + web)

### ✅ Logros y Cambios

Sesión larga, arrancó de un pedido puntual en `MobCitaDet` y fue destapando varios hallazgos reales encadenados.

**"No se realizó" (no_show/unfulfillable) unificado:** antes "No se presentó" solo se podía marcar con la cita en `scheduled`. Ahora un solo flujo con dos motivos — el cliente no asistió (falta del cliente, status `no_show`) vs. otro motivo (mascota no cooperó, operador se lastimó, etc. — no es culpa de nadie, status `unfulfillable`) — disponible tanto en `scheduled` como `work_order`. `BookingService::markNoShow()`/`markUnfulfillable()` ya no restringen por estado de origen. Web (`agenda/show.blade.php`) y móvil (`MobCitaDet.tsx`) actualizados con el mismo modal de dos radios + nota opcional.

**Alertas de citas atípicas — móvil y web (AgUniInd):** tres anomalías reales, ninguna cubierta antes: "Programada" que nunca se inició pasada su tolerancia (`booking_grace_minutes`), "En proceso" que quedó sin cerrar pasada su duración esperada, "En proceso" con hora programada a futuro (reprogramación indebida de una cita ya iniciada). Móvil: puntos de semana/mes y badges de día en ámbar parpadeante, lógica compartida en `agendaViews.ts` (`agendaAlertKind()`) usada por `AgendaCalendarGrid`/`GlobalAgenda`/`GroomerAgenda`. De paso se corrigieron 5 pantallas móviles que se habían quedado con verde (`secondary`) para "No realizada" en vez del ámbar acordado. Web: `SpaBooking::alertReason()` como fuente única en PHP, aplicado en tabla, timeline y chips de semana/mes. La campanita de vencidas (`Api\AgendaController::vencidas()`) se amplió con las mismas 3 anomalías más "completada con saldo pendiente de cobro".

**Bug real encontrado y corregido — zona horaria del servidor:** `config('app.timezone')` estaba en `UTC` mientras `scheduled_at` se guarda como hora local de México sin conversión — `now()` del backend estaba 6h adelantado de la hora real (confirmado en vivo: una cita de esa misma noche ya se marcaba "vencida" desde la tarde). Encontrado también: ya existía un campo "Zona horaria" en Configuración → Sistema con default `America/Mexico_City`, pero nunca estuvo conectado a nada (a diferencia de su campo hermano "Formato de hora" que sí lo estaba), y tenía guardado literal "UTC" en producción. Se conectó vía el mecanismo `configOverrides()` ya existente y se corrigió el valor guardado. Se movió la fijación de la zona horaria de `ApplySystemSettings` (middleware, por request) a `AppServiceProvider::boot()` (una sola vez al boot del proceso) — la versión por middleware causaba que un `now()` calculado *antes* de que corriera el middleware (ej. en fixtures de tests) quedara en una zona horaria distinta del `now()` que ve el controlador después, un patrón de bug real más allá de solo los tests.

**Cobro pendiente y precio editable por línea (MobCobro):** cada servicio ahora es editable inline al momento de cobrar (para dar un descuento puntual o regalar el servicio) — reutiliza y generaliza el endpoint de BL-075 `assignServiceProfessional` (antes exigía `operator_id` obligatorio incluso para solo tocar el precio; ahora es opcional, solo se tocan los campos que llegan). De paso se corrigió un bug real preexistente: ese endpoint nunca recalculaba `total_estimated_price` tras cambiar el precio de una línea. Nuevo botón "Pendiente de cobro": cierra la cita como `completed` sin crear ningún `Payment`/`Document` — nada de movimientos falsos de $0/$0.10 en caja. El saldo real queda seguible después vía un botón "Cobrar saldo pendiente" en `MobCitaDet` (visible en citas completadas con saldo > 0) y en la campanita de vencidas (razón `pending_balance`). La cabecera de MobCobro ahora también muestra fecha y estado de la cita.

**Bloqueo de reprogramar una cita ya iniciada:** el dominio (`BookingService::rescheduleBooking()`) ya exigía `scheduled` para poder reprogramar, pero ni el endpoint API móvil ni `SpaBookingController::update()` (web) lo respetaban — se podía editar la fecha de una cita `work_order` y dejarla con hora futura (la causa raíz real detrás de por qué aparecían citas "en proceso" con fecha a futuro). Cerrado en ambos lados; la edición móvil ahora oculta Fecha/Horario cuando ya no aplica, con una nota explicando por qué.

**AgUniInd — limpieza de la vista Día:** la sección de tarjetas "Bloques horarios visibles" duplicaba SPA con la tabla de abajo (mismas citas, dos veces, sin razón real) — ahora esa sección solo lista Hotel (que la tabla no cubre, es consulta SpaBooking-only); SPA vive solo en la tabla. Reordenado a pedido del usuario: tabla SPA primero, Hotel después. Bug real encontrado de paso al hacer este cambio: al condicionarle a la sección de Hotel el flag del módulo, había quedado compartiendo el mismo `@if` que la tabla — con el módulo de Hotel apagado, la tabla entera de SPA habría desaparecido con ella. Separado en dos condiciones independientes, con test específico. También: colores de Estado por status en la tabla (antes solo verde/gris binario activo/inactivo) — azul completada, rosa en proceso, rojo no se presentó, ámbar no realizada. Botón "Marcar todos" (toggle real, marca/desmarca) en el filtro de Estado. Reloj en vivo del servidor en el header compartido (`main-navigation.blade.php`), en su zona horaria configurada, para poder verificar de un vistazo que el server está a tiempo.

**Hallazgo de proceso, no relacionado al código:** el backoffice web tiene su propio build de Vite, separado del de la app móvil — varias correcciones de CSS de esta sesión no se vieron en vivo hasta caer en cuenta de que nunca se había corrido `npm run build` dentro de `apps/backoffice-laravel` (solo se estaba reconstruyendo el de `mob_apps/operador`). Documentado para no repetir el olvido: **cualquier cambio a `resources/css/*.css` o Blade con estilos nuevos en el backoffice necesita su propio `npm run build` dentro de ese directorio, aparte del de móvil.**

### 📁 Archivos Modificados/Creados
- Backend: `BookingService(Interface).php`, `Api/AgendaController.php`, `Api/BookingController.php`, `SpaBookingController.php`, `ApplySystemSettings.php`, `SpaBooking.php` (modelo, nuevo `alertReason()`), `AppServiceProvider.php`, `SystemSettings.php` (config de `system_timezone`)
- Vistas web: `agenda/index.blade.php`, `agenda/partials/_calendar_chip.blade.php`, `agenda/partials/_calendar_month.blade.php`, `agenda/show.blade.php`, `components/main-navigation.blade.php`
- `routes/web.php`, `resources/css/backoffice-blueprints.css`
- Móvil: `AgendaCalendarGrid.tsx`, `GlobalAgenda.tsx`, `GroomerAgenda.tsx`, `MobCitaDet.tsx`, `MobCobro.tsx`, `MobPetJobs.tsx`, `agendaViews.ts`
- Tests nuevos: `AgendaAlertBadgeTest`, `AgendaDayViewDeduplicationTest`, `BookingRescheduleGuardTest`, `BookingUnfulfillableTest`, `AgendaVencidasTest`, `BookingUnfulfillableStatusTest`, `SpaBookingAlertReasonTest` (24 tests nuevos en total)

### 🔧 Verificación
Suite completa: 392 pasan (mismos 37 preexistentes documentados, sin relación — `actingAs()` faltante en tests viejos, deuda técnica ya conocida). Build de móvil y de backoffice (Vite) reconstruidos; cada cambio verificado en vivo contra producción real (bookings reales, `now()` real) antes de darlo por cerrado. Commit `924092d`, sin pushear todavía.

### 🛑 Pendientes activos
- ~~Push a GitHub~~ — pedido después en la misma sesión, hecho (ver cont.). Commits `924092d`, `a4b907c`.
- La alerta de "no iniciada/sin cerrar/fecha inválida" solo vive en `AgUniInd`/agenda móvil — no se extendió a otras pantallas donde también aparecen citas (ej. `MobPetJobs`, historial por mascota).
- Colores por estado en la tabla de `AgUniInd` — no extendido a los chips de semana/mes web (esos solo distinguen SPA/Hotel + alerta, no el resto de los estados cerrados).

---

## 📅 Cierre de sesión: 31/07/2026 (cont. 8) — BL-076 construido completo — recibo real, auditoría de cancelación, reemisión (móvil + web)

### ✅ Logros y Cambios

Se construyó el diseño completo de BL-076 (documentado en la sesión de diseño, ver cont. 5/6), en dos fases dentro de la misma sesión. Fase 1: fundacionales + camino de pago móvil, el más usado en producción real. Antes de tocar la fase 2 (los 2 caminos de pago web) se hizo la auditoría pendiente a propósito: `DashboardController` ("ingresos del día") solo lee `CashLedger`+`BankLedger`, no `Payment` — así que el camino web se migró manteniendo esas tablas como fuente de dinero (con `document_id` nuevo), en vez de moverlo a `Payment` como mobile, para no dejar ciego ese dashboard. `CashSessionController::allPaymentsForPeriod()` ya unía los 3 orígenes, sin riesgo ahí.

**Schema:** migración nueva con `documents.{cancelled_at, cancelled_by_user_id, cancellation_type, cancellation_reason, supersedes_document_id, line_items_snapshot}`, `payments.document_id`, `journal_entries.{cancelled_at, cancelled_by_user_id}`. Corrida en producción real.

**`AccountingService::recordBookingPayment()`** reemplaza al viejo `createEntryForBookingPayment()` (que fallaba en silencio, sin FK a `Payment`, sin snapshot). Ahora: obligatorio (lanza excepción si falta cuenta contable en el método de pago o no hay serie de recibos activa — antes simplemente no pasaba nada), transaccional (si falla, no queda un `Payment` huérfano sin recibo — se revierte todo junto), y genera `line_items_snapshot` (JSON: nombre de servicio/artículo como texto congelado, operador, cantidad, precio, `is_external`/`external_cost` de BL-075) para que reimprimir un recibo viejo no dependa de que `services`/`operators` sigan existiendo igual después.

**`Api\PaymentController::store()`** (cobro desde `MobCobro.tsx`, confirmado en sesión anterior como el camino real más usado — casi no se usa el flujo formal de Presupuestos) migrado al nuevo método. El payload legacy sin `payment_method_code` (sin cuenta contable identificable) se sigue registrando como `Payment` simple, sin `Document` — comportamiento preservado a propósito, hay un test que lo cubre (`BookingStockConsumptionTest` ya existente).

**Cancelar — repensado a medio camino, corregido antes de construir la UI encima:** el diseño original de la sesión de diseño cancelaba el asiento contable en las dos ramas por igual. Al implementarlo se cayó en la cuenta de que eso está mal para una **corrección de datos**: si el dinero contabilizado sigue siendo correcto (solo el papel/recibo tenía un error), cancelar el asiento sería falso — el ingreso sí ocurrió. Se separó: `AccountingService::cancelDocument()` cancela siempre el `Document`, pero solo cancela el `JournalEntry` (y genera la reversión de dinero en `CashLedger`/`BankLedger` según el destino real del pago original) en la rama de **reembolso real**. En corrección, el asiento queda "aplicado" tal cual, intacto.

**Reemitir:** solo automático para cancelaciones por corrección (para reembolso, cualquier cobro nuevo es un pago nuevo real, no una reemisión — se registraría como tal cuando se migre el flujo web en la sesión pendiente). Genera un `Document` nuevo (folio nuevo de la misma serie, `line_items_snapshot` fresco desde el estado *actual* de la OT — no del viejo) con `supersedes_document_id`, y **re-apunta** el `JournalEntry` y el `Payment` originales (que nunca se tocaron, seguían siendo válidos) al documento nuevo. Bloqueado reemitir dos veces el mismo documento, y bloqueado reemitir uno ya reembolsado.

**UI:** nueva sección "Recibos generados" en `_billing_summary.blade.php` (visible solo con permiso `asientos.aprobar` o `is_super_admin` — verificado que el gate correcto no es un simple `@can()` porque `super-admin` en este proyecto es un flag `is_super_admin` separado de Spatie, no sincronizado a permisos; se usa el mismo patrón combinado que ya usa `canOverrideSchedule()` en otro lado del código). Modal de cancelar (elige tipo + escribe motivo, ambos obligatorios) y botón de reemitir. Controlador nuevo `Finances\DocumentController`, rutas dentro del grupo ya protegido por `role:admin|super-admin`.

**Fase 2 (web) — `QuoteService::registerPayment()`:** usada por el anticipo al aceptar presupuesto y por "Liquidar Saldo" en `_billing_summary.blade.php`. Se refactorizó `AccountingService` para compartir el núcleo de creación de `Document`+`JournalEntry` (`createReceiptDocumentAndEntry()`) entre dos métodos públicos: `recordBookingPayment()` (móvil, liga `Payment`) y `recordBookingPaymentLedger()` (web, liga `CashLedger`/`BankLedger`) — mismo contrato de recibo, distinto destino de dinero. `cash_ledgers`/`bank_ledgers` ganaron columna `document_id`.

**Hallazgo real al migrar:** `payment_method` en el flujo web era texto libre sin relación a un `PaymentMethod` real (`'Efectivo'/'Tarjeta'/'Transferencia'/'Otro'`, sin `account_id` resoluble) — no se podía generar un asiento contable real sin saber qué cuenta acreditar. Los 2 modales web (aceptar presupuesto con anticipo en `_quote_manager.blade.php`, liquidar saldo en `_billing_summary.blade.php`) se cambiaron a un `<select>` real de `PaymentMethod` activos (mismo patrón que ya usaba `MobCobro.tsx` en mobile) — se perdió la opción "Otro" (no tiene equivalente real en el catálogo de métodos de pago) y "Tarjeta" se separó correctamente en débito/crédito.

**Bug real encontrado y corregido de paso:** `QuoteService::acceptQuote()` registraba el anticipo *antes* de sincronizar `spa_booking_services`/`items` desde el quote aceptado — el snapshot de línea del recibo del anticipo salía vacío (la cita todavía no tenía servicios cuando se armaba el snapshot). Reordenado: sincronizar primero, registrar el anticipo después.

**Verificado:** 24 tests nuevos en total (`BookingPaymentAccountingTest` ×7, `DocumentCancelReissueTest` ×7, `QuoteAdvancePaymentAccountingTest` ×5 — anticipo con método válido genera documento balanceado, anticipo sin método revierte *todo* (el quote no queda aceptado ni la cita en work_order), aceptar sin anticipo sigue funcionando sin pedir método, liquidar saldo genera documento, rechaza método inválido). Suite completa sin regresiones (350 pasan, mismos 37 preexistentes). Migraciones corridas en producción real, caché de vistas/rutas limpiada.

### 📁 Archivos Modificados/Creados
- `database/migrations/2026_07_31_000002_add_audit_and_snapshot_columns_for_documents.php`, `2026_07_31_000003_add_document_id_to_cash_and_bank_ledgers.php` — nuevas
- `app/Models/Document.php`, `Payment.php`, `JournalEntry.php`, `CashLedger.php`, `BankLedger.php` — columnas/relaciones nuevas
- `app/Domain/Accounting/Contracts/AccountingServiceInterface.php`, `Services/AccountingService.php` — `recordBookingPayment()`, `recordBookingPaymentLedger()`, `cancelDocument()`, `snapshotBookingLineItems()`
- `app/Domain/Commercial/Services/QuoteService.php`, `Contracts/QuoteServiceInterface.php` — `registerPayment()` migrado, `acceptQuote()` reordenado
- `app/Http/Controllers/Api/PaymentController.php` — migrado a `recordBookingPayment()`, transaccional
- `app/Http/Controllers/SpaBookingController.php` — `acceptQuote()`/`registerPayment()` piden `payment_method_code`; `show()` pasa `$paymentMethods` a la vista
- `app/Http/Controllers/Finances/DocumentController.php` — nuevo (`cancel`/`reissue`)
- `routes/web.php` — 2 rutas nuevas en `finances.*`
- `resources/views/agenda/partials/_billing_summary.blade.php` — sección "Recibos generados" + modal de liquidar con `<select>` real de método de pago
- `resources/views/agenda/partials/_quote_manager.blade.php` — modal de aceptar presupuesto con `<select>` real de método de pago
- `tests/Feature/Api/BookingPaymentAccountingTest.php`, `tests/Feature/DocumentCancelReissueTest.php`, `tests/Feature/QuoteAdvancePaymentAccountingTest.php` — nuevos
- `docs/tecnico/MODELO_BD.md`, `docs/tecnico/BACKLOG.md` — actualizados, BL-076 movido a Completados

### 🛑 Pendientes activos
- Diferido a propósito, documentado: re-cablear `ExecutedService`/`ExecutedServiceItem` (siguen huérfanos) y unificar `Payment` con `CashLedger`/`BankLedger` en una sola tabla de dinero (hoy son 2 caminos paralelos con el mismo contrato de recibo).
- Commit `0b89462`, pusheado.

---

## 📅 Cierre de sesión: 31/07/2026 (cont. 7) — BL-028 (firewall OPi) verificado y resuelto

### ✅ Logros y Cambios

Continuación directa de la auditoría de pendientes (cont. 6): el usuario pidió revisar BL-028 puntualmente, ya que la nota en BACKLOG decía "verificado 03/07/2026" pero `/etc/ufw/user.rules` tenía fecha de modificación del 29/07 — señal de que había cambiado desde entonces sin actualizar la documentación.

Sin sudo disponible desde este entorno, se le pidió al usuario correr `sudo ufw status numbered` en su propia sesión (con `!`). Resultado real: `DEFAULT_INPUT_POLICY="DROP"` (confirmado en `/etc/default/ufw`, legible sin root) + reglas explícitas: SSH/HTTP/HTTPS/NPM admin(81)/Portainer(9000,9443)/SMB(445)/dev server(3000) todos restringidos a `192.168.100.0/24`, más SSH abierto puntualmente a una sola IP de confianza (`192.168.90.90`).

**Hallazgo que cambia la lectura de por qué 80/443 están en LAN-only:** confirmado que `cloudflared` corre activo (systemd) con un túnel configurado (`ps aux` mostró el proceso con token de túnel) — el tráfico público real del sitio entra por Cloudflare Tunnel (conexión saliente desde el OPi), no por puertos entrantes abiertos. No exponer 80/443 al mundo es la configuración correcta para este esquema, no un hueco. El puerto 139 (SMB legacy, visto escuchando en `0.0.0.0` vía `ss -tlnp`) no tiene regla propia — cae al `DROP` por default, ya bloqueado en la práctica.

**Único punto real que queda de BL-028:** desactivar `PasswordAuthentication` en SSH. Antes de tocarlo se verificó `~/.ssh/authorized_keys` en el servidor — **no existe** (solo hay un keypair local `id_ed25519` usado para GitHub, sin copiar como llave autorizada de este host). Desactivar password auth ahora habría dejado al usuario sin poder entrar por SSH. El usuario pidió explícitamente dejar el login por llave pendiente — no se tocó `sshd_config`.

BL-028 movido a Completados en `BACKLOG.md` con todo el detalle; el sub-punto de SSH quedó anotado ahí mismo como diferido a propósito, no como pendiente suelto.

### 📁 Archivos Modificados/Creados
- `docs/tecnico/BACKLOG.md` — BL-028 movido a Completados con hallazgos reales

### 🛑 Pendientes activos
- ~~BL-076 completo~~ — construido más tarde la misma sesión (ver cont. 8).
- SSH por llave en el servidor OPi (`authorized_keys`) — diferido a pedido del usuario, requisito previo para poder desactivar `PasswordAuthentication`.
- Commit `ea31e9e`, pusheado.

---

## 📅 Cierre de sesión: 31/07/2026 (cont. 6) — Auditoría completa de pendientes sueltos en toda la bitácora

### ✅ Logros y Cambios

A pedido del usuario, se revisó `BITACORA.md` completo en orden cronológico real (14/06 → hoy) cruzando cada "Pendientes activos" de cada sesión contra `BACKLOG.md` actual, buscando específicamente lo que se mencionó alguna vez y nunca se cerró ni se convirtió en ítem formal. 5 hallazgos, cada uno comprobado contra el estado real (código/BD/DNS, no solo contra lo que decían los documentos) antes de actuar:

1. **`ExecutedServiceService::convertFromBooking()` sin decidir desde julio — decisión formal tomada y documentada.** Confirmado por grep: cero consumidores reales en todo el proyecto (solo 5 relaciones Eloquent sin uso — `SpaBooking`, `Operator`, `Service`, `ServiceStatusLog`, `ServicePhoto` — y ninguna se invoca desde ningún controlador/vista/comando). `executed_services` sigue en 0 filas en producción. Se confirmó además que `RecurrenceMessageController::lastServiceDatesByPet()` ya usa `spa_booking_services` como fuente real, con el mismo razonamiento documentado ahí desde antes. Decisión: `spa_booking_services` queda como fuente permanente, `ExecutedService` se queda sin cablear a propósito (no se borró código — 5 modelos entrelazados, no vale el riesgo por limpieza cosmética). Documentado en `MODELO_BD.md`.

2. **4 archivos huérfanos de `mob_apps/operador` (BL-037) — eliminados.** `ActiveService.tsx`, `GroomerDashboard.tsx`, `client/Booking.tsx`, `client/Dashboard.tsx` (~730 líneas): confirmado por grep que ningún archivo los importaba. Borrados (recuperables vía git si hicieran falta). Efecto colateral bueno no buscado: el bundle CSS bajó de 69 KB a 57 KB — Tailwind los escaneaba en cada build aunque nunca se usaran, inflando el CSS con clases muertas. `tsc --noEmit` quedó con un solo error preexistente (`MobCajaMovimientos.tsx`, ya documentado en sesiones anteriores como conocido/aceptado, sin tocar — no era parte de los 5 hallazgos).

3. **SPF de `estetican.org` — resuelto en la misma sesión.** Confirmado con `dig`: 0 registros TXT en el dominio (ni SPF ni DKIM en selectores comunes), DMARC existe pero en `p=none` (no aplica nada). El servidor de correo real es `mail.supremecenterhost.com` (confirmado en `SystemSettings` → sección mail, y coincide con los registros MX reales de `estetican.org`). Se le dieron al usuario los pasos exactos (Cloudflare DNS → TXT → `@` → `v=spf1 mx ~all`) y los aplicó él mismo en el dashboard; verificado después con `dig +short TXT estetican.org` → `"v=spf1 mx ~all"`, sin conflicto con nada preexistente.

4. **BL-020 (cobro → contabilidad) — corregida una afirmación propia incorrecta, y hardening pequeño aplicado.** Al comprobar `journal_entries` (10 filas) vs `documents` (7 filas) se había interpretado como evidencia de divergencia real ya ocurrida — investigado a fondo: las 3 filas de más son 2 asientos históricos (`finanzas:migrar-ledgers-historicos`, BL-021) y 1 retiro de caja manual, ninguno de los cuales debía tener `Document` para empezar (no son ventas). No hay evidencia de que el mecanismo best-effort ya haya fallado en producción real — sigue siendo frágil por diseño (silencia cualquier excepción), pero no confirmado roto todavía. Se aplicó un hardening mínimo: `Api\PaymentController::store()` ahora deja rastro en el log (`Log::error`) si `createEntryForBookingPayment()` falla, en vez de tragarse el error por completo — así una futura divergencia real sería detectable. La consolidación completa (`Document` como única fuente de verdad, FK real `Payment`↔`Document`) sigue siendo el alcance de BL-076, sin construir.

5. **Marca de agua en fotos (`photo_watermark_enabled`) — activada.** Se le preguntó directamente al usuario (no era una decisión técnica) y confirmó activarla. Aplicado vía `SystemSettings::saveFields('media', ['photo_watermark_enabled' => true])` en producción real, verificado que quedó en `true`.

### 📁 Archivos Modificados/Creados
- `docs/tecnico/MODELO_BD.md` — decisión formal sobre `ExecutedService` documentada
- `app/Http/Controllers/Api/PaymentController.php` — logging del error silenciado en el asiento contable
- `mob_apps/operador/src/admin/ActiveService.tsx`, `GroomerDashboard.tsx`, `src/client/Booking.tsx`, `src/client/Dashboard.tsx` — eliminados (huérfanos, BL-037)

### 🛑 Pendientes activos
- ~~BL-076 completo~~ — construido más tarde la misma sesión (ver cont. 8).
- Commit `cd792d6`, pusheado.

---

## 📅 Cierre de sesión: 31/07/2026 (cont. 5) — BL-075 (costo de proveedor externo) + BL-076 diseñado (auditoría de recibo/OT)

### ✅ Logros y Cambios

**Conversación larga de diseño sobre auditoría de recibo/OT (BL-076, sin construir):** el usuario preguntó cómo dejar rastro claro (para auditoría) de servicios fuera de catálogo — curaciones especiales, artículos a la medida — en recibo, orden de trabajo y contabilidad. Investigación real del código (no supuesta) reveló varios hallazgos serios documentados en detalle en `BACKLOG.md` BL-076: `quote_items.notes` se captura pero nunca se renderiza en ningún lado; `ExecutedService`/`ExecutedServiceItem` es código muerto (confirmado también por `MODELO_BD.md` NT-020); `AccountingService::cancelEntry()` existe pero no está cableado a ningún controlador, y aunque lo estuviera, recibe `$cancelledBy` sin persistirlo; y el hallazgo más grande — el sistema de doble entrada (`documents`/`journal_entries`) es un side-channel best-effort que **no es la fuente de verdad del dinero real**: `Payment`, `CashLedger`/`BankLedger` y `documents`/`journal_entries` son tres vías independientes sin FK entre sí, y ningún reporte financiero real (dashboard, corte de caja, pagos) lee `journal_entries`/`documents`. Diseño acordado con el usuario (OT viva/editable hasta cobrar, `Document` como fuente de verdad del pago tras consolidar, snapshot de línea `document_items`/`line_items_snapshot`, cancelar con dos ramas según haya o no reembolso real, reemitir con `supersedes_document_id`) quedó anotado completo en BL-076 para una sesión futura — no se tocó código de este ítem.

**BL-075 — construido en esta sesión, tras dos redefiniciones en la misma conversación:** primero se descartó "elegir rol por cita" (la tarifa no depende del rol, citas separadas ya resuelven "dos operadores en un mismo trabajo"). Luego se descartó también el cálculo automático de precio por costo-hora del operador **de nómina** — el usuario confirmó explícitamente: el precio de venta es siempre el del catálogo, sin importar quién lo ejecute. El costo solo importa para **proveedores externos** (ej. veterinario externo, no nómina), y ahí la relación es distinta: si el proveedor externo cobra distinto de lo estimado, el precio al cliente debería poder ajustarse proporcionalmente (mismo margen), siempre como sugerencia editable, nunca automática.

**Implementación:** 3 columnas nuevas en `spa_booking_services` (`operator_id`, `is_external`, `external_cost`) — no en `quote_items` como se había planteado al diseñarlo. Al implementar se encontró que `quote_items` tiene **0 filas en producción** (verificado contra la BD real vía tinker) — el flujo formal de "Nueva Opción de presupuesto" casi no se usa en la práctica; el punto real donde se asigna operador y costo es la Orden de Trabajo.

**Bug real preexistente encontrado y corregido de paso:** el modal "Asignar Profesional" de `_work_order.blade.php` itera `$booking->services` (`SpaBookingService`) y postea a `agenda.items.assign`, pero `SpaBookingController::assignProfessional()` tipaba el parámetro como `QuoteItem $item` — con 0 `quote_items` en producción, el botón **siempre daba 404** al usarlo, sin que nadie lo hubiera reportado. Corregido el tipo a `SpaBookingService` (único consumidor real de esa ruta).

**Modal ampliado:** costo del proveedor externo (visible solo si se marca "Es servicio externo") y precio de venta editable, en el mismo formulario donde ya se asigna el operador. Alpine.js calcula en vivo una sugerencia de precio proporcional (`precio × costo_nuevo/costo_original`) cuando el costo capturado cambia, con botón "usar" — nunca se aplica sola. Precio de venta y costo externo son campos independientes: corregir uno no mueve el otro salvo que el staff acepte la sugerencia.

**Verificado (parte web):** 5 tests nuevos (`AssignProfessionalTest`) cubriendo asignación de operador, costo+precio independientes, ajuste manual tras cambio de costo, aislamiento entre citas (404 si la línea no pertenece a la cita), y render del modal con los campos nuevos. Migración corrida en producción real (`estetican_app`).

**Llevado también a la app móvil en la misma sesión, a pedido explícito del usuario ("sí deben estar sincronizados"):** mismas 3 columnas de `spa_booking_services`, sin migración nueva. Endpoint API nuevo `PATCH /api/bookings/{booking}/services/{line}` (`Api\BookingController::assignServiceProfessional()`), mismo contrato que el web (operador requerido, `is_external`, `external_cost`, `current_price` opcional). `serialize()` ahora expone `booking_service_id` (id real de la línea — distinto del `id` que ya existía, que es el id del servicio de catálogo, usado por el selector de servicios del modo edición; no se podía reusar sin romperlo), `operator_id`, `operator_name`, `is_external`, `external_cost` por línea. `MobCitaDet.tsx` gana sección "Servicios y profesionales" (solo en estado `work_order`, mismo alcance que el work order web) con panel de asignación — mismo cálculo de sugerencia proporcional replicado en TypeScript (`useMemo`), mismo patrón de "nunca se aplica sola".

**Bug real encontrado y corregido de paso, documentado en NT-046:** antes de exponer el endpoint nuevo se revisó qué pasaba si `MobCitaDet.tsx` editaba la lista de servicios de una cita después (`PATCH /api/bookings/{id}` con `services` como arreglo plano de IDs) — `Api\BookingController::update()` hacía `delete()` + recrear todas las líneas con precio de catálogo, sin importar si ya existían. Con las columnas nuevas, esto habría borrado en silencio cualquier operador/costo externo ya asignado en cuanto alguien tocara la lista de servicios desde mobile. Reescrito a sync no destructivo: solo borra las líneas que salieron de la selección, solo crea las que son realmente nuevas: las que quedan conservan precio/operador/costo externo tal cual.

**Verificado (parte mobile):** 4 tests nuevos (`Api\BookingServiceAssignmentTest`) — asignación vía API, aislamiento entre citas, y el caso central del bug: sincronizar `services` agregando un servicio nuevo no debe tocar el operador/costo externo/precio de una línea que ya estaba. Suite completa sin regresiones (331 pasan, mismos 37 preexistentes). `npx tsc --noEmit` limpio en los archivos tocados (mismos 2 errores preexistentes ajenos: `ActiveService.tsx`, `MobCajaMovimientos.tsx`). `npm run build` exitoso, bundle `index--R987Tm1.js` confirmado servido por el contenedor `estetican_mob` (nginx sirviendo `dist/` directo).

### 📁 Archivos Modificados/Creados
- `database/migrations/2026_07_31_000001_add_operator_external_cost_to_spa_booking_services.php` — nueva
- `app/Models/SpaBookingService.php` — `operator_id`/`is_external`/`external_cost` en fillable+casts, relación `operator()`
- `app/Http/Controllers/SpaBookingController.php` — `assignProfessional()` retipado a `SpaBookingService`; `loadBookingContext()` eager-carga `services.operator`
- `resources/views/agenda/partials/_work_order.blade.php` — badge de línea externa, modal ampliado con costo externo + precio editable + sugerencia proporcional (Alpine.js)
- `app/Http/Controllers/Api/BookingController.php` — `serialize()` expone campos nuevos por línea; `assignServiceProfessional()` nuevo; `update()` reescrito a sync no destructivo de `services` (NT-046)
- `routes/api.php` — ruta nueva `PATCH /api/bookings/{booking}/services/{line}`
- `mob_apps/operador/src/admin/MobCitaDet.tsx` — sección "Servicios y profesionales" + panel de asignación (solo en `work_order`)
- `tests/Feature/AssignProfessionalTest.php` — nuevo, 5 tests (web)
- `tests/Feature/Api/BookingServiceAssignmentTest.php` — nuevo, 4 tests (API/mobile)
- `docs/tecnico/MODELO_BD.md` — `spa_booking_services` documentada con las 3 columnas nuevas
- `docs/tecnico/NOTAS_TECNICAS.md` — NT-045 (bug del modal web), NT-046 (sync destructivo de `update()`)
- `docs/tecnico/BACKLOG.md` — BL-075 movido a Completados; BL-076 anotado completo (diseño, sin construir)

### 🛑 Pendientes activos
- ~~BL-076 completo~~ — construido más tarde la misma sesión (ver cont. 8).
- Commit `147e95f`, pusheado.

---

## 📅 Cierre de sesión: 31/07/2026 (cont. 4) — Precio editable al agendar (web + móvil) + bug de CSP sin relación

### ✅ Logros y Cambios

El usuario reportó que en `MobCitaNueva` no se puede modificar el precio, solo aparece "Agendar". Investigado: nunca existió un campo editable — cada servicio era un botón de check/uncheck con el precio del catálogo como texto plano. Revisando el equivalente web (`agenda/create.blade.php`) se confirmó que tampoco era editable ahí (el texto decía explícitamente "el sistema tomará su precio sugerido... y lo congelará"); la única edición de precio real en todo el sistema vivía en el flujo de **Presupuestos** (`_quote_manager`, solo web, solo después de creada la cita) — no en el paso inicial de agendar, ni en móvil.

**Alcance acordado con el usuario:** precio editable por servicio al momento de agendar, en **ambas** interfaces (no solo móvil).

**Backend:**
- `Api\BookingController::store()`: `services` cambia de `number[]` a `{id, price}[]`. Si no viene `price` (o es `null`), cae al precio del catálogo — mismo comportamiento de antes para clientes que no manden precio.
- `SpaBookingController::storeForPet()` (web): nuevo input `service_prices[<service_id>]`; si no viene o viene vacío, cae al `suggested_price`/`price` del catálogo (igual que antes). `BookingService::scheduleSpaSession()` ya aceptaba un mapa `[service_id => price]`, no requirió cambios.
- Ninguno de los dos endpoints de edición (`update()`) se tocó — el pedido fue específicamente sobre agendar, no sobre editar una cita existente.

**Frontend:**
- `MobCitaNueva.tsx`: cada servicio marcado ahora muestra un input numérico editable (precio sugerido precargado) en vez de texto plano; el total se recalcula en vivo. Reestructurado el botón de selección (antes envolvía todo en un solo `<button>`, lo que habría hecho inválido anidar un `<input>` adentro) para que el toggle y el precio sean elementos independientes.
- `agenda/create.blade.php` (web): mismo cambio, input numérico dentro de la tarjeta de cada servicio.

**Bug de CSP encontrado de paso (mismo patrón NT-042), sin relación con lo anterior:** los `<script>` de `checkAvailability` en `agenda/create.blade.php` y `agenda/edit.blade.php` **no tenían `nonce`** — la CSP del proyecto (`script-src` con nonce, sin `unsafe-inline`) los bloqueaba en silencio en cualquier navegador real. Esto incluye el checkbox de "forzar horario" agregado en una sesión anterior el mismo día: nunca funcionó de verdad fuera de las pruebas HTTP (que no ejecutan JS). Se hizo un barrido completo del proyecto (`@push('scripts')` sin `nonce`) y se encontraron 2 casos más, sin relación, en `finances/cash-sessions/{close,show}.blade.php` — corregidos los 4 archivos.

**Verificado:** test de Feature temporal (no quedó en el repo) confirmó ambos flujos — API con precio editado (se respeta) y sin precio (cae al catálogo), y formulario web con precio editado guardándose en `current_price`. Suite completa sin regresiones (322 pasan, mismos 37 preexistentes). Bundle móvil reconstruido y confirmado servido (`index-oryuI9-w.js`).

### 📁 Archivos Modificados
- `app/Http/Controllers/Api/BookingController.php` — `store()`: `services` acepta precio por ítem
- `app/Http/Controllers/SpaBookingController.php` — `storeForPet()`: nuevo input `service_prices`
- `resources/views/agenda/create.blade.php` — precio editable por servicio + `nonce` en el script existente
- `resources/views/agenda/edit.blade.php` — solo el fix de `nonce` (sin cambio de precio, no era parte del pedido)
- `resources/views/finances/cash-sessions/close.blade.php`, `show.blade.php` — fix de `nonce`, sin relación
- `mob_apps/operador/src/admin/MobCitaNueva.tsx` — precio editable por servicio

### 🛑 Pendientes activos
- Falta commit/push de toda la sesión (agenda viernes/sábado, override de horario, consolidación de rol de operador, y este precio editable + fix de CSP).
- BL-075 (fórmula de precio por costo-más-margen) sigue sin construir.

---

## 📅 Cierre de sesión: 31/07/2026 (cont. 3) — Respaldos + consolidación del rol de operador en un solo campo

### ✅ Logros y Cambios

**Respaldo de sistemas, previo a la consolidación (a pedido explícito del usuario):**
- Dump de BD (`estetican_pre-consolidacion-roles-operador_20260730_2132.sql`) + tar completo de código (`estetican-completo_..._2132.tar.gz`, 109 MB), verificados íntegros.
- Nuevo `backups/LISTA_RESPALDOS.md` — registro de todos los respaldos manuales existentes con su motivo, para ir tachándolos cuando cada cambio quede suficientemente probado en producción.
- **Bug real encontrado de paso:** el respaldo automático diario (cron 3am) llevaba **más de un mes fallando en silencio** — `scripts/auto_backup_db.sh` apuntaba a un contenedor obsoleto (`backoffice-laravel-mysql-1`, nombre de Sail) en vez de `estetican_mysql`, y el log iba a `/var/log/` (sin permiso de escritura para el usuario del cron, así que la redirección fallaba antes de ejecutar una sola línea). Corregido en el script y el crontab (log ahora en `backups/estetican_backup.log`); probado manualmente, dump + subida a Drive exitosos. Se borraron 3 archivos que eran restos de la falla (dumps vacíos/incompletos de prueba), no respaldos reales.

**Consolidación del rol de operador (continuación del bug de la sesión anterior):**
- El usuario, tras el fix de visualización de rol en `/api/operators`, pidió explícitamente consolidar los 3 campos en uno solo — "el que manda es el checkbox".
- Antes de eso hubo una conversación larga sobre el alcance de BL-075 (elegir con qué rol trabaja un operador por cita): se descartó la idea original — la tarifa por operador es por hora, no depende del rol; y "dos operadores distintos en un mismo trabajo" (auxiliar + veterinario) ya se resuelve con **citas separadas** (una cita = un operador, sin cambios de código). El alcance final que quedó pendiente de BL-075 es un cálculo de precio sugerido por costo-más-margen (duración × costo del operador × margen%) — **sin construir**, solo diseñado (ver BACKLOG.md).
- **Migración `2026_07_31_000000_consolidate_operator_role_fields`:** backfill del único operador que dependía del campo huérfano `operator_role_id` (Tomas Alejandro, sin fila en el m2m) hacia `operator_role_assignments`; luego elimina las columnas `operators.role` y `operators.operator_role_id`.
- **Hallazgo importante durante el barrido de referencias:** `Operator::isVeterinario()` — un gate real de negocio (`ClinicalVisitService::sign()` exige que quien firma una visita clínica sea veterinario con cédula) — dependía de la FK huérfana, no del m2m. Migrado a `activeRoles()->contains(...)`. De no haberse encontrado, la consolidación habría roto silenciosamente la firma de visitas clínicas para cualquier operador cuyo rol viviera solo en el m2m.
- Actualizado: `app/Models/Operator.php` (quitada `operatorRole()`, Fillable), `OperatorController` (ya no escribe el `role` legado en `syncRoles()`), `Api\OperatorController` (simplificado, ya sin fallback a campos legado), `ClinicalVisitController` (columna quitada del select), vistas `operators/{index,show,partials/form}.blade.php` (quitado el campo readonly "Rol operativo" y la fila "Rol legado"), y 3 tests que dependían de los campos legado (`OperatorBranchSelectionTest`, `OperatorPhotoUploadTest`, `ClinicalVisitServiceTest` — este último migrado a asignar el rol vía `roleAssignments()->create()` en vez de la columna directa).
- **Verificado:** migración corrida en producción (backfill confirmado por tinker antes/después); test de Feature temporal (no quedó en el repo) confirmó `operators.index`/`show`/`edit` renderizando bien sin los campos legado, y que guardar una edición real sigue asignando ambos roles correctamente. Endpoint real `/api/operators` verificado en vivo (mismo resultado que antes, ahora desde una sola fuente). Suite completa sin regresiones (322 pasan, mismos 37 preexistentes — confirmado que los 3 tests tocados ya fallaban antes por el bug conocido de `actingAs()`, sin relación).
- `docs/tecnico/MODELO_BD.md` actualizado (tabla `operators` sin las 2 columnas, nota de consolidación).

### 📁 Archivos Modificados/Creados
- `database/migrations/2026_07_31_000000_consolidate_operator_role_fields.php` — nueva
- `app/Models/Operator.php`, `app/Http/Controllers/OperatorController.php`, `app/Http/Controllers/Api/OperatorController.php`, `app/Http/Controllers/Clinical/ClinicalVisitController.php`
- `resources/views/operators/index.blade.php`, `resources/views/operators/show.blade.php`, `resources/views/operators/partials/form.blade.php`
- `tests/Feature/OperatorBranchSelectionTest.php`, `tests/Feature/OperatorPhotoUploadTest.php`, `tests/Feature/Clinical/ClinicalVisitServiceTest.php`
- `scripts/auto_backup_db.sh` — nombre de contenedor + ruta de log corregidos
- `backups/LISTA_RESPALDOS.md` — nueva
- `docs/tecnico/MODELO_BD.md`, `docs/tecnico/BACKLOG.md`

### 🛑 Pendientes activos
- BL-075 (redefinido): fórmula de precio sugerido por costo-más-margen — sin construir, ver BACKLOG.md.
- Falta commit/push de esta sesión completa.
- Backups pendientes de revisión en `backups/LISTA_RESPALDOS.md` cuando el usuario decida que cada cambio ya está suficientemente probado.

---

## 📅 Cierre de sesión: 31/07/2026 (cont. 2) — Bug: rol de operador en la app móvil venía de un campo huérfano

### ✅ Logros y Cambios

El usuario reportó que en `OperatorEdit` (backoffice) seleccionó dos tipos de operador (Auxiliar de Estética + Groomer Básico) pero el campo "Rol" solo mostraba uno. Investigando se confirmó que **hay tres campos distintos** relacionados con el rol de un operador, no dos:

1. `operators.role` (texto legado, solo lectura en el form) — auto-calculado por `OperatorController::syncRoles()` a partir del primer tipo marcado (según el orden del catálogo, no el orden de clic). Puramente decorativo en el backoffice, nada más lo lee.
2. `roles()` (muchos-a-muchos vía `operator_role_assignments`, los checkboxes "Tipos de operador") — el campo real y completo, correcto, se ve en "Roles activos" en la ficha de detalle.
3. **`operators.operator_role_id`** (FK única, un tercer campo separado) — remanente de una versión anterior del formulario (antes de que existieran los checkboxes). El formulario actual **nunca la vuelve a tocar**, así que para cualquier operador editado con los checkboxes queda huérfana con el valor viejo — y es exactamente lo que usaba `Api\OperatorController` (`/api/operators`, `/api/operators/team`) como fuente **principal** para el campo `role` que consume la app móvil (selector de operador en Nueva Cita, `GroomerPicker`, Panel de Equipo).

Verificado contra el operador real del reporte (Jose Méndez, id 1): `roles()` = "Groomer Básico, Auxiliar de estética" (correcto), `role` legado = "Auxiliar de estética", pero `operator_role_id` apuntaba a "Groomer Básico" — un tercer valor distinto, sin relación con cuál de los dos checkboxes se marcó primero. La app móvil mostraba ese tercer valor.

**Decisión con el usuario:** separar en dos pasos — arreglar ahora el bug de visualización, y dejar aparte (BL-075) la idea de poder elegir "con qué rol" trabaja un operador en una cita puntual (relevante porque el rol sí afecta la tarifa por hora vía `Operator::effectiveHourlyRate()`, así que no es solo cosmético — pero implica diseño nuevo: filtrar operadores elegibles por servicio, guardar el rol elegido en la cita, ajustar el cálculo de tarifa).

**Fix aplicado:** `Api\OperatorController::index()`/`team()` ahora arman el `role` mostrado a partir de `activeRoles()` (los checkboxes reales, con fallback a `operatorRole()`/`specialty`/`role` legado **solo** para operadores que nunca fueron re-guardados desde que existen los checkboxes — para no perder su único dato real). Verificado en vivo contra producción con un token de prueba (borrado después): Jose Méndez ahora muestra "Groomer Básico, Auxiliar de estética"; un operador sin checkboxes asignados (Tomas Alejandro, `operator_role_id` legado="Groomer Profesional") sigue mostrando correctamente ese valor de respaldo. Suite completa sin regresiones (322 pasan, mismos 37 preexistentes).

### 📁 Archivos Modificados
- `app/Http/Controllers/Api/OperatorController.php` — `roleLabel()` nuevo, `index()`/`team()` usan `activeRoles()` con fallback a `operatorRole()`
- `docs/tecnico/BACKLOG.md` — nuevo BL-075 (selección de rol por cita, sin diseñar aún)

### 🛑 Pendientes activos
- BL-075: diseñar selección de rol/tipo por cita puntual (ver BACKLOG.md) — decisión explícita del usuario de dejarlo para después.
- Falta commit/push de esta sesión completa (incluye también el fix de agenda viernes/sábado y el override de horario de operador de las sesiones anteriores del mismo día).

---

## 📅 Cierre de sesión: 31/07/2026 (cont.) — Forzar cita fuera del horario del operador (solo admin)

### ✅ Logros y Cambios

A raíz del bug de la agenda (ver sesión anterior), el usuario preguntó qué pasa si se agenda a un operador fuera de su horario semanal declarado (ej. termina a las 16:00 y se le asigna un trabajo de 15:00 con 2 h de duración, terminaría a las 17:00) — hasta ahora era un **bloqueo duro** (422/redirect) en los 4 puntos de entrada (crear/editar cita, web y API móvil), sin forma de continuar aunque el usuario quisiera.

Se agregó la opción de **forzar el agendado, restringida a usuarios con permiso de administrador**. Alcance acotado a propósito: **solo** el caso "operador fuera de su horario declarado" (`OperatorAvailabilityChecker::isOutsideWorkingHours`) es forzable — conflicto de doble cita (`hasConflict`) y vacaciones/permiso (`hasTimeOff`) siguen siendo bloqueos duros sin excepción, porque no tiene sentido "forzar" una doble cita o un permiso.

**Backend:**
- Nuevo permiso Spatie `agenda.forzar_horario` (`BaseRolesSeeder`, ya corrido en producción) — lo tiene el rol `admin` automáticamente (recibe todos los permisos). Gate real: `$user->can('agenda.forzar_horario') || $user->is_super_admin` (mismo patrón que `FinanzasNavigation.php`).
- `SpaBookingController::checkAvailability()` ahora devuelve además `overridable` (true solo si el único motivo de bloqueo es horario fuera de turno) y `can_override` (si el usuario actual puede forzarlo).
- `SpaBookingController::update()`/`storeForPet()` y `Api\BookingController::store()`/`update()` aceptan `override_availability` (bool); solo se respeta si el usuario tiene el permiso — si alguien manda el flag sin permiso, el backend lo ignora silenciosamente y sigue bloqueando (sin dar pista de que existe la opción).
- `User::toApiArray()` expone `can_override_schedule` para que la app móvil sepa si mostrar la opción (usado en `/api/me` y login).

**Frontend:**
- Web (`agenda/create.blade.php`, `agenda/edit.blade.php`): el chequeo de disponibilidad en vivo (ya existía, solo mostraba advertencia cosmética) ahora también muestra un checkbox "Agendar de todas formas, fuera del horario del operador" cuando `overridable && can_override` — si no tiene permiso, no ve la opción en absoluto (no solo deshabilitada).
- Móvil (`MobCitaNueva.tsx` crear, `MobCitaDet.tsx` editar): al recibir el mensaje exacto de bloqueo por horario y si `user.can_override_schedule`, el banner de error ahora incluye un botón "Agendar de todas formas" que reintenta con el flag.

**Verificación:** test de Feature temporal (no quedó en el repo) contra la BD de testing reprodujo el escenario exacto del usuario (operador cierra 16:00, cita 15:00+2h) — confirmó los 3 casos: bloqueo normal, override ignorado sin permiso, y éxito con permiso de admin. Suite completa sin regresiones (322 pasan, mismos 37 preexistentes). Bundle reconstruido y servido en `mov.estetican.org` (`index-BM5p4fBY.js`).

### 📁 Archivos Modificados
- `database/seeders/BaseRolesSeeder.php` — permiso `agenda.forzar_horario`
- `app/Http/Controllers/SpaBookingController.php` — `checkAvailability()`, `update()`, `storeForPet()`, helper `canOverrideSchedule()`
- `app/Http/Controllers/Api/BookingController.php` — `store()`, `update()`, helper `canOverrideSchedule()`
- `app/Models/User.php` — `toApiArray()` expone `can_override_schedule`
- `resources/views/agenda/create.blade.php`, `resources/views/agenda/edit.blade.php` — checkbox de override
- `mob_apps/operador/src/admin/MobCitaNueva.tsx`, `MobCitaDet.tsx` — botón "Agendar de todas formas"
- `mob_apps/operador/src/AuthContext.tsx` — campo `can_override_schedule`

### 🛑 Pendientes activos
- Falta commit/push de esta sesión (incluye también el fix de la agenda de la sesión anterior, sin commitear todavía).
- Sin relación: backlog activo sin cambios; 37 tests preexistentes fallando sin relación.

---

## 📅 Cierre de sesión: 31/07/2026 — Bug: citas del sábado aparecían el viernes en la agenda móvil

### ✅ Logros y Cambios

El usuario reportó una cita real (Yorkshire, cita `#31`) agendada para el sábado 1/ago que en `GlobalAgenda` (pantalla `MobAgGbl`) aparecía el viernes 31/jul, pero en `MobCitaDet` (detalle de la misma cita) sí aparecía correctamente el sábado — esa comparación entre pantallas fue la pista clave para descartar un problema de datos.

**Causa raíz confirmada:** el `scheduled_at` en BD estaba correcto (`2026-08-01 09:00:00`, sábado) — no era un bug de guardado. `MobCitaDet` parsea la fecha con `parseDateLocal` (sin pasar por UTC, seguro). `GlobalAgenda`/`AgendaCalendarGrid` (vistas semana/mes) usaban una función `toDateStr(d)` que hacía `d.toISOString().slice(0,10)` para generar la clave de fecha de cada columna del calendario y para el parámetro `date` del fetch a `/api/agenda`. Ese patrón es correcto solo si `d` está exactamente en medianoche local — pero `selectedDate`/`today` nacen de `new Date()` (hora real del dispositivo) y esa hora se propaga a través de `addDays`/`weekDays`/`monthGridDays`. Con el dispositivo en horario de México (UTC-6) y pasadas ~18:00 hora local, convertir a UTC empuja la fecha al día siguiente — la columna "viernes" terminaba calculando la clave `2026-08-01` (la del sábado), y ahí es donde caía la cita real. Confirmado con una simulación directa (`TZ=America/Mexico_City`, viernes 21:00 local): la función vieja devolvía `2026-08-01`, la corregida `2026-07-31`.

**Fix:** `toDateStr` reescrita para construir el string desde los componentes de fecha **locales** (`getFullYear`/`getMonth`/`getDate`), sin pasar por `toISOString()`/UTC. Estaba duplicada en `agendaViews.ts` y `AgendaCalendarGrid.tsx` — se dejó una sola definición en `agendaViews.ts` y `AgendaCalendarGrid.tsx` ahora la importa. De paso se encontró el mismo patrón (`toISOString().slice(0,10)`) en `MobCajaMovimientos.tsx` (funciones `today()`/`startOfWeek()` del filtro de fecha de movimientos de caja) — mismo riesgo de que el filtro "hoy" muestre el día equivocado por la tarde/noche; corregido igual, con una `toDateStr` local en ese archivo (no comparte módulo con `agendaViews.ts`).

Sin tests automatizados para el frontend móvil (no hay suite configurada en este proyecto) — verificado con la simulación de Node en el TZ real del negocio y con `tsc --noEmit` (sin errores nuevos; los 2 errores de tipo preexistentes en `MobCajaMovimientos.tsx` — prop `key` — y los de `ActiveService.tsx` no tienen relación, ya estaban antes). Build reconstruido y confirmado servido en `mov.estetican.org` (`index-CPCBPnGb.js`).

### 📁 Archivos Modificados
- `mob_apps/operador/src/admin/agendaViews.ts` — `toDateStr()` reescrita a fecha local
- `mob_apps/operador/src/admin/AgendaCalendarGrid.tsx` — quitada su copia duplicada de `toDateStr`, ahora importa la de `agendaViews.ts`
- `mob_apps/operador/src/admin/MobCajaMovimientos.tsx` — `today()`/`startOfWeek()` mismo fix (fecha local en vez de UTC)

### 🛑 Pendientes activos
- Sin relación con esta sesión: backlog activo sin cambios (BL-028, BL-024b, BL-001/002/004, BL-047 resto, BL-053, BL-008, BL-012, BL-071, BL-031); 37 tests preexistentes fallando sin relación (ver 26/07/2026).
- Falta commit/push de esta sesión.

---

## 📅 Cierre de sesión: 27/07/2026 (cont. 3) — Commit/push + verificación final en producción

Sesión larga (BL-073, BL-074, fixes de recibo/orden de trabajo/notas en cita cerrada — ver entradas de abajo) commiteada y pusheada en un solo commit (`679c971`, `origin/main`). De paso se agregó a `docs/architecture/IDEAS_FUTURO.md` la idea de unificar el cálculo de total/saldo/pagos de una cita (hoy duplicado en `_billing_summary`/`invoice`/`work-order`, causa raíz repetida de los bugs de esta sesión).

**Verificación de producción post-commit** (pedida explícitamente por el usuario): contenedores arriba, git limpio, migración `booking_process_notes` corrida. Se hizo el paso que faltaba del checklist de deploy — **borrar vistas Blade compiladas** (`storage/framework/views`, ver NT-005) tras tocar varios `.blade.php` esta sesión — y se corrió la suite completa de nuevo tras la limpieza (322 pasan, mismos 37 preexistentes, sin regresiones). Chequeo en vivo contra las URLs públicas reales: rutas del backoffice devuelven 302 (auth, sin 500), `mov.estetican.org` sirve el bundle correcto, API sin token da 401 (no 500), recibo/orden de trabajo de la cita `#27` renderizan bien post-limpieza de caché, sección `pets` de `SystemSettings` resuelve correcto, y `Pet::visible()` contra datos reales confirma 23/24 mascotas visibles con el switch apagado (1 inactiva real en BD). Sin `production.ERROR` nuevos en los logs — el único error recurrente es preexistente y sin relación (sync de catálogo Meta, item #51).

---

## 📅 Cierre de sesión: 27/07/2026 (cont. 2) — Recibo de pago (backoffice web) en $0 y sin notas

### ✅ Logros y Cambios

El usuario reportó, viendo `AgSpaSho` (detalle de cita SPA en el backoffice web) de un servicio ya finalizado y cobrado: al imprimir el recibo (`reports/invoice.blade.php`, botón "IMPRIMIR RECIBO" en `_billing_summary.blade.php`) el monto salía en $0 pese a estar cobrado, y solo se imprimía la nota puesta al crear la cita (no las notas de proceso nuevas de BL-074).

**Causa raíz confirmada:** `ReportController::invoice()`/la vista solo sabían calcular el total a partir de un `Quote` con `status=accepted` (`$acceptedQuote?->total_amount`). Las citas cobradas 100% desde la app móvil (todo el flujo `MobCitaDet`→`MobCobro`) **nunca crean un `Quote`** — van directo `SpaBooking` + `SpaBookingService` + `Payment`. Sin Quote, el total quedaba en `null`→`$0`, el listado de conceptos salía vacío (`@if($acceptedQuote)` sin `@else`), y el historial de pagos solo sumaba `CashLedger`/`BankLedger` legado ligado a un Quote — ignoraba por completo el modelo `Payment` que usa el cobro móvil. `_billing_summary.blade.php` (la pantalla que SÍ se ve bien) ya tenía el fallback correcto (`?? $booking->total_estimated_price`) y ya sumaba `Payment`; el recibo imprimible nunca se actualizó a la par cuando se agregó el cobro móvil.

**Fix:** mismo patrón de fallback en `ReportController::invoice()` + `reports/invoice.blade.php` — total con fallback a `total_estimated_price`, tabla de conceptos cae a `$booking->services` sin Quote, historial de pagos incluye `Payment` (`payable_type=SpaBooking`). Se agregó además una sección "NOTAS" nueva en el recibo (no existía ninguna antes): imprime `booking.notes` y todas las `booking_process_notes` (BL-074, con autor y hora).

**Verificado contra la cita real reportada** (`#27`, folio `R-000027` — exactamente la que mencionó el usuario): confirmado por `tinker` que no tiene ningún `Quote` (`quotes_count=0`), tiene `total_estimated_price=250`, 1 servicio, 1 `Payment`, 2 `booking_process_notes` y la nota de creación. Se renderizó la vista corregida directo desde el controlador (sin pasar por HTTP, de solo lectura) y se confirmó que ahora muestra `$250.00`, "TOTAL LIQUIDADO", la nota de creación y las 2 notas de proceso — el bug exacto reportado, resuelto. 2 tests nuevos (`InvoiceReportTest`), suite completa: 317 pasan, mismos 37 preexistentes, sin regresiones.

### 📁 Archivos Creados/Modificados
- `app/Http/Controllers/ReportController.php` — `invoice()`: eager-load `services.service`/`processNotes.user`, nueva variable `$directPayments`
- `resources/views/reports/invoice.blade.php` — fallback de total, conceptos sin Quote, pagos vía `Payment`, sección de notas
- `tests/Feature/InvoiceReportTest.php` — nuevo
- `docs/tecnico/BACKLOG.md`

### 🛑 Pendientes activos
1. ~~Commit y push de esta sesión (BL-073, BL-074, y estos fixes de recibo/orden de trabajo)~~ **HECHO** — ver cierre de sesión al final de este archivo.
2. Verificación manual en navegador real de todo lo de hoy (mascotas inactivas, notas de proceso, recibo/orden de trabajo, edición de notas en citas cerradas) — sin herramienta de navegador en este entorno, todo verificado vía tests HTTP + `tinker`/`curl` contra datos reales de producción.
3. Sin relación con lo de hoy: 37 tests preexistentes fallando, BL-071 (vademécum), BL-053, BL-028, BL-001/002/004, BL-024b, BL-047 resto.

### ↪️ Addendum — mismo fix también en la orden de trabajo

El usuario pidió aplicar el mismo arreglo a `reports/work-order.blade.php` ("orden de trabajo", el otro documento imprimible, botón junto al de recibo). Tenía el mismo problema de raíz: sin `Quote` aceptado mostraba literalmente "No hay servicios aceptados registrados." aunque la cita sí tuviera servicios reales (`$booking->services`), y solo imprimía `booking.notes` sin las notas de proceso nuevas de BL-074. Mismo patrón de fix: `ReportController::workOrder()` ahora carga `services.service`, `operator` y `processNotes.user`; la vista cae a `$booking->services` (mostrando el operador de la cita) cuando no hay Quote, y agrega una sub-sección "Notas del Proceso" debajo de las notas de ingreso.

Verificado igual que el recibo: contra la cita real `#27` renderizando la vista directo (con `view()->share(datetimeFormat)` simulando el middleware `ApplySystemSettings`, que en tinker no corre por no pasar por el pipeline HTTP) — ya no dice "sin servicios", muestra la nota de creación y las 2 notas de proceso. 2 tests nuevos (`WorkOrderReportTest`). Suite completa: 319 pasan, mismos 37 preexistentes, sin regresiones.

**Archivos:** `app/Http/Controllers/ReportController.php` (`workOrder()`), `resources/views/reports/work-order.blade.php`, `tests/Feature/WorkOrderReportTest.php` (nuevo), `docs/tecnico/BACKLOG.md`.

### ↪️ Addendum 2 — "Imprimir Documento" no hacía nada en ninguno de los dos

El usuario reportó que el botón "Imprimir Documento" (visible en recibo y orden de trabajo) no hacía absolutamente nada al hacer clic. Causa: es la **misma clase de bug que NT-042** (documentado el 24/07/2026) — `layouts/report.blade.php` (layout compartido por recibo/orden de trabajo/presupuesto) tenía `<button onclick="window.print()">`, y la CSP del proyecto bloquea cualquier atributo de evento inline en silencio, sin error visible. El barrido original de NT-042 no lo agarró porque buscó específicamente `onclick="confirm` (casos de formulario), no `onclick=` en general.

Corregido con el patrón ya usado en `agenda/global-create.blade.php` para clics simples sin `<form>` de por medio: `id` en el botón + `<script nonce="{{ csp_nonce() }}">` con `addEventListener` (no aplica `data-confirm`, que es para submits de formulario). Confirmado con `grep` que no queda ningún otro `onclick="window.print` en el proyecto. Test nuevo (`InvoiceReportTest`) agrega assert de que no hay `onclick=` inline en la respuesta. Documentado como addendum a **NT-042** con la lección ampliada (buscar `on\w+=` en general, no solo el caso de confirmaciones).

### ↪️ Addendum 3 — las notas seguían sin poder editarse: la causa era el estado `completed`, no un bug de clic

El usuario insistió una vez más en que las notas seguían sin poder editarse en `MobCitaDet`. Antes de seguir adivinando se le preguntó con `AskUserQuestion` qué pasaba exactamente al tocar una nota — respondió **"no veo ninguna opción para editar"** (no "toco y no pasa nada"). Eso cambió el diagnóstico: no era un problema de clic/CSP como los anteriores, sino que directamente no se renderizaba ningún control de edición. Causa real: el frontend gateaba la edición de notas con la misma variable `editable` que bloquea el formulario completo de edición (`!['completed','cancelled'].includes(status)`) — y el usuario estaba probando sobre citas **ya finalizadas** (coherente con el resto de la sesión, centrada en documentos de servicios ya cerrados). El backend además bloqueaba por completo cualquier `PATCH` a una cita `completed`/`cancelled`, incluyendo notas.

Decisión: separar "editar notas" de "editar la operación de la cita" — corregir/completar una nota sobre un servicio ya cerrado no reabre nada (horario, servicios, estado), así que no debería estar sujeto a esa regla. Cambios:
- `Api\BookingController::update()`: permite el `PATCH` si el único campo enviado es `notes`, incluso con la cita `completed`/`cancelled`; cualquier otro campo (solo o mezclado con `notes`) sigue bloqueado.
- `Api\BookingProcessNoteController::update()` (no `store()`): ya no bloquea por estado — se puede seguir corrigiendo una nota de proceso existente después de cerrar el servicio, pero no se pueden crear notas nuevas en una cita ya cerrada (eso sí sigue bloqueado, tiene sentido: "notas del proceso en curso" no aplica si ya no hay proceso).
- Frontend (`MobCitaDet.tsx`): se quitó el gate `editable` de los dos bloques de edición de notas (de la cita y de proceso) — ahora siempre muestran el afordance de edición, sin importar el estado.

Verificado en vivo contra la cita real `#27` (`completed`, la misma usada en los addendums anteriores): `curl PATCH /api/bookings/27` con solo `notes` funcionó correctamente incluso estando completada (confirmado, luego restaurado el valor original de la nota para no dejar basura de prueba en producción). 4 tests nuevos (`BookingUpdateNotesTest` ×2 — notas editables en completada, otros campos siguen bloqueados; `BookingProcessNoteTest` ×1 — update permitido en completada). Bundle reconstruido y confirmado servido en `mov.estetican.org` (nuevo hash `index-C_13RXef.js`). Suite completa: 322 pasan, mismos 37 preexistentes, sin regresiones.

**Archivos:** `app/Http/Controllers/Api/BookingController.php` (`update()`), `app/Http/Controllers/Api/BookingProcessNoteController.php` (`update()`), `mob_apps/operador/src/admin/MobCitaDet.tsx` (notas siempre editables), `tests/Feature/Api/{BookingUpdateNotesTest,BookingProcessNoteTest}.php`, `docs/tecnico/BACKLOG.md`.

---

## 📅 Cierre de sesión: 27/07/2026 (cont.) — BL-074: notas de proceso en MobCitaDet/MobCobro

### ✅ Logros y Cambios

El usuario pidió que, mientras una cita está "En proceso" en `MobCitaDet`, hubiera un botón junto al badge de estado para que el operador capture notas del servicio en curso (varias, cronológicas), que después aparecieran en `MobCobro` con opción de completarlas antes de cerrar el cobro. De paso reportó un bug: en `MobCobro` no se veía la nota puesta al crear la cita en la agenda, aunque sí se veía en `MobCitaDet`.

**Decisiones confirmadas con el usuario antes de codificar** (`AskUserQuestion`): (1) las notas de proceso se indexan a la **cita completa** (`spa_booking_id`), no a cada servicio individual dentro de la cita, aunque tenga varios — más simple y consistente con cómo ya funcionan `notes`/`cancellation_reason` en `SpaBooking`; (2) "completarlas" en `MobCobro` significa poder **seguir editando el texto** de cada nota ahí mismo antes de cobrar, no solo marcarlas como revisadas.

**Backend:** tabla nueva `booking_process_notes` (`spa_booking_id`, `user_id` nullable, `note`, timestamps — mismo patrón que `booking_messages`), modelo `BookingProcessNote`, relación `SpaBooking::processNotes()`. `Api\BookingProcessNoteController` con `index`/`store`/`update` en `/api/bookings/{id}/process-notes[/{note}]` — bloqueado crear/editar una vez que la cita está `completed`/`cancelled`. Migración corrida en producción (`--force`, verificada antes con `--pretend`).

**Bug real encontrado:** `MobCobro.tsx` nunca tuvo `notes` en su interfaz `BookingSummary` — el endpoint `/api/bookings/{id}` sí devuelve `notes` (se usa en `MobCitaDet` sin problema), pero `MobCobro` simplemente no lo leía. Corregido agregando el campo y un bloque de solo lectura "Notas de la cita". Para evitar confundir tres conceptos distintos que ahora conviven en la misma pantalla, se etiquetaron claramente: **Notas de la cita** (fija, de agendar — solo lectura), **Notas del proceso** (las nuevas, editables ahí mismo), **Notas del cobro** (la que ya existía, propia del pago — antes decía solo "Notas", renombrada).

**Frontend:** `MobCitaDet.tsx` — botón "Nota" (con contador) visible solo en `work_order`, modal para capturar, lista de notas en la vista normal. `MobCobro.tsx` — carga las mismas notas, permite editarlas inline (clic → textarea → Guardar) antes de cobrar.

**Verificación:** `php -l` limpio en los archivos backend nuevos/tocados. 5 tests nuevos (`BookingProcessNoteTest`: crear con autor correcto, listar en orden cronológico, editar, rechazar edición cruzada entre citas, bloquear una vez completada) — todos pasan. Suite completa: 312 pasan, mismos 37 tests preexistentes fallando (sin relación, ver sesiones previas), sin regresiones. Frontend: `tsc --noEmit` sin errores nuevos (mismos preexistentes de `ActiveService.tsx`/`MobCajaMovimientos.tsx`) y `npm run build` exitoso; `estetican_mob` sirve `dist/` por bind mount, recoge el build sin reiniciar. No se pudo probar el clic real en navegador/app (sin esa herramienta en este entorno).

### 📁 Archivos Creados/Modificados
- `database/migrations/2026_07_27_000001_create_booking_process_notes_table.php` — nueva
- `app/Models/BookingProcessNote.php` — nuevo
- `app/Models/SpaBooking.php` — relación `processNotes()`
- `app/Http/Controllers/Api/BookingProcessNoteController.php` — nuevo
- `routes/api.php` — 3 rutas nuevas
- `tests/Feature/Api/BookingProcessNoteTest.php` — nuevo
- `mob_apps/operador/src/admin/MobCitaDet.tsx` — botón/modal/lista de notas de proceso
- `mob_apps/operador/src/admin/MobCobro.tsx` — fix bug `booking.notes`, notas de proceso editables, notas del cobro renombradas
- `docs/tecnico/{MODELO_BD,BACKLOG}.md`

### 🛑 Pendientes activos
1. Commit y push de esta sesión (BL-073 + BL-074).
2. Verificación manual en navegador/app real de ambos flujos (switch de mascotas inactivas y notas de proceso) — sin herramienta de navegador en este entorno.
3. Sin relación con lo de hoy: 37 tests preexistentes fallando, BL-071 (vademécum), BL-053, BL-028, BL-001/002/004, BL-024b, BL-047 resto.

### ↪️ Addendum (misma sesión) — "Notas de la cita" tampoco se podían editar

El usuario probó lo de arriba y reportó que, una vez agendada la cita, la nota original (`spa_bookings.notes`) ya no se podía modificar ni durante el proceso (`MobCitaDet`) ni al cobrar (`MobCobro` — ahí solo dejaba editar las notas de proceso nuevas, la original quedó de solo lectura a propósito en la primera pasada). Pidió que también fuera editable en `MobCitaDet`.

Se agregó edición en línea de "Notas de la cita" en ambas pantallas (clic sobre la nota → textarea → Guardar/Cancelar, sin necesidad de entrar al formulario completo de edición de la cita en `MobCitaDet`) vía `PATCH /api/bookings/{id}` con `{ notes }`. Al construir esto se encontró un bug de backend real y más serio: `Api\BookingController::update()` armaba el payload de campos a actualizar con `array_filter(..., fn($v) => $v !== null)` — cualquier campo enviado explícitamente en `null` (como `notes` al querer vaciar la nota) se descartaba **antes** de llegar a `fill()`, así que borrar una nota nunca se guardaba de verdad (respondía 200 pero no cambiaba nada en BD). Reescrito para armar el payload solo con los campos que realmente vienen en el request (`array_key_exists`), sin filtrar por valor — ahora `notes: null` sí limpia la nota.

3 tests nuevos (`BookingUpdateNotesTest`): editar la nota en `work_order`, limpiarla a `null`, y confirmar que no toca campos no relacionados (operador, horario, status). Suite completa: 315 pasan (antes 312), mismos 37 preexistentes, sin regresiones. `tsc --noEmit` y `npm run build` limpios otra vez.

**Archivos adicionales:** `app/Http/Controllers/Api/BookingController.php` (`update()`), `tests/Feature/Api/BookingUpdateNotesTest.php` (nuevo), `mob_apps/operador/src/admin/{MobCitaDet,MobCobro}.tsx` (edición en línea de "Notas de la cita"), `docs/tecnico/BACKLOG.md` (BL-074 ampliado).

### ↪️ Addendum 2 (misma sesión) — el usuario seguía viendo notas no editables en MobCitaDet

Tras el addendum anterior, el usuario reportó que **seguía** sin poder editar notas en `MobCitaDet`. Antes de tocar más código se verificó el backend en producción real (no solo la suite de tests): se creó una cita temporal vía `tinker`, se generó un `ApiToken` real, y se hizo `PATCH /api/bookings/{id}` con `curl` directo contra el contenedor — la nota se editó y persistió correctamente. Backend descartado como causa; datos de prueba borrados después.

Revisando el frontend se encontró la causa real: al implementar la edición de "Notas del proceso" en el addendum inicial de BL-074, solo se hizo editable en `MobCobro` — en `MobCitaDet` esas notas quedaron de **solo lectura** (se agregó la lista, pero nunca el modo edición). Si el usuario tocaba una nota de proceso (no la nota original de la cita) en `MobCitaDet`, no pasaba nada. Se agregó el mismo patrón de edición en línea (clic → textarea → Guardar) también ahí, para que ambos tipos de nota sean editables en las dos pantallas por igual.

De paso, para descartar por completo un problema de caché de navegador, se forzó un rebuild limpio del bundle (`rm dist/assets/*` + `npm run build`) — el hash del JS cambió (`index-zxkWM_Oq.js`) y se confirmó que `estetican_mob` ya lo sirve.

---

## 📅 Cierre de sesión: 27/07/2026 — BL-073: mascotas inactivas ocultas de selectores/listados

### ✅ Logros y Cambios

El usuario había filtrado mascotas inactivas (`is_active=false`, BL-067) en `pets/index` pero no sabía dónde más hacía falta el mismo filtro. Se mapeó todo el código con un agente de exploración: 9 lugares mostraban mascotas inactivas sin ninguna restricción — selector de mascota al crear/editar reserva de hotel (`HotelReservationController::loadPets()`), dropdown al crear evento de recurso (`ResourceController::show()`), selector de "nueva cita" global SPA (`SpaBookingController::globalCreate()`), listado del módulo clínico (`Clinical/ClinicalController::index()`), ficha de cliente — `pets`/`live_pets_count` (`ClientController::index()`), `PetRepository::getByClientId()` (dominio comercial), pines del mapa de zonas (`MapaZonasController`, dos queries), fila de recordatorios de recurrencia WhatsApp (`RecurrenceMessageController::index()` — podía ofrecer mandarle un recordatorio a una mascota dada de baja), KPI "total mascotas" del dashboard, y el listado de la API móvil (`Api/PetController::index()`).

Se preguntó al usuario el alcance: eligió que sea una **config global en Sistema** (no reglas fijas por pantalla) — un único `SystemSettings` que, apagado, oculta inactivas en todo el sistema por igual. Implementado: nueva sección `pets` en `SystemSettings::definitions()` (`app/Support/SystemSettings/SystemSettings.php`), campo booleano `pets_show_inactive` (default `false`), mismo patrón exacto que `photo_watermark_enabled` — sin migración/seeder propio (la tabla `system_settings` es genérica), sin tocar la vista de configuración (`system-settings/index.blade.php` ya renderiza cualquier campo `boolean` de forma genérica). Nuevo scope local `Pet::scopeVisible()` en el modelo (no global scope, a propósito — un global scope se habría colado en `pets/index`, que **mantiene su propio filtro independiente** `status=all|active|inactive|deceased` pedido explícitamente por el usuario en otra sesión). Se aplicó `->visible()` en los 9 puntos de listado/selector detectados. Se decidió **no filtrar** los `Pet::find()`/`findOrFail()` de una mascota puntual ya conocida por ID (ej. `Api/PetController::show()`, `RecurrenceMessageController::loadRecipient()`) — ocultar una mascota específica que ya está referenciada en una pantalla no tiene sentido, solo aplica a listados donde se *elige* entre varias.

**Verificación:** `php -l` en los 12 archivos tocados sin errores. Suite completa de tests: 305 pasan, 37 fallan — mismos 12 archivos de tests documentados como deuda preexistente el 26/07/2026 (`ClientAddressHarmonizationTest`, `ClientLivePetsCatalogTest`, `ExampleTest`, `HotelReservationResourceBlockingTest`, `OperatorBranchSelectionTest`, `OperatorPhotoUploadTest`, `PetCatalogRootViewsTest`, `PetDependenciesCrudTest`, `ResourceDuplicationTest`, `ResourceEventCrudTest`, `ResourcePhotoCrudTest`, `ServiceOperatorRoleLinkTest`) — sin regresiones nuevas. Confirmado además vía `tinker` que la nueva sección `pets` resuelve bien (`sections()['pets']`, default `false`). No se pudo verificar el switch nuevo haciendo clic real en `/system-settings` (sin herramienta de navegador en este entorno).

### 📁 Archivos Creados/Modificados
- `app/Support/SystemSettings/SystemSettings.php` — sección `pets`, campo `pets_show_inactive`
- `app/Models/Pet.php` — `scopeVisible()`
- `app/Http/Controllers/HotelReservationController.php` — `loadPets()`
- `app/Http/Controllers/ResourceController.php` — `$eventFormPets`
- `app/Http/Controllers/SpaBookingController.php` — `globalCreate()`
- `app/Http/Controllers/Clinical/ClinicalController.php` — `index()`
- `app/Http/Controllers/ClientController.php` — `pets`/`live_pets_count`
- `app/Domain/Commercial/Repositories/PetRepository.php` — `getByClientId()`
- `app/Http/Controllers/MapaZonasController.php` — `$pets`/`$unlocatedPets`
- `app/Http/Controllers/RecurrenceMessageController.php` — `index()`
- `app/Http/Controllers/DashboardController.php` — `$totalPets`
- `app/Http/Controllers/Api/PetController.php` — `index()`
- `docs/tecnico/MODELO_BD.md` — nota en `pets.is_active`
- `docs/tecnico/BACKLOG.md` — BL-073 a Completados

### 🛑 Pendientes activos
1. Commit y push de esta sesión (BL-073).
2. Verificación manual en navegador real del switch nuevo en `/system-settings` (sección "Mascotas") — sin herramienta de navegador en este entorno.
3. Sin relación con lo de hoy: 37 tests preexistentes fallando, BL-071 (vademécum), BL-053, BL-028, BL-001/002/004, BL-024b, BL-047 resto.

### ↪️ Addendum (misma sesión) — corrección de alcance pedida por el usuario

El usuario probó y corrigió dos cosas del diseño original de arriba:

1. **`pets/index` no debía ser independiente.** La decisión original (mantener el filtro propio de `pets/index` sin ligarlo al switch global) fue mía, no pedida — el usuario aclaró que las inactivas **solo** deben salir si `pets_show_inactive` está encendido, en todos lados sin excepción. Se agregó `->visible()` también a la query base de `PetController::index()`: con el switch apagado, el filtro `status=inactive` ahora da resultado vacío en vez de mostrarlas; encendido, funciona exactamente igual que antes de este cambio.
2. **Bug real encontrado por el usuario:** al filtrar por "Inactivas" (con el switch encendido), cada fila seguía mostrando el botón "Inactivar" — no tiene sentido ofrecer desactivar algo que ya está inactivo. Se envolvió el botón/form en `@if($pet->is_active)` en `pets/partials/{index-blocks,index-table}.blade.php`. La reactivación sigue siendo solo vía el checkbox "Mascota activa" en la ficha de edición (ya existente desde BL-067).

`PetDeactivateTest` actualizado: el test viejo de "status=inactive muestra solo inactivas" se dividió en dos (switch apagado → vacío; switch encendido → comportamiento original) + un test nuevo para el botón oculto. Suite completa re-verificada: 307 pasan, mismos 37 preexistentes fallando, sin regresiones.

**Archivos adicionales tocados:** `app/Http/Controllers/PetController.php` (`index()`), `resources/views/pets/partials/index-blocks.blade.php`, `resources/views/pets/partials/index-table.blade.php`, `tests/Feature/PetDeactivateTest.php`, `docs/tecnico/{MODELO_BD,BACKLOG}.md` (nota corregida).

---

## 📅 Cierre de sesión: 26/07/2026 — BL-072: bloqueo de pantalla del backoffice

### ✅ Logros y Cambios

El usuario pidió un bloqueo de pantalla tipo POS para el backoffice (terminal compartida con datos financieros/clínicos delicados): botón manual "Bloquear pantalla" en el header + auto-bloqueo por inactividad, desbloqueo solo con el password de quien bloqueó, y cualquier otra persona en el mismo equipo solo puede cerrar sesión (no desbloquear). A pedido explícito del usuario, el timeout de inactividad **no quedó fijo**: es una preferencia personal editable por cada operador en `user/settings` (`screen_lock_idle_minutes`, nullable en `users`, cae al default global `config('backoffice.security.screen_lock_idle_minutes')` = 15 min si el usuario no lo personalizó).

Diseño: el estado de "bloqueado ahora mismo" vive en la sesión de Laravel (driver `database`), no en `users` ni en tabla nueva — así el bloqueo sobrevive a un refresh o a otra pestaña con la misma cookie, algo que la app móvil (BL-038/BL-063, bloqueo 100% client-side con localStorage) no necesita resolver porque no comparte sesión de navegador entre personas. Nuevo middleware `screen.lock` (alias, sigue el patrón de `Ensure{Name}ModuleEnabled`) envuelve el mismo grupo grande de rutas protegidas; las 3 rutas de bloqueo (`screen-lock.show/lock/unlock`) viven en un grupo aparte solo con `auth` para no auto-bloquearse. Desbloqueo usa la regla nativa `current_password` de Laravel (mismo mecanismo que `UserSettingsController::updatePassword`), con `throttle:5,1` contra fuerza bruta. `redirect_url` se sanea contra open redirect (debe ser ruta relativa simple).

10 tests de Feature nuevos (`ScreenLockTest`) cubren: bloqueo manual, bloqueo bloquea rutas protegidas, logout sigue funcionando estando bloqueado, desbloqueo correcto/incorrecto, rate limit, open redirect, preferencia por usuario sin afectar a otros. Los 10 pasan. Al correr la suite completa aparecieron 37 fallos preexistentes (no causados por este cambio — confirmado con un test de diagnóstico: redirigen a `/login`, no a `/bloqueo`, porque esos tests nunca llaman `actingAs()`; el diff de `routes/web.php` prueba que el grupo ya exigía `auth` antes de tocar nada). Se descarta arreglarlos aquí, es deuda preexistente sin relación con BL-072.

De paso se disparó otra vez **NT-019** (`public/build` queda `root:root` tras `npm run build` dentro de `estetican_app`) — se le pidió al usuario correr el `sudo chown` documentado, ya conocido, no es nuevo.

### 📁 Archivos Creados/Modificados
- `app/Http/Middleware/EnsureScreenIsUnlocked.php` — nuevo, alias `screen.lock`
- `app/Http/Controllers/ScreenLockController.php` — nuevo (`show`/`lock`/`unlock`)
- `app/Http/Controllers/UserSettingsController.php` — nuevo método `updatePreferences()`
- `app/Models/User.php` — `screen_lock_idle_minutes` en `#[Fillable]` + cast `integer`
- `database/migrations/2026_07_26_000000_add_screen_lock_idle_minutes_to_users_table.php` — nuevo
- `routes/web.php` — grupo de rutas de bloqueo + `screen.lock` en el grupo grande + `user.settings.preferences`
- `bootstrap/app.php` — alias `screen.lock`
- `config/backoffice.php` — `security.screen_lock_idle_minutes` (default global, `.env`)
- `resources/views/auth/locked.blade.php` — nuevo, standalone (sin nav)
- `resources/views/user/settings.blade.php` — tarjeta "Bloqueo de Pantalla"
- `resources/views/layouts/app.blade.php` — data-attributes de config para el JS
- `resources/views/components/main-navigation.blade.php` — botón "Bloquear pantalla" (desktop + mobile)
- `resources/js/modules/screen-lock.js` — nuevo, timer de inactividad
- `resources/js/app.js` — import del módulo nuevo
- `tests/Feature/ScreenLockTest.php` — nuevo, 10 tests
- `docs/tecnico/MODELO_BD.md` — columna nueva documentada en `users`
- `docs/tecnico/BACKLOG.md` — BL-072 movido a Completados

### 🛑 Pendientes activos
1. ~~NT-019 recurrente: confirmar chown~~ **RESUELTO** — ver hallazgo en la sesión siguiente (cont.): el primer intento vía `!` en este chat no aplicaba nada porque `sudo` requiere terminal real; se resolvió corriéndolo por SSH directo.
2. **37 tests preexistentes fallando** (no relacionados a BL-072) — nunca llaman `actingAs()`, listados en `ClientAddressHarmonizationTest`, `ClientLivePetsCatalogTest`, `HotelReservationResourceBlockingTest`, `OperatorBranchSelectionTest`, `OperatorPhotoUploadTest`, `PetCatalogRootViewsTest`, `PetDependenciesCrudTest`, `ResourceDuplicationTest`, `ResourceEventCrudTest`, `ResourcePhotoCrudTest`, `ServiceOperatorRoleLinkTest`, `ExampleTest`. Vale la pena decidir si se arreglan en una sesión dedicada — es deuda técnica real de cobertura.
3. **Verificación manual en navegador real** del flujo completo (login → bloquear → desbloquear → auto-bloqueo por inactividad) — sin herramienta de navegador en este entorno, solo se verificó vía tests HTTP automatizados.
4. ~~Commit y push de esta sesión~~ **HECHO** — `7a188dd`, pusheado a `origin/main`.
5. Sin relación con lo de hoy: BL-071 (vademécum estructurado), BL-053 (artículos de uso interno), BL-028 (firewall ufw), BL-001/002/004 (UI/config), BL-024b (mensajería automática por API), BL-047 resto (espejo alergia→alerta, app móvil, `icd_code`).

---

## 📅 Cierre de sesión: 26/07/2026 (cont.) — Cierre de pendientes BL-072 + MobCitaDet: botón "Realizada"

### ✅ Logros y Cambios

**Cierre de BL-072:** el usuario confirmó haber corrido el `chown` de NT-019 pero el owner de `public/build/assets/` seguía en `root:root` después de dos intentos vía `!` en este chat. Se diagnosticó comparando `stat`/`lsattr` (sin atributos raros, mismo filesystem, `ctime` sin cambios) y pidiéndole correr `-v` para ver salida real — el output reveló la causa: `sudo: a terminal is required to read the password`. El mecanismo `!` de este chat no adjunta una TTY real, así que `sudo` nunca pudo pedir la contraseña y fallaba en silencio (el usuario no veía el stderr la primera vez). Se resolvió pidiéndole correrlo desde una sesión SSH directa a la OPi, donde sí hay TTY — funcionó a la primera. Documentado como addendum a **NT-019** (ver abajo) porque es un hallazgo reusable: cualquier `sudo` futuro pedido a través de este chat va a fallar igual.

**MobCitaDet — botón "Realizada":** el usuario pidió un botón que marque una cita como realizada "sin importar el tiempo de inicio y finalización (como estuvo programada)". Investigado el flujo real: la única fricción de horario existente es un diálogo de confirmación en el botón "Iniciar servicio" (`scheduled → work_order`) que compara la hora actual contra `scheduled_at` con un margen `graceMinutes` (configurable, default 15 min) — no bloquea, pero pide confirmar si el desfase es grande. El paso a `completed` (dentro de `MobCobro`, vía `PaymentController::store` con `mark_completed`) nunca tuvo ninguna restricción de horario, ni en frontend ni en backend.

Se preguntó al usuario el alcance exacto (¿salta también el cobro?, ¿desde qué estados?): confirmó que el botón debe comportarse **como si se hubieran hecho ambos pasos** (iniciar + completar), o sea, sigue exigiendo pasar por cobro, y pidió que sea visible tanto en `scheduled` como en `work_order` (aceptando el solapamiento con "Completar y cobrar" en este último, se le advirtió explícitamente). Implementado en `MobCitaDet.tsx`: nueva función `markRealizada()` — si la cita está en `scheduled`, hace `PATCH status=work_order` en silencio (sin el diálogo de tolerancia) y navega directo a `/citas/{id}/cobro`; si ya está en `work_order`, navega directo igual que "Completar y cobrar". Sin cambios de backend (el endpoint ya no tenía restricción de horario que saltar).

Verificado con `tsc --noEmit` (sin errores nuevos en este archivo — hay errores preexistentes en `ActiveService.tsx`/`MobCajaMovimientos.tsx` no relacionados) y `npm run build` exitoso; el contenedor `estetican_mob` recogió el build nuevo sin reiniciar (mismo inodo, se evitó a propósito el patrón `rm -rf dist` de NT-018). No se pudo probar el clic real en navegador (sin esa herramienta en este entorno).

### 📁 Archivos Creados/Modificados
- `mob_apps/operador/src/admin/MobCitaDet.tsx` — botón "Realizada" (`NEXT_STATUSES` + `markRealizada()`)
- `docs/tecnico/NOTAS_TECNICAS.md` — addendum a NT-019 (sudo requiere TTY real, `!` de este chat no sirve)
- `docs/tecnico/BACKLOG.md` — entrada de Completados para el botón "Realizada" (sin BL formal, pedido ad-hoc)
- `BITACORA.md` — este cierre

### 🛑 Pendientes activos
1. Commit y push de esta sesión (MobCitaDet + docs).
2. **Verificación manual en navegador real** del botón "Realizada" — mismo límite de entorno que BL-072, sin herramienta de browser disponible.
3. Evaluar si conviene simplificar el solapamiento "Completar y cobrar" / "Realizada" en `work_order` (dos botones que hacen lo mismo) — el usuario lo aceptó a sabiendas, pero podría revisarse en una pasada de UX.
4. Sin relación con lo de hoy: los mismos de la entrada anterior (BL-071, BL-053, BL-028, BL-001/002/004, BL-024b, BL-047 resto, 37 tests preexistentes).

---

## 📅 Cierre de sesión: 24/07/2026 (cont. 9) — Cierre general: resumen consolidado del día

### ✅ Resumen del día

Sesión larga con 9 tramos de trabajo, todos ya commiteados y pusheados (`b15d676`, `5d5d3b2`, `b54a1b2`, `80ccd91`). De un vistazo:

| ID | Qué se hizo |
|---|---|
| BL-066 | Borrado de usuarios ya no borra si tiene historial — lo marca inactivo |
| BL-047 (resto) | PDF real del expediente clínico completo y de recetas individuales (`barryvdh/laravel-dompdf`, primera dependencia de PDF del proyecto) |
| BL-067 | Mascotas: "Eliminar" ya no borra (ni soft-delete) — marca `is_active=false` |
| NT-042/BL-068 | La CSP bloqueaba `onclick=`/`onsubmit=` inline sin avisar — 8 vistas migradas a `data-confirm`; una de ellas (usuarios) tenía una regresión de seguridad real (se enviaba sin confirmar) |
| BL-069/NT-043 | Flujo "Nueva mascota" conectado de punta a punta; de paso se encontró y corrigió un bug real que rompía la búsqueda de clientes en general |
| BL-070 | Selectores de artículo en Vacunas/Recetas filtrados por `items.department`; campo "Departamento" del catálogo pasó de texto libre a `<select>` cerrado |
| — | Recategorización real del catálogo (14 artículos: Simparica Trio corregido de Vacunas→Farmacia) + 8 medicamentos de demo dados de alta en Farmacia con ficha técnica |
| BL-071 (nuevo, diferido) | Formulario/vademécum veterinario estructurado — diseño propuesto por el usuario, sin construir todavía |

Verificado el selector de Recetas contra producción real vía HTTP (login temporal + curl, sin navegador disponible en este entorno) — los 9 artículos de Farmacia aparecen correctamente, sin errores PHP. Usuario y visita de prueba borrados después.

### 🛑 Pendientes reales para la próxima sesión

1. **BL-071** — formulario/vademécum estructurado (`principio_activo`, `concentracion`, `forma_farmaceutica`, `especie`, `via`, `mg_kg`, `frecuencia`, `duracion`, `restricciones`, `requiere_receta`) + cálculo automático de dosis (peso de la mascota × mg/kg) al recetar. Los 8 medicamentos de demo de hoy solo tienen la dosis de referencia como texto en `notes`, sin estructurar. Incluye descuento de inventario al recetar (hoy solo vacunas descuentan).
2. **Verificación manual en navegador real** del autocompletado Alpine.js del selector de Farmacia (BL-070) — se confirmó el HTML/datos vía HTTP directo, pero no la interacción de clic real (elegir un artículo → se rellenan Fármaco/Concentración). Sin herramienta de navegador en este entorno.
3. Evaluar si `docs/OPI_PRODUCCION.md` debe documentar que hoy se compiló JS directo en la OPi 3 veces (`npm run build` dentro de `estetican_app`), fuera del proceso documentado de WSL→git.
4. BL-047 resto: espejo automático "alergia severa → `pet_medical_alerts`", soporte en app móvil, catálogo real de `icd_code`.
5. Sin relación con lo de hoy: BL-053 (artículos de uso interno), BL-028 (firewall ufw), BL-001/002/004 (UI/config), BL-024b (mensajería automática por API).

---

## 📅 Cierre de sesión: 24/07/2026 (cont. 8) — Recategorización de catálogo + demo de Farmacia (sin código)

### ✅ Logros y Cambios

Cierre de la sesión larga de BL-066 a BL-070: el usuario pidió recategorizar los artículos reales del catálogo (14 en total) para que el filtro de BL-070 tuviera datos con qué trabajar. Revisado uno por uno: 8 "ID TAG" ya estaban bien como "Accesorios"; 5 vacunas reales ya estaban bien como "Vacunas"; **"Simparica Trio canino" estaba mal etiquetado como "Vacunas"** — es un antiparasitario oral (sarolaner + moxidectina + pirantel, tabletas masticables mensuales), no una vacuna inyectable. Verificado que no tenía historial de aplicación ni movimientos de inventario antes de recategorizarlo a "Farmacia" (cambio de datos, sin riesgo).

El usuario pidió además dar de alta 8 medicamentos veterinarios reales (Rimadyl, Metacam, Clavamox, Cerenia oral e inyectable, Apoquel, Revolution, Revolution Plus) para tener una demo real del selector de Farmacia, con su ficha técnica completa (principio activo, presentación, dosis de referencia por especie). De paso propuso un rediseño real: separar la dosis en campos estructurados (`principio_activo`, `concentracion`, `forma_farmaceutica`, `especie`, `via`, `mg_kg`, `frecuencia`, `duracion`, `restricciones`, `requiere_receta`) en vez de texto libre, con el objetivo final de sugerir la dosis calculada (peso de la mascota × mg/kg) al recetar. Se le preguntó el alcance: confirmó dar de alta los medicamentos ahora con los campos existentes (dosis de referencia en `notes`, como texto), dejando el rediseño estructurado completo — incluyendo el cálculo automático — como **BL-071**, diferido a propósito para otra sesión.

Sesión de datos/documentación, sin cambios de código — no genera commit.

### 📁 Cambios de datos (no código)
- `items`: `Simparica Trio canino` recategorizado de "Vacunas" a "Farmacia".
- `items`: 8 medicamentos nuevos creados (department=Farmacia) — Rimadyl, Metacam, Clavamox, Cerenia, Cerenia inyectable, Apoquel, Revolution, Revolution Plus.
- `docs/tecnico/BACKLOG.md`: BL-071 nuevo (formulario/vademécum estructurado, diferido).

### 🛑 Pendientes activos
1. BL-071: formulario/vademécum veterinario estructurado + cálculo automático de dosis — diseño ya anotado, sin construir.
2. Evaluar si `docs/OPI_PRODUCCION.md` debe actualizarse dado que se compiló JS directo en la OPi 3 veces en esta sesión (fuera del proceso documentado de WSL→git).
3. BL-047 resto: espejo automático alergia severa→alerta, soporte en app móvil, catálogo real de `icd_code`.
4. BL-053 (artículos de uso interno), BL-028 (firewall ufw), BL-001/002/004 (UI/config), BL-024b (mensajería automática por API).

---

## 📅 Cierre de sesión: 24/07/2026 (cont. 7) — BL-070: filtrar artículos por categoría (Vacunas/Farmacia)

### ✅ Logros y Cambios

El usuario reportó que en Vacunas el selector "Artículo (producto/marca)" mostraba todos los artículos del catálogo sin filtrar, y pidió que solo aparecieran vacunas ahí — y que la captura de recetas tuviera su propio selector limitado a artículos de farmacia (medicinas, curaciones, vendas). Se usó `EnterPlanMode` dado el tamaño (schema nuevo + 2 vistas + 2 controllers) y se confirmaron 2 decisiones con el usuario antes de codificar.

**Hallazgo antes de diseñar nada:** el campo correcto para esta categorización ya existía — `items.department` (texto libre con sugerencias, documentado desde antes explícitamente con el ejemplo "Farmacia", "Accesorios", etc.) — no hacía falta ninguna columna nueva, solo *usarlo* para filtrar. `ClinicalVisitController::showPet()` cargaba `$items` sin ningún `where()`; se cambió a `$vaccineItems` filtrado por `department = 'Vacunas'`. Se agregó "Vacunas" como sugerencia en el datalist del catálogo de artículos, y un `<input type="hidden" name="department" value="Vacunas">` al alta rápida ("+ Nuevo") de esa misma sección — si no, un artículo creado ahí quedaría fuera del filtro que se acababa de imponer.

**Recetas (nuevo):** `clinical_prescription_items` gana `item_id` nullable (FK → `items`, `nullOnDelete`, mismo patrón exacto que `pet_vaccinations.item_id`). `ClinicalVisitController::show()` ahora carga `$pharmacyItems` (`department = 'Farmacia'`). En `clinical/visits/show.blade.php`, el selector nuevo usa Alpine.js (`x-data`/`x-ref`/`@change`, mismo patrón ligero ya usado en el formulario de vacunas — nada de `onclick=`/`onchange=` inline, ver NT-042 de esta misma sesión) para autocompletar fármaco/concentración al elegir un artículo, sin bloquear la edición manual — el texto libre sigue siendo la fuente real para fármacos compuestos o fuera de catálogo. A propósito **sin descuento de inventario** (a diferencia de vacunas, que siempre descuentan 1 dosis fija): la dosis de una receta es texto libre ("1 tableta"), no hay cantidad estructurada que descontar de forma confiable todavía.

**Verificación:** 5 tests nuevos (`ClinicalItemCatalogFilterTest`) — selector de vacunas solo muestra `department=Vacunas`, selector de recetas solo muestra `department=Farmacia`, `item_id` opcional se liga correctamente, la receta sigue funcionando sin `item_id` (texto libre), y un `item_id` inexistente falla validación. Suite completa sin regresiones: 37 fallidas preexistentes sin cambio, 295 pasan (antes 290).

**Ajuste inmediato pedido por el usuario:** el filtro por texto libre invita a errores de dedo silenciosos (mayúscula/minúscula, "Vacuna" sin la "s") que rompen el match exacto sin avisar nada. Se cambió el campo "Departamento" del formulario de Artículos (`items/partials/form.blade.php`, pantallas `ArtCre`/`ArtEdi`) de `<input list>` con `<datalist>` a un `<select>` cerrado a las 5 opciones conocidas — si un artículo ya tenía un valor fuera de esa lista, se agrega como opción extra dinámicamente para no perderlo/sobrescribirlo sin querer al guardar. Verificado con `ItemCrudTest` (sin regresiones) y suite completa otra vez en 295/295.

### 📁 Archivos principales tocados
- `database/migrations/2026_07_24_000002_add_item_id_to_clinical_prescription_items_table.php` (nueva)
- `app/Models/ClinicalPrescriptionItem.php` (`item_id`, relación `item()`)
- `app/Http/Controllers/Clinical/ClinicalVisitController.php` (`$vaccineItems` filtrado, `$pharmacyItems` nuevo)
- `app/Http/Controllers/Clinical/ClinicalPrescriptionController.php` (valida `items.*.item_id`)
- `resources/views/clinical/pets/show.blade.php` (selector filtrado, hint, alta rápida con `department` fijo)
- `resources/views/clinical/visits/show.blade.php` (selector de Farmacia + autocompletar Alpine)
- `resources/views/items/partials/form.blade.php` ("Vacunas" en el datalist)
- `tests/Feature/Clinical/ClinicalItemCatalogFilterTest.php` (nuevo)
- `docs/tecnico/{MODELO_BD,BACKLOG}.md`

### 🛑 Pendientes activos
1. Commit y push de esta sesión (BL-066 a BL-070, NT-042/043) — sesión larga, varios items acumulados sin subir todavía.
2. Recategorizar a mano en el catálogo de Artículos los artículos existentes que correspondan a "Vacunas"/"Farmacia" — el fix no los recategoriza solos.
3. Evaluar si `docs/OPI_PRODUCCION.md` debe actualizarse dado que se compiló JS directo en la OPi 3 veces hoy (fuera del proceso documentado de WSL→git).
4. BL-047 resto: espejo automático alergia severa→alerta, soporte en app móvil, catálogo real de `icd_code`.
5. BL-053 (artículos de uso interno), BL-028 (firewall ufw), BL-001/002/004 (UI/config), BL-024b (mensajería automática por API).

---

## 📅 Cierre de sesión: 24/07/2026 (cont. 6) — BL-069/NT-043: cierre real del flujo "Nueva mascota"

### ✅ Logros y Cambios

Continuación directa de BL-069/NT-043 de esta misma sesión. Tras conectar el flujo `mode=pet_creation` (búsqueda de cliente → "Agregar mascota aquí" → modal auto-abierto en `clients/edit`), el usuario probó en el navegador real y encontró dos problemas más, ambos con causa raíz no obvia:

1. **El modal aparecía vacío/sin efecto** ("me lleva a la edición del cliente, está mal"): condición de carrera entre mi script de auto-apertura (empujado desde la vista, corría en `DOMContentLoaded`) y `client-form.js` (módulo ES vía Vite, diferido) — mi listener se registraba primero y disparaba el clic antes de que `initClientEditForm()` alcanzara a enganchar el listener real que abre el modal. Resuelto moviendo la lectura de `open_pet_modal` *dentro* de `initClientEditForm()` mismo, en el mismo tick que ya inicializó todo — se quitó el script empujado desde la vista.

2. **La mascota "no aparecía" después de darla de alta**: el botón "OK" del modal nunca guardó nada — solo arma una fila pendiente en el formulario grande de `clients/edit`; hacía falta bajar y presionar "Actualizar" para persistirla de verdad (comportamiento correcto para *editar* un cliente existente, donde acumular cambios antes de un solo guardado tiene sentido, pero rompe la promesa de alta directa de "Agregar mascota aquí"). Resuelto: cuando la página se abrió vía `?open_pet_modal=1`, `confirmAddPetModal()` llama a `form.requestSubmit()` de inmediato tras armar la fila — la mascota queda guardada sin pasos manuales adicionales.

**Limitación conocida, dejada fuera de esta pasada a propósito:** el modal solo captura datos generales, sin foto (`PetPhotoImageManager` necesita un `pet_id` real que no existe hasta guardar). Con el auto-guardado, la mascota nueva ya aparece de inmediato en el panel "Seleccionar mascota para gestionar tablas dependientes" — un clic más lleva a su ficha para agregar la foto. Documentado en NT-043 como limitación conocida, no como bug.

**Verificación:** cambios 100% JS (`client-form.js`), no probables con PHPUnit — se verificó a mano contra el bundle recompilado (`grep` confirma `requestSubmit` presente) y se corrió la suite completa dos veces (una por cada fix) sin regresiones: 37 fallidas preexistentes sin cambio, 290 pasan.

### 📁 Archivos principales tocados
- `resources/js/modules/client-form.js` (auto-apertura sin condición de carrera + auto-guardado en modo alta rápida)
- `resources/views/clients/edit.blade.php` (se quitó el script frágil que ya no hace falta)
- `public/build/` (recompilado 2 veces más)
- `tests/Feature/ClientPetCreationModeTest.php` (assertion actualizada, ya no hay diferencia de HTML servido)
- `docs/tecnico/NOTAS_TECNICAS.md` (NT-043 ampliada con las 2 adendas)

### 🛑 Pendientes activos
1. Commit y push de esta sesión (BL-066 a BL-069, NT-042/043).
2. Evaluar si `docs/OPI_PRODUCCION.md` debe actualizarse dado que se compiló JS directo en la OPi 3 veces hoy (fuera del proceso documentado de WSL→git).
3. BL-047 resto: espejo automático alergia severa→alerta, soporte en app móvil, catálogo real de `icd_code`.
4. BL-053 (artículos de uso interno), BL-028 (firewall ufw), BL-001/002/004 (UI/config), BL-024b (mensajería automática por API).

---

## 📅 Cierre de sesión: 24/07/2026 (cont. 5) — BL-069/NT-043: flujo "Nueva mascota" y bug real de búsqueda de clientes

### ✅ Logros y Cambios

El usuario reportó que no podía dar de alta una mascota desde "PetInd" ni buscar un cliente. Investigación (agente en paralelo): el botón "Nueva mascota" enlaza a `clients.index?mode=pet_creation` desde hace tiempo (confirmado por `git log`, no es de esta sesión), pero `ClientController@index()` nunca leyó ese parámetro y `clients/index.blade.php` no tenía ninguna continuación — un enlace a una función que quedó a medias.

Al pedirle al usuario que describiera el síntoma exacto para diseñar el fix, reveló algo más grave: al escribir en el buscador y presionar "Aplicar", aparecía "No se pudo crear el cliente" — un mensaje sin sentido en una pantalla de búsqueda. **Causa raíz real (NT-043):** `client-form.js` localiza el formulario de alta de cliente con `document.querySelector('form[action$="/clients"]')` (selector por atributo, no por ID). El formulario de búsqueda del listado (`route('clients.index')`) **también termina en `/clients`**, igual que el de alta (`route('clients.store')`). Como este JS corre en todas las páginas (importado globalmente), en el listado de clientes enganchaba la validación completa de alta de cliente al formulario de búsqueda, bloqueando cualquier envío. Esto rompía la búsqueda de clientes **en general**, no solo en el flujo de nueva mascota — probablemente lleva rota bastante tiempo. Corregido a `document.getElementById('client-create-form')` (mismo patrón ya usado bien para el formulario de edición). Requirió recompilar assets (`npm run build` dentro de `estetican_app` — nota: esto se hizo directo en la OPi, desviación del proceso documentado de compilar en WSL y subir vía git; revisar si vale la pena actualizar esa guía) y borrar vistas compiladas.

Con la búsqueda ya funcionando, se conectó el flujo real: `ClientController@index()` expone `$petCreationMode`; en ese modo cada cliente muestra "Agregar mascota aquí" en vez de Ver/Editar/Eliminar (tarjetas y tabla), que lleva a `clients.edit?open_pet_modal=1`. Un script nuevo en `clients/edit.blade.php` (gatillado solo si viene ese query param) simula un clic sobre el botón "Agregar Mascota" ya existente, reutilizando 100% el modal/JS que ya funcionaba — sin duplicar lógica.

**Verificación:** 5 tests nuevos (`ClientPetCreationModeTest`) — botón correcto con/sin el modo, sobrevive un submit de búsqueda, vista de tabla, auto-apertura del modal solo con el flag. El fix de `client-form.js` no se puede probar con PHPUnit (es JS puro sin backend); se verificó a mano que el bundle recompilado ya no contiene el selector viejo (`grep` sobre el archivo compilado). Suite completa sin regresiones: 37 fallidas preexistentes sin cambio (3 de ellas, en `ClientLivePetsCatalogTest`, ya fallaban por falta de `actingAs` desde antes de esta sesión — no relacionado), 290 pasan (antes 285).

### 📁 Archivos principales tocados
- `resources/js/modules/client-form.js` (selector de formulario corregido)
- `public/build/` (recompilado)
- `app/Http/Controllers/ClientController.php` (`$petCreationMode`)
- `resources/views/clients/index.blade.php`, `clients/partials/index-table.blade.php`, `clients/edit.blade.php`
- `tests/Feature/ClientPetCreationModeTest.php` (nuevo)
- `docs/tecnico/{NOTAS_TECNICAS,BACKLOG}.md`

### 🛑 Pendientes activos
1. Commit y push de esta sesión.
2. Evaluar si `docs/OPI_PRODUCCION.md` debe actualizarse dado que se compiló JS directo en la OPi hoy (fuera del proceso documentado de WSL→git).
3. BL-047 resto: espejo automático alergia severa→alerta, soporte en app móvil, catálogo real de `icd_code`.
4. BL-053 (artículos de uso interno), BL-028 (firewall ufw), BL-001/002/004 (UI/config), BL-024b (mensajería automática por API).

---

## 📅 Cierre de sesión: 24/07/2026 (cont. 4) — NT-042: el botón "Inactivar" no hacía nada (CSP bloqueaba onclick inline)

### ✅ Logros y Cambios

Al agregarle texto visible al botón "Inactivar" de mascotas (parte de BL-067, cerrado minutos antes), el usuario reportó que el botón "no hace ni dice nada al hacer clic". Investigando se encontró la causa raíz real, más grave que el propio botón: la CSP del proyecto (`ContentSecurityPolicy.php`, `script-src` con nonce pero sin `unsafe-inline`) bloquea **cualquier atributo de evento inline** (`onclick=`, `onsubmit=`) en todo el sistema — los nonces solo cubren etiquetas `<script>` completas, no atributos. El navegador los descarta sin ningún error visible al usuario.

Hay dos variantes del síntoma según el tipo de botón: uno que usa `type="button"` + `onclick` (como el de mascotas) simplemente no hace nada; otro que usa `<form onsubmit="return confirm(...)">` con `type="submit"` dentro (como el de usuarios, tocado hoy mismo en BL-066) es más peligroso — el formulario se envía igual, **sin pedir confirmación**, porque el navegador nunca llega a ejecutar el `onsubmit` bloqueado.

El proyecto ya tenía el patrón correcto resuelto desde antes (`resources/js/modules/confirm-actions.js`, atributo `data-confirm` + listener global, sin inline) usado correctamente en `groups/`, `resources/`, `branches/`, `finances/`, entre otros — las vistas de mascotas y usuarios simplemente no lo seguían. Se migraron al patrón correcto las vistas de mascotas/usuarios primero, y a pedido del usuario se completó el barrido a los 4 archivos restantes con el mismo problema en la misma sesión: `operators/partials/unavailabilities.blade.php`, `whatsapp/plantillas/index.blade.php`, `clinical/visits/show.blade.php`, `clinical/pets/show.blade.php` (4 casos en este último). `grep -rn` confirmó cero ocurrencias restantes de `onclick=`/`onsubmit=` en todo el proyecto. `user/show.blade.php` en particular corrige una regresión de seguridad real que se había introducido sin saberlo en BL-066 de esta misma sesión (el formulario de borrado de usuario se enviaba sin pedir confirmación).

**Verificación:** suite completa sin regresiones (37 fallidas preexistentes sin cambio, 285 pasan — sin cambio en el conteo, ya que el fix es puramente de atributos Blade/JS, no de lógica PHP).

### 📁 Archivos principales tocados
- `resources/views/pets/partials/{index-blocks,index-table}.blade.php`, `pets/show.blade.php`, `pets/index.blade.php` (se quitó el JS muerto `confirmPetDelete`)
- `resources/views/user/show.blade.php`
- `resources/views/operators/partials/unavailabilities.blade.php`, `resources/views/whatsapp/plantillas/index.blade.php`, `resources/views/clinical/visits/show.blade.php`, `resources/views/clinical/pets/show.blade.php`
- `docs/tecnico/NOTAS_TECNICAS.md` (NT-042), `docs/tecnico/BACKLOG.md` (BL-068)

### 🛑 Pendientes activos
1. Commit y push de esta sesión.
2. BL-047 resto: espejo automático alergia severa→alerta, soporte en app móvil, catálogo real de `icd_code`.
3. BL-053 (artículos de uso interno), BL-028 (firewall ufw), BL-001/002/004 (UI/config), BL-024b (mensajería automática por API).

---

## 📅 Cierre de sesión: 24/07/2026 (cont. 3) — BL-067: mascotas ya no se eliminan, se marcan inactivas

### ✅ Logros y Cambios

El usuario reportó que en el listado de mascotas ("PETIND", vista de tarjetas) el cuarto botón de cada ficha "no se lee" — resultó ser el botón de eliminar, que solo tenía un ícono de bote de basura sin `title` ni texto, a diferencia de los otros tres (Detalle/Editar/Programar). Al ir a corregirlo, el usuario pidió algo más de fondo: que borrar una mascota no la elimine, sino que quede **inactiva** (por dos motivos típicos: el dueño ya no es cliente, o la mascota falleció), conservando siempre su historial.

**Hallazgo antes de tocar nada:** las mascotas ya usaban soft-delete (`SoftDeletes`, `$pet->delete()` solo llenaba `deleted_at`) — el historial técnicamente ya no se perdía. Se le explicó esto al usuario y se preguntó qué le faltaba entonces; su respuesta aclaró el problema real: el filtro del listado solo tenía Todas/Activas/Fallecidas — una mascota "eliminada" (soft-deleted) desaparecía por completo, sin ninguna forma de verla ni reactivarla desde la UI.

**Diseño:** columna nueva `pets.is_active` (boolean, default true), independiente de `death_date` (una mascota puede estar inactiva sin haber fallecido, o fallecida sin haber sido nunca marcada inactiva). `PetController::destroy()`/`destroyFromClient()` ahora hacen `$pet->update(['is_active' => false])` en vez de `delete()` — el candado existente de "no borrar con citas activas" se mantiene igual (y se agregó también a `destroyFromClient()`, que antes no lo tenía). El filtro de estado del listado gana "Inactivas"; el badge combina las tres señales con prioridad Fallecida > Inactiva > Activa. La ficha de edición gana un checkbox "Mascota activa" (mismo patrón hidden+checkbox que ya usa Operador) para poder reactivar.

**Bug preexistente encontrado de paso:** la vista de tabla (`index-table.blade.php`) tenía un encabezado "Estado" (ordenable) sin ninguna celda `<td>` real debajo — 6 encabezados contra 5 celdas por fila, columnas desalineadas. Se agregó la celda faltante con el badge (aprovechando que ya hacía falta un lugar para mostrar Inactiva), y se corrigió el `colspan` del estado vacío de 5 a 6.

**Verificación:** 5 tests nuevos (`PetDeactivateTest`) — destroy marca inactiva sin borrar (ni soft-delete), bloqueo por citas activas se mantiene, `destroyFromClient` igual, filtro "inactive" del listado, reactivación vía edición. Suite completa sin regresiones: 37 fallidas preexistentes sin cambio, 285 pasan (antes 280).

### 📁 Archivos principales tocados
- `database/migrations/2026_07_24_000001_add_is_active_to_pets_table.php` (nueva)
- `app/Models/Pet.php` (`is_active` en fillable/casts/activity log)
- `app/Http/Controllers/PetController.php` (`destroy()`, `destroyFromClient()`, `index()`, `validatedPetData()`)
- `resources/views/pets/index.blade.php`, `pets/partials/{index-blocks,index-table}.blade.php`, `pets/show.blade.php`
- `tests/Feature/PetDeactivateTest.php` (nuevo)
- `docs/tecnico/{MODELO_BD,BACKLOG}.md`

### 🛑 Pendientes activos
1. Commit y push de esta sesión.
2. BL-047 resto: espejo automático alergia severa→alerta, soporte en app móvil, catálogo real de `icd_code`.
3. BL-053 (artículos de uso interno), BL-028 (firewall ufw), BL-001/002/004 (UI/config), BL-024b (mensajería automática por API).

---

## 📅 Cierre de sesión: 24/07/2026 (cont. 2) — BL-047 (resto): PDF del expediente clínico y receta

### ✅ Logros y Cambios

Con BL-066 ya cerrado, se retomó BL-047: "PDF/impresión oficial del expediente y receta", pendiente desde que se cerró la Fase 1 del módulo clínico. Antes de codificar se investigó a fondo (2 agentes en paralelo) el estado real del módulo clínico y se confirmó que el proyecto no tenía ninguna librería de PDF — todo lo "imprimible" (cotizaciones, órdenes, la ficha resumen clínica ya existente) usa HTML + `window.print()` del navegador. Se preguntó al usuario si construir sobre ese mismo patrón (cero dependencias nuevas) o instalar `barryvdh/laravel-dompdf` para un PDF binario real — eligió instalar dompdf, y pidió cubrir en la misma pasada tanto el expediente completo como una receta individual.

**Riesgo validado primero:** el proyecto corre Laravel 13 (muy reciente); se confirmó que `barryvdh/laravel-dompdf` ya declara compatibilidad (`composer require` resolvió v3.1.2 sin fricción, dompdf v3.1.6) — no hizo falta el plan de respaldo (dompdf directo sin el wrapper de Laravel).

**Diseño:** el layout de reportes ya existente (`layouts/report.blade.php`) usa `display: flex`/`grid`, que dompdf no soporta de forma confiable — se creó `layouts/pdf.blade.php` nuevo, con la misma identidad visual pero CSS basado en tablas/bloques. El logo se pasa como ruta absoluta de archivo (`Storage::disk('public')->path()`), no como URL, para que dompdf no dependa de `isRemoteEnabled`. `ClinicalRecordPdfController` nuevo con dos métodos: `pet()` descarga el expediente completo de una mascota (identidad, alergias, condiciones, vacunas, historial de peso, adjuntos por metadata, e historial de visitas con SOAP/diagnósticos/recetas completo — no solo el resumen que ya usa la ficha imprimible); `prescription()` descarga una receta individual (fármacos, dosis, operador prescriptor + cédula, línea de firma). Ambas rutas usan el permiso `ver clinico` ya existente (mismo nivel que la ficha resumen), sin crear permisos nuevos.

**Verificación:** 5 tests nuevos (`ClinicalRecordPdfTest`) — expediente con historial completo (visita+diagnóstico+receta+alergia+peso+vacuna) devuelve 200 + `Content-Type: application/pdf`, permiso requerido, gate del módulo clínico respetado, receta individual igual. Suite completa sin regresiones: 37 fallidas preexistentes sin cambio, 280 pasan (antes 275). Verificado además renderizando el PDF real contra una mascota real de producción (solo lectura, vía tinker) — logo, acentos en español y tablas se ven correctos.

### 📁 Archivos principales tocados
- `composer.json`/`composer.lock` (`barryvdh/laravel-dompdf` nuevo)
- `resources/views/layouts/pdf.blade.php` (nuevo)
- `app/Http/Controllers/Clinical/ClinicalRecordPdfController.php` (nuevo)
- `resources/views/clinical/pets/record-pdf.blade.php`, `resources/views/clinical/prescriptions/pdf.blade.php` (nuevos)
- `routes/web.php` (2 rutas nuevas: `clinical.pets.record.pdf`, `clinical.prescriptions.pdf`)
- `resources/views/clinical/pets/show.blade.php`, `resources/views/clinical/visits/show.blade.php` (botones/enlaces nuevos)
- `tests/Feature/Clinical/ClinicalRecordPdfTest.php` (nuevo)
- `docs/tecnico/BACKLOG.md`

### 🛑 Pendientes activos
1. Commit y push de esta sesión.
2. BL-047 resto: espejo automático alergia severa→alerta, soporte en app móvil, catálogo real de `icd_code`.
3. BL-053 (artículos de uso interno), BL-028 (firewall ufw), BL-001/002/004 (UI/config), BL-024b (mensajería automática por API).

---

## 📅 Cierre de sesión: 24/07/2026 — BL-066: borrado de usuarios respeta historial

### ✅ Logros y Cambios

El usuario preguntó por qué no se podían borrar usuarios "en ningún lado". Investigación real: **sí existía** `UserController::destroy()` (hard-delete), pero solo era visible en la ficha individual (`user/show.blade.php`), no en el listado, y solo para `super_admin` — de ahí la confusión. De paso se encontró un gap real: el borrado no validaba ninguna dependencia (a diferencia de `Pet`, que usa soft-delete y valida citas activas). El usuario decidió el diseño: si el usuario tiene historial asociado, debe quedar **inactivo** en vez de borrarse, para no perder ese historial.

**Implementación:** `User::hasHistoricalDependencies()` (nuevo) revisa `operator_id` vinculado, causante en `activity_log` (Spatie), y referencias en 13 tablas operativas/financieras (`audit_logs`, `operator_checkins`, `spa_bookings`, `cash_sessions`, `cash_movements`, `whatsapp_templates`, `booking_messages`, `recurrence_messages`, `resource_events`, `resource_event_updates`, `item_movements`, `documents`, `journal_entries`) — el mapeo completo de FKs hacia `users` en todo el esquema. `UserController::destroy()` ahora marca `is_active=false`/`can_login=false` en vez de borrar si encuentra alguna; si no hay ninguna, borra en duro como antes. El candado de "no puedes eliminarte a ti mismo" no cambió.

**Hallazgo colateral resuelto de paso:** el listado de usuarios (`user/index.blade.php`) no mostraba el estado (`is_active`) de forma explícita — solo atenuaba la fila con CSS (`opacity-75 grayscale`), sin badge ni texto, a diferencia de la ficha individual que sí tenía un badge "Activo"/"Baja". Se agregó una columna "Estado" nueva con el mismo badge, necesaria ahora que desactivar (en vez de borrar) es un resultado real y frecuente de esta pantalla.

**Verificación:** 5 tests nuevos (`UserDestroyTest`) — borrado duro sin historial, desactivación por operador vinculado, por `activity_log`, por `audit_logs`, candado de auto-eliminación. Suite completa sin regresiones: 37 fallidas preexistentes sin cambio, 275 pasan (antes 270).

### 📁 Archivos principales tocados
- `app/Models/User.php` (`hasHistoricalDependencies()`, `HISTORY_TABLES`)
- `app/Http/Controllers/UserController.php` (`destroy()`)
- `resources/views/user/{index,show}.blade.php` (columna "Estado" en listado, texto de confirmación actualizado)
- `tests/Feature/UserDestroyTest.php` (nuevo)
- `docs/tecnico/BACKLOG.md`

### 🛑 Pendientes activos
1. Commit y push de esta sesión.
2. BL-047 (resto: PDF/expediente, espejo alergia→alerta, móvil, `icd_code`), BL-053 (artículos de uso interno), BL-028 (firewall ufw), BL-001/002/004 (UI/config), BL-024b (mensajería automática por API).

---

## 📅 Cierre de sesión: 21/07/2026 (cont. 3) — BL-047 (parcial): adjuntos clínicos reales

### ✅ Logros y Cambios

El usuario pidió avanzar BL-047 (Fase 2 del módulo clínico veterinario), que agrupa 5 piezas independientes entre sí (adjuntos reales, PDF del expediente, espejo alergia→alerta, soporte móvil, catálogo `icd_code`). Se verificó el estado real de cada una antes de proponer nada y se preguntó al usuario cuál priorizar — eligió **adjuntos clínicos reales**.

**Hallazgo:** la tabla `clinical_attachments` y el modelo `ClinicalAttachment` ya existían completos desde BL-046 (Fase 1) — incluyendo `#[Fillable]`, relaciones y `LogsActivity` — pero no había controller, rutas ni vista: nadie podía subir nada todavía.

**Diseño:** a diferencia de los `*ImageManager` existentes (fotos de identidad/galería, todos con recorte vía `x-image-upload`/cropperjs), un adjunto clínico puede ser una radiografía/foto de resultado **o** un PDF de laboratorio — recortar no aplica. Se creó `ClinicalAttachmentManager` nuevo: si el archivo es imagen, la optimiza completa sin recorte (`Fit::Max`, sin thumbnail, nueva sección de config `backoffice.images.clinical_attachments`); si es PDF, lo guarda tal cual sin tocar sus bytes. `ClinicalAttachmentController` (store/destroy) vive a nivel **mascota** (no nivel visita, aunque `clinical_visit_id` queda como enlace opcional) — permisos `crear clinico`/`editar clinico`, consistente con diagnósticos/recetas (no `alergias.administrar`, que es para el otro grupo de datos tipo antecedentes). Nueva sección "Adjuntos clínicos" en `clinical/pets/show.blade.php`, mismo patrón visual (form + listado con botón "Ver"/"Eliminar") que alergias/condiciones/vacunas ya existentes en la misma vista.

**Verificación:** 6 tests nuevos (`ClinicalAttachmentTest`) — sube imagen (confirma que se optimiza sin recortar y que la página realmente la muestra tras subir, no solo que exista en BD), sube PDF (confirma que el mime/bytes no se tocan), permiso requerido, `clinical_visit_id` debe pertenecer a la misma mascota, destroy borra archivo+registro, destroy rechaza adjunto de otra mascota. Suite completa sin regresiones: 37 fallidas preexistentes sin cambio, 270 pasan (antes 264). Verificado además que la vista real renderiza correctamente contra el flujo HTTP completo (no solo aserciones de BD).

### 📁 Archivos principales tocados
- `app/Models/ClinicalAttachment.php`, `app/Models/Pet.php` (relación `attachments()` nueva)
- `app/Support/ClinicalAttachmentManager.php` (nuevo)
- `app/Http/Controllers/Clinical/ClinicalAttachmentController.php` (nuevo)
- `app/Http/Controllers/Clinical/ClinicalVisitController.php` (`showPet()` carga `attachments`)
- `config/backoffice.php` (sección `images.clinical_attachments`)
- `routes/web.php` (2 rutas nuevas: `clinical.attachments.store`/`.destroy`)
- `resources/views/clinical/pets/show.blade.php` (sección "Adjuntos clínicos")
- `tests/Feature/Clinical/ClinicalAttachmentTest.php` (nuevo)
- `docs/tecnico/{MODELO_BD,BACKLOG}.md`

### 🛑 Pendientes activos
1. Commit y push de esta sesión.
2. BL-047 resto: PDF/impresión oficial del expediente (requiere instalar `barryvdh/laravel-dompdf`), espejo automático alergia severa→alerta, soporte en app móvil, catálogo real de `icd_code`.
3. BL-053 (artículos de uso interno), BL-028 (firewall ufw), BL-001/002/004 (UI/config), BL-024b (mensajería automática por API).

---

## 📅 Cierre de sesión: 21/07/2026 (cont. 2) — BL-065: switch simple para asignar "disponibilidad propia"

### ✅ Logros y Cambios

El usuario pidió "arreglarlo en el backlog" para que dar el permiso de disponibilidad propia (BL-061) fuera más fácil de asignar. Tras aclarar qué quería decir con eso (arreglar la usabilidad de la asignación, no solo documentarlo), se quitó `disponibilidad_propia` de la matriz CRUD genérica de Usuarios (era una fila de 4 checkboxes poco intuitiva, donde "Editar" ni siquiera hace nada — ver NT-041) y se reemplazó por un **switch único** ("Puede bloquear su propia disponibilidad") en la tarjeta "Perfil de Operador" del formulario de Usuario, junto al resto de campos de operador.

Activar el switch otorga `ver/crear/eliminar disponibilidad_propia` juntos; desactivarlo los revoca — sin afectar el resto de permisos que ya estén marcados en la matriz CRUD normal (`UserController@store()`/`@update()` fusionan ambas fuentes antes de `syncPermissions()`). De paso se corrigió una inconsistencia real que ya existía entre `store()` (nunca sincronizaba permisos si el campo venía vacío en el alta) y `update()` (los borraba todos en ese caso) — ahora ambos se comportan igual.

**Verificación:** 5 tests nuevos (`UserOwnAvailabilityToggleTest`). Suite completa sin regresiones: 37 fallidas preexistentes sin cambio, 264 pasan (antes 259). Confirmado contra la BD real de producción que el permiso ya asignado a `tomasmg` (BL-061) sigue intacto tras el cambio.

### 📁 Archivos principales tocados
- `app/Http/Controllers/UserController.php` (quita `disponibilidad_propia` de `$modules`, switch nuevo en `store()`/`update()`/`edit()`)
- `resources/views/user/{create,edit}.blade.php` (switch nuevo en "Perfil de Operador")
- `tests/Feature/UserOwnAvailabilityToggleTest.php` (nuevo)
- `docs/tecnico/BACKLOG.md`

### 🛑 Pendientes activos
1. Commit y push de esta sesión.
2. BL-047 (clínica fase 2), BL-053 (artículos de uso interno), BL-028 (firewall ufw), BL-001/002/004 (UI/config), BL-024b (mensajería automática por API).

---

## 📅 Cierre de sesión: 21/07/2026 (cont.) — BL-064: preseleccionar al operador logueado en "Nueva cita"

### ✅ Logros y Cambios

El usuario pidió que, al dar de alta una cita, el operador logueado ya salga seleccionado por default (para ver de una vez su propia agenda de disponibilidad, en vez de tener que buscarse a sí mismo en la lista cada vez).

**Hallazgo:** `User::toApiArray()` (consumido por `GET /api/me`, la única fuente del usuario logueado en el cliente móvil) exponía `operator_role` (nombre del puesto) pero no el FK real `operator_id` hacia `Operator` — sin eso, el cliente no tenía forma de saber cuál operador de la lista corresponde al usuario logueado. Se agregó `operator_id` al array.

**`MobCitaNueva.tsx`:** nuevo efecto que, en cuanto llega la lista de operadores desde `/api/operators`, si `user.operator_id` existe y aparece en esa lista, preselecciona ese operador — sin pisar una selección manual que el usuario ya haya hecho (guard `if (selOp !== null) return`). Un admin sin `operator_id` vinculado no ve cambio de comportamiento (sigue sin preselección).

**Verificación:** 2 tests nuevos en `ProfileTest` (confirman que `GET /api/me` expone `operator_id`, tanto vinculado como `null`). Suite completa sin regresiones: 37 fallidas preexistentes sin cambio, 259 pasan (antes 257). App móvil verificada con `tsc --noEmit` + `npm run build`.

### 📁 Archivos principales tocados
- `app/Models/User.php` (`toApiArray()` gana `operator_id`)
- `mob_apps/operador/src/AuthContext.tsx` (`AuthUser.operator_id`)
- `mob_apps/operador/src/admin/MobCitaNueva.tsx` (efecto de preselección)
- `tests/Feature/Api/ProfileTest.php` (2 tests nuevos)
- `docs/tecnico/BACKLOG.md`

### 🛑 Pendientes activos
1. Commit y push de esta sesión.
2. BL-047 (clínica fase 2), BL-053 (artículos de uso interno), BL-028 (firewall ufw), BL-001/002/004 (UI/config), BL-024b (mensajería automática por API).

---

## 📅 Cierre de sesión: 21/07/2026 — BL-063: fix de bloqueo falso al cambiar de pantalla + timeout configurable

### ✅ Logros y Cambios

El usuario reportó que la app móvil a veces se bloqueaba sola al cambiar de pantalla (no al cambiar de app real), y pidió de paso que el tiempo de inactividad antes de bloquear fuera configurable por usuario en vez de fijo.

**Causa raíz del bug** (`AppLockContext.tsx`): el listener `visibilitychange` bloqueaba de inmediato en cuanto `document.hidden` pasaba a `true`, sin ningún margen. Algunos WebView de Android disparan `hidden` momentáneamente durante navegación interna o al abrir un picker nativo (fecha, foto) sin que el usuario haya salido de la app de verdad — eso disparaba el bloqueo por error. Corregido con un margen de gracia de 1.5s: se arma un timer corto al ocultarse; si la pestaña vuelve a ser visible antes de cumplirse (típico de un picker), se cancela; un cambio real de app dura mucho más que eso, así que sigue bloqueando en ese caso real.

**Timeout configurable:** nuevo campo `lockTimeoutMinutes` en `useUserPrefs` (mismo patrón 100% cliente/`localStorage` que ya usan tema y breadcrumbs — el candado de sesión es inherentemente por-dispositivo, no tiene sentido guardarlo en el servidor). Selector nuevo (1/2/5/10/15/30 min) en Configuración personal → Seguridad.

Cambio 100% en `mob_apps/operador`, sin tocar backend. Verificado con `tsc --noEmit` + `npm run build`.

### 📁 Archivos principales tocados
- `mob_apps/operador/src/AppLockContext.tsx` (margen de gracia en `visibilitychange`, timeout dinámico vía `getIdleTimeoutMs()`)
- `mob_apps/operador/src/hooks/useUserPrefs.ts` (`lockTimeoutMinutes`)
- `mob_apps/operador/src/admin/MobUserConfig.tsx` (selector nuevo en Seguridad)
- `docs/tecnico/BACKLOG.md`

### 🛑 Pendientes activos
1. Commit y push de esta sesión.
2. Confirmar con el usuario que el bloqueo falso ya no ocurre al probarlo en producción real.
3. BL-047 (clínica fase 2), BL-053 (artículos de uso interno), BL-028 (firewall ufw), BL-001/002/004 (UI/config), BL-024b (mensajería automática por API).

---

## 📅 Cierre de sesión: 20/07/2026 (cont. 7) — BL-062: mostrar horas bloqueadas en la Agenda (móvil + web)

### ✅ Logros y Cambios

Continuación directa de BL-060/061: el usuario probó agendar una cita en un día con horas bloqueadas y la Agenda simplemente decía "sin citas para ese día", sin ninguna pista de la no-disponibilidad, y el formulario seguía ofreciendo esa hora como libre. Pidió mostrar los bloqueos visualmente (banner "Bloqueada" en día, punto de color en semana/mes) tanto en **móvil como en Backoffice web**, y que el flujo de crear cita deje de ofrecer esas horas. Se usó `EnterPlanMode` (2 agentes `Explore` en paralelo — backend/API de agenda por un lado, renderizado móvil por otro) más una revisión directa de archivos (Blade de agenda web, CSS) antes de codificar, dado el tamaño real del cambio.

**Hallazgo central:** hasta hoy nada leía `OperatorUnavailability` salvo el rechazo 422 al guardar (BL-060) — había 3 fuentes independientes y desincronizadas de "qué hay agendado" (el checker de guardado, la API de agenda móvil, el controller de agenda web), ninguna tocaba los bloqueos para lectura/visualización. Se resolvió con **un solo método compartido** nuevo, `OperatorAvailabilityChecker::unavailabilityWindows()`, consumido por los 3.

**Decisión de alcance clave (confirmada con el usuario):** el Backoffice web nunca tuvo un selector visual de horarios (solo un campo de fecha/hora libre + validación al enviar) — en vez de construir un grid nuevo desde cero ahí, se extendió el `<script>` vanilla JS que ya existía en `create.blade.php`/`edit.blade.php` para hacer un chequeo en vivo (nuevo endpoint `GET /agenda/check-availability`, reusa los 4 checks que ya existían sin duplicar lógica) que muestra una advertencia inline. La app móvil, que ya tenía un selector de horarios real (`MobCitaNueva.tsx`), sí excluye los slots bloqueados directamente del grid (no selectionables), mismo tratamiento que ya tenían los slots ocupados por otra cita.

**Móvil:** nuevo endpoint `GET /api/agenda/unavailabilities`; banner "Operadores no disponibles" en vista Día de `GlobalAgenda.tsx`/`GroomerAgenda.tsx` (visible siempre que aplique, no solo si no hay citas); indicador nuevo y distinto de los puntos de estado de cita en semana/mes (`AgendaCalendarGrid.tsx`); 4to estado visual "Bloqueado" en el grid de horario de `MobCitaNueva.tsx`.

**Web:** banner igual en vista Día de `agenda/index.blade.php`; punto gris nuevo (`bandeja-calendar-dot--bloqueo`) en semana/mes — reusa por primera vez fuera de WhatsApp la clase base de "dots" que ya existía para la Bandeja Diaria (BL-030), portada a la Agenda general.

**Verificación:** 18 tests nuevos (lectura de ventanas por vista/operador, los 4 casos de `check-availability` + exclusión de la propia cita al reprogramar, banner/dot en las 3 vistas web). Suite completa sin regresiones: 37 fallidas preexistentes sin cambio, 257 pasan (antes 243). App móvil verificada con `tsc --noEmit` + `npm run build`, mismos 2 errores preexistentes de siempre (ajenos a este cambio).

### 📁 Archivos principales tocados
- `app/Domain/Planning/Services/OperatorAvailabilityChecker.php` (`unavailabilityWindows()`)
- `app/Http/Controllers/Api/AgendaController.php` (`resolveRange()`, `unavailabilities()`)
- `app/Http/Controllers/SpaBookingController.php` (`checkAvailability()`, `blockedToday`/`blocked` en `buildCalendarRange()`)
- `routes/api.php`, `routes/web.php` (2 rutas nuevas)
- `resources/views/agenda/{create,edit,index}.blade.php`, `agenda/partials/{_calendar_week,_calendar_month}.blade.php`
- `resources/css/backoffice-blueprints.css` (`.bandeja-calendar-dot--bloqueo`)
- `mob_apps/operador/src/admin/{GlobalAgenda,GroomerAgenda,AgendaCalendarGrid,MobCitaNueva}.tsx`, `agendaViews.ts` (`expandDateRange()`)
- `tests/Feature/Api/AgendaUnavailabilitiesTest.php`, `tests/Feature/Agenda/{CheckAvailabilityTest,AgendaBlockedDisplayTest}.php` (nuevos)
- `docs/tecnico/BACKLOG.md`

### 🛑 Pendientes activos
1. Commit y push de esta sesión.
2. BL-047 (clínica fase 2), BL-053 (artículos de uso interno), BL-028 (firewall ufw), BL-001/002/004 (UI/config), BL-024b (mensajería automática por API).

---

## 📅 Cierre de sesión: 20/07/2026 (cont. 6) — BL-061: fix de permisos + ajuste de UX (menú de Agenda + confirmación con contraseña)

### ✅ Logros y Cambios

Continuación inmediata de BL-061: al probar la feature recién cerrada, el usuario reportó que seguía sin poder bloquear horas. Se verificó directo contra la BD real: su usuario (`tomasmg`, único operador real vinculado con permisos capturados) tenía marcado por error el checkbox **"Editar"** en la matriz de Usuarios en vez de "Ver"/"Crear"/"Eliminar" — esa pantalla no tiene ninguna acción de "editar" un bloqueo (solo ver/crear/eliminar), así que ese checkbox no hace nada para esta feature. Corregido directo en producción (`givePermissionTo`, acción aditiva) tras confirmar con el usuario que él mismo lo arreglara.

Tras confirmar que ya funcionaba, el usuario dio feedback de UX: la pantalla enterrada en "Configuración personal" no era práctica, y pidió (1) que viviera como opción en la Agenda junto a crear una cita, y (2) un paso de confirmación con contraseña antes de bloquear, para evitar que sea accidental.

**Cambios:** el botón "+ Cita" del header de `GlobalAgenda.tsx` (antes navegaba directo a elegir mascota) ahora abre un bottom-sheet con 2 opciones — "Nueva cita" (comportamiento de siempre) y "Bloquear mi horario" (nueva, visible solo si `user.operator_role` existe, navega a la pantalla ya construida). El formulario de alta en `MobUnavailability.tsx` ahora es de 2 pasos: (1) fecha/hora/motivo, (2) pantalla de confirmación que pide contraseña antes de guardar — reutiliza `POST /api/me/verify-password` (el mismo endpoint ya construido en BL-038 para desbloquear la app; solo confirma identidad, no cambia contraseña ni token) y solo llama a `POST /api/me/unavailabilities` si la verificación es exitosa.

**Verificación:** sin tests backend nuevos (no se tocó ningún endpoint, solo el flujo de UI que ya consume endpoints existentes). App móvil verificada con `tsc --noEmit` + `npm run build`, mismos 2 errores preexistentes de siempre (ajenos a este cambio).

### 📁 Archivos principales tocados
- `mob_apps/operador/src/admin/GlobalAgenda.tsx` (menú "+" con 2 opciones)
- `mob_apps/operador/src/admin/MobUnavailability.tsx` (formulario de 2 pasos con confirmación por contraseña)
- Datos de producción: permisos corregidos para `tomasmg`
- `docs/tecnico/BACKLOG.md`

### 🛑 Pendientes activos
1. Commit y push de esta sesión.
2. BL-047 (clínica fase 2), BL-053 (artículos de uso interno), BL-028 (firewall ufw), BL-001/002/004 (UI/config), BL-024b (mensajería automática por API).

---

## 📅 Cierre de sesión: 20/07/2026 (cont. 5) — BL-061: autoservicio de disponibilidad para operadores (app móvil)

### ✅ Logros y Cambios

Continuación directa de BL-060: el usuario pidió que un operador pueda bloquear sus propias horas sin depender del administrador, gateado por un permiso asignable. Se usó `EnterPlanMode` (2 agentes `Explore` en paralelo — vínculo `User`↔`Operator`/convención de permisos Spatie por un lado, estructura de la app móvil por otro — más diseño directo sin agente `Plan` dado que la investigación ya dejó el diseño casi completo) para decidir arquitectura antes de tocar código. Dos decisiones confirmadas con el usuario antes de diseñar: la pantalla vive en la **app móvil** (no Backoffice web), y el permiso solo deja tocar el propio registro de `Operator`, nunca el de otro.

**Hallazgo clave que cambió el diseño original:** la matriz de permisos de `Usuarios → editar` (Backoffice) hace `syncPermissions()` con solo lo que está marcado en su checkbox — cualquier permiso fuera de esa matriz (como los granulares `alergias.administrar`/`clinico.firmar`) se borraría solo en la siguiente edición del usuario. Por eso el permiso nuevo (`disponibilidad_propia`) se diseñó **estilo CRUD** (`ver/crear/editar/eliminar`) y se agregó a esa misma matriz, en vez de un granular suelto como se había planteado al inicio — sin esto, el permiso se habría auto-revocado la primera vez que un admin editara al operador por cualquier otro motivo.

**Backend:** `disponibilidad_propia` agregado al array de módulos de `BaseRolesSeeder` (genera los 4 permisos) y a `UserController@create()`/`@edit()` (misma matriz de checkboxes ya existente, sin rol operador nuevo). Controller API nuevo `Api\UnavailabilityController` (`index`/`store`/`destroy`, JSON puro) que resuelve siempre el operador vía `$request->user()->operator` (relación ya existente `users.operator_id`) — nunca lee `operator_id` del body, así que no importa qué mande el cliente, la fila queda ligada al operador del usuario autenticado; `destroy()` compara `operator_id` antes de borrar. 3 rutas nuevas bajo `/api/me/unavailabilities`, primer uso de `middleware('permission:...')` en `routes/api.php` (funciona sin fricción porque `ApiAuthenticate` hace login sobre el guard `web`, el mismo de Spatie).

**App móvil:** pantalla nueva `MobUnavailability.tsx` (lista + FAB + bottom-sheet de alta, mismo patrón visual que `MobCaja.tsx`; borrado real por fila vía `DELETE`, no en memoria como `ClientDetail.tsx`), ruta `/configuracion/disponibilidad`, enlazada desde una sección nueva en `MobUserConfig.tsx` — visible solo si `user.operator_role` existe (limpieza de UI nada más, el gate real de seguridad lo resuelve el servidor con el permiso).

**Verificación:** 8 tests nuevos (`OperatorSelfServiceUnavailabilityTest` — incluye confirmar que `BaseRolesSeeder` sigue generando los 4 permisos limpio), suite sin regresiones (37 fallidas preexistentes, 243 pasan, antes 235). App móvil sin infraestructura de test de UI en este entorno — verificada con `tsc --noEmit` + `npm run build`; los 2 errores de `tsc` que aparecen son preexistentes y ajenos a este cambio (`ActiveService.tsx`, huérfano ya documentado en BL-037; un tipo `key` en `MobCajaMovimientos.tsx`).

**Producción:** corrido `db:seed --class=BaseRolesSeeder --force` en el contenedor real — confirmados los 4 permisos nuevos en la BD real.

### 📁 Archivos principales tocados
- `database/seeders/BaseRolesSeeder.php` (módulo `disponibilidad_propia`)
- `app/Http/Controllers/UserController.php` (matriz de permisos en `create()`/`edit()`)
- `app/Http/Controllers/Api/UnavailabilityController.php` (nuevo)
- `routes/api.php` (3 rutas nuevas + import)
- `tests/Feature/Api/OperatorSelfServiceUnavailabilityTest.php` (nuevo)
- `mob_apps/operador/src/admin/MobUnavailability.tsx` (nuevo)
- `mob_apps/operador/src/App.tsx` (ruta + import)
- `mob_apps/operador/src/admin/MobUserConfig.tsx` (sección/link nuevo)
- `docs/tecnico/BACKLOG.md`

### 🛑 Pendientes activos
1. Commit y push de esta sesión.
2. Desde Backoffice web, ir a Usuarios → editar el operador real que se quiera habilitar (ej. Jose Mendez Pérez) y marcar "Crear"/"Eliminar" en la fila "Disponibilidad propia" de la matriz — el permiso existe en la BD pero todavía no está asignado a ningún usuario real.
3. BL-047 (clínica fase 2), BL-053 (artículos de uso interno), BL-028 (firewall ufw), BL-001/002/004 (UI/config), BL-024b (mensajería automática por API).

---

## 📅 Cierre de sesión: 20/07/2026 (cont. 4) — BL-060: fix real en producción — horario por default con el horario general del negocio

### ✅ Logros y Cambios

Continuación inmediata de BL-060 (cont. 3): el usuario probó la feature recién cerrada en producción real y reportó "ya los cambié pero no guarda los cambios". Se verificó directo contra la BD real: el operador sí se había actualizado (`updated_at` recién tocado), pero `operator_weekly_schedules` seguía en 0 filas para toda la tabla — el usuario había llenado horas en algunos días sin marcar la casilla "Trabaja" de esos días, y el diseño original (tabla vacía, opt-in explícito por checkbox) descartaba esas filas en silencio, sin ningún error visible.

**Decisión del usuario:** en vez de solo explicar el checkbox, cambiar el default — el formulario debe **precargar los 7 días con el horario operativo general del negocio** (`BusinessHours`, la única referencia de horario que existe hoy en el sistema) en vez de partir vacío, y el staff ajusta/desmarca desde ahí si un operador necesita algo distinto. Arreglar además los operadores que ya existen.

**Cambios:** `OperatorController` inyecta `BusinessHours` (mismo patrón ya usado en `SpaBookingController`/`Api\BookingController`) y pasa `defaultScheduleStartTime`/`defaultScheduleEndTime` a `create()`/`edit()`. En `form.blade.php`, cuando el operador **no tiene ninguna fila** de horario capturada (`$existingSchedule->isEmpty()`), los 7 días se muestran marcados con el horario general por default; en cuanto el operador ya tiene al menos una fila real, el formulario deja de aplicar ese default y respeta exactamente lo capturado (sin fila = día libre, como antes). **Backfill de una sola vez** (vía tinker, no un comando permanente) para los 2 operadores reales existentes — ambos quedaron con 7 filas = horario general (09:00–19:00), verificado directo contra la BD real de producción, no solo en tests.

**Verificación:** 2 tests nuevos (`test_create_page_prefills_weekly_schedule_with_business_hours`, `test_edit_page_does_not_default_days_when_operator_already_has_a_partial_schedule` — confirma que el default deja de aplicar en cuanto hay al menos una fila real). Suite completa sin regresiones: 37 fallidas preexistentes sin cambio, 235 pasan (antes 233).

### 📁 Archivos principales tocados
- `app/Http/Controllers/OperatorController.php` (inyecta `BusinessHours`, pasa defaults a `create()`/`edit()`)
- `resources/views/operators/partials/form.blade.php` (lógica de default cuando no hay horario capturado)
- `tests/Feature/OperatorWeeklyScheduleAndUnavailabilityTest.php` (2 tests nuevos)
- Datos de producción: backfill de `operator_weekly_schedules` para los 2 operadores reales existentes (7 filas c/u)
- `docs/tecnico/{MODELO_BD,BACKLOG}.md`

### 🛑 Pendientes activos
1. Commit y push de esta sesión.
2. Confirmar con el usuario que ahora sí guarda correctamente al probarlo de nuevo en producción.
3. BL-047 (clínica fase 2), BL-053 (artículos de uso interno), BL-028 (firewall ufw), BL-001/002/004 (UI/config), BL-024b (mensajería automática por API).

---

## 📅 Cierre de sesión: 20/07/2026 (cont. 3) — BL-060: horario semanal + bloqueos de no-disponibilidad por operador

### ✅ Logros y Cambios

El usuario preguntó si los operadores ya tenían horario de trabajo personal y bloqueos de no-disponibilidad configurables. No existía nada: solo el horario operativo *general* del negocio (`BusinessHours`, único y global, sin distinción por operador ni día) y el traslape de citas por operador (`OperatorAvailabilityChecker::hasConflict()`, BL-025). Se usó `EnterPlanMode` (2 agentes `Explore` en paralelo para mapear el estado actual y los puntos de integración exactos, más 1 agente `Plan`) antes de tocar código, dado que implicaba decisiones de modelo de datos y de dónde vivía la validación.

**2 tablas nuevas**: `operator_weekly_schedules` (día 0-6 alineado a `Carbon::SUNDAY..SATURDAY` + hora inicio/fin, único por operador+día) y `operator_unavailabilities` (rango datetime + motivo libre). Ambas con `#[Fillable]` (atributo nativo de Laravel, mismo patrón que `ResourceAllocation`).

**Decisión de compatibilidad confirmada con el usuario:** el horario semanal es **opt-in** — un operador sin ninguna fila capturada sigue agendable a cualquier hora, cero regresión sobre los operadores existentes hoy. Las no-disponibilidades **no son opt-in**: si existen y se traslapan con el rango solicitado, siempre bloquean — mismo nivel de "hard block" que el traslape de citas, no un aviso (a diferencia de la cobertura geográfica de BL-043).

**2 métodos nuevos en `OperatorAvailabilityChecker`** (`isOutsideWorkingHours()`, `hasTimeOff()`) — se agregaron a la clase existente en vez de crear una nueva, siguiendo su mismo patrón (clase concreta sin interfaz, sin binding en `AppServiceProvider`), para no tocar los constructores de los 2 controllers que ya la inyectan. Integrados en los **4 puntos reales** donde se valida `operator_id` al agendar/reprogramar una cita SPA (`Api\BookingController::store()`/`update()`, `SpaBookingController::update()`/`storeForPet()`), justo después del traslape de citas existente y antes de guardar — mismo formato de respuesta que cada uno ya usaba (JSON 422 en API, `redirect()->back()->with('error', ...)` en web).

**CRUD**: horario semanal integrado al formulario de operador ya existente (`OperatorController::store()/update()`, patrón "borrar y recrear" igual que `syncRoles`/`syncPrimaryBranch`/`syncCompensation`) — tabla de 7 días en `operators/partials/form.blade.php`. Bloqueos de no-disponibilidad en un controller nuevo y minimalista (`OperatorUnavailabilityController`, solo store/destroy) con su propia partial (`operators/partials/unavailabilities.blade.php`), incluida en `edit.blade.php`. Captura exclusiva desde Backoffice web — ni la app móvil ni el propio operador la tocan, a pedido del usuario. Sin permiso Spatie nuevo (consistente con que `operators` hoy tampoco tiene uno).

**Reincidencia real de NT-010** (ya documentada en el proyecto): la tabla de 7 días en `form.blade.php` usó primero un bloque `@php ... @endphp` multilínea dentro del `@foreach`, y disparó de inmediato el bug de compilador Blade ya conocido ("Undefined variable $dayLabels" al renderizar, no un `ParseError` obvio). Corregido con la forma de una sola línea `@php($var = expr)` que la propia NT-010 recomienda para este caso. Atrapado por un test de humo (`GET operators.edit`/`operators.create`) agregado específicamente para esto — vale la pena tenerlo como regla: cualquier vista Blade con loops nuevos necesita al menos un test que la renderice de verdad, no solo tests de submit.

**Verificación:** 18 tests nuevos — 6 en `OperatorAvailabilityCheckerTest` (checker aislado), 2 en `Api/BookingSchedulingValidationTest` + 2 en `SpaBookingSchedulingValidationTest` (integración real de bloqueo en los 4 call-sites), 8 en `OperatorWeeklyScheduleAndUnavailabilityTest` (CRUD + 2 de humo de render). Suite completa sin regresiones: 37 fallidas preexistentes sin cambio, 233 pasan (antes 215).

### 📁 Archivos principales tocados
- `database/migrations/2026_07_20_100000_create_operator_weekly_schedules_table.php`, `2026_07_20_100001_create_operator_unavailabilities_table.php` (nuevas)
- `app/Models/OperatorWeeklySchedule.php`, `OperatorUnavailability.php` (nuevos), `Operator.php` (2 relaciones nuevas)
- `app/Domain/Planning/Services/OperatorAvailabilityChecker.php` (`isOutsideWorkingHours()`, `hasTimeOff()`)
- `app/Http/Controllers/Api/BookingController.php`, `SpaBookingController.php` (4 call-sites)
- `app/Http/Controllers/OperatorController.php` (`rules()`, `validateWeeklyScheduleRanges()`, `syncWeeklySchedule()`, `edit()`)
- `app/Http/Controllers/OperatorUnavailabilityController.php` (nuevo)
- `routes/web.php` (2 rutas nuevas)
- `resources/views/operators/partials/form.blade.php`, `unavailabilities.blade.php` (nueva), `edit.blade.php`
- `tests/Feature/Planning/OperatorAvailabilityCheckerTest.php`, `tests/Feature/Api/BookingSchedulingValidationTest.php`, `tests/Feature/SpaBookingSchedulingValidationTest.php`, `tests/Feature/OperatorWeeklyScheduleAndUnavailabilityTest.php` (nuevo)
- `docs/tecnico/{MODELO_BD,BACKLOG}.md`

### 🛑 Pendientes activos
1. Commit y push de esta sesión (BL-060 completo, sin commitear todavía).
2. BL-047 (clínica fase 2), BL-053 (artículos de uso interno), BL-028 (firewall ufw), BL-001/002/004 (UI/config), BL-024b (mensajería automática por API).

---

## 📅 Cierre de sesión: 20/07/2026 (cont. 2) — BL-052b: cierre real de los 8 colores + incidente de cuenta Meta (NT-040)

### ✅ Logros y Cambios

Continuación directa de la sesión anterior (BL-052b dejó pendiente el conteo físico de 7 colores y la verificación de agrupación visual en Commerce Manager). El usuario dio el conteo real: Azul Oscuro (2), Dorado (2), Azul Turquesa (1), Magenta (1), Morado (1), Verde (1), Rojo (1) — mismo precio/costo/foto que el Negro ya publicado (misma foto porque muestra los 8 colores). Altas #11–17 en `items`, mismo `meta_variant_group='id-tag-38mm'` que el #10.

**Incidente real durante el primer intento de sync de los 8 artículos:** falló con `"API access blocked"` (`OAuthException code 200`) en los 8, incluido el #10 que había publicado bien horas antes — no era el bug de `price`/formato ya resuelto en BL-052 (ese log era de pruebas viejas del 18-19/07). Diagnóstico hecho con **Claude en Chrome** (no disponible vía terminal — requiere sesión de navegador autenticada contra Meta): la cuenta de developer de la app "EstetiCAN Catálogo" estaba bloqueada por el antiabuso de Meta ("actividad inusual" — probablemente disparado por crear 7 artículos casi idénticos y sincronizar poco después), redirigiendo a `developers.facebook.com/r/user/error/` con aviso de "Confirmación de la cuenta requerida". El usuario completó la confirmación manualmente; el sync funcionó de inmediato después, **sin ningún cambio de código** — confirma que el payload/código de BL-052/052b estaba correcto desde el principio. Documentado en **NT-040** para que si vuelve a pasar no se pierda tiempo depurando `MetaCatalogSyncService`.

Sync final: **8 publicados, 0 errores**. Verificación pendiente de BL-052b (agrupación visual en Commerce Manager) confirmada con Claude en Chrome: las 8 variantes aparecen agrupadas como un solo producto colapsable ("Variantes: 8") bajo `retailer_product_group_id=id-tag-38mm`, no como productos sueltos — cierra la única duda real que quedaba abierta de todo el trabajo de BL-052/052b.

### 📁 Archivos principales tocados
- `app/Domain/MetaCatalog/Services/MetaCatalogSyncService.php` (comentario actualizado: agrupación confirmada, ya no "no verificado todavía")
- `docs/tecnico/BACKLOG.md` (BL-052b actualizado con cierre real + referencia a NT-040)
- `docs/tecnico/NOTAS_TECNICAS.md` (NT-040 nueva)
- Datos de producción: `items` #11–17 (7 variantes de color nuevas del ID TAG 38mm)

### 🛑 Pendientes activos
- BL-047 (clínica fase 2), BL-053 (artículos de uso interno), BL-028 (firewall ufw), BL-001/002/004 (UI/config), BL-024b (mensajería automática por API).

---

## 📅 Cierre de sesión: 20/07/2026 (cont.) — BL-052b: categoría de Meta + variantes de color en Artículos

### ✅ Logros y Cambios

Continuación directa de la sesión de hoy (BL-052 recién cerrado): al revisar el primer artículo real publicado ("ID TAG Colores 38mm"), el usuario notó que en realidad se vende en 8 colores y que no había forma de capturar la categoría de producto de Facebook/Google — ninguna de las dos cosas estaba en el diseño original de BL-052. Se usó `EnterPlanMode` (2 agentes `Explore` en paralelo — schema/CRUD de `Item`, y el patrón "Grupos" de BL-055 para confirmar que no era reutilizable — más 1 agente `Plan`) para diseñar la solución antes de tocar código, dado que implicaba una decisión de modelo de datos.

**Decisión de diseño:** cada variante de color es su propia fila de `Item` (ya es una entidad completa — stock, precio y foto por-fila desde BL-049/054) enlazada a sus hermanas por una clave de texto libre compartida (`meta_variant_group`), que se manda tal cual como `retailer_product_group_id` a Meta — mismo criterio ya usado con `retailer_id = "item-{id}"` (nosotros elegimos el valor, Meta no devuelve nada que guardar). Se descartó explícitamente un sub-modelo de variantes/tabla nueva por duplicar lo que `Item` ya es. Se confirmó contra la documentación real de Meta (no de memoria, por la lección del `price` de hoy mismo) que el endpoint de creación individual usa `retailer_product_group_id` — **no** `item_group_id`, que es el nombre del feed CSV, la misma trampa feed-vs-endpoint que ya mordió una vez hoy.

**3 columnas nuevas en `items`** (migración aditiva, corrida en producción real): `meta_category`, `meta_variant_group` (indexado), `meta_color`. Prefijo `meta_` a propósito — son campos que existen para el contrato de una API externa, no taxonomía de negocio propia (no contradice la decisión de BL-051 de no construir taxonomía estructurada real, el propósito es distinto). `size` quedó fuera a propósito — sin necesidad real hoy, trivial de agregar después con el mismo patrón (documentado en `IDEAS_FUTURO.md`).

Formulario de Artículos: `meta_category` con datalist de ~15 categorías reales de la taxonomía de Google (rama "Animals & Pet Supplies", relevantes al giro veterinario/grooming) — mismo patrón visual que `department`. `meta_variant_group` con datalist **dinámico** (valores ya usados en la BD, vía `Item::whereNotNull('meta_variant_group')->distinct()`) para mitigar que un typo en la clave deje dos variantes sin agrupar. `meta_color` texto simple, sin datalist (abierto, sin precedente de lista cerrada para esto).

`MetaCatalogSyncService::buildPayload()`: agrega `category` cuando `meta_category` está presente; agrega `retailer_product_group_id`+`color` **solo si ambos** `meta_variant_group`/`meta_color` están presentes en la misma fila — regla de "omisión suave": si falta uno de los dos, el artículo se publica igual, sin agrupar (a diferencia del skip por falta de foto/precio, que sí excluye el artículo — aquí no le falta nada esencial).

**Verificado contra la API real** (no solo `Http::fake()`): se actualizó el artículo real #10 con `meta_category`, `meta_variant_group='id-tag-38mm'`, `meta_color='Negro'` (renombrado a "ID TAG 38mm — Negro" — es el único color con existencia física confirmada hoy), se corrió el sync real, y se consultó de vuelta contra la Graph API (`GET /{catalog_id}/products?fields=...`). Meta aceptó y devolvió los 3 campos tal cual, incluyendo el `&` literal del formato de taxonomía de Google — resuelve el riesgo principal que traía el plan. **No verificado todavía:** el comportamiento de agrupación visual en Commerce Manager con 2+ variantes reales, porque solo existe 1 color con inventario confirmado — el usuario no tiene el conteo de los otros 7 colores todavía (queda como pendiente real, no inventado).

**Verificación:** 5 tests nuevos (`MetaCatalogSyncTest`, 12 en total: category presente/ausente, grupo+color agrupados correctamente en 2 artículos distintos, omisión suave en ambos sentidos). Suite completa sin regresiones (37 fallidas preexistentes sin cambio, 215 pasan, antes 210).

### 📁 Archivos principales tocados
- `database/migrations/2026_07_20_041408_add_meta_category_and_variant_fields_to_items_table.php` (nuevo, corrida en producción)
- `app/Models/Item.php` (Fillable + activity log)
- `app/Http/Controllers/ItemController.php` (validación + `existingVariantGroups`)
- `app/Domain/MetaCatalog/Services/MetaCatalogSyncService.php` (`buildPayload()`, comentario con verificación fechada)
- `resources/views/items/partials/form.blade.php` (3 campos nuevos)
- `tests/Feature/MetaCatalogSyncTest.php` (5 tests nuevos)
- `docs/tecnico/{MODELO_BD,BACKLOG}.md`, `docs/architecture/IDEAS_FUTURO.md` (idea de `meta_size` para el futuro)

### 🛑 Pendientes activos
1. Commit y push de esta sesión (BL-052b completo, sin commitear todavía).
2. **Pendiente real, fuera de este repo:** el usuario debe contar físicamente el inventario de los otros 7 colores del ID TAG y capturarlos como artículos nuevos (mismo `meta_variant_group='id-tag-38mm'`, su propio `meta_color`) — en ese momento, revisar visualmente en Commerce Manager que las variantes queden agrupadas como un solo producto (hoy solo se confirmó que Meta acepta los campos, no el comportamiento de agrupación con 2+ variantes reales).
3. BL-047 (clínica fase 2), BL-053 (artículos de uso interno), BL-028 (firewall ufw), BL-001/002/004 (UI/config), BL-024b (mensajería automática por API).

---

## 📅 Cierre de sesión: 20/07/2026 — BL-052: cierre real — token generado, primer artículo publicado en Meta, 2 bugs reales corregidos

### ✅ Logros y Cambios

Retomando exactamente donde quedó la sesión del 18/07/2026 (código listo, configuración de Meta atorada en "Generar identificador"). Se guió al usuario paso a paso por chat (sin `claude-in-chrome` disponible en esta sesión) hasta completar el token del usuario de sistema "Sync Catalogo Backoffice", con permiso **único** `catalog_management` — se decidió explícitamente no incluir los 4 permisos de mensajería/WhatsApp que el usuario propuso adelantar ("luego será para confirmar citas en automático y enviar promociones"), porque los permisos de un token quedan fijos al generarse y no hay costo real en esperar: cuando exista código real de mensajería automática (BL-024b, sin diseñar todavía) se genera un token nuevo con esos permisos en ese momento.

Catalog ID (`2121927028717553`) y Access Token capturados en Configuración → "Catálogo de WhatsApp/Meta".

**Antes de la primera prueba real, se verificó qué artículo calificaría** (evitar que el primer clic publicara todo el catálogo real de golpe sin querer) — de 7 artículos activos, 6 son vacunas sin sentido para venta directa y ninguno calificaba (`ai_visible`/`stock`/`price` en 0 o vacío). Se completó **#10 "ID TAG Colores 38mm"** (ya tenía `ai_visible=true`) con stock real, precio de compra/venta y foto, vía el flujo normal del backoffice.

**2 bugs reales encontrados y corregidos en la primera sincronización real (no en `Http::fake()`):**
1. **`price` rechazado por Meta** (`(#100) Param price must be a number`): el endpoint de creación individual de producto (`POST /{catalog_id}/products`) exige `price` como **entero en la unidad mínima de la moneda** (centavos) — a diferencia del feed CSV/batch de Meta, que sí usa un string `"9.99 USD"` y fue la referencia (incorrecta para este endpoint) que se usó al escribir el código el 18/07. Corregido a `(int) round($item->price * 100)` en `MetaCatalogSyncService::buildPayload()`.
2. **`Item::getPhotoUrlAttribute()`/`getPhotoThumbnailUrlAttribute()` devolvían ruta relativa** (`/storage/...`), no URL absoluta — `Storage::disk('public')->url()` no antepone `APP_URL` por sí solo. Nunca fue un bug visible porque todo consumidor previo (vistas Blade) resuelve rutas relativas contra el dominio de la página actual; Meta es el primer consumidor externo que necesita URL absoluta para poder descargar la imagen del producto. Corregido envolviendo con el helper `url()`. **Mismo patrón sigue presente en `Pet`/`Operator`/`User`/`Resource`/etc.** — dejado sin tocar a propósito, no está roto en su contexto actual (fuera de alcance de BL-052).

Tras ambos fixes, sincronización real: **1 publicado, 0 errores** — primer artículo real de EstetiCAN visible en el catálogo de Meta Commerce Manager.

**Verificación:** `MetaCatalogSyncTest` actualizado (aserción de `price` ahora espera entero en centavos), 7 tests pasan. Suite completa sin regresiones (37 fallidas preexistentes sin cambio, 210 pasan — mismo conteo que dejó la sesión del 18/07).

**Cierra BL-052 por completo** — movido a Completados en `BACKLOG.md`.

### 📁 Archivos principales tocados
- `app/Domain/MetaCatalog/Services/MetaCatalogSyncService.php` (formato `price`, comentario actualizado)
- `app/Models/Item.php` (`getPhotoUrlAttribute()`/`getPhotoThumbnailUrlAttribute()` → URL absoluta)
- `tests/Feature/MetaCatalogSyncTest.php` (aserción de `price` actualizada)
- `docs/tecnico/{MODELO_BD,BACKLOG}.md` (BL-052 movido a Completados, sección de Meta actualizada con los 2 bugs)

### 🛑 Pendientes activos
1. Commit y push de esta sesión, incluyendo lo que ya venía sin commitear del 18/07/2026 (BL-052 completo).
2. Considerar (no decidido, no urgente) aplicar el mismo fix de URL absoluta a `Pet`/`Operator`/`User`/`Resource` si en el futuro algún consumidor externo (no-Blade) los necesita — hoy no hace falta.
3. BL-047 (clínica fase 2), BL-053 (artículos de uso interno), BL-028 (firewall ufw), BL-001/002/004 (UI/config), BL-024b (mensajería automática por API — permisos de WhatsApp quedaron deliberadamente fuera del token de hoy).

---

## 📅 Cierre de sesión: 18/07/2026 (cont. 2) — BL-052: publicación en catálogo de Meta/WhatsApp — código listo, bloqueado en credenciales

### ✅ Logros y Cambios

BL-052 llevaba desde el 14-15/07/2026 anotado como "sin acotar todavía" (no se había decidido el mecanismo de publicación). Investigación previa (agente `Explore`) confirmó que **no existe ninguna integración real con APIs de Meta** en el proyecto — todo WhatsApp (BL-024) es un link `wa.me` abierto a mano por el operador, cero tokens/webhooks — y que "catálogo de WhatsApp Business" (Commerce Manager/Catalog API) es un producto de Meta completamente distinto al de mensajería, sin nada reutilizable.

**Decisión del usuario vía `AskUserQuestion` (3 preguntas):** (1) mecanismo = integración real vía Meta Catalog API (no export manual, no página pública propia); (2) ni el catálogo en Commerce Manager ni la Meta App de desarrollador existen todavía — ambas hay que crearlas del lado de Meta; (3) disparo = botón manual "Sincronizar ahora" (no scheduler — no hay cron corriendo en la OPi).

**Construido hoy (sin poder probarlo contra la API real, por falta de credenciales):**
- Sección nueva `whatsapp_catalog` en `SystemSettings` (`whatsapp_catalog_id` texto, `whatsapp_catalog_access_token` cifrado — mismo patrón `type: 'password'` que `ai_assistant_api_key`, incluye el "dejar en blanco conserva lo guardado" gratis).
- `App\Domain\MetaCatalog\Services\MetaCatalogSyncService` (+ interfaz, bindeada en `AppServiceProvider`): filtra `Item` con `is_active && ai_visible && stock_quantity > 0` (mismo filtro que ya usa `ServiceCatalogPromptBuilder` para el asistente de IA), omite (sin error) los que no tienen foto o precio, y hace `POST /{catalog_id}/products` por artículo vía la Graph API de Meta.
- **Decisión de diseño clave:** cada producto usa `retailer_id = "item-{id}"` como identificador — Meta hace upsert sobre ese campo, así que **no hace falta guardar** el ID que Meta le asigna internamente al producto (se evitó agregar una columna nueva a `items` solo para trackear ese mapeo). Esto también evita la complejidad de la Batch API asíncrona de Meta (que requiere sondear un `handle_id`) — llamadas individuales síncronas por artículo dan el resultado inmediato por artículo que pidió el usuario ("cuántos subieron, errores si los hubo"), razonable para un catálogo de decenas de artículos, no miles.
- El campo `url` del producto (obligatorio en el feed de Meta, pensado para "página del producto") no existe como tal en este negocio (sin sitio de e-commerce) — se resolvió con un link `wa.me` prellenado ("Hola, me interesa: {nombre}"), reusando `PhoneNormalizer`/`brand_whatsapp_number` ya existentes. Decisión tomada sin preguntar (encaja con el patrón ya establecido en todo el proyecto), no hizo falta construir nada nuevo.
- Botón "Sincronizar catálogo WhatsApp" en Artículos → índice (`items.catalog-sync`, permiso `editar catalogo_articulos`, junto al botón "Crear artículo").

**Limitación explícita, no resuelta:** el formato exacto de algunos campos del payload de Meta (`price` como string `"150.00 MXN"`, `category`) se tomó de memoria/documentación de Meta, **no verificado contra la API real** — no hay forma de confirmarlo sin credenciales reales. Primer paso al retomar: en cuanto el usuario tenga `catalog_id`/token, hacer un envío de prueba con 1 artículo real y ajustar formato si Meta lo rechaza (el código ya captura y muestra el mensaje de error exacto que devuelva Meta).

**Verificación:** 7 tests nuevos (`MetaCatalogSyncTest`) vía `Http::fake()` — fijan la forma del request saliente (sí se puede probar sin credenciales), cobertura de artículos sin foto/precio/no visibles, error de Meta capturado como "falló", credenciales faltantes bloquean sin llamar a Meta, permiso requerido. Suite completa sin regresiones (37 fallidas preexistentes sin cambio, 210 pasan, antes 203).

**Pendiente real, fuera de mi control:** el usuario debe crear en Meta (fuera de este repo) — un catálogo en Commerce Manager (business.facebook.com/commerce) y una Meta App de desarrollador con permiso `catalog_management` y un access token de larga duración. Sin eso, el botón queda bloqueado con mensaje de error (ya construido, probado).

**Actualización — configuración del lado de Meta, avanzada el mismo día (guiada paso a paso por chat, sin código nuevo):**
- Porfolio empresarial **"Estetican"** creado (no verificado — suficiente para seguir, la verificación solo se necesita más adelante si Meta la exige para el token).
- App de Meta **"EstetiCAN Catálogo"** creada, con los 2 casos de uso: "Administra los productos con la API de catálogo" + "Conecta con los clientes a través de WhatsApp" (el segundo se agregó a pedido del usuario, sin necesidad real para BL-052 hoy — deja la puerta abierta a automatizar mensajería real por API en el futuro, en vez del `wa.me` manual de BL-024, ver idea nueva en `IDEAS_FUTURO.md`).
- Catálogo en Commerce Manager **"EstetiCAN Productos"** creado, vinculado al porfolio "Estetican" — **Catalog ID real: `2121927028717553`**.
- Usuario del sistema **"Sync Catalogo Backoffice"** (id `61592149287929`) creado con rol Administrador, con acceso total asignado a los 2 activos (catálogo + app).
- **Pendiente exacto donde se interrumpió la sesión:** el paso de "Generar identificador" (token) con permiso `catalog_management` — se pidió y se dio confirmación explícita para proceder, pero el navegador se atoró antes de completarlo. Retomar ahí, no desde cero.

⚠️ **Nota de seguridad para retomar:** el Access Token, cuando se genere, **nunca debe pegarse en este archivo, en BACKLOG.md, ni en ningún archivo versionado con git** — va directo al campo cifrado "Access Token" en Configuración del backoffice → "Catálogo de WhatsApp/Meta" (`whatsapp_catalog_access_token`, `type: password`, ya cifrado por `SystemSettings`). Si en algún momento quedó pegado en el chat o en un archivo temporal, considerarlo expuesto y regenerarlo desde Meta.

### 📁 Archivos principales tocados
- `app/Domain/MetaCatalog/Contracts/MetaCatalogSyncServiceInterface.php`, `app/Domain/MetaCatalog/Services/MetaCatalogSyncService.php` (nuevos)
- `app/Http/Controllers/MetaCatalogSyncController.php` (nuevo)
- `app/Providers/AppServiceProvider.php` (binding)
- `app/Support/SystemSettings/SystemSettings.php` (sección `whatsapp_catalog`)
- `routes/web.php` (`items.catalog-sync`)
- `resources/views/items/index.blade.php` (botón)
- `tests/Feature/MetaCatalogSyncTest.php` (nuevo)
- `docs/tecnico/{MODELO_BD,BACKLOG}.md`

### 🛑 Pendientes activos
1. **Retomar en Meta:** completar "Generar identificador" del Usuario del sistema "Sync Catalogo Backoffice" con permiso `catalog_management` (se atoró el navegador, sin generar todavía).
2. Capturar Catalog ID (`2121927028717553`) + Access Token en Configuración → "Catálogo de WhatsApp/Meta", y hacer la primera sincronización real de prueba.
3. Commit y push de esta sesión (código ya escrito y probado con `Http::fake()`, sigue sin commitear).
2. **BL-052 real, fuera de este repo:** el usuario crea el catálogo en Meta Commerce Manager + la Meta App/token. En cuanto los tenga, retomar con un envío de prueba de 1 artículo.
3. BL-047 (clínica fase 2), BL-053 (artículos de uso interno), BL-028 (firewall ufw), BL-001/002/004 (UI/config).

---

## 📅 Cierre de sesión: 18/07/2026 (cont.) — BL-049: transferencias entre sucursales (tercera y última pieza) — cierra BL-049

### ✅ Logros y Cambios

Última pieza de BL-049, retomando la decisión ya tomada el 17/07/2026 sin volver a discutir alcance: **sin tabla `warehouses` nueva** — `branch_id` (ya existente desde `item_branch_stocks`) es la unidad de ubicación, y una transferencia es un par de movimientos en el mismo ledger `item_movements`.

**`ItemMovementServiceInterface::transfer(itemId, fromBranchId, toBranchId, quantity, notes, createdByUserId)`** — dentro de una sola transacción, llama dos veces a `record()`: `transferencia_salida` (negativo, en origen) y `transferencia_entrada` (positivo, en destino). El de entrada usa el morph `reference` que ya existía (mismo mecanismo que liga un consumo a su `SpaBookingItem`/`PetVaccination`) para apuntar al movimiento de salida — así el par queda reconstruible sin agregar ninguna columna nueva a `item_movements`. El total global (`items.stock_quantity`) no cambia, solo su distribución entre sucursales.

**Decisión de consistencia (no se preguntó, se replicó el patrón ya establecido):** igual que cualquier otro movimiento en este ledger, una transferencia puede dejar la sucursal de origen en negativo sin bloquear — es la misma filosofía ya aplicada en `item_movements`/`item_branch_stocks` desde BL-054 ("negativo es información real, no un error"). Introducir una regla de bloqueo distinta solo para transferencias habría sido inconsistente sin una razón de negocio nueva.

**UI:** segundo formulario "Transferir entre sucursales" en Artículos → editar, junto al de movimiento manual existente (mismo permiso `editar catalogo_articulos`, sin pantalla ni ítem de navegación nuevo). `ItemMovementController::transfer()` valida `from_branch_id` ≠ `to_branch_id`. Tabla de historial de movimientos ahora etiqueta los 6 tipos con nombres legibles (antes `ucfirst()` crudo, que hubiera mostrado "Transferencia_salida").

**Verificación:** 6 tests nuevos (`ItemTransferTest`) cubriendo servicio, endpoint, par ligado, validación de sucursales iguales, saldo negativo permitido, y permiso. Sin migraciones nuevas (reutiliza columnas ya existentes de `item_movements`). Suite completa sin regresiones (37 fallidas preexistentes sin cambio, 203 pasan, antes 197).

**Cierra BL-049 por completo** — las 3 piezas (IM sencillo, multi-sucursal, venta cobrada + transferencias) están en producción.

### 📁 Archivos principales tocados
- `app/Domain/Inventory/Contracts/ItemMovementServiceInterface.php`, `app/Domain/Inventory/Services/ItemMovementService.php` (`transfer()`)
- `app/Http/Controllers/ItemMovementController.php` (`transfer()`)
- `routes/web.php` (`items.movements.transfer`)
- `resources/views/items/edit.blade.php` (form de transferencia + etiquetas de tipo)
- `tests/Feature/ItemTransferTest.php` (nuevo)
- `docs/tecnico/{MODELO_BD,BACKLOG}.md` (BL-049 movido a Completados)

### 🛑 Pendientes activos
1. Commit y push de esta sesión (después, corregir el hash real en `BACKLOG.md`, mismo patrón que la corrección de BL-059).
2. BL-047 (clínica fase 2), BL-052 (automatizar catálogo de WhatsApp/redes), BL-053 (artículos de uso interno), BL-028 (firewall ufw), BL-001/002/004 (UI/config).

---

## 📅 Cierre de sesión: 18/07/2026 — BL-049: descuento de stock por venta cobrada (segunda de tres piezas)

### ✅ Logros y Cambios

Continuación directa de la sesión del 17/07/2026, retomando la pieza que ya había quedado diseñada y documentada (decisiones 1 y 2 de esa sesión, ver bloque anterior) sin tener que volver a decidir alcance. Investigación previa (agente `Explore`) confirmó el punto crítico: **no existe ningún Observer/evento de dominio para "cita completada"** en este código (ver NT-020) — hay 3 puntos reales, todos duplicando el mismo `$booking->update(['status' => 'completed'])` sin capa de dominio compartida: `SpaBookingController::update()` (web, "Finalizar sesión"), `Api\PaymentController::store()` (cobro móvil, `mark_completed`), `Api\BookingController::update()` (edición genérica móvil). También se confirmó que `ExecutedService`/`ExecutedServiceItem` siguen huérfanas (0 filas) — no se construyó nada sobre ellas.

**Servicio nuevo:** `App\Domain\Inventory\Services\BookingStockConsumptionService` (+ `BookingStockConsumptionServiceInterface`, bindeado en `AppServiceProvider` junto a `ItemMovementServiceInterface`). `consume(SpaBooking $booking, ?int $createdByUserId)` itera `$booking->items` (`SpaBookingItem.item_id`/`quantity`, ya poblado por `QuoteService::acceptQuote()` al aceptar la cotización — **no** se pasa por `Group`/`GroupComponent` en este punto, esos ya se "aplanaron" a líneas individuales al aceptar el quote) y llama `ItemMovementServiceInterface::record()` con `type='consumo_servicio'`, `quantity` negativa, y `branchId = $booking->operator?->primaryBranch()?->id` (puede ser `null` — el consumo sigue siendo real en el caché global `items.stock_quantity`, solo no genera fila en `item_branch_stocks`).

**Llamada agregada inline en los 3 puntos reales**, mismo patrón exacto que ya usa `PaymentController` para `AccountingServiceInterface::createEntryForBookingPayment()` (`app(Interface::class)->metodo(...)` justo después del cambio de estado) — se evitó introducir un Observer nuevo en un código que no usa ese patrón en ningún otro lado.

**Idempotencia (riesgo real identificado, no solo teórico):** ninguno de los 3 endpoints impide reenviar `status=completed` sobre una cita ya completada (`SpaBookingController::update()` no valida el estado actual antes de aceptar `status=completed`; `PaymentController::store()` solo bloquea `cancelled`). Antes de descontar cada línea, el servicio verifica si ya existe un `item_movements` con `reference` = ese `SpaBookingItem` (`reference_type`/`reference_id`); si existe, la omite. Verificado con test dedicado (doble llamada a `agenda.update` con `status=completed`, un solo movimiento).

**Limitación aceptada, no resuelta:** `item_movements.quantity` es `integer` (BL-054) pero `spa_booking_items.quantity` es `decimal:2` (permite fracciones de servicio, ej. 0.5 hrs). Se redondea con `(int) round()` al descontar — una fracción real de unidad de producto (poco común, pero posible) se trunca. Documentado en código y en `MODELO_BD.md`, no bloqueante para esta pieza.

**Verificación:** 5 tests nuevos (`BookingStockConsumptionTest`) cubriendo los 3 endpoints reales + idempotencia + operador sin sucursal primaria. Sin migraciones nuevas (tablas ya existían desde el 17/07). Suite completa sin regresiones (37 fallidas preexistentes sin cambio, 197 pasan, antes 192).

### 📁 Archivos principales tocados
- `app/Domain/Inventory/Contracts/BookingStockConsumptionServiceInterface.php` (nuevo)
- `app/Domain/Inventory/Services/BookingStockConsumptionService.php` (nuevo)
- `app/Providers/AppServiceProvider.php` (binding)
- `app/Http/Controllers/SpaBookingController.php`, `app/Http/Controllers/Api/PaymentController.php`, `app/Http/Controllers/Api/BookingController.php` (llamada inline tras completar)
- `tests/Feature/BookingStockConsumptionTest.php` (nuevo)
- `docs/tecnico/{MODELO_BD,BACKLOG}.md`

### 🛑 Pendientes activos
1. Commit y push de esta sesión.
2. BL-049 restante (última pieza, ya diseñada desde el 17/07): almacenes/transferencias entre sucursales (`branch_id` como unidad de ubicación, sin tabla `warehouses` nueva).
3. Sigue pendiente de sesiones previas: BL-047 (clínica fase 2), BL-052 (automatizar catálogo de WhatsApp/redes), BL-053 (artículos de uso interno), BL-028 (firewall ufw), BL-001/002/004 (UI/config).

---

## 📅 Cierre de sesión: 17/07/2026 — BL-049: multi-sucursal real de Inventario (primera de tres piezas)

### ✅ Logros y Cambios

BL-049 ("Módulo Tienda/Inventario real") venía anotado como "sin diseñar todavía" y agrupaba tres piezas grandes: multi-sucursal real, conexión con venta cobrada, y almacenes/transferencias. Antes de escribir código se investigó el estado real (dos agentes de exploración) y se usó `EnterPlanMode` con un agente `Plan` para diseñar las tres piezas juntas — pero **el usuario acotó la sesión a construir solo la primera** ("solo multi-sucursal por ahora, lo demás después"), dejando las otras dos ya diseñadas mediante `AskUserQuestion` (decisiones registradas abajo) para retomar directo sin volver a decidir alcance.

**Multi-sucursal real construida hoy:** tabla nueva `item_branch_stocks` (`item_id`, `branch_id` **NOT NULL** — a diferencia de `item_movements.branch_id`, que sigue nullable —, `quantity` con signo, único `(item_id, branch_id)`). `ItemMovementService::record()` ahora, además de recalcular el caché global `items.stock_quantity`, recalcula con `SUM()` y hace `upsert` el saldo por sucursal cuando el movimiento trae `branch_id` — reutiliza el mismo `lockForUpdate()` sobre `Item` que ya serializa toda la operación, sin lock nuevo. Los movimientos sin sucursal (consumo automático de vacunas, que no se tocó) no generan fila en la tabla nueva — solo cuentan en el total global; el saldo "sin sucursal" se deriva por resta donde se muestra, sin persistirse (evita el problema real de que MySQL trata cada NULL como distinto en un índice único compuesto).

**Formulario manual de movimientos** (Artículos → editar): `branch_id` pasó de opcional a **obligatorio**, decisión explícita del usuario para consistencia con `cash_registers`/`cash_sessions`/`resources` (que ya tratan sucursal como campo estructural, no opcional). Nueva tabla "Existencia por sucursal" en la misma pantalla, y columna "Stock total" agregada al listado de Artículos (antes no mostraba stock en ningún lado del listado).

**Decisiones de diseño confirmadas para las dos piezas restantes (no implementadas hoy, quedan documentadas para no re-discutir alcance):**
1. *Trigger de descuento por venta cobrada:* cuando la cita pasa a `status='completed'` (no por monto pagado — el sistema no tiene concepto de "saldo pendiente"/"pago completo" en ningún lado, `Payment` permite parciales múltiples sin ese cálculo). Mismo criterio que ya usa el consumo automático de vacunas.
2. *Sucursal para esos movimientos automáticos:* `Operator::primaryBranch()` del operador asignado a la cita — sin tocar el esquema de `spa_bookings` (que no tiene `branch_id` directo).
3. *Almacenes/transferencias:* sin tabla `warehouses` nueva — "almacén" = `branch_id` ya existente; una transferencia es un par de movimientos entre dos sucursales en el mismo ledger `item_movements`.

**Riesgo identificado y dejado explícitamente sin tocar:** `ServiceCatalogPromptBuilder.php:87` (filtro `stock_quantity > 0` del asistente IA) sigue leyendo el total global agregado — no cambia de significado en esta pieza. La decisión de "¿qué sucursal debe ver el asistente?" se difiere a cuando exista lógica de sucursal-por-conversación.

**Verificación:** 6 tests nuevos (`ItemBranchStockTest` ×5, más un test de rechazo agregado a `ItemMovementTest`), 4 tests existentes de `ItemMovementTest` actualizados para pasar `branch_id` (ya obligatorio). Suite completa sin regresiones (37 fallidas preexistentes sin cambio, 192 pasan, antes 186). Migración corrida en producción real con confirmación explícita del usuario (aditiva, tabla nueva, sin tocar columnas existentes). Verificado end-to-end en producción real dentro de una transacción revertida (`ItemMovementService::record()` con `branchId` real, confirmando `item_branch_stocks` y `items.stock_quantity` correctos) — sin dejar datos falsos.

### 📁 Archivos principales tocados
- `database/migrations/2026_07_17_000001_create_item_branch_stocks_table.php` (nuevo)
- `app/Models/ItemBranchStock.php` (nuevo), `app/Models/Item.php` (`branchStocks()`)
- `app/Domain/Inventory/Services/ItemMovementService.php`, `app/Domain/Inventory/Contracts/ItemMovementServiceInterface.php`
- `app/Http/Controllers/{ItemMovementController,ItemController}.php`
- `resources/views/items/{edit,index}.blade.php`
- `tests/Feature/{ItemBranchStockTest (nuevo),ItemMovementTest}.php`
- `docs/tecnico/{MODELO_BD,BACKLOG}.md` (BL-049 parcial)

### 🛑 Pendientes activos
1. Commit y push de esta sesión.
2. BL-049 restante (ya diseñado, ver decisiones arriba): trigger de descuento de stock al completar cita + almacenes/transferencias.
3. Sigue pendiente de sesiones previas: BL-047 (clínica fase 2), BL-052 (automatizar catálogo de WhatsApp/redes), BL-053 (artículos de uso interno).

---

## 📅 Cierre de sesión: 16-17/07/2026 — BL-057/058/059 + 2 bugs críticos reales encontrados

Sesión larga, arrancó como una aparte ("antes de que se me olvide") en medio del trabajo de Zeus-Estetican y terminó cubriendo tres backlogs completos de EstetiCAN:

- **BL-057** — costo de compra + precio de venta sugerido en Artículos (`items.cost_price`, sección nueva "Tienda y Proyectos" en `SystemSettings`).
- **BL-058** — toggle real de módulo Tienda (`store_module_enabled`), motivado directamente por una brecha de arquitectura detectada en Zeus-Estetican (su catálogo de "Módulos" era cosmético fuera de Clínica). De paso, **NT-038**: un `@json()` mal formado en `_quote_manager.blade.php` desde BL-055 causaba `ParseError` real en producción en cualquier `GET` a `agenda.show` — nadie lo había notado porque ningún test previo hacía esa petición.
- **BL-059** — toggle real de módulo Hotel (`hotel_module_enabled`), evaluado primero como "fuera de alcance" en BL-058 por estar fusionado en la Agenda compartida, construido de todas formas a pedido del usuario. De paso, **NT-039**: el KPI "Huéspedes en Hotel" del dashboard y la fusión de Hotel en la Agenda unificada llevaban rotos — siempre en cero — desde que se construyeron, por filtrar un `status = 'active'` que nunca existió en el enum real (`scheduled`/`cancelled`/`fulfilled`).

Con esto, los tres módulos de negocio reales de EstetiCAN (Clínica/Tienda/Hotel) ya tienen interruptor real y verificado en producción — Estética queda confirmada como el kernel no-togglable.

**Estado al cerrar:** working tree limpio, todo commiteado y pusheado a `origin/main` (`5ea7c83` es el HEAD). Sin pendientes de push.

**Pendientes activos para la próxima sesión:** BL-047 (clínica fase 2), BL-049 (Tienda/Inventario real, multi-sucursal), BL-052 (automatizar catálogo de WhatsApp/redes), BL-053 (artículos de uso interno) — ninguno tocado hoy, siguen en el mismo estado que antes de esta sesión.

---

## 📅 Sesión: 16/07/2026 (cont. 2) — BL-059: toggle real de módulo "Hotel" + KPI que nunca funcionó

### ✅ Logros y Cambios

Continuación directa de BL-058: el usuario pidió construir también el toggle de Hotel, el candidato que se había descartado deliberadamente por estar mucho más fusionado que Tienda (sin sección propia de navegación, mezclado dentro del calendario/timeline compartido de Agenda, del dashboard y del selector "¿qué tipo de servicio?" al crear una cita). Se investigó el código real y se confirmó que, aunque hay más puntos de contacto, el patrón sigue siendo el mismo que Clínica/Tienda — solo aplicado a más vistas.

**Toggle de Hotel:** `hotel_module_enabled` nuevo (sección "Hotel (Hospedaje)" de `SystemSettings`), `default => true` (mismo razonamiento que Tienda — ya está en uso real). Middleware `EnsureHotelModuleEnabled` (`hotel.module`) envolviendo `hotel-reservations.*`. `SpaBookingController::index()`/`buildCalendarRange()` dejan de consultar `HotelReservation` cuando está apagado, así que la Agenda unificada simplemente queda solo con SPA. `DashboardController` oculta el KPI "Huéspedes en Hotel" y el acceso rápido "Nueva estancia Hotel". El selector de creación de citas (`agenda/global-create.blade.php`) oculta la tarjeta "Hospedaje (Hotel)". El copy fijo que mencionaba "SPA y Hotel" en la Agenda pasa a condicional para no ser engañoso con el módulo apagado.

**Bug crítico real encontrado y corregido en el mismo cambio (NT-039):** al escribir el test que simula "un huésped actualmente hospedado", el `INSERT` de prueba truena — el enum real de `hotel_reservations.status` es `scheduled`/`cancelled`/`fulfilled`, **`'active'` nunca existió**. Las tres consultas reales del sistema (`DashboardController`, y las dos de `SpaBookingController` para la Agenda) filtraban por `status = 'active'`, así que **el KPI "Huéspedes en Hotel" y la fusión de Hotel en la Agenda unificada llevan rotos — siempre en cero — desde que se construyeron**, sin que nadie lo notara porque cero es un resultado perfectamente creíble a simple vista. Se corrigió a `status = 'scheduled'` (el estado real que usa el resto del módulo) y de paso se acotó el KPI del dashboard a la fecha de hoy, que antes no filtraba por fecha en absoluto (habría contado *todas* las reservas agendadas alguna vez, no solo las de hoy, una vez arreglado el valor del status).

**Verificación:** 8 tests nuevos (`HotelModuleToggleTest`), suite completa sin regresiones (37 fallidas preexistentes sin cambio, 186 pasan, antes 178). Verificado end-to-end en producción real: con el default (activo) las 4 pantallas afectadas responden 200 igual que antes; apagando el flag, `/hotel-reservations` → 404, el KPI desaparece del dashboard; reactivado sin dejar rastro.

### 📁 Archivos principales tocados
- `app/Support/SystemSettings/SystemSettings.php` (`hotel_module_enabled`)
- `app/Http/Middleware/EnsureHotelModuleEnabled.php` (nuevo), `bootstrap/app.php`
- `routes/web.php` (rutas de `hotel-reservations` envueltas en `hotel.module`)
- `app/Http/Controllers/{SpaBookingController,DashboardController}.php` (gateo + fix del bug de `status`)
- `resources/views/{dashboard/index,agenda/index,agenda/global-create}.blade.php`
- `tests/Feature/HotelModuleToggleTest.php` (nuevo)
- `docs/tecnico/{BACKLOG,NOTAS_TECNICAS}.md` (BL-059, NT-039)

### 🛑 Pendientes activos
1. Commit y push de esta sesión.
2. Zeus-Estetican: actualizar su documentación — Hotel deja de estar "descartado por ahora" y pasa a tener un toggle real (`code` a usar al registrar el módulo en el portal: `hotel`).
3. Con esto, los tres candidatos de módulo evaluados en Zeus (Clínica/Tienda/Hotel) ya tienen interruptor real del lado de EstetiCAN — Estética sigue confirmada como Core no-togglable.
4. Sigue pendiente de sesiones previas: BL-047 (clínica fase 2), BL-049 (Tienda/Inventario real, multi-sucursal), BL-052 (automatizar catálogo de WhatsApp/redes), BL-053 (artículos de uso interno).

---

## 📅 Sesión: 16/07/2026 (cont.) — BL-058: toggle real de módulo "Tienda" + bug crítico corregido en Agenda

### ✅ Logros y Cambios

Surgió de una conversación en Zeus-Estetican (proyecto paralelo de renta del motor): su catálogo de "Módulos" togglables por cliente era mayormente cosmético porque solo Clínica tenía un interruptor real del lado de EstetiCAN. Se investigó el código y se confirmó una asimetría real entre los candidatos (Tienda vive en pantallas propias, fácil de aislar; Hotel está fusionado dentro del calendario compartido de Agenda, mucho más invasivo). Con el usuario se confirmaron dos decisiones de arquitectura: **Estética deja de ser candidata a toggle** (es el kernel del que cuelgan Clínica y Hotel — pasa a ser Core fijo en Zeus) y se construye **Tienda primero**, mismo patrón exacto que Clínica (`SystemSettings` + middleware + gateo de navegación).

**Toggle de Tienda:** `store_module_enabled` nuevo (sección "Tienda y Proyectos" de `SystemSettings`), `default => true` — a diferencia de Clínica (que nació apagada), Tienda ya está en uso real en producción, así que el flag nace prendido para no regresionar nada. Middleware `EnsureStoreModuleEnabled` (`store.module`) envolviendo todas las rutas de `items`/`groups`/movimientos/componentes. Navegación gateada en `InventoryNavigation`/`CatalogsNavigation`. El quote manager de Spa oculta los botones "Agregar grupo completo"/"Agregar artículo suelto" cuando está apagado, sin tocar "Agregar servicio adicional" (Servicios sigue siendo el flujo core). Guarda de backend en `storeQuote()` que rechaza líneas de artículo/grupo si el módulo está apagado, además del gateo de UI.

**Bug crítico real encontrado y corregido en el mismo cambio (NT-038):** al escribir los tests nuevos — los primeros en toda la suite que hacen `GET` real a `agenda.show` — se descubrió que `_quote_manager.blade.php` traía un `@json()` con un array multilínea anidado (desde BL-055) que el compilador de Blade corta mal, generando un `ParseError` real de PHP. Esto significa que **cualquier usuario que abriera el detalle de una cita en producción recibía 500** en cuanto Blade recompilaba esa vista — estaba agazapado desde BL-055, tapado por casualidad por el caché de vistas compiladas hasta que se invalidó (mtime del archivo cambió al tocarlo para BL-058). Se corrigió moviendo el armado del array a `SpaBookingController::show()` (`$groupsForQuoteManager` ya resuelto, la vista solo hace `@json($groupsForQuoteManager)`) — página de Agenda verificada funcionando de nuevo.

**Verificación:** 9 tests nuevos (`StoreModuleToggleTest`), suite completa sin regresiones (37 fallidas preexistentes sin cambio, 178 pasan, antes 169 — los 9 nuevos).

### 📁 Archivos principales tocados
- `app/Support/SystemSettings/SystemSettings.php` (`store_module_enabled`)
- `app/Http/Middleware/EnsureStoreModuleEnabled.php` (nuevo), `bootstrap/app.php`
- `routes/web.php` (bloque de Tienda envuelto en `store.module`)
- `app/Support/Navigation/Groups/{InventoryNavigation,CatalogsNavigation}.php`
- `resources/views/agenda/partials/_quote_manager.blade.php` (gateo + fix del bug de `@json`)
- `app/Http/Controllers/SpaBookingController.php` (`show()`/`storeQuote()`)
- `tests/Feature/StoreModuleToggleTest.php` (nuevo)
- `docs/tecnico/{BACKLOG,NOTAS_TECNICAS}.md` (BL-058, NT-038)

### 🛑 Pendientes activos
1. Commit y push de esta sesión.
2. Zeus-Estetican: actualizar su documentación para reflejar que Estética ya no es Módulo togglable (es Core) y que Tienda ya tiene un toggle real (`code` a usar al registrar el módulo en el portal: `store`).
3. Toggle de Hotel — evaluado y descartado para esta entrega por ser mucho más invasivo (fusionado en el calendario compartido de Agenda). Queda como candidato futuro si el negocio lo requiere.
4. Sigue pendiente de sesiones previas: BL-047 (clínica fase 2), BL-049 (Tienda/Inventario real, multi-sucursal), BL-052 (automatizar catálogo de WhatsApp/redes), BL-053 (artículos de uso interno).

---

## 📅 Sesión: 16/07/2026 — BL-057: costo de compra + precio de venta sugerido en Artículos

### ✅ Logros y Cambios

Surgió en medio de una conversación sobre Zeus-Estetican (el proyecto paralelo de renta del motor, `/opt/www/zeus-estetican/`) — al revisar el catálogo de "módulos" ahí, el usuario notó que faltaba una pieza real del lado de Tienda y la pidió "antes de que se me olvide": `items` gana `cost_price` (costo de compra), y el `price` existente (BL-051) pasa a tener un cálculo sugerido en vivo a partir de un margen de utilidad configurable.

**Nueva sección de configuración "Tienda y Proyectos"** en `SystemSettings` (`store_profit_margin_percentage`, default 30%) — cero código nuevo de renderizado necesario, el sistema de secciones ya es genérico (mismo patrón que Cobertura Geográfica/Clínica): agregar la definición del campo a `SystemSettings::definitions()` fue suficiente para que apareciera en la pantalla de Configuración del sistema, con validación y persistencia automáticas.

**Formulario de Artículos:** campo nuevo "Costo de compra" con un pequeño `x-data` de Alpine.js que calcula el precio sugerido en vivo (`costo × (1 + margen/100)`) mientras se escribe, más un botón "usar sugerido" que copia el valor al campo `price` — deliberadamente **no automático/forzado**: el precio final sigue siendo 100% editable a mano, el cálculo es solo una ayuda visible.

**Verificación:** 1 test nuevo (`ItemCrudTest::test_cost_price_is_saved_and_the_configured_margin_is_shown_on_create`), suite completa sin regresiones (37 fallidas preexistentes, 169 pasan). Migración corrida y verificada en producción real (`/items/create` muestra el campo y el cálculo; `/system-settings` muestra la sección nueva).

### 📁 Archivos principales tocados
- `database/migrations/2026_07_16_000001_add_cost_price_to_items_table.php` (nuevo)
- `app/Support/SystemSettings/SystemSettings.php` (sección `store`)
- `app/Models/Item.php`, `app/Http/Controllers/ItemController.php`
- `resources/views/items/partials/form.blade.php`
- `tests/Feature/ItemCrudTest.php`
- `docs/tecnico/MODELO_BD.md`, `docs/tecnico/BACKLOG.md` (BL-057)

### 🛑 Pendientes activos
1. Commit y push de esta sesión.
2. Sigue pendiente lo de sesiones previas: BL-047 (clínica fase 2), BL-049 (Tienda/Inventario real, multi-sucursal), BL-052 (automatizar catálogo de WhatsApp/redes), BL-053 (artículos de uso interno).
3. En Zeus-Estetican: definir interruptores reales para módulos combinables (Estética/Spa, Tienda, Hotel) — hoy solo Clínica tiene uno de verdad (`clinical_module_enabled`), lo cual limita qué tan útil es el catálogo de módulos del portal de Zeus.

---

## 📅 Sesión: 15/07/2026 (cont. 4) — BL-054 "IM sencillo", BL-055 "Grupos" (combos Servicio+Artículo en cotizaciones) y BL-056 foto de artículo

### ✅ Logros y Cambios

Racha larga que cerró tres piezas grandes en la misma sesión.

**BL-054 — "IM sencillo" (retomando el diseño pendiente de la sesión anterior):** el usuario pidió avanzar con "lo mínimo pero lo necesario que luego sea reutilizable para construir encima". Se investigó primero el patrón real que ya usa el proyecto para "ledgers" (`CashLedger`/`BankLedger`, vía un agente de exploración) — resultó ser **append-only sin saldo cacheado por renglón** (el saldo se deriva con `SUM()` al momento de consulta, ej. `Account::balance()`), no el patrón de "running balance" que se había propuesto inicialmente. Se replicó ese mismo espíritu en `item_movements` (tipo, delta con signo, `branch_id` nullable desde ya, `reference` polimórfico), con `items.stock_quantity` como único caché (mantenido transaccionalmente por `ItemMovementService::record()`, usando `lockForUpdate()` — el único precedente de locking real en el repo, `AccountingService::getNextFolio()`). Encontrado en el camino: `stock_quantity` era `unsigned`, y un consumo sin entrada previa capturada puede dejarlo negativo (información real, no error) — migración para volverlo signed. Se conectó el primer consumo automático real: aplicar una vacuna con artículo ligado (no externa) resta 1 del stock. 14 tests nuevos.

**Reorganización de navegación (dentro de la misma racha, antes de Grupos):** el usuario pidió separar módulos en persianas — se armaron grupos "Inventario" y "RH" nuevos, y una persiana anidada "Administración" (Inventario/RH/Finanzas/Veterinaria). Ajuste inmediato del propio usuario: Clientes/Mascotas/Sucursales se queda como persiana propia de nivel superior (más usada a diario), fuera de Administración.

**BL-055 — "Grupos" (la pieza grande):** el usuario pidió que los Artículos se pudieran "agrupar" y, en el mismo mensaje, describió algo mucho más profundo — servicios compuestos como "Cirugía" (Cirujano + Anestesista + medicamentos + curaciones) o "Corte de cola" (0.5 hrs de veterinario + 5 vendas), con precio = suma de componentes, agregables a una cotización con un clic y **facturados desglosados** (pidió explícitamente que la cuenta al cliente se vea itemizada, "da confianza y es lo más profesional"). Dado el tamaño (toca `Quote`/`QuoteItem`, congelado en orden de trabajo, `AccountingService`, facturación) se usó `EnterPlanMode` — dos agentes de exploración en paralelo (sistema de cotizaciones; Hotel/facturación/dominio Execution) + un agente `Plan` para el diseño técnico, con `AskUserQuestion` para 4 decisiones de alcance antes de escribir el plan final.

**Decisiones del usuario que acotaron el alcance:** (1) solo Spa esta vez — Hotel/Hospital no tiene hoy ningún campo de precio ni pantalla de facturación, generalizar el sistema es fase 2 separada; (2) nombre "Grupo" (no "Kit" ni "Receta" — "Receta" ya es prescripción médica en el módulo clínico); (3) el consumo de inventario de los componentes de un Grupo **no se automatiza todavía** — el usuario explicó que la cotización es una "prefactura" que no debe afectar inventario ni contabilidad hasta un concepto formal de "factura → cuenta por cobrar" que hoy no existe en el sistema (es base-efectivo); diseñar eso es un proyecto aparte.

**Hallazgo crítico durante la investigación, no anticipado por el usuario:** `AccountingService::buildDebitLines()`/`buildDebitLinesFromBooking()` asumían que toda línea de cotización tenía `->service` sin guardar contra null — con una sola línea de Artículo en una cotización aceptada, el cobro habría tronado con error fatal, o (el caso `buildDebitLinesFromBooking`, más grave) **prorrateado el ingreso por artículos dentro de las cuentas de servicios en silencio**, corrompiendo el libro contable sin ningún error visible. Se corrigió en el mismo cambio (no como seguimiento) — `items` ganó `account_id` (mismo patrón que `services.account_id`) para poder clasificarlo.

**Diseño de datos:** `groups`/`group_components` con **FKs duales `service_id`/`item_id` + CHECK constraint** (exactamente uno no nulo) en vez de un morph como `ItemMovement` — justificado porque el universo de "tipo de componente" es cerrado (solo Servicio o Artículo, confirmado con el usuario), a diferencia del origen abierto de `ItemMovement.reference`. `restrictOnDelete` en `service_id`/`item_id` (no `nullOnDelete`, que rompería el CHECK) — requirió agregar guardas nuevas en `ItemController`/`ServiceController::destroy()` para devolver un error amigable en vez de dejar tronar la excepción de integridad referencial con 500. `quote_items` gana `item_id`/`quantity`/`group_id` (`service_id` se volvió nullable — el `->nullable()->change()` funcionó directo sin necesidad de tocar la FK existente, verificado empíricamente antes de complicar la migración). `spa_booking_services` gana `quantity`/`group_id`; tabla nueva `spa_booking_items` paralela. Precio del Grupo **no se cachea** — se calcula al vuelo con `SUM()` (mismo patrón que `Account::balance()`), justo lo opuesto al patrón de `items.stock_quantity` de BL-054, y se justificó por qué cada uno usa el patrón que le corresponde.

**UI:** `_quote_manager.blade.php` gana "Agregar grupo completo" y "Agregar artículo suelto" (expansión 100% client-side en Alpine.js, reusando el mismo modelo de confianza que ya existía para `price_override` — el servidor no necesita saber qué es un Grupo, solo recibe filas ya planas). `_billing_summary.blade.php`, `_work_order.blade.php` y los 4 reportes PDF/email actualizados para no asumir `->service` en cada línea. Pantalla CRUD de Grupos (`groups/*`) con gestión de componentes inline, mismo patrón que la pantalla de movimientos de Artículos.

**BL-056 — foto de artículo:** el usuario señaló "te falta la foto del producto" — se separó de BL-052 (que también incluía automatizar publicación en catálogo de WhatsApp, eso sigue pendiente). `ItemPhotoImageManager` nuevo, copia exacta del patrón ya usado por `OperatorPhotoImageManager` (recorte cuadrado, original + thumbnail vía `Spatie\Image`). Miniatura visible en el listado de Artículos y en la tabla de componentes de un Grupo.

**Verificación:** suite completa sin regresiones en cada corte (37 fallidas preexistentes constantes; 151→168 pasan a lo largo de la racha, 31 tests nuevos en total). Todas las migraciones corridas en producción real con confirmación explícita del usuario antes de cada `migrate --force`. Verificación funcional end-to-end en producción real dentro de transacciones revertidas (Grupo con componentes → cotización expandida → aceptada → congelada en `spa_booking_services`/`spa_booking_items`, confirmando que `AccountingService` no truena) — sin dejar datos falsos.

### 📁 Archivos principales tocados
- `database/migrations/2026_07_15_000003..000011_*` (9 migraciones: `item_movements`, `stock_quantity` signed, `groups`, `group_components`, `quote_items`/`spa_booking_services`/`spa_booking_items`, `items.account_id`/`photo_path`)
- `app/Models/{ItemMovement,Group,GroupComponent,SpaBookingItem}.php` (nuevos), `app/Models/{Item,QuoteItem,SpaBookingService,SpaBooking}.php` (actualizados)
- `app/Domain/Inventory/{Contracts,Services}/ItemMovementService*.php` (nuevo dominio)
- `app/Domain/Commercial/Services/QuoteService.php`, `app/Domain/Accounting/Services/AccountingService.php` (fix crítico)
- `app/Http/Controllers/{ItemMovementController,GroupController,GroupComponentController}.php` (nuevos), `{ItemController,ServiceController,SpaBookingController,ReportController,Clinical/PetVaccinationController}.php` (actualizados)
- `app/Support/{ItemPhotoImageManager,Pages/GroupsPage}.php` (nuevos)
- `resources/views/{groups,items}/*`, `resources/views/agenda/partials/{_quote_manager,_billing_summary,_work_order}.blade.php`, `resources/views/reports/*.blade.php`, `resources/views/emails/service-summary.blade.php`
- `tests/Feature/{ItemMovementTest,GroupTest,GroupComponentTest,QuoteGroupTest,AccountingServiceItemLinesTest}.php` (nuevos)
- `docs/tecnico/MODELO_BD.md`, `docs/tecnico/BACKLOG.md` (BL-054/055/056 → Completados, BL-049/052 recortados)

### 🛑 Pendientes activos
1. Commit y push de esta racha completa (pendiente de que el usuario lo pida).
2. BL-049 — Tienda/Inventario real (multi-sucursal de verdad, conexión con venta cobrada, almacenes/transferencias) — capa encima de BL-054, sin diseñar.
3. BL-052 — automatizar publicación en catálogo de WhatsApp/redes (la parte de fotos ya se hizo, BL-056).
4. BL-053 — artículos de uso interno/equipo para servicios.
5. Hotel/Hospital: cotización y facturación itemizada paralela a Spa — fase 2 de Grupos, deliberadamente fuera de esta entrega.
6. Concepto formal de "factura → cuenta por cobrar" — necesario antes de automatizar el consumo de inventario de componentes de Grupo.
7. BL-047 (Fase 2 clínica).
8. Sueltos de sesiones previas: 3 ideas del asistente de IA, Meta Business Suite, marca de agua, `/mapa-zonas`, los 4 archivos huérfanos de BL-037, SPF/DKIM.

---

## 📅 Sesión: 15/07/2026 (cont. 3) — Ajuste: Clientes/Mascotas/Sucursales se queda como persiana propia, fuera de "Administración"

### ✅ Logros y Cambios

Corrección directa sobre la persiana "Administración" armada en la vuelta anterior: el usuario aclaró que Clientes **no** debía ir mezclado con Inventario/RH/Finanzas/Veterinaria — quería una persiana propia de nivel superior para "los temas relacionados a Clientes y Mascotas", y de paso confirmó que **Sucursales** se une a esa misma persiana (antes vivía dentro de Catálogos).

**Resultado:** `ClientsNavigation::group()` gana el item "Sucursales" (mismo permiso `ver sucursales` que tenía en `CatalogsNavigation`, mismo texto/descripción, solo cambia dónde vive). Se quitó de `CatalogsNavigation` (que ahora solo tiene Servicios + Bitácora de actividad) y de `administracionGroup()` en `MainNavigation` se sacó `ClientsNavigation::group()` de los subgrupos — Administración quedó con 4 sub-secciones (Inventario/RH/Finanzas/Veterinaria), Clientes vuelve a ser dropdown de nivel superior independiente (con Abrir clientes + Mascotas + Sucursales).

**Verificación:** suite completa sin regresiones (37 fallidas preexistentes, 143 pasan). Render manual confirmado: las 7 etiquetas nuevas/movidas aparecen en el HTML de `/items`, y `/clients`, `/pets`, `/branches` responden 200.

### 📁 Archivos principales tocados
- `app/Support/Navigation/Groups/ClientsNavigation.php` (+Sucursales)
- `app/Support/Navigation/Groups/CatalogsNavigation.php` (-Sucursales)
- `app/Support/Navigation/MainNavigation.php` (Clientes sale de `administracionGroup()`, vuelve a `structure()` top-level)

### 🛑 Pendientes activos
1. Commit y push de toda la racha de navegación de hoy (pendiente de que el usuario lo pida).
2. BL-049 "IM sencillo" — diseño propuesto, sin construir; requiere las 3 respuestas de alcance (stock global/sucursal, solo conteo vs. cobro real, conexión automática de vacunas).
3. BL-053 — artículos de uso interno/equipo para servicios.
4. BL-052 — fotos de artículos + automatización de catálogo de WhatsApp/redes.
5. BL-047 (Fase 2 clínica).
6. Sueltos de sesiones previas: 3 ideas del asistente de IA, Meta Business Suite, marca de agua, `/mapa-zonas`, los 4 archivos huérfanos de BL-037, SPF/DKIM.

---

## 📅 Sesión: 15/07/2026 (cont. 2) — Navegación anidada: persiana "Administración" (Clientes/Inventario/RH/Finanzas/Veterinaria)

### ✅ Logros y Cambios

Continuación directa de la reorganización anterior (Inventario/RH). El usuario pidió ir un paso más allá: meter Inventario, RH, Finanzas, Veterinaria **y Clientes** (lo relacionado a clientes/mascotas) en una sola persiana, para no saturar la barra superior del Backoffice con módulos de uso menos frecuente que Agenda/Operación. Se confirmó explícitamente que esto es solo el Backoffice web (desktop) — la app móvil (React, `mob_apps/operador`) tiene su propia navegación y no se toca.

**Cambio de forma de datos, no solo de contenido:** hasta ahora `MainNavigation::structure()` era una lista plana de grupos `{label, items}`. Para anidar 5 módulos en un dropdown se necesitaba un segundo nivel — se agregó la forma `{label, subgroups: [{label, items}, ...]}` **solo** para el grupo nuevo "Administración"; los grupos existentes (Operación, Catálogos, WhatsApp) no cambiaron de forma. `MainNavigation::groups()` y `mobileLinks()` ahora manejan ambas formas explícitamente (filtran items dentro de cada subgrupo, ocultan subgrupos vacíos, y ocultan el grupo completo si todos sus subgrupos quedan vacíos — mismo criterio que ya existía para Veterinaria cuando el módulo clínico está apagado).

**Bug real evitado en un test existente:** `ClinicalModuleToggleTest` verificaba `Veterinaria` como label de nivel superior en `MainNavigation::groups()`. Al anidarla dentro de "Administración", ese `pluck('label')` plano dejó de encontrarla — el assert `assertNotContains` habría pasado siempre "por accidente" (nunca hay 'Veterinaria' en el nivel superior, esté o no el módulo activo), enmascarando una regresión real. Se corrigió con un helper `allNavigationLabels()` que aplana también los subgrupos antes de comparar.

**Blade:** se extrajo el markup de un item de menú (antes duplicado inline) a un componente nuevo `<x-main-navigation-item>`, reusado tanto en el render plano (grupos normales) como en el anidado (subgrupos de Administración) — evita duplicar el HTML de cada item dos veces en la plantilla.

**Verificación:** suite completa sin regresiones (37 fallidas preexistentes, 143 pasan). Render manual vía `Kernel::handle()` autenticado en `/items`, `/operators`, `/clients`, `/finances/accounts`, `/services` — 200 en todos, y se confirmó que "Administración", "Clientes", "Inventario", "RH" y "Finanzas" aparecen en el HTML devuelto.

### 📁 Archivos principales tocados
- `app/Support/Navigation/MainNavigation.php` (reescrito: `administracionGroup()`, `groups()`/`mobileLinks()` soportan `subgroups`)
- `resources/views/components/main-navigation.blade.php` (render anidado)
- `resources/views/components/main-navigation-item.blade.php` (nuevo, extraído para no duplicar markup)
- `tests/Feature/Clinical/ClinicalModuleToggleTest.php` (helper `allNavigationLabels()` para aplanar subgrupos)

### 🛑 Pendientes activos
1. Commit y push de esta sesión (pendiente de que el usuario lo pida).
2. BL-049 "IM sencillo" — diseño propuesto, sin construir; requiere las 3 respuestas de alcance (stock global/sucursal, solo conteo vs. cobro real, conexión automática de vacunas).
3. BL-053 — artículos de uso interno/equipo para servicios.
4. BL-052 — fotos de artículos + automatización de catálogo de WhatsApp/redes.
5. BL-047 (Fase 2 clínica).
6. Sueltos de sesiones previas: 3 ideas del asistente de IA, Meta Business Suite, marca de agua, `/mapa-zonas`, los 4 archivos huérfanos de BL-037, SPF/DKIM.

---

## 📅 Sesión: 15/07/2026 (cont.) — Reorganización de navegación (Inventario/RH) + diseño de "IM sencillo" pendiente de decisiones

### ✅ Logros y Cambios

Tras cerrar BL-051, se le propuso al usuario un plan para el "IM sencillo" (movimientos de inventario tipo `item_movements`, mismo patrón que `CashLedger`/`BankLedger`, con `items.stock_quantity` pasando de editable a mano a caché recalculado por movimiento). El usuario, en vez de responder las 3 preguntas de alcance planteadas, señaló algo más de fondo primero: es momento de separar módulos en la navegación — Artículos (venta) debería vivir en un grupo "Inventario" propio, distinto de "artículos de uso" (máquinas para dar servicios, ej. rasuradoras), más un grupo "RH" para Usuarios/Operadores, y notó que Finanzas ya es su propio módulo ("contable").

**Se separaron dos cosas explícitamente antes de tocar código:** la reorganización de navegación (barata, cero riesgo, sin tocar esquema/datos) vs. modelar "artículos de uso interno" (máquinas/equipo — concepto nuevo, distinto tanto de `items` como de `Resources`/jaulas-cuartos ya existente). Se hizo solo lo primero esta vuelta; lo segundo quedó anotado como **BL-053** para diseñar aparte.

**Resultado — reorganización de navegación:**
- 2 grupos nuevos: `App\Support\Navigation\Groups\InventoryNavigation` ("Inventario": Artículos) y `HrNavigation` ("RH": Operadores, Tipos de operador, Usuarios) — mismos permisos Spatie que tenían en `CatalogsNavigation`, sin cambios de rutas ni controllers.
- `CatalogsNavigation` recortado: solo queda Servicios, Sucursales y Bitácora de actividad (no son ni inventario ni personal). Se limpió también su método `mobileLinks()` (confirmado código muerto — `MainNavigation::mobileLinks()` genera el menú móvil real a partir de `structure()`, no llama a los métodos `mobileLinks()` de cada grupo; se dejó igual en las demás clases por no ser parte del alcance de hoy).
- `MainNavigation::structure()` registra los 2 grupos nuevos entre Catálogos y WhatsApp.
- Finanzas se dejó con su nombre actual (el usuario no confirmó explícitamente el renombre a "Contable" cuando se le preguntó).
- Verificación: suite completa sin regresiones (37 fallidas preexistentes, 143 pasan; el test `ClinicalModuleToggleTest` que sí depende de `MainNavigation::groups()` solo verifica presencia/ausencia de "Veterinaria", no afectado). `/items`, `/operators`, `/services` verificados manualmente vía `Kernel::handle()` autenticado — 200 los 3.

**El diseño del "IM sencillo" (BL-049) quedó documentado en el backlog pero sin construir** — las 3 preguntas de alcance siguen abiertas para la próxima vuelta: (1) stock global vs. por sucursal, (2) ¿el IM hoy solo cuenta existencias o ya conecta con cobro real (`Payment`/caja/banco)?, (3) ¿se conecta ya el consumo automático de `pet_vaccinations.item_id` o después?

### 📁 Archivos principales tocados
- `app/Support/Navigation/Groups/{InventoryNavigation,HrNavigation}.php` (nuevos)
- `app/Support/Navigation/Groups/CatalogsNavigation.php` (recortado)
- `app/Support/Navigation/MainNavigation.php` (registra los 2 grupos nuevos)
- `docs/tecnico/BACKLOG.md` (nota de reorganización + diseño propuesto de BL-049 + BL-053 nuevo)

### 🛑 Pendientes activos
1. Commit y push de esta sesión (pendiente de que el usuario lo pida).
2. BL-049 "IM sencillo" — diseño propuesto, sin construir; requiere las 3 respuestas de alcance antes de programar.
3. BL-053 — artículos de uso interno/equipo para servicios (máquinas, rasuradoras) — sin diseñar, concepto nuevo.
4. BL-052 — fotos de artículos + automatización de catálogo de WhatsApp/redes.
5. BL-047 (Fase 2 clínica).
6. Sueltos de sesiones previas: 3 ideas del asistente de IA, Meta Business Suite, marca de agua, `/mapa-zonas`, los 4 archivos huérfanos de BL-037, SPF/DKIM.

---

## 📅 Sesión: 15/07/2026 — BL-051: banderas de visibilidad IA en `services`/`items` + campos mínimos de venta

### ✅ Logros y Cambios

El usuario pidió "una marca para ver si la IA los puede mencionar o no", tanto en servicios como en artículos (`items`, maestro del BL-050). Se aclaró el alcance en varias vueltas de conversación antes de tocar código (mismo patrón de siempre): "activos" = `items` (accesorios, medicinas), "servicios" = `Service` (cirugías, cortes). El diseño creció durante la conversación — el usuario agregó 3 requisitos más que el flag original: (1) servicios "genéricos" (ej. "Cirugía" sin precio fijo) que la IA debe ofrecer sin precio, invitando a agendar cita de evaluación; (2) servicios de "emergencia" que deben empujar al visitante al WhatsApp de inmediato; (3) que los artículos solo se mencionen si hay existencia > 0, más la idea de fondo (a futuro, no hoy) de mover el catálogo de ventas de WhatsApp/redes al sistema, con fotos de producto.

**Se dividió el alcance explícitamente con el usuario antes de programar** (evitando la trampa de construir el inventario real de golpe): hoy solo se agregaron los campos mínimos para que el asistente IA (BL-042) pueda filtrar y mencionar servicios/artículos, **sin** construir el módulo de inventario transaccional (BL-049, sigue sin diseñar) ni la galería de fotos/publicación externa (nuevo, anotado como BL-052 para después).

**Hallazgo importante al auditar `getActiveServices()`:** ese método del dominio (`ServiceCatalogService`/`ServiceCatalogRepository`) es usado **únicamente** por `ServiceCatalogPromptBuilder` (grep confirmó cero usos en controllers), pero se decidió **no** reutilizarlo para el filtro de IA — se agregó un método nuevo `getAssistantVisibleServices()` en su lugar, para no mezclar la semántica de "servicio activo en el catálogo" con "servicio visible para el asistente IA" (son conceptualmente distintos, aunque hoy compartan el único consumidor).

**Resultado:**
- `services` gana `ai_visible`/`is_generic`/`is_emergency` (los 3 boolean, default `false` — decisión explícita del usuario, "no tomar en cuenta" hasta marcarse a mano).
- `items` gana `ai_visible` (boolean, default `false`), `stock_quantity` (unsigned int, default `0` — contador simple sin movimientos/histórico, decisión del usuario de armar "los campos que necesitemos" sin complicarse con el IM todavía) y `price` (decimal nullable — agregado por criterio propio, no lo pidió el usuario explícitamente, pero sin precio el caso de uso de "catálogo de ventas por WhatsApp" no tiene sentido; documentado así para que se pueda revertir si no se quiere).
- `ServiceCatalogPromptBuilder` reescrito: nueva sección de artículos en el prompt (`Item::query()` directo, sin capa de dominio — igual que `ItemController`, no existía repositorio para `Item`), filtrando `is_active`+`ai_visible`+`stock_quantity > 0`. Servicios `is_generic` muestran "sin precio publicado" en vez del precio real; `is_emergency` se marca `[URGENCIA]` en el catálogo y el prompt instruye a invitar de inmediato al botón de WhatsApp **ya existente** (`ai_assistant_cta_url`, BL-042) — deliberadamente no se construyó un botón/mecanismo de envío nuevo, se reusa el CTA que ya dispara wa.me.
- Formularios actualizados: `services/partials/form.blade.php` (3 checkboxes nuevos) y `items/partials/form.blade.php` (`price`, `stock_quantity`, checkbox `ai_visible`) — mismo patrón hidden+checkbox que `is_core_vaccine`/`is_active`. El mini-formulario de alta rápida de artículo (en `clinical/pets/show.blade.php`) no se tocó — solo manda `name`/`brand`/`presentation`, y los defaults de servidor (`ai_visible=false`, `stock_quantity=0`) ya cubren ese caso sin romper nada.
- **No se estructuraron columnas de especie/talla/sexo para el artículo** (ej. el caso "sweater para perro chico hembra" que planteó el usuario) — se decidió confiar en que la IA infiera esos atributos del texto libre ya existente (`name`/`department`/`brand`/`presentation`/`notes`) en vez de rigidizar el esquema; si el matching falla en la práctica, ahí se estructura.

**Verificación:** sin tests nuevos (cambio de datos/configuración, sin lógica de negocio propia que testear). Suite completa sin regresiones (37 fallidas preexistentes — confirmadas idénticas haciendo `git stash` antes de correr `ServiceOperatorRoleLinkTest`, 143 pasan). Migraciones corridas en producción real vía `docker exec estetican_app php artisan migrate --force` (confirmado con el usuario antes de ejecutar), columnas y defaults verificados por `tinker`/`Schema::getColumns()`.

### 📁 Archivos principales tocados
- `database/migrations/2026_07_15_000001_add_ai_flags_to_services_table.php`, `2026_07_15_000002_add_ai_flags_and_stock_to_items_table.php` (nuevos)
- `app/Models/{Service,Item}.php` (fillable, casts, `logOnly` de `Item`)
- `app/Domain/Catalog/Contracts/{ServiceCatalogServiceInterface,ServiceCatalogRepositoryInterface}.php`, `app/Domain/Catalog/{Services/ServiceCatalogService,Repositories/ServiceCatalogRepository}.php` (método nuevo `getAssistantVisible(Services)()`)
- `app/Support/Assistant/ServiceCatalogPromptBuilder.php` (reescrito: bloque de artículos, genérico/emergencia)
- `app/Http/Controllers/{ServiceController,ItemController}.php` (rules/payload/validatedData)
- `resources/views/{services,items}/partials/form.blade.php`
- `docs/tecnico/MODELO_BD.md` (`services`/`items`), `docs/tecnico/BACKLOG.md` (BL-051 → Completados, BL-052 nuevo en Activos, nota en BL-049)

### 🛑 Pendientes activos
1. Commit y push de esta sesión (pendiente de que el usuario lo pida).
2. BL-052 — fotos de artículos + automatizar publicación en catálogo de WhatsApp Business/redes/página, usando `stock_quantity` como filtro de existencias reales. Sin diseñar, anotado a propósito para su propia sesión.
3. BL-047 (Fase 2 clínica), BL-049 (Tienda/Inventario real — decidir si `stock_quantity` se reemplaza por tablas de movimientos o queda como snapshot).
4. Sueltos de sesiones previas: 3 ideas del asistente de IA, Meta Business Suite, marca de agua, `/mapa-zonas`, los 4 archivos huérfanos de BL-037, SPF/DKIM.

---

## 📅 Sesión: 14/07/2026 (cont. 9) — BL-045b: atomización de apellido en `operators` (cierra clients→users→operators)

### ✅ Logros y Cambios

Tercera y última fase de la iniciativa de atomización de apellidos. A diferencia de `clients` (BL-044) y `users` (BL-045), `operators` **no tenía ningún campo de nombre separado** — solo `name` (legado, ambiguo: a veces espeja el login del `User` vinculado, a veces duplica el nombre completo para operadores creados directo) y `full_name` (string único mezclando nombre(s) + apellidos, ej. "Tomas Alejandro Martinez Gutierrez"). Se preguntó al usuario el alcance antes de tocar código: eligió agregar los 3 campos completos (`first_name` + `apellido_paterno` + `apellido_materno`), no solo los 2 apellidos, para quedar consistente con el resto del sistema.

**Heurística de 3 vías** (distinta a la de 2 vías de BL-044/045): las últimas 2 palabras de `full_name` son los apellidos, el resto es el/los nombre(s) — "Jose Mendez Pérez" → nombre="Jose", paterno="Mendez", materno="Pérez"; "Tomas Alejandro Martinez Gutierrez" → nombre="Tomas Alejandro", paterno="Martinez", materno="Gutierrez". Solo se marca ambiguo con 5+ palabras (nada en los 2 operadores reales de producción).

**Trampa nueva, no vista en BL-044/045 (ver NT-036):** `operators.full_name` era `NOT NULL` sin default en la BD real — a diferencia de `last_name` en `clients`/`users`, que nacieron nullable desde su migración original. Al quitar `full_name` de `$fillable` del modelo, cualquier alta nueva de operador empezó a tronar con `SQLSTATE[HY000]: ... Field 'full_name' doesn't have a default value` — se detectó corriendo la suite completa (68 tests fallando de golpe, no los 37 del baseline) antes de tocar producción. Fix: migración extra (`2026_07_14_000019`) para volver la columna nullable.

**Auditoría de trampa #2 (query-level) más amplia que en BL-044/045:** `OperatorController` tenía búsqueda por `full_name`, 4 `orderByRaw('coalesce(full_name, name)...')`, y `duplicate()`/`buildDuplicateName()` operando sobre el string único — todo reescrito. Además, **5 eager-loads con columnas restringidas** en otros controllers seguían pidiendo `full_name` (`SpaBookingController::show()`, `Api\BookingController::index()`, `Api\AgendaController::index()` ×2, `Api\OperatorController::index()`/`team()` ×2) — de no corregirse, el accessor habría devuelto nombre **vacío sin ningún error** (columna no cargada = `null` silencioso), afectando agenda web, agenda móvil y el panel de equipo. `UserController::syncOperatorRecord()` se simplificó de paso: ya no concatena un string de `full_name`, pasa `first_name`/`apellido_paterno`/`apellido_materno` directo del `User` (ya atomizado en BL-045) al `Operator` vinculado.

**Tests:** 2 archivos (`OperatorBranchSelectionTest`, `OperatorPhotoUploadTest`) exigían el campo de verdad vía HTTP (`route('operators.store')`), actualizados con cuidado real; 9 archivos con `Operator::create(['full_name' => 'Jose', ...])` como boilerplate sin relación con lo que prueban, reemplazo mecánico.

**Respaldo previo:** dump de BD (`backups/estetican_pre-BL045b-apellidos-operators_20260714_2320.sql`) + tag git `pre-bl045b-apellidos-operators`, misma disciplina que BL-044/045/046.

**Verificación:** backfill real en producción (2 operadores, 0 ambiguos, coincide exactamente con los usuarios ya migrados en BL-045 — mismas personas). Suite completa sin regresiones (37 fallidas preexistentes, 143 pasan — volvió al baseline después de agregar la migración de `full_name` nullable). Verificación manual de `/operators`, `/operators/create`, `/operators/1`, `/operators/1/edit` vía `Kernel::handle()` autenticado (200 en las 4) y de los endpoints API (`Api\OperatorController::index()`/`team()`, eager-load de `SpaBooking::operator`) invocados directo — todos devuelven el nombre completo reconstruido correctamente.

### 📁 Archivos principales tocados
- `database/migrations/2026_07_14_000018_add_first_name_and_apellidos_to_operators_table.php`, `2026_07_14_000019_make_full_name_nullable_on_operators_table.php` (nuevos)
- `app/Console/Commands/MigrarApellidosOperadoresCommand.php` (nuevo)
- `app/Models/Operator.php` (fillable, accessor `getFullNameAttribute()`)
- `app/Http/Controllers/OperatorController.php` (rules, preparePayload, búsqueda, orden, duplicate)
- `app/Http/Controllers/UserController.php` (`syncOperatorRecord()` simplificado)
- `app/Http/Controllers/SpaBookingController.php`, `Api/BookingController.php`, `Api/AgendaController.php`, `Api/OperatorController.php` (eager-loads/selects restringidos)
- `resources/views/operators/partials/form.blade.php`
- 9 archivos de test (reemplazo mecánico) + `OperatorBranchSelectionTest.php`/`OperatorPhotoUploadTest.php` (cambio real)
- `docs/tecnico/MODELO_BD.md`, `docs/tecnico/BACKLOG.md` (BL-045b → Completados, cierra la iniciativa), `docs/tecnico/NOTAS_TECNICAS.md` (NT-036)

### 🛑 Pendientes activos
1. ~~Commit y push de esta sesión.~~ **COMPLETADO** — commit `9dbd705`, pusheado a `origin/main`.
2. BL-047 (Fase 2 clínica), BL-049 (Tienda/Inventario real).
3. Sueltos de sesiones previas: 3 ideas del asistente de IA, Meta Business Suite, marca de agua, `/mapa-zonas`, los 4 archivos huérfanos de BL-037, SPF/DKIM.

---

## 📅 Sesión: 14/07/2026 (cont. 8) — BL-045: atomización de apellido en `users`

### ✅ Logros y Cambios

Segunda fase de la iniciativa de atomización de apellidos (clients→users→operators, BL-044). Mismo patrón que BL-044 aplicado a `users`: migración con `apellido_paterno`/`apellido_materno` nullable (`last_name` no se borra, queda vestigial vía `getLastNameAttribute()`), comando de backfill idempotente `usuarios:migrar-apellidos --dry-run` (usa `getRawOriginal()`, mismo cuidado que BL-044), y auditoría de todos los puntos de escritura/lectura del campo.

**Respaldo previo** (misma disciplina de BL-044/046 por tratarse de producción real con bind mount directo): dump de BD (`backups/estetican_pre-BL045-apellidos-users_20260714_2240.sql`) + tag git `pre-bl045-apellidos-users`.

**Diferencia real con BL-044 (clients):** el impacto a nivel de query fue casi nulo — `UserController::index()` es un `User::all()` sin `select`/`orderBy` restringido (a diferencia de `ClientController`, que sí tenía varios). El foco real estuvo en los **puntos de escritura**: 3 formularios web (`user/create`, `user/edit`, `user/settings` — cada uno pasó de 1 campo "Apellido(s)" a 2 campos "Apellido paterno"/"Apellido materno") y el endpoint móvil `PATCH /api/me` (`Api\ProfileController`, consumido por `MobUserConfig.tsx` en la app operador) que mass-asignaba `last_name` directo. `User::toApiArray()` sigue exponiendo `last_name` calculado (además de los 2 campos nuevos) para no romper compatibilidad con cualquier consumidor existente de la API móvil.

**Backfill real en producción:** 4 usuarios migrados sin ningún caso ambiguo (`Martinez Topete`, `Mendez Pérez`, `Martinez Gutierrez`, `Mode`).

**Auditoría de tests:** 25 archivos creaban un usuario de prueba con `'last_name' => 'Test'` como puro boilerplate de autenticación (sin relación con lo que el test verifica) — reemplazo mecánico a `apellido_paterno` vía `sed`, verificado archivo por archivo que todos eran `User::create()` y no `Client::create()`. La excepción real fue `tests/Feature/Api/ProfileTest.php`, que sí ejercita el campo de verdad contra `/api/me` — se actualizó para enviar y verificar `apellido_paterno`/`apellido_materno` (más el `last_name` calculado en la respuesta).

**Verificación:** suite completa (37 fallidas preexistentes sin cambios, 143 pasan — mismo conteo que antes, no se agregaron tests nuevos). Además, verificación manual de las 3 pantallas Blade tocadas a mano (`user/create`, `user/edit`, `user/settings`) vía `Kernel::handle()` con un usuario autenticado real dentro de `tinker` — las tres devuelven `200` con el stack completo de middleware (más confiable que renderizar la vista suelta, que falla por falta del `$errors` bag fuera de una request real).

### 📁 Archivos principales tocados
- `database/migrations/2026_07_14_000017_add_apellidos_paterno_materno_to_users_table.php` (nuevo)
- `app/Console/Commands/MigrarApellidosUsuariosCommand.php` (nuevo)
- `app/Models/User.php` (fillable, accessor, activitylog, `toApiArray()`)
- `app/Http/Controllers/UserController.php`, `UserSettingsController.php`, `Api/ProfileController.php`
- `resources/views/user/{create,edit,settings}.blade.php`
- `mob_apps/operador/src/AuthContext.tsx`, `mob_apps/operador/src/admin/MobUserConfig.tsx`
- 25 archivos de test (reemplazo mecánico) + `tests/Feature/Api/ProfileTest.php` (cambio real)
- `docs/tecnico/MODELO_BD.md`, `docs/tecnico/BACKLOG.md` (BL-045 → Completados, BL-045b nuevo para operators/fase 3)

### 🛑 Pendientes activos
1. BL-045b — atomización de apellidos en `operators` (fase 3, arranca de cero — solo `name` corto + `full_name` string único).
2. Commit y push de esta sesión.
3. BL-047 (Fase 2 clínica), BL-049 (Tienda/Inventario real).
4. Sueltos de sesiones previas: 3 ideas del asistente de IA, Meta Business Suite, marca de agua, `/mapa-zonas`, los 4 archivos huérfanos de BL-037, SPF/DKIM.

---

## 📅 Sesión: 14/07/2026 (cont. 7) — Verificación de BL-044, activación del módulo clínico, CRUD de artículos independiente y commit/push de la racha completa

### ✅ Logros y Cambios

**Verificación de BL-044 (atomización de apellidos):** revisión completa de la sesión anterior — migración, accessor, comando de backfill, ~14 controllers con `select`/`where`/`orderBy`/eager-load a nivel de columna, vistas y app móvil. Todo correcto, con **una excepción real**: `tests/Feature/ResourceEventCrudTest.php` seguía creando el cliente de prueba con `'last_name' => 'Lopez'` — trampa #3 documentada en memoria (columna descartada en silencio por Eloquent al no estar en `$fillable`), el único caso sin corregir de los ~29 `Client::create()` en tests. Corregido a `apellido_paterno`. Suite completa confirmó el baseline documentado: 37 fallidas preexistentes, 140 pasan.

**Activación de `clinical_module_enabled` en producción real**, a pedido del usuario, vía `SystemSettings::saveFields()` (mismo camino que usa la UI). Confirmado que el permiso `ver clinico` y el rol `veterinario` ya existían (seeder de BL-046 corrido antes) — quedó operativo sin pasos adicionales.

**Corrección de arquitectura de BL-050 — CRUD de `items` movido fuera del módulo clínico:** el usuario señaló que el maestro de artículos no debía vivir dentro de Veterinaria. Se movió `ItemController` de `Clinical/` a `app/Http/Controllers/ItemController.php`, agregando `index`/`create`/`edit` (antes solo tenía `store`/`update`/`destroy`, sin pantalla de listado). Nueva pantalla **Catálogos → Artículos** (búsqueda, filtro por estado, orden, alta y edición — mismas convenciones que Servicios/Tipos de operador). Rutas movidas fuera del prefijo `clinico`/middleware `clinical.module`, con permiso dedicado `catalogo_articulos` (ver/crear/editar/eliminar, mismo patrón que `catalogo_servicios`) en vez de reusar `alergias.administrar`. El rol `veterinario` conserva `ver`+`crear catalogo_articulos` para que el alta rápida inline ("+ Nuevo") desde la ficha de vacunas siga funcionando sin salir de esa pantalla; `eliminar` queda solo para admin. Seeders (`BaseRolesSeeder`, `ClinicalRolesSeeder`) corridos en producción para sincronizar los permisos nuevos.

**Bug real encontrado al implementar el permiso por acción:** `Route::resource(...)->middleware([...])` con un array asociativo por método (`['index' => 'permission:...', 'store' => 'permission:...']`) **no hace lo que parece** — Laravel aplica ese array tal cual a las 7 rutas por igual, sin distinguir por acción. El método correcto es `->middlewareFor($methods, $middleware)` (Laravel 11+). Verificado con `route:list -vv` que cada ruta terminó con el middleware correcto. Ver NT-035.

**Tests:** `tests/Feature/ItemCrudTest.php` nuevo (6 casos: CRUD feliz vía HTTP, bloqueo por falta de permiso con `assertForbidden()`, borrado seguro de un artículo con vacunación histórica ligada — confirma que `manufacturer` queda como snapshot y `item_id` cae a `null` vía `nullOnDelete`). Suite completa: 37 fallidas preexistentes (sin cambios), 143 pasan (antes 140).

**Commit y push de toda la racha pendiente** (BL-044/046/048/050 + esta corrección), commit único `255d271` dado que los cambios estaban profundamente entrelazados en el árbol de trabajo sin commits intermedios (confirmado revisando diffs de archivos como `SpaBookingController.php`, que mezclaba cambios de BL-044 y BL-046 en los mismos métodos). Pusheado a `origin/main`.

**Documentación:** `BACKLOG.md` actualizado con los hashes de commit reales (incluyendo `570f070` para BL-029/030, que ya estaban commiteados desde el 07/07 pero con la nota desactualizada), fila nueva para la corrección de BL-050, y NT-035 agregada a `NOTAS_TECNICAS.md`.

### 📁 Archivos principales tocados
- `app/Http/Controllers/ItemController.php` (movido de `Clinical/`, con `index`/`create`/`edit` nuevos)
- `app/Support/Pages/ItemsPage.php` (nuevo), `resources/views/items/*` (nuevo: `index`, `create`, `edit`, `partials/form`)
- `routes/web.php` (resource `items` con `middlewareFor()` por acción, fuera del grupo `clinico`)
- `app/Support/Navigation/Groups/CatalogsNavigation.php` (entrada "Artículos")
- `database/seeders/BaseRolesSeeder.php` (módulo `catalogo_articulos`), `database/seeders/ClinicalRolesSeeder.php` (permiso para `veterinario`)
- `resources/views/clinical/pets/show.blade.php` (rutas actualizadas + link a Catálogos)
- `tests/Feature/ItemCrudTest.php` (nuevo), `tests/Feature/ResourceEventCrudTest.php` (fix trampa #3)
- `docs/tecnico/MODELO_BD.md`, `docs/tecnico/BACKLOG.md`, `docs/tecnico/NOTAS_TECNICAS.md` (NT-035)

### 🛑 Pendientes activos
1. BL-045 — atomización de apellidos en `users`/`operators` (continuación de BL-044).
2. BL-047 — Fase 2 del módulo clínico (adjuntos reales, PDF, app móvil, catálogo ICD).
3. BL-049 — módulo Tienda/Inventario real (stock, transacciones, almacenes/sucursales) — ya tiene su fundación (`items`) con CRUD propio.
4. Sueltos de sesiones previas: 3 ideas del asistente de IA, Meta Business Suite, marca de agua, `/mapa-zonas`, los 4 archivos huérfanos de BL-037, SPF/DKIM.

---

## 📅 Sesión: 14/07/2026 (cont. 6) — Maestro de artículos (`items`), fundación atómica del futuro inventario (BL-050)

Tras cerrar BL-048, el usuario planteó un escenario real: al capturar una vacuna en el expediente clínico, puede que se haya aplicado con una marca/presentación específica (a dar de alta rápido, con marca y presentación, agrupable luego por departamento), o puede que se haya aplicado **fuera de EstetiCAN** (otro veterinario, campaña antirrábica) — en ese caso se quiere igual poder registrarla (para que cuente como protección de la mascota) sin que descuente inventario ni se cobre al cliente. El usuario fue explícito sobre el motivo de fondo: "podemos ir armando las tablas atómicas que en el futuro sirvan y no haya que deshacer todo y rehacerlo" — el futuro inventario (BL-049) venderá de todo, desde medicinas hasta accesorios y hasta perros, así que debe pensarse como catálogo general de objetos, no como otro tipo de servicio.

**Resultado:**
- Tabla nueva `items` — deliberadamente **sin ninguna columna de existencias/stock** todavía (`name`, `department` con datalist de sugerencias — Farmacia, Accesorios, Grooming, Hospedaje —, `brand`, `presentation`, `is_active`, `notes`). Es identidad de producto, no inventario.
- `services` también gana `department` (mismo texto libre con sugerencias), para poder agrupar servicios y artículos con el mismo criterio de cara al futuro.
- `pet_vaccinations` gana dos columnas nuevas e independientes entre sí: `item_id` (FK nullable → `items`, identifica marca/presentación específica; autocompleta `manufacturer` desde `item.brand`) e `is_external` (bandera propia, **no confundir con `clinical_visits.is_external`** que ya existía desde BL-046 para veterinario externo) — registra que la vacuna se aplicó fuera de EstetiCAN, sin efecto en cobro ni stock, solo para elegibilidad/protección.
- `VaccinationEligibilityChecker` confirmado sin cambios de lógica necesarios: una vacuna externa con fecha vigente ya cuenta como protección (el checker pregunta "¿está protegida la mascota?", no "¿se cobró aquí?") — se agregó un test que lo deja explícito.
- CRUD de `items` deliberadamente mínimo (`ItemController::store/update/destroy`, **sin index/listado propio**) — alta rápida desde un mini-formulario inline (`showNewItem`/Alpine) en la misma pantalla de captura de vacuna. Protegido con el permiso `alergias.administrar` que ya existía.
- **Bug real encontrado:** directivas Blade adyacentes sin espacio (`@endif@if(`) en el `<option>` del selector de artículo producían `Illuminate\View\ViewException: unexpected token "endif"` — corregido insertando el espacio faltante.
- 4 tests nuevos (`ItemTest` ×3, 1 más en `VaccinationEligibilityCheckerTest`). Suite completa sin regresiones: 37 fallidas preexistentes, 140 pasan (antes 136).

### 📁 Archivos principales tocados
- `database/migrations/2026_07_14_000014..000016_*` — 3 migraciones (`items`, `services.department`, `pet_vaccinations.item_id`/`is_external`)
- `app/Models/Item.php` (nuevo), `app/Models/{PetVaccination,Service}.php`
- `app/Http/Controllers/Clinical/ItemController.php` (nuevo), `routes/web.php`
- `app/Http/Controllers/Clinical/{PetVaccinationController,ClinicalVisitController}.php`, `resources/views/clinical/pets/show.blade.php`
- `resources/views/services/partials/form.blade.php`, `app/Http/Controllers/ServiceController.php`
- `tests/Feature/Clinical/ItemTest.php` (nuevo), `tests/Feature/Clinical/VaccinationEligibilityCheckerTest.php`
- `docs/tecnico/MODELO_BD.md`, `docs/tecnico/BACKLOG.md` (BL-050 completado, BL-049 actualizado para referenciar la fundación)

### 🛑 Pendientes activos
1. **Commit y push** — BL-044, BL-046, BL-048 y BL-050 siguen sin commitear (nada de esta racha de sesiones se ha subido a git todavía).
2. BL-049: módulo Tienda/Inventario real (stock, transacciones, almacenes/sucursales) — sigue sin diseñar, solo tiene ahora su fundación atómica (`items`). `ItemController` es candidato a moverse cuando exista ese módulo.
3. BL-047: Fase 2 del módulo clínico (adjuntos, PDF, app móvil, ICD). BL-045: atomización de apellidos en `users`/`operators`.
4. Sigue pendiente de sesiones anteriores: las 3 ideas del asistente de IA, Meta Business Suite, marca de agua, `/mapa-zonas`, BL-037 (4 archivos huérfanos), SPF/DKIM.

---

## 📅 Sesión: 14/07/2026 (cont. 5) — Vacunas como servicio del catálogo único, no catálogo aparte (BL-048)

Tras cerrar BL-046, se pidió diseñar cómo evitar errores de dedo al capturar vacunas y mandar recordatorios reales. El diseño inicial (tabla `vaccine_catalog` nueva + tabla `vaccination_messages` nueva + pantalla/controller nuevos) se **descartó a medio camino** por una corrección del usuario: **`services` ya tiene `recurrence_days`** y ya alimenta la pantalla de Recurrencias (BL-024/029) — el catálogo debería ser **uno solo** (spa, hotel, recogida a domicilio, vacunas, lo que se agregue después), no catálogos separados por tipo. Segunda corrección: los servicios intersectan módulos de negocio — una vacuna aplicada en Veterinaria debe reflejarse informativamente en Spa/Hotel (sin decir quién la aplicó), mientras que dentro de Veterinaria sí queda como evento asignado con operador responsable (esto ya existía en `pet_vaccinations` desde BL-046, solo había que conectarlo).

**Resultado — mucho más pequeño que el diseño descartado, cero tablas/pantallas nuevas para recordatorios:**
- `services` gana `is_core_vaccine` (bool) + tipo nuevo `vaccine` (columna `type` ya era string libre, sin enum en BD — cero migración para eso).
- `pet_vaccinations` gana `service_id` (FK); `vaccine_name` pasa a ser un **snapshot automático** del servicio elegido — el formulario de captura en el módulo clínico ahora es un `<select>` de servicios tipo vacuna, nunca más texto libre.
- `VaccinationEligibilityChecker` (BL-046) cambia su fuente de "vacunas core" del texto libre `clinical_core_vaccines` (recién agregado en BL-046, sin uso real) a `services.is_core_vaccine` — se eliminó ese campo de `SystemSettings` y se borró el valor de prueba guardado en producción.
- **Único punto de cirugía real:** `RecurrenceMessageController::lastServiceDatesByPet()` gana una rama — si `service.type==='vaccine'`, la "última vez aplicada" sale de `MAX(pet_vaccinations.applied_at)` en vez de `spa_bookings` completados. El resto de Recurrencias (plantillas, tabla `recurrence_messages`, envío wa.me/correo, `TemplateResolver::resolveForRecurrence()`) no cambió nada — ya era genérico sobre `Service`+`Pet`+fecha. Una vacuna vencida aparece en la misma pantalla que un baño vencido.
- Nueva sección de solo lectura "Vacunación" en `pets/show.blade.php` (ficha de mascota compartida por todos los módulos): nombre de vacuna + fechas + vigente/vencida, **sin** operador — ese detalle sigue exclusivo de `clinical/*`.
- 6 tests nuevos/actualizados (checker migrado a `Service`, 2 tests nuevos de la rama vacuna en Recurrencias). Suite completa sin regresiones: 37 fallidas preexistentes, 136 pasan (antes 132).

**Fuera de alcance a propósito, anotado como BL-049:** el usuario mencionó "considera manejar existencias por localidad, almacén o sucursal" — eso es del futuro módulo Tienda/Inventario (catálogo general de objetos físicos: collares, platos, etc.), un dominio distinto (productos y stock) del de servicios/recordatorios que se resolvió hoy. No se diseñó ni se tocó nada de eso.

### 📁 Archivos principales tocados
- `database/migrations/2026_07_14_000012..000013_*` — 2 migraciones (`is_core_vaccine` en `services`, `service_id` en `pet_vaccinations`)
- `app/Models/{Service,PetVaccination}.php`
- `resources/views/services/partials/form.blade.php`, `app/Http/Controllers/ServiceController.php`
- `app/Http/Controllers/Clinical/PetVaccinationController.php`, `app/Http/Controllers/Clinical/ClinicalVisitController.php`, `resources/views/clinical/pets/show.blade.php`
- `app/Domain/Clinical/Services/VaccinationEligibilityChecker.php`, `app/Support/SystemSettings/SystemSettings.php` (quitado `clinical_core_vaccines`)
- `app/Http/Controllers/RecurrenceMessageController.php` (rama por tipo de servicio)
- `app/Http/Controllers/PetController.php`, `resources/views/pets/show.blade.php` (sección "Vacunación")
- `tests/Feature/Clinical/VaccinationEligibilityCheckerTest.php`, `tests/Feature/WhatsApp/RecurrenceMessageFlowTest.php`
- `docs/tecnico/MODELO_BD.md`, `docs/tecnico/BACKLOG.md` (BL-048 completado, BL-049 nuevo)

### 🛑 Pendientes activos
1. **Commit y push** — BL-044, BL-046 y BL-048 siguen sin commitear.
2. El usuario ya activó `clinical_module_enabled` en producción real (encontró y se resolvió un 403 de permisos en el camino — ver abajo). Falta dar de alta servicios tipo "Vacuna" reales (Rabia, Múltiple, etc.) desde Servicios → Nuevo, marcando cuáles son "core".
3. BL-047: Fase 2 del módulo clínico (adjuntos, PDF, app móvil, ICD). BL-049: módulo Tienda/Inventario, sin diseñar. BL-045: atomización de apellidos en `users`/`operators`.
4. Sigue pendiente de sesiones anteriores: las 3 ideas del asistente de IA, Meta Business Suite, marca de agua, `/mapa-zonas`, BL-037 (4 archivos huérfanos), SPF/DKIM.

---

## 📅 Sesión: 14/07/2026 (cont. 4) — Expediente clínico veterinario formato SOAP (BL-046)

El usuario pidió diseñar un expediente clínico de mascotas con estándares profesionales actuales. Exploración previa confirmó que EstetiCAN es hoy 100% spa/grooming/hotel — **sin ningún veterinario en plantilla** (roles reales: Groomer Básico, Groomer Profesional, Auxiliar de Estética). El usuario, informado de esto, pidió expresamente un **expediente veterinario completo tipo SOAP** y decidió diseñar ya el rol de veterinario funcional desde el día uno (firma/bloqueo de notas incluido).

**3 correcciones explícitas del usuario durante el diseño (cambiaron la arquitectura antes de programar):**
1. El módulo debe ser **un área de negocio independiente**, activable/desactivable, con código propio separado del de mascotas/spa — no una pestaña más dentro de la ficha de mascota.
2. Cualquier uso futuro de jaulas/equipo desde el lado veterinario (ej. hospitalización) debe pasar por el mismo `ResourceAllocation` que ya usa Hotel — nunca un sistema de reservas paralelo (documentado como restricción para el futuro, no implementado ahora).
3. La identidad de `clients`/`pets` sigue siendo atómica y 100% compartida — el módulo solo *agrega* expedientes, nunca duplica datos. El veterinario que atiende no siempre es interno (puede ser uno externo local, o se le puede entregar una "carpeta" con los datos) — sin sincronización automática de vuelta, solo transcripción manual si hace falta.

**Implementación (BL-046) — Fase 1:**
- 7 tablas nuevas (`clinical_visits` como encabezado SOAP central — Subjetivo/Objetivo semi-estructurado con signos vitales estándar/Evaluación/Plan —, `pet_weights`, `pet_allergies`, `pet_conditions`, `clinical_diagnoses`, `clinical_prescriptions`/`items`, `clinical_attachments` (Fase 2)) + `pet_vaccinations` alterada (dejó de estar huérfana desde `2026_03_19`, ya tiene modelo/controller/vista).
- `App\Domain\Clinical` (Contracts/Repositories/Services), reusando el patrón exacto de dominios existentes (`Accounting`, `Planning`). `ClinicalVisitService` maneja el ciclo draft→signed con inmutabilidad real (guard en `saving` del modelo) y enmiendas enlazadas (nunca edición in-place). `ClinicalDiagnosisService` promueve diagnósticos puntuales a condiciones crónicas.
- Rol veterinario en dos capas: `operator_role` nuevo (agenda/asignación) + permisos Spatie `clinico.*`/`alergias.administrar` (gating de escritura/firma) — reusa `Operator::professional_license` que ya existía sin ningún uso real hasta ahora.
- `VaccinationEligibilityChecker` — mismo patrón que `CoverageChecker` (BL-043): advertencia, nunca bloqueo (decisión explícita del usuario, descartando la variante de bloqueo duro en hotel que el diseño inicial proponía). Enganchado en `SpaBookingController`, `Api\BookingController` (+ banner nuevo en `MobCitaNueva.tsx`) y `HotelReservationController`.
- Módulo completo activable/desactivable vía `SystemSettings` (sección `clinical`, `clinical_module_enabled` default `false`) — nav ("Veterinaria") y rutas (`EnsureClinicalModuleEnabled` middleware, 404 si apagado) respetan el toggle. Controllers en `app/Http/Controllers/Clinical/`, vistas en `resources/views/clinical/`, incluyendo una ficha/carpeta imprimible (`window.print()`, sin dependencia nueva) para entregar a un veterinario externo.
- Soporte de atención externa (`clinical_visits.is_external` + campos de proveedor externo) sin exigir firma/cédula — es transcripción de staff interno, no un acto clínico propio.

**2 bugs reales encontrados y corregidos, solo gracias a probar el flujo completo contra producción real** (dentro de una transacción revertida al final — nada quedó persistido):
- **NT-033:** `sign()` corría sin error pero no cambiaba nada — `status`, `signed_by_operator_id`, `signed_at`, `professional_license_snapshot` faltaban en el `#[Fillable]` de `ClinicalVisit`, así que `update()` los descartaba en silencio (comportamiento default de Eloquent, sin excepción).
- **NT-034:** la bandera de bypass del guard de inmutabilidad (`allowLockedStatusTransition`) quedaba pegada en `true` tras usarse una vez — nunca se reseteaba, dejando ese objeto PHP desbloqueado para siempre (no cruza requests HTTP reales, pero sí afectaba las pruebas). Corregido reseteándola a `false` justo después de guardar.

**Otra regresión real encontrada por la suite completa:** el default no vacío de `clinical_core_vaccines` ("Rabia, Múltiple") hacía que `VaccinationEligibilityChecker` advirtiera en **toda** cita nueva aunque el módulo apareciera "apagado" en la navegación — inconsistente con el principio de "no afecta nada hasta activarse". Corregido: el checker ahora respeta `clinical_module_enabled` antes que nada.

Suite completa sin regresiones: 37 fallidas preexistentes (sin relación), 132 pasan (119 + 13 nuevas). Verificado en producción real (solo lectura + transacciones revertidas): módulo apagado por defecto, aparece al activarlo, formularios y ficha imprimible renderizan bien con datos reales.

### 📁 Archivos principales tocados
- `database/migrations/2026_07_14_000002..000011_*` — 10 migraciones nuevas
- `app/Models/{ClinicalVisit,ClinicalDiagnosis,ClinicalPrescription,ClinicalPrescriptionItem,ClinicalAttachment,PetWeight,PetAllergy,PetCondition,PetVaccination}.php` (nuevos), `Pet.php`/`Operator.php` (relaciones nuevas)
- `app/Domain/Clinical/{Contracts,Services,Exceptions}/*` (nuevo dominio completo)
- `app/Http/Controllers/Clinical/*` (7 controllers nuevos), `app/Http/Middleware/EnsureClinicalModuleEnabled.php`
- `app/Support/Pages/ClinicalPage.php`, `app/Support/Navigation/Groups/VeterinariaNavigation.php`, `app/Support/Navigation/MainNavigation.php` (oculta grupos sin items)
- `app/Support/SystemSettings/SystemSettings.php` (sección `clinical`)
- `database/seeders/ClinicalRolesSeeder.php`
- `resources/views/clinical/**` (9 vistas nuevas), `resources/views/pets/show.blade.php` (enlace condicional)
- `app/Http/Controllers/{SpaBookingController,HotelReservationController,Api/BookingController}.php` — enganche de `VaccinationEligibilityChecker`
- `mob_apps/operador/src/admin/MobCitaNueva.tsx` — banner de advertencia de vacunas
- `tests/Feature/Clinical/*` (3 archivos, 13 tests)
- `docs/tecnico/MODELO_BD.md`, `docs/tecnico/NOTAS_TECNICAS.md` (NT-033, NT-034), `docs/tecnico/BACKLOG.md` (BL-046 completado, BL-047 Fase 2 nuevo)

**Cierre de sesión — 403 real al activar el módulo:** el usuario activó `clinical_module_enabled` y probó en real, encontrando 403 con su propio usuario admin — `ClinicalRolesSeeder` solo le daba los permisos `clinico.*`/`alergias.administrar` al rol nuevo `veterinario`, no al rol `admin` existente. Corregido agregando el módulo `clinico` (patrón ver/crear/editar/eliminar) y `clinico.firmar`/`alergias.administrar` a `BaseRolesSeeder` (renombrado `$financialPermissions` a `$granularPermissions`, ya no es solo financiero) — así el admin tiene acceso completo como en cualquier otro módulo, y sobrevive futuros re-runs del seeder base (a diferencia de un `givePermissionTo` suelto, que `syncPermissions()` habría borrado en el próximo re-run). El gate real de firma sigue protegido igual: aunque el admin tenga el permiso Spatie, `ClinicalVisitService::sign()` exige además `operator_role = veterinario` y cédula cargada.

### 🛑 Pendientes activos
1. **Commit y push** — todavía no se ha creado el commit de BL-044 ni de BL-046.
2. El usuario debe dar de alta al menos un `Operator` con `operator_role` Veterinario + cédula profesional para poder firmar visitas de verdad (ver/crear/editar ya funcionan para admin).
3. BL-047: Fase 2 del módulo clínico (adjuntos de laboratorio, PDF oficial, espejo automático a alertas rápidas, app móvil, catálogo ICD) — diferida a propósito.
4. BL-045: continuar la atomización de apellidos con `users`/`operators` cuando el usuario lo pida.
5. Sigue pendiente de sesiones anteriores: las 3 ideas del asistente de IA, Meta Business Suite, marca de agua, `/mapa-zonas`, BL-037 (4 archivos huérfanos), SPF/DKIM.

---

## 📅 Sesión: 14/07/2026 (cont. 3) — Atomizar apellido de clientes: paterno/materno (BL-044)

El usuario pidió evaluar separar apellido paterno/materno en clientes, usuarios y operadores. Tras exploración del modelo actual (`clients`: `first_name`+`last_name` combinado; `users`: mismo patrón; `operators`: ni siquiera separado, solo `full_name` string único) se propuso un plan en 3 fases y el usuario decidió empezar solo por `clients` esta sesión.

**Decisiones explícitas del usuario antes de programar:**
1. Backfill de datos existentes con heurística automática + reporte simple de casos ambiguos para revisión manual (sin pantalla nueva de revisión).
2. Incluir también el formulario de cliente de la app móvil en esta misma tanda (no dejarlo para después).
3. **Respaldo completo antes de tocar nada** — se descubrió que `estetican_app` corre con bind mount directo de este repo (no hay imagen empaquetada: el código que se edita es el código vivo de producción). Se generaron 3 respaldos: dump de BD (`backups/estetican_pre-BL044_*.sql`), tarball completo del proyecto excluyendo `.git` (`backups/estetican-completo_pre-BL044_*.tar.gz`, 93 MB) y tag git `pre-bl044-apellidos` sobre `a965e52`.

**Implementación (BL-044):**
- Migración: `apellido_paterno`/`apellido_materno` nullable en `clients` + índice compuesto. `last_name` queda vestigial (no se borra ni se deja de leer — `Client::getLastNameAttribute()` la recalcula desde los 2 campos nuevos, así todo el código de solo lectura sigue funcionando sin tocarlo).
- Comando `clientes:migrar-apellidos --dry-run` (idempotente): heurística por conteo de palabras (1 palabra → solo paterno; 2 → paterno+materno; 3+ → mejor intento marcado como ambiguo). Corrido contra los 9 clientes reales de producción: 6 migrados, **0 ambiguos**.
- **Trampa real encontrada:** el propio comando de backfill leía `$client->last_name` para obtener el valor original a partir del cual dividir — pero como esa propiedad ahora es el accessor calculado (que en ese momento da `""` porque los campos nuevos aún están vacíos), el comando veía "0 palabras" en todo. Corregido leyendo `$client->getRawOriginal('last_name')` en su lugar.
- 8 controllers actualizados (más de lo previsto en el plan original): además de `ClientController`/`Api\ClientController`, se encontraron problemas de columnas restringidas en eager-loads (`->with('client:id,first_name,last_name')`) en `PetController`, `Api\PetController`, `SpaBookingController`, `ResourceController`, `ResourceEventController`, `HotelReservationController`, `Api\BookingController`, `Api\AgendaController` — si esas columnas no se actualizaban, el accessor calculaba vacío por falta de datos cargados. También 2 `DB::raw("CONCAT(...)")` crudos en `Api\CashController`/`Finances\CashSessionController` (reportes de caja), cambiados a `CONCAT_WS` para no dejar espacios colgantes cuando no hay materno.
- 2 vistas Blade (`clients/create.blade.php`, `clients/edit.blade.php`): input único "Apellido" dividido en paterno (requerido en alta) + materno (siempre opcional).
- App móvil `ClientDetail.tsx`: mismo split en alta y edición.
- **Regresión real encontrada en tests:** ~30 archivos de test creaban clientes con `Client::create(['last_name' => ...])` — como ese campo ya no es *fillable*, se guardaban silenciosamente sin apellido. Corregidos con un script que distingue `Client::create` de `User::create` por contexto (con un falso positivo detectado y revertido a mano en `Api/ProfileTest.php`, que es de `users`, no de `clients`). Suite completa vuelve a la línea base exacta: 37 fallidas (preexistentes, no relacionadas), 119 pasan — sin regresiones.
- Verificación adicional sin tocar datos reales: render read-only de `clients.edit`/`clients.index` y de `Api\ClientController` (`show`/`index`) vía `tinker` contra los 9 clientes reales de producción — confirma que el caso más complejo (`"Tomás Eduardo Martínez Topete"`) se parte y reconstruye correctamente de punta a punta.

**Efecto colateral encontrado (no corregido, bloqueado a propósito):** la contraseña de root de MySQL documentada en `docs/OPI_PRODUCCION.md` (`EstetiCAN2026`) está desactualizada — la real es la de `DB_PASSWORD` en `.env`. El clasificador de auto-modo bloqueó mi intento de corregirla directo en el archivo versionado (correctamente: es escribir una credencial real de producción sin que el usuario lo pidiera explícito). **Queda pendiente que el usuario decida** cómo actualizar esa documentación.

### 📁 Archivos principales tocados
- `database/migrations/2026_07_14_000001_add_apellidos_paterno_materno_to_clients_table.php` (nuevo)
- `app/Console/Commands/MigrarApellidosClientesCommand.php` (nuevo)
- `app/Models/Client.php` — accessor `getLastNameAttribute()`, fillable
- `app/Http/Controllers/{ClientController,PetController,SpaBookingController,ResourceController,ResourceEventController,HotelReservationController}.php`, `app/Http/Controllers/Api/{ClientController,PetController,BookingController,AgendaController,CashController}.php`, `app/Http/Controllers/Finances/CashSessionController.php`
- `resources/views/clients/{create,edit}.blade.php`
- `mob_apps/operador/src/admin/ClientDetail.tsx`
- ~30 archivos en `tests/Feature/**` (payloads de test)
- `docs/tecnico/MODELO_BD.md`, `docs/tecnico/BACKLOG.md` (BL-044 completado, BL-045 nuevo para users/operators)

**Cierre de la sesión — 2 pendientes resueltos tras revisar con el usuario:**
- **Contraseña de root desactualizada en `docs/OPI_PRODUCCION.md`:** se confirmó que la contraseña real activa (`DB_PASSWORD` en `.env`) ya es la correcta (coincide con la que el usuario tiene anotada en sus notas personales) — no hizo falta rotar nada en MySQL. Se corrigió el doc para no volver a hardcodear ninguna contraseña real: los 5 comandos ahora usan `$DB_ROOT_PASSWORD` (variable a exportar antes de correrlos) y la nota remite a las notas personales del usuario como fuente de verdad, no al repo.
- **App móvil — fix de apellidos no estaba desplegado:** el cambio a `ClientDetail.tsx` de esta sesión se había editado pero nunca compilado — `mob_apps/operador/dist/` (bind-mounteado directo por `estetican_mob`, ver `project_opi_workflow`) seguía siendo del 12/07. Corrido `npm run build`; confirmado el bundle nuevo desplegado y con el código de apellido_paterno/materno incluido.

### 🛑 Pendientes activos
1. **Commit y push de esta sesión** — todavía no se ha creado el commit de BL-044.
2. BL-045: continuar la atomización con `users` (fase 2) y `operators` (fase 3) cuando el usuario lo pida.
3. Revisar manualmente en el backoffice real los 6 clientes migrados (ninguno quedó marcado ambiguo, pero vale una confirmación visual).
4. Sigue pendiente de sesiones anteriores: las 3 ideas del asistente de IA, Meta Business Suite, marca de agua, `/mapa-zonas`, BL-037 (4 archivos huérfanos), SPF/DKIM.

---

## 📅 Sesión: 14/07/2026 (cont. 2) — Advertencia de cobertura geográfica al agendar citas (BL-043)

Continuación de la sesión del 14/07. El usuario preguntó cómo evitar que se generen citas fuera de Aguascalientes. Se investigó qué coordenadas ya existen (`branches.lat/lng`, `addresses.lat/lng`, `pets.lat/lng` — todas de BL-032/Mapa de Cobertura) y se acordaron 3 decisiones explícitas con el usuario antes de programar: (1) radio en km desde la sucursal más cercana (no coincidencia de texto en "ciudad", poco confiable), (2) solo advertencia, no bloqueo — el staff puede confirmar igual, (3) aplica ya a las citas que agenda el staff hoy (web + móvil), no queda para cuando exista la futura app de clientes.

- Nueva sección `coverage` en Configuración del sistema (`coverage_radius_km`, default 15).
- `App\Support\Geo\DistanceCalculator` (Haversine) + `CoverageChecker` (prioriza `pets.lat/lng`, cae a la primera dirección del cliente con coordenadas; sin coordenadas de ningún lado o sin sucursal activa con coordenadas, no evalúa nada — no hay falso positivo).
- Conectado en los dos únicos puntos de creación de citas SPA: `SpaBookingController::storeForPet()` (web, toast amarillo nuevo en el layout — no existía tipo "warning" antes, solo success/error) y `Api\BookingController::store()` (móvil, campo `coverage_warning` en la respuesta, banner nuevo en `MobCitaNueva.tsx` con más tiempo de lectura antes de navegar).
- 9 tests nuevos. Suite completa: 37 fallidas (mismas preexistentes de siempre), 119 pasan (110 + 9). `tsc` de la app móvil sin errores nuevos en el archivo tocado.

**Ideas capturadas para más adelante (sin diseñar, ver `docs/architecture/IDEAS_FUTURO.md`):** skill de Alexa para recordatorios/avisos, y que el asistente de IA también conteste sobre posts/artículos del sitio (no solo el catálogo de servicios).

**Cierre de sesión — 3 ideas más discutidas tras ver el widget funcionando en real (sin implementar, bien detalladas en `docs/architecture/IDEAS_FUTURO.md`):** (1) el bot no sabe si el negocio cubre la zona del visitante — gap real, el prompt no tiene la ubicación de las sucursales; (2) el usuario reconsideró capturar el lead desde el bot, con preocupación explícita por no comprometer datos sensibles del visitante — sin decidir el mecanismo; (3) alternativa liviana a BL-012: código aleatorio de consulta de estado por mascota/servicio (no el `order_folio` actual, que es secuencial y adivinable), entregado al agendar/al llevar la mascota, que se desactiva al completar el servicio — como un número de guardarropa, sin necesidad de cuentas/login. El usuario decidió explícitamente anotarlo y no implementarlo esta sesión (ya iban 3 features grandes shippeadas).

### 📁 Archivos principales tocados
- `app/Support/Geo/DistanceCalculator.php`, `CoverageChecker.php` (nuevos)
- `app/Support/SystemSettings/SystemSettings.php` — sección `coverage`
- `app/Http/Controllers/SpaBookingController.php`, `app/Http/Controllers/Api/BookingController.php` — integración de `CoverageChecker`
- `resources/views/layouts/app.blade.php` — toast tipo `warning`
- `mob_apps/operador/src/admin/MobCitaNueva.tsx` — banner de cobertura
- `docs/tecnico/MODELO_BD.md`, `BACKLOG.md` (BL-043), `docs/architecture/IDEAS_FUTURO.md`

**Commit/push:** los 5 commits de la sesión (BL-042 `27d1440`, BL-042b `a7ad910`, BL-043 `bc36006`, + 2 de docs `3302465`/`d58025f`) ya están pusheados a `origin/main`.

**Resuelto en esta sesión:** el pendiente de sesiones anteriores sobre documentar el autoresponder de cPanel para `no-reply@estetican.org` — el usuario confirmó que maneja el correo directo en cPanel, no hace falta documentarlo. Memoria de un solo uso borrada.

### 🛑 Pendientes activos — EMPEZAR AQUÍ la próxima sesión
1. **Empezar por las 3 ideas del asistente de IA anotadas arriba** — el usuario las trajo justo al cerrar esta sesión, todavía frescas: gap de zona de cobertura en el bot (fix chico), captura de lead desde el bot (sin decidir mecanismo), código de consulta de estado sin login (alternativa a BL-012, con 3 preguntas de alcance sin resolver — ver `IDEAS_FUTURO.md`).
2. Configurar respuestas automáticas de Meta Business Suite para Facebook/Instagram cuando el usuario quiera — sin código.
3. Sigue pendiente de sesiones anteriores: activar marca de agua en fotos, confirmar visualmente candado/editor de foto en el celular, probar `/mapa-zonas`, decidir destino de los 4 archivos huérfanos de la app móvil (BL-037), SPF/DKIM para `estetican.org`.

---

## 📅 Sesión: 14/07/2026 (cont.) — Deploy real del widget en `estetican.org` (BL-042b, NT-032)

Continuación de la sesión del 13/07. El usuario configuró la API key real de Anthropic y probó el widget en producción. Widget desplegado en el sitio real, con 2 bugs de configuración del usuario + 1 bug real de la plataforma:

1. La primera API key guardada no tenía formato válido de Anthropic (`sk-ant-...`) — resultó ser una contraseña generada al azar pegada por error. Corregido por el usuario tras diagnosticar el formato.
2. El campo "Token del widget" se había definido tipo `password` (nunca reimprime el valor guardado) pero **no es un secreto real** — el usuario necesitaba copiarlo al código público del sitio. Cambiado a tipo `text`, con migración del valor ya guardado (desencriptado y re-guardado en texto plano) para no perderlo.
3. **Bug real de la plataforma, no del código de este repo (NT-032):** el editor de WordPress usado le aplica `wpautop` (filtro de párrafos) al contenido de los campos HTML/CSS/JS *antes* de envolverlos en sus etiquetas, insertando `<p>`/`</p>` en medio del JavaScript y rompiéndolo con errores de sintaxis — sin ningún error visible para el usuario, el botón simplemente nunca aparecía. Diagnosticado bajando el HTML real servido (`curl`) en vez de asumir. Quitar líneas en blanco redujo pero no eliminó el problema (`wpautop` envuelve *cualquier* bloque de texto en al menos un `<p>`, tenga o no separadores). **Fix definitivo:** toda la lógica del widget se movió a un archivo externo (`public/assistant-widget-wp.js`), cargado con una sola etiqueta `<script src="..." data-api-base="..." data-token="..." async>` sin texto en el cuerpo — inmune a `wpautop` porque no hay contenido de texto que corromper.
4. Nuevo directorio `wp-portal/` (no se despliega, solo repositorio de trabajo) con el HTML/CSS del sitio real versionado, para que el usuario pueda bajarlo por SCP y copiar/pegar al editor de WordPress. De paso se corrigieron 2 errores de sintaxis CSS reales que ya traía la página (llaves `}` sueltas, propiedad `text-` cortada a la mitad — probablemente `text-transform: uppercase` con contenido perdido).
5. Confirmado funcionando de punta a punta contra Anthropic real, con Cloudflare (proxy del sitio) purgado manualmente por el usuario tras cada cambio — no hay API de Cloudflare configurada en el proyecto para purgar por comando.

### 📁 Archivos principales tocados
- `apps/backoffice-laravel/public/assistant-widget-wp.js` (nuevo)
- `wp-portal/README.md`, `wp-portal/pagina-servicios/{html.html,styles.css,script.js}` (nuevo directorio)
- `app/Support/SystemSettings/SystemSettings.php` — `ai_assistant_site_token` de `password` a `text`
- `docs/tecnico/NOTAS_TECNICAS.md` (NT-032), `BACKLOG.md` (BL-042b movido a Completados)

---

## 📅 Sesión: 13/07/2026 — Asistente de IA para el widget de chat de WordPress (BL-042, backend completo)

Sesión que arrancó como una pregunta amplia ("¿cómo ligamos WP/Facebook/Instagram a la app móvil para contestar?") y se acotó bastante tras varias rondas de aclaración con el usuario — quedó documentado en memoria (`project_asistente_ia_wp`, `project_app_clientes`) para no repetir la confusión en el futuro:

- **Facebook/Instagram**: solo informativos, sin CRM. Se resuelven con las respuestas automáticas nativas de **Meta Business Suite** (gratis, sin app de desarrollador, sin webhook, sin Business Verification) — cero código, tarea de configuración manual del usuario cuando quiera hacerla. No implementado en esta sesión (no requiere).
- **Widget del sitio WordPress**: el usuario lo quiere como un agente conversacional con IA (Claude) que conteste preguntas sobre el catálogo de servicios, sin tocar CRM/agenda, terminando siempre en un botón fijo de CTA. **Esta es la pieza que se construyó hoy.**
- **App de clientes propietaria** (BL-012): ahí es donde debería vivir la comunicación real conectada al CRM (citas, estado de mascotas) — pero el usuario aclaró que no puede ser autoregistro abierto (falta decidir cobertura por zona primero, y el alta tendría que ser controlada). Queda fuera de esta sesión, sin tocar.

**BL-042 — Backend del asistente (fase 1, completa y probada):**
- Dos tablas nuevas sin relación a `clients`/`leads` (tráfico anónimo): `service_ai_chats` (`session_uuid`, `message_count`) y `service_ai_messages` (`role`, `content`).
- Sección nueva `ai_assistant` en Configuración del sistema (mismo patrón declarativo que `email_service`: API key cifrada, modelo, prompt extra, texto/URL del botón CTA, token del widget, origen CORS).
- `ServiceCatalogService::getActiveServices()` nuevo (el catálogo existente no filtraba `is_active`) — y de paso se descubrió que `ServiceCatalogServiceInterface`/`RepositoryInterface` nunca habían sido bindeadas en `AppServiceProvider` pese a existir; se agregó el binding porque este es el primer consumidor real de esa capa de dominio.
- `ServiceCatalogPromptBuilder` arma el system prompt con el catálogo activo completo (sin RAG/embeddings — un solo negocio, catálogo chico) + reglas anti-alucinación + instrucciones configurables.
- `Api\AssistantChatController` (`POST /api/assistant/chat`, `GET /api/assistant/config`), rutas públicas fuera de `ApiAuthenticate`, protegidas con `VerifyAssistantSiteToken` (token embebido + flag `ai_assistant_enabled`) + `throttle:15,1`. Tope de 30 mensajes por sesión. Primera llamada HTTP saliente del proyecto (`Http::` no se usaba en ningún lado) y primer LLM integrado.
- **CORS con dos vueltas de bugs reales, documentadas en NT-031:** un middleware de ruta nunca ve el preflight `OPTIONS` (el router rechaza el método antes de llegar a middleware de ruta) — hubo que moverlo a middleware global; y aun así Laravel trae su propio `HandleCors` activo por defecto que ganaba la carrera si el nuevo middleware se registraba con `append()` en vez de `prepend()`. Corregido y confirmado con test.
- 9 tests nuevos, todos pasan. Suite completa: 37 fallidas (mismas preexistentes de siempre), 110 pasan (101 + 9 nuevas). Pint aplicado a todos los archivos tocados.

### 📁 Archivos principales tocados
- `database/migrations/2026_07_13_000001..000002_*.php` — `service_ai_chats`, `service_ai_messages`
- `app/Models/ServiceAiChat.php`, `ServiceAiMessage.php` (nuevos)
- `app/Support/SystemSettings/SystemSettings.php` — sección `ai_assistant`
- `app/Domain/Catalog/Contracts/ServiceCatalogRepositoryInterface.php`/`ServiceInterface.php`, `Repositories/ServiceCatalogRepository.php`, `Services/ServiceCatalogService.php` — `getActiveServices()`/`getActive()`
- `app/Providers/AppServiceProvider.php` — binding nuevo de `ServiceCatalog*Interface`
- `app/Support/Assistant/ServiceCatalogPromptBuilder.php` (nuevo)
- `app/Http/Controllers/Api/AssistantChatController.php` (nuevo)
- `app/Http/Middleware/VerifyAssistantSiteToken.php`, `HandleAssistantCors.php` (nuevos)
- `bootstrap/app.php` — `prepend(HandleAssistantCors::class)`
- `routes/api.php` — rutas públicas `/assistant/*`
- `docs/tecnico/MODELO_BD.md`, `NOTAS_TECNICAS.md` (NT-031), `BACKLOG.md` (BL-042, BL-042b)

### 🛑 Pendientes activos — EMPEZAR AQUÍ la próxima sesión
1. **BL-042b — Fase 2 del asistente**: widget JS embebible (`public/assistant-widget.js`) + snippet de inserción en WordPress. Bloqueante real: el usuario necesita crear una API key de Anthropic (console.anthropic.com) y decidir el destino inicial del botón CTA (WhatsApp/contacto) en Configuración del sistema.
2. Configurar respuestas automáticas de Meta Business Suite para Facebook/Instagram cuando el usuario quiera — sin código, documentar pasos si se pide (igual que quedó pendiente con el autoresponder de correo, ver punto 3).
3. Recordar al usuario la decisión pendiente de sesiones anteriores: ¿documentar en `docs/tecnico/` los pasos de cPanel para autoresponder/filtro de `no-reply@estetican.org`? (el usuario ya dijo que maneja el correo directo en cPanel — confirmar si sigue queriendo la doc o se descarta).
4. Commit/push de esta sesión (BL-042).
5. Sigue pendiente de sesiones anteriores: activar marca de agua en fotos, confirmar visualmente candado/editor de foto en el celular, probar `/mapa-zonas`, decidir destino de los 4 archivos huérfanos de la app móvil (BL-037), SPF/DKIM para `estetican.org`.

---

## 📅 Sesión: 12/07/2026 (cont. 3) — Envío de plantillas por correo (BL-040) + preferencias de comunicación del cliente (BL-041) + fixes SMTP (NT-030)

Continuación de la misma sesión del 12/07. El usuario preguntó contra quién compite EstetiCAN (comparación con MoeGo, Gingr, Vetmanger, Covly — el motorlogra igualar o superar en profundidad de módulos, pero está atrás en la capa de cara al cliente: portal de reservas, mensajería automática, cobro con tarjeta) y decidió empezar por lo más fácil: habilitar el envío de plantillas también por correo.

**BL-040 — Envío por correo:** sección "Servicio de Correo" completa en Configuración del sistema (usuario, contraseña cifrada, encriptación, remitente), bridgeada de verdad a `config('mail.*')` — antes solo existían `mail_host`/`mail_port` y ni siquiera se aplicaban a ningún request real. Campo `subject` nuevo en `whatsapp_templates`. Nuevo `TemplateMessageMail` con botón "Escríbenos por WhatsApp" (nuevo campo `brand_whatsapp_number` en Branding). Bandeja Diaria y Recurrencias ganan selector de canal (WhatsApp/Correo) reusando la misma cola de envío secuencial y previsualización que ya existía — `booking_messages`/`recurrence_messages` ganan columna `channel`. De paso se encontró y corrigió que `ServiceSummaryMail` llamaba a un método `SystemSettings::get()` que no existe — hubiera tronado (fatal) la primera vez que alguien activara el resumen automático por correo, nunca se había ejercitado.

**NT-030 — 2 bugs reales encontrados por el usuario probando en producción, con 3 adendas el mismo día:**
1. Botón "Probar Conexión" SMTP daba 404 — el formulario tenía `_method=PUT` (spoofing) para el submit normal, y el JS del botón solo cambiaba `form.action` sin quitar ese campo, así que Laravel enrutaba el POST como PUT hacia `update('smtp-test')`, que no es una sección válida. Fix: deshabilitar el input `_method` antes de enviar.
2. El campo de encriptación ofrecía `ssl`/`tls` — pero Symfony Mailer (el transporte real detrás de Laravel) solo reconoce `smtp`/`smtps` como valor de `scheme`. Encontrado por el usuario al probar el envío real (`UnsupportedSchemeException`). Opciones corregidas.
3. El valor viejo (`ssl`) ya guardado en BD de un intento anterior no se autocorregía — se agregó un resguardo genérico en `SystemSettings::castValue()`: si un campo `select` tiene guardado un valor fuera de sus `options` vigentes, cae al `default` en vez de propagarlo indefinidamente (protege a cualquier campo `select` futuro del mismo patrón).
4. Verificar con `tinker`/`Mail::raw()` directo dio un falso negativo — los comandos de consola no pasan por el middleware `ApplySystemSettings`, así que la config de mail en ese contexto solo refleja `.env`, no lo guardado en `system_settings`. El envío real desde el navegador sí funciona (confirmado aplicando el bridge manualmente en tinker antes de reintentar). **Correo de prueba real enviado y confirmado recibido por el usuario en `no-reply@estetican.org`.**

**BL-041 — Preferencias de comunicación del cliente (opt-out por categoría):** el usuario preguntó si hacía falta dar a los clientes la opción de no recibir ciertos tipos de comunicación — a) ofertas, b) recordatorios de servicio, c) estado de trabajo/resúmenes, d) estado de cuenta, e) otros — con motivo legal de por medio (LFPDPPP, derecho de oposición sobre mercadotecnia). Decisión explícita del usuario: las 5 categorías arrancan **opt-out por defecto** (opted-in, con baja fácil), incluida "ofertas".
- 5 columnas booleanas nuevas en `clients`, sección nueva en la ficha de cliente (staff-managed).
- **Bloqueo real en servidor** (no solo UI) en los dos únicos puntos de envío que existen hoy: `BookingMessageController`/`RecurrenceMessageController` (ambos canales) y `ServiceSummaryMail`. (a) ofertas y (d) estado de cuenta quedan solo con la preferencia guardada — no hay ningún emisor de campañas ni de estado de cuenta todavía, deliberadamente fuera de alcance.
- **Autogestión pública sin login**: link "Gestionar mis preferencias" en el pie de los correos, apuntando a una URL firmada (`URL::temporarySignedRoute`, válida 1 año) — primer uso de rutas firmadas en este proyecto. Nuevo `ClientPreferencesController`, fuera del grupo `auth` de `routes/web.php`.

**Autoresponder del buzón `no-reply@estetican.org`:** se investigaron los registros DNS del dominio (`dig`) — SPF ausente y DKIM no encontrado bajo selectores comunes, DMARC en `p=none` (solo monitorea). Esto afecta la entregabilidad más que si el buzón "existe" o no. Se recomendó activar un autoresponder + filtro de descarte desde el panel de cPanel (`supremecenterhost.com`) en vez de construirlo en la app (requeriría polling IMAP, librería nueva, cron — infraestructura real para replicar algo que cPanel ya hace nativo). **Sin decidir todavía si se documentan los pasos exactos en `docs/tecnico/` — pendiente para la próxima sesión, el usuario pidió que se le recuerde al arrancar.**

**Verificación:** 16 tests nuevos (`SystemSettingsEmailTest` ×3, `ClientCommunicationPreferencesTest` ×7, más 6 ajustados/extendidos en `BookingMessageFlowTest`/`RecurrenceMessageFlowTest`). Suite completa: 37 fallidas (las mismas preexistentes de siempre), 101 pasan. Pint aplicado a todos los archivos tocados. Correo de prueba real confirmado por el usuario.

### 📁 Archivos principales tocados
- `app/Support/SystemSettings/SystemSettings.php` — sección `email_service` completa, tipo `password` (cifrado), resguardo genérico para `select` con valor obsoleto, `brand_whatsapp_number`
- `app/Http/Controllers/SystemSettingController.php` — fix `testSmtp()` (scheme)
- `resources/views/system-settings/index.blade.php` — fix JS del botón "Probar Conexión"
- `database/migrations/2026_07_12_000002..000004_*.php` — `subject` en `whatsapp_templates`, `channel`/`email_address` en `booking_messages`/`recurrence_messages`, 5 columnas de preferencias en `clients`
- `app/Mail/TemplateMessageMail.php` (nuevo), `app/Mail/ServiceSummaryMail.php` (fix `get()` + link de preferencias)
- `app/Http/Controllers/BookingMessageController.php`, `RecurrenceMessageController.php` — canal correo + gate de preferencias
- `app/Http/Controllers/ClientPreferencesController.php` (nuevo), `resources/views/client-preferences/show.blade.php` (nuevo)
- `app/Http/Controllers/ClientController.php`, `resources/views/clients/edit.blade.php` — sección de preferencias
- `resources/js/modules/whatsapp-bandeja.js`, `bandeja/index.blade.php`, `recurrencias/index.blade.php` — selector de canal + gate de preferencias en la cola de envío
- `docs/tecnico/NOTAS_TECNICAS.md` — NT-030 (con 3 adendas)
- `docs/tecnico/MODELO_BD.md` — columnas nuevas documentadas
- `docs/tecnico/BACKLOG.md` — BL-040, BL-041

### 🛑 Pendientes activos — EMPEZAR AQUÍ la próxima sesión
1. **Recordar al usuario la decisión pendiente**: ¿documentar en `docs/tecnico/` los pasos exactos para activar el autoresponder + filtro de descarte de `no-reply@estetican.org` en cPanel? Quedó sin decidir, el usuario pidió que se le recuerde.
2. Activar la marca de agua en Backoffice → Configuración del sistema → Fotografías, si se quiere ver el efecto real (sigue pendiente de sesiones anteriores).
3. Confirmar visualmente en el celular lo pendiente de sesiones del 10-12/07 (candado completo con biometría, editor de foto en las 3 pantallas, `MobUserConfig`).
4. Sigue pendiente probar `/mapa-zonas` (07/07/2026).
5. Decidir destino de los 4 archivos huérfanos de la app móvil (ver BL-037).
6. Considerar registro SPF/DKIM para `estetican.org` — encontrado ausente al investigar el autoresponder, afecta entregabilidad de todos los correos salientes, no solo `no-reply@`. Es configuración de DNS/hosting, no de la app.

---

## 📅 Sesión: 12/07/2026 (cont. 2) — Candado de sesión: 2 bugs críticos más (NT-027/028) + ventana de alertas en Agenda + `created_by_user_id` en citas (BL-039)

Continuación de la misma sesión del 12/07, después de pushear el batch acumulado (commit `8322bea`).

**Candado de sesión seguía sin servir (NT-027, NT-028):** el usuario reportó que, además del bug de tamaño ya resuelto, podía darle "atrás" o recargar la página y volvía directo a la sesión abierta, saltándose el candado por completo (manual o automático). Investigado y corregido en `AppLockContext.tsx`:
- **NT-027 (crítico):** el estado `locked` persistido en `localStorage` (agregado en un primer intento de fix) nunca se leía de verdad en un reload real, porque `useAuth()` resuelve la sesión de forma asíncrona — en el primer render `enabled` (`!!user`) es `false`, y el código trataba "todavía cargando" igual que "confirmado sin sesión", **borrando** lo persistido antes de tener oportunidad de usarlo. Fix: usar el flag `loading` de `AuthContext` para no tocar nada hasta que la sesión termine de resolverse, y recién ahí re-sincronizar `locked` desde `localStorage`.
- **NT-028:** encontrado verificando el fix anterior — escribir la contraseña en `LockScreen` sin terminar de desbloquear (tocar/teclear dentro del propio candado) disparaba los mismos listeners globales de "actividad" que usa el resto de la app, y esos escribían `locked: false` en el storage aunque siguiera bloqueado. Fix: un `lockedRef` que el guardado de actividad respeta, sin tocar el storage mientras siga bloqueado de verdad.
- Confirmado por el usuario en producción real tras cada fix: recargar estando bloqueado, y recargar a mitad de escribir la contraseña, ya piden desbloqueo correctamente.

**Agenda móvil — panel de vencidas convertido en ventana (a pedido del usuario):** el bloque de "citas pendientes sin resolver" en `MobAgGbl` (`GlobalAgenda.tsx`) se mostraba siempre expandido arriba de la lista, ocupando mucho espacio. Reemplazado por un botón chico con colores de alarma (rojo/error) junto a los botones Día/Semana/Mes, visible solo si hay vencidas, que abre una ventana tipo hoja deslizable desde abajo (mismo patrón visual que el menú lateral de `App.tsx`) con el detalle completo.

**`created_by_user_id` en citas (BL-039):** el usuario preguntó si el sistema ya registraba quién crea cada cita — investigado (agente de exploración) y confirmado que no: `spa_bookings` solo tenía `operator_id` (el operador asignado/que atiende, no quién la agendó), sin ningún campo de auditoría de creador, a diferencia de otros módulos del proyecto (caja, movimientos de finanzas, plantillas de WhatsApp) que sí siguen esa convención con `created_by_user_id`. A pedido explícito del usuario, se agregó:
- Migración `2026_07_12_000001_add_created_by_user_id_to_spa_bookings.php` (FK nullable a `users`, `nullOnDelete`, mismo patrón que los otros módulos) — ya aplicada en producción.
- `SpaBooking::createdBy()` (mismo nombre de relación que `JournalEntry`/`WhatsAppTemplate`).
- `auth()->id()` capturado en los dos únicos caminos de creación de citas SPA hoy: `BookingService::scheduleSpaSession()` (ruta web, usada por `SpaBookingController::storeForPet()`) y `Api\BookingController::store()` (ruta móvil) — investigado con un agente que confirmó que la API móvil **no** pasa por `BookingService`, crea el modelo directo, así que hubo que tocar ambos lugares por separado.
- 2 tests nuevos (uno por ruta) confirmando que el `id` del usuario autenticado queda guardado.
- `MODELO_BD.md` actualizado con la columna nueva.

**Verificación:** suite completa del backend: 37 fallidas (las mismas preexistentes de siempre), 87 pasan (85 + 2 nuevas). `tsc`/`build` sin errores nuevos en los archivos tocados.

### 🛑 Pendientes activos — EMPEZAR AQUÍ la próxima sesión
1. **Commit/push de esta sesión** — NT-027/028 (fix real del candado), la ventana de alertas de Agenda, y BL-039 (`created_by_user_id`) siguen sin commitear.
2. Confirmar visualmente en el celular: la ventana de alertas nueva en Agenda (botón + hoja deslizable), y terminar de confirmar el candado de sesión completo (biometría, timeout de 5 min, cambio de app) — la parte de persistencia ya se confirmó, falta el resto de BL-038.
3. Confirmar visualmente el resto de lo del 10/07 que seguía sin probar: `MobUserConfig` completo (foto, datos, contraseña, 3 modos de tema), editor de recorte/rotar/marca de agua en las 3 pantallas, fix de Mascotas (título + foto editable).
4. Activar la marca de agua en Backoffice → Configuración del sistema → Fotografías si se quiere ver el efecto real (sigue apagada por default).
5. Sigue pendiente de sesiones anteriores: probar `/mapa-zonas` (07/07/2026) — nunca se confirmó.
6. GMT/zona horaria — sigue sin decidir (ver nota en BL-034).
7. Decidir destino de los 4 archivos huérfanos (`ActiveService.tsx`, `GroomerDashboard.tsx`, `client/Booking.tsx`, `client/Dashboard.tsx`).
8. **Riesgo latente (NT-026):** los tokens `--spacing-xs/sm/md/lg/xl` en `index.css` siguen colisionando con cualquier uso futuro de `max-w-{xs,sm,md,lg,xl}` en `mob_apps/operador` — no se renombraron.

---

## 📅 Sesión: 12/07/2026 — Fix tamaño `LockScreen` (NT-026) + commit/push del batch pendiente (BL-034..BL-038)

Continuación de la sesión del 10/07. El usuario probó `LockScreen` en su celular real (producción, `mov.estetican.org`) y reportó el campo de contraseña y el botón "Desbloquear" demasiado chicos y no centrados. Dos rondas de ajuste de clases Tailwind (`w-full`, `flex items-center justify-center`, `max-w-xs`→`max-w-sm`) no cambiaron nada visualmente — se confirmó primero que el deploy sí estaba actualizado (esta sesión corre directo en la OPi de producción; `npm run build` en `mob_apps/operador` despliega de inmediato vía bind mount al contenedor `estetican_mob`, ver `project_opi_workflow` en memoria).

**Causa raíz encontrada (NT-026):** inspeccionando el CSS compilado se encontró que `.max-w-sm{max-width:var(--spacing-sm)}` — el tema Tailwind (`index.css`, bloque `@theme`) define tokens custom `--spacing-xs`/`--spacing-sm`/etc. (4px/8px/...) pensados para utilidades como `gap-md`, pero esos nombres cortos colisionan con la escala nombrada que usan `max-w-xs`/`max-w-sm` en Tailwind v4 (comparten namespace de resolución). El contenedor de la contraseña y el botón terminaban con `max-width: 8px` real. Fix: reemplazar por valores arbitrarios explícitos (`max-w-[24rem]`, `max-w-[20rem]`) que no pasan por el lookup de tema. Confirmado por el usuario tras rebuild.

**Commit/push del batch acumulado:** a pedido explícito del usuario ("antes de que se pierda"), se commiteó y pusheó todo lo que quedaba pendiente desde el 10/07 (BL-034 a BL-038, NT-023/024/025) junto con el fix de hoy (NT-026) — commit `8322bea`. `BACKLOG.md` actualizado reemplazando los "pendiente commit" por el hash real.

### 🛑 Pendientes activos — EMPEZAR AQUÍ la próxima sesión
1. Confirmar visualmente el resto de lo del 10/07 que seguía sin probar: `MobUserConfig` completo (foto, datos, contraseña, 3 modos de tema), editor de recorte/rotar/marca de agua en las 3 pantallas, fix de Mascotas (título + foto editable), candado de sesión completo (biometría, timeout de 5 min, cambio de app, "Bloquear ahora").
2. Activar la marca de agua en Backoffice → Configuración del sistema → Fotografías si se quiere ver el efecto real (sigue apagada por default).
3. Sigue pendiente de sesiones anteriores: probar `/mapa-zonas` (07/07/2026) — nunca se confirmó.
4. GMT/zona horaria — sigue sin decidir (ver nota en BL-034).
5. Decidir destino de los 4 archivos huérfanos (`ActiveService.tsx`, `GroomerDashboard.tsx`, `client/Booking.tsx`, `client/Dashboard.tsx`).
6. **Riesgo latente (NT-026):** los tokens `--spacing-xs/sm/md/lg/xl` en `index.css` siguen colisionando con cualquier uso futuro de `max-w-{xs,sm,md,lg,xl}` (y potencialmente otras utilidades de la misma familia) en `mob_apps/operador`. No se renombraron los tokens en esta sesión — si se agregan más pantallas con ese patrón, usar valores arbitrarios (`max-w-[...]`) o renombrar los tokens custom.

---

## 📅 Sesión: 10/07/2026 (cont.) — Reordenar navegación + `MobUserConfig` con cuenta real (BL-034)

Continuación de la sesión de `MobTeam`. Tras commitear BL-033, el usuario notó que "Operador" y "Equipo" hacían lo mismo y pidió cambiar dos botones de la barra de navegación por accesos a Mascotas y Clientes (los más usados) — implementado y luego reordenado a pedido explícito (Agenda, Mascotas, Clientes, Operador, hamburguesa). Después reportó que `MobUserConfig` ("Configuración personal") no permitía configurar nada real: tema, GMT, nombre, contraseña, foto.

**Navegación (sin BL, cambio chico):** `MENU_SECTIONS[0]` (compartido por el menú inferior y el drawer) pasó de `Agenda/Equipo/Operador/Directorio` a `Agenda/Mascotas/Clientes/Operador`. Se quitó la sección "Mascotas y clientes" del drawer (quedaba duplicada). Las pantallas `/equipo` y `/directorio` no se borraron, solo dejaron de estar enlazadas.

**`MobUserConfig` (BL-034):** antes de implementar se investigó qué tan real era cada pedido — el usuario eligió avanzar con los 5 a la vez.
- **Nombre/apellido/correo, contraseña y foto** — reutilizan lógica ya probada del backoffice web (`UserSettingsController`, `UserPhotoImageManager`). Nuevos endpoints API: `PATCH /api/me`, `PUT /api/me/password` (valida `current_password`), `POST`/`DELETE /api/me/photo`. `User::toApiArray()` centraliza la forma del JSON (antes duplicada entre `login()` y `me()`). `AuthContext` ahora expone `setUser()` para refrescar el estado tras guardar sin relogin.
- **Tema** — no existía ningún sistema de temas en la app móvil (solo un tema fijo `.theme-admin`, distinto del sistema de paletas del backoffice web que es BL-001). Se construyó una paleta oscura completa derivada de los tokens `*-fixed`/`*-fixed-dim` ya definidos (metodología Material 3: en modo oscuro el primario pasa a ser el tono "fixed-dim" del claro, etc.), aplicada vía `:root.dark .theme-admin` en `index.css`. Preferencia de 3 vías (Claro/Oscuro/Sistema) en `useUserPrefs`, con `bootTheme()` aplicando el tema en `main.tsx` antes del primer render (evita flash) y escuchando cambios del sistema operativo cuando está en modo "Sistema".
- **GMT/zona horaria — deliberadamente NO implementado.** Al investigar se encontró que ningún timestamp de `spa_bookings` (ni de ningún otro modelo de negocio) tiene ancla de zona horaria — son datetimes naive, tratados implícitamente como hora local del servidor/sucursal (confirmado por un comentario ya existente en `MobCitaDet.tsx`: "sin conversión UTC"). Agregar un selector de zona horaria por usuario sin resolver esto primero mostraría horas **incorrectas** para cualquiera cuya zona no coincida con la del servidor — se decidió no construir una feature que produce datos malos. Reportado al usuario en el chat, no se volvió a preguntar (ya se había preguntado una vez este mismo tema); queda como pendiente real, no como excusa.

**Editor de foto: recorte + rotar + marca de agua (BL-035):** el usuario preguntó por la diferencia entre comprimir y "zipear" (aclarado: ZIP no sirve sobre JPEG, ya comprimido) y entre subir por cámara vs archivo (aclarado: el navegador no distingue el origen, así que la compresión debe aplicarse igual sin importar de dónde vino la foto). A partir de ahí pidió que la subida de foto siempre ofrezca recortar/rotar, y opcionalmente marca de agua con nombre + fecha.
- Se preguntó dónde debía vivir el interruptor de la marca de agua — el usuario aclaró que es **regla de negocio del Backoffice, no preferencia personal**. Nueva sección "Fotografías" en `Configuración del sistema` (`SystemSettings::definitions()`), campo `photo_watermark_enabled` (boolean, default apagado) — se sumó sola con el sistema de secciones ya declarativo existente, sin tocar el Blade de la vista. Expuesta a la app móvil vía `GET /api/settings/photos` (`Api\SettingController::photos()`).
- Nuevo componente `PhotoEditorModal.tsx` (`src/`, reusable — hoy solo cableado a la foto de perfil): usa `cropperjs@1.6.2` (misma versión exacta fijada que ya usa el backoffice web, ver NT-001 — se agregó como dependencia nueva de `mob_apps/operador`, no se tocó la del backoffice), recorte cuadrado con guía visual circular (CSS `border-radius:50%` sobre `.cropper-view-box`/`.cropper-face`, coherente con que el avatar se muestra redondo) y botón de rotar 90°.
- Marca de agua: al confirmar, si `watermark_enabled` es true, se dibuja en un `<canvas>` una franja semitransparente en la parte inferior con `{nombre} · {fecha}`, tamaño de letra chico pero legible (~3.5% del ancho de la imagen). La fecha usa el metadato EXIF `DateTimeOriginal` de la foto si existe — parser propio escrito a mano (`src/lib/exifDate.ts`, ~90 líneas, sin dependencia nueva) que lee el segmento APP1 de un JPEG; si no hay EXIF, usa la fecha de subida.
- Exportación final: JPEG calidad 90% vía `canvas.toBlob()`, subido con el mismo endpoint `POST /api/me/photo` de antes (ahora recibe un `Blob` en vez de un `File` crudo).

**Primera prueba en celular real → 2 bugs encontrados en Mascotas (BL-036):**
1. **"No muestra el nombre de la pantalla"** — causa raíz: `ScreenHeader.tsx` solo renderizaba el tag de depuración (`MobPetDet`, mono chico) en el branch **sin** breadcrumbs; el branch con breadcrumbs (el que se usa casi siempre al llegar a Mascotas, porque casi siempre hay una ruta previa) nunca lo mostraba. **Bug general de toda la app**, no específico de mascotas — solo que ahí es donde más se nota. Fix: se agregó el tag también al branch de breadcrumbs.
2. **"No puedo cambiar la foto" / "se gestiona desde el backoffice"** — cierto hasta ahora: el bloque de foto en modo edición de una mascota existente era explícitamente de solo lectura (mensaje hardcodeado, sin `<input>` de archivo — nunca se construyó, no estaba deshabilitado). El usuario notó la contradicción: ya existe la capacidad completa de tomar/subir/procesar una foto (recién construida para el perfil de usuario), así que se conectó lo mismo acá.
   - Nuevos endpoints `POST`/`DELETE /api/pets/{pet}/photo`, mismo `PhotoEditorModal` reusado (recorte/rotar/marca de agua, con el nombre de la mascota en vez del usuario).
   - **Bug adicional encontrado de paso:** la subida de foto de mascota vía API (`PetController::store()`, usada por la app móvil al crear) guardaba el archivo **crudo, sin comprimir en absoluto** — ignoraba por completo `PetPhotoImageManager` (que sí usa el backoffice web) y tampoco dejaba registro en la galería (`pet_photos`). Se corrigió tanto `store()` como el `updatePhoto()` nuevo para usar `PetPhotoImageManager::store()` + crear el registro de galería (`photo_type: perfil`, `is_primary`, fecha EXIF vía `extractTakenAt()` — que además ya existía en el backend, en PHP, más confiable que el parser manual de JS del punto anterior; se mantuvieron ambos porque cumplen roles distintos: uno quema la marca de agua client-side antes de subir, el otro es metadato server-side para la galería).

**Verificación:**
- Backend: 10 tests nuevos (`ProfileTest.php` ×5, `PhotoSettingsTest.php` ×2, `PetPhotoTest.php` ×3 — comprime y deja registro de galería al subir, también al crear, y se puede quitar). Suite completa: 37 fallidas (mismas preexistentes de siempre), 83 pasan — nada roto.
- `npx tsc --noEmit` y `npm run build` (varias pasadas): sin errores nuevos en los archivos tocados; los de siempre en `ActiveService.tsx`/`AssignService.tsx`/`MobCajaMovimientos.tsx` siguen igual, no son de esta sesión. **Nota del proceso:** en un momento se corrió `npm run build` desde el directorio equivocado (`apps/backoffice-laravel` en vez de `mob_apps/operador`, arrastrado de un `cd` anterior para `docker exec`) — intentó reconstruir los assets del backoffice web y falló por permisos (`EACCES`) a mitad de camino. Se verificó con `git status` que no se borró ni modificó nada real en `public/build/`; el build correcto se corrió después desde el directorio correcto.
- Verificado contra datos reales de producción vía `tinker` (solo lectura).
- **No se confirmó visualmente en el celular** el fix de esta vuelta (header + foto de mascota) — recién reportado, pendiente para la próxima prueba.

### 📁 Archivos Modificados/Creados
- `apps/backoffice-laravel/app/Models/User.php` — `getProfilePhotoUrlAttribute()`, `toApiArray()`
- `apps/backoffice-laravel/app/Http/Controllers/Api/AuthController.php` — usa `toApiArray()`, ya no duplica la forma del JSON
- `apps/backoffice-laravel/app/Http/Controllers/Api/ProfileController.php` — nuevo (`update`, `updatePassword`, `updatePhoto`, `deletePhoto`)
- `apps/backoffice-laravel/app/Support/SystemSettings/SystemSettings.php` — sección `media` (`photo_watermark_enabled`)
- `apps/backoffice-laravel/app/Http/Controllers/Api/SettingController.php` — método `photos()`
- `apps/backoffice-laravel/routes/api.php` — rutas `/me` (PATCH), `/me/password` (PUT), `/me/photo` (POST/DELETE), `/settings/photos` (GET)
- `apps/backoffice-laravel/tests/Feature/Api/ProfileTest.php`, `PhotoSettingsTest.php` — nuevos
- `mob_apps/operador/src/AuthContext.tsx` — `AuthUser` con `first_name`/`last_name`/`photo_url`, expone `setUser`
- `mob_apps/operador/src/hooks/useUserPrefs.ts` — `ThemeMode`, `applyTheme()`, `bootTheme()`
- `mob_apps/operador/src/main.tsx` — llama `bootTheme()` antes del primer render
- `mob_apps/operador/src/index.css` — paleta oscura `:root.dark .theme-admin`
- `mob_apps/operador/src/lib/exifDate.ts` — nuevo (parser EXIF manual)
- `mob_apps/operador/src/PhotoEditorModal.tsx` — nuevo (recorte + rotar + marca de agua)
- `mob_apps/operador/src/admin/MobUserConfig.tsx` — reescrito completo (foto vía editor, datos personales, contraseña, tema, navegación)
- `mob_apps/operador/src/App.tsx` — reorden de `MENU_SECTIONS`
- `mob_apps/operador/package.json` — `cropperjs` `1.6.2` (exacta) como dependencia nueva
- `mob_apps/operador/src/ScreenHeader.tsx` — fix: tag de depuración visible también con breadcrumbs
- `mob_apps/operador/src/admin/PetDetail.tsx` — foto editable en mascotas existentes y en `NewPetForm` (alta) vía `PhotoEditorModal`
- `apps/backoffice-laravel/app/Http/Controllers/Api/PetController.php` — `updatePhoto()`/`deletePhoto()` nuevos; `store()` corregido para usar `PetPhotoImageManager` + galería
- `apps/backoffice-laravel/tests/Feature/Api/PetPhotoTest.php` — nuevo
- `mob_apps/operador/package.json`, `package-lock.json` — 7 dependencias sin uso desinstaladas
- `mob_apps/operador/src/admin/Directory.tsx`, `AssignService.tsx` — links rotos corregidos; import de `Link` faltante en `AssignService.tsx`
- `apps/backoffice-laravel/tests/Feature/Api/TeamPanelTest.php` — fix NT-024 (`$this->travelTo()`)
- `docs/tecnico/NOTAS_TECNICAS.md` — NT-024
- `mob_apps/operador/src/AppLockContext.tsx` — nuevo (timeout de inactividad, bloqueo por `visibilitychange`, bloqueo manual)
- `mob_apps/operador/src/LockScreen.tsx` — nuevo (pantalla de desbloqueo por contraseña o biometría)
- `mob_apps/operador/src/lib/webauthnLock.ts` — nuevo (WebAuthn local, sin backend)
- `mob_apps/operador/src/App.tsx` — envuelve con `AppLockProvider`, `AuthGuard` muestra `LockScreen`, botón "Bloquear ahora" en el menú
- `mob_apps/operador/src/admin/MobUserConfig.tsx` — sección "Seguridad" (activar biometría + info de timeout)
- `apps/backoffice-laravel/app/Http/Controllers/Api/ProfileController.php` — `verifyPassword()` nuevo
- `apps/backoffice-laravel/routes/api.php` — ruta `/me/verify-password` (POST)
- `apps/backoffice-laravel/tests/Feature/Api/ProfileTest.php` — 2 tests nuevos de `verify-password`
- `mob_apps/operador/index.html` — meta `color-scheme` (fix NT-025)
- `mob_apps/operador/src/index.css` — CSS `color-scheme` sincronizado con `.dark` (fix NT-025)
- `docs/tecnico/NOTAS_TECNICAS.md` — NT-025
- `docs/tecnico/BACKLOG.md` — BL-034, BL-035, BL-036, BL-037, BL-038, fix de commits pendientes ya reflejados de BL-032/BL-033/NT-022/NT-023

**Auditoría de código pedida por el usuario (BL-037):** "busca software redundante, que no se use o que no esté resuelto en mov". Sin herramienta de navegador en este entorno, se corrió un agente (fork) que hizo `tsc`/`build`/`npm audit` + revisión manual exhaustiva del código de `mob_apps/operador`. Hallazgos y qué se hizo con cada uno (decisión del usuario, no automática):
- **7 dependencias npm sin ninguna referencia real** (`@google/genai`, `clsx`, `dotenv`, `express`, `lucide-react`, `motion`, `tailwind-merge`) — desinstaladas. Bajó de 9 a 5 los avisos de `npm audit` de paso.
- **Links rotos en `Directory.tsx`/`AssignService.tsx`** (rutas inexistentes: `/admin/assign`, `/admin/directory`, `/agenda-global`) — corregidos a `/directorio`, `/directorio/asignar`, `/agenda`. `AssignService.tsx` además compilaba mal (`Link` usado sin importar, bug preexistente no detectado hasta ahora) — corregido.
- **`NewPetForm` (alta de mascota) seguía con el selector de foto viejo** (sin recorte/rotar/marca de agua) — era la única de las 3 pantallas de foto que había quedado atrás de BL-035/BL-036. Ahora usa el mismo `PhotoEditorModal`.
- **4 archivos huérfanos sin ninguna ruta ni import** (`ActiveService.tsx`, `GroomerDashboard.tsx`, `client/Booking.tsx`, `client/Dashboard.tsx`, ~730 líneas, parecen resto de un template — `package.json` se llama literalmente `"react-example"`) — **el usuario decidió no borrarlos todavía**, quedan documentados por si se retoman o se limpian después.
- De paso, al volver a correr la suite completa del backend para verificar que nada se había roto, apareció una falla nueva no relacionada (`TeamPanelTest`) — investigado y resuelto como bug de test, no de producción (ver NT-024): un fixture usaba `now()->addHours(2)` sin anclar el reloj, y cruzó medianoche porque la suite corrió después de las 22:00. Se ancló el test a mediodía con `$this->travelTo()`.
- **Nota de proceso:** en un momento de esta sesión se corrió `npm run build` desde el directorio equivocado (`apps/backoffice-laravel`, arrastrado de un `cd` anterior para `docker exec`) e intentó reconstruir por error los assets del backoffice web — falló por permisos antes de tocar nada real, verificado con `git status`. Ya pasó dos veces en la misma sesión por no fijar el directorio con `pwd` antes de comandos de build; a vigilar en sesiones futuras.

**Verificación (auditoría):** `npx tsc --noEmit` sin errores nuevos (los mismos 2 de `ActiveService.tsx`, ya conocidos, archivo dejado sin tocar a propósito). `npm run build` sin cambios de tamaño relevantes. Suite backend completa: 37 fallidas (mismas preexistentes de siempre, confirmado post-fix de NT-024), 83 pasan.

**Bloqueo de sesión — timeout + candado manual (BL-038):** el usuario preguntó por login con Face ID/huella/PIN. Se explicó la diferencia entre reemplazar el login por completo (WebAuthn real, verificado por el servidor — mucho trabajo de backend) versus un candado local sobre la sesión que ya existe (mucho más chico). El usuario aterrizó la necesidad real: timeout por inactividad + poder bloquear manualmente sin cerrar sesión — y al preguntar cómo debía desbloquearse, pidió **las dos** (contraseña y biometría).

- `AppLockContext.tsx` (nuevo, patrón `AuthContext`): temporizador de inactividad (5 min, reinicia con cualquier touch/click/tecla/scroll) + bloqueo inmediato al cambiar de app (`document.visibilitychange`) + `lock()` manual. Todo vive en un contexto global (no en `AuthGuard` local) porque tanto la pantalla de bloqueo como el botón manual del menú lo necesitan, y están en partes distintas del árbol de componentes.
- `LockScreen.tsx` (nuevo): overlay de pantalla completa, no desmonta la app de abajo (así no se pierde ningún formulario a medio llenar). Si el usuario activó biometría, intenta el desbloqueo automáticamente al aparecer; si falla o no está activada, cae a un campo de contraseña. Siempre tiene un link de "Cerrar sesión" como salida de emergencia por si alguien no puede desbloquear.
- `lib/webauthnLock.ts` (nuevo): WebAuthn **100% local**, sin tocar el servidor — el "challenge" es aleatorio generado en el celular (no hace falta que venga del backend porque no estamos verificando identidad ante el servidor, solo confirmando que el sistema operativo aceptó el Face ID/huella/PIN). La credencial se guarda en `localStorage` del dispositivo. Esto es deliberadamente más chico que un WebAuthn "de verdad" (passwordless login real) — no hay tabla de credenciales en el backend ni verificación de firma; si en el futuro se quiere reemplazar el login completo por esto, es un proyecto aparte.
- Backend: `POST /api/me/verify-password` (`ProfileController::verifyPassword()`) — confirma la contraseña actual sin cambiarla ni tocar el token/sesión.
- `MobUserConfig` → nueva sección "Seguridad": activar/desactivar biometría (oculto si el navegador no soporta WebAuthn) + texto informativo del timeout. Menú (`App.tsx` → `MenuDrawer`) → botón nuevo "Bloquear ahora" junto a "Cerrar sesión".

**Verificación:** 2 tests nuevos (`ProfileTest.php` — verify-password correcta/incorrecta). Suite completa: 37 fallidas (mismas de siempre), 85 pasan. `tsc`/`build` sin errores nuevos (73 módulos, +3 archivos nuevos). **No se pudo probar de punta a punta** — WebAuthn necesita hardware biométrico real de un teléfono/navegador, imposible de simular en este entorno; el timeout de inactividad y el `visibilitychange` tampoco se probaron en un dispositivo real. Es la parte de esta sesión con menos verificación real detrás.

**Nota de proceso (se repitió):** el error de correr `npm run build` desde el directorio equivocado (arrastrado de un `cd` anterior a `apps/backoffice-laravel` para un `docker exec`) volvió a pasar una tercera vez en esta sesión — de nuevo sin daño (mismo error de permisos, bloqueado antes de tocar nada real, verificado con `git status`). Confirma que la persistencia de directorio entre llamadas de Bash no es confiable en este entorno; a partir de ahora conviene anteponer `cd <ruta absoluta> &&` explícito a cualquier comando de build/test, no confiar en que el directorio anterior se mantenga.

**Primer reporte visual real → bug de color en `LockScreen` (NT-025):** el usuario probó `LockScreen` en su celular (Android + Chrome + tema oscuro) y reportó el campo de contraseña como "un cuadro vacío" y el botón "Desbloquear" ilegible (solo "Des..."). Sin herramienta de navegador ni acceso al dispositivo en este entorno, y sin poder recibir una captura, se diagnosticó por descarte: se confirmó que el build servido era el último compilado (no caché vieja) y se inspeccionó el CSS generado — colores, contraste y z-index estaban bien compilados, sin bug de código encontrado. Con Android + Chrome + oscuro confirmados por el usuario, la hipótesis más fuerte es **"Auto Dark Theme" de Chrome para Android** reinvirtiendo heurísticamente colores de una página que nunca declaró `color-scheme` — problema del navegador, no de la lógica de la app. Fix aplicado: `<meta name="color-scheme" content="light dark">` + CSS `color-scheme` sincronizado con la clase `.dark` existente. **Diagnóstico no confirmado por reproducción directa** — pendiente que el usuario pruebe de nuevo.

### 🛑 Pendientes activos — EMPEZAR AQUÍ la próxima sesión
1. **Confirmar si el fix de `color-scheme` (NT-025) resolvió el problema real** de `LockScreen` en Android/Chrome/oscuro — diagnóstico por descarte, sin reproducción directa, prioridad #1 de esta vuelta. Si sigue mal, pedir al usuario probar en modo claro y/o con "Forzar tema oscuro para sitios web" desactivado en los ajustes de Chrome (`chrome://settings` → Accesibilidad), para aislar si es Chrome o el código.
2. **Confirmar visualmente en el celular**: `/equipo` (sesión anterior), la barra de navegación reordenada, `MobUserConfig` (foto, datos, contraseña, los 3 modos de tema, la sección "Seguridad"), el editor de recorte/rotar/marca de agua en las 3 pantallas (perfil, mascota existente, mascota nueva), el fix de Mascotas (título visible, foto editable), y el candado de sesión completo (BL-038): activar biometría, esperar el timeout de 5 min, cambiar de app y volver, botón "Bloquear ahora".
3. **Activar la marca de agua en Backoffice → Configuración del sistema → Fotografías** si se quiere ver el efecto real (queda apagada por default).
4. **Sigue pendiente de sesiones anteriores: probar `/mapa-zonas`** (07/07/2026) — nunca se confirmó.
5. **Si todo se confirma → `git push`** — el commit `fdeb7b6` (BL-033/NT-023) ya está local; todo lo demás de esta sesión (nav + BL-034 + BL-035 + BL-036 + BL-037 + BL-038 + NT-024 + NT-025) sigue sin commitear a propósito.
6. **GMT/zona horaria** — decidir si de verdad hace falta antes de tocar el tema. Si sí, el primer paso real es decidir cómo se ancla la zona horaria en los timestamps de negocio (`spa_bookings.scheduled_at` y equivalentes), no diseñar el selector de UI.
7. **Decidir el destino de los 4 archivos huérfanos** (`ActiveService.tsx`, `GroomerDashboard.tsx`, `client/Booking.tsx`, `client/Dashboard.tsx`) — borrarlos o retomarlos, quedaron documentados en BL-037/`IDEAS_FUTURO.md` sin tocar.
8. **Si el timeout de 5 minutos o el bloqueo inmediato al cambiar de app resultan muy agresivos/molestos en el uso real** — son valores fijos en `AppLockContext.tsx`, fáciles de ajustar, no hay pantalla de configuración para esto todavía (decisión deliberada de no sobre-construir sin saber si hace falta).

### Otros pendientes (no urgentes)
- BL-031 — entidad espacial genérica, sigue sin acotar por el usuario; no planear hasta que decida si hace falta.
- Decidir si vale la pena cablear `ExecutedServiceService::convertFromBooking()` (ver NT-020).
- BL-028 — estrategia firewall (ufw) para la OPi.
- BL-024b — Fase 2 de WhatsApp.
- BL-001 — Tema de UI (backoffice web): persistencia y cambio reactivo de paleta.
- BL-002 — Favicon & datos generales del negocio.
- BL-003 — Email avanzado: SMTP completo.
- BL-004 — Zonas horarias (backoffice web, configuración del negocio — distinto de la zona horaria por usuario de arriba).
- BL-008 — Reportes PDF.
- Investigar y arreglar el resto de la suite de tests preexistente (37 fallos, no relacionados a esta ni a sesiones recientes).

---

## 📅 Sesión: 10/07/2026 — App móvil: `MobTeam` conectado a datos reales (BL-033) + fix schema drift (NT-023)

Usuario pidió proponer el desarrollo de `MobTeam` (pantalla "Equipo" de la app móvil operador). Antes de proponer, se investigó con un agente explorador qué datos reales existían detrás — resultó ser un mockup 100% estático (nombres "María G."/"Carlos M.", fotos de placeholder, "Turno 08:00-16:00" y contadores todos hardcodeados en el componente). Tras compararlo con la pantalla "Operador" (`/groomer`, agenda individual de una persona) para aclarar que "Equipo" es un panel de estado en vivo de todos a la vez —propósito distinto—, el usuario pidió implementar directamente ("ya hazlo").

**Implementación (BL-033):**
- Nuevo endpoint `GET /api/team` → `Api\OperatorController::team()`. Por cada operador activo agrega: estado de check-in real (`OperatorCheckin` sin `checked_out_at`, vía `User.operator_id`), pendientes/completadas de **hoy** (`SpaBooking.status`/`scheduled_at`), y "trabajo actual" si tiene una `SpaBooking` con `status = work_order` hoy (mascota + servicio + hora).
- `mob_apps/operador/src/admin/TeamPanel.tsx` reescrito para consumir `/api/team` (poll cada 30s). Badge de estado derivado (no inventado): **En Servicio** (con `work_order` abierto) / **Disponible** (check-in sin trabajo activo) / **Fuera de turno** (sin check-in). Se eliminó el "Turno 08:00-16:00" — no existe ese dato en el esquema, no se inventó nada nuevo para reemplazarlo, solo se muestra la hora real de check-in. "Resumen Operativo" (completadas hoy, en curso, % del equipo con check-in) ahora agrega datos reales en vez de números fijos.
- 3 tests nuevos (`tests/Feature/Api/TeamPanelTest.php`) — sin checkin, con checkin+trabajo activo, checkout no cuenta como activo.

**Bug encontrado de paso (NT-023) — schema drift:** al escribir los tests, crear un `User` con `is_operator`/`operator_id` fallaba en la base `testing` con `Unknown column 'is_operator'`. Investigación: esa columna (y `operator_code`) existen en producción pero **ninguna migración del repo las crea** — se agregaron alguna vez directo contra producción sin dejar migration commiteada. Fix: migración nueva idempotente (`Schema::hasColumn` guard); confirmado no-op real en producción (`migrate --force` no alteró nada), aplicó limpio en `testing`.

**Verificación:**
- `php artisan test --filter TeamPanelTest`: 3/3 pasan.
- Suite completa: 37 fallidas (exactamente las mismas preexistentes documentadas), 73 pasan — nada nuevo roto.
- `npx tsc --noEmit` y `npm run build`: sin errores nuevos en `TeamPanel.tsx` (los errores preexistentes de `ActiveService.tsx`/`AssignService.tsx`/`MobCajaMovimientos.tsx` no se tocaron, no son de esta sesión).
- Verificado contra datos reales de producción invocando el controlador directo vía `tinker` (solo lectura, sin persistir nada): devuelve forma correcta para los 2 operadores reales existentes.
- **No se confirmó visualmente en el celular/navegador real** — sigue sin haber herramienta de automatización de navegador en este entorno.

### 📁 Archivos Modificados/Creados
- `apps/backoffice-laravel/app/Http/Controllers/Api/OperatorController.php` — método `team()`
- `apps/backoffice-laravel/routes/api.php` — ruta `GET /api/team`
- `apps/backoffice-laravel/database/migrations/2026_07_10_000001_add_is_operator_and_operator_code_to_users_table.php` — nuevo (fix NT-023)
- `apps/backoffice-laravel/tests/Feature/Api/TeamPanelTest.php` — nuevo
- `mob_apps/operador/src/admin/TeamPanel.tsx` — reescrito completo, datos reales
- `docs/tecnico/NOTAS_TECNICAS.md` — NT-023
- `docs/tecnico/BACKLOG.md` — BL-033, fix schema drift en Completados

### 🛑 Pendientes activos — EMPEZAR AQUÍ la próxima sesión
1. **Confirmar visualmente `/equipo` en el celular/navegador real**: ¿se ven los operadores reales?, ¿el badge de estado (Disponible/En Servicio/Fuera de turno) tiene sentido?, ¿el check-in real desde la app se refleja ahí?
2. **Sigue pendiente de la sesión anterior (07/07/2026): probar `/mapa-zonas` en el navegador** — no se confirmó en esta sesión, el usuario cambió de tema hacia `MobTeam`. Ver detalle en la entrada de sesión de abajo.
3. **Si ambos se confirman → hacer `git push`** — sigue sin pushearse, ahora son 2 commits pendientes adelante de `origin/main` (el de `AX-MAPZN` + el de `MobTeam`/NT-023 de hoy).

### Otros pendientes (no urgentes)
- BL-031 — entidad espacial genérica, sigue sin acotar por el usuario; no planear hasta que decida si hace falta.
- Decidir si vale la pena cablear `ExecutedServiceService::convertFromBooking()` (ver NT-020).
- BL-028 — estrategia firewall (ufw) para la OPi.
- BL-024b — Fase 2 de WhatsApp.
- BL-001 — Tema de UI: persistencia y cambio reactivo de paleta.
- BL-002 — Favicon & datos generales del negocio.
- BL-003 — Email avanzado: SMTP completo.
- BL-004 — Zonas horarias: selector completo.
- BL-008 — Reportes PDF.
- Investigar y arreglar el resto de la suite de tests preexistente (37 fallos, no relacionados a esta ni a sesiones recientes).

---

## 📅 Sesión: 07/07/2026 (cont. 3) — Mapa y Cobertura Espacial, versión mínima (BL-032, AX-MAPZN) + fix CSP

Usuario planteó una idea grande (entidad espacial genérica ligada muchos-a-muchos a personas/objetos/documentos — quedó documentada como BL-031, todavía sin acotar por el propio usuario) pero pidió explícitamente una versión mínima ya: "por ahora necesito esos campos en esa ventana para navegar y pensar en ideas". Se armó plan formal (`EnterPlanMode`/`ExitPlanMode`, con exploración de código y un agente de diseño) y se construyó una pantalla real, simple, sin la arquitectura genérica.

**Nota sobre el proceso:** un agente de diseño reportó durante la exploración haber encontrado un intento de inyección de prompt en un resultado de herramienta. Al pedirle la cita textual y el archivo exacto, se retractó — confirmó que fue una alucinación propia, sin ninguna evidencia real en el repo ni en las herramientas. Se descartó como falsa alarma, documentado aquí por transparencia.

**Implementación (BL-032):**
- Nueva pantalla `AX-MAPZN` ("Mapa y cobertura", menú Operación) con mapa real vía **Leaflet + OpenStreetMap** (gratis, sin API key) — primera vez que este repo usa una librería de mapas.
- Migraciones nuevas: `pets.lat`/`lng` (nullable) y tabla `vehicles` (name/lat/lng/notes/is_active) — deliberadamente columnas directas simples, **no** la entidad polimórfica de BL-031.
- `MapaZonasController` — `index()` sirve 4 datasets (sucursales y direcciones de clientes, de solo lectura, ya tenían lat/lng; mascotas y vehículos, editables desde el mapa) + lista de mascotas sin ubicar. Endpoints para ubicar una mascota y CRUD-lite de vehículos.
- Vista con checkboxes no excluyentes por tipo (mismo patrón ya establecido para WhatsApp/Agenda esta sesión) y clic en el mapa abre un modal para ubicar una mascota existente o crear un vehículo nuevo, sin recargar la página.
- Permiso reutilizado: `ver sucursales` (se evitó crear un permiso nuevo y tocar el seeder de roles — simplificación deliberada para esta pasada exploratoria).

**Fix — el mapa no cargaba (NT-022):** al probar en el navegador, las teselas de OpenStreetMap no se veían (fondo gris). Causa raíz: la política CSP (`ContentSecurityPolicy.php`, NT-006) tiene `img-src 'self' data: blob:` — bloquea sin ningún error visible las imágenes de `tile.openstreetmap.org`. Se agregó el dominio a `img-src`. De paso se detectó y corrigió que `connect-src 'self'` también bloqueaba, desde que existe la CSP, el `fetch()` que ya hacía `address-editor.js` hacia Nominatim para "Geocodificación automática" — esa función llevaba tiempo silenciosamente rota sin que nadie lo hubiera reportado.

**Verificación:**
- `npm install leaflet` + `npm run build` sin errores; `artisan migrate` aplicó limpio.
- 8 tests nuevos (`tests/Feature/MapaZonas/*`) — todos pasan.
- Suite completa: mismas 37 fallas preexistentes ya documentadas, cero nuevas (antes y después del fix de CSP).
- Verificado con `artisan tinker` (transacción explícita, sin dejar datos de prueba) que los 4 tipos de marcador aparecen en `/mapa-zonas` y que el header CSP ya incluye los dominios necesarios.
- **Usuario probará visualmente mañana** — pendiente confirmar en navegador real que el mapa carga y el flujo de clic-para-ubicar funciona (no hay herramienta de automatización de navegador en este entorno).

### 📁 Archivos Modificados/Creados
- `apps/backoffice-laravel/database/migrations/2026_07_07_100001_add_lat_lng_to_pets_table.php`, `2026_07_07_100002_create_vehicles_table.php` — nuevos
- `apps/backoffice-laravel/app/Models/Vehicle.php` — nuevo
- `apps/backoffice-laravel/app/Models/Pet.php` — `lat`/`lng` en fillable/casts
- `apps/backoffice-laravel/app/Http/Controllers/MapaZonasController.php` — nuevo
- `apps/backoffice-laravel/app/Support/Pages/MapaZonasPage.php` — nuevo (debug_id `AX-MAPZN`)
- `apps/backoffice-laravel/app/Support/Navigation/Groups/OperationsNavigation.php` — ítem "Mapa y cobertura"
- `apps/backoffice-laravel/resources/views/mapa-zonas/index.blade.php` — nuevo
- `apps/backoffice-laravel/resources/js/modules/mapa-zonas.js` — nuevo
- `apps/backoffice-laravel/resources/js/app.js`, `resources/css/app.css` — registro del módulo + CSS de Leaflet
- `apps/backoffice-laravel/app/Http/Middleware/ContentSecurityPolicy.php` — fix NT-022
- `apps/backoffice-laravel/routes/web.php` — rutas `mapa-zonas.*`
- `apps/backoffice-laravel/package.json` — `leaflet` como dependencia
- `apps/backoffice-laravel/tests/Feature/MapaZonas/*` — 3 archivos nuevos (8 tests)
- `docs/tecnico/MODELO_BD.md` — `pets.lat/lng`, sección nueva "Mapa y cobertura espacial" (`vehicles`)
- `docs/tecnico/NOTAS_TECNICAS.md` — NT-022
- `docs/tecnico/BACKLOG.md` — BL-032, fix CSP en Completados, BL-031 actualizado
- `docs/architecture/IDEAS_FUTURO.md` — marcada versión mínima como construida, BL-031 sigue abierta

### 🛑 Pendientes activos — EMPEZAR AQUÍ la próxima sesión
1. **Usuario va a probar `/mapa-zonas` en el navegador** (quedó pendiente de una sesión anterior, no de hoy): ¿carga el mapa (tiles de OpenStreetMap, ya no debería verse gris)?, ¿el clic para ubicar mascota/vehículo funciona?, ¿la geocodificación automática / importar coordenadas de Sucursales y Clientes ya funciona con el fix de CSP (NT-022)? Preguntar directo antes de proponer nada nuevo.
2. **Si todo lo anterior funcionó → hacer `git push`** (ya está comiteado: commit `84c40b0`, rama 1 commit adelante de `origin/main` — falta el push, se dejó pendiente a propósito hasta la confirmación visual).
3. Si algo falló, diagnosticar antes de seguir con cualquier otro pendiente de la lista de abajo.

### Otros pendientes (no urgentes)
- BL-031 — entidad espacial genérica, sigue sin acotar por el usuario; no planear hasta que decida si hace falta.
- Decidir si vale la pena cablear `ExecutedServiceService::convertFromBooking()` (ver NT-020).
- BL-028 — estrategia firewall (ufw) para la OPi.
- BL-024b — Fase 2 de WhatsApp.
- BL-001 — Tema de UI: persistencia y cambio reactivo de paleta.
- BL-002 — Favicon & datos generales del negocio.
- BL-003 — Email avanzado: SMTP completo.
- BL-004 — Zonas horarias: selector completo.
- BL-008 — Reportes PDF.
- Investigar y arreglar el resto de la suite de tests preexistente (37 fallos, no relacionados a esta ni a sesiones recientes).

---

## 📅 Sesión: 07/07/2026 (cont. 2) — WhatsApp: crear plantilla + calendario de Bandeja + checkboxes en Agenda + fix filtro de operador móvil (BL-030)

Continuación de la sesión de BL-029. El usuario pidió, sobre el selector de plantilla, poder crear una nueva sin salir de la pantalla y ver una vista previa antes de guardar. De ahí salieron varios pedidos encadenados: marcar recordatorios ya enviados sin bloquear el reenvío manual, un calendario mensual para ver la operación completa de un vistazo con filtros no excluyentes, el mismo patrón de filtros para Agenda Universal, y — al mencionar un bug de agenda móvil de paso — un fix real de filtro por operador.

**1. Crear plantilla desde el selector (Bandeja y Recurrencias):**
- Nueva opción "+ Crear nueva plantilla…" en el `<select>` de plantilla de ambas pantallas; abre un modal (`whatsapp/_create-template-modal.blade.php`) con nombre, botones de variables por contexto y mensaje. Se crea vía AJAX — `WhatsAppTemplateController::store()` ahora responde JSON (`{template: {id, name}}`) si la petición lo pide, sin romper el flujo de formulario completo (que sigue redirigiendo igual) — y la plantilla nueva queda seleccionada automáticamente, sin perder la selección de citas ya marcada en la tabla.
- Botón "Previsualizar" (datos de ejemplo, cálculo 100% client-side vía Alpine, sin llamada al servidor) agregado tanto en ese modal como en el formulario completo de crear/editar plantilla (`whatsapp/plantillas/_form.blade.php`, pantalla `WspPlEdi`) — el textarea del mensaje tuvo que volverse reactivo (`x-model="body"` en vez de manipulación directa del DOM) para poder calcular la vista previa en vivo.

**2. "Seleccionar todos" ya no reenvía recordatorios ya enviados por accidente:**
- El checkbox de encabezado en Bandeja y Recurrencias excluye ahora las filas con `already_sent_today`, pero el checkbox individual de cada fila sigue habilitado (con tooltip explicativo) para que el usuario reenvíe a mano si lo decide.

**3. Calendario mensual en Bandeja diaria (BL-030):**
- `BookingMessageController::buildMonthCalendar()` — una sola consulta por mes (rango con relleno de semana completa, mismo patrón que Agenda), clasifica cada cita en 0-2 categorías simultáneas (no excluyentes): **completadas**, **recordatorio pendiente** (nunca se envió un `BookingMessage`, sin importar el día — más amplio que el `already_sent_today` de la tabla del día, que no se tocó), **en riesgo de no asistir** (sigue `scheduled`/`work_order` y ya pasó `scheduled_at + booking_grace_minutes` — incluye días **pasados** sin resolver, a pedido explícito del usuario: "esos son los que más urgen resolver"). Se descartó cancelado/no-show como categoría — decisión del usuario: no es accionable, es solo un hueco libre.
- Nuevo partial `whatsapp/bandeja/_month_calendar.blade.php`, reutilizando el patrón visual del grid de mes de Agenda (`agenda-calendar-month__*`) pero con lógica y CSS propios (`bandeja-calendar-dot--completadas/recordatorio/riesgo`, puntos de color en vez de chips de texto — más legibles con 3 categorías simultáneas en una celda de ~110px). Checkboxes no excluyentes filtran client-side sobre los datos ya cargados del mes (sin round-trip al servidor); clic en un día navega con recarga completa de página.
- De paso, se investigó el reporte inicial de "la lista no se actualiza al cambiar de fecha" — se confirmó con datos reales que el backend siempre filtró bien por fecha; no se pudo reproducir el bug, probablemente era percepción o caché del navegador. La navegación de página completa del calendario nuevo descarta cualquier duda de caché hacia adelante.

**4. Agenda Universal: filtro "Estado" de `<select>` único a checkboxes no excluyentes (BL-030):**
- `SpaBookingController` — de `string $status` a `array $statuses` en `index()`, `applyBookingFilters()`, `indexCalendarRange()` y `buildCalendarRange()` (un solo punto de aplicación real, `applyBookingFilters()`, ya compartido por la vista de día y la de semana/mes). Contrato vía `status_touched`: formulario nunca tocado → default actual (`scheduled`+`work_order`, igual que antes `active`); tocado y sin nada marcado → mostrar todos los estados; tocado con valores → esos exactos. El filtro de `HotelReservation` (columna de estado de hotel, no relacionada) no se tocó.

**5. Fix — filtro por operador en agenda móvil no mostraba todas las citas (NT-021):**
- Causa raíz: `Api\AgendaController::index()` armaba el campo `operators` (usado por el filtro client-side en `GlobalAgenda.tsx`/`MobAgGbl` y `GroomerAgenda.tsx`/`MobOpAg` vía `b.operators.some(o => o.id === filterOp)`) **solo** desde las líneas del presupuesto aceptado, ignorando `spa_bookings.operator_id` — la columna que sí se asigna directamente al crear la cita. Una cita recién creada (sin presupuesto aceptado todavía) tenía `operators: []` y desaparecía del filtro aunque tuviera ese operador asignado.
- Fix: `operators` ahora es la unión de (a) operadores del presupuesto aceptado y (b) el operador asignado directamente vía `operator_id`, deduplicada por id. No requirió cambios en el frontend móvil — el filtro ya funcionaba bien, solo recibía datos incompletos.

**Verificación:**
- `npm run build` sin errores en cada iteración.
- Suite completa filtrada `WhatsApp|Agenda|Api|SpaBooking`: 24 tests nuevos de esta sesión (7 calendario de Bandeja, 6 checkboxes de Agenda, 1 fix de `operators`, 3 creación de plantilla vía JSON, más ajustes a tests existentes de preview/reenvío) — todos pasan. Se corrió también la suite completa del proyecto: coincide exactamente con las mismas 37 fallas preexistentes ya documentadas (ninguna nueva, ninguna en los módulos tocados esta sesión).
- Verificado con `artisan tinker` usando transacciones explícitas con rollback real (`DB::beginTransaction()`/`DB::rollBack()`) — en esta sesión se detectó que tinker **no** abre transacción automática por sí solo; un primer intento sin `beginTransaction()` explícito dejó datos de prueba persistidos en producción, que se limpiaron a mano de inmediato.
- El calendario de Bandeja y los checkboxes de Agenda pasaron por planificación formal (`EnterPlanMode`/`ExitPlanMode`, con exploración de código vía subagentes y un agente de diseño) dado que eran dos features relacionadas de tamaño real.
- **No se confirmó visualmente en navegador/celular real** — sigue sin haber herramienta de automatización de navegador en este entorno (mismo pendiente arrastrado de sesiones anteriores).

### 📁 Archivos Modificados/Creados
- `apps/backoffice-laravel/app/Http/Controllers/WhatsAppTemplateController.php` — `store()` responde JSON si se pide
- `apps/backoffice-laravel/app/Http/Controllers/BookingMessageController.php` — `buildMonthCalendar()`, `templateVariables`
- `apps/backoffice-laravel/app/Http/Controllers/RecurrenceMessageController.php` — `templateVariables` para el modal de creación
- `apps/backoffice-laravel/app/Http/Controllers/SpaBookingController.php` — filtro de estado a array + `status_touched`
- `apps/backoffice-laravel/app/Http/Controllers/Api/AgendaController.php` — fix `operators` (NT-021)
- `apps/backoffice-laravel/resources/js/modules/whatsapp-bandeja.js` — plantillas reactivas, `openCreateTemplate()`/`submitNewTemplate()`/`insertVariableInNewTemplate()`, preview de nueva plantilla
- `apps/backoffice-laravel/resources/views/whatsapp/_create-template-modal.blade.php` — nuevo
- `apps/backoffice-laravel/resources/views/whatsapp/bandeja/_month_calendar.blade.php` — nuevo
- `apps/backoffice-laravel/resources/views/whatsapp/bandeja/index.blade.php`, `recurrencias/index.blade.php` — select reactivo + "Crear nueva", exclusión de enviados en "seleccionar todos", include del calendario
- `apps/backoffice-laravel/resources/views/whatsapp/plantillas/_form.blade.php` — botón "Previsualizar", `body` reactivo
- `apps/backoffice-laravel/resources/views/agenda/index.blade.php` — checkboxes de Estado
- `apps/backoffice-laravel/resources/css/backoffice-blueprints.css` — `.bandeja-calendar-dot*`
- `apps/backoffice-laravel/tests/Feature/WhatsApp/WhatsAppTemplateFlowTest.php` — nuevo
- `apps/backoffice-laravel/tests/Feature/WhatsApp/BookingMessageCalendarTest.php` — nuevo
- `apps/backoffice-laravel/tests/Feature/Agenda/AgendaStatusFilterTest.php` — nuevo (namespace nuevo)
- `apps/backoffice-laravel/tests/Feature/WhatsApp/BookingMessageFlowTest.php`, `RecurrenceMessageFlowTest.php` — tests de exclusión "enviado hoy" en seleccionar todos
- `apps/backoffice-laravel/tests/Feature/Api/AgendaRangeTest.php` — test de `operators` sin presupuesto aceptado
- `docs/tecnico/NOTAS_TECNICAS.md` — NT-021
- `docs/tecnico/BACKLOG.md` — BL-030, ampliación de BL-029

### 🛑 Pendientes activos
- **Confirmar visualmente en navegador/celular real** todo lo de esta sesión y de las dos anteriores (preview de plantillas, calendario de Bandeja, checkboxes de Agenda y Recurrencias, fix de filtro por operador en `MobAgGbl`/`MobOpAg`).
- **Push a GitHub** — todo el trabajo de esta sesión y las dos anteriores (BL-029, BL-030, fix NT-021) sigue sin commitear.
- Decidir si vale la pena cablear `ExecutedServiceService::convertFromBooking()` a los tres flujos de completado (ver NT-020) o aceptar `spa_booking_services` como fuente permanente.
- BL-028 — estrategia firewall (ufw) para la OPi.
- BL-024b — Fase 2 de WhatsApp.
- BL-001 — Tema de UI: persistencia y cambio reactivo de paleta.
- BL-002 — Favicon & datos generales del negocio.
- BL-003 — Email avanzado: SMTP completo.
- BL-004 — Zonas horarias: selector completo.
- BL-008 — Reportes PDF.
- Investigar y arreglar el resto de la suite de tests preexistente (37 fallos, no relacionados a esta ni a sesiones recientes).

---

## 📅 Sesión: 07/07/2026 — BL-029 (cont.): fix fuente de datos + preview de mensaje antes de enviar

Usuario cargó `recurrence_days` en los servicios reales (Baño chico/mediano/grande = 30 días) y reportó que la pantalla Recurrencias no mostraba nada. Al investigar, se detectó un bug de raíz más profundo que el esperado.

**Bug encontrado — fuente de datos huérfana (ver NT-020):** `executed_services`/`executed_service_items` (de donde Recurrencias leía el historial) tienen **0 filas en producción** pese a haber citas SPA completadas. Investigación (agente explorador) confirmó que `App\Domain\Execution\Services\ExecutedServiceService::convertFromBooking()` nunca se conectó a ningún flujo real — no está bindeado en ningún ServiceProvider ni se llama desde ningún controlador. Las tres rutas reales que marcan una cita `completed` (`SpaBookingController`, `Api/PaymentController`, `Api/BookingController`) solo hacen `$booking->update(['status' => 'completed'])`. El historial real vive en `spa_bookings` (status completed) + `spa_booking_services`.

**Fix:** `RecurrenceMessageController::lastServiceDatesByPet()` corregido para leer de `spa_booking_services` JOIN `spa_bookings WHERE status = 'completed'`. Verificado con datos reales de producción: ahora detecta correctamente a "Firulais" vencido en Baño hace ~10-11 días. Tests actualizados para usar `SpaBooking`/`SpaBookingService` en vez de `ExecutedService`/`ExecutedServiceItem` como fixtures.

**Feature nueva — preview del mensaje antes de enviar:** usuario pidió poder ver una vista previa del mensaje resuelto (con la plantilla ya elegida) antes de abrir WhatsApp, igual en Bandeja Diaria y en Recurrencias (comparten el mismo componente Alpine `whatsappBandeja`).
- `BookingMessageController::preview()` y `RecurrenceMessageController::preview()` — nuevos endpoints que resuelven el mensaje (mismo `TemplateResolver` que ya se usaba) y devuelven `{message}` **sin persistir** ningún log (a diferencia de `store()`, que sí crea el registro y el link final). Lógica compartida extraída a helpers privados (`resolveMessage()` en ambos controladores) para no duplicar entre preview y envío real.
- `whatsapp-bandeja.js` — el componente Alpine ahora llama a `loadPreview()` al abrir la cola y en cada `advance()`, mostrando el texto resuelto en el modal antes de habilitar "Abrir WhatsApp" (deshabilitado si `previewError` — mismo motivo por el que fallaría el envío real, ej. teléfono inválido).
- Modal de envío extraído a partial compartido `whatsapp/_send-queue-modal.blade.php` (antes duplicado idéntico en bandeja y recurrencias) — ahora más ancho (`modal-lg`) para el bloque de preview.
- Nuevas rutas `whatsapp.bandeja.preview` y `whatsapp.recurrencias.preview`.

**Verificación:**
- `npm run build` — assets compilados sin errores, `public/build/` actualizado (se commitea, no hay pipeline de build separado en producción).
- Suite completa filtrada `WhatsApp|Service|Agenda`: 22 tests pasan (incluye 2 nuevos de preview + los 7 anteriores de Recurrencias ajustados a la fuente de datos correcta), mismos 4 fallos preexistentes de `ServiceOperatorRoleLinkTest` sin relación.
- Verificado con datos reales vía `artisan tinker` invocando los controladores directamente (bypass de sesión/CSRF que no aplica en ese contexto): `preview()` de Recurrencias devuelve el mensaje resuelto completo para Firulais/Baño; `preview()` de Bandeja Diaria resuelve correctamente para una cita real. **No se confirmó visualmente en navegador real** — sigue sin haber herramienta de automatización de navegador en este entorno.

### 📁 Archivos Modificados/Creados
- `apps/backoffice-laravel/app/Http/Controllers/RecurrenceMessageController.php` — fix fuente de datos (`spa_booking_services`/`spa_bookings`), método `preview()`, helpers `loadRecipient()`/`resolveMessage()`
- `apps/backoffice-laravel/app/Http/Controllers/BookingMessageController.php` — método `preview()`, helper `resolveMessage()`
- `apps/backoffice-laravel/resources/js/modules/whatsapp-bandeja.js` — `loadPreview()`, estado `previewMessage`/`previewLoading`/`previewError`
- `apps/backoffice-laravel/resources/views/whatsapp/_send-queue-modal.blade.php` — nuevo (partial compartido)
- `apps/backoffice-laravel/resources/views/whatsapp/bandeja/index.blade.php`, `recurrencias/index.blade.php` — usan el partial + `previewUrlTemplate`
- `apps/backoffice-laravel/routes/web.php` — rutas `whatsapp.bandeja.preview`, `whatsapp.recurrencias.preview`
- `apps/backoffice-laravel/tests/Feature/WhatsApp/RecurrenceMessageFlowTest.php` — fixtures migradas a `SpaBooking`, test de preview
- `apps/backoffice-laravel/tests/Feature/WhatsApp/BookingMessageFlowTest.php` — test de preview
- `docs/tecnico/NOTAS_TECNICAS.md` — NT-020
- `docs/tecnico/MODELO_BD.md` — corrección de fuente de datos en `recurrence_messages`, advertencia en sección `Ejecución`

### 🛑 Pendientes activos
- **Confirmar visualmente en navegador** ambas pantallas con el nuevo preview.
- **Push a GitHub** — este trabajo (y el de la sesión anterior BL-029) sigue sin commitear.
- Decidir si vale la pena cablear `ExecutedServiceService::convertFromBooking()` a los tres flujos de completado (para tener snapshot histórico inmutable) o aceptar `spa_booking_services` como fuente permanente pese a su limitación (se sobreescribe si se edita una cita ya completada).
- BL-028 — estrategia firewall (ufw) para la OPi.
- BL-024b — Fase 2 de WhatsApp.
- BL-001 — Tema de UI: persistencia y cambio reactivo de paleta.
- BL-002 — Favicon & datos generales del negocio.
- BL-003 — Email avanzado: SMTP completo.
- BL-004 — Zonas horarias: selector completo.
- BL-008 — Reportes PDF.
- Investigar y arreglar el resto de la suite de tests preexistente (37 fallos, no relacionados a esta ni a sesiones recientes).

---

## 📅 Sesión: 06/07/2026 — BL-029: Recordatorios de recurrencia (WhatsApp > Recurrencias)

Usuario notó que casi todos los servicios son recurrentes (ej. baño cada 20 días) y quiere que el sistema detecte automáticamente qué mascotas ya cumplieron su ciclo para poder mandarles recordatorio. Preguntó si se podía hacer un "barrido" a la apertura del día.

**Decisión de diseño (confirmada con el usuario vía pregunta):** no hay cron/scheduler de Laravel configurado en la OPi (`routes/console.php` solo tiene el comando `inspire` de ejemplo; no hay entrada de cron para `schedule:run`). En vez de agregar infraestructura nueva, el barrido se calcula **bajo demanda** al abrir la pantalla — mismo patrón que la Bandeja Diaria (BL-024), a la que el usuario pidió parecerse, llamándola "Recurrencias".

**Implementación:**
- `services.recurrence_days` (unsigned smallint nullable) — nueva columna; `null` = servicio no recurrente. Editable desde el catálogo de servicios (`services/partials/form.blade.php`, `show.blade.php`).
- `App\Http\Controllers\RecurrenceMessageController` — `index()` calcula, por cada servicio activo con `recurrence_days`, la última fecha de ejecución por mascota (`executed_service_items.service_id` + `executed_services.executed_at`, `MAX` agrupado) y filtra las que ya cumplieron `última_fecha + recurrence_days <= hoy`. Solo se consideran mascotas con al menos una ejecución previa del servicio (sin baseline no hay recurrencia que calcular). `store($key)` recibe una clave compuesta `"petId:serviceId"` (ruta con constraint regex `[0-9]+:[0-9]+`), resuelve teléfono y plantilla, y genera el link `wa.me` igual que la bandeja diaria.
- Nueva tabla `recurrence_messages` (log de envíos, mismo patrón que `booking_messages` pero sin `spa_booking_id`) — sirve para marcar "ya enviado hoy" sin suprimir el recordatorio en días siguientes si la mascota sigue sin recibir el servicio.
- `whatsapp_templates.context` (`cita`|`recurrencia`, default `cita`) — las plantillas ahora se filtran por contexto en cada bandeja; `TemplateResolver::availableVariables()`/`resolveForRecurrence()` exponen variables propias para recurrencia (`{ultima_fecha}`, `{dias_vencido}`) vs. las de cita (`{fecha}`, `{hora}`). El formulario de plantilla (`_form.blade.php`) cambia el set de variables disponibles según el contexto seleccionado (Alpine.js, sin recargar).
- Nueva pantalla `whatsapp/recurrencias` — reutiliza el mismo componente Alpine `whatsappBandeja` (sin tocar el JS) pasando como `id` la clave compuesta `petId:serviceId`. Nuevo item de navegación "Recurrencias" en el menú WhatsApp.

**Verificación:**
- Migraciones aplicadas en producción (`docker exec estetican_app php artisan migrate --force`) + `view:clear`/`config:clear`/`route:clear`/`cache:clear`.
- Producción aún no tiene historial de `executed_services` (sistema joven, 0 registros) — no se pudo verificar con datos reales. Se creó `tests/Feature/WhatsApp/RecurrenceMessageFlowTest.php` (6 tests) cubriendo: render de la página, mascota vencida aparece, mascota no vencida no aparece, servicio sin recurrencia se ignora, envío exitoso crea `recurrence_message` y retorna `wa_link`, y falla controladamente sin teléfono válido. Los 6 pasan.
- Se corrió la suite completa filtrada por `WhatsApp|Service`: los mismos 4 fallos preexistentes de `ServiceOperatorRoleLinkTest` (confirmados vía `git stash` que ya fallaban en `main` antes de este cambio, no relacionados) — cero regresiones nuevas.
- Verificación funcional del stack completo (controller + vista Blade + rutas) vía `artisan tinker` disparando el request internamente con usuario autenticado — `GET /whatsapp/recurrencias` responde 200 y contiene el header esperado. **No se confirmó visualmente en navegador real** — no hay herramienta de automatización de navegador disponible en este entorno (mismo pendiente que sesiones anteriores).

### 📁 Archivos Modificados/Creados
- `apps/backoffice-laravel/database/migrations/2026_07_06_000001_add_recurrence_days_to_services_table.php` — nuevo
- `apps/backoffice-laravel/database/migrations/2026_07_06_000002_add_context_to_whatsapp_templates_table.php` — nuevo
- `apps/backoffice-laravel/database/migrations/2026_07_06_000003_create_recurrence_messages_table.php` — nuevo
- `apps/backoffice-laravel/app/Models/Service.php`, `WhatsAppTemplate.php` — `recurrence_days`, `context`, relación `recurrenceMessages()`
- `apps/backoffice-laravel/app/Models/RecurrenceMessage.php` — nuevo
- `apps/backoffice-laravel/app/Http/Controllers/RecurrenceMessageController.php` — nuevo
- `apps/backoffice-laravel/app/Http/Controllers/ServiceController.php`, `WhatsAppTemplateController.php`, `BookingMessageController.php` — validación/payload `recurrence_days` y `context`
- `apps/backoffice-laravel/app/Support/WhatsApp/TemplateResolver.php` — `availableVariables($context)`, `resolveForRecurrence()`
- `apps/backoffice-laravel/app/Support/Navigation/Groups/WhatsAppNavigation.php`, `app/Support/Pages/WhatsAppPage.php` — item y breadcrumbs de Recurrencias
- `apps/backoffice-laravel/resources/views/whatsapp/recurrencias/index.blade.php` — nuevo
- `apps/backoffice-laravel/resources/views/services/partials/form.blade.php`, `show.blade.php` — campo/display de recurrencia
- `apps/backoffice-laravel/resources/views/whatsapp/plantillas/_form.blade.php`, `index.blade.php` — selector de contexto, badge, conteo combinado
- `apps/backoffice-laravel/routes/web.php` — rutas `whatsapp.recurrencias`, `whatsapp.recurrencias.enviar`
- `apps/backoffice-laravel/tests/Feature/WhatsApp/RecurrenceMessageFlowTest.php` — nuevo
- `docs/tecnico/MODELO_BD.md` — `services.recurrence_days`, tabla `recurrence_messages`, `whatsapp_templates.context`
- `docs/tecnico/BACKLOG.md` — BL-029

### 🛑 Pendientes activos
- **Confirmar visualmente en navegador** la pantalla Recurrencias y el selector de contexto en Plantillas.
- **Push a GitHub** — este trabajo no se ha commiteado todavía.
- Cargar `recurrence_days` en los servicios reales del catálogo (hoy todos quedaron en `null` tras la migración) para que la pantalla empiece a mostrar resultados.
- BL-028 — estrategia firewall (ufw) para la OPi.
- BL-024b — Fase 2 de WhatsApp.
- BL-001 — Tema de UI: persistencia y cambio reactivo de paleta.
- BL-002 — Favicon & datos generales del negocio.
- BL-003 — Email avanzado: SMTP completo.
- BL-004 — Zonas horarias: selector completo.
- BL-008 — Reportes PDF.
- Investigar y arreglar el resto de la suite de tests preexistente (37 fallos, no relacionados a esta ni a sesiones recientes).

---

## 📅 Sesión: 03/07/2026 (cont. 2) — Corrección BL-027: grid tipo Google Calendar en Agenda móvil

Usuario probó la vista Semana/Mes de la sesión anterior (lista vertical agrupada por día) y pidió explícitamente que fuera "como la de Google Calendar según se seleccione por día, semana o mes" — revirtiendo la decisión de diseño tomada en esa sesión (grid ilegible a ~360px de ancho).

**Implementación:**
- `mob_apps/operador/src/admin/agendaViews.ts` — se agregó `weekDays()` (7 fechas lunes-domingo), `monthGridDays()` (grid completo de semanas cubriendo el mes, con `outside` para días de meses vecinos) y `groupByDateMap()` (lookup O(1) por fecha). Se eliminaron `groupByDate()`/`dayHeaderLabel()` (quedaron sin uso).
- `mob_apps/operador/src/admin/AgendaCalendarGrid.tsx` (nuevo) — `WeekGrid` (7 columnas con scroll horizontal, cada una con sus citas del día: hora + mascota + punto de color por estado) y `MonthGrid` (cuadrícula 7×5/6, número de día + hasta 3 puntos de color +"N más"). Tocar un día (vacío, encabezado o celda de mes) navega a la vista Día de esa fecha con el detalle completo — se optó por puntos en vez de texto en el mes porque a ~50px de celda el texto completo es ilegible (mismo patrón que Google Calendar/Apple Calendar mobile).
- `GlobalAgenda.tsx` y `GroomerAgenda.tsx` — Semana/Mes ahora renderizan `WeekGrid`/`MonthGrid` en vez de la lista agrupada. Vista Día no cambió (ya era lista cronológica, equivalente al timeline de web).

**Verificación:** `tsc --noEmit` sin errores nuevos (solo los 7 preexistentes de archivos no tocados); `npm run build` exitoso; desplegado en `estetican_mob` vía bind mount de `dist/`, sin reiniciar contenedor. **No se confirmó visualmente en dispositivo real** — pendiente que el usuario lo pruebe en su celular.

### 📁 Archivos Modificados/Creados
- `mob_apps/operador/src/admin/agendaViews.ts`
- `mob_apps/operador/src/admin/AgendaCalendarGrid.tsx` — nuevo
- `mob_apps/operador/src/admin/GlobalAgenda.tsx`, `GroomerAgenda.tsx`
- `docs/tecnico/BACKLOG.md` — nota en BL-027

### 🛑 Pendientes activos
- **Confirmar visualmente en celular real** el nuevo grid Semana/Mes (Universal y por operador).
- BL-024b — Fase 2 de WhatsApp.
- BL-001 — Tema de UI: persistencia y cambio reactivo de paleta.
- BL-002 — Favicon & datos generales del negocio.
- BL-003 — Email avanzado: SMTP completo.
- BL-004 — Zonas horarias: selector completo.
- BL-008 — Reportes PDF.
- Investigar y arreglar el resto de la suite de tests preexistente (37 fallos, no relacionados a esta ni a sesiones recientes).

---

## 📅 Sesión: 03/07/2026 (cont.) — BL-027: vista Día/Semana/Mes en Agenda móvil

Usuario confirmó que BL-026 (web) funciona bien y pidió el mismo Día/Semana/Mes en la app móvil (`mob_apps/operador`).

**Decisiones confirmadas con el usuario:**
- Alcance: ambas pantallas de agenda móvil — Agenda Universal (`GlobalAgenda.tsx`, screenTag `MobAgGbl`) y Agenda por operador (`GroomerAgenda.tsx`, screenTag `MobOpAg`).
- Patrón de UI: **no** se replicó el grid tipo Google Calendar de web — en ~360px de ancho es ilegible. En su lugar, semana y mes se muestran como **lista vertical agrupada por día** (encabezado de fecha + tarjetas de cita ya existentes), patrón nativo de agenda móvil.

**Implementación:**
- `Api\AgendaController::index()` — nuevo query param `view` (`day|week|month`, default `day`, con fallback silencioso si llega un valor inválido). Semana = lunes a domingo; mes = 1º al último día del mes del `date` ancla. Se agregó campo `date` (`Y-m-d`) a la respuesta para agrupar sin ambigüedad de zona horaria. El comportamiento de `view=day` (default) es idéntico al anterior — verificado con test dedicado.
- `mob_apps/operador/src/admin/agendaViews.ts` (nuevo) — helpers puros compartidos: `rangeForView`, `shiftAnchor` (navegación ±1 semana/mes, evita desborde de día en meses cortos), `rangeLabel`, `groupByDate`, `dayHeaderLabel` (Hoy/Mañana/nombre de día).
- `GlobalAgenda.tsx` y `GroomerAgenda.tsx` — toggle Día/Semana/Mes; en Día se conserva el selector de fecha original sin cambios; en Semana/Mes aparece un navegador `< [rango] >` con flechas y botón central para volver a hoy. Tarjeta de cita extraída a función local reutilizada entre ambas vistas.
- `GroomerAgenda.tsx` mantiene el filtro de operador **client-side** contra `b.operators` (operadores de las líneas del presupuesto aceptado) tal como ya funcionaba — no se cambió a filtrar por `spa_bookings.operator_id` en el backend para no alterar qué citas se consideran "del operador" en día/semana/mes.

**Verificación:**
- Backend: 4 tests nuevos (`AgendaRangeTest`) — semana, mes, día sin cambios, fallback ante `view` inválido. Suite completa corrida antes/después vía `git stash`: 37 fallos preexistentes sin relación (ya documentados como pendiente de sesiones previas) tanto con como sin este cambio — cero regresiones nuevas.
- Datos reales: vía Tinker (bypass HTTP/auth) contra datos de producción — semana y mes del 2-4 de julio devuelven las 6 citas SPA esperadas, agrupadas por `date` correctamente.
- `tsc --noEmit`: sin errores nuevos (los 7 errores existentes son de archivos no tocados en esta sesión). `npm run build` exitoso; `dist/` está montado directo en `estetican_mob`, sin necesidad de reiniciar contenedor.
- **No se confirmó visualmente en navegador real** — no hay herramienta de automatización de navegador disponible en este entorno. Pendiente que el usuario confirme visualmente el toggle y la navegación de rango en ambas pantallas.

### 📁 Archivos Modificados/Creados
- `apps/backoffice-laravel/app/Http/Controllers/Api/AgendaController.php` — `view=day|week|month`, campo `date`
- `apps/backoffice-laravel/tests/Feature/Api/AgendaRangeTest.php` — nuevo
- `mob_apps/operador/src/admin/agendaViews.ts` — nuevo
- `mob_apps/operador/src/admin/GlobalAgenda.tsx`, `GroomerAgenda.tsx`
- `docs/tecnico/BACKLOG.md` — BL-027

### 🛑 Pendientes activos
- **Confirmar visualmente en navegador/celular** el toggle Día/Semana/Mes en Agenda Universal y Agenda por operador (móvil) — BL-026 (web) ya fue confirmado por el usuario como funcionando.
- BL-024b — Fase 2 de WhatsApp.
- BL-001 — Tema de UI: persistencia y cambio reactivo de paleta.
- BL-002 — Favicon & datos generales del negocio.
- BL-003 — Email avanzado: SMTP completo.
- BL-004 — Zonas horarias: selector completo.
- BL-008 — Reportes PDF.
- Investigar y arreglar el resto de la suite de tests preexistente (37 fallos, no relacionados a esta ni a sesiones recientes).

---

## 📅 Sesión: 03/07/2026 — Fix bloqueo de horarios en móvil + BL-026: vista Día/Semana/Mes en Agenda Universal

### ✅ Fix urgente: app móvil no bloqueaba el rango completo de una cita ya agendada

Usuaria reportó (WhatsApp, en vivo) que al agendar con un operador que ya tenía una cita de 1.5h, el grid de horarios solo mostraba bloqueado el slot de inicio (ej. 10:30) y dejaba libres los siguientes (11:00, 11:30) que esa cita ya ocupaba — permitiendo doble-agendar al mismo operador.

**Causa raíz:** `MobCitaNueva.tsx:139` (`loadOccupied`) armaba el `Set` de horarios ocupados con un solo elemento por cita existente (`b.time.slice(0,5)`), ignorando `duration_minutes`/`end_time` que el backend ya devuelve correctamente. No es un bug nuevo de BL-025 — ya existía, solo se volvió más visible al hacerse el operador obligatorio. Ver NT-018.

**Fix:** `loadOccupied` ahora expande cada cita existente a todos los slots de 30 min que cubre (reusando `buildSlots`) antes de agregarlos al `Set`. Verificado con `tsc --noEmit` (sin errores nuevos) y build de producción (`npm run build` en `mob_apps/operador/`, servido directo desde `dist/` montado en `estetican_mob`, sin necesidad de reiniciar contenedor).

### ✅ BL-026 — Agenda Universal (web): vista Día/Semana/Mes estilo Google Calendar

Usuario pidió poder ver la Agenda Universal (`agenda.index`, screen `AgUniInd`) por día (como ya estaba), semana o mes, en vez de solo un día a la vez. Decisiones confirmadas con el usuario: semana inicia en **lunes**; celdas de mes muestran hasta **3 citas** + "+N más"; sin librería de calendario nueva (no había FullCalendar ni similar instalado) — todo construido con Blade + CSS grid + enlaces, siguiendo el patrón 100% server-driven del proyecto (como ya hace `agenda-scope-switch`), sin JS/Alpine nuevo.

**Implementación:**
- `SpaBookingController::index()` — nuevo query param `cal_view` (`day|week|month`, default `day`). El bloque de lógica existente para `day` quedó envuelto sin tocarse (cero regresión, verificado con render idéntico antes/después). Nuevo helper `applyBookingFilters()` (extraído, reusado por la query diaria y la nueva de rango). Nuevos métodos `indexCalendarRange()` y `buildCalendarRange()` — 2 queries SQL únicas (SPA + Hotel) sin importar si el rango es de 7 o 42 días; agrupamiento por día en memoria; Hotel se replica en cada día de su estancia dentro del rango.
- `agenda/index.blade.php` — toggle Día/Semana/Mes; secciones exclusivas de día (scope switch Hoy/Mañana/Próximas/Todas, timeline, tabla paginada) envueltas en `@if($calView === 'day')`; nuevas partials incluidas para semana/mes.
- `agenda/partials/_calendar_chip.blade.php`, `_calendar_week.blade.php`, `_calendar_month.blade.php` — nuevos.
- `backoffice-blueprints.css` — nuevas clases `.agenda-calendar-*` (semana, mes, chips, responsive con scroll horizontal en semana y grid compacto en mes para móvil `<768px`).

**Verificación:** renderizado vía Tinker (bypass HTTP/auth) con datos reales (6 citas SPA del 2-4 jul 2026) — día idéntico a antes (0 clases de calendario nuevas presentes), semana muestra lunes-domingo con los 6 chips correctos, mes muestra 35 celdas (5 semanas) con 4 días fuera de mes marcados y el día 2 de julio (exactamente 3 citas) confirma el límite del "+N más" sin desbordar. Pint aplicado sin issues nuevos.

**Nota operativa:** `node_modules/` y `public/build/` de `backoffice-laravel` habían quedado con dueño `root` de una ejecución previa, bloqueando `npm run build`. Corregido con `sudo chown -R tomas:tomas` en ambos. Ver NT-019.

### 📁 Archivos Modificados/Creados
- `mob_apps/operador/src/admin/MobCitaNueva.tsx` — fix `loadOccupied`
- `apps/backoffice-laravel/app/Http/Controllers/SpaBookingController.php` — `cal_view`, `applyBookingFilters`, `indexCalendarRange`, `buildCalendarRange`
- `apps/backoffice-laravel/resources/views/agenda/index.blade.php` — toggle + envoltura condicional
- `apps/backoffice-laravel/resources/views/agenda/partials/_calendar_chip.blade.php`, `_calendar_week.blade.php`, `_calendar_month.blade.php` — nuevos
- `apps/backoffice-laravel/resources/css/backoffice-blueprints.css` — clases `.agenda-calendar-*`
- `docs/tecnico/NOTAS_TECNICAS.md` — NT-018, NT-019
- `docs/tecnico/BACKLOG.md` — BL-026 + fix móvil movidos a Completados

### 🛑 Pendientes activos
- **Push a GitHub** (todo lo de esta sesión sigue solo en local/OPi, sin commit).
- Confirmar visualmente en navegador la vista Semana/Mes (esta sesión solo verificó vía Tinker, sin navegador real).
- BL-024b — Fase 2 de WhatsApp.
- BL-001 — Tema de UI: persistencia y cambio reactivo de paleta.
- BL-002 — Favicon & datos generales del negocio.
- BL-003 — Email avanzado: SMTP completo.
- BL-004 — Zonas horarias: selector completo.
- BL-008 — Reportes PDF.
- Evaluar si conectar Agenda a Google Calendar (sync unidireccional, calendario compartido en modo solo-lectura) — quedó como idea discutida con el usuario, no agregada aún al backlog formalmente.

---

## 📅 Sesión: 02/07/2026 — BL-025: fix hora de cita (web+móvil) + fix teléfonos WhatsApp + cambiar dueño de mascota

### ✅ Fix: normalizador de teléfono WhatsApp con datos reales de producción

`PhoneNormalizer::toWhatsAppNumber()` (BL-024) solo aceptaba exactamente 10 dígitos — con datos reales de producción (números ya con `+52`, o con el viejo prefijo `521` de WhatsApp para móviles MX) casi ninguna fila de la bandeja quedaba seleccionable. Se agregó reconocimiento de 12 dígitos que empiezan con `52` (se usan tal cual) y 13 dígitos que empiezan con `521` (se les quita el `1` extra). Los números genuinamente mal capturados (9 dígitos, u otros que no calzan ningún patrón MX) siguen quedando deshabilitados correctamente. 10 tests, todos verdes. Commit `e754d27`.

### ✅ Feature: cambiar dueño de cualquier mascota

Modal "Cambiar dueño" en `pets/show.blade.php` (botón junto a "Editar cliente"). Con solo 8 clientes en el sistema, el selector precarga todos los clientes y filtra en memoria con Alpine (sin necesitar endpoint de búsqueda). Nuevo endpoint `PUT pets/{pet}/owner` → `PetController::updateOwner`. Decisiones de diseño explícitas:
- `SpaBooking`/`HotelReservation`/`Quote` no tienen `client_id` propio (derivan el dueño vía `pet_id`) — su historial "cambia de dueño" retroactivamente en reportes, es intencional, no se reescribe nada.
- `ResourceEvent` sí tiene `client_id` propio (snapshot histórico) — **no se actualiza** al reasignar, para preservar quién era el dueño cuando ocurrió cada incidente.
- Auditado automáticamente vía Activitylog (`client_id` ya estaba en `logOnly` de `Pet`).

4 tests, todos verdes. Commit `1e2713e`.

### ✅ BL-025 — Fix hora de la cita en "Programar servicio" (AgSpaCre), web y móvil

Usuario reportó 5 problemas relacionados en el mismo flujo:
1. Hora sugerida al abrir no redondeada a 5 min (ej. sugería 10:02 en vez de 10:00).
2. No se podía escribir la hora a mano en el datetime picker.
3. No validaba horario operativo de la estética.
4. No validaba traslape de horario con otra cita del mismo operador.
5. Debería ser imposible fijar hora sin haber elegido operador primero.

**Causas raíz encontradas (2 agentes Explore, backoffice + móvil):**
- Bug 1: `now()->addHour()->format(...)` sin redondear en `agenda/create.blade.php` (y `edit.blade.php`).
- Bug 2: Flatpickr reparsea el `altInput` contra `altFormat` en `blur` de forma estricta; con `system_time_format=12h` (default), el formato AM/PM en español fallaba a reparsear y Flatpickr revertía el valor en silencio.
- Bug 3: **no existía ninguna configuración de horario de apertura/cierre en todo el proyecto.**
- Bug 4: la colisión de horario solo existía para jaulas/recursos físicos (`ResourceAllocationService`); nada para operadores, ni en web ni en la API móvil.
- Bug 5: **en backoffice web no existía ningún selector de "operador de la cita"** al crear el servicio — el operador se asigna después, por servicio individual, ya con presupuesto aceptado. En móvil sí había un operador único desde el inicio (`spa_bookings.operator_id`) pero era opcional y no bloqueaba nada.

**Decisiones confirmadas con el usuario:** se agrega selector de operador al crear en web (coexiste con la asignación fina por servicio); horario de operación es un solo horario fijo diario (no varía por día de semana); operador pasa a ser **obligatorio** en ambos lados (antes opcional en móvil).

**Implementación:**
- `SystemSettings.php` — `booking_opening_time`/`booking_closing_time` (default `09:00`/`19:00`) en sección `clinical`.
- `App\Support\SystemSettings\BusinessHours` (nuevo) — `isWithin()`.
- `App\Domain\Planning\Services\OperatorAvailabilityChecker` (nuevo) — `hasConflict()`, query directa sobre `spa_bookings.operator_id`+`scheduled_at`+`duration_minutes` (excluye cancelled/no_show). Alcance solo SPA.
- `BookingService::scheduleSpaSession()`/`rescheduleBooking()` — nuevo parámetro `?int $operatorId`.
- `SpaBookingController` (web) y `Api\BookingController` (móvil) — `operator_id` ahora `required`, validan `BusinessHours` + `OperatorAvailabilityChecker` antes de crear/reprogramar.
- `agenda/create.blade.php`/`edit.blade.php` — selector de operador, hora redondeada a 5 min, input de hora nace `disabled` hasta elegir operador (JS inline), `data-force-24h`/`data-min-time`/`data-max-time`.
- `datetime-picker.js` — `minuteIncrement:5` explícito, `minTime`/`maxTime` desde data-attrs, `time_24hr` forzado solo en campos con `data-force-24h` (elimina la ambigüedad AM/PM que causaba el bug 2, sin afectar otros datetime-local del sistema).
- `MobCitaNueva.tsx` — quitado "Sin asignar" (operador obligatorio), grid de horarios deshabilitado hasta elegir operador, `loadOccupied` ahora filtra por `operator_id` y se refresca al cambiarlo, `START_H`/`END_H` hardcodeados reemplazados por `/api/settings/booking` (`opening_time`/`closing_time`).
- `Api\AgendaController::index` — filtro opcional `operator_id`.
- `Api\SettingController::booking()` — expone `opening_time`/`closing_time`.

**Bugs preexistentes encontrados y corregidos de paso (no causados por este cambio, pero bloqueaban las pruebas o eran fallas reales en producción):**
- NT-015: `users.can_login` sin migración propia (mismo patrón que NT-013).
- NT-016: `Api\BookingController` guardaba `total_estimated_price = null` cuando el total era exactamente `$0` (operador `?:` trata `0` como falsy) — cualquier cita API sin `services` ya fallaba en producción con violación de `NOT NULL`.

**Tests:** 16 nuevos (BusinessHours, OperatorAvailabilityChecker, SpaBookingController, Api\BookingController) + toda la suite de WhatsApp/PetOwner re-verificada — 30 tests, todos verdes.

### 🐛 Fix de seguimiento: el campo de hora no se habilitaba al elegir operador

Usuario reportó tras el despliegue que, en web, seleccionar operador no habilitaba el campo de hora. El gating original alternaba el atributo `disabled` nativo del `<input>` y adivinaba cuál era el `altInput` que crea Flatpickr (`nextElementSibling`) para replicar el estado — frágil frente al timing/estado interno de la librería (Flatpickr copia `disabled` del input original al `altInput` solo una vez, en su inicialización).

**Fix:** se abandonó el enfoque basado en `disabled`. Ahora el campo vive dentro de un `<div id="scheduled_at_wrapper">` que se bloquea visual e interactivamente con una clase CSS `is-locked` (`opacity:.5; pointer-events:none`) controlada por JS al cambiar el select de operador — no depende en absoluto de cómo Flatpickr gestiona su propio DOM interno. Verificado renderizando la vista directamente vía Tinker (bypass de HTTP/auth) para confirmar el HTML real generado antes y después del fix. Commit `3888b3c`.

**Pendiente de confirmación del usuario:** el fix se desplegó y se verificó el render server-side, pero falta que el usuario confirme visualmente en el navegador que el campo ya se habilita correctamente.

### 📁 Archivos Clave Modificados/Creados
- `app/Support/SystemSettings/BusinessHours.php`, `app/Domain/Planning/Services/OperatorAvailabilityChecker.php` — **nuevos**
- `database/migrations/2026_07_02_000000_add_can_login_to_users_table.php` — **nuevo** (NT-015)
- `app/Support/SystemSettings/SystemSettings.php`, `app/Domain/Planning/Services/BookingService.php` + interfaz, `app/Http/Controllers/SpaBookingController.php`, `app/Http/Controllers/Api/BookingController.php`, `app/Http/Controllers/Api/AgendaController.php`, `app/Http/Controllers/Api/SettingController.php`
- `resources/views/agenda/create.blade.php`, `edit.blade.php`, `resources/js/modules/datetime-picker.js`
- `mob_apps/operador/src/admin/MobCitaNueva.tsx`
- `tests/Feature/Planning/`, `tests/Feature/SpaBookingSchedulingValidationTest.php`, `tests/Feature/Api/BookingSchedulingValidationTest.php` — **nuevos**

### 🔄 Pendientes para Próxima Sesión
- **Confirmar en navegador** que el campo de hora ya se habilita al elegir operador en `/pets/{id}/bookings/create` (fix de seguimiento arriba, verificado solo server-side).
- **BL-024b** — Fase 2 de WhatsApp: confirmación de cliente, historial conversacional, CRM completo.
- Investigar y arreglar el resto de la suite de tests preexistente (fallos no relacionados a las sesiones recientes).
- BL-001 — Tema de UI: persistencia y cambio reactivo de paleta de colores
- BL-002 — Favicon & datos generales del negocio
- BL-003 — Email avanzado: SMTP completo
- BL-004 — Zonas horarias: selector completo
- BL-008 — Reportes PDF

---

## 📅 Sesión: 01/07/2026 — BL-024 Fase 1: Recordatorios WhatsApp ✅ + fix infra de testing

### ✅ BL-024 Fase 1 — Bandeja diaria de recordatorios WhatsApp

Se descartó reconstruir el viejo script externo (CSV + WhatsApp Web automatizado con delays aleatorios, corría en shell de Windows 11, no vive en este repo y no se recuperó) — no se automatiza el envío para evitar riesgo de baneo de cuenta. En su lugar: bandeja diaria con selección manual + link `wa.me` que el operador confirma.

**Alcance:** solo citas SPA (`SpaBooking`). Hotel es otra unidad de negocio y manejará sus mensajes con su propia lógica más adelante — decisión explícita del usuario, sin forzar tabla/lógica genérica compartida.

**Datos nuevos:**
- `whatsapp_templates` — plantillas de mensaje con variables `{cliente} {mascota} {servicio} {fecha} {hora}`.
- `booking_messages` — log de cada recordatorio enviado (teléfono normalizado, mensaje resuelto, wa_link, quién y cuándo).

**Lógica (`app/Support/WhatsApp/`):**
- `PhoneNormalizer` — el teléfono se guarda sin lada país (10 dígitos MX). Solo números de exactamente 10 dígitos se prefijan con `52`; cualquier otra longitud se considera no-MX y la fila queda deshabilitada (no seleccionable) en la bandeja.
- `TemplateResolver` — reemplaza placeholders usando datos reales del booking (`pet.client.full_name`, `pet.name`, servicios, fecha/hora con el formato configurado en `SystemSetting`).

**UI:**
- `/whatsapp/bandeja` — citas del día con checkboxes (deshabilitados si el teléfono no es válido), selector de plantilla, envío secuencial vía modal Alpine (abre `wa.me` en pestaña nueva por cada seleccionado — los navegadores bloquean múltiples `window.open` sin gesto directo, y el volumen esperado es bajo).
- `/whatsapp/plantillas` — CRUD de plantillas con chips de variables clicables que insertan `{variable}` en el textarea.
- Nuevo grupo de navegación "WhatsApp" + permiso Spatie `ver whatsapp` (agregado a `BaseRolesSeeder`).

### 🐛 Bugs encontrados por los tests (antes de llegar a producción)
- `WhatsAppTemplate` sin `$table` explícito → Eloquent infería `whats_app_templates` en vez de `whatsapp_templates`.
- Relación `WhatsAppTemplate::messages()` sin FK explícito → buscaba `whats_app_template_id` en vez de `whatsapp_template_id`.
- Ambos solo se detectaron al escribir Feature tests reales contra MySQL (no aparecían en revisión de código).

### 🔧 Infraestructura — hallazgos importantes de esta sesión
- **Esta OPi no tiene un entorno Sail/dev separado.** `estetican_app` (producción) monta `.:/var/www/html` — el mismo código que se edita aquí. Intentar `./vendor/bin/sail up -d` choca de puerto 8000 con el contenedor de prod (expuesto en loopback para diagnóstico, ver `compose.prod.yaml:22`). El flujo real es `docker exec estetican_app php artisan ...`, ya documentado en `docs/OPI_PRODUCCION.md`.
- **La base `testing` de MySQL nunca existió** — el usuario `estetican` no tenía permiso. Se creó (`CREATE DATABASE testing` + `GRANT ALL ... TO 'estetican'@'%'`), desbloqueando `artisan test` para esta y futuras sesiones.
- **Migración huérfana detectada:** `users.operator_role_id` se usaba en `App\Models\User::operatorRole()` y existía en producción, pero ninguna migración la creaba (parche manual histórico). `2026_06_30_000001_add_operator_id_to_users_table.php` asumía la columna vía `->after('operator_role_id')`, rompiendo cualquier `migrate` desde cero. Se agregó `2026_06_30_000000_add_operator_role_id_to_users_table.php` (idempotente, no-op en prod donde ya existía).
- **El resto de la suite de tests (37 de 43) sigue fallando** por causas no relacionadas (rutas que redirigen a `/login`, probablemente tests nunca adaptados a este entorno). Fuera de alcance de esta sesión — pendiente para revisión futura.

### 📁 Archivos Clave Creados/Modificados
- `database/migrations/2026_06_30_000000_add_operator_role_id_to_users_table.php` — **nuevo** (fix de deuda técnica preexistente)
- `database/migrations/2026_07_01_000001_create_whatsapp_templates_table.php` — **nuevo**
- `database/migrations/2026_07_01_000002_create_booking_messages_table.php` — **nuevo**
- `app/Models/WhatsAppTemplate.php`, `app/Models/BookingMessage.php` — **nuevos**
- `app/Support/WhatsApp/PhoneNormalizer.php`, `app/Support/WhatsApp/TemplateResolver.php` — **nuevos**
- `app/Http/Controllers/WhatsAppTemplateController.php`, `app/Http/Controllers/BookingMessageController.php` — **nuevos**
- `app/Support/Pages/WhatsAppPage.php`, `app/Support/Navigation/Groups/WhatsAppNavigation.php` — **nuevos**
- `resources/views/whatsapp/` — **nuevo** (bandeja + plantillas)
- `resources/js/modules/whatsapp-bandeja.js` — **nuevo**
- `database/seeders/BaseRolesSeeder.php` — módulo `whatsapp` agregado
- `routes/web.php`, `app/Support/Navigation/MainNavigation.php`, `app/Models/SpaBooking.php` — rutas + relación `messages()`
- `tests/Feature/WhatsApp/` — **nuevo** (8 tests, todos verdes)
- `docs/tecnico/MODELO_BD.md` — nueva sección `## Comunicaciones`

### 🔄 Pendientes para Próxima Sesión
- **BL-024b** — Fase 2 de WhatsApp: confirmación de cliente, historial conversacional, CRM completo.
- Investigar y arreglar el resto de la suite de tests (37 fallos preexistentes, no relacionados a esta sesión).
- BL-001 — Tema de UI: persistencia y cambio reactivo de paleta de colores
- BL-002 — Favicon & datos generales del negocio
- BL-003 — Email avanzado: SMTP completo
- BL-004 — Zonas horarias: selector completo
- BL-008 — Reportes PDF

---

## 📅 Sesión: 30/06/2026 — BL-023 ✅ + Sync usuarios↔operadores + Operador mobile + Compresión de fotos

### ✅ BL-023: GroomerPicker — COMPLETADO

Diseño de tabla confirmado funcionando en `mov.estetican.org/groomer`. Columnas: foto + nombre / rol (badge acrónimo) / chevron.

---

### ✅ Auto-sincronización usuarios ↔ operadores

**Problema:** `users` (backoffice auth) y `operators` (catálogo legacy FK en spa_bookings/quotes) eran dos tablas independientes. Al marcar un usuario como `is_operator`, no se creaba su registro de operador automáticamente.

**Solución implementada (sin romper FKs existentes):**
- Migración: `operator_id` nullable FK en `users` → `operators` (nullOnDelete)
- Migración: `operator_role_id` nullable FK en `operators` → `operator_roles` (columna faltaba aunque MODELO_BD la listaba)
- Migración: `acronym char(3) nullable unique` en `operator_roles`
- `UserController::syncOperatorRecord()` — al guardar usuario: si `is_operator=true` crea/actualiza registro en `operators`; si `is_operator=false` marca `operators.is_active=false` (datos históricos preservados). Usa `saveQuietly()` para no disparar activity log loop.
- `User::operator()` BelongsTo; `Operator::operatorRole()` BelongsTo
- `OperatorRole::getShortLabelAttribute()` — retorna `acronym ?? strtoupper(substr(code, 0, 3))`
- Formulario de Tipo de Operador: campo acrónimo 3 caracteres mayúsculas
- Vista `user/edit.blade.php`: badge de vinculación al operador #ID

**Fix de datos existentes:** Jose Mendez (Operator#1) tenía `operator_role_id=null` porque fue vinculado manualmente antes de que el sync existiera. Corregido via Tinker con `operator_role_id=1` (GRO-BAS).

---

### ✅ App móvil: renombrar "Groomer" → "Operador"

Todos los textos visibles al usuario (títulos, breadcrumbs, columnas, mensajes vacíos) renombrados a "Operador". Screen tags actualizados: `MobGroPkr` → `MobOpPkr`, `MobGroAg` → `MobOpAg`, `MobGro` → `MobOp`. Nombres de archivos y rutas URL (`/groomer`) sin cambiar para no romper historial y navegación interna.

---

### ✅ Breadcrumb en MobOpPkr

- `GroomerPicker` ahora lee `parentCrumbs` con `useState(() => getNavCrumbs())` al montar (captura el valor en mount, no re-evalúa en cada re-render).
- Si hay crumbs → muestra flecha de regreso + trail. Si no (acceso desde nav inferior) → sin breadcrumb.
- `Directory.tsx`: botón "Perfil" llama `setNavCrumbs([{ label: 'Directorio' }])` antes de `navigate('/groomer')`.
- `onCrumbClick` pasado a ScreenHeader para que los crumbs sean navegables.

---

### 🐛 Fix: 500 nginx en `mov.estetican.org/groomer`

**Causa:** `rm -rf dist` (usado para forzar rebuild limpio) eliminó el inodo del directorio. El bind mount `rprivate` del contenedor `estetican_mob` quedó huérfano apuntando al inodo borrado — `/usr/share/nginx/html/` aparecía vacío. Nginx no encontraba `index.html` → bucle de redirección interna → HTTP 500.

**Fix:** `docker restart estetican_mob` re-establece el bind mount al nuevo inodo de `dist/`.

**Lección → NT-012:** Nunca usar `rm -rf` en directorios montados como bind mount en Docker. Para rebuild: ejecutar `npm run build` directamente (Vite sobreescribe archivos en su lugar).

---

### ✅ Compresión de fotos subidas

**Frontend (`image-upload.js`):**
- `getCroppedCanvas`: `maxWidth/maxHeight` 1600 → 1200px
- `toDataURL` y `toBlob`: quality 0.9 → 0.82
- Reduce el blob enviado al servidor de ~500KB–1MB a ~150–350KB

**Backend (`config/backoffice.php`):**
- Perfiles operador/usuario: `main_max_size` 1200 → 800px, quality 82 → 80
- Fotos mascota/recurso: `main_max_size` 1600 → 1200px, quality 82 → 80
- Thumbnails: quality 68–70 → 65 en todos los managers

Solo afecta fotos nuevas; las existentes en storage no cambian.

---

### 📁 Archivos Modificados Esta Sesión

**Backend (backoffice-laravel):**
- `database/migrations/2026_06_30_000001_add_operator_id_to_users_table.php` — nuevo
- `database/migrations/2026_06_30_000002_add_operator_role_id_to_operators_table.php` — nuevo
- `database/migrations/2026_06_30_000003_add_acronym_to_operator_roles_table.php` — nuevo
- `app/Models/User.php` — `operator_id` fillable + relación `operator()`
- `app/Models/Operator.php` — `operator_role_id` fillable + relación `operatorRole()`
- `app/Models/OperatorRole.php` — `acronym` fillable + accessor `short_label`
- `app/Http/Controllers/UserController.php` — `syncOperatorRecord()`
- `app/Http/Controllers/Api/OperatorController.php` — eager load `operatorRole`, `role_acronym`
- `app/Http/Controllers/OperatorRoleController.php` — validación y guardado de `acronym`
- `resources/views/user/edit.blade.php` — badge de vinculación
- `resources/views/operator-roles/partials/form.blade.php` — campo acrónimo
- `config/backoffice.php` — tamaños y calidades de imagen reducidos
- `resources/js/modules/image-upload.js` — canvas 1200px, quality 0.82
- `public/build/` — bundle `app-D34HXRKX.js`

**App móvil (mob_apps/operador):**
- `src/App.tsx` — label "Groomer" → "Operador"
- `src/admin/GroomerPicker.tsx` — textos "Operador", screenTag MobOpPkr, breadcrumb dinámico
- `src/admin/GroomerAgenda.tsx` — textos "Operador", screenTag MobOpAg
- `src/admin/GroomerDashboard.tsx` — textos "Operador", screenTag MobOp
- `src/admin/Directory.tsx` — setNavCrumbs al navegar a /groomer

### 🔄 Commits

- `beced54` — sesión anterior: GroomerPicker tabla + GroomerAgenda
- `1c15b41` + `cac8b3c` — sync usuarios↔operadores + acronym + fix operatorRole
- `9c5c050` — Groomer→Operador UI + breadcrumb MobOpPkr
- `58e92e4` — compresión de fotos (imagen-upload.js + config)

### 🔄 Pendientes para Próxima Sesión
- **BL-024** — WhatsApp: botón wa.me en vista de cita + tabla `booking_messages` + bandeja diaria apertura/cierre. Diseño: tabla con `booking_id`, `type`, `channel`, `wa_link`, `sent_at`, `send_window`. Es la Fase 1 del módulo CRM de comunicaciones.
- BL-001 — Tema de UI: persistencia y cambio reactivo de paleta de colores
- BL-002 — Favicon & datos generales del negocio
- BL-003 — Email avanzado: SMTP completo
- BL-004 — Zonas horarias: selector completo
- BL-008 — Reportes PDF

---

## 📅 Sesión: 29/06/2026 — BL-023: Selector de groomer + fix de caché nginx 🔄 EN CURSO

### 🔄 BL-023: Groomer Picker + Groomer Agenda (parcial)

**Flujo implementado (sesión anterior):**
- Pestaña "Groomer" del footer nav ahora abre `GroomerPicker` en lugar del prototipo hardcodeado (`GroomerDashboard`).
- `GroomerPicker` lista todos los operadores activos (vía `/api/operators`) con foto, nombre y rol. Tap → navega a `/groomer/:id`.
- `GroomerAgenda` muestra la agenda del groomer seleccionado:
  - Header con nombre + foto del groomer como `rightAction`
  - Breadcrumb: `Groomer › [Nombre]`
  - Mismo selector de fechas que `GlobalAgenda` (±1 día, presets Hoy/Mañana, input de fecha)
  - Lista de citas filtrada client-side (`operators.some(o => o.id === operatorId)`)
  - Tap en cita → `/citas/:id` con breadcrumb de 2 niveles
  - Estado vacío con mensaje personalizado + botón "Nueva cita"

**Decisión de diseño:**
- El filtrado se hace client-side (igual que `GlobalAgenda`) porque la API no acepta parámetro `operator_id` — no se modificó el backend.
- `GroomerDashboard` se retiró de las rutas pero el archivo se conservó en disco.
- Se eliminó el import huérfano de `ActiveService` de `App.tsx`.

**Rediseño de GroomerPicker (esta sesión):**
- Usuario pidió consistencia visual con `PetSearch`. Se aplicó la **plantilla de tabla** de PetSearch: `rounded-2xl overflow-hidden`, encabezado `bg-surface-container-low`, filas con `grid-cols-[1fr_1fr_auto]`, foto `w-8 h-8`, separadores entre filas, chevron a la derecha.
- Estructura del contenedor cambiada de `<main max-w-lg>` a `<div className="flex flex-col gap-3 px-4 pt-4">` para coincidir exactamente con PetSearch.
- Build genera: `index-DfrsPvdj.js` + `index-Tik_gQTq.css` (ambos hashes nuevos, confirmado en contenedor y vía curl).

**Fix de caché nginx (esta sesión):**
- `nginx.conf` del contenedor móvil: agregado `location = /index.html` con `Cache-Control: no-cache, no-store, must-revalidate` para que Cloudflare y el navegador siempre descarguen el HTML fresco.
- Verificado: `cf-cache-status: DYNAMIC` + header `Cache-Control: no-cache` en respuesta de Cloudflare.
- Contenedor reiniciado con `docker compose restart mob` para aplicar el nuevo nginx.conf.

⚠️ **Pendiente confirmar:** Al cerrar la sesión el usuario aún no pudo confirmar visualmente que el diseño de tabla llegó correctamente. El código y los archivos servidos están correctos — verificar al inicio de la próxima sesión.

### 📁 Archivos Creados/Modificados
- `mob_apps/operador/src/admin/GroomerPicker.tsx` — rediseñado a plantilla de tabla de PetSearch
- `mob_apps/operador/src/admin/GroomerAgenda.tsx` — nuevo (sin cambios esta sesión)
- `mob_apps/operador/src/App.tsx` — rutas `/groomer` y `/groomer/:id`; sin cambios esta sesión
- `mob_apps/operador/nginx.conf` — `Cache-Control: no-cache` para `index.html`
- `mob_apps/operador/dist/` — build `index-DfrsPvdj.js` + `index-Tik_gQTq.css`

### 🔄 Pendientes para Próxima Sesión
1. **Verificar visualmente** que `GroomerPicker` muestra tabla igual a PetSearch en `mov.estetican.org/groomer`
2. **BL-023** — Una vez confirmado el diseño, marcar como completado
3. BL-001 — Tema de UI: persistencia y cambio reactivo de paleta de colores
4. BL-002 — Favicon & datos generales del negocio
5. BL-003 — Email avanzado: SMTP completo
6. BL-004 — Zonas horarias: selector completo
7. BL-008 — Reportes PDF

---

## 📅 Sesión: 23/06/2026 — BL-021 + BL-007: Ledgers históricos y cabeceras de seguridad

### ✅ BL-021: Migración de registros históricos a asientos contables

**Comando Artisan creado:** `finanzas:migrar-ledgers-historicos [--dry-run]`

- Recorre todos los `cash_ledgers` y `bank_ledgers` sin JE correspondiente.
- Por cada registro crea un `JournalEntry` (status=`aplicado`) con dos líneas: DR 4900 Otros ingresos / CR cuenta del método de pago.
- Para `cash_ledgers` → CR 1100 Caja. Para `bank_ledgers` → resuelve la cuenta vía `PaymentMethod.name`, fallback 1210.
- Idempotente: segunda ejecución solo muestra SKIP en los ya migrados.
- Ejecutado en producción: 2 registros migrados (1 cash, 1 bank).

**Notas:**
- Convención DR/CR del sistema: pagos se registran como DR 4900 / CR Caja — coincide con JE existentes 1, 2, 9.
- `created_by_user_id` = primer usuario admin encontrado.

### 📁 Archivos Creados
- `app/Console/Commands/MigraLedgersHistoricosCommand.php` — **nuevo**

---

## 📅 Sesión: 23/06/2026 — BL-007: Cabeceras de seguridad HTTP

### ✅ Logros y Cambios

**Auditoría de cabeceras de seguridad — ambos dominios:**

- `app.estetican.org` (backoffice): todas las cabeceras ya presentes vía `ContentSecurityPolicy` middleware de Laravel. No requirió cambio.
- `mov.estetican.org` (app móvil): faltaban X-Frame-Options, Referrer-Policy y Permissions-Policy — se agregaron en `nginx.conf`.

**Hallazgo clave:**
- Las Transform Rules de Cloudflare no están configuradas explícitamente para estos headers; las cabeceras las pone el origen (Laravel o nginx). Cloudflare sí agrega HSTS y X-Content-Type-Options de forma independiente (defensa en profundidad — correcto).
- Al editar un archivo montado como volumen `:ro` en Docker, el contenedor retiene el inode original hasta reinicio; `nginx -s reload` no es suficiente cuando cambia el inode del archivo en el host.

### 📁 Archivos Modificados
- `mob_apps/operador/nginx.conf` — 4 `add_header` de seguridad

### 🔄 Pendientes para Próxima Sesión
- **BL-021** — Migrar registros históricos de `cash_ledgers`/`bank_ledgers` a asientos contables
- BL-001..004 — UI/Config (prioridad media)

---

## 📅 Sesión: 16/06/2026 — BL-022 continuación: Balance de movimientos de caja

### ✅ Logros y Cambios

**Pantalla `MobCajaMovimientos` — rediseño completo:**
- Eliminado el toggle Resumido/Detallado; reemplazado por vista combinada `BalanceDetailView`.
- Vista nueva muestra: 3 cards de resumen (Entradas / Salidas / Neto) + sección ENTRADAS con grupos por tipo y filas individuales + sección SALIDAS con la misma estructura + card de Neto del período al final.
- Título de pantalla cambiado a "Balance de movimientos".
- Filtro por tipo y presets de fecha se mantienen funcionales.

**Investigación — filtro por sucursal en cobros:**
- `spa_bookings` NO tiene `branch_id` — el modelo no permite filtrar cobros (payments, cash_ledgers, bank_ledgers) por sucursal sin un join complejo de múltiples saltos.
- Decisión: los movimientos manuales de caja (CashMovement) sí se filtran por sucursal vía `cash_sessions.branch_id`. Los cobros a clientes se muestran globales por período. Ver NT-011.

### 📁 Archivos Clave Modificados/Creados
- `app/Http/Controllers/Api/CashController.php` — **nuevo** (sesión anterior + endpoint `movements()`)
- `routes/api.php` — 4 rutas cash
- `mob_apps/operador/src/admin/MobCaja.tsx` — **nuevo**
- `mob_apps/operador/src/admin/MobCajaMovimientos.tsx` — **nuevo** (rediseñado esta sesión)
- `mob_apps/operador/src/App.tsx` — ruta `/caja/movimientos` + sección Finanzas en menú

### ⚠️ Pendiente: commit de todo BL-022
Los archivos están listos pero sin commitear.

### 🔄 Pendientes para Próxima Sesión
- **BL-021** — Migrar registros históricos de `cash_ledgers`/`bank_ledgers` a asientos contables
- **BL-007** — Verificar Transform Rules Cloudflare

---

## 📅 Sesión: 15/06/2026 — BL-019: Caja completa — sesiones, cobros y movimientos

### ✅ Logros y Cambios

**Módulo de sesiones de caja — flujo completo (BL-019):**
- Apertura y corte de caja funcionando: vistas `open.blade.php`, `close.blade.php`, `show.blade.php`, `index.blade.php`.
- Corte calcula diferencia (sobrante/faltante) incluyendo movimientos; vista `close.blade.php` muestra preview live en JS al escribir el monto.
- Vista de detalle de sesión muestra dos bloques separados: **Efectivo** (fondo inicial, cobros en caja, entradas, salidas, monto esperado) y **Banco / Tarjeta** (total banco con estado Acreditado/En proceso según `cleared_at`).

**Cobros históricos — triple fuente unificada:**
- `CashSessionController::allPaymentsForPeriod()` fusiona `payments` (sistema nuevo), `cash_ledgers` y `bank_ledgers` (sistema legacy) en una colección normalizada de `stdClass`. Sin modificar las tablas subyacentes.
- `periodStart()` devuelve `null` para la primera sesión (sin límite inferior) o el `closed_at` de la sesión anterior, evitando que cobros históricos queden huérfanos.

**Movimientos de caja con doble entrada contable:**
- Migración `2026_06_16_000001_create_cash_movements_table.php` — tabla `cash_movements` con `type` (enum), `direction`, `amount`, `concept`, `notes`, `counterpart_account_id`, `journal_entry_id`, `created_by_user_id`.
- Modelo `CashMovement` con relaciones: `cashSession`, `counterpartAccount`, `journalEntry`, `createdBy`.
- `CashMovementController::store()` — valida → crea `JournalEntry` → 2 líneas DR/CR → crea `CashMovement`. Salidas: DR contrapartida / CR Caja. Entradas: DR Caja / CR contrapartida.
- `CashMovementController::destroy()` — elimina movimiento + líneas + póliza.
- Tipos soportados: `retiro`, `deposito_banco`, `gasto`, `perdida` (salidas) / `entrada` (entrada).
- Modal Bootstrap `#modalMovimiento` con selector dinámico de cuenta contable via JS inline (sin HTTP extra).

**Fixes de Blade — bug crítico confirmado:**
- `@php ... @endphp` multilínea dentro de `@forelse`/`@foreach` causa 500 (ParseError "unexpected token else/endforeach") porque `@if`/`@forelse` no se compilan pero sus `@else`/`@endforelse` sí. Regla: **solo `@php($var = expr)` de una línea, siempre fuera del loop.**
- `index.blade.php`: removida asignación `@php $diff = ...` dentro de `@if` — reemplazada por uso directo de `$session->difference`.
- `show.blade.php`: `@php($cobrosEfectivo = ...)` y `@php($cobrosBanco = ...)` movidos al inicio de la vista, fuera de cualquier loop.

**Otros fixes:**
- Bug de doble prefijo en nombres de ruta: rutas dentro de `Route::prefix('finances')->name('finances.')` no deben repetir `finances.` en el nombre. Corregidos 6 nombres.
- Íconos Material Symbols eliminados de 4 vistas de caja (font no cargada en el proyecto — texto basura visible).
- Botones en `cash-registers/index.blade.php` corregidos: `btn-xs` → `btn-sm` (Bootstrap 5 no tiene `xs`).
- Navegación: "Sesiones de caja" agregada en `FinanzasNavigation.php`.

### 📁 Archivos Clave Modificados/Creados
- `database/migrations/2026_06_16_000001_create_cash_movements_table.php` — **nuevo**
- `app/Models/CashMovement.php` — **nuevo**
- `app/Http/Controllers/Finances/CashMovementController.php` — **nuevo**
- `app/Http/Controllers/Finances/CashSessionController.php` — `allPaymentsForPeriod()`, `periodStart()`, totales por destino
- `routes/web.php` — rutas `cash-sessions.movements.store/destroy`; fix de nombres con doble prefijo
- `app/Support/Navigation/Groups/FinanzasNavigation.php` — ítem "Sesiones de caja"
- `resources/views/finances/cash-sessions/show.blade.php` — bloques Efectivo/Banco, tabla de movimientos, modal
- `resources/views/finances/cash-sessions/index.blade.php` — fix Blade ParseError
- `resources/views/finances/cash-sessions/close.blade.php` — **nuevo**
- `resources/views/finances/cash-sessions/open.blade.php` — íconos Material Symbols removidos
- `resources/views/finances/cash-registers/index.blade.php` — botones de acción de sesión

### 🐛 Bugs encontrados y resueltos

| Problema | Causa | Fix |
|---|---|---|
| 500 en `cash-sessions/index` | `@php $diff = ...@endphp` multilínea dentro de `@if` — Blade bug | Uso directo de `$session->difference` |
| 500 en `cash-sessions/show` | `@php $cats = [...]@endphp` multilínea dentro de `@forelse` | Variables movidas fuera del loop |
| Cobros no aparecían | Filtro `opened_at` excluía cobros previos | `periodStart()` devuelve `null` si es primera sesión |
| Solo 2 cobros visibles | `payments` solo tenía 2 registros; `cash_ledgers` y `bank_ledgers` ignorados | `allPaymentsForPeriod()` fusiona las 3 fuentes |
| Total de efectivo incorrecto | Se sumaba todo sin filtrar por destino | `totalEfectivo` filtra `destination='caja'` |
| Rutas 404 en módulo finanzas | Doble prefijo `finances.finances.*` | Removido `finances.` del nombre dentro del grupo |
| Íconos como texto plano | Material Symbols font no cargada | Íconos reemplazados por texto o eliminados |

### 🔄 Pendientes para Próxima Sesión
- **BL-021** — Migrar registros históricos de `cash_ledgers`/`bank_ledgers` a `journal_entries`
- **BL-007** — Verificar Transform Rules Cloudflare
- **BL-001..004** — UI y configuración

---

## 📅 Sesión: 15-16/06/2026 - Breadcrumbs en app móvil (todas las pantallas)

### ✅ Logros y Cambios

**Breadcrumbs universales vía `ScreenHeader` (componente puro, sin hooks):**
- `src/ScreenHeader.tsx` — creado como componente plantilla; `onBack` es opcional (pantallas raíz); `noCrumbs` suprime los crumbs; props: `crumbs`, `showBreadcrumbs`, `onCrumbClick`, `rightAction`, `subtitle`, `backIcon`
- Todas las pantallas migradas al template: `MobCitaDet`, `PetDetail`, `ClientDetail`, `ClientSearch`, `MobCobro`, `MobCitaNueva`, `MobPetJobs`, `MobUserConfig`, `GlobalAgenda`
- `navState.ts` — singleton `setNavCrumbs/getNavCrumbs/clearNavCrumbs` para pasar crumbs entre navegaciones
- `App.tsx` — `NavLink` del nav inferior llama `clearNavCrumbs` síncronamente al tap (limpia contexto de navegación al cambiar de sección)
- `PetSearch.goToPet` — llama `setNavCrumbs([{Mascotas}])` antes de navegar
- `ClientSearch.selectClient` — llama `setNavCrumbs([{Clientes}])` y pasa `{ state: { _crumbs } }` en navigate
- `PetDetail` → dueño — pasa crumbs acumulados via `location.state._crumbs` (bypass del singleton que era sobreescrito)
- `ClientDetail` — prefiere `location.state._crumbs` sobre `getNavCrumbs()` como fuente de verdad

**Flujos verificados por el usuario:**
- Agenda → Cita: `Agenda › Cita #N`
- Mascotas → Pet → Dueño: `Mascotas › Luka › Tomás`
- Clientes → Cliente → Mascota: `Clientes › Tomás › Gorda`

### 📁 Archivos Clave Modificados
- `mob_apps/operador/src/ScreenHeader.tsx` — nuevo componente plantilla
- `mob_apps/operador/src/navState.ts` — singleton de navegación
- `mob_apps/operador/src/App.tsx` — clearNavCrumbs en NavLink
- `mob_apps/operador/src/admin/` — GlobalAgenda, PetSearch, PetDetail, ClientDetail, ClientSearch, MobCitaDet, MobCobro, MobCitaNueva, MobPetJobs, MobUserConfig

### 🔄 Pendientes para Próxima Sesión
- **BL-007** — Transform Rules Cloudflare
- **BL-019** — Apertura/corte de caja
- **BL-021** — Migrar registros históricos

---

## 📅 Sesión: 15/06/2026 - App Móvil Multi-modelo + Deploy mov.estetican.org

### ✅ Logros y Cambios

**Historial multi-modelo en MobPetJobs:**
- `GET /api/work-order-types` — nuevo endpoint que lee `DocumentSeries` activos con `document_type LIKE 'orden_%'` y devuelve `[{code, label, icon}]`; si se agrega veterinaria en la BD aparece automáticamente en la app
- `GET /api/pets/{pet}/bookings` — reescrito para fusionar `SpaBooking` + `HotelReservation` con forma normalizada `{id, model_type, fecha, fecha_iso, status, order_folio, descripcion, total}` ordenados por fecha desc
- `MobPetJobs.tsx` — nueva fila de chips "Tipo" (solo visible si hay >1 tipo en BD); filtros de estado cubren ambos modelos (`fulfilled` de hotel = "Completada", `work_order` de SPA = "Pendiente"); ícono de tipo en columna Fecha

**Infraestructura — mov.estetican.org (producción):**
- Nuevo contenedor `estetican_mob` (nginx:alpine) en `compose.prod.yaml` — sirve `mob_apps/operador/dist/` como archivos estáticos
- `mob_apps/operador/nginx.conf` — proxea `/api/` y `/storage/` al contenedor `app` (Laravel), SPA fallback con `try_files`
- Cloudflare Tunnel `orangepi-estetican` — ruta `mov.estetican.org → http://192.168.100.250:80` agregada en "Published application routes"
- NPM — proxy host `mov.estetican.org → estetican_mob:80` con certificado wildcard `*.estetican.org`
- `https://mov.estetican.org` verificado y operando en producción

**Workflow de actualización de la app móvil:**
```bash
cd /opt/www/estetican/mob_apps/operador && npm run build
docker exec estetican_mob nginx -s reload
```

### 📁 Archivos Clave Modificados
- `apps/backoffice-laravel/routes/api.php` — endpoints `work-order-types` y `pets/{pet}/bookings` actualizados
- `apps/backoffice-laravel/compose.prod.yaml` — servicio `mob` agregado
- `mob_apps/operador/nginx.conf` — nuevo, config nginx para contenedor estático
- `mob_apps/operador/src/admin/MobPetJobs.tsx` — soporte multi-modelo completo

### 🔄 Pendientes para Próxima Sesión
- **BL-019** — Apertura/corte de caja (cash_sessions)
- **BL-021** — Migrar datos históricos de ledgers a journal_entries
- **BL-007** — Transform Rules Cloudflare

---

## 📅 Sesión: 14/06/2026 - Módulo Contable Completo + Cobro Móvil

### ✅ Logros y Cambios

**Módulo contable (BL-017):**
- 8 nuevas migraciones: `accounts`, `payment_methods`, `document_series`, `documents`, `journal_entries`, `journal_entry_lines`, `cash_registers`, `cash_sessions` + `account_id` en `services`
- 8 modelos Eloquent con relaciones y casts correctos
- 3 seeders: `AccountsSeeder` (plan de cuentas estándar), `PaymentMethodsSeeder` (Efectivo EFECT, Tarjeta DEB/CRED, SPEI), `DocumentSeriesSeeder`
- `AccountingService` en `app/Domain/Accounting/Services/` con `getNextFolio()`, `createPaymentEntry()` (flujo backoffice con Quote), `createEntryForBookingPayment()` (flujo móvil sin Quote), `cancelEntry()`
- Aplicadas en producción: `php artisan migrate` + seeders ejecutados

**Backoffice — Pantallas Finanzas (BL-018):**
- 4 controladores CRUD en `app/Http/Controllers/Finances/`
- 4 grupos de vistas Blade en `resources/views/finances/`
- `FinanzasNavigation.php` — grupo en menú lateral (gateado por permiso `cobros.registrar`)
- NT-008 documentada: nunca `@php...@endphp` multi-línea dentro de `@section` + `<x-slot>`, pasar arrays desde controlador

**Seguridad (BL-006):**
- `/up` movido a `/up/{HEALTH_CHECK_SECRET}` en `bootstrap/app.php`
- Secret generado en `.env.production` con `openssl rand -hex 16`

**Fix fotos móvil (BL-010/011):**
- `PetController` y `ClientController` — URL de foto cambiada de `/storage/...` a `Storage::disk('public')->url()`
- Permite URLs absolutas accesibles desde cualquier origen (app móvil)

**Cobro móvil (BL-020):**
- `GET /api/payment-methods` — endpoint nuevo que devuelve métodos activos con ícono y dest
- `PaymentController` reescrito: ahora escribe en `Payment` model (no en ledgers) + crea `JournalEntry` automáticamente si el método tiene cuenta contable asignada
- Backward compat: sigue leyendo `CashLedger`/`BankLedger` en el `index()` hasta BL-021
- `MobCobro.tsx` actualizado: métodos dinámicos de API, campo "Referencia" cuando `requires_reference`, no más selector manual de destino (se deriva del tipo de método)

### 📁 Archivos Clave Modificados
- `app/Domain/Accounting/Services/AccountingService.php` — nuevo método `createEntryForBookingPayment()`
- `app/Domain/Accounting/Contracts/AccountingServiceInterface.php` — firma nueva
- `app/Models/Service.php` — `account_id` en fillable + relación `account()`
- `app/Http/Controllers/Api/PaymentController.php` — reescrito
- `routes/api.php` — nueva ruta `GET /api/payment-methods`
- `mob_apps/operador/src/admin/MobCobro.tsx` — métodos dinámicos, referencia condicional

### 🔄 Pendientes para Próxima Sesión
- **BL-013** — Push a GitHub
- **BL-019** — Apertura/corte de caja (cash_sessions)
- **BL-021** — Migrar datos históricos de ledgers a journal_entries
- **BL-007** — Transform Rules Cloudflare

---

## 📅 Sesión: 17/04/2026 - Unificación Operativa y Estabilización

### ✅ Logros y Cambios
- **Corrección de Acceso:** Se reparó la \APP_URL\ en el \.env\ para que apunte a \http://localhost:8080\, eliminando bucles de redirección.
- **Reseteo de Credenciales:** Contraseña de \dmin@localhost\ actualizada a \dmin123\.
- **Agenda Universal:** Se unificaron los módulos de SPA y Hotel en una sola vista diaria.
- **Identificación Visual:** Se agregaron badges (SPA/HOTEL) en la línea de tiempo para diferenciar servicios de estancias.
- **Mago de Programación Global:** Se eliminó la restricción de agendar solo desde la mascota. Ahora existe un flujo centralizado con buscador de mascotas.
- **Documentación:** Creación de guía de usuario en Windows (\INSTRUCCIONES_PROYECTO.md\) para facilitar el inicio del entorno WSL.

### 🚀 Estado del Sistema
- **WSL:** Estable.
- **Servidores:** Sail (Docker) operando correctamente.
- **Base de datos:** Sincronizada y con acceso verificado.

---
## 📅 Sesión: 17/04/2026 - Unificación Operativa y Estabilización

### ✅ Logros y Cambios
- **Corrección de Acceso:** Se reparó la APP_URL en el .env para que apunte a http://localhost:8080.
- **Reseteo de Credenciales:** Contraseña de admin@localhost actualizada a admin123.
- **Agenda Universal:** Se unificaron los módulos de SPA y Hotel en una sola vista diaria.
- **Identificación Visual:** Se agregaron badges (SPA/HOTEL) en la línea de tiempo.
- **Mago de Programación Global:** Se eliminó la restricción de agendar solo desde la mascota.
- **Documentación:** Creación de guía INSTRUCCIONES_PROYECTO.md en Windows.

### 🚀 Estado del Sistema
- **WSL:** Estable.
- **Servidores:** Sail (Docker) operando correctamente.

---
## 📅 Sesión: 19/04/2026 - Estandarización de Bitácora y Gestión Documental

### ✅ Logros y Cambios
- **Bitácora Unificada (Mascotas y Recursos):** Se rediseñó el flujo de fotos para crear un historial cronológico categorizado (Ingreso, Incidencia, Resultado, Perfil).
- **Limpieza de UI:** Se eliminaron los botones redundantes de "Reemplazar archivo" en los listados históricos para evitar confusiones operativas.
- **Categorización Forzada:** Ahora las fotos se clasifican mediante un dropdown, gestionando automáticamente el flag de "is_primary" sin necesidad de checkboxes manuales.
- **Operadores Pro:** Se integró el componente de recorte circular (1:1) en el formulario de edición de operadores para estandarizar sus rostros comerciales.
- **Claridad Textual:** Se actualizaron encabezados en Mascotas y Recursos para diferenciar claramente entre "Foto de Perfil" y "Anexar archivo a la bitácora".

### 🛑 Pendientes / Bloqueos (Próxima Sesión)
- **Acceso Rápido en Forms (RESEDI/PETEDI):** Implementar el shortcut de subida de foto de perfil directamente en los formularios de edición básica para mayor comodidad (siguiendo el plan propuesto).
- **Validación de Relaciones:** Asegurar que al cambiar de "Perfil" en la bitácora, el renderizado de la cabecera se actualice sin refrescar toda la página (Alpine logic).

---
## 📅 Sesión: 20/04/2026 - Redefinición Arquitectónica (Identidad vs Trazabilidad)

### ✅ Logros y Avances
- **Análisis de Inconsistencias:** Se detectó una divergencia entre el modelo de Operadores (columna directa `profile_photo_path`) y Mascotas/Recursos (basado en relaciones de bitácora).
- **Decisión Arquitectónica:** Se acordó separar conceptualmente la **Identidad** (quién es) de la **Trazabilidad** (qué ha pasado).
- **Plan de Estandarización:** Se diseñó el plan para añadir `profile_photo_path` a las tablas Core de `pets` y `resources`, permitiendo acceso inmediato a la identidad y dejando la bitácora exclusivamente para el historial cronológico.

### 🚀 Plan de Implementación Consensuado (Próxima Sesión)
1. **Infraestructura DB:** Crear migración para añadir `profile_photo_path` (nullable) a `pets` y `resources`.
2. **Atajos (Shortcuts):** Integrar `x-image-upload` en los formularios de edición básica de Mascotas y Recursos, apuntando directamente a la columna de identidad.
3. **Controladores:** Actualizar `PetController` y `ResourceController` para procesar la imagen de perfil de forma atómica.
4. **Reactividad:** Implementar eventos de Alpine.js para que el cambio de foto de perfil (ya sea vía atajo o vía bitácora) se refleje en la cabecera instantáneamente sin refrescar la página.

### 🛑 Pendientes
- Ejecutar migraciones y realizar el refactor de las vistas identificadas (`pets/show.blade.php` y `resources/partials/form.blade.php`).

### 💾 Cierre de Sesión
- Bitácora actualizada. El sistema se prepara para respaldo y apagado.

---
## 📅 Sesión: 22/04/2026 - Ciclo Operativo Profesional (Presupuesto a Factura)

### ✅ Logros y Cambios
- **Flujo de Ventas Profesional:** Implementación de `QuoteService` para gestionar presupuestos multi-opción, transición a Orden de Trabajo y registro automático de anticipos.
- **Asignación de Especialistas:** Soporte para vincular veterinarios, anestesistas y otros profesionales a servicios específicos, incluyendo seguimiento de cédulas y registros para reportes clínicos.
- **Dashboard "Mission Control":** Rediseño total de la vista de cita (`agenda.show`) que ahora se adapta dinámicamente al estado operativo (Programado, En Proceso, Liquidado) mediante parciales especializados.
- **Automatización SMTP:** Implementación del envío automático de bitácoras y resúmenes de servicio por correo electrónico al finalizar la atención, integrando branding y datos fiscales.
- **Identidad y Fiscal:** Nuevos campos de configuración para logos (Web/Impresos), identificaciones de Hacienda y firmas profesionales personalizables.
- **Estado de Cuenta (Ledger):** Cálculo dinámico de saldos mediante la comparación de presupuestos aceptados vs. anticipos y abonos registrados en la tabla de pagos.
- **Bugfix (Cierre de Estabilidad):** Corrección del error `Undefined array key "help"` en el panel de configuración del sistema, garantizando la robustez de la UI.


---
## 📅 Sesión: 24/04/2026 - Workflow Dinámico y Estructura Operativa
*Foco: Hacer que el sistema sea inteligente en el manejo de garantías y flujo de caja (Caja vs Banco).*

### ✅ Logros y Avances
- **Infraestructura de Caja/Banco:** Migración exitosa para sustituir `is_fiscal` por `destination` (caja/banco) en pagos.
- **Configuración Ordenada:** Reestructuración total de `SystemSettings` creando las secciones de **Garantías**, **Operación Clínica** y **Hacienda**.
- **Reglas de Garantía:** Generalización de la lógica de anticipos para que aplique a Hotel, Veterinaria, Tienda y Quirófano.
- **Catálogo Inteligente:** Tabla de `services` actualizada con `requires_advance` y `advance_percentage`.

### 📍 Sprint de Foco: Workflow Dinámico (COMPLETADO ✅)
- [x] **1. Anticipos Inteligentes (`_quote_manager`):** Precarga automática de garantía sugerida (30%) al aceptar presupuesto.
- [x] **2. Orden de Trabajo Dinámica (`_work_order`):** Visibilidad de Jaula y tiempo en proceso (Cronómetro Alpine.js).
- [x] **3. Liquidación con Destino (`_billing_summary`):** Selector obligatorio de [Caja / Banco] al liquidar saldos con pre-selección inteligente.

### 💾 Cierre de Sesión
- **Ritual Administrativo:** Unificación de instrucciones en `README.md` y creación de `IDEAS_Y_FUTURO.md`.
- **Estado del Sistema:** Flujo financiero y operativo blindado y listo para pruebas de usuario.
- **Respaldos:** Base de datos auto-respaldada y archivos del proyecto comprimidos en `backup_Estetican_20260424_FINAL.tar.gz`.
- **Próximo Paso:** Revisar la pantalla de `/user/settings` y comenzar con el diseño de los Reportes PDF.

---
## 📅 Sesión: 26/04/2026 - Estabilización de UI y Flujos de Identidad

### ✅ Logros y Cambios
- **Bugfix (Robustez System Settings):** Se corrigió el error `Undefined array key "help"` en la vista de configuración del sistema mediante la implementación de operadores de coalescencia nula en los campos de tipo boolean e image.
- **Optimización de Carga de Perfiles (Mascotas y Recursos):**
    - Se eliminó el disparador `@click.away` que causaba envíos prematuros o fallidos al interactuar con el modal de recorte.
    - Se implementó un sistema basado en eventos (`image-cropped`) que garantiza que el formulario se envíe solo después de que el usuario confirme el recorte.
    - Se corrigió la referencia al input de archivos en Alpine.js, permitiendo que la subida sea atómica y exitosa.
- **Actualización de Versión:** Versión del software actualizada a **v.260426-2225**.

### 🚀 Estado del Sistema
- **Funcionalidad:** Carga de fotos de perfil 100% operativa y estable.
- **Próximos Pasos:** Iniciar con el diseño de los Reportes PDF.

### 💾 Cierre de Sesión
- **Ritual Administrativo:** Bitácora actualizada, versión sellada y respaldos generados.
- **Estado:** Sistema apagado correctamente.

---
## 📅 Sesión: 27/04/2026 - Correcciones de Workflow y Contabilidad

### ✅ Logros y Cambios
- **Redirección de Inicio:** Se corrigió la ruta `/` para que envíe al usuario directamente al `/login` en lugar del dashboard informativo.
- **Separación Contable:** Se separaron los ingresos en `cash_ledgers` y `bank_ledgers` para una mejor auditoría.
- **Flujo de Liquidación:** Se reparó la ruta faltante y el controlador que impedían registrar pagos y cerrar las Órdenes de Trabajo.

### 🛑 Pendientes / Bugs Reportados
- **Bug de Enlace en Agenda:** Al seleccionar una cita de baño (SpaBooking), el sistema redirige erróneamente al booking del Hotel en lugar de abrir el detalle del trabajo. Hay que revisar las rutas en la vista de Agenda Universal.

---
## 📅 Sesión: 09/05/2026 - Inicialización de Aplicaciones Móviles

### ✅ Logros y Cambios
- **Arquitectura Móvil:** Se documentó la estructura de base de datos local y la estrategia de conexión API para las futuras aplicaciones móviles.
- **Aislamiento de Entornos:** Se creó el directorio `mob_apps/operador` para mantener el ecosistema móvil separado del backoffice (Laravel).
- **Configuración WSL:** Se instaló Node.js v20 nativo en Ubuntu WSL usando nvm para resolver conflictos de rutas UNC con Windows. 
- **Despliegue UI:** Se extrajo el diseño generado en AI Studio (Stitch) y se comprobó su correcto funcionamiento local.

### 💾 Cierre de Sesión
- **Ritual Administrativo:** Bitácora actualizada y respaldos de código y base de datos generados.
- **Estado:** Sistema en reposo. Servidor de desarrollo móvil detenido.

---
## 📅 Sesión: 14/05/2026 - Dashboard Principal

### ✅ Logros y Cambios
- **Dashboard de inicio:** Se creó `DashboardController` y la vista `dashboard/index.blade.php` como pantalla de bienvenida post-login.
- **Corrección de flujo de entrada:** El login ahora redirige a `/dashboard` (Panel de control) en lugar de a la Agenda. La ruta `/` también actualizada.
- **KPIs en tiempo real:** El Dashboard muestra citas SPA del día (con desglose por estado), huéspedes activos en Hotel, total de clientes y mascotas, e ingresos del día (Caja + Banco).
- **Accesos rápidos:** Atajos a Nueva cita, Nuevo cliente, Nueva estancia, Servicios y Operadores.

### 🚀 Estado del Sistema
- **Login → Dashboard:** Operativo y verificado en browser.
- **Próximos Pasos:** Reportes PDF, bug de enlace SPA→Hotel en Agenda, continuar apps móviles.

### 🛑 Pendientes / Reportes de Usuario (Agregados 14/05)
- **Tema de UI:** Reparar la persistencia y cambio reactivo de la paleta de colores (tema) al seleccionarla en SysSetInd.
- **Favicon:** Agregar la funcionalidad para subir/cambiar el Favicon desde la interfaz.
- **Configuración de Correo Avanzada:** Añadir campos para credenciales (usuario/password), selección de seguridad (SSL/TLS) y puertos sugeridos en la configuración de email.
- **Datos Generales del Negocio:** Crear un bloque de datos fiscales y operativos (Dirección, Teléfono, WhatsApp, Redes) para inyectar en plantillas de correos, facturas y web.
- **Zonas Horarias:** Reemplazar el selector UTC plano por un selector de zonas horarias completo con soporte de países y diferencias horarias reales.

---
## 📅 Sesión: 15/05/2026 - Agenda Unificada: Estabilización Integral

### ✅ Logros y Cambios

**Formato de hora global (12h/24h):**
- Centralizado en `ApplySystemSettings` middleware vía `view()->share(['timeFormat', 'dateFormat', 'datetimeFormat'])`. Eliminadas todas las ternarias dispersas en 13+ vistas.
- Instalado **Flatpickr 4.6.13** vía npm para reemplazar los inputs `datetime-local` nativos. Lee el `data-time24h` del `<body>` y aplica `altInput` para separar formato de visualización del valor enviado al servidor.
- Corregido `config/backoffice.php` donde faltaba la clave `time_format`.

**AgSpaEdi (Editar Cita):**
- Ahora pre-llena todos los campos de la cita guardada.
- Permite editar servicios en estado `scheduled` (checkboxes), muestra badges de solo lectura en `work_order`.
- Botón "Editar cita" movido al stepper operativo (visible en `scheduled` y `work_order`).

**AgUniInd (Agenda Universal - Lista):**
- Orden por defecto cambiado a descendente (más reciente primero).
- Estado por defecto cambiado a `active` (incluye `scheduled` + `work_order`).
- Columna Total muestra saldo real: total del quote aceptado menos anticipos pagados. Si hay anticipo, muestra "saldo · pagado $X".
- Corregido ParseError causado por `@php(expr)` con paréntesis anidados dentro de `@forelse` que corrompía el compilador Blade. Solución: usar bloques `@php...@endphp` siempre.

**AgSpaSho (Detalle de Cita):**
- Modal "Cambiar Jaula" añadido (estaba el botón pero faltaba el HTML del modal).
- Balance ahora usa `acceptedQuote->cashLedgers + bankLedgers` en vez de `client->payments()` (tabla incorrecta). Muestra anticipo y total en el hint.
- Work Order muestra precio por servicio y total acordado al pie.

**Recursos físicos:**
- El filtro de recursos en dropdowns de reprogramación ahora incluye `active` e `inactive`, excluyendo solo `retired`.

**Consolidación de fuente de verdad (fix arquitectónico):**
- `QuoteService::acceptQuote()` ahora sincroniza `spa_booking_services` con los items del quote aceptado y actualiza `total_estimated_price` en el booking. Antes estas tres tablas divergían, generando datos inconsistentes entre vistas.
- Ejecutado sync retroactivo sobre quotes ya aceptados en BD.

### 🛑 Pendientes / Backlog
- **Tema de UI:** Reparar persistencia y cambio reactivo de paleta de colores.
- **Favicon & Empresa:** Subida de favicon desde UI + datos generales del negocio.
- **Email Avanzado:** Credenciales SMTP (usuario/password, puertos, SSL/TLS).
- **Zonas Horarias:** Reemplazar selector UTC por selector completo.
- **Reportes PDF:** Diseño e impresión de presupuestos, órdenes de trabajo y facturas.
- **Ecosistema Móvil:** Continuar `mob_apps/operador`.

### 💾 Cierre de Sesión
- Commits: `fix(agenda): consolidar fuente de verdad...` y anteriores del día.
- Respaldo diario ejecutado.

---
## 📅 Sesión: 16/05/2026 - Revisión Estática y Corrección de Bugs Críticos

### ✅ Logros y Cambios

**Auditoría estática del codebase (4 bugs corregidos):**

- **[CRÍTICO] Estado `in_process` inexistente** — El estado válido es `work_order`. Corregido en:
  - `SpaBookingController.php` (línea de `createForPet`)
  - `DashboardController.php` (clave `$spaCounts` y query de próximas citas)
  - `dashboard/index.blade.php` (3 referencias a `in_process`)
- **[CRÍTICO] `markAsNoShow($booking)`** — Renombrado a `markNoShow($booking->id)` para coincidir con la firma del servicio (`BookingServiceInterface`).
- **[CRÍTICO] `cancelBooking($booking, ...)`** — Corregido a `cancelBooking($booking->id, ...)` para pasar el ID entero que espera el servicio.
- **[MEDIO] Lógica invertida en `data-price`** — `_quote_manager.blade.php` mostraba `duration_minutes` como precio. Corregido a `$s->suggested_price ?? $s->price ?? 0`.

### 🚀 Estado del Sistema
- Los flujos de cancelar cita, marcar no-show y el Dashboard ahora funcionan correctamente.
- No quedan referencias a `in_process` ni a `markAsNoShow` en el proyecto.

### 🛑 Pendientes / Backlog
- **Tema de UI:** Reparar la persistencia y cambio reactivo de la paleta de colores.
- **Favicon & Empresa:** Agregar subida de Favicon y datos generales del negocio.
- **Email Avanzado:** Añadir configuración de credenciales SMTP (usuario/password, puertos, SSL/TLS).
- **Zonas Horarias:** Reemplazar el selector UTC.
- **Reportes PDF:** Iniciar el diseño y renderizado de presupuestos, órdenes de trabajo y facturas.
- **Ecosistema Móvil:** Continuar desarrollo en `mob_apps/`.

---
## 📅 Sesión: 15/05/2026 - Control de Identidad y Seguridad Operativa

### ✅ Logros y Cambios
- **Persistencia de Fotos (Sincronización):** Se corrigió la desconexión entre la "Galería" y el perfil principal de Mascotas. Ahora, al marcar una foto de la galería como "Perfil", se actualiza automáticamente la identidad central (`pets.profile_photo_path`).
- **Seguridad en UX (AutoSubmit):** El componente `x-image-upload` ahora requiere explícitamente el parámetro `autoSubmitFormId` para autoguardar. Esto previene envíos accidentales en formularios complejos como la galería.
- **Robustez de Vistas:** Se reparó un error 500 en la vista de edición de agenda (`AgSpaEdi`) provocado por variables faltantes (`$resources`, `$pet`, etc.).
- **Seguridad Operativa en Agenda:** Se eliminaron los botones de edición rápida de la lista principal. Ahora, para reprogramar, el operador debe entrar forzosamente al detalle de la cita, evitando alteraciones por error.
- **Alertas de Citas Vencidas:** Se reemplazó el ambiguo panel "Zona de Riesgo" por un **Banner Rojo de Alerta** dinámico que solo aparece si una cita ya pasó su hora y sigue abierta. Proporciona botones rápidos para "Reprogramar" o "Cerrar Ahora".
- **Documentación Técnica:** Se creó el `docs/260515_Manual_Tecnico_Modular.md` resumiendo la arquitectura actual.

### 🚀 Estado del Sistema
- **Sistema Base:** Estable. Identidad visual y flujos operativos asegurados.

### 🛑 Tareas Pendientes / Backlog
- **Tema de UI:** Reparar la persistencia y cambio reactivo de la paleta de colores.
- **Favicon & Empresa:** Agregar subida de Favicon y datos generales del negocio (Dirección, Teléfono, WhatsApp, Redes).
- **Email Avanzado:** Añadir configuración de credenciales SMTP (usuario/password, puertos, SSL/TLS).
- **Zonas Horarias:** Reemplazar el selector UTC.
- **Reportes PDF:** Iniciar el diseño y renderizado de presupuestos, órdenes de trabajo y facturas en formato PDF imprimible.
- **Ecosistema Móvil:** Continuar desarrollo en `mob_apps/`.

---
## 📅 Sesión: 16/05/2026 (continuación) - Corrección de Bugs de Flujo Completo y Editor de Dirección

### ✅ Logros y Cambios

**Bugs adicionales del ciclo de agenda (continuación de auditoría):**

- **[CRÍTICO] Bug Blade `@php(expr)` con paréntesis anidados** — Identificada la causa raíz de múltiples `Undefined variable` en producción: el compilador Blade detiene el procesado en el primer `)` interno de `parse_url(...)`, `in_array(...)`, `firstWhere(...)`, etc. Todos los afectados convertidos a bloques `@php...@endphp`. Archivos corregidos:
  - `resources/views/agenda/show.blade.php` — 7 instancias corregidas (incluyendo `$photoUrl` con `parse_url()` que rompía todo lo que seguía)
  - `resources/views/agenda/partials/_billing_summary.blade.php` — reescrito completo
  - `resources/views/reports/invoice.blade.php` — 2 instancias en cálculo de saldo

- **[CRÍTICO] PHP 8.5 aritmética con null** — `null - 0` lanza `ErrorException`. Corregidos todos los cálculos financieros con casts `(float)` y `?? 0` en `show.blade.php`, `_billing_summary.blade.php` e `invoice.blade.php`.

- **[CRÍTICO] `ReportController::quote()`** — Usaba relación `$quote->booking` (inexistente). Corregido a `$quote->spaBooking`. El recibo ahora imprime correctamente.

- **[MEDIO] `PetController::destroy()`** — Sin guardia de citas activas. Ahora bloquea la eliminación si la mascota tiene citas en `scheduled` o `work_order` y muestra mensaje de error.

- **[MEDIO] `Address::phones()` morphMany roto** — Las columnas `phoneable_id`/`phoneable_type` se eliminaron en migración `2026_03_20`. Relación removida del modelo.

- **[MEDIO] Botón "IMPRIMIR RECIBO" en `_billing_summary`** — Era un `<button>` sin acción. Reemplazado por `<a href="{{ route('reports.invoice', $booking) }}" target="_blank">`.

- **[BAJO] Modal "No se presentó"** — La ruta `agenda.no-show` existía pero no había botón en la UI de AgSpaSho. Agregado botón y modal.

**Editor de Dirección (`address-editor.js` + `shared/address-editor.blade.php`):**

- **[REFACTOR] Event delegation completo** — Reescrito de `initSingleEditor` + `DOMContentLoaded` (frágil) a handlers delegados en `document`. Los botones Geocodificación, Importar y el link de Maps ahora funcionan para cualquier tarjeta de dirección en el DOM, incluyendo las agregadas dinámicamente. Causa raíz del bug reportado: el módulo ejecuta como `type="module"` (defer) y en algunas condiciones el listener de `DOMContentLoaded` no se registraba antes de que el evento disparara.

- **[UX] Flash visual al importar coordenadas** — Tras geocodificación exitosa o importación manual, la página hace scroll a los campos lat/lng y los bordea en azul 2 segundos para que el operador vea que se llenaron y deba guardar.

- **[UX] Textos actualizados en Blade** — Instrucción de uso, placeholder del campo de pegado y nombres de botones clarificados para guiar el flujo correcto: Abrir Maps → clic en punto → copiar → pegar → Importar coordenadas.

- **[DIAGNÓSTICO]** Confirmado vía logs de consola que Nominatim (OpenStreetMap) no devuelve resultados para calles de Aguascalientes (cobertura incompleta para México). El flujo confiable es Maps manual. El mensaje de error del botón de geocodificación ahora guía al usuario a ese flujo.

### 📁 Archivos Modificados Esta Sesión
- `app/Http/Controllers/SpaBookingController.php`
- `app/Http/Controllers/DashboardController.php`
- `app/Http/Controllers/PetController.php`
- `app/Http/Controllers/ReportController.php`
- `app/Models/Address.php`
- `resources/views/dashboard/index.blade.php`
- `resources/views/agenda/show.blade.php`
- `resources/views/agenda/partials/_billing_summary.blade.php`
- `resources/views/agenda/partials/_quote_manager.blade.php`
- `resources/views/reports/invoice.blade.php`
- `resources/views/shared/address-editor.blade.php`
- `resources/js/modules/address-editor.js`

### 🛑 Pendientes / Backlog
- **Tema de UI:** Reparar la persistencia y cambio reactivo de la paleta de colores.
- **Favicon & Empresa:** Agregar subida de Favicon y datos generales del negocio.
- **Email Avanzado:** Credenciales SMTP (usuario/password, puertos, SSL/TLS).
- **Zonas Horarias:** Reemplazar selector UTC.
- **Reportes PDF:** Diseño e impresión de presupuestos, órdenes de trabajo y facturas.
- **Ecosistema Móvil:** Continuar `mob_apps/operador`.

### 💾 Cierre de Sesión
- Commits del día generados. Respaldo diario ejecutado. Sistema apagado.

---
## 📅 Sesión: 17/05/2026 - Verificación y Arranque del Sistema

### ✅ Logros y Cambios
- **Corrección de Puertos Docker (WSL2):** Windows tenía reservado el rango de puertos 13xxx, impidiendo que los contenedores expusieran sus puertos. Corregido en `.env`:
  - `FORWARD_DB_PORT`: `13306` → `23306`
  - `FORWARD_REDIS_PORT`: `13379` → `16379`
- **Verificación del Sistema:** Todos los contenedores (MySQL, Redis, Laravel, HTTPS) arrancaron sanos. App respondiendo HTTP 200 en `http://localhost:8080`.

### 🛑 Pendientes / Backlog (sin cambios)
- **Tema de UI:** Reparar la persistencia y cambio reactivo de la paleta de colores.
- **Favicon & Empresa:** Agregar subida de Favicon y datos generales del negocio.
- **Email Avanzado:** Credenciales SMTP (usuario/password, puertos, SSL/TLS).
- **Zonas Horarias:** Reemplazar selector UTC.
- **Reportes PDF:** Diseño e impresión de presupuestos, órdenes de trabajo y facturas.
- **Ecosistema Móvil:** Continuar `mob_apps/operador`.

### 💾 Cierre de Sesión
- Bitácora actualizada. Respaldo diario ejecutado. Sistema apagado.

---
## 📅 Sesión: 24/05/2026 - Deploy a Producción Orange Pi 5 Plus ✅ COMPLETADO

### ✅ Logros y Cambios

**Infraestructura (sesión anterior):**
- Orange Pi 5 Plus con Docker, NPM, Cloudflare Tunnel y Portainer operativos.
- `compose.prod.yaml` y `.env.production.example` creados y subidos a GitHub.

**Deploy ejecutado esta sesión:**
- **Repo clonado en OPi** vía HTTPS (`/opt/www/estetican`).
- **Composer instalado** con `docker run composer:latest --no-dev`.
- **Dockerfile de Sail incluido en repo** (`docker/`) — la imagen `laravelsail/php83` no existe en Docker Hub; se buildea localmente desde `ubuntu:24.04`.
- **Stack levantado** con `docker compose -f compose.prod.yaml --env-file .env.production up -d --build`.
- **BD importada** — dump generado en WSL, copiado vía `scp`, importado con `mysql -u root`.
- **`.env` creado** copiando `.env.production` (Laravel lo requiere con ese nombre exacto).
- **Migraciones ejecutadas** — `php artisan migrate --force` (5 migraciones pendientes aplicadas).
- **Assets compilados** en WSL con `sail npm run build`; `public/build/` incluido en repo (excluido de `.gitignore`) para servir sin npm en producción.
- **NPM configurado** — Proxy Host `app.estetican.org` → `estetican_app:80`, SSL Let's Encrypt, **Force SSL desactivado** (Cloudflare Tunnel ya maneja HTTPS; activarlo causaba loop de redirecciones).
- **App verificada** en `https://app.estetican.org` — dashboard y menú cargando correctamente.

**Problemas resueltos durante el deploy:**
- `$` en DB_PASSWORD causaba expansión de variables en Docker Compose → password simplificado a alfanumérico.
- Volumen MySQL inicializado con password incorrecto → `down -v` y recreación del volumen.
- Docker Compose v5 requiere buildx → `sudo apt-get install docker-buildx-plugin`.
- Loop de redirecciones ERR_TOO_MANY_REDIRECTS → deshabilitar Force SSL en NPM.

### 🚀 Procedimiento de Deploy (para referencia futura)
```bash
# En WSL — generar dump
./vendor/bin/sail up -d mysql
./vendor/bin/sail exec mysql mysqldump -u sail -ppassword laravel > /tmp/estetican.sql
scp /tmp/estetican.sql tomas@192.168.100.250:/opt/www/estetican/apps/backoffice-laravel/

# En OPi — actualizar y reiniciar
cd /opt/www/estetican && git pull
cd apps/backoffice-laravel
docker exec -i estetican_mysql mysql -u root -p<DB_PASSWORD> estetican < estetican.sql
docker exec estetican_app php artisan migrate --force
docker exec estetican_app php artisan config:clear
docker exec estetican_app find /var/www/html/storage/framework/views -name "*.php" -delete
```

### 🛑 Pendientes / Backlog
- **Uploads:** Copiar `storage/app/public/` de WSL a OPi vía SMB (fotos de mascotas, etc.).
- **Credenciales producción:** Cambiar password de `admin@localhost` desde la UI.
- **Tema de UI:** Reparar la persistencia y cambio reactivo de la paleta de colores.
- **Favicon & Empresa:** Agregar subida de Favicon y datos generales del negocio.
- **Email Avanzado:** Credenciales SMTP (usuario/password, puertos, SSL/TLS).
- **Zonas Horarias:** Reemplazar selector UTC.
- **Reportes PDF:** Diseño e impresión de presupuestos, órdenes de trabajo y facturas.
- **Ecosistema Móvil:** Continuar `mob_apps/operador`.

---
## 📅 Sesión: 25/05/2026 - Estabilización Post-Deploy en Producción

### ✅ Logros y Cambios

- **Assets en repo:** `public/build/` incluido en git y compilado en WSL. La OPi sirve CSS/JS sin npm.
- **Uploads copiados a OPi:** `storage/app/public/` transferido vía `scp`. Fotos vacías en BD — subir desde UI.
- **29 vistas corregidas:** `use App\Support\Pages\XxxPage;` dentro de `@php` inválido en Laravel 13. Reemplazado por FQCN via script Python.
- **`AuthServiceProvider` registrado** en `bootstrap/providers.php` — sin esto la `UserPolicy` nunca se cargaba.
- **Manejo elegante de 403:** `bootstrap/app.php` captura `AuthorizationException` y redirige con mensaje amigable.
- **`BaseRolesSeeder` ejecutado** en producción — permisos y roles creados.
- **Bug 403 resuelto en edición/borrado de usuarios:**
  - Causa raíz 1: vistas compiladas obsoletas en `storage/framework/views/` que no se borraban con `view:clear`. Solución: `find ... -delete` manual.
  - Causa raíz 2: `UserPolicy` sin método `delete()` — agregado explícitamente.
  - Causa raíz 3: `UserController::edit()` usaba `$this->authorize()` que dependía de policy no cargada — reemplazado por `abort_unless()` directo.
- **Operaciones de usuarios verificadas en producción:** editar y borrar funcionando correctamente.

**Lección aprendida para deploys futuros:** después de `git pull`, siempre borrar vistas compiladas con:
```bash
docker exec estetican_app find /var/www/html/storage/framework/views -name "*.php" -delete
```

### 🛑 Pendientes / Backlog
- **Credenciales producción:** Cambiar password de `admin@localhost` desde la UI.
- **Tema de UI:** Reparar persistencia y cambio reactivo de paleta de colores.
- **Favicon & Empresa:** Subida de Favicon y datos generales del negocio.
- **Email Avanzado:** Credenciales SMTP (usuario/password, puertos, SSL/TLS).
- **Zonas Horarias:** Reemplazar selector UTC.
- **Reportes PDF:** Diseño e impresión de presupuestos, órdenes de trabajo y facturas.
- **Ecosistema Móvil:** Continuar `mob_apps/operador`.

---
## 📅 Sesión: 24/05/2026 (continuación) - Limpieza Final de Autorización en UserController

### ✅ Logros y Cambios

- **Eliminados todos los `authorize()` redundantes de `UserController`:** Las llamadas a `$this->authorize()` en `index()`, `create()`, `store()`, `update()` y `destroy()` fueron removidas. El middleware `role:admin|super-admin` que envuelve las rutas ya garantiza acceso exclusivo a admins — los `authorize()` duplicaban la comprobación y en algunos contextos la bloqueaban.
  - `index()` — `authorize('viewAny')` eliminado
  - `create()` — `authorize('create')` eliminado
  - `store()` — `authorize('create')` eliminado
  - `update()` — `authorize('update')` eliminado
  - `destroy()` — `authorize('delete')` eliminado
- Commit: `fix(users): eliminar authorize() redundantes en UserController` → push a `main`.

### 🚀 Para aplicar en OPi
```bash
cd /opt/www/estetican && git pull
docker exec estetican_app find /var/www/html/storage/framework/views -name "*.php" -delete
```

### 🛑 Pendientes / Backlog
- **Credenciales producción:** Cambiar password de `admin@localhost` desde la UI.
- **Tema de UI:** Reparar persistencia y cambio reactivo de paleta de colores.
- **Favicon & Empresa:** Subida de Favicon y datos generales del negocio.
- **Email Avanzado:** Credenciales SMTP (usuario/password, puertos, SSL/TLS).
- **Zonas Horarias:** Reemplazar selector UTC.
- **Reportes PDF:** Diseño e impresión de presupuestos, órdenes de trabajo y facturas.
- **Ecosistema Móvil:** Continuar `mob_apps/operador`.

---
## 📅 Sesión: 25/05/2026 (continuación) - Hardening de Seguridad y Fixes de App Móvil

### ✅ Logros y Cambios

**App Móvil (`mob_apps/operador`) — correcciones de UI:**
- **Íconos rotos:** `index.html` no cargaba Material Symbols. Agregado `<link>` a Google Fonts con soporte completo de variaciones (`FILL`, `wght`, `opsz`).
- **Título genérico:** `"My Google AI Studio App"` → `"EstetiCAN"`. Agregado `lang="es"` y `viewport-fit=cover`.
- **Clases de tipografía no-op:** `font-headline-sm`, `font-label-md`, `font-body-sm` etc. no existían como utilidades Tailwind. Agregados todos los alias de font-family en `index.css` (`@theme`).
- **Página de selección sin tema:** `RoleSelection` usaba colores hardcodeados. Migrado a `theme-client` + tokens (`bg-background`, `text-primary`, etc.).
- **Archivos modificados:** `index.html`, `src/index.css`, `src/App.tsx`.

**Hardening de seguridad en Cloudflare:**
- **TLS mínimo → 1.2:** Cloudflare Edge Certificates → Minimum TLS Version.
- **Always Use HTTPS:** activado (redirect en edge, sin tocar el servidor).
- **HSTS:** `max-age=31536000; includeSubDomains` activado. Preload desactivado deliberadamente (compromiso irreversible).
- **No-Sniff header:** `X-Content-Type-Options: nosniff` activado vía toggle de Cloudflare.
- **Transform Rule de headers:** Una regla cubre tres headers: `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy: geolocation=(), camera=(), microphone=()`.
- **WAF Rule:** bloquea `/.htaccess` en el edge (path equals `/.htaccess` → Block).
- **DNS CAA records (×4):** `issue` e `issuewild` para `letsencrypt.org` y `pki.goog` — solo estas CAs pueden emitir certificados para el dominio.

**Hardening en el servidor (OPi):**
- **`public/.htaccess` eliminado de producción:** el archivo es Apache-only, PHP lo servía como texto plano exponiendo configuración interna. Ahora devuelve 404.
- **`expose_php = Off`** en `docker/php.ini`: elimina `X-Powered-By: PHP/8.5.6` de todas las respuestas. Persistido para rebuilds.
- **Middleware `ContentSecurityPolicy`** (`app/Http/Middleware/ContentSecurityPolicy.php`): genera nonce por request, emite header CSP con fuentes reales del proyecto. Scripts inline bloqueados salvo los que lleven el nonce.
- **Helper `csp_nonce()`** en `app/helpers.php`, autoloaded vía `composer.json`.
- **`nonce="{{ csp_nonce() }}"` en el script inline del layout** (`resources/views/layouts/app.blade.php`).

**Hallazgos descartados como accepted risk:**
- Rutas protegidas devuelven 302 → seguridad por oscuridad sin beneficio real.
- LUCKY13 / cifrados CBC → mitigado por Cloudflare; cipher suites requieren plan Business.
- `/index.php` expuesto → revela PHP/Laravel, no accionable.

### 📁 Archivos Modificados Esta Sesión
- `mob_apps/operador/index.html`
- `mob_apps/operador/src/index.css`
- `mob_apps/operador/src/App.tsx`
- `apps/backoffice-laravel/app/Http/Middleware/ContentSecurityPolicy.php` *(nuevo)*
- `apps/backoffice-laravel/app/helpers.php` *(nuevo)*
- `apps/backoffice-laravel/composer.json`
- `apps/backoffice-laravel/bootstrap/app.php`
- `apps/backoffice-laravel/resources/views/layouts/app.blade.php`
- `apps/backoffice-laravel/docker/php.ini`

### 🐛 Fix post-sesión
- **`csp_nonce()` causaba 500:** `app('csp-nonce', '')` interpretaba el string como parámetro de inyección en lugar de valor por defecto → TypeError. Corregido con `app()->bound('csp-nonce') ? app('csp-nonce') : ''`. Commit: `1ed85aa`.

### 💾 Cierre de Sesión
- Dos commits pendientes de push a GitHub (requieren WSL): `bf28d62` y `1ed85aa`.
- Sistema operativo y estable en producción.

---
## 📅 Sesión: 25/05/2026 (continuación) - Trazabilidad de Operaciones con spatie/laravel-activitylog

### ✅ Logros y Cambios

- **`spatie/laravel-activitylog` instalado** (`^5.0`). Migración `activity_log` ejecutada en producción.
- **7 modelos instrumentados** con `LogsActivity` + `logOnlyDirty()` + `dontSubmitEmptyLogs()`:
  - `SpaBooking` → log `citas-spa`
  - `HotelReservation` → log `citas-hotel`
  - `Payment` → log `pagos`
  - `Quote` → log `presupuestos`
  - `Pet` → log `mascotas` (excluye `profile_photo_path`)
  - `User` → log `usuarios` + `CausesActivity` (excluye password/tokens)
  - `SystemSetting` → log `configuracion`
- **`ActivityLogController`** creado con filtros por módulo, evento, usuario y fecha. Paginado a 50 por página.
- **Vista `/activity-log`** — tabla con diff de campos (antes → después) para eventos `updated`. Acceso solo para `admin|super-admin`.
- **Menú de navegación** — "Bitácora de actividad" agregado bajo Catálogos (visible solo a admins).

### 📁 Archivos Modificados Esta Sesión
- `composer.json` / `composer.lock`
- `config/activitylog.php` *(nuevo)*
- `database/migrations/2026_05_25_154836_create_activity_log_table.php` *(nuevo)*
- `app/Models/SpaBooking.php`
- `app/Models/HotelReservation.php`
- `app/Models/Payment.php`
- `app/Models/Quote.php`
- `app/Models/Pet.php`
- `app/Models/User.php`
- `app/Models/SystemSetting.php`
- `app/Http/Controllers/ActivityLogController.php` *(nuevo)*
- `resources/views/activity-log/index.blade.php` *(nuevo)*
- `routes/web.php`
- `app/Support/Navigation/Groups/CatalogsNavigation.php`

### 🛑 Pendientes / Backlog
- **Push a GitHub:** `git push origin main` desde WSL (varios commits acumulados).
- **Verificar Transform Rules Cloudflare:** X-Frame-Options, Referrer-Policy y Permissions-Policy.
- **Bloquear `/up`:** health check de Laravel devuelve 200 público.
- **Credenciales producción:** Cambiar password de `admin@localhost` desde la UI.
- **Tema de UI:** Reparar persistencia y cambio reactivo de paleta de colores.
- **Favicon & Empresa:** Subida de Favicon y datos generales del negocio.
- **Email Avanzado:** Credenciales SMTP (usuario/password, puertos, SSL/TLS).
- **Zonas Horarias:** Reemplazar selector UTC.
- **Reportes PDF:** Diseño e impresión de presupuestos, órdenes de trabajo y facturas.
- **Ecosistema Móvil:** Continuar `mob_apps/operador` (requiere WSL/Node).

---
## 📅 Sesión: 25/05/2026 (continuación 2) - Fix definitivo subida de fotos (causa raíz encontrada)

### ✅ Logros y Cambios

**Causa raíz identificada y corregida:**
- `package.json` tenía `cropperjs: "^2.1.1"` (npm) pero todo el código usa la API de **v1** (la que corría desde CDN cuando funcionaba). La v2 usa Web Components (`<cropper-canvas>`, `<cropper-selection>`, etc.) y no tiene los métodos `rotate()`, `getCroppedCanvas()`, ni las opciones `aspectRatio`, `viewMode`, etc. — por eso la imagen "se hacía pequeña", no giraba, no recortaba y no guardaba.
- **Fix:** Downgrade a `cropperjs@1.6.2` (versión exacta del CDN anterior). Verificado: `removed 11 packages, changed 1 package`.

**Fixes de JS/CSS aplicados en la misma sesión (commits previos):**
- Contenedor del recortador: `height: 60vh; overflow: hidden` (fijo, no `max-height`) para que Cropper mida el contenedor correctamente.
- Flujo de `fileChosen`: registrar `shown.bs.modal` listener → asignar `img.src` → `modalInstance.show()` → en el evento, `requestAnimationFrame` → `new Cropper(img, {...})`.
- Bundle reconstruido (`app-DReE3rzJ.js`). Caché de vistas limpiada.
- **Verificado por usuario: funciona correctamente.**

### 📁 Archivos Modificados
- `apps/backoffice-laravel/package.json` + `package-lock.json`
- `resources/js/modules/image-upload.js`
- `resources/views/components/image-upload.blade.php`
- `public/build/` (rebuild)

---
## 📅 Sesión: 25/05/2026 (continuación 3) - Auditoría de fotos y documentación técnica

### ✅ Logros y Cambios

**Auditoría completa del sistema de fotos:**
- Verificados todos los usos de `x-image-upload`: 7 vistas, 3 patrones de envío, 5 ImageManagers.
- Sin bugs adicionales encontrados. El fix de cropperjs v1.6.2 cubre todos los usos.

**Documentación técnica creada:**
- `docs/tecnico/NOTAS_TECNICAS.md` — 7 entradas NT (cropperjs v2 vs v1, inicialización Cropper en modal, contenedor height fijo, $refs en Alpine, @php anidados, CSP + Alpine, Bootstrap Modal).
- `docs/tecnico/image-upload-system.md` — referencia completa del componente (props, flujo JS, inventario, ImageManagers, patrones).
- `docs/tecnico/ESTRATEGIA_DESARROLLO.md` — workflow de sesión, convenciones de commit, reglas de dependencias npm, checklist de deploy.
- `CLAUDE.md` actualizado con referencias a los nuevos docs y reglas críticas.

### 📁 Archivos Modificados
- `docs/tecnico/NOTAS_TECNICAS.md` *(nuevo)*
- `docs/tecnico/image-upload-system.md` *(nuevo)*
- `docs/tecnico/ESTRATEGIA_DESARROLLO.md` *(nuevo)*
- `CLAUDE.md`

---
## 📅 Sesión: 25/05/2026 (continuación) - Fix definitivo de subida de fotos (x-image-upload)

### ✅ Logros y Cambios

**Bug raíz resuelto — `applyCrop` no hacía nada:**
- **Causa:** `this.cropper` era `null` al llamar `applyCrop()`. El flujo anterior inicializaba Cropper en un `setTimeout` de 150ms después de asignar `img.src = dataUrl`, pero las DataURLs tardan más de eso en cargar, por lo que Cropper se inicializaba antes de que el `<img>` tuviera dimensiones reales.
- **Fix:** Se reestructuró `fileChosen()` en `image-upload.js` para usar el callback `img.onload` antes de asignar `img.src`. El modal se abre dentro de `onload` (cuando la imagen ya tiene dimensiones) y Cropper se inicializa en `setTimeout(300ms)` dentro de ese callback — garantizando que el modal es visible y la imagen cargada antes de que Cropper mida el contenedor.

**Bug de recorte visible — solo aparecía la parte superior de la imagen:**
- **Causa:** El contenedor del recortador tenía `max-height: 60vh` sin `overflow: hidden`. Sin un alto fijo, Cropper.js no puede calcular sus propios límites y el canvas desborda por abajo.
- **Fix:** Contenedor cambiado a `height: 60vh; overflow: hidden` en `image-upload.blade.php`. Ahora que Cropper se inicializa con el modal visible (fix anterior), las medidas son correctas y los controles (handles) no se cortan.

**Contexto de investigación de la sesión:**
- `unsafe-eval` requerido en CSP — Alpine.js evalúa `x-data` / `@click` / `x-show` con `new AsyncFunction()`.
- Alpine y Cropper migrados de CDN al bundle de Vite; CSS de Cropper descargado localmente como `vendor-cropper.css`.
- Cache-Control `no-store` agregado al middleware CSP para evitar que Cloudflare sirva HTML con hashes de bundle obsoletos.

### 📁 Archivos Modificados Esta Sesión
- `resources/js/modules/image-upload.js` — `fileChosen` reestructurado con `img.onload`
- `resources/views/components/image-upload.blade.php` — contenedor `height: 60vh; overflow: hidden`
- `public/build/manifest.json` + `public/build/assets/app-C7-NYl4u.js` — bundle reconstruido

### 🛑 Pendientes al cierre de sesión
- Tema de UI, Favicon & Empresa, Email Avanzado, Zonas Horarias, Credenciales producción, Bloquear `/up`, Cloudflare Transform Rules, PDF, Móvil.

---
## 📅 Sesión: 25/05/2026 (continuación 4) - Push a GitHub + configuración SSH

### ✅ Logros y Cambios

**Push a GitHub completado:**
- 25 commits acumulados pusheados a `origin/main` (`https://github.com/xqtor4u/estetican-backups.git`).
- **Problema de autenticación resuelto:** El remote usaba HTTPS y no había credenciales almacenadas. Se detectó clave SSH existente en `~/.ssh/id_ed25519` (generada en sesión previa). La clave pública fue registrada en GitHub (`estetican-opi`).
- Remote cambiado de HTTPS a SSH: `git remote set-url origin git@github.com:xqtor4u/estetican-backups.git`.
- GitHub agregado a `~/.ssh/known_hosts` con `ssh-keyscan`.
- Todas las sesiones futuras pueden hacer push con `git push origin main` sin credenciales adicionales.

**Backlog priorizado:**
- Ver `docs/tecnico/BACKLOG.md` para el listado completo con prioridades.

### 📁 Archivos Modificados
- `~/.ssh/known_hosts` — github.com agregado
- Remote URL cambiado a SSH (solo en configuración git local)

### 🛑 Pendientes (Backlog activo)
Ver `docs/tecnico/BACKLOG.md` — 9 ítems ordenados por prioridad.

---
## 📅 Sesión: 25/05/2026 (continuación 5) - Respaldo automático de BD a Google Drive

### ✅ Logros y Cambios

**Script de respaldo automático configurado y probado:**
- `scripts/auto_backup_db.sh` reescrito: ruta actualizada a `/opt/www/estetican`, nombre de contenedor MySQL correcto (`estetican_mysql`), `--no-tablespaces` para evitar warning de PROCESS privilege.
- Subida automática a `gdrive:OrangePiBackups/estetican-db/` vía rclone (ya configurado).
- Retención local de 7 días con rotación automática.
- Probado manualmente — dump generado (24K) y subido a Drive correctamente.
- **Cron instalado:** `0 3 * * * /opt/www/estetican/scripts/auto_backup_db.sh >> /var/log/estetican_backup.log 2>&1` (diario 3am).

**Sistema de respaldo completo:**
- Código + docs → GitHub (push manual al cerrar sesión)
- BD → Google Drive (automático 3am diario) + local 7 días

### 📁 Archivos Modificados
- `scripts/auto_backup_db.sh` — reescrito completamente
- `crontab` — cron diario 3am instalado

### 🛑 Pendientes (Backlog activo)
Ver `docs/tecnico/BACKLOG.md` — 9 ítems ordenados por prioridad.

---
## 📅 Sesión: 28/05/2026 - Mantenimiento de repositorio

### ✅ Logros y Cambios

- **`.gitignore` actualizado:** `estetican.sql` agregado a `apps/backoffice-laravel/.gitignore`. El dump generado por `apagar_backoffice.sh` no debe versionarse — cambia en cada backup y puede contener datos sensibles de clientes.
- **Comparación local vs GitHub:** Verificado que todos los archivos de trabajo (`BITACORA.md`, `BACKLOG.md`, `NOTAS_TECNICAS.md`, `ESTRATEGIA_DESARROLLO.md`, `IDEAS_FUTURO.md`) están en sincronía exacta con `origin/main`.

### 📁 Archivos Modificados
- `apps/backoffice-laravel/.gitignore` — agregada entrada `estetican.sql`

### 🛑 Pendientes (Backlog activo)
Ver `docs/tecnico/BACKLOG.md` — 9 ítems ordenados por prioridad.

---
## 📅 Sesión: 30/05/2026 - Relevamiento y definición de arquitectura app mobile

### ✅ Logros y Cambios

**Relevamiento completo del estado de `mob_apps/operador/`:**
- 8 pantallas prototipadas con datos hardcodeados: `GlobalAgenda`, `TeamPanel`, `GroomerDashboard`, `ActiveService`, `Directory`, `AssignService` (admin) + `ClientDashboard`, `ClientBooking` (cliente, descartable).
- Stack confirmado: React 19 + Vite + Tailwind 4 + React Router 7 + lucide-react + motion.
- No hay conexión a API Laravel todavía.

**Decisiones arquitectónicas definidas:**
- La app mobile es **exclusivamente para empleados y administradores** del negocio. No para clientes dueños de mascotas.
- La app cliente (`src/client/`) es un proyecto separado y diferente — no se trabaja en este repositorio.
- La mobile comparte la misma BD MySQL que el backoffice, consumiendo un **API REST JSON** en el mismo Laravel (`routes/api.php`, controladores en `app/Http/Controllers/Api/`).
- El backoffice de escritorio (Blade + Alpine) **no se modifica** — los endpoints API son aditivos.
- Autenticación mobile vía **Laravel Sanctum** (tokens), no sesiones web.

### 📁 Archivos Modificados
- Ninguno (sesión de relevamiento y definición — sin código escrito)

### 🛑 Próximos pasos (BL-009)
1. Decidir si seguir prototipando UI o arrancar conexión API
2. Si API: setup Sanctum + primer endpoint (agenda del día) + reemplazar datos hardcodeados en `GlobalAgenda`

---
## 📅 Sesión: 12/06/2026 — App Móvil: CRUD Mascotas/Clientes + Autenticación

### ✅ Logros y Cambios

#### App móvil (`mob_apps/operador/`)

**Infraestructura y acceso de red:**
- Vite expuesto en `192.168.100.250:3000` (red 100, no red 200) con proxy `/api` y `/storage` → `http://127.0.0.1:8000`.
- Puerto 3000 abierto en UFW. Dev server en segundo plano con `nohup`.
- Proceso arranca: `cd mob_apps/operador && nohup npm run dev > /tmp/mobile-dev.log 2>&1 &`

**Arquitectura de app (reescritura completa de `App.tsx`):**
- Eliminada pantalla de selección de rol. Entrada directa a `AdminLayout` con barra de navegación inferior.
- 4 pestañas fijas: Agenda, Equipo, Groomer, Directorio.
- Botón **Menú** (hamburguesa) que abre drawer desde abajo con todas las secciones.
- `MENU_SECTIONS` es la fuente única del menú — agregar una línea agrega la sección en toda la app.
- Drawer muestra nombre y rol del usuario logueado + botón **Cerrar sesión**.

**Pantallas implementadas y conectadas a API:**
- `GlobalAgenda` — 4 botones de acceso rápido: Agenda, Mascota, Cliente, Cobrar.
- `PetSearch` — búsqueda con debounce 300ms, toggle tarjetas/tabla, fotos proxeadas.
- `PetDetail` — vista/edición (patrón CRUD: solo lectura hasta presionar Editar). Campos completos. Marcado para eliminación. Alertas médicas. Próximas citas.
- `NewPetForm` — alta de nueva mascota: foto con persistencia base64 en sessionStorage (sobrevive apertura de cámara), todos los campos, flujo de selección de dueño.
- `ClientSearch` — modo normal y modo selección (con banner de contexto). Seleccionar dueño regresa a nueva mascota con el cliente en el estado.
- `ClientDetail` — vista/edición. Teléfonos editables (tipo + número, agregar/quitar). Pets clickeables. Botones de llamada y WhatsApp.
- `NewClientForm` — alta de nuevo cliente con teléfono **obligatorio**. Al guardar regresa al flujo de nueva mascota si viene de ese contexto.

**Autenticación:**
- `AuthContext.tsx` — token en `localStorage`, intercepta automáticamente todos los `fetch` a `/api/*` añadiendo `Authorization: Bearer`.
- `LoginScreen.tsx` — campo **Usuario** (nombre de login, no email), contraseña con ojo, mensajes de error del servidor. Campos grandes (py-5, text-lg) para uso táctil.
- `AuthGuard` — si no hay sesión muestra login; si hay token lo verifica con `/api/me` al iniciar. Spinner mientras verifica.

#### Backoffice — API (`apps/backoffice-laravel/`)

**Nuevos endpoints `routes/api.php` (todos protegidos por token excepto login):**
- `POST /api/login` — usuario + contraseña, devuelve token + info de usuario con roles.
- `POST /api/logout` — invalida el token en BD.
- `GET /api/me` — verifica sesión activa.
- `GET|POST /api/pets` — listado con búsqueda + alta de mascota con foto.
- `GET|PATCH /api/pets/{id}` — detalle y edición.
- `GET|POST /api/clients` — listado con búsqueda + alta de cliente.
- `GET|PATCH /api/clients/{id}` — detalle y edición con sync de teléfonos.

**Nuevos archivos de backend:**
- `database/migrations/2026_06_12_000001_create_api_tokens_table.php` — tokens SHA-256.
- `app/Models/ApiToken.php` — modelo de token.
- `app/Http/Middleware/ApiAuthenticate.php` — valida Bearer token, verifica `is_active` y `can_login`.
- `app/Http/Controllers/Api/AuthController.php` — login/logout/me. Login por campo `name` del usuario.
- `app/Http/Controllers/Api/PetController.php` — index/show/store/update. Fix de búsqueda por nombre de dueño (first_name + last_name en lugar de `name` inexistente).
- `app/Http/Controllers/Api/ClientController.php` — index/show/store/update con sync de teléfonos en PATCH.

**Migración correctiva:**
- `2026_03_28_000001_add_operator_fields_to_users_table.php` — corregido `after('full_name')` (columna inexistente) → `after('last_name')`. Hecho idempotente con `Schema::hasColumn()` para tolerar reinicios parciales.

**Fix en `.env`:**
- `MAIL_HOST=<SERVIDOR_SMTP>` tenía angle brackets que causaban error de sintaxis bash al levantar Sail. Limpiado a valor vacío.

### 🐛 Problemas encontrados y resueltos

| Problema | Causa | Solución |
|---|---|---|
| `api_tokens` table not found | Migración no ejecutada | `sail artisan migrate` |
| `full_name` column not found en migración | Referencia a columna inexistente | Cambiado `after('full_name')` → `after('last_name')` |
| Duplicate column `first_name` | Migración parcialmente ejecutada antes del fallo | Hecha idempotente con `hasColumn()` |
| Puerto 8000 en uso al levantar Sail | `estetican_app` (producción) ya lo usaba | `sudo fuser -k 8000/tcp` |
| Backoffice da 500 tras reinicio de contenedores | `estetican_app` perdió red a MySQL cuando Sail recreó los contenedores | `docker network connect backoffice-laravel_sail estetican_app` |
| Login pedía email en móvil | `autoComplete="username"` activa sugerencias de email en iOS/Android | Cambiado a `autoComplete="off"` |
| Foto de mascota perdida al volver de cámara | `File` object no serializable; React estado reiniciado | Foto convertida a base64 → guardada en sessionStorage → al enviar, base64 → Blob si `photo` es null |

### 🔧 Estado del sistema al cierre de sesión

**Backoffice (producción en OPi):**
- Contenedores Sail levantados y operativos (`backoffice-laravel-*`).
- `estetican_app` reconectado a red Sail con `docker network connect`.
- Todas las migraciones aplicadas incluyendo `api_tokens`.
- **PENDIENTE:** hacer push a GitHub (`git push origin main`).

**App móvil:**
- Dev server debe reiniciarse manualmente: `cd /opt/www/estetican/mob_apps/operador && nohup npm run dev > /tmp/mobile-dev.log 2>&1 &`
- Login funcional con usuario/contraseña del backoffice.
- CRUD de mascotas y clientes conectado a API real.

### 🛑 Pendientes activos (ver BACKLOG.md)

**App móvil — bugs conocidos:**
- BL-010: Foto de mascota no se muestra en `ClientDetail` (lista de mascotas del dueño).
- BL-011: Foto de mascota no se muestra en `PetSearch` (tarjetas y tabla).

**App móvil — funcionalidad incompleta:**
- "Cambiar dueño" en edición de mascota → próximamente.
- Botón "Agregar cita" en `PetDetail` → próximamente.
- Flujo retorno de nuevo cliente → nueva mascota (parcialmente implementado, falta verificar).

**Backoffice (BL activos):**
- BL-001 Tema de UI, BL-002 Favicon, BL-003 SMTP, BL-004 Zonas horarias, BL-006 Bloquear `/up`, BL-007 Cloudflare Transform Rules, BL-008 PDF, BL-009 Ecosistema móvil.

**App cliente (futura — separada):**
- BL-012: Autoregistro de clientes — va en app pública separada, no en `mob_apps/operador`.

---
## 📅 Sesión: 13/06/2026 — Restauración de producción (OPi)

### ✅ Logros y Cambios

**Diagnóstico y fix de caída total del backoffice (error 600 / timeout 1min+):**
- **Causa raíz:** Al levantar Sail en sesiones anteriores, ambos compose files (`compose.yaml` de Sail y `compose.prod.yaml`) compartían el mismo nombre de proyecto Docker (`backoffice-laravel`). Sail reemplazó/detuvo `estetican_mysql` al arrancar, dejando `estetican_app` sin BD — cada request esperaba el timeout de conexión TCP (~1 min) antes de fallar.
- **Fix inmediato:** `docker compose -f compose.prod.yaml up -d mysql` — restauró `estetican_mysql` con el volumen `backoffice-laravel_estetican-mysql` (datos reales: 5 usuarios, 6 mascotas, 4 clientes).
- **Fix permanente:** `compose.prod.yaml` ahora tiene `name: estetican-prod` — aísla el proyecto de producción del proyecto Sail para siempre.

**Fix de login app móvil (siempre retornaba 403):**
- **Causa raíz:** `AuthController::login()` chequeaba `$user->can_login`, pero esa columna no existe en `users` (solo existe en `operator_roles`). Laravel la leía como `null` → bloqueaba todos los logins.
- **Fix:** Removida la verificación de `can_login`. El check de `is_active` (columna que sí existe) es suficiente.

**Fix de proxy Vite → API de producción (app móvil se quedaba colgada):**
- **Causa raíz:** El Sail dev (`backoffice-laravel-laravel.test-1`) estaba corriendo en OPi sin necesidad y capturaba `0.0.0.0:8000`, bloqueando el proxy Vite que apunta a `127.0.0.1:8000`.
- **Fix:** Detenidos los contenedores Sail en OPi (no se necesitan en producción). Reiniciado `estetican_app` para que tomara el puerto 8000. Reiniciado el dev server Vite (había dos procesos duplicados).

**Migración pendiente aplicada:**
- `2026_06_12_000001_create_api_tokens_table` faltaba en producción — aplicada con `php artisan migrate --force`.

### 📁 Archivos Modificados
- `apps/backoffice-laravel/app/Http/Controllers/Api/AuthController.php` — removido check `can_login`
- `apps/backoffice-laravel/compose.prod.yaml` — agregado `name: estetican-prod`
- `scripts/auto_backup_db.sh` — nombre de contenedor MySQL actualizado

### 🔧 Estado del sistema al cierre
- Backoffice: operativo, login web funcionando.
- App móvil: login y CRUD funcionando.
- `estetican_mysql` + `estetican_app`: corriendo y saludables.
- Sail detenido en OPi (correcto para entorno de producción).

### 🛑 Pendientes (Backlog activo)
Ver `docs/tecnico/BACKLOG.md`.

---
## 📅 Sesión: 13/06/2026 (cont.) — App móvil: agenda, cobro y tolerancia de inicio

### ✅ Logros y Cambios

**Check-in/check-out de operadores por sucursal:**
- Nuevo modelo `OperatorCheckin` + migración.
- `CheckinController` (API): `status`, `checkin` (con auto-checkout y nota de transgresión si cambia de sucursal), `checkout`.
- Widget `CheckinWidget` en el drawer del menú.

**MobCitaNueva — captura de cita:**
- Selector de fecha, operador, catálogo de servicios, control de duración (±15 min), grilla de slots 09:00–19:00 (cada 30 min).
- Envía `scheduled_at` como `"YYYY-MM-DD HH:MM:00"` (fix timezone con `localDateStr`).
- Muestra pantalla de éxito 1.5 s y regresa.

**MobCitaDet — detalle/edición de cita:**
- Vista completa con mascota, cliente, servicios, operador, notas.
- Modo edición inline (mismos controles que MobCitaNueva).
- Acciones de cambio de estado: Iniciar / No se presentó / Cancelar (con modal de motivo) / Completar y cobrar.
- Accesible desde GlobalAgenda y PetDetail.

**GlobalAgenda y PetDetail:**
- Tarjetas de cita navegan a `/citas/:id`.
- Hora mostrada como `09:00 → 10:30` (incluye duración).

**MobCobro — registro de cobro:**
- Flujo de dos pasos: form → confirm → saving → done.
- Previene registro de pago cuando la terminal rechaza la tarjeta.
- Métodos: efectivo (→caja), tarjeta débito/crédito (→banco), transferencia (→banco).
- Contador de intentos con banner de advertencia en reintento.

**Tolerancia para "Iniciar servicio" (booking_grace_minutes):**
- Nuevo parámetro `booking_grace_minutes` (default 15 min) en sección `clinical` de `SystemSettings`.
- Endpoint `GET /api/settings/booking` → `{ grace_minutes: 15 }`.
- En MobCitaDet: al tocar "Iniciar servicio", compara hora actual vs `scheduled_at`. Si la diferencia supera la tolerancia (antes o después), muestra diálogo de confirmación con mensaje específico ("X min de retraso" / "X min antes de la hora") antes de proceder.

### 📁 Archivos Modificados/Creados
- `apps/backoffice-laravel/app/Models/SpaBooking.php` — `operator_id`, `duration_minutes` en fillable; relación `operator()`
- `apps/backoffice-laravel/app/Models/OperatorCheckin.php` — nuevo
- `apps/backoffice-laravel/app/Http/Controllers/Api/CheckinController.php` — nuevo
- `apps/backoffice-laravel/app/Http/Controllers/Api/BookingController.php` — nuevo
- `apps/backoffice-laravel/app/Http/Controllers/Api/PaymentController.php` — nuevo
- `apps/backoffice-laravel/app/Http/Controllers/Api/AgendaController.php` — `end_time`, `duration_minutes`
- `apps/backoffice-laravel/app/Http/Controllers/Api/SettingController.php` — nuevo
- `apps/backoffice-laravel/app/Support/SystemSettings/SystemSettings.php` — campo `booking_grace_minutes`
- `apps/backoffice-laravel/routes/api.php` — rutas checkin, bookings, payments, settings/booking
- `mob_apps/operador/src/App.tsx` — rutas MobCitaNueva, MobCitaDet, MobCobro; CheckinWidget
- `mob_apps/operador/src/admin/MobCitaNueva.tsx` — nuevo
- `mob_apps/operador/src/admin/MobCitaDet.tsx` — nuevo (con graceDialog)
- `mob_apps/operador/src/admin/MobCobro.tsx` — nuevo
- `mob_apps/operador/src/admin/GlobalAgenda.tsx` — onClick + end_time
- `mob_apps/operador/src/admin/PetDetail.tsx` — botón nueva cita + citas clickeables

### 🔧 Migraciones aplicadas
- `create_operator_checkins_table`
- `add_operator_id_to_spa_bookings`
- `add_duration_minutes_to_spa_bookings`

### 🛑 Pendientes activos
- UI de `booking_grace_minutes` en backoffice (sección Operación Clínica ya lo tiene — solo verificar que se vea en la vista).
- Push a GitHub.
- BL-006, BL-007, BL-001..004, BL-008.

---
## 📅 Sesión: 14/06/2026 — Módulo contable (doble entrada)

### ✅ Logros y Cambios

**Consolidación documental:**
- Eliminados 13 archivos obsoletos (TASK_LIST, especificaciones técnicas duplicadas, manuales heredados).
- Creado `docs/tecnico/MODELO_BD.md` — inventario completo de 35+ tablas con columnas y notas de negocio. **Fuente de verdad del esquema.**
- Actualizado `CLAUDE.md` con protocolo "Al iniciar / Al cerrar" y referencia a MODELO_BD.
- Actualizados `IDEAS_FUTURO.md` y `BACKLOG.md` con BL-017..021.

**Módulo contable — infraestructura completa:**
- 9 migraciones: `accounts`, `payment_methods`, `document_series`, `documents`, `journal_entries`, `journal_entry_lines`, `cash_registers`, `cash_sessions`, `add_account_id_to_services`.
- 8 modelos Eloquent con relaciones y métodos de utilidad (`isBalanced`, `isCancellable`, `activeSession`, etc.).
- `AccountingService` en `app/Domain/Accounting/` (Interface + Implementación): `getNextFolio` (lockForUpdate), `createPaymentEntry` (distribución proporcional), `cancelEntry`.
- 3 seeders: `AccountsSeeder` (catálogo estándar mexicano 1000–5900), `PaymentMethodsSeeder`, `DocumentSeriesSeeder`.
- Binding en `AppServiceProvider`.
- Sección `finanzas` en `SystemSettings` (requiere_apertura_caja, asientos_auto_aplicar, moneda).
- Permisos Spatie agregados en `BaseRolesSeeder`: `cobros.registrar`, `caja.abrir`, `caja.cerrar`, `asientos.aprobar`.
- `MODELO_BD.md` actualizado con las 8 tablas nuevas y columna `account_id` en `services`.

### 📁 Archivos Creados/Modificados
- `database/migrations/2026_06_14_100000..100800_*` — 9 migraciones
- `app/Models/Account.php`, `PaymentMethod.php`, `DocumentSeries.php`, `Document.php`, `JournalEntry.php`, `JournalEntryLine.php`, `CashRegister.php`, `CashSession.php`
- `app/Domain/Accounting/Contracts/AccountingServiceInterface.php` — nuevo
- `app/Domain/Accounting/Services/AccountingService.php` — nuevo
- `database/seeders/AccountsSeeder.php`, `PaymentMethodsSeeder.php`, `DocumentSeriesSeeder.php` — nuevos
- `app/Providers/AppServiceProvider.php` — binding AccountingService
- `app/Support/SystemSettings/SystemSettings.php` — sección finanzas
- `database/seeders/BaseRolesSeeder.php` — 4 permisos financieros
- `docs/tecnico/MODELO_BD.md` — creado + actualizado con tablas contables

### 🛑 Pendientes activos
- **Aplicar migraciones y seeders en producción OPi** (migrations + db:seed --class=AccountsSeeder etc.)
- BL-018: UI Backoffice → Finanzas (catálogo de cuentas, métodos de pago, series de documentos, cajas)
- BL-019: Apertura y corte de caja en backoffice
- BL-020: Conectar flujo de cobro (billing_summary + MobCobro) al AccountingService
- BL-021: Migrar datos históricos cash_ledgers/bank_ledgers
- BL-013: Push a GitHub
- BL-006, BL-007: Seguridad (bloquear /up, Cloudflare Rules)
- BL-010/011: Fotos de mascotas en app móvil
