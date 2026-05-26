# Notas Técnicas de Desarrollo — EstetiCAN

> Este archivo documenta bugs no obvios, decisiones de arquitectura con consecuencias prácticas,
> y soluciones que costaron tiempo de diagnóstico. Cada entrada explica el SÍNTOMA, la CAUSA RAÍZ
> y la SOLUCIÓN, para que no se repita el mismo problema.

---

## NT-001 — cropperjs: v2 incompatible con código v1

**Fecha:** 2026-05-25
**Componente:** `x-image-upload` / `resources/js/modules/image-upload.js`
**Síntoma:** Al subir una foto, el modal abría pero la imagen se veía pequeña; no recortaba, no giraba y no guardaba.

**Causa raíz:**
El `package.json` tenía `"cropperjs": "^2.1.1"`. La v2 es una reescritura completa basada en Web Components (`<cropper-canvas>`, `<cropper-selection>`, etc.) con API completamente distinta. La v1 tenía métodos `rotate()`, `getCroppedCanvas()` y opciones como `aspectRatio`, `viewMode`, `dragMode`, etc. En v2 esos métodos NO EXISTEN en el objeto `Cropper`. La clase `Cropper` de v2 solo tiene `getCropperCanvas()`, `getCropperImage()`, `getCropperSelection()` y `destroy()`.

El código usa la API de v1 (la misma que corría del CDN `cropperjs@1.6.2`).

**Solución:**
```bash
npm install cropperjs@1.6.2 --save-exact
npm run build
```

**Regla de oro:**
- El CSS en `resources/css/vendor-cropper.css` es de v1. Si alguna vez se actualiza cropperjs, se debe descargar el CSS de la versión correspondiente Y verificar que todos los métodos usados existan en la nueva API.
- `package.json` debe fijar la versión exacta: `"cropperjs": "1.6.2"` (sin `^`).

---

## NT-002 — Inicialización de Cropper.js en modal Bootstrap

**Fecha:** 2026-05-25
**Componente:** `x-image-upload` / `resources/js/modules/image-upload.js`
**Síntoma:** Imagen visible en el modal pero el recortador no aparece o aparece con dimensiones incorrectas (muy pequeño).

**Causa raíz:**
Cropper.js mide el contenedor cuando se inicializa. Si se inicializa antes de que el modal de Bootstrap sea visible (`.modal.fade` no ha terminado su transición), el contenedor tiene dimensiones 0 o incorrectas.

**Solución:**
```javascript
// Registrar el listener ANTES de abrir el modal
const onShown = () => {
    modalEl.removeEventListener('shown.bs.modal', onShown);
    requestAnimationFrame(() => {         // garantiza un frame pintado
        this.cropper = new Cropper(img, { aspectRatio, ... });
    });
};
modalEl.addEventListener('shown.bs.modal', onShown);
img.src = dataUrl;              // asignar src después del listener
this.modalInstance.show();      // abrir modal — shown.bs.modal se disparará al terminar la animación (~300ms)
```

**Por qué `requestAnimationFrame`:** `shown.bs.modal` puede dispararse justo antes de que el navegador pinte el frame final. El `rAF` garantiza que Cropper mide dimensiones reales.

**Por qué NO `img.decode()`:** `decode()` en un elemento oculto dentro de un modal no garantiza que el modal tenga layout. El tiempo de la animación Bootstrap (~300ms) es suficiente para que cualquier DataURL se decodifique.

---

## NT-003 — Contenedor del modal: `height` fijo vs `max-height`

**Fecha:** 2026-05-25
**Componente:** `resources/views/components/image-upload.blade.php`
**Síntoma:** El recortador aparece pero el área de recorte es mínima o solo muestra la parte superior de la imagen.

**Causa raíz:**
Con `max-height: 60vh; overflow: hidden`, el contenedor tiene una altura determinada por el contenido (la imagen). Cuando Cropper.js mide el contenedor antes de que la imagen tenga sus dimensiones correctas, puede obtener un alto de 0 o incorrecto.

