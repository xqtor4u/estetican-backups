---
tags: [manual, app]
---

# 10 Métodos de pago y cajas

**Para qué sirve:** definir cómo te pueden pagar (efectivo, tarjeta, transferencia) y registrar las cajas físicas de cada sucursal para abrir y cerrar turno.

**Antes de empezar:** [[02 Sucursales]]. Necesitas ser **Administrador**.

**Dónde está:**
- **Operaciones del negocio → Finanzas → Métodos de pago**
- **Operaciones del negocio → Finanzas → Cajas**

## Pasos

### Métodos de pago
1. En **Métodos de pago**, toca **Nuevo método**.
2. Escribe el **Código** (en mayúsculas, por ejemplo EFECT) y el **Nombre** (por ejemplo «Efectivo»). El nombre es el que ve el operador.
3. En **Tipo**, elige **Efectivo**, **Tarjeta**, **Transferencia**, **Criptomoneda** o **Pasarela de pago**. Con **Efectivo**, el cobro cuenta como dinero en caja; con los demás, como banco.
4. Si quieres que el operador capture el número de autorización, marca **Requiere referencia**.
5. Si llevas contabilidad, elige la **Cuenta contable asociada**.
6. Deja marcado **Activo**.
7. Guarda.

### Cajas
1. En **Cajas**, toca **Nueva caja**.
2. Escribe el **Nombre de la caja** (por ejemplo «Caja principal») y elige la **Sucursal**.
3. Deja marcada **Caja activa**.
4. Toca **Crear caja**.

## Efecto en el mob
- Los métodos **activos** son los que el operador ve en **Método de pago** al cobrar ([[10 Cobrar y entregar]]). Con **Requiere referencia**, el celular le pide la **Referencia**.
- La caja de su sucursal es la que el operador abre y cierra desde **Finanzas** en el celular.
- Hoy se puede cobrar **sin** tener caja abierta.

## Qué vas a ver
- **Método de pago creado.** y **Caja creada.**
- En **Cajas**, cada caja con su sucursal y el botón **Abrir sesión**.

## Si algo sale mal
| Lo que ves | Qué hacer |
|---|---|
| En el celular: «No hay métodos de pago configurados.» | Crea al menos un método **Activo**. |
| **No se puede eliminar una caja con sesiones registradas.** | Desmarca **Caja activa** en vez de eliminarla. |
| **Solo un administrador del sistema puede acceder a esta sección.** | Finanzas es solo para administradores. |

**Siguiente:** [[11 Artículos y stock]]
