# Estrategia de Desarrollo — EstetiCAN

> Guía de trabajo para sesiones de desarrollo. Define cómo organizamos el trabajo,
> documentamos decisiones y evitamos regresiones.

---

## 1. Estructura de documentación

```
/
├── BITACORA.md              — Historial cronológico de sesiones (QUÉ se hizo y cuándo)
├── CLAUDE.md                — Instrucciones para el agente IA (contexto permanente)
├── docs/
│   ├── tecnico/
│   │   ├── ESTRATEGIA_DESARROLLO.md   ← este archivo
│   │   ├── NOTAS_TECNICAS.md          — Bugs, causas raíz y soluciones (NT-XXX)
│   │   └── image-upload-system.md     — Referencia del sistema de fotos
│   └── architecture/
│       └── IDEAS_FUTURO.md            — Backlog de ideas (no tareas inmediatas)
```

**Regla:** La `BITACORA.md` registra EL QUÉ. Las `NOTAS_TECNICAS.md` registran EL POR QUÉ y el CÓMO. No duplicar contenido entre ambos.

---

## 2. Protocolo de sesión

### Al iniciar
1. Leer `BITACORA.md` — identificar el sprint activo y los pendientes
2. Revisar si hay notas técnicas relevantes para el trabajo del día

### Durante la sesión
- Trabajar en ítems del sprint activo (no inventar scope)
- Cada bug significativo → agregar entrada en `NOTAS_TECNICAS.md` (NT-XXX)
- Si algo es "buena idea pero no hoy" → `docs/architecture/IDEAS_FUTURO.md`

### Al cerrar
1. Actualizar `BITACORA.md` con lo que se hizo, archivos tocados, pendientes
2. Generar los commits correspondientes
3. Ejecutar `./apagar_backoffice.sh` (backup automático de BD)

---

## 3. Convenciones de commit

Formato: `tipo(alcance): descripción corta`

| Tipo | Cuándo |
|---|---|
| `feat` | Nueva funcionalidad |
| `fix` | Corrección de bug |
| `chore` | Tareas de mantenimiento (builds, configs, bitácora) |
| `refactor` | Refactor sin cambio de comportamiento |
| `docs` | Solo documentación |
| `test` | Solo tests |

Ejemplos:
```
fix(cropperjs): bajar de v2.1.1 a v1.6.2 — API incompatible
feat(agenda): agregar filtro por operador en agenda universal
chore(bitacora): sesión 25/05/2026
```

---

## 4. Reglas de dependencias npm

### Versiones exactas para librerías de UI/visualización
Las librerías de UI tienen breaking changes frecuentes entre minor/patch versions. Fijar la versión exacta:

```json
{
  "cropperjs": "1.6.2",
  "flatpickr": "4.6.13"
}
```

**NO usar `^` en librerías de UI** — la actualización automática puede romper la API sin advertencia (ver NT-001).

### Para librerías de infraestructura (Alpine, Vite, Bootstrap)
Se puede usar `^` con cuidado, pero siempre probar el upgrade manualmente antes de deployar.

---

## 5. Checklist de deploy a OPi (producción)

```bash
# En WSL — compilar y generar dump
cd apps/backoffice-laravel
./vendor/bin/sail npm run build          # genera public/build/ con hash nuevo
./vendor/bin/sail exec mysql mysqldump -u sail -ppassword laravel > /tmp/estetican.sql
git add public/build/ && git commit -m "chore: rebuild assets"
git push origin main

# En OPi
cd /opt/www/estetican
git pull
cd apps/backoffice-laravel
docker exec estetican_app php artisan migrate --force
docker exec estetican_app php artisan config:clear
docker exec estetican_app find /var/www/html/storage/framework/views -name "*.php" -delete
# Si hay dump nuevo:
docker exec -i estetican_mysql mysql -u root -p<DB_PASSWORD> estetican < estetican.sql
```

**CRÍTICO:** Siempre borrar vistas compiladas después de `git pull`. Las vistas compiladas en caché pueden contener imports PHP inválidos que el compilador Blade regenera.

---

## 6. Arquitectura de fotos — reglas de oro

1. **El frontend recorta, el backend solo almacena.** El cliente envía el blob ya recortado. El backend nunca recorta imágenes (es responsabilidad del componente `x-image-upload`).
2. **Identidad vs Trazabilidad.** `profile_photo_path` en la tabla es la identidad actual (quién es). La tabla de fotos (`pet_photos`, `resource_photos`) es el historial (qué ha pasado).
3. **La foto de perfil siempre tiene `is_primary=true`** en la tabla de fotos. Cuando se cambia la foto de perfil, se marca la nueva como `is_primary=true` y todas las demás como `false`.
4. **Watermark en el cliente.** El watermark se quema en el canvas antes de generar el blob. El backend recibe el JPG ya con watermark.

---

## 7. Cuando algo no funciona

### Checklist de diagnóstico frontend
1. ¿El bundle fue reconstruido? (`npm run build`) ¿El manifest apunta al nuevo hash?
2. ¿Las vistas compiladas fueron limpiadas? (`find .../views -name "*.php" -delete`)
3. ¿El browser está usando caché? (Ctrl+Shift+R para hard-refresh)
4. ¿Hay errores en la consola del browser?
5. ¿Las versiones de las dependencias npm son las correctas? (ver `package.json`)

### Checklist de diagnóstico backend
1. ¿`storage:link` está activo? (`public/storage` apunta a `storage/app/public/`)
2. ¿El directorio de storage tiene permisos de escritura?
3. ¿Las migraciones están al día? (`php artisan migrate --status`)
4. ¿Las vistas compiladas están limpias? (ver arriba)
5. ¿El `AuthServiceProvider` está en `bootstrap/providers.php`?
