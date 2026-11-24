@extends('layouts.app')
@section('title', 'Cuenta de '.$cliente->clinom)
@section('content')
    @include('empresas.partials.alert')
    <div class="flex flex-wrap items-center gap-3 mb-4">
        <a href="{{ route('avicola.clientes') }}" class="h-9 px-3 rounded-xl bg-white shadow-sm text-sm font-semibold flex items-center">← Clientes</a>
        <div class="flex-1"><h1 class="text-xl font-bold text-gray-800">{{ $cliente->clinom }}</h1><p class="text-sm text-gray-500">{{ $cliente->clinum }}{{ $cliente->telefono ? ' · '.$cliente->telefono : '' }}</p></div>
    </div>

    <div class="grid lg:grid-cols-2 gap-4 mb-4">
        {{-- Cuenta corriente --}}
        <section class="bg-white rounded-2xl shadow-sm p-5">
            <div class="flex items-end justify-between mb-3">
                <div><p class="text-xs text-gray-500">Debe</p><p class="text-3xl font-extrabold text-gray-800">S/ {{ number_format((float) ($saldo->saldo ?? 0), 2) }}</p>
                    @if (($saldo->vencido ?? 0) > 0)<p class="text-sm text-rose-600 font-semibold">S/ {{ number_format((float) $saldo->vencido, 2) }} vencido</p>@endif</div>
            </div>
            @if (($saldo->saldo ?? 0) > 0)
                <form method="POST" action="{{ route('avicola.abonar', $cliente->clicod) }}" class="grid grid-cols-3 gap-2 items-end">
                    @csrf
                    <label class="text-xs font-semibold text-gray-500">Abona S/<input type="number" step="0.01" min="0.01" name="monto" required class="mt-1 block w-full h-10 rounded-xl border-gray-300 text-right font-bold"></label>
                    <label class="text-xs font-semibold text-gray-500">Con<select name="medio" class="mt-1 block w-full h-10 rounded-xl border-gray-300 text-sm">
                        @foreach ($medios as $m)<option value="{{ $m->id_med_pag }}">{{ $m->nom_med_pag }}</option>@endforeach</select></label>
                    <button class="h-10 rounded-xl bg-emerald-600 text-white text-sm font-bold">Registrar abono</button>
                    <input name="operacion" maxlength="30" placeholder="N° de operación (Yape, depósito…)" class="col-span-3 h-9 rounded-xl border-gray-300 text-sm">
                </form>
                <p class="mt-2 text-xs text-gray-400">El abono paga primero lo más antiguo. El efectivo entra a tu caja del turno.</p>
            @endif
            <table class="w-full text-sm mt-4">
                <thead class="text-xs text-gray-500 uppercase"><tr><th class="text-left py-1">Documento</th><th class="text-left">Fecha</th><th class="text-left">Vence</th><th class="text-right">Total</th><th class="text-right">Debe</th></tr></thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($pendientes as $p)
                        <tr class="{{ $p->fec_ven && $p->fec_ven < now()->toDateString() ? 'text-rose-700' : '' }}">
                            <td class="py-1.5 font-mono text-xs">{{ $p->serdoc }}-{{ $p->numdoc }}</td><td>{{ \Carbon\Carbon::parse($p->ccafem)->format('d/m') }}</td>
                            <td>{{ $p->fec_ven ? \Carbon\Carbon::parse($p->fec_ven)->format('d/m') : '—' }}</td>
                            <td class="text-right">{{ number_format((float) $p->total, 2) }}</td><td class="text-right font-bold">{{ number_format((float) $p->saldo, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-4 text-center text-gray-400">✓ No debe nada.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>

        {{-- Jabas --}}
        <section class="bg-white rounded-2xl shadow-sm p-5" x-data="{ tipo: 'DEVOLUCION' }">
            <p class="text-xs text-gray-500">Jabas que tiene</p>
            <p class="text-3xl font-extrabold {{ $jabas > 0 ? 'text-orange-600' : 'text-gray-800' }}">{{ $jabas }}</p>
            <form method="POST" action="{{ route('avicola.jabas', $cliente->clicod) }}" class="mt-3 grid grid-cols-3 gap-2 items-end">
                @csrf
                <label class="text-xs font-semibold text-gray-500">Movimiento<select name="tipo" x-model="tipo" class="mt-1 block w-full h-10 rounded-xl border-gray-300 text-sm">
                    <option value="DEVOLUCION">Devolvió</option><option value="SALIDA">Se llevó</option>
                    @if (auth()->user()->esAdmin())<option value="AJUSTE">Ajuste (+/−)</option>@endif</select></label>
                <label class="text-xs font-semibold text-gray-500">Jabas<input type="number" name="cantidad" required :min="tipo === 'AJUSTE' ? -9999 : 1" class="mt-1 block w-full h-10 rounded-xl border-gray-300 text-center font-bold"></label>
                <button class="h-10 rounded-xl bg-orange-500 text-white text-sm font-bold">Guardar</button>
                <input name="observacion" maxlength="150" placeholder="Observación (opcional)" class="col-span-3 h-9 rounded-xl border-gray-300 text-sm">
            </form>
            <table class="w-full text-sm mt-4">
                <thead class="text-xs text-gray-500 uppercase"><tr><th class="text-left py-1">Fecha</th><th class="text-left">Movimiento</th><th class="text-right">Jabas</th><th class="text-left pl-3">Nota</th></tr></thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($movJabas as $j)
                        <tr><td class="py-1.5 text-gray-500">{{ \Carbon\Carbon::parse($j->fecha)->format('d/m H:i') }}</td>
                            <td>{{ ['SALIDA' => 'Se llevó', 'DEVOLUCION' => 'Devolvió', 'AJUSTE' => 'Ajuste'][$j->tipo] ?? $j->tipo }}</td>
                            <td class="text-right font-bold {{ $j->cantidad > 0 ? 'text-orange-600' : 'text-emerald-600' }}">{{ $j->cantidad > 0 ? '+' : '' }}{{ $j->cantidad }}</td>
                            <td class="pl-3 text-xs text-gray-500">{{ $j->observacion }}</td></tr>
                    @empty
                        <tr><td colspan="4" class="py-4 text-center text-gray-400">Sin movimientos de jabas.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>
    </div>

    <section class="bg-white rounded-2xl shadow-sm overflow-x-auto">
        <p class="px-5 py-3 border-b border-gray-100 font-bold text-gray-800">Últimas compras</p>
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs text-gray-500 uppercase"><tr><th class="px-3 py-2 text-left">Fecha</th><th class="px-3 py-2 text-left">Comprobante</th><th class="px-3 py-2 text-left">Producto</th>
                <th class="px-3 py-2 text-right">Jabas</th><th class="px-3 py-2 text-right">Aves</th><th class="px-3 py-2 text-right">Neto kg</th><th class="px-3 py-2 text-right">Precio</th><th class="px-3 py-2 text-right">Total</th></tr></thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($ventas as $v)
                    <tr class="{{ $v->ccabaj ? 'opacity-40 line-through' : '' }}"><td class="px-3 py-2 text-gray-500">{{ \Carbon\Carbon::parse($v->fecha)->format('d/m/Y H:i') }}</td>
                        <td class="px-3 py-2 font-mono text-xs">{{ $v->serdoc }}-{{ $v->numdoc }}@if ($v->credito) <span class="px-1 rounded bg-amber-100 text-amber-800 font-sans font-bold">CRÉDITO</span>@endif</td>
                        <td class="px-3 py-2">{{ $v->pronom }}</td><td class="px-3 py-2 text-right">{{ $v->jabas }}</td><td class="px-3 py-2 text-right">{{ $v->aves }}</td>
                        <td class="px-3 py-2 text-right">{{ number_format((float) $v->peso_neto, 2) }}</td><td class="px-3 py-2 text-right">{{ number_format((float) $v->precio, 2) }}</td>
                        <td class="px-3 py-2 text-right font-bold">{{ number_format((float) $v->total, 2) }}</td></tr>
                @empty
                    <tr><td colspan="8" class="px-3 py-6 text-center text-gray-400">Sin compras.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>
@endsection
