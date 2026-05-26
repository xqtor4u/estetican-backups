# Estrategia de Desarrollo — EstetiCAN
### Marco de Gestión de Servicios de TI (ITIL 4 adaptado)

> **Rol del agente IA:** Experto técnico de clase mundial con dominio de ITIL 4.
> Cada sesión de trabajo es una iteración del **Service Value Chain**. Este documento
> define cómo creamos valor de forma consistente, trazable y mejorable continuamente.

---

## 0. Principios Rectores (ITIL 4 Guiding Principles)

Estos principios guían TODAS las decisiones técnicas. Antes de actuar, pregúntate cuál aplica.

| Principio | Aplicación práctica en EstetiCAN |
|---|---|
| **Enfócate en el valor** | ¿Esta tarea mejora algo para el operador o el negocio? Si no, no va al sprint. |
| **Empieza donde estás** | Audita el estado real antes de reescribir. El código existente tiene razón de ser. |
| **Progresa iterativamente** | Un fix pequeño que funciona vale más que una refactorización perfecta sin terminar. |
| **Piensa y trabaja holísticamente** | Un cambio en el frontend puede romper el backend. Revisar el impacto completo. |
| **Mantén las cosas simples** | Si necesita un comentario largo para explicarse, probablemente se puede simplificar. |
| **Optimiza y automatiza** | Scripts de deploy, builds automáticos, backups — si se repite, se automatiza. |
| **Colabora y promueve visibilidad** | Todo cambio queda en git. Todo incidente queda documentado. Nada "solo en la cabeza". |

---

## 1. Sistema de Valor del Servicio (Service Value System)

```
DEMANDA / OPORTUNIDAD
        │
        ▼
┌─────────────────────────────────────────────────────────┐
│                 CADENA DE VALOR DEL SERVICIO            │
│                                                         │
│  PLANEAR → DISEÑAR → CONSTRUIR → DESPLEGAR → OPERAR     │
│     ↑                                              │    │
│     └──────────── MEJORAR CONTINUAMENTE ───────────┘    │
└─────────────────────────────────────────────────────────┘
        │
        ▼
     VALOR
(funcionalidad estable, negocio operando, usuarios satisfechos)
```

Cada sesión de desarrollo recorre este ciclo. No hay "solo escribir código" — siempre hay planeación antes y mejora después.

---

## 2. Estructura de Conocimiento (Knowledge Management)

ITIL llama a esto el **Sistema de Gestión del Conocimiento del Servicio (SKMS)**. Nuestro SKMS:

```
/
├── BITACORA.md                    ─── Registro de Servicio (Service Log)
│                                      QUÉ se hizo y cuándo. Historial cronológico.
├── CLAUDE.md                      ─── Contexto Permanente del Agente IA
│                                      Reglas de arquitectura y patrones de trabajo.
├── docs/tecnico/
│   ├── ESTRATEGIA_DESARROLLO.md  ─── Marco de Proceso (este archivo)
│   │                                  CÓMO trabajamos.
│   ├── NOTAS_TECNICAS.md         ─── Base de Errores Conocidos (KEDB)
│   │                                  POR QUÉ falló y cómo se resolvió.
│   └── image-upload-system.md    ─── Catálogo de Servicios (Service Catalog)
│                                      QUÉ hace cada componente y cómo usarlo.
└── docs/architecture/
    └── IDEAS_FUTURO.md            ─── Backlog Estratégico
                                       Ideas que no tienen sprint asignado aún.
```

**Regla de flujo de conocimiento:**
- Nuevo bug resuelto → siempre termina en `NOTAS_TECNICAS.md`
- Nueva funcionalidad → siempre termina en el manual correspondiente
- Idea futura → siempre termina en `IDEAS_FUTURO.md`
- NUNCA dejar conocimiento "solo en la bitácora" sin capturarlo en el lugar correcto

---

## 3. Protocolo de Sesión (Service Value Chain en práctica)

### 3.1 PLANEAR (antes de escribir código)
1. Leer `BITACORA.md` — identificar el sprint activo y los pendientes
2. Leer las entradas de `NOTAS_TECNICAS.md` relevantes al área de trabajo
3. Confirmar que la tarea genera valor al usuario final (Principio 1)

