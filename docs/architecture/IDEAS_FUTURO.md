# 💡 Ideas y Futuro: EstetiCAN 2
*Este archivo contiene las funcionalidades, módulos e integraciones que NO son parte del Sprint actual pero que deben ser consideradas a posteriori.*

---

## 🏦 Facturación y Hacienda (México)
- [ ] **Módulo de Facturación SAT:** Integración con PAC para timbrado 4.0.
- [ ] **Factura Global de Mostrador:** Generación automática de factura al público en general para todos los pagos "En Banco" sin RFC asociado.
- [ ] **Perfiles Fiscales:** Guardado de RFC, Razón Social y Régimen Fiscal por cliente.
- [ ] **Desglose de Impuestos:** Cálculo dinámico de IVA (16%) en el cierre de cuenta.

## 🛒 Tienda y Suministros
- [ ] **Gestión de Stock:** Control de inventarios para productos de tienda.
- [ ] **Lead Time:** Tiempos de entrega para pedidos especiales de tienda.
- [ ] **Kits de Productos:** Paquetes que incluyan servicio + producto (ej. Baño + Collar Antipulgas).
- [ ] **Variantes por talla en Artículos (idea surgida 20/07/2026, ver BL-052b).** BL-052b agregó `meta_variant_group`/`meta_color` a `items` para representar el mismo producto en varios colores hacia el catálogo de Meta (cada color = su propia fila de `Item`, enlazadas por una clave compartida). Si algún producto futuro necesita variar por **talla** en vez de (o además de) color, el mecanismo es directamente extensible: una columna `meta_size` más, un `if` más en `MetaCatalogSyncService::buildPayload()` que mande `size` cuando esté presente, mismo `meta_variant_group` compartido. No construir hasta que exista un producto real que lo necesite.

## 💳 Pasarelas de Pago
- [ ] **Mercado Pago / Stripe:** Generación de links de pago y QR dinámicos.
- [ ] **Webhooks:** Confirmación automática de pago en el CRM al recibir la señal de la pasarela.

## 📈 Inteligencia y Otros
- [ ] **Reportes Contables:** Exportación de archivos para contador (Efectivo vs Banco).
- [ ] **Penalizaciones Automáticas:** Lógica para retener el anticipo si el cliente no llega a su cita de Hotel después de X horas.

## 🏗️ Configuración y Modelo de Negocio

- [ ] **Plantillas de instalación por modelo de negocio** — al instalar el sistema, el dueño elige su perfil (peluquería canina, hotel, veterinaria, mixto, con una o más áreas de negocio). El sistema precarga automáticamente: catálogo de cuentas contables, series de documentos, métodos de pago, permisos por rol y configuración base. Evita configuración manual desde cero en cada instalación nueva.

## 📒 Módulos Futuros (Contabilidad y Operación)

- [ ] **Módulo de Contabilidad completa** — mayor general, balanza de comprobación, estado de resultados, balance general. Exportación de movimientos para contador (XML, CSV, Excel).
- [ ] **Módulo de Inventarios** — control de stock de productos (accesorios, medicamentos, insumos). Movimientos de entrada/salida ligados a ventas y servicios.
- [ ] **Módulo de Veterinaria** — historial clínico, consultas, recetas, vacunas avanzadas, cirugías. Con asignación de médicos y especialistas. **La arquitectura ya lo soporta:** agregar `orden_vet` en `document_series` (is_active=true) + modelo `VetAppointment` + una línea en `/api/pets/{pet}/bookings` → los chips de tipo en MobPetJobs aparecen automáticamente.
- [ ] **Módulo de RRHH y Nómina** — control de asistencia, cálculo de nómina, pagos a destajo ligados a servicios ejecutados.

## 🖼️ Imágenes — Optimización futura (ideas surgidas 30/06/2026)

