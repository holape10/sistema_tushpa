@extends('layouts.app')
@section('title', 'Editar Sucursal')
@section('content')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    @include('empresas.partials.alert')

    @php
        $input = 'block w-full mt-1 rounded-xl border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500';
        $campo = fn($nombre) => old($nombre, $sucursal->$nombre);
        $grupos = [
            ['titulo' => 'Comprobantes de venta', 'icono' => 'fa-receipt', 'claves' => ['01', '03', '13']],
            ['titulo' => 'Notas de crédito', 'icono' => 'fa-file-circle-minus', 'claves' => ['07F', '07B']],
            ['titulo' => 'Notas de débito', 'icono' => 'fa-file-circle-plus', 'claves' => ['08F', '08B']],
            ['titulo' => 'Proformas (cotizaciones, sin valor tributario)', 'icono' => 'fa-file-lines', 'claves' => ['PR']],
            ['titulo' => 'Guías de remisión electrónicas', 'icono' => 'fa-truck-fast', 'claves' => ['GR']],
        ];
        // Clases completas (Tailwind no detecta clases armadas por partes)
        $azul = ['badge' => 'bg-blue-100 text-blue-700', 'texto' => 'text-blue-700'];
        $verde = ['badge' => 'bg-emerald-100 text-emerald-700', 'texto' => 'text-emerald-700'];
        $gris = ['badge' => 'bg-slate-100 text-slate-700', 'texto' => 'text-slate-700'];
        $rojo = ['badge' => 'bg-rose-100 text-rose-700', 'texto' => 'text-rose-700'];
        $ambar = ['badge' => 'bg-amber-100 text-amber-700', 'texto' => 'text-amber-700'];
        $violeta = ['badge' => 'bg-violet-100 text-violet-700', 'texto' => 'text-violet-700'];
        $colores = ['01' => $azul, '03' => $verde, '13' => $gris, '07F' => $rojo, '07B' => $rojo, '08F' => $ambar, '08B' => $ambar, 'PR' => $violeta, 'GR' => $azul];
        $nombresCortos = ['01' => 'Factura', '03' => 'Boleta', '13' => 'Nota de venta', '07F' => 'De facturas', '07B' => 'De boletas', '08F' => 'De facturas', '08B' => 'De boletas', 'PR' => 'Proforma', 'GR' => 'Guía remitente'];
        $secciones = ['datos' => ['Datos', 'fa-store'], 'direccion' => ['Dirección', 'fa-location-dot'], 'series' => ['Series', 'fa-hashtag'],
                      'impresion' => ['Impresión', 'fa-print'], 'venta' => ['Venta', 'fa-cash-register']];
    @endphp

    <form method="POST" action="{{ route('sucursales.update', $sucursal->id_empresa_negocio) }}" class="max-w-6xl mx-auto pb-24"
          x-data="{ formato: @js($campo('formato_impresion') ?: 'TICKET'), igv: @js((string) $campo('tip_igv_pred')), pred: @js((string) $campo('tdocod_pred')), stock: @js((string) ($campo('control_stock') ?: 'libre')) }">
        @csrf @method('PATCH')

        {{-- Encabezado --}}
        <div class="rounded-2xl bg-gradient-to-r from-indigo-700 via-indigo-600 to-violet-600 text-white p-5 sm:p-6 mb-5 shadow-sm">
            <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                <div class="w-14 h-14 rounded-2xl bg-white/15 flex items-center justify-center text-2xl shrink-0"><i class="fas fa-store"></i></div>
                <div class="min-w-0 flex-1">
                    <p class="text-xs uppercase tracking-wider text-indigo-200">Sucursal · RUC {{ $sucursal->IdEmpresa }}</p>
                    <h2 class="text-xl sm:text-2xl font-extrabold truncate">{{ $sucursal->nombre_comercial }}</h2>
                    <p class="text-sm text-indigo-100 truncate"><i class="fas fa-location-dot mr-1"></i>{{ $sucursal->direccion }}</p>
                </div>
                <span class="self-start sm:self-center px-3 py-1 rounded-full text-xs font-bold {{ $sucursal->estado === 'Activo' ? 'bg-emerald-400 text-emerald-950' : 'bg-white/20' }}">{{ strtoupper($sucursal->estado) }}</span>
            </div>
        </div>

        <div class="lg:grid lg:grid-cols-[200px_1fr] gap-6">
            {{-- Índice de secciones --}}
            <nav class="mb-4 lg:mb-0">
                <div class="flex lg:flex-col gap-1 overflow-x-auto lg:sticky lg:top-4 bg-white lg:bg-transparent rounded-2xl p-1 shadow-sm lg:shadow-none">
                    @foreach ($secciones as $id => [$nombre, $icono])
                        <a href="#{{ $id }}" class="flex items-center gap-2 px-3 py-2 rounded-xl text-sm font-semibold text-gray-600 hover:bg-indigo-50 hover:text-indigo-700 whitespace-nowrap">
                            <i class="fas {{ $icono }} w-4 text-center"></i>{{ $nombre }}</a>
                    @endforeach
                </div>
            </nav>

            <div class="space-y-5 min-w-0">
                {{-- Datos generales --}}
                <section id="datos" class="bg-white rounded-2xl shadow-sm scroll-mt-4">
                    <header class="flex items-center gap-3 px-5 py-4 border-b border-gray-100">
                        <span class="w-9 h-9 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center"><i class="fas fa-store"></i></span>
                        <div><h3 class="font-bold text-gray-800">Datos de la sucursal</h3><p class="text-xs text-gray-400">Cómo se muestra el negocio en los comprobantes.</p></div>
                    </header>
                    <div class="grid sm:grid-cols-6 gap-4 p-5">
                        <label class="text-sm font-medium text-gray-600 sm:col-span-4">Nombre comercial
                            <input name="nombre_comercial" value="{{ $campo('nombre_comercial') }}" required maxlength="255" class="{{ $input }}"></label>
                        <label class="text-sm font-medium text-gray-600 sm:col-span-2">Estado
                            <select name="estado" class="{{ $input }}">
                                @foreach (['Activo', 'Inactivo'] as $e)<option @selected($campo('estado') == $e)>{{ $e }}</option>@endforeach
                            </select></label>
                        <label class="text-sm font-medium text-gray-600 sm:col-span-6">Descripción / tipo de negocio
                            <input name="tipo_negocio" value="{{ $campo('tipo_negocio') }}" maxlength="255" class="{{ $input }}"></label>
                        <label class="text-sm font-medium text-gray-600 sm:col-span-2">Teléfono
                            <input name="telefono" value="{{ $campo('telefono') }}" maxlength="30" placeholder="Ej. 965 123 456" class="{{ $input }}"></label>
                        <label class="text-sm font-medium text-gray-600 sm:col-span-2">Correo
                            <input type="email" name="correo" value="{{ $campo('correo') }}" maxlength="255" placeholder="ventas@minegocio.com" class="{{ $input }}"></label>
                        <label class="text-sm font-medium text-gray-600 sm:col-span-2">Web
                            <input name="web" value="{{ $campo('web') }}" maxlength="255" placeholder="www.minegocio.com" class="{{ $input }}"></label>
                    </div>
                </section>

                {{-- Dirección fiscal --}}
                <section id="direccion" class="bg-white rounded-2xl shadow-sm scroll-mt-4">
                    <header class="flex items-center gap-3 px-5 py-4 border-b border-gray-100">
                        <span class="w-9 h-9 rounded-xl bg-sky-50 text-sky-600 flex items-center justify-center"><i class="fas fa-location-dot"></i></span>
                        <div><h3 class="font-bold text-gray-800">Dirección del establecimiento</h3><p class="text-xs text-gray-400">Viaja en cada comprobante electrónico. Si falta, SUNAT pone observaciones.</p></div>
                    </header>
                    <div class="grid sm:grid-cols-4 gap-4 p-5">
                        <label class="text-sm font-medium text-gray-600 sm:col-span-4">Dirección
                            <input name="direccion" value="{{ $campo('direccion') }}" required maxlength="255" class="{{ $input }}"></label>
                        <div class="text-sm font-medium text-gray-600 sm:col-span-3">Ciudad / distrito <span class="font-normal text-gray-400">(escribe y elige; el ubigeo, departamento y provincia salen solos)</span>
                            <div class="mt-1">@include('empresas.partials.ubigeo', ['name' => 'ubigeo', 'valor' => $campo('ubigeo')])</div></div>
                        <label class="text-sm font-medium text-gray-600">Código de establecimiento SUNAT
                            <input name="codigofiscal" value="{{ $campo('codigofiscal') }}" maxlength="4" inputmode="numeric" placeholder="0000" class="{{ $input }} font-mono">
                            <span class="text-xs font-normal text-gray-400">0000 = domicilio fiscal (principal)</span></label>
                    </div>
                </section>

                {{-- Series y correlativos --}}
                <section id="series" class="bg-white rounded-2xl shadow-sm scroll-mt-4">
                    <header class="flex items-center gap-3 px-5 py-4 border-b border-gray-100">
                        <span class="w-9 h-9 rounded-xl bg-violet-50 text-violet-600 flex items-center justify-center"><i class="fas fa-hashtag"></i></span>
                        <div><h3 class="font-bold text-gray-800">Series y correlativos</h3>
                            <p class="text-xs text-gray-400">El correlativo es el <strong>último número usado</strong>; el próximo sale con +1. No puede bajar de lo ya emitido.</p></div>
                    </header>
                    <div class="p-5 space-y-6">
                        @foreach ($grupos as $g)
                            <div>
                                <p class="text-xs font-bold uppercase tracking-wider text-gray-500 mb-2"><i class="fas {{ $g['icono'] }} mr-1"></i>{{ $g['titulo'] }}</p>
                                <div class="grid sm:grid-cols-2 xl:grid-cols-3 gap-3">
                                    @foreach ($g['claves'] as $clave)
                                        @php $c = $series[$clave]; $color = $colores[$clave]; @endphp
                                        <div class="rounded-2xl border border-gray-200 p-4 hover:border-indigo-300 hover:shadow-sm transition"
                                             x-data="{ serie: @js($campo($c['serie'])), num: {{ (int) $campo($c['numero']) }} }">
                                            <div class="flex items-center justify-between mb-3">
                                                <span class="font-semibold text-gray-700 text-sm">{{ $nombresCortos[$clave] }}</span>
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $color['badge'] }}">{{ $c['tdocod'] ?? $clave }}</span>
                                            </div>
                                            <div class="grid grid-cols-5 gap-2">
                                                <label class="col-span-2 text-xs text-gray-500">Serie
                                                    <input name="{{ $c['serie'] }}" x-model="serie" required maxlength="4" class="{{ $input }} uppercase font-mono font-bold text-center"></label>
                                                <label class="col-span-3 text-xs text-gray-500">Último usado
                                                    <input type="number" name="{{ $c['numero'] }}" x-model.number="num" min="{{ $ultimos[$clave] }}" required class="{{ $input }} font-mono text-right"></label>
                                            </div>
                                            <div class="mt-3 rounded-xl bg-gray-50 px-3 py-2">
                                                <p class="text-[10px] uppercase text-gray-400">Próximo</p>
                                                <p class="font-mono font-extrabold {{ $color['texto'] }}" x-text="(serie || '').toUpperCase() + '-' + String((parseInt(num) || 0) + 1).padStart(8, '0')"></p>
                                                <p class="text-[10px] text-gray-400">Emitido en el sistema: {{ $ultimos[$clave] ?: 'ninguno' }}</p>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                        <p class="text-xs text-gray-400"><i class="fas fa-circle-info mr-1"></i>SUNAT exige que la nota de una factura use una serie que empiece con <strong>F</strong> y la de una boleta con <strong>B</strong>.</p>
                    </div>
                </section>

                {{-- Impresión --}}
                <section id="impresion" class="bg-white rounded-2xl shadow-sm scroll-mt-4">
                    <header class="flex items-center gap-3 px-5 py-4 border-b border-gray-100">
                        <span class="w-9 h-9 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center"><i class="fas fa-print"></i></span>
                        <div><h3 class="font-bold text-gray-800">Formato de impresión</h3><p class="text-xs text-gray-400">Cómo se imprimen y se ven los comprobantes de esta sucursal.</p></div>
                    </header>
                    <div class="grid sm:grid-cols-2 gap-4 p-5">
                        <label class="relative cursor-pointer rounded-2xl border-2 p-4 flex gap-4 items-center transition"
                               :class="formato === 'TICKET' ? 'border-indigo-500 bg-indigo-50/50' : 'border-gray-200 hover:border-gray-300'">
                            <input type="radio" name="formato_impresion" value="TICKET" x-model="formato" class="sr-only">
                            <div class="w-14 h-20 shrink-0 rounded bg-white border border-gray-300 shadow-sm p-1.5 flex flex-col gap-1">
                                <span class="h-1 w-3/4 mx-auto bg-gray-400 rounded"></span><span class="h-0.5 bg-gray-200"></span>
                                <span class="h-0.5 bg-gray-300"></span><span class="h-0.5 bg-gray-300"></span><span class="h-0.5 bg-gray-300"></span>
                                <span class="h-0.5 bg-gray-200 mt-auto"></span><span class="h-1 w-1/2 ml-auto bg-gray-500 rounded"></span>
                            </div>
                            <div>
                                <p class="font-bold text-gray-800">Ticket 80 mm</p>
                                <p class="text-xs text-gray-500">Impresora térmica de caja. Lo usa la mayoría.</p>
                                <span x-show="formato === 'TICKET'" class="inline-block mt-1 text-[10px] font-bold text-indigo-700"><i class="fas fa-circle-check"></i> SELECCIONADO</span>
                            </div>
                        </label>
                        <label class="relative cursor-pointer rounded-2xl border-2 p-4 flex gap-4 items-center transition"
                               :class="formato === 'A4' ? 'border-indigo-500 bg-indigo-50/50' : 'border-gray-200 hover:border-gray-300'">
                            <input type="radio" name="formato_impresion" value="A4" x-model="formato" class="sr-only">
                            <div class="w-16 h-20 shrink-0 rounded bg-white border border-gray-300 shadow-sm p-1.5 flex flex-col gap-1">
                                <div class="flex justify-between"><span class="h-2 w-5 bg-gray-400 rounded"></span><span class="h-2.5 w-5 border border-gray-400 rounded-sm"></span></div>
                                <span class="h-1.5 bg-gray-100 border border-gray-200 rounded-sm"></span>
                                <span class="h-0.5 bg-gray-300"></span><span class="h-0.5 bg-gray-300"></span><span class="h-0.5 bg-gray-300"></span>
                                <span class="h-1 w-2/5 ml-auto bg-gray-500 rounded mt-auto"></span>
                            </div>
                            <div>
                                <p class="font-bold text-gray-800">Hoja A4</p>
                                <p class="text-xs text-gray-500">Impresora normal o PDF para enviar al cliente.</p>
                                <span x-show="formato === 'A4'" class="inline-block mt-1 text-[10px] font-bold text-indigo-700"><i class="fas fa-circle-check"></i> SELECCIONADO</span>
                            </div>
                        </label>
                        <p class="sm:col-span-2 text-xs text-gray-400"><i class="fas fa-circle-info mr-1"></i>Desde el Panel de ventas siempre puedes ver un comprobante en el otro formato. La impresión directa a la impresora térmica (agente) sigue en ticket.</p>
                    </div>
                </section>

                {{-- Configuración de venta --}}
                <section id="venta" class="bg-white rounded-2xl shadow-sm scroll-mt-4">
                    <header class="flex items-center gap-3 px-5 py-4 border-b border-gray-100">
                        <span class="w-9 h-9 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center"><i class="fas fa-cash-register"></i></span>
                        <div><h3 class="font-bold text-gray-800">Configuración de venta</h3><p class="text-xs text-gray-400">Valores con los que arranca cada venta.</p></div>
                    </header>
                    <div class="p-5 space-y-5">
                        <div>
                            <p class="text-sm font-medium text-gray-600 mb-2">Afectación del IGV de los productos</p>
                            <div class="grid sm:grid-cols-2 gap-3">
                                @foreach (['10' => ['Gravado', 'Cobra IGV (18%)'], '20' => ['Exonerado', 'Sin IGV (Amazonía, etc.)']] as $v => [$t, $d])
                                    <label class="relative cursor-pointer rounded-xl border-2 px-4 py-3 transition" :class="igv === '{{ $v }}' ? 'border-indigo-500 bg-indigo-50/50' : 'border-gray-200 hover:border-gray-300'">
                                        <input type="radio" name="tip_igv_pred" value="{{ $v }}" x-model="igv" class="sr-only">
                                        <span class="font-bold text-gray-800">{{ $v }} · {{ $t }}</span><span class="block text-xs text-gray-500">{{ $d }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-600 mb-2">¿Se puede vender sin stock?</p>
                            <div class="grid sm:grid-cols-3 gap-3">
                                @foreach (\App\Support\ControlStock::NIVELES as $v => [$t, $d])
                                    <label class="relative cursor-pointer rounded-xl border-2 px-4 py-3 transition" :class="stock === '{{ $v }}' ? 'border-indigo-500 bg-indigo-50/50' : 'border-gray-200 hover:border-gray-300'">
                                        <input type="radio" name="control_stock" value="{{ $v }}" x-model="stock" class="sr-only">
                                        <span class="font-bold text-gray-800">{{ $v === 'libre' ? '🟡' : ($v === 'productos' ? '🟢' : '✅') }} {{ $t }}</span><span class="block text-xs text-gray-500 mt-0.5">{{ $d }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-600 mb-2">Comprobante que sale seleccionado al cobrar</p>
                            <div class="grid grid-cols-3 gap-3">
                                @foreach (['13' => ['Nota de venta', 'fa-file-lines'], '03' => ['Boleta', 'fa-file-invoice'], '01' => ['Factura', 'fa-file-invoice-dollar']] as $v => [$t, $i])
                                    <label class="relative cursor-pointer rounded-xl border-2 px-3 py-3 text-center transition" :class="pred === '{{ $v }}' ? 'border-indigo-500 bg-indigo-50/50 text-indigo-700' : 'border-gray-200 text-gray-600 hover:border-gray-300'">
                                        <input type="radio" name="tdocod_pred" value="{{ $v }}" x-model="pred" class="sr-only">
                                        <i class="fas {{ $i }} text-xl"></i><span class="block text-sm font-bold mt-1">{{ $t }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>

        {{-- Barra de guardar fija --}}
        <div class="fixed bottom-0 inset-x-0 z-30 bg-white/90 backdrop-blur border-t border-gray-200">
            <div class="max-w-6xl mx-auto px-4 py-3 flex items-center justify-end gap-3">
                <span class="hidden sm:block mr-auto text-xs text-gray-400">Los cambios de series aplican desde el próximo comprobante.</span>
                <a href="{{ route('sucursales.index') }}" class="px-5 py-2.5 rounded-xl bg-gray-100 text-gray-700 font-semibold hover:bg-gray-200">Cancelar</a>
                <button class="px-6 py-2.5 rounded-xl bg-indigo-600 text-white font-semibold hover:bg-indigo-700 shadow"><i class="fas fa-floppy-disk mr-1"></i> Guardar cambios</button>
            </div>
        </div>
    </form>
@endsection
