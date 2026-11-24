{{-- Punto Venta IA: el cajero habla ("2 jabones y una inca kola", "paga 30 en efectivo y 20 con yape", "boleta", "cobra")
     y el asistente arma la venta en el mismo Punto Venta. Lo que no existe se ofrece crear; lo que tiene varias opciones se elige. --}}
<section class="mb-4 rounded-3xl bg-gradient-to-r from-indigo-700 via-violet-700 to-fuchsia-700 text-white shadow-lg p-4 sm:p-5">
    <div class="flex flex-wrap items-center gap-4">
        <button type="button" @click="iaEscuchar()" :aria-pressed="ia.escuchando" :disabled="ia.pensando"
                :class="ia.escuchando ? 'bg-rose-500 animate-pulse ring-8 ring-rose-300/40' : 'bg-white/15 hover:bg-white/25 ring-4 ring-white/20'"
                class="w-20 h-20 shrink-0 rounded-full flex items-center justify-center transition disabled:opacity-60"
                :title="ia.escuchando ? 'Escuchando… (clic para detener)' : 'Hablar'" aria-label="Hablar">
            <svg class="w-10 h-10" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11a7 7 0 01-14 0m7 7v4m-4 0h8M12 15a3 3 0 003-3V5a3 3 0 00-6 0v7a3 3 0 003 3z"/></svg>
        </button>
        <div class="flex-1 min-w-[16rem]">
            <p class="text-xs font-bold uppercase tracking-widest text-white/70">🤖 Punto Venta IA · habla y yo armo la venta</p>
            <p class="text-lg font-bold leading-snug mt-0.5"
               x-text="ia.pensando ? 'Pensando…' : (ia.escuchando ? (ia.texto || 'Te escucho…') : (ia.respuesta || 'Toca el micrófono y di, por ejemplo: «2 jabones y una inca kola»'))"></p>
            <p class="text-xs text-white/70 mt-1" x-show="ia.ultimo && !ia.escuchando" x-text="'Dijiste: «' + ia.ultimo + '»'"></p>
            {{-- También se puede escribir --}}
            <form @submit.prevent="iaProcesar(ia.escrito); ia.escrito = ''" class="mt-2 flex gap-2">
                <input type="text" x-model="ia.escrito" placeholder="…o escríbelo aquí y Enter" autocomplete="off"
                       class="flex-1 h-10 rounded-xl border-0 bg-white/15 placeholder:text-white/60 text-white text-sm focus:ring-2 focus:ring-white/60">
            </form>
        </div>
        <div class="hidden md:block text-xs text-white/80 leading-relaxed">
            <p class="font-bold text-white mb-0.5">Puedes decir:</p>
            <p>«3 inca kola y 2 panes» · «quita el pan»</p>
            <p>«paga 30 en efectivo y 20 con yape»</p>
            <p>«boleta» · «factura con RUC 20…» · «cobra»</p>
        </div>
    </div>

    {{-- Varios productos parecidos: el cajero elige cuál --}}
    <template x-for="(e, i) in ia.elegir" :key="'e' + i">
        <div class="mt-3 rounded-2xl bg-white text-slate-800 p-3">
            <p class="text-sm font-bold">¿Cuál «<span x-text="e.texto"></span>»? <span class="font-normal text-slate-500" x-text="'(' + num(e.cantidad) + ')'"></span></p>
            <div class="mt-2 flex flex-wrap gap-2">
                <template x-for="p in e.opciones" :key="p.id">
                    <button type="button" @click="iaElegir(i, p)" class="px-3 py-2 rounded-xl bg-indigo-50 hover:bg-indigo-100 text-sm font-semibold text-indigo-800">
                        <span x-text="p.nombre"></span> · <span x-text="soles(p.precio)"></span></button>
                </template>
                <button type="button" @click="ia.crear.push({ nombre: e.texto.toUpperCase(), precio: e.precio || '', cantidad: e.cantidad }); ia.elegir.splice(i, 1)"
                        class="px-3 py-2 rounded-xl bg-amber-50 hover:bg-amber-100 text-sm font-semibold text-amber-800">➕ Ninguno: crear «<span x-text="e.texto"></span>»</button>
                <button type="button" @click="ia.elegir.splice(i, 1)" class="px-3 py-2 rounded-xl text-sm text-slate-500 hover:bg-slate-100">Omitir</button>
            </div>
        </div>
    </template>

    {{-- No existe: se crea al aceptar (nombre y precio) --}}
    <template x-for="(c, i) in ia.crear" :key="'c' + i">
        <form @submit.prevent="iaCrear(i)" class="mt-3 rounded-2xl bg-white text-slate-800 p-3 flex flex-wrap items-end gap-2">
            <p class="w-full text-sm font-bold">🆕 «<span x-text="c.nombre"></span>» no existe. ¿Lo creo y lo agrego (<span x-text="num(c.cantidad)"></span>)?</p>
            <label class="flex-1 min-w-[12rem] text-xs font-semibold text-slate-500">Nombre
                <input x-model="c.nombre" required maxlength="150" class="block w-full mt-1 h-10 rounded-xl border-slate-300 text-sm uppercase"></label>
            <label class="w-32 text-xs font-semibold text-slate-500">Precio S/
                <input type="number" step="0.01" min="0.01" x-model="c.precio" required class="block w-full mt-1 h-10 rounded-xl border-slate-300 text-sm text-right font-bold"></label>
            <label class="w-28 text-xs font-semibold text-slate-500">Stock inicial
                <input type="number" step="any" min="0" x-model="c.stock" placeholder="0" class="block w-full mt-1 h-10 rounded-xl border-slate-300 text-sm text-right"></label>
            <button :disabled="c.enviando" class="h-10 px-4 rounded-xl bg-emerald-600 text-white text-sm font-bold disabled:opacity-50" x-text="c.enviando ? 'Creando…' : '✓ Aceptar'"></button>
            <button type="button" @click="ia.crear.splice(i, 1)" class="h-10 px-3 rounded-xl text-sm text-slate-500 hover:bg-slate-100">No</button>
        </form>
    </template>
</section>
