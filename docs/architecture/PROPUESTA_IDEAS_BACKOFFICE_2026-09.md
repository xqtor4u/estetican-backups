# Propuesta — ideas pendientes del backoffice (27/09/2026)

Pedido de Tomas: proponer antes de construir las ideas grandes de `IDEAS_FUTURO.md`, **sin
facturación SAT** (falta el sistema de timbrado/PAC). Todo lo que se apruebe de aquí se construye
en `tst` y se porta enseguida a producción (no es emergencia).

Estado verificado contra el código antes de proponer:
- **Permisos de Hotel por acción** — ya hechos (`ver/crear/editar hotel` en `routes/web.php`). Se
  marca como completado en `IDEAS_FUTURO.md`.
- **PDF** — `barryvdh/laravel-dompdf` ya instalado y en uso (reportes de caja, expediente
  clínico). El presupuesto, la orden y el recibo existen como vistas imprimibles HTML
  (`reports/invoice`, `reports/work-order`), no como PDF.
- **Inventario** — ya existe base (`ItemBranchStock`, `ItemMovement`, consumo por cita).
- **`executed_services`** — sigue sin cablearse (ningún flujo llama `convertFromBooking()`).
- **`branch_id` en citas y pagos** — no existe.

Tamaños: **S** = una sesión, **M** = 2–3 sesiones, **L** = requiere diseño previo contigo.

---

## A. Ganancias rápidas (recomendado para el siguiente bloque)

| # | Idea | Qué resuelve | Tamaño |
|---|---|---|---|
| A1 | **Presupuesto, Orden de Trabajo y Recibo en PDF, con envío por WhatsApp/correo** (BL-008) | Mandar al cliente el presupuesto o su recibo sin imprimir. Se reusan las vistas imprimibles que ya existen + dompdf; el envío reusa el selector de WhatsApp y el correo SMTP ya configurados. | M |
| A2 | **Unificar el cálculo de total/saldo/pagos de una cita en un solo lugar** | Hoy vive duplicado en 3+ vistas; cada desincronización es un bug de dinero (EST-008 fue uno). Un método en `SpaBooking` que todas usen. | S |
| A3 | **Test automático de cobertura de permisos en rutas** | Convierte la regla de seguridad #3 de "recordarlo a mano" a "el test falla si una ruta de negocio no tiene `permission:`". | S |
| A4 | **Límite de subida de imágenes configurable + miniaturas en listas** | Menos datos en la app móvil (listas de mascotas/operadores) y el límite de 10 MB deja de estar en código. | S |

## B. Operación diaria

| # | Idea | Qué resuelve | Tamaño |
|---|---|---|---|
| B1 | **`branch_id` en citas y pagos** | Base para reportes y caja por sucursal, y para que `{sucursal}` salga de la cita y no de quien envía. Migración aditiva + llenado al crear; datos viejos se asignan a la única sucursal activa. | M |
| B2 | **Horario operativo por sucursal** | Hoy hay un solo horario global. Solo vale la pena si abren una sucursal con horario distinto. Depende de B1. | M |
| B3 | **Cuadrícula de horarios en el alta de cita web** | Paridad con la cuadrícula arrastrable del móvil (ZEUS-043) en vez de escribir la hora a mano. | M |
| B4 | **Historial inmutable de servicios ejecutados** | Cablear `convertFromBooking()` al completar una cita: qué se hizo exactamente queda congelado aunque se edite la cita después. Base para nómina a destajo (D4) y recurrencias más confiables. | M |
| B5 | **Penalización automática de anticipo en Hotel** | Retener el anticipo si el cliente no llega después de X horas. Regla de negocio a definir contigo (horas, porcentaje). | S–M |

## C. Cobro y relación con el cliente

| # | Idea | Qué resuelve | Tamaño |
|---|---|---|---|
| C1 | **Links de pago / QR con Mercado Pago** (+ webhook que registra el pago solo) | El cliente paga anticipo o saldo desde su celular. Requiere cuenta de Mercado Pago del negocio y decidir quién absorbe la comisión. Recomiendo Mercado Pago sobre Stripe para México. | L |
| C2 | **Código de consulta sin login** (alternativa a BL-012) | El cliente ve el estado de su mascota con un código aleatorio, sin cuentas ni contraseñas. | M |
| C3 | **WhatsApp Business API real** | Ya en curso como BL-024b (alta en Meta). Envío automático de recordatorios sin abrir WhatsApp a mano. | L (en curso) |

## D. Módulos grandes (requieren diseño contigo antes de tocar código)

| # | Idea | Nota |
|---|---|---|
| D1 | Contabilidad completa y exportación para contador | Ya hay pólizas (`JournalEntry`); faltan balanza, estado de resultados y exportes. Útil incluso sin SAT. |
| D2 | Kits servicio + producto, tiempos de entrega de tienda | Depende del uso real de Tienda. |
| D3 | Plantillas de instalación por modelo de negocio | Encaja con el alta automática de clínicas de Zeus (ZEUS-001) — conviene diseñarlo allá. |
| D4 | RRHH y nómina a destajo | Depende de B4. |
| D5 | Alexa, Outlook, login sin contraseña (WebAuthn) | Diferir hasta que haya una necesidad real. |

---

## Estado (27/09/2026)

**A2, A1, A3, B1, A4 y B2 construidos en `tst` y portados a producción el mismo día** — ver `BITACORA.md`. Siguen pendientes de decisión: B3–B5, C1–C3, D1–D5.

## Recomendación original

Siguiente bloque: **A2 → A1 → A3 → B1**, en ese orden. A2 primero porque A1 (los PDF) va a
mostrar totales y saldos, y conviene que salgan de un solo cálculo. B1 abre la puerta a todo lo
que es "por sucursal". C1 (pagos en línea) es el de más impacto comercial, pero necesita tu
decisión sobre la cuenta de Mercado Pago y las comisiones antes de empezar.
