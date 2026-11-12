{{-- Contar con foto (inventario y conteo ciego): contarConFoto(id, nombre) abre la cámara, la IA cuenta y la persona confirma.
     Devuelve la cantidad confirmada (en la unidad del stock) o null si cancela. --}}
<div id="cf_modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4">
    <div class="w-full max-w-md rounded-2xl bg-white shadow-xl p-5 space-y-3">
        <div class="flex items-start justify-between gap-3">
            <div><p class="text-xs font-bold uppercase text-indigo-600">📷 Contar con foto</p>
                <p id="cf_producto" class="font-bold text-gray-800"></p></div>
            <button type="button" data-cf="cancelar" class="text-gray-400 hover:text-gray-600 text-xl leading-none">×</button>
        </div>
        <img id="cf_vista" alt="" class="hidden w-full max-h-56 object-contain rounded-xl bg-gray-50">
        <p id="cf_cargando" class="hidden text-center text-sm text-gray-500 py-4">Contando con IA… (unos segundos)</p>
        <p id="cf_error" class="hidden rounded-xl bg-rose-50 border border-rose-200 text-rose-700 px-3 py-2 text-sm"></p>
        <div id="cf_resultado" class="hidden space-y-2">
            <div class="rounded-xl bg-indigo-50 px-4 py-3">
                <p class="text-xs text-gray-500">La IA contó</p>
                <p id="cf_detalle" class="text-lg font-extrabold text-indigo-800"></p>
                <p id="cf_calculo" class="text-xs text-gray-600"></p>
                <p class="mt-1 text-xs">Confianza: <b id="cf_confianza"></b></p>
            </div>
            <p id="cf_aviso" class="hidden rounded-xl bg-amber-50 border border-amber-200 text-amber-800 px-3 py-2 text-xs"></p>
            <label class="block text-sm font-semibold text-gray-700">Cantidad a anotar (revísala)
                <input id="cf_cantidad" type="number" step="any" min="0" class="mt-1 w-full h-11 rounded-xl border-gray-300 text-right text-lg font-bold"></label>
        </div>
        <div class="flex flex-wrap justify-end gap-2 pt-1">
            <button type="button" data-cf="foto" class="h-10 px-4 rounded-xl bg-gray-100 text-gray-700 text-sm font-semibold hover:bg-gray-200">📷 Tomar foto</button>
            <button type="button" data-cf="usar" id="cf_usar" class="hidden h-10 px-4 rounded-xl bg-indigo-600 text-white text-sm font-bold hover:bg-indigo-700">Usar esta cantidad</button>
        </div>
        <p class="text-[11px] text-gray-400">Toma la foto con buena luz. En rumas, de lado (que se vea el frente y el costado). La IA ayuda, pero tú confirmas.</p>
    </div>
    <input id="cf_archivo" type="file" accept="image/*" capture="environment" class="hidden">
</div>
<script>
    (() => {
        const $ = id => document.getElementById(id);
        const modal = $('cf_modal'), archivo = $('cf_archivo');
        let actual = null;

        const mostrar = (id, si) => $(id).classList.toggle('hidden', !si);
        const cerrar = valor => {
            modal.classList.add('hidden'); modal.classList.remove('flex');
            if (actual) { actual.resolver(valor); actual = null; }
        };

        async function contar(foto) {
            $('cf_vista').src = URL.createObjectURL(foto); mostrar('cf_vista', true);
            mostrar('cf_cargando', true); mostrar('cf_error', false); mostrar('cf_resultado', false); mostrar('cf_usar', false);
            const datos = new FormData();
            datos.append('producto', actual.id); datos.append('foto', foto); datos.append('_token', '{{ csrf_token() }}');
            try {
                const r = await fetch('{{ route('inventario.contar_foto') }}', { method: 'POST', body: datos, headers: { Accept: 'application/json' } });
                const j = await r.json().catch(() => ({}));
                if (!r.ok || !j.ok) throw new Error(j.mensaje || j.message || 'No se pudo contar con la foto.');
                $('cf_detalle').textContent = j.detalle;
                $('cf_calculo').textContent = j.calculo;
                $('cf_confianza').textContent = j.confianza;
                $('cf_confianza').className = { alta: 'text-emerald-700', media: 'text-amber-700', baja: 'text-rose-700' }[j.confianza] || '';
                $('cf_aviso').textContent = j.aviso; mostrar('cf_aviso', !!j.aviso);
                $('cf_cantidad').value = j.cantidad;
                mostrar('cf_resultado', true); mostrar('cf_usar', true);
            } catch (e) {
                $('cf_error').textContent = e.message; mostrar('cf_error', true);
            } finally {
                mostrar('cf_cargando', false);
            }
        }

        archivo.addEventListener('change', () => { if (archivo.files[0] && actual) contar(archivo.files[0]); archivo.value = ''; });
        modal.addEventListener('click', e => {
            const accion = e.target.closest('[data-cf]')?.dataset.cf;
            if (accion === 'cancelar' || e.target === modal) cerrar(null);
            if (accion === 'foto') archivo.click();
            if (accion === 'usar') {
                const v = parseFloat($('cf_cantidad').value);
                if (!(v >= 0)) { alert('Escribe una cantidad válida.'); return; }
                cerrar(v);
            }
        });

        window.contarConFoto = (id, nombre) => new Promise(resolver => {
            if (actual) actual.resolver(null);
            actual = { id, resolver };
            $('cf_producto').textContent = nombre;
            ['cf_vista', 'cf_cargando', 'cf_error', 'cf_resultado', 'cf_usar'].forEach(x => mostrar(x, false));
            modal.classList.remove('hidden'); modal.classList.add('flex');
            archivo.click();
        });
    })();
</script>
