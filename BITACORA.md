# 📓 Bitácora de Desarrollo - EstetiCAN 2

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
