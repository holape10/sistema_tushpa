@extends('layouts.app')
@section('title', 'Puntos y Premios')
@section('content')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    @include('empresas.partials.alert')
    @php
        $in = 'block w-full rounded-lg border-gray-300 text-sm focus:border-violet-500 focus:ring-violet-500';
        $activo = (int) ($cfg->fid_activo ?? 0) === 1;
        $porPunto = (float) ($cfg->fid_soles_por_punto ?? 1);
    @endphp

    <div class="space-y-5" x-data="fidelizacion()">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-extrabold text-gray-800"><i class="fas fa-star text-violet-600"></i> Puntos y Premios</h1>
                <p class="text-sm text-gray-500">Cada cliente con DNI o RUC gana puntos con sus compras y los canjea por premios.</p>
            </div>
            <span class="px-4 py-2 rounded-full text-sm font-bold {{ $activo ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-500' }}">
                {{ $activo ? '● Fidelización ACTIVA en esta sucursal' : '○ Fidelización APAGADA en esta sucursal' }}</span>
        </div>

        <div class="grid sm:grid-cols-3 gap-3">
            <div class="bg-white rounded-2xl shadow-sm p-4"><p class="text-xs text-gray-500 font-semibold">Clientes con puntos</p><p class="text-3xl font-black text-violet-700">{{ number_format($resumen['clientes']) }}</p></div>
            <div class="bg-white rounded-2xl shadow-sm p-4"><p class="text-xs text-gray-500 font-semibold">Puntos ganados este mes</p><p class="text-3xl font-black text-emerald-600">{{ number_format($resumen['ganados_mes']) }}</p></div>
            <div class="bg-white rounded-2xl shadow-sm p-4"><p class="text-xs text-gray-500 font-semibold">Premios canjeados este mes</p><p class="text-3xl font-black text-amber-600">{{ number_format($resumen['canjes_mes']) }}</p></div>
        </div>

        <div class="grid lg:grid-cols-2 gap-5">
            {{-- Regla --}}
            <form method="POST" action="{{ route('fidelizacion.config') }}" class="bg-white rounded-2xl shadow-sm p-5 space-y-4" x-data="{ on: {{ $activo ? 'true' : 'false' }}, pp: {{ $porPunto }} }">
                @csrf
                <h2 class="font-bold text-gray-700">1. Regla de puntos de esta sucursal</h2>
                <div class="flex gap-2">
                    <label class="flex-1 cursor-pointer rounded-xl border-2 p-3 text-center font-bold" :class="on ? 'border-emerald-500 bg-emerald-50 text-emerald-700' : 'border-gray-200 text-gray-500'">
                        <input type="radio" name="fid_activo" value="1" x-model="on" @change="on = true" class="sr-only" {{ $activo ? 'checked' : '' }} @disabled(!$esAdmin)> SÍ, sumar puntos</label>
                    <label class="flex-1 cursor-pointer rounded-xl border-2 p-3 text-center font-bold" :class="!on ? 'border-gray-500 bg-gray-50 text-gray-700' : 'border-gray-200 text-gray-500'">
                        <input type="radio" name="fid_activo" value="0" @change="on = false" class="sr-only" {{ $activo ? '' : 'checked' }} @disabled(!$esAdmin)> NO</label>
                </div>
                <div class="grid grid-cols-2 gap-3 text-sm">
                    <label>Cada S/ … de compra = 1 punto
                        <input type="number" step="0.01" min="0.01" name="fid_soles_por_punto" x-model.number="pp" value="{{ $porPunto }}" class="{{ $in }} mt-1" @disabled(!$esAdmin)></label>
                    <label>Compra mínima para sumar (S/)
                        <input type="number" step="0.10" min="0" name="fid_compra_minima" value="{{ (float) ($cfg->fid_compra_minima ?? 0) }}" class="{{ $in }} mt-1" @disabled(!$esAdmin)></label>
                </div>
                <p class="text-sm bg-violet-50 text-violet-800 rounded-lg px-3 py-2" x-text="'Ejemplo: una compra de S/ 100 da ' + Math.floor(100 / (pp || 1)) + ' puntos.'"></p>
                <p class="text-xs text-gray-500">Suman las facturas, boletas y notas de venta con DNI o RUC (no "VENTA AL PORTADOR"). Si la venta se anula o tiene nota de crédito total, sus puntos se descuentan.</p>
                @if ($esAdmin)<button class="px-5 py-2.5 rounded-xl bg-violet-600 text-white font-bold hover:bg-violet-700">Guardar regla</button>@endif
            </form>

            {{-- Premios --}}
            <div class="bg-white rounded-2xl shadow-sm p-5 space-y-3">
                <h2 class="font-bold text-gray-700">2. Premios</h2>
                <div class="divide-y border rounded-xl">
                    @forelse ($premios as $p)
                        <div class="flex items-center gap-3 px-3 py-2 {{ $p->activo && ! $p->vencido ? '' : 'opacity-50' }}">
                            <span class="w-9 h-9 rounded-full bg-amber-100 text-amber-600 flex items-center justify-center"><i class="fas {{ $p->IdProducto ? 'fa-box' : 'fa-gift' }}"></i></span>
                            <div class="flex-1 min-w-0"><p class="font-semibold text-gray-800 truncate">{{ $p->nombre }}{{ $p->activo ? '' : ' (inactivo)' }}</p>
                                <p class="text-xs text-gray-500 truncate">
                                    @if ($p->IdProducto)<span class="text-sky-700">Sale del almacén: {{ rtrim(rtrim(number_format($p->cantidad, 3), '0'), '.') }} × {{ $p->pronom }}@if ((int) $p->promocion === 0) · stock {{ rtrim(rtrim(number_format((float) $p->stock, 2), '0'), '.') ?: 0 }}@endif</span>@endif
                                    @if ($p->vence)<span class="{{ $p->vencido ? 'text-rose-600 font-semibold' : '' }}"> · {{ $p->vencido ? 'Venció' : 'Vence' }} el {{ \Illuminate\Support\Carbon::parse($p->vence)->format('d/m/Y') }}</span>@endif
                                    @if ($p->descripcion) · {{ $p->descripcion }}@endif
                                </p></div>
                            <span class="font-black text-violet-700 whitespace-nowrap">{{ number_format($p->puntos) }} pts</span>
                            @if ($esAdmin)<button type="button" @click="premio = @js(['premio_id' => $p->premio_id, 'nombre' => $p->nombre, 'descripcion' => $p->descripcion, 'puntos' => $p->puntos, 'activo' => (bool) $p->activo, 'IdProducto' => $p->IdProducto, 'producto' => $p->pronom, 'cantidad' => (float) $p->cantidad, 'vence' => $p->vence])" class="text-xs text-violet-600 font-semibold">Editar</button>@endif
                        </div>
                    @empty
                        <p class="p-5 text-center text-sm text-gray-400">Aún no hay premios. Ej.: GASEOSA 1.5L por 100 puntos, DESCUENTO S/ 20 por 500 puntos.</p>
                    @endforelse
                </div>
                @if ($esAdmin)
                    <form method="POST" action="{{ route('fidelizacion.premio') }}" class="rounded-xl bg-amber-50 p-3 grid sm:grid-cols-6 gap-2 text-sm items-end">
                        @csrf
                        <input type="hidden" name="premio_id" :value="premio.premio_id">
                        <label class="sm:col-span-3">Premio<input name="nombre" x-model="premio.nombre" required maxlength="120" placeholder="GASEOSA 1.5L" class="{{ $in }} mt-1 uppercase"></label>
                        <label class="sm:col-span-2">Cuesta (puntos)<input type="number" name="puntos" x-model="premio.puntos" required min="1" class="{{ $in }} mt-1"></label>
                        <label class="flex items-center gap-1 pb-2"><input type="hidden" name="activo" value="0"><input type="checkbox" name="activo" value="1" x-model="premio.activo" class="rounded"> Activo</label>
                        <div class="sm:col-span-4 relative">Producto de mi lista <span class="text-gray-400">(opcional: descuenta stock al canjear)</span>
                            <input type="hidden" name="IdProducto" :value="premio.IdProducto || ''">
                            <div x-show="premio.IdProducto" class="mt-1 flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-3 py-2">
                                <i class="fas fa-box text-sky-600"></i><span class="flex-1 truncate" x-text="premio.producto"></span>
                                <button type="button" @click="premio.IdProducto = null; premio.producto = ''" class="text-xs text-rose-600 font-semibold">Quitar</button>
                            </div>
                            <input x-show="!premio.IdProducto" x-model="buscaProd" @input.debounce.300ms="buscarProducto()" placeholder="Buscar producto…" class="{{ $in }} mt-1">
                            <div x-show="productos.length" @click.outside="productos = []" class="absolute z-20 mt-1 w-full bg-white rounded-lg shadow-xl border max-h-56 overflow-y-auto">
                                <template x-for="pr in productos" :key="pr.IdProducto">
                                    <button type="button" @click="premio.IdProducto = pr.IdProducto; premio.producto = pr.pronom; if (!premio.nombre) premio.nombre = pr.pronom; productos = []; buscaProd = ''"
                                            class="w-full text-left px-3 py-2 hover:bg-amber-50" x-text="pr.pronom"></button>
                                </template>
                            </div>
                        </div>
                        <label class="sm:col-span-2" x-show="premio.IdProducto">Cantidad que entrega<input type="number" step="0.001" min="0.001" name="cantidad" x-model="premio.cantidad" class="{{ $in }} mt-1"></label>
                        <label class="sm:col-span-3">Vence el <span class="text-gray-400">(opcional)</span><input type="date" name="vence" x-model="premio.vence" class="{{ $in }} mt-1"></label>
                        <label class="sm:col-span-3">Detalle <span class="text-gray-400">(opcional)</span><input name="descripcion" x-model="premio.descripcion" maxlength="255" class="{{ $in }} mt-1"></label>
                        <div class="sm:col-span-6 flex gap-2">
                            <button class="px-4 py-2 rounded-lg bg-amber-500 text-white font-bold" x-text="premio.premio_id ? 'Guardar cambios' : 'Agregar premio'"></button>
                            <button type="button" x-show="premio.premio_id" @click="premio = vacio()" class="px-4 py-2 rounded-lg bg-white font-semibold text-gray-600">Nuevo</button>
                        </div>
                    </form>
                @endif
            </div>
        </div>

        {{-- Clientes --}}
        <div class="grid lg:grid-cols-[1fr_1.2fr] gap-5 items-start">
            <div class="bg-white rounded-2xl shadow-sm p-5 space-y-3">
                <h2 class="font-bold text-gray-700">3. Clientes</h2>
                <input x-model="q" @input.debounce.300ms="buscar()" placeholder="Buscar por DNI, RUC o nombre" class="{{ $in }}">
                <div class="divide-y border rounded-xl max-h-[460px] overflow-y-auto">
                    <template x-for="c in (q.trim().length >= 2 ? resultados : ranking)" :key="c.clicod">
                        <button type="button" @click="abrir(c.clicod)" class="w-full flex items-center gap-3 px-3 py-2 text-left hover:bg-violet-50" :class="ficha?.cliente.clicod === c.clicod ? 'bg-violet-50' : ''">
                            <span class="flex-1 min-w-0"><span class="block font-semibold text-gray-800 truncate text-sm" x-text="c.clinom"></span><span class="block text-xs text-gray-400" x-text="c.clinum"></span></span>
                            <span class="font-black text-violet-700 whitespace-nowrap" x-text="Number(c.puntos).toLocaleString() + ' pts'"></span>
                        </button>
                    </template>
                    <p x-show="!(q.trim().length >= 2 ? resultados : ranking).length" class="p-5 text-center text-sm text-gray-400" x-text="q.trim().length >= 2 ? 'Sin resultados.' : 'Aún ningún cliente tiene puntos.'"></p>
                </div>
                <p class="text-xs text-gray-400" x-show="q.trim().length < 2">Los 30 clientes con más puntos.</p>
            </div>

            <div class="bg-white rounded-2xl shadow-sm p-5">
                <p x-show="!ficha" class="text-center text-gray-400 py-16"><i class="fas fa-hand-pointer text-3xl"></i><br>Elige un cliente para ver sus puntos y canjear premios.</p>
                <template x-if="ficha">
                    <div class="space-y-4">
                        <div class="rounded-2xl bg-gradient-to-br from-violet-600 to-indigo-700 text-white p-5">
                            <p class="text-sm opacity-90" x-text="ficha.cliente.clinom + ' · ' + ficha.cliente.clinum"></p>
                            <p class="text-4xl font-black mt-1"><span x-text="Number(ficha.cliente.puntos).toLocaleString()"></span> <span class="text-lg">puntos</span></p>
                        </div>
                        <div>
                            <p class="font-semibold text-gray-700 text-sm mb-2">Canjear premio</p>
                            <div class="grid sm:grid-cols-2 gap-2">
                                @foreach ($premios->where('activo', 1)->where('vencido', false) as $p)
                                    <button type="button" @click="canjear({{ $p->premio_id }}, @js($p->nombre), {{ $p->puntos }})" :disabled="ficha.cliente.puntos < {{ $p->puntos }}"
                                            class="text-left rounded-xl border-2 p-3 disabled:opacity-40 disabled:cursor-not-allowed hover:border-amber-400"
                                            :class="ficha.cliente.puntos >= {{ $p->puntos }} ? 'border-amber-300 bg-amber-50' : 'border-gray-200'">
                                        <span class="block font-bold text-sm">{{ $p->nombre }}</span>
                                        <span class="block text-xs" :class="ficha.cliente.puntos >= {{ $p->puntos }} ? 'text-emerald-700 font-semibold' : 'text-gray-500'"
                                              x-text="ficha.cliente.puntos >= {{ $p->puntos }} ? '✔ Le alcanza · {{ $p->puntos }} pts' : 'Le faltan ' + ({{ $p->puntos }} - ficha.cliente.puntos) + ' pts'"></span>
                                    </button>
                                @endforeach
                            </div>
                            @if ($premios->where('activo', 1)->where('vencido', false)->isEmpty())<p class="text-sm text-gray-400">Crea premios arriba para poder canjear.</p>@endif
                        </div>
                        @if ($esAdmin)
                            <div class="rounded-xl bg-gray-50 p-3 grid grid-cols-[120px_1fr_auto] gap-2 items-end text-sm">
                                <label>Ajustar (+/-)<input type="number" x-model.number="ajuste.puntos" placeholder="50 ó -50" class="{{ $in }} mt-1"></label>
                                <label>Motivo<input x-model="ajuste.motivo" maxlength="150" placeholder="Ej. puntos de bienvenida" class="{{ $in }} mt-1"></label>
                                <button type="button" @click="ajustar()" class="px-3 py-2 rounded-lg bg-gray-800 text-white font-semibold">Aplicar</button>
                            </div>
                        @endif
                        <div>
                            <p class="font-semibold text-gray-700 text-sm mb-2">Historial</p>
                            <div class="divide-y border rounded-xl text-sm max-h-72 overflow-y-auto">
                                <template x-for="h in ficha.historial">
                                    <div class="flex items-center gap-3 px-3 py-2">
                                        <span class="w-16 text-[10px] font-black px-1.5 py-0.5 rounded text-center"
                                              :class="{ VENTA: 'bg-emerald-100 text-emerald-700', CANJE: 'bg-amber-100 text-amber-700', ANULACION: 'bg-rose-100 text-rose-700', AJUSTE: 'bg-gray-100 text-gray-700' }[h.tipo]" x-text="h.tipo"></span>
                                        <span class="flex-1 min-w-0 truncate" x-text="h.detalle"></span>
                                        <span class="font-bold whitespace-nowrap" :class="h.puntos > 0 ? 'text-emerald-600' : 'text-rose-600'" x-text="(h.puntos > 0 ? '+' : '') + h.puntos"></span>
                                        <span class="text-xs text-gray-400 w-24 text-right" x-text="h.fecha.slice(8, 10) + '/' + h.fecha.slice(5, 7) + ' ' + h.fecha.slice(11, 16)"></span>
                                    </div>
                                </template>
                                <p x-show="!ficha.historial.length" class="p-4 text-center text-gray-400">Sin movimientos.</p>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <script>
        function fidelizacion() {
            const RUTA = @json(url('fidelizacion'));
            const CSRF = @json(csrf_token());
            const vacio = () => ({ premio_id: '', nombre: '', descripcion: '', puntos: '', activo: true, IdProducto: null, producto: '', cantidad: 1, vence: '' });
            const post = (url, datos) => fetch(url, { method: 'POST', body: JSON.stringify(datos),
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF } })
                .then(async r => { const d = await r.json(); return r.status === 422 ? { ok: false, mensaje: Object.values(d.errors || {})[0]?.[0] } : d; })
                .catch(() => ({ ok: false, mensaje: 'Sin conexión con el servidor.' }));
            return {
                buscaProd: '', productos: [],
                async buscarProducto() {
                    if (this.buscaProd.trim().length < 2) { this.productos = []; return; }
                    this.productos = await fetch(RUTA + '/productos?q=' + encodeURIComponent(this.buscaProd.trim()), { headers: { 'Accept': 'application/json' } }).then(r => r.json()).catch(() => []);
                },
                q: '', resultados: [], ranking: @js($ranking), ficha: null, premio: vacio(), vacio, ajuste: { puntos: null, motivo: '' },
                async buscar() {
                    if (this.q.trim().length < 2) { this.resultados = []; return; }
                    this.resultados = await fetch(RUTA + '/clientes?q=' + encodeURIComponent(this.q.trim()), { headers: { 'Accept': 'application/json' } }).then(r => r.json()).catch(() => []);
                },
                async abrir(id) {
                    this.ficha = await fetch(RUTA + '/clientes/' + id, { headers: { 'Accept': 'application/json' } }).then(r => r.json()).catch(() => null);
                },
                async canjear(premioId, nombre, puntos) {
                    if (!confirm(`¿Canjear "${nombre}" por ${puntos} puntos para ${this.ficha.cliente.clinom}?`)) return;
                    const r = await post(RUTA + '/clientes/' + this.ficha.cliente.clicod + '/canjear', { premio_id: premioId });
                    tushpaAviso(r.mensaje, r.ok);
                    if (r.ok) this.abrir(this.ficha.cliente.clicod);
                },
                async ajustar() {
                    const r = await post(RUTA + '/clientes/' + this.ficha.cliente.clicod + '/ajustar', this.ajuste);
                    tushpaAviso(r.mensaje, r.ok ? true : 'aviso');
                    if (r.ok) { this.ajuste = { puntos: null, motivo: '' }; this.abrir(this.ficha.cliente.clicod); }
                },
            };
        }
    </script>
@endsection
