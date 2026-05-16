# 📘 Manual Técnico Modular - EstetiCAN 2
**Versión de Documento:** 260515.2
**Estado del Proyecto:** Operativo / Fase de Estabilización — Agenda y Contabilidad Consolidadas

## 1. Arquitectura del Sistema
EstetiCAN 2 es una aplicación de gestión backoffice construida sobre un stack moderno y robusto, optimizada para entornos de alta interactividad sin la complejidad de un SPA completo.

- **Core:** Laravel 13.x (PHP 8.3+)
- **Base de Datos:** MySQL 8.0
- **Frontend:** Laravel Blade + Alpine.js 3.x + Bootstrap 5.3
- **Gestión de Activos:** Laravel Vite (con soporte para hot-reload en WSL)
- **Entorno de Desarrollo:** WSL 2 (Ubuntu) con Laravel Sail (Docker)

### Estructura de Directorios Clave
- `app/Domain/`: Lógica de negocio organizada por dominios (Planning, Resources, Commercial).
- `app/Support/`: Clases de soporte, managers de imágenes y generadores de páginas.
- `resources/views/components/`: Componentes Blade reutilizables (UI Kit).
- `resources/views/agenda/`: Módulo central de operaciones.

---

## 2. Módulos Core

### 🐾 Mascotas e Identidad (`Pets`)
El sistema separa la **Identidad** de la **Trazabilidad**.
- **Identidad:** Almacenada en la tabla `pets` (columna `profile_photo_path`). Es la foto oficial que aparece en círculos y perfiles.
- **Trazabilidad (Bitácora):** Almacenada en `pet_photos`. Es un historial cronológico de fotos (Ingreso, Incidencia, Resultado).
- **Sincronización:** Al marcar una foto de la bitácora como "Perfil", el sistema actualiza automáticamente la identidad en la tabla `pets`.

### 📅 Agenda Unificada (`Unified Agenda`)
El corazón operativo que integra dos líneas de negocio en una sola lectura.
- **SPA (Citas):** Basado en `SpaBookings`. Maneja servicios, presupuestos y órdenes de trabajo. Se apoya en un **Mago de Programación Global** que permite agendar buscando directamente al cliente o mascota.
- **Hotel (Estancias):** Basado en `HotelReservations`. Maneja bloqueos de jaulas por rangos de fechas.
- **Estados de Cita SPA:**
  1. `scheduled`: Programada (esperando presupuesto).
  2. `work_order`: En proceso (orden de trabajo abierta, permite asignar Operadores/Especialistas).
  3. `completed`: Finalizada (generación de saldo y reporte).

### 💰 Gestión Comercial y Financiera
- **Presupuestos (`Quotes`):** Permite múltiples versiones por cita. Una vez aceptada, "congela" los servicios y precios en una Orden de Trabajo.
- **Contabilidad Separada:** Los ingresos se dividen en `cash_ledgers` (Caja física) y `bank_ledgers` (Bancos/Tarjetas) para auditorías precisas. El sistema fuerza a elegir el destino al cerrar cuentas.
- **Garantías (Anticipos):** Soporte para requerir porcentajes de anticipo configurables por tipo de servicio (ej. cirugías).

### 📊 Dashboard Principal (KPIs)
El punto de entrada post-login (`/dashboard`) que ofrece "Mission Control" a nivel macro:
- KPIs en tiempo real: Citas del día, Huéspedes en Hotel, Ingresos (Caja + Banco).
- Enrutador rápido para nuevas entidades y acceso a configuraciones.

### 📱 Ecosistema Móvil (`mob_apps`)
Directorio independiente del Backoffice diseñado para las futuras interfaces de operadores en piso.
- Arquitectura separada con servidor Node.js local.
- Consumo de API hacia Laravel.

---

## 3. Componentes Técnicos Especializados

### `x-image-upload`
Componente de UI avanzado basado en Alpine.js que gestiona:
- Selección de archivos y vista previa.
- Recorte interactivo (Circular para perfiles, Rectangular para bitácora).
- **Auto-Submit:** Propiedad `autoSubmitFormId` para enviar el formulario instantáneamente tras el recorte (ideal para cambios rápidos de perfil).

### `ResourceAllocationService`
Gestiona la disponibilidad de activos físicos (Jaulas, Consultorios, Mesas).
- Evita colisiones de horarios.
- Permite "liberar" recursos automáticamente al cancelar o finalizar citas.

---

## 4. Patrones de UI/UX (Aesthetics)
- **Mission Control:** Las vistas de detalle (`show`) son contextuales. Muestran diferentes herramientas (presupuestos, cronómetros, reportes) según el estado actual del objeto.
- **Micro-interacciones:** Uso extensivo de Alpine.js para cronómetros en tiempo real, modales reactivos y filtrado dinámico.
- **Alertas de Seguridad:** Sistema de detección de "Citas Vencidas" que genera banners rojos automáticos con accesos directos para corregir el flujo (Reprogramar o Cerrar).

---

## 5. Patrones Blade Críticos

### ⚠️ `@php(expr)` con paréntesis anidados — BUG CONOCIDO
El compilador Blade usa regex para `@php(expr)` y falla silenciosamente cuando la expresión contiene paréntesis anidados (ej: `route(...)`, `firstWhere(...)`, `in_array(...)`). Esto corrompe la compilación de todo el template a partir del punto del error.

**Regla:** Nunca usar `@php(expr)` con funciones anidadas. Siempre usar el bloque:
```blade
@php
    $var = expresion_compleja();
@endphp
```

### Contabilidad: fuente única de verdad
- `CashLedger` y `BankLedger` son morphMany en `Quote` (`payable_type = App\Models\Quote`).
- Para calcular el saldo: `$quote->cashLedgers->sum('amount') + $quote->bankLedgers->sum('amount')`.
- `client->payments()` NO es equivalente. No usar para calcular balances de citas.

### Flujo de aceptación de quote
Al llamar `QuoteService::acceptQuote()`:
1. Quote → `status = accepted`
2. Otros quotes del booking → `status = rejected`
3. Si hay anticipo → crea `CashLedger` o `BankLedger` con `payable = Quote`
4. Sincroniza `spa_booking_services` desde `quote_items` (precio correcto en `current_price`)
5. Actualiza `spa_bookings.total_estimated_price = quote.total_amount`
6. Booking → `status = work_order`

---

## 6. Mantenimiento y Extensibilidad
- **Bitácora de Desarrollo:** Siempre consultar `BITACORA.md` en la raíz para el historial de cambios atómicos.
- **Configuración de Sistema:** Centralizada en `SystemSettings`. Permite cambiar branding, colores, parámetros de Hacienda y correos sin tocar el código.
- **Formato de hora global:** `ApplySystemSettings` middleware comparte `$timeFormat`, `$dateFormat`, `$datetimeFormat` con todas las vistas. No usar ternarias en los templates.

---
*Documento generado automáticamente por Antigravity AI para la sesión 260515.*
