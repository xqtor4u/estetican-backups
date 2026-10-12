---
title: Flujo real de recepción y agendado de citas — app móvil (mov.estetican.org)
tags: [estetican, mov, manual, agenda, recepcion]
verificado_contra: estetican@6913582 (main, 12/10/2026)
fuente: /opt/www/estetican — mob_apps/operador/ (React) + apps/backoffice-laravel/ (API)
---

# Flujo real de recepción y agendado — app móvil (`mov.estetican.org`)

> [!info] Cómo leer este manual
> Todo lo de aquí se verificó leyendo el código fuente de producción (`/opt/www/estetican`, commit `6913582`), no con capturas ni suposiciones.
> - **Componente** = archivo React en `mob_apps/operador/src/`.
> - **Backend** = archivo PHP en `apps/backoffice-laravel/` (`archivo:línea` → función).
> - Los textos entre «comillas angulares» son **literales** de la interfaz, tal como los ve el operador.
> - Las cajas `[!warning] Diferencia` marcan lo que el código hace distinto de lo esperado o pasos que **no existen**.

## Mapa de pantallas

| Paso | Ruta | Componente | `screenTag` (etiqueta interna) |
|---|---|---|---|
| Check-in a sucursal | menú inferior «Menú» | `CheckinWidget.tsx` (dentro de `MenuDrawer` en `App.tsx`) | — |
| Agenda (punto de entrada) | `/agenda` | `admin/GlobalAgenda.tsx` | `MobAgGbl` |
| Buscar mascota | `/mascotas` | `admin/PetSearch.tsx` | `MobPetSrch` |
| Búsqueda universal | `/buscar` | `admin/UniversalSearch.tsx` | `MobBuscar` |
| Ficha de mascota / alta | `/mascotas/:id`, `/mascotas/nuevo` | `admin/PetDetail.tsx` (`NewPetForm`) | `MobPetDet` / `MobPetNew` |
| Elegir / crear dueño | `/clientes/seleccionar`, `/clientes/nuevo` | `admin/ClientSearch.tsx`, `admin/ClientDetail.tsx` (`NewClientForm`) | `MobCliNew` |
| Nueva cita (servicio, horario, recurrencia) | `/mascotas/:id/cita/nueva` | `admin/MobCitaNueva.tsx` | `MobCitaNueva` |
| Hoja de servicios | (modal) | `admin/ServicePickerSheet.tsx` | — |
| Cuadrícula de horarios | (dentro de Nueva cita) | `admin/DraggableSlotGrid.tsx` | — |
| Bloquear mi horario (ausencias) | `/configuracion/disponibilidad` | `admin/MobUnavailability.tsx` | — |

Rutas declaradas en `App.tsx` → `App()` (bloque `<Routes>`).

---

## Paso 0 — Check-in del operador a la sucursal

**Dónde:** botón «Menú» de la barra inferior → tarjeta de check-in arriba del menú (`App.tsx` → `MenuDrawer`, que monta `<CheckinWidget />`).

1. Sin check-in, la tarjeta dice «Sin check-in activo» y a la derecha «Registrar».
2. Al tocarla aparece «Selecciona sucursal», un selector con «— Sucursal —» y los botones «Cancelar» / «Check-in» (`CheckinWidget.tsx` → `doCheckin`).
   - La lista de sucursales viene de `GET /api/branches` (`OperatorController::branches`, requiere permiso `ver sucursales`): **todas** las sucursales por nombre, sin filtrar por activas ni por las asignadas al operador.
3. Ya con check-in: «Check-in activo», nombre de la sucursal, «desde HH:MM» y botón «Salir» (check-out).

**Backend** (`app/Http/Controllers/Api/CheckinController.php`):
- `checkin()` (l. 36): valida `branch_id` requerido y existente. Si ya hay un check-in abierto **en otra sucursal**, lo cierra solo (`auto_checkout = true`) y guarda una `transgression_note` del tipo «Check-out automático por check-in en "X" a las HH:MM…». Si es la misma sucursal, responde «Ya estás registrado en esta sucursal.» sin crear otro.
- `status()` (l. 14) y `checkout()` (l. 86): consultan o cierran el check-in abierto (`operator_checkins.checked_out_at` nulo).
- Estas tres rutas (`routes/api.php` l. 104-106) solo piden estar autenticado, no un `permission:`.

