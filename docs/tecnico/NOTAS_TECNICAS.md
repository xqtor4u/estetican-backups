# Base de Errores Conocidos (KEDB) — EstetiCAN
### Known Error Database · Gestión de Problemas ITIL 4

> **Propósito ITIL:** Este archivo es el registro formal de Problemas del servicio.
> Un Problema es la causa raíz de uno o más Incidentes. Documentarlo aquí previene
> que el mismo Incidente se repita y reduce el MTTR (Mean Time To Restore) de futuras
> ocurrencias.
>
> **Cuándo crear una entrada:** Cuando se resuelve un bug no trivial, o cuando
> se identifica un riesgo técnico aunque no haya ocurrido aún como incidente.
>
> **Formato de cada entrada:**
> - **ID:** NT-XXX (secuencial)
> - **Clasificación:** Severidad (P1–P4) e impacto (quién/qué afecta)
> - **Síntoma:** Lo que ve el usuario o el operador
> - **Causa raíz:** El por qué técnico real
> - **Workaround:** Solución temporal si existe (para aplicar mientras se resuelve)
> - **Solución definitiva:** El fix permanente aplicado
> - **Lección:** La regla de oro para que no vuelva a ocurrir

---

## Índice de Severidades

| Nivel | Criterio | Tiempo de respuesta |
|---|---|---|
| **P1 — Crítico** | Sistema caído o flujo de negocio principal bloqueado | Inmediato (Cambio de Emergencia) |
| **P2 — Alto** | Funcionalidad importante rota, hay workaround parcial | Misma sesión |
| **P3 — Medio** | Funcionalidad degradada, usuarios pueden operar con dificultad | Próxima sesión |
| **P4 — Bajo** | Problema cosmético o riesgo identificado (no manifestado) | Backlog |

---

## NT-056 — El candado de `mov` (`AppLockContext`) ignoraba el tiempo de inactividad configurado — un timer aparte de 1.5s al cambiar de app dominaba cualquier bloqueo real

| Campo | Valor |
|---|---|
| **Fecha** | 06/08/2026 |
| **Severidad** | P3 — molesto para el usuario (candado se siente arbitrario), no es un hueco de seguridad — si algo, el comportamiento previo era *más* estricto de lo pedido |
| **Componente** | `mob_apps/operador/src/AppLockContext.tsx` |
| **Impacto** | El selector "Bloqueo automático" de `MobUserConfig` (1 a 30 min) casi nunca era lo que en la práctica decidía cuándo se bloqueaba la app en un celular real |
| **Estado** | ✅ Resuelto |

**Síntoma:** el usuario reportó que el candado "no respeta los tiempos... de la nada se bloquea" — confirmado que ocurría específicamente al cambiar de app un momento (WhatsApp, fotos, una llamada) y volver, sin importar qué tiempo hubiera configurado.

**Causa raíz:** existían **dos mecanismos de bloqueo independientes**. El temporizador de inactividad (`resetTimer()`, reiniciado en cada `touchstart`/`mousedown`/`keydown`/`scroll`) sí respetaba `lockTimeoutMinutes`. Pero además, en `visibilitychange` hacia `hidden`, un segundo timer hardcodeado (`HIDDEN_GRACE_MS = 1500`) bloqueaba a los 1.5 segundos **sin leer la preferencia del usuario para nada**. Ese timer se había agregado a propósito (BL-063, 20/07/2026, commit `b50b96a`) para filtrar falsos `hidden` que algunos WebView de Android disparan momentáneamente durante pickers nativos (fecha, foto) sin que el usuario haya salido de verdad de la app — pero como efecto secundario, en el uso real de un celular (donde cambiar de app un segundo es constante), este segundo mecanismo casi siempre ganaba la carrera antes de que el temporizador de minutos configurado tuviera oportunidad de ser la causa real del bloqueo.

**Solución definitiva:** se eliminó el timer aparte de `HIDDEN_GRACE_MS` por completo. Al ocultarse la pestaña no se dispara ninguna acción especial — como no hay más eventos de actividad mientras está oculta, el temporizador de inactividad ya en marcha (con la duración real configurada) sigue corriendo solo, y bloquea cuando corresponde, sea por quietud dentro de la app o por estar en otra app. Al volver a visible, en vez de reiniciar el timer a ciegas (`resetTimer()` sin más), primero se revalida contra lo persistido en `localStorage` vía `computeLockedFromStorage()` — necesario porque Android puede congelar el proceso en segundo plano y el `setTimeout` en memoria nunca llega a dispararse aunque haya pasado tiempo de sobra. Esto además resuelve el problema original que `HIDDEN_GRACE_MS` intentaba parchear: un falso `hidden` de picker ahora simplemente no hace nada (no hay timer que lo malinterprete), y al volver, `computeLockedFromStorage()` calcula correctamente que no pasó suficiente tiempo real como para bloquear.

**De paso:** se agregó la opción "Nunca" (`lockTimeoutMinutes = 0` → `getIdleTimeoutMs()` devuelve `Infinity`, `resetTimer()` no programa ningún `setTimeout` con eso) — decisión de producto confirmada con el usuario: "Nunca" apaga el candado por completo, incluyendo el bloqueo al cambiar de app, no solo el de inactividad.

**Lección:** cuando existen dos rutas distintas hacia el mismo estado (`locked = true`), un timer "de seguridad" agregado para resolver un problema puntual (falsos positivos de un evento del navegador) puede terminar silenciosamente reemplazando al mecanismo principal en el uso real, si su umbral es mucho más corto. Antes de agregar un segundo temporizador paralelo para "cubrir un caso especial", considerar si el temporizador principal, con una revalidación correcta contra tiempo real (no contra el `setTimeout` en memoria, que puede congelarse), ya cubre ese caso sin necesitar un mecanismo aparte.

---

## NT-055 — `<label htmlFor>` sobre un `<input type="date">` oculto no abre el calendario nativo en escritorio (sí en Android) — usar `showPicker()`

| Campo | Valor |
|---|---|
| **Fecha** | 2026-08-06 |
| **Severidad** | P3 — funcionalidad importante degradada en un dispositivo, con workaround manual (usar el celular) disponible |
| **Componente** | `mob_apps/operador/src/admin/MobCitaNueva.tsx` — selector de fecha libre ("Nueva cita") |
| **Impacto** | En escritorio, el ícono de calendario no abría ningún selector — solo quedaban disponibles los 7 chips de "esta semana" (hoy..+6 días), sin forma de registrar una cita atrasada o más allá de la semana visible |
| **Estado** | ✅ Resuelto |

**Síntoma:** en PC, tocar el ícono de calendario en "Nueva cita" no hacía nada visible; solo se podían elegir los días del chip strip (esta semana). En el celular (Android) el mismo ícono sí abría el selector nativo de fecha sin problema — reportado por un usuario real que necesitaba dar de alta una cita de días atrás que otro operador olvidó registrar.

**Causa raíz:** el `<input type="date" id="free-date" className="sr-only">` real estaba visualmente oculto, con un `<label htmlFor="free-date">` como único disparador. En navegadores de escritorio (Chrome/Edge), enfocar un `<input type="date">` vía `<label>` solo mueve el foco — el calendario emergente solo se abre al clicar el ícono de calendario que el propio navegador dibuja **dentro** del control nativo, que aquí era invisible (`sr-only`) y por lo tanto nunca clickeable. En Android, cualquier foco sobre un `<input type="date">` dispara automáticamente el selector nativo del sistema operativo, sin depender de ese ícono interno — de ahí la diferencia de plataforma. No había ningún `min`/`max` restringiendo fechas pasadas ni en este input ni en el backend (`scheduled_at` solo valida formato) — el bloqueo era puramente de interacción, no de reglas de negocio.

**Solución definitiva:** reemplazado el `<label>` por un `<button type="button">` con `onClick` que llama explícitamente a `inputRef.current.showPicker()` (con fallback a `.focus()` si el navegador no soporta el método).

**Lección:** cualquier `<input type="date">`/`<input type="time">` oculto visualmente (`sr-only`) y disparado desde un ícono/label externo necesita `showPicker()` explícito para funcionar en escritorio — el patrón "label + input oculto" abre el picker nativo solo en móvil; nunca asumir que se comporta igual en ambas plataformas sin probarlo en las dos.

---

## NT-054 — `fetch` sin timeout deja el spinner de sesión/candado atascado para siempre si Android congela el socket en segundo plano

| Campo | Valor |
|---|---|
| **Fecha** | 2026-08-06 |
| **Severidad** | P2 — bloquea por completo a un usuario real hasta que cierre y reabra la app, sin ningún error visible en pantalla |
| **Componente** | `mob_apps/operador/src/AuthContext.tsx` (chequeo de sesión al montar), `mob_apps/operador/src/LockScreen.tsx` (`verify-password`) |
| **Impacto** | Cualquier operador en Android que mande la app `mov` a segundo plano mientras hay un fetch pendiente puede quedar atrapado en el spinner de carga o en el candado, sin ninguna salida |
| **Estado** | ✅ Resuelto |

**Síntoma:** una operadora reportó estar "adentro" (autenticada, contraseña del candado aceptada) pero sin renderizar — el spinner de carga giraba indefinidamente en su celular Android, sin ningún error visible.

**Causa raíz:** la Fetch API no tiene timeout por default. Android puede congelar (o directamente descartar) el socket de un fetch en curso al mandar la pestaña a segundo plano, sin que la promesa llegue a resolver ni a rechazar nunca. `AuthContext.tsx` dependía de esa promesa (`/api/me` al montar) para bajar `loading=false`, y `LockScreen.tsx` dependía de otra (`/api/me/verify-password`) para llamar `onUnlock()` — si el fetch nunca resolvía, ninguno de los dos flujos tenía forma de salir del estado "cargando". Confirmado contra los logs reales de producción: la última llamada (`verify-password`, `200` correcto) quedó registrada varios minutos antes de que la usuaria siguiera reportándose bloqueada, sin ningún request posterior — consistente con un fetch que nunca completó del lado del navegador.

**Solución definitiva:** `fetchWithTimeout()` nuevo (`src/lib/fetchWithTimeout.ts`) — `AbortController` a 12s, aplicado en los dos puntos de espera. De paso se separó "timeout/error de red" de "401 real": solo un 401 explícito borra el token guardado; un timeout ahora muestra una pantalla de "reintentar" sin desloguear a alguien con una sesión todavía válida.

**Lección:** cualquier fetch que gatee un estado de carga de pantalla completa (spinner, overlay bloqueante) en la app móvil necesita timeout explícito — sin uno, un solo socket colgado (típico al reanudar desde segundo plano en Android) es indistinguible de "la app se rompió" para quien lo sufre.

---

## NT-053 — Gatear un `Route::resource()` con `permission:` no cubre las rutas satélite/anidadas del mismo objeto — hay que buscarlas explícitamente

| Campo | Valor |
|---|---|
| **Fecha** | 2026-08-05 |
| **Severidad** | P3 — Medio (patrón de proceso, no un bug puntual — cada vez que se repita deja un hueco de autorización nuevo) |
| **Componente** | `routes/web.php`, `routes/api.php` — cualquier módulo con acciones registradas fuera de su `Route::resource()` |
| **Impacto** | Durante la auditoría de autorización de esta sesión (ver NT-052 y la sesión completa de remediación de IDOR/privesc), se encontró repetidamente el mismo patrón: un objeto de negocio tenía su `Route::resource()` correctamente gateado con `->middlewareFor(...)`, pero acciones relacionadas al mismo objeto — registradas como rutas sueltas fuera del resource — se quedaban sin ningún `permission:` |
| **Estado** | ✅ Documentado — remediado en los casos encontrados, sin garantía de que no existan más |

**Síntoma:** al auditar `routes/web.php` buscando rutas de negocio sin `permission:`/`role:`/`auth` real (regla 3 de "Seguridad — reglas obligatorias" en `CLAUDE.md`), aparecieron varios casos donde el recurso base SÍ estaba protegido pero rutas "satélite" del mismo objeto no: `resources/{resource}/profile-photo`, `/duplicate`, `/photos/*` (fuera de `Route::resource('resources', ...)`), `operators/{operator}/duplicate` y `/unavailabilities/*` (fuera de `Route::resource('operators', ...)`), `hotel-reservations/{id}/cancel` (fuera del resource de hotel), y las rutas de `clients/{client}/pets/{pet}/*` dentro de `Route::scopeBindings()` (mismo objeto `Pet` que ya tenía gate en su resource top-level, pero accedido por una URL anidada distinta).

**Causa raíz:** `Route::resource(...)->middlewareFor(...)` solo aplica el middleware a las 7 acciones RESTful estándar (`index`/`show`/`create`/`store`/`edit`/`update`/`destroy`). Cualquier acción adicional sobre el mismo modelo de negocio — duplicar, subir foto, cancelar, una vista anidada bajo otro prefijo de URL — se registra como una ruta `Route::post()`/`get()`/etc. independiente, y el gate del resource no la alcanza. No es un bug de Laravel, es el comportamiento documentado — el riesgo es puramente de proceso: al gatear un resource es fácil dar por cerrado el objeto completo sin buscar el resto de sus rutas.

**Solución definitiva (para esta sesión):** grep de todas las rutas que mencionan el mismo controller/segmento de URL del objeto que se está gateando (ej. `grep -n "resources/{resource}" routes/web.php` después de gatear el resource `resources`) antes de dar por cerrado un objeto de negocio — no basta con aplicar `middlewareFor()` al resource y seguir.

**Lección — regla de proceso para la próxima vez que se agregue un `permission:` a un objeto:** después de gatear un `Route::resource()`, buscar explícitamente (`grep` por el segmento de URL o por el controller) cualquier otra ruta del mismo objeto registrada fuera del resource, antes de considerar el objeto "cerrado". Ver regla 1 de "Seguridad — reglas obligatorias" en `CLAUDE.md` — esta nota documenta el matiz que esa regla no explicitaba: "toda ruta nueva" incluye las satélite, no solo las del resource principal.

---

## NT-052 — Headers de seguridad de nginx (`mov.estetican.org`) ausentes en `/index.html` pese a estar declarados — herencia de `add_header` + bind-mount de archivo único pegado al inode viejo

| Campo | Valor |
|---|---|
| **Fecha** | 2026-08-04 |
| **Severidad** | P2 — Alto (protección de clickjacking/CSP ausente en el documento HTML principal, el único que de verdad importa para esos headers) |
| **Componente** | `mob_apps/operador/nginx.conf` (contenedor `estetican_mob`) |
| **Impacto** | Toda navegación real a `mov.estetican.org` (SPA fallback a `/index.html`) se servía sin `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`, `X-Content-Type-Options` ni `Content-Security-Policy` — solo los assets estáticos (`/assets/*.js`, `*.css`) sí los llevaban |
| **Estado** | ✅ RESUELTO |

**Síntoma:** al auditar en vivo los headers de `mov.estetican.org` contra `app.estetican.org` (que sí los tenía completos vía middleware Laravel), `curl -I https://mov.estetican.org/` solo devolvía `strict-transport-security` y `x-content-type-options` (ambos inyectados por Cloudflare a nivel de zona, no por el origen) — pese a que `nginx.conf` ya declaraba explícitamente los 4 headers con `always` a nivel de `server{}`. Pedir un asset (`/assets/index-*.css`) sí traía los 4 headers completos — la diferencia de comportamiento entre ambas rutas fue la pista.

**Causa raíz (dos capas, la segunda solo apareció al intentar corregir la primera):**

1. **Herencia de `add_header` en nginx:** un `location` que define sus propios `add_header` **no hereda** los `add_header` del `server{}` que lo contiene — nginx no los combina, el location con `add_header` propio empieza de cero. `location = /index.html` solo declaraba `Cache-Control`/`Pragma`/`Expires` (para forzar que el SPA nunca sirva un `index.html` cacheado), lo que pisaba en silencio los 4 headers de seguridad heredables del `server{}`. Como toda navegación real llega a `/index.html` vía `index`/`try_files` (internal redirect que reevalúa el location match), el HTML principal quedaba desprotegido mientras los assets estáticos (que caen en `location /`, sin `add_header` propio) sí los recibían.

2. **Bind-mount de archivo único pegado al inode viejo:** al corregir el `nginx.conf` del host (repitiendo los headers dentro de `location = /index.html`) y correr `docker exec estetican_mob nginx -s reload`, el contenedor seguía sirviendo el contenido de antes — `nginx -t`/`-s reload` "funcionaban" pero validaban/recargaban el archivo viejo. Causa: el bind-mount de Docker es **a nivel de archivo individual** (`Source: nginx.conf` → `Destination: default.conf`, no un directorio completo), y la edición del archivo (write nuevo + rename) generó un **inode nuevo** en el host; el bind-mount del contenedor, ya establecido desde que se creó el contenedor, quedó apuntando al inode viejo. `nginx -s reload` relee el archivo en la ruta que el contenedor ve — pero esa ruta seguía resolviendo al inode viejo por el mount, no al contenido actual. Confirmado comparando `stat -c '%i'` del archivo en el host contra `docker exec estetican_mob stat -c '%i' /etc/nginx/conf.d/default.conf` — no coincidían.

**Solución definitiva:**
1. Repetir los 4 headers de seguridad (+ `Content-Security-Policy` nuevo, ajustado a los recursos reales de esta SPA — sin `unsafe-eval` ni dominios de OpenStreetMap que sí usa `app.estetican.org`) dentro de `location = /index.html`, además de en el `server{}` — necesario porque ese location ya tiene sus propios `add_header` de caché.
2. `docker restart estetican_mob` en vez de `nginx -s reload` cuando el cambio es a un archivo bind-mounted individualmente (no a un directorio) — el restart fuerza a Docker a re-resolver el bind mount contra el path actual, tomando el inode vigente. Verificado post-restart comparando inodes de nuevo (coincidieron) antes de dar el fix por bueno — no basta con que el comando no truene.

**Lección:** dos gotchas independientes, cada uno silencioso a su manera — nginx no avisa que un `location` dejó de heredar `add_header`, y Docker no avisa que un bind-mount de archivo único quedó apuntando a un inode viejo tras editar el archivo por fuera del contenedor. Ninguno de los dos se detecta con "el comando no dio error" — ambos requieren verificación explícita contra el resultado real (comparar contenido/headers servidos, no solo que `nginx -t`/`reload` regresen éxito). Ver regla 4 de "Seguridad — reglas obligatorias" en `CLAUDE.md`, agregada a raíz de este mismo hallazgo.

