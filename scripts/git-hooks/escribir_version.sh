#!/bin/sh
# Escribe la versión visible del backoffice ("v.AAMMDD-HHMM" en la navegación) a partir de la
# fecha del commit actual. La lee config/backoffice.php (brand.version). El archivo VERSION no se
# versiona: lo regeneran los hooks post-commit/post-merge/post-checkout/post-rewrite de .git/hooks,
# que solo llaman a este script. Instalar con: scripts/git-hooks/instalar.sh

root="$(git rev-parse --show-toplevel)" || exit 0

# EstetiCAN real guarda Laravel en apps/backoffice-laravel; los tenants (tst) en la raíz del repo.
if [ -d "$root/apps/backoffice-laravel" ]; then
    app="$root/apps/backoffice-laravel"
else
    app="$root"
fi

git log -1 --format=%cd --date=format:%y%m%d-%H%M > "$app/VERSION" 2>/dev/null || true
exit 0
