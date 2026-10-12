---
title: Referencia técnica del manual backoffice
tags: [manual, app, tecnico]
verificado_contra: estetican@62f6b02 (main, 12/10/2026)
fuente: /opt/www/estetican/apps/backoffice-laravel
---

# Referencia técnica del manual backoffice

Respaldo técnico de las notas de `app/`. Ahí el manual va en lenguaje de negocio; aquí está, por nota, de dónde sale cada cosa en el código, los permisos reales y los huecos.

**Convenciones**
- Rutas: `routes/web.php`.
- Vistas: `resources/views/…`.
- Navegación: `app/Support/Navigation/MainNavigation.php` + `Groups/*Navigation.php`.
- «Admin» = `User::is_super_admin`: `users.role = 'admin'` o rol Spatie `admin` / `super-admin` (`app/Models/User.php` l. ~116).
- El grupo `Route::middleware('superadmin')` (`routes/web.php` l. ~288-340, `EnsureSuperAdmin` → 403 «Solo un administrador del sistema puede acceder a esta sección.») cubre: `system-settings`, `users`, `activity-log` y todo `finances.*` (cuentas, métodos de pago, series, cajas, sesiones, movimientos y reportes de caja).

## Mapa del menú real

| Pestaña | Ítem | Ruta | Gate del menú | Gate de la ruta |
|---|---|---|---|---|
| Clientes | Abrir clientes | `clients.index` | `ver clientes` | `ver clientes` |
| Clientes | Mascotas | `pets.index` | `ver mascotas` | `ver mascotas` |
| Clientes | Sucursales | `branches.index` | `ver sucursales` | `ver sucursales` |
| Clientes | Días inhábiles | `non-working-days.index` | `ver sucursales` | ver: `ver sucursales`; crear: `crear sucursales`; borrar: `eliminar sucursales` |
| Veterinaria | Expediente clínico / Farmacia | `clinical.index`, `items.index?search=Farmacia` | `clinical_module_enabled` + `ver clinico` / `ver catalogo_articulos` | `clinical.module` + `ver clinico` |
| Operación | Agenda | `agenda.index` | `ver agenda` | `ver agenda` |
| Operación | Series recurrentes | `agenda.series.index` | `agenda.series_recurrentes` | igual |
| Operación | Recursos y activos | `resources.index` | `ver sucursales` | `ver/crear/editar sucursales` |
| Operación | Mapa y cobertura | `mapa-zonas.index` | `ver sucursales` | `ver mascotas` |
| Operación | Configuración del sistema | `system-settings.index` | `ver configuracion_sistema` | **superadmin** |
| Catálogos | Servicios | `services.index` | `ver catalogo_servicios` | CRUD `catalogo_servicios` |
| Catálogos | Grupos | `groups.index` | `store_module_enabled` + `ver catalogo_grupos` | `store.module` |
| RH | Operadores | `operators.index` | `ver operadores` | CRUD `operadores` |
| RH | Tipos de operador | `operator-roles.index` | `ver sucursales` | CRUD `operadores` |
| RH | Usuarios | `users.index` | `ver usuarios` | **superadmin** |
| Operaciones del negocio → Inventario | Artículos | `items.index` | `store_module_enabled` + `ver catalogo_articulos` | `store_or_clinical.module` |
| Operaciones del negocio → Finanzas | Catálogo de cuentas, Métodos de pago, Series de documentos, Cajas, Sesiones de caja | `finances.*` | `cobros.registrar` | **superadmin** |
| Reportes → Caja | Resumen de caja, Métodos de pago, Por operador, Pendientes por cobrar, Cierre de turno | `finances.cash-reports.*` | `caja.ver` | **superadmin** |
| Reportes → General | Bitácora de actividad | `activity-log.index` | `ver usuarios` | **superadmin** |
| WhatsApp | Bandeja diaria, Recurrencias, Plantillas de mensaje | `whatsapp.*` | `ver whatsapp` | `ver/crear whatsapp` |

- Hotel (`hotel-reservations.*`, `hotel.module` + CRUD `hotel`) **no está en el menú**. Se llega desde el dashboard y la agenda.
- `/` (`home`): con `ver dashboard` o siendo admin → `dashboard.index`; si no, al primer enlace de `MainNavigation::mobileLinks()`.

---

## Parte 1 — Puesta en marcha

