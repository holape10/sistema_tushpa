@extends('layouts.app')
@section('title', 'Plan Contable')
@section('content')
    @include('empresas.contabilidad._nav')

    @php
        $colorTipo = ['ACTIVO' => 'bg-sky-100 text-sky-700', 'PASIVO' => 'bg-rose-100 text-rose-700', 'PATRIMONIO' => 'bg-violet-100 text-violet-700',
            'GASTO' => 'bg-amber-100 text-amber-800', 'INGRESO' => 'bg-emerald-100 text-emerald-700', 'RESULTADO' => 'bg-slate-100 text-slate-600',
            'ANALITICA' => 'bg-slate-100 text-slate-600', 'ORDEN' => 'bg-slate-100 text-slate-600'];
        $camposConfig = [
            'Ventas' => ['cta_por_cobrar' => 'Cuentas por cobrar (clientes)', 'cta_igv' => 'IGV - cuenta propia', 'cta_ventas' => 'Ventas gravadas', 'cta_ventas_exo' => 'Ventas exoneradas / inafectas'],
            'Compras' => ['cta_por_pagar' => 'Cuentas por pagar (proveedores)', 'cta_compras' => 'Compras de mercaderías', 'cta_mercaderias' => 'Mercaderías (almacén)', 'cta_variacion' => 'Variación de inventarios'],
            'Caja, bancos y costo' => ['cta_caja' => 'Caja (efectivo)', 'cta_banco' => 'Banco (tarjeta, Yape, transferencia…)', 'cta_costo_ventas' => 'Costo de ventas',
                'cta_renta4_pagar' => 'Retención 4ta categoría (honorarios)'],
            'Planilla' => ['cta_sueldos' => 'Sueldos y salarios (gasto)', 'cta_essalud_gasto' => 'EsSalud (gasto)', 'cta_remun_pagar' => 'Remuneraciones por pagar',
                'cta_essalud_pagar' => 'EsSalud por pagar', 'cta_onp_pagar' => 'ONP por pagar', 'cta_afp_pagar' => 'AFP por pagar',
                'cta_renta5_pagar' => 'Renta 5ta por pagar', 'cta_adelantos' => 'Adelantos al personal'],
        ];
    @endphp

    <div x-data="{ tab: @js(request('tab', 'plan')), form: null }">
        <div class="flex gap-2 mb-4">
            <button type="button" @click="tab = 'plan'" :class="tab === 'plan' ? 'bg-slate-800 text-white' : 'bg-white text-gray-600'" class="px-4 py-2 rounded-xl text-sm font-semibold shadow-sm">Plan de cuentas</button>
            <button type="button" @click="tab = 'config'" :class="tab === 'config' ? 'bg-slate-800 text-white' : 'bg-white text-gray-600'" class="px-4 py-2 rounded-xl text-sm font-semibold shadow-sm">Cuentas de centralización</button>
        </div>

        {{-- ============ PLAN ============ --}}
        <div x-show="tab === 'plan'">
            <div class="flex flex-col lg:flex-row gap-3 mb-3">
                <form method="GET" class="flex flex-1 gap-2">
                    <input type="search" name="q" value="{{ $q }}" placeholder="Buscar cuenta o nombre…" class="flex-1 rounded-xl border-gray-300 text-sm">
                    <button class="px-4 rounded-xl bg-indigo-600 text-white text-sm font-semibold">Buscar</button>
                </form>
                <div class="flex gap-2">
                    <button type="button" @click="form = { cuenta: '', descripcion: '', estado: 'Activo', nueva: true }" class="px-4 py-2 rounded-xl bg-emerald-600 text-white text-sm font-semibold hover:bg-emerald-700"><i class="fas fa-plus"></i> Nueva subcuenta</button>
                    <a href="{{ route('contabilidad.plan.excel') }}" class="px-4 py-2 rounded-xl bg-green-700 text-white text-sm font-semibold"><i class="fas fa-file-excel"></i></a>
                </div>
            </div>
            <div class="flex flex-wrap gap-1.5 mb-3 text-xs">
                <a href="{{ route('contabilidad.plan') }}" class="px-3 py-1 rounded-full font-semibold {{ !request('elemento') ? 'bg-indigo-600 text-white' : 'bg-white text-gray-600' }}">Todos</a>
                @foreach ($elementos as $e => $nom)
                    <a href="{{ route('contabilidad.plan', ['elemento' => $e]) }}" class="px-3 py-1 rounded-full font-semibold {{ request('elemento') == $e ? 'bg-indigo-600 text-white' : 'bg-white text-gray-600 hover:bg-gray-50' }}">{{ $e }} · {{ $nom }}</a>
                @endforeach
            </div>

            <div class="bg-white rounded-2xl shadow-sm overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-700 text-white text-xs uppercase">
                        <tr><th class="px-4 py-2 text-left w-36">Cuenta</th><th class="px-4 py-2 text-left">Denominación</th><th class="px-3 py-2">Tipo</th>
                            <th class="px-3 py-2">Naturaleza</th><th class="px-3 py-2">Uso</th><th class="px-3 py-2"></th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($cuentas as $c)
                            <tr class="hover:bg-gray-50 {{ $c->nivel == 2 ? 'bg-slate-50 font-bold' : '' }} {{ $c->estado !== 'Activo' ? 'opacity-50' : '' }}">
                                <td class="px-4 py-1.5 font-mono" style="padding-left: {{ 1 + ($c->nivel - 2) * 0.6 }}rem">{{ $c->cuenta }}</td>
                                <td class="px-4 py-1.5 {{ $c->imputable ? 'text-gray-700' : 'text-gray-900' }}" style="padding-left: {{ 1 + ($c->nivel - 2) * 0.6 }}rem">{{ $c->descripcion }}</td>
                                <td class="px-3 py-1.5 text-center"><span class="text-[10px] px-2 py-0.5 rounded-full font-bold {{ $colorTipo[$c->tipo] ?? '' }}">{{ $c->tipo }}</span></td>
                                <td class="px-3 py-1.5 text-center text-xs text-gray-500">{{ $c->naturaleza === 'D' ? 'Deudora' : 'Acreedora' }}</td>
                                <td class="px-3 py-1.5 text-center text-xs">
                                    @if ($c->imputable)<span class="text-emerald-700 font-semibold">Imputable</span>@else<span class="text-gray-400">Título</span>@endif
                                    @if (isset($usadas[$c->cuenta]))<i class="fas fa-circle text-[6px] text-indigo-500 align-middle ml-1" title="Tiene movimientos"></i>@endif
                                </td>
                                <td class="px-3 py-1.5 text-right whitespace-nowrap">
                                    <button type="button" class="text-indigo-600 text-xs hover:underline" @click="form = @js(['cuenta' => $c->cuenta, 'descripcion' => $c->descripcion, 'estado' => $c->estado, 'nueva' => false])">Editar</button>
                                    <button type="button" class="text-emerald-600 text-xs hover:underline ml-2" @click="form = { cuenta: @js($c->cuenta), descripcion: '', estado: 'Activo', nueva: true }">+ Sub</button>
                                    @if ($c->nivel > 2 && !isset($usadas[$c->cuenta]))
                                        <form method="POST" action="{{ route('contabilidad.cuenta.eliminar', $c->id) }}" class="inline" onsubmit="return confirm('¿Eliminar la cuenta {{ $c->cuenta }}?')">
                                            @csrf @method('DELETE')<button class="text-rose-500 text-xs hover:underline ml-2">Eliminar</button></form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">No hay cuentas con ese filtro.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <p class="text-xs text-gray-400 mt-2">Plan Contable General Empresarial (PCGE). Solo las cuentas <strong>imputables</strong> (sin subcuentas) reciben movimientos. Al crear una subcuenta, su cuenta superior pasa a ser de título.</p>
        </div>

        {{-- ============ CONFIGURACIÓN ============ --}}
        <form x-show="tab === 'config'" x-cloak method="POST" action="{{ route('contabilidad.config') }}" class="space-y-4">
            @csrf
            <datalist id="imputables">@foreach ($imputables as $cta => $nom)<option value="{{ $cta }}">{{ $nom }}</option>@endforeach</datalist>
            <div class="grid md:grid-cols-2 xl:grid-cols-4 gap-4">
                @foreach ($camposConfig as $grupo => $campos)
                    <section class="bg-white rounded-2xl shadow-sm p-4 space-y-3">
                        <h3 class="font-bold text-gray-800">{{ $grupo }}</h3>
                        @foreach ($campos as $campo => $nombre)
                            <label class="block text-sm text-gray-600">{{ $nombre }}
                                <input name="{{ $campo }}" list="imputables" value="{{ old($campo, $config->$campo) }}" required class="block w-full mt-1 rounded-lg border-gray-300 font-mono">
                                <span class="text-[11px] text-gray-400">{{ $imputables[old($campo, $config->$campo)] ?? '⚠ no existe o no es imputable' }}</span>
                            </label>
                        @endforeach
                    </section>
                @endforeach
            </div>
            <section class="bg-white rounded-2xl shadow-sm p-4">
                <h3 class="font-bold text-gray-800 mb-1">Medios de pago</h3>
                <p class="text-xs text-gray-400 mb-3">Cuenta donde entra el dinero de cada medio. Vacío = Caja si es efectivo, Banco en los demás.</p>
                <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    @foreach ($medios as $m)
                        <label class="text-sm text-gray-600">{{ $m->nom_med_pag }}
                            <input name="medios[{{ $m->id_med_pag }}]" list="imputables" value="{{ $m->cuenta_contable }}" placeholder="{{ str_contains(mb_strtoupper($m->nom_med_pag), 'EFECTIVO') ? $config->cta_caja : $config->cta_banco }}" class="block w-full mt-1 rounded-lg border-gray-300 font-mono"></label>
                    @endforeach
                </div>
            </section>
            <section class="bg-white rounded-2xl shadow-sm p-4 flex flex-col sm:flex-row gap-4">
                <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="asiento_costo" value="1" @checked($config->asiento_costo) class="mt-1 rounded">
                    <span><strong>Asiento de costo de ventas</strong><span class="block text-xs text-gray-500">69 Costo de ventas → 20 Mercaderías, con el costo de cada producto vendido.</span></span></label>
                <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="asiento_destino" value="1" @checked($config->asiento_destino) class="mt-1 rounded">
                    <span><strong>Asiento de destino de compras</strong><span class="block text-xs text-gray-500">20 Mercaderías → 61 Variación de inventarios, el mismo día de cada compra.</span></span></label>
            </section>
            <button class="px-6 py-3 rounded-xl bg-indigo-600 text-white font-semibold hover:bg-indigo-700">Guardar configuración</button>
        </form>

        {{-- Modal cuenta --}}
        <template x-if="form">
            <div class="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4" @click.self="form = null">
                <form method="POST" action="{{ route('contabilidad.cuenta.guardar') }}" class="bg-white rounded-2xl shadow-xl w-full max-w-md p-5 space-y-3 text-sm">
                    @csrf
                    <h3 class="font-bold text-gray-800" x-text="form.nueva ? 'Nueva subcuenta' : 'Editar cuenta ' + form.cuenta"></h3>
                    <label class="block">Código de cuenta
                        <input name="cuenta" x-model="form.cuenta" :readonly="!form.nueva" required inputmode="numeric" maxlength="12" placeholder="Ej. 104103"
                               class="block w-full mt-1 rounded-lg border-gray-300 font-mono read-only:bg-gray-50"></label>
                    <p x-show="form.nueva" class="text-xs text-gray-400">Escribe el código completo: debe empezar con el de su cuenta superior (ej. 1041 → 104103).</p>
                    <label class="block">Denominación
                        <input name="descripcion" x-model="form.descripcion" required maxlength="200" class="block w-full mt-1 rounded-lg border-gray-300 uppercase"></label>
                    <label x-show="!form.nueva" class="block">Estado
                        <select name="estado" x-model="form.estado" class="block w-full mt-1 rounded-lg border-gray-300"><option>Activo</option><option>Inactivo</option></select></label>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="form = null" class="px-4 py-2 rounded-xl bg-gray-100 font-semibold">Cancelar</button>
                        <button class="px-5 py-2 rounded-xl bg-indigo-600 text-white font-semibold">Guardar</button>
                    </div>
                </form>
            </div>
        </template>
    </div>
@endsection
