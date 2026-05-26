# Sistema de Subida de Fotografías — Referencia Técnica

> Última actualización: 2026-05-25

## Componente central: `x-image-upload`

**Ubicación:** `resources/views/components/image-upload.blade.php`
**Lógica JS:** `resources/js/modules/image-upload.js`
**CSS Cropper:** `resources/css/vendor-cropper.css` (v1.6.2 — NO actualizar sin leer la nota crítica abajo)

### Props

| Prop | Default | Descripción |
|---|---|---|
| `name` | — | Nombre del campo `input[type=file]` en el form |
| `value` | null | Path relativo al storage público (para preview inicial) |
| `label` | `'Foto de perfil'` | Texto bajo el preview |
| `previewShape` | `'circle'` | `circle`, `square`, `rect` |
| `defaultIcon` | `'bi-person-fill'` | Icono Bootstrap cuando no hay foto |
| `maxWidth` | `'120px'` | CSS `max-width` del wrapper |
| `aspectRatio` | `1` | Proporción del recorte (1 = cuadrado, 4/3 = horizontal) |
| `formId` | null | Asocia el file input a un form externo (attr `form`) |
| `watermarkText` | null | Texto quemado en esquina inferior derecha con fecha |
| `autoSubmitFormId` | null | ID del form a auto-enviar al confirmar recorte |

### Flujo interno (JS)

1. Usuario hace click en el botón-cámara (label HTML nativo, sin JS)
2. Selecciona archivo → `fileChosen()` se ejecuta
3. `FileReader` lee el archivo como DataURL
4. Se registra el listener `shown.bs.modal` **antes** de asignar `img.src`
5. Se asigna `img.src = dataUrl` y se abre el modal Bootstrap
6. Al disparar `shown.bs.modal` → `requestAnimationFrame` → `new Cropper(img, options)`
7. Usuario recorta / gira
8. `applyCrop()` → `canvas.toBlob()` → carga el blob en el `<input>` vía `DataTransfer`
9. Dispatch del evento `image-cropped` con `{ name: inputName }`
10. Si hay `autoSubmitFormId` → busca el form y hace click en su botón submit

### Patrones de auto-submit en el sistema

**Patrón A — autoSubmitFormId (preferido, simple):**
```blade
<form id="pet-profile-photo-form" action="..." method="POST" enctype="multipart/form-data">
    @csrf
    <x-image-upload name="photo" autoSubmitFormId="pet-profile-photo-form" ... />
    <button type="submit" style="display:none;"></button>
</form>
```
Usado en: foto de perfil de mascota (`pets/show.blade.php`)

**Patrón B — evento @image-cropped (para forms complejos):**
```blade
<form action="..." x-data="{ submit() { this.$el.submit(); } }">
    <div @image-cropped="submit()">
        <x-image-upload name="photo" x-ref="photoInput" ... />
    </div>
</form>
```
Usado en: foto de perfil de recurso (`resources/show.blade.php`)

**Patrón C — submit manual (fotos de bitácora):**
El `x-image-upload` vive dentro de un form con botón "Subir y Guardar". El usuario aplica el recorte y luego hace click manual.
Usado en: fotos adicionales de mascotas, recursos, eventos.

---

## Inventario de usos en el sistema

### Fotos de perfil (recorte 1:1, forma circular o cuadrada)

| Vista | Entidad | Ruta POST | Auto-submit | Pattern |
|---|---|---|---|---|
| `pets/show.blade.php` | Mascota | `pets.profile-photo.update` | Sí | A |
| `resources/show.blade.php` | Recurso | `resources.profile-photo.update` | Sí (evento) | B |
| `operators/partials/form.blade.php` | Operador | `operators.store` / `operators.update` | No | C |
| `user/edit.blade.php` | Usuario | `users.update` | No | C |
| `user/create.blade.php` | Usuario | `users.store` | No | C |
| `user/settings.blade.php` | Usuario (self) | `user.settings.update` | No | C |

### Fotos de bitácora (recorte 4:3, sin auto-submit)

| Vista | Entidad | Ruta | Notas |
|---|---|---|---|
| `pets/show.blade.php` | Mascota | `clients.pets.photos.store` | Con selector de tipo (ingreso/incidencia/resultado/perfil) |
| `resources/show.blade.php` | Recurso | `resources.photos.store` | Con selector de tipo |
| `resources/events/show.blade.php` | Evento de recurso | `resources.events.photos.store` + `update` | Evidencia fotográfica de incidentes |

---

## Procesamiento backend — ImageManagers

**Patrón común:** Todos heredan del mismo contrato. Reciben el archivo ya recortado desde el frontend.

```
Archivo → orient (EXIF) → resize (Fit::Max) → JPG → optimize → guardar
                                                               → thumbnail (Fit::Crop)
```

| Manager | Directorio | Max | Thumbnail |
|---|---|---|---|
| `PetPhotoImageManager` | `pet-photos/Y/m/` | Config backoffice | Sí |
| `ResourcePhotoImageManager` | `resource-photos/Y/m/` | Config backoffice | Sí |
| `ResourceEventPhotoImageManager` | `resource-event-photos/Y/m/` | Config backoffice | Sí |
| `UserPhotoImageManager` | `user-photos/Y/m/` | 5 MB | Sí |
| `OperatorPhotoImageManager` | `operator-photos/Y/m/` | 15 MB | Sí |

**Estructura de archivos en storage:**
```
storage/app/public/
  pet-photos/2026/05/
    original/abc123_opt.jpg
    thumbs/abc123_opt.jpg
```

---

## Consideraciones de despliegue

- `php artisan storage:link` debe ejecutarse en cada deploy (el script `levantar_backoffice.sh` lo hace)
- En producción (OPi), copiar `storage/app/public/` vía `scp` al hacer migración de datos
- El bundle Vite incluye el CSS de Cropper — NO depende de CDN externo

---

## Notas críticas

Ver `docs/tecnico/NOTAS_TECNICAS.md` para historial de bugs y soluciones.
