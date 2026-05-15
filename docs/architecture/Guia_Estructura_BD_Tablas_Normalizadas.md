# Guia de Estructura de BD y Tablas Normalizadas

Fecha de actualizacion: 2026-03-26 16:25 CST
Alcance: mapa de estructura relacional del backoffice Laravel, con foco en tablas normalizadas y decisiones de separacion de responsabilidades.

Convencion de mantenimiento: toda actualizacion significativa de esta guia debe registrar fecha y hora.

## Objetivo de esta guia

Esta guia sirve para un desarrollador externo que necesita entender rapidamente:

- que tablas existen
- cual es su responsabilidad
- como se relacionan
- que normalizaciones ya se hicieron
- donde hay divergencias historicas entre esquema base y dominio actual

## Principios de normalizacion aplicados

### 1. Separar entidad comercial de entidad operativa

- `clients` representa al dueño o responsable
- `pets` representa a la mascota como entidad operativa principal

### 2. Separar planeacion de ejecucion real

- `spa_bookings` y `hotel_reservations` representan planeacion
- `executed_services` y `stays` representan ejecucion real

### 3. Separar datos core de historiales y anexos

- `pets` solo guarda datos base estables
- alertas medicas viven en `pet_medical_alerts`
- fotos generales viven en `pet_photos`
- estados operativos viven en `service_status_logs`

### 4. Evitar columnas JSON cuando el dato necesita operarse por separado

Caso aplicado:

- `pets.medical_alerts` fue retirado del core y migrado a `pet_medical_alerts`

### 5. Evitar mezclar evidencias operativas con identidad visual general

- `pet_photos` != `service_photos`

## Mapa general de dominios y tablas

### Captacion

- `campaign_sources`
- `leads`

### Relacion comercial

- `clients`
- `addresses`
- `phones`

### Operacion central por mascota

- `pets`
- `pet_medical_alerts`
- `pet_photos`
- `pet_vaccinations`

### Catalogo operativo

- `services`
- `operator_roles`
- `operators`

### Operacion multisucursal

- `branches`
- `operator_branch_assignments`

### Planeacion

- `spa_bookings`
- `spa_booking_services`
- `hotel_reservations`

### Ejecucion real

- `executed_services`
- `executed_service_items`
- `stays`

### Seguimiento operativo

- `service_status_logs`
- `service_photos`

### Comunicacion y auditoria

- `notification_queues`
- `audit_logs`

## Tablas normalizadas clave

### `clients`

Responsabilidad:

- entidad comercial base del dueño o responsable

Relaciona con:

- `addresses` por `client_id`
- `phones` por `client_id`
- `pets` por `client_id`
- `notification_queues` por `client_id`
- `leads` por `lead_id` opcional

Nota:

- el telefono ya no vive en `clients`; se normalizo hacia `phones`

### `addresses`

Responsabilidad:

- almacenar multiples direcciones de cliente

Relacion:

- `belongs to clients`

Normalizacion aplicada:

- direccion separada del cliente para soportar multiples ubicaciones y potencial GIS
- armonizada con `branches` para usar `street`, `exterior_number`, `interior_number`, `colonia`, `city`, `state`, `zip`, `country`, `lat`, `lng`

### `phones`

Responsabilidad:

- almacenar multiples telefonos por cliente

Relacion vigente:

- `belongs to clients`

Normalizacion aplicada:

- migracion desde estructura polimorfica `phoneable_*` a `client_id`
- se eliminaron columnas polimorficas una vez migrados los datos

### `pets`

Responsabilidad:

- datos base estables de la mascota

Relaciona con:

- `clients`
- `pet_medical_alerts`
- `pet_photos`
- `pet_vaccinations`
- `spa_bookings`
- `hotel_reservations`
- `stays`
- `executed_services`

Normalizacion aplicada:

- se retiraron `weight`, `temperament` y `medical_alerts` del core
- el core actual queda enfocado a identidad y atributos estables

### `pet_medical_alerts`

Responsabilidad:

- alertas medicas activas o historicas de mascota

Relacion:

- `belongs to pets`

Normalizacion aplicada:

- reemplazo de JSON embebido en `pets`