---

## NT-051 — El saldo pendiente de la Agenda solo veía pagos vía presupuesto aceptado, ignorando `Payment` directo — toda cita cobrada desde móvil sin Quote se veía como impaga

| Campo | Valor |
|---|---|
| **Fecha** | 2026-08-03 |
| **Severidad** | P2 — Alto (dato financiero visible incorrecto en las pantallas operativas principales) |
| **Componente** | `agenda/index.blade.php` (columna Total), `agenda/show.blade.php` (tarjeta Balance) |
| **Impacto** | Cualquier cita cobrada desde la app móvil sin presupuesto (`Quote`) de por medio — el camino real más usado en producción — se mostraba con saldo pendiente completo aunque ya estuviera pagada |
| **Estado** | ✅ RESUELTO |

**Síntoma:** el usuario preguntó por qué una cita "Completada" no decía si ya se había cobrado.

**Causa raíz:** el cálculo de saldo en ambas vistas sumaba únicamente `CashLedger`/`BankLedger` ligados a un presupuesto aceptado (`$acceptedQuote->cashLedgers->sum('amount')`). El cobro desde la app móvil (`Api\PaymentController`) registra en `Payment` directo, sin pasar nunca por un `Quote` — el mismo patrón de bug ya corregido antes en `reports/invoice.blade.php` (27/07/2026), pero nunca replicado en la Agenda.

**Solución definitiva:** `SpaBooking::totalPaid()`/`unpaidBalance()` — método único en el modelo que suma ambos caminos (ledger vía Quote + `Payment` directo), reutilizado en las dos vistas. También se agregó el guard `if ($this->status === 'cancelled') return 0.0` — una cita cancelada nunca se llegó a prestar, así que su `total_estimated_price` no es dinero pendiente real.

**Lección:** cuando un cálculo de dinero se duplica a mano en más de una vista (ya iba en 4 lugares: `_billing_summary`, `reports/invoice`, `reports/work-order`, y ahora Agenda), cada copia nueva hereda el mismo riesgo de quedarse desincronizada la próxima vez que cambie la forma de cobrar. Centralizar en el modelo es más barato que seguir parchando cada aparición por separado (ver también `IDEAS_FUTURO.md`, ítem de unificar `BookingBillingSummary`).

---

## NT-050 — Los reportes impresos (recibo/OT/presupuesto) se salían del margen derecho al imprimir en carta — faltaba `box-sizing: border-box`

| Campo | Valor |
|---|---|
| **Fecha** | 2026-08-03 |
| **Severidad** | P3 — Medio (documento funcional pero mal recortado al imprimir) |
| **Componente** | `layouts/report.blade.php` |
| **Impacto** | Los 3 documentos impresos (`reports/quote`, `reports/work-order`, `reports/invoice`) se veían cortados por la derecha en tamaño carta |
| **Estado** | ✅ RESUELTO |

**Síntoma:** el usuario reportó que los reportes no se ajustaban al tamaño de la página (carta), saliéndose por el margen derecho.

**Causa raíz:** el CSS del layout no tenía ningún reset `box-sizing: border-box` en ningún lado — con el `box-sizing: content-box` por default del navegador, el `padding` y `border` de `.container` (en modo impresión, forzado a `width:100%` pero con su `padding:20px` original sin tocar) y de `.info-box` dentro del grid de 2 columnas se **sumaban** al ancho declarado en vez de quedar incluidos en él, empujando el contenido más allá del ancho real disponible dentro del `@page { margin: 1.5cm }`.

**Solución definitiva:** `*, *::before, *::after { box-sizing: border-box; }` al inicio del `<style>` del layout — reset estándar, ausente en este archivo desde su creación.

**Lección:** cualquier layout standalone que no herede de `layouts/app.blade.php` (que sí tiene el reset de Bootstrap) necesita su propio reset de `box-sizing` explícito — es fácil de omitir al escribir CSS desde cero y el síntoma (desborde en el lado que se suma según el modelo de caja) no es obvio a simple vista en el código.

---

## NT-049 — Reprogramar una cita ya `work_order` era posible desde los endpoints (API móvil y web), aunque el dominio ya lo prohibía — causa real de citas "en proceso" con fecha a futuro

| Campo | Valor |
|---|---|
| **Fecha** | 2026-08-01 |
| **Severidad** | P3 — Medio (dejaba datos inconsistentes — `work_order` con `scheduled_at` futuro — sin bloquear operación, pero rompía cualquier lógica que asumiera esa combinación imposible) |
| **Componente** | `Api\BookingController::update()`, `SpaBookingController::update()` (web) |
| **Impacto** | Citas "en proceso" apareciendo con fecha programada a futuro en la agenda (móvil y web) |
| **Estado** | ✅ RESUELTO |

**Síntoma:**
El usuario reportó ver citas en `work_order` con fecha a futuro en la agenda semanal/mensual, sin haber ninguna acción visible que lo explicara.

**Causa raíz:**
`BookingService::rescheduleBooking()` (dominio) ya tenía la regla correcta: `if ($booking->status !== 'scheduled') return false;` — solo se puede reprogramar una cita que no ha iniciado. Pero **ni el endpoint API móvil (`Api\BookingController::update()`) ni el controlador web (`SpaBookingController::update()`) pasaban por ese método para validar** — el primero llenaba `scheduled_at` directo sobre el modelo sin chequear el estado actual; el segundo llamaba a `rescheduleBooking()` pero no verificaba su valor de retorno `false`, así que el resto del método seguía ejecutándose (incluyendo el cambio de `resource_id`) y reportaba éxito igual. La UI de edición (`MobCitaDet`) tampoco ocultaba los controles de fecha/hora para una cita ya iniciada — nada en la cadena impedía la operación.

**Solución definitiva:** guardia explícita en ambos controladores (`if ($booking->status !== 'scheduled') return 422/error` antes de aplicar cualquier cambio de fecha). La UI móvil oculta las secciones de Fecha/Horario en modo edición cuando la cita ya no está en `scheduled`, con una nota explicando por qué.

**Lección:** una regla de negocio en la capa de dominio (`BookingService`) no protege nada si los controladores que en teoría la usan tienen un camino alterno (fill directo sobre el modelo) que la evita, o si ignoran el `bool` de retorno de un método que puede fallar en silencio. Verificar siempre que **todos** los puntos de entrada (API, web, cualquier atajo) pasen por la misma validación — y que ningún código downstream siga corriendo tras un `false` no manejado.

---

## NT-048 — `app.timezone` en `UTC` mientras `scheduled_at` se guarda como hora local sin conversión — `now()` del backend adelantado 6 horas

| Campo | Valor |
|---|---|
| **Fecha** | 2026-08-01 |
| **Severidad** | P2 — Alto (cualquier comparación de hora en el backend — alertas de citas, banner "Cita Vencida" — podía dar falsos positivos para eventos de esa misma tarde/noche) |
| **Componente** | `config/app.php` (`timezone`), `App\Support\SystemSettings\SystemSettings::system_timezone`, `App\Http\Middleware\ApplySystemSettings` |
| **Impacto** | Cualquier código que compare `now()` contra `scheduled_at` (u otra fecha) — el aviso "Cita Vencida" del backoffice web, y la nueva lógica de alertas de citas atípicas construida esta misma sesión |
| **Estado** | ✅ RESUELTO |

**Síntoma:**
El usuario reportó que dos citas de esa mañana (Tokyo 10:30, Molly 12:00) no aparecían marcadas como atípicas siendo ya las 16:xx. Al investigar, se confirmó algo más grave: `now()` del backend devolvía las 22:xx cuando la hora real en México era 16:xx — un desfase de 6 horas exacto.

**Causa raíz:**
`config/app.php` tenía `'timezone' => 'UTC'`. Todo el sistema (frontend móvil, formularios web) envía y guarda `scheduled_at` como texto de hora local de México sin ninguna conversión de zona horaria ("12:00:00" significa mediodía CDMX, no UTC). Con `app.timezone=UTC`, Carbon interpreta ese mismo texto **como si ya fuera UTC** — un booking real de "esta noche a las 20:00" (hora México) se interpretaba como "20:00 UTC", que es 6 horas **antes** de la hora México real en ese momento (`now()` = hora UTC real, adelantada 6h respecto a México) — cualquier cita de esta noche ya aparecía "vencida" desde la tarde. Se confirmó en vivo con un caso hipotético. Además: ya existía un campo "Zona horaria" en Configuración → Sistema con default `America/Mexico_City` — pero nunca estuvo conectado a nada (a diferencia de su campo hermano "Formato de hora", que sí usa el mecanismo `configOverrides()`), y en producción tenía guardado literal `"UTC"`.

**Solución definitiva:** se agregó `'config' => 'backoffice.system.timezone'` a la definición del campo `system_timezone` en `SystemSettings.php` (mismo patrón que su hermano `system_time_format`), y se corrigió el valor guardado a `America/Mexico_City` vía `SystemSettings::saveFields()`. La fijación real de la zona horaria (`date_default_timezone_set()` + `config(['app.timezone' => ...])`) se movió de `ApplySystemSettings` (middleware, corre solo en requests HTTP, una vez por request) a `AppServiceProvider::boot()` (corre al bootstrap completo de la aplicación — antes de rutas, antes de cualquier comando artisan, antes del cuerpo de un test). El middleware quedó solo con locale/sesión.

**Por qué importaba el lugar, no solo el valor:** al aplicar el fix solo en el middleware, los tests que construían un `scheduled_at` con `now()` **antes** de hacer el request HTTP (patrón normal en fixtures de test) quedaban calculados con una zona horaria distinta de la que el controlador usaba después — el middleware corre *durante* el request, cambiando `date_default_timezone_set()` (un estado global de PHP) a mitad de camino. Moverlo a `boot()` lo fija una sola vez, antes de que exista cualquier código (de test o real) que pueda leer `now()`.

**Lección:** `date_default_timezone_set()` es un efecto global de PHP, no algo scoped al request — fijarlo dentro de un middleware (que corre *durante* cada request) puede dejar inconsistente cualquier `now()` calculado *antes* de que ese middleware corra en el mismo proceso (fixtures de test, comandos artisan, jobs en cola). Fijar zona horaria/locale derivados de configuración de negocio debe vivir en el boot de la aplicación (`ServiceProvider::boot()`), no en middleware, si se quiere que aplique de forma uniforme a *todo* el proceso, no solo al ciclo request→response.

---

## NT-047 — `QuoteService::acceptQuote()` registraba el anticipo antes de sincronizar los servicios de la cita — el snapshot del recibo nacía vacío

| Campo | Valor |
|---|---|
| **Fecha** | 2026-07-31 |
| **Severidad** | P3 — Medio (el recibo se emitía igual, con el monto correcto, pero sin el detalle de línea — auditoría incompleta, no pérdida de dinero) |
| **Componente** | `app/Domain/Commercial/Services/QuoteService.php::acceptQuote()` |
| **Impacto** | `line_items_snapshot` (BL-076) vacío en el `Document` generado por el anticipo al aceptar un presupuesto con anticipo |
| **Estado** | ✅ RESUELTO |

**Síntoma:**
No reportado — encontrado por un test propio (`QuoteAdvancePaymentAccountingTest`) al construir BL-076 fase web: `assertNotEmpty($document->line_items_snapshot)` fallaba con "an array is not empty" pese a que el presupuesto sí tenía servicios.

**Causa raíz:**
El método hacía, en este orden: (1) marcar el quote como aceptado, (2) rechazar otros quotes, (3) **registrar el anticipo** (que dispara `AccountingService::recordBookingPaymentLedger()` → `snapshotBookingLineItems($booking)`), (4) recién ahí sincronizar `spa_booking_services`/`spa_booking_items` desde el quote aceptado. En el paso 3, la cita todavía no tenía ninguna línea de servicio real (`$booking->services` seguía vacío) — el snapshot se armaba contra una cita sin servicios, aunque el presupuesto sí los tuviera.

**Solución definitiva:** se invirtió el orden — sincronizar `spa_booking_services`/`items` (paso 3) **antes** de registrar el anticipo (paso 4, ahora al final). El snapshot del recibo del anticipo ahora refleja los servicios recién aceptados.

**Lección:** cuando una operación dispara un efecto secundario que lee el estado "actual" de una entidad relacionada (acá: el snapshot de línea leyendo `$booking->services`), verificar que ese estado ya esté actualizado en el momento en que se dispara el efecto — no asumir que el orden de los pasos dentro de una transacción es irrelevante solo porque todos corren atómicamente. Atomicidad (todo o nada) no es lo mismo que orden correcto (qué se lee antes de que otra cosa se escriba).

---

## NT-046 — Sincronizar `services` desde `Api\BookingController::update()` borraba y recreaba todas las líneas, perdiendo precio/operador/costo externo editados

| Campo | Valor |
|---|---|
| **Fecha** | 2026-07-31 |
| **Severidad** | P3 — Medio (riesgo identificado antes de manifestarse como incidente real — no había reporte de datos perdidos) |
| **Componente** | `Api\BookingController::update()` (endpoint usado por `mob_apps/operador`, `MobCitaDet.tsx` en modo edición) |
| **Impacto** | Cualquier edición desde mobile que reenvíe el arreglo `services` (reprogramar servicios de una cita) — potencial, no confirmado como ya ocurrido en producción |
| **Estado** | ✅ RESUELTO |

**Síntoma:**
No reportado — encontrado al llevar BL-075 (costo de proveedor externo por línea) a la app móvil. Antes de exponer un endpoint nuevo para asignar operador/costo por línea, se revisó qué pasaba si el mismo booking se editaba después desde `MobCitaDet.tsx`, que reenvía `services` como un arreglo plano de IDs de catálogo (`selSvcs`, sin precio ni ningún otro dato de línea).

**Causa raíz:**
`update()` hacía `$booking->services()->delete()` seguido de recrear cada línea con `SpaBookingService::create(['current_price' => precio_de_catálogo])` — sin importar si la línea ya existía. Esto no solo ignoraba cualquier precio editado a mano (bug preexistente, ya presente antes de esta sesión), sino que con las columnas nuevas de BL-075 (`operator_id`, `is_external`, `external_cost`) habría empezado a **borrar en silencio** cualquier profesional/costo externo ya asignado a una línea, en cuanto alguien tocara la lista de servicios desde mobile — sin ningún mensaje de error, sin que el usuario supiera que perdió esa asignación.

**Solución definitiva:** reescrito para hacer un sync no destructivo — solo `delete()` de las líneas cuyo `service_id` ya no está en la lista nueva, y solo `create()` para `service_id` que no existían antes; las líneas que permanecen (mismo `service_id`) no se tocan, conservando `current_price`, `operator_id`, `is_external` y `external_cost` tal cual estaban. `total_estimated_price` se recalcula sumando `current_price` real de las líneas resultantes en vez de sumar precios de catálogo.

**Lección:** cuando una tabla gana columnas nuevas con estado que se asigna en un momento posterior a su creación (operador, costo, lo que sea), hay que revisar **todos** los puntos que hacen `delete()` + recreate de esa tabla como mecanismo de "sincronizar una lista" — un patrón que era inofensivo cuando la tabla solo tenía datos derivables del catálogo deja de serlo en cuanto empieza a cargar datos que solo existen ahí.

---

## NT-045 — "Asignar Profesional" en la Orden de Trabajo daba 404 siempre: type-hint apuntaba a `QuoteItem`, la ruta recibe IDs de `SpaBookingService`

| Campo | Valor |
|---|---|
| **Fecha** | 2026-07-31 |
| **Severidad** | P2 — Alto (funcionalidad completamente inoperante, sin reporte previo del usuario) |
| **Componente** | `SpaBookingController::assignProfessional()` + `resources/views/agenda/partials/_work_order.blade.php` |
| **Impacto** | Botón "Asignar" de cada línea de servicio en toda Orden de Trabajo — nunca funcionó en producción |
| **Estado** | ✅ RESUELTO |

**Síntoma:**
No reportado por el usuario — encontrado al construir BL-075 (costo de proveedor externo), que necesitaba extender este mismo modal. Al revisar el controlador antes de tocarlo, se detectó la inconsistencia de tipos.

**Causa raíz:**
`_work_order.blade.php` itera `$booking->services` (relación `SpaBooking::services()`, modelo `SpaBookingService`) y arma `route('agenda.items.assign', [$booking, $item])` con el `id` de esas filas. Pero `SpaBookingController::assignProfessional(Request $request, SpaBooking $booking, QuoteItem $item)` tipaba el tercer parámetro como `QuoteItem` — un modelo completamente distinto, de otra tabla. El binding implícito de Laravel busca ese ID en `quote_items`, no en `spa_booking_services`. Verificado contra la base de datos real de producción: `quote_items` tiene **0 filas** (el flujo formal de "Nueva Opción de presupuesto" casi no se usa en la práctica — casi todo el negocio agenda y cobra directo, sin pasar por Presupuestos), así que cualquier ID de `SpaBookingService` (1 a 27 en producción al momento de este hallazgo) nunca coincidía con ningún `QuoteItem` existente → 404 garantizado en el 100% de los casos, desde que se construyó esta pantalla.

**Por qué no se detectó antes:** el 404 ocurre solo al hacer submit del modal (no al abrirlo), en una pantalla operativa (Orden de Trabajo) que probablemente se usa poco para esta acción específica en el día a día — sin un reporte explícito de "el botón no funciona", quedó invisible. No había ningún test cubriendo esta ruta.

**Solución definitiva:** retipado el parámetro a `SpaBookingService $item` (el modelo real que la vista siempre envió). De paso se corrigió que el checkbox `is_external`, al no enviarse cuando queda desmarcado (comportamiento normal de un `<input type="checkbox">` en HTML), nunca se podía poner en `false` una vez marcado — ahora se usa `$request->boolean('is_external')` en vez de depender de que la clave exista en el payload validado.

**Lección:** cuando una vista arma una ruta con `route(..., [$booking, $item])` a partir de una relación (`$booking->services`), verificar de qué modelo es esa relación **antes** de confiar en el type-hint del método del controlador — el nombre del parámetro (`$item`) no garantiza que coincida con el modelo real que la vista está enviando. Vale la pena grepear el nombre de la tabla real que alimenta el `@foreach` cuando el type-hint de un controlador "parece" razonable pero no hay tests que lo confirmen.

