---
tags: [manual, app]
---

# 02 Sucursales

**Para qué sirve:** dar de alta cada local donde atiendes, con su dirección y su horario.

**Antes de empezar:** [[01 Datos del negocio]].

**Dónde está:** **Clientes → Sucursales**.

## Pasos
1. Toca **Crear sucursal**.
2. Escribe la **Clave** (corta, por ejemplo «CEN») y el **Nombre**. Ninguno de los dos se puede repetir con otra sucursal.
3. Llena la dirección. Con la dirección y el punto en el mapa se calcula el aviso de «fuera de zona».
4. Si esta sucursal abre en otro horario que el general, llena **Abre** y **Cierra**. Si los dejas vacíos, usa el horario general.
5. Si abre otros días que el general, marca **Días de operación propios** y elige los días.
6. Deja marcada **Sucursal activa**.
7. Toca **Guardar sucursal**.

## Efecto en el mob
- Las sucursales aparecen en la lista **— Sucursal —** cuando el operador hace check-in ([[01 Inicio del día]]).
- El horario y los días de la sucursal mandan sobre los generales al agendar ([[05 Agendar día y hora]]).
- Ojo: la sucursal de una cita **no** sale del check-in, sino de la **Sucursal asignada** del usuario que agenda ([[08 Usuarios, roles y permisos]]).

## Qué vas a ver
- El mensaje **Sucursal creada.**
- La sucursal en la lista, como **Activa**.

## Si algo sale mal
| Lo que ves | Qué hacer |
|---|---|
| Error en **Clave** o **Nombre** | Ya existe otra sucursal con ese dato. Usa otro. |
| Error en **Cierra** | La hora de cierre tiene que ser después de la de apertura. Si pones una, pon las dos. |
| **No se puede eliminar la sucursal porque todavía tiene operadores asignados.** | Primero cambia la sucursal base de esos operadores ([[09 Operadores]]). |
| No ves **Crear sucursal** | Si no ves esta opción, tu usuario no tiene acceso; pídelo al administrador principal. |

> [!warning] Mejor desactiva que eliminar
> Si eliminas una sucursal, se borran también sus jaulas, sus cajas y el historial de cortes de caja. Si ya no la usas, quítale **Sucursal activa** y guarda.

**Siguiente:** [[03 Días inhábiles]]
