# Manual Tecnico de Desarrollo Modular Antigravity Estetican (Editable)

Fecha de actualizacion: 2026-03-26 12:47 CST
Estado: copia editable derivada para mantenimiento vivo.

## Nota de mantenimiento

Existe un manual en PDF en [docs/architecture/Manual_Tecnico_Desarrollo_Modular_Antigravity_Estetican.pdf](/home/tomas/EstetiCAN_2/docs/architecture/Manual_Tecnico_Desarrollo_Modular_Antigravity_Estetican.pdf), pero no es un formato conveniente para cambios incrementales dentro del flujo actual de desarrollo.

Por esa razon, este archivo Markdown se crea como fuente editable para:

- registrar cambios tecnicos recientes
- facilitar onboarding de desarrollo externo
- mantener sincronizadas decisiones de arquitectura, modulo y base de datos

Recomendacion operativa:

- mantener el PDF como referencia historica
- mantener este Markdown como fuente viva a partir de ahora
- registrar fecha y hora cada vez que este documento reciba una actualizacion significativa

## Contexto general del sistema

Backoffice Laravel para operacion modular de EstetiCAN, con enfoque en:

- leads y origen de campanas
- clientes
- mascotas
- citas de spa
- reservas de hotel
- estancias reales
- estados operativos
- notificaciones
- auditoria

## Estado del workspace actual

Workspace principal:

- `apps/backoffice-laravel`

Documentacion viva relevante:

- [docs/architecture/Bitacora_Backoffice_Clientes_y_Pets.md](/home/tomas/EstetiCAN_2/docs/architecture/Bitacora_Backoffice_Clientes_y_Pets.md)
- [docs/architecture/README.md](/home/tomas/EstetiCAN_2/docs/architecture/README.md)
- [docs/architecture/Extracto_Modulos_BD_Antigravity_Estetican.md](/home/tomas/EstetiCAN_2/docs/architecture/Extracto_Modulos_BD_Antigravity_Estetican.md)
- [docs/architecture/Especificacion_Tecnica_Clientes_Backoffice.md](/home/tomas/EstetiCAN_2/docs/architecture/Especificacion_Tecnica_Clientes_Backoffice.md)
- [docs/architecture/Especificacion_Tecnica_Mascotas_Backoffice.md](/home/tomas/EstetiCAN_2/docs/architecture/Especificacion_Tecnica_Mascotas_Backoffice.md)
- [docs/architecture/Especificacion_Tecnica_Fotos_Mascota_Backoffice.md](/home/tomas/EstetiCAN_2/docs/architecture/Especificacion_Tecnica_Fotos_Mascota_Backoffice.md)
- [docs/architecture/Guia_Estructura_BD_Tablas_Normalizadas.md](/home/tomas/EstetiCAN_2/docs/architecture/Guia_Estructura_BD_Tablas_Normalizadas.md)

## Modulos del negocio

Resumen actualizado a partir del extracto y del estado del repo:

| Modulo | Estado de implementacion observado | Nota |
|---|---|---|
| Leads | parcial / no revisado a detalle en esta iteracion | existe en dominio y DB sugerida |
| Clientes | activo | CRUD operativo con relaciones |
| Mascotas | activo | CRUD embebido y gestion especializada |
| Servicios | activo | CRUD operativo con rol de operador canonico |
| Tipos de operador | activo | catalogo `operator_roles` ya desacoplado de texto libre |
| Operadores | activo | ficha operativa, foto y base de sucursal |
| Sucursales | activo | CRUD con direccion atomizada y coordenadas |
| Spa bookings | pendiente de aterrizaje funcional completo | dominio presente |
| Hotel reservations | pendiente de aterrizaje funcional completo | dominio presente |
| Stays | presente en modelo de datos | no documentado aqui a detalle |
| Estados operativos | presente en modelo de datos | no documentado aqui a detalle |
| Notificaciones | presente en modelo de datos | no documentado aqui a detalle |
| Auditoria | presente en modelo de datos | no documentado aqui a detalle |

## Convenciones tecnicas observadas

- stack actual: Laravel 13, PHP 8.3+, Sail, MySQL, Redis
- UI server-side con Blade y Bootstrap
- rutas web como principal entrypoint para modulos revisados
- pruebas feature en PHPUnit
- documentacion funcional viva en Markdown dentro de `docs/architecture`

## Infraestructura local

Scripts operativos:

- [scripts/levantar_backoffice.sh](/home/tomas/EstetiCAN_2/scripts/levantar_backoffice.sh)
- [scripts/apagar_backoffice.sh](/home/tomas/EstetiCAN_2/scripts/apagar_backoffice.sh)

Reglas verificadas:

- levantar siempre primero WSL/Linux y luego Docker
- `levantar_backoffice.sh` espera disponibilidad de MySQL antes de migrar
- `levantar_backoffice.sh` asegura `storage:link`
- para entorno local actual, `APP_URL` debe ser `http://localhost:8000`
- existe soporte adicional de HTTPS local mobile-first con `mkcert` + Caddy sobre `https://<ip>:8443`
- Android puede requerir importar la CA raiz local desde `.certs/rootCA.crt`
- para comandos Laravel con dependencia de DB, preferir Sail; en host siguen fallando resoluciones como `mysql`

