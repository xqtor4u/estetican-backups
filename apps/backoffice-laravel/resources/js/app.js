import './bootstrap';
import Alpine from 'alpinejs';
import Cropper from 'cropperjs';
import imageUploadFactory from './modules/image-upload';
import whatsappBandejaFactory from './modules/whatsapp-bandeja';
import whatsappPhoneLinkFactory from './modules/whatsapp-phone-link';
import mapaZonasFactory from './modules/mapa-zonas';
import './modules/address-editor';
import './modules/client-form';
import './modules/confirm-actions';
import './modules/screen-lock';
import './modules/pet-photo-exif';
import './modules/password-toggle';
import './modules/resizable-columns';
import './modules/system-settings-preview';
import './modules/datetime-picker';

// Expose Cropper globally so image-upload module can reference it
window.Cropper = Cropper;

// Register Alpine components before Alpine.start() — guaranteed order
Alpine.data('imageUpload', imageUploadFactory);
Alpine.data('whatsappBandeja', whatsappBandejaFactory);
Alpine.data('whatsappPhoneLink', whatsappPhoneLinkFactory);
Alpine.data('mapaZonas', mapaZonasFactory);

window.Alpine = Alpine;
Alpine.start();