- [ ] **Recompresión retroactiva de fotos existentes** — artisan command `media:recompress` que itere sobre `media` (MediaLibrary) y regeneare conversiones con los nuevos parámetros de calidad (config `backoffice.images.*`). Solo afectaría archivos en storage; las URLs ya registradas seguirían siendo válidas porque MediaLibrary sobreescribiría los archivos en su lugar.
- [ ] **Servir thumbnails en MobOpPkr y PetSearch** — el endpoint `/api/operators` y `/api/pets` actualmente devuelven la URL de la imagen principal (tamaño completo). Si MediaLibrary genera una conversión `thumbnail` (160×160), se puede servir en listas y selectores para reducir el payload de la primera carga de ~250KB/foto a ~8–15KB/foto.
- [ ] **Límite de tamaño configurable por SystemSetting** — actualmente el límite de 10MB está hardcodeado en `image-upload.js`. Moverlo a `SystemSetting` (`max_upload_size_mb`) para que se configure desde el backoffice sin tocar código.

## 📱 App Móvil — Operador (ideas surgidas 13/06/2026)
- [ ] **Notificaciones push al operador** cuando se le asigna una nueva cita.
- [ ] **Historial de check-ins** visible en el perfil — últimas entradas/salidas con transgresiones.
- [ ] **Reporte de puntualidad** en backoffice — basado en cuántas veces se disparó el diálogo de tolerancia de inicio (`booking_grace_minutes`) y si se confirmó.
- [ ] **Cobro parcial / anticipo desde app móvil** — MobCobro actualmente registra liquidación completa; permitir pagos parciales con saldo pendiente visible.
- [ ] **Foto de la sesión desde app móvil** — subir foto `ingreso`/`resultado` directamente desde la cita activa (categorías del modelo `PetPhoto`).
- [ ] **Nueva cita desde GlobalAgenda** — botón FAB en la agenda que pregunte mascota antes de abrir MobCitaNueva (hoy solo se puede crear desde ficha de mascota).

## 🧾 Historial de Servicios Ejecutados (idea surgida 07/07/2026, ver NT-020)
- [ ] **Cablear `ExecutedServiceService::convertFromBooking()`** a los tres flujos reales que marcan una cita `completed` (`SpaBookingController`, `Api/PaymentController`, `Api/BookingController`) para que `executed_services`/`executed_service_items` empiecen a poblarse con un snapshot histórico inmutable. Hoy WhatsApp > Recurrencias (BL-029) lee de `spa_bookings`/`spa_booking_services` como workaround, que tiene una limitación real: si se edita una cita ya completada, `spa_booking_services` se borra y recrea, perdiendo el detalle histórico exacto de qué se hizo y cuándo. Evaluar si vale la pena vs. aceptar la limitación actual (el sistema opera con pocas ediciones post-completado).

## 💰 Caja y Finanzas — mejoras futuras (ideas surgidas 16/06/2026)
- [ ] **`branch_id` en `spa_bookings` y `payments`** — actualmente `spa_bookings` no tiene columna `branch_id`, lo que impide filtrar cobros por sucursal en el balance de movimientos. Agregar la columna (migración) y poblarla al crear la cita; hacer lo mismo en `payments`. Esto permitiría que `GET /api/cash/movements` filtre cobros por sucursal del operador (ver NT-011).
- [ ] **Saldo inicial en el balance de movimientos** — `MobCajaMovimientos` muestra neto del período (entradas − salidas) pero no el fondo inicial de la sesión de caja. Para un balance completo debería mostrar: fondo inicial + entradas − salidas = saldo esperado. Requiere ligar el período del balance a la sesión de caja correspondiente.
- [ ] **Corte de caja desde app móvil** — MobCaja actualmente solo muestra la sesión activa; agregar botón de corte con captura del monto físico contado y cálculo del diferencial (sobrante/faltante).

