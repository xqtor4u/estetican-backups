#!/bin/bash
BASE=/home/tomas/EstetiCAN_2/apps/backoffice-laravel/resources/views

apply_id() {
  local file="$BASE/$1"
  local id="$2"
  if [ -f "$file" ]; then
    if grep -q 'screenDebugId' "$file"; then
      sed -i "s/@php(\\\$screenDebugId = '[^']*')/@php(\$screenDebugId = '$id')/" "$file"
    else
      sed -i "1s/^/@php(\$screenDebugId = '$id')\n/" "$file"
    fi
    echo "OK: $1 -> $id"
  else
    echo "SKIP (not found): $1"
  fi
}

apply_id dashboard/index.blade.php HomInd
apply_id agenda/index.blade.php AgInd
apply_id agenda/show.blade.php AgSho
apply_id agenda/global-create.blade.php AgNew
apply_id agenda/create.blade.php AgNew
apply_id agenda/edit.blade.php AgEdi
apply_id clients/index.blade.php CliInd
apply_id clients/show.blade.php CliSho
apply_id clients/create.blade.php CliNew
apply_id clients/edit.blade.php CliEdi
apply_id pets/index.blade.php PetInd
apply_id pets/show.blade.php PetSho
apply_id hotel-reservations/index.blade.php AgHotInd
apply_id hotel-reservations/show.blade.php AgHotSho
apply_id hotel-reservations/create.blade.php AgHotNew
apply_id hotel-reservations/edit.blade.php AgHotEdi
apply_id operators/index.blade.php OpeInd
apply_id operators/show.blade.php OpeSho
apply_id operators/create.blade.php OpeNew
apply_id operators/edit.blade.php OpeEdi
apply_id operator-roles/index.blade.php OprRolInd
apply_id operator-roles/show.blade.php OprRolSho
apply_id operator-roles/create.blade.php OprRolNew
apply_id operator-roles/edit.blade.php OprRolEdi
apply_id services/index.blade.php SerInd
apply_id services/show.blade.php SerSho
apply_id services/create.blade.php SerNew
apply_id services/edit.blade.php SerEdi
apply_id resources/index.blade.php ResInd
apply_id resources/show.blade.php ResSho
apply_id resources/create.blade.php ResNew
apply_id resources/edit.blade.php ResEdi
apply_id branches/index.blade.php BraInd
apply_id branches/show.blade.php BraSho
apply_id branches/create.blade.php BraNew
apply_id branches/edit.blade.php BraEdi
apply_id system-settings/index.blade.php SysSetInd
apply_id user/index.blade.php UsrInd
apply_id user/show.blade.php UsrSho
apply_id user/create.blade.php UsrNew
apply_id user/edit.blade.php UsrEdi
apply_id user/settings.blade.php UsrSet

echo ""
echo "Done. Verifying sample..."
head -1 "$BASE/dashboard/index.blade.php"
head -1 "$BASE/agenda/index.blade.php"
head -1 "$BASE/clients/index.blade.php"
