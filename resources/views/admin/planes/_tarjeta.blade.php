@php $nuevo = !$pl->exists; @endphp
<form method="POST" action="{{ $nuevo ? route('admin.planes.guardar') : route('admin.planes.guardar', $pl) }}"
      class="relative bg-white rounded-2xl shadow-sm p-5 space-y-3 border-2 {{ $pl->destacado ? 'border-indigo-500' : ($nuevo ? 'border-dashed border-slate-300' : 'border-transparent') }}">
    @csrf
    @if ($pl->destacado)<span class="absolute -top-2.5 right-4 px-2.5 py-0.5 rounded-full bg-indigo-600 text-white text-[10px] font-bold">MÁS POPULAR</span>@endif
    <div class="flex items-center justify-between">
        <p class="font-bold text-slate-700">{{ $nuevo ? '+ Nuevo plan' : $pl->nombre }}</p>
        @unless ($nuevo)<span class="text-xs text-slate-400">{{ $uso[$pl->id] ?? 0 }} clientes activos</span>@endunless
    </div>
    <div class="grid grid-cols-2 gap-3">
        <label class="text-xs font-semibold text-slate-500">Nombre
            <input name="nombre" value="{{ $pl->nombre }}" required maxlength="60" class="mt-1 w-full rounded-xl border-slate-300 text-sm uppercase font-bold">
        </label>
        <label class="text-xs font-semibold text-slate-500">Precio mensual S/
            <input type="number" step="0.01" min="0" name="precio" value="{{ $pl->precio }}" required class="mt-1 w-full rounded-xl border-slate-300 text-sm text-right font-bold">
        </label>
    </div>
    <label class="block text-xs font-semibold text-slate-500">Descripción corta
        <input name="descripcion" value="{{ $pl->descripcion }}" maxlength="255" class="mt-1 w-full rounded-xl border-slate-300 text-sm">
    </label>
    <label class="block text-xs font-semibold text-slate-500">Incluye (una línea por cada cosa)
        <textarea name="caracteristicas" rows="4" class="mt-1 w-full rounded-xl border-slate-300 text-sm">{{ $pl->caracteristicas }}</textarea>
    </label>
    <div class="grid grid-cols-2 gap-3">
        <label class="text-xs font-semibold text-slate-500">Máx. usuarios
            <input type="number" min="1" name="max_usuarios" value="{{ $pl->max_usuarios }}" placeholder="Ilimitado" class="mt-1 w-full rounded-xl border-slate-300 text-sm">
        </label>
        <label class="text-xs font-semibold text-slate-500">Orden
            <input type="number" min="0" name="orden" value="{{ $pl->orden ?? 0 }}" class="mt-1 w-full rounded-xl border-slate-300 text-sm">
        </label>
    </div>
    <div class="flex flex-wrap gap-x-4 gap-y-1 text-sm">
        <label class="inline-flex items-center gap-2"><input type="checkbox" name="tienda_virtual" value="1" @checked($pl->tienda_virtual) class="rounded"> Tienda virtual</label>
        <label class="inline-flex items-center gap-2"><input type="checkbox" name="destacado" value="1" @checked($pl->destacado) class="rounded"> Más popular</label>
        <label class="inline-flex items-center gap-2"><input type="hidden" name="activo" value="0"><input type="checkbox" name="activo" value="1" @checked($pl->activo) class="rounded"> Activo</label>
    </div>
    <button class="w-full h-10 rounded-xl {{ $nuevo ? 'bg-slate-800' : 'bg-indigo-600 hover:bg-indigo-700' }} text-white text-sm font-bold">{{ $nuevo ? 'Crear plan' : 'Guardar' }}</button>
</form>