## 🗺️ Mapa y Cobertura Espacial — AX-MAPZN (idea surgida 07/07/2026)
- [x] **Versión mínima construida (BL-032, 07/07/2026).** Pantalla `AX-MAPZN` real ya existe (`mapa-zonas.index`, menú Operación → "Mapa y cobertura"): Leaflet + OpenStreetMap mostrando sucursales/clientes (de solo lectura, ya tenían lat/lng) y permitiendo ubicar mascotas (`pets.lat/lng`, columnas nuevas) o crear vehículos (`vehicles`, tabla nueva) con clic en el mapa. Ver `docs/tecnico/MODELO_BD.md` sección "Mapa y cobertura espacial".
- [ ] **Ver BL-031 — pendiente, NO construida todavía.** Entidad espacial genérica ("Ubicación": dirección + lat/lng como entidad propia, no atada a un solo dueño) ligada muchos-a-muchos vía tabla pivote polimórfica a lo que sea: personas (Cliente, Operador), objetos (Mascota, Vehículo), documentos (`Document`, ya existe para facturación), etc. La versión mínima de arriba usa columnas directas simples (`pets.lat/lng`, `vehicles`) — **no** esta arquitectura genérica; evaluar si algún día hace falta migrar a ella o si las columnas directas bastan para siempre.
- **Pendiente de definir (el propio usuario no lo tiene claro todavía):** qué hay físicamente "en ese punto" (edificio, casa, solar, etc.) — recomendación: no forzar un enum ahora, usar un campo libre (`label`/`kind`) y convertir a catálogo más adelante si hace falta reportar por tipo. Evitar diseñar la taxonomía completa antes de tener un caso de uso real que la necesite.
- **Alcance real todavía sin acotar:** ¿reemplaza el `lat`/`lng` directo que ya tienen `branches`/`addresses`, o convive con ellos? ¿Vehículos de reparto es un modelo nuevo completo (con placa, capacidad, etc.) o solo un punto en el mapa? Definir antes de planear implementación.

## 🔐 Sesión y Seguridad — App Móvil (ideas surgidas 10-11/07/2026, ver BL-038)

- [ ] **Login sin contraseña de verdad (WebAuthn real, verificado por servidor)** — lo construido en BL-038 es un candado *local* sobre una sesión que ya existe (el celular verifica Face ID/huella por su cuenta, sin involucrar al backend). Reemplazar el login mismo por WebAuthn sería un proyecto aparte y bastante más grande: tabla de credenciales por usuario/dispositivo, endpoints de challenge/response, verificación de firma en el servidor (librería tipo `web-auth/webauthn-lib`). Evaluar solo si el candado local no resulta suficiente en el uso real.
- [ ] **Zona horaria por usuario** — quedó descartado en BL-034 no por falta de interés sino porque los timestamps de negocio (`spa_bookings.scheduled_at` y equivalentes) son *naive* (sin ancla de zona horaria) — agregar un selector de UI sin resolver esto primero mostraría horas incorrectas. El primer paso real, si algún día hace falta, es decidir una estrategia de zona horaria a nivel de datos (¿todo se guarda en UTC y se convierte al mostrar? ¿se ancla a la zona de la sucursal?), no diseñar el selector.
- [x] **Timeout de inactividad configurable (BL-063, 21/07/2026).** Ya no es un valor fijo — `useUserPrefs.lockTimeoutMinutes` (1/2/5/10/15/30 min, selector en Configuración personal → Seguridad, 100% cliente/`localStorage`, por dispositivo). De paso se corrigió un bug real: el bloqueo inmediato al perder foco (`document.hidden`) disparaba en falso durante navegación interna o al abrir un picker nativo en Android — ahora tiene un margen de gracia de 1.5s antes de bloquear.

## 🧹 Limpieza pendiente — App Móvil (BL-037, 10/07/2026)

- [ ] **Decidir destino de 4 archivos huérfanos** (`ActiveService.tsx`, `GroomerDashboard.tsx`, `client/Booking.tsx`, `client/Dashboard.tsx`, ~730 líneas) — sin ruta ni import en ningún lado, links internos rotos, parecen resto de un template sin terminar. El usuario prefirió no borrarlos todavía. Decidir: completar como pantallas reales, o borrar.

## 🔔 Skill de Alexa — recordatorios y avisos (idea surgida 14/07/2026)

- [ ] **Skill de Alexa para recordatorios/avisos** (ej. "recoger a tu perro", vencimiento de recordatorios de servicio, alertas de citas). Sin acotar todavía: ¿quién recibe el aviso — el cliente en su casa, o el negocio/staff? ¿Notificación proactiva (Alexa Notifications/Reminders API, requiere que el cliente vincule la skill a su cuenta de Amazon) o solo consulta bajo demanda ("Alexa, pregunta a EstetiCAN cuándo recojo a mi perro")? Requiere alta en Amazon Developer Console, definición de intents, y probablemente un backend propio (endpoint que la skill consuma) — no es un simple webhook. Evaluar alcance real antes de diseñar.

## 🧠 Base de conocimientos para el asistente de IA — más allá del catálogo (idea surgida 14/07/2026)

