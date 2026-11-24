@extends('layouts.app')
@section('title', 'Clientes y jabas')
@section('content')
    @include('empresas.partials.alert')
    <div class="grid sm:grid-cols-3 gap-3 mb-4">
        <div class="rounded-2xl bg-white shadow-sm p-4"><p class="text-xs text-gray-500">Te deben (cuenta corriente)</p><p class="text-2xl font-extrabold text-gray-800">S/ {{ number_format($total['saldo'], 2) }}</p></div>
        <div class="rounded-2xl bg-white shadow-sm p-4"><p class="text-xs text-gray-500">Vencido</p><p class="text-2xl font-extrabold {{ $total['vencido'] > 0 ? 'text-rose-600' : 'text-gray-800' }}">S/ {{ number_format($total['vencido'], 2) }}</p></div>
        <div class="rounded-2xl bg-white shadow-sm p-4"><p class="text-xs text-gray-500">Jabas en poder de clientes</p><p class="text-2xl font-extrabold text-gray-800">{{ number_format($total['jabas']) }}</p></div>
    </div>

    <form class="bg-white rounded-2xl shadow-sm p-4 mb-4 flex gap-3">
        <input name="q" value="{{ $q }}" placeholder="Buscar cliente por nombre, DNI o RUC" class="flex-1 rounded-xl border-gray-300 text-sm">
        <button class="px-4 rounded-xl bg-indigo-600 text-white text-sm font-semibold">Buscar</button>
    </form>

    <div class="bg-white rounded-2xl shadow-sm overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs text-gray-500 uppercase"><tr>
                <th class="px-3 py-2 text-left">Cliente</th><th class="px-3 py-2 text-left">Documento</th><th class="px-3 py-2 text-right">Debe</th>
                <th class="px-3 py-2 text-right">Vencido</th><th class="px-3 py-2 text-right">Jabas</th><th class="px-3 py-2 text-left">Última compra</th><th></th></tr></thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($clientes as $c)
                    <tr class="hover:bg-gray-50">
                        <td class="px-3 py-2 font-semibold text-gray-800">{{ $c->clinom }}</td>
                        <td class="px-3 py-2 text-gray-500">{{ $c->clinum }}</td>
                        <td class="px-3 py-2 text-right font-bold {{ $c->saldo > 0 ? 'text-gray-800' : 'text-gray-300' }}">{{ number_format($c->saldo, 2) }}</td>
                        <td class="px-3 py-2 text-right {{ $c->vencido > 0 ? 'text-rose-600 font-bold' : 'text-gray-300' }}">{{ number_format($c->vencido, 2) }}</td>
                        <td class="px-3 py-2 text-right {{ $c->jabas > 0 ? 'font-bold text-orange-600' : 'text-gray-300' }}">{{ $c->jabas }}</td>
                        <td class="px-3 py-2 text-gray-500">{{ $c->ultima ? \Carbon\Carbon::parse($c->ultima)->format('d/m/Y') : '—' }}</td>
                        <td class="px-3 py-2 text-right"><a href="{{ route('avicola.cliente', $c->clicod) }}" class="text-indigo-600 hover:underline font-semibold">Ver cuenta →</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-3 py-8 text-center text-gray-400">Aún no hay clientes con ventas avícolas.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
