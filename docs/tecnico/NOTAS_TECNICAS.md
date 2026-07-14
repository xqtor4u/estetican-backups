# Base de Errores Conocidos (KEDB) — EstetiCAN
### Known Error Database · Gestión de Problemas ITIL 4

> **Propósito ITIL:** Este archivo es el registro formal de Problemas del servicio.
> Un Problema es la causa raíz de uno o más Incidentes. Documentarlo aquí previene
> que el mismo Incidente se repita y reduce el MTTR (Mean Time To Restore) de futuras
> ocurrencias.
>
> **Cuándo crear una entrada:** Cuando se resuelve un bug no trivial, o cuando
> se identifica un riesgo técnico aunque no haya ocurrido aún como incidente.
>
> **Formato de cada entrada:**
> - **ID:** NT-XXX (secuencial)
> - **Clasificación:** Severidad (P1–P4) e impacto (quién/qué afecta)
> - **Síntoma:** Lo que ve el usuario o el operador
> - **Causa raíz:** El por qué técnico real
> - **Workaround:** Solución temporal si existe (para aplicar mientras se resuelve)
> - **Solución definitiva:** El fix permanente aplicado
> - **Lección:** La regla de oro para que no vuelva a ocurrir

---

## Índice de Severidades

| Nivel | Criterio | Tiempo de respuesta |
|---|---|---|
| **P1 — Crítico** | Sistema caído o flujo de negocio principal bloqueado | Inmediato (Cambio de Emergencia) |
| **P2 — Alto** | Funcionalidad importante rota, hay workaround parcial | Misma sesión |
| **P3 — Medio** | Funcionalidad degradada, usuarios pueden operar con dificultad | Próxima sesión |
| **P4 — Bajo** | Problema cosmético o riesgo identificado (no manifestado) | Backlog |

---

## NT-001 — cropperjs v2 incompatible con código v1

| Campo | Valor |
|---|---|
| **Fecha** | 2026-05-25 |
| **Severidad** | P1 — Crítico |
| **Componente** | `x-image-upload` / `resources/js/modules/image-upload.js` |
| **Impacto** | Subida de fotos 100% inoperativa en todo el sistema (7 vistas afectadas) |
| **Estado** | ✅ RESUELTO |

**Síntoma:**
Al subir una foto, el modal abría pero la imagen se veía muy pequeña; no recortaba, no giraba y no guardaba. El comportamiento era idéntico en todas las entidades (mascotas, recursos, operadores, usuarios).

**Causa raíz:**
`package.json` tenía `"cropperjs": "^2.1.1"`. La v2 es una reescritura completa basada en Web Components (`<cropper-canvas>`, `<cropper-selection>`, etc.) con API totalmente distinta a v1. En v2, los métodos `rotate()`, `getCroppedCanvas()` y las opciones `aspectRatio`, `viewMode`, `dragMode`, `autoCropArea`, etc. **no existen** en la clase `Cropper`. La clase v2 solo expone `getCropperCanvas()`, `getCropperImage()`, `getCropperSelection()`, `destroy()`. El código usaba la API de v1 (la misma que funcionaba vía CDN `cropperjs@1.6.2`). El modal se abría porque Bootstrap funciona independientemente, pero Cropper inicializaba un objeto v2 vacío — de ahí la imagen pequeña (la `<img>` original sin Cropper encima).

**Workaround:**
Ninguno viable. El sistema de fotos era completamente inoperable.

**Solución definitiva:**
```bash
npm install cropperjs@1.6.2 --save-exact
npm run build
# En package.json queda: "cropperjs": "1.6.2" (sin ^)
```

**Lección:**
- Las librerías de UI con major versions son **ítems de configuración controlados**. Ver CMDB en `ESTRATEGIA_DESARROLLO.md §8`.
- **Nunca usar `^` en librerías de UI** como cropperjs o flatpickr. Los breaking changes entre major versions son totales.
- El CSS en `resources/css/vendor-cropper.css` es de v1. Si se actualiza cropperjs, el CSS también debe actualizarse y las diferencias de API deben auditarse.

---

## NT-002 — Inicialización de Cropper.js antes de que el modal sea visible

| Campo | Valor |
|---|---|
| **Fecha** | 2026-05-25 |
| **Severidad** | P2 — Alto |
| **Componente** | `x-image-upload` / `resources/js/modules/image-upload.js` |
| **Impacto** | Recortador aparece con dimensiones incorrectas o sin renderizar |
| **Estado** | ✅ RESUELTO |

**Síntoma:**
La imagen se veía en el modal pero el recortador no aparecía, o aparecía muy pequeño. Los controles de Cropper (handles, crop box) no eran visibles o eran diminutos.

**Causa raíz:**
Cropper.js mide el contenedor DOM cuando se inicializa (`new Cropper(img, options)`). Si se llama antes de que Bootstrap haya completado la animación `fade` del modal (`.show()` dispara la animación, pero el modal no tiene dimensiones reales hasta que termina), Cropper obtiene `clientWidth: 0` o dimensiones incorrectas y construye un canvas de tamaño 0.

Los intentos fallidos fueron:
- `setTimeout(300ms)` → el tiempo varía según el dispositivo
- `img.decode()` → decodifica la imagen pero no garantiza que el modal tenga layout

**Workaround:**
Ninguno funcional. El síntoma era indistinguible de NT-001 para el usuario.

**Solución definitiva:**
```javascript
// Registrar el listener ANTES de abrir el modal
const onShown = () => {
    modalEl.removeEventListener('shown.bs.modal', onShown);
    requestAnimationFrame(() => {         // garantiza exactamente un frame pintado
        this.cropper = new Cropper(img, { aspectRatio, ...opciones });
    });
};
modalEl.addEventListener('shown.bs.modal', onShown);
img.src = dataUrl;              // asignar src DESPUÉS del listener
this.modalInstance.show();      // fired → shown.bs.modal al terminar animación (~300ms)
```

`shown.bs.modal` es el evento oficial de Bootstrap que garantiza que el modal es completamente visible. El `requestAnimationFrame` adicional asegura que el navegador ha pintado al menos un frame.

**Lección:**
- Siempre inicializar librerías de visualización (Cropper, Charts, Maps) dentro del evento de "elemento visible" de su contenedor, nunca antes.
- `img.decode()` sirve para decodificar datos de imagen, no para garantizar layout CSS.

---

## NT-003 — Contenedor del modal: `height` fijo vs `max-height`

| Campo | Valor |
|---|---|
| **Fecha** | 2026-05-25 |
| **Severidad** | P3 — Medio |
| **Componente** | `resources/views/components/image-upload.blade.php` |
| **Impacto** | El área de recorte muestra solo la parte superior de la imagen |
| **Estado** | ✅ RESUELTO |

**Síntoma:**
El recortador se inicializaba correctamente pero solo mostraba la parte superior de la imagen. Los handles de Cropper quedaban fuera del área visible.

**Causa raíz:**
El contenedor del modal tenía `max-height: 60vh; overflow: hidden`. Con `max-height`, la altura del contenedor es determinada por el contenido (la imagen). Cuando hay imágenes con orientación portrait (más altas que anchas), `width: 100%` hace la imagen tan alta que excede el viewport y los controles de Cropper quedan ocultos por `overflow: hidden`. Con `height: 60vh` (fijo), el contenedor siempre tiene 60vh sin importar la imagen.

**Workaround:**
Usar solo imágenes landscape. No aplicable en producción.

**Solución definitiva:**
```html
<!-- CORRECTO: altura fija -->
<div style="height: 60vh; overflow: hidden;">
    <img src="" x-ref="cropImage" style="display:block; max-width: 100%;">
</div>

<!-- INCORRECTO: altura variable según contenido -->
<div style="max-height: 60vh; overflow: hidden;">
    <img src="" x-ref="cropImage" style="display:block; width: 100%;">
</div>
```

**Lección:**
- Cropper.js necesita un contenedor con **dimensiones absolutas** (no relativas al contenido).
- `max-height` + `overflow:hidden` es una combinación que recorta visualmente pero no fija el layout desde la perspectiva de Cropper.

---

## NT-004 — Alpine.js: `x-ref` en Blade Components con `x-data` anidado

| Campo | Valor |
|---|---|
| **Fecha** | 2026-05-25 |
| **Severidad** | P4 — Bajo (diseño documentado, no bug) |
| **Componente** | `resources/show.blade.php` — Patrón B de auto-submit |
| **Impacto** | Si se implementa incorrectamente, el form no se envía al aplicar el recorte |
| **Estado** | ✅ DOCUMENTADO |

**Contexto:**
El form padre necesita acceder al `input[type=file]` dentro de `x-image-upload` para verificar si hay archivo antes de enviar.

**Patrón correcto:**
```blade
<form x-data="{ submit() { this.$el.submit(); } }">
    <div @image-cropped="submit()">
        {{-- x-ref va en el componente Blade, no en el div wrapper --}}
        <x-image-upload x-ref="photoInput" ... />
    </div>
</form>
```

`x-ref="photoInput"` sobre un Blade component pasa el atributo al div raíz del componente mediante `$attributes->merge()`. Alpine del form padre puede acceder con `this.$refs.photoInput` porque el `x-ref` está en el elemento raíz del componente hijo, que pertenece al scope del padre (el scope hijo comienza con `x-data` en ese mismo elemento).

