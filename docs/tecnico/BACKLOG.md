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
| ~~BL-007~~ | ~~Verificar Transform Rules Cloudflare: X-Frame-Options, Referrer-Policy, Permissions-Policy~~ **COMPLETADO** | Seguridad | Headers en nginx de mob; backoffice ya los tenía vía middleware Laravel |

### Prioridad Alta — App Móvil (operador)

| ID | Ítem | Tipo | Notas |
|---|---|---|---|
| ~~BL-020~~ | ~~Breadcrumbs en todas las pantallas vía ScreenHeader~~ **COMPLETADO** | Feature | |
| ~~BL-023~~ | ~~App móvil: Selector de operador + agenda individual + renombrar Groomer→Operador~~ **COMPLETADO** | Feature | screenTags MobOpPkr/MobOpAg; breadcrumb dinámico; compresión de fotos |
| BL-024b | WhatsApp Fase 2: confirmación de cliente, CRM completo, bandeja de apertura/cierre diaria con doble vía | Feature/CRM | Fase 1 completada (ver Completados). Alcance futuro: recepción de respuestas, historial conversacional |

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
| ~~BL-022~~ | ~~App móvil: gestión de caja — sesión activa, registrar movimientos, balance de movimientos~~ **COMPLETADO** | Feature | Ver NT-011 sobre filtro sucursal en cobros |
| ~~BL-021~~ | ~~Migrar registros históricos de `cash_ledgers`/`bank_ledgers` a asientos contables~~ **COMPLETADO** | Mantenimiento | Comando `finanzas:migrar-ledgers-historicos`; idempotente; ejecutado en prod |

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
| BL-013 | Push a GitHub + deploy mov.estetican.org en producción | 15/06/2026 | `338690a` |
| BL-020 | Breadcrumbs universales en app móvil vía ScreenHeader | 16/06/2026 | `0d6a1a7` |
| BL-015 | App móvil: historial multi-modelo (SPA + Hotel) en MobPetJobs con filtros dinámicos | 15/06/2026 | `c9f1a2d` |
| BL-010 | App móvil: foto de mascota no se mostraba — URL relativa → `Storage::disk('public')->url()` | 14/06/2026 | — |
| BL-011 | App móvil: foto en PetSearch misma raíz que BL-010 — misma corrección | 14/06/2026 | — |
| BL-014 | `booking_grace_minutes` — verificado, funciona correctamente | 14/06/2026 | — |
| BL-017 | Módulo contable: 8 tablas, 8 modelos, 3 seeders, AccountingService, `account_id` en services | 14/06/2026 | — |
| BL-018 | Backoffice: pantallas Finanzas (accounts, payment_methods, document_series, cash_registers) | 14/06/2026 | — |
| BL-020 | App móvil: cobro con métodos dinámicos desde API + Payment model + asiento contable | 14/06/2026 | — |
| BL-019 | Backoffice: apertura/corte de caja + movimientos (retiro, depósito, gasto, pérdida, entrada) con póliza doble entrada automática | 15/06/2026 | — |
| BL-022 | App móvil: gestión de caja — ver sesión activa + registrar movimientos con póliza automática | 16/06/2026 | — |
| BL-007 | Cabeceras seguridad HTTP: X-Frame-Options, Referrer-Policy, Permissions-Policy en nginx de mob | 23/06/2026 | `1769893` |
| BL-021 | Comando `finanzas:migrar-ledgers-historicos` — JE para cash_ledgers y bank_ledgers históricos | 23/06/2026 | `d9097c0` |
| BL-023 | App móvil: Groomer→Operador + breadcrumb MobOpPkr + compresión imágenes + sync users↔operators | 30/06/2026 | `9c5c050` `58e92e4` |
| BL-024 | Backoffice: recordatorios WhatsApp Fase 1 — bandeja diaria con selección por checkbox, plantillas con variables, envío manual vía wa.me (sin automatización) | 01/07/2026 | `44084ae` `e754d27` |
| — | Backoffice: cambiar dueño de cualquier mascota (modal en `pets/show.blade.php`) | 02/07/2026 | `1e2713e` |
| BL-025 | Programar servicio (AgSpaCre, web y móvil): redondeo de hora a 5 min, fix de hora manual, horario operativo configurable, operador obligatorio + validación de traslape por operador | 02/07/2026 | `9161146` `3888b3c` |
| — | App móvil: fix `loadOccupied` no expandía slots ocupados por `duration_minutes` — permitía doble-agendar al mismo operador (ver NT-018) | 03/07/2026 | `71eae72` |
| BL-026 | Agenda Universal (web): vista Día/Semana/Mes estilo Google Calendar — toggle server-driven, grid semana (lunes-domingo) y mes (5-6 semanas, +3 chips y "+N más"), sin librería JS nueva | 03/07/2026 | `300148e` |
| BL-027 | Agenda móvil (Universal MobAgGbl y por operador MobOpAg): vista Día/Semana/Mes — `/api/agenda` acepta `view=day\|week\|month`. Semana/Mes corregidas a grid tipo Google Calendar (7 columnas/celdas con puntos de color) a pedido del usuario, reemplazando la lista agrupada inicial | 03/07/2026 | `c3cd3d0` `8fb7c99` `8682040` |

---

## Reglas de gestión

- Un ítem **no se elimina** — se mueve a Completados con fecha y referencia de commit.
- Si un ítem genera una NT, agregar referencia en la columna Notas.
- Si un ítem se descarta (won't do), moverlo con motivo documentado.
