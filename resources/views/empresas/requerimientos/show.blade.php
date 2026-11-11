@extends('layouts.app')
@section('title', $codigo)
@section('content')
@use('App\Support\Requerimientos')
@php
    $num = fn ($n) => rtrim(rtrim(number_format((float) $n, 3, '.', ''), '0'), '.') ?: '0';
    $unidad = fn ($u) => \App\Support\Recetas::nombreUnidad($u ?: 'NIU');
    [$nombreEstado, $color] = Requerimientos::ESTADOS[$r->estado];
    $chip = ['amber' => 'bg-amber-50 text-amber-800 ring-amber-200', 'emerald' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        'slate' => 'bg-slate-100 text-slate-500 ring-slate-200', 'sky' => 'bg-sky-50 text-sky-700 ring-sky-200'];
    $pendiente = $r->estado === 'PENDIENTE';
    $puedeComprar = $pendiente && $atiende;
    $estimado = $lineas->sum(fn ($l) => (float) ($l->comprado ?? $l->cantidad) * (float) ($l->precio ?? $l->costo_actual));
@endphp
<div class="max-w-4xl mx-auto space-y-4" x-data="{ anular: false }">
    @if (session('success'))<div class="rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 text-sm">{{ session('success') }}</div>@endif
    @if (session('error'))<div class="rounded-xl bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 text-sm">{{ session('error') }}</div>@endif
    @if ($errors->any())<div class="rounded-xl bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 text-sm">{{ $errors->first() }}</div>@endif

    <div class="bg-white rounded-2xl shadow-sm p-5 space-y-3">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-xs font-bold uppercase text-gray-400">Requerimiento {{ $sedeEnvia ? 'a otra sede' : 'del día' }}</p>
                <h1 class="text-2xl font-extrabold text-gray-800">{{ $codigo }}
                    <span class="align-middle px-2.5 py-1 rounded-full text-xs font-bold ring-1 {{ $chip[$color] }}">{{ $nombreEstado }}</span>
                    @if ($r->urgente)<span class="align-middle px-2.5 py-1 rounded-full text-xs font-bold bg-rose-600 text-white">🚨 URGENTE</span>@endif</h1>
                <p class="text-sm text-gray-600">Para el <b>{{ \Carbon\Carbon::parse($r->fecha_para)->locale('es')->isoFormat('dddd D [de] MMMM') }}</b> · {{ $sedePide }} · {{ $almacen }}
                    · pidió <b>{{ $pidio }}</b> el {{ \Carbon\Carbon::parse($r->created_at)->format('d/m/Y H:i') }}</p>
                @if ($sedeEnvia)<p class="text-sm text-sky-700 font-semibold">🏬 Se le pide a la sede {{ $sedeEnvia }}</p>@endif
                @if ($r->observaciones)<p class="text-sm text-gray-600">📌 {{ $r->observaciones }}</p>@endif
                @if ($compra)<p class="text-sm text-emerald-700 font-semibold">✔ Comprado por {{ $atendio }}: compra {{ $compra->com_doc_ser }}-{{ $compra->com_doc_num }} por S/ {{ number_format((float) $compra->total_com, 2) }}</p>@endif
                @if ($r->estado === 'ANULADO')<p class="text-sm text-slate-500">Anulado por {{ $atendio }}: {{ $r->motivo_anulacion }}</p>@endif
            </div>
            <div class="flex flex-wrap gap-2">
                @if ($editable ?? false)
                    <a href="{{ route('requerimientos.editar', $r->id) }}" class="h-10 px-4 inline-flex items-center rounded-xl bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-sm font-bold">✏️ Cambiar pedido</a>
                @endif
                <a href="{{ route('requerimientos.imprimir', $r->id) }}" target="_blank" class="h-10 px-4 inline-flex items-center rounded-xl bg-gray-100 hover:bg-gray-200 text-sm font-bold">🖨 Imprimir</a>
                @unless ($sedeEnvia)
                    <a href="{{ route('requerimientos.imprimir', [$r->id, 'compra' => 'ticket']) }}" target="_blank" title="Lo comprado (guárdalo antes)" class="h-10 px-4 inline-flex items-center rounded-xl bg-emerald-50 hover:bg-emerald-100 text-emerald-800 text-sm font-bold">🧾 Comprado · Ticket</a>
                    <a href="{{ route('requerimientos.imprimir', [$r->id, 'compra' => 'a4']) }}" target="_blank" title="Lo comprado (guárdalo antes)" class="h-10 px-4 inline-flex items-center rounded-xl bg-emerald-50 hover:bg-emerald-100 text-emerald-800 text-sm font-bold">📄 Comprado · A4</a>
                @endunless
                @if ($pendiente)
                    <button type="button" @click="anular = !anular" class="h-10 px-4 rounded-xl bg-white ring-1 ring-rose-200 text-rose-700 text-sm font-bold hover:bg-rose-50">Anular</button>
                @endif
            </div>
        </div>
        <form x-show="anular" x-cloak method="POST" action="{{ route('requerimientos.anular', $r->id) }}" class="flex flex-wrap gap-2">
            @csrf
            <input name="motivo" required maxlength="255" placeholder="¿Por qué se anula?" class="flex-1 min-w-56 h-10 rounded-xl border-gray-300 text-sm">
            <button class="h-10 px-4 rounded-xl bg-rose-600 text-white text-sm font-bold">Confirmar anulación</button>
        </form>
    </div>

    @if ($despacha)
        {{-- La sede central: qué envía de su almacén --}}
        <form method="POST" action="{{ route('requerimientos.despachar', $r->id) }}" class="bg-white rounded-2xl shadow-sm">
            @csrf
            <div class="p-4 sm:p-5 flex flex-wrap items-end gap-3 border-b border-gray-100">
                <p class="flex-1 min-w-56 text-sm text-gray-600">🚚 La sede <b>{{ $sedePide }}</b> te pide esto. Pon cuánto envías (0 si no hay): sale de tu almacén ahora y entra al suyo cuando lo reciban.</p>
                <label class="text-sm font-semibold text-gray-700">Sale de
                    <select name="id_almacen_origen" class="mt-1 block h-10 rounded-xl border-gray-300 text-sm">
                        @foreach ($almacenes as $a)<option value="{{ $a->id_almacen }}">{{ $a->descripcion }}</option>@endforeach
                    </select>
                </label>
            </div>
            <div class="divide-y divide-gray-100">
                @foreach ($lineas as $l)
                    @php $par = $pares[$l->IdProducto] ?? null; $hay = $par ? (float) ($stockOrigen[$par->IdProducto] ?? 0) : 0; @endphp
                    <div class="flex flex-wrap sm:flex-nowrap items-center gap-3 px-4 sm:px-5 py-3">
                        <div class="flex-1 min-w-48">
                            <p class="font-bold text-gray-800">{{ $l->pronom }}: <span class="text-indigo-700">{{ $num($l->cantidad) }} {{ $unidad($l->umecod) }}</span></p>
                            <p class="text-xs {{ $par ? 'text-gray-500' : 'text-rose-600 font-semibold' }}">
                                {{ $par ? 'Tienes '.$num($hay).' de '.$par->pronom : 'No está en tu catálogo (ni por código ni por nombre): créalo o envía 0' }}{{ $l->nota ? ' · '.$l->nota : '' }}</p>
                        </div>
                        <label class="text-xs text-gray-500">Envío
                            <input type="number" step="0.001" min="0" name="enviados[{{ $l->id }}]" value="{{ $par ? $num(min((float) $l->cantidad, max($hay, 0))) : 0 }}" @disabled(! $par)
                                   class="block w-28 h-10 rounded-xl border-gray-300 text-right font-bold disabled:bg-gray-100"></label>
                    </div>
                @endforeach
            </div>
            <div class="p-4 flex justify-end"><button class="h-12 px-6 rounded-xl bg-sky-600 hover:bg-sky-700 text-white font-bold">🚚 Despachar a {{ $sedePide }}</button></div>
        </form>
    @elseif ($recibe)
        {{-- La sede que pidió: confirma lo que llegó --}}
        <form method="POST" action="{{ route('requerimientos.recibir', $r->id) }}" class="bg-white rounded-2xl shadow-sm">
            @csrf
            <p class="p-4 sm:p-5 text-sm text-gray-600 border-b border-gray-100">📦 La sede <b>{{ $sedeEnvia }}</b> lo despachó. Revisa lo que llegó (si llegó menos, corrígelo) y confirma: entra a tu stock.</p>
            <div class="divide-y divide-gray-100">
                @foreach ($lineas->where('enviado', '>', 0) as $l)
                    <div class="flex flex-wrap sm:flex-nowrap items-center gap-3 px-4 sm:px-5 py-3">
                        <p class="flex-1 min-w-48 font-bold text-gray-800">{{ $l->pronom }} <span class="text-sm font-normal text-gray-500">· enviaron {{ $num($l->enviado) }} {{ $unidad($l->umecod) }}</span></p>
                        <label class="text-xs text-gray-500">Llegó
                            <input type="number" step="0.001" min="0" max="{{ (float) $l->enviado }}" name="recibidos[{{ $l->id }}]" value="{{ $num($l->enviado) }}" class="block w-28 h-10 rounded-xl border-gray-300 text-right font-bold"></label>
                    </div>
                @endforeach
            </div>
            <div class="p-4 flex justify-end"><button class="h-12 px-6 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold">✔ Confirmar lo que llegó</button></div>
        </form>
    @else
        <form method="POST" action="{{ route('requerimientos.marcar', $r->id) }}" class="space-y-4" x-data="{ comprobante: '12' }">
            @csrf
            <div class="bg-white rounded-2xl shadow-sm">
                @if ($puedeComprar)
                    <p class="px-4 sm:px-5 pt-4 text-sm text-gray-600">🛒 En el mercado: todo viene marcado ✔. <b>Quítale el check a lo que no compraste.</b>
                        Pon cuánto compraste y a cuánto (precio por unidad, con IGV); lo que dejes vacío se registra tal como se pidió.</p>
                @endif
                <div class="divide-y divide-gray-100">
                    @foreach ($lineas->groupBy(fn ($l) => $l->cat_nom ?? 'Sin categoría') as $categoria => $grupo)
                        <p class="px-4 sm:px-5 pt-3 pb-1 text-xs font-bold uppercase text-gray-400">{{ $categoria }}</p>
                        @foreach ($grupo as $l)
                            @php $noComprado = $l->comprado !== null && (float) $l->comprado <= 0; @endphp
                            <div class="flex flex-wrap sm:flex-nowrap items-center gap-3 px-4 sm:px-5 py-3" x-data="{ ok: {{ $noComprado ? 'false' : 'true' }} }" :class="ok ? '' : 'bg-rose-50/60'">
                                @if ($puedeComprar)
                                    <input type="checkbox" x-model="ok" class="w-6 h-6 rounded-md border-gray-300 text-emerald-600 focus:ring-emerald-500 shrink-0" title="¿Se compró?">
                                    <template x-if="!ok"><input type="hidden" name="lineas[{{ $l->id }}][comprado]" value="0"></template>
                                @endif
                                <div class="flex-1 min-w-40" :class="ok ? '' : 'opacity-60'">
                                    <p class="font-bold text-gray-800"><span :class="ok ? '' : 'line-through'">{{ $l->pronom }}</span>: <span class="text-indigo-700">{{ $num($l->cantidad) }} {{ $unidad($l->umecod) }}</span>
                                        @if ($puedeComprar)<span x-show="!ok" x-cloak class="ml-1 text-xs font-bold text-rose-600 no-underline">no se compró</span>@endif</p>
                                    <p class="text-xs text-gray-500">Había {{ $num($l->stock) }}{{ (float) $l->minimo > 0 ? ' · mín. '.$num($l->minimo) : '' }}{{ (float) $l->consumo_dia > 0 ? ' · se gasta '.$num($l->consumo_dia).'/día' : '' }}{{ $l->nota ? ' · '.$l->nota : '' }}</p>
                                </div>
                                @if ($puedeComprar)
                                    <label class="text-xs text-gray-500" x-show="ok">Compré
                                        <input type="number" step="0.001" min="0" name="lineas[{{ $l->id }}][comprado]" value="{{ $l->comprado !== null && ! $noComprado ? $num($l->comprado) : '' }}" placeholder="{{ $num($l->cantidad) }}" :disabled="!ok" class="block w-24 h-10 rounded-xl border-gray-300 text-right font-bold"></label>
                                    <label class="text-xs text-gray-500" x-show="ok">Precio unit. S/
                                        <input type="number" step="0.01" min="0" name="lineas[{{ $l->id }}][precio]" value="{{ $l->precio !== null ? (float) $l->precio : '' }}" placeholder="{{ number_format((float) $l->costo_actual, 2, '.', '') }}" :disabled="!ok" class="block w-28 h-10 rounded-xl border-gray-300 text-right"></label>
                                @elseif ($l->recibido !== null)
                                    <span class="text-sm font-semibold {{ (float) $l->recibido + 0.0001 >= (float) $l->enviado ? 'text-emerald-700' : 'text-amber-700' }}">llegó {{ $num($l->recibido) }} de {{ $num($l->enviado) }}</span>
                                @elseif ($l->enviado !== null)
                                    <span class="text-sm font-semibold text-sky-700">{{ (float) $l->enviado > 0 ? '🚚 enviado '.$num($l->enviado) : 'no había' }}</span>
                                @elseif ($l->comprado !== null)
                                    <span class="text-sm {{ (float) $l->comprado > 0 ? 'text-emerald-700' : 'text-rose-600' }} font-semibold">{{ (float) $l->comprado > 0 ? '✔ '.$num($l->comprado).($l->precio !== null ? ' a S/ '.number_format((float) $l->precio, 2) : '') : 'no se compró' }}</span>
                                @endif
                            </div>
                        @endforeach
                    @endforeach
                </div>
                @unless ($sedeEnvia)
                    <p class="px-4 sm:px-5 py-3 text-right text-sm text-gray-600 border-t border-gray-100">Costo aproximado: <b class="text-gray-800">S/ {{ number_format($estimado, 2) }}</b>
                        <span class="text-xs text-gray-400">(con los precios marcados o el último costo)</span></p>
                @endunless
            </div>
            @if ($puedeComprar)
                <div class="bg-white rounded-2xl shadow-sm p-4 sm:p-5 space-y-2">
                    <p class="text-sm font-bold text-gray-700">🧾 ¿Qué comprobante te dieron?</p>
                    <div class="grid sm:grid-cols-3 gap-2 text-sm">
                        <label class="flex items-start gap-2 rounded-xl p-3 ring-1 cursor-pointer" :class="comprobante === '01' ? 'ring-emerald-400 bg-emerald-50' : 'ring-gray-200'">
                            <input type="radio" name="comprobante" value="01" x-model="comprobante" class="mt-0.5"><span><b>Factura</b><br><span class="text-xs text-gray-500">Con el RUC del vendedor: el IGV te sirve de crédito fiscal.</span></span></label>
                        <label class="flex items-start gap-2 rounded-xl p-3 ring-1 cursor-pointer" :class="comprobante === '03' ? 'ring-emerald-400 bg-emerald-50' : 'ring-gray-200'">
                            <input type="radio" name="comprobante" value="03" x-model="comprobante" class="mt-0.5"><span><b>Boleta</b><br><span class="text-xs text-gray-500">Sustenta el gasto, pero sin crédito fiscal.</span></span></label>
                        <label class="flex items-start gap-2 rounded-xl p-3 ring-1 cursor-pointer" :class="comprobante === '12' ? 'ring-emerald-400 bg-emerald-50' : 'ring-gray-200'">
                            <input type="radio" name="comprobante" value="12" x-model="comprobante" class="mt-0.5"><span><b>Sin comprobante</b><br><span class="text-xs text-gray-500">Ticket o nada (mercado). Entra al stock igual, pero no es gasto deducible.</span></span></label>
                    </div>
                </div>
                <div class="sticky bottom-3 flex flex-wrap justify-end gap-2">
                    <button name="registrar" value="0" class="h-12 px-5 rounded-xl bg-white ring-1 ring-gray-300 font-bold shadow">💾 Guardar lo comprado</button>
                    <button name="registrar" value="1" class="h-12 px-5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold shadow-lg">✔ Registrar como compra →</button>
                </div>
            @endif
        </form>
    @endif
</div>
@endsection