> [!warning] Diferencia — el check-in NO decide la sucursal de la cita
> Se esperaba que la sucursal activa del operador saliera de su check-in. **No es así.** El check-in solo se lee en dos lugares:
> - `OperatorController::index`: el panel de equipo muestra quién está registrado y dónde.
> - `ItemController`: el stock de artículos se filtra por la sucursal del check-in.
>
> La sucursal de una cita se resuelve en `app/Support/Branches/BranchResolver.php` → `forNewBooking()` (l. 27), en este orden:
> 1. `users.branch_id` del **usuario que agenda** (sucursal fija en su perfil);
> 2. si no tiene, la sucursal del **operador responsable**, solo si tiene exactamente una;
> 3. si no, la **única sucursal activa**, cuando solo hay una;
> 4. si no, `null`.
>
> La aplica `SpaBookingObserver::creating()` (l. 37) a toda cita nueva. Agendar sin check-in funciona igual: la pantalla de Nueva cita no lo pide ni lo consulta.
>
> Además, el comentario de `CheckinWidget.tsx` dice que el widget también se usa «en la pantalla de Caja». Hoy **solo** se monta en el menú (`App.tsx`): ese comentario quedó desactualizado.

---

## Paso 1 — Buscar la mascota

### Entrada A (la normal): desde la Agenda
`GlobalAgenda.tsx` → `startNewCita()` (l. 436).
- Arriba a la derecha, botón «Cita» (solo si el usuario tiene `can_view_pets`).
  - Si el usuario es operador (`operator_role`), primero se abre una hoja con «Nueva cita» («Agendar servicio para una mascota») y «Bloquear mi horario» («Vacaciones o permiso — no se te podrá agendar»).
  - Un día sin citas muestra «Sin citas para este día» y el botón «Nueva cita».
- `startNewCita` recuerda **la fecha que se está viendo en la Agenda** y el **operador filtrado** (si no es "Todos") con `setCitaPresetDate` / `setCitaPresetOperator` (`navState.ts`), y lleva a `/mascotas`.

### Buscar
`PetSearch.tsx`:
- Encabezado «Elegí la mascota» con subtítulo «Cita para {fecha larga}» cuando se viene de la Agenda; si no, «Mascotas».
- Campo «Buscar por nombre, raza o dueño…». Busca 300 ms después de dejar de escribir (`fetchPets`, l. 82) → `GET /api/pets?search=…`.
- **Backend:** `PetController::index` (l. 184) usa `TokenSearch` sobre nombre, raza y especie de la mascota y sobre nombre, apellidos y correo del dueño. Solo mascotas `visible()`. Cada resultado trae «próxima visita» (cita `scheduled`/`work_order` futura).
- Sin resultados: «Sin resultados para "{texto}"».
- Al tocar una mascota (`goToPet`, l. 116): si se vino de la Agenda, salta **directo** al formulario «Nueva cita»; si no, abre la ficha.

> [!warning] Diferencia — la lista de Mascotas no busca por teléfono
> `PetController::index` **no** incluye `phones.number`. Para encontrar una mascota por el celular del dueño hay que usar la lupa del encabezado («Buscar», `ScreenHeader.tsx` → `/buscar`, `UniversalSearch.tsx`, campo «Mascota, cliente, teléfono o cita…»). `SearchController::searchPets` sí busca en `client.phones.number`.
> Pero desde la búsqueda universal se llega a la **ficha** de la mascota, no directo a Nueva cita. Ahí se agenda con el ícono `event_add` (título «Agregar cita», `PetDetail.tsx` l. 515).

### Si la mascota no existe → alta (incluye el dueño)
- En `PetSearch`, botón «Nueva» → **antes que nada pide el dueño**: navega a `/clientes/seleccionar` con `returnTo: '/mascotas/nuevo'`.
- `PetDetail.tsx` (`isNew`, l. 453): si se llega a «Nueva mascota» sin dueño, muestra «Primero selecciona al dueño» / «Busca un cliente existente o dale de alta nuevo» y el botón «Buscar / crear dueño».
- El alta de cliente se detalla en el **Paso 3**. Al guardarlo, se regresa aquí con el cliente ya elegido.
- Formulario `NewPetForm` (l. 109), encabezado «Nueva mascota», botón «Guardar»: «Nombre *», «Especie», «Raza», «Sexo», «Tamaño», «Peso (kg)», «Color de pelaje», «Fecha de nacimiento», «Microchip», «Tatuaje», «Esterilizado», «Notas», más la foto.
- **Backend:** `PetController::store` (l. 102) exige solo `client_id` y `name`. Valida `sex` ∈ male/female, `size` ∈ small/medium/large/giant y `weight_kg` entre 0.1 y 200. Si viene peso, lo registra en `pet_weights`; si viene foto, la guarda como foto de perfil.
- Al guardar, lleva a la **ficha** de la mascota (`/mascotas/{id}`), no a Nueva cita. Desde ahí se agenda con «Agregar cita» (`event_add`). La fecha elegida en la Agenda se conserva porque `consumeCitaPresetDate` solo se consume al abrir Nueva cita.