### 3.2 DISEÑAR Y CONSTRUIR (durante la sesión)
- Trabajar únicamente en ítems del sprint activo
- Ante un bug nuevo: diagnosticar causa raíz antes de aplicar el fix (no parchear síntomas)
- Si aparece una idea futura: anotarla en `IDEAS_FUTURO.md` y continuar
- Cada bug significativo resuelto → entrada en `NOTAS_TECNICAS.md` antes de cerrar la sesión

### 3.3 DESPLEGAR (al enviar a producción)
Ver checklist completo en §7.

### 3.4 MEJORAR (al cerrar la sesión)
1. Actualizar `BITACORA.md`: qué se hizo, archivos modificados, pendientes
2. Generar commits con el formato estándar (§4)
3. Revisar: ¿hay algún riesgo nuevo que documentar?
4. Ejecutar `./apagar_backoffice.sh` (backup automático de BD)

---

## 4. Gestión de Cambios (Change Enablement)

ITIL clasifica los cambios según su riesgo e impacto. Esta clasificación determina cuánto proceso se aplica.

### Tipo 1 — Cambio Estándar (pre-aprobado, riesgo bajo)
Son cambios rutinarios de bajo riesgo con un procedimiento conocido.

**Ejemplos:**
- Corrección de texto, etiquetas o traducciones
- Ajuste de estilos CSS menores
- Actualización de la bitácora o documentación
- Agregar una validación simple

**Proceso:** Desarrollar → Commit → Deploy. Sin revisión adicional.

### Tipo 2 — Cambio Normal (planificado, riesgo medio)
Cambios que modifican comportamiento, flujo de datos o arquitectura.

**Ejemplos:**
- Nueva funcionalidad (feature)
- Cambio en migraciones de base de datos
- Modificación de rutas o middlewares
- Actualización de dependencias

**Proceso:** Planear → Revisar impacto → Desarrollar → Probar → Commit → Deploy con checklist.

### Tipo 3 — Cambio de Emergencia (producción caída, riesgo alto)
El sistema está fallando y los usuarios no pueden operar.

**Ejemplos:**
- Error 500 en producción en flujo crítico
- Pérdida de acceso a la aplicación
- Corrupción de datos

**Proceso (comprimido):**
1. Diagnosticar causa raíz inmediatamente
2. Aplicar el fix mínimo necesario
3. Verificar en producción
4. Documentar el incidente en `BITACORA.md` con el tag `🔴 EMERGENCIA`
5. Crear entrada en `NOTAS_TECNICAS.md` (obligatorio, mismo día)

---

## 5. Gestión de Incidentes vs Gestión de Problemas

Esta es una distinción crítica en ITIL que el equipo debe interiorizar.

```
INCIDENTE: "El sistema no sube fotos" → restaurar el servicio rápido
PROBLEMA:  "¿Por qué no sube fotos?" → encontrar y eliminar la causa raíz
```

### Ciclo de vida del Incidente
```
Detección → Registro (BITACORA) → Diagnóstico → Resolución → Cierre
```
- El objetivo es **restaurar el servicio lo antes posible**
- Si hay un workaround disponible, aplicarlo mientras se investiga la causa raíz
- Registrar en `BITACORA.md` con estado y solución aplicada

### Ciclo de vida del Problema
```
Identificación → Análisis de Causa Raíz → Workaround (si aplica) → Solución → NOTAS_TECNICAS
```
- El objetivo es **prevenir la recurrencia**
- La causa raíz puede no encontrarse en la misma sesión — el problema queda abierto
- Una vez resuelta, la entrada va a `NOTAS_TECNICAS.md`

**Regla práctica:** Si el mismo tipo de error aparece 2 veces, existe un Problema subyacente. Documentarlo aunque no se tenga la causa raíz aún.

---

## 6. Convenciones de Commit (Change Records)

En ITIL, cada cambio tiene un registro. El commit ES ese registro.

### Formato
```
tipo(alcance): descripción imperativa en presente

Cuerpo opcional: qué cambió y por qué (no cómo, eso está en el código).
Referencia a nota técnica si aplica: ver NT-XXX
```

### Tipos de cambio → tipo de commit

