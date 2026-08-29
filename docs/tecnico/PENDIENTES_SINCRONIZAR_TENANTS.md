# Pendientes a sincronizar con los tenants (Zeus-Estetican)

> **Qué es este documento:** EstetiCAN real (`/opt/www/estetican`) es **producción** — acá
> solo se atienden **emergencias** (bugs que rompen operación real, incidentes de datos, huecos
> de seguridad). Todo addon, upgrade o mejora que **no** sea emergencia se construye del lado
> de Zeus-Estetican, en el sandbox `tst` (`tstapp.estetican.org` / `tstmov.estetican.org`,
> `/opt/www/zeus-estetican/tenants/tst/`) — el sandbox de referencia para todos los tenants.
>
> Como el motor es el mismo código clonado en cada tenant, **una emergencia real de producción
> normalmente también existe como bug latente en `tst`** (y en cualquier otro clon real) hasta
> que se porta. Este documento registra qué se arregló acá y si ya se replicó allá — dirección
> opuesta a `docs/tecnico/PENDIENTES_SINCRONIZAR_ESTETICAN.md` del lado de Zeus-Estetican (ese
> es para lo que se descubre en el sandbox y falta subir a producción; nunca al revés).
>
> Namespace de IDs: **SYNC-XXX** (contador propio de este archivo, independiente del de Zeus).
> Un ítem no se borra al portarse — se mueve a "Aplicados" con fecha.

---

## Pendientes

### SYNC-003 — `/agenda` (web) embebe el directorio completo de operadores para un usuario restringido

**Encontrado:** 28/08/2026, probando `SYNC-030` (operador restringido) en producción real con un
usuario de prueba. `SpaBookingController::index()` pasaba `$operators` (id + nombre de **todos**
los operadores activos) a `agenda/index.blade.php` sin scope — lo agregó `SYNC-052` (Zeus,
Fase 3b) para el pop-up de "reasignar operador". Un usuario con solo `ver agenda` (sin
`agenda.ver_todas`) recibía la lista de compañeros embebida en el JS de la página
(`var OPERATORS = [...]`), aunque `/api/operators` sí le devuelve 403 y la acción de reasignar
exige `editar agenda`.

**Severidad:** baja (solo id + nombre; el plan de `SYNC-030` marca ese dato como no sensible)
pero **inconsistente** entre web y API.

**Fix aplicado en EstetiCAN real (commit `3cb9f00`):** en `SpaBookingController::index()`,
`$operators` solo se pasa si `is_super_admin || can('agenda.ver_todas')`; si no, `collect()`
vacía (el `<select>` del pop-up queda vacío — igual no puede reasignar).
`tests/Feature/Agenda/SpaBookingControllerWebScopingTest.php` — caso nuevo (`var OPERATORS = []`
para el restringido; directorio completo con `agenda.ver_todas`).

**Pendiente de portar a `tenants/tst`:** el `SpaBookingController.php` de `tst` (post-`SYNC-052`)
tiene el mismo `$operators` sin gatear — mismo bug latente. Portar el gate + el test cuando se
toque ese archivo.

---

## Aplicados

### SYNC-005 — `POST /api/login` (login de la app móvil) no tenía rate limit — H7

**Encontrado:** 28/08/2026, revisando el reporte de pruebas de `tstmov` (hallazgo **H7** del
`260828 ANALISIS_Y_PLAN_TSTMOV.md` de Zeus). El `throttle:login` solo estaba en `POST /login`
web, y el limiter con nombre `login` construía la clave por credencial leyendo solo
`$request->input('email')` — la app móvil manda `username`. Fuerza bruta sin límite contra la
puerta del móvil.

**Fix aplicado en EstetiCAN real (commit `e95d7c0`):**
- `routes/api.php` — `->middleware('throttle:login')` en `POST /api/login`.
- `AppServiceProvider::boot()` — el limiter lee `email ?: username`.
- `tests/Feature/Auth/LoginThrottleTest.php` — 2 casos nuevos. Ver **NT-063**.

**Verificado:** en `tstmov` real, 6º intento fallido de `/api/login` → HTTP 429.

**Portado a `tenants/tst` (28/08/2026):** mismos cambios en `routes/api.php` y
`AppServiceProvider.php` del tenant. El `tst_mob` no se tocó (es cambio de backend). No versionado
(`tenants/` en `.gitignore`) — si `tst` se reprovisiona, re-portar.

### SYNC-004 — El ítem "Artículos" del menú de la app móvil no está gateado por permiso — H1

**Encontrado:** 28/08/2026, reporte de pruebas de `tstmov` (hallazgo **H1**). Un operador
restringido (sin `ver catalogo_articulos`) veía el botón "Artículos" en el menú móvil; al tocarlo,
`/api/items` devuelve 403 — botón muerto. Todos los demás ítems ya se gatean con `requiresFlag` +
`can_view_*` desde `SYNC-030`; "Artículos" se quedó fuera.

**Fix aplicado en EstetiCAN real (commit `5fe1168`):**
- `app/Models/User.php` — `toApiArray()` +`'can_view_articulos' => $this->can('ver catalogo_articulos') || $this->is_super_admin`.
- `mob_apps/operador/src/AuthContext.tsx` — `can_view_articulos` en `AuthUser`.
- `mob_apps/operador/src/App.tsx` — `'can_view_articulos'` en la unión `MenuVisibilityFlag` y
  `requiresFlag: 'can_view_articulos'` en el ítem "Artículos".
- `tests/Feature/Api/RestrictedOperatorClientPetAccessTest.php` — 2 casos nuevos (`/api/items` →
  403 y `/api/me` `can_view_articulos: false`; con `ver catalogo_articulos` → `true`).