> [!warning] Diferencia — el orden real es dueño → mascota, no mascota → dueño
> El flujo esperado (mascota → servicio → alta de cliente si hace falta) **no existe** en el móvil. Una mascota nueva no se puede crear sin dueño: el backend exige `client_id` (`required|exists:clients,id`) y la interfaz manda a elegir o crear al dueño **antes** de mostrar el formulario de la mascota. Tampoco se puede elegir servicio antes de que exista la mascota, porque Nueva cita vive en `/mascotas/:id/cita/nueva`.

---

## Paso 2 — Servicio: disponibilidad y precio

**Pantalla:** «Nueva cita» (`MobCitaNueva.tsx`), con subtítulo «{mascota} · {especie}» y una franja con la foto, especie, raza, tamaño («Pequeño/Mediano/Grande/Gigante») y peso.

Al abrir, carga (l. 186-206):
- la mascota (`GET /api/pets/{id}`);
- el catálogo (`GET /api/services`);
- los operadores (`GET /api/operators`);
- las jaulas (`GET /api/resources`);
- el horario (`GET /api/settings/booking`);
- y, por cada servicio, sus operadores calificados (`GET /api/services/{id}/operators`, l. 216).

### Agregar servicios
- Sección «Servicios», botón «Agregar servicio» → hoja `ServicePickerSheet.tsx` con título «Agregar servicio». Si hay más de 6 servicios aparece el buscador «Buscar servicio…». Cada opción muestra nombre, `$precio` y duración; botón «Cancelar». Si ya se agregaron todos: «Ya agregaste todos los servicios.»
- Cada servicio queda como una **línea** (`addLine`, l. 418) con:
  - **duración** editable con −/+ en pasos de 5 min (de 5 a 480 min); arranca en la del catálogo;
  - **precio** editable (campo numérico); arranca en el del catálogo;
  - **operador**: selector «Elegir operador…» con solo los calificados para ese servicio. Si no hay ninguno: «Ningún operador activo está calificado para este servicio»;
  - botón «Empieza {hora}» para elegir en qué servicio trabaja la cuadrícula. La 1ª línea lleva la marca «responsable» y la leyenda «inicio de la cita».
- Operador sugerido (`defaultOperatorFor`, l. 410): el filtrado en la Agenda, o el propio usuario si es operador, siempre que esté calificado. Si no, el único calificado cuando hay exactamente uno.
- Encabezado de la sección: duración total y suma de precios. Si la cita tiene huecos, agrega «(trabajo X)».

### De dónde sale el precio
- `ServiceController::index` (l. 14): servicios con `is_active = true`, con **un solo `price` por servicio**.
- `BookingController::store` usa el precio que manda el móvil por línea. Si no viene, cae al `services.price` del catálogo. `total_estimated_price` = suma de las líneas.

> [!warning] Diferencia — el precio no depende de la mascota
> No hay precio por tamaño, raza ni peso: en el código no existe ninguna tabla ni regla de precios por tamaño. El tamaño y el peso solo se **muestran** en la franja de la mascota, para que el operador ajuste el precio **a mano** si corresponde. El backend acepta cualquier precio ≥ 0.

### Quién puede hacer el servicio
- `GET /api/services/{id}/operators` → `ServiceController::operators` (l. 39) → `OperatorServiceResolver::operatorsFor`: plantilla del rol de puesto, más capacidades directas otorgadas, menos las revocadas; o todos si el servicio es `open_to_all_operators`.
- Al guardar, `BookingController::store` lo vuelve a validar (`canPerform`). Si falla: «{Operador} no está calificado para {Servicio}.»

---

## Paso 3 — Alta de cliente (dueño) si hace falta