---

## NT-044 — `<script>` sin atributo `nonce` bajo `@push('scripts')`: bloqueado en silencio por la CSP (variante de NT-042)

| Campo | Valor |
|---|---|
| **Fecha** | 2026-07-31 |
| **Severidad** | P2 — Alto (silencioso: no lanza error visible, la funcionalidad simplemente no existe en el navegador real) |
| **Componente** | `app/Http/Middleware/ContentSecurityPolicy.php` + cualquier vista con un `<script>` sin `nonce` dentro de `@push('scripts')` |
| **Impacto** | 4 archivos con el bug: `agenda/create.blade.php`, `agenda/edit.blade.php` (el chequeo de disponibilidad en vivo — incluye el checkbox de "forzar horario" agregado ese mismo día, nunca funcional fuera de pruebas HTTP), `finances/cash-sessions/close.blade.php`, `finances/cash-sessions/show.blade.php` |
| **Estado** | ✅ RESUELTO |

**Síntoma:**
Encontrado no por reporte del usuario sino al agregar una funcionalidad nueva al mismo script (`checkAvailability` en `agenda/create.blade.php`) — al revisar por qué el script pudiera no ejecutarse en un navegador real, se detectó que el `<script>` que lo contiene no tenía `nonce`.

**Causa raíz:**
`ContentSecurityPolicy.php` define `script-src 'self' 'nonce-{$nonce}' 'unsafe-eval'` — sin `'unsafe-inline'`. A diferencia de NT-042 (atributos de evento inline como `onclick=`), esto es sobre **etiquetas `<script>` completas**: por spec de CSP, una etiqueta `<script>` inline (sin `src`) solo se ejecuta si su atributo `nonce` coincide con el nonce declarado en la cabecera `Content-Security-Policy` de esa respuesta. `@stack('scripts')` en `layouts/app.blade.php` se renderiza tal cual, sin envolver ni inyectar `nonce` automáticamente — cada vista que empuja un `<script>` vía `@push('scripts')` es responsable de poner su propio `nonce="{{ csp_nonce() }}"`. Los 4 archivos afectados simplemente lo omitieron al escribir el script (probablemente copiando una plantilla más antigua, de antes de que la CSP con nonce existiera).

**Solución definitiva:** agregar `nonce="{{ csp_nonce() }}"` a la etiqueta `<script>` en los 4 archivos. Barrido completo del proyecto confirmó que no quedan más casos (`grep` de `<script>` sin `nonce` ni `src=` dentro de `resources/views`).

**Lección:** al escribir un nuevo `<script>` dentro de `@push('scripts')` en este proyecto, **siempre** agregar `nonce="{{ csp_nonce() }}"` a la etiqueta de apertura — no hay ningún mecanismo automático que lo inyecte. Esta nota es la contraparte de NT-042: ese cubre atributos de evento inline (`onclick=`), esta cubre etiquetas `<script>` completas sin nonce — ambos casos fallan igual de silenciosos (sin error visible para el usuario, solo en la consola de DevTools), así que conviene revisar los dos patrones juntos al auditar una vista nueva.

---

## NT-043 — La búsqueda de clientes no se podía enviar ("No se pudo crear el cliente")

| Campo | Valor |
|---|---|
| **Fecha** | 2026-07-24 |
| **Severidad** | P1 — Crítico (bloqueaba buscar cualquier cliente desde el listado, no solo en el flujo de alta de mascota) |
| **Componente** | `resources/js/modules/client-form.js` (`initClientCreateForm()`) |
| **Impacto** | El botón "Aplicar" del filtro de búsqueda en `clients/index.blade.php` no funcionaba nunca — mostraba "No se pudo crear el cliente" y no enviaba la búsqueda |
| **Estado** | ✅ RESUELTO |

**Síntoma:**
El usuario reportó que al intentar dar de alta una mascota nueva (botón "Nueva mascota" en el listado de mascotas → lo lleva a buscar el cliente dueño), escribía parte del nombre, presionaba "Aplicar" y en vez de filtrar aparecía el mensaje "No se pudo crear el cliente" — un mensaje sin sentido en una pantalla de búsqueda.

**Causa raíz:**
`initClientCreateForm()` localizaba el formulario de alta de cliente con `document.querySelector('form[action$="/clients"]')` — un selector por atributo, no por ID. El formulario de búsqueda de `clients/index.blade.php` (componente `x-list-filters`, método GET) apunta a `route('clients.index')`, cuya URL **también termina en `/clients`** — igual que `route('clients.store')`, el destino real del formulario de alta. Como `client-form.js` se importa globalmente en `app.js` y corre en **todas** las páginas, en el listado de clientes el selector encontraba el formulario de búsqueda (el único que hace match ahí) y le enganchaba toda la validación pensada para crear un cliente completo (nombre, dirección, teléfono) — validación que siempre falla en un formulario de búsqueda, bloqueando el envío con `event.preventDefault()`.

**Solución definitiva:** cambiar el selector a `document.getElementById('client-create-form')`, igual patrón ya usado correctamente para el formulario de edición (`document.getElementById('client-edit-form')`, línea 308 del mismo archivo). Requirió recompilar assets (`npm run build` dentro de `estetican_app`) y borrar vistas compiladas.

**Lección:** nunca seleccionar formularios por `action` (atributo `[action$="..."]`/`[action^="..."]`) cuando dos formularios distintos de la misma pantalla pueden compartir la misma URL base (típico entre un formulario GET de búsqueda hacia `index` y un formulario POST de alta hacia `store`, ambos con la misma ruta raíz). Usar siempre `id` único por formulario.

**Adenda — condición de carrera al auto-abrir el modal "Agregar Mascota" (BL-069):** el primer intento de conectar `clients.edit?open_pet_modal=1` con la apertura automática del modal usó un `<script nonce>` empujado desde la vista (`@push('scripts')`) que hacía `document.querySelector('[data-client-edit-action="show-pet-modal"]')?.click()` en `DOMContentLoaded`. Fallaba silenciosamente: ese script clásico (no-módulo) se ejecuta *durante* el parseo del HTML, mientras que `client-form.js` se carga como módulo ES (vía Vite) y se difiere hasta *después* de terminar el parseo — así que el listener `DOMContentLoaded` de la vista se registraba primero y disparaba el clic **antes** de que `initClientEditForm()` (que vive en `client-form.js`) alcanzara a enganchar el listener de clics que abre el modal. El usuario solo veía la página de edición cruda, sin modal, como si el botón lo hubiera mandado a "editar cliente" sin más. **Solución real:** mover la lectura de `open_pet_modal` (vía `URLSearchParams(window.location.search)`) *dentro* de `initClientEditForm()` mismo, justo después de registrar los listeners de acciones — se ejecuta en el mismo tick que ya inicializó todo lo necesario, sin depender del orden de carga entre script clásico y módulo. Se quitó por completo el script empujado desde la vista.

**Adenda 2 — el modal "OK" no guardaba nada, solo armaba una fila pendiente:** con el modal ya abriendo bien, el usuario reportó que tras completarlo la mascota "no aparecía" en el panel "Seleccionar mascota para gestionar tablas dependientes" de `clients/edit.blade.php`. Causa: `confirmAddPetModal()` solo inserta un `<tr>` nuevo en la tabla `#pets` del formulario grande y marca `formChanged` — **no envía nada al servidor**; el usuario debe además bajar y presionar "Actualizar" para que `ClientController@update()` recién cree el registro real. Ese paso extra tiene sentido cuando se edita un cliente existente (uno puede acumular varios cambios antes de guardar todos juntos), pero rompe la promesa del botón "Agregar mascota aquí" del flujo `mode=pet_creation`, que se supone hace un alta directa.

**Solución:** en `confirmAddPetModal()`, si la página se abrió con `?open_pet_modal=1`, se llama a `form.requestSubmit()` inmediatamente después de armar la fila — dispara el mismo evento `submit` real (con su validación existente, `collectClientFormWarnings`) que ya usa el botón "Actualizar" manual, así que la mascota queda guardada de verdad sin pasos adicionales. Fuera de ese modo (uso normal de "Editar cliente"), el comportamiento de acumular cambios sigue igual.

**Limitación conocida, no resuelta hoy:** el modal solo captura datos generales (nombre, especie, raza, etc.) — no hay campo de foto, porque `PetPhotoImageManager` requiere un `pet_id` real que no existe hasta que la mascota se guarda. Tras el auto-guardado, la mascota nueva sí aparece de inmediato en el panel "Seleccionar mascota" — desde ahí, un clic más lleva a su ficha (`clients.pets.show`) donde sí se puede subir la foto. Es un paso adicional, no un bug; unificar esto en un solo paso implicaría rediseñar el modal para subir la foto después de crear el registro (tipo "guardar y continuar"), evaluado como fuera de alcance de esta sesión.

---

## NT-042 — `onclick=`/`onsubmit=` inline no funcionan bajo la CSP del proyecto

| Campo | Valor |
|---|---|
| **Fecha** | 2026-07-24 |
| **Severidad** | P2 — Alto (silencioso: no lanza error visible, algunos casos ejecutan la acción SIN pedir confirmación) |
| **Componente** | `app/Http/Middleware/ContentSecurityPolicy.php` + cualquier vista con atributos de evento inline |
| **Impacto** | Botones de confirmación rotos en 8 vistas — las 8 corregidas en esta sesión: `pets/partials/{index-blocks,index-table}.blade.php`, `pets/show.blade.php`, `user/show.blade.php`, `operators/partials/unavailabilities.blade.php`, `whatsapp/plantillas/index.blade.php`, `clinical/visits/show.blade.php`, `clinical/pets/show.blade.php` (4 casos en este último) |
| **Estado** | ✅ RESUELTO |

**Síntoma:**
El botón "Inactivar" de mascotas (tarjetas/tabla) no hacía absolutamente nada al hacer clic — ni confirmación, ni error visible. Reportado por el usuario tras agregarle texto/tooltip a un botón que antes solo tenía un ícono (por eso nunca se había notado que además estaba roto).

**Causa raíz:**
`ContentSecurityPolicy.php` define `script-src 'self' 'nonce-{$nonce}' 'unsafe-eval'` — sin `'unsafe-inline'` y sin `script-src-attr` propio. Por spec de CSP, el nonce solo autoriza etiquetas `<script nonce="...">` completas; **no autoriza atributos de evento inline** (`onclick="..."`, `onsubmit="..."`) — esos se rigen por `script-src-attr`, que al no estar declarado hereda de `script-src` (sin `unsafe-inline` → bloqueado). El navegador los descarta silenciosamente, sin lanzar ningún error visible al usuario (solo aparece en la consola de DevTools).

Dos variantes del síntoma, según el tipo de botón:
- `<button type="button" onclick="...">` (mascotas, tarjetas/tabla): el clic **no hace nada** — no hay otra forma de disparar el submit.
- `<form onsubmit="return confirm(...)">` con `<button type="submit">` dentro (mascotas ficha, usuarios ficha): el `onsubmit` nunca corre, así que el navegador **envía el formulario igual, sin pedir confirmación** — más peligroso que el primer caso porque la acción sí ocurre, solo que sin el paso de seguridad.

**Solución definitiva:**
El proyecto ya tenía el patrón correcto resuelto desde antes (`resources/js/modules/confirm-actions.js`): un listener global en `document` que escucha clics en cualquier elemento con el atributo `data-confirm="mensaje"`, sin usar atributos inline. Requiere que el botón sea `type="submit"` dentro del `<form>` a enviar (no `type="button"` con JS custom). Ya lo usan correctamente `groups/`, `resources/`, `branches/`, `finances/`, `hotel-reservations/`, entre otros.

**Lección:** nunca usar `onclick=`/`onsubmit=` (ni ningún atributo `on*=`) en vistas nuevas de este proyecto — la CSP los bloquea sin avisar. Usar siempre `data-confirm="mensaje"` en un `<button type="submit">` dentro del `<form>` real a enviar.

Barrido completo del proyecto (`grep -rn 'onsubmit=\|onclick="confirm'`) confirmó cero ocurrencias restantes tras corregir los 4 archivos pendientes.

**Addendum (27/07/2026):** apareció una recurrencia real — `layouts/report.blade.php` (compartido por recibo, orden de trabajo y presupuesto) tenía `<button onclick="window.print()">`, exactamente el mismo bug: clic sin ningún efecto, sin error visible. El barrido de esta nota original nunca lo agarró porque el patrón de grep usado (`onclick="confirm`) era específico para el caso de confirmaciones — no cubría `onclick=` en general. Corregido con el otro patrón ya establecido en el proyecto para clics simples sin `<form>` de por medio (distinto de `data-confirm`, que requiere un submit real): `<script nonce="{{ csp_nonce() }}">` + `addEventListener` sobre un `id`, mismo estilo que ya usa `agenda/global-create.blade.php`. Verificado con `grep -rn 'onclick="window.print'` sin más ocurrencias en el proyecto.

**Lección ampliada:** al auditar por este bug, buscar `on\w+=` en general (no solo `onclick="confirm`) — cualquier atributo de evento inline cae en el mismo bloqueo silencioso, no solo los ligados a confirmaciones de formulario.

---

## NT-001 — cropperjs v2 incompatible con código v1

| Campo | Valor |
|---|---|
| **Fecha** | 2026-05-25 |
| **Severidad** | P1 — Crítico |
| **Componente** | `x-image-upload` / `resources/js/modules/image-upload.js` |
| **Impacto** | Subida de fotos 100% inoperativa en todo el sistema (7 vistas afectadas) |
| **Estado** | ✅ RESUELTO |

**Síntoma:**
Al subir una foto, el modal abría pero la imagen se veía muy pequeña; no recortaba, no giraba y no guardaba. El comportamiento era idéntico en todas las entidades (mascotas, recursos, operadores, usuarios).

**Causa raíz:**
`package.json` tenía `"cropperjs": "^2.1.1"`. La v2 es una reescritura completa basada en Web Components (`<cropper-canvas>`, `<cropper-selection>`, etc.) con API totalmente distinta a v1. En v2, los métodos `rotate()`, `getCroppedCanvas()` y las opciones `aspectRatio`, `viewMode`, `dragMode`, `autoCropArea`, etc. **no existen** en la clase `Cropper`. La clase v2 solo expone `getCropperCanvas()`, `getCropperImage()`, `getCropperSelection()`, `destroy()`. El código usaba la API de v1 (la misma que funcionaba vía CDN `cropperjs@1.6.2`). El modal se abría porque Bootstrap funciona independientemente, pero Cropper inicializaba un objeto v2 vacío — de ahí la imagen pequeña (la `<img>` original sin Cropper encima).

**Workaround:**
Ninguno viable. El sistema de fotos era completamente inoperable.

**Solución definitiva:**
```bash
npm install cropperjs@1.6.2 --save-exact
npm run build
# En package.json queda: "cropperjs": "1.6.2" (sin ^)
```

**Lección:**
- Las librerías de UI con major versions son **ítems de configuración controlados**. Ver CMDB en `ESTRATEGIA_DESARROLLO.md §8`.
- **Nunca usar `^` en librerías de UI** como cropperjs o flatpickr. Los breaking changes entre major versions son totales.
- El CSS en `resources/css/vendor-cropper.css` es de v1. Si se actualiza cropperjs, el CSS también debe actualizarse y las diferencias de API deben auditarse.

---

## NT-002 — Inicialización de Cropper.js antes de que el modal sea visible

| Campo | Valor |
|---|---|
| **Fecha** | 2026-05-25 |
| **Severidad** | P2 — Alto |
| **Componente** | `x-image-upload` / `resources/js/modules/image-upload.js` |
| **Impacto** | Recortador aparece con dimensiones incorrectas o sin renderizar |
| **Estado** | ✅ RESUELTO |

**Síntoma:**
La imagen se veía en el modal pero el recortador no aparecía, o aparecía muy pequeño. Los controles de Cropper (handles, crop box) no eran visibles o eran diminutos.

**Causa raíz:**
Cropper.js mide el contenedor DOM cuando se inicializa (`new Cropper(img, options)`). Si se llama antes de que Bootstrap haya completado la animación `fade` del modal (`.show()` dispara la animación, pero el modal no tiene dimensiones reales hasta que termina), Cropper obtiene `clientWidth: 0` o dimensiones incorrectas y construye un canvas de tamaño 0.

Los intentos fallidos fueron:
- `setTimeout(300ms)` → el tiempo varía según el dispositivo
- `img.decode()` → decodifica la imagen pero no garantiza que el modal tenga layout

**Workaround:**
Ninguno funcional. El síntoma era indistinguible de NT-001 para el usuario.

**Solución definitiva:**
```javascript
// Registrar el listener ANTES de abrir el modal
const onShown = () => {
    modalEl.removeEventListener('shown.bs.modal', onShown);
    requestAnimationFrame(() => {         // garantiza exactamente un frame pintado
        this.cropper = new Cropper(img, { aspectRatio, ...opciones });
    });
};
modalEl.addEventListener('shown.bs.modal', onShown);
img.src = dataUrl;              // asignar src DESPUÉS del listener
this.modalInstance.show();      // fired → shown.bs.modal al terminar animación (~300ms)
```

`shown.bs.modal` es el evento oficial de Bootstrap que garantiza que el modal es completamente visible. El `requestAnimationFrame` adicional asegura que el navegador ha pintado al menos un frame.

**Lección:**
- Siempre inicializar librerías de visualización (Cropper, Charts, Maps) dentro del evento de "elemento visible" de su contenedor, nunca antes.
- `img.decode()` sirve para decodificar datos de imagen, no para garantizar layout CSS.

---

## NT-003 — Contenedor del modal: `height` fijo vs `max-height`

| Campo | Valor |
|---|---|
| **Fecha** | 2026-05-25 |
| **Severidad** | P3 — Medio |
| **Componente** | `resources/views/components/image-upload.blade.php` |
| **Impacto** | El área de recorte muestra solo la parte superior de la imagen |
| **Estado** | ✅ RESUELTO |

**Síntoma:**
El recortador se inicializaba correctamente pero solo mostraba la parte superior de la imagen. Los handles de Cropper quedaban fuera del área visible.

