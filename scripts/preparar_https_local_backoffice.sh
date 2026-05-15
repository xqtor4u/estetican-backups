#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
APP_DIR="$ROOT_DIR/apps/backoffice-laravel"
CERT_DIR="$APP_DIR/.certs"
CERT_FILE="$CERT_DIR/backoffice-local.pem"
KEY_FILE="$CERT_DIR/backoffice-local-key.pem"
ROOT_CA_FILE="$CERT_DIR/rootCA.pem"
QUIET_MODE=0

if [[ "${1:-}" == "--quiet" ]]; then
	QUIET_MODE=1
fi

print_step() {
	if [[ "$QUIET_MODE" -eq 0 ]]; then
		printf '%s\n' "$1"
	fi
}

print_install_hint() {
	printf '%s\n' "Instalalo en Windows con: winget install FiloSottile.mkcert" >&2
	printf '%s\n' "Si ya lo instalaste, cierra y vuelve a abrir PowerShell/WSL para refrescar PATH y aliases." >&2
	printf '%s\n' "Luego vuelve a correr: $ROOT_DIR/scripts/preparar_https_local_backoffice.sh" >&2
}

first_existing_path() {
	local candidate

	for candidate in "$@"; do
		if [[ -n "$candidate" && -f "$candidate" ]]; then
			printf '%s\n' "$candidate"
			return 0
		fi
	done

	return 1
}

find_mkcert_cmd() {
	if command -v mkcert >/dev/null 2>&1; then
		command -v mkcert
		return 0
	fi

	if command -v powershell.exe >/dev/null 2>&1; then
		local win_path
		win_path="$(powershell.exe -NoProfile -Command "Get-Command mkcert -ErrorAction SilentlyContinue | Select-Object -ExpandProperty Source" 2>/dev/null | tr -d '\r')"

		if [[ -n "$win_path" ]]; then
			wslpath -u "$win_path"
			return 0
		fi
	fi

	local fallback_path
	fallback_path="$(first_existing_path \
		"/mnt/c/Users/$USER/AppData/Local/Microsoft/WinGet/Links/mkcert.exe" \
		"/mnt/c/Users/$USER/AppData/Local/Microsoft/WindowsApps/mkcert.exe" \
		"/mnt/c/Users/$USER/AppData/Local/Microsoft/WinGet/Packages/FiloSottile.mkcert_Microsoft.Winget.Source_8wekyb3d8bbwe/mkcert.exe" \
		"/mnt/c/Program Files/mkcert/mkcert.exe" \
		/mnt/c/Users/*/AppData/Local/Microsoft/WinGet/Links/mkcert.exe \
		/mnt/c/Users/*/AppData/Local/Microsoft/WindowsApps/mkcert.exe \
		/mnt/c/Users/*/AppData/Local/Microsoft/WinGet/Packages/FiloSottile.mkcert_Microsoft.Winget.Source_8wekyb3d8bbwe/mkcert.exe \
		2>/dev/null || true)"

	if [[ -n "$fallback_path" ]]; then
		printf '%s\n' "$fallback_path"
		return 0
	fi

	return 1
}

detect_windows_lan_ips() {
	if ! command -v powershell.exe >/dev/null 2>&1; then
		return 0
	fi

	powershell.exe -NoProfile -Command "[Console]::OutputEncoding=[System.Text.Encoding]::UTF8; Get-NetIPAddress -AddressFamily IPv4 | Where-Object { \$_.IPAddress -notlike '127.*' -and \$_.IPAddress -notlike '169.254*' -and \$_.InterfaceAlias -notmatch 'WSL|Loopback|vEthernet|Hyper-V|Docker|Bluetooth|NordLynx|Tailscale|ZeroTier' } | Select-Object -ExpandProperty IPAddress" 2>/dev/null \
		| tr -d '\r' \
		| sed '/^$/d'
}

normalize_path() {
	local maybe_path="$1"

	if [[ "$maybe_path" =~ ^[A-Za-z]:\\ ]]; then
		wslpath -u "$maybe_path"
		return 0
	fi

	printf '%s\n' "$maybe_path"
}

if [[ ! -d "$APP_DIR" ]]; then
	printf '%s\n' "Error: no existe el directorio $APP_DIR" >&2
	exit 1
fi

MKCERT_CMD="$(find_mkcert_cmd || true)"

if [[ -z "$MKCERT_CMD" ]]; then
	printf '%s\n' "mkcert no esta instalado." >&2
	print_install_hint
	exit 1
fi

mkdir -p "$CERT_DIR"

readarray -t LAN_IPS < <(detect_windows_lan_ips)

TARGETS=(localhost 127.0.0.1 ::1)

for ip in "${LAN_IPS[@]}"; do
	if [[ -n "$ip" ]]; then
		TARGETS+=("$ip")
	fi
done

print_step "Instalando o verificando la CA local de mkcert..."
"$MKCERT_CMD" -install >/dev/null

print_step "Generando certificado local para: ${TARGETS[*]}"
"$MKCERT_CMD" -cert-file "$CERT_FILE" -key-file "$KEY_FILE" "${TARGETS[@]}" >/dev/null

CAROOT_PATH="$("$MKCERT_CMD" -CAROOT | tr -d '\r')"
CAROOT_PATH="$(normalize_path "$CAROOT_PATH")"

if [[ -f "$CAROOT_PATH/rootCA.pem" ]]; then
	cp "$CAROOT_PATH/rootCA.pem" "$ROOT_CA_FILE"
fi

print_step "Certificados listos:"
print_step " - Certificado: $CERT_FILE"
print_step " - Llave: $KEY_FILE"

if [[ -f "$ROOT_CA_FILE" ]]; then
	print_step " - CA raiz para instalar en el celular: $ROOT_CA_FILE"
fi

if [[ "$QUIET_MODE" -eq 0 && "${#LAN_IPS[@]}" -gt 0 ]]; then
	printf '%s\n' "IPs cubiertas para HTTPS local:"
	for ip in "${LAN_IPS[@]}"; do
		printf ' - https://%s:%s\n' "$ip" "8443"
	done
fi
