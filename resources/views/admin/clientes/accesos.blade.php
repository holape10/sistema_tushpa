@extends('admin.layout')
@section('title', 'Contraseñas · '.($cliente->nombre_comercial ?: $cliente->razon_social))

@section('content')
    <a href="{{ route('admin.clientes.index') }}" class="text-sm text-slate-500">← Clientes</a>
    <h1 class="text-xl font-bold mt-1">Usuarios de {{ $cliente->nombre_comercial ?: $cliente->razon_social }}</h1>
    <p class="text-sm text-slate-500 mb-4">{{ $cliente->ruc }} · <a href="{{ $cliente->url() }}" target="_blank" class="text-indigo-700 underline">{{ $cliente->host() }}</a>
        · Si un usuario olvidó su contraseña, ponle una nueva aquí y entrégasela.</p>

    @if ($c = session('clave'))
        {{-- La contraseña solo se muestra esta vez --}}
        <div class="mb-5 rounded-2xl bg-emerald-50 border border-emerald-200 p-5" x-data="{ copiado: false }">
            <p class="font-bold text-emerald-800">✔ Contraseña nueva para {{ $c['nombre'] }}</p>
            <dl class="mt-2 grid sm:grid-cols-[auto_1fr] gap-x-6 gap-y-1 text-sm">
                <dt class="text-slate-500">Entrar en</dt><dd><a href="{{ $c['url'] }}" target="_blank" class="font-semibold text-indigo-700 underline">{{ $c['url'] }}</a></dd>
                <dt class="text-slate-500">Usuario</dt><dd class="font-mono font-semibold">{{ $c['usuario'] }}</dd>
                <dt class="text-slate-500">Contraseña</dt><dd class="font-mono text-lg font-bold">{{ $c['password'] }}</dd>
            </dl>
            @php($texto = "Hola, estos son tus datos para entrar a tu sistema TUSHPA:\nEntrar en: {$c['url']}\nUsuario: {$c['usuario']}\nContraseña: {$c['password']}\nTe recomendamos cambiarla al entrar.")
            <div class="mt-3 flex flex-wrap gap-2">
                <button type="button" @click="navigator.clipboard.writeText(@js($texto)).then(() => copiado = true)" class="px-3 h-9 rounded-xl bg-white ring-1 ring-emerald-300 text-sm font-semibold" x-text="copiado ? '✔ Copiado' : 'Copiar datos'"></button>
                @if ($cliente->contacto_telefono)
                    <a href="https://wa.me/51{{ preg_replace('/\D/', '', $cliente->contacto_telefono) }}?text={{ rawurlencode($texto) }}" target="_blank" rel="noopener"
                       class="px-3 h-9 inline-flex items-center rounded-xl bg-green-600 text-white text-sm font-bold">Enviar por WhatsApp</a>
                @endif
            </div>
            <p class="mt-2 text-xs text-amber-700">No se volverá a mostrar: cópiala o envíala ahora.</p>
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 text-sm">@foreach ($errors->all() as $er)<p>{{ $er }}</p>@endforeach</div>
    @endif

    <div class="bg-white rounded-2xl shadow-sm overflow-x-auto">
        <table class="w-full text-sm tabla-movil">
            <thead class="bg-slate-50 text-slate-500 text-xs uppercase">
                <tr><th class="text-left px-4 py-3">Usuario</th><th class="text-left px-4 py-3">Rol</th><th class="text-left px-4 py-3">Sucursal</th>
                    <th class="text-left px-4 py-3">Estado</th><th class="px-4 py-3 text-right">Nueva contraseña</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($usuarios as $u)
                    <tr class="{{ $u->estusu ? '' : 'opacity-60' }}">
                        <td class="px-4 py-3" data-label="Usuario"><p class="font-semibold">{{ trim($u->apeusu ?: $u->name) }}</p><p class="text-xs text-slate-500 font-mono">{{ $u->email }}</p></td>
                        <td class="px-4 py-3" data-label="Rol">{{ $u->rol ?? '—' }}</td>
                        <td class="px-4 py-3" data-label="Sucursal">{{ $u->sucursal ?? '—' }}</td>
                        <td class="px-4 py-3" data-label="Estado">
                            <span class="px-2 py-0.5 rounded-full text-xs font-semibold {{ $u->estusu ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600' }}">{{ $u->estusu ? 'Activo' : 'Desactivado' }}</span></td>
                        <td class="px-4 py-3 celda-acciones">
                            <form method="POST" action="{{ route('admin.clientes.accesos.clave', [$cliente->id, $u->IdUsuario]) }}" class="flex flex-wrap justify-start md:justify-end items-center gap-2"
                                  onsubmit="return confirm('¿Poner una contraseña nueva a {{ e($u->email) }}? La anterior deja de funcionar.')">
                                @csrf
                                <input name="password" placeholder="Vacío = se genera sola" minlength="6" maxlength="60" autocomplete="new-password" class="rounded-xl border-slate-300 text-sm h-9 w-48">
                                @unless ($u->estusu)
                                    <label class="flex items-center gap-1 text-xs"><input type="checkbox" name="activar" value="1" class="rounded border-slate-300"> Reactivar</label>
                                @endunless
                                <button class="px-3 h-9 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold">Cambiar</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-10 text-center text-slate-400">Esta empresa no tiene usuarios.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Equipos de los usuarios: soporte los autoriza o bloquea sin depender de un equipo de confianza de la empresa --}}
    @if ($equipos->isNotEmpty())
        @php($nombres = $usuarios->keyBy('IdUsuario'))
        @php($porRevisar = $equipos->flatten()->where('por_revisar', true)->whereNull('bloqueado_el')->count())
        <div class="mt-6 bg-white rounded-2xl shadow-sm overflow-hidden">
            <div class="px-4 py-3 border-b border-slate-100 flex flex-wrap items-center gap-3">
                <div class="flex-1 min-w-60"><p class="font-bold">🔐 Equipos (PC y celulares)</p>
                    <p class="text-xs text-slate-500">Si la empresa se quedó sin un equipo de confianza (cambió de PC, borró el navegador) o entraste tú desde tu PC, autorízalos aquí.</p></div>
                @if ($porRevisar)
                    <form method="POST" action="{{ route('admin.clientes.equipos', [$cliente->id, 'todos']) }}" onsubmit="return confirm('¿Autorizar los {{ $porRevisar }} equipos por revisar? Revisa antes que los reconozcas.')">@csrf
                        <input type="hidden" name="accion" value="confiar">
                        <button class="h-9 px-3 rounded-xl bg-emerald-600 text-white text-xs font-bold">✓ Autorizar los {{ $porRevisar }} por revisar</button></form>
                @endif
            </div>
            <div class="divide-y divide-slate-100 text-sm">
                @foreach ($equipos as $idUsuario => $lista)
                    @php($u = $nombres[$idUsuario] ?? null)
                    <p class="px-4 pt-3 pb-1 text-xs font-bold text-slate-500 uppercase">{{ $u ? trim($u->apeusu ?: $u->name).' · '.$u->email : 'Usuario '.$idUsuario }}</p>
                    @foreach ($lista as $e)
                        <div class="px-4 py-2 flex flex-wrap items-center gap-3 {{ $e->por_revisar && ! $e->bloqueado_el ? 'bg-amber-50/60' : '' }}">
                            <span class="text-lg">{{ $e->tipo === 'PC' ? '💻' : '📱' }}</span>
                            <div class="flex-1 min-w-56"><p class="font-semibold">{{ $e->nombre }}</p>
                                <p class="text-xs text-slate-500">IP {{ $e->ip }}{{ $e->ciudad ? ' ('.$e->ciudad.')' : '' }} · última vez {{ \Carbon\Carbon::parse($e->ultima_vez)->format('d/m/Y H:i') }}</p></div>
                            @if ($e->bloqueado_el)
                                <span class="px-2 py-0.5 rounded-full text-xs font-bold bg-rose-100 text-rose-700">Bloqueado</span>
                            @elseif ($e->por_revisar)
                                <span class="px-2 py-0.5 rounded-full text-xs font-bold bg-amber-200 text-amber-900">Por revisar</span>
                            @elseif ($e->confiable)
                                <span class="px-2 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-700">✓ De confianza</span>
                            @endif
                            @if (! $e->confiable || $e->bloqueado_el)
                                <form method="POST" action="{{ route('admin.clientes.equipos', [$cliente->id, $e->id]) }}">@csrf <input type="hidden" name="accion" value="confiar">
                                    <button class="h-8 px-3 rounded-lg bg-emerald-600 text-white text-xs font-bold">{{ $e->bloqueado_el ? 'Desbloquear' : '✓ Autorizar' }}</button></form>
                            @endif
                            @unless ($e->bloqueado_el)
                                <form method="POST" action="{{ route('admin.clientes.equipos', [$cliente->id, $e->id]) }}" onsubmit="return confirm('¿Bloquear este equipo? Se cierra su sesión y ya no podrá entrar.')">@csrf
                                    <input type="hidden" name="accion" value="bloquear">
                                    <button class="h-8 px-3 rounded-lg bg-white border border-rose-200 text-rose-600 text-xs font-bold">Bloquear</button></form>
                            @endunless
                        </div>
                    @endforeach
                @endforeach
            </div>
        </div>
    @endif
@endsection