**Causa raíz:**
El contenedor del modal tenía `max-height: 60vh; overflow: hidden`. Con `max-height`, la altura del contenedor es determinada por el contenido (la imagen). Cuando hay imágenes con orientación portrait (más altas que anchas), `width: 100%` hace la imagen tan alta que excede el viewport y los controles de Cropper quedan ocultos por `overflow: hidden`. Con `height: 60vh` (fijo), el contenedor siempre tiene 60vh sin importar la imagen.

**Workaround:**
Usar solo imágenes landscape. No aplicable en producción.

**Solución definitiva:**
```html
<!-- CORRECTO: altura fija -->
<div style="height: 60vh; overflow: hidden;">
    <img src="" x-ref="cropImage" style="display:block; max-width: 100%;">
</div>

<!-- INCORRECTO: altura variable según contenido -->
<div style="max-height: 60vh; overflow: hidden;">
    <img src="" x-ref="cropImage" style="display:block; width: 100%;">
</div>
```

**Lección:**
- Cropper.js necesita un contenedor con **dimensiones absolutas** (no relativas al contenido).
- `max-height` + `overflow:hidden` es una combinación que recorta visualmente pero no fija el layout desde la perspectiva de Cropper.

---

## NT-004 — Alpine.js: `x-ref` en Blade Components con `x-data` anidado

| Campo | Valor |
|---|---|
| **Fecha** | 2026-05-25 |
| **Severidad** | P4 — Bajo (diseño documentado, no bug) |
| **Componente** | `resources/show.blade.php` — Patrón B de auto-submit |
| **Impacto** | Si se implementa incorrectamente, el form no se envía al aplicar el recorte |
| **Estado** | ✅ DOCUMENTADO |

**Contexto:**
El form padre necesita acceder al `input[type=file]` dentro de `x-image-upload` para verificar si hay archivo antes de enviar.

**Patrón correcto:**
```blade
<form x-data="{ submit() { this.$el.submit(); } }">
    <div @image-cropped="submit()">
        {{-- x-ref va en el componente Blade, no en el div wrapper --}}
        <x-image-upload x-ref="photoInput" ... />
    </div>
</form>
```

`x-ref="photoInput"` sobre un Blade component pasa el atributo al div raíz del componente mediante `$attributes->merge()`. Alpine del form padre puede acceder con `this.$refs.photoInput` porque el `x-ref` está en el elemento raíz del componente hijo, que pertenece al scope del padre (el scope hijo comienza con `x-data` en ese mismo elemento).

**Alternativa más simple (preferida):**
```blade
<x-image-upload autoSubmitFormId="mi-form-id" ... />
```
Usar `autoSubmitFormId` directamente en el componente evita toda esta complejidad.

---

## NT-005 — Blade: `@php(expr)` con paréntesis anidados

| Campo | Valor |
|---|---|
| **Fecha** | 2026-05-15 (detectado en auditoría de producción) |
| **Severidad** | P1 — Crítico |
| **Componente** | Compilador Blade (afecta cualquier vista) |
| **Impacto** | Variables `undefined` aleatorias en producción. 29 vistas afectadas en el incidente del 25/05. |
| **Estado** | ✅ RESUELTO (corrección masiva aplicada con script Python) |

**Síntoma:**
Variables PHP aparecen como `undefined` de forma aleatoria en producción. El error puede ser `Undefined variable $X` en cualquier punto de la vista después de la directiva afectada.

**Causa raíz:**
La directiva `@php(expr)` tiene un parser que no maneja paréntesis anidados. Al encontrar el primer `)` interno (en funciones como `parse_url(...)`, `in_array(...)`, `firstWhere(...)`), el compilador Blade cierra la directiva y el resto de la expresión queda sin procesar, corrompiendo todo lo que sigue en la vista.

**Workaround:**
Extraer la expresión a una variable PHP antes del template. No siempre práctico.

**Solución definitiva:**
```blade
{{-- ❌ MAL: el parser Blade falla en el primer ) interno --}}
@php($url = parse_url($value, PHP_URL_PATH))
@php($found = in_array($item->id, $ids))

{{-- ✅ BIEN: bloque completo, sin ambigüedad --}}
@php
    $url = parse_url($value, PHP_URL_PATH);
    $found = in_array($item->id, $ids);
@endphp
```

**Lección:**
- **Regla absoluta:** No usar `@php(expr)` con paréntesis anidados. Solo expresiones simples sin funciones que contengan paréntesis.
- Para todo lo demás: definir la variable en el controlador y pasarla con `compact()`.
- **Ojo:** `@php ... @endphp` multilínea también tiene sus propios bugs dentro de loops y secciones — ver NT-008 y NT-010.
- Las vistas compiladas en caché pueden ocultar el error hasta que la caché se limpie. Siempre limpiar vistas después de cambios en Blade.

---

## NT-006 — CSP + Alpine.js requiere `unsafe-eval`

| Campo | Valor |
|---|---|
| **Fecha** | 2026-05-25 |
| **Severidad** | P1 — Crítico (Alpine completamente bloqueado sin esto) |
| **Componente** | `app/Http/Middleware/ContentSecurityPolicy.php` |
| **Impacto** | Alpine.js no evalúa ninguna expresión. La UI interactiva queda completamente rota. |
| **Estado** | ✅ RESUELTO |

**Síntoma:**
Con CSP activo sin `unsafe-eval`: los directives `x-data`, `@click`, `x-show`, `x-if` no tienen efecto. La UI parece "muerta".

**Causa raíz:**
Alpine.js v3 evalúa sus expresiones de template usando `new AsyncFunction()` (equivalente a `eval()`). Esta API requiere `unsafe-eval` en la directiva `script-src` del CSP. Sin ella, el navegador bloquea la evaluación silenciosamente.

**Solución definitiva:**
```php
// ContentSecurityPolicy.php
"script-src 'self' 'nonce-{$nonce}' 'unsafe-eval'",
```

**Lección:**
- Los scripts inline (bloques `<script>...</script>`) necesitan el nonce.
- El bundle (servido desde `/build/assets/`) es `'self'` y no necesita nonce.
- `unsafe-eval` es necesario para Alpine pero NO para Bootstrap, Cropper u otras librerías.
- Considerar migrar a Alpine CSP Build (versión sin `eval`) si se requiere mayor seguridad en el futuro.

---

## NT-007 — Riesgo: Bootstrap Modal desvincula Alpine `$refs` si se mueve al body

| Campo | Valor |
|---|---|
| **Fecha** | 2026-05-25 |
| **Severidad** | P4 — Bajo (riesgo identificado, no ocurrido) |
| **Componente** | `x-image-upload` — modal Bootstrap |
| **Impacto** | Potencial: `this.$refs.cropModal` queda `undefined` si Bootstrap mueve el modal |
| **Estado** | ⚠️ RIESGO DOCUMENTADO — no ocurre en configuración actual |

**Contexto:**
Bootstrap Modal puede mover el elemento `<div class="modal">` al `<body>` cuando se usa `data-bs-target` (trigger HTML declarativo). Si el modal sale del scope Alpine, `this.$refs.cropModal` podría quedar inválido.

**Por qué NO ocurre actualmente:**
El modal se inicializa vía JS con `new bootstrap.Modal(modalEl)` pasando la referencia directa. En este modo Bootstrap NO mueve el elemento al body. El modal permanece donde está en el DOM, dentro del scope Alpine.

**Si llegara a ocurrir:**
1. Guardar la referencia antes de que Bootstrap mueva el modal: `const modalRef = this.$refs.cropModal`
2. Mover manualmente: `document.body.appendChild(modalRef)`
3. Inicializar Cropper con la referencia guardada (no con `this.$refs`)

**Lección:**
- Siempre inicializar Bootstrap Modal con la referencia al elemento, no con atributos `data-bs-target`.
- Preferir `new bootstrap.Modal(element)` sobre `<button data-bs-toggle="modal" data-bs-target="#...">`.

---

## NT-008 — Blade: `@php ... @endphp` multi-línea rompe el section stack cuando coexiste con `<x-slot>`

| Campo | Valor |
|---|---|
| **Fecha** | 2026-06-14 |
| **Severidad** | P2 — Alto (500 en producción) |
| **Componente** | Blade — section stack / componentes con slots |
| **Impacto** | `ViewException: Cannot end a section without first starting one` |
| **Estado** | ✅ RESUELTO |

**Síntoma:**
Vistas con `@extends` + `@section` + `<x-slot:actions>` + bloque `@php ... @endphp` multi-línea arrojaban 500 en producción con el error `Cannot end a section without first starting one`.

**Causa raíz:**
El compilador de Blade gestiona `@section`/`@endsection` y los slots de componentes (`<x-slot:actions>`) a través del mismo stack interno. Un bloque `@php ... @endphp` multi-línea, al compilarse como `<?php ... ?>`, interfiere con el rastreo del stack en ciertos contextos, dejando a `@endsection` sin una sección abierta que cerrar.

**Solución:**
Nunca definir variables con `@php ... @endphp` (forma de bloque) dentro de vistas que tengan `@section` + componentes con slots. En cambio:

1. **Preferido:** pasar la variable desde el controlador con `compact()`.
2. **Alternativa:** usar la forma de expresión `@php($var = valor)` (sin `@endphp`) para asignaciones simples dentro del cuerpo de un loop.
3. **Para arrays estáticos** usados en la vista: siempre definirlos en el controlador.

**Lo que NO produce el error:**
- `@php($var = expr)` en una sola línea (sin `@endphp`).
- Bloques `@php ... @endphp` dentro de partials `@include` (que no tienen `@section`).

**Patrón correcto:**
```php
// Controlador
return view('finances.payment-methods.index', compact('methods', 'typeLabels'));
```
```blade
{{-- Vista: sin @php de bloque --}}
{{ $typeLabels[$method->type] ?? $method->type }}
```


---

## NT-010 — Blade: `@php ... @endphp` multilínea dentro de `@foreach`/`@forelse` causa ParseError

| Campo | Valor |
|---|---|
| **Fecha** | 2026-06-15 |
| **Severidad** | P1 — Crítico (500 en producción) |
| **Componente** | Compilador Blade — directivas dentro de loops |
| **Impacto** | `ParseError: unexpected token 'else'` o `unexpected token 'endforeach'` en cualquier vista con loop |
| **Estado** | ✅ RESUELTO |

**Síntoma:**
La vista arroja 500 con `ParseError: syntax error, unexpected token "else"` o `unexpected token "endforeach"`. El error apunta a una línea dentro de un `@forelse` o `@foreach`.

**Causa raíz:**
En la versión de Laravel/Blade del proyecto, el bloque `@php ... @endphp` multilínea dentro de un loop compila de forma inconsistente: algunas directivas que siguen dentro del mismo loop (`@else`, `@endif`, `@endforelse`) se compilan a PHP pero sin su correspondiente apertura, creando tokens huérfanos que rompen la sintaxis del archivo compilado.

Ejemplo que falla:
```blade
@forelse($sessions as $session)
    @php
        $diff = $session->difference;
    @endphp
    @if($diff > 0)
        ...
    @else               {{-- 💥 este @else queda huérfano --}}
        ...
    @endif
@empty
@endforelse
```

**Workaround:**
Ninguno en caliente — requiere corrección del template.

**Solución definitiva:**
1. **Opción preferida:** pasar la variable desde el controlador.
2. **Si la variable debe calcularse en vista:** definirla ANTES del loop con `@php($var = expr)` de una sola línea (sin `@endphp`), y referenciarla dentro del loop sin reasignar.

```blade
{{-- ❌ MAL: @php multilínea dentro del loop --}}
@forelse($sessions as $session)
    @php
        $diff = $session->difference;
    @endphp
    {{ $diff }}
@empty
@endforelse

{{-- ✅ BIEN: usar directamente el valor, sin @php dentro del loop --}}
@forelse($sessions as $session)
    {{ $session->difference }}
@empty
@endforelse

{{-- ✅ BIEN: si necesitas pre-calcular algo, hazlo FUERA del loop --}}
@php($totals = $sessions->pluck('difference'))
@forelse($sessions as $i => $session)
    {{ $totals[$i] }}
@empty
@endforelse
```

**Lección:**
- **Regla de proyecto:** NUNCA usar `@php ... @endphp` (forma de bloque, multilínea) dentro de `@foreach`, `@forelse` o cualquier loop Blade.
- Dentro de loops: solo `@php($var = expr)` en una sola línea, y solo si no hay otra opción.
- La forma más segura siempre es pasar todo desde el controlador con `compact()`.
- Este bug es distinto de NT-005 (paréntesis anidados en `@php(expr)`) y de NT-008 (block dentro de section+slot). Son tres familias de bugs del compilador Blade.

---

## NT-009 — Cloudflare Tunnel: "Hostname routes" ≠ hostnames públicos

| Campo | Valor |
|---|---|
| **Fecha** | 2026-06-15 |
| **Severidad** | P3 — Medio (confusión de UI que bloquea el deploy) |
| **Componente** | Cloudflare Zero Trust — configuración del tunnel `orangepi-estetican` |
| **Impacto** | Tiempo perdido intentando configurar hostname público en el lugar equivocado |
| **Estado** | ✅ DOCUMENTADO |

**Síntoma:**
Al agregar un subdominio nuevo (`mov.estetican.org`) al tunnel de Cloudflare, la sección "Hostname routes" en Zero Trust → Networks → Connectors parece el lugar correcto pero en realidad es para **hostnames privados** (red privada, requiere Cloudflare WARP / One Client).

**Causa raíz:**
Cloudflare renombró y reorganizó su interfaz de Zero Trust. La tabla de "Public Hostnames" del tunnel (antes en la pestaña del mismo nombre al editar el tunnel) ahora se llama **"Published application routes"** y está en una pestaña diferente dentro del mismo tunnel.

**Solución:**
Para agregar un hostname público a un tunnel existente:
1. Zero Trust → Networks → Connectors → Cloudflare Tunnels → clic en el tunnel
2. Pestaña **Published application routes** (NO "Hostname routes")
3. Botón "Add" → Subdomain + Domain + Service: `http://192.168.100.250:80`
4. Cloudflare crea el registro DNS automáticamente (tipo Tunnel)

**Lección:**
- "Hostname routes" = red privada (necesita WARP)
- "Published application routes" = hostnames públicos accesibles desde cualquier browser
- Al agregar la ruta, NO crear manualmente el registro DNS — Cloudflare lo crea solo con tipo "Tunnel"

---

## NT-013 — `migrate` desde cero falla: `users.operator_role_id` sin migración propia

| Campo | Valor |
|---|---|
| **Fecha** | 2026-07-01 |
| **Severidad** | P2 — Bloquea `artisan migrate` en cualquier base de datos nueva (ej. `testing`) |
| **Componente** | `database/migrations/2026_06_30_000001_add_operator_id_to_users_table.php` |
| **Impacto** | `Schema::table('users', ...)->after('operator_role_id')` falla con `Unknown column 'operator_role_id'` en una base nueva |
| **Estado** | ✅ RESUELTO |

**Causa raíz:**
`users.operator_role_id` existe en producción y se usa activamente en `App\Models\User::operatorRole()`, pero ninguna migración del repo la crea — se agregó en algún momento fuera del flujo de migraciones (parche manual histórico, posible edición de un archivo de migración después de haber corrido en prod). La migración `2026_06_30_000001_add_operator_id_to_users_table.php` asume la columna presente vía `->after('operator_role_id')`, lo cual solo es cierto en producción, no en una base nueva.

**Cómo se detectó:** al habilitar por primera vez la base `testing` (ver más abajo) para correr `artisan test`.

**Solución:** migración nueva `2026_06_30_000000_add_operator_role_id_to_users_table.php` (timestamp anterior a la 000001) que crea la columna solo si falta (`Schema::hasColumn` guard) — no-op en producción, corrige el flujo en bases nuevas.

**Lección:** cuando una migración usa `->after('columna_de_otra_migracion')`, verificar que esa columna tenga su propia migración rastreable en el repo, no solo que exista en producción.

---

## NT-014 — No existe entorno Sail/dev separado en esta OPi; usar `docker exec estetican_app`

| Campo | Valor |
|---|---|
| **Fecha** | 2026-07-01 |
| **Severidad** | P3 — Bloquea trabajo si se intenta seguir el flujo documentado de WSL2+Sail al pie de la letra |
| **Componente** | Todo el repo — expectativa de entorno de desarrollo |
| **Estado** | ✅ RESUELTO (documentado) |

**Síntoma:** `./vendor/bin/sail up -d` falla con `Error starting userland proxy: ... bind: address already in use` en el puerto 8000.

**Causa raíz:** El contenedor de producción `estetican_app` (`compose.prod.yaml`) expone `127.0.0.1:${APP_PORT:-8000}:80` a propósito, para diagnóstico local — y monta `.:/var/www/html`, es decir, sirve exactamente el mismo código fuente de `apps/backoffice-laravel` que se edita en esta OPi. No hay una base de datos ni contenedor de desarrollo separados; todo el trabajo (migraciones, tinker, tests) corre directo contra el contenedor de producción vía `docker exec estetican_app ...`, como ya documentaba `docs/OPI_PRODUCCION.md` y `docs/tecnico/ESTRATEGIA_DESARROLLO.md`.

**Solución:** no usar Sail en este proyecto. Comandos: `docker exec estetican_app php artisan migrate --force`, `docker exec -it estetican_app php artisan tinker`, `docker exec estetican_app npm run build` (node/npm sí están instalados dentro de esa imagen).

**Lección:** la sección "Development Environment" de `CLAUDE.md` (WSL2 + Sail) describe un flujo que no aplica a esta instancia de la OPi — es el flujo genérico/aspiracional del template del proyecto, no el real. El real está en `docs/OPI_PRODUCCION.md`.

---

## NT-015 — Otra columna sin migración propia: `users.can_login`

| Campo | Valor |
|---|---|
| **Fecha** | 2026-07-02 |
| **Severidad** | P3 — Bloquea `migrate` desde cero en base nueva; sin impacto en producción |
| **Componente** | `users.can_login` |
| **Estado** | ✅ RESUELTO |

