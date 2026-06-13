# Backlog — EstetiCAN
### Registro de Trabajo Pendiente

> **Uso:** Este archivo es la fuente de verdad del backlog. Actualizar en cada sesión.
> Cuando un ítem se completa, moverlo a la sección **Completados** con fecha.
> Los ítems en **Activos** son los candidatos para el próximo sprint.

---

## Activos

### Prioridad Alta — Seguridad y Estabilidad

| ID | Ítem | Tipo | Notas |
|---|---|---|---|
| BL-006 | Bloquear endpoint `/up` (health check Laravel — devuelve 200 público) | Seguridad | Información de infraestructura expuesta |
| BL-007 | Verificar Transform Rules Cloudflare: X-Frame-Options, Referrer-Policy, Permissions-Policy | Seguridad | Completar cabeceras HTTP de seguridad |

### Prioridad Media — Funcionalidad de UI y Configuración

| ID | Ítem | Tipo | Notas |
|---|---|---|---|
| BL-001 | Tema de UI: reparar persistencia y cambio reactivo de paleta de colores | Bug/Feature | El tema se pierde al recargar o no se aplica reactivamente |
| BL-002 | Favicon & Empresa: subida de favicon + datos generales del negocio | Feature | `SystemSetting` — branding básico |
| BL-003 | Email Avanzado: credenciales SMTP (usuario/password, puertos, SSL/TLS) | Feature | Actualmente solo tiene host configurado |
| BL-004 | Zonas Horarias: reemplazar selector UTC por selector completo | Feature | Selector actual solo muestra offsets UTC, no nombres de zona |

### Prioridad Alta — Nuevas Capacidades

| ID | Ítem | Tipo | Notas |
|---|---|---|---|
| BL-009 | Ecosistema Móvil: continuar `mob_apps/operador` (requiere WSL/Node nvm) | Feature | React 19 + Vite — API Laravel pendiente de conexión |
| BL-010 | App móvil: foto de mascota no se muestra en `ClientDetail` (lista de mascotas del dueño) — investigar cómo se construye la URL de imagen | Bug | `mob_apps/operador` |
| BL-011 | App móvil: foto de mascota tampoco se muestra en `PetSearch` (tarjetas y tabla) — misma investigación pendiente que BL-010 | Bug | `mob_apps/operador` |
| BL-012 | App clientes (futura): autoregistro de clientes — crear cuenta desde la app pública; usuarios del operador se dan de alta solo desde el backoffice | Feature | App separada, no `mob_apps/operador` |

### Prioridad Baja — Nuevas Capacidades

| ID | Ítem | Tipo | Notas |
|---|---|---|---|
| BL-008 | Reportes PDF: diseño e impresión de presupuestos, órdenes de trabajo y facturas | Feature | Evaluar `barryvdh/laravel-dompdf` o Browsershot |

---

## Completados

| ID | Ítem | Sesión | Commit |
|---|---|---|---|
| — | Fix subida de fotos: cropperjs v2→v1.6.2 | 25/05/2026 | `d1e4fdd` |
| — | Fix inicialización Cropper: shown.bs.modal + rAF | 25/05/2026 | `8c9a7e5` |
| — | Documentación técnica ITIL: NOTAS_TECNICAS, image-upload-system, ESTRATEGIA | 25/05/2026 | `0a593d4` |
| — | Push a GitHub + configuración SSH (clave `estetican-opi`) | 25/05/2026 | — |
| BL-005 | Cambiar password de `admin@localhost` desde la UI | 28/05/2026 | — |

---

## Reglas de gestión

- Un ítem **no se elimina** — se mueve a Completados con fecha y referencia de commit.
- Si un ítem genera una NT, agregar referencia en la columna Notas.
- Si un ítem se descarta (won't do), moverlo con motivo documentado.
