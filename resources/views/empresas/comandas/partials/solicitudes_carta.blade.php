{{--
    Lo que piden los clientes desde el QR de su mesa: un pedido (el mozo lo acepta y va a cocina), "llamar al mozo"
    o "la cuenta". Se revisa cada 8 segundos y suena un aviso cuando llega algo nuevo.
--}}
<div id="solicitudes_carta" class="fixed bottom-4 right-4 z-40 w-[min(380px,calc(100vw-2rem))] space-y-2"></div>
<script>
    (function () {
        const cont = document.getElementById('solicitudes_carta');
        const URL_LISTA = @json(route('comandas.solicitudes'));
        const URL_BASE = @json(url('comandas/solicitudes')) + '/';
        let vistas = new Set(), primera = true;

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

        function tarjeta(s) {
            const titulo = { PEDIDO: '🔔 Pedido desde la mesa', MOZO: '🙋 Llaman al mozo', CUENTA: '🧾 Piden la cuenta' }[s.tipo];
            const color = { PEDIDO: 'ring-amber-300 bg-amber-50', MOZO: 'ring-sky-300 bg-sky-50', CUENTA: 'ring-violet-300 bg-violet-50' }[s.tipo];
            const items = s.items.map((i) => `<li>${Number(i.cantidad)}× ${esc(i.nombre)}${i.nota ? ` <span class="text-gray-500">(${esc(i.nota)})</span>` : ''}</li>`).join('');
            const botones = s.tipo === 'PEDIDO'
                ? `<button data-aceptar="${s.id}" class="h-10 flex-1 rounded-xl bg-emerald-600 text-sm font-black text-white">✔ Aceptar y a cocina</button>
                   <button data-rechazar="${s.id}" class="h-10 rounded-xl bg-white px-3 text-sm font-bold text-rose-600 ring-1 ring-rose-200">Rechazar</button>`
                : `<button data-aceptar="${s.id}" class="h-10 flex-1 rounded-xl bg-indigo-600 text-sm font-black text-white">${s.tipo === 'MOZO' ? '✔ Voy' : '✔ Ya la llevo'}</button>`;
            return `<div class="rounded-2xl p-3 shadow-xl ring-2 ${color}">
                <div class="flex items-baseline justify-between gap-2"><p class="font-black text-gray-800">${titulo}</p>
                    <span class="text-xs text-gray-500">${s.hace < 1 ? 'ahora' : 'hace ' + s.hace + ' min'}</span></div>
                <p class="text-lg font-black text-indigo-700">${esc(s.mesa)}${s.nombre ? ` <span class="text-sm font-semibold text-gray-600">· ${esc(s.nombre)}</span>` : ''}</p>
                ${items ? `<ul class="mt-1 text-sm text-gray-800">${items}</ul><p class="text-right text-sm font-black">${soles(s.total)}</p>` : ''}
                <div class="mt-2 flex gap-2">${botones}</div></div>`;
        }

        function cargar() {
            fetch(URL_LISTA, { headers: { Accept: 'application/json' } }).then((r) => r.json()).then((lista) => {
                cont.innerHTML = lista.map(tarjeta).join('');
                const nuevas = lista.filter((s) => !vistas.has(s.id));
                if (nuevas.length && !primera) sonar();
                vistas = new Set(lista.map((s) => s.id));
                primera = false;
            }).catch(() => {});
        }

        cont.addEventListener('click', (e) => {
            const aceptar = e.target.closest('[data-aceptar]'), rechazar = e.target.closest('[data-rechazar]');
            if (!aceptar && !rechazar) return;
            const boton = aceptar || rechazar;
            let datos = {};
            if (rechazar) {
                const motivo = prompt('¿Por qué no se acepta? (el cliente lo verá)', 'Se acabó un plato');
                if (motivo === null) return;
                datos = { motivo };
            }
            boton.disabled = true;
            postJson(URL_BASE + (aceptar ? aceptar.dataset.aceptar + '/aceptar' : rechazar.dataset.rechazar + '/rechazar'), datos).then((d) => {
                toast(d.mensaje || (d.ok ? 'Listo' : 'No se pudo.'));
                cargar();
                if (aceptar && typeof refrescarTodo === 'function') refrescarTodo();
            });
        });

        cargar();
        cadaSiVisible(cargar, 8000);
    })();
</script>