### `pet_photos`

Responsabilidad:

- identidad visual general de mascota

Relacion:

- `belongs to pets`

Normalizacion aplicada:

- fotos generales separadas de fotos operativas
- metadatos de la foto guardados por fila

### `pet_vaccinations`

Responsabilidad:

- historico de vacunas y vigencias

Relacion:

- `belongs to pets`

Valor operativo:

- especialmente relevante para acceso a hotel

### `services`

Responsabilidad:

- catalogo maestro de servicios

Relaciona con:

- `operator_roles`
- `spa_booking_services`
- `executed_service_items`

Normalizacion aplicada:

- cada servicio ya apunta a un `operator_role_id` canonico para evitar tipos de operador libres o ambiguos

### `operator_roles`

Responsabilidad:

- catalogo canonico de especialidades o tipos de operador

Relaciona con:

- `services`
- `operators`

Valor operativo:

- evita duplicados semanticos de rol
- permite fijar una tarifa base por hora a nivel de especialidad

### `operators`

Responsabilidad:

- catalogo operativo del personal ejecutor, separado de autenticacion

Relaciona con:

- `operator_roles`
- `branches`
- `operator_branch_assignments`
- `executed_services`

Normalizacion aplicada:

- se separa de `users` para no mezclar identidad operativa con login o permisos
- usa catalogos controlados para rol y base operativa en lugar de texto libre

### `branches`

Responsabilidad:

- catalogo maestro de sucursales o bases operativas

Relaciona con:

- `operators`
- `operator_branch_assignments`

Normalizacion aplicada:

- la direccion deja de quedar como texto libre disperso y se captura atomizada con `street`, `exterior_number`, `interior_number`, `colonia`, `city`, `state`, `zip`, `country`
- `lat` y `lng` quedan opcionales para geolocalizacion mas precisa en Google Maps

Valor operativo:

- compartir ubicacion por WhatsApp
- abrir la sucursal en Maps
- reutilizar una misma estructura direccional consistente con clientes

### `spa_bookings`

Responsabilidad:

- cita o servicio spa planeado

Relaciona con:

- `pets`
- `spa_booking_services`
- opcionalmente `executed_services`

Normalizacion aplicada:

- separacion entre reserva planeada y servicio ejecutado

### `spa_booking_services`

Responsabilidad:

- pivot normalizado entre una cita spa y los servicios incluidos

Relaciona con:

- `spa_bookings`
- `services`

Valor:

- conserva precio vigente al momento de reservar

### `hotel_reservations`

Responsabilidad:

- reserva planeada de hotel

Relaciona con:

- `pets`
- `stays` opcionalmente

Normalizacion aplicada:

- reserva planeada separada de ocupacion real

Estado actual observado:

- la reserva hotel ya cuenta con flujo HTTP propio en el backoffice Laravel
- la reserva puede bloquear una `jaula` o recurso tipo `cage` durante todo el rango planeado usando `resource_allocations`
- en esta etapa el bloqueo de reserva hotel no agrega limpieza posterior; esa parte queda reservada para la ocupacion real en `stays`

### `stays`

Responsabilidad:

- ocupacion real de hotel

Relaciona con:

- `pets`
- `hotel_reservations` opcional
- `service_status_logs`
- `service_photos`

### `resources`

Responsabilidad:

- catalogo maestro de activos fisicos compartidos por sucursal

Relaciona con:

- `branches`
- `resource_allocations`
- `resource_photos`
- `resource_events`

Valor operativo:

- representa una unidad fisica real y asignable, por ejemplo una `jaula`
- evita tratar capacidad como numero abstracto cuando la operacion necesita saber exactamente que activo quedo ocupado

Estado actual observado:

- la tabla base y el modelo ya existen en el backoffice Laravel como cimiento para `jaulas` y otros activos compartidos
- `Agenda SPA` ya puede referenciar estos activos al programar o reprogramar, aunque el CRUD operativo de recursos todavia no exista

### `resource_photos`

Responsabilidad:

- historial visual y evidencia fotografica de cada activo fisico

Relaciona con:

- `resources`

Normalizacion aplicada:

- las fotos no viven como una sola columna dentro de `resources` porque el activo necesita evolucion, evidencia de desgaste, accidentes, garantia y reparaciones en multiples momentos
- cada foto guarda su propio metadato operativo (`photo_type`, `taken_at`, `description`, `is_primary`) y referencia al archivo fisico optimizado en storage

Estado actual observado:

- la tabla, el modelo y las rutas CRUD ya existen en el backoffice Laravel
- los archivos se guardan en storage publico bajo `resource-photos/{Y}/{m}/original` y generan miniatura derivada en `resource-photos/{Y}/{m}/thumbs`
- la ficha del recurso ya permite alta, edicion, reemplazo y eliminacion de fotos sin salir del detalle del activo

### `resource_events`

Responsabilidad:

- indice maestro de incidentes, observaciones, bloqueos, mantenimientos detectados y otros hechos operativos asociados a un activo

Relaciona con:

- `resources`
- `users` como detector, responsable y eventual cerrador
- `clients` opcionalmente
- `pets` opcionalmente
- `services` opcionalmente
- `source_type` y `source_id` opcionalmente para enlazar `spa_bookings`, `hotel_reservations`, `stays` u otras entidades operativas concretas

Normalizacion aplicada:

- el evento vive como registro maestro indexable y filtrable por tipo, estado, severidad, recurso y fecha
- no conviene mezclar en una sola fila el evento maestro, el seguimiento temporal y toda la evidencia porque eso rompe lectura, auditoria y escalabilidad
- el evento puede existir aunque no tenga fotos, y las fotos pueden agregarse despues por etapa de seguimiento

Estructura materializada en esta iteracion:

- `resource_id`
- `event_type`
- `event_status`
- `severity`
- `title`
- `description`
- `occurred_at` nullable
- `detected_at`
- `detected_by_user_id` nullable
- `responsible_user_id` nullable
- `closed_by_user_id` nullable
- `resolved_at` nullable
- `client_id` nullable
- `pet_id` nullable
- `service_id` nullable
- `source_type` nullable
- `source_id` nullable

Valor operativo:

- permite saber que paso, cuando paso, quien lo detecto, quien quedo responsable y con que cliente, mascota o servicio estuvo relacionado
- permite disparar reglas operativas posteriores, por ejemplo bloqueo de recurso, mantenimiento o cierre formal del incidente

Estado actual observado:

- la tabla, el modelo, las rutas y la vista de detalle ya existen en el backoffice Laravel
- la ficha del recurso ya permite crear eventos operativos enlazados opcionalmente a cliente, mascota, servicio y usuarios responsables
- cada evento ya cuenta con pantalla propia de seguimiento y evidencia fotografica por etapa

### `resource_event_updates`

Responsabilidad:

- historial cronologico de seguimiento del evento, con cambios de estado, notas, reasignaciones y acciones correctivas por etapa

Relaciona con:

- `resource_events`
- `users` como autor del seguimiento

Normalizacion aplicada:

- el seguimiento no debe sobrescribir la fila maestra del evento porque cada etapa necesita conservarse como evidencia historica
- este patron es equivalente al de una bitacora temporal separada del estado actual

Estructura materializada en esta iteracion:

- `resource_event_id`
- `update_type`
- `from_status` nullable
- `to_status` nullable
- `notes`
- `created_by_user_id` nullable
- `created_at`

Valor operativo:

- deja rastro de recepcion, diagnostico, contencion, reparacion, validacion y cierre
- permite reconstruir quien hizo cada movimiento sin depender solo de `updated_at`

Estado actual observado:

- la tabla y el modelo ya existen en el backoffice Laravel
- el detalle del evento ya permite registrar seguimientos con cambio opcional de estado y persistencia del historial

### `resource_event_photos`

Responsabilidad:

- evidencia fotografica ligada a una etapa concreta del evento operativo

Relaciona con:

- `resource_events`
- `resource_event_updates` opcionalmente cuando la foto pertenece a una etapa puntual del seguimiento

Normalizacion aplicada:

