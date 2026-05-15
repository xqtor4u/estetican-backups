# Bitacora Backoffice Clientes y Pets

Fecha base: 2026-03-20 00:00 CST

## Convencion documental

Fecha de definicion de la convencion: 2026-03-25 00:00 CST
Fecha de actualizacion de la convencion: 2026-03-26 12:47 CST

- toda actualizacion significativa del proyecto debe reflejarse en esta bitacora con fecha y hora
- toda actualizacion significativa sobre documentacion viva debe registrar fecha y hora dentro del documento intervenido
- los identificadores tecnicos de pantalla deben usar camel labeling sin separadores, con bloques abreviados de hasta tres letras por nivel y prefijos modulares definidos de forma canonica
- al cierre del dia, antes de apagar o cerrar trabajo, se debe registrar aqui el resumen de cambios relevantes del dia si hubo movimiento significativo
- toda checklist nueva debe declarar explicitamente su `Fecha de definicion`
- si una checklist cambia de estructura o prioridad, debe conservar su fecha de definicion original y agregar la fecha de actualizacion correspondiente
- en UI y presentacion, cualquier patron repetible debe nacer como plantilla base reusable; los catalogos hijos solo deben declarar sus particularidades sobre esa base

## Cambios implementados

### Infraestructura local
- Se levantaron los contenedores de Docker del backoffice Laravel.
- Se corrigio la configuracion faltante de variables para `docker compose`.
- Se validaron migraciones dentro del entorno Sail.
- Se agrego el script [scripts/levantar_backoffice.sh](/home/tomas/EstetiCAN_2/scripts/levantar_backoffice.sh) para levantar entorno, correr migraciones y limpiar cache.
- Se agrego [scripts/apagar_backoffice.sh](/home/tomas/EstetiCAN_2/scripts/apagar_backoffice.sh) para bajar el stack del backoffice sin borrar volumenes.
- Se ajusto [scripts/levantar_backoffice.sh](/home/tomas/EstetiCAN_2/scripts/levantar_backoffice.sh) para esperar disponibilidad real de MySQL antes de correr migraciones y evitar `Connection refused` al primer arranque.
- Se ajusto [scripts/levantar_backoffice.sh](/home/tomas/EstetiCAN_2/scripts/levantar_backoffice.sh) para asegurar `storage:link` y permitir visualizar archivos subidos desde el navegador.
- Regla operativa persistida: antes de levantar el proyecto, primero WSL/Linux y luego Docker.

### Clientes
- Se habilito el CRUD de clientes con relaciones cargadas para direcciones y telefonos.
- Se corrigio la visualizacion de tipos en espanol en las vistas de clientes.
- Se ajusto la logica de cambios no guardados en edicion para evitar falsos avisos al guardar.
- El listado de clientes ahora permite alternar entre vista por bloques y vista tabular con una mascota viva por renglon, repitiendo cliente cuando hace falta para lectura operativa rapida.

### Telefonos
- Se corrigio el modelo `Phone` para trabajar con `client_id`.
- Se ajusto el flujo de alta y edicion para que los telefonos nuevos se guarden correctamente desde la edicion del cliente.
- Se corrigio el bloqueo del submit causado por campos `required` ocultos dentro de modales.

### Pets / Perros
- Se integro `pets` como relacion del cliente.
- Se agrego soporte de alta, edicion, eliminacion y visualizacion de perros desde la pantalla de clientes.
- Se incorporo la visualizacion de perros en listado y detalle de cliente.
- Se normalizo el core de `pets` para dejar solo datos base del perro.
- Se crearon las tablas `pet_medical_alerts` y `pet_photos`.
- Se migraron alertas medicas historicas desde `pets.medical_alerts` a `pet_medical_alerts`.
- Se movieron datos historicos de `temperament` y `weight` a `pets.notes` antes de retirar esas columnas.
- Se agrego `death_date` para identificar mascotas fallecidas y excluirlas despues de flujos operativos.
- Se agrego `species` para soportar perro, gato, pajaro u otra especie.
- Se agrego calculo de edad actual o edad al fallecer en UI.
- Las mascotas fallecidas ahora se muestran en gris en listado, detalle y edicion.
- Se agrego seleccion explicita de mascota desde clientes para gestionar sus tablas dependientes directas.
- Se implemento vista de mascota seleccionada con CRUD para `pet_medical_alerts` y `pet_photos`.
- El upload de `pet_photos` ahora redimensiona la imagen principal para controlar espacio y genera miniatura dedicada para bloques de catalogo y cliente.
- En listado y detalle de cliente se muestran solo mascotas vivas, usando miniatura de su foto principal si existe.
- `pet_photos.taken_at` ahora puede autocompletarse desde metadatos EXIF de la imagen cuando el usuario deja vacio el campo en el formulario.
- La vista de mascota seleccionada ahora intenta prellenar `Fecha de toma` en el navegador al elegir una foto, antes de guardar, usando EXIF cuando exista.
- Se corrigio `APP_URL` del entorno local a `http://localhost:8000` para que las URLs publicas de fotos apunten al puerto real del backoffice y no a `localhost` puerto 80.
- Se documentaron por separado las especificaciones tecnicas de clientes y mascotas para desarrollo externo.
- Se creo una copia editable del manual tecnico en Markdown para mantener actualizado el contenido que hoy tambien existe en PDF.
- Se agrego documentacion tecnica separada para el submodulo de fotos de mascota.
- Se agrego una guia de estructura de BD y tablas normalizadas para desarrollo externo.
- Se agrego un indice README para navegar la documentacion de arquitectura.

