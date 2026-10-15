---
tags: [manual, app]
---

# 09 Operadores

**Para qué sirve:** completar la ficha de trabajo de cada persona de piso: su puesto, su sucursal base, los días y horas que trabaja, y los servicios que hace.

**Antes de empezar:**
- La persona ya tiene usuario con **¿Es personal operativo?** marcado ([[08 Usuarios, roles y permisos]]). Así su ficha de operador ya existe.
- Ya existen sus puestos ([[07 Tipos de operador]]) y sus servicios ([[06 Servicios y precios]]).

**Dónde está:** **RH → Operadores**.

## Pasos
1. Busca a la persona en la lista y toca **Editar**.
2. En **Tipos de operador**, marca su puesto o puestos. El primero que marcas es el principal.
3. En **Base de operación**, elige su sucursal.
4. Si quieres, pon el **Pago por hora**. Si lo dejas vacío, se usa la tarifa del puesto.
5. En **Horario de trabajo semanal**, para cada **Día**:
   - deja marcado **Trabaja** si ese día viene, o desmárcalo si descansa;
   - ajusta la **Hora inicio** y la **Hora fin**.

   Viene lleno con el horario general del negocio para los 7 días. **Revísalo siempre**: si no desmarcas su día de descanso, se le puede agendar ese día.
6. Deja marcado **Operador activo**.
7. Toca **Actualizar operador**.
8. Más abajo, en **Servicios que realiza**, revisa su lista. Ya trae los servicios de su puesto (**Plantilla de rol**). Agrega o quita servicios sueltos y toca **Guardar servicios**.

> [!warning] Los datos personales mandan desde el usuario
> Nombre, teléfono, dirección, fecha de ingreso y **activo / inactivo** se copian desde su **usuario** cada vez que guardas el usuario. Corrígelos en **RH → Usuarios**. Si los cambias solo aquí, se pierden la próxima vez que alguien guarde su usuario.

## Efecto en el mob
- El **horario semanal** decide cuándo se le puede agendar. Fuera de su horario, el celular dice «…no labora en el horario indicado» al guardar ([[12 Horarios no disponibles]]).
- **Servicios que realiza** decide para qué servicios aparece en **Elegir operador…** ([[04 Servicio y precio]]).
- Solo los operadores **activos** aparecen al agendar.

## Qué vas a ver
- **Operador creado.** u **Operador actualizado.**
- En **Servicios que realiza**, cada servicio con su origen: **Abierto a todos**, **Plantilla de rol** o **Capacidad directa**.

## Si algo sale mal
| Lo que ves | Qué hacer |
|---|---|
| **Aún no hay tipos de operador. Crea primero el catálogo para poder asignarlos.** | Ve a [[07 Tipos de operador]]. |
| No aparece la sucursal en **Base de operación** | Créala en [[02 Sucursales]]. |
| La persona no aparece en la lista | Revisa que su usuario tenga **¿Es personal operativo?** ([[08 Usuarios, roles y permisos]]). |
| No ves **RH → Operadores** | Si no ves esta opción, tu usuario no tiene acceso; pídelo al administrador principal. |

> [!danger] No uses «Eliminar» con un operador
> Eliminar un operador lo borra para siempre: **sus citas pasadas se quedan sin operador** y se pierde su historial. Para que alguien deje de trabajar, sigue [[03 Dar de baja a un operador]].

**Siguiente:** [[10 Métodos de pago y cajas]]
