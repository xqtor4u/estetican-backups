#!/bin/bash
# Backup y push incremental a GitHub para UX EstetiCAN
# Autor: xqtor4u
# Uso: ./backup_push_ux.sh "Mensaje opcional de commit"

BACKUP_DIR="/home/tomas/EstetiCAN_2/backups/ux"
REPO_URL="https://github.com/xqtor4u/estetican-backups.git"
BRANCH="main"

# 1. Realiza backup incremental (puedes comentar si ya lo haces aparte)
rsync -a --delete --exclude='vendor/' --exclude='node_modules/' /home/tomas/EstetiCAN_2/apps/backoffice-laravel/resources/views/ "$BACKUP_DIR/views_$(date +%Y-%m-%d_%H-%M-%S)/"
rsync -a --delete /home/tomas/EstetiCAN_2/apps/backoffice-laravel/public/ "$BACKUP_DIR/public_$(date +%Y-%m-%d_%H-%M-%S)/"
rsync -a --delete /home/tomas/EstetiCAN_2/apps/backoffice-laravel/resources/js/ "$BACKUP_DIR/js_$(date +%Y-%m-%d_%H-%M-%S)/"

cd "$BACKUP_DIR"

# 2. Inicializa git si es necesario
git rev-parse --is-inside-work-tree 2>/dev/null || git init

git remote | grep origin >/dev/null || git remote add origin "$REPO_URL"
git branch | grep $BRANCH >/dev/null || git checkout -b $BRANCH

git add .
COMMIT_MSG="Backup incremental $(date +'%Y-%m-%d %H:%M')"
if [ -n "$1" ]; then
  COMMIT_MSG="$1"
fi
git commit -m "$COMMIT_MSG"
git push -u origin $BRANCH