**Entrada:** «Nueva» en Mascotas → «Seleccionar dueño» (`ClientSearch.tsx`, campo «Buscar por nombre o teléfono…») → botón «Nuevo» → «Nuevo cliente» (`ClientDetail.tsx` → `NewClientForm`, l. 115).

Campos (literales): «Nombre(s) *», «Apellido paterno», «Apellido materno», «Correo electrónico», «Dirección», «Ciudad», «Estado», «Código postal», «Teléfonos *» (tipo «Celular / Casa / Trabajo / Otro», número y «Ext.») y «Notas». Botón «Guardar».

Leyenda bajo los teléfonos: «El primer teléfono **Celular** de la lista es el que se usa para WhatsApp/SMS — usa las flechas para reordenar por importancia.»

**Validación en el móvil** (`save`, l. 130):
- «El nombre es obligatorio»;
- «Debe capturarse al menos un teléfono»;
- «Cada teléfono debe tener {N dígitos}» (formato según `GET /api/settings/phone-format`).

**Backend:** `ClientController::store` (l. 80):
- `first_name` requerido;
- `phones` (arreglo, mínimo 1) o `phone` legado;
- cada número pasa por la regla `ValidPhoneNumber` de la configuración;
- `type` ∈ mobile/home/work/other (por defecto `mobile`);
- el orden se guarda en `sort_order`.

Al guardar regresa a «Nueva mascota» con el dueño ya elegido.

> [!warning] Diferencia — no se exige un celular
> Lo mínimo es **un teléfono de cualquier tipo**, no un celular. El formulario arranca con la primera fila en «Celular», pero si el operador la cambia a «Casa» se guarda sin aviso, y el cliente queda sin número utilizable para WhatsApp. Tampoco se revisa si el teléfono ya existe en otro cliente (no hay control de duplicados en `store`).

---

## Paso 4 — Calendario: fecha y horario

### Fecha
- Sección «Fecha»: chips «Hoy», «Mañana» y los 5 días siguientes, más un ícono de calendario («Elegir otra fecha del calendario») que abre el selector nativo.
- Si se vino de la Agenda, arranca en **el día que se estaba viendo** (`consumeCitaPresetDate`).

### Horario (cuadrícula)
Sección «Horario» (si hay varias líneas, «Horario · {servicio}» y la nota «Cambiá de servicio con el botón «Empieza» de cada uno.»).

- Sin operador en la línea: «Elige el operador de {servicio} para ver su disponibilidad.» Mientras carga: «Verificando disponibilidad…».
- Cuadrícula de **30 min** (`DraggableSlotGrid.tsx`). Un toque fija el inicio; arrastrar cambia también la duración.
- Rango de la cuadrícula: apertura–cierre de `GET /api/settings/booking` → `SettingController::booking` (l. 15) → `BusinessHours::for(BranchResolver::forNewBooking(null))`. Es el horario de **la sucursal del usuario que agenda** (o el general de Configuración si no tiene); por defecto 09:00–19:00.
- Leyenda: «Seleccionado», «Duración», «Bloque usado a medias», «Ocupado», «Bloqueado», «Otro servicio de esta cita», «No cabe la duración» y, con jaula, «Jaula ocupada».
- Qué marca la cuadrícula como ocupado (`loadOccupied`, l. 226):
  - **Citas del operador** — `GET /api/agenda?date=…&operator_id=…` (`AgendaController::index`), con las ventanas reales por línea de servicio (`busy`).
  - **Ausencias** (vacaciones/permisos) — `GET /api/agenda/unavailabilities` (`AgendaController::unavailabilities`, l. 166). Se capturan en «Bloquear mi horario» (`MobUnavailability.tsx`).
  - **Choque con otra línea de esta cita con el mismo operador** (`slotWouldConflict`, l. 380); también si la línea se pasa del cierre.
- Mensajes al pasar sobre un bloque, por ejemplo:
  - «Operador no disponible (vacaciones/permiso)»;
  - «Otra cita termina/empieza a mitad de este bloque — no se puede agendar acá»;
  - «El servicio no cabe acá (se traslapa o pasa del cierre)».