- las fotos generales del activo siguen viviendo en `resource_photos`
- las fotos del incidente o de la reparacion deben vivir separadas para no mezclar historial general del recurso con evidencia de un caso puntual
- un evento puede tener varias fotos en apertura, varias en seguimiento y varias al cierre

Estructura materializada en esta iteracion:

- `resource_event_id`
- `resource_event_update_id` nullable
- `photo_url`
- `photo_type`
- `taken_at` nullable
- `description` nullable
- `is_primary` default false

Estado actual observado:

- la tabla, el modelo y las rutas CRUD ya existen en el backoffice Laravel
- cada foto del evento se comprime, estandariza y genera miniatura en el mismo flujo, igual que `resource_photos`
- el detalle del evento ya permite asociar varias fotos a una etapa concreta del seguimiento o dejarlas ligadas al caso general

### `resource_allocations`

Responsabilidad:

- asignacion temporal, bloqueo o uso real de un activo fisico dentro de una ventana de tiempo

Relaciona con:

- `resources`
- `spa_bookings` opcionalmente
- `hotel_reservations` opcionalmente
- `stays` opcionalmente

Normalizacion aplicada:

- la ocupacion del activo no debe quedar embebida como columna directa dentro de `spa_bookings`, `hotel_reservations` o `stays`
- el uso del activo se modela como fila independiente para soportar reserva, ocupacion real, limpieza, mantenimiento, aislamiento y bloqueo manual

Estado actual observado:

- la tabla base y el modelo ya existen en el backoffice Laravel, junto con un servicio de dominio para validar traslapes y generar el bloqueo de limpieza posterior al uso
- la capa actual ya se conecta con `spa_bookings` para crear, mover o liberar asignaciones desde el flujo de agenda SPA

### Regla operativa propuesta para jaulas

- `Jaula X` se asigna creando una fila explicita en `resource_allocations`; no se infiere solo por mascota, servicio o sucursal
- una jaula fisica real equivale a un solo registro en `resources`
- la asignacion debe apuntar a la entidad origen que consume el activo (`spa_booking`, `hotel_reservation` o `stay`) mediante referencia directa o patron polimorfico controlado
- la misma mascota puede usar distintas jaulas en distintos momentos; por eso la jaula no debe vivir fija dentro de `pets`

### Regla operativa propuesta para limpieza intra horarios

- el tiempo de limpieza no debe sumarse silenciosamente a la duracion del servicio como si fuera trabajo sobre la mascota
- la asignacion de jaula debe generar dos ventanas consecutivas:
	- ventana de uso operativo de la mascota
	- ventana de limpieza o saneamiento del activo
- ambas ventanas bloquean disponibilidad de la jaula para evitar sobreasignacion entre citas seguidas
- el bloqueo de limpieza debe guardarse como su propia fila en `resource_allocations` con tipo operativo distinto (`cleaning`, `sanitation` o equivalente), enlazado a la asignacion principal cuando convenga trazabilidad

### Traduccion minima recomendada a datos

- `resources`
	- `id`
	- `branch_id`
	- `resource_type` con valor inicial canonico `cage`
	- `code` humano visible, por ejemplo `J-A01`
	- `capacity_label` o clasificacion de tamano
	- `administrative_status`
	- `operational_status`
- `resource_allocations`
	- `id`
	- `resource_id`
	- `allocation_type` con valores iniciales `reserved`, `occupied`, `cleaning`, `maintenance`, `manual_block`
	- `starts_at`
	- `ends_at`
	- `pet_id` opcional para trazabilidad rapida
	- `source_type` y `source_id` o equivalente explicito hacia `spa_bookings`, `hotel_reservations` o `stays`
	- `parent_allocation_id` opcional para enlazar el bloqueo de limpieza con la ocupacion principal
	- `notes`
- `resource_photos`
	- `id`
	- `resource_id`
	- `photo_url`
	- `photo_type`
	- `taken_at`
	- `description`
	- `is_primary`
- `resource_events`
	- `id`
	- `resource_id`
	- `event_type`
	- `event_status`
	- `severity`
	- `title`
	- `description`
	- `occurred_at`
	- `detected_at`
	- `detected_by_user_id`
	- `responsible_user_id`
	- `closed_by_user_id`
	- `resolved_at`
	- `client_id`
	- `pet_id`
	- `service_id`
	- `source_type` y `source_id`
