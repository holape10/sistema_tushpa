@extends('layouts.app')
@section('title', 'Separados')

@section('content')
{{-- Separados (apartados): lo que los clientes dejaron separado con adelanto. Los abonos son los de Cuentas por cobrar. --}}
@php
    $soles = fn ($n) => 'S/ '.number_format((float) $n, 2);
    $colores = ['SEPARADO' => 'bg-sky-100 text-sky-800', 'VENCIDO' => 'bg-rose-100 text-rose-800', 'ENTREGADO' => 'bg-emerald-100 text-emerald-800', 'ANULADO' => 'bg-gray-100 text-gray-500'];
@endphp
<div x-data="separados(@js($medios->map(fn ($m) => ['id' => (int) $m->id_med_pag, 'nombre' => $m->nom_med_pag])))" class="max-w-6xl mx-auto space-y-4">
    @include('empresas.partials.alert')
    @if (session('error'))
        <div class="rounded-xl bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 text-sm">{{ session('error') }}</div>
    @endif

    <section class="rounded-3xl bg-gradient-to-br from-amber-500 to-orange-600 text-white p-5 sm:p-6 shadow-lg">
        <h1 class="text-2xl sm:text-3xl font-black">📦 Separados</h1>
        <p class="mt-1 text-white/90">Lo que tus clientes dejaron separado con un adelanto. Se separa en el <b>Punto Venta</b> con el botón <b>SEPARAR</b>:
            el producto ya no se puede vender a otro. Aquí cobras sus abonos, le avisas por WhatsApp y lo entregas cuando termina de pagar.</p>
        <div class="mt-3 flex flex-wrap gap-4 text-sm font-bold">
            <span>{{ $separados->whereIn('situacion', ['SEPARADO', 'VENCIDO'])->count() }} pendiente(s)</span>
            <span>Por cobrar: {{ $soles($separados->whereIn('situacion', ['SEPARADO', 'VENCIDO'])->sum('saldo')) }}</span>
            <span>{{ $separados->where('situacion', 'VENCIDO')->count() }} vencido(s)</span>
        </div>
    </section>

    <form class="flex flex-wrap gap-2">
        @foreach (['pendientes' => 'Pendientes', 'entregados' => 'Entregados', 'todos' => 'Todos'] as $clave => $nombre)
            <button name="ver" value="{{ $clave }}" class="h-10 px-4 rounded-xl text-sm font-bold {{ $filtro === $clave ? 'bg-gray-900 text-white' : 'bg-white text-gray-700 shadow-sm' }}">{{ $nombre }}</button>
        @endforeach
        <input type="search" name="q" value="{{ $q }}" placeholder="Cliente, DNI o celular" class="flex-1 min-w-[12rem] h-10 rounded-xl border-gray-300 text-sm">
        <a href="{{ route('pv.index') }}" class="h-10 px-4 inline-flex items-center rounded-xl bg-amber-500 text-white text-sm font-bold">+ Separar en el Punto Venta</a>
    </form>

    @forelse ($separados as $s)
        <div class="bg-white rounded-2xl shadow-sm p-4 {{ $s->situacion === 'VENCIDO' ? 'ring-2 ring-rose-200' : '' }}">
            <div class="flex flex-wrap items-start gap-3">
                <div class="flex-1 min-w-[14rem]">
                    <p class="font-extrabold text-gray-800">{{ $s->codigo }} · {{ $s->clinom }}
                        <span class="ml-1 px-2 py-0.5 rounded-full text-[11px] font-black {{ $colores[$s->situacion] }}">{{ $s->situacion }}</span></p>
                    <p class="text-xs text-gray-500">DNI {{ $s->clinum }}{{ $s->telefono ? ' · 📱 '.$s->telefono : '' }} · {{ $s->documento }}
                        · separado el {{ date('d/m/Y', strtotime($s->fecha)) }} · recoge hasta el <b class="{{ $s->situacion === 'VENCIDO' ? 'text-rose-600' : '' }}">{{ date('d/m/Y', strtotime($s->vence)) }}</b></p>
                    <p class="mt-1 text-sm text-gray-700">{{ implode(' · ', $s->productos) }}</p>
                </div>
                <div class="text-right text-sm">
                    <p>Total <b>{{ $soles($s->total) }}</b></p>
                    <p class="text-emerald-700">Pagó {{ $soles($s->pagado) }}</p>
                    <p class="text-lg font-black {{ $s->saldo > 0 ? 'text-rose-600' : 'text-emerald-700' }}">{{ $s->saldo > 0 ? 'Falta '.$soles($s->saldo) : '✓ Pagado' }}</p>
                </div>
            </div>
            @if (in_array($s->situacion, ['SEPARADO', 'VENCIDO'], true))
                <div class="mt-3 flex flex-wrap gap-2">
                    @if ($s->saldo > 0 && $s->cue_cob_id)
                        <button type="button" @click="abrir(@js(['cuenta' => (int) $s->cue_cob_id, 'codigo' => $s->codigo, 'cliente' => $s->clinom, 'saldo' => $s->saldo]))"
                                class="h-10 px-4 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold">💵 Cobrar abono</button>
                    @endif
                    <a href="{{ route('separados.whatsapp', $s->id) }}" target="_blank" rel="noopener" class="h-10 px-4 inline-flex items-center rounded-xl bg-green-50 text-green-700 hover:bg-green-100 text-sm font-bold">💬 Avisar por WhatsApp</a>
                    <form method="POST" action="{{ route('separados.entregar', $s->id) }}"
                          @if ($s->saldo > 0) onsubmit="return confirm(@js('Aún falta cobrar '.$soles($s->saldo).'. ¿Entregarlo igual? El saldo queda como deuda en Cuentas por cobrar.'))" @endif>
                        @csrf
                        @if ($s->saldo > 0)<input type="hidden" name="aunque_debe" value="1">@endif
                        <button class="h-10 px-4 rounded-xl text-sm font-bold {{ $s->saldo > 0 ? 'bg-gray-100 text-gray-600' : 'bg-indigo-600 hover:bg-indigo-700 text-white' }}">📦 Entregar</button>
                    </form>
                    <a href="{{ route('ventas.index', ['desde' => $s->fecha, 'hasta' => $s->fecha, 'cliente' => $s->clinum]) }}" class="h-10 px-3 inline-flex items-center text-xs text-gray-400 hover:text-rose-600"
                       title="Si el cliente no vuelve: anula la venta en el Panel de Ventas (el producto vuelve al stock)">¿No volvió? Anular en Ventas</a>
                </div>
            @elseif ($s->situacion === 'ENTREGADO')
                <p class="mt-2 text-xs text-emerald-700">Entregado el {{ date('d/m/Y H:i', strtotime($s->entregado_at)) }}</p>
            @endif
        </div>
    @empty
        <p class="bg-white rounded-2xl shadow-sm p-8 text-center text-gray-500">No hay separados {{ $filtro === 'pendientes' ? 'pendientes' : '' }}. Se separa desde el Punto Venta con el botón <b>SEPARAR</b>.</p>
    @endforelse

    {{-- Cobrar un abono: el mismo cobro de Cuentas por cobrar (recibo y caja) --}}
    <template x-if="pago">
        <div class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4" @click.self="pago = null">
            <form @submit.prevent="cobrar()" class="bg-white rounded-2xl shadow-xl w-full max-w-sm p-5 space-y-3">
                <p class="font-extrabold text-gray-800" x-text="'Abono a ' + pago.codigo"></p>
                <p class="text-sm text-gray-500" x-text="pago.cliente + ' · falta S/ ' + pago.saldo.toFixed(2)"></p>
                <label class="block text-xs font-semibold text-gray-500">Monto
                    <input type="number" step="0.01" min="0.01" :max="pago.saldo" x-model.number="pago.monto" class="mt-1 w-full h-12 rounded-xl border-gray-300 text-2xl font-black text-right"></label>
                <label class="block text-xs font-semibold text-gray-500">Medio de pago
                    <select x-model.number="pago.medio" class="mt-1 w-full h-10 rounded-xl border-gray-300 text-sm">
                        <template x-for="m in medios" :key="m.id"><option :value="m.id" x-text="m.nombre"></option></template>
                    </select></label>
                <div class="flex gap-2 pt-1">
                    <button type="button" @click="pago = null" class="flex-1 h-11 rounded-xl bg-gray-100 font-bold">Cancelar</button>
                    <button :disabled="enviando" class="flex-1 h-11 rounded-xl bg-emerald-600 text-white font-black disabled:opacity-50" x-text="enviando ? 'Cobrando…' : 'Cobrar'"></button>
                </div>
            </form>
        </div>
    </template>
    <iframe x-ref="recibo" class="hidden" title="Recibo"></iframe>