**Alternativa más simple (preferida):**
```blade
<x-image-upload autoSubmitFormId="mi-form-id" ... />
```
Usar `autoSubmitFormId` directamente en el componente evita toda esta complejidad.

---

## NT-005 — Blade: `@php(expr)` con paréntesis anidados

| Campo | Valor |
|---|---|
| **Fecha** | 2026-05-15 (detectado en auditoría de producción) |
| **Severidad** | P1 — Crítico |
| **Componente** | Compilador Blade (afecta cualquier vista) |
| **Impacto** | Variables `undefined` aleatorias en producción. 29 vistas afectadas en el incidente del 25/05. |
| **Estado** | ✅ RESUELTO (corrección masiva aplicada con script Python) |

**Síntoma:**
Variables PHP aparecen como `undefined` de forma aleatoria en producción. El error puede ser `Undefined variable $X` en cualquier punto de la vista después de la directiva afectada.

**Causa raíz:**
La directiva `@php(expr)` tiene un parser que no maneja paréntesis anidados. Al encontrar el primer `)` interno (en funciones como `parse_url(...)`, `in_array(...)`, `firstWhere(...)`), el compilador Blade cierra la directiva y el resto de la expresión queda sin procesar, corrompiendo todo lo que sigue en la vista.

**Workaround:**
Extraer la expresión a una variable PHP antes del template. No siempre práctico.

**Solución definitiva:**
```blade
{{-- ❌ MAL: el parser Blade falla en el primer ) interno --}}
@php($url = parse_url($value, PHP_URL_PATH))
@php($found = in_array($item->id, $ids))

{{-- ✅ BIEN: bloque completo, sin ambigüedad --}}
@php
    $url = parse_url($value, PHP_URL_PATH);
    $found = in_array($item->id, $ids);
@endphp
```

**Lección:**
- **Regla absoluta:** No usar `@php(expr)` con paréntesis anidados. Solo expresiones simples sin funciones que contengan paréntesis.
- Para todo lo demás: definir la variable en el controlador y pasarla con `compact()`.
- **Ojo:** `@php ... @endphp` multilínea también tiene sus propios bugs dentro de loops y secciones — ver NT-008 y NT-010.
- Las vistas compiladas en caché pueden ocultar el error hasta que la caché se limpie. Siempre limpiar vistas después de cambios en Blade.

---

## NT-006 — CSP + Alpine.js requiere `unsafe-eval`

| Campo | Valor |
|---|---|
| **Fecha** | 2026-05-25 |
| **Severidad** | P1 — Crítico (Alpine completamente bloqueado sin esto) |
| **Componente** | `app/Http/Middleware/ContentSecurityPolicy.php` |
| **Impacto** | Alpine.js no evalúa ninguna expresión. La UI interactiva queda completamente rota. |
| **Estado** | ✅ RESUELTO |

**Síntoma:**
Con CSP activo sin `unsafe-eval`: los directives `x-data`, `@click`, `x-show`, `x-if` no tienen efecto. La UI parece "muerta".

**Causa raíz:**
Alpine.js v3 evalúa sus expresiones de template usando `new AsyncFunction()` (equivalente a `eval()`). Esta API requiere `unsafe-eval` en la directiva `script-src` del CSP. Sin ella, el navegador bloquea la evaluación silenciosamente.

**Solución definitiva:**
```php
// ContentSecurityPolicy.php
"script-src 'self' 'nonce-{$nonce}' 'unsafe-eval'",
```

**Lección:**
- Los scripts inline (bloques `<script>...</script>`) necesitan el nonce.
- El bundle (servido desde `/build/assets/`) es `'self'` y no necesita nonce.
- `unsafe-eval` es necesario para Alpine pero NO para Bootstrap, Cropper u otras librerías.
- Considerar migrar a Alpine CSP Build (versión sin `eval`) si se requiere mayor seguridad en el futuro.

---

## NT-007 — Riesgo: Bootstrap Modal desvincula Alpine `$refs` si se mueve al body

| Campo | Valor |
|---|---|
| **Fecha** | 2026-05-25 |
| **Severidad** | P4 — Bajo (riesgo identificado, no ocurrido) |
| **Componente** | `x-image-upload` — modal Bootstrap |
| **Impacto** | Potencial: `this.$refs.cropModal` queda `undefined` si Bootstrap mueve el modal |
| **Estado** | ⚠️ RIESGO DOCUMENTADO — no ocurre en configuración actual |

**Contexto:**
Bootstrap Modal puede mover el elemento `<div class="modal">` al `<body>` cuando se usa `data-bs-target` (trigger HTML declarativo). Si el modal sale del scope Alpine, `this.$refs.cropModal` podría quedar inválido.

**Por qué NO ocurre actualmente:**
El modal se inicializa vía JS con `new bootstrap.Modal(modalEl)` pasando la referencia directa. En este modo Bootstrap NO mueve el elemento al body. El modal permanece donde está en el DOM, dentro del scope Alpine.

**Si llegara a ocurrir:**
1. Guardar la referencia antes de que Bootstrap mueva el modal: `const modalRef = this.$refs.cropModal`
2. Mover manualmente: `document.body.appendChild(modalRef)`
3. Inicializar Cropper con la referencia guardada (no con `this.$refs`)

**Lección:**
- Siempre inicializar Bootstrap Modal con la referencia al elemento, no con atributos `data-bs-target`.
- Preferir `new bootstrap.Modal(element)` sobre `<button data-bs-toggle="modal" data-bs-target="#...">`.

---

## NT-008 — Blade: `@php ... @endphp` multi-línea rompe el section stack cuando coexiste con `<x-slot>`

| Campo | Valor |
|---|---|
| **Fecha** | 2026-06-14 |
| **Severidad** | P2 — Alto (500 en producción) |
| **Componente** | Blade — section stack / componentes con slots |
| **Impacto** | `ViewException: Cannot end a section without first starting one` |
| **Estado** | ✅ RESUELTO |

**Síntoma:**
Vistas con `@extends` + `@section` + `<x-slot:actions>` + bloque `@php ... @endphp` multi-línea arrojaban 500 en producción con el error `Cannot end a section without first starting one`.

**Causa raíz:**
El compilador de Blade gestiona `@section`/`@endsection` y los slots de componentes (`<x-slot:actions>`) a través del mismo stack interno. Un bloque `@php ... @endphp` multi-línea, al compilarse como `<?php ... ?>`, interfiere con el rastreo del stack en ciertos contextos, dejando a `@endsection` sin una sección abierta que cerrar.

**Solución:**
Nunca definir variables con `@php ... @endphp` (forma de bloque) dentro de vistas que tengan `@section` + componentes con slots. En cambio:

1. **Preferido:** pasar la variable desde el controlador con `compact()`.
2. **Alternativa:** usar la forma de expresión `@php($var = valor)` (sin `@endphp`) para asignaciones simples dentro del cuerpo de un loop.
3. **Para arrays estáticos** usados en la vista: siempre definirlos en el controlador.

**Lo que NO produce el error:**
- `@php($var = expr)` en una sola línea (sin `@endphp`).
- Bloques `@php ... @endphp` dentro de partials `@include` (que no tienen `@section`).

**Patrón correcto:**
```php
// Controlador
return view('finances.payment-methods.index', compact('methods', 'typeLabels'));
```
```blade
{{-- Vista: sin @php de bloque --}}
{{ $typeLabels[$method->type] ?? $method->type }}
```


---

## NT-010 — Blade: `@php ... @endphp` multilínea dentro de `@foreach`/`@forelse` causa ParseError

| Campo | Valor |
|---|---|
| **Fecha** | 2026-06-15 |
| **Severidad** | P1 — Crítico (500 en producción) |
| **Componente** | Compilador Blade — directivas dentro de loops |
| **Impacto** | `ParseError: unexpected token 'else'` o `unexpected token 'endforeach'` en cualquier vista con loop |
| **Estado** | ✅ RESUELTO |

**Síntoma:**
La vista arroja 500 con `ParseError: syntax error, unexpected token "else"` o `unexpected token "endforeach"`. El error apunta a una línea dentro de un `@forelse` o `@foreach`.

**Causa raíz:**
En la versión de Laravel/Blade del proyecto, el bloque `@php ... @endphp` multilínea dentro de un loop compila de forma inconsistente: algunas directivas que siguen dentro del mismo loop (`@else`, `@endif`, `@endforelse`) se compilan a PHP pero sin su correspondiente apertura, creando tokens huérfanos que rompen la sintaxis del archivo compilado.

Ejemplo que falla:
```blade
@forelse($sessions as $session)
    @php
        $diff = $session->difference;
    @endphp
    @if($diff > 0)
        ...
    @else               {{-- 💥 este @else queda huérfano --}}
        ...
    @endif
@empty
@endforelse
```

**Workaround:**
Ninguno en caliente — requiere corrección del template.

**Solución definitiva:**
1. **Opción preferida:** pasar la variable desde el controlador.
2. **Si la variable debe calcularse en vista:** definirla ANTES del loop con `@php($var = expr)` de una sola línea (sin `@endphp`), y referenciarla dentro del loop sin reasignar.