**00 Checklist:** el orden sale de las dependencias:
- `resources.branch_id` es requerido;
- la plantilla de `operator-roles` necesita servicios existentes;
- el usuario con `is_operator` crea el `Operator` (`UserController::syncOperatorRecord`);
- `Operator.branch_id` → `operator_branch_assignments`;
- `CashRegister` necesita la sucursal;
- `items` necesita `store_module_enabled`.

**01 Datos del negocio**
- `SystemSettingController` (index/update/patchField), secciones definidas en `app/Support/SystemSettings/SystemSettings.php` (l. 276-891).
- Botón de cada sección: `'Guardar ' . mb_strtolower(label)`, salvo `ui` → «Guardar configuración».
- Horario y días: `booking_opening_time` / `booking_closing_time` / `booking_open_*` → `BusinessHours`.
- Tolerancia: `booking_grace_minutes` (expuesto en `GET /api/settings/booking`).
- `finanzas_requiere_apertura_caja`: **sin lecturas** fuera de `SystemSettings.php`, así que no tiene efecto.

**02 Sucursales**
- `BranchController`; validación en `rules()` (l. ~119):
  - `code` y `name` requeridos y únicos; dirección y `lat`/`lng` opcionales;
  - `opening_time`/`closing_time` en formato H:i, `required_with` entre sí, cierre `after` apertura;
  - `own_operating_days` + `operating_days[]`.
- `destroy` bloquea si hay `operatorAssignments`: «No se puede eliminar la sucursal porque todavía tiene operadores asignados.»
- **Las FK con `ON DELETE CASCADE` hacia `branches`:** `cash_registers`, `cash_sessions`, `resources`, `operator_checkins`, `item_branch_stocks`, `non_working_days`, `operator_branch_assignments`.
- `CoverageChecker` usa `branches.lat`/`lng` y `coverage_radius_km`.

**03 Días inhábiles**
- `NonWorkingDayController` (index/store/destroy). Duplicado → «Ese día ya está registrado como inhábil.»
- Lo lee **solo** `BookingSeriesService` (`locate` / `rejectReason`). `SpaBookingController` (web) y `Api\BookingController` (móvil) **no** lo consultan.

**04 Recursos y jaulas**
- `ResourceController`: `branch_id` requerido; `resource_type` ∈ cage/room/equipment/other; `name` ≤ 120; `administrative_status` ∈ active/inactive/retired; `operational_status` ∈ available/occupied/cleaning/maintenance/blocked.
- El móvil (`Api\ResourceController::index`) lista `resource_type = cage` con `administrative_status` ∈ active/inactive, **sin filtrar por sucursal**.

**05 Módulos activables**
- `hotel_module_enabled`, `clinical_module_enabled` y `store_module_enabled` → middlewares `hotel.module` / `clinical.module` / `store.module` / `store_or_clinical.module` y las clases de navegación.
- Móvil: `GET /api/items` lleva `store.module` + `ver catalogo_articulos`.
- Valores en producción (12/10/2026): los tres en `true`.

**06 Servicios y precios**
- `ServiceController`. Formulario `services/partials/form.blade.php`:
  - tipos spa / hotel / extra / combo / vaccine;
  - precio, duración, `recurrence_days`;
  - banderas del asistente IA.
- **Quién lo realiza** (`services/edit.blade.php`):
  - `updateEligibility` (`open_to_all_operators`);
  - `addOperatorCapability` / `removeOperatorCapability` (`editar catalogo_servicios`).
- `destroy` con `hasHistoricalUsage()` → inactiva con el mensaje «Este servicio ya se usó antes…».
- El precio se copia a `spa_booking_services.current_price` al agendar, y a `spa_booking_series.template.lines[].price` en las series. Cambiarlo no toca lo ya agendado.

**07 Tipos de operador**
- `OperatorRoleController`:
  - `code` y `name` únicos;
  - `acronym` de 3 caracteres `[A-Z0-9]`;
  - `default_hourly_rate`, `is_active`, `can_login`.
- Plantilla: `updateTemplate` → `operator_role_service_template`.
- **`operator_roles.can_login` no se lee en ningún lado.** El único `can_login` que se usa es el de `users`, en `ApiAuthenticate`.

**08 Usuarios, roles y permisos**
- `UserController` (todo bajo superadmin), vistas `user/create|edit.blade.php`.
- Validación:
  - `name` y `email` únicos, `password` mínimo 6 + `confirmed`;
  - `role` ∈ admin/operator, `is_active`, `can_login`, `is_operator`;
  - `operator_code` único, `operator_role_id`, `branch_id`.
