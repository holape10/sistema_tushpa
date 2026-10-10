{{--
    Formulario de producto compartido por crear y editar.
    Pantalla ancha: datos a la izquierda, imagen y precios a la derecha (fija al hacer scroll). Celular: una sola columna.
    Variables: $producto (null al crear), $unidades, $categorias, $subcategorias, $itemsParaCombo, $comboActual (editar)
--}}
@php
    $p = $producto ?? null;
    $campo = 'w-full h-11 rounded-xl border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500';
    $tipos = [
        0 => ['🥤', 'Producto', 'Se vende tal cual (gaseosas, abarrotes, snacks).'],
        4 => ['🧅', 'Insumo', 'Materia prima para preparar otros productos. No se vende.'],
        2 => ['🍽️', 'Preparado', 'El plato final que se vende (ej. Ceviche Mixto).'],
        6 => ['🎁', 'Combo', 'Varios productos o preparados a un solo precio.'],
    ];
@endphp

<form action="{{ $p ? route('productos.update', $p->IdProducto) : route('productos.store') }}" method="POST" enctype="multipart/form-data"
      x-data="productoForm()" @invalid.capture="if ($event.target.closest('[data-modal-precios]')) modalPrecios = true"
      class="max-w-7xl mx-auto pb-28">
    @csrf
    @if ($p) @method('PUT') @endif

    {{-- Encabezado: tipo --}}
    <div class="bg-white rounded-2xl shadow-sm p-4 sm:p-5 mb-6">
        @if ($p)
            <div class="flex flex-wrap items-center gap-3">
                <span class="text-3xl">{{ $tipos[(int) $p->promocion][0] ?? '🥤' }}</span>
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Editando {{ strtolower($p->tipo_nombre) }}</p>
                    <h2 class="text-lg font-bold text-gray-800 truncate">{{ $p->pronom }}</h2>
                </div>
                @if (in_array((int) $p->promocion, [2, 8], true) && auth()->user()->esAdmin())
                    <a href="{{ route('recetas.editar', $p->IdProducto) }}" class="h-10 px-4 rounded-xl bg-amber-100 hover:bg-amber-200 text-amber-900 text-sm font-bold flex items-center gap-1.5">🍳 Receta y costo</a>
                @endif
                <label class="text-xs text-gray-500">¿Te equivocaste de tipo?
                    <select name="promocion" x-model="tipo" class="block mt-1 h-10 rounded-xl border-gray-300 text-sm font-semibold">
                        @foreach ($tipos as $valor => [$icono, $nombre])<option value="{{ $valor }}">{{ $icono }} {{ $nombre }}</option>@endforeach
                        @if ((int) $p->promocion === 8)<option value="8">🥗 Entrada</option>@endif
                    </select>
                    @error('promocion')<span class="block mt-1 text-rose-600 font-semibold max-w-xs">{{ $message }}</span>@enderror
                </label>
            </div>
        @else
            <p class="text-sm font-semibold text-gray-700 mb-3">¿Qué vas a registrar?</p>
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                @foreach ($tipos as $valor => [$icono, $nombre, $ayuda])
                    <label class="relative cursor-pointer">
                        <input type="radio" name="promocion" value="{{ $valor }}" x-model="tipo" class="peer sr-only">
                        <div class="h-full border-2 rounded-xl p-3 flex items-start gap-3 border-gray-200 transition
                                    peer-checked:border-indigo-600 peer-checked:bg-indigo-50 peer-focus-visible:ring-2 peer-focus-visible:ring-indigo-300 hover:border-gray-300">
                            <span class="text-2xl leading-none">{{ $icono }}</span>
                            <span>
                                <span class="block text-sm font-bold text-gray-800">{{ $nombre }}</span>
                                <span class="hidden sm:block text-xs text-gray-500 leading-snug mt-0.5">{{ $ayuda }}</span>
                            </span>
                        </div>
                    </label>
                @endforeach
            </div>
        @endif
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">

        {{-- ================= Columna principal ================= --}}
        <div class="lg:col-span-2 space-y-6 min-w-0">

            {{-- Información general --}}
            <section class="bg-white rounded-2xl shadow-sm">
                <header class="px-5 py-4 border-b border-gray-100">
                    <h3 class="font-bold text-gray-800">Información general</h3>
                    <p class="text-xs text-gray-400">Nombre, códigos y clasificación.</p>
                </header>
                <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nombre *</label>
                        <input type="text" name="pronom" value="{{ old('pronom', $p->pronom ?? '') }}" required maxlength="150"
                               class="{{ $campo }} uppercase font-semibold">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Descripción <span class="text-xs font-normal text-gray-400">(opcional · sale en la carta digital, ej.: "Ron, menta, limón, soda")</span></label>
                        <input type="text" name="descripcion" value="{{ old('descripcion', $p->descripcion ?? '') }}" maxlength="255"
                               class="{{ $campo }}">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Código interno</label>
                        @if ($p)
                            <input type="text" value="{{ $p->procod }}" disabled class="w-full h-11 rounded-xl border-gray-200 bg-gray-100 text-sm text-gray-500">
                        @else
                            <input type="text" name="procod" value="{{ old('procod') }}" maxlength="20" placeholder="Se genera solo si lo dejas vacío" class="{{ $campo }}">
                        @endif
                    </div>

                    @include('empresas.productos.partials.codigo_barra')

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Unidad de medida *</label>
                        <select name="umecod" x-model="umecod" required class="{{ $campo }}">
                            @foreach ($unidades as $u)
                                <option value="{{ $u->umecod }}">{{ $u->umenom }}</option>
                            @endforeach
                        </select>
                        <p class="text-xs text-gray-400 mt-1">Unidad predeterminada de venta y stock.</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Categoría</label>
                        <select name="cat_id" class="{{ $campo }}">
                            <option value="">— Sin categoría —</option>
                            @foreach ($categorias as $c)
                                <option value="{{ $c->cat_id }}" @selected(old('cat_id', $p->cat_id ?? null) == $c->cat_id)>{{ $c->cat_nom }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="sm:col-span-2 sm:w-1/2 sm:pr-2.5">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Subcategoría</label>
                        <select name="subcat_id" class="{{ $campo }}">
                            <option value="">— Sin subcategoría —</option>
                            @foreach ($subcategorias as $s)
                                <option value="{{ $s->subcat_id }}" @selected(old('subcat_id', $p->subcat_id ?? null) == $s->subcat_id)>{{ $s->subcat_nom }}</option>
                            @endforeach
                        </select>
                    </div>

                    @include('empresas.productos.partials.equivalencia')
                </div>
            </section>

            @include('empresas.productos.partials.presentaciones')

            {{-- Contenido del combo --}}
            <section class="bg-white rounded-2xl shadow-sm" x-show="tipo == '6'" x-cloak>
                <header class="px-5 py-4 border-b border-gray-100">
                    <h3 class="font-bold text-gray-800">¿Qué incluye este combo?</h3>
                    <p class="text-xs text-gray-400">Escribe la cantidad de cada uno; deja en 0 los que no van.</p>
                </header>
                <div class="p-5" x-data="{ q: '', soloElegidos: false, norm(t) { return String(t).normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase(); } }">
                    <div class="flex flex-wrap items-center gap-3 mb-3">
                        <input type="search" x-model="q" placeholder="🔍 Buscar producto o plato…" class="flex-1 min-w-48 h-10 rounded-xl border-gray-300 text-sm">
                        <label class="text-sm text-gray-600 flex items-center gap-1.5"><input type="checkbox" x-model="soloElegidos" class="rounded"> Ver solo los elegidos</label>
                    </div>
                    <div class="grid sm:grid-cols-2 gap-2 max-h-96 overflow-y-auto">
                        @foreach ($itemsParaCombo as $item)
                            <label class="flex items-center justify-between gap-3 text-sm rounded-xl border border-gray-100 px-3 py-2 hover:bg-gray-50"
                                   x-data="{ n: @js($item->pronom) }" x-show="(!q.trim() || norm(n).includes(norm(q.trim()))) && (!soloElegidos || Number($el.querySelector('input').value) > 0)">
                                <span class="text-gray-700 min-w-0">{{ $item->pronom }}
                                    <span class="block text-xs text-gray-400">{{ $item->promocion == 2 ? 'Preparado' : 'Producto' }}</span>
                                </span>
                                <input type="number" min="0" step="1" placeholder="0" name="combo_items[{{ $item->IdProducto }}]"
                                       value="{{ old('combo_items.' . $item->IdProducto, isset($comboActual) ? ($comboActual[$item->IdProducto] ?? '') : '') }}"
                                       class="w-20 h-10 rounded-lg border-gray-300 text-sm text-center focus:border-indigo-500 focus:ring-indigo-500">
                            </label>
                        @endforeach
                    </div>
                    @if ($itemsParaCombo->isEmpty())
                        <p class="text-sm text-gray-400">Primero registra al menos un Producto o Preparado para poder armar combos.</p>
                    @endif
                </div>
            </section>

            @include('empresas.productos.partials.menu_trio')
        </div>

        {{-- ================= Columna lateral ================= --}}
        <aside class="space-y-6 lg:sticky lg:top-4 min-w-0">
            @include('empresas.productos.partials.imagen')

            <section class="bg-white rounded-2xl shadow-sm">
                <header class="px-5 py-4 border-b border-gray-100">
                    <h3 class="font-bold text-gray-800">Precio y costo</h3>
                </header>
                <div class="p-5 space-y-4">
                    @include('empresas.productos.partials.precio')

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Costo</label>
                        <div class="flex">
                            <span class="inline-flex items-center px-3 rounded-l-xl border border-r-0 border-gray-300 bg-gray-50 text-gray-500 text-sm">S/</span>
                            <input type="number" step="0.01" min="0" name="costo" x-model="costo"
                                   class="flex-1 min-w-0 h-11 rounded-r-xl border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                    </div>

                    <div x-show="tipo != '4' && precio > 0 && costo > 0" x-cloak class="rounded-xl px-3 py-2 text-sm"
                         :class="precio - costo >= 0 ? 'bg-emerald-50 text-emerald-800' : 'bg-rose-50 text-rose-700'">
                        Ganancia por unidad: <strong x-text="soles(precio - costo)"></strong>
                        <span x-text="'(' + Math.round((precio - costo) / precio * 100) + '%)'"></span>
                    </div>

                    @if ($p)
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Estado</label>
                            <select name="proest" class="{{ $campo }}">
                                <option value="Activo" @selected(old('proest', $p->proest) == 'Activo')>Activo — se vende en las cajas</option>
                                <option value="Inactivo" @selected(old('proest', $p->proest) == 'Inactivo')>Inactivo — oculto en las cajas</option>
                            </select>
                        </div>
                    @endif

                    <label x-show="tipo == '0'" x-cloak class="flex items-start gap-3 p-3 rounded-xl border border-orange-200 bg-orange-50 cursor-pointer">
                        <input type="checkbox" name="es_combustible" value="1" @checked(old('es_combustible', $p->es_combustible ?? false))
                               class="mt-0.5 rounded border-gray-300 text-orange-600 focus:ring-orange-500">
                        <span class="text-sm"><span class="font-semibold text-orange-800">⛽ Es combustible</span>
                            <span class="block text-xs text-orange-700">Sale como botón grande en el PV Grifo y se vende por importe (S/) o por galones. Usa la unidad Galón.</span></span>
                    </label>

                    <label x-show="tipo == '0' || tipo == '4'" x-cloak class="flex items-start gap-3 p-3 rounded-xl border border-teal-200 bg-teal-50 cursor-pointer">
                        <input type="checkbox" name="control_lote" value="1" @checked(old('control_lote', $p->control_lote ?? false))
                               class="mt-0.5 rounded border-gray-300 text-teal-600 focus:ring-teal-500">
                        <span class="text-sm"><span class="font-semibold text-teal-800">Controlar lote y vencimiento</span>
                            <span class="block text-xs text-teal-700">Farmacias: al comprar pide lote y vencimiento; al vender sale primero el que vence antes.</span></span>
                    </label>
                </div>
            </section>

            {{-- Cuentas contables de este producto para el asiento de venta (CONCAR). Vacías = las generales de CONCAR --}}
            <section class="bg-white rounded-2xl shadow-sm">
                <header class="px-5 py-4 border-b border-gray-100">
                    <h3 class="font-bold text-gray-800">Contabilidad <span class="text-xs font-normal text-gray-400">(opcional)</span></h3>
                    <p class="text-xs text-gray-500 mt-0.5">Cuentas para el asiento de venta en CONCAR. Si las dejas vacías se usan las de Contabilidad &gt; CONCAR.</p>
                </header>
                <div class="p-5 grid grid-cols-2 gap-3">
                    <label class="block text-sm font-medium text-gray-700">Debe <span class="text-xs text-gray-400">(12 / 14)</span>
                        <input name="debe" value="{{ old('debe', $p->debe ?? '') }}" maxlength="12" placeholder="121201"
                               class="{{ $campo }} font-mono mt-1"></label>
                    <label class="block text-sm font-medium text-gray-700">Haber <span class="text-xs text-gray-400">(70 / 75)</span>
                        <input name="haber" value="{{ old('haber', $p->haber ?? '') }}" maxlength="12" placeholder="704101"
                               class="{{ $campo }} font-mono mt-1"></label>
                </div>
            </section>
        </aside>
    </div>

    @include('empresas.productos.partials.precios_modal')

    {{-- Barra de acciones fija --}}
    <div class="fixed bottom-0 inset-x-0 z-40 bg-white/95 backdrop-blur border-t border-gray-200" style="padding-bottom: env(safe-area-inset-bottom)">
        <div class="max-w-7xl mx-auto px-4 py-3 flex items-center justify-end gap-3">
            <a href="{{ route('productos.index') }}" class="px-5 h-11 inline-flex items-center rounded-xl text-sm font-semibold text-gray-600 hover:bg-gray-100">Cancelar</a>
            <button class="px-6 h-11 rounded-xl bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700 shadow-sm">
                {{ $p ? 'Guardar cambios' : 'Guardar producto' }}
            </button>
        </div>
    </div>
</form>
