@extends('admin.layout')
@section('title', 'Clientes')
@section('ancho', 'max-w-screen-2xl')

@section('content')
    @if ($c = session('creado'))
        {{-- Datos de acceso: la contraseña solo se muestra esta vez --}}
        <div class="mb-6 rounded-2xl bg-emerald-50 border border-emerald-200 p-5">
            <p class="font-bold text-emerald-800">✔ Se creó {{ $c['razon_social'] }}</p>
            <p class="text-sm text-emerald-700 mb-3">Datos de acceso para entregar al cliente:</p>
            <dl class="grid sm:grid-cols-2 gap-x-6 gap-y-1 text-sm">
                <dt class="text-slate-500">Acceso</dt><dd><a href="{{ $c['url'] }}" target="_blank" class="font-semibold text-indigo-700 underline">{{ $c['url'] }}</a></dd>
                <dt class="text-slate-500">Usuario</dt><dd class="font-mono font-semibold">{{ $c['usuario'] }}</dd>
                <dt class="text-slate-500">Contraseña</dt><dd class="font-mono font-semibold">{{ $c['password'] }}</dd>
                <dt class="text-slate-500">Base de datos</dt><dd class="font-mono">{{ $c['base'] }}</dd>
            </dl>
            <p class="mt-3 text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
                🔒 El candado https de este subdominio se activa solo en <strong>1 a 2 minutos</strong>.
                Si el navegador dice "La conexión no es privada", espera un momento o entra mientras tanto por
                <a href="{{ preg_replace('#^https://#', 'http://', $c['url']) }}" target="_blank" class="font-semibold underline">{{ preg_replace('#^https://#', 'http://', $c['url']) }}</a>.
            </p>
        </div>
    @endif

    <div id="cabecera-clientes" class="flex flex-wrap items-center gap-3 mb-4">
        <h1 class="text-xl font-bold">Clientes</h1>
        <div data-totales class="flex gap-2 text-xs font-semibold">
            <span class="px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-700">{{ $totales['activos'] }} activos</span>
            @if ($totales['suspendidos'])<span class="px-2.5 py-1 rounded-full bg-rose-100 text-rose-700">{{ $totales['suspendidos'] }} suspendidos</span>@endif
            @if ($totales['por_vencer'])<span class="px-2.5 py-1 rounded-full bg-amber-100 text-amber-700">{{ $totales['por_vencer'] }} vencen en 7 días</span>@endif
            <span class="px-2.5 py-1 rounded-full bg-indigo-100 text-indigo-700">Ingreso mensual S/ {{ number_format($totales['ingreso'], 2) }}</span>
        </div>
        {{-- Filtros: prueba gratis (registro desde la web) y las suspendidas hace tiempo, para eliminar --}}
        <div data-totales class="w-full flex flex-wrap gap-2 text-xs font-semibold">
            @foreach (['' => 'Todos', 'prueba' => '🎁 En prueba ('.$totales['en_prueba'].')', 'web' => '🌐 Desde la web',
                'eliminar' => '🗑 Para eliminar ('.$totales['para_eliminar'].')', 'eliminados' => 'Eliminados'] as $valor => $texto)
                <a href="{{ route('admin.clientes.index', array_filter(['filtro' => $valor, 'q' => $q, 'usuario' => $usuario])) }}"
                   class="px-3 py-1.5 rounded-full border {{ $filtro === $valor ? 'bg-slate-900 text-white border-slate-900' : 'bg-white text-slate-600 border-slate-200 hover:border-slate-400' }}">{{ $texto }}</a>
            @endforeach
        </div>
        {{-- Busca mientras escribes (sin recargar la página) y, para el dueño, ver solo las empresas de un usuario --}}
        <form class="ml-auto flex flex-wrap gap-2" method="GET" x-data="buscadorClientes()" @submit.prevent="buscar()">
            @if ($usuarios->isNotEmpty())
                <select name="usuario" @change="buscar()" class="rounded-xl border-slate-300 text-sm h-10">
                    <option value="">Todos los usuarios</option>
                    @foreach ($usuarios as $id => $nombre)
                        <option value="{{ $id }}" @selected($usuario === (int) $id)>{{ $nombre }} ({{ $porUsuario[$id] ?? 0 }})</option>
                    @endforeach
                </select>
            @endif
            <div class="relative">
                <input type="search" name="q" value="{{ $q }}" placeholder="RUC o nombre" autocomplete="off" @input.debounce.300ms="buscar()"
                       class="rounded-xl border-slate-300 text-sm h-10 w-56 sm:w-64 pr-8">
                <span x-show="cargando" x-cloak class="absolute right-3 top-2.5 text-slate-400 text-sm animate-spin">◌</span>
            </div>
            @if ($filtro)<input type="hidden" name="filtro" value="{{ $filtro }}">@endif
        </form>
        <a href="{{ route('admin.clientes.create') }}" class="px-4 h-10 inline-flex items-center rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold">+ Nuevo cliente</a>
    </div>

    <div id="lista-clientes">
    <div class="bg-white rounded-2xl shadow-sm overflow-x-auto">
        <table class="w-full text-sm tabla-movil">
            <thead class="bg-slate-50 text-slate-500 text-xs uppercase">
                <tr>
                    <th class="text-left px-4 py-3">Empresa</th>
                    <th class="text-left px-4 py-3">Acceso</th>
                    <th class="text-left px-4 py-3">Plan</th>
                    <th class="text-left px-4 py-3">Vence</th>
                    <th class="text-left px-4 py-3">Estado</th>
                    <th class="text-left px-4 py-3">Comprobantes</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($clientes as $cl)
                    <tr class="{{ $cl->activo() ? '' : 'bg-rose-50/50' }}">
                        <td class="px-4 py-3 min-w-56" data-label="Empresa">
                            <p class="font-semibold">{{ $cl->nombre_comercial ?: $cl->razon_social }}
                                @if ($cl->rubro)<span class="ml-1 px-1.5 py-0.5 rounded text-[10px] font-bold bg-indigo-50 text-indigo-700">{{ \App\Support\Rubros::RUBROS[$cl->rubro]['nombre'] ?? $cl->rubro }}</span>@endif</p>
                            <p class="text-xs text-slate-400">{{ $cl->ruc }} · {{ $cl->razon_social }}</p>
                        </td>
                        <td class="px-4 py-3" data-label="Acceso">
                            <a href="{{ $cl->url() }}" target="_blank" class="text-indigo-700 hover:underline">{{ $cl->host() }}</a>
                            <div class="mt-1 flex items-center gap-2 text-xs">
                                @if ($cl->tieneHttps())
                                    <span class="text-emerald-600 font-semibold">🔒 https activo</span>
                                @else
                                    <span class="text-amber-600 font-semibold">⚠ sin https</span>
                                    <form method="POST" action="{{ route('admin.clientes.https', $cl) }}" class="inline">
                                        @csrf
                                        <button class="px-2 py-0.5 rounded-lg bg-amber-500 hover:bg-amber-600 text-white font-semibold">Activar https</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                        <td class="px-4 py-3" data-label="Plan">
                            {{ $cl->plan ?: '—' }}
                            @if ($cl->planContratado)<span class="block text-xs text-slate-400">S/ {{ number_format($cl->planContratado->precio, 2) }} /mes</span>@endif
                        </td>
                        <td class="px-4 py-3" data-label="Vence">
                            @if ($cl->vence_el)
                                <span class="{{ $cl->vence_el->isPast() ? 'text-rose-600 font-semibold' : ($cl->vence_el->lte(now()->addDays(7)) ? 'text-amber-600 font-semibold' : '') }}">{{ $cl->vence_el->format('d/m/Y') }}</span>
                            @else — @endif
                        </td>
                        <td class="px-4 py-3" data-label="Estado">
                            @if ($cl->estado === 'ELIMINADO')
                                <span class="px-2 py-0.5 rounded-full bg-slate-200 text-slate-600 text-xs font-semibold" title="{{ $cl->motivo_suspension }}">Eliminado</span>
                            @elseif ($cl->activo() && $cl->enPrueba())
                                @php $diasPrueba = $cl->diasPrueba(); @endphp
                                <span class="px-2 py-0.5 rounded-full text-xs font-semibold {{ $diasPrueba <= 3 ? 'bg-amber-100 text-amber-700' : 'bg-indigo-100 text-indigo-700' }}"
                                      title="Prueba gratis hasta el {{ $cl->prueba_hasta->format('d/m/Y') }}">🎁 Prueba · {{ max(0, $diasPrueba) }} {{ $diasPrueba === 1 ? 'día' : 'días' }}</span>
                            @elseif ($cl->activo())
                                <span class="px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 text-xs font-semibold">Activo</span>
                            @else
                                <span class="px-2 py-0.5 rounded-full bg-rose-100 text-rose-700 text-xs font-semibold" title="{{ $cl->motivo_suspension }}">Suspendido</span>
                                @if ($cl->suspendido_el && $cl->suspendido_el->toDateString() <= $limiteEliminar)
                                    <span class="block mt-1 text-[11px] font-semibold text-rose-600">desde {{ $cl->suspendido_el->format('d/m/Y') }} · para eliminar</span>
                                @elseif ($cl->suspendido_el)
                                    <span class="block mt-1 text-[11px] text-slate-400">desde {{ $cl->suspendido_el->format('d/m/Y') }}</span>
                                @endif
                            @endif
                            @if ($cl->origen === 'web')<span class="block mt-1 text-[11px] text-slate-400">🌐 registro web</span>@endif
                        </td>
                        <td class="px-4 py-3" data-label="Comprobantes">
                            @php $cp = $comprobantes[$cl->id] ?? ['tipos' => [], 'total' => 0, 'mes' => 0]; @endphp
                            @if ($cp['total'])
                                <p class="font-semibold">{{ number_format($cp['total']) }} <span class="text-xs font-normal text-slate-400">· {{ number_format($cp['mes']) }} este mes</span></p>
                                <p class="text-xs text-slate-500 whitespace-nowrap">
                                    @foreach ($cp['tipos'] as $t => $n)<span class="mr-1.5" title="{{ ['01' => 'Facturas', '03' => 'Boletas', '07' => 'Notas de crédito', '08' => 'Notas de débito', '13' => 'Notas de venta'][$t] ?? 'Tipo ' . $t }}">{{ $t }}: {{ number_format($n) }}</span>@endforeach
                                </p>
                            @else <span class="text-slate-400">—</span> @endif
                            @if ($usuarios->isNotEmpty() && $cl->creado_por)<p class="text-[11px] text-slate-400 mt-0.5">De: {{ $usuarios[$cl->creado_por] ?? '—' }}</p>@endif
                        </td>
                        <td class="px-4 py-3 text-right celda-acciones">
                            <div class="flex flex-wrap justify-start md:justify-end items-center gap-y-1.5 md:max-w-64 md:ml-auto">
                            @if ($cl->contacto_telefono && $cl->estado !== 'ELIMINADO')
                                <a href="https://wa.me/51{{ preg_replace('/\D/', '', $cl->contacto_telefono) }}?text={{ rawurlencode('Hola '.($cl->contacto_nombre ?: '').', te saludamos de TUSHPA por tu sistema '.($cl->nombre_comercial ?: $cl->razon_social).'.') }}"
                                   target="_blank" rel="noopener" class="text-xs font-semibold text-emerald-600 hover:text-emerald-700 mr-2" title="Escribirle por WhatsApp">💬</a>
                            @endif
                            @if ($cl->activo() && $cl->enPrueba())
                                <form method="POST" action="{{ route('admin.clientes.pagado', $cl) }}" class="inline" onsubmit="return confirm('¿Ya pagó? Sale de la prueba y vence en un mes.')">
                                    @csrf
                                    <button class="text-xs font-semibold text-indigo-600 hover:text-indigo-800 mr-2">Ya pagó</button>
                                </form>
                            @endif
                            @if ($cl->estado === 'ELIMINADO')
                                <span class="text-xs text-slate-400">Eliminado</span>
                            @else
                            <a href="{{ route('admin.clientes.edit', $cl) }}" class="text-xs font-semibold text-slate-600 hover:text-indigo-700 mr-2">Editar</a>
                            @if (auth('superadmin')->user()->es_dueno)
                                <a href="{{ route('admin.anuncios.index', ['empresa' => $cl->id]) }}" class="text-xs font-semibold text-slate-600 hover:text-indigo-700 mr-2" title="Mostrarle un mensaje arriba de su sistema">Anunciar</a>
                            @endif
                            <a href="{{ route('admin.clientes.accesos', $cl->id) }}" class="text-xs font-semibold text-slate-600 hover:text-indigo-700 mr-2" title="Ver sus usuarios y ponerles una contraseña nueva">Contraseñas</a>
                            <a href="{{ route('admin.clientes.comprobantes', $cl->id) }}" class="text-xs font-semibold text-slate-600 hover:text-indigo-700 mr-2" title="Pendientes y rechazados de SUNAT">Comprobantes</a>
                            @if ($cl->activo())
                                <button type="button" x-data class="text-xs font-semibold text-amber-600 hover:text-amber-700 mr-2"
                                        @click="$dispatch('advertir', @js(['id' => $cl->id, 'nombre' => $cl->nombre_comercial ?: $cl->razon_social, 'telefono' => $cl->contacto_telefono,
                                            'mensaje' => $cl->aviso_mensaje, 'fecha' => $cl->aviso_fecha ? \Illuminate\Support\Carbon::parse($cl->aviso_fecha)->toDateString() : null, 'suspender' => (bool) $cl->aviso_suspender,
                                            'accion' => route('admin.clientes.aviso', $cl), 'quitar' => route('admin.clientes.aviso.quitar', $cl)]))">{{ $cl->aviso_mensaje ? '⚠ Advertido' : 'Advertir' }}</button>
                            @endif
                            <form method="POST" action="{{ route('admin.clientes.estado', $cl) }}" class="inline"
                                  onsubmit="{{ $cl->activo() ? "const m = prompt('Motivo de la suspensión (lo verá el cliente):', 'Falta de pago'); if (m === null) return false; this.motivo.value = m;" : "return confirm('¿Reactivar este cliente?')" }}">
                                @csrf
                                <input type="hidden" name="motivo">
                                <button class="text-xs font-semibold {{ $cl->activo() ? 'text-rose-600' : 'text-emerald-600' }}">{{ $cl->activo() ? 'Suspender' : 'Reactivar' }}</button>
                            </form>
                            {{-- Dejar en cero: borra los movimientos de prueba antes de empezar a trabajar (con la misma advertencia y el RUC para confirmar) --}}
                            <a href="{{ route('admin.clientes.reiniciar', $cl) }}" class="ml-2 text-xs font-semibold text-slate-500 hover:text-amber-700">Dejar en cero</a>
                            {{-- Eliminar (solo el dueño, solo suspendidas): se saca un respaldo antes de borrar la base --}}
                            @if (! $cl->activo() && auth('superadmin')->user()->es_dueno)
                                <form method="POST" action="{{ route('admin.clientes.eliminar', $cl) }}" class="inline"
                                      data-aviso="{{ 'Se borrará la base de datos de '.$cl->razon_social.' (antes se guarda un respaldo). Para confirmar escribe su RUC: '.$cl->ruc }}"
                                      onsubmit="const r = prompt(this.dataset.aviso); if (r === null) return false; this.confirmacion.value = r;">
                                    @csrf
                                    <input type="hidden" name="confirmacion">
                                    <button class="ml-2 text-xs font-semibold text-slate-500 hover:text-rose-700">Eliminar</button>
                                </form>
                            @endif
                            @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-10 text-center text-slate-400">Aún no hay clientes. Crea el primero con “+ Nuevo cliente”.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $clientes->links() }}</div>
    </div>

    {{-- Advertencia antes de suspender --}}
    <div x-data="advertencia()" @advertir.window="abrir($event.detail)" x-show="c" x-cloak class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
        <form method="POST" :action="c?.accion" class="bg-white rounded-2xl shadow-2xl w-full max-w-lg p-5 space-y-3" @click.outside="c = null">
            @csrf
            <h2 class="text-lg font-bold">⚠ Advertir a <span x-text="c?.nombre"></span></h2>
            <p class="text-sm text-slate-500">Lo verá en todas las pantallas de su sistema hasta que pague o quites la advertencia.</p>
            <label class="block text-sm font-semibold text-slate-600">Fecha límite de pago
                <input type="date" name="aviso_fecha" x-model="fecha" @change="armar()" min="{{ now()->toDateString() }}" class="mt-1 w-full rounded-xl border-slate-300"></label>
            <label class="block text-sm font-semibold text-slate-600">Mensaje
                <textarea name="aviso_mensaje" x-model="mensaje" rows="5" required minlength="10" maxlength="600" class="mt-1 w-full rounded-xl border-slate-300 text-sm"></textarea></label>
            <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="aviso_suspender" value="1" x-model="suspender" class="mt-0.5 rounded border-slate-300">
                <span>Suspender automáticamente si no paga hasta esa fecha</span></label>
            <div class="flex flex-wrap gap-2 pt-1">
                <button class="px-4 h-10 rounded-xl bg-amber-500 text-white text-sm font-bold">Enviar advertencia</button>
                <a x-show="c?.telefono" :href="'https://wa.me/' + tel() + '?text=' + encodeURIComponent(mensaje)" target="_blank" rel="noopener"
                   class="px-4 h-10 inline-flex items-center rounded-xl bg-green-600 text-white text-sm font-bold">También por WhatsApp</a>
                <button type="button" x-show="c?.mensaje" @click="quitar()" class="px-4 h-10 rounded-xl bg-slate-100 text-sm font-semibold">Quitar advertencia</button>
                <button type="button" @click="c = null" class="px-4 h-10 rounded-xl text-sm text-slate-500">Cancelar</button>
            </div>
        </form>
        <form x-ref="quitar" method="POST" :action="c?.quitar" class="hidden">@csrf</form>
    </div>
    <script>
        function buscadorClientes() {
            let peticion = 0;
            return {
                cargando: false,
                async buscar() {
                    const params = new URLSearchParams(new FormData(this.$root));
                    [...params.keys()].filter(k => !params.get(k)).forEach(k => params.delete(k));
                    const url = location.pathname + (params.toString() ? '?' + params : '');
                    const yo = ++peticion;
                    this.cargando = true;
                    try {
                        const html = await (await fetch(url, { headers: { Accept: 'text/html' } })).text();
                        if (yo !== peticion) return;
                        const nuevo = new DOMParser().parseFromString(html, 'text/html');
                        document.getElementById('lista-clientes').innerHTML = nuevo.getElementById('lista-clientes').innerHTML;
                        // Con el usuario también cambian los totales y los filtros
                        const cab = document.getElementById('cabecera-clientes'), cabNueva = nuevo.getElementById('cabecera-clientes');
                        if (cab && cabNueva) cab.querySelectorAll('[data-totales]').forEach((el, i) => { el.innerHTML = cabNueva.querySelectorAll('[data-totales]')[i]?.innerHTML ?? el.innerHTML; });
                        history.replaceState(null, '', url);
                    } catch (e) {
                        location.href = url;
                    } finally {
                        if (yo === peticion) this.cargando = false;
                    }
                },
            };
        }

        function advertencia() {
            return {
                c: null, fecha: '', mensaje: '', suspender: true,
                abrir(c) {
                    this.c = c;
                    const d = new Date(); d.setDate(d.getDate() + 5);
                    this.fecha = c.fecha || d.toISOString().slice(0, 10);
                    this.suspender = c.mensaje ? c.suspender : true;
                    if (c.mensaje) this.mensaje = c.mensaje; else this.armar();
                },
                fechaTexto() { const [a, m, d] = this.fecha.split('-'); return `${d}/${m}/${a}`; },
                armar() {
                    if (this.c?.mensaje) return;
                    this.mensaje = `Estimado cliente ${this.c.nombre}: tiene pendiente el pago del servicio TUSHPA de este mes. `
                        + `Si no se regulariza hasta el ${this.fechaTexto()}, el sistema se suspenderá automáticamente. `
                        + 'Si ya pagó, envíenos su comprobante por WhatsApp. Gracias.';
                },
                tel() { const n = (this.c?.telefono || '').replace(/\D/g, ''); return n.length === 9 ? '51' + n : n; },
                quitar() { if (confirm('¿Quitar la advertencia?')) this.$refs.quitar.submit(); },
            };
        }
    </script>
@endsection
