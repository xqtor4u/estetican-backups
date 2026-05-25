import Cropper from 'cropperjs';

export default function imageUploadFactory(initialUrl, aspectRatio, watermarkText = null, autoSubmitFormId = null) {
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

            reader.onload = async (e) => {
                const img = this.$refs.cropImage;
                const modalEl = this.$refs.cropModal;

                if (this.cropper) {
                    this.cropper.destroy();
                    this.cropper = null;
                }

                // Decode the DataURL image fully before doing anything with layout
                img.src = e.target.result;
                try { await img.decode(); } catch (_) { /* decode() not critical */ }

                if (!this.modalInstance) {
                    this.modalInstance = new bootstrap.Modal(modalEl);
                }

                // Wait for Bootstrap modal fade to finish (shown.bs.modal)
                // so Cropper measures real container dimensions
                await new Promise(resolve => {
                    const onShown = () => {
                        modalEl.removeEventListener('shown.bs.modal', onShown);
                        resolve();
                    };
                    modalEl.addEventListener('shown.bs.modal', onShown);
                    this.modalInstance.show();
                });

                this.cropper = new Cropper(img, {
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
            };

            reader.readAsDataURL(file);
        },
        rotateLeft() {
            if (this.cropper) this.cropper.rotate(-90);
        },
        rotateRight() {
            if (this.cropper) this.cropper.rotate(90);
        },
        applyCrop() {
            if (!this.cropper) return;

            const canvas = this.cropper.getCroppedCanvas({
                maxWidth: 1600,
                maxHeight: 1600,
                imageSmoothingQuality: 'high',
            });

            if (typeof watermarkText === 'string' && watermarkText.trim() !== '') {
                const ctx = canvas.getContext('2d');
                const dateStr = new Date().toLocaleDateString('es-ES', {
                    year: 'numeric', month: 'short', day: 'numeric',
                    hour: '2-digit', minute: '2-digit'
                });
                const fullText = watermarkText.trim() + ' - ' + dateStr;
                const fontSize = Math.max(14, Math.floor(canvas.height * 0.03));
                ctx.font = `600 ${fontSize}px sans-serif`;
                const padding = fontSize * 0.6;
                const textWidth = ctx.measureText(fullText).width;
                const textHeight = fontSize;
                ctx.fillStyle = 'rgba(0, 0, 0, 0.5)';
                const boxX = canvas.width - textWidth - (padding * 2);
                const boxY = canvas.height - textHeight - (padding * 2);
                ctx.fillRect(boxX, boxY, textWidth + (padding * 2), textHeight + (padding * 2));
                ctx.textBaseline = 'top';
                ctx.textAlign = 'left';
                ctx.fillStyle = '#ffffff';
                ctx.fillText(fullText, boxX + padding, boxY + padding);
            }

            this.imageUrl = canvas.toDataURL('image/jpeg', 0.9);

            canvas.toBlob((blob) => {
                const croppedFile = new File(
                    [blob],
                    this.originalFile.name.replace(/\.[^/.]+$/, '') + '.jpg',
                    { type: 'image/jpeg', lastModified: Date.now() }
                );

                const dataTransfer = new DataTransfer();
                dataTransfer.items.add(croppedFile);
                this.$refs.fileInput.files = dataTransfer.files;

                this.$dispatch('image-cropped', { name: this.$refs.fileInput.name });

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
            if (this.modalInstance) this.modalInstance.hide();
            if (this.cropper) {
                this.cropper.destroy();
                this.cropper = null;
            }
            this.$refs.fileInput.value = '';
            this.originalFile = null;
        },
    };
}
