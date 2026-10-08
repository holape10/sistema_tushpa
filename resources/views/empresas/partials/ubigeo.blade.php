{{--
    Campo de ubigeo: se escribe la ciudad o el distrito y se elige de la lista (el código de 6 dígitos sale solo).
    Uso con Alpine:  @include('empresas.partials.ubigeo', ['model' => 'g.llegada_ubigeo'])
    Uso en formulario normal:  @include('empresas.partials.ubigeo', ['name' => 'ubigeo', 'valor' => $sucursal->ubigeo])
--}}
@php $valorInicial = $valor ?? ''; @endphp
<div class="relative" x-data="ubigeoCampo(@js((string) $valorInicial))"
     @if (!empty($model)) x-modelable="codigo" x-model="{{ $model }}" @endif
     @click.outside="res = []">
    @if (!empty($name))<input type="hidden" name="{{ $name }}" :value="codigo">@endif
    <div x-show="codigo && !editando" class="flex items-center gap-2 rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-sm min-h-[38px]">
        <svg class="w-4 h-4 text-rose-500 shrink-0" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2a7 7 0 00-7 7c0 5.25 7 13 7 13s7-7.75 7-13a7 7 0 00-7-7zm0 9.5A2.5 2.5 0 1112 6.5a2.5 2.5 0 010 5z"/></svg>
        <span class="flex-1 min-w-0 truncate" :title="nombre"><span class="font-semibold" x-text="nombre || 'Ubigeo'"></span> <span class="text-gray-400 text-xs" x-text="'(' + codigo + ')'"></span></span>
        <button type="button" @click="editar()" class="text-xs font-semibold text-indigo-600 hover:underline shrink-0">Cambiar</button>
    </div>
    <input x-show="!codigo || editando" x-ref="buscar" x-model="q" @input.debounce.250ms="buscar()" @keydown.enter.prevent="res[0] && elegir(res[0])"
           @keydown.escape="editando = false; res = []" placeholder="Escribe la ciudad o el distrito…" autocomplete="off"
           class="block w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
    <div x-show="res.length" x-cloak class="absolute z-30 mt-1 w-full bg-white rounded-xl shadow-xl border max-h-64 overflow-y-auto">
        <template x-for="u in res" :key="u.ubigeo">
            <button type="button" @click="elegir(u)" class="w-full text-left px-3 py-2 text-sm hover:bg-indigo-50">
                <span x-text="u.nombre"></span> <span class="text-xs text-gray-400" x-text="u.ubigeo"></span>
            </button>
        </template>
    </div>
    <p x-show="sinResultados" x-cloak class="text-xs text-amber-700 mt-1">No encontramos ese lugar. Prueba solo con el nombre del distrito.</p>
</div>

@once
    <script>
        function ubigeoCampo(inicial) {
            return {
                codigo: inicial || '', nombre: '', q: '', res: [], editando: false, sinResultados: false,
                init() {
                    this.$watch('codigo', c => this.ponerNombre(c));
                    this.ponerNombre(this.codigo);
                },
                async consultar(q) {
                    return fetch(@json(route('ubigeos')) + '?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } }).then(r => r.json()).catch(() => []);
                },
                async ponerNombre(c) {
                    if (!/^\d{6}$/.test(c || '')) { this.nombre = ''; return; }
                    const r = await this.consultar(c);
                    this.nombre = r[0]?.ubigeo === c ? r[0].nombre : '';
                },
                async buscar() {
                    this.sinResultados = false;
                    if (this.q.trim().length < 3) { this.res = []; return; }
                    this.res = await this.consultar(this.q.trim());
                    this.sinResultados = !this.res.length;
                },
                elegir(u) { this.codigo = u.ubigeo; this.nombre = u.nombre; this.q = ''; this.res = []; this.editando = false; this.sinResultados = false; },
                editar() { this.editando = true; this.q = ''; this.$nextTick(() => this.$refs.buscar.focus()); },
            };
        }
    </script>
@endonce