- `resource_event_updates`
	- `id`
	- `resource_event_id`
	- `update_type`
	- `from_status`
	- `to_status`
	- `notes`
	- `created_by_user_id`
- `resource_event_photos`
	- `id`
	- `resource_event_id`
	- `resource_event_update_id`
	- `photo_url`
	- `photo_type`
	- `taken_at`
	- `description`
	- `is_primary`

### Regla de disponibilidad

- una `jaula` esta disponible solo si no existe ninguna fila activa en `resource_allocations` cuyo rango se traslape con la ventana solicitada de uso o limpieza
- para agenda SPA, la ventana solicitada debe calcularse como `scheduled_at + duracion_servicio + buffer_limpieza`
- para hotel, la ventana debe separar `reserva planeada`, `ocupacion real` y `limpieza posterior`, porque no siempre coinciden exactamente en el tiempo

### `executed_services`

Responsabilidad:

- servicio efectivamente realizado

Relaciona con:

- `pets`
- `spa_bookings` opcional
- `executed_service_items`
- `service_status_logs`
- `service_photos`

### `executed_service_items`

Responsabilidad:

- detalle normalizado de componentes cobrados en una ejecucion

Relaciona con:

- `executed_services`
- `services`

### `service_status_logs`

Responsabilidad:

- bitacora de estados operativos

Relaciona con:

- `executed_services` opcional
- `stays` opcional

Normalizacion aplicada:

- status historico separado del registro maestro actual

### `service_photos`

Responsabilidad:

- evidencia visual de servicio o estancia

Relaciona con:

- `executed_services` opcional
- `stays` opcional

Normalizacion aplicada:

- evidencia operativa separada de identidad visual general en `pet_photos`

### `notification_queues`

Responsabilidad:

- cola de mensajes salientes por cliente

Relacion:

- `belongs to clients`

### `audit_logs`

Responsabilidad:

- trazabilidad de acciones sobre entidades

Relacion:

- `user_id` opcional hacia usuarios
- `entity_type` y `entity_id` como referencia flexible a entidad afectada

## Relacion principal resumida

```text
campaign_sources -> leads -> clients -> pets
clients -> addresses
clients -> phones
clients -> notification_queues

pets -> pet_medical_alerts
pets -> pet_photos
pets -> pet_vaccinations
pets -> spa_bookings -> spa_booking_services -> services
pets -> hotel_reservations -> stays
pets -> executed_services -> executed_service_items -> services
branches -> resources -> resource_allocations
spa_bookings -> resource_allocations
hotel_reservations -> resource_allocations
stays -> resource_allocations

executed_services -> service_status_logs
stays -> service_status_logs
executed_services -> service_photos
stays -> service_photos
```

## Normalizaciones ya realizadas que no deben revertirse

- `pets.medical_alerts` JSON -> `pet_medical_alerts`
- `clients.phone` -> `phones`
- `phones.phoneable_*` -> `phones.client_id`
- fotos generales de mascota -> `pet_photos`
- fotos de servicio / estancia -> `service_photos`
- retiro de `weight` y `temperament` del core de `pets`

## Divergencias y cautelas para externos

- algunas migraciones base no representan por si solas el estado final del dominio; siempre revisar migraciones evolutivas posteriores
- hay decisiones ya aprobadas funcionalmente que viven en la bitacora y especificaciones, no solo en el schema inicial
- `pet_photos.photo_type` aun tiene huella historica de `profile`, aunque la UI actual usa `perfil`
- el modelo y la UI de cliente trabajan hoy con mascotas vivas en index/show y con todas en edit

## Recomendaciones para trabajar la BD sin romper el dominio

- no reintroducir campos multivalor o JSON en `pets` para alertas o fotos
- no mezclar planeacion con ejecucion en una sola tabla
- no colgar nuevas evidencias operativas en `pet_photos`
- antes de tocar `phones`, revisar toda la migracion desde el modelo polimorfico heredado
- mantener documentadas las migraciones evolutivas cuando cambien el sentido del modelo