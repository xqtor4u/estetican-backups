@props([
    'name',
    'value' => null,
    'label' => 'Foto de perfil',
    'previewShape' => 'circle', // circle, square or rect
    'defaultIcon' => 'bi-person-fill',
    'maxWidth' => '120px',
    'aspectRatio' => 1, // 1 for circle/square, 4/3 for rect
    'formId' => null,
    'watermarkText' => null,
    'autoSubmitFormId' => null
])

<div {{ $attributes->merge(['class' => 'image-upload-wrapper']) }} 
     x-data="imageUpload('{{ $value ? Storage::disk('public')->url($value) : '' }}', {{ $aspectRatio }}, {{ $watermarkText ? '\'' . addslashes($watermarkText) . '\'' : 'null' }}, {{ $autoSubmitFormId ? '\'' . $autoSubmitFormId . '\'' : 'null' }})"
     style="max-width: {{ $maxWidth }};">
    
    <div class="position-relative mb-2">
        <!-- Preview Area -->
        <div class="image-upload-preview text-center shadow-sm 
            @if($previewShape === 'circle') rounded-circle 
            @elseif($previewShape === 'square') rounded-4 
            @else rounded-4 @endif overflow-hidden border bg-light d-flex align-items-center justify-content-center"
             style="aspect-ratio: {{ $aspectRatio === 1 ? '1/1' : '4/3' }}; width: 100%;">
            
            <template x-if="imageUrl">
                <img :src="imageUrl" alt="Preview" class="img-fluid w-100 h-100 object-fit-cover">
            </template>
            
            <template x-if="!imageUrl">
                <div class="text-body-secondary py-4">
                    <i class="bi {{ $defaultIcon }} display-4"></i>
                </div>
            </template>
        </div>

        <input type="file" 
               x-ref="fileInput"
               name="{{ $name }}" 
               id="{{ $name }}" 
               @if($formId) form="{{ $formId }}" @endif
               class="d-none" 
               accept="image/*"
               @click="$event.target.value = ''"
               @change="fileChosen">

        <!-- Trigger Button -->
        <div class="btn btn-sm btn-dark rounded-circle position-absolute bottom-0 end-0 shadow-sm d-flex align-items-center justify-content-center"
               style="width: 32px; height: 32px; cursor: pointer;"
               title="Cambiar imagen"
               @click="$refs.fileInput.click()">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-camera-fill" viewBox="0 0 16 16"><path d="M10.5 8.5a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0"/><path d="M2 4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2h-1.172a2 2 0 0 1-1.414-.586l-.828-.828A2 2 0 0 0 9.172 2H6.828a2 2 0 0 0-1.414.586l-.828.828A2 2 0 0 1 3.172 4zm.5 2a.5.5 0 1 1 0-1 .5.5 0 0 1 0 1m9 2.5a3.5 3.5 0 1 1-7 0 3.5 3.5 0 0 1 7 0"/></svg>
        </div>
    </div>

    @if($label)
        <div class="text-center small text-muted text-uppercase letter-space fw-bold" style="font-size: 0.65rem;">
            {{ $label }}
        </div>
    @endif

    <!-- Crop Modal -->
    <div class="modal fade" tabindex="-1" aria-hidden="true" x-ref="cropModal" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="modal-title fw-bold">Ajustar Fotografía</h5>
                    <button type="button" class="btn-close" aria-label="Close" @click="cancelCrop"></button>
                </div>
                <div class="modal-body py-4 text-center">
                    <div style="height: 60vh; width: 100%; overflow: hidden;" class="bg-dark rounded-4 shadow-sm mb-3">
                        <img src="" x-ref="cropImage" style="max-width: 100%; display:block;">
                    </div>
                    
                    <div class="btn-group shadow-sm" role="group" aria-label="Rotate Controls">
                        <button type="button" class="btn btn-light border" @click="rotateLeft" title="Rotar a la izquierda">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-arrow-counterclockwise" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M8 3a5 5 0 1 1-4.546 2.914.5.5 0 0 0-.908-.417A6 6 0 1 0 8 2v1z"/><path d="M8 4.466V.534a.25.25 0 0 0-.41-.192L5.23 2.308a.25.25 0 0 0 0 .384l2.36 1.966A.25.25 0 0 0 8 4.466z"/></svg> 
                            Rotar
                        </button>
                        <button type="button" class="btn btn-light border" @click="rotateRight" title="Rotar a la derecha">
                            Rotar
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-arrow-clockwise" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M8 3a5 5 0 1 0 4.546 2.914.5.5 0 0 1 .908-.417A6 6 0 1 1 8 2v1z"/><path d="M8 4.466V.534a.25.25 0 0 1 .41-.192l2.36 1.966c.12.1.12.284 0 .384L8.41 4.658A.25.25 0 0 1 8 4.466z"/></svg>
                        </button>
                    </div>
                    <div class="text-muted small mt-2">Usa tus dedos o el mouse para enfocar el área deseada.</div>
                </div>
                <div class="modal-footer border-top-0 pt-0">
                    <button type="button" class="btn btn-link text-muted text-decoration-none" @click="cancelCrop">Cancelar</button>
                    <button type="button" class="btn btn-primary rounded-pill px-4" @click="applyCrop">Aplicar Recorte</button>
                </div>
            </div>
        </div>
    </div>
</div>

