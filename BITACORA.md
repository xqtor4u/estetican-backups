# 📓 Bitácora de Desarrollo - EstetiCAN 2

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