### Servicios y operadores
- Se creo la tabla `operators` como catalogo operativo separado de `users`.
- Se definio que `operators` representa a quien ejecuta el servicio y debe servir despues para metricas de desempeno y posible pago a destajo.
- Se enriquecio `services` para soportar descripcion, precio sugerido, duracion sugerida y bandera de activo sin romper compatibilidad con datos previos.
- Se extendio `executed_services` con `operator_id` y `service_summary` para registrar quien hizo el trabajo y como se describio finalmente el servicio ejecutado.
- Se extendio `executed_service_items` con snapshots de nombre, descripcion, tipo y duracion para congelar el historico aunque el catalogo cambie despues.
- Se reforzaron modelos y relaciones del dominio de servicios para que el core de catalogo, agenda y ejecucion deje de depender de modelos vacios.
- Se agrego CRUD inicial para `services` y `operators` con rutas resource, validacion basica y vistas Blade de listado, alta, detalle y edicion.
- Se agrego el grupo `Catálogos` a la barra principal con accesos directos a servicios y operadores.
- Las nuevas pantallas ya declaran breadcrumbs consistentes con el layout global para no abrir una navegacion paralela.
- `services` ahora cuenta con `code` unico para evitar duplicados ambiguos y facilitar seleccion operativa posterior.
- Se agrego accion de duplicar servicio para clonar rapidamente un catalogo base y ajustar la copia antes de activarla.
- El listado de servicios ya permite ordenar por los encabezados de Servicio y Tipo desde la propia tabla.
- Se agrego capa estructural para operadores con roles multiples, asignacion a sucursal base y perfil vigente de compensacion por hora.
- El formulario de operadores ahora puede capturar roles multiples separados por coma, base de operacion y tarifa por hora sin depender todavia de modulos de RRHH o usuarios.
- La asignacion automatica por capacidades y base operativa aun no esta implementada; la estructura ya queda preparada para esa capa posterior.
- `operator_roles` se formalizo como catalogo controlado con clave, descripcion, tarifa base por hora y estado activo.
- Se agrego CRUD inicial para `operator_roles` como catalogo canonico de tipos de operador dentro del grupo `Catálogos`.
- Operadores deja de capturar tipos por texto libre y ahora selecciona desde el catalogo canonico para evitar duplicados semanticos como `groomer` vs `grumer`.
- La ficha de operador se amplió con nombre completo, INE, numero de IMSS, direccion, telefono, contacto de emergencia, fecha de contratacion y notas para sostener mejor la operacion diaria sin esperar todavia al modulo de RRHH.
- Se cerro la correccion de bases operativas: `branches` ya se expone como catalogo CRUD y operadores deja de autocrear sucursales por texto libre; ahora selecciona `branch_id` desde catalogo.
- El flujo desde crear operador ahora permite abrir `Nueva sucursal`, regresar al formulario y volver con la sucursal nueva ya preseleccionada mediante `branch_id` en query string.
- Operadores ahora soporta foto unica de perfil almacenada en disco `public` con original optimizado y miniatura derivada para listado, ficha y edicion.
- Sucursales ahora incorpora direccion atomizada (`street`, `colonia`, `city`, `state`, `zip`, `country`) y coordenadas opcionales (`lat`, `lng`) para compartir ubicacion por WhatsApp y abrir la sede en Google Maps.
- El formulario de sucursal ahora incluye boton para intentar geocodificar la direccion desde navegador y un enlace directo para abrir la busqueda en Google Maps antes de confirmar `lat` y `lng`.
- Para resolver desfaces del geocodificador, el formulario de sucursal ahora permite pegar un link o coordenadas de Google Maps y extraer `lat`/`lng` exactos del punto correcto.
- En sucursales se termino de atomizar la linea vial: `street`, `exterior_number` e `interior_number` quedan separados para soportar fraccionamientos cerrados y edificios donde formatos como `NN-II` se geocodifican mal si van juntos.
- La baja de sucursales ya no solo inactiva: ahora elimina fisicamente cuando no tiene asignaciones, y bloquea el borrado con mensaje claro si todavia hay operadores ligados.
- Direcciones de clientes armonizadas con sucursales: `addresses` ahora tambien usa `street`, `exterior_number`, `interior_number`, `zip`, `lat` y `lng`, y las vistas consumen una direccion formateada comun.
- La edicion de cliente dejo de usar una tabla horizontal para direcciones y ahora renderiza tarjetas editables con geocodificacion, enlace a Google Maps e importacion de coordenadas exactas por direccion.
- Se extrajo un blueprint Blade compartido para direccion atomizada con coordenadas; sucursales y clientes ya consumen la misma pieza base y el mismo JS de Maps/geocodificacion, con espaciado mas compacto para pantallas largas.
- Se preparo soporte de HTTPS local para pruebas mobile-first: `mkcert` genera certificados LAN en `.certs/`, un proxy Caddy opcional expone `https://<ip>:8443` y `scripts/levantar_backoffice.sh` ya detecta y anuncia URLs HTTP/HTTPS probables para celular.
- Se corrigio la deteccion de `mkcert` desde WSL para instalaciones de WinGet sin alias funcional, usando fallback directo al directorio real del paquete; ya se generaron certificados LAN y se valido el arranque del proxy HTTPS local en `:8443`.
- Se corrigio la generacion global de URLs seguras detras de Caddy: Laravel ahora confia en el proxy inverso mediante `trustProxies`, evitando enlaces `http://` al navegar por `https://<ip>:8443`.
- Se corrigio un error adicional en el proxy HTTPS local donde algunas URLs absolutas salian con puerto `:0`, provocando `ERR_UNSAFE_PORT`; el proxy quedo simplificado para usar los headers por defecto de Caddy y ahora las URLs salen como `https://<ip>:8443`.
- Se genero tambien una copia `rootCA.crt` a partir de la CA local para facilitar la instalacion del certificado raiz en Android y Samsung sin depender solo del `.pem`.
- Se documento y valido el flujo real de prueba mobile-first: red Wi-Fi privada, acceso LAN en `https://192.168.90.20:8443` y certificado local confiable instalado en el telefono.
- Se retiro `Alt-Svc` del proxy HTTPS local para evitar seguimientos erraticos a `:443` desde navegadores moviles cuando el acceso real expone `:8443`.
- La navegacion mobile-first deja de depender del colapso JS de Bootstrap: el menu principal ahora expone un patron nativo `details/summary` tipo hamburguesa para asegurar apertura y toque confiable en Android.

### 2026-03-25
- Se agrego profiling local configurable en [apps/backoffice-laravel/app/Http/Middleware/ProfileBackofficeRequests.php](/home/tomas/EstetiCAN_2/apps/backoffice-laravel/app/Http/Middleware/ProfileBackofficeRequests.php) para exponer tiempos de request, tiempos SQL, cantidad de queries y memoria pico mediante headers y logs de lentitud.
- La compresion de fotos de mascotas y operadores se mantuvo server-side con `spatie/image`, pero ahora sus limites y calidades viven en [apps/backoffice-laravel/config/backoffice.php](/home/tomas/EstetiCAN_2/apps/backoffice-laravel/config/backoffice.php) y [apps/backoffice-laravel/.env.example](/home/tomas/EstetiCAN_2/apps/backoffice-laravel/.env.example), evitando hardcodes en clases de soporte.
- Se agrego cache selectivo y atomizado para catálogos reutilizados: especies de mascotas y roles activos de operador ahora salen de objetos de soporte cacheados, con invalidacion automatica al guardar o borrar modelos, evitando consultas repetidas en pantallas de mascotas y formularios de servicios.
- Se agrego una migracion de indices operativos para listados del backoffice: clientes, mascotas, servicios y roles de operador ahora tienen indices compuestos alineados con filtros y ordenamientos frecuentes, reduciendo costo de lectura sin introducir cache agresivo sobre listados completos.
- Se recortaron columnas en los listados raiz de clientes, mascotas y servicios para que los eager loads traigan solo los datos realmente renderizados en esas vistas; con eso baja memoria por request y se reduce transferencia entre ORM y PHP sin cambiar la UI.
- Se aplico el mismo criterio de poda al detalle de clientes y mascotas: direcciones, telefonos, mascotas relacionadas, alertas medicas y fotos ahora cargan solo columnas consumidas por la vista, reduciendo overhead en paginas de detalle sin tocar reglas funcionales.
- El cache de catalogos se ajusto para guardar datos planos serializables en lugar de colecciones/modelos completos; con eso se evita la deserializacion inconsistente entre procesos y el listado raiz de mascotas vuelve a responder estable con profiling activo.
- Se tomaron mediciones internas con el profiler ya encendido: `/clients` y `/pets` quedaron alrededor de 25-28 ms por request en el entorno actual, mientras `/services` ronda 9 ms; el siguiente cuello visible ya no es estructural de bootstrap sino datos/render concretos por modulo cuando crezca el volumen real.
- Se corrigio una regresion visual en la presentacion de catalogos: la base compartida de filtros y tablas recupera profundidad, contraste y jerarquia, y el listado de servicios vuelve a mostrar contexto operativo con tarjetas-resumen, etiquetas semanticas y filas mas legibles sin romper la plantilla reusable.
- Se corrigio la carga de assets del backoffice para que el estilo compilado sea la ruta por defecto: el modo `Vite hot reload` ahora queda opt-in por config (`BACKOFFICE_USE_VITE_HOT=true`) y el script de arranque elimina `public/hot` cuando no se desea desarrollo live, evitando que una marca stale deje la UI casi sin estilos aunque el build exista.
- Se ajusto la densidad de escritorio del shell compartido para aprovechar mejor el ancho util: el layout principal pasa a contenedor fluido con limite mas amplio y se reducen paddings/gutters en header, breadcrumbs, cards, botones, alertas y tablas cuando hay viewport grande.
- Se corrigio una rigidez adicional del shell al volver a agrandar la ventana en desktop: la navegacion deja de usar el contenedor fijo de Bootstrap y los paddings laterales grandes dejan de congelarse en un valor estatico, permitiendo que el ancho util crezca de forma continua hasta su limite maximo.
- Se elimino tambien el tope global de ancho del shell en desktop; el backoffice ahora puede expandirse a todo el viewport disponible y solo conserva padding lateral responsivo para no pegar el contenido a los bordes.
- Se corrigio esa decision para no romper navegacion ni breadcrumbs: el shell global vuelve a una anchura segura y la expansión extra se mueve solo al contenido del catálogo de servicios mediante un wrapper de breakout en desktop grande.
- El patrón de breakout de contenido se extendio tambien a clientes y mascotas, y su activacion baja a desktop normal (`>= 1200px`) para que tablas y filtros aprovechen mejor el viewport sin esperar a resoluciones excepcionalmente anchas.
- La responsividad de bloques tambien deja de depender de columnas fijas de Bootstrap en catalogos clave: mascotas y tarjetas de mascotas vivas pasan a grids fluidos `auto-fit`, y los resúmenes superiores se adaptan con el mismo criterio para responder mejor a anchos intermedios y grandes.
- La plantilla base de tablas ahora soporta redimensionar columnas con el raton desde los encabezados, estilo hoja de calculo: el comportamiento vive en `list-table` y un modulo JS compartido, de modo que los catalogos que usan esa pieza heredan handles de resize sin reimplementar lógica por vista.