## Acceso local mobile-first

Estado operativo verificado al cierre de hoy:

- proxy HTTPS local expuesto en LAN sobre `https://192.168.90.20:8443`
- Laravel ya confia en el proxy inverso para generar enlaces seguros de forma global
- el proxy local elimina `Alt-Svc` para no inducir navegacion a `:443` cuando el puerto real publicado es `8443`
- la portada y la navegacion principal ya renderizan URLs absolutas seguras sin `http://` ni `:0`
- la navegacion movil usa un menu hamburguesa nativo `details/summary` para no depender del colapso JS de Bootstrap en Android

Decisiones operativas:

- mantener `http://localhost:8000` como URL local base de escritorio y desarrollo interno
- usar `https://<ip-lan>:8443` cuando el flujo de validacion sea desde celular en la misma red privada
- si el telefono no abre el sitio, revisar primero: perfil privado de la Wi-Fi, acceso al puerto `8443` y confianza de la CA local

## Modulo Clientes: resumen ejecutivo

Referencia detallada:

- [docs/architecture/Especificacion_Tecnica_Clientes_Backoffice.md](/home/tomas/EstetiCAN_2/docs/architecture/Especificacion_Tecnica_Clientes_Backoffice.md)

Puntos clave:

- cliente es entidad comercial principal
- relaciones activas: direcciones, telefonos, mascotas
- las mascotas vivas se muestran en index y show
- edit permite administracion mas amplia, incluyendo acceso a dependencias de mascota

## Modulo Mascotas: resumen ejecutivo

Referencia detallada:

- [docs/architecture/Especificacion_Tecnica_Mascotas_Backoffice.md](/home/tomas/EstetiCAN_2/docs/architecture/Especificacion_Tecnica_Mascotas_Backoffice.md)

Puntos clave:

- mascota es la entidad operativa principal dentro del flujo cliente/mascota
- `pets` fue refactorizada para dejar solo datos base estables
- alertas medicas viven en `pet_medical_alerts`
- fotos generales viven en `pet_photos`
- existe pipeline de foto optimizada + miniatura derivada + soporte EXIF

## Submodulo Fotos de Mascota: resumen ejecutivo

Referencia detallada:

- [docs/architecture/Especificacion_Tecnica_Fotos_Mascota_Backoffice.md](/home/tomas/EstetiCAN_2/docs/architecture/Especificacion_Tecnica_Fotos_Mascota_Backoffice.md)

Puntos clave:

- `pet_photos` es el almacenamiento canonico de fotos generales
- la UI de clientes debe consumir miniatura, no imagen principal
- el pipeline actual genera principal optimizada y derivado thumbnail
- EXIF puede poblar `taken_at` si el campo llega vacio

## Guia de Base de Datos Normalizada

Referencia detallada:

- [docs/architecture/Guia_Estructura_BD_Tablas_Normalizadas.md](/home/tomas/EstetiCAN_2/docs/architecture/Guia_Estructura_BD_Tablas_Normalizadas.md)

Uso recomendado:

- tomarla como mapa relacional rapido para desarrollo externo
- usarla junto con las especificaciones separadas por modulo

## Divergencias o puntos de atencion

Estas diferencias deben estar claras para cualquier externo:

- hay migraciones base que no reflejan por si solas el estado actual del dominio; hay migraciones posteriores que lo completan
- el valor historico `profile` en `pet_photos.photo_type` convive con la decision actual de usar `perfil`
- el modulo de clientes exige email en formulario, aunque el esquema base lo deja nullable

## Foto y almacenamiento publico

Resumen tecnico:

- `pet_photos.photo_url` guarda la ruta principal optimizada
- la miniatura se deriva a partir de la misma ruta cambiando `original/` por `thumbs/`
- las URLs publicas dependen del disco `public` y de `APP_URL`
- para diagnostico local, si una foto da 404 pero existe en disco, revisar primero `APP_URL`

## Pendientes abiertos de negocio y arquitectura

- agenda de servicios programados con estados y penalizacion por `no_show`
- definicion final de overlay o version etiquetada de fotos de mascota
- consolidacion de documentacion para modulos no revisados en esta iteracion
- aterrizar el modulo transversal de recursos fisicos usando `branches` ya implementado como cimiento

## Seguimiento del modulo de recursos y activos

La checklist detallada de `resources` deja de vivir duplicada en este manual.

Fuente canonica:

- revisar y actualizar la seccion `Checklist previsible del modulo de recursos y activos` en `docs/architecture/Bitacora_Backoffice_Clientes_y_Pets.md`

Resumen ejecutivo vigente:

