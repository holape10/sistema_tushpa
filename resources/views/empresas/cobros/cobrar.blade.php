<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Cobrar - Sistema Tushpa</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" href="{{ asset('imagenes/512.png') }}" type="image/png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@3.4.1/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body { background: #ecf0f5; font-family: Arial, sans-serif; padding-top: 15px; }
        .box-x { background: #fff; border-radius: 6px; box-shadow: 0 1px 3px rgba(0,0,0,.15); margin-bottom: 15px; overflow: hidden; }
        .box-x-h { background: #2c3e50; color: #fff; padding: 12px 15px; font-weight: bold; font-size: 13px; text-transform: uppercase; display: flex; justify-content: space-between; align-items: center; }
        .box-x-b { padding: 15px; }
        label { font-size: 12px; margin-bottom: 2px; }
        .form-group { margin-bottom: 10px; }
        .totales { background: #fff3cd; border-left: 4px solid #ffc107; padding: 8px 10px; border-radius: 5px; margin-bottom: 10px; }
        .tbl-det th { background: #2c3e50; color: #fff; font-size: 11px; text-align: center; }
        .tbl-det td { font-size: 12px; vertical-align: middle !important; }
        .big { height: 38px; font-size: 15pt; font-weight: bold; text-align: center; }
        .panel-pago { border-left: 2px dashed #ccc; padding-left: 15px; }
        .sug-wrap { position: relative; }
        .sug-list { position: absolute; z-index: 50; left: 0; right: 0; top: 100%; background: #fff; border: 1px solid #ccc; border-radius: 0 0 6px 6px;
                    box-shadow: 0 6px 14px rgba(0,0,0,.15); max-height: 260px; overflow-y: auto; list-style: none; margin: 0; padding: 0; display: none; }
        .sug-list li { padding: 6px 10px; cursor: pointer; font-size: 12px; border-bottom: 1px solid #f1f1f1; }
        .sug-list li small { color: #888; display: block; }
        .sug-list li.activo, .sug-list li:hover { background: #eaf2fb; }
        .sep-ctrl { display: flex; align-items: center; justify-content: center; gap: 3px; }
        .sep-ctrl button { width: 24px; height: 24px; padding: 0; border: none; border-radius: 4px; background: #8e44ad; color: #fff; font-weight: bold; }
        .sep-ctrl input { width: 46px; text-align: center; height: 24px; border: 1px solid #ccc; border-radius: 4px; }
        tr.sep-elegido td { background: #f5eefa !important; }
        @media (max-width: 991px) { .panel-pago { border-left: none; padding-left: 0; border-top: 2px dashed #ccc; padding-top: 15px; margin-top: 10px; } }
    </style>
</head>
<body>
<div class="container-fluid">
    @if (session('success'))
        <div class="alert alert-success text-center" style="margin-bottom:10px;"><i class="fas fa-print"></i> {{ session('success') }}</div>
    @endif
    <div class="row">

        <!-- IZQUIERDA: datos del comprobante -->
        <div class="col-lg-5">
            <div class="box-x">
                <div class="box-x-h">
                    <span>Datos del comprobante <small style="font-weight:normal; text-transform:none; background:#27ae60; padding:2px 8px; border-radius:10px; margin-left:6px;">Turno N° {{ $turno->turno }} abierto</small></span>
                    <label style="margin:0; font-weight:bold;">IMPRIMIR
                        <input type="checkbox" id="imprimir" checked>
                    </label>
                </div>
                <div class="box-x-b">
                    <div class="row">
                        <div class="col-sm-4">
                            <div class="form-group">
                                <label>Comprobante</label>
                                <select id="tdocod" class="form-control input-sm">
                                    @foreach ($comprobantes as $c)
                                        <option value="{{ $c->tdocod }}" @selected($c->tdocod == ($negocio->tdocod_pred ?? '13'))>{{ $c->tdodes }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="form-group">
                                <label>Estado pago</label>
                                <select id="estadopago" class="form-control input-sm">
                                    @foreach ($estadopagos as $e)
                                        <option value="{{ $e->cre_dia_id }}" data-tipo="{{ $e->cre_dia_tip }}" data-dias="{{ $e->cre_dia_fac }}">{{ $e->cre_dia_nom }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="form-group">
                                <label>X consumo</label>
                                <select id="consumo" class="form-control input-sm">
                                    <option value="0">NO</option>
                                    <option value="1">SI</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>F. emisión</label>
                                <input type="date" id="fecEmi" class="form-control input-sm" value="{{ now()->format('Y-m-d') }}">
                            </div>
                        </div>
                        <div class="col-sm-6" id="div_fecVen" style="display:none;">
                            <div class="form-group">
                                <label>F. vencimiento</label>
                                <input type="date" id="fecVen" class="form-control input-sm" value="{{ now()->addDay()->format('Y-m-d') }}">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-sm-3">
                            <div class="form-group">
                                <label>Tipo</label>
                                <select id="tdicod" class="form-control input-sm">
                                    @foreach ($documentos as $d)
                                        <option value="{{ $d->tdicod }}" @selected($d->tdicod == '1')>{{ $d->tdides }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="form-group">
                                <label>DNI / RUC</label>
                                <div class="input-group input-group-sm">
                                    <input type="text" id="clinum" class="form-control" value="00000000" maxlength="15">
                                    <span class="input-group-btn">
                                        <button type="button" class="btn btn-primary" id="btn_buscar"><i class="fas fa-search"></i></button>
                                    </span>
                                </div>
                                <small id="msg_cliente" class="text-danger"></small>
                            </div>
                        </div>
                        <div class="col-sm-5">
                            <div class="form-group">
                                <label>Nombre o razón social <small class="text-muted">(escribe para buscar)</small></label>
                                <div class="sug-wrap">
                                <input type="text" id="clinom" class="form-control input-sm" autocomplete="off" value="{{ !empty($pedido->ped_cli_nom) && !in_array($pedido->ped_cli_nom, ['CONSUMO EN SALON', 'PARA LLEVAR']) ? $pedido->ped_cli_nom : 'VENTA AL PORTADOR' }}">
                                <ul id="sug_clientes" class="sug-list"></ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-sm-4">
                            <div class="form-group">
                                <label>Dirección</label>
                                <input type="text" id="clidir" class="form-control input-sm" value="--">
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="form-group">
                                <label>Correo</label>
                                <input type="text" id="clicor" class="form-control input-sm">
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="form-group">
                                <label>Teléfono</label>
                                <input type="text" id="telefono" class="form-control input-sm">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Observaciones</label>
                        <textarea id="observaciones" rows="2" class="form-control" maxlength="100"></textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- DERECHA: detalle + cobro -->
        <div class="col-lg-7">
            <div class="box-x">
                <div class="box-x-h">
                    <span>
                        @if ($separadas)<span style="background:#8e44ad; padding:2px 8px; border-radius:10px; margin-right:6px;">CUENTA SEPARADA</span>@endif
                        Detalle:
                        @if ($mesa)
                            {{ $piso->pis_nom ?? '' }} / {{ $mesa->mes_nom }}
                        @else
                            {{ strtoupper($pedido->ped_tip) }} - {{ strtoupper($pedido->ped_cli_nom) }}
                        @endif
                    </span>
                    @if ($separadas)
                        <a href="{{ route('cobros.cobrar', $pedido->ped_id) }}" class="btn btn-default btn-xs">Cobrar todo junto</a>
                    @else
                        <a href="{{ route('cobros.separadas', $pedido->ped_id) }}" class="btn btn-primary btn-xs" style="background:#8e44ad; border-color:#8e44ad;">Cuentas Separadas</a>
                    @endif
                </div>
                <div class="box-x-b">
                    <div class="row">
                        <div class="col-md-7">
                            @if ($separadas)
                                <div style="display:flex; gap:6px; margin-bottom:6px;">
                                    <span style="font-size:12px; color:#555; flex:1;">Elige qué productos paga <strong>esta</strong> persona:</span>
                                    <button type="button" class="btn btn-default btn-xs" id="sep_todo">Todo</button>
                                    <button type="button" class="btn btn-default btn-xs" id="sep_nada">Nada</button>
                                </div>
                            @endif
                            <table class="table table-bordered table-condensed tbl-det">
                                <thead>
                                    <tr><th>PRODUCTO</th><th style="width:60px">{{ $separadas ? 'PEND.' : 'CANT.' }}</th>
                                        @if ($separadas)<th style="width:110px">COBRAR</th>@endif
                                        <th style="width:70px">PRECIO</th><th style="width:75px">TOTAL</th></tr>
                                </thead>
                                <tbody>
                                    @foreach ($detalle as $d)
                                        <tr data-clave="{{ $d->clave }}" data-precio="{{ $d->ped_det_pre }}" data-max="{{ $d->cantidad_pendiente }}">
                                            <td>{{ $d->descripcion }}@if ($d->item_obs)<br><small class="text-muted">{{ $d->item_obs }}</small>@endif</td>
                                            <td class="text-center">{{ rtrim(rtrim(number_format($d->cantidad_pendiente, 2), '0'), '.') }}</td>
                                            @if ($separadas)
                                                <td><div class="sep-ctrl">
                                                    <button type="button" class="sep-menos">−</button>
                                                    <input type="number" class="sep-cant" min="0" max="{{ $d->cantidad_pendiente }}" step="1" value="0">
                                                    <button type="button" class="sep-mas">+</button>
                                                </div></td>
                                            @endif
                                            <td class="text-right">{{ number_format($d->ped_det_pre, 2) }}</td>
                                            <td class="text-right"><strong class="linea-total">{{ number_format($separadas ? 0 : $d->cantidad_pendiente * $d->ped_det_pre, 2) }}</strong></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            @if ($cuentasPrevias->isNotEmpty())
                                <div style="font-size:11px; color:#555; background:#f7f7f7; padding:6px 8px; border-radius:5px; margin-bottom:6px;">
                                    <strong>Ya cobrado de esta mesa:</strong>
                                    @foreach ($cuentasPrevias as $cp)
                                        <a href="{{ route('cobros.voucher', $cp->IdCpe_cabecera) }}" target="_blank">{{ $cp->serdoc }}-{{ $cp->numdoc }}</a> ({{ $cp->ccanom }} · S/ {{ number_format($cp->ccaitv, 2) }}){{ $loop->last ? '' : ',' }}
                                    @endforeach
                                </div>
                            @endif
                            <p class="text-muted" style="font-size:11px;">Para agregar o quitar productos vuelve a la comanda (botón EDITAR de la mesa).</p>
                        </div>

                        <div class="col-md-5 panel-pago">
                            <div class="totales">
                                <div style="font-size:11pt; color:#d33;"><b>Por cobrar:</b> S/ <span id="lbl_total">{{ number_format($separadas ? 0 : $total, 2) }}</span>
                                    @if ($separadas)<div style="font-size:9pt; color:#666;">Pendiente en la mesa: S/ {{ number_format($total, 2) }}</div>@endif</div>
                            </div>

                            <div class="row">
                                <div class="col-xs-6">
                                    <label>PAGA CON S/:</label>
                                    <input type="number" step="any" id="paga" class="form-control big" value="0.00" style="color:#555;">
                                </div>
                                <div class="col-xs-6">
                                    <label>VUELTO S/:</label>
                                    <input type="text" id="vuelto" class="form-control big" value="0.00" readonly style="color:#d33; background:#f4f4f4;">
                                </div>
                            </div>

                            <div class="form-group" style="margin-top:10px;">
                                <label>TOTAL COMP.</label>
                                <input type="text" id="total_comp" class="form-control big" value="{{ number_format($separadas ? 0 : $total, 2, '.', '') }}" readonly style="color:#000; background:#f4f4f4;">
                            </div>

                            <hr style="margin:8px 0;">

                            <div id="bloque_medios">
                                <label>MEDIOS DE PAGO</label>
                                <select id="med_pag" class="form-control input-sm" style="margin-bottom:5px;">
                                    @foreach ($mediospagos as $m)
                                        <option value="{{ $m->id_med_pag }}" data-nom="{{ $m->nom_med_pag }}" @selected($m->predeterminado == '1')>{{ $m->nom_med_pag }}</option>
                                    @endforeach
                                </select>
                                <div class="input-group input-group-sm">
                                    <input type="number" step="any" id="mon_med_pag" class="form-control" value="{{ number_format($separadas ? 0 : $total, 2, '.', '') }}">
                                    <span class="input-group-btn">
                                        <button type="button" class="btn btn-primary" id="btn_agregar_medio"><i class="fas fa-plus-square"></i> AGREGAR</button>
                                    </span>
                                </div>
                                <table class="table table-condensed" style="margin:8px 0 0; font-size:12px;"><tbody id="tbody_med_pag"></tbody></table>
                                <small class="text-muted">Restante: S/ <span id="lbl_restante">{{ number_format($separadas ? 0 : $total, 2) }}</span>. Si no agregas ninguno, se cobra todo con el medio seleccionado arriba.</small>
                            </div>

                            <hr style="margin:10px 0;">

                            <button type="button" id="btnRegistrar" class="btn btn-success btn-block" style="height:45px; font-size:11pt; font-weight:bold;">
                                {{ $separadas ? 'COBRAR ESTA CUENTA' : 'REGISTRAR Y COBRAR' }}
                            </button>
                            <a href="{{ route('comandas.seleccion') }}" class="btn btn-default btn-block" style="margin-top:8px; font-weight:bold;">SALIR</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
    const SEPARADAS = {{ $separadas ? 'true' : 'false' }};
    const TDOCOD_PRED = '{{ $negocio->tdocod_pred ?? '13' }}';
    let TOTAL = {{ $separadas ? 0 : number_format($total, 2, '.', '') }};
    const PED_ID = {{ $pedido->ped_id }};
    let medios = [];

    const el = id => document.getElementById(id);
    const fmt = n => (Math.round(n * 100) / 100).toFixed(2);

    // Comprobante -> tipo de documento de identidad (como tu sistema viejo)
    el('tdocod').addEventListener('change', function () {
        if (this.value === '01') el('tdicod').value = '6';
        if (this.value === '03') el('tdicod').value = '1';
    });

    // Estado de pago: contado muestra medios; crédito muestra vencimiento
    el('estadopago').addEventListener('change', function () {
        const opt = this.selectedOptions[0];
        const contado = opt.dataset.tipo === 'CONTADO';
        el('bloque_medios').style.display = contado ? 'block' : 'none';
        el('div_fecVen').style.display = contado ? 'none' : 'block';

        if (!contado) {
            const dias = parseInt(opt.dataset.dias) || 1;
            const f = new Date(el('fecEmi').value + 'T00:00:00');
            f.setDate(f.getDate() + dias);
            el('fecVen').value = f.toISOString().slice(0, 10);
        }
    });

    // Vuelto
    el('paga').addEventListener('input', function () {
        el('vuelto').value = fmt(Math.max(0, (parseFloat(this.value) || 0) - TOTAL));
    });

    // Medios de pago
    function renderMedios() {
        const tb = el('tbody_med_pag');
        tb.innerHTML = '';
        medios.forEach((m, i) => {
            tb.insertAdjacentHTML('beforeend',
                `<tr><td><span class="label label-success">${m.nom}</span> S/ ${fmt(m.monto)}</td>
                 <td style="width:30px"><button type="button" class="btn btn-danger btn-xs" onclick="quitarMedio(${i})">&times;</button></td></tr>`);
        });
        const suma = medios.reduce((a, m) => a + m.monto, 0);
        el('lbl_restante').textContent = fmt(TOTAL - suma);
        el('mon_med_pag').value = fmt(Math.max(0, TOTAL - suma));
    }
    function quitarMedio(i) { medios.splice(i, 1); renderMedios(); }

    el('btn_agregar_medio').addEventListener('click', function () {
        const sel = el('med_pag');
        const monto = parseFloat(el('mon_med_pag').value) || 0;
        if (monto <= 0) return;
        if (medios.some(m => m.id == sel.value)) { alert('EL MEDIO DE PAGO YA SE ENCUENTRA AGREGADO'); return; }
        medios.push({ id: sel.value, nom: sel.selectedOptions[0].dataset.nom, monto: monto });
        renderMedios();
    });

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

    // ---------- Cuentas separadas ----------
    function seleccionSeparada() {
        const sel = {};
        document.querySelectorAll('tr[data-clave]').forEach(tr => {
            const cant = parseFloat(tr.querySelector('.sep-cant')?.value) || 0;
            if (cant > 0) sel[tr.dataset.clave] = cant;
        });
        return sel;
    }

    function recalcular() {
        let total = 0;
        document.querySelectorAll('tr[data-clave]').forEach(tr => {
            const inp = tr.querySelector('.sep-cant');
            const max = parseFloat(tr.dataset.max);
            let cant = Math.min(Math.max(parseFloat(inp.value) || 0, 0), max);
            inp.value = cant;
            const linea = Math.round(cant * parseFloat(tr.dataset.precio) * 100) / 100;
            tr.querySelector('.linea-total').textContent = fmt(linea);
            tr.classList.toggle('sep-elegido', cant > 0);
            total += linea;
        });
        TOTAL = Math.round(total * 100) / 100;
        el('lbl_total').textContent = fmt(TOTAL);
        el('total_comp').value = fmt(TOTAL);
        medios = [];          // al cambiar el total se vuelven a armar los medios de pago
        renderMedios();
        el('vuelto').value = fmt(Math.max(0, (parseFloat(el('paga').value) || 0) - TOTAL));
    }

    if (SEPARADAS) {
        document.addEventListener('click', e => {
            const tr = e.target.closest('tr[data-clave]');
            if (!tr) return;
            const inp = tr.querySelector('.sep-cant');
            if (e.target.closest('.sep-mas')) { inp.value = (parseFloat(inp.value) || 0) + 1; recalcular(); }
            if (e.target.closest('.sep-menos')) { inp.value = (parseFloat(inp.value) || 0) - 1; recalcular(); }
        });
        document.addEventListener('change', e => { if (e.target.classList.contains('sep-cant')) recalcular(); });
        el('sep_todo').addEventListener('click', () => { document.querySelectorAll('tr[data-clave]').forEach(tr => tr.querySelector('.sep-cant').value = tr.dataset.max); recalcular(); });
        el('sep_nada').addEventListener('click', () => { document.querySelectorAll('.sep-cant').forEach(i => i.value = 0); recalcular(); });
    }

    // ---------- Comprobante según el cliente: RUC = factura; si no, el predeterminado ----------
    function aplicarComprobante(tdicod) {
        const tieneFactura = !!el('tdocod').querySelector('option[value="01"]');
        if (tdicod === '6' && tieneFactura) {
            el('tdocod').value = '01';
            return 'FACTURA';
        }
        // Un DNI no puede llevar factura: vuelve al comprobante predeterminado (o boleta si el predeterminado es factura)
        if (el('tdocod').value === '01') el('tdocod').value = TDOCOD_PRED === '01' ? '03' : TDOCOD_PRED;
        return null;
    }

    function llenarCliente(c) {
        el('clinum').value = c.num ?? el('clinum').value;
        el('clinom').value = c.nom || '';
        el('clidir').value = c.dir || '--';
        el('clicor').value = c.cor || '';
        el('telefono').value = c.tel || '';
        if (c.tdicod) el('tdicod').value = c.tdicod;
        ultimoBuscado = el('clinum').value.trim();
        const comp = aplicarComprobante(c.tdicod);
        mensajeCliente(comp ? '✔ Cliente con RUC: se cambió a FACTURA.' : '✔ Cliente seleccionado.', 'ok');
    }

    // ---------- Búsqueda predictiva por nombre (base de datos propia) ----------
    const lista = el('sug_clientes');
    let sugerencias = [], activo = -1, timerSug = null, ctrlSug = null;

    function cerrarSugerencias() { lista.style.display = 'none'; activo = -1; }

    function pintarSugerencias() {
        lista.innerHTML = '';
        if (!sugerencias.length) { cerrarSugerencias(); return; }
        sugerencias.forEach((c, i) => {
            const li = document.createElement('li');
            li.className = i === activo ? 'activo' : '';
            const nom = document.createElement('span'); nom.textContent = c.nom;
            const det = document.createElement('small'); det.textContent = (c.tdicod === '6' ? 'RUC ' : 'DOC ') + c.num + (c.dir && c.dir !== '--' ? ' · ' + c.dir : '');
            li.append(nom, det);
            li.addEventListener('mousedown', ev => { ev.preventDefault(); llenarCliente(c); cerrarSugerencias(); });
            lista.appendChild(li);
        });
        lista.style.display = 'block';
    }

    el('clinom').addEventListener('input', function () {
        clearTimeout(timerSug);
        const q = this.value.trim();
        if (q.length < 2 || q === 'VENTA AL PORTADOR') { cerrarSugerencias(); return; }
        timerSug = setTimeout(() => {
            ctrlSug?.abort();
            ctrlSug = new AbortController();
            fetch(`{{ route('cobros.clientes') }}?q=${encodeURIComponent(q)}`, { headers: { 'Accept': 'application/json' }, signal: ctrlSug.signal })
                .then(r => r.json())
                .then(data => { sugerencias = data; activo = data.length ? 0 : -1; pintarSugerencias(); })
                .catch(() => {});
        }, 250);
    });
    el('clinom').addEventListener('keydown', function (e) {
        if (lista.style.display !== 'block') return;
        if (e.key === 'ArrowDown') { e.preventDefault(); activo = Math.min(activo + 1, sugerencias.length - 1); pintarSugerencias(); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); activo = Math.max(activo - 1, 0); pintarSugerencias(); }
        else if (e.key === 'Enter') { e.preventDefault(); if (sugerencias[activo]) { llenarCliente(sugerencias[activo]); cerrarSugerencias(); } }
        else if (e.key === 'Escape') { cerrarSugerencias(); }
    });
    el('clinom').addEventListener('blur', () => setTimeout(cerrarSugerencias, 150));
    el('clinom').addEventListener('focus', function () { if (this.value === 'VENTA AL PORTADOR') this.select(); });

    // Registrar
    el('btnRegistrar').addEventListener('click', async function () {
        const btn = this;
        const contado = el('estadopago').selectedOptions[0].dataset.tipo === 'CONTADO';

        if (contado && medios.length) {
            const suma = medios.reduce((a, m) => a + m.monto, 0);
            if (Math.abs(suma - TOTAL) > 0.01) {
                alert(`¡REVISAR MEDIO DE PAGO!\n\nTotal a cobrar: S/ ${fmt(TOTAL)}\nIngresado: S/ ${fmt(suma)}\nDiferencia: S/ ${fmt(TOTAL - suma)}`);
                return;
            }
        }

        if (SEPARADAS && TOTAL <= 0) { alert('Elige qué productos se cobran en esta cuenta.'); return; }
        btn.disabled = true;
        btn.textContent = 'PROCESANDO...';
        const restaurar = () => { btn.disabled = false; btn.textContent = SEPARADAS ? 'COBRAR ESTA CUENTA' : 'REGISTRAR Y COBRAR'; };

        const body = {
            ped_id: PED_ID,
            tdocod: el('tdocod').value,
            estadopago: el('estadopago').value,
            fecEmi: el('fecEmi').value,
            fecVen: el('fecVen').value,
            consumo: el('consumo').value,
            tdicod: el('tdicod').value,
            clinum: el('clinum').value.trim(),
            clinom: el('clinom').value.trim(),
            clidir: el('clidir').value,
            clicor: el('clicor').value,
            telefono: el('telefono').value,
            observaciones: el('observaciones').value,
            paga: parseFloat(el('paga').value) || 0,
            imprimir: el('imprimir').checked ? 1 : 0,
            // Si no agregó medios, se cobra todo con el medio que está seleccionado en la lista (no con el predeterminado)
            id_med_pag: contado ? (medios.length ? medios.map(m => m.id) : [el('med_pag').value]) : [],
            mon_med_pag: contado ? (medios.length ? medios.map(m => m.monto) : [TOTAL]) : [],
            // Cuentas separadas: { clave: cantidad } de lo que paga esta persona (null = todo lo pendiente)
            separadas: SEPARADAS ? seleccionSeparada() : null,
        };

        try {
            const res = await fetch("{{ route('cobros.registrar') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify(body)
            });
            const data = await res.json();

            if (res.status === 422) { alert(Object.values(data.errors)[0][0]); restaurar(); return; }
            if (data.estado === 'success') { window.location.href = data.redirect; return; }
            alert(data.mensaje || 'No se pudo registrar el cobro.');
            restaurar();
        } catch (e) {
            alert('Error de conexión con el servidor.');
            restaurar();
        }
    });
</script>
</body>
</html>