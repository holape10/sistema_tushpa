@extends('admin.layout')
@section('title', 'Certificados digitales')

@section('content')
    @use('App\Support\Certificados')
    @php $in = 'mt-1 w-full rounded-xl border-slate-300 text-sm'; @endphp
    <div class="flex flex-wrap items-center gap-3 mb-4">
        <div>
            <h1 class="text-xl font-bold">Certificados digitales en {{ config('tenancy.dominio') }}</h1>
            <p class="text-sm text-slate-500">Los precios y textos que se ven en la sección "Certificado digital" de la página principal.
                El botón "Lo quiero" abre tu WhatsApp con el pedido escrito.</p>
        </div>
        <a href="{{ route('sitio.inicio') }}#certificados" target="_blank" class="ml-auto px-3 py-2 rounded-xl bg-white shadow-sm text-sm font-semibold">Ver en la web ↗</a>
    </div>

    @if ($errors->any())
        <div class="mb-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 text-sm">@foreach ($errors->all() as $er)<p>{{ $er }}</p>@endforeach</div>
    @endif

    <div class="grid md:grid-cols-2 xl:grid-cols-3 gap-4">
        @foreach ($certificados->concat([null]) as $c)
            <form method="POST" action="{{ $c ? route('admin.certificados.guardar', $c->id) : route('admin.certificados.guardar') }}"
                  class="bg-white rounded-2xl shadow-sm p-5 space-y-3 {{ $c ? ($c->activo ? '' : 'opacity-60') : 'border-2 border-dashed border-slate-300' }}">
                @csrf
                <p class="font-bold">{{ $c ? '🔐 '.$c->nombre : '➕ Nuevo certificado' }}
                    @if ($c && $c->destacado)<span class="ml-1 px-1.5 rounded text-[10px] font-bold bg-amber-100 text-amber-700">DESTACADO</span>@endif</p>
                <div class="grid grid-cols-2 gap-2">
                    <label class="block text-sm">Nombre<input name="nombre" value="{{ $c->nombre ?? '' }}" required maxlength="60" placeholder="1 año" class="{{ $in }}"></label>
                    <label class="block text-sm">Años de validez<input type="number" name="anios" min="1" max="10" value="{{ $c->anios ?? 1 }}" class="{{ $in }}"></label>
                    <label class="block text-sm">Precio S/<input type="number" name="precio" step="0.01" min="1" value="{{ $c ? (float) $c->precio : '' }}" required class="{{ $in }} font-bold"></label>
                    <label class="block text-sm">Precio antes <span class="text-xs text-slate-400">(tachado)</span>
                        <input type="number" name="precio_antes" step="0.01" min="0" value="{{ $c && $c->precio_antes ? (float) $c->precio_antes : '' }}" placeholder="Opcional" class="{{ $in }}"></label>
                </div>
                <label class="block text-sm">Texto corto<input name="detalle" value="{{ $c->detalle ?? '' }}" maxlength="200" placeholder="Ideal para empezar a facturar" class="{{ $in }}"></label>
                <div class="flex flex-wrap items-center gap-3 text-sm">
                    <label class="flex items-center gap-1.5"><input type="checkbox" name="activo" value="1" @checked($c->activo ?? true) class="rounded"> Se muestra</label>
                    <label class="flex items-center gap-1.5"><input type="checkbox" name="destacado" value="1" @checked($c->destacado ?? false) class="rounded"> Destacado</label>
                    <label class="flex items-center gap-1 ml-auto">Orden <input type="number" name="orden" min="0" max="999" value="{{ $c->orden ?? 0 }}" class="w-16 rounded-lg border-slate-300 text-sm"></label>
                </div>
                @if ($c && $c->anios > 1)<p class="text-xs text-slate-500">Sale a S/ {{ number_format(Certificados::porAnio($c), 2) }} por año.</p>@endif
                <div class="flex gap-2">
                    <button class="flex-1 h-10 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold">{{ $c ? 'Guardar' : 'Agregar' }}</button>
                    @if ($c)
                        <button formaction="{{ route('admin.certificados.eliminar', $c->id) }}" onclick="return confirm('¿Eliminar este certificado de la web?')"
                                class="h-10 px-3 rounded-xl bg-rose-50 text-rose-700 text-sm font-semibold">Eliminar</button>
                    @endif
                </div>
            </form>
        @endforeach
    </div>
@endsection
