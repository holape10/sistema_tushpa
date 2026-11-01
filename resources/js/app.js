// resources/js/app.js
import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';

Alpine.plugin(collapse);
window.Alpine = Alpine;
Alpine.start();

// Tiempo real (mesas, cocina, avisos del QR) y avisos al celular del mozo
import './echo';
import './avisos-celular';
