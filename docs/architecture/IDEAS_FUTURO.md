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
- [ ] **Timeout de inactividad / bloqueo al cambiar de app configurable** — hoy son valores fijos en `AppLockContext.tsx` (5 min de inactividad, bloqueo inmediato al perder foco). Si en el uso real resultan muy agresivos o muy laxos, se puede exponer como preferencia (personal o de negocio, a decidir) en vez de tocar el código cada vez.

## 🧹 Limpieza pendiente — App Móvil (BL-037, 10/07/2026)

- [ ] **Decidir destino de 4 archivos huérfanos** (`ActiveService.tsx`, `GroomerDashboard.tsx`, `client/Booking.tsx`, `client/Dashboard.tsx`, ~730 líneas) — sin ruta ni import en ningún lado, links internos rotos, parecen resto de un template sin terminar. El usuario prefirió no borrarlos todavía. Decidir: completar como pantallas reales, o borrar.

---
*Si una idea nace en la Bitácora pero no se puede ejecutar hoy, se mueve aquí.*