@once
<script nonce="{{ csp_nonce() }}">
    function imageUpload(initialUrl, aspectRatio, watermarkText = null, autoSubmitFormId = null) {
        return {
            imageUrl: initialUrl,
            cropper: null,
            modalInstance: null,
            originalFile: null,
            autoSubmitFormId: autoSubmitFormId,
            fileChosen(event) {
                const file = event.target.files[0];
                if (!file) return;

                if (file.size > 10 * 1024 * 1024) {
                    alert('La imagen es demasiado pesada. El máximo permitido es 10MB.');
                    event.target.value = '';
                    return;
                }

                this.originalFile = file;
                const reader = new FileReader();

                reader.onload = (e) => {
                    this.$refs.cropImage.src = e.target.result;
                    
                    if (!this.modalInstance) {
                        this.modalInstance = new bootstrap.Modal(this.$refs.cropModal);
                    }
                    this.modalInstance.show();

                    // Cleanup previous cropper
                    if (this.cropper) {
                        this.cropper.destroy();
                    }

                    // Delay initialization to ensure modal is visible for proper sizing
                    setTimeout(() => {
                        this.cropper = new Cropper(this.$refs.cropImage, {
                            aspectRatio: aspectRatio,
                            viewMode: 1,
                            dragMode: 'move',
                            autoCropArea: 1,
                            restore: false,
                            guides: true,
                            center: true,
                            highlight: false,
                            cropBoxMovable: true,
                            cropBoxResizable: true,
                            toggleDragModeOnDblclick: false,
                        });
                    }, 150);
                };

                reader.readAsDataURL(file);
            },
            rotateLeft() {
                if(this.cropper) this.cropper.rotate(-90);
            },
            rotateRight() {
                if(this.cropper) this.cropper.rotate(90);
            },
            applyCrop() {
                if (!this.cropper) return;

                const canvas = this.cropper.getCroppedCanvas({
                    maxWidth: 1600,
                    maxHeight: 1600,
                    imageSmoothingQuality: 'high',
                });

                // Apply watermark if provided
                if (typeof watermarkText === 'string' && watermarkText.trim() !== '') {
                    const ctx = canvas.getContext('2d');
                    
                    const dateStr = new Date().toLocaleDateString('es-ES', { 
                        year: 'numeric', 
                        month: 'short', 
                        day: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                    const fullText = watermarkText.trim() + " - " + dateStr;
                    
                    // Calculate responsive font size (3% of image height, minimum 14px)
                    const fontSize = Math.max(14, Math.floor(canvas.height * 0.03)); 
                    ctx.font = `600 ${fontSize}px sans-serif`;
                    
                    const padding = fontSize * 0.6;
                    const textMetrics = ctx.measureText(fullText);
                    const textWidth = textMetrics.width;
                    const textHeight = fontSize;
                    
                    // Draw semi-transparent background box at bottom right
                    ctx.fillStyle = 'rgba(0, 0, 0, 0.5)';
                    const boxX = canvas.width - textWidth - (padding * 2);
                    const boxY = canvas.height - textHeight - (padding * 2);
                    const boxWidth = textWidth + (padding * 2);
                    const boxHeight = textHeight + (padding * 2);
                    
                    // Simple filled rectangle for maximum legibility
                    ctx.fillRect(boxX, boxY, boxWidth, boxHeight);
                    
                    // Draw text
                    ctx.textBaseline = 'top';
                    ctx.textAlign = 'left';
                    ctx.fillStyle = '#ffffff';
                    ctx.fillText(fullText, boxX + padding, boxY + padding);
                }

                // Generate visual preview immediately
                this.imageUrl = canvas.toDataURL('image/jpeg', 0.9);

                // Convert to Blob and inject into native input [File API Injection]
                canvas.toBlob((blob) => {
                    // Create a valid File object from the Blob
                    const croppedFile = new File([blob], this.originalFile.name.replace(/\.[^/.]+$/, "") + ".jpg", {
                        type: "image/jpeg",
                        lastModified: new Date().getTime()
                    });

                    // Force the file into the DOM FileList
                    const dataTransfer = new DataTransfer();
                    dataTransfer.items.add(croppedFile);
                    this.$refs.fileInput.files = dataTransfer.files;

                    this.$dispatch('image-cropped', { name: '{{ $name }}' });

                    if (this.autoSubmitFormId) {
                        const form = document.getElementById(this.autoSubmitFormId) || this.$el.closest('form');
                        if (form) {
                            const submitBtn = form.querySelector('button[type=submit], input[type=submit]');
                            if (submitBtn) {
                                submitBtn.click();
                            } else {
                                form.submit();
                            }
                        }
                    }

                    this.modalInstance.hide();
                    this.cropper.destroy();
                    this.cropper = null;
                }, 'image/jpeg', 0.9);
            },
            cancelCrop() {
                if(this.modalInstance) this.modalInstance.hide();
                if(this.cropper) {
                    this.cropper.destroy();
                    this.cropper = null;
                }
                this.$refs.fileInput.value = '';
                this.originalFile = null;
            }
        }
    }
</script>
@endonce

<style>
    .image-upload-preview img {
        transition: transform 0.3s ease;
    }
    .image-upload-preview:hover img {
        transform: scale(1.05);
    }
    /* Ensure cropper wrapper is properly contained within bootstrap modal */
    .cropper-container {
        width: 100% !important;
    }
</style>