- [ ] **Que el asistente de IA del widget de WP (BL-042) también conteste preguntas sobre contenido publicado en el sitio** (posts/artículos de WordPress), no solo sobre el catálogo de servicios (`ServiceCatalogPromptBuilder` hoy solo usa `services`). Sin acotar todavía: ¿se trae el contenido de los posts en cada request vía la REST API nativa de WordPress (`/wp-json/wp/v2/posts`, sin plugin nuevo) y se inyecta en el prompt igual que el catálogo (mismo patrón, sin RAG/embeddings, viable si son pocos posts)? ¿O hace falta indexar/buscar (embeddings) si el volumen de contenido crece? Empezar por la opción simple (traer todo, igual que el catálogo) y solo subir a RAG si el volumen lo justifica — mismo criterio que ya se usó para el catálogo de servicios.

## 🤖 Asistente de IA (BL-042) — próximos pasos evaluados (idea surgida 14/07/2026)

Tras ver el widget funcionando en real, el usuario planteó 3 mejoras relacionadas. Ninguna se construyó todavía — quedaron discutidas pero no implementadas, con las preguntas abiertas que faltan resolver antes de empezar:

- [ ] **El bot no sabe si el negocio cubre la zona del visitante.** Gap real encontrado: `ServiceCatalogPromptBuilder` (BL-042) solo inyecta el catálogo de servicios en el prompt — no tiene ninguna noción de dónde están las sucursales (`branches.lat/lng`, ya existe) ni de radio de cobertura (`coverage_radius_km`, BL-043, ya existe). El bot no puede contestar "¿me pueden atender si vivo en tal colonia?". Fix acotado y de bajo riesgo: agregar la dirección/zona de las sucursales activas al system prompt (reusar `Branch::getFormattedAddressAttribute()`), y opcionalmente el radio de cobertura configurado, para que el bot pueda responder con esa info en vez de no saber nada. No requiere pedirle ubicación al visitante todavía — alcanza con que el bot sepa dónde está el negocio.

- [ ] **Capturar el lead desde el bot.** El usuario reconsideró la decisión original de BL-042 ("solo informativo, sin conexión a CRM") — quiere encontrarle utilidad **sin comprometer datos sensibles del visitante**, preocupación explícita por privacidad (mismo espíritu que LFPDPPP/BL-041). Sin decidir todavía: ¿se piden datos solo si el visitante pide explícitamente que lo contacten, o siempre al iniciar el chat (más parecido a un formulario previo)? Evaluar el mecanismo antes de tocar código — probablemente un `Lead` nuevo (ya existe `App\Models\Lead`/`LeadService` en `Commercial`, ver BL-042 plan original) creado solo bajo consentimiento explícito del visitante, nunca de forma silenciosa.

- [ ] **Código de consulta de estado sin login (alternativa liviana a BL-012).** En vez de un sistema de cuentas/contraseñas para que el cliente vea el estado de su mascota, el usuario propuso algo más simple y más seguro: un **código aleatorio por mascota/servicio** (no el `order_folio` actual, que es secuencial y por lo tanto adivinable — folio 1005 revela que existen 1004/1006), generado al agendar la cita y entregado al cliente cuando trae a la mascota (apoyado en la pantalla de Orden de Trabajo que ya existe, `agenda/show.blade.php` → `_work_order.blade.php`, hoy solo vista por staff — no hay impresión/PDF todavía, eso es BL-008, sigue pendiente). El código se desactiva al completar el servicio, así que si el cliente pierde el papel/ticket viejo no queda un hueco de seguridad permanente. Es como un número de guardarropa, no un login — nadie necesita cuenta ni contraseña, y no expone nombre/dirección/teléfono ni otras mascotas del cliente, solo el estado de *esa* mascota en *ese* servicio puntual. **Preguntas sin resolver:** (a) qué debe mostrar exactamente la consulta — ¿solo texto de estado ("en proceso"/"listo"), o también las fotos que ya se suben durante el servicio (`PetPhoto`, categorías `ingreso`/`resultado`)?; (b) ¿aplica solo a citas SPA al inicio, o también a estadías de Hotel desde el día uno?; (c) ¿el código se muestra en pantalla al staff para que lo dicte, o hace falta imprimirlo/mandarlo (¿WhatsApp?) — depende de si BL-008 (PDF) se resuelve antes o si alcanza con mostrarlo en pantalla.

