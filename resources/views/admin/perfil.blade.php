@extends('admin.layout')
@section('title', 'Mi perfil')

@section('content')
    @php $in = 'mt-1 block w-full rounded-xl border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500'; @endphp

    @if ($errors->any())
        <div class="mb-4 rounded-xl bg-amber-50 border border-amber-200 text-amber-900 px-4 py-3 text-sm">
            @foreach ($errors->all() as $error)<div>• {{ $error }}</div>@endforeach
        </div>
    @endif

    {{-- Cabecera con la foto --}}
    <div class="bg-gradient-to-r from-indigo-700 to-indigo-500 rounded-2xl p-5 text-white flex items-center gap-4 mb-5">
        @include('admin.partials.avatar', ['usuario' => $yo, 'tamano' => 'w-20 h-20 text-2xl'])
        <div class="min-w-0">
            <h1 class="text-xl font-black truncate">{{ $yo->nombre }}</h1>
            <p class="text-sm text-indigo-100 truncate">{{ $yo->email }}</p>
            <p class="text-xs text-indigo-200 mt-1">{{ $yo->es_dueno ? 'Dueño del sistema' : 'Usuario del panel' }}
                @if ($yo->ultimo_acceso) · Último acceso {{ $yo->ultimo_acceso->format('d/m/Y H:i') }}@endif</p>
        </div>
    </div>

    <div class="grid lg:grid-cols-2 gap-5">
        {{-- ================= Mis datos ================= --}}
        <form method="POST" action="{{ route('admin.perfil.guardar') }}" enctype="multipart/form-data" class="bg-white rounded-2xl shadow-sm p-5 space-y-3"
              x-data="{ previa: null }">
            @csrf
            <h2 class="font-bold text-slate-700">👤 Mis datos</h2>
            <div class="flex items-center gap-4">
                <template x-if="previa"><img :src="previa" class="w-20 h-20 rounded-full object-cover ring-2 ring-indigo-200" alt="Foto nueva"></template>
                <div x-show="!previa">@include('admin.partials.avatar', ['usuario' => $yo, 'tamano' => 'w-20 h-20 text-2xl'])</div>
                <div class="space-y-1">
                    <label class="inline-block px-3 py-2 rounded-xl bg-indigo-50 text-indigo-700 text-sm font-bold cursor-pointer hover:bg-indigo-100">📷 {{ $yo->foto ? 'Cambiar foto' : 'Subir foto' }}
                        <input type="file" name="foto" accept="image/png,image/jpeg,image/webp" class="sr-only"
                               @change="previa = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : null"></label>
                    @if ($yo->foto)<button form="quitar-foto" class="block text-xs text-rose-600 hover:underline">Quitar foto</button>@endif
                </div>
            </div>
            <label class="block text-sm font-semibold text-slate-600">Nombre
                <input name="nombre" value="{{ old('nombre', $yo->nombre) }}" required maxlength="120" class="{{ $in }}"></label>
            <div class="grid sm:grid-cols-2 gap-3">
                <label class="block text-sm font-semibold text-slate-600">Celular
                    <input name="celular" value="{{ old('celular', $yo->celular) }}" maxlength="20" inputmode="tel" placeholder="987654321" class="{{ $in }}"></label>
                <label class="block text-sm font-semibold text-slate-600">Fecha de nacimiento
                    <input type="date" name="fecha_nacimiento" value="{{ old('fecha_nacimiento', $yo->fecha_nacimiento?->format('Y-m-d')) }}" max="{{ now()->subDay()->format('Y-m-d') }}" class="{{ $in }}"></label>
            </div>
            <label class="block text-sm font-semibold text-slate-600">Correo para entrar
                <input value="{{ $yo->email }}" disabled class="{{ $in }} bg-slate-50 text-slate-500">
                <span class="block mt-1 text-xs font-normal text-slate-400">Con este correo entras al panel. No se cambia aquí.</span></label>
            <div class="flex justify-end"><button class="px-5 h-10 rounded-xl bg-indigo-600 text-white text-sm font-bold">Guardar mis datos</button></div>
        </form>

        {{-- ================= Contraseña ================= --}}
        <div class="bg-white rounded-2xl shadow-sm p-5 space-y-3" x-data="{ ver: false }">
            <h2 class="font-bold text-slate-700">🔒 Cambiar mi contraseña</h2>
            @if ($proximoCambio)
                <div class="rounded-xl bg-amber-50 border border-amber-200 p-4 text-sm text-amber-900">
                    <p class="font-bold">Ya cambiaste tu contraseña esta semana.</p>
                    <p class="mt-1">Podrás cambiarla de nuevo el <b>{{ $proximoCambio->format('d/m/Y') }}</b> a las {{ $proximoCambio->format('H:i') }}.</p>
                    @unless ($yo->es_dueno)<p class="mt-2 text-xs">¿La olvidaste? Pide al dueño (o a quien te creó) que te la restablezca en <b>Usuarios</b>.</p>@endunless
                </div>
            @else
                <p class="text-xs text-slate-500">Puedes cambiarla <b>una vez por semana</b>. Usa al menos 10 caracteres, mejor con números y letras.</p>
                <form method="POST" action="{{ route('admin.perfil.clave') }}" class="space-y-3">
                    @csrf
                    <label class="block text-sm font-semibold text-slate-600">Contraseña actual
                        <input :type="ver ? 'text' : 'password'" name="actual" required autocomplete="current-password" class="{{ $in }}"></label>
                    <label class="block text-sm font-semibold text-slate-600">Nueva contraseña
                        <input :type="ver ? 'text' : 'password'" name="password" required minlength="10" autocomplete="new-password" class="{{ $in }}"></label>
                    <label class="block text-sm font-semibold text-slate-600">Repite la nueva contraseña
                        <input :type="ver ? 'text' : 'password'" name="password_confirmation" required minlength="10" autocomplete="new-password" class="{{ $in }}"></label>
                    <label class="flex items-center gap-2 text-sm text-slate-600"><input type="checkbox" x-model="ver" class="rounded text-indigo-600"> Mostrar lo que escribo</label>
                    <div class="flex justify-end"><button class="px-5 h-10 rounded-xl bg-slate-800 text-white text-sm font-bold">Cambiar contraseña</button></div>
                </form>
            @endif
            <p class="text-xs text-slate-400 pt-2 border-t">🔐 Nadie del equipo te pedirá tu contraseña por WhatsApp ni por correo.</p>
        </div>
    </div>
    <form id="quitar-foto" method="POST" action="{{ route('admin.perfil.foto.quitar') }}" onsubmit="return confirm('¿Quitar tu foto?')">@csrf</form>
@endsection
