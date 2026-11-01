{{-- JS: buscar cliente por DNI/RUC (va dentro de cobrar.blade.php) --}}
    // Buscar cliente (BD local y, si es RUC, la API de SUNAT)
    let buscando = false, ultimoBuscado = '';
    function mensajeCliente(texto, color) {
        el('msg_cliente').textContent = texto;
        el('msg_cliente').className = color === 'ok' ? 'text-success' : (color === 'info' ? 'text-info' : 'text-danger');
    }

    function buscarCliente() {
        const doc = el('clinum').value.trim();
        if (!doc || buscando) return;
        buscando = true;
        ultimoBuscado = doc;

        const btn = el('btn_buscar');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        mensajeCliente(doc.length === 11 ? 'Buscando RUC en SUNAT...' : 'Buscando cliente...', 'info');
        el('clinom').value = 'Buscando...';

        fetch(`{{ url('cobros/cliente') }}/${encodeURIComponent(doc)}`, { headers: { 'Accept': 'application/json' } })
            .then(r => r.json())
            .then(d => {
                if (d.error) {
                    el('clinom').value = '';
                    if (/^\d{11}$/.test(doc)) el('tdicod').value = '6';
                    mensajeCliente(d.error);
                    return;
                }
                llenarCliente(Object.assign({ num: doc }, d));
                if (d.tdicod === '6') mensajeCliente('✔ RUC encontrado: se cambió a FACTURA.', 'ok');
            })
            .catch(() => { el('clinom').value = ''; mensajeCliente('No se pudo consultar. Revisa tu conexión.'); })
            .finally(() => {
                buscando = false;
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-search"></i>';
            });
    }
    el('btn_buscar').addEventListener('click', buscarCliente);
    // Hotel: el huésped dio su RUC al ingresar → se busca para pasar a FACTURA
    if (/^\d{11}$/.test(el('clinum').value.trim())) buscarCliente();
    el('clinum').addEventListener('keypress', e => { if (e.key === 'Enter') { e.preventDefault(); buscarCliente(); } });
    // Al completar un RUC (11 dígitos) se busca solo; un DNI (8) se busca al salir del campo
    // (no al llegar a 8 dígitos, porque también puede ser el inicio de un RUC)
    el('clinum').addEventListener('input', function () {
        const v = this.value.trim();
        if (/^(10|15|17|20)\d{9}$/.test(v) && v !== ultimoBuscado) buscarCliente();
    });
    el('clinum').addEventListener('blur', function () {
        const v = this.value.trim();
        if (/^\d{8}$/.test(v) && v !== '00000000' && v !== ultimoBuscado) buscarCliente();
    });

