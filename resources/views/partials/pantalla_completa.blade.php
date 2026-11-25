{{-- Pantalla completa: botón pequeño abajo a la izquierda (casi transparente hasta pasar el mouse). Se quita con el mismo
     botón o con ESC (el navegador sale solo de la pantalla completa al presionar ESC). --}}
<button type="button" x-data="{ completa: !!document.fullscreenElement }"
        x-init="document.addEventListener('fullscreenchange', () => completa = !!document.fullscreenElement)"
        @click="completa ? document.exitFullscreen?.() : document.documentElement.requestFullscreen?.().catch(() => {})"
        :title="completa ? 'Salir de pantalla completa (Esc)' : 'Pantalla completa'"
        :aria-label="completa ? 'Salir de pantalla completa' : 'Pantalla completa'"
        class="print:hidden fixed left-1.5 bottom-1.5 z-40 w-8 h-8 rounded-full bg-white shadow-md ring-1 ring-gray-200 text-gray-500 flex items-center justify-center opacity-50 hover:opacity-100 hover:text-indigo-600 focus:opacity-100 transition">
    <svg x-show="!completa" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5"/></svg>
    <svg x-show="completa" x-cloak class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M9 4v5H4M15 4v5h5M9 20v-5H4M15 20v-5h5"/></svg>
</button>
