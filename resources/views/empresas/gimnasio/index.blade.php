@extends('layouts.app')
@section('title', 'Gimnasio')
@section('content')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    @include('empresas.partials.alert')

    @php
        $in = 'block w-full rounded-lg border-gray-300 text-sm focus:border-orange-500 focus:ring-orange-500';
        $btn = 'inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold';
    @endphp

    <div x-data="gimnasio()" class="space-y-5">
        {{-- Cabecera --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-extrabold text-gray-800"><i class="fas fa-dumbbell text-orange-500"></i> Gimnasio</h1>
                <p class="text-sm text-gray-500">Clientes, planes, congelamientos e ingreso ·
                    <span class="text-gray-400">Portal del cliente:</span>
                    <a href="{{ route('socio.portal') }}" target="_blank" class="font-semibold text-orange-600 underline">{{ route('socio.portal') }}</a>
                    <button type="button" onclick="navigator.clipboard.writeText('{{ route('socio.portal') }}'); this.textContent='✔ Copiado'" class="text-xs text-gray-500 underline ml-1">Copiar</button></p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="button" @click="nuevo()" class="{{ $btn }} bg-orange-500 text-white hover:bg-orange-600"><i class="fas fa-user-plus"></i> Nuevo cliente</button>
                <a href="{{ route('gimnasio.acceso') }}" target="_blank" class="{{ $btn }} bg-gray-900 text-white hover:bg-black"><i class="fas fa-door-open"></i> Control de ingreso</a>
                @if ($esAdmin)
                    <button type="button" @click="modal = 'planes'" class="{{ $btn }} bg-white border border-gray-300 text-gray-700 hover:bg-gray-50"><i class="fas fa-tags"></i> Planes</button>
                @endif
            </div>
        </div>

        @if (!$turno)
            <div class="rounded-xl bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-800">
                <i class="fas fa-triangle-exclamation"></i> No tienes un turno de caja abierto: puedes registrar clientes, pero para vender planes
                <a href="{{ route('turnos.index') }}" class="font-bold underline">apertura tu turno</a>.
            </div>
        @endif
        @if ($planes->where('activo', 1)->isEmpty())
            <div class="rounded-2xl border-2 border-dashed border-orange-300 bg-orange-50 p-5 text-sm text-orange-900">
                <p class="font-bold text-base"><i class="fas fa-flag-checkered"></i> Primer paso: crea tus planes</p>
                <p class="mt-1">Por ejemplo: <b>MENSUAL</b> 30 días S/ 80 (puede congelar 15 días), <b>TRIMESTRAL</b> 90 días, <b>RUTINA DEL DÍA</b> 1 día S/ 10.</p>
                @if ($esAdmin)<button type="button" @click="modal = 'planes'" class="mt-3 {{ $btn }} bg-orange-500 text-white">Crear planes</button>@endif
            </div>
        @endif

        {{-- Indicadores (también filtran la lista) --}}
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
            <template x-for="k in tarjetas" :key="k.estado">
                <button type="button" @click="filtro = filtro === k.estado ? '' : k.estado"
                        class="text-left bg-white rounded-2xl shadow-sm p-4 border-2 transition" :class="filtro === k.estado ? k.borde : 'border-transparent hover:border-gray-200'">
                    <p class="text-xs font-semibold text-gray-500" x-text="k.nombre"></p>
                    <p class="text-3xl font-black" :class="k.texto" x-text="cuenta(k.estado)"></p>
                </button>
            </template>
            <div class="bg-gray-900 text-white rounded-2xl shadow-sm p-4">
                <p class="text-xs font-semibold text-gray-300">Vinieron hoy</p>
                <p class="text-3xl font-black">{{ $hoy }}</p>
            </div>
        </div>

        {{-- Lista --}}
        <div class="bg-white rounded-2xl shadow-sm">
            <div class="p-3 border-b flex flex-wrap gap-2 items-center">
                <div class="relative flex-1 min-w-[220px]">
                    <i class="fas fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                    <input x-model="q" placeholder="Buscar por nombre, DNI, celular o código" class="{{ $in }} pl-9">
                </div>
                <span class="text-xs text-gray-500" x-text="visibles.length + ' cliente(s)'"></span>
            </div>
            <div class="divide-y">
                <template x-for="c in visibles.slice(0, mostrar)" :key="c.soc_id">
                    <div class="flex items-center gap-3 px-4 py-3 hover:bg-orange-50/40">
                        <button type="button" @click="abrirFicha(c.soc_id)" class="shrink-0">
                            <template x-if="c.foto"><img :src="c.foto" class="w-12 h-12 rounded-xl object-cover ring-2" :class="anillo(c.color)" alt=""></template>
                            <template x-if="!c.foto"><span class="w-12 h-12 rounded-xl flex items-center justify-center font-black text-white text-lg" :class="fondo(c.color)" x-text="c.nombre.charAt(0)"></span></template>
                        </button>
                        <button type="button" @click="abrirFicha(c.soc_id)" class="flex-1 min-w-0 text-left">
                            <p class="font-bold text-gray-800 truncate"><span x-text="c.nombre"></span>
                                <span x-show="c.cumple" title="¡Hoy es su cumpleaños!">🎂</span>
                                <i x-show="c.huella" class="fas fa-fingerprint text-gray-400 text-xs" title="Tiene huella registrada"></i></p>
                            <p class="text-xs text-gray-500 truncate">
                                <span x-text="'N° ' + c.codigo + ' · ' + c.doc"></span><span x-show="c.telefono" x-text="' · ' + c.telefono"></span>
                                <span x-show="c.entrenador" class="text-indigo-600" x-text="' · Entrenador: ' + c.entrenador"></span></p>
                        </button>
                        <div class="hidden md:block text-sm text-right w-48">
                            <p class="font-semibold text-gray-700 truncate" x-text="c.plan || '—'"></p>
                            <p class="text-xs text-gray-500" x-show="c.vence" x-text="(c.estado === 'CONGELADO' ? 'Congelado hasta ' + fecha(c.congelado_hasta) + ' · ' : '') + 'vence ' + fecha(c.vence)"></p>
                        </div>
                        <span class="px-2.5 py-1 rounded-full text-[11px] font-black whitespace-nowrap" :class="chip(c.color)"
                              x-text="c.estado === 'VIGENTE' || c.estado === 'POR_VENCER' ? c.texto + ' · ' + c.dias + 'd' : c.texto"></span>
                        <button type="button" @click="abrirFicha(c.soc_id, 'vender')" class="hidden sm:inline-flex px-3 py-1.5 rounded-lg bg-orange-500 text-white text-xs font-bold hover:bg-orange-600">Vender plan</button>
                    </div>
                </template>
                <p x-show="!visibles.length" class="p-10 text-center text-gray-400">No hay clientes con ese filtro.</p>
                <button type="button" x-show="visibles.length > mostrar" @click="mostrar += 100" class="w-full py-3 text-sm font-semibold text-orange-600 hover:bg-orange-50">Ver más</button>
            </div>
        </div>

        {{-- ============================================================ Modal: cliente (nuevo / editar) --}}
        <div x-show="modal === 'cliente'" x-cloak class="fixed inset-0 z-50 bg-black/50 flex items-start sm:items-center justify-center p-3 overflow-y-auto" @keydown.escape.window="cerrarCamara(); modal = ficha ? 'ficha' : null">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl my-4">
                <div class="px-5 py-3 border-b flex justify-between items-center">
                    <h3 class="font-bold text-gray-800" x-text="form.soc_id ? 'Editar cliente' : 'Nuevo cliente'"></h3>
                    <button @click="cerrarCamara(); modal = form.soc_id && ficha ? 'ficha' : null" class="text-gray-400 text-2xl leading-none">&times;</button>
                </div>
                <div class="p-5 grid sm:grid-cols-[180px_1fr] gap-5">
                    {{-- Foto: archivo o cámara --}}
                    <div class="text-center">
                        <div class="relative w-40 h-40 mx-auto rounded-2xl overflow-hidden bg-orange-50 ring-2 ring-orange-100 flex items-center justify-center">
                            <video x-ref="video" x-show="camara" autoplay playsinline class="w-full h-full object-cover"></video>
                            <img x-show="!camara && form.vista" :src="form.vista" class="w-full h-full object-cover" alt="">
                            <i x-show="!camara && !form.vista" class="fas fa-user text-6xl text-orange-200"></i>
                        </div>
                        <div class="mt-2 flex flex-wrap justify-center gap-1.5">
                            <button type="button" x-show="!camara" @click="abrirCamara()" class="px-2.5 py-1.5 rounded-lg bg-gray-900 text-white text-xs font-semibold"><i class="fas fa-camera"></i> Cámara</button>
                            <button type="button" x-show="camara" @click="tomarFoto()" class="px-2.5 py-1.5 rounded-lg bg-orange-500 text-white text-xs font-semibold"><i class="fas fa-circle-dot"></i> Tomar foto</button>
                            <label x-show="!camara" class="cursor-pointer px-2.5 py-1.5 rounded-lg bg-gray-100 text-gray-700 text-xs font-semibold"><i class="fas fa-image"></i> Archivo
                                <input type="file" accept="image/*" class="hidden" @change="elegirArchivo($event)"></label>
                        </div>
                        <label x-show="form.vista && form.soc_id" class="mt-1 inline-flex items-center gap-1 text-xs text-rose-600"><input type="checkbox" x-model="form.quitar_foto" class="rounded"> Quitar foto</label>
                        <canvas x-ref="lienzo" class="hidden"></canvas>
                    </div>
                    <div class="grid sm:grid-cols-2 gap-3 text-sm">
                        <label>DNI *
                            <input x-model="form.clinum" @change="buscarDoc()" @keydown.enter.prevent="buscarDoc()" maxlength="15" inputmode="numeric" class="{{ $in }} mt-1"></label>
                        <label>Fecha de nacimiento
                            <input type="date" x-model="form.fecha_nac" max="{{ now()->subDay()->toDateString() }}" class="{{ $in }} mt-1"></label>
                        <label class="sm:col-span-2">Nombre completo *
                            <input x-model="form.clinom" maxlength="150" class="{{ $in }} mt-1 uppercase"></label>
                        <label>Celular
                            <input x-model="form.telefono" maxlength="20" inputmode="tel" class="{{ $in }} mt-1"></label>
                        <label>Correo
                            <input type="email" x-model="form.clicor" maxlength="100" class="{{ $in }} mt-1"></label>
                        <label>Entrenador
                            <select x-model="form.entrenador_id" class="{{ $in }} mt-1">
                                <option value="">— Sin entrenador —</option>
                                @foreach ($entrenadores as $e)<option value="{{ $e->IdUsuario }}">{{ $e->apeusu }}</option>@endforeach
                            </select></label>
                        <label><span><i class="fas fa-fingerprint text-gray-400"></i> Código de huella <span class="text-gray-400">(opcional)</span></span>
                            <input x-model="form.huella" maxlength="30" placeholder="N° en el lector" class="{{ $in }} mt-1"></label>
                        <label class="sm:col-span-2">Observaciones (lesiones, alergias…)
                            <input x-model="form.obs" maxlength="255" class="{{ $in }} mt-1"></label>
                        <p class="sm:col-span-2 text-xs text-gray-500 bg-gray-50 rounded-lg px-3 py-2">
                            <i class="fas fa-mobile-screen"></i> El cliente entra a su portal con su <b>DNI</b> como usuario y contraseña la primera vez; ahí crea su propia contraseña.
                        </p>
                        <button type="button" @click="guardar()" :disabled="ocupado" class="sm:col-span-2 py-2.5 rounded-xl bg-orange-500 text-white font-bold hover:bg-orange-600 disabled:opacity-50"
                                x-text="ocupado ? 'Guardando…' : 'Guardar cliente'"></button>
                    </div>
                </div>
            </div>
        </div>

        {{-- ============================================================ Modal: ficha --}}
        <div x-show="modal === 'ficha'" x-cloak class="fixed inset-0 z-50 bg-black/50 flex items-start justify-center p-3 overflow-y-auto" @keydown.escape.window="if (modal === 'ficha') cerrarFicha()">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-4xl my-4" x-show="ficha">
                <template x-if="ficha">
                    <div>
                        {{-- Encabezado --}}
                        <div class="p-5 flex gap-4 items-center border-b">
                            <template x-if="ficha.cliente.foto"><img :src="ficha.cliente.foto" class="w-20 h-20 rounded-2xl object-cover ring-4" :class="anillo(ficha.situacion.color)" alt=""></template>
                            <template x-if="!ficha.cliente.foto"><span class="w-20 h-20 rounded-2xl flex items-center justify-center text-3xl font-black text-white" :class="fondo(ficha.situacion.color)" x-text="ficha.cliente.clinom.charAt(0)"></span></template>
                            <div class="flex-1 min-w-0">
                                <p class="text-xl font-extrabold text-gray-800 truncate" x-text="ficha.cliente.clinom"></p>
                                <p class="text-sm text-gray-500" x-text="'N° ' + ficha.cliente.codigo + ' · DNI ' + ficha.cliente.clinum + (ficha.cliente.edad !== null ? ' · ' + ficha.cliente.edad + ' años' : '') + (ficha.cliente.telefono ? ' · ' + ficha.cliente.telefono : '')"></p>
                                <span class="inline-block mt-1 px-2.5 py-1 rounded-full text-xs font-black" :class="chip(ficha.situacion.color)"
                                      x-text="ficha.situacion.texto + (ficha.situacion.vence ? ' · vence ' + fecha(ficha.situacion.vence) : '')"></span>
                                <span x-show="ficha.cliente.estado !== 'ACTIVO'" class="inline-block mt-1 px-2.5 py-1 rounded-full text-xs font-black bg-gray-800 text-white" x-text="ficha.cliente.estado"></span>
                            </div>
                            <button type="button" @click="editar()" class="px-3 py-2 rounded-lg bg-gray-100 text-gray-700 text-sm font-semibold"><i class="fas fa-pen"></i> <span class="hidden sm:inline">Editar</span></button>
                            <button @click="cerrarFicha()" class="text-gray-400 text-3xl leading-none">&times;</button>
                        </div>
                        <div class="px-5 pt-3 flex gap-1 overflow-x-auto border-b">
                            <template x-for="t in [['vender', 'Vender plan'], ['membresias', 'Membresías'], ['congelar', 'Congelamientos'], ['ingresos', 'Ingresos'], ['nutricion', 'Nutrición'], ['cuenta', 'Portal y estado']]">
                                <button type="button" @click="tab = t[0]" class="px-3 py-2 text-sm font-semibold whitespace-nowrap border-b-2 -mb-px"
                                        :class="tab === t[0] ? 'border-orange-500 text-orange-600' : 'border-transparent text-gray-500 hover:text-gray-700'" x-text="t[1]"></button>
                            </template>
                        </div>

                        {{-- Vender plan --}}
                        <div x-show="tab === 'vender'" class="p-5 grid md:grid-cols-2 gap-5 text-sm">
                            <div class="space-y-3">
                                <p class="font-bold text-gray-700">1. Elige el plan</p>
                                <div class="grid grid-cols-2 gap-2">
                                    <template x-for="p in ficha.planes" :key="p.plan_id">
                                        <button type="button" @click="elegirPlan(p)" class="text-left rounded-xl border-2 p-3 transition"
                                                :class="venta.plan_id === p.plan_id ? 'border-orange-500 bg-orange-50' : 'border-gray-200 hover:border-orange-300'">
                                            <p class="font-bold text-gray-800 text-sm" x-text="p.nombre"></p>
                                            <p class="text-xs text-gray-500" x-text="p.dias + (p.dias === 1 ? ' día' : ' días') + (p.congelar_max ? ' · congela ' + p.congelar_max + 'd' : '')"></p>
                                            <p class="font-black text-orange-600" x-text="soles(p.precio)"></p>
                                        </button>
                                    </template>
                                </div>
                                <p x-show="!ficha.planes.length" class="text-gray-400">Aún no hay planes. {{ $esAdmin ? 'Créalos en el botón Planes.' : 'Pide al administrador que los cree.' }}</p>
                                <div class="grid grid-cols-2 gap-3" x-show="venta.plan_id">
                                    <label>Empieza el
                                        <input type="date" x-model="venta.inicio" :disabled="venta.dias <= 1" class="{{ $in }} mt-1 disabled:bg-gray-100"></label>
                                    <label>Precio S/
                                        <input type="number" step="0.10" min="0.1" x-model.number="venta.precio" class="{{ $in }} mt-1"></label>
                                    <p class="col-span-2 text-xs text-gray-500" x-show="venta.dias > 1" x-text="'Vigente del ' + fecha(venta.inicio) + ' al ' + fecha(sumarDias(venta.inicio, venta.dias - 1))"></p>
                                </div>
                            </div>
                            <div class="space-y-3" x-show="venta.plan_id">
                                <p class="font-bold text-gray-700">2. Comprobante y pago</p>
                                <label class="block">Comprobante
                                    <select x-model="venta.tdocod" @change="if (venta.tdocod === '01') { venta.clinum = ''; venta.clinom = ''; venta.tdicod = '6' } else { datosEnVenta() }" class="{{ $in }} mt-1">
                                        <option value="03">Boleta</option><option value="01">Factura</option><option value="13">Nota de venta</option>
                                    </select></label>
                                <div class="grid grid-cols-[130px_1fr] gap-2">
                                    <label>DNI / RUC<input x-model="venta.clinum" @change="buscarDocVenta()" maxlength="15" class="{{ $in }} mt-1"></label>
                                    <label>Nombre / razón social<input x-model="venta.clinom" maxlength="150" class="{{ $in }} mt-1"></label>
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <label>Medio de pago
                                        <select x-model="venta.medio" class="{{ $in }} mt-1">
                                            @foreach ($mediospagos as $m)<option value="{{ $m->id_med_pag }}">{{ $m->nom_med_pag }}</option>@endforeach
                                        </select></label>
                                    <label>Paga con S/<input type="number" step="0.10" min="0" x-model.number="venta.paga" class="{{ $in }} mt-1"></label>
                                </div>
                                <p x-show="venta.paga > venta.precio" class="text-sm">Vuelto: <b x-text="soles(venta.paga - venta.precio)"></b></p>
                                <label class="flex items-center gap-2"><input type="checkbox" x-model="venta.imprimir" class="rounded"> Imprimir comprobante</label>
                                <button type="button" @click="vender()" :disabled="ocupado || {{ $turno ? 'false' : 'true' }}" class="w-full py-3 rounded-xl bg-orange-500 text-white font-black hover:bg-orange-600 disabled:opacity-50"
                                        x-text="ocupado ? 'Registrando…' : 'COBRAR ' + soles(venta.precio)"></button>
                                @if (!$turno)<p class="text-xs text-amber-700">Apertura tu turno de caja para cobrar.</p>@endif
                            </div>
                        </div>

                        {{-- Membresías --}}
                        <div x-show="tab === 'membresias'" class="p-5">
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead class="text-xs text-gray-500 bg-gray-50"><tr><th class="px-3 py-2 text-left">Plan</th><th class="px-3 py-2 text-left">Desde</th><th class="px-3 py-2 text-left">Hasta</th><th class="px-3 py-2 text-right">Precio</th><th class="px-3 py-2 text-left">Comprobante</th></tr></thead>
                                    <tbody class="divide-y">
                                        <template x-for="m in ficha.membresias" :key="m.mem_id">
                                            <tr :class="m.estado === 'ANULADA' ? 'opacity-50 line-through' : ''">
                                                <td class="px-3 py-2 font-semibold" x-text="m.plan"></td>
                                                <td class="px-3 py-2" x-text="fecha(m.inicio)"></td>
                                                <td class="px-3 py-2"><span x-text="fecha(m.fin)"></span>
                                                    <span x-show="diasEntre(m.inicio, m.fin) + 1 > m.dias" class="text-xs text-sky-600" x-text="'(+' + (diasEntre(m.inicio, m.fin) + 1 - m.dias) + 'd congelados)'"></span></td>
                                                <td class="px-3 py-2 text-right" x-text="soles(m.precio)"></td>
                                                <td class="px-3 py-2"><a x-show="m.IdCpe_cabecera" :href="'{{ url('voucher') }}/' + m.IdCpe_cabecera" target="_blank" class="text-orange-600 underline" x-text="m.comprobante"></a></td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                                <p x-show="!ficha.membresias.length" class="p-8 text-center text-gray-400">Aún no compró ningún plan.</p>
                            </div>
                        </div>

                        {{-- Congelamientos --}}
                        <div x-show="tab === 'congelar'" class="p-5 space-y-4 text-sm">
                            <div class="divide-y border rounded-xl">
                                <template x-for="g in ficha.congelamientos" :key="g.con_id">
                                    <div class="p-3 flex flex-wrap items-center gap-3" :class="g.estado === 'ANULADO' ? 'opacity-50' : ''">
                                        <div class="flex-1 min-w-[200px]">
                                            <p class="font-bold"><i class="fas fa-snowflake text-sky-500"></i>
                                                <span x-text="fecha(g.desde) + ' al ' + fecha(g.hasta) + ' · ' + g.dias + (g.dias === 1 ? ' día' : ' días')"></span>
                                                <span class="ml-1 px-1.5 py-0.5 rounded text-[10px] font-bold" :class="g.origen === 'CLIENTE' ? 'bg-sky-100 text-sky-700' : 'bg-gray-100 text-gray-700'" x-text="g.origen === 'CLIENTE' ? 'LO PIDIÓ EL CLIENTE' : 'ADMINISTRADOR'"></span>
                                                <span x-show="g.estado === 'ANULADO'" class="ml-1 px-1.5 py-0.5 rounded text-[10px] font-bold bg-rose-100 text-rose-700">ANULADO</span></p>
                                            <p class="text-xs text-gray-500" x-text="g.motivo + (g.detalle ? ': ' + g.detalle : '')"></p>
                                            <p class="text-[11px] text-gray-400" x-show="g.nota" x-text="g.nota + (g.apeusu ? ' (' + g.apeusu + ')' : '')"></p>
                                        </div>
                                        @if ($esAdmin)
                                            <div class="flex gap-1.5" x-show="g.estado === 'ACTIVO' && g.hasta >= hoyIso">
                                                <button type="button" x-show="g.desde <= hoyIso" @click="accionCongelamiento(g, 'levantar')" class="px-2.5 py-1.5 rounded-lg bg-emerald-600 text-white text-xs font-bold" title="El cliente volvió antes: desde hoy puede entrar">Volvió antes</button>
                                                <button type="button" @click="editarCongelamiento(g)" class="px-2.5 py-1.5 rounded-lg bg-gray-100 text-gray-700 text-xs font-bold">Editar</button>
                                                <button type="button" @click="accionCongelamiento(g, 'anular')" class="px-2.5 py-1.5 rounded-lg bg-rose-50 text-rose-700 text-xs font-bold">Anular</button>
                                            </div>
                                        @endif
                                    </div>
                                </template>
                                <p x-show="!ficha.congelamientos.length" class="p-6 text-center text-gray-400">Sin congelamientos.</p>
                            </div>
                            @if ($esAdmin)
                                <div class="rounded-xl bg-sky-50 p-4 space-y-3">
                                    <p class="font-bold text-sky-900" x-text="cong.con_id ? 'Editar congelamiento' : 'Congelar (administrador)'"></p>
                                    <div class="grid sm:grid-cols-4 gap-2">
                                        <label>Desde<input type="date" x-model="cong.desde" class="{{ $in }} mt-1"></label>
                                        <label>Hasta<input type="date" x-model="cong.hasta" :min="cong.desde" class="{{ $in }} mt-1"></label>
                                        <label>Motivo
                                            <select x-model="cong.motivo" class="{{ $in }} mt-1">@foreach ($motivos as $m)<option>{{ $m }}</option>@endforeach</select></label>
                                        <label>Detalle<input x-model="cong.detalle" maxlength="200" class="{{ $in }} mt-1"></label>
                                    </div>
                                    <div class="flex gap-2">
                                        <button type="button" @click="guardarCongelamiento()" :disabled="ocupado" class="px-4 py-2 rounded-lg bg-sky-600 text-white font-bold disabled:opacity-50" x-text="cong.con_id ? 'Guardar cambios' : 'Congelar'"></button>
                                        <button type="button" x-show="cong.con_id" @click="limpiarCong()" class="px-4 py-2 rounded-lg bg-white text-gray-600 font-semibold">Cancelar</button>
                                    </div>
                                    <p class="text-xs text-sky-800">Los días congelados se suman al final del plan. El cliente también puede congelar desde su portal (solo desde mañana y hasta los días que permite su plan); lo que él registra no lo puede deshacer.</p>
                                </div>
                            @else
                                <p class="text-xs text-gray-500">Solo el administrador puede congelar, editar o anular. El cliente puede congelar desde su portal.</p>
                            @endif
                        </div>

                        {{-- Ingresos --}}
                        <div x-show="tab === 'ingresos'" class="p-5">
                            <div class="divide-y border rounded-xl text-sm max-h-[420px] overflow-y-auto">
                                <template x-for="a in ficha.asistencias">
                                    <div class="px-3 py-2 flex items-center gap-3">
                                        <i class="fas" :class="a.resultado === 'PERMITIDO' ? 'fa-circle-check text-emerald-500' : 'fa-circle-xmark text-rose-500'"></i>
                                        <span class="font-semibold w-36" x-text="fechaHora(a.fecha_hora)"></span>
                                        <span class="text-xs px-1.5 py-0.5 rounded bg-gray-100 text-gray-600" x-text="a.metodo"></span>
                                        <span class="text-xs text-gray-500 flex-1 truncate" x-text="a.motivo"></span>
                                    </div>
                                </template>
                                <p x-show="!ficha.asistencias.length" class="p-8 text-center text-gray-400">Sin ingresos registrados.</p>
                            </div>
                        </div>

                        {{-- Nutrición (la registra el entrenador) --}}
                        <div x-show="tab === 'nutricion'" class="p-5 space-y-3 text-sm">
                            <template x-for="n in ficha.nutricion" :key="n.nut_id">
                                <div class="border rounded-xl p-4">
                                    <p class="font-bold text-gray-800" x-text="fecha(n.fecha) + ' · ' + n.objetivo"></p>
                                    <p class="text-xs text-gray-500" x-text="'Entrenador: ' + (n.entrenador || '—') + (n.peso ? ' · ' + n.peso + ' kg' : '') + (n.imc ? ' · IMC ' + n.imc : '') + (n.calorias ? ' · ' + n.calorias + ' kcal' : '')"></p>
                                </div>
                            </template>
                            <p x-show="!ficha.nutricion.length" class="p-8 text-center text-gray-400">El entrenador aún no le registró un plan de nutrición.</p>
                        </div>

                        {{-- Portal y estado --}}
                        <div x-show="tab === 'cuenta'" class="p-5 grid sm:grid-cols-2 gap-4 text-sm">
                            <div class="rounded-xl border p-4 space-y-2">
                                <p class="font-bold">Portal del cliente</p>
                                <p class="text-gray-600">Usuario: <b x-text="ficha.cliente.clinum"></b></p>
                                <p class="text-gray-600" x-text="ficha.cliente.tiene_clave ? 'Ya creó su propia contraseña.' : 'Aún no entra: su contraseña es su DNI.'"></p>
                                <p class="text-gray-500 text-xs" x-show="ficha.cliente.acceso" x-text="'Último ingreso al portal: ' + fechaHora(ficha.cliente.acceso)"></p>
                                <button type="button" @click="restablecerClave()" class="px-3 py-1.5 rounded-lg bg-gray-100 font-semibold">Olvidó su contraseña</button>
                            </div>
                            @if ($esAdmin)
                                <div class="rounded-xl border p-4 space-y-2">
                                    <p class="font-bold">Estado del cliente</p>
                                    <select x-model="estadoManual" class="{{ $in }}"><option>ACTIVO</option><option>SUSPENDIDO</option><option>RETIRADO</option></select>
                                    <button type="button" @click="cambiarEstado()" class="px-3 py-1.5 rounded-lg bg-gray-900 text-white font-semibold">Guardar estado</button>
                                    <p class="text-xs text-gray-500">Suspendido o retirado no puede entrar ni ver su portal.</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </template>
            </div>
        </div>

        {{-- ============================================================ Modal: planes --}}
        @if ($esAdmin)
            <div x-show="modal === 'planes'" x-cloak class="fixed inset-0 z-50 bg-black/50 flex items-start sm:items-center justify-center p-3 overflow-y-auto" @keydown.escape.window="modal = null">
                <div class="bg-white rounded-2xl shadow-2xl w-full max-w-3xl my-4">
                    <div class="px-5 py-3 border-b flex justify-between items-center">
                        <h3 class="font-bold text-gray-800"><i class="fas fa-tags text-orange-500"></i> Planes del gimnasio</h3>
                        <button @click="modal = null" class="text-gray-400 text-2xl leading-none">&times;</button>
                    </div>
                    <div class="p-5 space-y-4 text-sm">
                        <table class="w-full">
                            <thead class="text-xs text-gray-500 bg-gray-50"><tr><th class="px-3 py-2 text-left">Plan</th><th class="px-3 py-2">Días</th><th class="px-3 py-2 text-right">Precio</th><th class="px-3 py-2">Puede congelar</th><th></th></tr></thead>
                            <tbody class="divide-y">
                                <template x-for="p in planes" :key="p.plan_id">
                                    <tr :class="p.activo ? '' : 'opacity-50'">
                                        <td class="px-3 py-2 font-semibold" x-text="p.nombre + (p.activo ? '' : ' (inactivo)')"></td>
                                        <td class="px-3 py-2 text-center" x-text="p.dias"></td>
                                        <td class="px-3 py-2 text-right" x-text="soles(p.precio)"></td>
                                        <td class="px-3 py-2 text-center" x-text="p.congelar_max ? p.congelar_max + ' días' : 'No'"></td>
                                        <td class="px-3 py-2 text-right"><button type="button" @click="plan = { ...p, activo: !!p.activo }" class="text-orange-600 font-semibold">Editar</button></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                        <div class="rounded-xl bg-orange-50 p-4 grid sm:grid-cols-6 gap-2 items-end">
                            <label class="sm:col-span-2">Nombre<input x-model="plan.nombre" maxlength="80" placeholder="MENSUAL" class="{{ $in }} mt-1 uppercase"></label>
                            <label>Días<input type="number" min="1" x-model.number="plan.dias" class="{{ $in }} mt-1"></label>
                            <label>Precio S/<input type="number" step="0.10" min="0.1" x-model.number="plan.precio" class="{{ $in }} mt-1"></label>
                            <label :class="plan.dias > 1 ? '' : 'opacity-40'">Congela (días)<input type="number" min="0" x-model.number="plan.congelar_max" :disabled="plan.dias <= 1" class="{{ $in }} mt-1"></label>
                            <label class="flex items-center gap-2 pb-2"><input type="checkbox" x-model="plan.activo" class="rounded"> Activo</label>
                            <div class="sm:col-span-6 flex gap-2">
                                <button type="button" @click="guardarPlan()" :disabled="ocupado" class="px-4 py-2 rounded-lg bg-orange-500 text-white font-bold disabled:opacity-50" x-text="plan.plan_id ? 'Guardar cambios' : 'Agregar plan'"></button>
                                <button type="button" x-show="plan.plan_id" @click="plan = planVacio()" class="px-4 py-2 rounded-lg bg-white text-gray-600 font-semibold">Nuevo</button>
                            </div>
                            <p class="sm:col-span-6 text-xs text-orange-900">Un plan de <b>1 día</b> es la rutina del día: sirve también para que entre un cliente con plan congelado. "Congela" son los días que el cliente puede congelar solo desde su portal (0 = no puede).</p>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        {{-- Aviso --}}
        <div x-show="aviso.visible" x-cloak x-transition class="fixed bottom-5 left-1/2 -translate-x-1/2 z-[60] px-5 py-3 rounded-xl shadow-2xl text-white text-sm font-semibold max-w-md text-center"
             :class="aviso.ok ? 'bg-emerald-600' : 'bg-rose-600'" x-text="aviso.texto"></div>
    </div>

    <script>
        function gimnasio() {
            const RUTA = @json(url('gimnasio'));
            const CSRF = @json(csrf_token());
            const hoyIso = @json(now()->toDateString());
            const planVacio = () => ({ plan_id: null, nombre: '', dias: 30, precio: null, congelar_max: 0, activo: true });
            return {
                clientes: @js($clientes), planes: @js($planes), q: '', filtro: '', mostrar: 100, modal: null, tab: 'vender', ocupado: false,
                ficha: null, form: {}, venta: {}, cong: {}, plan: planVacio(), planVacio, estadoManual: 'ACTIVO', camara: null, hoyIso,
                aviso: { visible: false, ok: true, texto: '' },
                tarjetas: [
                    { estado: 'VIGENTE', nombre: 'Vigentes', texto: 'text-emerald-600', borde: 'border-emerald-400' },
                    { estado: 'POR_VENCER', nombre: 'Por vencer', texto: 'text-amber-500', borde: 'border-amber-400' },
                    { estado: 'CONGELADO', nombre: 'Congelados', texto: 'text-sky-500', borde: 'border-sky-400' },
                    { estado: 'VENCIDO', nombre: 'Vencidos', texto: 'text-rose-600', borde: 'border-rose-400' },
                    { estado: 'SIN_PLAN', nombre: 'Sin plan', texto: 'text-gray-500', borde: 'border-gray-400' },
                ],
                cuenta(e) { return this.clientes.filter(c => c.estado === e).length; },
                get visibles() {
                    const q = this.q.trim().toLowerCase();
                    return this.clientes.filter(c => (!this.filtro || c.estado === this.filtro)
                        && (!q || [c.nombre, c.doc, c.telefono, c.codigo].some(v => (v || '').toLowerCase().includes(q))));
                },
                // ---- formato
                soles(n) { return 'S/ ' + Number(n || 0).toFixed(2); },
                fecha(s) { if (!s) return ''; const [a, m, d] = s.slice(0, 10).split('-'); return `${d}/${m}/${a}`; },
                fechaHora(s) { return s ? this.fecha(s) + ' ' + s.slice(11, 16) : ''; },
                sumarDias(s, n) { const d = new Date(s + 'T12:00:00'); d.setDate(d.getDate() + n); return d.toISOString().slice(0, 10); },
                diasEntre(a, b) { return Math.round((new Date(b + 'T12:00:00') - new Date(a + 'T12:00:00')) / 86400000); },
                chip(c) { return { green: 'bg-emerald-100 text-emerald-700', amber: 'bg-amber-100 text-amber-800', sky: 'bg-sky-100 text-sky-700', red: 'bg-rose-100 text-rose-700' }[c] || 'bg-gray-100 text-gray-600'; },
                fondo(c) { return { green: 'bg-emerald-500', amber: 'bg-amber-500', sky: 'bg-sky-500', red: 'bg-rose-500' }[c] || 'bg-gray-400'; },
                anillo(c) { return { green: 'ring-emerald-400', amber: 'ring-amber-400', sky: 'ring-sky-400', red: 'ring-rose-400' }[c] || 'ring-gray-300'; },
                avisar(texto, ok = true) {
                    this.aviso = { visible: true, ok, texto };
                    clearTimeout(this._t); this._t = setTimeout(() => this.aviso.visible = false, ok ? 3500 : 6000);
                },
                async post(url, datos = {}) {
                    const esForm = datos instanceof FormData;
                    try {
                        const r = await fetch(url, { method: 'POST', body: esForm ? datos : JSON.stringify(datos),
                            headers: Object.assign({ 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF }, esForm ? {} : { 'Content-Type': 'application/json' }) });
                        const d = await r.json();
                        if (r.status === 422) return { ok: false, mensaje: Object.values(d.errors || {})[0]?.[0] || d.message };
                        return d;
                    } catch (e) { return { ok: false, mensaje: 'Sin conexión con el servidor.' }; }
                },
                async recargarLista() {
                    this.clientes = await fetch(RUTA + '/clientes', { headers: { 'Accept': 'application/json' } }).then(r => r.json()).catch(() => this.clientes);
                },

                // ---- cliente
                nuevo() {
                    this.ficha = null;
                    this.form = { soc_id: null, clinum: '', clinom: '', telefono: '', clicor: '', fecha_nac: '', huella: '', entrenador_id: '', obs: '', vista: null, archivo: null, quitar_foto: false };
                    this.modal = 'cliente';
                },
                editar() {
                    const c = this.ficha.cliente;
                    this.form = { soc_id: c.soc_id, clinum: c.clinum, clinom: c.clinom, telefono: c.telefono || '', clicor: c.clicor || '', fecha_nac: c.fecha_nac || '',
                        huella: c.huella || '', entrenador_id: c.entrenador_id || '', obs: c.obs || '', vista: c.foto, archivo: null, quitar_foto: false };
                    this.modal = 'cliente';
                },
                async buscarDoc() {
                    const doc = (this.form.clinum || '').trim();
                    if (!/^\d{8}$/.test(doc) || this.form.clinom) return;
                    const d = await fetch('{{ url('cobros/cliente') }}/' + doc, { headers: { 'Accept': 'application/json' } }).then(r => r.json()).catch(() => ({}));
                    if (d && d.nom) { this.form.clinom = d.nom; this.form.telefono = this.form.telefono || d.tel || ''; this.form.clicor = this.form.clicor || d.cor || ''; }
                },
                elegirArchivo(e) {
                    const f = e.target.files[0];
                    if (f) { this.form.archivo = f; this.form.vista = URL.createObjectURL(f); this.form.quitar_foto = false; }
                },
                async abrirCamara() {
                    try {
                        this.camara = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user', width: 640, height: 640 }, audio: false });
                        this.$refs.video.srcObject = this.camara;
                    } catch (e) { this.avisar('No se pudo abrir la cámara: revisa el permiso del navegador.', false); }
                },
                tomarFoto() {
                    const v = this.$refs.video, c = this.$refs.lienzo, lado = Math.min(v.videoWidth, v.videoHeight);
                    c.width = c.height = 600;
                    c.getContext('2d').drawImage(v, (v.videoWidth - lado) / 2, (v.videoHeight - lado) / 2, lado, lado, 0, 0, 600, 600);
                    c.toBlob(b => { this.form.archivo = new File([b], 'foto.jpg', { type: 'image/jpeg' }); this.form.vista = URL.createObjectURL(b); this.form.quitar_foto = false; }, 'image/jpeg', 0.88);
                    this.cerrarCamara();
                },
                cerrarCamara() { this.camara?.getTracks().forEach(t => t.stop()); this.camara = null; },
                async guardar() {
                    if (!this.form.clinum.trim() || this.form.clinom.trim().length < 3) return this.avisar('Escribe el DNI y el nombre completo.', false);
                    const fd = new FormData();
                    ['soc_id', 'clinum', 'clinom', 'telefono', 'clicor', 'fecha_nac', 'huella', 'entrenador_id', 'obs'].forEach(k => { if (this.form[k]) fd.append(k, this.form[k]); });
                    if (this.form.archivo) fd.append('foto', this.form.archivo);
                    if (this.form.quitar_foto) fd.append('quitar_foto', 1);
                    this.ocupado = true;
                    const r = await this.post(RUTA + '/clientes', fd);
                    this.ocupado = false;
                    this.avisar(r.mensaje, r.ok);
                    if (!r.ok) return;
                    this.cerrarCamara();
                    await this.recargarLista();
                    await this.abrirFicha(r.soc_id, this.form.soc_id ? this.tab : 'vender');
                },

                // ---- ficha
                async abrirFicha(id, tab = null) {
                    const d = await fetch(RUTA + '/clientes/' + id, { headers: { 'Accept': 'application/json' } }).then(r => r.json()).catch(() => null);
                    if (!d) return this.avisar('No se pudo abrir la ficha.', false);
                    this.ficha = d; this.estadoManual = d.cliente.estado;
                    this.tab = tab || (['VENCIDO', 'SIN_PLAN', 'POR_VENCER'].includes(d.situacion.estado) ? 'vender' : 'membresias');
                    this.venta = { plan_id: null, dias: 0, inicio: hoyIso, precio: 0, tdocod: '03', tdicod: '1', clinum: '', clinom: '', medio: @json((string) ($mediospagos->first()->id_med_pag ?? '')), paga: null, imprimir: true };
                    this.datosEnVenta(); this.limpiarCong();
                    this.modal = 'ficha';
                },
                cerrarFicha() { this.modal = null; this.ficha = null; },
                elegirPlan(p) { Object.assign(this.venta, { plan_id: p.plan_id, dias: p.dias, inicio: p.inicio, precio: Number(p.precio) }); },
                datosEnVenta() { const c = this.ficha?.cliente; if (!c) return; this.venta.clinum = c.clinum; this.venta.clinom = c.clinom; this.venta.tdicod = c.clinum.length === 11 ? '6' : '1'; },
                async buscarDocVenta() {
                    const doc = (this.venta.clinum || '').trim();
                    if (!/^\d{8}$|^\d{11}$/.test(doc)) return;
                    const d = await fetch('{{ url('cobros/cliente') }}/' + doc, { headers: { 'Accept': 'application/json' } }).then(r => r.json()).catch(() => ({}));
                    if (d && d.nom) { this.venta.clinom = d.nom; this.venta.tdicod = doc.length === 11 ? '6' : '1'; this.venta.clidir = d.dir && d.dir !== '--' ? d.dir : ''; if (doc.length === 11) this.venta.tdocod = '01'; }
                },
                async vender() {
                    const v = this.venta;
                    if (v.tdocod === '01' && !/^\d{11}$/.test(v.clinum)) return this.avisar('Para factura escribe el RUC.', false);
                    this.ocupado = true;
                    const r = await this.post(RUTA + '/clientes/' + this.ficha.cliente.soc_id + '/vender', {
                        plan_id: v.plan_id, inicio: v.inicio, precio: v.precio, tdocod: v.tdocod, clinum: v.clinum, clinom: v.clinom, tdicod: v.tdicod, clidir: v.clidir || '',
                        id_med_pag: [v.medio], mon_med_pag: [v.precio], paga: v.paga || v.precio, imprimir: v.imprimir ? 1 : 0,
                    });
                    this.ocupado = false;
                    this.avisar(r.ok ? `${r.mensaje} ${r.numero}` + (r.vuelto > 0 ? ` · vuelto ${this.soles(r.vuelto)}` : '') : r.mensaje, r.ok);
                    if (!r.ok) return;
                    if (v.imprimir && !r.impreso) window.open('{{ url('voucher') }}/' + r.id + '?imprimir=1', '_blank');
                    await this.recargarLista();
                    await this.abrirFicha(this.ficha.cliente.soc_id, 'membresias');
                },
                async restablecerClave() {
                    if (!confirm('¿Restablecer la contraseña del portal? Volverá a ser su DNI.')) return;
                    const r = await this.post(RUTA + '/clientes/' + this.ficha.cliente.soc_id + '/clave');
                    this.avisar(r.mensaje, r.ok);
                },
                async cambiarEstado() {
                    const r = await this.post(RUTA + '/clientes/' + this.ficha.cliente.soc_id + '/estado', { estado: this.estadoManual });
                    this.avisar(r.mensaje, r.ok);
                    if (r.ok) { this.ficha.cliente.estado = this.estadoManual; this.recargarLista(); }
                },

                // ---- congelamientos (administrador)
                limpiarCong() { this.cong = { con_id: null, desde: hoyIso, hasta: this.sumarDias(hoyIso, 6), motivo: 'VIAJE', detalle: '' }; },
                editarCongelamiento(g) { this.cong = { con_id: g.con_id, desde: g.desde, hasta: g.hasta, motivo: g.motivo, detalle: g.detalle || '' }; },
                async guardarCongelamiento() {
                    const c = this.cong;
                    this.ocupado = true;
                    const r = await this.post(c.con_id ? RUTA + '/congelamientos/' + c.con_id : RUTA + '/clientes/' + this.ficha.cliente.soc_id + '/congelar', c);
                    this.ocupado = false;
                    this.avisar(r.mensaje, r.ok);
                    if (r.ok) { await this.recargarLista(); await this.abrirFicha(this.ficha.cliente.soc_id, 'congelar'); }
                },
                async accionCongelamiento(g, accion) {
                    const pregunta = accion === 'levantar' ? '¿El cliente volvió antes? Desde hoy podrá entrar y su plan vuelve a correr.' : '¿Anular este congelamiento? Esos días ya no se sumarán a su plan.';
                    if (!confirm(pregunta)) return;
                    const r = await this.post(RUTA + '/congelamientos/' + g.con_id + '/' + accion);
                    this.avisar(r.mensaje, r.ok);
                    if (r.ok) { await this.recargarLista(); await this.abrirFicha(this.ficha.cliente.soc_id, 'congelar'); }
                },

                // ---- planes
                async guardarPlan() {
                    if (!this.plan.nombre.trim() || !this.plan.dias || !this.plan.precio) return this.avisar('Completa nombre, días y precio.', false);
                    this.ocupado = true;
                    const r = await this.post(RUTA + '/planes', { ...this.plan, activo: this.plan.activo ? 1 : 0 });
                    this.ocupado = false;
                    this.avisar(r.mensaje, r.ok);
                    if (r.ok) { this.planes = r.planes; this.plan = planVacio(); }
                },
            };
        }
    </script>
@endsection
