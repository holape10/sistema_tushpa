{{--
    Lo que piden los clientes desde el QR de su mesa: un pedido (el mozo lo acepta y va a cocina), "llamar al mozo"
    o "la cuenta". Se revisa cada 8 segundos y suena un aviso cuando llega algo nuevo.
    Cada tipo tiene su color (pedido ámbar, mozo azul, cuenta fucsia) y, antes de aceptar un pedido, el mozo puede bajar
    o quitar lo que el cliente pidió de más.
--}}
<div id="solicitudes_carta" class="fixed bottom-4 right-4 z-40 w-[min(380px,calc(100vw-2rem))] space-y-2"></div>
<script>
    (function () {
        const cont = document.getElementById('solicitudes_carta');
        const URL_LISTA = @json(route('comandas.solicitudes'));
        const URL_BASE = @json(url('comandas/solicitudes')) + '/';
        let vistas = new Set(), primera = true, lista = [];
        // Lo que el mozo bajó o quitó de cada pedido antes de aceptarlo: { idSolicitud: [cantidad por línea] }
        const ajustes = {};

        // Un "ding" corto (sin archivos de sonido)
        function sonar() {
            try {
                const ctx = new (window.AudioContext || window.webkitAudioContext)();
                [0, .18].forEach((t, i) => {
                    const o = ctx.createOscillator(), g = ctx.createGain();
                    o.frequency.value = i ? 1320 : 880;
                    g.gain.setValueAtTime(.25, ctx.currentTime + t);
                    g.gain.exponentialRampToValueAtTime(.001, ctx.currentTime + t + .35);
                    o.connect(g).connect(ctx.destination);
                    o.start(ctx.currentTime + t);
                    o.stop(ctx.currentTime + t + .4);
                });
            } catch (e) {}
            if (navigator.vibrate) navigator.vibrate([200, 100, 200]);
        }

        // Colores bien distintos para no confundir: pedido (ámbar), llaman al mozo (azul), piden la cuenta (fucsia)
        const ESTILO = {
            PEDIDO: { titulo: '🔔 Pedido desde la mesa', caja: 'ring-amber-400 bg-amber-50', franja: 'bg-amber-400', mesa: 'text-amber-800' },
            MOZO: { titulo: '🙋 Llaman al mozo', caja: 'ring-sky-500 bg-sky-100', franja: 'bg-sky-500', mesa: 'text-sky-800', boton: 'bg-sky-600', texto: '✔ Voy' },
            CUENTA: { titulo: '🧾 Piden la cuenta', caja: 'ring-fuchsia-500 bg-fuchsia-100', franja: 'bg-fuchsia-500', mesa: 'text-fuchsia-800', boton: 'bg-fuchsia-600', texto: '✔ Ya la llevo' },
        };

        const cantidadesDe = (s) => ajustes[s.id] || s.items.map((i) => Number(i.cantidad));

        function tarjeta(s) {
            const e = ESTILO[s.tipo];
            const cant = cantidadesDe(s);
            const editado = cant.some((c, n) => c !== Number(s.items[n].cantidad));
            const items = s.items.map((i, n) => {
                const quitado = cant[n] === 0;
                const controles = s.tipo === 'PEDIDO'
                    ? (quitado
                        ? `<button data-volver="${s.id}" data-linea="${n}" title="Volver a ponerlo" class="h-7 rounded-lg bg-white px-2 text-xs font-bold text-gray-600 ring-1 ring-gray-300">↺</button>`
                        : `<button data-bajar="${s.id}" data-linea="${n}" title="Bajar uno" class="h-7 w-7 rounded-lg bg-white text-base font-black text-gray-700 ring-1 ring-gray-300">−</button>
                           <button data-quitar="${s.id}" data-linea="${n}" title="Quitar" class="h-7 w-7 rounded-lg bg-white text-sm font-black text-rose-600 ring-1 ring-rose-200">✕</button>`)
                    : '';
                return `<li class="flex items-center gap-2 py-0.5 ${quitado ? 'line-through text-gray-400' : ''}">
                    <span class="flex-1">${cant[n]}× ${esc(i.nombre)}${i.nota ? ` <span class="text-gray-500">(${esc(i.nota)})</span>` : ''}</span>${controles}</li>`;
            }).join('');
            const total = s.tipo === 'PEDIDO' ? s.items.reduce((t, i, n) => t + cant[n] * (Number(i.precio) || 0), 0) : s.total;
            const botones = s.tipo === 'PEDIDO'
                ? `<button data-aceptar="${s.id}" class="h-10 flex-1 rounded-xl bg-emerald-600 text-sm font-black text-white">✔ Aceptar y a cocina</button>
                   <button data-rechazar="${s.id}" class="h-10 rounded-xl bg-white px-3 text-sm font-bold text-rose-600 ring-1 ring-rose-200">Rechazar</button>`
                : `<button data-aceptar="${s.id}" class="h-10 flex-1 rounded-xl ${e.boton} text-sm font-black text-white">${e.texto}</button>`;
            return `<div class="overflow-hidden rounded-2xl shadow-xl ring-2 ${e.caja}"><div class="h-2 ${e.franja}"></div><div class="p-3">
                <div class="flex items-baseline justify-between gap-2"><p class="font-black text-gray-800">${e.titulo}</p>
                    <span class="text-xs text-gray-500">${s.hace < 1 ? 'ahora' : 'hace ' + s.hace + ' min'}</span></div>
                <p class="text-lg font-black ${e.mesa}">${esc(s.mesa)}${s.nombre ? ` <span class="text-sm font-semibold text-gray-600">· ${esc(s.nombre)}</span>` : ''}</p>
                ${items ? `<ul class="mt-1 text-sm text-gray-800">${items}</ul>
                    <p class="text-right text-sm font-black">${editado ? '<span class="mr-1 text-xs font-bold text-amber-700">(corregido)</span>' : ''}${soles(total)}</p>` : ''}
                <div class="mt-2 flex gap-2">${botones}</div></div></div>`;
        }

        const pintar = () => { cont.innerHTML = lista.map(tarjeta).join(''); };

        function cargar() {
            fetch(URL_LISTA, { headers: { Accept: 'application/json' } }).then((r) => r.json()).then((nueva) => {
                lista = nueva;
                pintar();
                const nuevas = lista.filter((s) => !vistas.has(s.id));
                if (nuevas.length && !primera) sonar();
                vistas = new Set(lista.map((s) => s.id));
                primera = false;
            }).catch(() => {});
        }

        cont.addEventListener('click', (e) => {
            // Bajar, quitar o volver a poner un producto del pedido (no se guarda todavía: va al aceptar)
            const cambio = e.target.closest('[data-bajar],[data-quitar],[data-volver]');
            if (cambio) {
                const id = Number(cambio.dataset.bajar || cambio.dataset.quitar || cambio.dataset.volver), n = Number(cambio.dataset.linea);
                const s = lista.find((x) => x.id === id);
                if (!s) return;
                const cant = ajustes[id] = cantidadesDe(s).slice();
                cant[n] = cambio.dataset.volver ? Number(s.items[n].cantidad) : cambio.dataset.quitar ? 0 : Math.max(0, cant[n] - 1);
                pintar();
                return;
            }
            const aceptar = e.target.closest('[data-aceptar]'), rechazar = e.target.closest('[data-rechazar]');
            if (!aceptar && !rechazar) return;
            const boton = aceptar || rechazar;
            let datos = {};
            if (aceptar && ajustes[aceptar.dataset.aceptar]) {
                const cant = ajustes[aceptar.dataset.aceptar];
                if (!cant.some((c) => c > 0)) { toast('Quitaste todo: usa "Rechazar" para avisarle al cliente.'); return; }
                datos = { cantidades: cant };
            }
            if (rechazar) {
                const motivo = prompt('¿Por qué no se acepta? (el cliente lo verá)', 'Se acabó un plato');
                if (motivo === null) return;
                datos = { motivo };
            }
            boton.disabled = true;
            postJson(URL_BASE + (aceptar ? aceptar.dataset.aceptar + '/aceptar' : rechazar.dataset.rechazar + '/rechazar'), datos).then((d) => {
                toast(d.mensaje || (d.ok ? 'Listo' : 'No se pudo.'));
                if (d.ok) delete ajustes[boton.dataset.aceptar || boton.dataset.rechazar];
                cargar();
                if (aceptar && typeof refrescarTodo === 'function') refrescarTodo();
            });
        });

        cargar();
        cadaSiVisible(cargar, 8000);
    })();
</script>
