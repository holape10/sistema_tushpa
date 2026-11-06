{{-- Formulario de cuenta bancaria. Recibe: $cuenta (o null), $medios, $cuentasBanco, $bancos --}}
@php $sel = array_map('intval', old('medios', $cuenta ? $medios->where('cuenta_bancaria_id', $cuenta->id)->pluck('id_med_pag')->all() : [])); @endphp
<form method="POST" action="{{ $cuenta ? route('bancos.update', $cuenta->id) : route('bancos.store') }}" class="space-y-4">
    @csrf
    @if ($cuenta) @method('PUT') @endif
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        <label class="text-xs font-semibold text-gray-600">Banco *
            <input name="banco" list="lista-bancos" value="{{ old('banco', $cuenta->banco ?? '') }}" required maxlength="60" class="mt-1 w-full h-10 rounded-xl border-gray-300 text-sm">
            <datalist id="lista-bancos">@foreach ($bancos as $b)<option value="{{ $b }}">@endforeach</datalist></label>
        <label class="text-xs font-semibold text-gray-600">N° de cuenta *
            <input name="numero" value="{{ old('numero', $cuenta->numero ?? '') }}" required maxlength="40" placeholder="191-1234567-0-12" class="mt-1 w-full h-10 rounded-xl border-gray-300 text-sm"></label>
        <label class="text-xs font-semibold text-gray-600">Cuenta contable *
            <select name="cuenta_contable" required class="mt-1 w-full h-10 rounded-xl border-gray-300 text-sm">
                @foreach ($cuentasBanco as $codigo => $nombre)
                    <option value="{{ $codigo }}" @selected(old('cuenta_contable', $cuenta->cuenta_contable ?? '104101') == $codigo)>{{ $codigo }} · {{ $nombre }}</option>
                @endforeach
            </select></label>
        <label class="text-xs font-semibold text-gray-600">Desde qué fecha la llevas *
            <input type="date" name="fecha_inicio" value="{{ old('fecha_inicio', $cuenta->fecha_inicio ?? now()->startOfMonth()->toDateString()) }}" required class="mt-1 w-full h-10 rounded-xl border-gray-300 text-sm"></label>
        <label class="text-xs font-semibold text-gray-600">Saldo del banco ese día (S/) *
            <input type="number" step="0.01" name="saldo_inicial" value="{{ old('saldo_inicial', $cuenta->saldo_inicial ?? '0') }}" required class="mt-1 w-full h-10 rounded-xl border-gray-300 text-sm"></label>
        @if ($cuenta)
            <label class="flex items-center gap-2 text-sm text-gray-600 mt-6"><input type="hidden" name="activo" value="0">
                <input type="checkbox" name="activo" value="1" @checked(old('activo', $cuenta->activo)) class="rounded text-indigo-600"> Cuenta activa</label>
        @endif
    </div>
    <div>
        <p class="text-xs font-semibold text-gray-600">¿Qué medios de pago caen en esta cuenta?</p>
        <p class="text-xs text-gray-400 mb-2">Los cobros y pagos con estos medios aparecen en el libro bancos y la contabilidad los lleva a la cuenta contable elegida.</p>
        <div class="flex flex-wrap gap-2">
            @forelse ($medios as $m)
                @php $deOtra = $m->cuenta_bancaria_id && (! $cuenta || (int) $m->cuenta_bancaria_id !== (int) $cuenta->id); @endphp
                <label class="flex items-center gap-2 rounded-xl border border-gray-200 px-3 py-1.5 text-sm {{ $deOtra ? 'opacity-50' : 'cursor-pointer hover:border-indigo-300' }}"
                       title="{{ $deOtra ? 'Ya está en otra cuenta bancaria' : '' }}">
                    <input type="checkbox" name="medios[]" value="{{ $m->id_med_pag }}" @checked(in_array((int) $m->id_med_pag, $sel, true)) @disabled($deOtra) class="rounded text-indigo-600">
                    {{ $m->nom_med_pag }}
                </label>
            @empty
                <p class="text-sm text-gray-400">No hay medios de pago registrados.</p>
            @endforelse
        </div>
    </div>
    <div class="flex justify-end"><button class="h-10 px-6 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold">{{ $cuenta ? 'Guardar cambios' : 'Crear cuenta' }}</button></div>
</form>
