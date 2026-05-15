#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP_DIR="$ROOT_DIR/apps/backoffice-laravel"

printf '%s
' "Apagando backoffice Laravel desde WSL/Linux."

if ! command -v docker >/dev/null 2>&1; then
    printf '%s
' "Error: docker no esta disponible en esta sesion. Abre WSL y verifica Docker Desktop/Engine." >&2
    exit 1
fi

if ! docker info >/dev/null 2>&1; then
    printf '%s
' "Error: Docker no responde. Inicia Docker Desktop/Engine y vuelve a intentar." >&2
    exit 1
fi



# Respaldo robusto de la base de datos antes de apagar (usar root para evitar errores de privilegios)
BACKUP_DIR="$APP_DIR/backups"
mkdir -p "$BACKUP_DIR"
BACKUP_FILE="$BACKUP_DIR/backup_$(date +%F_%H-%M).sql"
printf '%s\n' "Generando respaldo automático de la base de datos (usuario root) en $BACKUP_FILE..."
docker exec backoffice-laravel-mysql-1 mysqldump -u root -ppassword laravel > "$BACKUP_FILE" || printf '%s\n' "[ADVERTENCIA] No se pudo generar respaldo automático. Verifica el contenedor y credenciales."

cd "$APP_DIR"

printf '%s
' "Bajando contenedores del backoffice..."
docker compose down

printf '%s
' "Backoffice detenido."
printf '%s
' "Las bases y datos persistentes se conservan en los volumenes de Docker."