# Servicio: Subida y Recorte de Fotografías
### Entrada del Catálogo de Servicios · EstetiCAN

> **Propósito ITIL:** Este documento es la entrada formal del Catálogo de Servicios
> para el componente de subida de fotos. Define qué hace el servicio, cómo usarlo
> correctamente, quién lo consume y cómo diagnosticar fallas.

---

## Definición del Servicio

| Atributo | Valor |
|---|---|
| **Nombre** | Subida y Recorte de Fotografías |
| **Identificador** | SVC-PHOTO-01 |
| **Propietario técnico** | Agente IA / Desarrollador principal |
| **Versión actual** | cropperjs 1.6.2 — bundle `app-DReE3rzJ.js` |
| **Estado** | ✅ Operativo |
| **Última verificación** | 2026-05-25 (verificado por usuario) |

**Valor que entrega:**
Permite a los operadores subir fotos de mascotas, recursos, operadores y usuarios con recorte interactivo, marca de agua con fecha, y persistencia organizada por entidad. La foto resultante es un JPEG optimizado con las proporciones correctas para cada caso de uso.

---

## Componente Central: `x-image-upload`

**Ubicación:** `resources/views/components/image-upload.blade.php`
**Lógica JS:** `resources/js/modules/image-upload.js`
**CSS:** `resources/css/vendor-cropper.css` (v1.6.2)

> ⚠️ **NO actualizar cropperjs** sin leer NT-001. La v2 tiene API incompatible.

### Props del componente

| Prop | Tipo | Default | Descripción |
|---|---|---|---|
| `name` | string | — | Campo `input[type=file]`. Debe coincidir con la validación del controlador. |
| `value` | string\|null | null | Path de storage público para el preview inicial. |
| `label` | string | `'Foto de perfil'` | Texto bajo el preview. `null` para ocultar. |
| `previewShape` | `circle`\|`square`\|`rect` | `circle` | Forma del preview. |
| `defaultIcon` | string | `'bi-person-fill'` | Clase Bootstrap Icons cuando no hay foto. |
| `maxWidth` | string | `'120px'` | CSS `max-width` del wrapper completo. |
| `aspectRatio` | number | `1` | Proporción de recorte. `1` = cuadrado, `4/3` = paisaje. |
| `formId` | string\|null | null | Si el `<input>` pertenece a un form externo (attr HTML `form`). |
| `watermarkText` | string\|null | null | Texto + fecha/hora quemado en esquina inferior derecha del JPEG. |
| `autoSubmitFormId` | string\|null | null | ID del form a enviar automáticamente al confirmar recorte. |

### Flujo de operación (ciclo de vida del servicio)

```
Usuario click en ícono cámara
        │
        ▼
   File Picker (nativo del OS)
        │
        ▼
fileChosen() — validación de tamaño (máx 10 MB en frontend)
        │
        ▼
FileReader.readAsDataURL()
        │
        ▼
Listener shown.bs.modal registrado
        │
        ▼
img.src = dataUrl → modal.show()
        │
        ▼ (animación Bootstrap ~300ms)
shown.bs.modal dispara
        │
        ▼
requestAnimationFrame()
        │
        ▼
new Cropper(img, { aspectRatio, ... })  ← ver NT-002 y NT-003
        │
        ▼
Usuario: recortar / rotar
        │
        ▼
applyCrop() → canvas.toBlob() → DataTransfer → input.files
        │
        ├── watermarkText ≠ null → quema texto en canvas antes de toBlob
        │
        ▼
Dispatch evento 'image-cropped'
        │
        ├── autoSubmitFormId → click en button[submit] del form → POST
        └── @image-cropped listener → lógica custom del padre → POST
```

---

## Patrones de Integración

Hay tres patrones probados. Elegir según el contexto.

### Patrón A — Auto-submit directo (preferido para fotos de perfil)
**Cuándo:** La foto es el único dato del form. No hay otros campos.

