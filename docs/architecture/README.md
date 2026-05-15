# Documentacion de Arquitectura

Ultima actualizacion: 2026-03-26 12:03 CST

Indice rapido de documentacion tecnica y funcional del workspace.

## Documentacion viva principal

- [Bitacora_Backoffice_Clientes_y_Pets.md](/home/tomas/EstetiCAN_2/docs/architecture/Bitacora_Backoffice_Clientes_y_Pets.md)
- [Manual_Tecnico_Desarrollo_Modular_Antigravity_Estetican_EDITABLE.md](/home/tomas/EstetiCAN_2/docs/architecture/Manual_Tecnico_Desarrollo_Modular_Antigravity_Estetican_EDITABLE.md)
- [Guia_Estructura_BD_Tablas_Normalizadas.md](/home/tomas/EstetiCAN_2/docs/architecture/Guia_Estructura_BD_Tablas_Normalizadas.md)

## Especificaciones por modulo

- [Especificacion_Tecnica_Clientes_Backoffice.md](/home/tomas/EstetiCAN_2/docs/architecture/Especificacion_Tecnica_Clientes_Backoffice.md)
- [Especificacion_Tecnica_Mascotas_Backoffice.md](/home/tomas/EstetiCAN_2/docs/architecture/Especificacion_Tecnica_Mascotas_Backoffice.md)
- [Especificacion_Tecnica_Fotos_Mascota_Backoffice.md](/home/tomas/EstetiCAN_2/docs/architecture/Especificacion_Tecnica_Fotos_Mascota_Backoffice.md)

## Referencias historicas o de apoyo

- [Extracto_Modulos_BD_Antigravity_Estetican.md](/home/tomas/EstetiCAN_2/docs/architecture/Extracto_Modulos_BD_Antigravity_Estetican.md)
- [Manual_Tecnico_Desarrollo_Modular_Antigravity_Estetican.pdf](/home/tomas/EstetiCAN_2/docs/architecture/Manual_Tecnico_Desarrollo_Modular_Antigravity_Estetican.pdf)

## Criterio de uso

- usar el PDF solo como referencia historica
- usar la bitacora como fuente viva mas reciente para cambios funcionales, prioridades operativas, checklist activas y cierre diario
- usar el manual editable y las especificaciones separadas como capa estructurada de apoyo, sincronizada contra la bitacora cuando aplique
- toda actualizacion significativa en documentacion viva debe dejar fecha y hora dentro del documento editado
- actualizar la bitacora para cambios funcionales y decisiones de negocio con fecha y hora
- hacer corte de bitacora al final del dia antes de cerrar trabajo cuando haya habido cambios significativos
- actualizar la guia de BD cuando cambien relaciones o normalizaciones
- usar la checklist de `resources` en la bitacora como unica fuente de verdad para pendientes operativos de ese modulo
- toda checklist nueva debe incluir etiqueta de `Fecha de definicion`
- en UI, preferir plantillas base reutilizables y composicion/herencia de catalogos hijos antes que vistas aisladas por modulo

## Estado de cierre 2026-03-23

- infraestructura local y acceso mobile-first documentados con flujo HTTP local + HTTPS LAN
- sucursales, operadores, tipos de operador y servicios ya forman parte del baseline operativo documentado
- el siguiente bloque recomendado queda concentrado en `resources` y disponibilidad operativa, no en volver a abrir `branches`
- la checklist detallada de `resources` ya no se duplica en el manual editable; su seguimiento canonico vive en la bitacora

## Actualización 2026-03-27
- Se corrigió error de redeclaración en ResourceAllocationServiceInterface.
- Se detectó y documentó un bloqueo técnico: el contenedor laravel.test no puede resolver el hostname mysql, impidiendo pruebas y migraciones.
- Se realizaron intentos de diagnóstico y recreación completa del entorno Docker Compose, sin éxito.
- El avance en recursos y agenda queda bloqueado hasta resolver la conectividad interna Docker.
- Ver detalles y bitácora en Bitacora_Backoffice_Clientes_y_Pets.md.

## Cierre de sesión 2026-03-27
- Entorno de desarrollo y pruebas verificado, todos los tests pasan.
- Respaldo automático de base de datos implementado y validado.
- Bitácora y Task List actualizadas.
- No quedan incidencias técnicas abiertas.