Mismo patrón que NT-013: `users.can_login` existe en producción (usado por `App\Http\Middleware\ApiAuthenticate` para bloquear login de usuarios desactivados) pero ninguna migración del repo la crea. Se agregó `2026_07_02_000000_add_can_login_to_users_table.php` con guard `Schema::hasColumn()` — no-op en producción, corrige el flujo en bases nuevas. Detectado al escribir tests contra la base `testing` recién habilitada (ver NT-014).

**Lección:** cada vez que se habilite `artisan test` desde cero en este proyecto, es probable que aparezcan más columnas de `users`/`operators` parcheadas manualmente sin migración rastreable (patrón recurrente de deuda técnica histórica). Solo se corrigen bajo demanda, cuando bloquean trabajo activo — no se persigue la lista completa preventivamente.

---

## NT-016 — `Api\BookingController`: `total_estimated_price` quedaba `null` con total en $0

| Campo | Valor |
|---|---|
| **Fecha** | 2026-07-02 |
| **Severidad** | P2 — Cualquier cita creada vía API móvil sin `services` (o con servicios de precio $0) fallaba con `Column 'total_estimated_price' cannot be null` |
| **Componente** | `app/Http/Controllers/Api/BookingController.php` (`store`, `update`) |
| **Estado** | ✅ RESUELTO |

**Causa raíz:** `'total_estimated_price' => $estimatedTotal ?: null` — el operador `?:` trata `0` como falsy, así que un total exactamente en `$0` (sin servicios, o servicios gratuitos) se guardaba como `null`, violando la columna `NOT NULL`. Detectado por un test nuevo (`BookingSchedulingValidationTest::test_accepts_a_valid_booking`) que no incluía `services` en el payload — no era un caso hipotético, cualquier llamada real a la API sin ese campo ya fallaba en producción.

**Solución:** guardar siempre `$estimatedTotal` (o `$prices->sum()`) directo, sin el atajo `?: null` — `0` es un total válido, no equivalente a "sin dato".

**Lección:** `?:` (operador Elvis) es peligroso con valores numéricos donde `0` es un resultado legítimo — usar `??` (null coalescing) o ninguno de los dos si el valor nunca debería convertirse a `null`.

---

## NT-019 — `node_modules`/`public/build` quedan `root` tras alguna ejecución previa y bloquean `npm run build` en la OPi

| Campo | Valor |
|---|---|
| **Fecha** | 2026-07-03 |
| **Severidad** | P3 — Bloquea compilación de assets hasta corregir permisos manualmente |
| **Componente** | `apps/backoffice-laravel/node_modules/`, `apps/backoffice-laravel/public/build/` |
| **Estado** | ✅ RESUELTO (workaround conocido) |

**Síntoma:** `npm run build` falla con `EACCES: permission denied` al escribir en `node_modules/.vite-temp/` o al intentar borrar `public/build/assets/*` antes de generar los nuevos archivos.

**Causa raíz:** en algún momento esos directorios se crearon/escribieron con el usuario `root` (probablemente una ejecución anterior de un proceso dentro de un contenedor Docker, o un `npm install`/build corrido con `sudo` por error) en vez de con el usuario `tomas`. `tomas` no tiene permiso de escritura sobre el árbol, así que Vite no puede crear su archivo temporal de config ni limpiar el directorio de salida.

**Solución:**
```bash
sudo chown -R tomas:tomas apps/backoffice-laravel/node_modules
sudo chown -R tomas:tomas apps/backoffice-laravel/public/build
npm run build
```

**Lección:** si `npm run build` falla con `EACCES` en la OPi, revisar primero el dueño de `node_modules/` y `public/build/` (`stat -c "%U:%G" <ruta>`) antes de investigar nada del código — es casi siempre este problema de permisos, no un error de Vite.

**Addendum (26/07/2026) — el `chown` vía el prefijo `!` de Claude Code no aplica nada:**

Al reproducirse este mismo problema durante BL-072, se le pidió al usuario correr el `sudo chown` de arriba usando `!sudo chown ...` dentro del chat de Claude Code. El comando "corrió" sin error visible, pero el owner de `public/build/assets/` no cambió (verificado con `stat`/`lsattr`/`ctime` — nada se tocó). Al pedir la misma orden con `-v` para ver salida real, apareció el motivo:
```
sudo: a terminal is required to read the password; either use the -S option to read from standard input or configure an askpass helper
sudo: a password is required
```
El mecanismo `!<comando>` de Claude Code no adjunta una TTY real al proceso, así que `sudo` no puede pedir la contraseña interactivamente y falla — a veces en silencio si no se revisa el `stderr`. **Cualquier comando que requiera `sudo` debe correrse desde una terminal real (SSH directo a la OPi o consola física), nunca vía `!` en el chat.** El agente puede sugerir el comando exacto, pero no puede ejecutarlo ni verificar que el usuario lo corrió en el lugar correcto sin pedir la salida completa.

---

## NT-018 — App móvil: `loadOccupied` no expandía el rango ocupado según `duration_minutes`

| Campo | Valor |
|---|---|
| **Fecha** | 2026-07-03 |
| **Severidad** | P2 — Alto (permite doble-agendar al mismo operador desde la app móvil) |
| **Componente** | `mob_apps/operador/src/admin/MobCitaNueva.tsx` — función `loadOccupied` |
| **Estado** | ✅ RESUELTO |

**Síntoma:** al agendar una cita para un operador que ya tenía otra cita de 1.5h, el grid de horarios solo mostraba bloqueado el slot exacto de inicio de la cita existente (ej. 10:30), dejando los siguientes slots que esa cita ya ocupa (11:00, 11:30) como disponibles para agendar.

**Causa raíz:** `loadOccupied` (línea ~139) construía el `Set` de horarios ocupados con `bookings.map(b => b.time.slice(0, 5))` — un solo elemento por cita existente, ignorando por completo `duration_minutes`/`end_time` que el backend (`Api\AgendaController::index`) ya devuelve correctamente. `isSlotInvalid` sí expande correctamente la duración de la cita **nueva** que se está creando, pero consultaba contra un `Set` que nunca contenía el rango completo de las citas **ya agendadas**. No es un bug introducido por BL-025 — ya existía; se hizo más visible al volverse el operador obligatorio en ese sprint.

**Solución:** `loadOccupied` ahora expande cada cita existente a todos los slots de 30 min que cubre (`buildSlots(startMin, endMin)`, usando `duration_minutes`/`end_time` de la respuesta) antes de agregarlos al `Set`.

**Lección:** cuando un grid de disponibilidad consulta "¿está ocupado este slot?", verificar que el cálculo de "ocupado" considere la duración completa de cada evento existente, no solo su hora de inicio — es un patrón fácil de omitir y el bug pasa desapercibido hasta que dos citas se solapan en horarios no evidentes a simple vista.

---

## NT-017 — No usar `disabled` nativo para bloquear campos con Flatpickr (`altInput`)

| Campo | Valor |
|---|---|
| **Fecha** | 2026-07-02 |
| **Severidad** | P3 — El campo de hora en "Programar servicio" no se habilitaba al elegir operador |
| **Componente** | `resources/views/agenda/create.blade.php`/`edit.blade.php` + Flatpickr (`altInput: true`) |
| **Estado** | ✅ RESUELTO |

**Causa raíz (fragilidad, no un bug puntual):** con `altInput: true`, Flatpickr crea un input visible nuevo (`self.altInput`) y copia `disabled` del input original **solo una vez, en su inicialización** (`self.altInput.disabled = self.input.disabled` en `flatpickr.js`). Alternar `disabled` en el input original *después* de que Flatpickr ya inicializó no garantiza que el campo visible (`altInput`) refleje el cambio de forma confiable, y localizarlo vía `nextElementSibling` para sincronizarlo a mano es frágil frente al timing de carga de módulos.

**Solución:** para gatear la interactividad de un campo con Flatpickr, envolverlo en un `<div>` contenedor y alternar una clase CSS (`opacity` + `pointer-events: none`) en el wrapper, en vez de tocar `disabled` en el input. No depende de cómo Flatpickr arma su DOM interno.

**Lección:** con cualquier librería que clona/envuelve un `<input>` (Flatpickr, Select2, etc.), evitar alternar atributos nativos (`disabled`, `readonly`) después de la inicialización — preferir gatear la interacción a nivel de un contenedor CSS.

---

## NT-012 — Docker bind mount pierde enlace cuando se elimina y recrea el directorio fuente

| Campo | Valor |
|---|---|
| **Fecha** | 2026-06-30 |
| **Severidad** | P1 — Crítico (nginx 500, app móvil inaccesible) |
| **Componente** | Contenedor `estetican_mob` — bind mount `mob_apps/operador/dist/` → `/usr/share/nginx/html/` |
| **Impacto** | nginx sirve directorio vacío → `index.html` no existe → bucle de redirección interna → HTTP 500 en toda la app |
| **Estado** | ✅ RESUELTO |

**Síntoma:**
Después de ejecutar `rm -rf dist && npm run build` para un rebuild limpio, `mov.estetican.org` devuelve HTTP 500. El log de nginx muestra:
```
rewrite or internal redirection cycle while internally redirecting to "/index.html"
```
`docker exec estetican_mob ls /usr/share/nginx/html/` devuelve vacío.

**Causa raíz:**
Docker bind mounts con propagación `rprivate` (el default en Docker Compose) almacenan una referencia al **inodo** del directorio, no a la ruta. Al ejecutar `rm -rf dist`, el directorio se elimina y su inodo desaparece. `npm run build` crea un **nuevo** directorio `dist/` con un **inodo diferente**. El contenedor `estetican_mob` sigue apuntando al inodo anterior (ya borrado), por lo que ve el directorio montado como vacío aunque en el host existan los archivos nuevos.

**Workaround:**
```bash
docker restart estetican_mob
```
El restart re-establece el bind mount apuntando al nuevo inodo de `dist/`.

**Solución definitiva:**
**Nunca usar `rm -rf dist`** en directorios que son bind mounts de contenedores Docker. Para forzar un rebuild limpio, ejecutar directamente:
```bash
npm run build
```
Vite sobreescribe los archivos existentes sin eliminar el directorio raíz, preservando el inodo.

**Lección:**
- `rm -rf <directorio>` + `mkdir <directorio>` = nuevo inodo = bind mount huérfano en todos los contenedores que montan ese directorio.
- Para invalidar caché de build: usar flags de Vite (`--force`) o borrar solo el contenido interno (`rm -rf dist/*`), nunca el directorio en sí.
- Verificar bind mount vacío: `docker exec <container> ls /ruta/montada/` inmediatamente si nginx devuelve 500.

---

## NT-011 — `spa_bookings` no tiene `branch_id`: cobros no filtrables por sucursal

| Campo | Valor |
|---|---|
| **Fecha** | 2026-06-16 |
| **Severidad** | P4 — Bajo (limitación de modelo, no bug en producción) |
| **Componente** | `CashController::movements()` — endpoint `GET /api/cash/movements` |
| **Impacto** | El balance de movimientos de caja en la app móvil muestra cobros de todas las sucursales, no solo de la sucursal del checkin activo |

**Síntoma:**
Al intentar filtrar `payments`, `cash_ledgers` y `bank_ledgers` por sucursal del operador, cualquier query que use `spa_bookings.branch_id` falla con `Unknown column 'branch_id' in 'where clause'`.

**Causa raíz:**
La tabla `spa_bookings` no tiene columna `branch_id`. La sucursal de una cita SPA se infiere indirectamente (vía el operador asignado o el recurso asignado), pero no está desnormalizada en la tabla de citas. Las tablas de pagos (`payments`, `cash_ledgers`, `bank_ledgers`) son polimórficas apuntando a `spa_bookings` o `quotes`, por lo que no tienen branch directo tampoco.

**Workaround aplicado:**
Los movimientos manuales de caja (`CashMovement`) sí se filtran por sucursal vía `cash_sessions.branch_id`. Los cobros a clientes se muestran por período sin filtro de sucursal (aceptable porque el sistema actualmente opera en una sola sucursal).

**Solución definitiva (futura):**
Si se requiere filtrado multi-sucursal de cobros, agregar columna `branch_id` a `spa_bookings` (y potencialmente a `payments`). Crear migración y actualizar los servicios que crean bookings para poblar el campo.

**Lección:**
Antes de implementar filtros por sucursal en queries sobre `payments`/`cash_ledgers`/`bank_ledgers`, verificar que la cadena `payments → payable → branch_id` existe en el esquema. El modelo actual no lo soporta sin cambios de migración.

---

## NT-020 — `ExecutedService`/`ExecutedServiceItem` existen en el esquema pero están huérfanas — ningún flujo real las llena

| Campo | Valor |
|---|---|
| **Fecha** | 2026-07-07 |
| **Severidad** | P3 — Medio (feature nueva construida sobre datos inexistentes) |
| **Componente** | `App\Domain\Execution\Services\ExecutedServiceService` / tablas `executed_services`, `executed_service_items` |
| **Impacto** | La pantalla WhatsApp > Recurrencias (BL-029) se implementó leyendo de `executed_service_items` y no mostraba resultados aun después de cargar `services.recurrence_days`, pese a haber citas SPA completadas en producción |

**Síntoma:**
`App\Models\ExecutedServiceItem::count()` devuelve `0` en producción a pesar de que `spa_bookings` tiene registros con `status = 'completed'`.

**Causa raíz:**
`ExecutedServiceService::convertFromBooking()` (pensado para congelar un snapshot histórico de servicios ejecutados al completar una cita) nunca se conectó a ningún flujo real: no está bindeado en ningún `ServiceProvider`, ningún controlador la invoca, y las tres rutas reales que marcan una cita como `completed` (`SpaBookingController.php` — acción "Finalizar sesión", `Api/PaymentController.php` — cobro con `mark_completed`, `Api/BookingController.php` — update de estado desde la app móvil) solo hacen `$booking->update(['status' => 'completed'])` sin tocar `ExecutedService`. El modelo y las tablas quedaron como scaffolding de una funcionalidad que nunca se terminó de cablear.

**Dónde vive el historial real:**
`spa_bookings` (con `status = 'completed'`, usando `scheduled_at` como fecha) + `spa_booking_services` (qué servicios incluyó esa cita). Es la única fuente poblada hoy, aunque con una limitación: `spa_booking_services` refleja el set *actual* de servicios de la cita (se borra y recrea si se edita después de completada — ver `SpaBookingController::update`), por lo que un cambio posterior a la finalización podría perder el detalle histórico exacto.

**Solución aplicada:**
`RecurrenceMessageController::lastServiceDatesByPet()` se corrigió para leer de `spa_booking_services` JOIN `spa_bookings WHERE status = 'completed'` en vez de `executed_service_items`.

**Lección:**
Antes de construir cualquier feature nueva sobre `ExecutedService`/`ExecutedServiceItem`/`App\Domain\Execution`, verificar primero si tienen filas reales en producción (`ExecutedServiceItem::count()`). Si se requiere un snapshot histórico inmutable (que no cambie si se edita una cita ya completada), la solución de fondo es cablear `ExecutedServiceService::convertFromBooking()` en los tres puntos de transición a `completed` — pendiente, no resuelto en esta sesión.

## NT-021 — `Api\AgendaController`: campo `operators` ignoraba `spa_bookings.operator_id`, citas sin presupuesto aceptado desaparecían al filtrar por operador

| Campo | Valor |
|---|---|
| **Fecha** | 2026-07-07 |
| **Severidad** | P2 — Alto (citas reales invisibles al filtrar, sin error visible) |
| **Componente** | `App\Http\Controllers\Api\AgendaController::index()` — endpoint `/api/agenda` |
| **Impacto** | App móvil, pantallas Agenda Universal (`MobAgGbl`) y Agenda por operador (`MobOpAg`): al seleccionar un operador en el filtro, citas recién creadas (sin presupuesto aceptado todavía) desaparecían de la lista aunque tuvieran ese operador asignado |

**Síntoma:** usuario reporta que al filtrar por operador en `MobAgGbl` no salen todas las citas de ese operador.

**Causa raíz:** el campo `operators` que devuelve `/api/agenda` (usado por el filtro client-side en `GlobalAgenda.tsx` y `GroomerAgenda.tsx` vía `b.operators.some(o => o.id === filterOp)`) se calculaba **únicamente** a partir de las líneas del presupuesto aceptado (`quotes.items.operator`), ignorando `spa_bookings.operator_id` — la columna que sí se asigna directamente al crear la cita (`MobCitaNueva.tsx`). Si la cita todavía no tenía presupuesto aceptado, `operators` quedaba `[]` y el filtro la excluía siempre, sin importar el operador asignado.

**Solución aplicada:** `AgendaController::index()` ahora arma `operators` como la unión de (a) los operadores del presupuesto aceptado y (b) el operador asignado directamente vía `operator_id` (relación `SpaBooking::operator()`), deduplicados por id. El filtro client-side en la app móvil no cambió — solo recibía datos incompletos.

**Lección:** cuando un mismo concepto ("operador de esta cita") tiene dos fuentes posibles en el modelo de datos (columna directa vs. derivado de otra entidad relacionada), cualquier endpoint que exponga ese concepto debe unir ambas fuentes explícitamente — no asumir que una sola cubre todos los casos del ciclo de vida (una cita pasa por "sin presupuesto" antes de llegar a "con presupuesto aceptado").

## NT-022 — CSP bloqueaba silenciosamente las teselas de OpenStreetMap (mapa en blanco en `AX-MAPZN`)

| Campo | Valor |
|---|---|
| **Fecha** | 2026-07-07 |
| **Severidad** | P2 — Alto (pantalla nueva inutilizable, sin error visible en la UI) |
| **Componente** | `App\Http\Middleware\ContentSecurityPolicy.php` |
| **Impacto** | Pantalla `AX-MAPZN` (Mapa y cobertura espacial) — el mapa de Leaflet cargaba pero las teselas de OpenStreetMap nunca se veían (fondo gris/blanco permanente) |

**Síntoma:** usuario reporta "no carga el mapa" al probar `/mapa-zonas` por primera vez.

**Causa raíz:** la directiva CSP `img-src 'self' data: blob:` (ver NT-006, agregada 2026-05-25 junto con el resto de la política) solo permite imágenes del propio origen — bloquea sin aviso visible las peticiones de `<img>` que hace Leaflet hacia `https://{a,b,c}.tile.openstreetmap.org/...`. El navegador descarta la petición silenciosamente (violación de CSP, no un error de red), así que el mapa se ve simplemente vacío/gris sin ningún mensaje de error en consola visible para un usuario no técnico. De paso se detectó que `connect-src 'self'` también bloqueaba (desde que existe la CSP) el `fetch()` que ya hacía `address-editor.js` hacia `https://nominatim.openstreetmap.org` para la "Geocodificación automática" — esa función llevaba tiempo silenciosamente rota sin que nadie lo hubiera reportado.

