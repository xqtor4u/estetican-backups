# Backlog — EstetiCAN
### Registro de Trabajo Pendiente

> **Uso:** Este archivo es la fuente de verdad del backlog. Actualizar en cada sesión.
> Cuando un ítem se completa, moverlo a la sección **Completados** con fecha.
> Los ítems en **Activos** son los candidatos para el próximo sprint.

---

## Activos

### Prioridad Alta — Seguridad y Estabilidad

| ID | Ítem | Tipo | Notas |
|---|---|---|---|
| BL-006 | ~~Bloquear endpoint `/up`~~ **COMPLETADO** — movido a sección Completados | Seguridad | |
| ~~BL-007~~ | ~~Verificar Transform Rules Cloudflare: X-Frame-Options, Referrer-Policy, Permissions-Policy~~ **COMPLETADO** | Seguridad | Headers en nginx de mob; backoffice ya los tenía vía middleware Laravel |
| BL-028 | Diseñar estrategia de defensa por firewall (ufw) para el servidor OPi | Seguridad | Estado actual verificado 03/07/2026: ufw y fail2ban activos/habilitados (jail sshd: maxretry=4, findtime=10min, bantime=1h); SSH permite password además de pubkey (`PasswordAuthentication yes`); puerto 22 y SMB (139/445) escuchan en `0.0.0.0` en ambas LAN; Portainer/NPM admin ya restringidos a `192.168.100.250`. Definir reglas ufw explícitas por servicio/red y evaluar desactivar auth por password en SSH |

### Prioridad Alta — App Móvil (operador)

| ID | Ítem | Tipo | Notas |
|---|---|---|---|
| ~~BL-020~~ | ~~Breadcrumbs en todas las pantallas vía ScreenHeader~~ **COMPLETADO** | Feature | |
| ~~BL-023~~ | ~~App móvil: Selector de operador + agenda individual + renombrar Groomer→Operador~~ **COMPLETADO** | Feature | screenTags MobOpPkr/MobOpAg; breadcrumb dinámico; compresión de fotos |
| BL-024b | WhatsApp Fase 2: confirmación de cliente, CRM completo, bandeja de apertura/cierre diaria con doble vía | Feature/CRM | Fase 1 completada (ver Completados). Alcance futuro: recepción de respuestas, historial conversacional |

### Prioridad Media — Funcionalidad de UI y Configuración

| ID | Ítem | Tipo | Notas |
|---|---|---|---|
| BL-001 | Tema de UI: reparar persistencia y cambio reactivo de paleta de colores | Bug/Feature | El tema se pierde al recargar o no se aplica reactivamente |
| BL-002 | Favicon & Empresa: subida de favicon + datos generales del negocio | Feature | `SystemSetting` — branding básico |
| ~~BL-003~~ | ~~Email Avanzado: credenciales SMTP (usuario/password, puertos, SSL/TLS)~~ **COMPLETADO** — cumplido por BL-040 (ver Completados) | Feature | |
| BL-004 | Zonas Horarias: reemplazar selector UTC por selector completo | Feature | Selector actual solo muestra offsets UTC, no nombres de zona |

### Prioridad Alta — Módulo Financiero (sprint actual)

| ID | Ítem | Tipo | Notas |
|---|---|---|---|
| ~~BL-022~~ | ~~App móvil: gestión de caja — sesión activa, registrar movimientos, balance de movimientos~~ **COMPLETADO** | Feature | Ver NT-011 sobre filtro sucursal en cobros |
| ~~BL-021~~ | ~~Migrar registros históricos de `cash_ledgers`/`bank_ledgers` a asientos contables~~ **COMPLETADO** | Mantenimiento | Comando `finanzas:migrar-ledgers-historicos`; idempotente; ejecutado en prod |

### Prioridad Baja — Nuevas Capacidades

| ID | Ítem | Tipo | Notas |
|---|---|---|---|
| BL-008 | Reportes PDF: diseño e impresión de presupuestos, órdenes de trabajo y facturas | Feature | Evaluar `barryvdh/laravel-dompdf` o Browsershot |
| BL-012 | App clientes (futura): autoregistro de clientes — app pública separada | Feature | No es `mob_apps/operador` |
| BL-031 | Mapa y Cobertura Espacial (`AX-MAPZN`) — entidad espacial genérica (dirección/punto) ligada muchos-a-muchos a personas/objetos/documentos | Feature | Sigue sin acotar (ver `docs/architecture/IDEAS_FUTURO.md`). La pantalla `AX-MAPZN` ya existe en versión mínima (ver BL-032 en Completados, 07/07/2026) con columnas directas simples — esta arquitectura genérica queda como evolución futura si el usuario decide que hace falta |

