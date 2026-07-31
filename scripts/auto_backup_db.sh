#!/usr/bin/env bash
# Respaldo automático de BD + subida a Google Drive — EstetiCAN
# Cron: 0 3 * * * /opt/www/estetican/scripts/auto_backup_db.sh >> /opt/www/estetican/backups/estetican_backup.log 2>&1

set -euo pipefail

APP_DIR="/opt/www/estetican/apps/backoffice-laravel"
BACKUP_DIR="/opt/www/estetican/backups"
GDRIVE_PATH="OrangePiBackups/estetican-db"
RETENTION_DAYS=7
TIMESTAMP=$(date +"%Y-%m-%d_%H-%M")
FILE_NAME="backup_${TIMESTAMP}.sql.gz"

mkdir -p "$BACKUP_DIR"

# Cargar credenciales desde .env
if [ ! -f "$APP_DIR/.env" ]; then
    echo "[ERROR] .env no encontrado en $APP_DIR"
    exit 1
fi
export $(grep -v '^#' "$APP_DIR/.env" | grep -E '^DB_' | xargs)

echo "[$(date)] Iniciando respaldo: $FILE_NAME"

# Dump desde el contenedor MySQL
if docker exec estetican_mysql mysqldump \
    -u"$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" \
    --single-transaction --routines --triggers --no-tablespaces \
    | gzip > "$BACKUP_DIR/$FILE_NAME"; then
    echo "[$(date)] Dump OK: $FILE_NAME ($(du -sh "$BACKUP_DIR/$FILE_NAME" | cut -f1))"
else
    echo "[ERROR] Falló el dump de la base de datos."
    exit 1
fi

# Subir a Google Drive
if rclone copy "$BACKUP_DIR/$FILE_NAME" "gdrive:$GDRIVE_PATH" --quiet; then
    echo "[$(date)] Subido a Drive: gdrive:$GDRIVE_PATH/$FILE_NAME"
else
    echo "[ADVERTENCIA] No se pudo subir a Drive. El backup local sí existe."
fi

# Rotación local: borrar archivos con más de N días
find "$BACKUP_DIR" -name "backup_*.sql.gz" -mtime +$RETENTION_DAYS -delete
echo "[$(date)] Rotación local completada (retención: ${RETENTION_DAYS} días)."

echo "[$(date)] Respaldo finalizado."