| Tipo ITIL | Tipo de commit | Cuándo |
|---|---|---|
| Estándar | `fix`, `chore`, `docs` | Bug conocido, mantenimiento, documentación |
| Normal | `feat`, `refactor` | Nueva funcionalidad, reestructuración |
| Emergencia | `fix` + `🔴` en cuerpo | Hotfix de producción urgente |

### Alcances comunes
`agenda`, `pets`, `resources`, `operators`, `payments`, `reports`, `auth`, `ui`, `build`, `deploy`

### Ejemplos
```
fix(cropperjs): bajar de v2.1.1 a v1.6.2 — API incompatible (ver NT-001)
feat(agenda): agregar filtro por operador en vista universal
chore(bitacora): sesión 25/05/2026 — fix fotos y documentación técnica
fix(pets): 🔴 EMERGENCIA — error 500 en subida de foto de perfil
```

---

## 7. Gestión de Liberaciones (Release Management)

### 7.1 Checklist de Deploy a Producción (OPi)

**Pre-deploy — en WSL:**
```bash
# 1. Verificar que los tests pasan
./vendor/bin/sail artisan test

# 2. Compilar assets y verificar el bundle
./vendor/bin/sail npm run build
# Confirmar que manifest.json apunta al nuevo hash

# 3. Generar backup de BD antes del push
./vendor/bin/sail exec mysql mysqldump -u sail -ppassword laravel > /tmp/estetican_pre_deploy.sql

# 4. Commit y push
git add public/build/ && git commit -m "chore: rebuild assets para deploy"
git push origin main
```

**Deploy — en OPi:**
```bash
cd /opt/www/estetican
git pull

cd apps/backoffice-laravel

# CRÍTICO: limpiar vistas compiladas SIEMPRE (ver NT-005)
docker exec estetican_app find /var/www/html/storage/framework/views -name "*.php" -delete

# Migraciones y caché
docker exec estetican_app php artisan migrate --force
docker exec estetican_app php artisan config:clear
docker exec estetican_app php artisan route:clear

# Si hay nuevo dump de BD:
docker exec -i estetican_mysql mysql -u root -p<DB_PASSWORD> estetican < estetican.sql
```

**Post-deploy — verificación:**
```bash
# Verificar que la app responde
curl -s -o /dev/null -w "%{http_code}" https://app.estetican.org
# Esperado: 200 o 302

# Revisar logs de error recientes
docker exec estetican_app tail -50 /var/www/html/storage/logs/laravel.log | grep -i error
```

### 7.2 Rollback de Emergencia
```bash
# Si el deploy rompió algo, revertir al commit anterior
git revert HEAD --no-edit
git push origin main
# Luego ejecutar el checklist de deploy nuevamente con el commit revertido
```

---

## 8. Gestión de la Configuración (Configuration Management — CMDB lite)

Los Ítems de Configuración (CIs) clave del sistema. Actualizar esta tabla cuando cambien.

### Entorno de Producción (OPi)
| CI | Valor actual | Notas |
|---|---|---|
| Hardware | Orange Pi 5 Plus | ARM64, 16GB RAM |
| OS | Linux 6.1.x Armbian | |
| Docker | Docker Engine + Compose v5 | Requiere buildx |
| App Container | `estetican_app` | PHP 8.3, Laravel 13 |
| MySQL Container | `estetican_mysql` | MySQL 8.4 |
| Redis Container | `estetican_redis` | |
| Proxy | Nginx Proxy Manager | Puerto 80/443 → estetican_app:80 |
| CDN/Tunnel | Cloudflare Tunnel | SSL terminado en edge |
| Dominio prod | `app.estetican.org` | |

### Dependencias críticas de la aplicación
| Dependencia | Versión fijada | Por qué está fijada |
|---|---|---|
| `cropperjs` | `1.6.2` (exacta) | v2 rompe toda la API de recorte (NT-001) |
| `flatpickr` | `4.6.13` (exacta) | Cambios de API en minor versions |
| PHP | `8.3+` | `8.5` tiene aritmética más estricta con null |
| Laravel | `13.x` | Blade compilador cambia entre versiones |

