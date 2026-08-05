# Guía de Operación — Orange Pi 5 Plus (Producción)

## Acceso

| Método | Detalle |
|---|---|
| SSH / PuTTY | `tomas@192.168.100.250` (solo desde LAN) |
| App web | `https://app.estetican.org` |
| Portainer | `http://192.168.100.250:9000` |
| NPM (Nginx Proxy Manager) | `http://192.168.100.250:81` |

**Credenciales de la app:** `admin@localhost` / (cambiar desde UI)

---

## Directorio del proyecto en la OPi

```
/opt/www/estetican/apps/backoffice-laravel/
```

---

## Contenedores

| Nombre | Rol |
|---|---|
| `estetican_app` | Laravel (PHP + servidor web) |
| `estetican_mysql` | Base de datos MySQL 8.4 |
| `estetican_redis` | Caché Redis |
| `nginx-proxy-manager` | Proxy inverso + SSL |

---

## Levantar el sistema

Los contenedores tienen `restart: unless-stopped` — arrancan solos al reiniciar la OPi.

Para levantar manualmente:
```bash
cd /opt/www/estetican/apps/backoffice-laravel
docker compose -f compose.prod.yaml --env-file .env.production up -d
```

---

## Apagar el sistema

```bash
cd /opt/www/estetican/apps/backoffice-laravel
docker compose -f compose.prod.yaml down
```

> **No uses `down -v`** — eso borra el volumen de MySQL y pierdes la BD.

---

## Ver estado de los contenedores

```bash
docker ps
```

Todos deben aparecer como `Up` y MySQL/Redis como `(healthy)`.

---

## Ver logs de la app

```bash
# Logs del servidor (tiempo real)
docker logs estetican_app -f

# Logs de Laravel
docker exec estetican_app tail -50 /var/www/html/storage/logs/laravel.log
```

---

## Actualizar código desde GitHub (deploy)

```bash
cd /opt/www/estetican
git pull
cd apps/backoffice-laravel

# Borrar vistas compiladas (SIEMPRE después de un pull)
docker exec estetican_app find /var/www/html/storage/framework/views -name "*.php" -delete

# Si hubo cambios en migraciones
docker exec estetican_app php artisan migrate --force

# Limpiar caché de configuración
docker exec estetican_app php artisan config:clear

# Limpiar caché de permisos Spatie
docker exec estetican_app php artisan permission:cache-reset
```

---

## Respaldo de base de datos

> Los comandos de esta sección usan `$DB_ROOT_PASSWORD` — antes de correrlos, exporta la variable con el valor real (ver "Notas importantes" más abajo sobre dónde vive esa contraseña): `export DB_ROOT_PASSWORD='...'`

**Desde la OPi (manual):**
```bash
docker exec estetican_mysql mysqldump -u root -p"$DB_ROOT_PASSWORD" estetican > /opt/www/estetican/backups/estetican_$(date +%Y%m%d_%H%M).sql
```

**Desde WSL hacia la OPi (reemplaza la BD en OPi con la de desarrollo):**
```bash
# 1. Generar dump en WSL
cd /home/tomas/EstetiCAN_2/apps/backoffice-laravel
./vendor/bin/sail up -d mysql
./vendor/bin/sail exec mysql mysqldump -u sail -ppassword laravel > /tmp/estetican.sql

# 2. Copiar a OPi
scp /tmp/estetican.sql tomas@192.168.100.250:/opt/www/estetican/apps/backoffice-laravel/

# 3. Importar en OPi (vía PuTTY)
docker exec -i estetican_mysql mysql -u root -p"$DB_ROOT_PASSWORD" estetican < /opt/www/estetican/apps/backoffice-laravel/estetican.sql
```

---

## Restaurar respaldo

```bash
docker exec -i estetican_mysql mysql -u root -p"$DB_ROOT_PASSWORD" estetican < /ruta/al/backup.sql
```

---

## Tinker (consola interactiva de Laravel)

```bash
docker exec -it estetican_app php artisan tinker
```

Ejemplos útiles:
```php
# Ver usuarios y roles
App\Models\User::all()->each(fn($u) => print($u->id.' | '.$u->email.' | '.$u->getRoleNames()->join(',').PHP_EOL));

# Resetear password
App\Models\User::find(2)->update(['password' => bcrypt('nuevo_password')]);

# Asignar rol admin
App\Models\User::find(2)->assignRole('admin');
```

---

## Compilar assets (CSS/JS)

Los assets se compilan en **WSL** y se suben vía git. No se usan en la OPi directamente.

```bash
# En WSL
cd /home/tomas/EstetiCAN_2/apps/backoffice-laravel
./vendor/bin/sail npm run build
git add public/build/
git commit -m "chore(assets): recompilar para producción"
git push origin main

# En OPi
cd /opt/www/estetican && git pull
docker exec estetican_app find /var/www/html/storage/framework/views -name "*.php" -delete
```

---

## Solución de problemas frecuentes

| Síntoma | Solución |
|---|---|
| App no carga / 502 | `docker ps` — verificar que `estetican_app` está `Up` |
| Error de vista / variable undefined | Borrar vistas compiladas: `docker exec estetican_app find /var/www/html/storage/framework/views -name "*.php" -delete` |
| Error 403 en rutas protegidas | `docker exec estetican_app php artisan permission:cache-reset` |
| Cambios de código no se reflejan | Borrar vistas compiladas + `docker restart estetican_app` |
| BD no conecta | `docker exec estetican_mysql mysqladmin ping -u root -p"$DB_ROOT_PASSWORD"` (exporta la variable primero, ver "Notas importantes") |
| Loop de redirecciones en el dominio | Verificar que "Force SSL" esté **desactivado** en NPM para `app.estetican.org` |
| Cambios a `mov_apps/operador/nginx.conf` no se reflejan tras editarlo | `docker restart estetican_mob` — **no** `nginx -s reload` ni `nginx -t` a secas. Es un bind-mount de archivo único; el reload puede reportar éxito pero seguir sirviendo el contenido viejo (ver NT-052) |

---

## Notas importantes

- **DB_PASSWORD en producción:** no vive en este repo — está en las notas personales del usuario ("Estetican Notas.txt", junto con las demás API keys y contraseñas de admin). Coincide con el `DB_PASSWORD` real de `apps/backoffice-laravel/.env` (sin símbolos especiales — Docker Compose los interpreta como variables)
- **`proxy_net`** es una red Docker externa creada por NPM — nunca la borres con `down -v`
- **Force SSL en NPM debe estar DESACTIVADO** — Cloudflare Tunnel ya maneja HTTPS
- Los assets compilados (`public/build/`) están en el repo — no se necesita npm en la OPi