**Solución aplicada:**
```php
"img-src 'self' data: blob: https://*.tile.openstreetmap.org",
"connect-src 'self' https://nominatim.openstreetmap.org",
```

**Lección:** cualquier integración nueva con un servicio externo (tiles, geocodificación, APIs de terceros) debe agregarse explícitamente a la CSP — no basta con que el código JS esté bien escrito, el navegador la bloquea de forma completamente silenciosa (sin excepción JS, sin respuesta HTTP fallida visible) si el dominio no está en la whitelist. Al agregar cualquier `fetch()`/`<img>`/`<script>` hacia un dominio externo nuevo, verificar primero `app/Http/Middleware/ContentSecurityPolicy.php`.

---

## NT-023 — `users.is_operator`/`operator_code` existían en producción sin migración que los creara (schema drift)

| Campo | Valor |
|---|---|
| **Fecha** | 2026-07-10 |
| **Severidad** | P2 — Alto (rompe tests y cualquier entorno nuevo/`migrate:fresh`, sin afectar producción ya migrada) |
| **Componente** | `app/Models/User.php`, tabla `users` |
| **Impacto** | Base de datos `testing` (usada por PHPUnit) y cualquier instalación nueva — `INSERT`/`Schema` fallan con `Unknown column 'is_operator'` |

**Síntoma:** al escribir tests para el endpoint `/api/team` (BL-033), crear un `User` con `is_operator`/`operator_id` fallaba con `SQLSTATE[42S22]: Column not found: 1054 Unknown column 'is_operator'` contra la base `testing`, pese a que el modelo `User` las declara en `$fillable` y se usan activamente en producción (`Api\CheckinController`, filtros de operador).

**Causa raíz:** la base de datos de producción (`estetican`) sí tiene las columnas `is_operator` (`tinyint(1) not null default 0`) y `operator_code` (`varchar(255) unique nullable`), pero **ninguna migración del repositorio las crea** — `grep -rl "is_operator" database/migrations/` no devuelve nada. Se agregaron en algún momento directo contra producción (probablemente vía `Schema::` ad-hoc en `tinker`, ver patrón de riesgo ya documentado en la sesión de BITACORA del 07/07/2026 sobre `tinker` sin transacción explícita) sin dejar el migration file correspondiente commiteado. La base `testing` nunca las tuvo porque solo corre las migraciones versionadas.

**Solución aplicada:** migración nueva `2026_07_10_000001_add_is_operator_and_operator_code_to_users_table.php`, idempotente (`Schema::hasColumn` guard, mismo patrón que `2026_03_28_000001_add_operator_fields_to_users_table.php`) — no-op confirmado en producción (`php artisan migrate --force` no alteró nada), aplicó limpio en `testing`.

**Lección:** cualquier cambio de esquema debe pasar por una migración commiteada, nunca por `Schema::`/`DB::statement` suelto en `tinker` contra producción — si se hace por urgencia, hay que escribir el migration file (con guard `hasColumn`) en la misma sesión, o el drift queda invisible hasta que algo (como un test nuevo) lo destapa. Si un test falla con `Unknown column` en un campo que sí existe en producción, sospechar de esto antes que de un bug de test.

---

## NT-024 — Test de `TeamPanelTest` fallaba solo después de las ~22:00 (medianoche cruzada en fixtures con `now()->addHours()`)

| Campo | Valor |
|---|---|
| **Fecha** | 2026-07-10 |
| **Severidad** | P3 — Bajo (solo afecta la suite de tests, no producción) |
| **Componente** | `tests/Feature/Api/TeamPanelTest.php` |
| **Impacto** | Falla intermitente de un test según la hora real del día en que corre la suite — no relacionado a ningún cambio de código real |

**Síntoma:** la suite completa pasó de 37 a 38 fallidas entre dos corridas de la misma sesión, sin ningún cambio de código backend entre medio. El test que empezó a fallar (`operator with active checkin and open work order reports current job`) esperaba `pending_today = 2`, recibió `1`.

**Causa raíz:** el fixture del test crea una `SpaBooking` con `scheduled_at => now()->addHours(2)` para simular "una cita de hoy más tarde". La suite corrió pasadas las 22:00 (hora del contenedor) — `now()->addHours(2)` cruzó medianoche y cayó en el día siguiente, quedando fuera del rango `whereBetween($today, $tomorrow)` que usa `Api\OperatorController::team()` para calcular "hoy". El código de producción está bien; el test es el que no contempló que un offset relativo puede cruzar el límite del día según la hora real de ejecución.

**Solución aplicada:** `$this->travelTo(now()->setTime(12, 0))` al inicio del test — ancla el reloj a mediodía antes de crear los fixtures, así ningún `now()->addHours()`/`subHours()` dentro del rango usado por el test cruza medianoche sin importar a qué hora real corra la suite.

**Lección:** cualquier test que use `now()->addHours(N)`/`subHours(N)` para fixtures de "hoy" es potencialmente frágil según la hora de ejecución — anclar el reloj con `$this->travelTo()`/`Carbon::setTestNow()` a una hora segura (ej. mediodía) al inicio del test, no confiar en la hora real del entorno. Si una suite que antes pasaba limpio empieza a fallar sin cambios de código, revisar primero si el test depende de la hora del día antes de sospechar una regresión real.

---

## NT-025 — Colores ilegibles en `LockScreen` en Android Chrome con tema oscuro — falta `color-scheme` (probable "Auto Dark Theme" de Chrome)

| Campo | Valor |
|---|---|
| **Fecha** | 2026-07-11 |
| **Severidad** | P2 — Alto (pantalla nueva difícil/imposible de usar en el navegador más común, Android Chrome) |
| **Componente** | `mob_apps/operador/index.html`, `src/index.css` |
| **Impacto** | Cualquier pantalla de la app móvil, no solo `LockScreen` — pero se detectó ahí primero |

**Síntoma:** usuario reportó que en `LockScreen` (Android, Chrome, tema oscuro) el campo de contraseña se veía como "un cuadro vacío" y el botón "Desbloquear" solo se alcanzaba a leer "Des..." sin poder distinguir el resto del texto.

**Diagnóstico (sin poder ver la pantalla — no hay herramienta de automatización de navegador ni acceso al dispositivo en este entorno):** se verificó primero que el build servido coincidía con el último compilado (no era caché vieja) y se inspeccionó el CSS generado — las variables de color de `.theme-admin`/`:root.dark .theme-admin` y las clases `bg-primary`/`text-on-primary`/`z-[60]` estaban bien compiladas, con buen contraste en ambos temas. Con el código descartado como causa directa, y confirmado Android + Chrome + tema oscuro activo, la sospecha más fuerte es **"Auto Dark Theme" de Chrome para Android** — una función del navegador (no relacionada a `prefers-color-scheme`) que reinvierte heurísticamente los colores de páginas que no declaran explícitamente que ya manejan su propio modo oscuro. Sin la declaración `color-scheme`, Chrome puede "corregir" colores que ya son correctos, produciendo combinaciones rotas (texto casi invisible sobre su propio fondo) — coincide con los síntomas reportados. El proyecto nunca declaró `color-scheme` en ningún lado.

**Solución aplicada:** `<meta name="color-scheme" content="light dark">` en `index.html` + `:root { color-scheme: light; } :root.dark { color-scheme: dark; }` en `index.css`, sincronizado con la misma clase `.dark` que ya controla la paleta (ver BL-034/BL-038). Esto le dice al navegador explícitamente que la página ya maneja ambos temas — no debería seguir "corrigiendo" colores por su cuenta.

**Sin confirmar:** es un diagnóstico basado en evidencia indirecta (código descartado + patrón de síntomas + navegador/SO reportados), no una reproducción directa — pendiente que el usuario confirme si el fix resuelve el problema real en su teléfono.

**Lección:** cualquier app web con modo oscuro propio (paletas vía clase CSS, no solo `prefers-color-scheme`) debe declarar `color-scheme` explícitamente — sin eso, Chrome para Android (y en menor medida otros navegadores con "forzar oscuro") puede reinterpretar y romper colores que el código ya calculó correctamente. Si un bug de color reportado por un usuario no aparece en el CSS/código fuente, sospechar de un mecanismo del navegador antes que de la lógica de la app.

---

## NT-026 — Utilidades `max-w-xs`/`max-w-sm` de Tailwind resolvían a 4px/8px por colisión con tokens custom `--spacing-*`

| Campo | Valor |
|---|---|
| **Fecha** | 2026-07-12 |
| **Severidad** | P3 — Medio (rompe layout visualmente, sin afectar datos ni funcionalidad) |
| **Componente** | `mob_apps/operador/src/index.css` (tokens `@theme`), cualquier uso de `max-w-xs`/`max-w-sm`/`max-w-md`/`max-w-lg`/`max-w-xl` en el proyecto |
| **Impacto** | `LockScreen.tsx` — campo de contraseña y botón "Desbloquear" se veían diminutos (más chicos que el texto "Cerrar Sesión") |

**Síntoma:** el usuario reportó el campo de contraseña y el botón "Desbloquear" de `LockScreen` como demasiado chicos y no centrados, incluso después de dos rondas de ajuste de clases Tailwind (`w-full`, `flex items-center justify-center`, `max-w-xs`→`max-w-sm`) y de confirmar que el build nuevo sí estaba desplegado (mismo hash de asset en el contenedor `estetican_mob` y en lo que respondía `mov.estetican.org` en vivo).

**Causa raíz:** `index.css` define en el bloque `@theme` tokens custom de espaciado con nombres cortos (`--spacing-xs: 4px`, `--spacing-sm: 8px`, `--spacing-md: 16px`, etc., pensados para utilidades como `gap-md`/`p-lg`). En Tailwind v4, `w-*`, `max-w-*`, `min-w-*`, `p-*`, `m-*`, `gap-*` etc. comparten el mismo namespace de resolución de la escala de espaciado por nombre de clave — al existir una clave custom `sm`/`xs` en `--spacing-*`, esa definición **shadowea** la escala por defecto que normalmente usan `max-w-sm`/`max-w-xs` (que en Tailwind stock son ~384px/320px). Se confirmó inspeccionando el CSS compilado: `.max-w-sm{max-width:var(--spacing-sm)}` → 8px real, no 384px.

**Solución aplicada:** en `LockScreen.tsx`, reemplazar `max-w-xs`/`max-w-sm` por valores arbitrarios explícitos que no pasan por el lookup de tema — `max-w-[24rem]` (384px) y `max-w-[20rem]` (320px) — que no colisionan con ningún token custom.

**Sin resolver a nivel de proyecto:** el resto del código de `mob_apps/operador` no usa `max-w-{xs,sm,md,lg,xl}` (verificado por grep), así que no hay otras instancias rotas hoy — pero la colisión sigue latente para cualquier uso futuro de esas clases mientras existan los tokens `--spacing-xs`/`--spacing-sm`/etc. en `@theme`. No se renombraron los tokens custom en esta sesión (hubiera sido un cambio más amplio, fuera del alcance del bug reportado).

**Lección:** en Tailwind v4, agregar tokens custom en `@theme` con nombres cortos genéricos (`xs`, `sm`, `md`, `lg`, `xl`) es peligroso porque esos nombres son compartidos por **todas** las utilidades de la familia de espaciado (ancho, alto, padding, margin, gap), no solo la utilidad para la que se pensaron. Si el layout de un componente se ve roto de forma que no tiene sentido con las clases escritas (por ejemplo, un `max-w-sm` que se ve más chico que el contenido sin restricción), inspeccionar el CSS **compilado** de esa clase específica antes de seguir ajustando clases a ciegas — el bug puede estar en la resolución del token, no en la clase usada.

---

## NT-027 — El candado de sesión (`AppLockContext`) se perdía en cada reload por una carrera con la carga asíncrona de `useAuth()`

| Campo | Valor |
|---|---|
| **Fecha** | 2026-07-12 |
| **Severidad** | P1 — Crítico (bypassea por completo el candado de sesión, BL-038) |
| **Componente** | `mob_apps/operador/src/AppLockContext.tsx`, `src/AuthContext.tsx` |
| **Impacto** | Cualquier reload de página, o "atrás" del navegador cuando implicaba una navegación real, mostraba la app desbloqueada sin importar que estuviera bloqueada manual o automáticamente |

**Síntoma:** el usuario reportó que bloquear la sesión (manual o por timeout) no servía de nada si después recargaba la página o usaba "atrás" — volvía directo a la pantalla desbloqueada.

**Causa raíz:** un primer intento de fix persistió `locked` en `localStorage` y lo leía en un lazy initializer de `useState` condicionado a `enabled` (`!!user`). Pero `useAuth()` resuelve la sesión de forma asíncrona (`fetch('/api/me')` tras leer el token de `localStorage`, ver `AuthContext.tsx:59-76`) — en el primer render de cada reload, `user` todavía es `null`, así que `enabled` es `false` y el initializer devuelve `false` sin importar lo persistido. Peor: el `useEffect` que reacciona a `enabled` trataba "todavía no sé si hay sesión" (`loading = true`) igual que "confirmado que no hay sesión" (`loading = false, user = null`) — en ambos casos entraba a la misma rama, que llamaba `clearStoredState()`, **borrando** el estado de bloqueo guardado antes de que hubiera oportunidad de usarlo cuando `enabled` pasara a `true` más tarde.

**Solución aplicada:** se expone `loading` desde `useAuth()` y se usa para no tocar nada (ni el estado en memoria ni lo persistido) mientras la sesión todavía se está resolviendo. Una vez que `loading` es `false`, si hay sesión (`enabled`) se re-sincroniza `locked` explícitamente desde `localStorage` (no se confía en el valor del lazy initializer, que quedó fijado en el primer render); si no hay sesión, recién ahí se limpia el estado persistido.

**Lección:** un lazy initializer de `useState` solo corre **una vez**, en el primer render — si depende de un valor que llega de forma asíncrona (como el usuario autenticado), va a quedar con un valor obsoleto para siempre a menos que algo lo vuelva a calcular explícitamente cuando esa dependencia async se resuelve. Cualquier estado derivado de `useAuth()` en este proyecto debe distinguir `loading` de "confirmado sin sesión" — son casos distintos, no el mismo `else`.

---

## NT-028 — Escribir en el campo de contraseña de `LockScreen` marcaba la sesión como desbloqueada en el storage persistido, sin haber terminado de desbloquear

| Campo | Valor |
|---|---|
| **Fecha** | 2026-07-12 |
| **Severidad** | P2 — Alto (bypass parcial del candado, BL-038, encontrado durante la verificación de NT-027) |
| **Componente** | `mob_apps/operador/src/AppLockContext.tsx` |
| **Impacto** | Recargar la página a mitad de escribir la contraseña en `LockScreen` (sin llegar a tocar "Desbloquear") mostraba la app desbloqueada en el siguiente reload |

**Síntoma:** encontrado mientras se verificaba el fix de NT-027 — bloquear, empezar a escribir la contraseña sin terminar, y recargar, mostraba la app sin candado.

**Causa raíz:** los listeners de actividad (`touchstart`, `mousedown`, `keydown`, `scroll`) están registrados en `document` con burbujeo, así que también se disparan por los toques/tecleo **dentro de la propia pantalla de bloqueo** (escribir en el campo de contraseña, tocar el botón "Desbloquear"). Cada uno de esos eventos llama `resetTimer()`, que escribía `{ locked: false, ... }` en `localStorage` de forma incondicional (con throttle de 5s) — sin verificar si en ese momento la app seguía realmente bloqueada.

**Solución aplicada:** se agregó `lockedRef` (un `useRef` sincronizado con el estado `locked` real) y `resetTimer()` ahora omite el guardado en `localStorage` si `lockedRef.current` es `true`. La programación del timeout de auto-bloqueo de 5 min no se ve afectada, solo el guardado indebido del flag `locked: false`.

**Lección:** cuando una pantalla de overlay/candado convive con listeners globales de "actividad reciente" en `document`, esos listeners no distinguen por defecto si el toque/tecla ocurrió dentro del overlay o en la app real debajo — hay que guardar el estado "verdadero" actual en algo legible de forma síncrona (un ref, no el estado de React que se actualiza async) para poder filtrar esos casos dentro del mismo handler.

---

## NT-030 — Botón "Probar Conexión" SMTP daba 404 por el spoofing de método (`_method=PUT`) del formulario que lo contenía

| Campo | Valor |
|---|---|
| **Fecha** | 2026-07-12 |
| **Severidad** | P2 — Alto (imposible probar la configuración SMTP desde la UI) |
| **Componente** | `resources/views/system-settings/index.blade.php` |
| **Impacto** | Solo el botón de prueba SMTP — el guardado normal de la sección no se ve afectado |

**Síntoma:** el usuario reportó `404 Not Found` en `POST https://app.estetican.org/system-settings/smtp-test` al usar el botón "Probar Conexión" en Configuración del sistema → Servicio de Correo.

**Causa raíz:** el formulario de cada sección de settings tiene `method="POST"` + `@method('PUT')` (un input oculto `_method=PUT`, la forma estándar de Laravel de simular verbos HTTP no soportados nativamente por los formularios HTML) para el submit normal hacia `system-settings.update`. El JS del botón de prueba (`data-test-smtp`) solo cambiaba `form.action` a la ruta de prueba y llamaba `form.submit()` — sin tocar el campo `_method`. Laravel interpreta cualquier POST con `_method=PUT` como un PUT real, así que la petición terminaba enrutada a la ruta `PUT /system-settings/{section}` (que coincide con el patrón `/system-settings/smtp-test`, tomando `smtp-test` como valor de `{section}`) en vez de a la ruta real `POST /system-settings/smtp-test`. Dentro de `SystemSettingController::update()`, `abort_unless($systemSettings->hasSection('smtp-test'), 404)` — como `smtp-test` no es una sección real, tronaba 404. La ruta de prueba en sí nunca se ejecutaba.

