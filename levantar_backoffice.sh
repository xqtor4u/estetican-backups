#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP_DIR="$ROOT_DIR/apps/backoffice-laravel"
HTTPS_CERT_FILE="$APP_DIR/.certs/backoffice-local.pem"
HTTPS_KEY_FILE="$APP_DIR/.certs/backoffice-local-key.pem"

detect_windows_lan_ips() {
    if ! command -v powershell.exe >/dev/null 2>&1; then
        return 0
    fi

    powershell.exe -NoProfile -Command "[Console]::OutputEncoding=[System.Text.Encoding]::UTF8; Get-NetIPAddress -AddressFamily IPv4 | Where-Object { \$_.IPAddress -notlike '127.*' -and \$_.IPAddress -notlike '169.254*' -and \$_.InterfaceAlias -notmatch 'WSL|Loopback|vEthernet|Hyper-V|Docker|Bluetooth|NordLynx|Tailscale|ZeroTier' } | Select-Object InterfaceAlias,IPAddress | Format-Table -HideTableHeaders" 2>/dev/null \
        | tr -d '\r' \
        | sed '/^$/d'
}

maybe_prepare_local_https() {
    local prep_script="$ROOT_DIR/scripts/preparar_https_local_backoffice.sh"

    if [[ -f "$HTTPS_CERT_FILE" && -f "$HTTPS_KEY_FILE" ]]; then
        return 0
    fi

    if [[ -f "$prep_script" ]]; then
        bash "$prep_script" --quiet >/dev/null 2>&1 || true
    fi
}

maybe_build_frontend_assets() {
    local npm_path
    local node_path
    local use_hot_reload="${BACKOFFICE_USE_VITE_HOT:-false}"

    build_with_container() {
        if docker compose exec -T laravel.test node -v >/dev/null 2>&1 && docker compose exec -T laravel.test npm -v >/dev/null 2>&1; then
            printf '%s\n' "Compilando assets frontend con Vite dentro de Docker..."
            docker compose exec -T laravel.test npm run build
            return 0
        fi

        return 1
    }

    if [[ "$use_hot_reload" != "true" && -f "$APP_DIR/public/hot" ]]; then
        printf '%s\n' "Removiendo marcador hot de Vite para usar assets compilados estables..."
        rm -f "$APP_DIR/public/hot"
    fi

    if [[ -f "$APP_DIR/public/build/manifest.json" ]]; then
        return 0
    fi

    npm_path="$(command -v npm || true)"
    node_path="$(command -v node || true)"

    if [[ -z "$npm_path" || -z "$node_path" ]]; then
        if build_with_container; then
            return 0
        fi

        printf '%s\n' "Aviso: npm/node no estan disponibles en Linux y tampoco fue posible compilar dentro de Docker; el backoffice seguira con CSS estatico de respaldo." >&2
        return 0
    fi

    if [[ "$npm_path" == /mnt/* || "$node_path" == /mnt/* ]]; then
        if build_with_container; then
            return 0
        fi

        printf '%s\n' "Aviso: npm/node resuelven a binarios de Windows desde WSL y tampoco fue posible compilar dentro de Docker; se omite build de Vite para evitar fallos por rutas UNC." >&2
        return 0
    fi

    printf '%s\n' "Compilando assets frontend con Vite..."
    npm run build
}

printf '%s\n' "Recordatorio: levantar siempre WSL/Linux y Docker antes del backoffice."

if ! command -v docker >/dev/null 2>&1; then
    printf '%s\n' "Error: docker no esta disponible en esta sesion. Abre WSL y verifica Docker Desktop/Engine." >&2
    exit 1
fi

if ! docker info >/dev/null 2>&1; then
    printf '%s\n' "Error: Docker no responde. Inicia Docker Desktop/Engine y vuelve a intentar." >&2
    exit 1
fi

cd "$APP_DIR"

if [[ ! -f .env ]]; then
    printf '%s\n' "Error: no existe .env en $APP_DIR" >&2
    exit 1
fi

set -a
# shellcheck disable=SC1091
source .env
set +a

if [[ "${BACKOFFICE_USE_VITE_HOT:-false}" != "true" && -f "$APP_DIR/public/hot" ]]; then
    printf '%s\n' "Removiendo marcador hot de Vite para evitar cargar assets de un dev server no solicitado..."
    rm -f "$APP_DIR/public/hot"
fi

maybe_prepare_local_https

printf '%s\n' "Levantando contenedores..."
compose_args=(up -d)

if [[ -f "$HTTPS_CERT_FILE" && -f "$HTTPS_KEY_FILE" ]]; then
    compose_args=(--profile https up -d)
fi

docker compose "${compose_args[@]}"

printf '%s\n' "Esperando a que MySQL acepte conexiones..."
mysql_ready=0
for _ in {1..30}; do
    if docker compose exec -T mysql mysqladmin ping -h 127.0.0.1 -u"${DB_USERNAME}" -p"${DB_PASSWORD}" --silent >/dev/null 2>&1; then
        mysql_ready=1
        break
    fi
    sleep 2
done

if [[ "$mysql_ready" -ne 1 ]]; then
    printf '%s\n' "Error: MySQL no quedo listo a tiempo. Revisa docker compose logs mysql." >&2
    exit 1
fi

printf '%s\n' "Ejecutando migraciones..."
./vendor/bin/sail artisan migrate --force

printf '%s\n' "Verificando enlace publico de storage..."
./vendor/bin/sail artisan storage:link || true

printf '%s\n' "Reconstruyendo caches de Laravel para acelerar carga..."
./vendor/bin/sail artisan config:clear
./vendor/bin/sail artisan route:clear
./vendor/bin/sail artisan view:clear
./vendor/bin/sail artisan config:cache
./vendor/bin/sail artisan route:cache
./vendor/bin/sail artisan view:cache

maybe_build_frontend_assets

printf '%s\n' "Entorno listo."
printf '%s\n' "Backoffice: http://localhost:8000"

if [[ -f "$HTTPS_CERT_FILE" && -f "$HTTPS_KEY_FILE" ]]; then
    printf '%s\n' "Backoffice HTTPS local: https://localhost:${HTTPS_PORT:-8443}"
fi

lan_candidates="$(detect_windows_lan_ips || true)"

if [[ -n "$lan_candidates" ]]; then
    printf '%s\n' "URLs probables para probar desde el celular:"
    while IFS= read -r line; do
        interface_name="$(awk '{print $1}' <<< "$line")"
        interface_ip="$(awk '{print $2}' <<< "$line")"

        if [[ -n "$interface_name" && -n "$interface_ip" ]]; then
            printf ' - %s: http://%s:%s\n' "$interface_name" "$interface_ip" "${APP_PORT:-8000}"

            if [[ -f "$HTTPS_CERT_FILE" && -f "$HTTPS_KEY_FILE" ]]; then
                printf '   %s HTTPS: https://%s:%s\n' "$interface_name" "$interface_ip" "${HTTPS_PORT:-8443}"
            fi
        fi
    done <<< "$lan_candidates"
fi

if [[ ! -f "$HTTPS_CERT_FILE" || ! -f "$HTTPS_KEY_FILE" ]]; then
    printf '%s\n' "Para HTTPS local con mkcert corre: $ROOT_DIR/scripts/preparar_https_local_backoffice.sh"
fi

printf '%s\n' "Si no abre desde Windows, confirma primero que WSL y Docker sigan activos."