**Solución:**
```html
<!-- CORRECTO: altura fija para que Cropper pueda medir -->
<div style="height: 60vh; overflow: hidden;">
    <img src="" x-ref="cropImage" style="display:block; max-width: 100%;">
</div>

<!-- INCORRECTO: altura variable, Cropper puede medir 0 -->
<div style="max-height: 60vh; overflow: hidden;">
    <img src="" x-ref="cropImage" style="display:block; width: 100%;">
</div>
```

**Regla:** El contenedor de Cropper.js siempre debe tener una altura CSS fija (`height`, no `max-height`).

---

## NT-004 — Alpine.js: `x-ref` en Blade Components con `x-data` anidado

**Fecha:** 2026-05-25
**Componente:** `resources/show.blade.php` (patrón B de auto-submit)
**Síntoma / Contexto:** El form padre necesita un ref al `x-image-upload` para encontrar el input de archivo y enviar el form programáticamente.

**Patrón que funciona:**
```blade
<form x-data="{ submit() { this.$el.submit(); } }">
    <div @image-cropped="submit()">
        <x-image-upload x-ref="photoInput" ... />
    </div>
</form>
```

`x-ref="photoInput"` sobre el Blade component pasa el atributo al div raíz del componente (via `$attributes->merge()`). Alpine del form padre puede acceder a `this.$refs.photoInput` porque el `x-ref` está en el elemento raíz del componente hijo, que técnicamente pertenece al scope del padre (el scope hijo comienza con `x-data` que está en el mismo elemento).

**Alternativa más simple:** usar `autoSubmitFormId` en el componente directamente.

---

## NT-005 — Blade: `@php(expr)` con paréntesis anidados

**Fecha:** 2026-05-15 (detectado en auditoría)
**Síntoma:** Variables `undefined` aleatorias en producción. El compilador Blade detiene el procesado en el primer `)` interno de `parse_url(...)`, `in_array(...)`, `firstWhere(...)`, etc.

**Causa raíz:** La directiva `@php(expr)` tiene un parser rudimentario que no maneja paréntesis anidados.

**Solución:** Usar siempre el bloque completo:
```blade
{{-- MAL --}}
@php($url = parse_url($value, PHP_URL_PATH))

{{-- BIEN --}}
@php
    $url = parse_url($value, PHP_URL_PATH);
@endphp
```

---

## NT-006 — CSP + Alpine.js requiere `unsafe-eval`

**Fecha:** 2026-05-25
**Componente:** `app/Http/Middleware/ContentSecurityPolicy.php`
**Síntoma:** Alpine.js no evalúa expresiones (`x-data`, `@click`, `x-show`) cuando hay CSP sin `unsafe-eval`.

**Causa raíz:** Alpine.js v3 usa `new AsyncFunction()` internamente para evaluar expresiones de templates, lo que requiere `unsafe-eval`.

**Solución en CSP:**
```php
"script-src 'self' 'nonce-{$nonce}' 'unsafe-eval'",
```

**Nota:** Los scripts inline (JS en `<script>`) necesitan el nonce. Alpine cargado desde el bundle no necesita nonce porque el bundle es `'self'`.

---

## NT-007 — Bootstrap Modal movido al body rompe Alpine $refs

**Fecha:** 2026-05-25 (riesgo conocido, no ocurrido)
**Componente:** `x-image-upload`
**Contexto:** Bootstrap Modal mueve el elemento modal al `<body>` cuando tiene `data-bs-backdrop`. Esto podría sacar el modal del scope de Alpine.

**Estado actual:** No ocurre porque `data-bs-backdrop="static"` se aplica sin `data-bs-target` — el modal es inicializado por JS con `new bootstrap.Modal(modalEl)` pasando la referencia directa. Bootstrap no lo mueve al body en este caso porque lo inicializamos con la referencia al elemento, no con un trigger HTML.

**Si se manifiesta:** Usar `document.body.appendChild(modalEl)` manualmente y guardar el ref antes de moverlo.
