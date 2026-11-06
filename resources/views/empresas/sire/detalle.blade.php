@extends('layouts.app')
@section('title', 'Compras con detalle')

@php
    $tipos = ['01' => 'Factura', '02' => 'Rec. honorarios', '03' => 'Boleta', '07' => 'N. Crédito', '08' => 'N. Débito', '12' => 'Ticket', '14' => 'Serv. público'];
    $compras = true;
    $soloXml = $propuesta ? $sinPropuesta : $comprobantes;
    $nombrePeriodo = \Carbon\Carbon::createFromFormat('Ym', $periodo)->locale('es')->translatedFormat('F Y');
    $total = count($comprobantes);
    $faltan = $total - $conDetalle;
@endphp

@section('content')
<div class="max-w-7xl mx-auto space-y-4" x-data="Object.assign(sireDbf(@js($totales)), comprasDetalle())">

    {{-- Pestañas del SIRE --}}
    <div class="flex flex-wrap items-center gap-2">
        <a href="{{ route('sire.index', ['ventas', 'periodo' => $periodo]) }}" class="px-4 h-10 inline-flex items-center rounded-xl text-sm font-bold bg-white text-slate-600 hover:bg-slate-50">Ventas (RVIE)</a>
        <a href="{{ route('sire.index', ['compras', 'periodo' => $periodo]) }}" class="px-4 h-10 inline-flex items-center rounded-xl text-sm font-bold bg-white text-slate-600 hover:bg-slate-50">Compras (RCE)</a>
        <span class="px-4 h-10 inline-flex items-center rounded-xl text-sm font-bold bg-indigo-600 text-white">Compras con detalle</span>
        <a href="{{ route('sire.ventas.detalle', ['periodo' => $periodo]) }}" class="px-4 h-10 inline-flex items-center rounded-xl text-sm font-bold bg-white text-slate-600 hover:bg-slate-50">Ventas con detalle</a>
        <a href="{{ route('sire.dbf', ['periodo' => $periodo]) }}" class="px-4 h-10 inline-flex items-center rounded-xl text-sm font-bold bg-white text-slate-600 hover:bg-slate-50">Contabilidad (DBF)</a>
    </div>

    {{-- Periodo, subir XML y descargas --}}
    <section class="bg-white rounded-2xl shadow-sm p-4 grid gap-4 lg:grid-cols-[auto_1fr] xl:grid-cols-[auto_1fr_auto] lg:items-end">
        <form method="GET">
            <label class="text-xs font-semibold text-slate-500">Periodo
                <input type="month" value="{{ substr($periodo, 0, 4) }}-{{ substr($periodo, 4, 2) }}"
                       onchange="this.form.periodo.value = this.value.replace('-', ''); this.form.submit()" class="block mt-1 h-10 rounded-xl border-slate-300 text-sm">
                <input type="hidden" name="periodo" value="{{ $periodo }}">
            </label>
        </form>

        <form method="POST" action="{{ route('sire.detalle.subir', ['periodo' => $periodo]) }}" enctype="multipart/form-data" class="flex flex-wrap items-end gap-2"
              @submit="subiendo = true">
            @csrf
            <label class="flex-1 min-w-[220px] text-xs font-semibold text-slate-500">XML de tus compras (sueltos o en un .zip)
                <input type="file" name="archivos[]" multiple accept=".xml,.zip" required
                       class="block w-full mt-1 text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:font-semibold file:text-indigo-700">
            </label>
            <button class="h-10 px-5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold disabled:opacity-50" :disabled="subiendo">
                <span x-text="subiendo ? 'Leyendo XML…' : 'Subir XML'"></span>
            </button>
        </form>

        <div class="flex flex-wrap gap-2 lg:justify-end">
            @if ($propuesta)
                <a href="{{ route('sire.dbf.excel', ['compras', 'periodo' => $periodo]) }}"
                   class="h-10 px-4 inline-flex items-center rounded-xl bg-white border border-emerald-600 text-emerald-700 hover:bg-emerald-50 text-sm font-bold">⬇ Excel (DBF)</a>
                <a href="{{ route('sire.dbf.descargar', ['compras', 'periodo' => $periodo]) }}"
                   @click="if (totales.descuadrados) { $event.preventDefault(); tushpaAviso('Hay ' + totales.descuadrados + ' comprobante(s) que no cuadran o tienen el IGV en otra casilla: corrígelos o no los lleves.', false); }"
                   class="h-10 px-4 inline-flex items-center rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold">⬇ Descargar almaingr.dbf</a>
            @endif
            <a href="{{ route('sire.detalle.excel', ['periodo' => $periodo]) }}" class="h-10 px-4 inline-flex items-center rounded-xl bg-white border border-slate-300 text-slate-700 hover:bg-slate-50 text-sm font-bold">Excel con detalle</a>
            <form method="GET" action="{{ route('sire.detalle.concar') }}" class="flex">
                <input type="hidden" name="periodo" value="{{ $periodo }}">
                <input type="number" name="desde" min="1" max="9999" placeholder="Desde 1" title="Número del primer asiento"
                       class="w-24 h-10 rounded-l-xl border-slate-300 text-sm">
                <button class="h-10 px-4 rounded-r-xl bg-slate-800 hover:bg-slate-900 text-white text-sm font-bold">CONCAR con detalle</button>
            </form>
        </div>
    </section>

    @unless ($propuesta)
        <div class="rounded-xl bg-amber-50 border border-amber-200 text-amber-900 px-4 py-3 text-sm">
            Aún no trajiste la propuesta de compras de <strong>{{ $nombrePeriodo }}</strong>. Sin ella se listan solo los XML que subas
            (por su fecha). <a href="{{ route('sire.index', ['compras', 'periodo' => $periodo]) }}" class="font-bold underline">Traer la propuesta de SUNAT</a>
            para saber qué facturas faltan y que el CONCAR cuadre con lo declarado.
        </div>
    @endunless

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        <button type="button" @click="filtro = 'todos'" class="text-left bg-white rounded-2xl shadow-sm p-4 ring-2" :class="filtro === 'todos' ? 'ring-indigo-500' : 'ring-transparent'">
            <p class="text-xs font-semibold text-slate-500">{{ $propuesta ? 'Comprobantes en SUNAT' : 'Comprobantes con XML' }}</p>
            <p class="text-2xl font-extrabold">{{ $total }}</p>
        </button>
        <div class="bg-white rounded-2xl shadow-sm p-4">
            <p class="text-xs font-semibold text-slate-500">Con detalle</p>
            <p class="text-2xl font-extrabold text-emerald-600">{{ $conDetalle }}</p>
        </div>
        <button type="button" @click="filtro = 'sinxml'" class="text-left bg-white rounded-2xl shadow-sm p-4 ring-2" :class="filtro === 'sinxml' ? 'ring-amber-500' : 'ring-transparent'">
            <p class="text-xs font-semibold text-slate-500">Falta su XML</p>
            <p class="text-2xl font-extrabold {{ $faltan ? 'text-amber-600' : 'text-emerald-600' }}">{{ $faltan }}</p>
        </button>
        <button type="button" @click="filtro = 'sincuenta'" class="text-left bg-white rounded-2xl shadow-sm p-4 ring-2" :class="filtro === 'sincuenta' ? 'ring-rose-500' : 'ring-transparent'">
            <p class="text-xs font-semibold text-slate-500">Líneas sin cuenta</p>
            <p class="text-2xl font-extrabold {{ $lineasSinCuenta ? 'text-rose-600' : 'text-emerald-600' }}">{{ $lineasSinCuenta }}</p>
            <p class="text-[11px] text-slate-400">Sin cuenta va a la {{ $cfg['cta_compras'] }}</p>
        </button>
    </div>

    @if ($faltan)
        <section class="bg-white rounded-2xl shadow-sm p-4 flex flex-wrap items-center gap-3">
            <div class="flex-1 min-w-[240px] text-sm">
                <p class="font-bold text-slate-800">Traer de SUNAT los {{ $faltan }} XML que faltan</p>
                @if ($faltaJsonPe)
                    <p class="text-amber-700">{{ $faltaJsonPe }}</p>
                @else
                    <p class="text-slate-500" x-text="progreso || 'Se bajan solos con la Clave SOL de la empresa. Puedes seguir trabajando en otra pestaña.'"></p>
                @endif
            </div>
            <button type="button" @click="traer()" :disabled="trayendo || {{ $faltaJsonPe ? 'true' : 'false' }}"
                    class="h-10 px-5 rounded-xl bg-amber-500 hover:bg-amber-600 text-white text-sm font-bold disabled:opacity-50">
                <span x-text="trayendo ? 'Trayendo…' : '⬇ Traer XML de SUNAT'"></span>
            </button>
        </section>
    @endif

    {{-- Avance mientras se traen los XML: que se vea que el sistema está trabajando --}}
    <div x-show="trayendo" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
        <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl text-center">
            <div class="mx-auto h-12 w-12 rounded-full border-4 border-amber-200 border-t-amber-500 animate-spin"></div>
            <p class="mt-4 text-lg font-extrabold text-slate-800">Trayendo los XML de SUNAT…</p>
            <p class="mt-1 text-3xl font-black text-amber-600" x-text="total ? `${hechos} de ${total}` : ''"></p>
            <div class="mt-3 h-3 w-full overflow-hidden rounded-full bg-slate-100">
                <div class="h-full rounded-full bg-amber-500 transition-all duration-500" :style="`width: ${total ? Math.round(hechos * 100 / total) : 3}%`"></div>
            </div>
            <p class="mt-3 text-sm text-slate-600" x-text="progreso"></p>
            <p class="mt-2 text-xs text-slate-400">Cada XML demora unos segundos en SUNAT. No cierres esta pestaña.</p>
        </div>
    </div>

    @if ($propuesta)
        @include('empresas.sire.partials.dbf_resumen', ['comprobantes' => $dbf])
        @include('empresas.sire.partials.dbf_cuentas_todos')

        <div class="flex flex-wrap items-center gap-2 text-sm">
            @foreach (['todos' => 'Todos', 'descuadre' => 'No cuadran / IGV mal', 'excluidos' => 'No se llevan', 'sinxml' => 'Sin detalle (XML)', 'sincuenta' => 'Líneas sin cuenta'] as $valor => $texto)
                <button type="button" @click="filtro = '{{ $valor }}'" class="px-3 h-8 rounded-full font-semibold"
                        :class="filtro === '{{ $valor }}' ? 'bg-indigo-600 text-white' : 'bg-white text-slate-600'">{{ $texto }}</button>
            @endforeach
            <span class="text-xs text-slate-400 ml-auto">Los cambios se guardan solos · desmarca «Llevar» lo que no va al .dbf.</span>
        </div>
        <p class="text-xs text-slate-500">
            💡 En «Ver detalle» escribe la cuenta de cada línea y presiona Enter: el sistema la recuerda y la pone sola a las líneas iguales de ese proveedor, ahora y en los próximos meses.
        </p>

        <div class="space-y-2">
            @forelse ($dbf as $c)
                @include('empresas.sire.partials.dbf_fila')
            @empty
                <div class="bg-white rounded-2xl shadow-sm p-8 text-center text-slate-500">La propuesta de {{ $nombrePeriodo }} no tiene comprobantes.</div>
            @endforelse
        </div>
    @endif

    @if ($propuesta && $soloXml)
        <p class="pt-2 text-sm font-bold text-slate-700">XML que no están en la propuesta de {{ $nombrePeriodo }}
            <span class="font-normal text-slate-500">(de otro mes o SUNAT aún no los registra; no entran al .dbf ni al CONCAR de este mes)</span></p>
    @endif
    @forelse ($soloXml as $c)
        @php $sinCuenta = collect($c['lineas'])->whereNull('cuenta')->count(); @endphp
        <section class="bg-white rounded-2xl shadow-sm overflow-hidden" x-data="{ abierto: false }"
                 x-show="filtro === 'todos' || (filtro === 'sinxml' && {{ $c['xml'] ? 'false' : 'true' }}) || (filtro === 'sincuenta' && {{ $sinCuenta ? 'true' : 'false' }})">
            <button type="button" @click="abierto = !abierto" class="w-full flex flex-wrap items-center gap-x-3 gap-y-1 px-4 py-3 text-left">
                <span class="font-bold whitespace-nowrap">{{ $c['serie'] }}-{{ $c['numero'] }}</span>
                <span class="text-xs text-slate-400">{{ $tipos[$c['tipo']] ?? 'Tipo '.$c['tipo'] }} · {{ $c['fecha'] ? date('d/m/Y', strtotime($c['fecha'])) : '' }}</span>
                <span class="flex-1 min-w-[160px] text-sm text-slate-700">{{ $c['nombre'] }} <span class="text-xs text-slate-400">{{ $c['ruc'] }}</span></span>
                <span class="font-bold whitespace-nowrap">{{ $c['moneda'] === 'USD' ? 'US$' : 'S/' }} {{ number_format($c['total'], 2) }}</span>
                @if (! $c['xml'])
                    <span class="rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-bold text-amber-800">Falta XML</span>
                @elseif ($sinCuenta)
                    <span class="rounded-full bg-rose-100 px-2.5 py-0.5 text-xs font-bold text-rose-700">{{ $sinCuenta }} sin cuenta</span>
                @else
                    <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-bold text-emerald-700">✔ {{ count($c['lineas']) }} línea(s)</span>
                @endif
                @if ($c['xml'])<a href="{{ route('sire.detalle.pdf', $c['xml']->id) }}" target="_blank" @click.stop class="text-xs font-bold text-slate-600 hover:text-indigo-700">📄 PDF</a>
                    <span x-text="abierto ? '−' : '+'" class="text-slate-400 text-lg w-4 text-center"></span>@endif
            </button>
            @if ($c['xml'])
                <div x-show="abierto" x-cloak class="border-t border-slate-100">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-slate-50 text-slate-500 text-xs uppercase">
                                <tr>
                                    <th class="text-left px-4 py-2">Descripción</th>
                                    <th class="text-right px-3 py-2">Cant.</th>
                                    <th class="text-right px-3 py-2">V. unit.</th>
                                    <th class="text-right px-3 py-2">Base</th>
                                    <th class="text-right px-3 py-2">IGV</th>
                                    <th class="text-left px-3 py-2 w-36">Cuenta</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($c['lineas'] as $l)
                                    <tr class="border-t border-slate-100">
                                        <td class="px-4 py-2">{{ $l->descripcion }} @if ($l->codigo)<span class="text-xs text-slate-400">{{ $l->codigo }}</span>@endif</td>
                                        <td class="px-3 py-2 text-right whitespace-nowrap">{{ rtrim(rtrim(number_format($l->cantidad, 4, '.', ''), '0'), '.') }} <span class="text-xs text-slate-400">{{ $l->unidad }}</span></td>
                                        <td class="px-3 py-2 text-right">{{ number_format($l->precio, 2) }}</td>
                                        <td class="px-3 py-2 text-right">{{ number_format($l->base, 2) }}</td>
                                        <td class="px-3 py-2 text-right">{{ number_format($l->igv, 2) }}</td>
                                        <td class="px-3 py-1.5">
                                            <input type="text" inputmode="numeric" maxlength="12" value="{{ $l->cuenta }}" placeholder="{{ $cfg['cta_compras'] }}"
                                                   @keydown.enter.prevent="guardar([{{ $l->id }}], $event.target)" @change="guardar([{{ $l->id }}], $event.target)"
                                                   class="w-full h-9 rounded-lg border-slate-300 text-sm font-mono {{ $l->cuenta ? '' : 'bg-rose-50' }}">
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if (count($c['lineas']) > 1)
                        <div class="flex flex-wrap items-center gap-2 px-4 py-3 bg-slate-50 text-sm">
                            <span class="text-slate-600">Todo este comprobante a la cuenta</span>
                            <input type="text" inputmode="numeric" maxlength="12" placeholder="Ej. 6563" class="w-28 h-9 rounded-lg border-slate-300 text-sm font-mono"
                                   @keydown.enter.prevent="guardar(@js(collect($c['lineas'])->pluck('id')->all()), $event.target, true)">
                            <span class="text-xs text-slate-400">y presiona Enter</span>
                        </div>
                    @endif
                </div>
            @endif
        </section>
    @empty
        @unless ($propuesta)
            <div class="bg-white rounded-2xl shadow-sm p-8 text-center text-slate-500">
                No hay compras de {{ $nombrePeriodo }}. Trae la propuesta de SUNAT o sube los XML de tus facturas.
            </div>
        @endunless
    @endforelse
