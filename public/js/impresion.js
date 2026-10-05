/**
 * Impresión directa (sin vista previa) para los puntos de venta.
 * Pide al servidor que mande el comprobante a la impresora de caja; si el agente de impresión
 * no está conectado, devuelve false y la pantalla usa la impresión del navegador como siempre.
 *
 * Uso: <script src="/js/impresion.js" data-url="{{ url('impresion/comprobante') }}" data-csrf="{{ csrf_token() }}"></script>
 *      const ok = await TushpaImpresion.comprobante(idCpe);
 */
(function () {
    const tag = document.currentScript;
    const URL_BASE = tag.dataset.url;
    const CSRF = tag.dataset.csrf;

    window.TushpaImpresion = {
        async comprobante(id) {
            if (!id || !URL_BASE) return false;
            try {
                const r = await fetch(URL_BASE + '/' + id, {
                    method: 'POST',
                    headers: { Accept: 'application/json', 'X-CSRF-TOKEN': CSRF },
                });
                if (!r.ok) return false;
                const d = await r.json();
                return d.directa ? d : false;
            } catch (e) {
                return false;
            }
        },
    };
})();