</div>

<script>
    function separados(medios) {
        return {
            medios, pago: null, enviando: false,
            abrir(s) { this.pago = { ...s, monto: s.saldo, medio: medios[0]?.id ?? null }; },
            async cobrar() {
                this.enviando = true;
                try {
                    const r = await fetch(@js(url('cuentas/cobrar')) + '/' + this.pago.cuenta + '/pagar', {
                        method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': @js(csrf_token()) },
                        body: JSON.stringify({ fecha: @js(now()->toDateString()), medios: [{ id: this.pago.medio, monto: this.pago.monto }], comentario: 'ABONO SEPARADO ' + this.pago.codigo }),
                    });
                    const d = await r.json();
                    if (r.status === 422) { tushpaAviso(Object.values(d.errors)[0][0], 'error'); return; }
                    if (d.estado !== 'ok') { tushpaAviso(d.mensaje || 'No se pudo cobrar.', 'error'); return; }
                    if (!d.impresora && d.recibo) this.$refs.recibo.src = d.recibo + (d.recibo.includes('?') ? '&' : '?') + 'imprimir=1';
                    tushpaAviso('Abono cobrado.', 'ok');
                    setTimeout(() => location.reload(), 900);
                } catch (e) {
                    tushpaAviso('Sin conexión con el servidor.', 'error');
                } finally {
                    this.enviando = false;
                }
            },
        };
    }
</script>
@endsection