```blade
{{-- ❌ MAL: @php multilínea dentro del loop --}}
@forelse($sessions as $session)
    @php
        $diff = $session->difference;
    @endphp
    {{ $diff }}
@empty
@endforelse

{{-- ✅ BIEN: usar directamente el valor, sin @php dentro del loop --}}
@forelse($sessions as $session)
    {{ $session->difference }}
@empty
@endforelse

{{-- ✅ BIEN: si necesitas pre-calcular algo, hazlo FUERA del loop --}}
@php($totals = $sessions->pluck('difference'))
@forelse($sessions as $i => $session)
    {{ $totals[$i] }}
@empty
@endforelse
```

**Lección:**
- **Regla de proyecto:** NUNCA usar `@php ... @endphp` (forma de bloque, multilínea) dentro de `@foreach`, `@forelse` o cualquier loop Blade.
- Dentro de loops: solo `@php($var = expr)` en una sola línea, y solo si no hay otra opción.
- La forma más segura siempre es pasar todo desde el controlador con `compact()`.
- Este bug es distinto de NT-005 (paréntesis anidados en `@php(expr)`) y de NT-008 (block dentro de section+slot). Son tres familias de bugs del compilador Blade.

---

## NT-009 — Cloudflare Tunnel: "Hostname routes" ≠ hostnames públicos

| Campo | Valor |
|---|---|
| **Fecha** | 2026-06-15 |
| **Severidad** | P3 — Medio (confusión de UI que bloquea el deploy) |
| **Componente** | Cloudflare Zero Trust — configuración del tunnel `orangepi-estetican` |
| **Impacto** | Tiempo perdido intentando configurar hostname público en el lugar equivocado |
| **Estado** | ✅ DOCUMENTADO |

**Síntoma:**
Al agregar un subdominio nuevo (`mov.estetican.org`) al tunnel de Cloudflare, la sección "Hostname routes" en Zero Trust → Networks → Connectors parece el lugar correcto pero en realidad es para **hostnames privados** (red privada, requiere Cloudflare WARP / One Client).

**Causa raíz:**
Cloudflare renombró y reorganizó su interfaz de Zero Trust. La tabla de "Public Hostnames" del tunnel (antes en la pestaña del mismo nombre al editar el tunnel) ahora se llama **"Published application routes"** y está en una pestaña diferente dentro del mismo tunnel.

**Solución:**
Para agregar un hostname público a un tunnel existente:
1. Zero Trust → Networks → Connectors → Cloudflare Tunnels → clic en el tunnel
2. Pestaña **Published application routes** (NO "Hostname routes")
3. Botón "Add" → Subdomain + Domain + Service: `http://192.168.100.250:80`
4. Cloudflare crea el registro DNS automáticamente (tipo Tunnel)

**Lección:**
- "Hostname routes" = red privada (necesita WARP)
- "Published application routes" = hostnames públicos accesibles desde cualquier browser
- Al agregar la ruta, NO crear manualmente el registro DNS — Cloudflare lo crea solo con tipo "Tunnel"

---

## NT-013 — `migrate` desde cero falla: `users.operator_role_id` sin migración propia

| Campo | Valor |
|---|---|
| **Fecha** | 2026-07-01 |
| **Severidad** | P2 — Bloquea `artisan migrate` en cualquier base de datos nueva (ej. `testing`) |
| **Componente** | `database/migrations/2026_06_30_000001_add_operator_id_to_users_table.php` |
| **Impacto** | `Schema::table('users', ...)->after('operator_role_id')` falla con `Unknown column 'operator_role_id'` en una base nueva |
| **Estado** | ✅ RESUELTO |

**Causa raíz:**
`users.operator_role_id` existe en producción y se usa activamente en `App\Models\User::operatorRole()`, pero ninguna migración del repo la crea — se agregó en algún momento fuera del flujo de migraciones (parche manual histórico, posible edición de un archivo de migración después de haber corrido en prod). La migración `2026_06_30_000001_add_operator_id_to_users_table.php` asume la columna presente vía `->after('operator_role_id')`, lo cual solo es cierto en producción, no en una base nueva.

**Cómo se detectó:** al habilitar por primera vez la base `testing` (ver más abajo) para correr `artisan test`.

**Solución:** migración nueva `2026_06_30_000000_add_operator_role_id_to_users_table.php` (timestamp anterior a la 000001) que crea la columna solo si falta (`Schema::hasColumn` guard) — no-op en producción, corrige el flujo en bases nuevas.

**Lección:** cuando una migración usa `->after('columna_de_otra_migracion')`, verificar que esa columna tenga su propia migración rastreable en el repo, no solo que exista en producción.

---

## NT-014 — No existe entorno Sail/dev separado en esta OPi; usar `docker exec estetican_app`

| Campo | Valor |
|---|---|
| **Fecha** | 2026-07-01 |
| **Severidad** | P3 — Bloquea trabajo si se intenta seguir el flujo documentado de WSL2+Sail al pie de la letra |
| **Componente** | Todo el repo — expectativa de entorno de desarrollo |
| **Estado** | ✅ RESUELTO (documentado) |

**Síntoma:** `./vendor/bin/sail up -d` falla con `Error starting userland proxy: ... bind: address already in use` en el puerto 8000.

**Causa raíz:** El contenedor de producción `estetican_app` (`compose.prod.yaml`) expone `127.0.0.1:${APP_PORT:-8000}:80` a propósito, para diagnóstico local — y monta `.:/var/www/html`, es decir, sirve exactamente el mismo código fuente de `apps/backoffice-laravel` que se edita en esta OPi. No hay una base de datos ni contenedor de desarrollo separados; todo el trabajo (migraciones, tinker, tests) corre directo contra el contenedor de producción vía `docker exec estetican_app ...`, como ya documentaba `docs/OPI_PRODUCCION.md` y `docs/tecnico/ESTRATEGIA_DESARROLLO.md`.

**Solución:** no usar Sail en este proyecto. Comandos: `docker exec estetican_app php artisan migrate --force`, `docker exec -it estetican_app php artisan tinker`, `docker exec estetican_app npm run build` (node/npm sí están instalados dentro de esa imagen).

**Lección:** la sección "Development Environment" de `CLAUDE.md` (WSL2 + Sail) describe un flujo que no aplica a esta instancia de la OPi — es el flujo genérico/aspiracional del template del proyecto, no el real. El real está en `docs/OPI_PRODUCCION.md`.

---

## NT-015 — Otra columna sin migración propia: `users.can_login`

| Campo | Valor |
|---|---|
| **Fecha** | 2026-07-02 |
| **Severidad** | P3 — Bloquea `migrate` desde cero en base nueva; sin impacto en producción |
| **Componente** | `users.can_login` |
| **Estado** | ✅ RESUELTO |

Mismo patrón que NT-013: `users.can_login` existe en producción (usado por `App\Http\Middleware\ApiAuthenticate` para bloquear login de usuarios desactivados) pero ninguna migración del repo la crea. Se agregó `2026_07_02_000000_add_can_login_to_users_table.php` con guard `Schema::hasColumn()` — no-op en producción, corrige el flujo en bases nuevas. Detectado al escribir tests contra la base `testing` recién habilitada (ver NT-014).

**Lección:** cada vez que se habilite `artisan test` desde cero en este proyecto, es probable que aparezcan más columnas de `users`/`operators` parcheadas manualmente sin migración rastreable (patrón recurrente de deuda técnica histórica). Solo se corrigen bajo demanda, cuando bloquean trabajo activo — no se persigue la lista completa preventivamente.

---

## NT-016 — `Api\BookingController`: `total_estimated_price` quedaba `null` con total en $0

| Campo | Valor |
|---|---|
| **Fecha** | 2026-07-02 |
| **Severidad** | P2 — Cualquier cita creada vía API móvil sin `services` (o con servicios de precio $0) fallaba con `Column 'total_estimated_price' cannot be null` |
| **Componente** | `app/Http/Controllers/Api/BookingController.php` (`store`, `update`) |
| **Estado** | ✅ RESUELTO |

**Causa raíz:** `'total_estimated_price' => $estimatedTotal ?: null` — el operador `?:` trata `0` como falsy, así que un total exactamente en `$0` (sin servicios, o servicios gratuitos) se guardaba como `null`, violando la columna `NOT NULL`. Detectado por un test nuevo (`BookingSchedulingValidationTest::test_accepts_a_valid_booking`) que no incluía `services` en el payload — no era un caso hipotético, cualquier llamada real a la API sin ese campo ya fallaba en producción.

**Solución:** guardar siempre `$estimatedTotal` (o `$prices->sum()`) directo, sin el atajo `?: null` — `0` es un total válido, no equivalente a "sin dato".

**Lección:** `?:` (operador Elvis) es peligroso con valores numéricos donde `0` es un resultado legítimo — usar `??` (null coalescing) o ninguno de los dos si el valor nunca debería convertirse a `null`.

---

## NT-019 — `node_modules`/`public/build` quedan `root` tras alguna ejecución previa y bloquean `npm run build` en la OPi

| Campo | Valor |
|---|---|
| **Fecha** | 2026-07-03 |
| **Severidad** | P3 — Bloquea compilación de assets hasta corregir permisos manualmente |
| **Componente** | `apps/backoffice-laravel/node_modules/`, `apps/backoffice-laravel/public/build/` |
| **Estado** | ✅ RESUELTO (workaround conocido) |