## Decisiones de modelo aprobadas

### Tabla `pets`
Debe guardar solo informacion base y estable del perro:
- `client_id`
- `name`
- `species`
- `breed`
- `birth_date`
- `death_date`
- `microchip_code`
- `tattoo_code`
- `sex`
- `coat_color`
- `size`
- `is_sterilized`
- `notes`

Implementado en base:
- `birth_date`
- `death_date`
- `species`
- `microchip_code`
- `tattoo_code`
- `sex`
- `coat_color`
- `size`
- `is_sterilized`
- `notes`

### Datos que no deben quedar en `pets`
- alertas medicas como JSON
- fotos de servicio
- historial operativo de servicios
- peso historico si se va a controlar por tiempo

### Alertas medicas
Se aprobo sacar `medical_alerts` de `pets` y moverlo a tabla propia:
- `pet_medical_alerts`
  - `pet_id`
  - `category`
  - `description`
  - `severity`
  - `notes`
  - `is_active`
  - timestamps

Estado actual:
- tabla creada
- migracion de alertas historicas aplicada
- CRUD disponible desde la vista de mascota seleccionada para alta, edicion y baja logica de alertas

### Fotos
Separacion aprobada:
- `service_photos`: evidencia de servicio o estancia, ligada a `executed_service_id` o `stay_id`
- `pet_photos`: foto principal e historial visual general del perro

Propuesta para `pet_photos`:
- `pet_id`
- `photo_url`
- `photo_type`
- `taken_at`
- `description`
- `is_primary`
- timestamps

Estado actual:
- tabla `pet_photos` creada
- CRUD disponible con carga de archivo real, persistencia de ruta en tabla y manejo de foto principal
- el archivo principal se guarda optimizado y con limite de dimension, sin conservar una copia gigante del original
- se genera miniatura fisica para vistas resumidas de mascotas vivas

### Servicios y ejecucion
Separacion aprobada:
- `services`: catalogo reusable con definicion comercial y operativa base
- `operators`: catalogo del personal que ejecuta trabajos, separado de autenticacion
- `executed_services`: cabecera del trabajo realizado sobre la mascota
- `executed_service_items`: detalle historico congelado de los conceptos cobrados o ejecutados

Estado actual:
- `services` conserva `type`, `name`, `price` y `duration_minutes` heredados, y ahora agrega `description`, `suggested_price`, `suggested_duration_minutes` e `is_active`
- `executed_services` ahora puede guardar `operator_id` y `service_summary` ademas de precio final, fecha y notas
- `executed_service_items` ya queda preparado para snapshots de nombre, descripcion, tipo y duracion al momento de ejecutar
- `operators` queda como base para trazabilidad operativa y metricas por persona sin depender todavia de `users`

Decisiones vigentes:
- `operators` y `users` son conceptos distintos y no deben mezclarse en la primera capa del core
- el historico de ejecucion no debe leer nombre o descripcion viva desde `services`; debe congelar snapshot en `executed_service_items`
- pagos a destajo y liquidaciones no entran en esta iteracion; quedan explicitamente aplazados
- administracion de usuarios y permisos tambien queda aplazada para una capa posterior

## Tablas ligadas a pets detectadas
- `pet_vaccinations`
- `spa_bookings`
- `hotel_reservations`
- `stays`
- `executed_services`

## Pendientes funcionales

### Agenda de servicios programados
Falta modelar y/o aterrizar el flujo funcional para servicios programados en agenda:
- los servicios programados deben vivir en agenda mientras su estado sea activo
- al concluir deben salir de agenda por alguno de estos resultados:
  - realizado
  - cancelado
  - abandonado / no show
- el abandono debe generar penalizacion

### Estados sugeridos
Base recomendada para servicios programados:
- `scheduled`
- `completed`
- `cancelled`
- `no_show`
- `unfulfillable` si aplica operativamente

### Pendientes deliberadamente aplazados
- modelar liquidaciones, reglas de pago a destajo y cortes por periodo para `operators`
- definir capa de usuarios, roles y permisos del backoffice
- decidir si despues existira relacion opcional entre `users` y `operators` para login interno sin mezclar identidad operativa con autenticacion

## Task-List operativo (actualización 2026-03-27 cierre)

- [hecho] Corregir error de conectividad y migraciones MySQL
- [hecho] Validar que todos los tests pasen
- [hecho] Implementar respaldo automático robusto en script de apagado
- [hecho] Documentar política de respaldo obligatorio
- [pendiente] Repoblar datos de desarrollo si es necesario
- [pendiente] Mantener verificación periódica de respaldos

*No quedan incidencias técnicas abiertas al cierre de la jornada.*

## Decision funcional 2026-03-26

- `SPA` y `Hotel` dejan de considerarse variantes del mismo booking.
- `SPA` se programa como cita puntual:
  - una `fecha + hora` de inicio
  - seleccion de uno o varios servicios
  - duracion operativa en minutos derivada de los servicios seleccionados
- `Hotel` se programa como reserva de estancia:
  - rango `inicio/fin`
  - asignacion de instalacion disponible (`jaula`, espacio o recurso de hospedaje)
  - lifecycle posterior de check-in / check-out / stay
- Esta diferencia queda aprobada para el diseño de `Agenda` como modulo paraguas con submodulos separados por linea operativa.

## Incidencia abierta 2026-03-26

- Se detecta como pendiente próximo que el `Alta de Clientes` no está funcionando correctamente.
- Debe revisarse y corregirse como incidencia prioritaria del flujo base de operación comercial antes de expandir demasiado los módulos satélite.

## Actualizacion 2026-03-23

- `services` ahora queda ligado a `operator_roles` mediante `operator_role_id` para evitar asignacion ambigua de especialidad operativa.
- el formulario de servicios expone selector de tipo de operador y acceso directo a crear un nuevo tipo de operador desde la misma captura.
- crear un tipo de operador desde servicios ahora conserva `return_to`, agrega boton `Regresar` y al guardar retorna al formulario de origen.
- quedó operativo el acceso mobile-first por HTTPS local sobre LAN con Caddy y `mkcert`, incluyendo instalacion de CA local en Android mediante `rootCA.crt`.
- se corrigio la generacion global de enlaces seguros detras del proxy y se valido que la portada ya emite URLs `https://192.168.90.20:8443/...` sin `http://` ni `:0`.
- la barra principal ahora resuelve navegacion movil con menu hamburguesa nativo basado en `details/summary`, en lugar del colapso JS de Bootstrap que no estaba respondiendo de forma estable en Galaxy.

## Cierre del dia 2026-03-26

- Se consolido el modulo de `Configuración del sistema` con persistencia real, middleware de overrides runtime y secciones operativas de `Visualización`, `Sistema` y `Seguridad`.
- `Visualización` ya controla paletas predefinidas, densidad y preview en vivo antes de guardar; se añadió tambien `Restaurar visualización actual`.
- Las paletas quedaron mas diferenciadas y la variante antes llamada `Costa profesional` se reconvirtió a `Floral editorial`.
- Se reforzó la legibilidad del backoffice en catálogos: zebra striping real por celdas, contraste de botones de acciones por fila y jerarquía compartida de `Ver`, `Editar`, `Duplicar`, `Eliminar`.
- Se corrigieron estados visuales del menu principal y dropdowns para evitar casos de texto ilegible en `hover` o en elementos activos dentro de `Catálogos`.
- Se abrió la primera versión funcional de `Agenda` colgada de las plantillas compartidas del backoffice:
  - listado base de agenda
  - programación desde mascota
  - lectura de próximos servicios programados desde la ficha de mascota
- Se formalizó la separación conceptual y funcional entre `SPA` y `Hotel`:
  - `SPA` como cita puntual con `fecha + hora` y duración en minutos
  - `Hotel` como reserva por rango de fechas con instalación disponible (`jaula` o recurso de hospedaje)