**Portado a `tenants/tst` (28/08/2026):** mismos 3 archivos (`User.php`, `AuthContext.tsx`,
`App.tsx`). Imagen `tst_mob` **reconstruida** (`docker compose -f compose.prod.yaml up -d --build mob`)
— el bundle de `tst` trae el `dist/` horneado. No versionado (`tenants/` en `.gitignore`).

### SYNC-002 — 500 al guardar en USEEDI un usuario ya vinculado a un operador (`operators.operator_role_id` eliminada)

**Encontrado:** 27/08/2026, reportado por el usuario (visto primero en `tst`) — "al cambiar los
permisos del CRUD de un admin, el servidor marca 500 al aplicar". La correlación con los
permisos es casual: el 500 salta en *cualquier* guardado de ese usuario.

**Causa:** `UserController::syncOperatorRecord()` armaba `$data` con
`'operator_role_id' => $user->operator_role_id` y hacía `Operator::where('id', ...)->update($data)`.
La migración `2026_07_31_000000_consolidate_operator_role_fields` eliminó
`operators.operator_role_id` (el rol del operador vive ahora en `operator_role_assignments`). El
`->update()` de query builder no filtra por `$fillable` → manda la columna inexistente a SQL →
`SQLSTATE[42S22] Unknown column 'operator_role_id'` → 500, y el guardado del usuario falla en
silencio. Un operador *nuevo* (sin `operator_id`) no truena porque ahí es
`Operator::create($data)` y mass-assignment descarta la clave; solo truena el camino `->update()`
(usuario ya vinculado). `users.operator_role_id` sigue existiendo y el selector "Tipo de
Operador" de USEEDI la guarda bien vía `fill()` — el bug era solo propagarla a `operators`.

**Fix aplicado en EstetiCAN real:**
- `apps/backoffice-laravel/app/Http/Controllers/UserController.php` — eliminada la línea
  `'operator_role_id' => $user->operator_role_id,` del array `$data` de `syncOperatorRecord()`
  (con comentario del porqué). Una línea.
- `apps/backoffice-laravel/tests/Feature/UserOperatorSyncTest.php` — regresión nueva (2 casos):
  `operators` ya no tiene la columna; `PUT users.update` de un usuario con `is_operator=true` +
  `operator_id` vinculado redirige (no 500), persiste `users.operator_role_id` y actualiza el
  registro de operador.

**Verificado:** Pint limpio; 42 tests `--filter=User` en verde (incluidos los 2 nuevos). Sin
migración, sin otras dependencias.

**Portado a `tenants/tst` (27/08/2026):** se arregló ahí primero — `SYNC-048` de
`docs/tecnico/PENDIENTES_SINCRONIZAR_ESTETICAN.md` de Zeus-Estetican (misma línea, mismo test,
48 tests `--filter=User` en verde). El `syncOperatorRecord()` de ambos repos era idéntico, sin
divergencia propia del tenant.

### SYNC-001 — Agenda: barra de "Ventana" (Hoy/Mañana/Próximas/Todas) y el filtro de Estado eran dos paneles desconectados

**Encontrado:** 13/08/2026, reportado directo por el usuario en producción — al marcar
"Marcar todos" en el filtro de Estado y después tocar la barra de Ventana, el cambio de
checkboxes se perdía silenciosamente.

**Causa:** dos controles independientes para el mismo parámetro `date_scope` — la barra de
pestañas de arriba usaba `<a href>` con la URL ya armada al cargar la página, y el panel de
filtros tenía su propio `<select name="date_scope">` (al que además le faltaba la opción
"Todas"). Cualquier cambio hecho en un panel que no se hubiera enviado todavía se perdía al
navegar por el otro.

**Fix aplicado en EstetiCAN real** (ver `BITACORA.md` 13/08/2026 para el detalle completo):
- `apps/backoffice-laravel/resources/views/agenda/index.blade.php` — los botones de Ventana y
  de día anterior/siguiente pasaron de `<a href>` sueltos a `<button type="submit"
  form="agenda-filters-form" name="date_scope" value="...">`, enviando el mismo `<form>` que
  el resto de los filtros. Se eliminó el `<select>` duplicado. Se agregaron hidden `sort`/
  `direction` para no perder el orden de tabla al aplicar filtros.
- `apps/backoffice-laravel/resources/views/components/list-filters.blade.php` — prop opcional
  nueva `id` (retrocompatible, no afecta a los otros 10 `index.blade.php` que usan
  `x-list-filters`).

**Verificado:** compila sin error Blade, Pint limpio, 76 tests de Agenda pasan, suite completa
447 pasan / 37 fallan (deuda de fixtures preexistente, sin regresiones nuevas). No se pudo
probar en navegador real (sin Chrome conectado en esa sesión).

**Portado a `tenants/tst` el mismo día (13/08/2026):** se confirmó primero que los dos archivos
de `tst` eran byte-idénticos a la versión pre-fix de EstetiCAN (sin divergencia propia del
tenant) — se copiaron los mismos 2 archivos ya corregidos y se corrió `view:clear`+`view:cache`
en `tst_app` (compiló sin error; sin phpunit instalado ahí para correr tests). Avisado a la
sesión paralela de Zeus-Estetican (`tst-3c`) por `SendMessage` para que lo deje anotado en su
propia `BITACORA.md`/`NOTAS_TECNICAS.md`.

**Nota:** `tenants/` está en `.gitignore` de Zeus-Estetican a propósito (no es código propio) —
si `tst` se reprovisiona desde cero, este fix se pierde ahí y habría que volver a portarlo.
