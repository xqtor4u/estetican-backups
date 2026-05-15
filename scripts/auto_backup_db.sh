#!/usr/bin/env bash
# 🛡️ Seguro contra Atorones - EstetiCAN 2
# Respaldo automático de base de datos

SET_DIR="/home/tomas/EstetiCAN_2"
APP_DIR="$SET_DIR/apps/backoffice-laravel"
BACKUP_DIR="$SET_DIR/backups/auto"
TIMESTAMP=$(date +"%Y-%m-%d_%H-%M")
FILE_NAME="auto_backup_$TIMESTAMP.sql.gz"

# Asegurar que el directorio existe
mkdir -p "$BACKUP_DIR"

# Cargar variables del .env (para DB_PASSWORD, etc)
if [ -f "$APP_DIR/.env" ]; then
    export $(grep -v '^#' "$APP_DIR/.env" | xargs)
else
    echo "Error: .env no encontrado en $APP_DIR"
    exit 1
fi

# Realizar el dump desde Docker
# Usamos -T para evitar errores de TTY en cron
if docker compose -f "$APP_DIR/compose.yaml" exec -T mysql mysqldump -u"$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" | gzip > "$BACKUP_DIR/$FILE_NAME"; then
    echo "✅ Respaldo exitoso: $FILE_NAME"
    
    # Rotación: Borrar archivos con más de 7 días (10080 minutos)
    find "$BACKUP_DIR" -name "auto_backup_*.sql.gz" -mmin +10080 -delete
    echo "♻️ Rotación de archivos antiguos completada."
else
    echo "❌ Error al realizar el respaldo."
    exit 1
fi