**Regla:** Las versiones exactas (`sin ^`) son ítems de configuración controlados. Cambiarlas es un **Cambio Normal** (§4) y requiere prueba explícita.

---

## 9. Mejora Continua del Servicio (Continual Service Improvement — CSI)

ITIL 4 llama a esto la **Práctica de Mejora Continua**. El modelo es simple:

```
¿Cuál es la visión?       → El backlog de negocio
¿Dónde estamos ahora?     → Auditoría del estado actual
¿Dónde queremos estar?    → Sprint activo
¿Cómo llegamos?           → Sesión de desarrollo
¿Llegamos?                → Verificación post-deploy
¿Cómo mantenemos el paso? → BITACORA + NOTAS_TECNICAS
```

### Registro CSI — Preguntas al cerrar cada sesión

1. **¿Funcionó lo que se deployó?** Si no → incidente a documentar
2. **¿El deploy fue más lento/difícil de lo necesario?** Si sí → qué proceso mejorar
3. **¿Apareció un bug que no anticipamos?** Si sí → ¿hay una NT que lo cubra? Si no → crearla
4. **¿Quedó deuda técnica?** Si sí → registrarla en el backlog, no ignorarla
5. **¿La documentación refleja el estado actual?** Si no → corregirla

---

## 10. Diagnóstico — Árbol de Decisión

Cuando algo falla, seguir este árbol antes de tocar código.

```
¿El sistema está completamente caído?
├── SÍ → Cambio de Emergencia (§4, Tipo 3)
│         Revisar: Docker, nginx, dominio, migraciones
└── NO ─→ ¿El error está en la UI (browser)?
          ├── SÍ → ¿Hay errores en la consola del browser?
          │        ├── SÍ → Ver mensaje de error exacto, buscar en NOTAS_TECNICAS
          │        └── NO → ¿El bundle fue reconstruido y la caché limpiada?
          │                 └── Ejecutar checklist de diagnóstico frontend (§10.1)
          └── NO ─→ ¿El error es un 500 / excepción PHP?
                    ├── SÍ → Ver laravel.log, buscar en NOTAS_TECNICAS
                    └── NO → ¿El flujo produce resultados incorrectos?
                              └── Revisar controlador + servicio de dominio
```

### 10.1 Checklist de Diagnóstico Frontend
1. ¿El bundle fue reconstruido? (`npm run build`) ¿El manifest apunta al nuevo hash?
2. ¿Las vistas compiladas fueron limpiadas? (`find .../views -name "*.php" -delete`)
3. ¿El browser tiene caché vieja? (Ctrl+Shift+R para hard-refresh, o ventana incógnita)
4. ¿Hay errores en la consola del browser? (F12 → Console)
5. ¿Las versiones de dependencias npm son las correctas? (comparar `package.json` con la CMDB §8)

### 10.2 Checklist de Diagnóstico Backend
1. ¿`storage:link` está activo? (`public/storage` → `storage/app/public/`)
2. ¿Permisos de escritura en storage? (`ls -la storage/app/public/`)
3. ¿Migraciones al día? (`php artisan migrate --status`)
4. ¿Vistas compiladas limpias? (ver arriba — NT-005)
5. ¿`AuthServiceProvider` en `bootstrap/providers.php`? (ver sesión 25/05)
6. ¿`app()->bound('csp-nonce')` antes de usarlo? (ver sesión 25/05)

---

## 11. Arquitectura de Fotos — Reglas Inmutables

Estas reglas no se negocian. Cambiarlas requiere una decisión arquitectónica documentada.

1. **El frontend recorta, el backend solo almacena.** El blob llega ya procesado. Los ImageManagers nunca recortan.
2. **Identidad vs Trazabilidad.** `profile_photo_path` = quién es (estado actual). Tabla de fotos = qué ha pasado (historial). Son dos cosas distintas.
3. **`is_primary=true` sincronizado.** Siempre que se cambia la foto de perfil, se actualiza tanto `profile_photo_path` como los flags `is_primary` en la tabla de fotos.
4. **Watermark en el cliente.** Se quema en el canvas JavaScript antes de generar el blob. El servidor recibe el JPG terminado.
5. **Versión de cropperjs fijada.** `1.6.2` exacto. Ver NT-001 antes de cualquier actualización.
