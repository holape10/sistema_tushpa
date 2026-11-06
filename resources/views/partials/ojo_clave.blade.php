{{--
    Ojito para ver lo que se escribió en una clave. Va dentro de un contenedor con x-data="{ ver: false }" y class="relative",
    junto a un <input :type="ver ? 'text' : 'password'" class="... pr-10">.
--}}
<button type="button" @click="ver = !ver" tabindex="-1" :title="ver ? 'Ocultar la clave' : 'Ver lo que escribí'"
        class="absolute inset-y-0 right-0 flex items-center px-3 text-slate-400 hover:text-indigo-600">
    <svg :class="ver ? 'hidden' : ''" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.04 12.32a1 1 0 010-.64C3.42 7.5 7.36 4.5 12 4.5c4.64 0 8.57 3 9.96 7.18a1 1 0 010 .64C20.58 16.5 16.64 19.5 12 19.5c-4.64 0-8.57-3-9.96-7.18z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
    <svg :class="ver ? '' : 'hidden'" x-cloak class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.22A10.48 10.48 0 002.04 12c1.38 4.18 5.32 7.5 9.96 7.5 1.99 0 3.84-.55 5.43-1.5M6.23 6.23A10.45 10.45 0 0112 4.5c4.64 0 8.57 3 9.96 7.5a10.5 10.5 0 01-4.19 5.27M6.23 6.23L3 3m3.23 3.23l3.65 3.65m7.89 7.89L21 21m-3.23-3.23l-3.65-3.65m0 0a3 3 0 10-4.24-4.24m4.24 4.24L9.88 9.88"/></svg>
</button>