- Se dejó documentado que `Agenda` debe evolucionar como módulo paraguas con submódulos separados por línea operativa (`SPA`, `Hotel`, `Veterinaria`, etc.).
- Se registró como incidencia abierta y `pendiente próximo` que el `Alta de Clientes` no está funcionando correctamente.

### Prioridades para mañana

- corregir `Alta de Clientes`
- renombrar y consolidar la agenda actual como `Agenda SPA`
- preparar la estructura paraguas de `Agenda` para abrir después `Agenda Hotel`
- comenzar modelado de disponibilidad de instalaciones para hotel (`jaulas` / recursos)

## Actualizacion 2026-03-25

- esta bitacora queda como fuente viva unica para la checklist operativa del modulo de `resources`.
- el manual editable deja de duplicar la checklist detallada y solo apunta aqui para evitar divergencias.
- la checklist de `resources` se reorganiza por fases MVP para que el siguiente bloque de trabajo tenga prioridad operativa explicita.
- la landing page fue actualizada para reflejar el baseline operativo real del backoffice: clientes, catalogos, operadores y sucursales activos; `resources` queda visible como siguiente bloque.
- la ficha de mascota ahora permite editar el core directamente desde su propio detalle, tanto en el catalogo raiz como dentro del contexto de cliente; `pets.name` se trata como dato editable normal y no como indice relacional fijo.
- el indice raiz de mascotas ahora soporta filtros comunes para bloques y tabla, mas ordenacion ascendente o descendente desde encabezados de la tabla siguiendo el blueprint de vistas listadas.

## Actualizacion 2026-03-26 - configuracion, shell y agenda

- Se inicio el modulo de configuración del sistema como capa propia del backoffice, separado de catálogos operativos, con rutas y navegación dedicadas en `Operación` para no contaminar módulos de negocio con ajustes globales.
- Se agrego persistencia tipada en la nueva tabla `system_settings`, junto con el modelo [apps/backoffice-laravel/app/Models/SystemSetting.php](/home/tomas/EstetiCAN_2/apps/backoffice-laravel/app/Models/SystemSetting.php) y el servicio [apps/backoffice-laravel/app/Support/SystemSettings/SystemSettings.php](/home/tomas/EstetiCAN_2/apps/backoffice-laravel/app/Support/SystemSettings/SystemSettings.php), que mezcla defaults de [apps/backoffice-laravel/config/backoffice.php](/home/tomas/EstetiCAN_2/apps/backoffice-laravel/config/backoffice.php) con overrides persistidos.
- Se agrego el middleware [apps/backoffice-laravel/app/Http/Middleware/ApplySystemSettings.php](/home/tomas/EstetiCAN_2/apps/backoffice-laravel/app/Http/Middleware/ApplySystemSettings.php) para aplicar en runtime zona horaria, locale, lifetime de sesión y overrides de `backoffice.*` antes de renderizar vistas o ejecutar interacciones del shell.
- El módulo inicial ya expone tres secciones reales en [apps/backoffice-laravel/resources/views/system-settings/index.blade.php](/home/tomas/EstetiCAN_2/apps/backoffice-laravel/resources/views/system-settings/index.blade.php): `Visualización`, `Sistema` y `Seguridad`, con wiring efectivo sobre branding del shell, densidad visual, resize compartido de tablas, TTL de catálogos, confirmaciones destructivas y expiración por inactividad.
- La plantilla base y el runtime compartido quedaron conectados a esa configuración: el layout ahora publica `density`, `confirm-actions` y `resizable tables` como estado del `<body>`, la base de tablas toma por defecto el flag centralizado y los módulos JS compartidos respetan esos switches sin duplicar lógica por pantalla.
- La base compartida de tablas ahora alterna renglones con dos fondos claros cercanos pero distinguibles, para mejorar rastreo visual horizontal en listados largos sin romper el look suave del backoffice ni requerir estilos por módulo.
- Se ajusto ese zebra striping para que la alternancia sea visible tambien en reposo: el renglón par ahora tiene contraste más claro/legible desde la presentación habitual, y el hover queda solo como refuerzo adicional de foco.
- El módulo de configuración visual ahora permite escoger paletas predefinidas profesionalmente optimizadas desde `Visualización`; esas paletas gobiernan variables compartidas de tema, shell, landing y tablas base, de modo que el cambio afecta de verdad el look operativo sin rehacer CSS por módulo.
- La selección de paleta dejó de depender de la persiana nativa del `<select>` y pasó a tarjetas tipo radio con preview completa; así la elección visual queda directa, estable y consistente con la vista previa mostrada al usuario.
- Se incrementó la distancia cromática entre presets (`Tierra clínica`, `Costa profesional`, `Pizarra premium`, `Sage ledger`) y cada tarjeta ahora incluye una mini previsualización de interfaz para que la diferencia entre paletas sea evidente antes de guardar.
- Se reforzó el zebra striping de tablas operativas aplicándolo sobre `td` y no solo sobre `tr`, con tokens específicos por paleta para fila impar, fila par y hover; así la alternancia sí permanece visible incluso con clases Bootstrap de tabla y estados mutados.
- La selección de paleta ahora hace preview en vivo sobre el `body` y en la tarjeta resumen de visualización; el cambio sigue siendo temporal hasta enviar `Guardar configuración`, con lo que se separa claramente previsualización local vs persistencia real.
- La sección `Visualización` ahora también previsualiza en vivo la densidad (`cómoda`/`compacta`) y expone un botón `Restaurar visualización actual` para volver al estado persistido sin guardar. Además, la antigua `Costa profesional` se reconvirtió visualmente en `Floral editorial`, con rosas empolvados y acentos vino suave.
- Se reforzó el contraste del botón `Editar` (`btn-outline-warning`) y del clúster de acciones de tabla (`Ver`, `Duplicar`, `Eliminar`) con overrides compartidos de Bootstrap para que sigan siendo legibles sobre cualquier paleta activa.
- Se unificó la jerarquía semántica de acciones en listados usando `catalog-actions-cluster` también en sucursales, operadores, clientes y tipos de operador; `Editar` quedó como acción operativa dominante, `Ver/Cliente` y `Mascota/Detalle` como navegación secundaria visible, y `Eliminar/Inactivar` como destructiva clara pero controlada.
- Como antesala al módulo de `Programación de servicios`, se expuso el CTA `Programar servicio` en mascotas: tabla, bloques y gestión/ficha. El CTA queda visible pero marcado como `Próximo` hasta que exista la UI/ruta de agenda, alineando expectativa funcional con la navegacion futura.
- Se abrió la primera versión real del módulo `Agenda`, colgada de las plantillas compartidas (`page-header`, `list-table`, `list-filters`, `catalog-overview`, `catalog-actions-cluster`) y disparada desde mascota. Incluye listado inicial de bookings, formulario de programación desde mascota, navegacion operativa activa y corrección de esquema para notas en `spa_bookings`.
- se extrajo un blueprint compartido de listados para clientes, mascotas y servicios mediante componentes reutilizables de cambio de vista, filtros y encabezados ordenables; el patron deja de vivir solo en mascotas y pasa a ser base comun de catalogos y listados operativos.
- el orden por `Cliente` en la tabla de clientes se corrigio para seguir el nombre visible `first_name last_name`; antes mezclaba la expectativa visual con orden por apellido y podia dejar el primer renglon fuera de la intuicion del usuario.
- la tabla de clientes ahora separa `Nombre` y `Apellido` en columnas distintas, con ordenacion independiente por cada una; la vista por bloques conserva el nombre compuesto para no romper su lectura compacta.
- quedo implementado el modulo raiz `pets` con `index` y `show`, cambio entre vistas `Bloques` y `Tabla`, y reutilizacion de la ficha especializada de mascota desde navegacion raiz.
- se fija decision metodologica para el frontend del backoffice: en adelante los patrones de presentacion deben construirse como plantillas base tipo objeto/componente y heredarse en catalogos hijos para definir solo variaciones del dominio.
- operadores, sucursales y tipos de operador fueron adecuados al blueprint comun de listados con filtros y encabezados ordenables, para cerrar la conversion de los catalogos ya construidos antes de abrir el siguiente modulo.
- se profundizo la base reusable de listados con un contenedor comun de tabla y paginacion (`list-table`), de modo que clientes, mascotas, servicios, operadores, sucursales y tipos de operador ya no repiten la estructura HTML principal del grid tabular.
- para seguir mejores practicas de separacion de responsabilidades, el blueprint comun de listados ahora ya expone una capa CSS dedicada en `public/css/backoffice-blueprints.css`; los componentes Blade de filtros, cambio de vista, ordenacion y tabla quedan mas enfocados en estructura y estado que en presentacion embebida.
- se saco tambien el CSS global inline del layout principal a `public/css/backoffice-app.css`, dejando `layouts/app.blade.php` como ensamblador de assets y no como contenedor de estilos masivos; con eso la base visual del backoffice empieza a separarse mejor entre estructura Blade y presentacion CSS.
- se dio el siguiente paso hacia mejores practicas de frontend: la base visual del backoffice y el blueprint de listados ya existen tambien dentro de `resources/css/` para que Vite pueda gestionarlos, el layout consume `@vite` cuando hay hot file o manifest, y el script de arranque intenta compilar assets solo si no hay servidor Vite activo ni build disponible.
- la logica reusable del editor de direcciones tambien sale de Blade hacia `resources/js/modules/address-editor.js` y se carga por `resources/js/app.js`; el parcial `shared/address-editor-scripts` queda solo como respaldo cuando no existe `hot` ni `manifest`, para no romper formularios en entornos sin pipeline frontend disponible.
- el flujo dinamico de alta y edicion de clientes tambien se separa a `resources/js/modules/client-form.js`; crear/agregar direcciones, telefonos, mascotas y la advertencia de cambios no guardados dejan de vivir principalmente en Blade y pasan al entrypoint frontend, manteniendo fallback inline solo para entornos sin assets Vite disponibles.
- el arranque del backoffice deja de depender del `npm` del host cuando WSL resuelve binarios Windows: `scripts/levantar_backoffice.sh` ahora intenta compilar assets con `npm run build` dentro del contenedor `laravel.test`, que ya expone `node` y `npm`, reduciendo el riesgo de fallos por rutas UNC en entornos mixtos WSL + Docker Desktop.
- se consolido la capa global del shell sin mezclar particulares de modulo: `config/backoffice.php` pasa a guardar branding y labels globales del contenedor, mientras la presentacion se atomiza en `backoffice-theme.css` (tokens y tipografia), `backoffice-shell.css` (layout, menu, breadcrumbs, encabezados) y `backoffice-landing.css` (portada).
- la navegacion mantiene dentro del componente solo los items particulares de cada dominio; la base global ya no absorbe catalogos ni copy especifico de modulo fuera de lo estrictamente comun.
- se dio un paso adicional en esa misma linea: la navegacion principal ya no declara en un solo Blade todos los particulares de clientes, catalogos y operacion; esos grupos pasan a clases hijas separadas en `app/Support/Navigation/Groups/`, mientras `main-navigation.blade.php` queda como ensamblador del shell y renderer comun.
- se aplico el mismo criterio a breadcrumbs y encabezados base de pagina: los metadatos repetibles de clientes, mascotas, servicios, operadores, tipos de operador y sucursales pasan a clases hijas en `app/Support/Pages/`, y las vistas quedan enfocadas en acciones y contenido particular en lugar de reescribir contexto comun en cada archivo.