- Selector opcional de jaula: «Sin jaula» o una jaula. Solo **avisa** (punto en la cuadrícula); no bloquea.
- «Notas» con el texto de ejemplo «Instrucciones especiales, cuidados, comportamiento…».
- Botón inferior según el estado:
  - «Agendar cita» si falta algo;
  - «Revisa los horarios» si algo choca;
  - «Agendar HH:MM → HH:MM» cuando todo está listo;
  - «Crear serie desde HH:MM…» si se activó la repetición;
  - «Guardando cita…» mientras guarda.

  Arriba a la derecha también está «Agendar».
- Errores del lado del móvil (`save`, l. 465):
  - «Agrega al menos un servicio para continuar»;
  - «Cada servicio necesita un operador asignado»;
  - «Toca un horario para el primer servicio»;
  - «La cita se pasa del horario de cierre»;
  - «Algún servicio se traslapa con otra cita o un bloqueo del operador».

### Qué valida el servidor al guardar
`POST /api/bookings` → `BookingController::store` (`app/Http/Controllers/Api/BookingController.php`, l. 218), permiso `crear agenda`.

1. **Campos obligatorios:** `pet_id` y `operator_id` (el responsable = operador de la 1ª línea), y `scheduled_at` (`Y-m-d H:i:s`). Opcionales: `services[]` (`id`, `price`, `operator_id`, `duration_minutes` 5-480, `offset_minutes`), `notes` (≤ 1000), `resource_id`, `override_availability` y los campos de repetición.
2. **Horario y días de la sucursal:** `BusinessHours::isWithin()` sobre la sucursal de `BranchResolver::forNewBooking(operator_id)`. Si falla, responde 422:
   - «El negocio no abre los {día}s.» (días de `branches.operating_days`, o los `booking_open_*` generales);
   - «La hora elegida está fuera del horario operativo (HH:MM–HH:MM).»
3. **Calificación** de cada operador para su servicio (ver Paso 2).
4. **Choque entre líneas de la misma cita** con el mismo operador: «"A" y "B" se traslapan y tienen el mismo operador — dale a uno otro horario u otro operador.»
5. **Disponibilidad por línea:** `OperatorAvailabilityChecker::validateSequentialAssignments`. Cada operador se valida **solo en su tramo** (inicio + offset, duración):
   - `hasConflict` → «El operador asignado a "X" ya tiene una cita en ese horario.» (ignora citas `cancelled`/`no_show` y líneas canceladas o no realizadas);
   - `isOutsideWorkingHours` → «…no labora en el horario indicado.» Revisa el horario semanal del operador (`operator_weekly_schedules`). Es **opcional**: si el operador no tiene ninguna fila capturada, no restringe nada;
   - `hasTimeOff` → «…no está disponible en ese periodo (vacaciones/permiso).»

   Una línea sin operador («por asignar») no se valida.
6. **Forzar horario:** solo «no labora en el horario indicado» se puede saltar. Si el usuario tiene `agenda.forzar_horario` (o es super admin), el banner rojo «No se pudo agendar» ofrece «Agendar de todas formas» (`override_availability`). Los choques de agenda y las vacaciones/permisos no se pueden forzar.
7. **Se guarda en una sola transacción** la cita (`status = scheduled`) con sus líneas `spa_booking_services` (precio, operador, offset y duración). Después se asigna la jaula: si está ocupada, la cita **sí** queda guardada, con el aviso «La cita quedó agendada pero la jaula no se asignó: ya está ocupada en esa ventana.»
8. **Avisos que no bloquean:**
   - cobertura (`CoverageChecker`) → banner «Fuera de zona habitual» («Esta mascota está a X km de {sucursal}, fuera del radio de cobertura de Y km.»);
   - vacunas (`VaccinationEligibilityChecker`) → banner «Vacunas pendientes» («Esta mascota no tiene vigente: …»).
9. **Éxito:** «¡Cita agendada!», «{fecha larga} a las HH:MM — HH:MM» y el nombre de la mascota. A los 1.5 s (4 s si hubo aviso) regresa a la **Agenda del día agendado** (aunque el texto diga «Regresando a la ficha…»).

