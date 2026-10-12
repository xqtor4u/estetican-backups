---
title: Manual del operador — EstetiCAN
tags: [manual]
---

# Manual del operador — EstetiCAN

Manuales de uso para el personal. Se revisaron contra la app real de producción. Esta carpeta se sincroniza con Syncthing hacia la PC Windows y queda versionada en el repo (`docs/manual-operador/`).

## mob — app de celular (`mov.estetican.org`)

El ciclo de atención de una mascota, en orden:

1. [[01 Inicio del día]] — entrar a la app y hacer check-in en tu sucursal.
2. [[02 Buscar mascota]] — encontrar al perro del cliente.
3. [[03 Alta de mascota y dueño]] — si es nuevo: dueño (nombre y celular) y mascota.
4. [[04 Servicio y precio]] — qué se le hace, cuánto cuesta y quién lo hace.
5. [[05 Agendar día y hora]] — elegir día y hora; qué significa cada color.
6. [[06 Cita recurrente]] — repetir la cita cada semana o cada mes, y fijar o descartar.
7. [[07 Operador y jaula]] — quién hace cada servicio y en qué jaula espera.
8. [[08 Durante el servicio]] — iniciar y terminar el trabajo.
9. [[09 Avisar por WhatsApp]] — «tu perro ya está listo».
10. [[10 Cobrar y entregar]] — cobrar, o dejar pendiente de cobro.
11. [[11 Mover, cancelar o reagendar]] — cambiar, cancelar o marcar que no llegó.
12. [[12 Horarios no disponibles]] — vacaciones, permisos, horarios y festivos.

## Backoffice — `app.estetican.org` (administradores y encargados)

### 1. Puesta en marcha (en orden)
- [[00 Checklist de arranque]] — la lista de todo lo que configuras antes del primer día.
- [[01 Datos del negocio]] — nombre, logo, horario general y días que abres.
- [[02 Sucursales]]
- [[03 Días inhábiles]] — festivos y cierres.
- [[04 Recursos y jaulas]]
- [[05 Módulos activables]] — Hotel, Veterinaria y Tienda.
- [[06 Servicios y precios]] — y quién puede hacer cada servicio.
- [[07 Tipos de operador]] — los puestos y sus servicios.
- [[08 Usuarios, roles y permisos]] — quién entra y qué puede hacer.
- [[09 Operadores]] — puesto, sucursal base, horario y servicios.
- [[10 Métodos de pago y cajas]]
- [[11 Artículos y stock]]
- [[12 WhatsApp]] — plantillas, bandeja, y qué falta para los recordatorios automáticos.

### 2. Supervisión diaria y semanal
- [[01 Tablero]]
- [[02 Agenda]] — ver, mover y cancelar citas.
- [[03 Series recurrentes]] — pausar, cancelar y extender.
- [[04 Clientes y mascotas]] — buscar, corregir y arreglar duplicados.
- [[05 Hotel]] — reservar estancias.
- [[06 Caja]] — abrir, revisar y cortar caja.
- [[07 Reportes]]

### 3. Cambios frecuentes
- [[01 Ausencia o permiso de un operador]]
- [[02 Cambiar el horario de un operador]]
- [[03 Dar de baja a un operador]]
- [[04 Cambiar un precio]]
- [[05 Abrir una sucursal nueva]]

## Recursos

- [[referencia-tecnica-mob]] — app de celular: para soporte y desarrollo, de dónde sale cada paso en el código, los permisos reales y lo que no existe o funciona distinto.
- [[referencia-tecnica-app]] — lo mismo para el backoffice.

## Convenciones

- `mob/` — notas de la app de celular.
- `app/` — notas del backoffice web: `1-Puesta en marcha/`, `2-Operacion/` y `3-Cambios/`.
- `_recursos/` — referencia técnica, imágenes y adjuntos.