## Actualizacion 2026-03-26 - limpieza frontend y rendimiento

- se completo la limpieza del camino transicional de frontend: las vistas de crear y editar cliente ya no cargan fallbacks inline para alta dinamica de direcciones, telefonos, mascotas ni para la advertencia de cambios no guardados; toda esa logica queda centralizada en `resources/js/modules/client-form.js`.
- el editor de direcciones deja de depender del parcial Blade legacy `shared/address-editor-scripts`; sucursales y clientes pasan a usar solo el modulo comun `resources/js/modules/address-editor.js` cargado desde `resources/js/app.js`.
- con el build validado dentro de `laravel.test` y el `manifest` ya generado, el backoffice consolida Vite como ruta estandar para JS reusable y elimina codigo muerto de respaldo que ya estaba duplicando la fuente de verdad.
- se cerro una segunda pasada de estandarizacion modular: las confirmaciones destructivas dejan de vivir como `onclick` en Blade y pasan a un modulo comun `resources/js/modules/confirm-actions.js`, mientras la lectura EXIF para fotos de mascota sale de la vista y queda concentrada en `resources/js/modules/pet-photo-exif.js`.
- se limpiaron tambien residuos de presentacion inline en vistas operativas recurrentes: miniaturas de mascotas, previews de fotos y avatars de operadores ahora usan clases compartidas en `resources/css/backoffice-app.css` en lugar de `style=` repetido por vista.
- con esta pasada, las vistas del backoffice quedan sin scripts embebidos ni handlers inline propios; el unico `script` directo que permanece en Blade es la carga global de Bootstrap desde el layout base.
- se cerro tambien esa ultima excepcion del layout: Bootstrap ya no entra por CDN, ahora se instala desde `npm`, se integra al pipeline Vite mediante `resources/css/app.css` y `resources/js/bootstrap.js`, y el fallback sin `hot` ni `manifest` usa copias locales en `public/vendor/bootstrap/`.
- se limpiaron vistas compiladas para retirar referencias viejas y se valido de nuevo el build productivo dentro de `laravel.test`, dejando el backoffice sin dependencias frontend externas directas para su shell base.
- se atendio tambien la vulnerabilidad alta detectada por `npm audit`: el arbol frontend actualizo las transitivas vulnerables de `picomatch` a versiones corregidas (`2.3.2` y `4.0.4`) sin cambiar dependencias directas del proyecto.
- tras el ajuste se revalido `npm run build` y `npm audit`, dejando el frontend del backoffice con `0` vulnerabilidades reportadas en el momento del cierre.
- para mejorar tiempos de carga del shell y reducir trabajo repetido por request, la ruta raiz deja de usar closure y pasa a `Route::view`, habilitando `route:cache`; ademas `scripts/levantar_backoffice.sh` deja de vaciar todo con `optimize:clear` y ahora reconstruye `config:cache`, `route:cache` y `view:cache` al levantar el entorno.
- se agrego un profiler ligero para entorno local mediante middleware: expone headers de tiempo total, tiempo SQL, cantidad de queries y memoria pico, y registra en log requests o queries lentas segun umbrales configurables en `config/backoffice.php`.

## Actualizacion 2026-03-26 12:38 CST - ids tecnicos de pantalla para soporte

- el shell del backoffice ya puede mostrar junto a `Backoffice operativo` una clave tecnica hardcodeada de modulo y pantalla para identificar rapido la vista activa durante soporte, QA y debug con usuarios.
- la visibilidad de esa clave queda controlada desde `Configuración del sistema` dentro de `Visualización`, de modo que se puede encender o apagar sin tocar codigo ni contaminar la UI productiva cuando no haga falta.
- por ahora el comportamiento base queda aplicado con visibilidad encendida por defecto; el usuario puede apagarla despues desde `Configuración del sistema` si necesita una vista mas limpia.
- la clave de pantalla queda declarada de forma hardcodeada en la metadata compartida de cada pagina operativa y, en la portada, directamente en la vista; con esto el identificador no depende de labels visibles ni de inferencias sobre la ruta actual.
- la convencion vigente para esos identificadores pasa a ser camel labeling sin separadores, usando bloques abreviados de hasta tres letras por nivel, por ejemplo `AgSpaInd`, `AgSpaCrePet` o `OprRolEdi`.
- catalogo maestro inicial de prefijos para esos identificadores:
  - `Hom` para portada o dashboard raiz
  - `Cli` para clientes
  - `Pet` para mascotas
  - `Ag` para agenda como modulo paraguas
  - `Spa` para agenda SPA
  - `Bra` para sucursales
  - `Ope` para operadores
  - `OprRol` para tipos de operador
  - `Ser` para servicios
  - `SysSet` para configuracion del sistema
  - `AgHot` reservado para agenda hotel
  - `Res` reservado para resources
  - `Vet` reservado para agenda veterinaria
