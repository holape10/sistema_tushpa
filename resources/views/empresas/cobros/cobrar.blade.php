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
        @media (max-width: 991px) { .panel-pago { border-left: none; padding-left: 0; border-top: 2px dashed #ccc; padding-top: 15px; margin-top: 10px; } }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">

        <!-- IZQUIERDA: datos del comprobante -->
        <div class="col-lg-5">
            <div class="box-x">
                <div class="box-x-h">
                    <span>Datos del comprobante</span>
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
                                <label>Nombre o razón social</label>
                                <input type="text" id="clinom" class="form-control input-sm" value="{{ !empty($pedido->ped_cli_nom) && !in_array($pedido->ped_cli_nom, ['CONSUMO EN SALON', 'PARA LLEVAR']) ? $pedido->ped_cli_nom : 'VENTA AL PORTADOR' }}">
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
                        Detalle:
                        @if ($mesa)
                            {{ $piso->pis_nom ?? '' }} / {{ $mesa->mes_nom }}
                        @else
                            {{ strtoupper($pedido->ped_tip) }} - {{ strtoupper($pedido->ped_cli_nom) }}
                        @endif
                    </span>
                    <button type="button" class="btn btn-primary btn-xs" onclick="alert('Las cuentas separadas aún no están construidas en el sistema nuevo.')">Cuentas Separadas</button>
                </div>
                <div class="box-x-b">
                    <div class="row">
                        <div class="col-md-7">
                            <table class="table table-bordered table-condensed tbl-det">
                                <thead>
                                    <tr><th>PRODUCTO</th><th style="width:60px">CANT.</th><th style="width:70px">PRECIO</th><th style="width:75px">TOTAL</th></tr>
                                </thead>
                                <tbody>
                                    @foreach ($detalle as $d)
                                        <tr>
                                            <td>{{ $d->descripcion }}@if ($d->item_obs)<br><small class="text-muted">{{ $d->item_obs }}</small>@endif</td>
                                            <td class="text-center">{{ rtrim(rtrim(number_format($d->cantidad_pendiente, 2), '0'), '.') }}</td>
                                            <td class="text-right">{{ number_format($d->ped_det_pre, 2) }}</td>
                                            <td class="text-right"><strong>{{ number_format($d->cantidad_pendiente * $d->ped_det_pre, 2) }}</strong></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            <p class="text-muted" style="font-size:11px;">Para agregar o quitar productos vuelve a la comanda (botón EDITAR de la mesa).</p>
                        </div>

                        <div class="col-md-5 panel-pago">
                            <div class="totales">
                                <div style="font-size:11pt; color:#d33;"><b>Por cobrar:</b> S/ <span id="lbl_total">{{ number_format($total, 2) }}</span></div>
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
                                <input type="text" class="form-control big" value="{{ number_format($total, 2, '.', '') }}" readonly style="color:#000; background:#f4f4f4;">
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
                                    <input type="number" step="any" id="mon_med_pag" class="form-control" value="{{ number_format($total, 2, '.', '') }}">
                                    <span class="input-group-btn">
                                        <button type="button" class="btn btn-primary" id="btn_agregar_medio"><i class="fas fa-plus-square"></i> AGREGAR</button>
                                    </span>
                                </div>
                                <table class="table table-condensed" style="margin:8px 0 0; font-size:12px;"><tbody id="tbody_med_pag"></tbody></table>
                                <small class="text-muted">Restante: S/ <span id="lbl_restante">{{ number_format($total, 2) }}</span>. Si no agregas ninguno, se cobra todo con el medio predeterminado.</small>
                            </div>

                            <hr style="margin:10px 0;">

                            <button type="button" id="btnRegistrar" class="btn btn-success btn-block" style="height:45px; font-size:11pt; font-weight:bold;">
                                REGISTRAR Y COBRAR
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
    const TOTAL = {{ number_format($total, 2, '.', '') }};
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

    // Buscar cliente
    function buscarCliente() {
        const doc = el('clinum').value.trim();
        el('msg_cliente').textContent = '';
        if (!doc) return;
        fetch(`/cobros/cliente/${encodeURIComponent(doc)}`)
            .then(r => r.json())
            .then(d => {
                if (d.error) { el('msg_cliente').textContent = d.error; return; }
                el('clinom').value = d.nom || '';
                el('clidir').value = d.dir || '--';
                el('clicor').value = d.cor || '';
                el('telefono').value = d.tel || '';
                if (d.tdicod) el('tdicod').value = d.tdicod;
            });
    }
    el('btn_buscar').addEventListener('click', buscarCliente);
    el('clinum').addEventListener('keypress', e => { if (e.key === 'Enter') { e.preventDefault(); buscarCliente(); } });

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

        btn.disabled = true;
        btn.textContent = 'PROCESANDO...';
        const restaurar = () => { btn.disabled = false; btn.textContent = 'REGISTRAR Y COBRAR'; };

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
            id_med_pag: contado ? medios.map(m => m.id) : [],
            mon_med_pag: contado ? medios.map(m => m.monto) : [],
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