**Síntoma:** `npm run build` falla con `EACCES: permission denied` al escribir en `node_modules/.vite-temp/` o al intentar borrar `public/build/assets/*` antes de generar los nuevos archivos.

**Causa raíz:** en algún momento esos directorios se crearon/escribieron con el usuario `root` (probablemente una ejecución anterior de un proceso dentro de un contenedor Docker, o un `npm install`/build corrido con `sudo` por error) en vez de con el usuario `tomas`. `tomas` no tiene permiso de escritura sobre el árbol, así que Vite no puede crear su archivo temporal de config ni limpiar el directorio de salida.

**Solución:**
```bash
sudo chown -R tomas:tomas apps/backoffice-laravel/node_modules
sudo chown -R tomas:tomas apps/backoffice-laravel/public/build
npm run build
```

**Lección:** si `npm run build` falla con `EACCES` en la OPi, revisar primero el dueño de `node_modules/` y `public/build/` (`stat -c "%U:%G" <ruta>`) antes de investigar nada del código — es casi siempre este problema de permisos, no un error de Vite.

---

## NT-018 — App móvil: `loadOccupied` no expandía el rango ocupado según `duration_minutes`

| Campo | Valor |
|---|---|
| **Fecha** | 2026-07-03 |
| **Severidad** | P2 — Alto (permite doble-agendar al mismo operador desde la app móvil) |
| **Componente** | `mob_apps/operador/src/admin/MobCitaNueva.tsx` — función `loadOccupied` |
| **Estado** | ✅ RESUELTO |

**Síntoma:** al agendar una cita para un operador que ya tenía otra cita de 1.5h, el grid de horarios solo mostraba bloqueado el slot exacto de inicio de la cita existente (ej. 10:30), dejando los siguientes slots que esa cita ya ocupa (11:00, 11:30) como disponibles para agendar.

**Causa raíz:** `loadOccupied` (línea ~139) construía el `Set` de horarios ocupados con `bookings.map(b => b.time.slice(0, 5))` — un solo elemento por cita existente, ignorando por completo `duration_minutes`/`end_time` que el backend (`Api\AgendaController::index`) ya devuelve correctamente. `isSlotInvalid` sí expande correctamente la duración de la cita **nueva** que se está creando, pero consultaba contra un `Set` que nunca contenía el rango completo de las citas **ya agendadas**. No es un bug introducido por BL-025 — ya existía; se hizo más visible al volverse el operador obligatorio en ese sprint.

**Solución:** `loadOccupied` ahora expande cada cita existente a todos los slots de 30 min que cubre (`buildSlots(startMin, endMin)`, usando `duration_minutes`/`end_time` de la respuesta) antes de agregarlos al `Set`.

**Lección:** cuando un grid de disponibilidad consulta "¿está ocupado este slot?", verificar que el cálculo de "ocupado" considere la duración completa de cada evento existente, no solo su hora de inicio — es un patrón fácil de omitir y el bug pasa desapercibido hasta que dos citas se solapan en horarios no evidentes a simple vista.

---

## NT-017 — No usar `disabled` nativo para bloquear campos con Flatpickr (`altInput`)

| Campo | Valor |
|---|---|
| **Fecha** | 2026-07-02 |
| **Severidad** | P3 — El campo de hora en "Programar servicio" no se habilitaba al elegir operador |
| **Componente** | `resources/views/agenda/create.blade.php`/`edit.blade.php` + Flatpickr (`altInput: true`) |
| **Estado** | ✅ RESUELTO |

**Causa raíz (fragilidad, no un bug puntual):** con `altInput: true`, Flatpickr crea un input visible nuevo (`self.altInput`) y copia `disabled` del input original **solo una vez, en su inicialización** (`self.altInput.disabled = self.input.disabled` en `flatpickr.js`). Alternar `disabled` en el input original *después* de que Flatpickr ya inicializó no garantiza que el campo visible (`altInput`) refleje el cambio de forma confiable, y localizarlo vía `nextElementSibling` para sincronizarlo a mano es frágil frente al timing de carga de módulos.

**Solución:** para gatear la interactividad de un campo con Flatpickr, envolverlo en un `<div>` contenedor y alternar una clase CSS (`opacity` + `pointer-events: none`) en el wrapper, en vez de tocar `disabled` en el input. No depende de cómo Flatpickr arma su DOM interno.

**Lección:** con cualquier librería que clona/envuelve un `<input>` (Flatpickr, Select2, etc.), evitar alternar atributos nativos (`disabled`, `readonly`) después de la inicialización — preferir gatear la interacción a nivel de un contenedor CSS.

---

## NT-012 — Docker bind mount pierde enlace cuando se elimina y recrea el directorio fuente

| Campo | Valor |
|---|---|
| **Fecha** | 2026-06-30 |
| **Severidad** | P1 — Crítico (nginx 500, app móvil inaccesible) |
| **Componente** | Contenedor `estetican_mob` — bind mount `mob_apps/operador/dist/` → `/usr/share/nginx/html/` |
| **Impacto** | nginx sirve directorio vacío → `index.html` no existe → bucle de redirección interna → HTTP 500 en toda la app |
| **Estado** | ✅ RESUELTO |

**Síntoma:**
Después de ejecutar `rm -rf dist && npm run build` para un rebuild limpio, `mov.estetican.org` devuelve HTTP 500. El log de nginx muestra:
```
rewrite or internal redirection cycle while internally redirecting to "/index.html"
```
`docker exec estetican_mob ls /usr/share/nginx/html/` devuelve vacío.

**Causa raíz:**
Docker bind mounts con propagación `rprivate` (el default en Docker Compose) almacenan una referencia al **inodo** del directorio, no a la ruta. Al ejecutar `rm -rf dist`, el directorio se elimina y su inodo desaparece. `npm run build` crea un **nuevo** directorio `dist/` con un **inodo diferente**. El contenedor `estetican_mob` sigue apuntando al inodo anterior (ya borrado), por lo que ve el directorio montado como vacío aunque en el host existan los archivos nuevos.

**Workaround:**
```bash
docker restart estetican_mob
```
El restart re-establece el bind mount apuntando al nuevo inodo de `dist/`.

**Solución definitiva:**
**Nunca usar `rm -rf dist`** en directorios que son bind mounts de contenedores Docker. Para forzar un rebuild limpio, ejecutar directamente:
```bash
npm run build
```
Vite sobreescribe los archivos existentes sin eliminar el directorio raíz, preservando el inodo.

**Lección:**
- `rm -rf <directorio>` + `mkdir <directorio>` = nuevo inodo = bind mount huérfano en todos los contenedores que montan ese directorio.
- Para invalidar caché de build: usar flags de Vite (`--force`) o borrar solo el contenido interno (`rm -rf dist/*`), nunca el directorio en sí.
- Verificar bind mount vacío: `docker exec <container> ls /ruta/montada/` inmediatamente si nginx devuelve 500.

---

## NT-011 — `spa_bookings` no tiene `branch_id`: cobros no filtrables por sucursal

| Campo | Valor |
|---|---|
| **Fecha** | 2026-06-16 |
| **Severidad** | P4 — Bajo (limitación de modelo, no bug en producción) |
| **Componente** | `CashController::movements()` — endpoint `GET /api/cash/movements` |
| **Impacto** | El balance de movimientos de caja en la app móvil muestra cobros de todas las sucursales, no solo de la sucursal del checkin activo |

**Síntoma:**
Al intentar filtrar `payments`, `cash_ledgers` y `bank_ledgers` por sucursal del operador, cualquier query que use `spa_bookings.branch_id` falla con `Unknown column 'branch_id' in 'where clause'`.

**Causa raíz:**
La tabla `spa_bookings` no tiene columna `branch_id`. La sucursal de una cita SPA se infiere indirectamente (vía el operador asignado o el recurso asignado), pero no está desnormalizada en la tabla de citas. Las tablas de pagos (`payments`, `cash_ledgers`, `bank_ledgers`) son polimórficas apuntando a `spa_bookings` o `quotes`, por lo que no tienen branch directo tampoco.

**Workaround aplicado:**
Los movimientos manuales de caja (`CashMovement`) sí se filtran por sucursal vía `cash_sessions.branch_id`. Los cobros a clientes se muestran por período sin filtro de sucursal (aceptable porque el sistema actualmente opera en una sola sucursal).

**Solución definitiva (futura):**
Si se requiere filtrado multi-sucursal de cobros, agregar columna `branch_id` a `spa_bookings` (y potencialmente a `payments`). Crear migración y actualizar los servicios que crean bookings para poblar el campo.

**Lección:**
Antes de implementar filtros por sucursal en queries sobre `payments`/`cash_ledgers`/`bank_ledgers`, verificar que la cadena `payments → payable → branch_id` existe en el esquema. El modelo actual no lo soporta sin cambios de migración.

---

## NT-020 — `ExecutedService`/`ExecutedServiceItem` existen en el esquema pero están huérfanas — ningún flujo real las llena

