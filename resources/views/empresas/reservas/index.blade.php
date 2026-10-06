@extends('layouts.app')
@section('title', 'Reservas')
@section('content')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    @include('empresas.partials.alert')

    @php
        $in = 'block w-full rounded-lg border-gray-300 text-sm focus:border-violet-500 focus:ring-violet-500';
        $f = \Carbon\Carbon::parse($fecha)->locale('es');
        $colores = ['Pendiente' => 'bg-amber-100 text-amber-800', 'Confirmada' => 'bg-green-100 text-green-700', 'Atendida' => 'bg-indigo-100 text-indigo-700',
                    'Cancelada' => 'bg-gray-200 text-gray-600', 'No asistió' => 'bg-red-100 text-red-700'];
        $activas = $reservas->whereIn('estado', ['Pendiente', 'Confirmada']);
        // Datos para editar desde el modal
        $paraEditar = $reservas->mapWithKeys(fn($r) => [$r->res_id => [
            'res_id' => $r->res_id, 'nombre_cliente' => $r->nombre_cliente, 'telefono' => $r->telefono,
            'cantidad_personas' => $r->cantidad_personas, 'fecha_reserva' => $r->fecha_reserva,
            'hora_inicio' => substr($r->hora_inicio, 0, 5), 'hora_fin' => substr($r->hora_fin, 0, 5),
            'pis_id' => $r->pis_id, 'mes_id' => $r->mes_id, 'observacion' => $r->observacion,
            'items' => ($detalles[$r->res_id] ?? collect())->map(fn($d) => ['IdProducto' => $d->IdProducto, 'nombre' => $d->pronom,
                'cantidad' => (float) $d->cantidad, 'precio' => (float) $d->precio_unitario, 'nota' => $d->nota_producto])->values(),
        ]]);
    @endphp

    <div x-data="reservas()">
        {{-- Navegación por fecha --}}
        <div class="bg-white rounded-2xl shadow-sm p-4 mb-4 flex flex-wrap items-center gap-3">
            <a href="{{ route('reservas.index', ['fecha' => $f->copy()->subDay()->toDateString()]) }}" class="px-3 py-2 rounded-lg bg-gray-100 hover:bg-gray-200">◀</a>
            <form><input type="date" name="fecha" value="{{ $fecha }}" onchange="this.form.submit()" class="rounded-lg border-gray-300 text-sm"></form>
            <a href="{{ route('reservas.index', ['fecha' => $f->copy()->addDay()->toDateString()]) }}" class="px-3 py-2 rounded-lg bg-gray-100 hover:bg-gray-200">▶</a>
            <h2 class="text-lg font-bold text-gray-800 capitalize">{{ $f->isoFormat('dddd D [de] MMMM') }}</h2>
            <button type="button" @click="nueva()" class="ml-auto px-4 py-2 rounded-xl bg-violet-600 text-white text-sm font-semibold hover:bg-violet-700"><i class="fas fa-plus"></i> Nueva reserva</button>
        </div>

        {{-- Próximos 7 días --}}
        <div class="flex flex-wrap gap-2 mb-4">
            @for ($i = 0; $i < 7; $i++)
                @php $d = now()->addDays($i); $n = $proximos[$d->toDateString()] ?? 0; @endphp
                <a href="{{ route('reservas.index', ['fecha' => $d->toDateString()]) }}"
                   class="px-3 py-2 rounded-xl text-sm text-center min-w-20 {{ $fecha === $d->toDateString() ? 'bg-violet-600 text-white' : 'bg-white shadow-sm hover:bg-violet-50' }}">
                    <span class="block text-xs capitalize opacity-80">{{ $i === 0 ? 'Hoy' : ($i === 1 ? 'Mañana' : $d->locale('es')->isoFormat('ddd D')) }}</span>
                    <span class="font-bold">{{ $n }} reserva{{ $n == 1 ? '' : 's' }}</span>
                </a>
            @endfor
        </div>

        {{-- Resumen --}}
        <div class="grid grid-cols-3 gap-3 mb-4">
            <div class="bg-white rounded-2xl shadow-sm p-4"><p class="text-xs text-gray-500 uppercase">Reservas activas</p><p class="text-2xl font-bold text-violet-700">{{ $activas->count() }}</p></div>
            <div class="bg-white rounded-2xl shadow-sm p-4"><p class="text-xs text-gray-500 uppercase">Personas esperadas</p><p class="text-2xl font-bold text-gray-800">{{ $activas->sum('cantidad_personas') }}</p></div>
            <div class="bg-white rounded-2xl shadow-sm p-4"><p class="text-xs text-gray-500 uppercase">Pedido anticipado</p><p class="text-2xl font-bold text-emerald-700">S/ {{ number_format($activas->sum('total_estimado'), 2) }}</p></div>
        </div>

        {{-- Lista --}}
        <div class="space-y-3">
            @forelse ($reservas as $r)
                @php
                    $tel = preg_replace('/\D/', '', (string) $r->telefono);
                    if (strlen($tel) === 9) { $tel = '51' . $tel; }
                    $msg = "Hola {$r->nombre_cliente}, le confirmamos su reserva para {$r->cantidad_personas} persona(s) el " . $f->isoFormat('dddd D [de] MMMM') . ' a las ' . substr($r->hora_inicio, 0, 5) . '. ¡Lo esperamos!';
                @endphp
                <div class="bg-white rounded-2xl shadow-sm p-4 flex flex-wrap gap-4 items-start {{ in_array($r->estado, ['Cancelada', 'No asistió']) ? 'opacity-60' : '' }}">
                    <div class="text-center w-20 shrink-0">
                        <p class="text-2xl font-extrabold text-violet-700">{{ substr($r->hora_inicio, 0, 5) }}</p>
                        <p class="text-xs text-gray-400">hasta {{ substr($r->hora_fin, 0, 5) }}</p>
                    </div>
                    <div class="flex-1 min-w-60">
                        <p class="font-bold text-gray-800 text-lg">{{ $r->nombre_cliente }}
                            <span class="ml-2 px-2 py-0.5 rounded-full text-xs font-bold {{ $colores[$r->estado] ?? 'bg-gray-100' }}">{{ $r->estado }}</span></p>
                        <p class="text-sm text-gray-600">
                            <i class="fas fa-users text-gray-400"></i> {{ $r->cantidad_personas }} persona(s)
                            @if ($r->telefono) · <i class="fas fa-phone text-gray-400"></i> {{ $r->telefono }} @endif
                            · <i class="fas fa-chair text-gray-400"></i> {{ $r->mes_nom ? ($r->pis_nom ? $r->pis_nom . ' / ' : '') . $r->mes_nom : 'Sin mesa elegida' }}
                        </p>
                        @if (!empty($detalles[$r->res_id]))
                            <p class="text-sm text-gray-600 mt-1">🍽 {{ $detalles[$r->res_id]->map(fn($d) => rtrim(rtrim(number_format($d->cantidad, 2), '0'), '.') . ' ' . $d->pronom . ($d->nota_producto ? " ({$d->nota_producto})" : ''))->implode(', ') }}
                                <span class="text-emerald-700 font-semibold">· S/ {{ number_format($r->total_estimado, 2) }}</span></p>
                        @endif
                        @if ($r->observacion)<p class="text-sm text-amber-700 mt-1">» {{ $r->observacion }}</p>@endif
                        <p class="text-xs text-gray-400 mt-1">Registró: {{ $r->registrado_por }}@if ($r->ped_id) · Pedido N° {{ $r->ped_id }}@endif</p>
                    </div>
                    <div class="flex flex-wrap gap-2 items-center">
                        @if (in_array($r->estado, ['Pendiente', 'Confirmada']))
                            @if ($tel)
                                <a href="https://wa.me/{{ $tel }}?text={{ rawurlencode($msg) }}" target="_blank" rel="noopener" class="px-3 py-1.5 rounded-lg bg-green-50 text-green-700 text-sm font-semibold hover:bg-green-100"><i class="fab fa-whatsapp"></i> Confirmar por WhatsApp</a>
                            @endif
                            @if ($r->estado === 'Pendiente')
                                <button type="button" @click="estado({{ $r->res_id }}, 'Confirmada')" class="px-3 py-1.5 rounded-lg bg-green-600 text-white text-sm font-semibold">Confirmada</button>
                            @endif
                            <button type="button" @click="editar({{ $r->res_id }})" class="px-3 py-1.5 rounded-lg bg-gray-100 text-gray-700 text-sm font-semibold">Editar</button>
                            <button type="button" @click="estado({{ $r->res_id }}, 'No asistió')" class="px-3 py-1.5 rounded-lg bg-red-50 text-red-700 text-sm">No vino</button>
                            <button type="button" @click="estado({{ $r->res_id }}, 'Cancelada')" class="px-3 py-1.5 rounded-lg bg-gray-50 text-gray-500 text-sm">Cancelar</button>
                        @endif
                    </div>
                </div>
            @empty
                <div class="bg-white rounded-2xl shadow-sm p-10 text-center text-gray-400">
                    <div class="text-5xl mb-2">📅</div>
                    No hay reservas para este día.
                </div>
            @endforelse
        </div>
        <p class="text-xs text-gray-400 mt-3">Cuando el cliente llega, atiéndelo desde <strong>Comandas → Reservas</strong>: se le asigna la mesa y su pedido anticipado pasa a la comanda.</p>

        {{-- Modal de reserva --}}
        <div x-show="form" x-cloak class="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4" @click.self="form = null">
            <template x-if="form">
                <div class="bg-white rounded-2xl shadow-xl w-full max-w-2xl max-h-[92vh] overflow-y-auto">
                    <div class="px-5 py-3 border-b flex justify-between items-center sticky top-0 bg-white">
                        <h3 class="font-bold text-gray-800" x-text="form.res_id ? 'Editar reserva' : 'Nueva reserva'"></h3>
                        <button @click="form = null" class="text-gray-400 text-2xl leading-none">&times;</button>
                    </div>
                    <div class="p-5 space-y-4 text-sm">
                        <div class="grid sm:grid-cols-3 gap-3">
                            <label class="sm:col-span-2">Nombre del cliente *<input x-model="form.nombre_cliente" maxlength="150" class="{{ $in }} uppercase" placeholder="¿A nombre de quién?"></label>
                            <label>Teléfono<input x-model="form.telefono" maxlength="20" inputmode="tel" class="{{ $in }}" placeholder="9xx xxx xxx"></label>
                            <label>¿Cuántas personas? *<input type="number" min="1" x-model.number="form.cantidad_personas" class="{{ $in }} text-lg font-bold"></label>
                            <label>Fecha *<input type="date" x-model="form.fecha_reserva" class="{{ $in }}"></label>
                            <div class="grid grid-cols-2 gap-2">
                                <label>Llega *<input type="time" x-model="form.hora_inicio" @change="ajustarSalida()" class="{{ $in }}"></label>
                                <label>Hasta<input type="time" x-model="form.hora_fin" class="{{ $in }}"></label>
                            </div>
                        </div>

                        <div class="grid sm:grid-cols-2 gap-3">
                            <label>Zona (opcional)
                                <select x-model="form.pis_id" @change="form.mes_id = ''" class="{{ $in }}">
                                    <option value="">Cualquiera</option>
                                    @foreach ($pisos as $p)<option value="{{ $p->pis_id }}">{{ $p->pis_nom }}</option>@endforeach
                                </select></label>
                            <label>Mesa que prefiere (opcional)
                                <select x-model="form.mes_id" class="{{ $in }}">
                                    <option value="">La que esté libre al llegar</option>
                                    <template x-for="m in mesasDeZona()" :key="m.mes_id">
                                        <option :value="m.mes_id" x-text="m.mes_nom"></option>
                                    </template>
                                </select></label>
                        </div>

                        <div>
                            <p class="font-semibold text-gray-700 mb-1">Platos que van a pedir (opcional)</p>
                            <div class="flex gap-2 mb-2">
                                <input list="lista_productos" x-model="buscar" @keydown.enter.prevent="agregar()" placeholder="Busca el plato y presiona Enter" class="flex-1 rounded-lg border-gray-300 text-sm">
                                <button type="button" @click="agregar()" class="px-4 rounded-lg bg-violet-600 text-white text-sm font-semibold">Agregar</button>
                            </div>
                            <template x-for="(it, i) in form.items" :key="i">
                                <div class="flex gap-2 items-center mb-1">
                                    <input type="number" min="0.5" step="0.5" x-model.number="it.cantidad" class="w-20 rounded-lg border-gray-300 text-sm text-center">
                                    <span class="flex-1 font-medium" x-text="it.nombre"></span>
                                    <input x-model="it.nota" maxlength="100" placeholder="nota (ej. sin ají)" class="w-40 rounded-lg border-gray-300 text-xs">
                                    <span class="w-20 text-right text-gray-600" x-text="'S/ ' + (it.cantidad * it.precio).toFixed(2)"></span>
                                    <button type="button" @click="form.items.splice(i, 1)" class="text-red-600 font-bold px-1">✕</button>
                                </div>
                            </template>
                            <p x-show="form.items.length" class="text-right font-semibold text-emerald-700" x-text="'Total estimado: S/ ' + total().toFixed(2)"></p>
                        </div>

                        <label class="block">Observación (cumpleaños, silla de bebé, alergias...)
                            <textarea x-model="form.observacion" rows="2" maxlength="500" class="{{ $in }}"></textarea></label>

                        <p class="text-red-600 text-sm" x-text="error"></p>
                        <button type="button" @click="guardar()" :disabled="enviando" class="w-full py-3 rounded-xl bg-violet-600 text-white font-bold hover:bg-violet-700 disabled:opacity-50">GUARDAR RESERVA</button>
                    </div>
                </div>
            </template>
        </div>
        <datalist id="lista_productos">
            @foreach ($productos as $p)<option value="{{ $p->pronom }}"></option>@endforeach
        </datalist>
    </div>

    <script>
        function reservas() {
            const MESAS = @json($mesas);
            const PRODUCTOS = @json($productos);
            const RESERVAS = @json($paraEditar);
            const CSRF = '{{ csrf_token() }}';
            const URL = "{{ url('reservas') }}";
            const post = (url, data) => fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': CSRF }, body: JSON.stringify(data) })
                .then(async r => { const d = await r.json().catch(() => ({ success: false, message: 'Error del servidor.' })); if (r.status === 422) d.message = Object.values(d.errors)[0][0]; return d; });

            return {
                form: null, buscar: '', error: '', enviando: false,
                nueva() {
                    this.error = '';
                    this.form = { res_id: null, nombre_cliente: '', telefono: '', cantidad_personas: 2, fecha_reserva: @json($fecha),
                        hora_inicio: '20:00', hora_fin: '22:00', pis_id: '', mes_id: '', observacion: '', items: [] };
                },
                editar(id) {
                    this.error = '';
                    const r = JSON.parse(JSON.stringify(RESERVAS[id]));
                    r.pis_id = r.pis_id ?? ''; r.mes_id = r.mes_id ?? '';
                    this.form = r;
                },
                mesasDeZona() { return MESAS.filter(m => !this.form.pis_id || String(m.pis_id) === String(this.form.pis_id)); },
                ajustarSalida() {
                    // Por defecto se reserva la mesa 2 horas
                    const [h, m] = this.form.hora_inicio.split(':').map(Number);
                    const fin = (h + 2) % 24;
                    this.form.hora_fin = String(fin < h ? 23 : fin).padStart(2, '0') + ':' + (fin < h ? '59' : String(m).padStart(2, '0'));
                },
                agregar() {
                    const p = PRODUCTOS.find(x => x.pronom === this.buscar.trim());
                    if (!p) { this.error = 'Elige el plato de la lista.'; return; }
                    this.error = '';
                    const ya = this.form.items.find(i => i.IdProducto === p.IdProducto);
                    if (ya) ya.cantidad++; else this.form.items.push({ IdProducto: p.IdProducto, nombre: p.pronom, cantidad: 1, precio: Number(p.propun), nota: '' });
                    this.buscar = '';
                },
                total() { return this.form.items.reduce((a, i) => a + (Number(i.cantidad) || 0) * i.precio, 0); },
                async guardar(forzar = false) {
                    if (!this.form.nombre_cliente.trim()) { this.error = 'Escribe el nombre del cliente.'; return; }
                    this.enviando = true;
                    const datos = Object.assign({}, this.form, { forzar, pis_id: this.form.pis_id || null, mes_id: this.form.mes_id || null,
                        items: this.form.items.map(i => ({ IdProducto: i.IdProducto, cantidad: i.cantidad, nota: i.nota })) });
                    const res = await post(this.form.res_id ? `${URL}/${this.form.res_id}` : URL, datos);
                    this.enviando = false;
                    if (res.choque) { if (confirm(res.message)) this.guardar(true); return; }
                    if (!res.success) { this.error = res.message || 'No se pudo guardar.'; return; }
                    window.location.href = "{{ route('reservas.index') }}?fecha=" + this.form.fecha_reserva;
                },
                async estado(id, estado) {
                    if (estado !== 'Confirmada' && !confirm(`¿Marcar la reserva como "${estado}"?`)) return;
                    const res = await post(`${URL}/${id}/estado`, { estado });
                    if (!res.success) { alert(res.message); return; }
                    location.reload();
                },
            };
        }
    </script>
@endsection
