@extends('layouts.app')
@section('title', 'Empresas del estudio')

@section('content')
<div class="max-w-6xl mx-auto space-y-4" x-data="{ nueva: {{ $errors->any() ? 'true' : 'false' }} }">

    <section class="bg-gradient-to-r from-indigo-700 to-indigo-500 rounded-2xl p-5 text-white flex flex-wrap items-center gap-4">
        <div class="flex-1 min-w-[220px]">
            <p class="text-xs uppercase tracking-wider text-indigo-200">Estudio contable · RUC {{ $estudio->IdEmpresa }}</p>
            <h1 class="text-xl font-extrabold">{{ $estudio->NomEmpresa }}</h1>
            <p class="text-sm text-indigo-100 mt-0.5">Cada empresa guarda su contabilidad, SIRE, planilla y asistencia por separado. Elige arriba en cuál trabajas.</p>
        </div>
        <div class="text-center">
            <p class="text-3xl font-black">{{ $activas }}@if ($limite)<span class="text-lg font-bold text-indigo-200"> / {{ $limite }}</span>@endif</p>
            <p class="text-xs text-indigo-100">{{ $limite ? 'empresas activas de tu plan' : 'empresas activas' }}</p>
        </div>
        <a href="{{ route('sire.bandeja') }}" class="h-11 px-5 inline-flex items-center rounded-xl bg-emerald-500 hover:bg-emerald-600 text-white font-bold">📥 Bandeja SIRE</a>
        @if (! $limite || $activas < $limite)
            <button type="button" @click="nueva = !nueva" class="h-11 px-5 rounded-xl bg-white text-indigo-700 font-bold hover:bg-indigo-50">+ Nueva empresa</button>
        @else
            <span class="rounded-xl bg-white/15 px-4 py-2 text-sm font-semibold">Llegaste al límite de tu plan</span>
        @endif
    </section>

    {{-- Nueva empresa: el RUC trae la razón social y la dirección de SUNAT --}}
    <form x-show="nueva" x-cloak method="POST" action="{{ route('estudio.guardar') }}" class="bg-white rounded-2xl shadow-sm p-5 grid gap-3 sm:grid-cols-2"
          x-data="{ ruc: @js(old('ruc', '')), razon: @js(old('razon_social', '')), direccion: @js(old('direccion', '')), buscando: false, aviso: '',
                    async buscar() {
                        if (!/^\d{11}$/.test(this.ruc)) return;
                        this.buscando = true; this.aviso = '';
                        try {
                            const r = await fetch(@js(url('/api/ruc')) + '/' + this.ruc, { headers: { Accept: 'application/json' } });
                            const d = await r.json();
                            if (r.ok) { this.razon = d.nom || this.razon; this.direccion = d.dir || this.direccion; } else { this.aviso = d.error || 'No se encontró en SUNAT: escribe los datos.'; }
                        } catch (e) { this.aviso = 'No se pudo consultar SUNAT: escribe los datos.'; }
                        this.buscando = false;
                    } }">
        @csrf
        <label class="text-sm font-semibold text-gray-700">RUC *
            <input name="ruc" x-model="ruc" @input.debounce.400ms="buscar()" maxlength="11" inputmode="numeric" required
                   class="mt-1 w-full rounded-xl border-gray-300 font-mono" placeholder="20xxxxxxxxx">
            <span class="text-xs font-normal" :class="aviso ? 'text-amber-600' : 'text-gray-400'" x-text="buscando ? 'Buscando en SUNAT…' : (aviso || 'Se llenan solos la razón social y la dirección.')"></span>
        </label>
        <label class="text-sm font-semibold text-gray-700">Razón social *
            <input name="razon_social" x-model="razon" required maxlength="200" class="mt-1 w-full rounded-xl border-gray-300"></label>
        <label class="text-sm font-semibold text-gray-700">Nombre comercial
            <input name="nombre_comercial" value="{{ old('nombre_comercial') }}" maxlength="150" class="mt-1 w-full rounded-xl border-gray-300"></label>
        <label class="text-sm font-semibold text-gray-700">Dirección
            <input name="direccion" x-model="direccion" maxlength="250" class="mt-1 w-full rounded-xl border-gray-300"></label>
        <div class="sm:col-span-2 rounded-xl bg-slate-50 p-4">
            <p class="text-sm font-bold text-slate-700">Claves y cuentas <span class="font-normal text-slate-500">— de una vez (opcional: lo que no tengas lo pones luego con "Configurar")</span></p>
            <div class="mt-3 grid gap-4 lg:grid-cols-3">
                @include('empresas.estudio._credenciales')
            </div>
        </div>
        <div class="sm:col-span-2 flex justify-end gap-2">
            <button type="button" @click="nueva = false" class="h-10 px-4 rounded-xl bg-gray-100 font-semibold text-gray-600">Cancelar</button>
            <button class="h-10 px-5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold">Registrar empresa</button>
        </div>
    </form>

    <form method="GET" class="flex gap-2">
        <input name="q" value="{{ $q }}" placeholder="Buscar por RUC o razón social…" class="flex-1 h-11 rounded-xl border-gray-300">
        <button class="h-11 px-5 rounded-xl bg-slate-800 text-white font-bold">Buscar</button>
    </form>

    <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 text-xs uppercase">
                    <tr>
                        <th class="text-left px-4 py-3">RUC</th>
                        <th class="text-left px-4 py-3">Razón social</th>
                        <th class="text-left px-4 py-3">SIRE</th>
                        <th class="text-left px-4 py-3">Quién la trabaja</th>
                        <th class="text-right px-4 py-3"></th>
                    </tr>
                </thead>
                    @forelse ($empresas as $e)
                        @php $conAcceso = $accesos[$e->IdEmpresa] ?? []; @endphp
                        <tbody x-data="{ accesos: false, configurar: false }">
                        <tr class="border-t border-slate-100 {{ $e->activa ? '' : 'opacity-50' }}">
                            <td class="px-4 py-3 font-mono font-bold text-indigo-700 whitespace-nowrap">{{ $e->IdEmpresa }}
                                @unless ($e->activa)<span class="ml-1 rounded bg-slate-200 px-1.5 text-[11px] font-bold text-slate-600">INACTIVA</span>@endunless</td>
                            <td class="px-4 py-3"><p class="font-semibold text-slate-800">{{ $e->NomEmpresa }}</p><p class="text-xs text-slate-400">{{ $e->DirEmpresa }}</p></td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                @if ($e->client_id && $e->sire_usuario)
                                    <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-bold text-emerald-700">SIRE ✔</span>
                                @else
                                    <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-bold text-amber-800">Faltan credenciales</span>
                                @endif
                                <span class="ml-1 rounded-full px-2 py-0.5 text-xs font-bold {{ $e->sol_usuario ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">{{ $e->sol_usuario ? 'SOL ✔' : 'Sin Clave SOL' }}</span>
                            </td>
                            <td class="px-4 py-3 relative">
                                <button type="button" @click="accesos = !accesos" class="text-xs font-semibold text-slate-600 hover:text-indigo-700">
                                    {{ $conAcceso ? count($conAcceso).' usuario(s)' : 'Solo administradores' }} ▾</button>
                                <form x-show="accesos" x-cloak @click.outside="accesos = false" method="POST" action="{{ route('estudio.accesos', $e->IdEmpresa) }}"
                                      class="absolute z-20 mt-1 w-72 rounded-xl bg-white p-3 shadow-xl ring-1 ring-slate-200 space-y-1">
                                    @csrf
                                    <p class="text-xs text-slate-500 mb-1">Los administradores ven todas. Marca quién más trabaja esta empresa:</p>
                                    @forelse ($usuarios as $u)
                                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="usuarios[]" value="{{ $u->IdUsuario }}" @checked(in_array($u->IdUsuario, $conAcceso)) class="rounded text-indigo-600">
                                            {{ $u->apeusu }}</label>
                                    @empty
                                        <p class="text-sm text-slate-400">Aún no hay usuarios (Mantenimiento → Usuarios).</p>
                                    @endforelse
                                    <button class="mt-2 w-full h-9 rounded-lg bg-indigo-600 text-white text-sm font-bold">Guardar accesos</button>
                                </form>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-2">
                                    <button type="button" @click="configurar = !configurar" class="h-9 px-3 rounded-lg bg-white ring-1 ring-indigo-200 hover:bg-indigo-50 text-indigo-700 text-xs font-bold">Configurar</button>
                                    @if ($e->activa)
                                        <form method="POST" action="{{ route('estudio.cambiar') }}">@csrf<input type="hidden" name="ruc" value="{{ $e->IdEmpresa }}">
                                            <button class="h-9 px-3 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold whitespace-nowrap">Trabajar aquí</button></form>
                                    @endif
                                    <form method="POST" action="{{ route('estudio.estado', $e->IdEmpresa) }}"
                                          @if ($e->activa) onsubmit="return confirm('¿Desactivar {{ addslashes($e->NomEmpresa) }}? Ya nadie la verá en el selector; sus datos se conservan.')" @endif>
                                        @csrf<button class="h-9 px-3 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-semibold">{{ $e->activa ? 'Desactivar' : 'Activar' }}</button></form>
                                </div>
                            </td>
                        </tr>
                        {{-- Lo que necesita la empresa: API SIRE, Clave SOL principal (XML de compras) y cuentas por defecto --}}
                        @php $cta = $cuentas[$e->IdEmpresa] ?? null; @endphp
                        <tr x-show="configurar" x-cloak class="bg-slate-50">
                            <td colspan="5" class="px-4 py-4">
                                <form method="POST" action="{{ route('estudio.configurar', $e->IdEmpresa) }}" class="grid gap-4 lg:grid-cols-3">
                                    @csrf
                                    @include('empresas.estudio._credenciales', ['e' => $e, 'cta' => $cta])
                                    <div class="lg:col-span-3 flex justify-end gap-2">
                                        <button type="button" @click="configurar = false" class="h-9 px-4 rounded-lg bg-white ring-1 ring-slate-200 text-sm font-semibold text-slate-600">Cerrar</button>
                                        <button class="h-9 px-5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold">Guardar datos de {{ $e->IdEmpresa }}</button>
                                    </div>
                                </form>
                            </td>
                        </tr>
                        </tbody>
                    @empty
                        <tbody><tr><td colspan="5" class="px-4 py-10 text-center text-slate-400">{{ $q ? 'Ninguna empresa coincide con la búsqueda.' : 'Aún no registras empresas: usa "+ Nueva empresa".' }}</td></tr></tbody>
                    @endforelse
            </table>
        </div>
    </div>
</div>
@endsection
