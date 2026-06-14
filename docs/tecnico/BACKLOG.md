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
| BL-006 | ~~Bloquear endpoint `/up`~~ **COMPLETADO** — movido a sección Completados | Seguridad | |
| BL-007 | Verificar Transform Rules Cloudflare: X-Frame-Options, Referrer-Policy, Permissions-Policy | Seguridad | Completar cabeceras HTTP de seguridad |

### Prioridad Alta — App Móvil (operador)

| ID | Ítem | Tipo | Notas |
|---|---|---|---|
| BL-013 | Push a GitHub (rama `main`) — acumular commits de sesiones 13-14/06/2026 | Mantenimiento | `git push origin main` desde OPi |

### Prioridad Media — Funcionalidad de UI y Configuración

| ID | Ítem | Tipo | Notas |
|---|---|---|---|
| BL-001 | Tema de UI: reparar persistencia y cambio reactivo de paleta de colores | Bug/Feature | El tema se pierde al recargar o no se aplica reactivamente |
| BL-002 | Favicon & Empresa: subida de favicon + datos generales del negocio | Feature | `SystemSetting` — branding básico |
| BL-003 | Email Avanzado: credenciales SMTP (usuario/password, puertos, SSL/TLS) | Feature | Actualmente solo tiene host configurado |
| BL-004 | Zonas Horarias: reemplazar selector UTC por selector completo | Feature | Selector actual solo muestra offsets UTC, no nombres de zona |

### Prioridad Alta — Módulo Financiero (sprint actual)

| ID | Ítem | Tipo | Notas |
|---|---|---|---|
| BL-019 | Backoffice: apertura y corte de caja por sucursal | Feature | cash_sessions — quién, cuándo, qué sucursal, diferencia al cierre |
| BL-021 | Migrar registros históricos de `cash_ledgers`/`bank_ledgers` a asientos contables | Mantenimiento | Script de migración de datos históricos |

### Prioridad Baja — Nuevas Capacidades

| ID | Ítem | Tipo | Notas |
|---|---|---|---|
| BL-008 | Reportes PDF: diseño e impresión de presupuestos, órdenes de trabajo y facturas | Feature | Evaluar `barryvdh/laravel-dompdf` o Browsershot |
| BL-012 | App clientes (futura): autoregistro de clientes — app pública separada | Feature | No es `mob_apps/operador` |

---

## Completados

| ID | Ítem | Sesión | Commit |
|---|---|---|---|
| — | Fix subida de fotos: cropperjs v2→v1.6.2 | 25/05/2026 | `d1e4fdd` |
| — | Fix inicialización Cropper: shown.bs.modal + rAF | 25/05/2026 | `8c9a7e5` |
| — | Documentación técnica ITIL: NOTAS_TECNICAS, image-upload-system, ESTRATEGIA | 25/05/2026 | `0a593d4` |
| — | Push a GitHub + configuración SSH (clave `estetican-opi`) | 25/05/2026 | — |
| BL-005 | Cambiar password de `admin@localhost` desde la UI | 28/05/2026 | — |
| BL-006 | Bloquear endpoint `/up` con secret key en `bootstrap/app.php` | 14/06/2026 | — |
| BL-009 | Ecosistema Móvil: app `mob_apps/operador` conectada a API real | 13/06/2026 | — |
| BL-010 | App móvil: foto de mascota no se mostraba — URL relativa → `Storage::disk('public')->url()` | 14/06/2026 | — |
| BL-011 | App móvil: foto en PetSearch misma raíz que BL-010 — misma corrección | 14/06/2026 | — |
| BL-014 | `booking_grace_minutes` — verificado, funciona correctamente | 14/06/2026 | — |
| BL-017 | Módulo contable: 8 tablas, 8 modelos, 3 seeders, AccountingService, `account_id` en services | 14/06/2026 | — |
| BL-018 | Backoffice: pantallas Finanzas (accounts, payment_methods, document_series, cash_registers) | 14/06/2026 | — |
| BL-020 | App móvil: cobro con métodos dinámicos desde API + Payment model + asiento contable | 14/06/2026 | — |

---

## Reglas de gestión

- Un ítem **no se elimina** — se mueve a Completados con fecha y referencia de commit.
- Si un ítem genera una NT, agregar referencia en la columna Notas.
- Si un ítem se descarta (won't do), moverlo con motivo documentado.
