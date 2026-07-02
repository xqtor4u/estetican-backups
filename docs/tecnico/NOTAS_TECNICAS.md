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

