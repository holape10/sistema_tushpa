{{-- Código de barras del producto (lector USB o cámara del celular) --}}
<div>
    <label class="block text-sm font-medium text-gray-700 mb-1">Código de barras</label>
    <div class="flex">
        <input type="text" name="codigo_barra" x-model="codigoBarra" maxlength="50" placeholder="Escanea o escribe el código…"
               @keydown.enter.prevent
               class="flex-1 min-w-0 h-11 rounded-l-xl border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
        <button type="button" @click="escanear('producto')" title="Escanear con la cámara" aria-label="Escanear con la cámara"
                class="px-4 rounded-r-xl bg-sky-500 hover:bg-sky-600 text-white flex items-center">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
        </button>
    </div>
    <p class="text-xs text-gray-400 mt-1">Las cajas lo leen con el lector o la cámara.</p>
</div>
