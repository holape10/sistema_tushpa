{{-- Imagen del producto (se ve en el PV táctil, el PV móvil y el listado) --}}
<section class="bg-white rounded-2xl shadow-sm p-5">
    <div class="flex items-center justify-between mb-3">
        <h3 class="font-bold text-gray-800">Imagen</h3>
        <button type="button" x-show="vistaImagen" x-cloak @click="borrarImagen()" class="text-xs font-semibold text-rose-600 hover:underline">Quitar</button>
    </div>
    <label class="group relative block aspect-square max-h-72 w-full mx-auto rounded-2xl border-2 border-dashed border-gray-200 bg-gray-50 overflow-hidden cursor-pointer hover:border-indigo-300">
        <img x-show="vistaImagen" :src="vistaImagen" alt="Imagen del producto" class="w-full h-full object-contain bg-white">
        <span x-show="!vistaImagen" class="absolute inset-0 flex flex-col items-center justify-center text-gray-400 gap-2 p-4 text-center">
            <svg class="w-12 h-12" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            <span class="text-sm font-semibold text-indigo-600">Toca para subir una imagen</span>
            <span class="text-xs">o toma la foto desde el celular</span>
        </span>
        <span x-show="vistaImagen" x-cloak class="absolute bottom-2 inset-x-2 rounded-lg bg-black/60 text-white text-xs font-semibold py-1.5 text-center opacity-0 group-hover:opacity-100 transition">Cambiar imagen</span>
        <input x-ref="imagen" type="file" name="imagen" accept="image/jpeg,image/png,image/webp" class="sr-only" @change="elegirImagen($event)">
    </label>
    <input type="hidden" name="quitar_imagen" :value="quitarImagen ? 1 : 0">
    <p class="text-xs text-gray-400 mt-2 text-center">JPG, PNG o WEBP hasta 4 MB.</p>
</section>
