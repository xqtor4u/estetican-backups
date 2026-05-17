# Task List - EstetiCAN Backoffice

Fecha de definición: 2026-03-30
Última actualización: 2026-05-16

Este documento centraliza las tareas pendientes del proyecto. La bitácora (`Bitacora_Backoffice_Clientes_y_Pets.md`) registrará únicamente los cambios ya implementados.

## Pendientes

### Usuarios y Acceso (Sprint activo)
- **[ ] USECRE:** Añadir sección de Operador y toggle `can_login` en el formulario de alta de usuario.
- **[ ] USEIND:** Añadir columnas `can_login` e `is_operator` a la tabla del listado de usuarios.
- **[ ] USESHO:** Mostrar datos de operador en la vista de detalle de usuario.
- **[ ] Operador GRO-JMP:** Asignar `operator_role_id` desde la interfaz de edición de usuario.
- **[ ] Operadores:** Decidir en sprint siguiente si se depreca/elimina la tabla `operators` (ahora los datos viven en `users`).
- **[ ] Roles:** Asignar roles Spatie (`super-admin`, `recepcionista`, etc.) a usuarios operativos desde el módulo de Roles y Permisos.
- **[ ] Permisos:** Proteger botones de acción en vistas Blade con directivas `@can('accion modulo')`.

### Agenda y Recursos
- **[ ] Agenda:** Afinar reglas de negocio por área antes de dar por cerrado el módulo.
- **[ ] Jaulas/Recursos:** Modelar jaulas como activo físico explícito y transversal para disponibilidad, bloqueo y ocupación.
- **[ ] Jaulas/Recursos:** Conectar el selector/asignador de recursos desde la UI del módulo de Hotel/Estancias.
- **[ ] Jaulas/Recursos:** Crear un CRUD y seeder operativo para el catálogo de `resources` (jaulas, mesas de grooming, etc.).

### Servicios y Operadores
- **[ ] Operadores:** Implementar asignación automática de operadores a servicios basada en capacidades y base operativa.
- **[ ] Servicios:** Implementar la capa de pagos a destajo para operadores.

### Configuración del Sistema
- **[ ] Tema de UI:** Reparar persistencia y cambio reactivo de paleta de colores en SysSetInd.
- **[ ] Favicon & Empresa:** Subida de favicon desde UI + bloque de datos generales (Dirección, Teléfono, WhatsApp, Redes) para inyectar en plantillas.
- **[ ] Email Avanzado:** Campos de credenciales SMTP (usuario/password), selección de seguridad (SSL/TLS) y puertos sugeridos.
- **[ ] Zonas Horarias:** Reemplazar selector UTC plano por selector completo con países y diferencias horarias reales.

### Reportes
- **[ ] PDF Presupuesto:** Renderizado imprimible de `Quote` con desglose de servicios, precios y datos fiscales.
- **[ ] PDF Orden de Trabajo:** Hoja operativa para el groomer con servicios, operadores asignados y notas.
- **[ ] PDF Recibo/Factura:** Documento de cierre con saldo final, pagos registrados y branding del negocio.

### Ecosistema Móvil
- **[ ] App Operador:** Continuar desarrollo en `mob_apps/operador` (React 19 + Vite + Tailwind). Conectar con API Laravel.

### General
- **[ ] UI:** Implementar la UI para la gestión de `pet_medical_alerts`.
- **[ ] UI:** Implementar la UI para la gestión de `pet_photos`.
- **[ ] Seguridad:** Añadir el `ojito` de contraseña en el formulario de login.

---
## ✅ Completados (sesión 2026-05-16)
- **[x] Bug `in_process` inexistente:** Corregido a `work_order` en SpaBookingController, DashboardController y dashboard/index.blade.php.
- **[x] `markAsNoShow` / `cancelBooking` firma incorrecta:** Corregidos a `markNoShow($id)` y `cancelBooking($id, reason)`.
- **[x] Bug `@php(expr)` Blade con paréntesis anidados:** Causa raíz identificada y corregida en show.blade.php (7 instancias), _billing_summary.blade.php y invoice.blade.php.
- **[x] PHP 8.5 null arithmetic:** Casts `(float)` + `?? 0` en todos los cálculos financieros de vistas.
- **[x] ReportController relación incorrecta:** `$quote->booking` → `$quote->spaBooking`. Recibo imprimible corregido.
- **[x] PetController::destroy() sin guardia:** Bloquea eliminación con citas activas.
- **[x] Address::phones() morphMany roto:** Columnas eliminadas en migración; relación removida.
- **[x] Botón IMPRIMIR RECIBO en _billing_summary:** `<button>` sin acción → `<a href route()>` funcional.
- **[x] Modal No-Show en AgSpaSho:** Botón y modal añadidos para registrar inasistencias.
- **[x] data-price en _quote_manager:** Mostraba `duration_minutes`; corregido a `suggested_price ?? price`.
- **[x] address-editor.js event delegation:** Reescrito completo. Handlers delegados en `document`, sin dependencia de DOMContentLoaded. Flash visual en lat/lng al importar coordenadas.
- **[x] address-editor.blade.php textos:** Botones e instrucciones actualizados al flujo correcto (Maps manual).

## ✅ Completados (sesión 2026-05-15)
- **[x] Formato hora global:** Centralizado en middleware, eliminadas ternarias dispersas, Flatpickr instalado.
- **[x] AgSpaEdi:** Pre-llenado correcto, edición de servicios en `scheduled`.
- **[x] AgUniInd:** Orden descendente, filtro `active` por defecto, columna Total con saldo real.
- **[x] AgSpaSho:** Modal Cambiar Jaula, balance correcto, precio por servicio en work order.
- **[x] ParseError Blade:** Corregido bug de `@php(expr)` con paréntesis anidados dentro de `@forelse`.
- **[x] Consolidación fuente de verdad:** `acceptQuote()` sincroniza `spa_booking_services` + `total_estimated_price` desde `quote_items`.