- Permisos:
  - `syncPermissions(permissions[])` (**reemplaza** todos los directos), más `OWN_AVAILABILITY_PERMISSIONS` si `can_manage_own_availability`;
  - `role = admin` → `assignRole('admin')`.
- **Permisos que la interfaz deja asignar:**
  - la matriz CRUD (`ver/crear/editar/eliminar` × `agenda`, `clientes`, `mascotas`, `sucursales`, `catalogo_servicios`, `configuracion_sistema`, `usuarios`);
  - `CAJA_PERMISSIONS` (`caja.ver`, `caja.abrir`, `caja.cerrar`, `caja.movimientos.crear/editar/revertir`);
  - `agenda.ver_todas`;
  - `*_disponibilidad_propia`.
- **Permisos que existen (75 en total) pero no tienen casilla:** `cobros.registrar`, `agenda.series_recurrentes`, `agenda.forzar_horario`, `ver/crear/editar/eliminar operadores`, `*_whatsapp`, `*_hotel`, `*_clinico`, `clinico.firmar`, `alergias.administrar`, `*_catalogo_articulos`, `*_catalogo_grupos`, `ver dashboard`, `asientos.aprobar`, y los legados `manage-*` y `administrar todo`.
- **Roles Spatie:** existen `admin` (71 permisos) y `veterinario` (10), pero no hay interfaz para asignar un rol que no sea `admin`.
- `syncOperatorRecord`: con `is_operator`, crea o actualiza un `Operator` copiando nombre, teléfono, dirección, `hire_date`, `is_active`, etc. **No hay forma desde la interfaz de ligar un usuario a un `Operator` ya existente.**
- `users.branch_id` lo usa `BranchResolver::forNewBooking` (sucursal de la cita y horario de la cuadrícula del móvil) y Caja.
- **Usuarios en producción** (12/10/2026): 4 admins y 2 no-admin, sin permisos fuera de los de la interfaz.

**09 Operadores**
- `OperatorController`:
  - `code` único y `first_name` requerido;
  - `role_ids[]` → `operator_role_assignments`, `branch_id` → `syncPrimaryBranch`;
  - `hourly_rate`, `weekly_schedule[0..6]` (enabled / start_time / end_time) → `operator_weekly_schedules`.
- Al crear, el horario se precarga con el general en los 7 días, así que `isOutsideWorkingHours` siempre aplica.
- Capacidades: `syncServiceCapabilities` («Servicios que realiza el operador actualizados.»).
- `destroy`: **borrado físico**, sin soft delete. Las FK `spa_bookings.operator_id`, `spa_booking_services.operator_id`, `quote_items.operator_id` y `executed_services.operator_id` quedan en `SET NULL`; `clinical_visits.operator_id` está en `RESTRICT`, así que el borrado falla si el operador tiene visitas clínicas.

**10 Métodos de pago y cajas**
- `PaymentMethodController`: `type` ∈ cash/card/transfer/crypto/gateway, `requires_reference`, `account_id`.
- `CashRegisterController`: `destroy` bloquea si hay sesiones.
- El móvil, en `Api\CashController::openSession`, usa la sucursal del usuario y la **primera** caja de esa sucursal → «Esta sucursal no tiene ninguna caja configurada.»
- Métodos activos en producción: Efectivo, Tarjeta de débito, Tarjeta de crédito, Transferencia SPEI.

**11 Artículos y stock**
- `ItemController` y `ItemMovementController` (`store` / `transfer`, con `store.module`).
- Movimientos inmutables, de tipo entrada / ajuste (suma / resta) / pérdida / transferencia / consumo por servicio.
- El móvil filtra la existencia por la sucursal del **check-in** (`Api\ItemController`).

**12 WhatsApp**
- `BookingMessageController` (bandeja), `RecurrenceMessageController` y `WhatsAppTemplateController`.
- Contextos de plantilla: cita / recurrencia / cliente / general. Desde una cita, el móvil muestra cita + cliente + general (`Api\ClientWhatsAppController::contextsFor`).
- El envío por WhatsApp abre `wa.me` y alguien lo confirma a mano. El correo sale desde el servidor.
- **Automático:** `whatsapp:enviar-recordatorios-cita`, programado cada 15 min (`routes/console.php`) → `MetaWhatsAppSender`. En producción, `whatsapp_messaging_enabled` está vacío (apagado), `phone_number_id` vacío y `template_name` null, así que no envía nada. Pendiente BL-024b (alta en Meta).

## Parte 2 — Operación