---

## Completados

| ID | Ítem | Sesión | Commit |
|---|---|---|---|
| — | Fix subida de fotos: cropperjs v2→v1.6.2 | 25/05/2026 | `d1e4fdd` |
| — | Fix inicialización Cropper: shown.bs.modal + rAF | 25/05/2026 | `8c9a7e5` |
| — | Documentación técnica ITIL: NOTAS_TECNICAS, image-upload-system, ESTRATEGIA | 25/05/2026 | `0a593d4` |
| — | Push a GitHub + configuración SSH (clave `estetican-opi`) | 25/05/2026 | — |
| BL-005 | Cambiar password de `admin@localhost` desde la UI | 28/05/2026 | — |
| BL-006 | Bloquear endpoint `/up` con secret key en `bootstrap/app.php` | 14/06/2026 | — |
| BL-009 | Ecosistema Móvil: app `mob_apps/operador` conectada a API real | 13/06/2026 | — |
| BL-013 | Push a GitHub + deploy mov.estetican.org en producción | 15/06/2026 | `338690a` |
| BL-020 | Breadcrumbs universales en app móvil vía ScreenHeader | 16/06/2026 | `0d6a1a7` |
| BL-015 | App móvil: historial multi-modelo (SPA + Hotel) en MobPetJobs con filtros dinámicos | 15/06/2026 | `c9f1a2d` |
| BL-010 | App móvil: foto de mascota no se mostraba — URL relativa → `Storage::disk('public')->url()` | 14/06/2026 | — |
| BL-011 | App móvil: foto en PetSearch misma raíz que BL-010 — misma corrección | 14/06/2026 | — |
| BL-014 | `booking_grace_minutes` — verificado, funciona correctamente | 14/06/2026 | — |
| BL-017 | Módulo contable: 8 tablas, 8 modelos, 3 seeders, AccountingService, `account_id` en services | 14/06/2026 | — |
| BL-018 | Backoffice: pantallas Finanzas (accounts, payment_methods, document_series, cash_registers) | 14/06/2026 | — |
| BL-020 | App móvil: cobro con métodos dinámicos desde API + Payment model + asiento contable | 14/06/2026 | — |
| BL-019 | Backoffice: apertura/corte de caja + movimientos (retiro, depósito, gasto, pérdida, entrada) con póliza doble entrada automática | 15/06/2026 | — |
| BL-022 | App móvil: gestión de caja — ver sesión activa + registrar movimientos con póliza automática | 16/06/2026 | — |
| BL-007 | Cabeceras seguridad HTTP: X-Frame-Options, Referrer-Policy, Permissions-Policy en nginx de mob | 23/06/2026 | `1769893` |
| BL-021 | Comando `finanzas:migrar-ledgers-historicos` — JE para cash_ledgers y bank_ledgers históricos | 23/06/2026 | `d9097c0` |
| BL-023 | App móvil: Groomer→Operador + breadcrumb MobOpPkr + compresión imágenes + sync users↔operators | 30/06/2026 | `9c5c050` `58e92e4` |
| BL-024 | Backoffice: recordatorios WhatsApp Fase 1 — bandeja diaria con selección por checkbox, plantillas con variables, envío manual vía wa.me (sin automatización) | 01/07/2026 | `44084ae` `e754d27` |
| — | Backoffice: cambiar dueño de cualquier mascota (modal en `pets/show.blade.php`) | 02/07/2026 | `1e2713e` |
| BL-025 | Programar servicio (AgSpaCre, web y móvil): redondeo de hora a 5 min, fix de hora manual, horario operativo configurable, operador obligatorio + validación de traslape por operador | 02/07/2026 | `9161146` `3888b3c` |
| — | App móvil: fix `loadOccupied` no expandía slots ocupados por `duration_minutes` — permitía doble-agendar al mismo operador (ver NT-018) | 03/07/2026 | `71eae72` |
| BL-026 | Agenda Universal (web): vista Día/Semana/Mes estilo Google Calendar — toggle server-driven, grid semana (lunes-domingo) y mes (5-6 semanas, +3 chips y "+N más"), sin librería JS nueva | 03/07/2026 | `300148e` |
| BL-027 | Agenda móvil (Universal MobAgGbl y por operador MobOpAg): vista Día/Semana/Mes — `/api/agenda` acepta `view=day\|week\|month`. Semana/Mes corregidas a grid tipo Google Calendar (7 columnas/celdas con puntos de color) a pedido del usuario, reemplazando la lista agrupada inicial | 03/07/2026 | `c3cd3d0` `8fb7c99` `8682040` |
| — | App móvil: fix filtro por operador en Agenda (`MobAgGbl`/`MobOpAg`) — `/api/agenda` no incluía el operador asignado directamente (`operator_id`) cuando la cita no tenía presupuesto aceptado, y desaparecía del filtro (ver NT-021) | 07/07/2026 | — (pendiente commit) |
| BL-029 | Recordatorios de recurrencia: `services.recurrence_days` (ej. Baño cada 30 días) + pantalla WhatsApp > Recurrencias que calcula bajo demanda (sin cron) mascotas vencidas desde su última ejecución (`spa_bookings`/`spa_booking_services`, no `executed_services` — ver NT-020). Plantillas con `context` (cita/recurrencia) y variables propias. Preview del mensaje resuelto antes de abrir WhatsApp, compartido con Bandeja Diaria. Ampliado 07/07/2026: opción "Crear nueva plantilla" en el selector (modal, sin salir de la pantalla) en Bandeja y Recurrencias; botón "Previsualizar" con datos de ejemplo también en el modal y en el formulario completo de plantillas (crear/editar, `WspPlEdi`); "seleccionar todos" ya no incluye recordatorios ya enviados hoy (pero se pueden marcar a mano para reenviar) | 07/07/2026 | — (pendiente commit) |
| BL-030 | Calendario mensual en WhatsApp > Bandeja diaria (puntos de color por día: completadas / recordatorio pendiente / en riesgo de no asistir —incluye días pasados sin resolver—, checkboxes no excluyentes, clic en un día navega a esa fecha) + filtro "Estado" de Agenda Universal convertido de `<select>` único a checkboxes no excluyentes (mismo criterio: ninguno marcado = ver todos) | 07/07/2026 | — (pendiente commit) |
| BL-032 | Mapa y Cobertura Espacial — versión mínima (`AX-MAPZN`): Leaflet + OpenStreetMap (sin API key) en nueva pantalla dentro de "Operación". Muestra sucursales y direcciones de clientes (ya tenían lat/lng), permite ubicar mascotas y crear vehículos de reparto con clic en el mapa (`pets.lat/lng` nuevas, tabla `vehicles` nueva — mínima, sin placa/capacidad). Checkboxes no excluyentes por tipo. Deliberadamente **no** es la arquitectura genérica de BL-031, que sigue abierta | 07/07/2026 | `84c40b0` |
| — | Fix CSP bloqueaba silenciosamente las teselas de OpenStreetMap en `AX-MAPZN` (mapa en blanco) — de paso se corrigió que también bloqueaba la geocodificación (Nominatim) del editor de direcciones desde que existe la CSP (ver NT-022) | 07/07/2026 | `84c40b0` |
| BL-033 | App móvil: `MobTeam` ("Equipo") conectada a datos reales — reemplaza mockup hardcodeado. Endpoint `GET /api/team` (`Api\OperatorController::team()`) agrega por operador: check-in activo (real, vía `OperatorCheckin`/`User.operator_id`), pendientes/completadas del día (`SpaBooking`) y trabajo actual si tiene una orden abierta (`status = work_order`). Se eliminó el "Turno 08:00-16:00" inventado — no hay ese dato en el esquema — y el resumen operativo ahora agrega datos reales del equipo en vez de números fijos | 10/07/2026 | `fdeb7b6` |
| — | Fix schema drift: `users.is_operator`/`operator_code` existían en producción sin migración commiteada, rompía la base `testing` (ver NT-023) — migración idempotente nueva, no-op confirmado en producción | 10/07/2026 | `fdeb7b6` |
| — | App móvil: barra de navegación reordenada a pedido del usuario (Agenda, Mascotas, Clientes, Operador) — se quitaron "Equipo" y "Directorio" del menú principal/tabs por ser redundantes con "Operador" y con las pantallas de Mascotas/Clientes ya existentes; las pantallas en sí no se borraron, solo dejaron de estar enlazadas | 10/07/2026 | `8322bea` |
| BL-034 | App móvil: `MobUserConfig` conectado a cuenta real — el usuario reportó que no permitía configurar nada real. Agregado: editar nombre/apellido/correo, cambio de contraseña (valida contraseña actual), foto de perfil (subir/quitar, reutiliza `UserPhotoImageManager`), selector de tema Claro/Oscuro/Sistema (paleta oscura nueva completa vía variables CSS, aplica a toda la app de inmediato, persiste en `localStorage`). Endpoints nuevos `PATCH /api/me`, `PUT /api/me/password`, `POST`/`DELETE /api/me/photo`. **GMT/zona horaria por usuario quedó deliberadamente fuera de esta pasada** — los timestamps de `spa_bookings` no tienen ancla de zona horaria (son naive), así que una conversión por-usuario mostraría horas incorrectas; requiere primero decidir una estrategia de zona horaria a nivel de datos, no es un simple selector de UI | 10/07/2026 | `8322bea` |
| BL-035 | App móvil: editor de foto antes de subir (recorte + rotar + marca de agua opcional) para la foto de perfil de `MobUserConfig`, vía `cropperjs` `1.6.2` (misma versión fijada que usa el backoffice web, NT-001) nuevo en `mob_apps/operador`. La marca de agua (nombre + fecha, pequeña y legible, franja inferior semitransparente) es **una regla de negocio, no una preferencia personal**: se activa/desactiva desde el Backoffice web (`Configuración del sistema` → sección nueva "Fotografías", `photo_watermark_enabled`, default apagado), expuesta a la app móvil vía `GET /api/settings/photos`. La fecha usa el metadato EXIF `DateTimeOriginal` de la foto si existe (parser propio, sin dependencia nueva, `src/lib/exifDate.ts`); si no hay EXIF, usa la fecha de subida | 10/07/2026 | `8322bea` |
| BL-036 | App móvil: fix — mascotas existentes no permitían cambiar la foto ("se gestiona desde el backoffice", mensaje hardcodeado sin excepción). Ahora usa el mismo `PhotoEditorModal` (recorte/rotar/marca de agua) de BL-035, conectado a nuevos endpoints `POST`/`DELETE /api/pets/{pet}/photo`. De paso se encontró y corrigió que la subida de foto de mascota vía API (tanto al crear como — ahora — al editar) **no comprimía la imagen en absoluto** (guardaba el archivo crudo) y no dejaba registro en la galería (`pet_photos`) como sí hace el backoffice web — ahora usa `PetPhotoImageManager` igual que la ruta web, con el mismo registro de trazabilidad (`photo_type: perfil`, `is_primary`, fecha EXIF vía `extractTakenAt()`) | 10/07/2026 | `8322bea` |
| — | Fix `ScreenHeader.tsx`: el tag de depuración de pantalla (ej. `MobPetDet`) desaparecía por completo cuando la pantalla mostraba breadcrumbs — el branch de breadcrumbs nunca lo renderizaba, a diferencia del branch sin breadcrumbs. Afecta a **toda la app**, no solo mascotas; reportado por el usuario como "no muestra el nombre de la pantalla" en Mascotas, que es donde más se nota porque casi siempre se llega ahí con breadcrumbs | 10/07/2026 | `8322bea` |
| BL-037 | App móvil: auditoría de código pedida por el usuario ("busca software redundante, que no se use o que no esté resuelto"). Sin herramienta de navegador en este entorno, se corrieron todos los chequeos estáticos disponibles (`tsc`, `npm run build`, `npm audit`) más una revisión manual exhaustiva. Encontrado y corregido: (1) 7 dependencias npm sin ninguna referencia en el código (`@google/genai`, `clsx`, `dotenv`, `express`, `lucide-react`, `motion`, `tailwind-merge`) — desinstaladas; (2) links rotos en `Directory.tsx`/`AssignService.tsx` que apuntaban a rutas inexistentes (`/admin/assign`, `/admin/directory`, `/agenda-global`) — corregidos a las rutas reales (`/directorio`, `/directorio/asignar`, `/agenda`); `AssignService.tsx` además le faltaba el import de `Link` (error de compilación preexistente, no detectado antes); (3) el formulario de alta de mascota (`NewPetForm` dentro de `PetDetail.tsx`) era la única pantalla que había quedado con el selector de foto viejo (sin recorte/rotar/marca de agua) tras BL-035/BL-036 — ahora usa el mismo `PhotoEditorModal`, las 3 pantallas de foto quedan consistentes. **Dejado sin tocar a pedido del usuario:** 4 archivos huérfanos sin ninguna ruta ni import (`ActiveService.tsx`, `GroomerDashboard.tsx`, `client/Booking.tsx`, `client/Dashboard.tsx`, ~730 líneas) — parecen resto de un template sin terminar (`package.json` se llama literalmente `"react-example"`); quedan documentados acá por si se decide borrarlos o retomarlos más adelante | 10/07/2026 | `8322bea` |
| — | Fix test flaky por hora del día en `TeamPanelTest` (ver NT-024) — no relacionado a código de producción | 10/07/2026 | `8322bea` |
| BL-038 | App móvil: bloqueo de sesión — timeout por inactividad (5 min) + bloqueo automático al cambiar de app (`visibilitychange`) + botón manual "Bloquear ahora" en el menú, todo sin cerrar sesión (el token sigue válido, solo se tapa la UI). Desbloqueo con contraseña (nuevo endpoint `POST /api/me/verify-password`, no cambia la contraseña ni el token) **o** con Face ID/huella/PIN del teléfono vía WebAuthn — verificación 100% local en el dispositivo, sin involucrar al servidor (el "challenge" es aleatorio generado en el celular, no hay tabla de credenciales en el backend); si el navegador no soporta WebAuthn o el usuario no lo activó, cae automáticamente a contraseña. Activación opcional en `MobUserConfig` → sección "Seguridad". **No se pudo probar de punta a punta en este entorno** — WebAuthn requiere hardware biométrico real de un navegador/teléfono, no hay forma de simularlo acá | 10/07/2026 | `8322bea` |
| — | Fix `LockScreen`: campo de contraseña y botón "Desbloquear" se veían diminutos (más chicos que el texto "Cerrar Sesión") — causa raíz: `max-w-xs`/`max-w-sm` colisionaban con tokens custom `--spacing-xs`/`--spacing-sm` del tema Tailwind, resolviendo a 4px/8px en vez de ~320px/384px (ver NT-026). Confirmado por el usuario probando en producción real tras el fix | 12/07/2026 | `8322bea` |
| — | Fix crítico de seguridad — el candado de sesión (BL-038) se perdía por completo en cualquier reload o "atrás" del navegador, sin importar si estaba bloqueado manual o automáticamente. Dos causas encontradas y corregidas en `AppLockContext.tsx`: (1) el estado persistido en `localStorage` se borraba antes de tiempo por una carrera con la carga asíncrona de `useAuth()` (ver NT-027); (2) escribir la contraseña en `LockScreen` sin terminar de desbloquear marcaba incorrectamente la sesión como desbloqueada en lo persistido (ver NT-028). Confirmado por el usuario en producción real tras el fix | 12/07/2026 | `d09b1f0` |
| — | App móvil: panel de "citas pendientes sin resolver" en `MobAgGbl` (Agenda) — antes ocupaba mucho espacio siempre expandido arriba de la lista; a pedido del usuario ahora es un botón chico con colores de alarma junto a Día/Semana/Mes que abre una ventana (hoja deslizable desde abajo, mismo patrón que el menú lateral) con el detalle | 12/07/2026 | `d09b1f0` |
| BL-039 | Backend: nueva columna `spa_bookings.created_by_user_id` (FK nullable a `users`, `nullOnDelete`) — registra qué usuario (dueño de la sesión, web o móvil) agendó cada cita. A pedido del usuario, que preguntó si esto ya se guardaba y no era el caso (antes solo se guardaba `operator_id`, el operador asignado/que atiende, no quién la registró). Capturado vía `auth()->id()` en `BookingService::scheduleSpaSession()` (ruta web) y `Api\BookingController::store()` (ruta móvil) — los dos únicos caminos de creación de citas SPA hoy. Relación `SpaBooking::createdBy()`. 2 tests nuevos (uno por ruta) confirman el guardado | 12/07/2026 | `d09b1f0` |
| BL-040 | Backoffice: envío de plantillas también por correo (paso 1 de la hoja de ruta de mensajería — ver comparación con MoeGo). Sección "Servicio de Correo" completa en Configuración del sistema (usuario, contraseña cifrada, encriptación, remitente), bridgeada de verdad a `config('mail.*')`. Campo `subject` nuevo en `whatsapp_templates`. Nuevo `TemplateMessageMail` con botón "Escríbenos por WhatsApp". Bandeja Diaria y Recurrencias ahora tienen selector de canal (WhatsApp/Correo) reusando la misma cola de envío y previsualización; `booking_messages`/`recurrence_messages` ganan columna `channel`. De paso se corrigió un bug preexistente real: `ServiceSummaryMail` llamaba a un método `SystemSettings::get()` que no existe — hubiera tronado (fatal) la primera vez que alguien activara el resumen automático por correo. 9 tests nuevos | 12/07/2026 | `69a03fc` |
| — | Fix `system-settings/smtp-test` daba 404, y el campo de encriptación SMTP ofrecía valores (`ssl`/`tls`) que Symfony Mailer no reconoce (son `smtp`/`smtps`) — encontrado por el usuario probando en producción real tras BL-040. Ver NT-030 (con 3 adendas del mismo día: opciones corregidas, resguardo genérico para valores `select` obsoletos guardados en BD, y la gotcha de que `tinker`/`artisan` no pasan por el middleware que aplica `SystemSettings` a la config de mail) | 12/07/2026 | `69a03fc` |
| BL-042 | Backoffice: asistente de IA (Claude) para el widget de chat del sitio público de WordPress — informativo, sin conexión a CRM/agenda. Responde preguntas sobre el catálogo de servicios activo (`ServiceCatalogService::getActiveServices()`, nuevo) y siempre invita a un botón fijo de CTA configurable. Sección nueva `ai_assistant` en Configuración del sistema (API key cifrada, modelo, prompt extra, texto/URL del botón, token del widget, origen CORS permitido). Nuevo `Api\AssistantChatController` (`POST /api/assistant/chat`, `GET /api/assistant/config`), rutas públicas fuera de `ApiAuthenticate`, protegidas con `VerifyAssistantSiteToken` + `HandleAssistantCors` (middleware global a medida, no `config/cors.php` — ver NOTAS_TECNICAS) + `throttle:15,1`. Tope de 30 mensajes por sesión anónima (`service_ai_chats`/`service_ai_messages`, sin relación a `clients`/`leads`). Primera integración HTTP saliente y primer uso de LLM en el proyecto. **Decisiones explícitas del usuario que acotaron el alcance:** Facebook/Instagram quedan fuera — son solo informativos y se resuelven con las respuestas automáticas nativas de Meta Business Suite (sin código); la comunicación real conectada al CRM (citas, estado de mascotas) le corresponde a la futura app de clientes (BL-012), no a este chatbot. Fase 2 pendiente: widget JS embebible + snippet de inserción en WordPress. 9 tests nuevos | 13/07/2026 | pendiente commit |
| BL-041 | Backoffice: preferencias de comunicación del cliente (opt-out por categoría — ofertas, recordatorios de servicio, estado de trabajo/resúmenes, estado de cuenta, otros), a pedido del usuario tras BL-040, con motivo legal (LFPDPPP, derecho de oposición sobre mercadotecnia). Las 5 categorías arrancan opt-out por defecto (opted-in), decisión explícita del usuario. Solo (b) recordatorios y (c) estado de trabajo tienen emisor real hoy — se bloquea el envío en servidor (422), no solo en la UI, en `BookingMessageController`/`RecurrenceMessageController` (ambos canales) y en `ServiceSummaryMail`. Nueva sección en la ficha de cliente (staff-managed) + autogestión pública sin login vía enlace firmado en el pie de los correos (`URL::temporarySignedRoute`, primer uso de rutas firmadas en el proyecto, válido 1 año) — `ClientPreferencesController` nuevo, fuera del grupo `auth` de `routes/web.php`. (a) ofertas y (d) estado de cuenta quedan solo con la preferencia guardada — no existe ningún emisor de campañas ni de estado de cuenta todavía. 7 tests nuevos | 12/07/2026 | `69a03fc` |

---

## Reglas de gestión

- Un ítem **no se elimina** — se mueve a Completados con fecha y referencia de commit.
- Si un ítem genera una NT, agregar referencia en la columna Notas.
- Si un ítem se descarta (won't do), moverlo con motivo documentado.
