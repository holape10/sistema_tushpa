@extends('layouts.app')
@section('title', 'Trabajadores')
@section('content')
    @include('empresas.planilla._nav')

    <div x-data="trabajadores(@js($afps))">
        <p class="text-sm text-gray-500 mb-3">Los trabajadores son los usuarios/empleados del sistema. Completa su sueldo y sistema de pensiones para incluirlos en la planilla.
            Para agregar uno nuevo, créalo en <a href="{{ route('usuarios.index') }}" class="text-indigo-600 font-semibold hover:underline">Usuarios</a>.</p>
        <div class="bg-white rounded-2xl shadow-sm overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-700 text-white text-xs uppercase">
                    <tr><th class="px-3 py-2 text-left">Trabajador</th><th class="px-3 py-2 text-left">Cargo</th><th class="px-3 py-2 text-left">Ingreso</th>
                        <th class="px-3 py-2 text-right">Sueldo</th><th class="px-3 py-2 text-center">Asig. fam.</th><th class="px-3 py-2">Pensión</th><th class="px-3 py-2 text-center">En planilla</th><th></th></tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($empleados as $e)
                        <tr class="hover:bg-gray-50 {{ $e->activo === null ? 'bg-amber-50/40' : '' }}">
                            <td class="px-3 py-2"><span class="font-semibold text-gray-800 uppercase">{{ trim(implode(' ', array_filter([$e->emp_ape_pat, $e->emp_ape_mat])) . ', ' . $e->emp_nom, ' ,') }}</span>
                                <span class="block text-xs text-gray-400">DNI {{ $e->emp_num_doc ?: '—' }} · {{ $e->sucursal }}</span></td>
                            <td class="px-3 py-2 text-gray-600">{{ $e->cargo ?? '—' }}</td>
                            <td class="px-3 py-2 text-gray-600 whitespace-nowrap">{{ $e->fecha_ingreso ? \Carbon\Carbon::parse($e->fecha_ingreso)->format('d/m/Y') : '—' }}</td>
                            <td class="px-3 py-2 text-right font-semibold">{{ $e->sueldo !== null ? number_format($e->sueldo, 2) : '—' }}</td>
                            <td class="px-3 py-2 text-center">{!! $e->asignacion_familiar ? '<i class="fas fa-check text-emerald-600"></i>' : '' !!}</td>
                            <td class="px-3 py-2 text-center text-xs">{{ $e->sistema_pension ? ($e->sistema_pension === 'AFP' ? 'AFP ' . $e->afp : $e->sistema_pension) : '—' }}</td>
                            <td class="px-3 py-2 text-center">
                                @if ($e->activo === null)<span class="text-[10px] px-2 py-0.5 rounded-full bg-amber-100 text-amber-700 font-bold">SIN DATOS</span>
                                @elseif ($e->activo)<span class="text-[10px] px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 font-bold">SÍ</span>
                                @else<span class="text-[10px] px-2 py-0.5 rounded-full bg-gray-100 text-gray-500 font-bold">NO</span>@endif
                            </td>
                            <td class="px-3 py-2 text-right"><button type="button" class="text-indigo-600 text-sm font-semibold hover:underline"
                                @click="editar({{ $e->id }}, @js(trim("{$e->emp_nom} {$e->emp_ape_pat}")), @js(['cargo' => $e->cargo, 'fecha_ingreso' => $e->fecha_ingreso, 'fecha_cese' => $e->fecha_cese,
                                    'sueldo' => $e->sueldo ?? (float) $rmv, 'asignacion_familiar' => (bool) $e->asignacion_familiar, 'sistema_pension' => $e->sistema_pension ?? 'ONP',
                                    'afp' => $e->afp, 'afp_comision' => $e->afp_comision ?? 'FLUJO', 'cuspp' => $e->cuspp, 'banco' => $e->banco, 'cuenta' => $e->cuenta,
                                    'activo' => $e->activo === null ? true : (bool) $e->activo]))">{{ $e->activo === null ? 'Completar' : 'Editar' }}</button></td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-4 py-10 text-center text-gray-400">No hay empleados registrados.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div x-show="form" x-cloak class="fixed inset-0 z-50 bg-black/40 flex items-end sm:items-center justify-center sm:p-4" @click.self="form = null">
            <form @submit.prevent="guardar()" class="bg-white w-full sm:max-w-xl rounded-t-2xl sm:rounded-2xl shadow-xl max-h-[95vh] overflow-y-auto">
                <template x-if="form">
                    <div>
                        <div class="px-5 py-3 border-b"><h3 class="font-bold text-gray-800 uppercase" x-text="nombre"></h3></div>
                        <div class="p-5 space-y-3 text-sm">
                            <div class="grid grid-cols-2 gap-3">
                                <label class="col-span-2">Cargo<input x-model="form.cargo" maxlength="100" class="block w-full mt-1 rounded-lg border-gray-300 uppercase"></label>
                                <label>Fecha de ingreso<input type="date" x-model="form.fecha_ingreso" class="block w-full mt-1 rounded-lg border-gray-300"></label>
                                <label>Fecha de cese<input type="date" x-model="form.fecha_cese" class="block w-full mt-1 rounded-lg border-gray-300"></label>
                                <label>Sueldo mensual (S/)<input type="number" step="0.01" min="0" x-model.number="form.sueldo" required class="block w-full mt-1 rounded-lg border-gray-300 text-right font-bold"></label>
                                <label class="flex items-center gap-2 pt-5"><input type="checkbox" x-model="form.asignacion_familiar" class="rounded"> Asignación familiar (hijos menores)</label>
                            </div>
                            <div class="rounded-xl bg-gray-50 p-3 grid grid-cols-2 gap-3">
                                <label>Sistema de pensiones
                                    <select x-model="form.sistema_pension" class="block w-full mt-1 rounded-lg border-gray-300"><option>ONP</option><option>AFP</option><option value="NINGUNO">Ninguno</option></select></label>
                                <template x-if="form.sistema_pension === 'AFP'">
                                    <div class="contents">
                                        <label>AFP<select x-model="form.afp" required class="block w-full mt-1 rounded-lg border-gray-300"><option value="">Elegir…</option>
                                            <template x-for="a in afps"><option :value="a" x-text="a" :selected="a === form.afp"></option></template></select></label>
                                        <label>Comisión<select x-model="form.afp_comision" class="block w-full mt-1 rounded-lg border-gray-300"><option value="FLUJO">Sobre flujo (sueldo)</option><option value="MIXTA">Mixta (sobre saldo)</option></select></label>
                                        <label>CUSPP<input x-model="form.cuspp" maxlength="15" class="block w-full mt-1 rounded-lg border-gray-300 uppercase"></label>
                                    </div>
                                </template>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <label>Banco<input x-model="form.banco" maxlength="30" class="block w-full mt-1 rounded-lg border-gray-300 uppercase"></label>
                                <label>N° de cuenta<input x-model="form.cuenta" maxlength="30" class="block w-full mt-1 rounded-lg border-gray-300"></label>
                            </div>
                            <label class="flex items-center gap-2"><input type="checkbox" x-model="form.activo" class="rounded"> Incluir en la planilla</label>
                            <p class="text-rose-600 font-semibold" x-text="error"></p>
                        </div>
                        <div class="px-5 py-3 border-t flex justify-end gap-2">
                            <button type="button" @click="form = null" class="px-4 py-2 rounded-xl bg-gray-100 font-semibold">Cancelar</button>
                            <button class="px-6 py-2 rounded-xl bg-indigo-600 text-white font-semibold">Guardar</button>
                        </div>
                    </div>
                </template>
            </form>
        </div>
    </div>
    <script>
        function trabajadores(afps) {
            return {
                afps, form: null, id: null, nombre: '', error: '',
                editar(id, nombre, datos) { this.id = id; this.nombre = nombre; this.form = datos; this.error = ''; },
                async guardar() {
                    const r = await fetch(@js(url('planilla/trabajadores')) + '/' + this.id, { method: 'POST',
                        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': @js(csrf_token()) }, body: JSON.stringify(this.form) });
                    const d = await r.json();
                    if (r.status === 422) { this.error = Object.values(d.errors)[0][0]; return; }
                    location.reload();
                },
            };
        }
    </script>
@endsection