| Campo | Valor |
|---|---|
| **Fecha** | 2026-07-07 |
| **Severidad** | P3 — Medio (feature nueva construida sobre datos inexistentes) |
| **Componente** | `App\Domain\Execution\Services\ExecutedServiceService` / tablas `executed_services`, `executed_service_items` |
| **Impacto** | La pantalla WhatsApp > Recurrencias (BL-029) se implementó leyendo de `executed_service_items` y no mostraba resultados aun después de cargar `services.recurrence_days`, pese a haber citas SPA completadas en producción |

**Síntoma:**
`App\Models\ExecutedServiceItem::count()` devuelve `0` en producción a pesar de que `spa_bookings` tiene registros con `status = 'completed'`.

**Causa raíz:**
`ExecutedServiceService::convertFromBooking()` (pensado para congelar un snapshot histórico de servicios ejecutados al completar una cita) nunca se conectó a ningún flujo real: no está bindeado en ningún `ServiceProvider`, ningún controlador la invoca, y las tres rutas reales que marcan una cita como `completed` (`SpaBookingController.php` — acción "Finalizar sesión", `Api/PaymentController.php` — cobro con `mark_completed`, `Api/BookingController.php` — update de estado desde la app móvil) solo hacen `$booking->update(['status' => 'completed'])` sin tocar `ExecutedService`. El modelo y las tablas quedaron como scaffolding de una funcionalidad que nunca se terminó de cablear.

**Dónde vive el historial real:**
`spa_bookings` (con `status = 'completed'`, usando `scheduled_at` como fecha) + `spa_booking_services` (qué servicios incluyó esa cita). Es la única fuente poblada hoy, aunque con una limitación: `spa_booking_services` refleja el set *actual* de servicios de la cita (se borra y recrea si se edita después de completada — ver `SpaBookingController::update`), por lo que un cambio posterior a la finalización podría perder el detalle histórico exacto.

**Solución aplicada:**
`RecurrenceMessageController::lastServiceDatesByPet()` se corrigió para leer de `spa_booking_services` JOIN `spa_bookings WHERE status = 'completed'` en vez de `executed_service_items`.

**Lección:**
Antes de construir cualquier feature nueva sobre `ExecutedService`/`ExecutedServiceItem`/`App\Domain\Execution`, verificar primero si tienen filas reales en producción (`ExecutedServiceItem::count()`). Si se requiere un snapshot histórico inmutable (que no cambie si se edita una cita ya completada), la solución de fondo es cablear `ExecutedServiceService::convertFromBooking()` en los tres puntos de transición a `completed` — pendiente, no resuelto en esta sesión.

## NT-021 — `Api\AgendaController`: campo `operators` ignoraba `spa_bookings.operator_id`, citas sin presupuesto aceptado desaparecían al filtrar por operador

| Campo | Valor |
|---|---|
| **Fecha** | 2026-07-07 |
| **Severidad** | P2 — Alto (citas reales invisibles al filtrar, sin error visible) |
| **Componente** | `App\Http\Controllers\Api\AgendaController::index()` — endpoint `/api/agenda` |
| **Impacto** | App móvil, pantallas Agenda Universal (`MobAgGbl`) y Agenda por operador (`MobOpAg`): al seleccionar un operador en el filtro, citas recién creadas (sin presupuesto aceptado todavía) desaparecían de la lista aunque tuvieran ese operador asignado |

**Síntoma:** usuario reporta que al filtrar por operador en `MobAgGbl` no salen todas las citas de ese operador.

**Causa raíz:** el campo `operators` que devuelve `/api/agenda` (usado por el filtro client-side en `GlobalAgenda.tsx` y `GroomerAgenda.tsx` vía `b.operators.some(o => o.id === filterOp)`) se calculaba **únicamente** a partir de las líneas del presupuesto aceptado (`quotes.items.operator`), ignorando `spa_bookings.operator_id` — la columna que sí se asigna directamente al crear la cita (`MobCitaNueva.tsx`). Si la cita todavía no tenía presupuesto aceptado, `operators` quedaba `[]` y el filtro la excluía siempre, sin importar el operador asignado.

**Solución aplicada:** `AgendaController::index()` ahora arma `operators` como la unión de (a) los operadores del presupuesto aceptado y (b) el operador asignado directamente vía `operator_id` (relación `SpaBooking::operator()`), deduplicados por id. El filtro client-side en la app móvil no cambió — solo recibía datos incompletos.

**Lección:** cuando un mismo concepto ("operador de esta cita") tiene dos fuentes posibles en el modelo de datos (columna directa vs. derivado de otra entidad relacionada), cualquier endpoint que exponga ese concepto debe unir ambas fuentes explícitamente — no asumir que una sola cubre todos los casos del ciclo de vida (una cita pasa por "sin presupuesto" antes de llegar a "con presupuesto aceptado").

## NT-022 — CSP bloqueaba silenciosamente las teselas de OpenStreetMap (mapa en blanco en `AX-MAPZN`)

| Campo | Valor |
|---|---|
| **Fecha** | 2026-07-07 |
| **Severidad** | P2 — Alto (pantalla nueva inutilizable, sin error visible en la UI) |
| **Componente** | `App\Http\Middleware\ContentSecurityPolicy.php` |
| **Impacto** | Pantalla `AX-MAPZN` (Mapa y cobertura espacial) — el mapa de Leaflet cargaba pero las teselas de OpenStreetMap nunca se veían (fondo gris/blanco permanente) |

**Síntoma:** usuario reporta "no carga el mapa" al probar `/mapa-zonas` por primera vez.

**Causa raíz:** la directiva CSP `img-src 'self' data: blob:` (ver NT-006, agregada 2026-05-25 junto con el resto de la política) solo permite imágenes del propio origen — bloquea sin aviso visible las peticiones de `<img>` que hace Leaflet hacia `https://{a,b,c}.tile.openstreetmap.org/...`. El navegador descarta la petición silenciosamente (violación de CSP, no un error de red), así que el mapa se ve simplemente vacío/gris sin ningún mensaje de error en consola visible para un usuario no técnico. De paso se detectó que `connect-src 'self'` también bloqueaba (desde que existe la CSP) el `fetch()` que ya hacía `address-editor.js` hacia `https://nominatim.openstreetmap.org` para la "Geocodificación automática" — esa función llevaba tiempo silenciosamente rota sin que nadie lo hubiera reportado.

**Solución aplicada:**
```php
"img-src 'self' data: blob: https://*.tile.openstreetmap.org",
"connect-src 'self' https://nominatim.openstreetmap.org",
```

**Lección:** cualquier integración nueva con un servicio externo (tiles, geocodificación, APIs de terceros) debe agregarse explícitamente a la CSP — no basta con que el código JS esté bien escrito, el navegador la bloquea de forma completamente silenciosa (sin excepción JS, sin respuesta HTTP fallida visible) si el dominio no está en la whitelist. Al agregar cualquier `fetch()`/`<img>`/`<script>` hacia un dominio externo nuevo, verificar primero `app/Http/Middleware/ContentSecurityPolicy.php`.

---

## NT-023 — `users.is_operator`/`operator_code` existían en producción sin migración que los creara (schema drift)

| Campo | Valor |
|---|---|
| **Fecha** | 2026-07-10 |
| **Severidad** | P2 — Alto (rompe tests y cualquier entorno nuevo/`migrate:fresh`, sin afectar producción ya migrada) |
| **Componente** | `app/Models/User.php`, tabla `users` |
| **Impacto** | Base de datos `testing` (usada por PHPUnit) y cualquier instalación nueva — `INSERT`/`Schema` fallan con `Unknown column 'is_operator'` |

**Síntoma:** al escribir tests para el endpoint `/api/team` (BL-033), crear un `User` con `is_operator`/`operator_id` fallaba con `SQLSTATE[42S22]: Column not found: 1054 Unknown column 'is_operator'` contra la base `testing`, pese a que el modelo `User` las declara en `$fillable` y se usan activamente en producción (`Api\CheckinController`, filtros de operador).

**Causa raíz:** la base de datos de producción (`estetican`) sí tiene las columnas `is_operator` (`tinyint(1) not null default 0`) y `operator_code` (`varchar(255) unique nullable`), pero **ninguna migración del repositorio las crea** — `grep -rl "is_operator" database/migrations/` no devuelve nada. Se agregaron en algún momento directo contra producción (probablemente vía `Schema::` ad-hoc en `tinker`, ver patrón de riesgo ya documentado en la sesión de BITACORA del 07/07/2026 sobre `tinker` sin transacción explícita) sin dejar el migration file correspondiente commiteado. La base `testing` nunca las tuvo porque solo corre las migraciones versionadas.

**Solución aplicada:** migración nueva `2026_07_10_000001_add_is_operator_and_operator_code_to_users_table.php`, idempotente (`Schema::hasColumn` guard, mismo patrón que `2026_03_28_000001_add_operator_fields_to_users_table.php`) — no-op confirmado en producción (`php artisan migrate --force` no alteró nada), aplicó limpio en `testing`.

**Lección:** cualquier cambio de esquema debe pasar por una migración commiteada, nunca por `Schema::`/`DB::statement` suelto en `tinker` contra producción — si se hace por urgencia, hay que escribir el migration file (con guard `hasColumn`) en la misma sesión, o el drift queda invisible hasta que algo (como un test nuevo) lo destapa. Si un test falla con `Unknown column` en un campo que sí existe en producción, sospechar de esto antes que de un bug de test.