- se revalido `route:list` y tambien el build frontend dentro de `laravel.test`; durante esa validacion se corrigio ownership heredado en `public/build` para evitar nuevos fallos de permisos al compilar assets desde Docker.

## Actualizacion 2026-03-26 12:51 CST - defaults de direccion para captura rapida

- `Configuración del sistema` ahora expone tres valores predefinidos para captura de direcciones: país, estado y ciudad.
- esos defaults ya alimentan el editor compartido de direcciones usado en clientes y sucursales, por lo que nuevas capturas arrancan con esos valores sin tener que reescribirlos cada vez.
- el modal de agregar dirección dentro de edición de cliente también toma esos defaults desde el runtime del layout, para no romper consistencia entre formulario renderizado y alta dinámica.

## Actualizacion 2026-03-26 13:00 CST - captura minima de clientes y advertencias persistentes

- la captura minima de cliente cambia: `email` deja de ser obligatorio y el requisito operativo pasa a ser nombre mas al menos un telefono activo.
- `last_name` tambien queda opcional en captura y se agrega migracion para permitirlo en la tabla `clients` sin forzar apellidos inventados o relleno artificial.
- las validaciones efimeras por `alert()` o por obligatorios del navegador se sustituyen en clientes por advertencias persistentes en modal Bootstrap, para que el usuario vea con claridad que la captura no se agrego y deba corregir o aceptar el mensaje antes de seguir.

## Actualizacion 2026-03-26 13:12 CST - flujo de captura rapida cliente > mascota > agenda

- la pantalla de alta de cliente se reorganiza para priorizar el flujo operativo real: primero cliente minimo (`nombre` + `telefono principal`), despues mascotas, y al final los complementarios como apellido, email, direcciones y telefonos extra.
- se agrega una salida directa `Crear y agendar servicio`, que lleva a la agenda de la primera mascota capturada cuando el usuario ya viene con intencion de programar en el mismo flujo.
- si se intenta usar esa salida directa sin mascota capturada, la advertencia persistente bloquea el avance y deja claro que primero debe existir al menos una mascota valida.

## Actualizacion 2026-03-26 14:43 CST - prioridad inmediata posterior para agenda y jaulas

- se deja asentado que la agenda aun no debe considerarse cerrada: falta afinarla segun el area de negocio y las necesidades reales de agendado de cada operacion.
- una vez resuelto ese afinado funcional, el task inmediato posterior pasa a ser el modelado de `jaulas` como activo compartido transversal, porque casi todas las lineas operativas dependen de ese recurso para reservar, ocupar, bloquear o reasignar capacidad.
- este criterio desplaza cualquier arranque prematuro de `resources` generico: primero debe quedar clara la logica operativa de agenda y enseguida aterrizar el activo critico compartido que condiciona la disponibilidad real.

## Actualizacion 2026-03-26 15:05 CST - agenda SPA con lectura diaria por horario

- la agenda actual se consolida explicitamente como `Agenda SPA` en labels y encabezados para evitar que la UI sugiera falsamente un modulo paraguas ya terminado.
- el listado operativo de agenda ahora puede leerse por ventana diaria (`hoy`, `mañana`, `fecha elegida`) o por proximas sesiones, sin perder filtro por estado ni busqueda cruzada por mascota, cliente o servicio.
- cada booking visible expone rango horario estimado (`inicio - fin`) y duracion estimada calculada desde los servicios seleccionados, lo que vuelve la vista mas util para recepcion y coordinacion antes de entrar a reprogramaciones, cancelaciones o capacidades compartidas.
- se agrego una lectura superior por bloques horarios visibles para el dia, junto con metricas de carga total estimada y ventana operativa detectada en la vista activa.
- se revalidaron rutas Laravel y build frontend despues del ajuste, dejando esta iteracion funcionalmente estable para seguir afinando reglas de agenda por area de negocio.

## Actualizacion 2026-03-26 15:20 CST - ciclo operativo del booking SPA

- `Agenda SPA` ya no se queda solo en alta y lectura: cada booking cuenta ahora con pantalla de detalle propia y acceso directo desde la agenda y desde las proximas sesiones de la ficha de mascota.
- se habilita reprogramacion controlada para bookings en estado `scheduled`, limitada por ahora a fecha, hora y notas operativas, sin tocar el snapshot congelado de servicios ni precios.
- se habilitan tambien acciones operativas de `cancelacion` y `no_show`, ambas registradas sobre el booking sin mezclar todavia esta capa con ejecucion real del servicio.
- las nuevas rutas del flujo quedan activas como `agenda.show`, `agenda.edit`, `agenda.update`, `agenda.cancel` y `agenda.no-show`; durante la validacion hubo que reconstruir `route:cache` para que Laravel expusiera el mapa nuevo de rutas en este entorno.
- se revalido build frontend y errores de editor despues del ajuste, dejando a `Agenda SPA` con su primer ciclo operativo minimo: programar, leer, reprogramar, cancelar y marcar no show.

## Actualizacion 2026-03-26 16:25 CST - criterio aprobado para asignacion de jaulas y limpieza

- se aprueba como criterio de negocio que `Jaula X` no se asigna por inferencia ni por simple capacidad abstracta: debe asignarse como activo fisico explicito dentro de agenda mediante una fila de disponibilidad temporal.
- la mascota no guarda una jaula fija; la asignacion correcta vive por evento operativo y por ventana de tiempo, para soportar reasignacion, sobrecupo evitado y trazabilidad real del activo.
- el tiempo de limpieza intra horarios no debe esconderse dentro de la duracion del servicio como si fuera tiempo de la mascota; debe bloquearse como una ventana adicional propia del activo inmediatamente despues del uso.
- por lo tanto, el modelo recomendado para `jaulas` requiere `resources` como catalogo del activo y `resource_allocations` como capa temporal de reserva, ocupacion, limpieza, mantenimiento o bloqueo manual.
- regla inicial propuesta para disponibilidad: una jaula queda libre solo si no existe traslape entre la ventana de uso solicitada y ninguna ventana activa de `reserved`, `occupied`, `cleaning`, `maintenance` o `manual_block`.
- para `Agenda SPA`, la ventana a evaluar debe considerar `scheduled_at + duracion estimada de servicios + buffer de limpieza`; para `Hotel`, la reserva y la ocupacion real deben seguir separadas de su limpieza posterior.

## Actualizacion 2026-03-26 17:02 CST - cimiento backend de recursos y asignaciones

- ya quedaron creadas y migradas las tablas `resources` y `resource_allocations` como base real del modulo de activos compartidos.
- tambien quedaron agregados los modelos, relaciones base y un servicio de dominio para asignar un activo a una entidad origen con validacion de traslape y creacion automatica del bloqueo de limpieza posterior.
- el buffer de limpieza queda centralizado en configuracion (`backoffice.system.resource_cleaning_buffer_minutes`) y tambien expuesto en `Configuración del sistema`, evitando hardcodes para futuras reglas de agenda.
- esta iteracion no abre todavia CRUD ni selector visual de jaulas en agenda; deja listo el backend para conectar esa seleccion sin reconstruir el modelo despues.

## Actualizacion 2026-03-26 17:15 CST - Agenda SPA ya enlaza jaulas y libera disponibilidad

- la pantalla de alta de `Agenda SPA` ya permite seleccionar opcionalmente una `jaula` o recurso fisico al programar el booking.
- la pantalla de reprogramacion del booking tambien permite cambiar o liberar esa asignacion, manteniendo la regla de traslape mas buffer de limpieza posterior.
- cancelar o marcar `no_show` en un booking SPA ya libera sus filas en `resource_allocations`, evitando que la capacidad quede retenida por una cita que ya no seguira viva.
- el selector actual muestra recursos tipo `cage` activos con contexto de sucursal; la validacion fuerte de disponibilidad sigue resolviendose server-side al guardar.
- con esto `Agenda SPA` ya consume la nueva capa de recursos, aunque todavia no exista el CRUD visual completo del modulo `Recursos y activos`.

## Actualizacion 2026-03-26 17:55 CST - duplicacion segura de recursos

