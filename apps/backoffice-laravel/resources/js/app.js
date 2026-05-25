import './bootstrap';
import Alpine from 'alpinejs';
import Cropper from 'cropperjs';
import imageUploadFactory from './modules/image-upload';
import './modules/address-editor';
import './modules/client-form';
import './modules/confirm-actions';
import './modules/pet-photo-exif';
import './modules/password-toggle';
import './modules/resizable-columns';
import './modules/system-settings-preview';
import './modules/datetime-picker';

// Expose Cropper globally so image-upload module can reference it
window.Cropper = Cropper;

// Register Alpine components before Alpine.start() — guaranteed order
Alpine.data('imageUpload', imageUploadFactory);

window.Alpine = Alpine;
Alpine.start();
