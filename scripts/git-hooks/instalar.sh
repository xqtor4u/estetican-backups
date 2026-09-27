#!/bin/sh
# Instala los hooks de git que mantienen al día el archivo VERSION (ver escribir_version.sh).
# Idempotente: se puede correr de nuevo sin efecto secundario.

root="$(git rev-parse --show-toplevel)" || exit 1
hooks="$(git rev-parse --git-path hooks)"

for hook in post-commit post-merge post-checkout post-rewrite; do
    printf '#!/bin/sh\nexec "%s/scripts/git-hooks/escribir_version.sh"\n' "$root" > "$hooks/$hook"
    chmod +x "$hooks/$hook"
done

"$root/scripts/git-hooks/escribir_version.sh"
echo "Hooks instalados. VERSION = $(cat "$root/apps/backoffice-laravel/VERSION" 2>/dev/null || cat "$root/VERSION")"
