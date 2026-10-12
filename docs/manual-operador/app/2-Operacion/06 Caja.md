---
tags: [manual, app]
---

# 06 Caja

**Para qué sirve:** abrir y cerrar el turno de caja de cada sucursal, revisar los cobros y registrar las entradas y salidas de efectivo.

**Antes de empezar:** cajas dadas de alta ([[10 Métodos de pago y cajas]]). Necesitas ser **Administrador**.

**Dónde está:** **Operaciones del negocio → Finanzas → Cajas** y **Finanzas → Sesiones de caja**.

## Pasos

### Abrir la caja (inicio del turno)
1. En **Cajas**, en la caja de tu sucursal, toca **Abrir sesión**.
2. Cuenta el efectivo con el que arrancas y escríbelo en **Fondo inicial**.
3. Si quieres, escribe **Notas (opcional)**.
4. Toca **Abrir caja**.

### Revisar el turno
1. Toca **Ver sesión**.
2. Revisa:
   - **Efectivo (caja)**: **Fondo inicial**, **Cobros efectivo**, **Entradas**, **Salidas** y **Esperado en caja**.
   - **Banco / Tarjeta**: los cobros con tarjeta o transferencia.
   - **Movimientos de caja**: retiros, depósitos al banco, gastos, pérdidas y entradas.

### Hacer el corte (fin del turno)
1. En la sesión, toca **Hacer corte**.
2. Cuenta el efectivo físico y escríbelo en **Efectivo contado**.
3. Revisa la **Diferencia estimada**.
4. Si hay diferencia, explícala en **Notas del corte (opcional)**.
5. Toca **Confirmar corte**.

## Efecto en el mob
- El operador también puede abrir y cerrar turno y registrar movimientos desde **Finanzas** en el celular. Necesita las casillas de **Caja** en su usuario y su **Sucursal asignada** ([[08 Usuarios, roles y permisos]]).
- Los cobros que hacen los operadores en el celular ([[10 Cobrar y entregar]]) aparecen en la sesión de caja.
- Hoy se puede cobrar **sin** tener la caja abierta.

> [!warning] Con más de una sucursal, el corte no separa
> Hoy, **Cobros efectivo** y **Esperado en caja** suman los cobros en efectivo de **todas las sucursales**, desde el corte anterior de esa caja. Con una sola sucursal cuadra bien. Con dos o más, el esperado sale **inflado**: compáralo contra el reporte **Métodos de pago** filtrado por sucursal ([[07 Reportes]]).

## Qué vas a ver
- Al abrir: **Sesión abierta.**
- Al cerrar: **Sesión cerrada. Caja cuadrada: sin diferencia.**, o la diferencia como **sobrante** o **faltante**.
- En **Sesiones de caja**, el historial con **Fondo inicial**, **Cierre**, **Diferencia** y **Estado** (**Abierta** o **Cerrada**).

## Si algo sale mal
| Lo que ves | Qué hacer |
|---|---|
| **La caja ya tiene una sesión abierta.** | Alguien ya abrió el turno. Úsalo o haz el corte primero. |
| En el celular: «Esta sucursal no tiene ninguna caja configurada.» | Crea la caja de esa sucursal ([[10 Métodos de pago y cajas]]). |
| **Solo un administrador del sistema puede acceder a esta sección.** | En el backoffice, Caja es solo para administradores. Los operadores usan la del celular. |

**Siguiente:** [[07 Reportes]]
