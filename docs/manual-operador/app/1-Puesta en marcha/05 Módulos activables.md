---
tags: [manual, app]
---

# 05 Módulos activables

**Para qué sirve:** prender o apagar las áreas de negocio que no son estética: Hotel, Veterinaria y Tienda.

**Antes de empezar:** [[01 Datos del negocio]]. Necesitas ser **Administrador**.

**Dónde está:** **Operación → Configuración del sistema**, en una sección por módulo.

## Pasos
1. Busca la sección del módulo:
   - **Hotel (Hospedaje)**
   - **Veterinaria (Expediente Clínico)**
   - **Tienda y Proyectos**
2. Marca o desmarca **Módulo activo**.
3. Toca el botón **Guardar** de esa sección.

## Qué hace cada uno

| Módulo | Si lo prendes | Si lo apagas |
|---|---|---|
| **Hotel (Hospedaje)** | Puedes reservar estancias con jaula. Aparecen en el Tablero, en la Agenda y en los accesos rápidos ([[05 Hotel]]). | Desaparece todo lo de Hotel. **No borra** las estancias guardadas. |
| **Veterinaria (Expediente Clínico)** | Aparece el menú **Veterinaria**: expediente clínico y farmacia. Al agendar, avisa si a la mascota le faltan vacunas «core». | Desaparece el menú. No borra datos. |
| **Tienda y Proyectos** | Aparecen **Artículos** (con existencias por sucursal) y **Grupos** (combos). En **Porcentaje de utilidad sugerido** pones el margen para sugerir precios ([[11 Artículos y stock]]). | Desaparecen Artículos y Grupos. No borra datos. |

La estética (agenda, servicios, cobros) siempre está prendida.

## Efecto en el mob
- **Tienda**: con el módulo prendido, el celular muestra **Artículos** en el menú, a quien tenga ese permiso. Hoy solo los administradores lo tienen ([[08 Usuarios, roles y permisos]]).
- **Veterinaria**: con el módulo prendido, al agendar en el celular puede salir el aviso **Vacunas pendientes** ([[05 Agendar día y hora]]).
- **Hotel**: no aparece en el celular.

## Qué vas a ver
- El menú del backoffice cambia al recargar la página.

## Si algo sale mal
| Lo que ves | Qué hacer |
|---|---|
| Prendiste un módulo y no ves su menú | Recarga la página. Si sigue sin salir, tu usuario no tiene permiso para ese módulo. |
| **Solo un administrador del sistema puede acceder a esta sección.** | Pídelo al administrador principal. |

**Siguiente:** [[06 Servicios y precios]]