---

## NT-024 — Test de `TeamPanelTest` fallaba solo después de las ~22:00 (medianoche cruzada en fixtures con `now()->addHours()`)

| Campo | Valor |
|---|---|
| **Fecha** | 2026-07-10 |
| **Severidad** | P3 — Bajo (solo afecta la suite de tests, no producción) |
| **Componente** | `tests/Feature/Api/TeamPanelTest.php` |
| **Impacto** | Falla intermitente de un test según la hora real del día en que corre la suite — no relacionado a ningún cambio de código real |

**Síntoma:** la suite completa pasó de 37 a 38 fallidas entre dos corridas de la misma sesión, sin ningún cambio de código backend entre medio. El test que empezó a fallar (`operator with active checkin and open work order reports current job`) esperaba `pending_today = 2`, recibió `1`.

**Causa raíz:** el fixture del test crea una `SpaBooking` con `scheduled_at => now()->addHours(2)` para simular "una cita de hoy más tarde". La suite corrió pasadas las 22:00 (hora del contenedor) — `now()->addHours(2)` cruzó medianoche y cayó en el día siguiente, quedando fuera del rango `whereBetween($today, $tomorrow)` que usa `Api\OperatorController::team()` para calcular "hoy". El código de producción está bien; el test es el que no contempló que un offset relativo puede cruzar el límite del día según la hora real de ejecución.

**Solución aplicada:** `$this->travelTo(now()->setTime(12, 0))` al inicio del test — ancla el reloj a mediodía antes de crear los fixtures, así ningún `now()->addHours()`/`subHours()` dentro del rango usado por el test cruza medianoche sin importar a qué hora real corra la suite.

**Lección:** cualquier test que use `now()->addHours(N)`/`subHours(N)` para fixtures de "hoy" es potencialmente frágil según la hora de ejecución — anclar el reloj con `$this->travelTo()`/`Carbon::setTestNow()` a una hora segura (ej. mediodía) al inicio del test, no confiar en la hora real del entorno. Si una suite que antes pasaba limpio empieza a fallar sin cambios de código, revisar primero si el test depende de la hora del día antes de sospechar una regresión real.

---

## NT-025 — Colores ilegibles en `LockScreen` en Android Chrome con tema oscuro — falta `color-scheme` (probable "Auto Dark Theme" de Chrome)

| Campo | Valor |
|---|---|
| **Fecha** | 2026-07-11 |
| **Severidad** | P2 — Alto (pantalla nueva difícil/imposible de usar en el navegador más común, Android Chrome) |
| **Componente** | `mob_apps/operador/index.html`, `src/index.css` |
| **Impacto** | Cualquier pantalla de la app móvil, no solo `LockScreen` — pero se detectó ahí primero |

**Síntoma:** usuario reportó que en `LockScreen` (Android, Chrome, tema oscuro) el campo de contraseña se veía como "un cuadro vacío" y el botón "Desbloquear" solo se alcanzaba a leer "Des..." sin poder distinguir el resto del texto.

**Diagnóstico (sin poder ver la pantalla — no hay herramienta de automatización de navegador ni acceso al dispositivo en este entorno):** se verificó primero que el build servido coincidía con el último compilado (no era caché vieja) y se inspeccionó el CSS generado — las variables de color de `.theme-admin`/`:root.dark .theme-admin` y las clases `bg-primary`/`text-on-primary`/`z-[60]` estaban bien compiladas, con buen contraste en ambos temas. Con el código descartado como causa directa, y confirmado Android + Chrome + tema oscuro activo, la sospecha más fuerte es **"Auto Dark Theme" de Chrome para Android** — una función del navegador (no relacionada a `prefers-color-scheme`) que reinvierte heurísticamente los colores de páginas que no declaran explícitamente que ya manejan su propio modo oscuro. Sin la declaración `color-scheme`, Chrome puede "corregir" colores que ya son correctos, produciendo combinaciones rotas (texto casi invisible sobre su propio fondo) — coincide con los síntomas reportados. El proyecto nunca declaró `color-scheme` en ningún lado.

**Solución aplicada:** `<meta name="color-scheme" content="light dark">` en `index.html` + `:root { color-scheme: light; } :root.dark { color-scheme: dark; }` en `index.css`, sincronizado con la misma clase `.dark` que ya controla la paleta (ver BL-034/BL-038). Esto le dice al navegador explícitamente que la página ya maneja ambos temas — no debería seguir "corrigiendo" colores por su cuenta.

**Sin confirmar:** es un diagnóstico basado en evidencia indirecta (código descartado + patrón de síntomas + navegador/SO reportados), no una reproducción directa — pendiente que el usuario confirme si el fix resuelve el problema real en su teléfono.

**Lección:** cualquier app web con modo oscuro propio (paletas vía clase CSS, no solo `prefers-color-scheme`) debe declarar `color-scheme` explícitamente — sin eso, Chrome para Android (y en menor medida otros navegadores con "forzar oscuro") puede reinterpretar y romper colores que el código ya calculó correctamente. Si un bug de color reportado por un usuario no aparece en el CSS/código fuente, sospechar de un mecanismo del navegador antes que de la lógica de la app.

---

## NT-026 — Utilidades `max-w-xs`/`max-w-sm` de Tailwind resolvían a 4px/8px por colisión con tokens custom `--spacing-*`

| Campo | Valor |
|---|---|
| **Fecha** | 2026-07-12 |
| **Severidad** | P3 — Medio (rompe layout visualmente, sin afectar datos ni funcionalidad) |
| **Componente** | `mob_apps/operador/src/index.css` (tokens `@theme`), cualquier uso de `max-w-xs`/`max-w-sm`/`max-w-md`/`max-w-lg`/`max-w-xl` en el proyecto |
| **Impacto** | `LockScreen.tsx` — campo de contraseña y botón "Desbloquear" se veían diminutos (más chicos que el texto "Cerrar Sesión") |

**Síntoma:** el usuario reportó el campo de contraseña y el botón "Desbloquear" de `LockScreen` como demasiado chicos y no centrados, incluso después de dos rondas de ajuste de clases Tailwind (`w-full`, `flex items-center justify-center`, `max-w-xs`→`max-w-sm`) y de confirmar que el build nuevo sí estaba desplegado (mismo hash de asset en el contenedor `estetican_mob` y en lo que respondía `mov.estetican.org` en vivo).

**Causa raíz:** `index.css` define en el bloque `@theme` tokens custom de espaciado con nombres cortos (`--spacing-xs: 4px`, `--spacing-sm: 8px`, `--spacing-md: 16px`, etc., pensados para utilidades como `gap-md`/`p-lg`). En Tailwind v4, `w-*`, `max-w-*`, `min-w-*`, `p-*`, `m-*`, `gap-*` etc. comparten el mismo namespace de resolución de la escala de espaciado por nombre de clave — al existir una clave custom `sm`/`xs` en `--spacing-*`, esa definición **shadowea** la escala por defecto que normalmente usan `max-w-sm`/`max-w-xs` (que en Tailwind stock son ~384px/320px). Se confirmó inspeccionando el CSS compilado: `.max-w-sm{max-width:var(--spacing-sm)}` → 8px real, no 384px.

**Solución aplicada:** en `LockScreen.tsx`, reemplazar `max-w-xs`/`max-w-sm` por valores arbitrarios explícitos que no pasan por el lookup de tema — `max-w-[24rem]` (384px) y `max-w-[20rem]` (320px) — que no colisionan con ningún token custom.

**Sin resolver a nivel de proyecto:** el resto del código de `mob_apps/operador` no usa `max-w-{xs,sm,md,lg,xl}` (verificado por grep), así que no hay otras instancias rotas hoy — pero la colisión sigue latente para cualquier uso futuro de esas clases mientras existan los tokens `--spacing-xs`/`--spacing-sm`/etc. en `@theme`. No se renombraron los tokens custom en esta sesión (hubiera sido un cambio más amplio, fuera del alcance del bug reportado).

**Lección:** en Tailwind v4, agregar tokens custom en `@theme` con nombres cortos genéricos (`xs`, `sm`, `md`, `lg`, `xl`) es peligroso porque esos nombres son compartidos por **todas** las utilidades de la familia de espaciado (ancho, alto, padding, margin, gap), no solo la utilidad para la que se pensaron. Si el layout de un componente se ve roto de forma que no tiene sentido con las clases escritas (por ejemplo, un `max-w-sm` que se ve más chico que el contenido sin restricción), inspeccionar el CSS **compilado** de esa clase específica antes de seguir ajustando clases a ciegas — el bug puede estar en la resolución del token, no en la clase usada.

---

## NT-027 — El candado de sesión (`AppLockContext`) se perdía en cada reload por una carrera con la carga asíncrona de `useAuth()`

| Campo | Valor |
|---|---|
| **Fecha** | 2026-07-12 |
| **Severidad** | P1 — Crítico (bypassea por completo el candado de sesión, BL-038) |
| **Componente** | `mob_apps/operador/src/AppLockContext.tsx`, `src/AuthContext.tsx` |
| **Impacto** | Cualquier reload de página, o "atrás" del navegador cuando implicaba una navegación real, mostraba la app desbloqueada sin importar que estuviera bloqueada manual o automáticamente |

