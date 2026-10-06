@extends('socio_portal.layout')
@section('titulo', 'Mi cuenta')

@section('acciones')
    <form method="POST" action="{{ route('socio.portal.salir') }}" class="ml-auto">
        @csrf
        <button class="text-sm font-semibold bg-white/10 hover:bg-white/20 rounded-xl px-3 py-2">Salir</button>
    </form>
@endsection

@section('contenido')
<script>
    function portalSocio() {
        return {
            d: @js($datos),
            carnet: 0,
            tab: 'deuda',
            async refrescar() {
                try {
                    const r = await fetch('{{ route('socio.portal.estado') }}', { headers: { 'Accept': 'application/json' } });
                    if (r.status === 401) { location.reload(); return; }
                    if (r.ok) this.d = await r.json();
                } catch (e) {}
            },
            soles(n) { return 'S/ ' + Number(n || 0).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
            fecha(s) { return new Date(s.replace(' ', 'T')).toLocaleDateString('es-PE', { day: '2-digit', month: 'short', year: 'numeric' }); },
            get color() { return { green: 'from-emerald-500 to-emerald-700', amber: 'from-amber-400 to-amber-600', red: 'from-rose-500 to-rose-700' }[this.d.color] || 'from-slate-500 to-slate-700'; },
        };
    }
</script>

<div x-data="portalSocio()" x-init="setInterval(() => refrescar(), 20000)" class="space-y-5">
    @if (session('aviso'))
        <div class="rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 text-sm">{{ session('aviso') }}</div>
    @endif
    @if ($cambiarClave)
        <div class="rounded-xl bg-amber-50 border border-amber-200 text-amber-900 px-4 py-3 text-sm">
            <b>Por seguridad crea tu propia contraseña</b> (hoy entras con tu DNI). Hazlo abajo en <a href="#clave" class="underline font-semibold">Cambiar contraseña</a>.
        </div>
    @endif

    {{-- Estado --}}
    <section class="rounded-3xl text-white p-6 shadow-lg bg-gradient-to-br" :class="color">
        <p class="text-sm opacity-90">Hola, {{ \Illuminate\Support\Str::of($socio->clinom)->title() }}</p>
        <p class="text-4xl font-black tracking-wide mt-1" x-text="d.estado"></p>
        <div class="flex flex-wrap gap-x-4 gap-y-1 mt-3 text-sm opacity-95">
            <span>Socio N° <b>{{ $socio->codigo }}</b></span>
            @if ($socio->categoria)<span>{{ $socio->categoria }} · cuota S/ {{ number_format($socio->cuota, 2) }}</span>@endif
        </div>
        <p class="mt-4 text-xs opacity-80 flex items-center gap-1.5">
            <span class="inline-block w-2 h-2 rounded-full bg-white animate-pulse"></span> En vivo · actualizado <span x-text="d.actualizado"></span>
        </p>
    </section>

    {{-- Resumen --}}
    <section class="grid grid-cols-2 gap-3">
        <div class="bg-white rounded-2xl shadow-sm p-4">
            <p class="text-xs text-slate-500">Mi deuda</p>
            <p class="text-2xl font-extrabold" :class="d.deuda > 0 ? 'text-rose-600' : 'text-emerald-600'" x-text="soles(d.deuda)"></p>
            <p class="text-xs text-slate-400" x-text="d.pendientes.length ? d.pendientes.length + (d.pendientes.length === 1 ? ' cargo pendiente' : ' cargos pendientes') : 'Estás al día'"></p>
        </div>
        <div class="bg-white rounded-2xl shadow-sm p-4">
            <p class="text-xs text-slate-500">Pagado en {{ now()->year }}</p>
            <p class="text-2xl font-extrabold text-slate-800" x-text="soles(d.pagado_anio)"></p>
            <p class="text-xs text-slate-400" x-text="d.pagos.length + ' pago(s) registrados'"></p>
        </div>
    </section>

    {{-- Pestañas --}}
    <nav class="bg-white rounded-2xl shadow-sm p-1 grid grid-cols-4 text-sm font-semibold">
        <template x-for="t in [['deuda', 'Deuda'], ['pagos', 'Pagos'], ['carnet', 'Carnet'], ['familia', 'Familia']]">
            <button type="button" @click="tab = t[0]" class="py-2 rounded-xl" :class="tab === t[0] ? 'bg-emerald-700 text-white' : 'text-slate-500'" x-text="t[1]"></button>
        </template>
    </nav>

    {{-- Deuda --}}
    <section x-show="tab === 'deuda'" class="bg-white rounded-2xl shadow-sm divide-y">
        <template x-for="c in d.pendientes">
            <div class="flex items-center justify-between gap-3 p-4">
                <div class="min-w-0">
                    <p class="font-semibold text-slate-700" x-text="c.descripcion"></p>
                    <p x-show="c.a_cuenta > 0" class="text-xs text-slate-400" x-text="'Ya pagaste ' + soles(c.a_cuenta) + ' a cuenta'"></p>
                </div>
                <p class="font-extrabold text-rose-600 whitespace-nowrap" x-text="soles(c.saldo)"></p>
            </div>
        </template>
        <div x-show="!d.pendientes.length" class="p-8 text-center">
            <p class="text-4xl">🎉</p>
            <p class="font-bold text-emerald-700 mt-2">¡Estás al día!</p>
            <p class="text-sm text-slate-500">Gracias por tu puntualidad.</p>
        </div>
        <p x-show="d.pendientes.length" class="p-4 text-xs text-slate-500">Puedes pagar en caja del club. Se aplica desde la cuota más antigua.</p>
    </section>

    {{-- Pagos --}}
    <section x-show="tab === 'pagos'" x-cloak class="bg-white rounded-2xl shadow-sm divide-y">
        <template x-for="p in d.pagos">
            <div class="flex items-center justify-between gap-3 p-4">
                <div class="min-w-0">
                    <p class="font-semibold text-slate-700" x-text="p.descripcion"></p>
                    <p class="text-xs text-slate-400"><span x-text="fecha(p.fecha)"></span> · <a :href="p.pdf" target="_blank" class="text-emerald-700 font-semibold underline" x-text="p.comprobante"></a></p>
                </div>
                <p class="font-extrabold text-emerald-700 whitespace-nowrap" x-text="soles(p.monto)"></p>
            </div>
        </template>
        <p x-show="!d.pagos.length" class="p-8 text-center text-slate-400">Aún no hay pagos registrados.</p>
    </section>

    {{-- Carnet digital --}}
    <section x-show="tab === 'carnet'" x-cloak class="space-y-3">
        <div class="flex gap-2 overflow-x-auto pb-1" x-show="d.carnets.length > 1">
            <template x-for="(c, i) in d.carnets">
                <button type="button" @click="carnet = i" class="px-3 py-1.5 rounded-full text-xs font-bold whitespace-nowrap"
                        :class="carnet === i ? 'bg-emerald-700 text-white' : 'bg-white text-slate-600'" x-text="c.tipo === 'TITULAR' ? 'Yo' : c.nombre.split(' ')[0]"></button>
            </template>
        </div>
        <div class="bg-white rounded-3xl shadow-sm overflow-hidden max-w-sm mx-auto">
            <div class="bg-emerald-800 text-white px-5 py-3 flex justify-between items-center">
                <span class="font-bold">{{ $negocio->nombre_comercial ?: $negocio->NomEmpresa }}</span>
                <span class="text-xs">N° {{ $socio->codigo }}</span>
            </div>
            <div class="p-5 text-center">
                <div class="w-56 h-56 mx-auto [&_svg]:w-full [&_svg]:h-full" x-html="d.carnets[carnet]?.qr"></div>
                <p class="mt-3 font-extrabold text-lg" x-text="d.carnets[carnet]?.nombre"></p>
                <p class="text-sm text-slate-500" x-text="d.carnets[carnet]?.tipo === 'TITULAR' ? 'SOCIO TITULAR' : 'FAMILIAR · ' + d.carnets[carnet]?.tipo"></p>
                <span class="inline-block mt-3 px-3 py-1 rounded-full text-sm font-bold text-white bg-gradient-to-r" :class="color" x-text="d.estado"></span>
            </div>
            <p class="bg-emerald-50 text-emerald-800 text-xs text-center py-2">Muestra este QR en portería</p>
        </div>
    </section>

    {{-- Familia --}}
    <section x-show="tab === 'familia'" x-cloak class="bg-white rounded-2xl shadow-sm divide-y">
        <template x-for="f in d.familiares">
            <div class="p-4">
                <p class="font-semibold text-slate-700" x-text="f.nombre"></p>
                <p class="text-xs text-slate-400" x-text="f.parentesco + (f.dni ? ' · DNI ' + f.dni : '')"></p>
            </div>
        </template>
        <p x-show="!d.familiares.length" class="p-8 text-center text-slate-400">No tienes familiares registrados. Pide en el club que los agreguen.</p>
    </section>

    {{-- Contraseña --}}
    <section id="clave" class="bg-white rounded-2xl shadow-sm p-5">
        <h2 class="font-bold text-slate-700">Cambiar contraseña</h2>
        @if ($errors->any())
            <div class="mt-3 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 px-4 py-2 text-sm">{{ $errors->first() }}</div>
        @endif
        <form method="POST" action="{{ route('socio.portal.clave') }}" class="mt-3 grid sm:grid-cols-3 gap-2">
            @csrf
            <input type="password" name="password" required minlength="6" placeholder="Nueva contraseña" autocomplete="new-password" class="h-11 rounded-xl border-slate-300">
            <input type="password" name="password_confirmation" required minlength="6" placeholder="Repítela" autocomplete="new-password" class="h-11 rounded-xl border-slate-300">
            <button class="h-11 rounded-xl bg-emerald-700 text-white font-bold">Guardar</button>
        </form>
    </section>
</div>
@endsection
