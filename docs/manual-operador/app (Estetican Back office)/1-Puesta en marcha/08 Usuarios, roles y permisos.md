---
tags: [manual, app]
---

# 08 Usuarios, roles y permisos

**Para qué sirve:** darle a cada persona su usuario para entrar al backoffice y a la app del celular, y decidir qué puede hacer.

**Antes de empezar:** [[02 Sucursales]] y [[07 Tipos de operador]]. Necesitas ser **Administrador**.

**Dónde está:** **RH → Usuarios**.

> [!important] No hay roles armables: los permisos van por persona
> EstetiCAN no tiene una pantalla de «roles» que armes una vez y luego asignes. Cada usuario tiene **dos niveles**:
> - **Administrador**: puede hacer **todo**, sin importar las casillas.
> - **Operador / Técnico**: solo lo que le marques en sus casillas.
>
> Para dos personas con el mismo puesto, marca las mismas casillas en las dos.

## Pasos

### Crear el usuario
1. Toca **Alta de usuario**.
2. En **Identidad y Autenticación**:
   - escribe el **Nombre de usuario (login)**, que es con el que entra;
   - escribe **Nombre(s)** y apellidos;
   - escribe el **Correo electrónico**, que es obligatorio y no se puede repetir;
   - escribe la **Contraseña** (mínimo 6 caracteres) y repítela en **Confirmar contraseña**.
3. En **Acceso al Sistema**:
   - deja marcado **Permitir acceso al Backoffice**;
   - en **Rol Administrativo**, elige **Administrador** u **Operador / Técnico**;
   - en **Estado del Empleado**, deja **Empleado Activo**.
4. En **Sucursal asignada**, elige su sucursal. **Es importante:** las citas que agende esta persona quedan en esa sucursal, y Caja la usa para saber qué sucursal opera.
5. Si es personal de piso, en **Perfil de Operador** marca **¿Es personal operativo?**. Escribe su **Código de operador** (por ejemplo GRO-001).
6. Si quieres que pueda apartar sus propias vacaciones desde el celular, marca **Puede bloquear su propia disponibilidad**.
7. Marca sus permisos (ver abajo).
8. Toca **Crear Usuario**.

> [!warning] Primero el usuario, después el operador
> Al marcar **¿Es personal operativo?**, se crea su ficha de operador. Después la completas en [[09 Operadores]].
> **No** des de alta primero a la persona en **RH → Operadores**: esa ficha no se puede ligar después a un usuario, y te quedaría duplicada.

### Las casillas de permisos (solo para Operador / Técnico)
- **Caja**: **Ver caja y reportes**, **Abrir turno**, **Cerrar turno**, **Registrar movimientos**, **Editar movimientos (concepto/notas)**, **Revertir movimientos**.
- **Agenda — visibilidad**: **Ver agenda de todos los operadores (no solo la propia)**. Sin esta casilla, solo ve sus propias citas.
- **Matriz de Permisos (CRUD)**: para cada ventana (**Agenda**, **Clientes**, **Mascotas**, **Recursos y Sucursales**, **Catálogos**, **Configuración**, **Usuarios**) marca **Ver**, **Crear**, **Editar** o **Borrar**.

### Ejemplos de perfiles
| Perfil | Rol | Casillas sugeridas |
|---|---|---|
| **Recepción** | Operador / Técnico | Agenda: Ver, Crear, Editar · Clientes: Ver, Crear, Editar · Mascotas: Ver, Crear, Editar · Recursos y Sucursales: Ver · Catálogos: Ver · **Ver agenda de todos** · Caja: Ver caja y reportes, Abrir turno, Cerrar turno, Registrar movimientos |
| **Groomer avanzado** | Operador / Técnico | Agenda: Ver, Crear, Editar · Clientes: Ver · Mascotas: Ver, Editar · Recursos y Sucursales: Ver · Catálogos: Ver · **Ver agenda de todos** · Puede bloquear su propia disponibilidad |
| **Groomer básico** | Operador / Técnico | Agenda: Ver, Editar · Mascotas: Ver · Recursos y Sucursales: Ver · Puede bloquear su propia disponibilidad |
| **Auxiliar** | Operador / Técnico | Agenda: Ver · Mascotas: Ver · Recursos y Sucursales: Ver |

**Recursos y Sucursales: Ver** hace falta para que le salga la lista de sucursales al hacer check-in en el celular.

> [!warning] Lo que hoy solo puede hacer un Administrador
> Estas cosas **no tienen casilla**. Un **Operador / Técnico** no las puede hacer, por más casillas que marques:
> - **Agendar desde el celular.** La pantalla **Nueva cita** necesita ver la lista de operadores, y eso no tiene casilla. Con un **Operador / Técnico**, la pantalla falla al elegir la hora. El **detalle** de las citas sí funciona.
> - **Cobrar** una cita (en el celular y en el backoffice).
> - Crear **citas recurrentes** y forzar el horario («Agendar de todas formas»).
> - Entrar en el backoffice a **RH → Operadores**, a la sección **WhatsApp**, a **Hotel**, a **Artículos**, a **Veterinaria** y al **Tablero**.
> - Entrar a **Configuración del sistema**, **Usuarios**, **Bitácora** y **Finanzas**, aunque marques **Configuración** o **Usuarios** en la matriz.
>
> Si alguien de recepción tiene que agendar en el celular o cobrar, hoy tiene que ser **Administrador**. Los perfiles de la tabla de arriba sirven hoy para quien **trabaja** las citas en el celular: verlas, iniciarlas, terminarlas, anotar y avisar por WhatsApp. El aviso por WhatsApp desde la cita necesita **Clientes: Ver**.

## Efecto en el mob
- El **Nombre de usuario (login)** y la **Contraseña** son con los que entra al celular ([[01 Inicio del día]]).
- Las casillas deciden qué botones ve en el celular. Donde falta una, el celular dice «pide a tu administrador que te dé acceso».
- **Sucursal asignada** decide en qué sucursal quedan sus citas y con qué horario se pinta su calendario ([[05 Agendar día y hora]]).

## Qué vas a ver
- **Usuario creado correctamente y perfil operativo vinculado.**
- En la lista, el usuario como **Activo** y **Habilitado**, y su perfil (**Administrador** o **Staff**).

## Si algo sale mal
| Lo que ves | Qué hacer |
|---|---|
| **Este … ya está registrado.** | El usuario o el correo ya existe. Usa otro. |
| **La confirmación de … no coincide.** | Escribe la misma contraseña en los dos campos. |
| **El campo … debe tener al menos 6 caracteres.** | La contraseña es muy corta. |
| **Solo un administrador del sistema puede acceder a esta sección.** | Tu usuario no es **Administrador**. |

> [!danger] Desactivar no corta la entrada al backoffice
> Hoy, **Baja / Inactivo** y quitar **Permitir acceso al Backoffice** sí le cierran la **app del celular**, pero **no** le impiden entrar al **backoffice**. Si alguien se va, **cámbiale también la contraseña** ([[03 Dar de baja a un operador]]).

**Siguiente:** [[09 Operadores]]
