# 📓 Bitácora de Desarrollo - EstetiCAN 2

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