</div>

@include('empresas.sire.partials.dbf_js')
<script>
    function comprasDetalle() {
        return {
            subiendo: false,
            trayendo: false,
            progreso: '',
            total: 0,
            hechos: 0,
            /** Pide los XML de a dos hasta que no quede ninguno, mostrando el avance; al final recarga para mostrarlos */
            async traer() {
                this.trayendo = true;
                this.total = 0;
                this.hechos = 0;
                this.progreso = 'Conectando con SUNAT…';
                let traidos = 0, sinXml = 0;
                try {
                    while (true) {
                        const r = await fetch(@json(route('sire.detalle.traer', ['periodo' => $periodo])), {
                            method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': @json(csrf_token()) },
                        });
                        const d = await r.json();
                        if (!this.total) this.total = d.pendientes || 0;
                        traidos += d.traidos || 0;
                        sinXml += (d.errores || []).length;
                        this.hechos = traidos + sinXml;
                        if (!d.ok) {
                            tushpaAviso(d.mensaje || 'No se pudo traer los XML.', false);
                            break;
                        }
                        this.progreso = `${traidos} XML traídos` + (sinXml ? ` · ${sinXml} que SUNAT no entregó` : '');
                        if (!d.quedan) break;
                    }
                } catch (e) {
                    tushpaAviso('Se cortó la conexión. Vuelve a presionar "Traer XML de SUNAT" para seguir: lo ya traído no se pierde.', false);
                }
                this.trayendo = false;
                if (traidos) {
                    this.progreso = `Listo: ${traidos} XML traídos. Mostrando el detalle…`;
                    location.reload();
                }
            },
            /** Guarda la cuenta; si se aprendió para otras líneas, recarga para mostrarlas */
            async guardar(lineas, campo, todas = false) {
                const cuenta = campo.value.trim();
                if (!cuenta) return;
                campo.disabled = true;
                try {
                    const r = await fetch(@json(route('sire.detalle.cuenta')), {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': @json(csrf_token()) },
                        body: JSON.stringify({ lineas, cuenta }),
                    });
                    const d = await r.json();
                    if (!r.ok) {
                        tushpaAviso(d.message || 'No se pudo guardar la cuenta.', false);
                        return;
                    }
                    if (todas || d.cambiadas > lineas.length) {
                        location.reload();
                        return;
                    }
                    campo.classList.remove('bg-rose-50');
                    campo.classList.add('bg-emerald-50');
                } finally {
                    campo.disabled = false;
                }
            },
        };
    }
</script>
@endsection
