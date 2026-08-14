# Pendientes a sincronizar con los tenants (Zeus-Estetican)

> **Qué es este documento:** EstetiCAN real (`/opt/www/estetican`) es **producción** — acá
> solo se atienden **emergencias** (bugs que rompen operación real, incidentes de datos, huecos
> de seguridad). Todo addon, upgrade o mejora que **no** sea emergencia se construye del lado
> de Zeus-Estetican, en el sandbox `tst` (`tstapp.estetican.org` / `tstmov.estetican.org`,
> `/opt/www/zeus-estetican/tenants/tst/`) — el sandbox de referencia para todos los tenants.
>
> Como el motor es el mismo código clonado en cada tenant, **una emergencia real de producción
> normalmente también existe como bug latente en `tst`** (y en cualquier otro clon real) hasta
> que se porta. Este documento registra qué se arregló acá y si ya se replicó allá — dirección
> opuesta a `docs/tecnico/PENDIENTES_SINCRONIZAR_ESTETICAN.md` del lado de Zeus-Estetican (ese
> es para lo que se descubre en el sandbox y falta subir a producción; nunca al revés).
>
> Namespace de IDs: **SYNC-XXX** (contador propio de este archivo, independiente del de Zeus).
> Un ítem no se borra al portarse — se mueve a "Aplicados" con fecha.

---

## Pendientes

_(vacío — no hay emergencias sin portar)_

---

## Aplicados

### SYNC-001 — Agenda: barra de "Ventana" (Hoy/Mañana/Próximas/Todas) y el filtro de Estado eran dos paneles desconectados

**Encontrado:** 13/08/2026, reportado directo por el usuario en producción — al marcar
"Marcar todos" en el filtro de Estado y después tocar la barra de Ventana, el cambio de
checkboxes se perdía silenciosamente.

**Causa:** dos controles independientes para el mismo parámetro `date_scope` — la barra de
pestañas de arriba usaba `<a href>` con la URL ya armada al cargar la página, y el panel de
filtros tenía su propio `<select name="date_scope">` (al que además le faltaba la opción
"Todas"). Cualquier cambio hecho en un panel que no se hubiera enviado todavía se perdía al
navegar por el otro.

**Fix aplicado en EstetiCAN real** (ver `BITACORA.md` 13/08/2026 para el detalle completo):
- `apps/backoffice-laravel/resources/views/agenda/index.blade.php` — los botones de Ventana y
  de día anterior/siguiente pasaron de `<a href>` sueltos a `<button type="submit"
  form="agenda-filters-form" name="date_scope" value="...">`, enviando el mismo `<form>` que
  el resto de los filtros. Se eliminó el `<select>` duplicado. Se agregaron hidden `sort`/
  `direction` para no perder el orden de tabla al aplicar filtros.
- `apps/backoffice-laravel/resources/views/components/list-filters.blade.php` — prop opcional
  nueva `id` (retrocompatible, no afecta a los otros 10 `index.blade.php` que usan
  `x-list-filters`).

**Verificado:** compila sin error Blade, Pint limpio, 76 tests de Agenda pasan, suite completa
447 pasan / 37 fallan (deuda de fixtures preexistente, sin regresiones nuevas). No se pudo
probar en navegador real (sin Chrome conectado en esa sesión).

**Portado a `tenants/tst` el mismo día (13/08/2026):** se confirmó primero que los dos archivos
de `tst` eran byte-idénticos a la versión pre-fix de EstetiCAN (sin divergencia propia del
tenant) — se copiaron los mismos 2 archivos ya corregidos y se corrió `view:clear`+`view:cache`
en `tst_app` (compiló sin error; sin phpunit instalado ahí para correr tests). Avisado a la
sesión paralela de Zeus-Estetican (`tst-3c`) por `SendMessage` para que lo deje anotado en su
propia `BITACORA.md`/`NOTAS_TECNICAS.md`.

**Nota:** `tenants/` está en `.gitignore` de Zeus-Estetican a propósito (no es código propio) —
si `tst` se reprovisiona desde cero, este fix se pierde ahí y habría que volver a portarlo.
