---
tags: [manual, app]
---

# 07 Tipos de operador

**Para qué sirve:** crear los puestos de tu equipo (por ejemplo «Groomer», «Auxiliar», «Bañador») y decir qué servicios trae cada puesto.

**Antes de empezar:** [[06 Servicios y precios]]. Los servicios ya tienen que existir para ponerlos en el puesto.

**Dónde está:** **RH → Tipos de operador**.

> [!info] Puesto no es lo mismo que permiso
> El **tipo de operador** dice **qué servicios sabe hacer** alguien. Lo que puede **ver o tocar** en la app y en el backoffice son sus permisos, y esos se ponen en su usuario ([[08 Usuarios, roles y permisos]]).

## Pasos

### Crear el puesto
1. Toca **Crear tipo**.
2. Escribe la **Clave** y el **Nombre**. Por ejemplo: GROOMER / «Groomer».
3. Si quieres, pon el **Acrónimo** (3 letras, por ejemplo GRO) y el **Costo por hora base**.
4. Escribe la **Descripción**.
5. Deja marcado **Tipo de Operador Activo**.
6. Toca **Guardar tipo**.

### Decir qué servicios trae el puesto
1. Abre el puesto y toca **Editar tipo**.
2. En **Servicios de la plantilla**, marca los servicios que hace todo el que tenga este puesto.
3. Toca **Guardar plantilla**.

### Ejemplo
| Puesto | Servicios de la plantilla |
|---|---|
| Groomer básico | Baño, secado, corte de uñas |
| Groomer avanzado | Todo lo del básico, más cortes de raza y deslanado |
| Auxiliar | Baño, secado |

Con **Abierto a todos los operadores** prendido en un servicio ([[06 Servicios y precios]]), ese servicio no necesita estar en ningún puesto.

## Efecto en el mob
- Al asignar el puesto a un operador ([[09 Operadores]]), sus servicios entran a la lista del operador. El celular lo ofrece en **Elegir operador…** para esos servicios ([[04 Servicio y precio]]).

## Qué vas a ver
- El puesto en la lista. En su detalle, **Uso actual**: cuántos operadores lo tienen.

## Si algo sale mal
| Lo que ves | Qué hacer |
|---|---|
| Error en **Clave**, **Nombre** o **Acrónimo** | Ya existe otro puesto con ese dato, o el acrónimo no tiene 3 letras o números en mayúsculas. |
| No aparece **Tipos de operador** en el menú | Si no ves esta opción, tu usuario no tiene acceso; pídelo al administrador principal. |

> [!info] Casilla sin efecto
> El puesto tiene la casilla **Permitir loguearse al sistema (Backoffice)**. Hoy **no cambia nada**: quién puede entrar se decide en cada usuario.

**Siguiente:** [[08 Usuarios, roles y permisos]]