- el catalogo de `resources` ahora expone accion `Duplicar` en listado, detalle y edicion para acelerar alta de jaulas o activos homogeneos sin recapturar manualmente su clasificacion.
- al duplicar, la copia conserva sucursal, tipo de recurso, capacidad, notas y demas clasificacion operativa relevante, reduciendo errores por olvido al crear series de activos similares.
- la nueva copia nace con clave unica derivada, nombre marcado como copia y estado administrativo `inactive`, de modo que el usuario debe revisar y confirmar antes de activarla en operacion real.
- se agrego prueba feature para cubrir tanto la presencia del CTA como el comportamiento de duplicacion dentro del modulo de recursos.

## Actualizacion 2026-03-26 18:19 CST - RESIND alineada con plantilla de tablas

- `ResInd` deja de quedarse en una tabla funcional minima y se alinea con el blueprint compartido de catalogos del backoffice.
- el indice de recursos ahora expone resumen superior, lectura rapida de estado visible, carga de asignaciones y ordenamiento por clave, recurso, sucursal, estado y asignaciones.
- las filas adoptan tambien la jerarquia visual comun del shell: `code pill`, stack de titulo y descripcion, badges de estado y cluster de acciones consistente con los otros catalogos operativos.
- se amplio la prueba feature del modulo para cubrir esta integracion del blueprint y el ordenamiento por asignaciones.

## Actualizacion 2026-03-26 18:34 CST - trazabilidad fotografica multiple para recursos

- el modulo `resources` ya soporta multiples fotos por activo mediante la nueva tabla `resource_photos`, en lugar de colgar una sola imagen de la fila principal del recurso.
- cada foto guarda tipo, fecha de toma, descripcion y bandera `is_primary`, lo que permite documentar desgaste, incidentes, reparaciones, garantias y evolucion visual del activo a lo largo del tiempo.
- los archivos se guardan en storage publico bajo `resource-photos/{Y}/{m}/original`, con miniatura derivada en `thumbs`, siguiendo el mismo patron operativo ya usado para mascotas.
- la ficha del recurso ahora incorpora alta, edicion, reemplazo y eliminacion de fotos dentro de la misma vista, manteniendo la trazabilidad sin salir del detalle del activo.
- se agregaron pruebas feature para CRUD de fotos, cambio de principal, reemplazo de archivo y autocompletado de `taken_at` desde metadatos EXIF cuando el campo llega vacio.

## Actualizacion 2026-03-26 19:06 CST - arquitectura propuesta para eventos operativos de recursos

- se define como siguiente capa recomendada una separacion explicita entre `resource_events`, `resource_event_updates` y `resource_event_photos`.
- `resource_events` funcionaria como indice maestro del caso: incidente, mantenimiento detectado, observacion operativa, bloqueo sanitario o cualquier hecho relevante asociado al activo.
- `resource_event_updates` guardaria el seguimiento por etapas, evitando que el historial se pierda al sobrescribir una sola fila del evento.
- `resource_event_photos` concentraria la evidencia del caso por apertura, seguimiento o cierre, mientras `resource_photos` seguiria reservado para el historial visual general del activo.
- el evento podra relacionarse opcionalmente con `client`, `pet`, `service` y tambien con una fuente operativa polimorfica como `spa_booking`, `hotel_reservation` o `stay`, ademas de detector, responsable y cerrador via `users`.

## Actualizacion 2026-03-26 19:52 CST - eventos operativos de recursos ya implementados

- ya quedaron creadas y probadas las tablas `resource_events`, `resource_event_updates` y `resource_event_photos` como segunda capa operativa del modulo de recursos.
- la ficha del recurso ya permite levantar un evento con tipo, severidad, estado, fechas, detector, responsable y relacion opcional con cliente, mascota y servicio.
- cada evento ya tiene su propia vista de detalle para registrar seguimientos por etapa y para adjuntar evidencia fotografica multiple ligada al caso completo o a un seguimiento puntual.
- las fotos del evento siguen el mismo criterio tecnico acordado: compresion, estandarizacion de formato y generacion de miniatura en el mismo flujo para cuidar almacenamiento y lectura uniforme.
- el bloque completo quedo validado con pruebas feature sobre alta del evento, cambio de estado, seguimiento, fotos por etapa y autocompletado de fecha desde EXIF.

## Actualizacion 2026-03-26 20:00 CST - hotel ya bloquea jaulas por rango planeado

- `hotel_reservations` ya tiene flujo web propio para listar, crear, editar, ver y cancelar reservas de hospedaje.
- al crear o editar una reserva hotel ahora se puede seleccionar una `jaula` activa y el sistema genera un bloqueo `reserved` en `resource_allocations` por todo el rango `start_at` / `end_at`.
- la validacion de traslape ya corre sobre la misma capa compartida que usa `Agenda SPA`, evitando sobreasignar una misma jaula entre spa y hotel.
- cancelar la reserva hotel libera de inmediato su bloqueo de jaula, evitando retencion falsa de capacidad.
- en esta iteracion el hotel bloquea la jaula durante la reserva planeada, pero la limpieza posterior queda deliberadamente pendiente para la futura integracion de `stays`, porque la ocupacion real no siempre termina exactamente en el horario planeado.

### Proximo bloque recomendado al retomar `resources`

- Este bloque corresponde al siguiente frente modular de `resources` una vez corregida la incidencia prioritaria de `Alta de Clientes` y afinada la `Agenda` segun area de negocio.
1. Arrancar por `jaulas` como primer activo compartido critico, en lugar de abrir un catalogo generico demasiado temprano.
2. Definir para jaulas su taxonomia minima: sucursal, tipo, capacidad, estado administrativo, estado operativo y motivo de bloqueo.
3. Crear tabla de asignaciones o bloqueos temporales (`resource_allocations`) para reservas, uso real, mantenimiento, limpieza, aislamiento y bloqueo manual.
4. Conectar esa disponibilidad compartida con `hotel_reservations`, `stays` y cualquier agenda que requiera ocupar capacidad fisica comun.
5. A partir de ese modelo validado, abrir el catalogo mas amplio de `resources` para otros activos que si convenga normalizar despues.
6. Crear bloqueos manuales para limpieza, contingencia o indisponibilidad operativa.
7. Crear programacion de mantenimientos (`resource_maintenance_plans`).
8. Crear historial de mantenimientos ejecutados (`resource_maintenance_events`).
9. Crear bitacora de cambios de estado del activo (`resource_status_events`).
10. Exponer CRUD inicial de recursos y su vista operativa de disponibilidad por rango y sucursal.
11. Exponer reporte de inventario por sucursal, estado y tipo de activo.
12. Exponer historial de uso y mantenimiento por activo.
13. Extender la misma logica a spa, veterinaria y otros recursos compartidos.

### Checklist previsible del modulo de recursos y activos

Fecha de definicion: 2026-03-23
Fecha de actualizacion de estructura: 2026-03-25

Fuente viva:

- esta seccion es la referencia canonica del backlog operativo de `resources`
- cualquier avance o cierre de items debe actualizarse aqui antes que en otros documentos

Objetivo:

- incorporar una capa transversal de recursos fisicos que permita disponibilidad, bloqueo, mantenimiento, inventario e historial por sucursal

Orden recomendado de ataque:

1. consolidar `branches` como base multisucursal ya disponible
2. crear `resources`
3. definir catalogos base de tipos y estados
4. crear `resource_allocations`
5. integrar hotel como primer consumidor
6. crear mantenimiento programado y ejecutado
7. abrir reportes e historial operativo

Checklist priorizada por fases:

### Fase 0. Definicion del MVP

- [ ] Definir alcance del MVP: solo jaulas o inventario completo.
- [ ] Definir tipos base de recurso.
- [ ] Definir estados administrativos del activo.
- [ ] Definir tipos de bloqueo operativo.
- [ ] Definir si habra capacidad por recurso o si cada recurso es unidad indivisible.

### Fase 1. Cimiento ya disponible y alta del catalogo base

- [x] Definir catalogo inicial de sucursales.
- [x] Crear tabla `branches`.
- [x] Crear tabla `resources` con `branch_id`.
- [x] Crear modelos y relaciones.
- [x] Crear CRUD de sucursales.
- [ ] Crear CRUD de recursos.

### Fase 2. Disponibilidad y consumo operativo

