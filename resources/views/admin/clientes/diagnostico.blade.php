@extends('admin.layout')
@section('title', 'Diagnóstico · '.($cliente->nombre_comercial ?: $cliente->razon_social))

@section('content')
    {{-- 🩺 Todo lo de soporte de una empresa en una pantalla: semáforo, arreglos de un clic, ver como el cliente y su día --}}
    @php
        $esDueno = auth('superadmin')->user()->es_dueno;
        $ir = [
            'rechazados' => route('admin.clientes.comprobantes', [$cliente->id, 'filtro' => 'rechazados']),
            'pendientes' => route('admin.clientes.comprobantes', [$cliente->id, 'filtro' => 'pendientes']),
            'resumenes' => route('admin.clientes.comprobantes', [$cliente->id, 'filtro' => 'resumenes']),
            'equipos' => route('admin.clientes.accesos', $cliente->id).'#equipos',
            'alertas' => route('admin.clientes.alertas', $cliente->id),
            'tickets' => route('admin.tickets.index', ['q' => $cliente->razon_social]),
            'errores' => $esDueno ? route('admin.errores.index') : null,
        ];
        $colores = ['rojo' => ['bg-rose-50 ring-rose-200', 'bg-rose-500', 'Urgente'], 'naranja' => ['bg-orange-50 ring-orange-200', 'bg-orange-400', 'Importante'],
            'amarillo' => ['bg-amber-50 ring-amber-200', 'bg-amber-300', 'Revisar']];
        $peor = $problemas[0]['nivel'] ?? null;
    @endphp

    <div class="flex flex-wrap items-center gap-3 mb-4">
        <a href="{{ route('admin.clientes.index') }}" class="h-9 px-3 rounded-xl bg-white shadow-sm text-sm font-semibold flex items-center">← Clientes</a>
        <div class="flex-1 min-w-[14rem]">
            <h1 class="text-xl font-bold">🩺 {{ $cliente->nombre_comercial ?: $cliente->razon_social }}</h1>
            <p class="text-sm text-slate-500">RUC {{ $cliente->ruc }} · <a href="{{ $cliente->url() }}" target="_blank" class="text-indigo-700 underline">{{ $cliente->host() }}</a>
                · {{ $cliente->estado }}{{ $cliente->vence_el ? ' · paga hasta el '.$cliente->vence_el->format('d/m/Y') : '' }}</p>
        </div>
        <a href="{{ route('admin.clientes.diagnostico', $cliente->id) }}" class="h-9 px-3 rounded-xl bg-white shadow-sm text-sm font-semibold flex items-center">↻ Revisar de nuevo</a>
    </div>

    {{-- Semáforo general --}}
    <div class="rounded-2xl p-4 mb-4 text-white shadow-sm {{ ['rojo' => 'bg-rose-600', 'naranja' => 'bg-orange-500', 'amarillo' => 'bg-amber-500'][$peor] ?? 'bg-emerald-600' }}">
        <p class="text-lg font-black">{{ $peor === null ? '✔ Todo en orden' : count($problemas).' cosa(s) por atender' }}</p>
        <p class="text-sm text-white/90">
            @if (! empty($info))
                Último ingreso: {{ $info['ultimo_ingreso'] ? \Carbon\Carbon::parse($info['ultimo_ingreso'])->format('d/m/Y H:i').' ('.$info['ultimo_usuario'].')' : '—' }}
                · Hoy: {{ $info['ventas_hoy'] }} comprobante(s), S/ {{ number_format($info['total_hoy'], 2) }}
                · {{ $info['usuarios_activos'] }} usuario(s) activo(s)
            @endif
        </p>
    </div>

    <div class="grid lg:grid-cols-3 gap-4">
        <div class="lg:col-span-2 space-y-2">
            @forelse ($problemas as $p)
                <div class="rounded-2xl ring-1 p-4 flex flex-wrap items-start gap-3 {{ $colores[$p['nivel']][0] }}">
                    <span class="mt-1.5 w-3 h-3 rounded-full shrink-0 {{ $colores[$p['nivel']][1] }}" title="{{ $colores[$p['nivel']][2] }}"></span>
                    <div class="flex-1 min-w-[12rem]">
                        <p class="font-bold text-slate-800">{{ $p['titulo'] }}</p>
                        @if ($p['detalle'])<p class="text-sm text-slate-600">{{ $p['detalle'] }}</p>@endif
                    </div>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($p['arreglos'] as $a)
                            <form method="POST" action="{{ route('admin.clientes.diagnostico.arreglar', $cliente->id) }}" onsubmit="return confirm(@js($a['confirmar']))">
                                @csrf<input type="hidden" name="accion" value="{{ $a['accion'] }}">
                                @foreach ($a['datos'] as $k => $v)<input type="hidden" name="datos[{{ $k }}]" value="{{ $v }}">@endforeach
                                <button class="h-9 px-3 rounded-xl bg-slate-900 text-white text-xs font-bold">🔧 {{ $a['texto'] }}</button>
                            </form>
                        @endforeach
                        @if ($p['ir'] && ($ir[$p['ir']] ?? null))
                            <a href="{{ $ir[$p['ir']] }}" class="h-9 px-3 inline-flex items-center rounded-xl bg-white ring-1 ring-slate-200 text-xs font-bold text-slate-700">Ver →</a>
                        @endif
                    </div>
                </div>
            @empty
                <div class="bg-white rounded-2xl shadow-sm p-8 text-center text-slate-500">No se encontró nada raro: SUNAT, caja, correlativos, equipos y errores están bien.</div>
            @endforelse
        </div>

        <div class="space-y-4">
            {{-- Ver como el cliente --}}
            <section class="bg-white rounded-2xl shadow-sm p-4" x-data="{ usuario: @js($usuarios->first()?->IdUsuario), modo: 'mirar' }">
                <h2 class="font-bold">👁 Ver como el cliente</h2>
                <p class="text-xs text-slate-500 mb-3">Entras a su sistema como ese usuario, sin pedirle la clave. Queda en su bitácora y dura una hora.</p>
                @if ($cliente->activo() && $usuarios->isNotEmpty())
                    <form method="POST" :action="@js(route('admin.clientes.soporte', [$cliente->id, 0])).replace(/\/0$/, '/' + usuario)" target="_blank" class="space-y-2">
                        @csrf
                        <select x-model="usuario" class="w-full rounded-xl border-slate-300 text-sm">
                            @foreach ($usuarios as $u)
                                <option value="{{ $u->IdUsuario }}">{{ trim($u->apeusu ?: $u->name) }}{{ (int) $u->role_id === 2 ? ' (administrador)' : '' }}</option>
                            @endforeach
                        </select>
                        @foreach ($modos as $valor => $texto)
                            <label class="flex items-center gap-2 text-sm"><input type="radio" name="modo" value="{{ $valor }}" x-model="modo"> {{ $texto }}</label>
                        @endforeach
                        <p x-show="modo === 'cambiar'" x-cloak class="text-xs text-rose-700">Lo que guardes queda como hecho por ese usuario (y en la bitácora, que entró soporte).</p>
                        <button class="w-full h-10 rounded-xl bg-sky-600 text-white text-sm font-bold">Entrar a su sistema ↗</button>
                    </form>
                @else
                    <p class="text-sm text-slate-400">{{ $cliente->activo() ? 'No tiene usuarios activos.' : 'La empresa no está activa.' }}</p>
                @endif
                @if ($visitas->isNotEmpty())
                    <p class="mt-3 text-[11px] font-bold text-slate-400 uppercase">Últimas visitas</p>
                    <ul class="text-xs text-slate-500 space-y-0.5">
                        @foreach ($visitas as $v)
                            <li>{{ \Carbon\Carbon::parse($v->usado_at)->format('d/m H:i') }} · {{ $v->superadmin_nombre }} como {{ $v->usuario_nombre }} ({{ $v->modo }})</li>
                        @endforeach
                    </ul>
                @endif
            </section>

            {{-- Atajos --}}
            <section class="bg-white rounded-2xl shadow-sm p-4">
                <h2 class="font-bold mb-2">Atajos</h2>
                <div class="grid grid-cols-2 gap-2 text-sm font-semibold">
                    <a href="{{ route('admin.clientes.linea_tiempo', $cliente->id) }}" class="rounded-xl bg-slate-50 px-3 py-2 hover:bg-slate-100">🕑 Línea de tiempo</a>
                    <a href="{{ route('admin.clientes.comprobantes', $cliente->id) }}" class="rounded-xl bg-slate-50 px-3 py-2 hover:bg-slate-100">🧾 Comprobantes</a>
                    <a href="{{ route('admin.clientes.accesos', $cliente->id) }}" class="rounded-xl bg-slate-50 px-3 py-2 hover:bg-slate-100">🔑 Contraseñas</a>
                    <a href="{{ route('admin.clientes.alertas', $cliente->id) }}" class="rounded-xl bg-slate-50 px-3 py-2 hover:bg-slate-100">🗂 Bitácora</a>
                    @if ($esDueno)
                        <a href="{{ route('admin.soporte_ia.index', ['cliente' => $cliente->id]) }}" class="rounded-xl bg-slate-50 px-3 py-2 hover:bg-slate-100">🤖 Soporte IA</a>
                        <a href="{{ route('admin.basedatos.index', ['base' => $cliente->base_datos]) }}" class="rounded-xl bg-slate-50 px-3 py-2 hover:bg-slate-100">🗄 Base de datos</a>
                    @endif
                </div>
            </section>
        </div>
    </div>
@endsection
