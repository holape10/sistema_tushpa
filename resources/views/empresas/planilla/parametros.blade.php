@extends('layouts.app')
@section('title', 'Parámetros de Planilla')
@section('content')
    @include('empresas.planilla._nav')
    @php $in = 'block w-full mt-1 rounded-lg border-gray-300 text-right'; @endphp

    <form method="POST" action="{{ route('planilla.parametros.guardar') }}" class="max-w-4xl space-y-4">
        @csrf
        <div class="flex items-center gap-2">
            <a href="{{ route('planilla.parametros', ['anio' => $anio - 1]) }}" class="w-9 h-9 rounded-xl bg-white shadow-sm flex items-center justify-center"><i class="fas fa-chevron-left"></i></a>
            <span class="px-4 py-2 rounded-xl bg-white shadow-sm font-bold">Año {{ $anio }}</span>
            <a href="{{ route('planilla.parametros', ['anio' => $anio + 1]) }}" class="w-9 h-9 rounded-xl bg-white shadow-sm flex items-center justify-center"><i class="fas fa-chevron-right"></i></a>
            <input type="hidden" name="anio" value="{{ $anio }}">
        </div>
        <div class="rounded-xl bg-amber-50 border border-amber-200 text-amber-800 text-sm px-4 py-3">
            <i class="fas fa-triangle-exclamation"></i> Verifica estos valores cada año (la RMV, la UIT y las comisiones de AFP cambian). Vienen con valores de referencia; confírmalos en
            SUNAT / SBS o con tu contador antes de pagar.
        </div>
        <section class="bg-white rounded-2xl shadow-sm p-5 grid sm:grid-cols-3 gap-4 text-sm">
            <label class="sm:col-span-3">Régimen laboral de la empresa
                <select name="regimen_laboral" class="block w-full mt-1 rounded-lg border-gray-300">
                    @foreach (\App\Support\Planilla::REGIMENES as $v => $n)<option value="{{ $v }}" @selected($p->regimen_laboral === $v)>{{ $n }}</option>@endforeach
                </select>
                <span class="text-xs text-gray-400">Define las gratificaciones que se proyectan para la renta de 5ta (general: 2, pequeña: 1, micro: ninguna).</span></label>
            <label>Remuneración mínima (RMV)<input type="number" step="0.01" name="rmv" value="{{ $p->rmv }}" class="{{ $in }}"></label>
            <label>UIT<input type="number" step="0.01" name="uit" value="{{ $p->uit }}" class="{{ $in }}"></label>
            <label>EsSalud (%)<input type="number" step="0.01" name="essalud" value="{{ $p->essalud }}" class="{{ $in }}"></label>
            <label>ONP (%)<input type="number" step="0.01" name="onp" value="{{ $p->onp }}" class="{{ $in }}"></label>
            <label class="flex items-center gap-2 sm:col-span-2 pt-5"><input type="checkbox" name="calcular_quinta" value="1" @checked($p->calcular_quinta) class="rounded"> Calcular retención de renta de 5ta categoría</label>
        </section>
        <section class="bg-white rounded-2xl shadow-sm p-5 text-sm">
            <h3 class="font-bold text-gray-800 mb-3">AFP</h3>
            <div class="grid sm:grid-cols-3 gap-4 mb-4">
                <label>Aporte obligatorio (%)<input type="number" step="0.01" name="afp_aporte" value="{{ $p->afp_aporte }}" class="{{ $in }}"></label>
                <label>Prima de seguro (%)<input type="number" step="0.01" name="afp_prima" value="{{ $p->afp_prima }}" class="{{ $in }}"></label>
                <label>Tope remuneración para la prima<input type="number" step="0.01" name="afp_tope_prima" value="{{ $p->afp_tope_prima }}" class="{{ $in }}"></label>
            </div>
            <p class="text-xs text-gray-500 mb-2">Comisión sobre el flujo (%) de cada AFP:</p>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                @foreach ($p->comisiones as $afp => $c)
                    <label>{{ $afp }}<input type="number" step="0.01" name="comisiones[{{ $afp }}]" value="{{ $c }}" class="{{ $in }}"></label>
                @endforeach
            </div>
        </section>
        <button class="px-6 py-3 rounded-xl bg-indigo-600 text-white font-semibold">Guardar parámetros de {{ $anio }}</button>
    </form>
@endsection