**Síntoma:** el usuario reportó que bloquear la sesión (manual o por timeout) no servía de nada si después recargaba la página o usaba "atrás" — volvía directo a la pantalla desbloqueada.

**Causa raíz:** un primer intento de fix persistió `locked` en `localStorage` y lo leía en un lazy initializer de `useState` condicionado a `enabled` (`!!user`). Pero `useAuth()` resuelve la sesión de forma asíncrona (`fetch('/api/me')` tras leer el token de `localStorage`, ver `AuthContext.tsx:59-76`) — en el primer render de cada reload, `user` todavía es `null`, así que `enabled` es `false` y el initializer devuelve `false` sin importar lo persistido. Peor: el `useEffect` que reacciona a `enabled` trataba "todavía no sé si hay sesión" (`loading = true`) igual que "confirmado que no hay sesión" (`loading = false, user = null`) — en ambos casos entraba a la misma rama, que llamaba `clearStoredState()`, **borrando** el estado de bloqueo guardado antes de que hubiera oportunidad de usarlo cuando `enabled` pasara a `true` más tarde.

**Solución aplicada:** se expone `loading` desde `useAuth()` y se usa para no tocar nada (ni el estado en memoria ni lo persistido) mientras la sesión todavía se está resolviendo. Una vez que `loading` es `false`, si hay sesión (`enabled`) se re-sincroniza `locked` explícitamente desde `localStorage` (no se confía en el valor del lazy initializer, que quedó fijado en el primer render); si no hay sesión, recién ahí se limpia el estado persistido.

**Lección:** un lazy initializer de `useState` solo corre **una vez**, en el primer render — si depende de un valor que llega de forma asíncrona (como el usuario autenticado), va a quedar con un valor obsoleto para siempre a menos que algo lo vuelva a calcular explícitamente cuando esa dependencia async se resuelve. Cualquier estado derivado de `useAuth()` en este proyecto debe distinguir `loading` de "confirmado sin sesión" — son casos distintos, no el mismo `else`.

---

## NT-028 — Escribir en el campo de contraseña de `LockScreen` marcaba la sesión como desbloqueada en el storage persistido, sin haber terminado de desbloquear

| Campo | Valor |
|---|---|
| **Fecha** | 2026-07-12 |
| **Severidad** | P2 — Alto (bypass parcial del candado, BL-038, encontrado durante la verificación de NT-027) |
| **Componente** | `mob_apps/operador/src/AppLockContext.tsx` |
| **Impacto** | Recargar la página a mitad de escribir la contraseña en `LockScreen` (sin llegar a tocar "Desbloquear") mostraba la app desbloqueada en el siguiente reload |

**Síntoma:** encontrado mientras se verificaba el fix de NT-027 — bloquear, empezar a escribir la contraseña sin terminar, y recargar, mostraba la app sin candado.

**Causa raíz:** los listeners de actividad (`touchstart`, `mousedown`, `keydown`, `scroll`) están registrados en `document` con burbujeo, así que también se disparan por los toques/tecleo **dentro de la propia pantalla de bloqueo** (escribir en el campo de contraseña, tocar el botón "Desbloquear"). Cada uno de esos eventos llama `resetTimer()`, que escribía `{ locked: false, ... }` en `localStorage` de forma incondicional (con throttle de 5s) — sin verificar si en ese momento la app seguía realmente bloqueada.

**Solución aplicada:** se agregó `lockedRef` (un `useRef` sincronizado con el estado `locked` real) y `resetTimer()` ahora omite el guardado en `localStorage` si `lockedRef.current` es `true`. La programación del timeout de auto-bloqueo de 5 min no se ve afectada, solo el guardado indebido del flag `locked: false`.

**Lección:** cuando una pantalla de overlay/candado convive con listeners globales de "actividad reciente" en `document`, esos listeners no distinguen por defecto si el toque/tecla ocurrió dentro del overlay o en la app real debajo — hay que guardar el estado "verdadero" actual en algo legible de forma síncrona (un ref, no el estado de React que se actualiza async) para poder filtrar esos casos dentro del mismo handler.

---

## NT-030 — Botón "Probar Conexión" SMTP daba 404 por el spoofing de método (`_method=PUT`) del formulario que lo contenía

| Campo | Valor |
|---|---|
| **Fecha** | 2026-07-12 |
| **Severidad** | P2 — Alto (imposible probar la configuración SMTP desde la UI) |
| **Componente** | `resources/views/system-settings/index.blade.php` |
| **Impacto** | Solo el botón de prueba SMTP — el guardado normal de la sección no se ve afectado |

**Síntoma:** el usuario reportó `404 Not Found` en `POST https://app.estetican.org/system-settings/smtp-test` al usar el botón "Probar Conexión" en Configuración del sistema → Servicio de Correo.

**Causa raíz:** el formulario de cada sección de settings tiene `method="POST"` + `@method('PUT')` (un input oculto `_method=PUT`, la forma estándar de Laravel de simular verbos HTTP no soportados nativamente por los formularios HTML) para el submit normal hacia `system-settings.update`. El JS del botón de prueba (`data-test-smtp`) solo cambiaba `form.action` a la ruta de prueba y llamaba `form.submit()` — sin tocar el campo `_method`. Laravel interpreta cualquier POST con `_method=PUT` como un PUT real, así que la petición terminaba enrutada a la ruta `PUT /system-settings/{section}` (que coincide con el patrón `/system-settings/smtp-test`, tomando `smtp-test` como valor de `{section}`) en vez de a la ruta real `POST /system-settings/smtp-test`. Dentro de `SystemSettingController::update()`, `abort_unless($systemSettings->hasSection('smtp-test'), 404)` — como `smtp-test` no es una sección real, tronaba 404. La ruta de prueba en sí nunca se ejecutaba.

**Solución aplicada:** antes de enviar el formulario hacia la ruta de prueba, se deshabilita (`disabled = true`) el input `_method` — los campos deshabilitados no se incluyen en el envío del formulario, así que el POST llega sin spoofing y Laravel lo enruta correctamente a `testSmtp()`. Se rehabilita después por si el envío no navega fuera de la página.

**Lección:** cualquier botón que reutilice un `<form>` existente para pegarle a una ruta *distinta* de la que ese formulario fue armado para servir debe revisar **todos** los campos ocultos del formulario (no solo `action`) — un `_method` de spoofing es fácil de pasar por alto porque no aparece en ningún lado visible de la UI, y cambia silenciosamente a qué ruta llega la petición real.

**Adenda (mismo día) — el campo "Encriptación" ofrecía valores que Symfony Mailer rechaza:** al arreglar el 404 de arriba, el usuario probó el envío real y encontró un segundo bug en la misma área: `UnsupportedSchemeException: The "ssl" scheme is not supported; supported schemes for mailer "smtp" are: "smtp", "smtps"`. El campo `mail_encryption` (agregado en la misma sesión, ver la sección de correo por plantilla) ofrecía `ssl`/`tls`/`''` como opciones — pero el transporte SMTP de Symfony Mailer (el que usa Laravel por debajo) solo reconoce literalmente `smtp` o `smtps` como valor de `scheme`, no `ssl`/`tls`. El `.env` de producción también traía `MAIL_SCHEME=ssl` desde antes, heredado y nunca antes ejercitado con éxito. Corregido: opciones del campo reducidas a `smtp`/`smtps`, con `smtps` (TLS implícito, puerto 465) como default cuando el valor existente no es válido.

**Adenda 2 — el valor inválido ya guardado en BD no se autocorregía:** el usuario ya había guardado `ssl` en un intento anterior (antes del fix de opciones) — `SystemSettings::castValue()` no validaba un valor `select` leído de la BD contra las opciones vigentes del campo, así que seguía devolviendo `ssl` indefinidamente aunque esa opción ya no existiera. Se agregó un resguardo genérico: si el valor guardado de un campo `select` no está entre sus `options` actuales, se usa el `default` en su lugar — protege a **cualquier** campo `select` del proyecto ante este mismo patrón (opciones que cambian de versión en versión), no solo a este.

**Adenda 3 — verificar vía `tinker` con `Mail::raw()` directo da falso negativo:** al confirmar el fix, un primer intento de enviar un correo de prueba vía `php artisan tinker` volvió a fallar con el mismo error de scheme — pero **no** era el mismo bug: los comandos de consola (`artisan`, `tinker`) no pasan por el pipeline de middleware HTTP, así que `ApplySystemSettings` (que aplica `SystemSettings::configOverrides()` a la config de Laravel) nunca corre — la config de mail en ese contexto refleja solo `.env`, no lo guardado en `system_settings`. El envío real desde el navegador (Bandeja Diaria, Recurrencias, resumen de servicio) sí pasa por ese middleware y sí ve la config correcta; confirmado aplicando `config(app(SystemSettings::class)->configOverrides())` manualmente antes de reintentar en tinker. **Implicación a futuro:** cualquier envío de correo disparado desde un comando artisan, job en cola, o cron (nada de esto existe todavía) necesitaría aplicar el bridge manualmente — no puede asumir que la config de mail ya está lista solo por leer `SystemSettings`.

