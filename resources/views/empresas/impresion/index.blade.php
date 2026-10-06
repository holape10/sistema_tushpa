@extends('layouts.app')
@section('title', 'Impresoras e impresión directa')
@section('content')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    @include('empresas.partials.alert')

    @php
        $in = 'block w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500';
        $estados = [0 => ['PENDIENTE', 'bg-gray-100 text-gray-700'], 1 => ['ENVIADO', 'bg-blue-100 text-blue-700'],
                    2 => ['IMPRESO', 'bg-green-100 text-green-700'], 9 => ['ERROR', 'bg-red-100 text-red-700']];
    @endphp

    <div x-data="{ form: null, nueva() { this.form = { id: null, descripcion: '', tip_conex_imp: 'COMPARTIDO', ruta: '', columnas: 42, predeterminado: false, abrir_cajon: false, activo: true }; },
                   editar(i) { this.form = Object.assign({}, i, { predeterminado: !!+i.predeterminado, abrir_cajon: !!+i.abrir_cajon, activo: !!+i.activo }); } }">

        {{-- Agente --}}
        <div class="bg-white rounded-2xl shadow-sm p-5 mb-5">
            <div class="flex flex-wrap items-center gap-4">
                <div class="w-12 h-12 rounded-xl flex items-center justify-center text-xl {{ $conectado ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-600' }}">
                    <i class="fas fa-print"></i>
                </div>
                <div class="flex-1 min-w-60">
                    <p class="font-bold text-gray-800">Agente de impresión:
                        <span class="{{ $conectado ? 'text-green-600' : 'text-red-600' }}">{{ $conectado ? 'CONECTADO' : 'DESCONECTADO' }}</span></p>
                    <p class="text-sm text-gray-500">
                        @if ($negocio->impresion_contacto)
                            Último contacto: {{ \Carbon\Carbon::parse($negocio->impresion_contacto)->locale('es')->diffForHumans() }}.
                        @else
                            Aún no se ha conectado ningún agente.
                        @endif
                        Mientras esté desconectado, los comprobantes y precuentas se imprimen con el navegador (con vista previa).
                    </p>
                </div>
                <form method="POST" action="{{ route('impresion.agente') }}" class="flex flex-wrap gap-2"
                      onsubmit="return confirm('Se generará una clave nueva para esta sucursal. Si ya tienes el agente instalado, tendrás que poner la clave nueva en su config.ini. ¿Continuar?')">
                    @csrf
                    <button name="modo" value="completo" class="px-4 py-2 rounded-xl bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700"
                            title="ZIP con worker.php, config.ini, iniciar.bat, invisible.vbs y LEEME.txt"><i class="fas fa-download"></i> Descargar agente (ZIP)</button>
                    <button name="modo" value="clave" class="px-4 py-2 rounded-xl bg-white border border-indigo-300 text-indigo-700 text-sm font-semibold hover:bg-indigo-50"
                            title="Para una PC que ya tiene el agente: el bloque que se agrega a su config.ini"><i class="fas fa-key"></i> Solo la clave</button>
                </form>
            </div>
            <details class="mt-4 text-sm text-gray-600">
                <summary class="cursor-pointer font-semibold text-indigo-700">¿Cómo instalarlo en la PC de las impresoras?</summary>
                <ol class="list-decimal list-inside mt-2 space-y-1">
                    <li>Descarga el <strong>ZIP</strong> y descomprímelo en una carpeta, por ejemplo <code class="bg-gray-100 px-1 rounded">C:\sistema</code>.</li>
                    <li>En esa carpeta debe estar <code class="bg-gray-100 px-1 rounded">php.exe</code> (PHP 7 u 8) y, para comprobantes A4, <code class="bg-gray-100 px-1 rounded">sumatrapdf.exe</code>
                        (<a href="https://www.sumatrapdfreader.org/download-free-pdf-viewer" target="_blank" rel="noopener" class="text-indigo-700 underline">descarga oficial</a>, versión portable).</li>
                    <li>Doble clic en <code class="bg-gray-100 px-1 rounded">iniciar.bat</code> para probar. Para que arranque solo: <em>Win+R</em> &gt; <code class="bg-gray-100 px-1 rounded">shell:startup</code> y pega un acceso directo a <code class="bg-gray-100 px-1 rounded">invisible.vbs</code>.</li>
                    <li><strong>¿La PC ya tiene el agente</strong> (de otra empresa o del sistema antiguo)? Usa <strong>Solo la clave</strong> y pega ese bloque al final de su <code class="bg-gray-100 px-1 rounded">config.ini</code>. Una sola PC puede imprimir para varias empresas.</li>
                    <li>Impresoras USB: compártelas en Windows (<em>Propiedades &gt; Compartir</em>) con un nombre corto (ej. <strong>CAJA</strong>). De red: su IP (ej. <strong>192.168.1.50</strong>). A4: el nombre exacto de la impresora en Windows.</li>
                </ol>
                <p class="mt-2">El agente revisa cada 2 segundos y deja todo anotado en <code class="bg-gray-100 px-1 rounded">agente.log</code>. Si la impresora de caja es <strong>A4</strong>, los comprobantes salen en PDF A4; si es ticketera, en ticket.</p>
            </details>
        </div>

        {{-- Impresoras --}}
        <div class="bg-white rounded-2xl shadow-sm overflow-hidden mb-5">
            <div class="flex items-center justify-between px-5 py-3 border-b">
                <h3 class="font-bold text-gray-800">Impresoras</h3>
                <button type="button" @click="nueva()" class="px-4 py-1.5 rounded-xl bg-green-600 text-white text-sm font-semibold hover:bg-green-700">+ Nueva impresora</button>
            </div>
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs uppercase"><tr>
                    <th class="px-4 py-2 text-left">Nombre</th><th class="px-4 py-2 text-left">Conexión</th><th class="px-4 py-2 text-left">Ruta / IP</th>
                    <th class="px-4 py-2 text-center">Papel</th><th class="px-4 py-2 text-center">Caja por defecto</th><th class="px-4 py-2 text-center">Estado</th><th class="px-4 py-2"></th></tr></thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($impresoras as $i)
                        <tr>
                            <td class="px-4 py-2 font-semibold text-gray-700">{{ $i->descripcion }} @if ($i->abrir_cajon)<span class="text-xs text-gray-400">(abre cajón)</span>@endif</td>
                            <td class="px-4 py-2">{{ ['RED' => 'Red (IP)', 'WINDOWS' => 'A4 / PDF (Windows)'][$i->tip_conex_imp] ?? 'Compartida Windows' }}</td>
                            <td class="px-4 py-2 font-mono text-xs">{{ $i->ruta }}</td>
                            <td class="px-4 py-2 text-center">@if ($i->tip_conex_imp === 'WINDOWS') A4 @else {{ $i->columnas == 32 ? '58 mm' : '80 mm' }} <span class="text-xs text-gray-400">({{ $i->columnas }} car.)</span> @endif</td>
                            <td class="px-4 py-2 text-center">{{ $i->predeterminado ? '✅' : '' }}</td>
                            <td class="px-4 py-2 text-center">{{ $i->activo ? 'Activa' : 'Inactiva' }}</td>
                            <td class="px-4 py-2 text-right whitespace-nowrap space-x-2">
                                <form method="POST" action="{{ route('impresion.prueba', $i->Id) }}" class="inline">@csrf<button class="text-indigo-600 hover:underline"><i class="fas fa-print"></i> Prueba</button></form>
                                <button type="button" @click='editar(@json($i))' class="text-indigo-600 hover:underline">Editar</button>
                                <form method="POST" action="{{ route('impresion.eliminar', $i->Id) }}" class="inline" onsubmit="return confirm('¿Eliminar la impresora {{ $i->descripcion }}?')">
                                    @csrf @method('DELETE')<button class="text-red-600 hover:underline">Eliminar</button></form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-8 text-center text-gray-400">Aún no hay impresoras. Agrega la de caja y las de cocina/bar.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Asignaciones --}}
        @if ($impresoras->isNotEmpty())
            <form method="POST" action="{{ route('impresion.asignaciones') }}" class="grid lg:grid-cols-2 gap-5 mb-5">
                @csrf
                <div class="bg-white rounded-2xl shadow-sm p-5">
                    <h3 class="font-bold text-gray-800">Cocina / bar: ¿dónde sale cada categoría?</h3>
                    <p class="text-xs text-gray-400 mb-3">Al enviar una comanda, cada producto se imprime en la impresora de su categoría. Sin impresora = no se imprime.</p>
                    <div class="space-y-2 max-h-80 overflow-y-auto">
                        @foreach ($categorias as $c)
                            <label class="flex items-center justify-between gap-3 text-sm">
                                <span class="text-gray-700">{{ $c->cat_nom }}</span>
                                <select name="categoria[{{ $c->cat_id }}]" class="w-48 rounded-lg border-gray-300 text-sm">
                                    <option value="">— No imprimir —</option>
                                    @foreach ($impresoras as $i)<option value="{{ $i->Id }}" @selected($c->impresora == $i->Id)>{{ $i->descripcion }}</option>@endforeach
                                </select>
                            </label>
                        @endforeach
                    </div>
                </div>
                <div class="bg-white rounded-2xl shadow-sm p-5">
                    <h3 class="font-bold text-gray-800">Caja: impresora de cada usuario</h3>
                    <p class="text-xs text-gray-400 mb-3">Comprobantes y precuentas salen en la impresora del usuario que cobra; si no tiene, en la de "caja por defecto".</p>
                    <div class="space-y-2 max-h-80 overflow-y-auto">
                        @foreach ($usuarios as $u)
                            <label class="flex items-center justify-between gap-3 text-sm">
                                <span class="text-gray-700">{{ $u->apeusu }}</span>
                                <select name="usuario[{{ $u->IdUsuario }}]" class="w-48 rounded-lg border-gray-300 text-sm">
                                    <option value="">— La de caja por defecto —</option>
                                    @foreach ($impresoras as $i)<option value="{{ $i->Id }}" @selected($u->terminal == $i->Id)>{{ $i->descripcion }}</option>@endforeach
                                </select>
                            </label>
                        @endforeach
                    </div>
                </div>
                <div class="lg:col-span-2 text-right">
                    <button class="px-5 py-2 rounded-xl bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700">Guardar asignaciones</button>
                </div>
            </form>
        @endif

        {{-- Cola --}}
        <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
            <div class="px-5 py-3 border-b flex justify-between items-center">
                <h3 class="font-bold text-gray-800">Últimas impresiones</h3>
                <a href="{{ route('impresion.index') }}" class="text-xs text-indigo-600 hover:underline"><i class="fas fa-rotate"></i> Actualizar</a>
            </div>
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs uppercase"><tr>
                    <th class="px-4 py-2 text-left">Hora</th><th class="px-4 py-2 text-left">Tipo</th><th class="px-4 py-2 text-left">Referencia</th>
                    <th class="px-4 py-2 text-left">Impresora</th><th class="px-4 py-2 text-left">Estado</th><th class="px-4 py-2"></th></tr></thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($cola as $c)
                        <tr>
                            <td class="px-4 py-2 whitespace-nowrap">{{ \Carbon\Carbon::parse($c->creado)->format('d/m H:i:s') }}</td>
                            <td class="px-4 py-2">{{ $c->tipo }}</td>
                            <td class="px-4 py-2">{{ $c->referencia }}</td>
                            <td class="px-4 py-2">{{ $c->impresora }}</td>
                            <td class="px-4 py-2">
                                <span class="px-2 py-0.5 rounded-full text-xs font-bold {{ $estados[$c->estado][1] ?? '' }}">{{ $estados[$c->estado][0] ?? $c->estado }}</span>
                                @if ($c->error)<span class="block text-xs text-red-600">{{ $c->error }}</span>@endif
                            </td>
                            <td class="px-4 py-2 text-right">
                                @if (in_array($c->estado, [1, 9]))
                                    <form method="POST" action="{{ route('impresion.reintentar', $c->id) }}">@csrf<button class="text-xs text-indigo-600 hover:underline">Reintentar</button></form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-6 text-center text-gray-400">Sin impresiones todavía.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Modal impresora --}}
        <div x-show="form" x-cloak class="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4" @click.self="form = null">
            <template x-if="form">
            <form method="POST" :action="form.Id ? '{{ url('impresoras') }}/' + form.Id : '{{ route('impresion.guardar') }}'"
                  class="bg-white rounded-2xl shadow-xl w-full max-w-md p-5 space-y-3">
                @csrf
                <h3 class="font-bold text-gray-800" x-text="form && form.Id ? 'Editar impresora' : 'Nueva impresora'"></h3>
                <label class="block text-sm">Nombre (ej. CAJA, COCINA, BAR)
                    <input name="descripcion" x-model="form.descripcion" required maxlength="30" class="{{ $in }} uppercase"></label>
                <label class="block text-sm">Conexión
                    <select name="tip_conex_imp" x-model="form.tip_conex_imp" class="{{ $in }}">
                        <option value="COMPARTIDO">Compartida en Windows (USB)</option>
                        <option value="RED">Red (IP, puerto 9100)</option>
                        <option value="WINDOWS">A4 / PDF (impresora instalada en Windows)</option>
                    </select></label>
                <label class="block text-sm"><span x-text="form && form.tip_conex_imp === 'RED' ? 'IP de la impresora' : (form && form.tip_conex_imp === 'WINDOWS' ? 'Nombre de la impresora A4 (EPSON A4 o PC_CAJA/EPSON A4)' : 'Compartida: CAJA (en la PC del agente) o PC_COCINA/COCINA (otra PC)')"></span>
                    <input name="ruta" x-model="form.ruta" required maxlength="100" class="{{ $in }} font-mono"
                           :placeholder="form && form.tip_conex_imp === 'RED' ? '192.168.1.50' : (form && form.tip_conex_imp === 'WINDOWS' ? 'PC_CAJA/EPSON A4' : 'PC_CAJA/CAJA')"></label>
                <label class="block text-sm" x-show="form.tip_conex_imp !== 'WINDOWS'">Papel
                    <select name="columnas" x-model.number="form.columnas" class="{{ $in }}">
                        <option value="42">80 mm (42 caracteres)</option>
                        <option value="48">80 mm (48 caracteres, letra pequeña)</option>
                        <option value="32">58 mm (32 caracteres)</option>
                    </select></label>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="predeterminado" value="1" x-model="form.predeterminado" class="rounded"> Impresora de caja por defecto</label>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="abrir_cajon" value="1" x-model="form.abrir_cajon" class="rounded"> Abrir el cajón de dinero al imprimir comprobantes</label>
                <label class="flex items-center gap-2 text-sm"><input type="hidden" name="activo" value="0"><input type="checkbox" name="activo" value="1" x-model="form.activo" class="rounded"> Activa</label>
                <div class="flex gap-2 pt-2">
                    <button type="button" @click="form = null" class="flex-1 py-2 rounded-xl bg-gray-100 text-gray-700">Cancelar</button>
                    <button class="flex-1 py-2 rounded-xl bg-indigo-600 text-white font-semibold">Guardar</button>
                </div>
            </form>
            </template>
        </div>
    </div>
@endsection