- `branches` ya esta consolidado como cimiento multisucursal con CRUD operativo
- la prioridad operativa inmediata posterior al ultimo cierre registrado es corregir `Alta de Clientes` y consolidar la agenda actual como `Agenda SPA`
- `resources` sigue siendo el siguiente bloque modular fuerte, pero debe retomarse despues de estabilizar la incidencia base de clientes y la estructura paraguas de `Agenda`
- al retomar `resources`, no volver a abrir `branches`
- la prioridad tecnica de ese frente ya no es un catalogo generico primero: debe arrancar por `jaulas` como activo compartido critico y por su capa de disponibilidad temporal
- `resource_allocations` debe modelar no solo ocupacion o reserva, sino tambien limpieza intra horarios, mantenimiento, aislamiento y bloqueo manual
- la regla aprobada es que `Jaula X` se asigna como activo explicito por ventana de tiempo y no por inferencia desde la mascota; el buffer de limpieza debe bloquearse como ventana separada del activo
- el cimiento backend de ese frente ya existe: tablas `resources` y `resource_allocations`, relaciones base y servicio de asignacion con validacion de traslape mas buffer de limpieza configurable
- `Agenda SPA` ya quedo conectada como primer consumidor real de esa capa: puede asignar, mover y liberar jaulas desde alta, reprogramacion, cancelacion y `no_show`
- despues viene la integracion de esa disponibilidad con `spa_bookings`, `hotel_reservations` y `stays`
- mantenimiento, auditoria y reportes quedan como la siguiente ola, no como primer corte

## Checklist de Seguridad y Login Jerárquico (2026-03-28)

- [ ] Crear usuario master Admin (admin/admin), no editable ni borrable
- [ ] Implementar login y logout (Blade personalizado, sin registro público)
- [ ] Middleware global: proteger todos los módulos, redirigir a login si no hay sesión
- [ ] CRUD de usuarios:
    - [ ] Listar usuarios
    - [ ] Crear usuario (solo Admin)
    - [ ] Editar usuario (solo Admin, excepto Admin master)
    - [ ] Borrar usuario (solo Admin, excepto Admin master)
    - [ ] Asignar permisos por módulo (checkboxes o roles)
- [ ] Permisos por módulo: solo usuarios con permiso pueden acceder
- [ ] El CRUD de usuarios solo visible/accesible para Admin
- [ ] Tiempo de caducidad de la app configurable en archivo (ej: config/app.php)
- [ ] Al expirar el tiempo, mostrar mensaje y bloquear acceso
- [ ] Documentar flujo y configuración en manual técnico

---

> El flujo de login y permisos debe ser consistente en escritorio y móvil. El usuario Admin debe existir siempre y no poder ser eliminado ni editado por otros usuarios.

## Criterio de continuidad documental

Para cambios futuros:

- actualizar primero la bitacora cuando el cambio sea funcional, operativo, de prioridad o de cierre diario
- sincronizar despues este Markdown editable cuando el cambio deba consolidarse como criterio tecnico, resumen transversal o referencia de arquitectura
- para ids tecnicos de pantalla, usar camel labeling sin separadores y con prefijos modulares canonicos; baseline vigente: `Hom`, `Cli`, `Pet`, `Ag`, `Spa`, `Bra`, `Ope`, `OprRol`, `Ser`, `SysSet`, con reservados `AgHot`, `Res` y `Vet`
- si el cambio afecta cliente, actualizar tambien su especificacion separada
- si el cambio afecta mascota, actualizar tambien su especificacion separada
- si el cambio afecta fotos de mascota, actualizar tambien su especificacion separada
- si el cambio afecta relaciones de datos o normalizacion, actualizar la guia de BD
- la bitacora es la fuente mas reciente de verdad operativa y cronologica; este manual resume, ordena y consolida la capa tecnica transversal

## Respaldo incremental y sincronización con GitHub (xqtor4u)

- Antes de realizar cambios arquitectónicos o de UX, se recomienda ejecutar el script de respaldo automático:

  Ruta del script:
  `/home/tomas/EstetiCAN_2/scripts/backup_push_ux.sh`

- El script realiza:
  - Backup incremental de las carpetas clave de UX (`views`, `public`, `js`) en `/home/tomas/EstetiCAN_2/backups/ux/`.
  - Inicializa (si es necesario) y sincroniza el backup con el repositorio privado de GitHub `estetican-backups` bajo el usuario `xqtor4u`.

- Uso:
  1. Dar permisos de ejecución:
     `chmod +x scripts/backup_push_ux.sh`
  2. Ejecutar el script antes de cada bloque de cambios:
     `./scripts/backup_push_ux.sh "Mensaje opcional de commit"`

- El historial de backups queda disponible tanto localmente como en la nube (GitHub), permitiendo revertir cualquier cambio importante de UX.

## Referencia de UX y experiencia final

- Consulta la propuesta y evolución de la experiencia de usuario (UX) en:
  - [docs/architecture/UX_Landing_y_Flujos.md](UX_Landing_y_Flujos.md)

- Objetivo: la landing debe mostrar los accesos rápidos y acciones clave (alta rápida, nueva cita, búsqueda, agenda) en la parte superior, visibles sin scroll, para máxima eficiencia operativa.

- Los botones y accesos más usados deben estar siempre accesibles arriba, evitando que el usuario tenga que desplazarse para operar lo esencial.