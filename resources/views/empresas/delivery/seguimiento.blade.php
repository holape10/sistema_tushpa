<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#3730a3">
    <meta name="robots" content="noindex">
    <title>Tu pedido · {{ $negocio->nombre_comercial ?? 'Delivery' }}</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
    <style>
        .sg-icono { background: none; border: none; }
        .sg-icono span { display: flex; align-items: center; justify-content: center; width: 38px; height: 38px; font-size: 22px; background: #fff; border-radius: 9999px; box-shadow: 0 1px 5px rgba(0,0,0,.35); }
    </style>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100 text-slate-800 antialiased" x-data="seguimiento()" x-init="iniciar()">

    <header class="relative isolate overflow-hidden bg-gradient-to-br from-indigo-800 via-indigo-700 to-blue-700 text-white rounded-b-[2rem] shadow-lg">
        <x-kene-adorno patron="escalera" />
        <div class="max-w-md mx-auto px-5 pt-6 pb-8 flex items-center gap-3">
            @if ($logo)
                <img src="{{ $logo }}" alt="" class="w-14 h-14 rounded-2xl bg-white object-contain p-1 shadow">
            @endif
            <div>
                <p class="text-sm text-indigo-200">{{ $negocio->nombre_comercial ?? 'Delivery' }}</p>
                <p class="text-2xl font-black" x-text="texto">{{ \App\Support\Delivery::ESTADOS[$pedido->entrega_estado ?? 'PREPARANDO'] }}</p>
                <p class="text-xs text-indigo-200">Pedido N° {{ $pedido->ped_id }}</p>
            </div>
        </div>
    </header>

    <main class="max-w-md mx-auto px-4 -mt-5 pb-10 space-y-3">
        <div class="bg-white rounded-2xl shadow-sm p-5">
            <ol class="space-y-0">
                <template x-for="(paso, i) in pasos" :key="paso.clave">
                    <li class="flex gap-3">
                        <div class="flex flex-col items-center">
                            <span class="w-8 h-8 rounded-full flex items-center justify-center text-sm"
                                  :class="hecho(i) ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-400'" x-text="paso.icono"></span>
                            <span x-show="i < pasos.length - 1" class="w-0.5 h-6" :class="hecho(i + 1) ? 'bg-indigo-600' : 'bg-slate-200'"></span>
                        </div>
                        <div class="flex-1 flex justify-between pt-1">
                            <p class="text-sm" :class="actual(i) ? 'font-black text-indigo-700' : (hecho(i) ? 'font-semibold text-slate-700' : 'text-slate-400')" x-text="paso.texto"></p>
                            <p class="text-xs text-slate-500" x-text="horas[paso.clave] || ''"></p>
                        </div>
                    </li>
                </template>
            </ol>
        </div>

        <div x-show="motorizado" class="bg-white rounded-2xl shadow-sm p-4 flex items-center gap-3">
            <span class="w-12 h-12 rounded-full bg-indigo-50 flex items-center justify-center text-2xl">🛵</span>
            <div class="flex-1">
                <p class="text-xs text-slate-500">Tu motorizado</p>
                <p class="font-bold text-slate-800" x-text="motorizado && motorizado.nombre"></p>
            </div>
            <a x-show="motorizado && motorizado.telefono" :href="motorizado && motorizado.telefono ? 'tel:' + motorizado.telefono : null" class="px-3 py-2 rounded-xl bg-indigo-600 text-white text-sm font-bold">📞 Llamar</a>
        </div>

        <div x-show="hayMapa" class="bg-white rounded-2xl shadow-sm overflow-hidden">
            <div id="mapa-seguimiento" class="h-72"></div>
            <p class="px-4 py-2 text-xs text-slate-500" x-text="moto ? 'Ubicación del motorizado actualizada hace ' + moto.hace + ' s' : (estado === 'EN_CAMINO' ? 'Esperando la ubicación del motorizado…' : 'Verás al motorizado en el mapa cuando salga hacia tu dirección.')"></p>
        </div>

        <div x-show="estado === 'ENTREGADO'" class="bg-white rounded-2xl shadow-sm p-6 text-center">
            <p class="text-5xl">✅</p>
            <p class="mt-2 font-black text-slate-800 text-lg">¡Pedido entregado!</p>
            <p class="text-sm text-slate-500 mt-1">Gracias por tu compra. ¡Buen provecho!</p>
        </div>

        @if ($detalle->isNotEmpty())
            <details class="bg-white rounded-2xl shadow-sm p-4 text-sm" @if ($pedido->entrega_origen === 'CARTA') open @endif>
                <summary class="font-bold text-slate-800 cursor-pointer">🧾 Tu pedido · S/ {{ number_format((float) $pedido->ped_tot, 2) }}</summary>
                <div class="mt-3 divide-y">
                    @foreach ($detalle as $d)
                        <div class="flex justify-between gap-3 py-1.5">
                            <span>{{ rtrim(rtrim(number_format((float) $d->ped_det_can, 2, '.', ''), '0'), '.') }} × {{ $d->descripcion }}</span>
                            <span class="text-slate-500 shrink-0">S/ {{ number_format($d->ped_det_can * $d->ped_det_pre, 2) }}</span>
                        </div>
                    @endforeach
                    <div class="flex justify-between pt-2 font-black text-slate-900"><span>Total</span><span>S/ {{ number_format((float) $pedido->ped_tot, 2) }}</span></div>
                </div>
                @if ($pago)<p class="mt-2 text-xs text-slate-500">Pago: {{ $pago }}</p>@endif
            </details>
        @endif

        @if (!empty($negocio->telefono))
            <a href="tel:{{ preg_replace('/\D/', '', $negocio->telefono) }}" class="block text-center text-sm font-semibold text-indigo-700">📞 Llamar al local</a>
        @endif
    </main>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
    <script>
        function seguimiento() {
            const URL_ESTADO = @json(route('pedido.seguimiento.estado', $token));
            const ORDEN = ['PREPARANDO', 'LISTO', 'EN_LOCAL', 'EN_CAMINO', 'ENTREGADO'];
            let mapa = null, marcaMoto = null, marcaDestino = null, marcaLocal = null, linea = null, encuadrado = false;

            function icono(emoji) {
                return L.divIcon({ className: 'sg-icono', html: '<span>' + emoji + '</span>', iconSize: [38, 38], iconAnchor: [19, 19] });
            }

            return {
                estado: @json($pedido->entrega_estado ?? 'PREPARANDO'), texto: @json(\App\Support\Delivery::ESTADOS[$pedido->entrega_estado ?? 'PREPARANDO']), horas: {}, motorizado: null, moto: null, hayMapa: false, temporizador: null,
                pasos: [
                    { clave: 'PREPARANDO', icono: '👨‍🍳', texto: 'Preparando tu pedido' },
                    { clave: 'LISTO', icono: '📦', texto: 'Listo para salir' },
                    { clave: 'EN_LOCAL', icono: '🏪', texto: 'El motorizado lo recoge' },
                    { clave: 'EN_CAMINO', icono: '🛵', texto: 'En camino' },
                    { clave: 'ENTREGADO', icono: '✅', texto: 'Entregado' },
                ],

                hecho(i) { return ORDEN.indexOf(this.estado) >= i; },
                actual(i) { return ORDEN.indexOf(this.estado) === i; },

                iniciar() {
                    this.cargar();
                    this.temporizador = setInterval(() => { if (!document.hidden) this.cargar(); }, 10000);
                    document.addEventListener('visibilitychange', () => { if (!document.hidden) this.cargar(); });
                },

                cargar() {
                    fetch(URL_ESTADO, { headers: { 'Accept': 'application/json' } }).then(r => r.json()).then(d => {
                        Object.assign(this, { estado: d.estado, texto: d.texto, horas: d.horas, motorizado: d.motorizado, moto: d.moto });
                        if (d.estado === 'ENTREGADO') clearInterval(this.temporizador);
                        this.pintarMapa(d);
                    }).catch(() => {});
                },

                pintarMapa(d) {
                    this.hayMapa = d.estado !== 'ENTREGADO' && !!(d.destino || d.moto);
                    if (!this.hayMapa || typeof L === 'undefined') return;
                    this.$nextTick(() => {
                        if (!mapa) {
                            mapa = L.map('mapa-seguimiento', { zoomControl: false, attributionControl: true });
                            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '© OpenStreetMap' }).addTo(mapa);
                            mapa.setView([(d.destino || d.moto).lat, (d.destino || d.moto).lng], 15);
                        }
                        if (d.destino && !marcaDestino) marcaDestino = L.marker([d.destino.lat, d.destino.lng], { icon: icono('🏠') }).addTo(mapa);
                        if (d.local && !marcaLocal) marcaLocal = L.marker([d.local.lat, d.local.lng], { icon: icono('🏪') }).addTo(mapa);
                        if (linea) { linea.remove(); linea = null; }
                        if (d.recorrido && d.recorrido.length > 1) linea = L.polyline(d.recorrido, { color: '#4f46e5', weight: 4, opacity: .7 }).addTo(mapa);
                        if (d.moto) {
                            if (marcaMoto) marcaMoto.setLatLng([d.moto.lat, d.moto.lng]);
                            else marcaMoto = L.marker([d.moto.lat, d.moto.lng], { icon: icono('🛵'), zIndexOffset: 1000 }).addTo(mapa);
                            if (!encuadrado && d.destino) {
                                mapa.fitBounds(L.latLngBounds([[d.moto.lat, d.moto.lng], [d.destino.lat, d.destino.lng]]), { padding: [40, 40], maxZoom: 16 });
                                encuadrado = true;
                            }
                        } else if (marcaMoto) {
                            marcaMoto.remove();
                            marcaMoto = null;
                        }
                        mapa.invalidateSize();
                    });
                },
            };
        }
    </script>
</body>
</html>