```blade
<form id="pet-profile-photo-form"
      action="{{ route('pets.profile-photo.update', $pet) }}"
      method="POST"
      enctype="multipart/form-data">
    @csrf
    <x-image-upload
        name="photo"
        :value="$pet->profile_photo_path"
        previewShape="circle"
        :aspectRatio="1"
        maxWidth="160px"
        :watermarkText="$pet->name"
        autoSubmitFormId="pet-profile-photo-form"
    />
    {{-- Botón invisible requerido para el submit programático --}}
    <button type="submit" style="display:none;"></button>
</form>
```

### Patrón B — Submit vía evento (para forms con estado Alpine)
**Cuándo:** El form padre tiene su propio `x-data`. No se puede usar `autoSubmitFormId` porque el contexto Alpine crea conflicto.

```blade
<form action="{{ route('resources.profile-photo.update', $resource) }}"
      method="POST"
      enctype="multipart/form-data"
      x-data="{ submit() { this.$el.submit(); } }">
    @csrf
    <div @image-cropped="submit()">
        <x-image-upload
            name="photo"
            :value="$resource->profile_photo_path"
            :watermarkText="$resource->name"
            x-ref="photoInput"
        />
    </div>
</form>
```

### Patrón C — Submit manual (para formularios complejos con múltiples campos)
**Cuándo:** La foto va junto con otros datos (categoría, descripción, fecha). El usuario aplica el recorte y luego hace click en "Guardar".

```blade
<form action="{{ route('clients.pets.photos.store', [$client, $pet]) }}"
      method="POST"
      enctype="multipart/form-data">
    @csrf
    <x-image-upload
        name="photo"
        previewShape="square"
        :aspectRatio="4/3"
        :watermarkText="$pet->name"
    />
    {{-- Otros campos del form --}}
    <select name="photo_type">...</select>
    <button type="submit">Subir y Guardar</button>
</form>
```

---

## Inventario de Consumidores del Servicio

### Fotos de identidad (perfil)

| Vista | Entidad | Controlador / Ruta | Patrón | Validación backend |
|---|---|---|---|---|
| `pets/show.blade.php` | Mascota | `PetController::updateProfilePhoto` | A | image, max:15360 |
| `resources/show.blade.php` | Recurso | `ResourceController::updateProfilePhoto` | B | image, max:15360 |
| `operators/partials/form.blade.php` | Operador | `OperatorController::store/update` | C | image, max:15360 |
| `user/edit.blade.php` | Usuario | `UserController::update` | C | image, max:5120 |
| `user/create.blade.php` | Usuario | `UserController::store` | C | image, max:5120 |
| `user/settings.blade.php` | Usuario (self) | `UserSettingsController::update` | C | image, max:5120 |

### Fotos de trazabilidad (bitácora / historial)

| Vista | Entidad | Controlador / Ruta | Patrón | Aspect Ratio |
|---|---|---|---|---|
| `pets/show.blade.php` | Mascota | `PetPhotoController::store` | C | 4:3 |
| `resources/show.blade.php` | Recurso | `ResourcePhotoController::store` | C | 4:3 |
| `resources/events/show.blade.php` | Evento | `ResourceEventPhotoController::store/update` | C | 4:3 |

---

## Procesamiento Backend — ImageManagers

**Principio de diseño:** El frontend entrega el blob ya recortado. Los ImageManagers solo almacenan — nunca recortan.

### Pipeline de procesamiento

```
Archivo recibido (POST)
        │
        ▼
Validación Laravel (tipo, tamaño)
        │
        ▼
ImageManager::store($file)
        │
        ├── orientImage()     — corrige orientación EXIF
        ├── resize(Fit::Max)  — redimensiona conservando proporción
        ├── encodeJpeg(q=85)  — convierte a JPEG
        ├── optimize()        — compresión sin pérdida
        └── save(original/)   — guarda en storage
                │
                └── createThumbnail()
                        ├── resize(Fit::Crop)  — recorte centrado al tamaño thumb
                        └── save(thumbs/)
```

