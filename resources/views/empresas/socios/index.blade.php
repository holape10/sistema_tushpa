@extends('layouts.app')
@section('title', 'Socios')
@section('content')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    @include('empresas.partials.alert')

    @php
        $in = 'block w-full rounded-lg border-gray-300 text-sm focus:border-emerald-500 focus:ring-emerald-500';
        $btn = 'inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold';
    @endphp

    <div x-data="socios()" x-init="iniciar()" class="space-y-5">
        {{-- Cabecera --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-extrabold text-gray-800"><i class="fas fa-id-card text-emerald-600"></i> Socios</h1>
                <p class="text-sm text-gray-500">Padrón, cuotas, cobranza y carnets ·
                    <span class="text-gray-400">Portal del socio:</span>
                    <a href="{{ route('socio.portal') }}" target="_blank" class="font-semibold text-emerald-700 underline">{{ route('socio.portal') }}</a>
                    <button type="button" onclick="navigator.clipboard.writeText('{{ route('socio.portal') }}'); this.textContent='✔ Copiado'" class="text-xs text-gray-500 underline ml-1">Copiar</button></p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="button" @click="nuevoSocio()" class="{{ $btn }} bg-emerald-600 text-white hover:bg-emerald-700"><i class="fas fa-user-plus"></i> Nuevo socio</button>
                @if ($esAdmin)
                    <button type="button" @click="modal = 'traer'" class="{{ $btn }} bg-white border border-emerald-500 text-emerald-700 hover:bg-emerald-50"><i class="fas fa-users"></i> Traer clientes como socios</button>
                    <button type="button" @click="abrirGenerar()" class="{{ $btn }} bg-indigo-600 text-white hover:bg-indigo-700"><i class="fas fa-calendar-plus"></i> Generar cuotas del mes</button>
                    <button type="button" @click="abrirCargo()" class="{{ $btn }} bg-white border border-gray-300 text-gray-700 hover:bg-gray-50"><i class="fas fa-file-invoice-dollar"></i> Cargo extraordinario</button>
                    <button type="button" @click="modal = 'config'" class="{{ $btn }} bg-white border border-gray-300 text-gray-700 hover:bg-gray-50"><i class="fas fa-gear"></i> Configuración</button>
                @endif
            </div>
        </div>

        @if (!$turno)
            <div class="rounded-xl bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-800">
                <i class="fas fa-triangle-exclamation"></i> No tienes un turno de caja abierto: puedes ver y registrar socios, pero para cobrar
                <a href="{{ route('turnos.index') }}" class="font-bold underline">apertura tu turno</a>.
            </div>
        @endif
        {{-- Primeros pasos: se muestra hasta completar la puesta en marcha --}}
        @php
            $pasos = [
                ['hecho' => $categorias->where('cuota', '>', 0)->isNotEmpty(), 'titulo' => 'Crea las categorías y su cuota',
                 'texto' => 'Ej.: ACTIVO S/ 100, JUVENIL S/ 50.', 'boton' => 'Crear categorías', 'accion' => "modal = 'config'"],
                ['hecho' => (bool) $cfg->IdProducto_ordinaria, 'titulo' => 'Elige el producto de la cuota',
                 'texto' => 'El producto CUOTA ORDINARIA (sirve para todos los meses).', 'boton' => 'Elegir producto', 'accion' => "modal = 'config'"],
                ['hecho' => $socios->isNotEmpty(), 'titulo' => 'Trae a tus socios',
                 'texto' => 'Todos los clientes con DNI entran de una vez; luego quitas a los que no son.', 'boton' => 'Traer clientes', 'accion' => "modal = 'traer'"],
                ['hecho' => (bool) $cfg->ultimo_periodo, 'titulo' => 'Genera las cuotas del mes',
                 'texto' => 'Un clic a fin de mes y cada socio queda con su cuota por pagar.', 'boton' => 'Generar cuotas', 'accion' => 'abrirGenerar()'],
            ];
            $pendientes = collect($pasos)->where('hecho', false)->count();
        @endphp
        @if ($esAdmin && $pendientes)
            <div class="bg-white rounded-2xl shadow-sm p-5 border-2 border-emerald-200">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="font-extrabold text-gray-800"><i class="fas fa-flag-checkered text-emerald-600"></i> Primeros pasos</h2>
                    <span class="text-sm text-gray-500">{{ 4 - $pendientes }} de 4 listos</span>
                </div>
                <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3">
                    @php $siguiente = collect($pasos)->search(fn($p) => !$p['hecho']); @endphp
                    @foreach ($pasos as $i => $p)
                        <div class="rounded-xl p-4 border {{ $p['hecho'] ? 'bg-emerald-50 border-emerald-200' : ($i === $siguiente ? 'border-emerald-500 ring-2 ring-emerald-200' : 'border-gray-200 opacity-70') }}">
                            <div class="flex items-center gap-2">
                                <span class="w-7 h-7 rounded-full flex items-center justify-center text-sm font-bold {{ $p['hecho'] ? 'bg-emerald-600 text-white' : 'bg-gray-200 text-gray-600' }}">{!! $p['hecho'] ? '<i class="fas fa-check"></i>' : $i + 1 !!}</span>
                                <p class="font-bold text-gray-800 text-sm">{{ $p['titulo'] }}</p>
                            </div>
                            <p class="text-xs text-gray-500 mt-2 min-h-[2.5rem]">{{ $p['texto'] }}</p>
                            @unless ($p['hecho'])
                                <button type="button" @click="{{ $p['accion'] }}" class="mt-2 w-full py-2 rounded-lg text-sm font-bold {{ $i === $siguiente ? 'bg-emerald-600 text-white hover:bg-emerald-700' : 'bg-gray-100 text-gray-700' }}">{{ $p['boton'] }}</button>
                            @endunless
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Resumen --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
            <div class="bg-white rounded-2xl shadow-sm p-4"><p class="text-xs text-gray-500">Activos</p><p class="text-2xl font-extrabold text-emerald-600" x-text="cuenta('ACTIVO')"></p></div>
            <div class="bg-white rounded-2xl shadow-sm p-4"><p class="text-xs text-gray-500">Suspendidos</p><p class="text-2xl font-extrabold text-rose-600" x-text="cuenta('SUSPENDIDO')"></p></div>
            <div class="bg-white rounded-2xl shadow-sm p-4"><p class="text-xs text-gray-500">Con deuda</p><p class="text-2xl font-extrabold text-amber-600" x-text="lista.filter(s => s.saldo > 0).length"></p></div>
            <div class="bg-white rounded-2xl shadow-sm p-4"><p class="text-xs text-gray-500">Por cobrar</p><p class="text-2xl font-extrabold text-gray-800" x-text="soles(lista.reduce((a, s) => a + s.saldo, 0))"></p></div>
        </div>

        {{-- Filtros --}}
        <div class="bg-white rounded-2xl shadow-sm p-4 grid sm:grid-cols-4 gap-3">
            <input type="search" x-model="f.texto" placeholder="Buscar por nombre, DNI o N° de socio" class="{{ $in }} sm:col-span-2">
            <select x-model="f.estado" class="{{ $in }}">
                <option value="">Todos los estados</option>
                @foreach (\App\Support\Socios::ESTADOS as $e)<option>{{ $e }}</option>@endforeach
            </select>
            <div class="flex gap-2">
                <select x-model="f.cat" class="{{ $in }}">
                    <option value="">Todas las categorías</option>
                    @foreach ($categorias as $c)<option value="{{ $c->cat_soc_id }}">{{ $c->nombre }}</option>@endforeach
                </select>
                <label class="flex items-center gap-1 text-sm whitespace-nowrap"><input type="checkbox" x-model="f.deuda" class="rounded"> Con deuda</label>
            </div>
        </div>

        {{-- Padrón --}}
        @if ($esAdmin)
            <div x-show="sel.length" x-cloak class="flex flex-wrap items-center gap-3 rounded-xl bg-rose-50 border border-rose-200 px-4 py-2 text-sm">
                <span class="font-semibold text-rose-800"><span x-text="sel.length"></span> seleccionado(s)</span>
                <select x-model="catMasiva" class="rounded-lg border-gray-300 text-sm py-1.5">
                    <option value="">Poner categoría…</option>
                    @foreach ($categorias as $c)<option value="{{ $c->cat_soc_id }}">{{ $c->nombre }} · S/ {{ number_format($c->cuota, 2) }}</option>@endforeach
                </select>
                <button type="button" @click="ponerCategoria({ ids: sel })" :disabled="ocupado || !catMasiva" class="px-3 py-1.5 rounded-lg bg-emerald-600 text-white font-bold disabled:opacity-40">Aplicar categoría</button>
                <button type="button" @click="eliminar(sel)" :disabled="ocupado" class="px-3 py-1.5 rounded-lg bg-rose-600 text-white font-bold disabled:opacity-40"><i class="fas fa-trash"></i> Quitar del padrón</button>
                <button type="button" @click="sel = []" class="text-rose-700 underline">Quitar selección</button>
                <span class="text-xs text-rose-700">Los que tienen cuotas o pagos no se borran: quedan como RETIRADO.</span>
            </div>
        @endif
        <div class="bg-white rounded-2xl shadow-sm overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs uppercase"><tr>
                    @if ($esAdmin)<th class="pl-4 py-2 w-8"><input type="checkbox" class="rounded" :checked="todosMarcados" @change="marcarTodos($event.target.checked)" title="Seleccionar los de la lista"></th>@endif
                    <th class="px-4 py-2 text-left">N°</th><th class="px-4 py-2 text-left">Socio</th><th class="px-4 py-2 text-left">Categoría</th>
                    <th class="px-4 py-2 text-center">Estado</th><th class="px-4 py-2 text-center">Fam.</th><th class="px-4 py-2 text-center">Meses</th>
                    <th class="px-4 py-2 text-right">Debe</th><th class="px-4 py-2"></th></tr></thead>
                <tbody class="divide-y divide-gray-100">
                    <template x-for="s in filtrados.slice(0, mostrar)" :key="s.soc_id">
                        <tr class="hover:bg-gray-50" :class="sel.includes(s.soc_id) ? 'bg-rose-50' : ''">
                            @if ($esAdmin)<td class="pl-4 py-2"><input type="checkbox" class="rounded" :checked="sel.includes(s.soc_id)" @change="sel = $event.target.checked ? [...sel, s.soc_id] : sel.filter(i => i !== s.soc_id)"></td>@endif
                            <td class="px-4 py-2 font-mono font-bold text-gray-600" x-text="s.codigo"></td>
                            <td class="px-4 py-2"><p class="font-semibold text-gray-800" x-text="s.clinom"></p><p class="text-xs text-gray-400" x-text="s.clinum + (s.telefono ? ' · ' + s.telefono : '')"></p></td>
                            <td class="px-4 py-2" x-text="s.categoria || '—'"></td>
                            <td class="px-4 py-2 text-center"><span class="px-2 py-0.5 rounded-full text-xs font-bold" :class="colorEstado(s.estado)" x-text="s.estado"></span></td>
                            <td class="px-4 py-2 text-center" x-text="s.familiares || ''"></td>
                            <td class="px-4 py-2 text-center font-bold" :class="s.meses ? 'text-amber-600' : 'text-gray-300'" x-text="s.meses || '0'"></td>
                            <td class="px-4 py-2 text-right font-bold" :class="s.saldo > 0 ? 'text-rose-600' : 'text-emerald-600'" x-text="soles(s.saldo)"></td>
                            <td class="px-4 py-2 text-right whitespace-nowrap">
                                <button type="button" @click="abrirFicha(s.soc_id, 'cobro')" class="px-3 py-1 rounded-lg bg-emerald-600 text-white text-xs font-bold hover:bg-emerald-700">Cobrar</button>
                                <button type="button" @click="abrirFicha(s.soc_id, 'datos')" class="px-2 py-1 rounded-lg bg-gray-100 text-gray-700 text-xs hover:bg-gray-200" title="Ficha"><i class="fas fa-pen"></i></button>
                                <a :href="'{{ url('socios') }}/' + s.soc_id + '/carnet'" target="_blank" class="px-2 py-1 rounded-lg bg-gray-100 text-gray-700 text-xs hover:bg-gray-200" title="Carnet"><i class="fas fa-id-badge"></i></a>
                                @if ($esAdmin)<button type="button" @click="eliminar([s.soc_id], s.clinom)" class="px-2 py-1 rounded-lg bg-gray-100 text-rose-600 text-xs hover:bg-rose-100" title="Quitar del padrón"><i class="fas fa-trash"></i></button>@endif
                            </td>
                        </tr>
                    </template>
                    <tr x-show="!filtrados.length"><td colspan="9" class="px-4 py-8 text-center text-gray-400">
                        <span x-text="lista.length ? 'No hay socios con ese filtro.' : 'Aún no hay socios.'"></span>
                        @if ($esAdmin)<button type="button" x-show="!lista.length" @click="modal = 'traer'" class="block mx-auto mt-3 px-4 py-2 rounded-xl bg-emerald-600 text-white font-bold"><i class="fas fa-users"></i> Traer todos los clientes como socios</button>@endif
                    </td></tr>
                </tbody>
            </table>
            <div x-show="filtrados.length > mostrar" class="p-3 text-center">
                <button type="button" @click="mostrar += 200" class="text-sm font-semibold text-emerald-700">Mostrar más (<span x-text="filtrados.length - mostrar"></span>)</button>
            </div>
        </div>

        {{-- ================= Ficha del socio ================= --}}
        <div x-show="modal === 'ficha'" x-cloak class="fixed inset-0 z-50 bg-black/40 flex items-start justify-center p-2 sm:p-6 overflow-y-auto" @keydown.escape.window="cerrar()">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-4xl" @click.outside="cerrar()">
                <div class="flex items-center justify-between px-5 py-3 border-b">
                    <div>
                        <h3 class="font-bold text-gray-800" x-text="ficha.socio.soc_id ? 'Socio N° ' + ficha.socio.codigo + ' · ' + ficha.socio.clinom : 'Nuevo socio'"></h3>
                        <span x-show="ficha.socio.soc_id" class="px-2 py-0.5 rounded-full text-xs font-bold" :class="colorEstado(ficha.socio.estado)" x-text="ficha.socio.estado"></span>
                    </div>
                    <button type="button" @click="cerrar()" class="text-gray-400 hover:text-gray-700 text-2xl leading-none">&times;</button>
                </div>
                <div class="flex gap-1 px-5 pt-3 border-b text-sm" x-show="ficha.socio.soc_id">
                    <template x-for="t in [['cobro', 'Estado de cuenta / Cobrar'], ['datos', 'Datos y familiares'], ['pagos', 'Pagos']]">
                        <button type="button" @click="tab = t[0]" class="px-3 py-2 -mb-px border-b-2 font-semibold"
                                :class="tab === t[0] ? 'border-emerald-600 text-emerald-700' : 'border-transparent text-gray-500'" x-text="t[1]"></button>
                    </template>
                </div>

                {{-- Cobro --}}
                <div x-show="tab === 'cobro'" class="p-5 grid lg:grid-cols-5 gap-5">
                    <div class="lg:col-span-3 space-y-3">
                        <div class="flex items-end gap-2">
                            <label class="text-xs font-semibold text-gray-500 flex-1">¿Paga solo una parte? Escribe cuánto trae y pulsa Aplicar
                                <input type="number" step="0.10" min="0" x-model.number="aCuenta" class="{{ $in }} mt-1"></label>
                            <button type="button" @click="repartir()" class="h-9 px-3 rounded-lg bg-gray-800 text-white text-xs font-bold">Aplicar</button>
                            <button type="button" @click="todo()" class="h-9 px-3 rounded-lg bg-gray-100 text-gray-700 text-xs font-bold">Todo</button>
                        </div>
                        <div class="border rounded-xl overflow-hidden">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-50 text-xs text-gray-500"><tr><th class="px-3 py-2 text-left">Concepto</th><th class="px-3 py-2 text-right">Saldo</th><th class="px-3 py-2 text-right w-28">Cobrar ahora</th><th class="w-8"></th></tr></thead>
                                <tbody class="divide-y">
                                    <template x-for="c in ficha.pendientes" :key="c.car_id">
                                        <tr>
                                            <td class="px-3 py-2"><span x-text="c.descripcion"></span>
                                                <span x-show="c.pagado > 0" class="block text-xs text-gray-400" x-text="'A cuenta: ' + soles(c.pagado) + ' de ' + soles(c.monto)"></span></td>
                                            <td class="px-3 py-2 text-right" x-text="soles(c.monto - c.pagado)"></td>
                                            <td class="px-3 py-1 text-right"><input type="number" step="0.10" min="0" :max="(c.monto - c.pagado).toFixed(2)" x-model.number="pagos[c.car_id]"
                                                   class="w-24 rounded-lg border-gray-300 text-sm text-right py-1"></td>
                                            <td class="text-center">@if ($esAdmin)<button type="button" @click="anularCargo(c)" class="text-gray-300 hover:text-rose-600" title="Anular cargo"><i class="fas fa-xmark"></i></button>@endif</td>
                                        </tr>
                                    </template>
                                    <tr x-show="!ficha.pendientes.length"><td colspan="4" class="px-3 py-6 text-center text-emerald-600 font-semibold"><i class="fas fa-circle-check"></i> Está al día</td></tr>
                                </tbody>
                            </table>
                        </div>
                        @if ($esAdmin)
                            <details class="text-sm">
                                <summary class="cursor-pointer font-semibold text-indigo-700">+ Agregar un cargo a este socio (cuota de ingreso, multa…)</summary>
                                <div class="grid sm:grid-cols-4 gap-2 mt-2">
                                    <select x-model="cargoUno.IdProducto" @change="cargoUno.monto = precioDe(cargoUno.IdProducto)" class="{{ $in }} sm:col-span-2">
                                        <option value="">Concepto…</option>
                                        @foreach ($conceptos as $p)<option value="{{ $p->IdProducto }}">{{ $p->pronom }}</option>@endforeach
                                    </select>
                                    <input type="number" step="0.10" min="0" x-model.number="cargoUno.monto" placeholder="Monto" class="{{ $in }}">
                                    <button type="button" @click="cargarUno()" class="rounded-lg bg-indigo-600 text-white text-sm font-bold">Cargar</button>
                                    <input x-model="cargoUno.descripcion" maxlength="150" placeholder="Descripción (opcional)" class="{{ $in }} sm:col-span-4">
                                </div>
                            </details>
                        @endif
                    </div>

                    <div class="lg:col-span-2 bg-gray-50 rounded-xl p-4 space-y-3">
                        <p class="text-xs text-gray-500">Total a cobrar</p>
                        <p class="text-3xl font-extrabold text-gray-800" x-text="soles(totalPago)"></p>
                        <label class="block text-xs font-semibold text-gray-500">Comprobante
                            <select x-model="cobro.tdocod" @change="if (cobro.tdocod === '01') { cobro.clinum = ''; cobro.clinom = ''; cobro.tdicod = '6' } else { datosSocioEnCobro() }" class="{{ $in }} mt-1">
                                <option value="03">Boleta</option><option value="01">Factura</option><option value="13">Nota de venta</option>
                            </select></label>
                        <p x-show="cobro.tdocod === '01'" class="text-xs text-indigo-700">Escribe el RUC de la empresa: el nombre y la dirección salen solos de SUNAT.</p>
                        <div class="grid grid-cols-3 gap-2">
                            <label class="col-span-1 block text-xs font-semibold text-gray-500">DNI / RUC
                                <input x-model="cobro.clinum" @keydown.enter.prevent="buscarDoc('cobro')" @change="buscarDoc('cobro')" maxlength="15" class="{{ $in }} mt-1"></label>
                            <label class="col-span-2 block text-xs font-semibold text-gray-500">A nombre de
                                <input x-model="cobro.clinom" maxlength="150" class="{{ $in }} mt-1"></label>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <label class="block text-xs font-semibold text-gray-500">Medio de pago
                                <select x-model="cobro.medio" class="{{ $in }} mt-1">
                                    @foreach ($mediospagos as $m)<option value="{{ $m->id_med_pag }}">{{ $m->nom_med_pag }}</option>@endforeach
                                </select></label>
                            <label class="block text-xs font-semibold text-gray-500">Paga con
                                <input type="number" step="0.10" min="0" x-model.number="cobro.paga" class="{{ $in }} mt-1"></label>
                        </div>
                        <p class="text-sm" x-show="cobro.paga > totalPago">Vuelto: <strong x-text="soles(cobro.paga - totalPago)"></strong></p>
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" x-model="cobro.imprimir" class="rounded"> Imprimir comprobante</label>
                        <button type="button" @click="cobrar()" :disabled="ocupado || totalPago <= 0 || !TURNO"
                                class="w-full py-3 rounded-xl bg-emerald-600 text-white font-extrabold hover:bg-emerald-700 disabled:opacity-40">
                            <i class="fas fa-cash-register"></i> COBRAR</button>
                        <p x-show="!TURNO" class="text-xs text-amber-700">Apertura tu turno de caja para cobrar.</p>
                    </div>
                </div>

                {{-- Datos y familiares --}}
                <form x-show="tab === 'datos'" @submit.prevent="guardar()" class="p-5 space-y-4">
                    <div class="grid sm:grid-cols-6 gap-3">
                        <label class="block text-xs font-semibold text-gray-500">N° socio
                            <input x-model="form.codigo" maxlength="20" placeholder="Automático" class="{{ $in }} mt-1 font-mono"></label>
                        <label class="block text-xs font-semibold text-gray-500">Documento
                            <select x-model="form.tdicod" class="{{ $in }} mt-1"><option value="1">DNI</option><option value="4">C. extranjería</option><option value="7">Pasaporte</option><option value="6">RUC</option></select></label>
                        <label class="block text-xs font-semibold text-gray-500">Número
                            <input x-model="form.clinum" @change="buscarDoc('form')" required maxlength="15" class="{{ $in }} mt-1"></label>
                        <label class="block text-xs font-semibold text-gray-500 sm:col-span-3">Apellidos y nombres
                            <input x-model="form.clinom" required maxlength="150" class="{{ $in }} mt-1 uppercase"></label>
                        <label class="block text-xs font-semibold text-gray-500 sm:col-span-2">Categoría
                            <select x-model="form.cat_soc_id" class="{{ $in }} mt-1">
                                <option value="">— Sin categoría (no genera cuota) —</option>
                                @foreach ($categorias as $c)<option value="{{ $c->cat_soc_id }}">{{ $c->nombre }} · S/ {{ number_format($c->cuota, 2) }}</option>@endforeach
                            </select></label>
                        <label class="block text-xs font-semibold text-gray-500">Ingreso
                            <input type="date" x-model="form.fecha_ingreso" class="{{ $in }} mt-1"></label>
                        <label class="block text-xs font-semibold text-gray-500">Nacimiento
                            <input type="date" x-model="form.fecha_nac" class="{{ $in }} mt-1"></label>
                        <label class="block text-xs font-semibold text-gray-500">Teléfono
                            <input x-model="form.telefono" maxlength="20" class="{{ $in }} mt-1"></label>
                        <label class="block text-xs font-semibold text-gray-500">Correo
                            <input type="email" x-model="form.clicor" maxlength="100" class="{{ $in }} mt-1"></label>
                        <label class="block text-xs font-semibold text-gray-500 sm:col-span-3">Dirección
                            <input x-model="form.clidir" maxlength="150" class="{{ $in }} mt-1"></label>
                        <label class="block text-xs font-semibold text-gray-500 sm:col-span-3">Observaciones
                            <input x-model="form.obs" maxlength="255" class="{{ $in }} mt-1"></label>
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <h4 class="font-bold text-gray-700">Familiares <span class="text-xs font-normal text-gray-400">({{ str_replace(',', ', ', $cfg->parentescos) }} · hijos hasta {{ $cfg->edad_max_hijos }} años)</span></h4>
                            <button type="button" @click="form.familiares.push({ fam_id: null, nombre: '', dni: '', parentesco: PARENTESCOS[0] || '', fecha_nac: '', activo: true })"
                                    class="text-sm font-semibold text-emerald-700">+ Agregar familiar</button>
                        </div>
                        <template x-for="(fa, i) in form.familiares" :key="i">
                            <div class="grid grid-cols-12 gap-2 mb-2 items-center" :class="fa.activo ? '' : 'opacity-50'">
                                <input x-model="fa.nombre" required placeholder="Apellidos y nombres" class="{{ $in }} col-span-12 sm:col-span-4 uppercase">
                                <input x-model="fa.dni" placeholder="DNI" maxlength="15" class="{{ $in }} col-span-4 sm:col-span-2">
                                <select x-model="fa.parentesco" class="{{ $in }} col-span-4 sm:col-span-2">
                                    <template x-for="p in PARENTESCOS"><option :value="p" x-text="p" :selected="p === fa.parentesco"></option></template>
                                </select>
                                <input type="date" x-model="fa.fecha_nac" class="{{ $in }} col-span-4 sm:col-span-2">
                                <span class="col-span-8 sm:col-span-1 text-xs" :class="edadExcedida(fa) ? 'text-rose-600 font-bold' : 'text-gray-400'" x-text="edad(fa.fecha_nac) !== null ? edad(fa.fecha_nac) + ' años' : ''"></span>
                                <label class="col-span-4 sm:col-span-1 text-xs flex items-center gap-1"><input type="checkbox" x-model="fa.activo" class="rounded"> Activo</label>
                            </div>
                        </template>
                        <p x-show="!form.familiares.length" class="text-sm text-gray-400">Sin familiares registrados.</p>
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-3 pt-2 border-t">
                        @if ($esAdmin)
                            <div x-show="form.soc_id" class="flex items-center gap-2 text-sm">
                                <span class="text-gray-500">Estado:</span>
                                <select x-model="estadoManual" class="rounded-lg border-gray-300 text-sm py-1">
                                    @foreach (\App\Support\Socios::ESTADOS as $e)<option>{{ $e }}</option>@endforeach
                                </select>
                                <button type="button" @click="cambiarEstado()" class="px-3 py-1 rounded-lg bg-gray-800 text-white text-xs font-bold">Cambiar</button>
                            </div>
                        @endif
                        <button type="button" x-show="form.soc_id" @click="restablecerClave()" class="text-xs text-gray-500 underline">Restablecer clave del portal</button>
                        <button :disabled="ocupado" class="ml-auto px-5 py-2 rounded-xl bg-emerald-600 text-white font-bold hover:bg-emerald-700 disabled:opacity-40">Guardar socio</button>
                    </div>
                </form>

                {{-- Pagos --}}
                <div x-show="tab === 'pagos'" class="p-5">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-xs text-gray-500"><tr><th class="px-3 py-2 text-left">Fecha</th><th class="px-3 py-2 text-left">Concepto</th><th class="px-3 py-2 text-left">Comprobante</th><th class="px-3 py-2 text-right">Monto</th></tr></thead>
                        <tbody class="divide-y">
                            <template x-for="p in ficha.pagos">
                                <tr :class="+p.anulado ? 'line-through text-gray-400' : ''">
                                    <td class="px-3 py-2" x-text="fechaHora(p.fecha)"></td>
                                    <td class="px-3 py-2" x-text="p.descripcion"></td>
                                    <td class="px-3 py-2"><a :href="'{{ url('voucher') }}/' + p.IdCpe_cabecera" target="_blank" class="text-indigo-700 underline" x-text="p.comprobante"></a></td>
                                    <td class="px-3 py-2 text-right" x-text="soles(p.monto)"></td>
                                </tr>
                            </template>
                            <tr x-show="!ficha.pagos.length"><td colspan="4" class="px-3 py-6 text-center text-gray-400">Sin pagos registrados.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        @if ($esAdmin)
        {{-- ================= Traer clientes ================= --}}
        <div x-show="modal === 'traer'" x-cloak class="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-5 space-y-4" @click.outside="modal = null">
                <h3 class="font-bold text-gray-800"><i class="fas fa-users text-emerald-600"></i> Traer clientes como socios</h3>
                <p class="text-sm text-gray-600">Todos los clientes con DNI que aún no son socios se agregan como socios ACTIVOS, con número correlativo.
                    Luego quita del padrón a los que no son socios (marca las casillas y pulsa "Quitar del padrón").</p>
                <label class="block text-sm font-semibold text-gray-600">Categoría con la que entran
                    <select x-model="desdeCat" class="{{ $in }} mt-1">
                        <option value="">Sin categoría (no genera cuota hasta que le pongas una)</option>
                        @foreach ($categorias as $c)<option value="{{ $c->cat_soc_id }}">{{ $c->nombre }} · S/ {{ number_format($c->cuota, 2) }}</option>@endforeach
                    </select></label>
                <div class="flex justify-end gap-2">
                    <button type="button" @click="modal = null" class="px-4 py-2 rounded-xl bg-gray-100 text-gray-700 text-sm font-semibold">Cancelar</button>
                    <button type="button" @click="desdeClientes()" :disabled="ocupado" class="px-4 py-2 rounded-xl bg-emerald-600 text-white text-sm font-bold disabled:opacity-40">Traer clientes</button>
                </div>
            </div>
        </div>

        {{-- ================= Generar cuotas ================= --}}
        <div x-show="modal === 'generar'" x-cloak class="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-5 space-y-4" @click.outside="modal = null">
                <h3 class="font-bold text-gray-800"><i class="fas fa-calendar-plus text-indigo-600"></i> Generar cuotas del mes</h3>
                <label class="block text-sm font-semibold text-gray-600">Mes
                    <input type="month" x-model="gen.mes" @change="previa()" class="{{ $in }} mt-1"></label>
                <div class="rounded-xl bg-gray-50 p-3 text-sm space-y-1" x-show="gen.datos">
                    <p>Se cargará la cuota de <strong x-text="gen.datos?.mes"></strong> a <strong x-text="gen.datos?.socios"></strong> socio(s) por <strong x-text="soles(gen.datos?.total)"></strong>.</p>
                    <p x-show="gen.datos?.ya_generados" class="text-gray-500"><span x-text="gen.datos?.ya_generados"></span> ya la tienen (no se repite).</p>
                    <p x-show="gen.datos?.sin_categoria" class="text-amber-700"><span x-text="gen.datos?.sin_categoria"></span> socio(s) no tienen categoría con cuota: no se les carga.</p>
                </div>
                <div x-show="gen.datos?.sin_categoria" class="rounded-xl border border-amber-300 bg-amber-50 p-3 space-y-2">
                    <p class="text-sm font-semibold text-amber-900">Ponles una categoría para que paguen cuota:</p>
                    <div class="flex gap-2">
                        <select x-model="catMasiva" class="{{ $in }}">
                            <option value="">Elige la categoría…</option>
                            @foreach ($categorias->where('cuota', '>', 0) as $c)<option value="{{ $c->cat_soc_id }}">{{ $c->nombre }} · S/ {{ number_format($c->cuota, 2) }}</option>@endforeach
                        </select>
                        <button type="button" @click="ponerCategoria({ sin_categoria: 1 }, true)" :disabled="ocupado || !catMasiva" class="px-3 rounded-lg bg-amber-600 text-white text-sm font-bold whitespace-nowrap disabled:opacity-40">Ponérsela</button>
                    </div>
                </div>
                <p class="text-xs text-gray-500">Activos y suspendidos reciben la cuota de su categoría. Luego, quien deba {{ $cfg->meses_suspension }} cuota(s) o más pasa a SUSPENDIDO.</p>
                <div class="flex justify-end gap-2">
                    <button type="button" @click="cerrar()" class="px-4 py-2 rounded-xl bg-gray-100 text-gray-700 text-sm font-semibold">Cancelar</button>
                    <button type="button" @click="generar()" :disabled="ocupado || !gen.datos?.socios" class="px-4 py-2 rounded-xl bg-indigo-600 text-white text-sm font-bold disabled:opacity-40">Generar</button>
                </div>
            </div>
        </div>

        {{-- ================= Cargo extraordinario ================= --}}
        <div x-show="modal === 'cargo'" x-cloak class="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-5 space-y-3" @click.outside="modal = null">
                <h3 class="font-bold text-gray-800"><i class="fas fa-file-invoice-dollar text-indigo-600"></i> Cargo a varios socios</h3>
                <p class="text-xs text-gray-500">Cuota extraordinaria, multa de asamblea, aporte… Queda como deuda de cada socio.</p>
                <label class="block text-sm font-semibold text-gray-600">Concepto
                    <select x-model="cargo.IdProducto" @change="cargo.monto = precioDe(cargo.IdProducto)" class="{{ $in }} mt-1">
                        <option value="">Elige…</option>
                        @foreach ($conceptos as $p)<option value="{{ $p->IdProducto }}">{{ $p->pronom }}</option>@endforeach
                    </select></label>
                <label class="block text-sm font-semibold text-gray-600">Descripción en el comprobante
                    <input x-model="cargo.descripcion" maxlength="150" placeholder="CUOTA EXTRAORDINARIA - PISCINA 2026" class="{{ $in }} mt-1 uppercase"></label>
                <div class="grid grid-cols-2 gap-2">
                    <label class="block text-sm font-semibold text-gray-600">Monto S/
                        <input type="number" step="0.10" min="0" x-model.number="cargo.monto" class="{{ $in }} mt-1"></label>
                    <label class="block text-sm font-semibold text-gray-600">A quiénes
                        <select x-model="cargo.cat_soc_id" class="{{ $in }} mt-1">
                            <option value="">Todos los activos</option>
                            @foreach ($categorias as $c)<option value="{{ $c->cat_soc_id }}">Categoría {{ $c->nombre }}</option>@endforeach
                        </select></label>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="modal = null" class="px-4 py-2 rounded-xl bg-gray-100 text-gray-700 text-sm font-semibold">Cancelar</button>
                    <button type="button" @click="cargarVarios()" :disabled="ocupado" class="px-4 py-2 rounded-xl bg-indigo-600 text-white text-sm font-bold disabled:opacity-40">Aplicar cargo</button>
                </div>
            </div>
        </div>

        {{-- ================= Configuración ================= --}}
        <div x-show="modal === 'config'" x-cloak class="fixed inset-0 z-50 bg-black/40 flex items-start justify-center p-4 overflow-y-auto">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-2xl p-5 space-y-5" @click.outside="modal = null">
                <div class="flex justify-between items-center">
                    <h3 class="font-bold text-gray-800"><i class="fas fa-gear"></i> Configuración de socios</h3>
                    <button type="button" @click="modal = null" class="text-gray-400 hover:text-gray-700 text-2xl leading-none">&times;</button>
                </div>

                <section>
                    <h4 class="font-semibold text-gray-700 mb-2">Categorías y cuota mensual</h4>
                    <table class="w-full text-sm mb-2">
                        <tbody class="divide-y">
                            @foreach ($categorias as $c)
                                <tr x-data="{ n: @js($c->nombre), q: {{ (float) $c->cuota }}, a: {{ (int) $c->activo }} }">
                                    <td class="py-1 pr-2"><input x-model="n" class="{{ $in }}"></td>
                                    <td class="py-1 pr-2 w-32"><input type="number" step="0.10" min="0" x-model.number="q" class="{{ $in }} text-right"></td>
                                    <td class="py-1 pr-2 w-24 text-xs"><label class="flex items-center gap-1"><input type="checkbox" x-model="a" class="rounded"> Activa</label></td>
                                    <td class="py-1 w-20"><button type="button" @click="guardarCategoria({ cat_soc_id: {{ $c->cat_soc_id }}, nombre: n, cuota: q, activo: a ? 1 : 0 })" class="px-3 py-1 rounded-lg bg-gray-800 text-white text-xs font-bold">Guardar</button></td>
                                </tr>
                            @endforeach
                            <tr>
                                <td class="py-1 pr-2"><input x-model="nuevaCat.nombre" placeholder="Nueva: ACTIVO, VITALICIO, JUVENIL…" class="{{ $in }} uppercase"></td>
                                <td class="py-1 pr-2"><input type="number" step="0.10" min="0" x-model.number="nuevaCat.cuota" placeholder="Cuota" class="{{ $in }} text-right"></td>
                                <td></td>
                                <td class="py-1"><button type="button" @click="guardarCategoria(nuevaCat)" class="px-3 py-1 rounded-lg bg-emerald-600 text-white text-xs font-bold">Agregar</button></td>
                            </tr>
                        </tbody>
                    </table>
                    <p class="text-xs text-gray-400">Una categoría con cuota 0 (honorario, vitalicio exonerado…) no genera cuota mensual.</p>
                </section>

                <section class="grid sm:grid-cols-2 gap-3">
                    <label class="block text-sm font-semibold text-gray-600 sm:col-span-2">Concepto de la cuota ordinaria
                        <select x-model="cfg.IdProducto_ordinaria" class="{{ $in }} mt-1">
                            <option value="">Elige el producto…</option>
                            @foreach ($conceptos as $p)<option value="{{ $p->IdProducto }}">{{ $p->pronom }}{{ $p->debe ? ' · ' . $p->debe . ' / ' . $p->haber : '' }}</option>@endforeach
                        </select>
                        <span class="text-xs font-normal text-gray-400">Elige <b>CUOTA ORDINARIA</b> (sin mes): sirve para todos los meses. Sus cuentas contables van al comprobante.</span>
                        <span x-show="cfg.IdProducto_ordinaria && !/^CUOTA ORDINARIA$/i.test(nombreConcepto(cfg.IdProducto_ordinaria))" class="block mt-1 text-xs font-semibold text-rose-600">
                            Ojo: elegiste «<span x-text="nombreConcepto(cfg.IdProducto_ordinaria)"></span>». Lo normal es «CUOTA ORDINARIA».</span></label>
                    <label class="block text-sm font-semibold text-gray-600">Suspender al deber (cuotas)
                        <input type="number" min="0" max="36" x-model.number="cfg.meses_suspension" class="{{ $in }} mt-1">
                        <span class="text-xs font-normal text-gray-400">0 = nunca suspender automáticamente</span></label>
                    <label class="block text-sm font-semibold text-gray-600">Edad máxima de hijos
                        <input type="number" min="0" max="99" x-model.number="cfg.edad_max_hijos" class="{{ $in }} mt-1"></label>
                    <label class="block text-sm font-semibold text-gray-600 sm:col-span-2">Parentescos permitidos (separados por coma)
                        <input x-model="cfg.parentescos" class="{{ $in }} mt-1 uppercase"></label>
                </section>
                <div class="flex justify-end">
                    <button type="button" @click="guardarConfig()" class="px-5 py-2 rounded-xl bg-emerald-600 text-white font-bold">Guardar configuración</button>
                </div>

                <section class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 space-y-2">
                    <h4 class="font-semibold text-emerald-800"><i class="fas fa-users"></i> Agregar clientes como socios</h4>
                    <p class="text-xs text-emerald-800">Todos los clientes con DNI que aún no son socios se agregan como ACTIVOS. Luego retira (estado RETIRADO) a los que no sean socios.</p>
                    <div class="flex gap-2">
                        <select x-model="desdeCat" class="{{ $in }}">
                            <option value="">Sin categoría (no genera cuota)</option>
                            @foreach ($categorias as $c)<option value="{{ $c->cat_soc_id }}">Categoría {{ $c->nombre }} · S/ {{ number_format($c->cuota, 2) }}</option>@endforeach
                        </select>
                        <button type="button" @click="desdeClientes()" :disabled="ocupado" class="px-4 rounded-lg bg-emerald-600 text-white text-sm font-bold whitespace-nowrap disabled:opacity-40">Agregar clientes con DNI</button>
                    </div>
                </section>
            </div>
        </div>
        @endif

        <div x-show="aviso" x-transition x-cloak class="fixed bottom-6 left-1/2 -translate-x-1/2 z-[60] px-5 py-3 rounded-xl shadow-lg text-white font-semibold"
             :class="avisoOk ? 'bg-emerald-600' : 'bg-rose-600'" x-text="aviso"></div>
    </div>

    <script>
        function socios() {
            const CSRF = '{{ csrf_token() }}';
            const URL_SOCIOS = '{{ url('socios') }}';
            return {
                TURNO: {{ $turno ? 'true' : 'false' }},
                PARENTESCOS: @js(\App\Support\Socios::parentescos($cfg)),
                EDAD_MAX: {{ (int) $cfg->edad_max_hijos }},
                lista: @js($socios),
                conceptos: @js($conceptos),
                cfg: @js(['IdProducto_ordinaria' => (string) $cfg->IdProducto_ordinaria, 'meses_suspension' => (int) $cfg->meses_suspension, 'edad_max_hijos' => (int) $cfg->edad_max_hijos, 'parentescos' => $cfg->parentescos]),
                f: { texto: '', estado: '', cat: '', deuda: false },
                mostrar: 200, modal: null, sel: [], tab: 'cobro', ocupado: false, cambios: false,
                ficha: { socio: {}, familiares: [], pendientes: [], pagos: [] },
                form: {}, estadoManual: 'ACTIVO',
                pagos: {}, aCuenta: null,
                cobro: { tdocod: '03', tdicod: '1', clinum: '', clinom: '', clidir: '', medio: '{{ $mediospagos->first()->id_med_pag ?? '' }}', paga: null, imprimir: true },
                cargoUno: { IdProducto: '', monto: null, descripcion: '' },
                cargo: { IdProducto: '', monto: null, descripcion: '', cat_soc_id: '' },
                gen: { mes: '{{ substr($periodo, 0, 4) }}-{{ substr($periodo, 4, 2) }}', datos: null },
                nuevaCat: { nombre: '', cuota: null }, desdeCat: '{{ $categorias->where('cuota', '>', 0)->first()->cat_soc_id ?? '' }}', catMasiva: '{{ $categorias->where('cuota', '>', 0)->first()->cat_soc_id ?? '' }}',
                aviso: '', avisoOk: true,

                iniciar() {},
                soles(n) { return 'S/ ' + Number(n || 0).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
                fechaHora(s) { return s ? new Date(s.replace(' ', 'T')).toLocaleString('es-PE', { dateStyle: 'short', timeStyle: 'short' }) : ''; },
                cuenta(e) { return this.lista.filter(s => s.estado === e).length; },
                colorEstado(e) { return { ACTIVO: 'bg-emerald-100 text-emerald-700', SUSPENDIDO: 'bg-rose-100 text-rose-700', RETIRADO: 'bg-gray-200 text-gray-600', FALLECIDO: 'bg-gray-800 text-white' }[e] || 'bg-gray-100'; },
                precioDe(id) { const p = this.conceptos.find(x => String(x.IdProducto) === String(id)); return p ? Number(p.propun) || null : null; },
                edad(f) { if (!f) return null; const d = new Date(f + 'T00:00'), h = new Date(); let e = h.getFullYear() - d.getFullYear(); if (h < new Date(h.getFullYear(), d.getMonth(), d.getDate())) e--; return e; },
                edadExcedida(fa) { const e = this.edad(fa.fecha_nac); return e !== null && this.EDAD_MAX > 0 && /^HIJO/.test(fa.parentesco || '') && e > this.EDAD_MAX; },
                get filtrados() {
                    const t = this.f.texto.trim().toUpperCase();
                    return this.lista.filter(s => (!t || (s.clinom || '').toUpperCase().includes(t) || (s.clinum || '').includes(t) || (s.codigo || '').toUpperCase() === t)
                        && (!this.f.estado || s.estado === this.f.estado) && (!this.f.cat || String(s.cat_soc_id) === this.f.cat) && (!this.f.deuda || s.saldo > 0));
                },
                get todosMarcados() { const v = this.filtrados.slice(0, this.mostrar); return v.length > 0 && v.every(s => this.sel.includes(s.soc_id)); },
                marcarTodos(si) {
                    const ids = this.filtrados.slice(0, this.mostrar).map(s => s.soc_id);
                    this.sel = si ? [...new Set([...this.sel, ...ids])] : this.sel.filter(i => !ids.includes(i));
                },
                async eliminar(ids, nombre) {
                    if (!confirm(nombre ? '¿Quitar a ' + nombre + ' del padrón de socios?' : '¿Quitar ' + ids.length + ' socio(s) del padrón?')) return;
                    this.ocupado = true;
                    const r = await this.post(URL_SOCIOS + '/eliminar', { ids });
                    this.ocupado = false;
                    this.avisar(r.mensaje, r.ok);
                    if (r.ok) setTimeout(() => location.reload(), 1200);
                },
                nombreConcepto(id) { const p = this.conceptos.find(x => String(x.IdProducto) === String(id)); return p ? p.pronom : ''; },
                async ponerCategoria(donde, enGenerar = false) {
                    this.ocupado = true;
                    const r = await this.post(URL_SOCIOS + '/poner-categoria', Object.assign({ cat_soc_id: this.catMasiva }, donde));
                    this.ocupado = false;
                    this.avisar(r.mensaje, r.ok);
                    if (!r.ok) return;
                    this.cambios = true;
                    if (enGenerar) { await this.previa(); } else { setTimeout(() => location.reload(), 1200); }
                },
                get totalPago() { return Math.round(Object.values(this.pagos).reduce((a, v) => a + (Number(v) || 0), 0) * 100) / 100; },
                avisar(t, ok = true) { this.aviso = t; this.avisoOk = ok; clearTimeout(this._t); this._t = setTimeout(() => this.aviso = '', 4500); },
                async post(url, data) {
                    const r = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF }, body: JSON.stringify(data || {}) });
                    const j = await r.json().catch(() => ({}));
                    if (r.status === 422) return { ok: false, mensaje: Object.values(j.errors || {}).flat()[0] || 'Revisa los datos.' };
                    if (!r.ok) return { ok: false, mensaje: j.message || 'Error del servidor.' };
                    return j;
                },
                cerrar() { this.modal = null; if (this.cambios) location.reload(); },

                // ---- Ficha
                nuevoSocio() {
                    this.ficha = { socio: {}, familiares: [], pendientes: [], pagos: [] };
                    this.form = { soc_id: null, codigo: '', tdicod: '1', clinum: '', clinom: '', clidir: '', telefono: '', clicor: '', cat_soc_id: '', fecha_ingreso: new Date().toISOString().slice(0, 10), fecha_nac: '', obs: '', familiares: [] };
                    this.tab = 'datos'; this.modal = 'ficha';
                },
                async abrirFicha(id, tab) {
                    const d = await fetch(URL_SOCIOS + '/' + id, { headers: { 'Accept': 'application/json' } }).then(r => r.json());
                    this.ficha = d;
                    const s = d.socio;
                    this.form = { soc_id: s.soc_id, codigo: s.codigo, tdicod: s.tdicod || '1', clinum: s.clinum, clinom: s.clinom, clidir: s.clidir === '--' ? '' : s.clidir,
                        telefono: s.telefono || '', clicor: s.clicor || '', cat_soc_id: s.cat_soc_id || '', fecha_ingreso: s.fecha_ingreso || '', fecha_nac: s.fecha_nac || '', obs: s.obs || '',
                        familiares: d.familiares.map(f => ({ fam_id: f.fam_id, nombre: f.nombre, dni: f.dni || '', parentesco: f.parentesco, fecha_nac: f.fecha_nac || '', activo: !!+f.activo })) };
                    this.estadoManual = s.estado;
                    this.pagos = {}; this.aCuenta = null;
                    d.pendientes.forEach(c => this.pagos[c.car_id] = Math.round((c.monto - c.pagado) * 100) / 100);
                    this.datosSocioEnCobro();
                    this.cobro.tdocod = '03'; this.cobro.paga = null;
                    this.tab = tab; this.modal = 'ficha';
                },
                datosSocioEnCobro() { const s = this.ficha.socio; this.cobro.clinum = s.clinum || ''; this.cobro.clinom = s.clinom || ''; this.cobro.tdicod = s.tdicod || '1'; this.cobro.clidir = s.clidir && s.clidir !== '--' ? s.clidir : ''; },
                async buscarDoc(donde) {
                    const obj = donde === 'cobro' ? this.cobro : this.form;
                    const doc = (obj.clinum || '').trim();
                    if (!/^\d{8}$|^\d{11}$/.test(doc)) return;
                    const d = await fetch('{{ url('cobros/cliente') }}/' + doc, { headers: { 'Accept': 'application/json' } }).then(r => r.json()).catch(() => ({}));
                    if (d && d.nom) {
                        obj.clinom = d.nom; obj.tdicod = d.tdicod || (doc.length === 11 ? '6' : '1');
                        if (donde === 'form') { this.form.clidir = d.dir && d.dir !== '--' ? d.dir : this.form.clidir; this.form.telefono = d.tel || this.form.telefono; this.form.clicor = d.cor || this.form.clicor; }
                        if (donde === 'cobro') obj.clidir = d.dir && d.dir !== '--' ? d.dir : '';
                        if (donde === 'cobro' && doc.length === 11) obj.tdocod = '01';
                    }
                },
                async guardar() {
                    this.ocupado = true;
                    const r = await this.post(URL_SOCIOS, this.form);
                    this.ocupado = false;
                    this.avisar(r.mensaje, r.ok);
                    if (r.ok) { this.cambios = true; await this.abrirFicha(r.soc_id, 'datos'); }
                },
                async restablecerClave() {
                    if (!confirm('¿Restablecer la contraseña del portal? Volverá a ser su DNI/RUC.')) return;
                    const r = await this.post(URL_SOCIOS + '/' + this.form.soc_id + '/clave');
                    this.avisar(r.mensaje, r.ok);
                },
                async cambiarEstado() {
                    const r = await this.post(URL_SOCIOS + '/' + this.form.soc_id + '/estado', { estado: this.estadoManual });
                    this.avisar(r.mensaje, r.ok); if (r.ok) { this.cambios = true; this.ficha.socio.estado = this.estadoManual; }
                },

                // ---- Cobro: amortiza desde la cuota más antigua
                repartir() {
                    let resta = Number(this.aCuenta) || 0;
                    this.ficha.pendientes.forEach(c => {
                        const saldo = Math.round((c.monto - c.pagado) * 100) / 100;
                        const toma = Math.min(saldo, resta);
                        this.pagos[c.car_id] = toma > 0 ? Math.round(toma * 100) / 100 : null;
                        resta = Math.round((resta - toma) * 100) / 100;
                    });
                    if (resta > 0) this.avisar('Sobran ' + this.soles(resta) + ': es más que la deuda.', false);
                },
                todo() { this.ficha.pendientes.forEach(c => this.pagos[c.car_id] = Math.round((c.monto - c.pagado) * 100) / 100); },
                async cobrar() {
                    if (this.cobro.tdocod === '01' && !/^\d{11}$/.test(this.cobro.clinum)) return this.avisar('Para factura escribe el RUC.', false);
                    this.ocupado = true;
                    const r = await this.post(URL_SOCIOS + '/' + this.ficha.socio.soc_id + '/cobrar', {
                        montos: this.pagos, tdocod: this.cobro.tdocod, clinum: this.cobro.clinum, clinom: this.cobro.clinom, tdicod: this.cobro.tdicod, clidir: this.cobro.clidir,
                        id_med_pag: [this.cobro.medio], mon_med_pag: [this.totalPago], paga: this.cobro.paga || this.totalPago, imprimir: this.cobro.imprimir ? 1 : 0,
                    });
                    this.ocupado = false;
                    if (!r.ok && r.estado !== 'success') return this.avisar(r.mensaje, false);
                    this.cambios = true;
                    this.avisar('✔ ' + r.numero + ' por ' + this.soles(r.total) + (r.vuelto > 0 ? ' · vuelto ' + this.soles(r.vuelto) : '') + (r.impreso ? ' · enviado a la impresora' : ''));
                    if (this.cobro.imprimir && !r.impreso) window.open('{{ url('voucher') }}/' + r.id + '?imprimir=1', '_blank');
                    await this.abrirFicha(this.ficha.socio.soc_id, 'cobro');
                },
                async anularCargo(c) {
                    if (!confirm('¿Anular el cargo "' + c.descripcion + '"?')) return;
                    const r = await this.post(URL_SOCIOS + '/cargos/' + c.car_id + '/anular');
                    this.avisar(r.mensaje, r.ok); if (r.ok) { this.cambios = true; this.abrirFicha(this.ficha.socio.soc_id, 'cobro'); }
                },
                async cargarUno() {
                    const r = await this.post(URL_SOCIOS + '/cargos', Object.assign({ soc_ids: [this.ficha.socio.soc_id] }, this.cargoUno));
                    this.avisar(r.mensaje, r.ok);
                    if (r.ok) { this.cambios = true; this.cargoUno = { IdProducto: '', monto: null, descripcion: '' }; this.abrirFicha(this.ficha.socio.soc_id, 'cobro'); }
                },

                // ---- Masivos y configuración
                abrirGenerar() { this.modal = 'generar'; this.previa(); },
                async previa() {
                    this.gen.datos = await fetch(URL_SOCIOS + '/generar/previa?periodo=' + this.gen.mes.replace('-', ''), { headers: { 'Accept': 'application/json' } }).then(r => r.json());
                },
                async generar() {
                    if (!confirm('¿Generar las cuotas de ' + this.gen.datos.mes + '?')) return;
                    this.ocupado = true;
                    const r = await this.post(URL_SOCIOS + '/generar', { periodo: this.gen.mes.replace('-', '') });
                    this.ocupado = false;
                    this.avisar(r.mensaje, r.ok);
                    if (r.ok) setTimeout(() => location.reload(), 1500);
                },
                abrirCargo() { this.cargo = { IdProducto: '', monto: null, descripcion: '', cat_soc_id: '' }; this.modal = 'cargo'; },
                async cargarVarios() {
                    const a = this.cargo.cat_soc_id ? 'los socios de esa categoría' : 'todos los socios activos';
                    if (!confirm('¿Aplicar ' + this.soles(this.cargo.monto) + ' a ' + a + '?')) return;
                    this.ocupado = true;
                    const r = await this.post(URL_SOCIOS + '/cargos', this.cargo);
                    this.ocupado = false;
                    this.avisar(r.mensaje, r.ok);
                    if (r.ok) setTimeout(() => location.reload(), 1500);
                },
                async guardarCategoria(c) {
                    const r = await this.post(URL_SOCIOS + '/categorias', c);
                    this.avisar(r.mensaje, r.ok); if (r.ok) setTimeout(() => location.reload(), 800);
                },
                async desdeClientes() {
                    if (!confirm('¿Agregar como socios a todos los clientes con DNI que aún no lo son?')) return;
                    this.ocupado = true;
                    const r = await this.post(URL_SOCIOS + '/desde-clientes', { cat_soc_id: this.desdeCat || null });
                    this.ocupado = false;
                    this.avisar(r.mensaje, r.ok); if (r.ok) setTimeout(() => location.reload(), 1200);
                },
                async guardarConfig() {
                    const r = await this.post(URL_SOCIOS + '/config', this.cfg);
                    this.avisar(r.mensaje, r.ok); if (r.ok) setTimeout(() => location.reload(), 800);
                },
            };
        }
    </script>
@endsection