**01 Tablero**
- `DashboardController::index` (`ver dashboard`):
  - citas del día por estado;
  - huéspedes de hotel (`scheduled` que cubren hoy);
  - totales de clientes y mascotas;
  - `Payment` del día por `destination`;
  - próximas citas.
- **Sin filtro de sucursal ni `visibleTo`.** `ver dashboard` no se puede asignar desde la interfaz: en la práctica, solo admins.

**02 Agenda**
- `SpaBookingController::index`: filtros `search`, `status[]`, `date_scope`, `cal_view`, `date`, `por_fijar`. **No filtra por sucursal ni por operador.**
- Acciones del detalle (`agenda/show.blade.php`):
  - Reprogramar / Editar (`agenda.edit` / `update`, `editar agenda`, con «Agendar de todas formas, fuera del horario del operador»);
  - cambiar jaula (`#modalChangeResource`);
  - cancelar (`agenda.cancel`);
  - no se realizó (`agenda.no-show` / `unfulfillable`);
  - fijar / descartar.

**03 Series recurrentes**
- `SpaBookingSeriesController` (index / show / pause / cancel / extend), permiso `agenda.series_recurrentes`.
- `BookingSeriesService::extend` tiene tope en `RecurrenceRule::MAX_OCCURRENCES = 60`.
- `series:cierre-diario` borra las citas no fijadas que ya vencieron.

**04 Clientes y mascotas**
- `ClientController` (resource), `PetController::updateOwner` (`pets.owner.update`: «Dueño de la mascota actualizado.» / «El dueño ya era ese cliente.»).
- **No existe fusión de clientes.**
- `ClientController::destroy` hace `$client->delete()` **sin candado** (botón «Eliminar» en `clients/partials/index-blocks.blade.php`, con `data-confirm="¿Eliminar?"`). FK en cascada:
  - desde `clients`: `pets`, `payments`, `cash_ledgers`, `bank_ledgers`, `phones`, `addresses`, `notification_queues`;
  - desde `pets`: `spa_bookings`, `hotel_reservations`, `clinical_visits`.

  Un clic borra el historial operativo y financiero del cliente.
- WhatsApp usa `PhoneNormalizer::bestPhoneFor`: el primer `mobile` y, si no hay, el primero de cualquier tipo.

**05 Hotel**
- `HotelReservationController`: index / create / store / show / edit / update / cancel.
- Validación: `pet_id`, `resource_id` opcional, `start_at`, `end_at` posterior al inicio.
- Estado inicial `scheduled`; `cancel` → `cancelled`. **Nada lo pasa a `completed`.**
- Sin check-in ni check-out reales, sin precio y sin cobro. Sin entrada en el menú. Sin presencia en el móvil.

**06 Caja**
- `CashSessionController`: open / store («Sesión abierta.», «La caja ya tiene una sesión abierta.»), show, close / doClose («Sesión cerrada. Caja cuadrada: sin diferencia.» o «Diferencia: $X sobrante/faltante»), y movimientos.
- **`CashSessionExpectedAmountService::paymentsForPeriod`** suma **todos** los `Payment`, **sin filtro de sucursal**, desde el `closed_at` anterior de la **misma caja** hasta el corte. Con varias sucursales, el «Esperado» incluye el efectivo de las otras.

**07 Reportes**
- `CashReportController` + `CashReportService`: resumen, métodos de pago, por operador, pendientes y cierres, con PDF y correo. **Sí** filtran por `branch_id`; un no-admin queda forzado a su `users.branch_id`, aunque la ruta web es superadmin.
- `ActivityLogController` (superadmin).
- No hay reportes de servicios, productividad ni clientes.

## Parte 3 — Cambios

**01 Ausencia o permiso**
- `OperatorUnavailabilityController::store` (`starts_at`, `ends_at` posterior, `reason`) → «Bloqueo de no disponibilidad registrado.»
- No toca las citas que ya existían.
- El móvil la ve vía `OperatorAvailabilityChecker::hasTimeOff` / `unavailabilityWindows`.

**02 Cambiar horario**
- `OperatorController::update` → `weekly_schedule`. No reprograma nada.

