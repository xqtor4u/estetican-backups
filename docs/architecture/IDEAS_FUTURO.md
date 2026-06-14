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
- [ ] **Módulo de Veterinaria** — historial clínico, consultas, recetas, vacunas avanzadas, cirugías. Con asignación de médicos y especialistas.
- [ ] **Módulo de RRHH y Nómina** — control de asistencia, cálculo de nómina, pagos a destajo ligados a servicios ejecutados.

## 📱 App Móvil — Operador (ideas surgidas 13/06/2026)
- [ ] **Notificaciones push al operador** cuando se le asigna una nueva cita.
- [ ] **Historial de check-ins** visible en el perfil — últimas entradas/salidas con transgresiones.
- [ ] **Reporte de puntualidad** en backoffice — basado en cuántas veces se disparó el diálogo de tolerancia de inicio (`booking_grace_minutes`) y si se confirmó.
- [ ] **Cobro parcial / anticipo desde app móvil** — MobCobro actualmente registra liquidación completa; permitir pagos parciales con saldo pendiente visible.
- [ ] **Foto de la sesión desde app móvil** — subir foto `ingreso`/`resultado` directamente desde la cita activa (categorías del modelo `PetPhoto`).
- [ ] **Nueva cita desde GlobalAgenda** — botón FAB en la agenda que pregunte mascota antes de abrir MobCitaNueva (hoy solo se puede crear desde ficha de mascota).

---
*Si una idea nace en la Bitácora pero no se puede ejecutar hoy, se mueve aquí.*
