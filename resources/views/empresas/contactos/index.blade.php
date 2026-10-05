@extends('layouts.app')
@section('title', $tipo === 'clientes' ? 'Clientes' : 'Proveedores')
@section('content')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    @include('empresas.partials.alert')
    @php
        $esCliente = $tipo === 'clientes';
        $singular = $esCliente ? 'cliente' : 'proveedor';
        $palabraSaldo = $esCliente ? 'Por cobrar' : 'Por pagar';
        $colores = ['bg-indigo-500', 'bg-emerald-500', 'bg-amber-500', 'bg-rose-500', 'bg-sky-500', 'bg-violet-500', 'bg-teal-500'];
    @endphp

    <div x-data="contactos(@js($tipo))">
        {{-- Resumen --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
            <div class="bg-white rounded-2xl shadow-sm p-4"><p class="text-xs text-gray-500">{{ $esCliente ? 'Clientes' : 'Proveedores' }} registrados</p><p class="text-2xl font-black text-gray-800">{{ (int) $resumen->n }}</p></div>
            <div class="bg-white rounded-2xl shadow-sm p-4"><p class="text-xs text-gray-500">Activos</p><p class="text-2xl font-black text-emerald-600">{{ (int) $resumen->activos }}</p></div>
            <div class="bg-white rounded-2xl shadow-sm p-4"><p class="text-xs text-gray-500">{{ $esCliente ? 'Que ya compraron' : 'A los que les compraste' }}</p><p class="text-2xl font-black text-indigo-700">{{ (int) $resumen->con_operaciones }}</p></div>
            <div class="bg-white rounded-2xl shadow-sm p-4"><p class="text-xs text-gray-500">{{ $palabraSaldo }} (total)</p><p class="text-2xl font-black {{ $esCliente ? 'text-amber-600' : 'text-rose-600' }}">S/ {{ number_format((float) $resumen->saldo, 2) }}</p></div>
        </div>

        {{-- Filtros --}}
        <div class="flex flex-col lg:flex-row gap-3 mb-4">
            <form method="GET" class="bg-white rounded-2xl shadow-sm p-3 flex flex-wrap items-end gap-2 flex-1">
                <label class="text-sm flex-1 min-w-[200px]">Buscar
                    <input name="q" value="{{ $q }}" placeholder="{{ $esCliente ? 'Nombre, DNI/RUC o teléfono' : 'Razón social, RUC o contacto' }}…" class="block w-full rounded-lg border-gray-300 text-sm"></label>
                <label class="text-sm">Estado
                    <select name="estado" onchange="this.form.submit()" class="block rounded-lg border-gray-300 text-sm">
                        @foreach (['activos' => 'Activos', 'inactivos' => 'Inactivos', 'todos' => 'Todos'] as $v => $n)<option value="{{ $v }}" @selected($estado === $v)>{{ $n }}</option>@endforeach
                    </select></label>
                <label class="text-sm">Ordenar por
                    <select name="orden" onchange="this.form.submit()" class="block rounded-lg border-gray-300 text-sm">
                        @foreach (['nombre' => 'Nombre', 'total' => $esCliente ? 'Más compras' : 'Más comprado', 'ultima' => 'Última operación', 'saldo' => $palabraSaldo] as $v => $n)<option value="{{ $v }}" @selected($orden === $v)>{{ $n }}</option>@endforeach
                    </select></label>
                <button class="px-4 py-2 rounded-xl bg-gray-100 text-gray-700 text-sm font-semibold"><i class="fas fa-magnifying-glass"></i></button>
                <a href="{{ request()->fullUrlWithQuery(['excel' => 1]) }}" class="px-3 py-2 rounded-xl bg-green-600 text-white text-sm font-semibold"><i class="fas fa-file-excel"></i> Excel</a>
            </form>
            <button type="button" @click="nuevo()" class="px-5 py-3 rounded-2xl bg-indigo-600 text-white text-sm font-bold hover:bg-indigo-700 shadow-sm self-stretch lg:self-auto"><i class="fas fa-plus"></i> Nuevo {{ $singular }}</button>
        </div>

        {{-- Lista --}}
        <div class="bg-white rounded-2xl shadow-sm overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-700 text-white text-xs uppercase">
                    <tr><th class="px-4 py-2 text-left">{{ $esCliente ? 'Cliente' : 'Proveedor' }}</th><th class="px-3 py-2 text-left">Contacto</th>
                        <th class="px-3 py-2 text-right">{{ $esCliente ? 'Compras' : 'Compras' }}</th><th class="px-3 py-2 text-right">Total</th>
                        <th class="px-3 py-2 text-right">{{ $palabraSaldo }}</th><th class="px-3 py-2 text-left">Última</th><th class="px-3 py-2"></th></tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($contactos as $c)
                        <tr class="hover:bg-gray-50 {{ $c->activo ? '' : 'opacity-50' }}">
                            <td class="px-4 py-2">
                                <div class="flex items-center gap-3">
                                    <span class="w-9 h-9 shrink-0 rounded-xl flex items-center justify-center text-white font-black {{ $colores[$c->id % count($colores)] }}">{{ mb_substr($c->nombre, 0, 1) }}</span>
                                    <span class="min-w-0"><span class="block font-semibold text-gray-800 truncate max-w-[320px]">{{ $c->nombre }}</span>
                                        <span class="block text-xs text-gray-400">{{ $docs[$c->tdicod] ?? 'DOC' }} {{ $c->doc }}{{ $c->activo ? '' : ' · INACTIVO' }}
                                            @if ($esCliente && ($c->mensual ?? false))<span class="ml-1 px-1.5 rounded bg-violet-100 text-violet-700 font-semibold">MENSUAL S/ {{ number_format($c->monto, 2) }}</span>@endif</span></span>
                                </div>
                            </td>
                            <td class="px-3 py-2 text-xs text-gray-600">
                                @if ($c->telefono)<span class="block"><i class="fas fa-phone text-gray-400 w-4"></i>{{ $c->telefono }}</span>@endif
                                @if ($c->correo)<span class="block truncate max-w-[200px]"><i class="fas fa-envelope text-gray-400 w-4"></i>{{ $c->correo }}</span>@endif
                                @if ($c->contacto)<span class="block"><i class="fas fa-user text-gray-400 w-4"></i>{{ $c->contacto }}</span>@endif
                                @if (!$c->telefono && !$c->correo && !$c->contacto)<span class="text-gray-300">—</span>@endif
                            </td>
                            <td class="px-3 py-2 text-right">{{ (int) $c->operaciones }}</td>
                            <td class="px-3 py-2 text-right font-semibold whitespace-nowrap">{{ number_format((float) $c->total, 2) }}</td>
                            <td class="px-3 py-2 text-right whitespace-nowrap {{ $c->saldo > 0 ? ($esCliente ? 'text-amber-600 font-bold' : 'text-rose-600 font-bold') : 'text-gray-300' }}">{{ $c->saldo > 0 ? number_format((float) $c->saldo, 2) : '—' }}</td>
                            <td class="px-3 py-2 text-xs text-gray-500 whitespace-nowrap">{{ $c->ultima ? \Carbon\Carbon::parse($c->ultima)->format('d/m/Y') : '—' }}</td>
                            <td class="px-3 py-2 text-right whitespace-nowrap">
                                <button type="button" @click="verHistorial({{ $c->id }}, @js($c->nombre))" title="Historial" class="text-emerald-600 px-1"><i class="fas fa-clock-rotate-left"></i></button>
                                @if ($c->telefono)
                                    <a href="https://wa.me/{{ strlen(preg_replace('/\D/', '', $c->telefono)) === 9 ? '51' : '' }}{{ preg_replace('/\D/', '', $c->telefono) }}" target="_blank" rel="noopener" title="WhatsApp" class="text-green-600 px-1"><i class="fab fa-whatsapp"></i></a>
                                @endif
                                <button type="button" title="Editar" class="text-indigo-600 px-1"
                                        @click="editar({{ $c->id }}, @js(['tdicod' => $c->tdicod ?: ($c->doc && strlen($c->doc) === 11 ? '6' : '1'), 'doc' => $c->doc, 'nombre' => $c->nombre,
                                            'direccion' => $c->direccion === '--' ? '' : $c->direccion, 'telefono' => $c->telefono, 'correo' => $c->correo, 'contacto' => $c->contacto, 'activo' => (bool) $c->activo,
                                            'mensual' => (bool) ($c->mensual ?? false), 'comprobante' => $c->comprobante ?? '', 'monto' => (float) ($c->monto ?? 0)]))"><i class="fas fa-pen"></i></button>
                                <form method="POST" action="{{ route('contactos.eliminar', [$tipo, $c->id]) }}" class="inline"
                                      onsubmit="return confirm('{{ $c->operaciones ? 'Tiene operaciones: se desactivará. ¿Continuar?' : '¿Eliminar este ' . $singular . '?' }}')">
                                    @csrf @method('DELETE')<button class="text-rose-500 px-1" title="{{ $c->operaciones ? 'Desactivar' : 'Eliminar' }}"><i class="fas fa-trash"></i></button></form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-10 text-center text-gray-400">No hay {{ $tipo }} con ese filtro.
                            {{ $esCliente ? 'Los clientes se crean solos al vender con DNI o RUC, o puedes registrarlos aquí.' : 'Los proveedores se crean solos al registrar una compra, o puedes registrarlos aquí.' }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $contactos->links() }}</div>

        {{-- Modal formulario --}}
        <div x-show="form" x-cloak class="fixed inset-0 z-50 bg-black/40 flex items-end sm:items-center justify-center sm:p-4" @click.self="form = null">
            <form @submit.prevent="guardar()" class="bg-white w-full sm:max-w-lg rounded-t-2xl sm:rounded-2xl shadow-xl max-h-[95vh] overflow-y-auto">
                <template x-if="form">
                    <div>
                        <div class="px-5 py-3 border-b flex justify-between items-center">
                            <h3 class="font-bold text-gray-800" x-text="(id ? 'Editar ' : 'Nuevo ') + '{{ $singular }}'"></h3>
                            <button type="button" @click="form = null" class="text-gray-400 text-2xl leading-none">&times;</button>
                        </div>
                        <div class="p-5 space-y-3 text-sm">
                            <div class="grid grid-cols-3 gap-3">
                                <label>Documento
                                    <select x-model="form.tdicod" class="block w-full mt-1 rounded-lg border-gray-300">
                                        @foreach ($docs as $v => $n)<option value="{{ $v }}">{{ $n }}</option>@endforeach
                                    </select></label>
                                <label class="col-span-2">Número
                                    <div class="flex gap-2 mt-1">
                                        <input x-model="form.doc" @input="autoTipo()" @keydown.enter.prevent="consultar()" maxlength="15" inputmode="numeric" required class="flex-1 min-w-0 rounded-lg border-gray-300 font-semibold">
                                        <button type="button" @click="consultar()" :disabled="buscando" title="Buscar en SUNAT (RUC)" class="px-3 rounded-lg bg-indigo-600 text-white disabled:opacity-50">
                                            <i class="fas" :class="buscando ? 'fa-spinner fa-spin' : 'fa-magnifying-glass'"></i></button>
                                    </div></label>
                            </div>
                            <p x-show="sunat" class="text-xs" :class="sunatOk ? 'text-emerald-600' : 'text-amber-600'" x-text="sunat"></p>
                            <label class="block">{{ $esCliente ? 'Nombre / razón social' : 'Razón social' }}<input x-model="form.nombre" required maxlength="200" class="block w-full mt-1 rounded-lg border-gray-300 uppercase"></label>
                            <label class="block">Dirección<input x-model="form.direccion" maxlength="200" class="block w-full mt-1 rounded-lg border-gray-300 uppercase"></label>
                            <div class="grid grid-cols-2 gap-3">
                                <label>Teléfono / celular<input x-model="form.telefono" maxlength="20" inputmode="tel" class="block w-full mt-1 rounded-lg border-gray-300"></label>
                                <label>Correo<input type="email" x-model="form.correo" maxlength="50" class="block w-full mt-1 rounded-lg border-gray-300"></label>
                            </div>
                            @unless ($esCliente)
                                <label class="block">Persona de contacto<input x-model="form.contacto" maxlength="100" placeholder="Ej. vendedor que te atiende" class="block w-full mt-1 rounded-lg border-gray-300 uppercase"></label>
                            @endunless
                            @if ($esCliente)
                                <div class="rounded-xl border border-violet-200 bg-violet-50 p-3 space-y-2">
                                    <label class="flex items-center gap-2 font-semibold text-violet-800"><input type="checkbox" x-model="form.mensual" class="rounded text-violet-600">
                                        Facturación mensual <span class="font-normal text-xs text-violet-600">(sale en Venta Masiva)</span></label>
                                    <div x-show="form.mensual" class="grid grid-cols-2 gap-3">
                                        <label>Comprobante
                                            <select x-model="form.comprobante" class="block w-full mt-1 rounded-lg border-gray-300">
                                                <option value="01">Factura</option><option value="03">Boleta</option><option value="13">Nota de venta</option>
                                            </select></label>
                                        <label>Monto mensual S/<input type="number" step="0.01" min="0" x-model="form.monto" class="block w-full mt-1 rounded-lg border-gray-300 text-right"></label>
                                    </div>
                                </div>
                            @endif
                            <label class="flex items-center gap-2"><input type="checkbox" x-model="form.activo" class="rounded"> Activo</label>
                            <p class="text-rose-600 font-semibold" x-text="error"></p>
                        </div>
                        <div class="px-5 py-3 border-t flex justify-end gap-2">
                            <button type="button" @click="form = null" class="px-4 py-2 rounded-xl bg-gray-100 font-semibold">Cancelar</button>
                            <button :disabled="enviando" class="px-6 py-2 rounded-xl bg-indigo-600 text-white font-semibold disabled:opacity-50" x-text="enviando ? 'Guardando…' : 'Guardar'"></button>
                        </div>
                    </div>
                </template>
            </form>
        </div>

        {{-- Panel historial --}}
        <div x-show="hist.abierto" x-cloak class="fixed inset-0 z-50 bg-black/40 flex justify-end" @click.self="hist.abierto = false" @keydown.escape.window="hist.abierto = false">
            <div class="bg-white w-full max-w-md h-full flex flex-col shadow-2xl" x-show="hist.abierto" x-transition:enter="transition duration-200" x-transition:enter-start="translate-x-full">
                <div class="px-5 py-4 border-b flex items-start gap-3">
                    <div class="flex-1 min-w-0"><p class="text-xs uppercase text-gray-400 font-bold">Historial</p><h3 class="font-bold text-gray-800 truncate" x-text="hist.nombre"></h3></div>
                    <button type="button" @click="hist.abierto = false" class="text-gray-400 text-2xl leading-none">&times;</button>
                </div>
                <div class="flex-1 overflow-y-auto divide-y divide-gray-100">
                    <p x-show="hist.cargando" class="p-6 text-center text-gray-400">Cargando…</p>
                    <template x-for="o in hist.filas">
                        <a :href="o.url" target="_blank" class="flex items-center gap-3 px-5 py-3 hover:bg-gray-50">
                            <div class="flex-1 min-w-0"><p class="font-semibold text-gray-700 text-sm"><span x-text="o.doc"></span> <span class="font-mono" x-text="o.numero"></span></p>
                                <p class="text-xs text-gray-400" x-text="fecha(o.fecha) + (o.detalle ? ' · ' + o.detalle : '')"></p></div>
                            <span class="font-bold text-sm" :class="o.total < 0 ? 'text-rose-600' : 'text-gray-800'" x-text="'S/ ' + n2(o.total)"></span>
                        </a>
                    </template>
                    <p x-show="!hist.cargando && !hist.filas.length" class="p-6 text-center text-gray-400 text-sm">Sin operaciones registradas.</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        function contactos(tipo) {
            const base = @js(url('/')) + '/' + tipo;
            const vacio = () => ({ tdicod: '6', doc: '', nombre: '', direccion: '', telefono: '', correo: '', contacto: '', activo: true, mensual: false, comprobante: '01', monto: 0 });
            return {
                form: null, id: null, error: '', enviando: false, buscando: false, sunat: '', sunatOk: true,
                hist: { abierto: false, cargando: false, nombre: '', filas: [] },
                n2(n) { return (Number(n) || 0).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
                fecha(f) { return f ? f.slice(0, 10).split('-').reverse().join('/') : ''; },
                nuevo() { this.id = null; this.form = vacio(); this.error = ''; this.sunat = ''; },
                editar(id, datos) { this.id = id; this.form = { ...vacio(), ...datos }; this.error = ''; this.sunat = ''; },
                autoTipo() {
                    const d = this.form.doc.trim();
                    if (/^\d{11}$/.test(d)) { this.form.tdicod = '6'; if (!this.form.nombre) this.consultar(); }
                    else if (/^\d{8}$/.test(d)) this.form.tdicod = '1';
                },
                async consultar() {
                    const d = this.form.doc.trim();
                    if (!/^\d{11}$/.test(d)) { this.sunatOk = false; this.sunat = 'La búsqueda en SUNAT es por RUC (11 dígitos).'; return; }
                    this.buscando = true; this.sunat = 'Consultando SUNAT…'; this.sunatOk = true;
                    try {
                        const r = await (await fetch(@js(url('contactos/sunat')) + '/' + d, { headers: { Accept: 'application/json' } })).json();
                        if (r.error) { this.sunatOk = false; this.sunat = r.error; return; }
                        this.form.nombre = r.nombre; this.form.direccion = r.direccion || this.form.direccion;
                        this.sunatOk = !r.estado || r.estado === 'ACTIVO';
                        this.sunat = '✔ Encontrado en SUNAT' + (r.estado ? ` · ${r.estado}` : '') + (r.condicion ? ` · ${r.condicion}` : '');
                    } catch (e) { this.sunatOk = false; this.sunat = 'No se pudo consultar SUNAT.'; }
                    finally { this.buscando = false; }
                },
                async guardar() {
                    this.enviando = true; this.error = '';
                    const r = await fetch(base + (this.id ? '/' + this.id : ''), { method: 'POST',
                        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': @js(csrf_token()) }, body: JSON.stringify(this.form) });
                    const d = await r.json().catch(() => ({}));
                    this.enviando = false;
                    if (r.status === 422) { this.error = Object.values(d.errors)[0][0]; return; }
                    if (!d.success) { this.error = 'No se pudo guardar.'; return; }
                    location.reload();
                },
                async verHistorial(id, nombre) {
                    this.hist = { abierto: true, cargando: true, nombre, filas: [] };
                    this.hist.filas = await (await fetch(base + '/' + id + '/historial', { headers: { Accept: 'application/json' } })).json();
                    this.hist.cargando = false;
                },
            };
        }
    </script>
@endsection
