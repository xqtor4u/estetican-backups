# 📓 Bitácora de Desarrollo - EstetiCAN 2

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