- [x] Crear tabla `resource_allocations` para conflictos de agenda y uso.
- [x] Crear reglas para evitar traslapes de asignacion.
- [x] Crear reglas para que cancelaciones liberen disponibilidad.
- [x] Conectar `spa_bookings` con asignacion/liberacion de recursos.
- [ ] Integrar `hotel_reservations` con recursos.
- [ ] Integrar `stays` con ocupacion real de recursos.
- [ ] Crear vista de disponibilidad por sucursal y rango.
- [ ] Crear bloqueo manual para contingencia, limpieza o apartados internos.

### Fase 3. Mantenimiento y trazabilidad del activo

- [ ] Crear tabla `resource_maintenance_plans` para periodicidad.
- [ ] Crear tabla `resource_maintenance_events` para ejecucion real.
- [ ] Crear tabla `resource_status_events` para auditoria de estado.
- [ ] Crear reglas para que mantenimiento bloquee disponibilidad.

### Fase 4. Reportes y endurecimiento

- [ ] Crear reporte de inventario por sucursal, tipo y estado.
- [ ] Crear reporte de historial de uso y mantenimiento por activo.
- [ ] Crear pruebas funcionales de disponibilidad, conflicto y mantenimiento.

Riesgos previsibles a vigilar:

- duplicar el concepto de estado del activo con el estado temporal de agenda
- mezclar reserva planeada con uso real
- permitir dos asignaciones activas sobre el mismo recurso y rango
- modelar mantenimiento solo como texto libre y perder trazabilidad
- dejar sucursal como dato opcional y luego romper la expansion multisede

## Nota de versionado
El versionado tecnico fino sigue viviendo en Git.
Esta bitacora complementa Git con decisiones funcionales, cambios estructurales y pendientes de negocio.

## Navegacion y landing page
- La ruta raiz deja de usar la pantalla default de Laravel y pasa a ser una landing operativa del backoffice.
- Se crea una barra de navegacion reusable como componente Blade para evitar menus duplicados por vista.
- La barra evoluciona a menus desplegables por dominio para soportar crecimiento modular sin saturar la barra principal con enlaces planos.
- El layout global monta ese componente, de forma que cualquier cambio futuro en el menu impacta todas las paginas que extienden `layouts.app`.
- La portada concentra accesos directos al flujo vigente y deja listo el patron para crecer hacia agenda, recursos y modulos multisucursal.
- Se agrega tambien un componente reusable de breadcrumbs en el layout global para poder profundizar navegacion por niveles sin rehacer la estructura en cada nueva pagina de detalle.
- Se agrega un componente reusable de encabezado de pagina para unificar titulo, contexto y acciones primarias en clientes y mascota seleccionada.

## Sprint 2026-04-14 — Usuarios, Acceso y Fusión Operadores

### Acceso y autenticación
- El login ahora utiliza **nombre de usuario** (`name`) en lugar del correo electrónico. El campo `name` tiene índice `unique` en BD.
- Se agrega el campo `can_login` (boolean, default `false`) a la tabla `users`. Controla si el usuario puede iniciar sesión en el backoffice, independientemente de si está activo como empleado.
- El `LoginController` verifica `can_login` tras validar credenciales; si es `false`, rechaza el acceso con mensaje claro aunque la contraseña sea correcta.
- Los usuarios ya existentes en el sistema recibieron `can_login = true` al aplicar la migración.
- Distinción aprobada: `is_active` = empleado operativo activo (aparece en agendas y selectores); `can_login` = acceso real al backoffice.

### Módulo de usuarios (CRUD completo)
- Se agrega botón **Eliminar** en el listado de usuarios con confirmación, restringido a super-admin. El propio usuario nunca puede eliminarse (doble candado: UI + servidor).
- Formulario de edición corregido: `first_name`, `last_name` descomentados en validación, asignación y modelo `Fillable`. Se migran como columnas reales en BD (no existían físicamente).
- El campo `hire_date` ahora se castea a `date` en el modelo para evitar error `format() on string`.
- Se eliminan columnas obsoletas `full_name` y `permisos` de la tabla `users` (migración aplicada).
- El formulario de edición incluye botones "ojito" para visualizar/ocultar contraseña en ambos campos.
- El nombre de usuario (`name`) y el correo electrónico (`email`) son editables con validación de unicidad que excluye al propio usuario.
- Identificadores de pantalla automáticos: el layout genera `ScreenDebugId` a partir del nombre de ruta cuando no está declarado explícitamente.

### Fusión Operadores → Usuarios
- Los operadores de servicio (groomers, etc.) ahora coexisten en la tabla `users`.
- Nuevos campos en `users`: `is_operator` (boolean), `operator_code` (varchar, unique), `operator_role_id` (FK a `operator_roles`).
- El formulario de edición incluye sección **"Datos de Operador"** con toggle dinámico que muestra/oculta código y tipo de operador.
- La sección **"Acceso al sistema"** expone control de estado laboral y toggle de permiso de login en el mismo formulario.
- Seeder `MigrateOperatorsToUsersSeeder` migró el operador existente `GRO-JMP` como usuario ID 4 con `can_login=false`.
- El modelo `User` incluye la relación `operatorRole()` → `BelongsTo OperatorRole`.
- El `UserController` carga el catálogo de `operator_roles` activos en `create()` y `edit()`.

### Respaldo
- Respaldo completo de BD generado en `/home/tomas/EstetiCAN_2/backups/backup_20260414.sql` (1,625 líneas).

### Pendientes inmediatos (próximo sprint)
- `USECRE`: añadir sección de Operador y toggle `can_login` al formulario de alta de usuario.
- `USEIND`: mostrar columnas `can_login` e `is_operator` en el listado.
- `USESHO`: mostrar datos de operador en la vista de detalle.
- Asignar `operator_role_id` al usuario GRO-JMP desde la interfaz.
- Decidir si se depreca/elimina la tabla `operators` en sprint siguiente.
### Sprint 2026-04-16 — Estandarización, Imágenes y Pulido Premium

#### Infraestructura y Estandarización
- **Control de Versiones Global:** Se implementó el identificador `AAMMDD-HHMM` (ej. `260416-2100`) centralizado en `config/backoffice.php` y visible en el shell global para trazabilidad de despliegues.
- **Terminología Unificada:** Se estandarizó el uso de **"Tipo de Operador"** en todo el sistema, eliminando ambigüedades entre roles técnicos y administrativos.
- **Optimización de Imagen Binaria:** Se instalaron `jpegoptim`, `optipng`, `pngquant` y `gifsicle` en el servidor Docker para permitir compresión real.

#### Gestión de Fotografía (Alto Rendimiento)
- **Motor de Compresión:** Se implementó `UserPhotoImageManager` y se sincronizaron todos los gestores (Mascotas, Recursos, Eventos) para aplicar compresión obligatoria al **82% de calidad** y eliminar metadatos innecesarios.
- **Trazabilidad:** Todos los archivos optimizados ahora incluyen el sufijo `_opt` (ej. `uuid_opt.jpg`) para evitar re-compresiones y facilitar auditoría visual.
- **Componente `<x-image-upload />`:** Nueva pieza de UI con previsualización instantánea (vía `URL.createObjectURL`) y validación de peso en el cliente (límite 5MB).
- **Integración de Usuarios:** Formulario de alta y edición de usuarios ahora soporta fotos de perfil con ciclo de vida completo (carga, reemplazo y borrado físico al eliminar usuario).

#### Experiencia de Usuario (Pulido Premium)
- **Estados Vacíos (Empty States):** Se creó el componente `<x-empty-state />` con iconos y CTAs para sustituir textos planos de "No hay datos" en todos los listados principales.
- **Sistema de Toasts:** Se reemplazaron las alertas estáticas que movían el layout por notificaciones flotantes temporales (Bootstrap Toasts) integradas en el layout global.
- **Confirmaciones Modernas:** Se migró de `confirm()` nativo a un **Modal Premium** de confirmación estilizado y manejado por JS, mejorando la seguridad en acciones destructivas.

#### Respaldos de Sesión
- **Base de Datos:** [`backups/260416-2240_estetican_db.sql`](file:///home/tomas/EstetiCAN_2/backups/260416-2240_estetican_db.sql)
- **Código Fuente:** [`backups/260416-2244_estetican_code.tar.gz`](file:///home/tomas/EstetiCAN_2/backups/260416-2244_estetican_code.tar.gz)

