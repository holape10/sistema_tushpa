{{-- Botón "Instalar app": aparece solo cuando el navegador permite instalar (Chrome/Edge en Android y PC) --}}
<button type="button" x-data="{ visible: window.tushpaPuedeInstalar?.() }" x-cloak x-show="visible"
        @tushpa-instalable.window="visible = true" @tushpa-instalada.window="visible = false"
        @click="if (await window.tushpaInstalar()) visible = false"
        class="{{ $clase ?? 'inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-indigo-600 text-white text-xs font-bold hover:bg-indigo-700' }}"
        title="Instalar el sistema como app en este equipo">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v12m0 0l-4-4m4 4l4-4M5 20h14"/></svg>
    Instalar app
</button>
