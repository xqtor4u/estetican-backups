# Task List - EstetiCAN Backoffice

Fecha de definición: 2026-03-30
Última actualización: 2026-04-14

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

### General
- **[ ] UI:** Implementar la UI para la gestión de `pet_medical_alerts`.
- **[ ] UI:** Implementar la UI para la gestión de `pet_photos`.
- **[ ] Seguridad:** Añadir el `ojito` de contraseña en el formulario de login.
