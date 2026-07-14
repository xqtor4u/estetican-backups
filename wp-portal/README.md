# Portal público (WordPress)

Este directorio **no se despliega** — es solo un repositorio de trabajo para el contenido del sitio WordPress público (`estetican.org`), que se administra por fuera de este proyecto. Sirve para tener el código versionado y poder copiar/pegar desde acá hacia el editor de WordPress (bloques separados de HTML/CSS/JS).

## Estructura

Una carpeta por página del sitio. Hoy solo existe una:

- `pagina-servicios/` — página principal de servicios (SPA/Hotel), con el widget del asistente de IA (BL-042) integrado.
  - `html.html` → bloque HTML del editor (incluye el HTML del widget + la etiqueta `<script>` que lo carga, al final)
  - `styles.css` → bloque CSS del editor
  - `script.js` → bloque JS del editor — **queda vacío a propósito**, ver más abajo por qué

## Widget del asistente de IA

El widget (botón flotante + panel de chat) apunta a `https://app.estetican.org/api/assistant/*`. Configuración completa en `apps/backoffice-laravel/app/Support/SystemSettings/SystemSettings.php` (sección `ai_assistant`) — ver `docs/tecnico/MODELO_BD.md` y `docs/tecnico/BACKLOG.md` (BL-042) para el diseño completo.

Si se agrega el widget a una página nueva, copiar el HTML del widget (desde `<!-- Widget del asistente de IA -->` en adelante, incluida la etiqueta `<script>` final) + el CSS del widget (sección `Widget del asistente de IA` al final de cualquier `styles.css` existente) — no hace falta tocar nada del backend ni del bloque JS.

## ⚠️ Por qué el bloque JS del editor queda vacío (causa raíz, 14/07/2026)

El editor de WordPress que se usa en este sitio le aplica `wpautop` (el filtro automático de párrafos de WordPress) al contenido del campo JS **antes** de envolverlo en la etiqueta `<script>` — no después. Eso significa que WordPress mete `<p>`/`</p>` alrededor (y, si hay líneas en blanco, también en medio) de cualquier JavaScript que se pegue en ese campo, rompiéndolo con errores de sintaxis. Confirmado con dos intentos:
1. Con líneas en blanco en el JS → `<p>`/`</p>` aparecían **en medio de funciones**, partiéndolas a la mitad.
2. Sin ninguna línea en blanco → seguía apareciendo un `<p>` al principio y `</p>` al final de **todo el bloque** (wpautop envuelve cualquier texto "suelto" en al menos un párrafo, tenga o no líneas en blanco adentro).

**Solución adoptada:** toda la lógica del widget vive en un archivo externo (`apps/backoffice-laravel/public/assistant-widget-wp.js`, servido como asset estático — no hace falta build ni deploy especial, es Laravel el que lo sirve directo). El HTML incluye una sola etiqueta `<script src="..." data-api-base="..." data-token="..." async></script>` **sin ningún texto entre `<script>` y `</script>`** — solo atributos. Como wpautop solo puede corromper *contenido de texto*, una etiqueta vacía con atributos es inmune, sin importar cuántos `<p>` le agregue alrededor (son inofensivos fuera del cuerpo del script).

Si en algún momento aparece un toggle de "HTML sin procesar" / "Raw HTML" / "Desactivar wpautop" en el editor real, ya no sería necesario este rodeo — pero mientras no se confirme que existe, esta es la solución estable. **No volver a pegar JavaScript con texto directo en el campo JS del editor** — siempre va a terminar corrompido.
