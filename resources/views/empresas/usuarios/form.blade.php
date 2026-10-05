@extends('layouts.app')
@section('title', $usuario ? 'Editar Empleado' : 'Nuevo Empleado')
@section('content')
    @include('empresas.partials.alert')

    @php
        $in = 'block w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500';
        $v = fn($campo, $defecto = null) => old($campo, $empleado->$campo ?? $usuario->$campo ?? $defecto);
        $rolSel = (int) old('role_id', $rolActual);
        $modSel = array_map('intval', old('modulos', $modulosAsignados));
    @endphp

    <form method="POST" action="{{ $usuario ? route('usuarios.update', $usuario->IdUsuario) : route('usuarios.store') }}"
          class="max-w-6xl space-y-5" x-data="formUsuario({{ $rolSel ?: 'null' }}, {{ $usuario ? 'true' : 'false' }})">
        @csrf
        @if ($usuario) @method('PUT') @endif

        {{-- INFORMACIÓN PERSONAL --}}
        <div class="bg-white rounded-2xl shadow-sm p-5">
            <h3 class="text-sm font-bold text-indigo-700 uppercase tracking-wide mb-4">Información personal</h3>
            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <label class="text-sm">Sucursal de trabajo
                    <select name="id_empresa_negocio" class="{{ $in }}">
                        @foreach ($sucursales as $s)
                            <option value="{{ $s->id_empresa_negocio }}" @selected($v('id_empresa_negocio', auth()->user()->id_empresa_negocio) == $s->id_empresa_negocio)>
                                {{ $s->nombre_comercial }} - {{ $s->IdEmpresa }}</option>
                        @endforeach
                    </select></label>
                <label class="text-sm">Tipo doc.
                    <select name="tdicod" class="{{ $in }}">
                        @foreach ($documentos as $d)
                            <option value="{{ $d->tdicod }}" @selected($v('tdicod', '1') == $d->tdicod)>{{ $d->tdides }}</option>
                        @endforeach
                    </select></label>
                <label class="text-sm">N° documento
                    <input name="emp_num_doc" value="{{ $v('emp_num_doc') }}" maxlength="15" placeholder="DNI / CE" class="{{ $in }}"></label>
                <label class="text-sm">Nombres *
                    <input name="emp_nom" value="{{ $v('emp_nom') }}" required maxlength="100" class="{{ $in }} uppercase"></label>
                <label class="text-sm">Apellido paterno *
                    <input name="emp_ape_pat" value="{{ $v('emp_ape_pat') }}" required maxlength="100" class="{{ $in }} uppercase"></label>
                <label class="text-sm">Apellido materno
                    <input name="emp_ape_mat" value="{{ $v('emp_ape_mat') }}" maxlength="100" class="{{ $in }} uppercase"></label>
                <label class="text-sm">Género
                    <select name="sex_cod" class="{{ $in }}">
                        <option value="">—</option>
                        <option value="M" @selected($v('sex_cod') == 'M')>MASCULINO</option>
                        <option value="F" @selected($v('sex_cod') == 'F')>FEMENINO</option>
                    </select></label>
                <label class="text-sm">Fecha de nacimiento
                    <input type="date" name="emp_fec_nac" value="{{ $v('emp_fec_nac') }}" max="{{ now()->subDay()->toDateString() }}" class="{{ $in }}"></label>
                <label class="text-sm">Estado
                    <select name="estusu" class="{{ $in }}">
                        <option value="1" @selected((string) old('estusu', $usuario->estusu ?? 1) === '1')>ACTIVO</option>
                        <option value="0" @selected((string) old('estusu', $usuario->estusu ?? 1) === '0')>INACTIVO</option>
                    </select></label>
                <label class="text-sm">Marca asistencia
                    <select name="asistencia" class="{{ $in }}">
                        <option value="0" @selected((string) $v('asistencia', 0) === '0')>NO</option>
                        <option value="1" @selected((string) $v('asistencia', 0) === '1')>SÍ</option>
                    </select></label>
            </div>
        </div>

        {{-- CONTACTO --}}
        <div class="bg-white rounded-2xl shadow-sm p-5">
            <h3 class="text-sm font-bold text-green-700 uppercase tracking-wide mb-4">Contacto y ubicación</h3>
            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <label class="text-sm">Teléfono fijo<input name="emp_tel" value="{{ $v('emp_tel') }}" maxlength="20" class="{{ $in }}"></label>
                <label class="text-sm">Celular<input name="emp_cel" value="{{ $v('emp_cel') }}" maxlength="20" class="{{ $in }}"></label>
                <label class="text-sm">Correo electrónico <span class="text-gray-400">(opcional)</span>
                    <input type="email" name="emp_cor" value="{{ $v('emp_cor') }}" maxlength="100" placeholder="ejemplo@correo.com" class="{{ $in }}"></label>
                <label class="text-sm">Dirección de residencia<input name="emp_dir" value="{{ $v('emp_dir') }}" maxlength="200" class="{{ $in }}"></label>
            </div>
        </div>

        {{-- CREDENCIALES --}}
        <div class="bg-white rounded-2xl shadow-sm p-5">
            <h3 class="text-sm font-bold text-amber-700 uppercase tracking-wide mb-4">Credenciales y rol</h3>
            <div class="grid sm:grid-cols-2 lg:grid-cols-5 gap-4">
                <label class="text-sm">Usuario de acceso *
                    <input name="email" value="{{ old('email', $usuario->email ?? '') }}" required maxlength="50" autocomplete="off"
                           placeholder="Nombre de usuario" class="{{ $in }}"></label>
                <label class="text-sm">Rol en el sistema *
                    <select name="role_id" x-model.number="rol" @change="aplicarPreset()" required class="{{ $in }}">
                        <option value="">-- Selecciona --</option>
                        @foreach ($roles as $r)
                            <option value="{{ $r->id }}" @selected($rolSel === (int) $r->id)>{{ $r->description }}</option>
                        @endforeach
                    </select></label>
                <label class="text-sm">Código móvil <span x-show="rol === 8" class="text-red-600">*</span>
                    <input name="codigo_movil" value="{{ old('codigo_movil', $usuario->codigo_movil ?? '') }}" inputmode="numeric" maxlength="6"
                           :required="rol === 8" placeholder="Solo números" oninput="this.value = this.value.replace(/\D/g, '')"
                           :class="rol === 8 ? 'bg-sky-50 border-sky-400' : ''" class="{{ $in }}">
                    <span class="text-xs text-gray-400" x-show="rol === 8">Con este código el mozo entra desde la tablet o celular.</span></label>
                <label class="text-sm">Contraseña {{ $usuario ? '' : '*' }}
                    <div class="relative" x-data="{ ver: false }">
                        <input :type="ver ? 'text' : 'password'" name="password" {{ $usuario ? '' : 'required' }} minlength="4" autocomplete="new-password"
                               placeholder="{{ $usuario ? 'Dejar vacío para no cambiar' : '' }}" class="{{ $in }} pr-9">
                        <button type="button" @click="ver = !ver" class="absolute inset-y-0 right-0 px-2 text-gray-400 text-xs">👁</button>
                    </div></label>
                <label class="text-sm">Confirmar contraseña
                    <input type="password" name="password_confirmation" autocomplete="new-password" class="{{ $in }}"></label>
            </div>
        </div>

        {{-- PERMISOS --}}
        <div class="bg-white rounded-2xl shadow-sm p-5">
            <div class="flex flex-wrap items-center gap-2 mb-1">
                <h3 class="text-sm font-bold text-gray-700 uppercase tracking-wide">Permisos del sistema</h3>
                <button type="button" @click="aplicarPreset(true)" class="ml-auto text-xs px-3 py-1 rounded-lg bg-indigo-50 text-indigo-700 hover:bg-indigo-100">Marcar según el rol</button>
                <button type="button" @click="marcarTodos(false)" class="text-xs px-3 py-1 rounded-lg bg-gray-100 text-gray-600 hover:bg-gray-200">Desmarcar todo</button>
            </div>
            <p class="text-xs text-gray-400 mb-4">Opciones del menú que verá este usuario. Al elegir el rol se marcan unas sugeridas.</p>
            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3">
                @foreach ($modulos as $grupo => $items)
                    <div class="border border-gray-200 rounded-xl p-3 bg-gray-50">
                        <h4 class="text-xs font-bold text-gray-700 uppercase mb-2 pb-1 border-b border-gray-200">{{ $grupo }}</h4>
                        @foreach ($items as $mod)
                            <label class="flex items-center gap-2 text-sm text-gray-600 py-0.5">
                                <input type="checkbox" name="modulos[]" value="{{ $mod->mod_id }}" class="chk-modulo rounded border-gray-300 text-indigo-600"
                                       @checked(in_array($mod->mod_id, $modSel, true))>
                                {{ $mod->mod_nom }}
                            </label>
                        @endforeach
                    </div>
                @endforeach
            </div>
        </div>

        <div class="flex justify-end gap-3">
            <a href="{{ route('usuarios.index') }}" class="px-6 py-2.5 rounded-xl border border-gray-300 text-sm font-medium text-gray-700 hover:bg-gray-50">✕ Cancelar</a>
            <button class="px-6 py-2.5 rounded-xl bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700">
                {{ $usuario ? 'GUARDAR CAMBIOS' : 'REGISTRAR EMPLEADO' }}</button>
        </div>
    </form>

    <script>
        const PRESETS = @json($presets);
        function formUsuario(rol, editando) {
            return {
                rol,
                // Al crear, elegir el rol marca los módulos sugeridos; al editar solo si se pide con el botón
                aplicarPreset(forzar = false) {
                    if (editando && !forzar) return;
                    const ids = (PRESETS[this.rol] || []).map(Number);
                    document.querySelectorAll('.chk-modulo').forEach(c => c.checked = ids.includes(Number(c.value)));
                },
                marcarTodos(v) { document.querySelectorAll('.chk-modulo').forEach(c => c.checked = v); },
            };
        }
    </script>
@endsection
