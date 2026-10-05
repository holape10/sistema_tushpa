@extends('layouts.app')
@section('title', 'Aperturar Turno')
@section('content')
    @include('empresas.partials.alert')
    @if (session('error'))
        <div class="mb-4 rounded-lg bg-amber-50 border border-amber-200 text-amber-800 px-4 py-2 text-sm">{{ session('error') }}</div>
    @endif

    <div class="max-w-2xl mx-auto bg-white rounded-2xl shadow-sm p-6">
        <div class="text-center mb-6">
            <div class="text-4xl mb-2">🔒</div>
            <h2 class="text-xl font-bold text-gray-800">No tienes un turno abierto</h2>
            <p class="text-sm text-gray-500">Para cobrar debes aperturar tu turno de caja. Usuario: <strong>{{ auth()->user()->apeusu }}</strong></p>
        </div>

        <form method="POST" action="{{ route('turnos.abrir') }}" x-data="arqueo()" class="space-y-5">
            @csrf
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Fondo inicial de caja (S/)</label>
                <input type="number" step="0.01" min="0" name="monto" x-model="monto" required
                       class="w-full rounded-xl border-gray-300 text-2xl font-bold text-center py-3 focus:border-indigo-500 focus:ring-indigo-500">
            </div>

            <div>
                <button type="button" @click="ver = !ver" class="text-sm text-indigo-600 hover:underline">
                    <span x-text="ver ? '▾' : '▸'"></span> Contar billetes y monedas (opcional)
                </button>
                <div x-show="ver" x-cloak class="mt-3 grid grid-cols-2 sm:grid-cols-3 gap-3">
                    @foreach ($denominaciones as $campo => $valor)
                        <label class="block">
                            <span class="text-xs text-gray-500">{{ $valor < 1 ? (int) round($valor * 100) . ' céntimos' : 'S/ ' . $valor }}</span>
                            <input type="number" min="0" step="1" name="{{ $campo }}" value="0"
                                   data-valor="{{ $valor }}" @input="sumar()"
                                   class="den w-full rounded-lg border-gray-300 text-sm">
                        </label>
                    @endforeach
                </div>
            </div>

            <button class="w-full py-3 rounded-xl bg-indigo-600 text-white font-bold text-lg hover:bg-indigo-700">
                APERTURAR TURNO
            </button>
        </form>
    </div>

    <script>
        function arqueo() {
            return {
                monto: '0.00', ver: false,
                sumar() {
                    let t = 0;
                    document.querySelectorAll('.den').forEach(i => t += (parseInt(i.value) || 0) * parseFloat(i.dataset.valor));
                    this.monto = t.toFixed(2);
                }
            };
        }
    </script>
@endsection