**Solución aplicada:** antes de enviar el formulario hacia la ruta de prueba, se deshabilita (`disabled = true`) el input `_method` — los campos deshabilitados no se incluyen en el envío del formulario, así que el POST llega sin spoofing y Laravel lo enruta correctamente a `testSmtp()`. Se rehabilita después por si el envío no navega fuera de la página.

**Lección:** cualquier botón que reutilice un `<form>` existente para pegarle a una ruta *distinta* de la que ese formulario fue armado para servir debe revisar **todos** los campos ocultos del formulario (no solo `action`) — un `_method` de spoofing es fácil de pasar por alto porque no aparece en ningún lado visible de la UI, y cambia silenciosamente a qué ruta llega la petición real.

**Adenda (mismo día) — el campo "Encriptación" ofrecía valores que Symfony Mailer rechaza:** al arreglar el 404 de arriba, el usuario probó el envío real y encontró un segundo bug en la misma área: `UnsupportedSchemeException: The "ssl" scheme is not supported; supported schemes for mailer "smtp" are: "smtp", "smtps"`. El campo `mail_encryption` (agregado en la misma sesión, ver la sección de correo por plantilla) ofrecía `ssl`/`tls`/`''` como opciones — pero el transporte SMTP de Symfony Mailer (el que usa Laravel por debajo) solo reconoce literalmente `smtp` o `smtps` como valor de `scheme`, no `ssl`/`tls`. El `.env` de producción también traía `MAIL_SCHEME=ssl` desde antes, heredado y nunca antes ejercitado con éxito. Corregido: opciones del campo reducidas a `smtp`/`smtps`, con `smtps` (TLS implícito, puerto 465) como default cuando el valor existente no es válido.

**Adenda 2 — el valor inválido ya guardado en BD no se autocorregía:** el usuario ya había guardado `ssl` en un intento anterior (antes del fix de opciones) — `SystemSettings::castValue()` no validaba un valor `select` leído de la BD contra las opciones vigentes del campo, así que seguía devolviendo `ssl` indefinidamente aunque esa opción ya no existiera. Se agregó un resguardo genérico: si el valor guardado de un campo `select` no está entre sus `options` actuales, se usa el `default` en su lugar — protege a **cualquier** campo `select` del proyecto ante este mismo patrón (opciones que cambian de versión en versión), no solo a este.

**Adenda 3 — verificar vía `tinker` con `Mail::raw()` directo da falso negativo:** al confirmar el fix, un primer intento de enviar un correo de prueba vía `php artisan tinker` volvió a fallar con el mismo error de scheme — pero **no** era el mismo bug: los comandos de consola (`artisan`, `tinker`) no pasan por el pipeline de middleware HTTP, así que `ApplySystemSettings` (que aplica `SystemSettings::configOverrides()` a la config de Laravel) nunca corre — la config de mail en ese contexto refleja solo `.env`, no lo guardado en `system_settings`. El envío real desde el navegador (Bandeja Diaria, Recurrencias, resumen de servicio) sí pasa por ese middleware y sí ve la config correcta; confirmado aplicando `config(app(SystemSettings::class)->configOverrides())` manualmente antes de reintentar en tinker. **Implicación a futuro:** cualquier envío de correo disparado desde un comando artisan, job en cola, o cron (nada de esto existe todavía) necesitaría aplicar el bridge manualmente — no puede asumir que la config de mail ya está lista solo por leer `SystemSettings`.

**Lección (adendas):** (1) cuando se agregan opciones a un campo `select` de configuración, verificar los valores exactos que espera la librería subyacente (no asumir nombres "obvios" como `ssl`/`tls`) — la fuente de verdad es el mensaje de error de la librería, no la intuición; (2) cualquier sistema de configuración basado en `select` con opciones versionables necesita un resguardo contra valores obsoletos guardados antes del cambio; (3) verificar código dependiente de middleware HTTP (como bridges de configuración) usando una request real o aplicando el middleware manualmente — `tinker`/`artisan` no son un sustituto fiel del pipeline de request real.

---

## NT-031 — CORS dinámico (BL-042): un middleware de ruta nunca ve el preflight `OPTIONS`, y el `HandleCors` por defecto de Laravel pisa cualquier middleware propio si se registra después

| Campo | Valor |
|---|---|
| **Fecha** | 2026-07-13 |
| **Severidad** | P3 — solo afectaba al widget de IA nuevo (BL-042), en desarrollo, nunca llegó a producción con el bug |
| **Componente** | `app/Http/Middleware/HandleAssistantCors.php`, `bootstrap/app.php` |
| **Impacto** | Ninguno en producción — encontrado y corregido durante el desarrollo, antes de exponer el endpoint al sitio real |

**Contexto:** el widget de IA para el sitio WordPress necesita CORS restringido a un origen configurable desde `SystemSettings` (BD), no a un dominio fijo — así que no se podía usar `config/cors.php` tal cual (ver más abajo por qué). Se optó por un middleware propio, `HandleAssistantCors`, que lee `ai_assistant_allowed_origin` en cada request.

**Primer intento (fallido) — middleware de ruta:** se registró como middleware de las rutas `/api/assistant/*` (`Route::middleware([...])`). Un test de preflight (`OPTIONS /api/assistant/chat`) nunca lo ejecutaba. Causa raíz: `Route::get`/`Route::post` solo registran los métodos indicados; Laravel resuelve el método de la petición **antes** de correr el middleware de la ruta (la resolución de rutas ocurre dentro del despacho al router, que es interno a la cadena de middleware globales, no al revés) — como no había ninguna ruta `OPTIONS` registrada para esa URI, el framework nunca llegaba a instanciar el middleware de esa ruta.

**Fix:** mover `HandleAssistantCors` a middleware **global** (`$middleware->append()`/`prepend()` en `bootstrap/app.php`, igual que `ContentSecurityPolicy`/`ApplySystemSettings`/`ProfileBackofficeRequests` ya existentes) — el middleware global envuelve toda la petición, incluida la resolución de rutas, así que sí ve el `OPTIONS` antes de que el router lo rechace. Acotado por path a mano dentro del propio middleware (`$request->is('api/assistant/*')`) para no afectar el resto de la app.

**Segundo bug, tras el fix anterior — el header salía como `*` en vez del origen configurado:** con el middleware ya global vía `append()`, el test seguía fallando, pero con `Access-Control-Allow-Origin: *` en vez de ausente. Causa: Laravel trae su propio `Illuminate\Http\Middleware\HandleCors` activo por defecto en el stack global del framework (con `config/cors.php` no publicado, usa el default de fábrica — `allowed_origins => ['*']`), y ese middleware corre **antes** que cualquiera agregado con `append()` (que se suman al final de la lista de middleware globales ya existentes, incluidos los de fábrica). El `HandleCors` de Laravel interceptaba el `OPTIONS` primero y respondía con su propio `*`, sin que `HandleAssistantCors` llegara a ejecutarse.

**Fix final:** usar `$middleware->prepend(HandleAssistantCors::class)` en vez de `append()` — así corre primero, intercepta el `OPTIONS` de las rutas `/api/assistant/*` y responde antes de que el `HandleCors` de fábrica tenga oportunidad.

**Por qué no `config/cors.php`:** los archivos de config de Laravel se cargan durante el bootstrap (`LoadConfiguration`), que ocurre **antes** de que se registren los proveedores de servicio (incluidos los de base de datos y caché). Leer `SystemSettings` (que consulta la tabla `system_settings` vía Eloquent, con caché) dentro de un archivo de config es un patrón fràgil que puede fallar según el momento exacto en que Laravel resuelve ese archivo — por eso el origen dinámico se resolvió con un middleware (que corre después del boot completo, con toda la app ya lista) en vez de intentar inyectar el valor en `config('cors.*')`.

**Lección:** (1) el middleware de CORS que necesita interceptar preflight `OPTIONS` **debe** ser global, nunca de ruta — la ruta todavía no existe (en términos de método HTTP) cuando el preflight llega; (2) `append()` sitúa el middleware **después** de los que ya trae Laravel por defecto (incluido `HandleCors`) — si el objetivo es que el propio middleware gane la carrera para responder primero, hace falta `prepend()`; (3) cualquier valor de configuración que dependa de la base de datos (vía `SystemSettings` u otro mecanismo similar) no debe leerse desde un archivo de `config/*.php` — el momento de carga de esos archivos no garantiza que la BD/caché ya estén disponibles.

---

## NT-032 — Widget del asistente (BL-042 Fase 2) invisible en `estetican.org`: el editor de WordPress corrompe JavaScript inline vía `wpautop`

| Campo | Valor |
|---|---|
| **Fecha** | 14/07/2026 |
| **Severidad** | P2 — Alto (el widget completo no funcionaba, sin ningún error visible para el usuario) |
| **Componente** | Contenido del sitio WordPress (`estetican.org`, fuera de este repo) — no es un bug de `apps/backoffice-laravel` |
| **Impacto** | Solo el widget del asistente; el resto del sitio no se vio afectado |

**Síntoma:** tras pegar el HTML/CSS/JS del widget en los tres bloques separados del editor de WordPress y publicar, el botón flotante del asistente nunca aparecía — sin ningún error visible para el usuario final.

**Diagnóstico:** se descargó el HTML real servido por `https://estetican.org/` (`curl`) para inspeccionar qué había llegado realmente a producción, en vez de asumir. Se encontró que el contenido del bloque JS llegaba envuelto así:
```html
<p><script data-wp-block-html="js">
(function () { ... })();</p>
<p></script></p>
```
El editor de WordPress usado en este sitio le aplica `wpautop()` (el filtro que WordPress usa para convertir saltos de línea en párrafos `<p>`, pensado para contenido de blog, no para código) al contenido **crudo** de cada campo **antes** de envolverlo en su etiqueta (`<script>`/`<style>`) — no después. El `wpautop()` nativo de WordPress sí protege el contenido dentro de `<script>`/`<style>` ya existente (lo reemplaza por un placeholder antes de fragmentar en párrafos, y lo restaura después) — pero esa protección no aplica acá porque las etiquetas `<script>`/`<style>` **todavía no existen** en el momento en que se ejecuta el filtro sobre el campo individual.

**Primer intento (insuficiente):** eliminar todas las líneas en blanco del HTML/CSS/JS (`wpautop` inserta `<p>`/`</p>` en cada salto de línea doble). Esto arregló la corrupción **interna** (antes partía funciones a la mitad), pero `wpautop` de todas formas envuelve **cualquier bloque de texto sin líneas en blanco** en un único `<p>` al principio y `</p>` al final — porque su algoritmo siempre trata contenido "suelto" (que no empieza con una etiqueta de bloque reconocida) como un párrafo, tenga o no separadores internos. El resultado: `<p>(function () {` al inicio y `})();</p>` al final — el `</p>` quedaba **dentro** del texto del `<script>` (el parser HTML no corta el contenido de `<script>` hasta encontrar `</script>` literal), y el motor de JS tronaba con `SyntaxError: Unexpected token '<'` al toparse con esos caracteres.

**Fix definitivo:** sacar toda la lógica del campo JS del editor por completo. En su lugar, el bloque HTML incluye una única etiqueta `<script src="https://app.estetican.org/assistant-widget-wp.js" data-api-base="..." data-token="..." async></script>` **sin ningún texto entre la apertura y el cierre** — toda la configuración va en atributos `data-*`, no en el cuerpo. Como `wpautop` solo puede corromper *contenido de texto*, una etiqueta vacía con atributos es inmune sin importar cuántos `<p>` sueltos le agregue alrededor (son inofensivos fuera del cuerpo del script). La lógica real vive en `apps/backoffice-laravel/public/assistant-widget-wp.js` — un asset estático servido directo por Laravel, nunca pasa por el editor de WordPress ni por ningún filtro de contenido. El bloque JS del editor queda vacío.

**Lección:** (1) cuando un comportamiento en producción no tiene explicación obvia, bajar el HTML real servido (`curl`) y comparar contra lo que se pegó — no asumir que "se guardó tal cual"; los CMS con editores de contenido casi siempre tienen algún filtro de formato activo que puede alterar código pegado como si fuera texto; (2) quitar líneas en blanco reduce pero **no elimina** el riesgo de un filtro tipo `wpautop` — el único blindaje real contra corrupción de contenido de texto es no depender de contenido de texto: usar atributos de etiqueta (`data-*`, `src`) en vez de cuerpo inline cuando el canal de entrega no es 100% confiable; (3) mismo patrón que NT-031 en el fondo — cuando una plataforma externa (WordPress, Meta, lo que sea) no da garantías sobre cómo transporta contenido, diseñar para que ese contenido sea trivial (vacío o solo atributos) en vez de intentar que sobreviva intacto.

## NT-033 — `ClinicalVisit::sign()` no firmaba nada, sin ningún error visible (BL-046)

| Campo | Valor |
|---|---|
| **Fecha** | 14/07/2026 |
| **Severidad** | P1 — el flujo de firma completo era inoperante, sin ninguna excepción ni mensaje de error |
| **Componente** | `app/Models/ClinicalVisit.php` (`#[Fillable]`), `app/Domain/Clinical/Services/ClinicalVisitService.php` |
| **Impacto** | Solo el módulo clínico (Fase 1, aún sin activar en producción) — detectado en pruebas antes de dar por cerrada la tarea |

**Síntoma:** `ClinicalVisitService::sign()` corría sin lanzar ninguna excepción (las 4 validaciones de permiso/rol/cédula pasaban correctamente), pero al releer la visita de la BD seguía en `status = 'draft'`, sin `signed_at` ni `professional_license_snapshot`.

**Diagnóstico:** `$visit->update(['status' => 'signed', 'signed_by_operator_id' => ..., 'signed_at' => ..., 'professional_license_snapshot' => ...])` — ninguno de esos 4 campos estaba en el `#[Fillable([...])]` del modelo. Eloquent, por defecto, **no lanza excepción** cuando `update()`/`create()` recibe claves fuera de `$fillable` — simplemente las descarta en silencio de la asignación masiva. El resto de la lógica (guardas, permisos) funcionaba perfecto; el bug estaba en que la escritura final nunca llegaba a tocar esas columnas.

**Fix:** agregar `status`, `signed_by_operator_id`, `signed_at`, `professional_license_snapshot` al `#[Fillable]` de `ClinicalVisit`. El control de acceso real sigue viviendo en `ClinicalVisitService` (único punto de entrada de escritura para el dominio) — el fillable no reintroduce riesgo porque ningún controller llama `update()` con input crudo del usuario sobre estos campos.

**Lección:** cuando un `update()`/`create()` con claves fuera de `$fillable` no lanza error visible, el síntoma es exactamente este — "corrió sin fallar, pero no cambió nada". Al depurar un guardado que "no hace nada" sin excepción, **lo primero** a revisar es si todas las claves del array pasado están en `$fillable`/`#[Fillable]`, antes de sospechar de lógica de negocio más compleja. Detectado en este caso ejecutando el flujo completo dentro de una transacción de prueba revertida contra datos reales de producción (`DB::transaction` + `throw` intencional al final) — sin ese tipo de prueba end-to-end, este bug hubiera llegado a producción intacto pese a que la suite de tests unitarios (que sí usaba `$fillable` correctamente en sus propios asserts) no lo detectó hasta que se escribió expresamente para probar la firma.

## NT-034 — Bandera de bypass de guardia (`allowLockedStatusTransition`) quedaba pegada en `true` tras usarse una vez (BL-046)

| Campo | Valor |
|---|---|
| **Fecha** | 14/07/2026 |
| **Severidad** | P2 — solo relevante si el mismo objeto PHP se reutiliza tras una enmienda dentro del mismo request (no reproducible cruzando requests HTTP reales) |
| **Componente** | `app/Domain/Clinical/Services/ClinicalVisitService.php::createAmendment()` |

**Síntoma:** tras crear una nota aclaratoria sobre una visita firmada (`$original->status = 'amended'` vía bypass del guard de inmutabilidad), el mismo objeto `$original` en memoria seguía aceptando ediciones adicionales sin lanzar `ClinicalVisitLockedException`, pese a que el guard del modelo revisa `getOriginal('status')`.

**Diagnóstico:** `allowLockedStatusTransition` es una propiedad pública en memoria (no una columna de BD), usada como bandera de un solo uso para permitir la transición `signed → amended` sin disparar el guard de `saving`. Se seteaba a `true` antes de guardar, pero **nunca se regresaba a `false`** después — el objeto PHP (no la fila de BD) quedaba permanentemente desbloqueado mientras esa instancia siguiera viva. `refresh()` no resetea propiedades públicas que no son atributos de Eloquent, así que ni siquiera releer el modelo lo corregía.

**Fix:** `$original->allowLockedStatusTransition = false;` inmediatamente después de `$original->save()` dentro de `createAmendment()`.

**Lección:** cualquier bandera de bypass de un guard debe resetearse explícitamente justo después de la operación que la necesitó, en el mismo método — nunca asumir que un `refresh()`/`fresh()` posterior la va a limpiar, porque esos métodos solo tocan atributos respaldados por columnas de BD, no propiedades públicas normales de PHP.

## NT-035 — `Route::resource(...)->middleware([...])` con array asociativo por acción no hace lo que parece (usar `middlewareFor()`)

| Campo | Valor |
|---|---|
| **Fecha** | 14/07/2026 |
| **Severidad** | P2 — no rompe en runtime de forma obvia, pero deja rutas sin la protección de permiso esperada (o con protección incorrecta en todas las acciones) |
| **Componente** | `routes/web.php` — `Route::resource('items', ItemController::class)` (BL-050, movido de Catálogos fuera del módulo clínico) |

**Síntoma:** al querer que cada acción del resource `items` (`index`, `create`/`store`, `edit`/`update`, `destroy`) exigiera un permiso Spatie distinto, la forma intuitiva fue pasar un array asociativo a `->middleware([...])`:
```php
Route::resource('items', ItemController::class)->middleware([
    'index' => 'permission:ver catalogo_articulos',
    'store' => 'permission:crear catalogo_articulos',
    // ...
]);
```
Esto **no lanza ningún error** al bootear ni al correr `route:list` en versiones donde `PendingResourceRegistration::middleware()` castea cada valor del array a string y lo aplica **tal cual, a las 7 acciones por igual** — es decir, terminaría intentando usar literales como `'index'` (la propia clave) como si fueran nombres de middleware, o en el mejor de los casos aplicando el mismo middleware a todas las rutas sin distinción por acción.