**03 Baja**
- `UserController::update` con `is_active = 0` y `can_login = 0`; `syncOperatorRecord` deja `operators.is_active = false`.
- `UserController::destroy`: con `hasHistoricalDependencies()` desactiva en vez de borrar; también bloquea borrarse a uno mismo.
- **Login web** (`app/Http/Controllers/LoginController.php::login`): solo `Auth::attempt`, **no revisa `is_active` ni `can_login`**. Tampoco hay middleware web que lo revise; solo `ApiAuthenticate` para el móvil.
- **Login móvil** (`Api\AuthController::login`): revisa `is_active` («Usuario inactivo»). `ApiAuthenticate` revisa `is_active` y `can_login`.

**04 Cambiar precio**
- `ServiceController::update` → «Actualizar servicio». Ver nota 06 sobre las copias del precio.

**05 Sucursal nueva**
- Resumen de las dependencias de la Parte 1.

---

## Diferencias y huecos

### No existe
1. **Constructor de roles.** Los permisos van por usuario: matriz más casillas. No hay roles armables ni plantillas de permisos.
2. **Casillas para varios permisos** que el sistema sí revisa:
   - `cobros.registrar`, `agenda.series_recurrentes`, `agenda.forzar_horario`;
   - `*_operadores`, `*_whatsapp`, `*_hotel`, `*_clinico`, `*_catalogo_articulos`, `ver dashboard`.

   En la práctica, cobrar, ver la lista de operadores, WhatsApp, hotel, veterinaria, artículos y el tablero son solo de admin.
3. **Fusión de clientes duplicados.** Alternativa: `Cambiar dueño` por mascota.
4. **Filtro por sucursal u operador** en la Agenda web y en el Tablero.
5. **Hotel:** entrada y salida reales, cobro, precio, paso a `completed`, entrada en el menú, presencia en el móvil.
6. **Ligar un usuario a un `Operator` existente** desde la interfaz.
7. **Reportes** de servicios, productividad o clientes.

### Funciona distinto a lo esperado
8. **Los días inhábiles solo afectan a las series.** Una cita suelta (web o móvil) se puede agendar en festivo, y el móvil no muestra el festivo. El ejemplo del encargo («si marcas un día festivo, en el mob ese día aparece bloqueado al agendar») **no** es lo que pasa.
9. **La sucursal de una cita** sale de `users.branch_id` del que agenda, no del check-in.
10. **El celular muestra las jaulas de todas las sucursales.**
11. **Los datos de un operador** (nombre, teléfono, activo…) se pisan con los del usuario cada vez que se guarda el usuario.
12. **`syncPermissions`** reemplaza todos los permisos directos del usuario al guardar el formulario. Un permiso dado por fuera de la interfaz (consola) se pierde al guardar.
13. **El menú muestra ítems que dan 403** a un no-admin: Configuración del sistema (con `ver configuracion_sistema`), Usuarios y Bitácora (`ver usuarios`), Finanzas (`cobros.registrar`) y Reportes de caja (`caja.ver`).

### A medias
14. **Recordatorios automáticos de WhatsApp:** la tarea está programada, pero falta el alta en Meta (BL-024b), la plantilla aprobada y el interruptor, que está apagado.
15. **«Requerir apertura de caja para cobrar»:** el ajuste existe pero nadie lo lee.
16. **`operator_roles.can_login`** («Permitir loguearse al sistema») no tiene efecto.
17. **Corte de caja multi-sucursal:** el esperado suma el efectivo de todas las sucursales (ver 06 Caja).

### Posibles emergencias (para decidir, no se tocaron)
18. **Seguridad — login web sin revisar estado:** un usuario en «Baja / Inactivo» o sin «Permitir acceso al Backoffice» **sigue entrando al backoffice** con su contraseña. El manual indica cambiar la contraseña como mitigación.
19. **Integridad de datos — «Eliminar» cliente:** con una confirmación simple, borra en cascada mascotas, citas, estancias, visitas clínicas, pagos y movimientos de caja y banco del cliente.
20. **Móvil — Nueva cita para no-admins:** `MobCitaNueva` hace `fetch('/api/operators').then(r => r.json()).then(setOperators)` sin revisar `r.ok`. Para cualquier no-admin la respuesta es un 403 en JSON, porque el `fetch` global manda `Accept: application/json`. Entonces `operators` queda como objeto y `operators.find(…)` en el resumen (l. ~1187) truena al elegir la hora. Lo mismo pasa con `/api/services` sin «Catálogos: Ver». `MobCitaDet` ya está protegido; `MobCitaNueva` no.

    Confirmado leyendo el código, **no reproducido en ejecución**. Corrige la nota 13 de `referencia-tecnica-mob.md`: no depende de roles, sino de que `ver operadores` no se puede asignar a un no-admin.
