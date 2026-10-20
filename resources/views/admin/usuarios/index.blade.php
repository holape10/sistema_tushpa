@extends('admin.layout')
@section('title', 'Usuarios del panel')

@section('content')
    <div class="mb-4">
        <h1 class="text-xl font-bold">Usuarios del panel</h1>
        <p class="text-sm text-slate-500">Quiénes pueden entrar a este panel. Cada usuario ve solo las empresas que él crea; el dueño ve todas.</p>
    </div>

    @if ($errors->any())
        <div class="mb-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 text-sm">
            @foreach ($errors->all() as $e)<p>{{ $e }}</p>@endforeach
        </div>
    @endif

    <div class="grid lg:grid-cols-3 gap-4 items-start">
        {{-- Nuevo usuario --}}
        <form method="POST" action="{{ route('admin.usuarios.store') }}" class="bg-white rounded-2xl shadow-sm p-5 space-y-3">
            @csrf
            <p class="font-bold">➕ Nuevo usuario</p>
            <label class="block text-sm">Nombre
                <input name="nombre" value="{{ old('nombre') }}" required maxlength="120" class="mt-1 w-full rounded-xl border-slate-300 text-sm"></label>
            <label class="block text-sm">Correo (con este entra)
                <input type="email" name="email" value="{{ old('email') }}" required maxlength="120" class="mt-1 w-full rounded-xl border-slate-300 text-sm"></label>
            <label class="block text-sm">Contraseña <span class="text-xs text-slate-400">(mínimo 10 caracteres)</span>
                <input type="password" name="password" required minlength="10" autocomplete="new-password" class="mt-1 w-full rounded-xl border-slate-300 text-sm"></label>
            @if ($esDueno)
                <label class="flex items-start gap-2 text-sm">
                    <input type="checkbox" name="puede_crear_usuarios" value="1" class="mt-0.5 rounded text-indigo-600">
                    <span>También puede crear usuarios</span>
                </label>
            @endif
            <button class="w-full h-10 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold">Crear usuario</button>
        </form>

        {{-- Lista --}}
        <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm overflow-x-auto">
            <table class="w-full text-sm tabla-movil">
                <thead class="bg-slate-50 text-slate-500 text-xs uppercase">
                    <tr><th class="text-left px-4 py-3">Usuario</th><th class="text-left px-4 py-3">Último acceso</th><th class="text-center px-4 py-3">Empresas</th><th class="text-left px-4 py-3">Permisos</th><th class="px-4 py-3"></th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($usuarios as $u)
                        <tr class="{{ $u->activo ? '' : 'bg-slate-50 text-slate-400' }}" x-data="{ clave: false }">
                            <td class="px-4 py-3">
                                <p class="font-semibold">{{ $u->nombre }}
                                    @if ($u->es_dueno)<span class="ml-1 px-1.5 rounded bg-amber-100 text-amber-800 text-[11px] font-bold">DUEÑO</span>@endif
                                    @unless ($u->activo)<span class="ml-1 px-1.5 rounded bg-rose-100 text-rose-700 text-[11px] font-bold">DESACTIVADO</span>@endunless</p>
                                <p class="text-xs text-slate-500">{{ $u->email }}</p>
                                @if ($u->creado_por)<p class="text-[11px] text-slate-400">Lo creó {{ $nombres[$u->creado_por] ?? '—' }}</p>@endif
                            </td>
                            <td class="px-4 py-3 text-xs">{{ $u->ultimo_acceso ? $u->ultimo_acceso->format('d/m/Y H:i') : 'Nunca' }}</td>
                            <td class="px-4 py-3 text-center">{{ $empresas[$u->id] ?? 0 }}</td>
                            <td class="px-4 py-3 text-xs">
                                @if ($u->es_dueno)
                                    Todo
                                @elseif ($u->puede_crear_usuarios)
                                    <span class="text-emerald-700 font-semibold">Crea usuarios</span>
                                @else
                                    Solo empresas
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @if ($puedeManejar($u))
                                    <div class="flex flex-wrap justify-end gap-1.5">
                                        @if ($esDueno)
                                            <form method="POST" action="{{ route('admin.usuarios.permiso', $u->id) }}">@csrf
                                                <button class="px-2.5 py-1 rounded-lg text-xs font-semibold {{ $u->puede_crear_usuarios ? 'bg-slate-100 text-slate-700' : 'bg-emerald-50 text-emerald-700' }}">
                                                    {{ $u->puede_crear_usuarios ? 'Quitar permiso de crear usuarios' : 'Dar permiso de crear usuarios' }}</button>
                                            </form>
                                        @endif
                                        <button type="button" @click="clave = !clave" class="px-2.5 py-1 rounded-lg bg-slate-100 text-slate-700 text-xs font-semibold">Cambiar contraseña</button>
                                        <form method="POST" action="{{ route('admin.usuarios.estado', $u->id) }}"
                                              onsubmit="return confirm('{{ $u->activo ? '¿Desactivar a '.$u->nombre.'? Ya no podrá entrar al panel.' : '¿Activar a '.$u->nombre.'?' }}')">@csrf
                                            <button class="px-2.5 py-1 rounded-lg text-xs font-semibold {{ $u->activo ? 'bg-rose-50 text-rose-700' : 'bg-indigo-50 text-indigo-700' }}">{{ $u->activo ? 'Desactivar' : 'Activar' }}</button>
                                        </form>
                                    </div>
                                    <form x-show="clave" x-cloak method="POST" action="{{ route('admin.usuarios.clave', $u->id) }}" class="mt-2 flex justify-end gap-1.5">@csrf
                                        <input type="password" name="password" required minlength="10" placeholder="Nueva (mín. 10)" autocomplete="new-password" class="h-8 rounded-lg border-slate-300 text-xs w-40">
                                        <button class="px-2.5 rounded-lg bg-indigo-600 text-white text-xs font-semibold">Guardar</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