> [!warning] Diferencias — disponibilidad en una cita normal (no recurrente)
> - **Festivos / días inhábiles NO se revisan.** `NonWorkingDay` (día inhábil general o por sucursal, que se captura en el backoffice web en «Días inhábiles») solo se consulta en `BookingSeriesService` (citas recurrentes). En `BookingController::store` no aparece: una cita suelta **se puede agendar en un festivo**.
> - **Los días cerrados no se ven en la cuadrícula.** El móvil ignora `operating_days` de `/api/settings/booking`: deja elegir un domingo cerrado, muestra la cuadrícula completa y el rechazo («El negocio no abre los domingos.») llega solo al guardar.
> - **El horario semanal del operador no se dibuja.** La cuadrícula solo marca citas y ausencias; las horas fuera de su turno se ven libres y se rechazan al guardar.
> - **El horario de la sucursal solo revisa la hora de inicio.** `BusinessHours::isWithin` compara la hora de arranque contra apertura y cierre; que la cita *termine* después del cierre lo frena solo el móvil («La cita se pasa del horario de cierre»), no el servidor.
> - **No se rechazan fechas pasadas** en la cita normal (sí en las series: «La fecha ya pasó.»).
> - **No se revisa si la mascota ya tiene otra cita a esa hora** en la cita normal (sí en las series: «La mascota ya tiene otra cita en ese horario.»).
> - **Con usuario restringido** (sin `agenda.ver_todas`), `/api/agenda` y `/api/agenda/unavailabilities` ignoran el `operator_id` pedido y devuelven solo lo **propio** (`AgendaController::index`, l. 35, y `unavailabilities`, l. 166). Si ese usuario agenda para otro operador, la cuadrícula le muestra **su** ocupación, no la del otro. El servidor igual valida al operador correcto al guardar.

---

## Paso 5 — Cita recurrente («Repetir esta cita»)

**Visible solo con permiso** `agenda.series_recurrentes` (o super admin): `can_create_series` en el móvil, revalidado en `BookingController::store` con «No tienes permiso para crear citas recurrentes.» (403).

### En la pantalla (`MobCitaNueva.tsx`)
- Casilla «Repetir esta cita». Al marcarla, propone la frecuencia del servicio (`services.recurrence_days`, la menor de las líneas): 7, 15 o 30 si coincide; si no, «Otro» con esos días.
- «Cada»: «7 días» · «15 días» · «30 días» · «Otro» (campo «días», 1-365) · «Mismo día cada mes» (con la nota «Ej.: si eliges el 1er lunes, se repite el 1er lunes de cada mes.»).
- «Vigencia»: «6 meses» · «1 año» · «Hasta…» (fecha).
- Nota: «Las que caigan en festivo, día cerrado u horario ocupado se recorren solas. Todas quedan por fijar en la agenda.»
- Botón «Crear serie desde HH:MM → HH:MM».

### Qué genera (backend)
- `RepeatInput::rules/rule/endsOn` (`app/Domain/Planning/Series/RepeatInput.php`):
  - `repeat_mode` 7/15/30/custom → `RecurrenceRule::everyNDays(n)`;
  - `monthly` → `RecurrenceRule::monthlyWeekday(semana, día)`. El móvil **no manda** la semana, así que se deduce de la fecha de la 1ª cita (`weekOfMonth`): 1er/2º/3er/4º; la 5ª ocurrencia cuenta como «último».
  - Vigencia: `6m` = +6 meses; `1y` = +1 año; `date` = hasta ese día inclusive.
- Requisito: al menos un servicio («Una cita recurrente necesita al menos un servicio.»). La **1ª fecha** pasa por **todas** las validaciones de la cita normal (Paso 4) antes de crear la serie.
- `RecurrenceRule::occurrences()` (l. 89) genera las fechas desde la 1ª (incluida) hasta la vigencia, con un **tope de 60 citas** (`MAX_OCCURRENCES`).
- `BookingSeriesService::create()` arma un registro `spa_booking_series` (en estado pendiente de revisión) y **una cita real por fecha**, en una sola transacción. Cada cita lleva:
  - `series_id`;
  - el mismo operador, líneas, precios y jaula;
  - la sucursal resuelta con la misma regla de `BranchResolver`.

### Cómo evita días no hábiles (`BookingSeriesService::locate` / `rejectReason`)
Cada fecha se revisa contra, en orden:
1. «La fecha ya pasó.»
2. Día inhábil (`NonWorkingDay::covering`, general o de la sucursal) → «Día inhábil: {motivo}.»
3. Días y horario de la sucursal (`BusinessHours::rejectionFor`).
4. «Choca con otra cita de la misma serie.»
5. «La mascota ya tiene otra cita en ese horario.»
6. Disponibilidad de cada operador por línea (citas, horario semanal **sin** poder forzarse, vacaciones/permisos).
7. «La jaula está ocupada en ese horario.»