### Configuración de cada manager

| Manager | Directorio base | Tamaño máx frontend | Tamaño máx backend |
|---|---|---|---|
| `PetPhotoImageManager` | `pet-photos/Y/m/` | 10 MB (JS) | 15 MB (Laravel) |
| `ResourcePhotoImageManager` | `resource-photos/Y/m/` | 10 MB | 15 MB |
| `ResourceEventPhotoImageManager` | `resource-event-photos/Y/m/` | 10 MB | 15 MB |
| `UserPhotoImageManager` | `user-photos/Y/m/` | 10 MB | 5 MB |
| `OperatorPhotoImageManager` | `operator-photos/Y/m/` | 10 MB | 15 MB |

**Estructura de archivos en storage:**
```
storage/app/public/
  pet-photos/
    2026/05/
      original/  550e8400-e29b-41d4-a716-446655440000_opt.jpg
      thumbs/    550e8400-e29b-41d4-a716-446655440000_opt.jpg
```

---

## Diagrama de Dependencias del Servicio

```
x-image-upload (Blade Component)
    │
    ├── resources/js/modules/image-upload.js
    │       ├── import Cropper from 'cropperjs'  [v1.6.2 — FIJADO]
    │       └── window.bootstrap.Modal           [v5.3 — del bundle]
    │
    ├── resources/css/vendor-cropper.css          [v1.6.2 — CSS manual]
    │
    └── window.Alpine (imageUpload data factory)  [v3.x — del bundle]
```

---

## Indicadores de Salud del Servicio (Health Checks)

Para verificar que el servicio funciona correctamente en producción:

**Verificación manual:**
1. Ir a cualquier vista de mascota → PetSho
2. Hacer click en el ícono de cámara de "Foto de Perfil"
3. Seleccionar una imagen
4. Verificar: modal abre con imagen visible a tamaño completo
5. Verificar: botones de rotar funcionan
6. Verificar: aplicar recorte actualiza el preview del círculo
7. Verificar: la foto guardada aparece al recargar la página

**Señales de falla y causa probable:**

| Síntoma | Causa probable | Referencia |
|---|---|---|
| Modal no abre | JS error, bundle no compilado | Revisar consola del browser |
| Imagen se ve pequeña / recortador invisible | cropperjs v2 instalada | NT-001 |
| Recortador aparece pero solo muestra parte superior | Contenedor `max-height` en vez de `height` | NT-003 |
| Recortador no se inicializa (modal abre, img visible, sin handles) | Inicialización antes de `shown.bs.modal` | NT-002 |
| "Aplicar Recorte" no hace nada | `this.cropper` es null (ver NT-001 y NT-002) | NT-001, NT-002 |
| Foto no aparece después de guardar | `storage:link` no activo, o path incorrecto | Deploy checklist §7 |
| 500 al subir | Permisos de storage, validación de tamaño | `storage/logs/laravel.log` |

---

## Consideraciones de Seguridad

- **CSP:** `img-src` debe incluir `data: blob:` para que el preview del canvas funcione (ver NT-006)
- **Permisos:** La ruta de upload requiere autenticación. Verificar middleware en `routes/web.php`
- **Validación:** El backend valida tipo y tamaño independientemente del frontend — nunca confiar solo en la validación JS
- **Watermark:** Se aplica en el cliente. No es una medida de seguridad, es una marca operativa. El servidor recibe el JPG ya marcado.

---

## Registro de Cambios del Servicio

| Versión | Fecha | Cambio |
|---|---|---|
| 1.0 | 2026-04-26 | Primera versión estable. CDN cropperjs 1.6.2 + Alpine CDN |
| 2.0 | 2026-05-25 | Migración CDN → bundle Vite. CSP agregado. `unsafe-eval` requerido. |
| 2.1 | 2026-05-25 | Downgrade cropperjs npm: v2.1.1 → v1.6.2. Fix NT-001, NT-002, NT-003. |