## 🏨 Permisos granulares para Hotel (idea surgida 16/07/2026, ver BL-059)

- [ ] **`hotel-reservations.*` no tiene permisos por acción** (a diferencia de `items`/`groups`/`services`, que sí usan `middlewareFor('index', 'permission:ver ...')` etc.) — hoy cualquier usuario autenticado con el módulo Hotel activo puede ver/crear/editar reservas, sin distinción de rol. Se dejó fuera del alcance de BL-059 (el toggle de módulo) a propósito, para no mezclar dos cambios de alcance distinto en el mismo commit — agregar permisos granulares (`ver/crear/editar/eliminar hotel_reservas`, mismo patrón que `catalogo_articulos`) es trabajo aparte, sin decidir todavía si el negocio realmente lo necesita (¿algún rol de staff no debería poder cancelar una estancia?).

## 📲 Mensajería real de WhatsApp por API (idea surgida 18/07/2026, ver BL-052)

- [ ] **Reemplazar el `wa.me` manual de BL-024 por la WhatsApp Business Platform (API oficial).** Al construir BL-052 se agregó, en la misma Meta App ("EstetiCAN Catálogo"), el caso de uso "Conecta con los clientes a través de WhatsApp" — sin necesidad real para el catálogo, a pedido explícito del usuario, dejando la puerta abierta a esto. Permitiría automatizar de verdad el envío de recordatorios/confirmaciones (Bandeja Diaria, Recurrencias) sin que el operador tenga que abrir WhatsApp y dar clic a mano, y habilitaría recepción real de respuestas del cliente — el alcance futuro que ya tenía anotado BL-024b ("recepción de respuestas, historial conversacional, CRM completo"). Requiere: plantillas de mensaje pre-aprobadas por Meta (a diferencia de las plantillas de texto libre actuales en `whatsapp_templates`), verificación del número de WhatsApp Business dentro de la misma app/porfolio, y diseñar el webhook de entrada (hoy no existe ninguno en el proyecto). Sin decidir ni diseñar todavía — es un cambio de fondo al mecanismo de mensajería, no una extensión menor.

## 📅 Horario y disponibilidad de operadores — pendientes reales (ideas surgidas 20-21/07/2026, ver BL-060/061/062)

- [ ] **Ver el horario semanal desde la app móvil (solo lectura).** BL-060 dejó la captura/edición del horario semanal exclusiva de Backoffice web a propósito. El operador ya puede *bloquear* sus propias horas desde móvil (BL-061), pero no puede *ver* qué días/horas tiene configurados como su horario base — tendría que pedirle a un admin que se lo diga o entrar al Backoffice web. Agregar una vista de solo lectura en Configuración personal (o junto a "Bloquear mi horario") sería una extensión pequeña y de bajo riesgo sobre lo ya construido.
- [ ] **Horario operativo real por sucursal, no solo uno global.** Hoy `BusinessHours`/`SystemSettings` tiene un único horario de apertura/cierre para todo el negocio (`booking_opening_time`/`booking_closing_time`), sin distinción por sucursal — surgió al pedir que el horario semanal de un operador "por default mostrara el horario de la sucursal" (BL-060, 20/07/2026), y se resolvió usando el horario general porque no existe nada por-sucursal todavía. Si el negocio llega a operar sucursales con horarios distintos entre sí, este es el punto real a resolver — no es solo agregar un selector de UI, hay que decidir cómo interactúa con el horario semanal por operador (BL-060) y con el selector de slots (BL-062).
- [ ] **Selector visual de horarios en el Backoffice web (paridad completa con la app móvil).** BL-062 decidió, a propósito, NO construir un grid de horarios clickeable en `agenda/create.blade.php`/`edit.blade.php` (el formulario web nunca tuvo uno) — en su lugar se agregó una advertencia en vivo (AJAX) que avisa si el horario elegido no está disponible, pero el staff sigue escribiendo la fecha/hora a mano en vez de elegir de una grilla como en `MobCitaNueva.tsx`. Construir el grid real en Blade/Alpine (mismo patrón de slots de 30 min, ocupado/bloqueado) sería el siguiente paso si la advertencia en vivo resulta insuficiente en el uso real.

---
*Si una idea nace en la Bitácora pero no se puede ejecutar hoy, se mueve aquí.*