Si la fecha no sirve, **se recorre sola**:
- primero más tarde **ese mismo día**, en pasos de 30 min hasta el cierre;
- después los **14 días siguientes** (`SEARCH_DAYS`), probando primero la hora original y luego todo el día desde la apertura, saltando días cerrados e inhábiles.

Si en 14 días no hay lugar, la fecha se **omite** y queda registrada en `skipped_occurrences`. Las citas recorridas guardan `series_original_at` y `series_move_reason`.

### Resultado en pantalla
- «¡Serie creada!»
- «{mascota} · {regla, p. ej. "Cada 30 días" / "1er lunes de cada mes"} · N citas · M recorridas · K sin lugar»
- «Todas quedan por fijar en la agenda.»
- La lista de fechas; cada recorrida muestra «Recorrida desde {fecha} — {motivo}».
- Botón «Ir a la agenda».

### Después: citas "por fijar"
- Toda cita de una serie nace **sin fijar** (virtual, sin `series_confirmed_at`).
- Desde el detalle de la cita:
  - «Fijar» → `POST /api/bookings/{id}/fijar` (`BookingController::pin`, permiso `editar agenda`);
  - «Descartar» → `POST /api/bookings/{id}/descartar` (`BookingController::discard`). Solo funciona si la cita está Programada y no tiene presupuesto ni pago.
- La tarea `series:cierre-diario` (23:50) borra las no fijadas que ya vencieron.

> [!warning] Diferencia — sin vista previa en el móvil
> La nota de la pantalla promete que las fechas «se recorren solas», y es cierto. Pero el móvil **no muestra ninguna vista previa** antes de crear: el usuario ve cuáles se recorrieron u omitieron **hasta después**, en «¡Serie creada!». La vista previa con el recorrido existe solo en el alta web (`agenda/create`).

---

## Resumen de diferencias contra lo esperado

| Se esperaba | Lo que hace el código |
|---|---|
| La sucursal activa sale del check-in | El check-in no interviene. La sucursal sale de `users.branch_id` → la única sucursal del operador → la única activa (`BranchResolver::forNewBooking`). |
| Flujo mascota → servicio → cliente | El dueño se elige o crea **antes** de la mascota, y la mascota **antes** del servicio. |
| Mínimo un celular para WhatsApp | Mínimo un teléfono de **cualquier** tipo; sin control de duplicados. |
| Precio según la mascota | Precio fijo del catálogo, editable a mano por línea. |
| El calendario bloquea festivos | Solo las **series** evitan festivos. Una cita suelta se puede agendar en un día inhábil. |
| El calendario muestra días cerrados y turnos | La cuadrícula solo muestra citas y ausencias. Días cerrados y horario semanal se rechazan al guardar. |
| Recurrencia con vista previa | En el móvil se crea directo; el reporte llega después («¡Serie creada!»). |

## Archivos de referencia

- **Móvil:**
  - `mob_apps/operador/src/App.tsx`
  - `CheckinWidget.tsx`
  - `navState.ts`
  - `ScreenHeader.tsx`
  - `admin/GlobalAgenda.tsx`
  - `admin/PetSearch.tsx`
  - `admin/UniversalSearch.tsx`
  - `admin/PetDetail.tsx`
  - `admin/ClientSearch.tsx`
  - `admin/ClientDetail.tsx`
  - `admin/MobCitaNueva.tsx`
  - `admin/ServicePickerSheet.tsx`
  - `admin/DraggableSlotGrid.tsx`
  - `admin/MobUnavailability.tsx`
- **API** (`apps/backoffice-laravel/`):
  - Rutas: `routes/api.php`
  - Controladores (`app/Http/Controllers/Api/`): `CheckinController`, `OperatorController`, `PetController`, `ClientController`, `SearchController`, `ServiceController`, `SettingController`, `AgendaController`, `BookingController`
  - Soporte y modelos: `app/Support/Branches/BranchResolver.php`, `app/Support/SystemSettings/BusinessHours.php`, `app/Observers/SpaBookingObserver.php`, `app/Models/NonWorkingDay.php`
  - Dominio: `app/Domain/Planning/Services/OperatorAvailabilityChecker.php`, `app/Domain/Planning/Series/{RepeatInput,RecurrenceRule,BookingSeriesService,SeriesLifecycleService}.php`
