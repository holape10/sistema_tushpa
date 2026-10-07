{{-- Datos de un paciente nuevo. Uso: x-data con objeto `p` (tipo, tdicod, clinum, clinom, telefono, mascota, especie, raza, sexo, fecha_nac) y método buscarDoc() --}}
@php $in = 'block w-full rounded-lg border-gray-300 text-sm focus:border-teal-500 focus:ring-teal-500'; @endphp
<div class="space-y-3">
    @if ($hayVeterinaria)
        <div class="flex gap-2 text-sm">
            <label class="flex items-center gap-1"><input type="radio" value="PERSONA" x-model="p.tipo"> Persona</label>
            <label class="flex items-center gap-1"><input type="radio" value="MASCOTA" x-model="p.tipo"> Mascota</label>
        </div>
    @endif
    <div x-show="p.tipo === 'MASCOTA'" class="grid grid-cols-3 gap-2">
        <label class="block text-xs font-semibold text-gray-500 col-span-3">Nombre de la mascota
            <input x-model="p.mascota" maxlength="100" class="{{ $in }} mt-1 uppercase" placeholder="FIRULAIS"></label>
        <label class="block text-xs font-semibold text-gray-500">Especie
            <input x-model="p.especie" list="especies" maxlength="40" class="{{ $in }} mt-1 uppercase" placeholder="CANINO"></label>
        <label class="block text-xs font-semibold text-gray-500">Raza
            <input x-model="p.raza" maxlength="60" class="{{ $in }} mt-1 uppercase"></label>
        <label class="block text-xs font-semibold text-gray-500">Sexo
            <select x-model="p.sexo" class="{{ $in }} mt-1"><option value="">—</option><option value="M">Macho</option><option value="F">Hembra</option></select></label>
        <datalist id="especies"><option>CANINO</option><option>FELINO</option><option>AVE</option><option>CONEJO</option></datalist>
        <p class="col-span-3 text-xs text-gray-500 pt-1 border-t">Datos del dueño:</p>
    </div>
    <div class="grid grid-cols-3 gap-2">
        <label class="block text-xs font-semibold text-gray-500">DNI / RUC
            <input x-model="p.clinum" @change="buscarDoc()" @keydown.enter.prevent="buscarDoc()" maxlength="15" inputmode="numeric" class="{{ $in }} mt-1"></label>
        <label class="block text-xs font-semibold text-gray-500 col-span-2"><span x-text="p.tipo === 'MASCOTA' ? 'Nombre del dueño' : 'Apellidos y nombres'"></span>
            <input x-model="p.clinom" maxlength="150" class="{{ $in }} mt-1 uppercase"></label>
        <label class="block text-xs font-semibold text-gray-500">Teléfono
            <input x-model="p.telefono" maxlength="20" class="{{ $in }} mt-1"></label>
        <label class="block text-xs font-semibold text-gray-500">Nacimiento
            <input type="date" x-model="p.fecha_nac" class="{{ $in }} mt-1"></label>
        <label class="block text-xs font-semibold text-gray-500 col-span-3 sm:col-span-1">¿Cómo nos conoció?
            <select x-model="p.origen" class="{{ $in }} mt-1"><option value="">—</option>@foreach (\App\Support\Clinica::ORIGENES as $o)<option>{{ $o }}</option>@endforeach</select></label>
        <label x-show="p.tipo !== 'MASCOTA'" class="block text-xs font-semibold text-gray-500">Sexo
            <select x-model="p.sexo" class="{{ $in }} mt-1"><option value="">—</option><option value="M">Masculino</option><option value="F">Femenino</option></select></label>
    </div>
    <p x-show="p.msg" x-text="p.msg" class="text-xs text-teal-700"></p>
</div>
