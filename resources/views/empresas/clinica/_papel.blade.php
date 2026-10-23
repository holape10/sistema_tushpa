{{--
    Historia en papel (antes del sistema): las hojas se fotografían con el celular/tablet o se suben escaneadas (fotos o PDF),
    con la fecha que tiene el papel. Se leen agrupadas por fecha y hoja por hoja. Usa el componente archivosClinica (_archivos).
--}}
<div x-data="archivosClinica({{ $h->his_cli_id }}, {{ \App\Support\Clinica::limiteSubida() }}, 'PAPEL')" x-init="cargar()"
     class="bg-white rounded-2xl shadow-sm p-4 space-y-3 border-l-4 border-amber-400">
    <div class="flex flex-wrap items-center gap-2">
        <h2 class="font-bold text-gray-700 flex-1">📜 Historia en papel <span class="font-normal text-gray-500">(antes del sistema)</span>
            <span x-show="lista.length" class="text-xs font-normal text-gray-400" x-text="'· ' + lista.length + (lista.length === 1 ? ' hoja' : ' hojas')"></span></h2>
        <button type="button" x-show="lista.length" @click="visor = visibles[0]" class="px-3 py-1.5 rounded-lg bg-amber-100 text-amber-800 text-xs font-bold hover:bg-amber-200">
            <i class="fas fa-book-open"></i> Leer desde la primera hoja</button>
    </div>

    <div class="rounded-xl bg-amber-50 p-3 space-y-2">
        <div class="flex flex-wrap items-end gap-2">
            <label class="text-xs font-semibold text-gray-600">Fecha del papel
                <input type="date" x-model="fechaDoc" max="{{ now()->toDateString() }}" class="block mt-1 rounded-lg border-gray-300 text-sm"></label>
            <label class="text-xs font-semibold text-gray-600 flex-1 min-w-[160px]">Nota (opcional)
                <input x-model="descripcion" maxlength="200" placeholder="Ej. Dr. Ramos · control y limpieza" class="block w-full mt-1 rounded-lg border-gray-300 text-sm"></label>
        </div>
        <div class="flex flex-wrap gap-2">
            <label class="flex-1 min-w-[150px] text-center px-3 py-2.5 rounded-lg bg-amber-500 text-white text-sm font-bold cursor-pointer hover:bg-amber-600" :class="subiendo && 'opacity-50 pointer-events-none'">
                <i class="fas fa-camera"></i> Fotografiar hoja
                <input type="file" accept="image/*" capture="environment" class="hidden" @change="subir($event)"></label>
            <label class="flex-1 min-w-[150px] text-center px-3 py-2.5 rounded-lg bg-white border border-amber-400 text-amber-800 text-sm font-bold cursor-pointer hover:bg-amber-100" :class="subiendo && 'opacity-50 pointer-events-none'">
                <i class="fas fa-upload"></i> Subir escaneo o fotos
                <input type="file" multiple accept="image/*,application/pdf" class="hidden" @change="subir($event)"></label>
        </div>
        <div x-show="subiendo" class="h-2 rounded-full bg-amber-100"><div class="h-2 rounded-full bg-amber-500" :style="'width:' + progreso + '%'"></div></div>
        <p class="text-[11px] text-gray-500">Pon la fecha que dice la hoja (si no se sabe, déjala vacía) y fotografía cada hoja de frente y con buena luz.
            La fecha se mantiene: puedes seguir tomando las demás hojas de esa misma atención. Para varias hojas a la vez, elige varias fotos o un PDF escaneado.</p>
    </div>

    <p x-show="!lista.length" class="text-sm text-gray-400 text-center py-2">Aún no se pasó la historia en papel de este paciente.</p>

    {{-- Hojas agrupadas por la fecha del papel (la más reciente arriba) --}}
    <template x-for="g in grupos" :key="g.fecha">
        <div class="space-y-1.5">
            <p class="text-xs font-bold text-amber-800">
                <i class="fas fa-calendar-day"></i> <span x-text="g.fecha ? fecha(g.fecha) : 'Sin fecha'"></span>
                <span class="font-normal text-gray-500" x-text="'· ' + g.hojas.length + (g.hojas.length === 1 ? ' hoja' : ' hojas') + (g.hojas[0].descripcion ? ' · ' + g.hojas[0].descripcion : '')"></span>
            </p>
            <div class="grid grid-cols-3 sm:grid-cols-5 lg:grid-cols-6 gap-2">
                <template x-for="a in g.hojas" :key="a.arc_id">
                    <button type="button" @click="a.imagen ? visor = a : window.open(a.url, '_blank')" class="relative aspect-[3/4] rounded-lg overflow-hidden bg-amber-50 border border-amber-200 text-left hover:ring-2 hover:ring-amber-400">
                        <img x-show="a.imagen" :src="a.url" loading="lazy" class="w-full h-full object-cover" alt="">
                        <div x-show="!a.imagen" class="w-full h-full flex flex-col items-center justify-center text-rose-600"><i class="fas fa-file-pdf text-3xl"></i><span class="text-[10px] px-1 truncate max-w-full" x-text="a.nombre"></span></div>
                        <span class="absolute bottom-0 inset-x-0 bg-black/55 text-white text-[10px] px-1.5 py-0.5" x-text="'Hoja ' + hoja(a)"></span>
                    </button>
                </template>
            </div>
        </div>
    </template>

    {{-- Lectura hoja por hoja --}}
    <div x-show="visor" x-cloak class="fixed inset-0 z-[70] bg-black/90 flex flex-col" @keydown.escape.window="visor = null" @keydown.arrow-right.window="visor && mover(1)" @keydown.arrow-left.window="visor && mover(-1)">
        <template x-if="visor">
            <div class="flex-1 flex flex-col min-h-0" x-data="{ zoom: false }">
                <div class="flex flex-wrap items-center gap-2 p-3 text-white">
                    <span class="flex-1 text-sm truncate"><b x-text="'Hoja ' + hoja(visor)"></b> · <span x-text="visor.fecha_doc ? fecha(visor.fecha_doc) : 'Sin fecha'"></span></span>
                    <button type="button" @click="zoom = !zoom" class="px-3 py-1.5 rounded-lg bg-white/15 text-sm" x-text="zoom ? 'Ver completa' : 'Acercar'"></button>
                    <a :href="visor.url" target="_blank" class="px-3 py-1.5 rounded-lg bg-white/15 text-sm">Abrir original</a>
                    <button type="button" @click="borrar(visor)" class="px-3 py-1.5 rounded-lg bg-rose-600 text-sm">Borrar</button>
                    <button type="button" @click="visor = null" class="px-3 py-1.5 rounded-lg bg-white/15 text-sm">✕</button>
                </div>
                <div class="flex-1 min-h-0 relative px-2" :class="zoom ? 'overflow-auto' : 'flex items-center justify-center'">
                    <img :src="visor.url" :class="zoom ? 'max-w-none w-[180%] mx-auto' : 'max-w-full max-h-full object-contain'" @click="zoom = !zoom" class="bg-white cursor-zoom-in" alt="">
                    <button type="button" @click="mover(-1); zoom = false" class="fixed left-3 top-1/2 w-11 h-11 rounded-full bg-white/25 text-white text-2xl">‹</button>
                    <button type="button" @click="mover(1); zoom = false" class="fixed right-3 top-1/2 w-11 h-11 rounded-full bg-white/25 text-white text-2xl">›</button>
                </div>
                <div class="p-3 flex flex-wrap gap-2 items-center">
                    <input type="date" x-model="visor.fecha_doc" max="{{ now()->toDateString() }}" class="rounded-lg text-sm" title="Fecha del papel">
                    <input x-model="visor.descripcion" maxlength="200" placeholder="Nota" class="flex-1 min-w-[160px] rounded-lg text-sm">
                    <button type="button" @click="guardarVisor()" class="px-3 py-2 rounded-lg bg-amber-500 text-white text-sm font-bold">Guardar</button>
                </div>
            </div>
        </template>
    </div>
</div>
