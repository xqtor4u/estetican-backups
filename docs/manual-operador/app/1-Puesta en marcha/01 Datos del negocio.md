---
tags: [manual, app]
---

# 01 Datos del negocio

**Para qué sirve:** poner el nombre y el logo de tu estética, su horario general y los días que abre.

**Antes de empezar:** nada. Este es el primer paso ([[00 Checklist de arranque]]).

**Dónde está:** **Operación → Configuración del sistema**. Solo la ve un administrador.

## Pasos
La pantalla tiene varias secciones. Cada una se guarda con su propio botón, que dice **Guardar** más el nombre de la sección.

1. En **Identidad y Branding**:
   - escribe el **Nombre de la Estética / Clínica**;
   - sube el **Logo para Interfaz (Web)** y el **Favicon**;
   - escribe el **WhatsApp del negocio (con lada, sin espacios)**.

   Toca **Guardar identidad y branding**.
2. En **Operación Clínica**:
   - pon la **Hora de apertura** y la **Hora de cierre**, en formato de 24 horas (por ejemplo, 09:00 y 19:00);
   - marca los días que abres: **Abre lunes**, **Abre martes**… **Abre domingo**;
   - pon la **Tolerancia para iniciar servicio (minutos)**: cuánto antes o después de la hora se puede iniciar una cita sin que la app pregunte.

   Toca **Guardar operación clínica**.
3. En **Sistema**, revisa la **Zona horaria** y el **Formato de hora**. Guarda.
4. En **Datos Fiscales** (si das recibos), llena la **Razón Social**, el **RFC / Identificación Fiscal** y la **Dirección Fiscal**. Guarda.
5. En **Servicio de Correo**, solo si quieres mandar reportes por correo: llena los datos de tu proveedor de correo. Guarda.
6. En **Cobertura Geográfica**, pon el **Radio de cobertura (km)** que cubres. Guarda.

## Efecto en el mob
- El horario general es el rango de horas que se ve en el calendario del celular, si la sucursal no tiene horario propio ([[05 Agendar día y hora]]).
- En un día que no abres, el celular no deja guardar la cita: dice «El negocio no abre los (día)s.». Ojo: el calendario **no** marca ese día antes; el aviso sale hasta que el operador guarda.
- La tolerancia decide cuándo aparece el botón **Iniciar** en la agenda del celular ([[08 Durante el servicio]]).
- El radio de cobertura hace que salga el aviso «Fuera de zona habitual» al agendar.

## Qué vas a ver
- Arriba, un mensaje verde de guardado.
- El nombre y el logo nuevos en el backoffice y en la pantalla de entrada de la app del celular.

## Si algo sale mal
| Lo que ves | Qué hacer |
|---|---|
| **Solo un administrador del sistema puede acceder a esta sección.** | Tu usuario no es **Administrador**. Pídeselo al administrador principal. |
| No aparece **Configuración del sistema** en el menú | Si no ves esta opción, tu usuario no tiene acceso; pídelo al administrador principal. |
| El campo marca error en rojo | Revisa el formato. Las horas van como 09:00, no como 9 am. |

> [!info] Una opción que hoy no hace nada
> En la sección **Finanzas** está **Requerir apertura de caja para cobrar**. Hoy **no tiene efecto**: se cobra igual con o sin caja abierta.

**Siguiente:** [[02 Sucursales]]