**Diagnóstico:** `Route::resource()` sí soporta middleware por acción, pero con un método **separado**: `->middlewareFor($methods, $middleware)` (Laravel 11+). `->middleware()` a secas siempre aplica de forma uniforme a todas las acciones del resource.

**Fix:**
```php
Route::resource('items', ItemController::class)->except(['show'])
    ->middlewareFor('index', 'permission:ver catalogo_articulos')
    ->middlewareFor(['create', 'store'], 'permission:crear catalogo_articulos')
    ->middlewareFor(['edit', 'update'], 'permission:editar catalogo_articulos')
    ->middlewareFor('destroy', 'permission:eliminar catalogo_articulos');
```
Verificado con `php artisan route:list --name=items.store -vv` — el middleware `permission:crear catalogo_articulos` aparece solo en esa ruta, no en las demás.

**Lección:** cuando un resource necesite permisos distintos por acción, usar `middlewareFor()` explícitamente y **siempre verificar con `route:list -vv`** cuál middleware quedó aplicado a cada ruta — este tipo de error de configuración no se manifiesta como excepción, sino como control de acceso silenciosamente mal aplicado (el peor tipo de bug de permisos: no truena, solo dice qué no debería).

## NT-036 — Quitar una columna de `$fillable` no basta si la columna es `NOT NULL` sin default (BL-045b)

| Campo | Valor |
|---|---|
| **Fecha** | 14/07/2026 |
| **Severidad** | P1 — rompe cualquier alta nueva en producción (`SQLSTATE[HY000]: Field '...' doesn't have a default value`), no un fallo silencioso |
| **Componente** | `operators.full_name`, migración `2026_03_22_231000_enrich_operators_personal_data.php` |

**Síntoma:** al atomizar `operators.full_name` en `first_name`/`apellido_paterno`/`apellido_materno` (mismo patrón usado en BL-044/045 para `clients.last_name`/`users.last_name`), quitar `full_name` de `$fillable` en `Operator` hizo que la suite completa pasara de 37 a **68 tests fallando** — cualquier `Operator::create()` sin pasar `full_name` explícito tronaba con un error SQL real, detectado antes de tocar producción.

**Diagnóstico:** a diferencia de `last_name` en `clients`/`users` (que nacieron `nullable` desde su migración original), `operators.full_name` se creó `nullable()` pero luego se le aplicó `->nullable(false)->change()` en la misma migración (`2026_03_22_231000`), tras hacer un backfill de una sola pasada (`full_name = name`). Quedó `NOT NULL` sin `default` en la BD real. El patrón "accessor calculado + columna vieja fuera de `$fillable`" asume implícitamente que la columna vieja acepta `NULL` — si no lo hace, Eloquent deja de escribirla (ya no es masa-asignable) pero MySQL exige un valor en el `INSERT`, y truena.

**Fix:** migración adicional (`2026_07_14_000019_make_full_name_nullable_on_operators_table.php`) que corre `$table->string('full_name')->nullable()->change()` **antes** de dar por buena la atomización.

**Lección:** antes de asumir que "nullable + accessor + backfill" basta para atomizar un campo, correr `SHOW COLUMNS FROM {tabla}` (o revisar la migración original de la columna) para confirmar que de verdad es `NULL`-able. Si es `NOT NULL` sin default, hace falta una migración extra para relajarla — y correr la suite completa *antes* de tocar producción es lo que expone esto rápido (68 fallas de golpe vs. el baseline de 37 es una señal inequívoca de que algo estructural se rompió, no solo tests desactualizados).

## NT-037 — `CLAUDE.md` decía PHP 8.3 + SQLite en tests; producción real corre PHP 8.5 sobre MySQL

| Campo | Valor |
|---|---|
| **Fecha** | 16/07/2026 |
| **Severidad** | P3 — no rompe nada en producción, pero cualquier decisión tomada confiando en la documentación (ej. elegir una imagen Docker por versión de PHP, o intentar correr la suite contra SQLite) falla de forma confusa |
| **Componente** | `CLAUDE.md` (secciones "Architecture → Stack" y "Testing & Linting"), imagen `estetican/app:prod` |

**Síntoma:** al clonar el motor de EstetiCAN para un proyecto externo (Zeus-Estetican, sandbox de tenant "Huellitas" — ver su `docs/tecnico/NOTAS_TECNICAS.md` NT-005/NT-006) y seguir la documentación al pie de la letra ("PHP 8.3+", "tests en SQLite en memoria"), dos cosas fallaron: `composer` exigió PHP ≥8.4 real (no 8.3), y la migración `2026_03_20_000003_cleanup_phones_table` (`DROP COLUMN` sobre una columna polimórfica) truena en SQLite (`no such column` tras el drop) porque Laravel emula `DROP COLUMN` recreando la tabla completa, y esa recreación tropieza con un índice relacionado que SQLite no reconcilia solo.

**Diagnóstico:** `docker exec estetican_app php -v` confirma **PHP 8.5.6** real en producción — la imagen `estetican/app:prod` se actualizó en algún punto sin que `CLAUDE.md` se actualizara junto. Y `docker exec estetican_app grep DB_CONNECTION .env` confirma `mysql`, no sqlite — los tests corren contra una BD `testing` en el mismo servidor MySQL, nunca contra SQLite; el texto original de la doc probablemente describía una intención inicial del proyecto que quedó obsoleta apenas se empezaron a escribir migraciones con `DROP COLUMN` sobre columnas polimórficas (MySQL soporta `DROP COLUMN` nativo sin tocar índices relacionados; SQLite no).

**Fix:** `CLAUDE.md` corregido — Stack dice PHP 8.5 (con nota de por qué cambió), Testing dice MySQL explícitamente con la incompatibilidad de SQLite documentada.

**Lección:** cuando otro proyecto (o una sesión futura) necesite tratar a EstetiCAN como una "caja negra" reproducible (clonarlo, migrarlo desde cero en un entorno nuevo), es la prueba de fuego real de si la documentación de stack sigue vigente — verificar versión real (`php -v` dentro del contenedor) y motor de BD real (`.env`) antes de confiar en lo escrito, sobre todo en proyectos que llevan meses en producción y pudieron actualizarse sin que alguien tocara `CLAUDE.md` a la par.

## NT-038 — `@json()` con array multilínea anidado en Blade truena en producción con `ParseError` (500 real en Agenda, latente desde BL-055)

| Campo | Valor |
|---|---|
| **Fecha** | 16/07/2026 |
| **Severidad** | P1 — cualquier usuario que abriera el detalle de una cita (`agenda.show`) recibía 500 en cuanto Blade recompilaba la vista (el caché de vistas compiladas la estaba "tapando" por casualidad hasta que se invalidó) |
| **Componente** | `resources/views/agenda/partials/_quote_manager.blade.php` |

**Síntoma:** al agregar los tests de `StoreModuleToggleTest` (BL-058) — los primeros en toda la suite que hacen `GET` a `agenda.show` — la vista truena con `ParseError: Unclosed '[' on line 83 does not match ')'` al compilarse. No es un error de sintaxis Blade (`app('blade.compiler')->compileString(...)` no lanza excepción), es un `ParseError` de **PHP real** al hacer `require` del archivo ya compilado — es decir, Blade genera PHP inválido y no se da cuenta.

**Diagnóstico:** el directivo `@json($groups->map(fn ($g) => [...anidado multilínea...]))` (líneas 82-92 originales) le pasaba al compilador de Blade una expresión PHP que abarca varias líneas, con un `map(fn (...) => [...])` anidado dentro de otro. El extractor de parámetros de `@json` corta la expresión de forma incorrecta a la mitad (justo después de `'service_id' => $c->service_id`), generando `<?php echo json_encode($groups->map(fn ($g) => [ 'id' => $g->id, ... 'service_id' => $c->service_id) ?>,` — el resto del array (`item_id`, `name`, `price`, `quantity`, los corchetes de cierre) queda fuera de PHP y se cuela como texto plano en medio del bloque `x-data`. Nadie lo detectó en BL-055 porque la verificación manual de esa sesión no hizo un `GET` real a `agenda.show` con datos — probó otras rutas y aceptación de cotizaciones vía POST directo. El bug estuvo agazapado en producción real desde el commit de BL-055 hasta hoy.

**Fix:** la construcción del array para JS se movió del Blade al controller — `SpaBookingController::show()` arma `$groupsForQuoteManager` como una `Collection` ya lista (mismo `map()` anidado, pero en PHP puro, no dentro de un directivo Blade), y la vista queda con `@json($groupsForQuoteManager)` — una sola variable, sin anidamiento inline. Mismo patrón general: lógica de armado de datos vive en el controller, la vista solo consume variables ya resueltas.

**Lección:** `@json()` (y en general cualquier directivo Blade) con una expresión PHP multilínea que anida `fn() => [...]` dentro de otro `fn() => [...]` es zona de riesgo real — el extractor de parámetros de Blade no siempre balancea correctamente paréntesis/corchetes anidados a través de varias líneas, y el error resultante es un `ParseError` en tiempo de ejecución, no en tiempo de compilación de Blade — así que `artisan view:cache`/`compileString()` no lo detectan, solo un `GET` real a la ruta lo expone. Cualquier vista con un `@json()` de más de una línea con estructuras anidadas debe moverse a una variable ya armada en el controller, nunca construirse inline en el Blade.

## NT-039 — El KPI "Huéspedes en Hotel" y la fusión de Hotel en la Agenda nunca mostraron nada: filtraban por un `status` que no existe en el enum real (BL-059)

| Campo | Valor |
|---|---|
| **Fecha** | 16/07/2026 |
| **Severidad** | P2 — no truena nada (0 resultados es un valor válido), pero la funcionalidad lleva rota silenciosamente desde que se construyó: ningún huésped de Hotel se refleja jamás en el dashboard ni en la Agenda unificada |
| **Componente** | `DashboardController::index()`, `SpaBookingController::index()`/`buildCalendarRange()` |

**Síntoma:** al escribir un test de `HotelModuleToggleTest` (BL-059) que crea una `HotelReservation` con `status => 'active'` — el valor que la lógica de negocio en `DashboardController`/`SpaBookingController` busca (`HotelReservation::where('status', 'active')`) — el `INSERT` truena con `SQLSTATE[01000]: Data truncated for column 'status'`.

**Diagnóstico:** el enum real de la columna (`database/migrations/2026_03_18_040025_create_hotel_reservations_table.php`) solo permite `['scheduled', 'cancelled', 'fulfilled']` — **`'active'` nunca fue un valor válido**. `HotelReservationController::store()` siempre crea la reserva con `status => 'scheduled'`, y esa columna se queda así hasta que se cancela o se marca cumplida; nunca pasa a `'active'`. Como resultado, `HotelReservation::where('status', 'active')` — usado tanto en el KPI del dashboard como en las dos consultas que alimentan el timeline/calendario unificado de Agenda — **jamás ha encontrado una sola fila**, sin importar cuántos huéspedes reales hubiera hospedados. El bug es silencioso porque "0 huéspedes" es un resultado perfectamente creíble a simple vista — nadie tenía forma de notar que el número estaba estructuralmente atado a cero.

**Fix:** las tres consultas cambiaron su filtro de `where('status', 'active')` a `where('status', 'scheduled')` (el estado real que usa todo el resto del módulo — ver `HotelReservationController`, `hotel-reservations/{index,show}.blade.php`, que solo conocen `scheduled`/`cancelled`). El KPI del dashboard, que no tenía ningún filtro de fecha, se corrigió además para acotar a hoy (`whereDate('start_at', '<=', hoy)->whereDate('end_at', '>=', hoy)`), igual que ya hacían las dos consultas de Agenda — sin este acotamiento, "Huéspedes en Hotel" habría contado *todas* las reservas agendadas alguna vez, no solo las de hoy.

**Lección:** cuando un enum de estado y el código que lo consulta se escriben en momentos distintos (o por separado), un valor de filtro que "suena correcto" (`'active'` para "huésped actualmente hospedado") puede no existir nunca en los datos reales — y si el resultado de "cero coincidencias" es un valor de negocio plausible (a diferencia de un error), el bug no se manifiesta como falla visible, solo como una métrica perpetuamente en cero. Vale la pena, al construir cualquier KPI o conteo nuevo, verificar el valor real contra el enum de la migración (`grep` directo a la migración que crea la columna), no solo confiar en que el nombre del estado usado en la query "suena" correcto.

---

## NT-040 — "API access blocked" (OAuthException code 200) en `MetaCatalogSyncService` no era un bug de payload — Meta bloqueó la cuenta de developer por "actividad inusual" (BL-052b)

| Campo | Valor |
|---|---|
| **Fecha** | 20/07/2026 |
| **Severidad** | P2 — bloquea por completo el sync a Meta (0 publicados de 8), pero es un bloqueo externo temporal, no corrompe datos ni afecta el resto del sistema |
| **Componente** | `App\Domain\MetaCatalog\Services\MetaCatalogSyncService::sync()` — ninguno, el código estaba correcto |

**Síntoma:** al dar de alta 7 artículos nuevos (variantes de color de "ID TAG 38mm", ver BL-052b) y correr el sync, los 8 artículos —incluido el #10 que había publicado bien horas antes— fallaron con `"(#200) API access blocked."` (`OAuthException`, `status 400`). El Backoffice solo reporta el conteo agregado ("8 con error, ver logs"), así que el mensaje real de Meta solo aparece en `storage/logs/laravel.log` (`Log::error` en `MetaCatalogSyncService::sync()`).

**Diagnóstico:** el mensaje `code: 200` de la Graph API generalmente indica un problema de permisos/token, no de payload — pero el payload ya estaba probado y funcionando el mismo día. Investigado con Claude en Chrome directo en `business.facebook.com`/`developers.facebook.com` (no hay forma de verificar esto desde la terminal): la cuenta de developer dueña de la app "EstetiCAN Catálogo" estaba bloqueada por el sistema antiabuso de Meta ("Confirmación de la cuenta requerida" — actividad inusual detectada), redirigiendo cualquier acceso a `developers.facebook.com/r/user/error/`. Mientras ese bloqueo está activo, **cualquier** llamada de la Graph API con tokens de esa app se rechaza con `code: 200`, sin importar qué tan bien formado esté el request. Causa probable del disparo: crear 7 artículos casi idénticos y correr el sync poco después puede leerse como actividad automatizada sospechosa para el antiabuso de Meta.

**Fix:** ninguno en código. El usuario completó la confirmación de identidad manualmente en `developers.facebook.com` ("Confirmar cuenta"); el sync funcionó de inmediato después, sin regenerar el token ni tocar `MetaCatalogSyncService`.

**Lección:** cuando la Graph API de Meta devuelve `code: 200` / "API access blocked" y el payload ya se había verificado funcionando antes, no seguir depurando el código — es casi seguro un bloqueo de cuenta/app del lado de Meta. Revisar primero `developers.facebook.com` (aviso de "actividad inusual") y `Configuración empresarial → Centro de seguridad` en Business Manager antes de tocar `MetaCatalogSyncService`. Como este entorno no tiene sesión de navegador autenticada contra Meta, este tipo de diagnóstico requiere Claude en Chrome (o al usuario directamente) — no es verificable por API/terminal.

---

## NT-041 — La matriz de permisos de `Usuarios → editar` sobrescribe (`syncPermissions()`) cualquier permiso que no esté en su lista de módulos — un permiso granular asignado por fuera se autorrevoca en la siguiente edición

| Campo | Valor |
|---|---|
| **Fecha** | 20/07/2026 |
| **Severidad** | P2 — no truena nada, pero un permiso nuevo puede parecer asignado y dejar de funcionar solo por editar al usuario por cualquier otro motivo, sin que nadie lo note de inmediato |
| **Componente** | `UserController@create()`/`@edit()`/`@store()`/`@update()`, `resources/views/user/edit.blade.php` |

**Síntoma esperado si no se sabe esto:** al diseñar un permiso Spatie nuevo (ej. `disponibilidad_propia`, BL-061) y asignarlo directo a un usuario vía `$user->givePermissionTo(...)` (o vía un rol), el permiso funciona... hasta que un admin edita a ese mismo usuario desde `Usuarios → editar` por cualquier motivo ajeno (cambiar teléfono, foto, etc.) y le da "Guardar" — el permiso desaparece sin ningún error ni aviso.

**Causa raíz:** `UserController@store()`/`@update()` (líneas ~113-114 y ~216-219) hacen `$user->syncPermissions($request->permissions)` — `syncPermissions()` de Spatie **reemplaza** todos los permisos directos del usuario por exactamente lo que venga en ese array, no los agrega. El array `$request->permissions` sale de una matriz de checkboxes módulo×acción (`$modules`/`$actions` definidos en `UserController@create()`/`@edit()`, renderizada en `resources/views/user/edit.blade.php`) que **solo conoce el patrón CRUD** (`"{$action} {$module}"`, ej. `"ver operadores"`). Los permisos "granulares" fuera de ese patrón (`alergias.administrar`, `clinico.firmar`, `cobros.registrar`, etc.) **nunca aparecen como checkbox** — si un usuario los tenía asignados directo (no vía rol), la matriz los ignora al construir `$request->permissions`, y `syncPermissions()` los borra en la siguiente edición.

**Cómo se evitó en BL-061:** en vez de crear el permiso nuevo (`disponibilidad_propia`) como granular suelto, se agregó como una entrada más de `$modules` en `BaseRolesSeeder.php` **y** en los dos arrays `$modules` de `UserController` — así generó los 4 permisos estándar (`ver/crear/editar/eliminar disponibilidad_propia`) y quedó representado en la matriz de checkboxes, sobreviviendo cualquier edición futura del usuario.

**Lección:** cualquier permiso Spatie que se vaya a asignar **directo a un usuario** (no solo vía rol) debe seguir el patrón CRUD y aparecer en los arrays `$modules` de `UserController@create()`/`@edit()` — nunca asumir que `givePermissionTo()` "simplemente funciona" para un permiso fuera de esa matriz, porque la próxima edición del usuario por cualquier otro campo lo borra en silencio. Los permisos granulares (`alergias.administrar` y similares) solo son seguros si se asignan **exclusivamente vía rol** (`Role::syncPermissions()`), nunca como permiso directo de un usuario individual.
