@extends('layouts.app')
@section('title', 'Proformas')

@section('content')
    @include('empresas.partials.alert')
    @if (session('error'))
        <div class="mb-4 rounded-lg bg-red-50 border border-red-200 text-red-700 px-4 py-2 text-sm">{{ session('error') }}</div>
    @endif

    <div class="grid sm:grid-cols-3 gap-3 mb-4">
        <div class="bg-white rounded-2xl shadow-sm p-4">
            <p class="text-xs font-semibold text-gray-500 uppercase">Pendientes de cobro</p>
            <p class="text-2xl font-extrabold text-indigo-700">{{ $resumen['cantidad'] }}</p>
        </div>
        <div class="bg-white rounded-2xl shadow-sm p-4">
            <p class="text-xs font-semibold text-gray-500 uppercase">Monto pendiente</p>
            <p class="text-2xl font-extrabold text-emerald-600">S/ {{ number_format($resumen['total'], 2) }}</p>
        </div>
        <div class="bg-indigo-50 rounded-2xl p-4 text-sm text-indigo-800">
            Las proformas se crean con el botón <strong>PROFORMA</strong> en Punto Venta, PV Farmacia, PV Móvil y PV.
            Para <strong>editarla o cobrarla</strong> se abre en la caja: al registrar se emite el comprobante.
        </div>
    </div>

    <form method="GET" class="bg-white rounded-2xl shadow-sm p-4 mb-4 grid grid-cols-2 sm:grid-cols-6 gap-3 items-end">
        <label class="col-span-2 text-xs font-semibold text-gray-500">Buscar
            <input type="text" name="q" value="{{ $q }}" placeholder="Cliente, DNI/RUC o número"
                   class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
        </label>
        <label class="text-xs font-semibold text-gray-500">Estado
            <select name="estado" class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="PENDIENTE" @selected($estado === 'PENDIENTE')>Pendientes</option>
                <option value="FACTURADA" @selected($estado === 'FACTURADA')>Cobradas</option>
                <option value="TODAS" @selected($estado === 'TODAS')>Todas</option>
            </select>
        </label>
        <label class="text-xs font-semibold text-gray-500">Desde
            <input type="date" name="desde" value="{{ $desde }}" class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
        </label>
        <label class="text-xs font-semibold text-gray-500">Hasta
            <input type="date" name="hasta" value="{{ $hasta }}" class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
        </label>
        <button class="h-[38px] rounded-xl bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700">Filtrar</button>
    </form>

    <div class="bg-white rounded-2xl shadow-sm overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                <tr>
                    <th class="px-4 py-3 text-left">Proforma</th>
                    <th class="px-4 py-3 text-left">Fecha</th>
                    <th class="px-4 py-3 text-left">Cliente</th>
                    <th class="px-4 py-3 text-left">Origen</th>
                    <th class="px-4 py-3 text-right">Total</th>
                    <th class="px-4 py-3 text-center">Estado</th>
                    <th class="px-4 py-3 text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($proformas as $p)
                    @php $numero = \App\Support\Proformas::numero($p); @endphp
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3">
                            <p class="font-mono font-bold text-gray-800">{{ $numero }}</p>
                            <p class="text-xs text-gray-400">{{ $p->usuario }}</p>
                        </td>
                        <td class="px-4 py-3 text-gray-600 whitespace-nowrap">{{ \Carbon\Carbon::parse($p->created_at)->format('d/m/Y H:i') }}</td>
                        <td class="px-4 py-3">
                            <p class="font-medium text-gray-700">{{ $p->clinom }}</p>
                            @if ($p->clinum !== '00000000')<p class="text-xs text-gray-400">{{ $p->tdicod === '6' ? 'RUC' : 'DOC' }} {{ $p->clinum }}</p>@endif
                            @if ($p->observaciones)<p class="text-xs text-amber-700">{{ $p->observaciones }}</p>@endif
                        </td>
                        <td class="px-4 py-3 text-gray-500 text-xs">{{ \App\Support\Proformas::ORIGENES[$p->origen] ?? $p->origen }}</td>
                        <td class="px-4 py-3 text-right font-semibold whitespace-nowrap">S/ {{ number_format($p->total, 2) }}</td>
                        <td class="px-4 py-3 text-center">
                            @if ($p->estado === 'PENDIENTE')
                                <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-700">Pendiente</span>
                            @else
                                <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-700">Cobrada</span>
                                @if ($p->serdoc)
                                    <a href="{{ route('cobros.voucher', $p->IdCpe_cabecera) }}" target="_blank" class="block text-xs text-indigo-600 hover:underline mt-0.5">
                                        {{ $p->serdoc }}-{{ str_pad($p->numdoc, 8, '0', STR_PAD_LEFT) }}</a>
                                @endif
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <div class="inline-flex flex-wrap justify-end gap-1.5">
                                <a href="{{ route('proformas.imprimir', $p->id_proforma) }}" target="_blank"
                                   class="px-2.5 py-1.5 rounded-lg text-xs font-semibold bg-gray-100 text-gray-700 hover:bg-gray-200">🖨 Imprimir</a>
                                @if ($p->estado === 'PENDIENTE')
                                    <a href="{{ \App\Http\Controllers\ProformaController::urlCaja($p) }}"
                                       class="px-2.5 py-1.5 rounded-lg text-xs font-semibold bg-indigo-50 text-indigo-700 hover:bg-indigo-100">✏️ Editar</a>
                                    <a href="{{ \App\Http\Controllers\ProformaController::urlCaja($p) }}"
                                       class="px-2.5 py-1.5 rounded-lg text-xs font-semibold bg-emerald-600 text-white hover:bg-emerald-700">💵 Cobrar</a>
                                    <form action="{{ route('proformas.destroy', $p->id_proforma) }}" method="POST" class="inline"
                                          onsubmit="return confirm('¿Eliminar la proforma {{ $numero }}?')">
                                        @csrf @method('DELETE')
                                        <button class="px-2.5 py-1.5 rounded-lg text-xs font-semibold text-rose-600 hover:bg-rose-50">Eliminar</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-gray-400">No hay proformas con estos filtros.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $proformas->links() }}</div>
@endsection