**Lección (adendas):** (1) cuando se agregan opciones a un campo `select` de configuración, verificar los valores exactos que espera la librería subyacente (no asumir nombres "obvios" como `ssl`/`tls`) — la fuente de verdad es el mensaje de error de la librería, no la intuición; (2) cualquier sistema de configuración basado en `select` con opciones versionables necesita un resguardo contra valores obsoletos guardados antes del cambio; (3) verificar código dependiente de middleware HTTP (como bridges de configuración) usando una request real o aplicando el middleware manualmente — `tinker`/`artisan` no son un sustituto fiel del pipeline de request real.

---

## NT-031 — CORS dinámico (BL-042): un middleware de ruta nunca ve el preflight `OPTIONS`, y el `HandleCors` por defecto de Laravel pisa cualquier middleware propio si se registra después

| Campo | Valor |
|---|---|
| **Fecha** | 2026-07-13 |
| **Severidad** | P3 — solo afectaba al widget de IA nuevo (BL-042), en desarrollo, nunca llegó a producción con el bug |
| **Componente** | `app/Http/Middleware/HandleAssistantCors.php`, `bootstrap/app.php` |
| **Impacto** | Ninguno en producción — encontrado y corregido durante el desarrollo, antes de exponer el endpoint al sitio real |

**Contexto:** el widget de IA para el sitio WordPress necesita CORS restringido a un origen configurable desde `SystemSettings` (BD), no a un dominio fijo — así que no se podía usar `config/cors.php` tal cual (ver más abajo por qué). Se optó por un middleware propio, `HandleAssistantCors`, que lee `ai_assistant_allowed_origin` en cada request.

**Primer intento (fallido) — middleware de ruta:** se registró como middleware de las rutas `/api/assistant/*` (`Route::middleware([...])`). Un test de preflight (`OPTIONS /api/assistant/chat`) nunca lo ejecutaba. Causa raíz: `Route::get`/`Route::post` solo registran los métodos indicados; Laravel resuelve el método de la petición **antes** de correr el middleware de la ruta (la resolución de rutas ocurre dentro del despacho al router, que es interno a la cadena de middleware globales, no al revés) — como no había ninguna ruta `OPTIONS` registrada para esa URI, el framework nunca llegaba a instanciar el middleware de esa ruta.

**Fix:** mover `HandleAssistantCors` a middleware **global** (`$middleware->append()`/`prepend()` en `bootstrap/app.php`, igual que `ContentSecurityPolicy`/`ApplySystemSettings`/`ProfileBackofficeRequests` ya existentes) — el middleware global envuelve toda la petición, incluida la resolución de rutas, así que sí ve el `OPTIONS` antes de que el router lo rechace. Acotado por path a mano dentro del propio middleware (`$request->is('api/assistant/*')`) para no afectar el resto de la app.

**Segundo bug, tras el fix anterior — el header salía como `*` en vez del origen configurado:** con el middleware ya global vía `append()`, el test seguía fallando, pero con `Access-Control-Allow-Origin: *` en vez de ausente. Causa: Laravel trae su propio `Illuminate\Http\Middleware\HandleCors` activo por defecto en el stack global del framework (con `config/cors.php` no publicado, usa el default de fábrica — `allowed_origins => ['*']`), y ese middleware corre **antes** que cualquiera agregado con `append()` (que se suman al final de la lista de middleware globales ya existentes, incluidos los de fábrica). El `HandleCors` de Laravel interceptaba el `OPTIONS` primero y respondía con su propio `*`, sin que `HandleAssistantCors` llegara a ejecutarse.

**Fix final:** usar `$middleware->prepend(HandleAssistantCors::class)` en vez de `append()` — así corre primero, intercepta el `OPTIONS` de las rutas `/api/assistant/*` y responde antes de que el `HandleCors` de fábrica tenga oportunidad.

**Por qué no `config/cors.php`:** los archivos de config de Laravel se cargan durante el bootstrap (`LoadConfiguration`), que ocurre **antes** de que se registren los proveedores de servicio (incluidos los de base de datos y caché). Leer `SystemSettings` (que consulta la tabla `system_settings` vía Eloquent, con caché) dentro de un archivo de config es un patrón fràgil que puede fallar según el momento exacto en que Laravel resuelve ese archivo — por eso el origen dinámico se resolvió con un middleware (que corre después del boot completo, con toda la app ya lista) en vez de intentar inyectar el valor en `config('cors.*')`.

**Lección:** (1) el middleware de CORS que necesita interceptar preflight `OPTIONS` **debe** ser global, nunca de ruta — la ruta todavía no existe (en términos de método HTTP) cuando el preflight llega; (2) `append()` sitúa el middleware **después** de los que ya trae Laravel por defecto (incluido `HandleCors`) — si el objetivo es que el propio middleware gane la carrera para responder primero, hace falta `prepend()`; (3) cualquier valor de configuración que dependa de la base de datos (vía `SystemSettings` u otro mecanismo similar) no debe leerse desde un archivo de `config/*.php` — el momento de carga de esos archivos no garantiza que la BD/caché ya estén disponibles.

---

## NT-032 — Widget del asistente (BL-042 Fase 2) invisible en `estetican.org`: el editor de WordPress corrompe JavaScript inline vía `wpautop`

| Campo | Valor |
|---|---|
| **Fecha** | 14/07/2026 |
| **Severidad** | P2 — Alto (el widget completo no funcionaba, sin ningún error visible para el usuario) |
| **Componente** | Contenido del sitio WordPress (`estetican.org`, fuera de este repo) — no es un bug de `apps/backoffice-laravel` |
| **Impacto** | Solo el widget del asistente; el resto del sitio no se vio afectado |

**Síntoma:** tras pegar el HTML/CSS/JS del widget en los tres bloques separados del editor de WordPress y publicar, el botón flotante del asistente nunca aparecía — sin ningún error visible para el usuario final.

**Diagnóstico:** se descargó el HTML real servido por `https://estetican.org/` (`curl`) para inspeccionar qué había llegado realmente a producción, en vez de asumir. Se encontró que el contenido del bloque JS llegaba envuelto así:
```html
<p><script data-wp-block-html="js">
(function () { ... })();</p>
<p></script></p>
```
El editor de WordPress usado en este sitio le aplica `wpautop()` (el filtro que WordPress usa para convertir saltos de línea en párrafos `<p>`, pensado para contenido de blog, no para código) al contenido **crudo** de cada campo **antes** de envolverlo en su etiqueta (`<script>`/`<style>`) — no después. El `wpautop()` nativo de WordPress sí protege el contenido dentro de `<script>`/`<style>` ya existente (lo reemplaza por un placeholder antes de fragmentar en párrafos, y lo restaura después) — pero esa protección no aplica acá porque las etiquetas `<script>`/`<style>` **todavía no existen** en el momento en que se ejecuta el filtro sobre el campo individual.

**Primer intento (insuficiente):** eliminar todas las líneas en blanco del HTML/CSS/JS (`wpautop` inserta `<p>`/`</p>` en cada salto de línea doble). Esto arregló la corrupción **interna** (antes partía funciones a la mitad), pero `wpautop` de todas formas envuelve **cualquier bloque de texto sin líneas en blanco** en un único `<p>` al principio y `</p>` al final — porque su algoritmo siempre trata contenido "suelto" (que no empieza con una etiqueta de bloque reconocida) como un párrafo, tenga o no separadores internos. El resultado: `<p>(function () {` al inicio y `})();</p>` al final — el `</p>` quedaba **dentro** del texto del `<script>` (el parser HTML no corta el contenido de `<script>` hasta encontrar `</script>` literal), y el motor de JS tronaba con `SyntaxError: Unexpected token '<'` al toparse con esos caracteres.

**Fix definitivo:** sacar toda la lógica del campo JS del editor por completo. En su lugar, el bloque HTML incluye una única etiqueta `<script src="https://app.estetican.org/assistant-widget-wp.js" data-api-base="..." data-token="..." async></script>` **sin ningún texto entre la apertura y el cierre** — toda la configuración va en atributos `data-*`, no en el cuerpo. Como `wpautop` solo puede corromper *contenido de texto*, una etiqueta vacía con atributos es inmune sin importar cuántos `<p>` sueltos le agregue alrededor (son inofensivos fuera del cuerpo del script). La lógica real vive en `apps/backoffice-laravel/public/assistant-widget-wp.js` — un asset estático servido directo por Laravel, nunca pasa por el editor de WordPress ni por ningún filtro de contenido. El bloque JS del editor queda vacío.

**Lección:** (1) cuando un comportamiento en producción no tiene explicación obvia, bajar el HTML real servido (`curl`) y comparar contra lo que se pegó — no asumir que "se guardó tal cual"; los CMS con editores de contenido casi siempre tienen algún filtro de formato activo que puede alterar código pegado como si fuera texto; (2) quitar líneas en blanco reduce pero **no elimina** el riesgo de un filtro tipo `wpautop` — el único blindaje real contra corrupción de contenido de texto es no depender de contenido de texto: usar atributos de etiqueta (`data-*`, `src`) en vez de cuerpo inline cuando el canal de entrega no es 100% confiable; (3) mismo patrón que NT-031 en el fondo — cuando una plataforma externa (WordPress, Meta, lo que sea) no da garantías sobre cómo transporta contenido, diseñar para que ese contenido sea trivial (vacío o solo atributos) en vez de intentar que sobreviva intacto.
