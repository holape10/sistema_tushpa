<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Hotel - Sistema Tushpa</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@3.4.1/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body { font-family: Arial, sans-serif; background-color: #f8f9fa; }
        .container-fluid { padding-top: 15px; padding-bottom: 15px; }
        .cabecera { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 10px 20px; margin-bottom: 10px; flex-wrap: wrap; }
        .cabecera img { height: 70px; object-fit: contain; }
        .cabecera h2 { color: #8e44ad; margin: 0; font-size: 2.0em; text-align: center; flex: 1; }
        .cabecera .reloj { display: block; font-size: .5em; color: #555; font-family: monospace; margin-top: 4px; }
        .acciones { display: flex; gap: 6px; flex-wrap: wrap; justify-content: flex-end; }
        .acciones .btn { font-weight: bold; border-radius: 20px; }

        .resumen { display: flex; gap: 8px; justify-content: center; flex-wrap: wrap; margin-bottom: 12px; }
        .resumen span { padding: 6px 14px; border-radius: 20px; color: #fff; font-weight: bold; font-size: .95em; }

        .pisos-selector { display: flex; overflow-x: auto; white-space: nowrap; padding: 10px 0; margin-bottom: 15px; background-color: #e9ecef; border-radius: 8px; }
        .pisos-selector .btn { flex-shrink: 0; margin: 0 5px; font-size: 1.15em; padding: 10px 22px; border-radius: 8px; background-color: #6c757d; color: #fff; border: none; }
        .pisos-selector .btn.active, .pisos-selector .btn:hover { background-color: #8e44ad; }

        .grid { display: flex; flex-wrap: wrap; justify-content: center; gap: 12px; padding: 10px; }
        .hab { position: relative; width: 170px; min-height: 125px; border-radius: 12px; color: #fff; border: none; cursor: pointer; padding: 8px;
               display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center;
               box-shadow: 0 4px 8px rgba(0,0,0,.15); line-height: 1.2; transition: transform .15s; }
        .hab:hover { transform: translateY(-3px); }
        .hab .nom { font-size: 1.45em; font-weight: bold; }
        .hab .tip { font-size: .75em; opacity: .9; text-transform: uppercase; }
        .hab .timer { font-family: monospace; font-size: 1.35em; font-weight: bold; margin-top: 4px; }
        .hab .cli { font-size: .75em; max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .hab .pago { position: absolute; top: -8px; right: -8px; font-size: .7em; font-weight: 900; padding: 3px 7px; border-radius: 10px; background: #fff; box-shadow: 0 2px 6px rgba(0,0,0,.3); }
        .hab.libre { background: #52BE80; }
        .hab.ocupado { background: #2980b9; }
        .hab.por-vencer { background: #f39c12; }
        .hab.vencido { background: #e74c3c; animation: parpadeo 1s infinite; }
        .hab.limpieza { background: #95a5a6; }
        .hab.mantenimiento { background: #34495e; }
        @keyframes parpadeo { 50% { opacity: .6; } }

        .alertas { max-width: 900px; margin: 0 auto 10px; }
        .alertas .alert { margin-bottom: 6px; padding: 8px 14px; font-weight: bold; cursor: pointer; }

        .btn-pv { background: #e67e22; color: #fff; font-size: 1.6em; padding: 16px 36px; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,.2); border: none; }
        .btn-pv:hover, .btn-pv:focus { background: #d35400; color: #fff; text-decoration: none; }

        .tiempo-info { display: flex; gap: 8px; text-align: center; margin-bottom: 12px; }
        .tiempo-info > div { flex: 1; background: #f4f6f7; border-radius: 8px; padding: 6px; }
        .tiempo-info small { display: block; color: #777; font-size: .75em; }
        .tiempo-info strong { font-size: 1.05em; }
        #det_restante { font-family: monospace; font-size: 1.3em; }
        .caja { border: 1px solid #e5e5e5; border-radius: 8px; padding: 10px; margin-bottom: 10px; }
        .caja h5 { margin: 0 0 8px; font-weight: bold; color: #555; }
        .grid-productos { display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 6px; max-height: 200px; overflow-y: auto; margin-top: 6px; }
        .product-item-kiosko { border: 1px solid #ddd; border-radius: 8px; padding: 6px; cursor: pointer; background: #fff; font-size: .85em; text-align: center; }
        .product-item-kiosko:hover { background: #f5eef8; border-color: #8e44ad; }
        .product-price-kiosko { font-weight: bold; color: #8e44ad; }
        .product-stock-kiosko { color: #999; font-size: .85em; }
        .btns-hab { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
        .btns-hab .btn { font-weight: 600; padding: 10px 6px; border: none; color: #fff; }
        .tabla-items { font-size: .9em; margin-bottom: 0; }
        .cobrado { color: #27ae60; font-size: .8em; }

        @media (max-width: 768px) {
            .cabecera h2 { font-size: 1.5em; }
            .cabecera img { height: 50px; }
            .hab { width: 47%; min-height: 110px; }
            .btn-pv { width: 100%; font-size: 1.3em; }
            .modal-dialog { margin: 10px; }
        }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="cabecera">
        <img src="{{ asset('imagenes/logo.png') }}" alt="Logo">
        <h2><i class="fas fa-bed"></i> Hotel · Habitaciones <span class="reloj" id="reloj"></span></h2>
        <div class="acciones">
            @if ($esAdmin)
                <button type="button" class="btn btn-default" onclick="abrirConfig()"><i class="fas fa-gear"></i> Configurar</button>
            @endif
            @if ($puedeCobrar)
                <a href="{{ route('turnos.index') }}" class="btn btn-default"><i class="fas fa-cash-register"></i> Caja</a>
            @endif
            <a href="{{ route('dashboard') }}" class="btn btn-default"><i class="fas fa-arrow-left"></i> Volver</a>
        </div>
    </div>

    @if (session('error'))
        <div class="alert alert-warning text-center">{{ session('error') }}</div>
    @endif
    @if (session('success'))
        <div class="alert alert-success text-center aviso-temporal"><i class="fas fa-print"></i> {{ session('success') }}</div>
    @endif
    <div id="toast" style="display:none; position:fixed; left:50%; bottom:24px; transform:translateX(-50%); z-index:3000; background:#2c3e50; color:#fff; padding:12px 20px; border-radius:10px; box-shadow:0 6px 18px rgba(0,0,0,.25); font-weight:bold;"></div>

    <div class="resumen">
        <span style="background:#52BE80;">Libres: <b id="n_libre">0</b></span>
        <span style="background:#2980b9;">Ocupadas: <b id="n_ocupado">0</b></span>
        <span style="background:#f39c12;">Por vencer (&le; <span id="lbl_aviso"></span> min): <b id="n_vencer">0</b></span>
        <span style="background:#e74c3c;">Tiempo cumplido: <b id="n_vencido">0</b></span>
        <span style="background:#95a5a6;">Limpieza: <b id="n_limpieza">0</b></span>
    </div>

    <div class="alertas" id="alertas"></div>
    <div class="pisos-selector" id="pisos"></div>
    <div class="grid" id="grid"><p class="text-muted">Cargando habitaciones...</p></div>

    @if ($puedeCobrar)
        <div class="text-center" style="margin:25px 0;">
            <a href="{{ route('cobros.directa', ['desde' => 'hotel']) }}" class="btn btn-pv"><i class="fas fa-cash-register"></i> PUNTO DE VENTA</a>
        </div>
    @endif
</div>

{{-- ============ Ingreso (habitación libre) ============ --}}
<div class="modal fade" id="m_ingreso" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background:#52BE80; color:#fff;">
                <button type="button" class="close" data-dismiss="modal" style="color:#fff; opacity:1;">&times;</button>
                <h4 class="modal-title"><i class="fas fa-door-open"></i> Ingreso · Habitación <span id="ing_hab"></span></h4>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-xs-12 col-sm-7 form-group">
                        <label>Servicio</label>
                        <select id="ing_servicio" class="form-control"></select>
                    </div>
                    <div class="col-xs-6 col-sm-2 form-group">
                        <label>Cant.</label>
                        <input type="number" id="ing_cantidad" class="form-control" value="1" min="1" step="1">
                    </div>
                    <div class="col-xs-6 col-sm-3 form-group">
                        <label>Precio S/</label>
                        <input type="number" id="ing_precio" class="form-control" min="0" step="0.10">
                    </div>
                </div>
                <div class="alert alert-info" style="padding:8px 12px;" id="ing_resumen"></div>
                <div class="row">
                    <div class="col-xs-12 col-sm-5 form-group">
                        <label>DNI / RUC <small class="text-muted">(opcional)</small></label>
                        <div class="input-group">
                            <input type="text" id="ing_doc" class="form-control" maxlength="15">
                            <span class="input-group-btn"><button type="button" class="btn btn-primary" id="ing_buscar"><i class="fas fa-search"></i></button></span>
                        </div>
                    </div>
                    <div class="col-xs-9 col-sm-5 form-group">
                        <label>Huésped</label>
                        <input type="text" id="ing_cliente" class="form-control" maxlength="150" placeholder="CLIENTE HOSPEDAJE">
                    </div>
                    <div class="col-xs-3 col-sm-2 form-group">
                        <label>Pers.</label>
                        <input type="number" id="ing_personas" class="form-control" value="1" min="1" max="20">
                    </div>
                </div>
                <small id="ing_msg" class="text-info"></small>
            </div>
            <div class="modal-footer">
                @if ($puedeCobrar)
                    <label class="pull-left" style="margin-top:8px;"><input type="checkbox" id="ing_cobrar"> Cobrar ahora</label>
                @endif
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success" id="ing_guardar" style="font-weight:bold;"><i class="fas fa-key"></i> REGISTRAR INGRESO</button>
            </div>
        </div>
    </div>
</div>

{{-- ============ Habitación ocupada ============ --}}
<div class="modal fade" id="m_hab" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" id="det_cabecera" style="background:#2980b9; color:#fff;">
                <button type="button" class="close" data-dismiss="modal" style="color:#fff; opacity:1;">&times;</button>
                <h4 class="modal-title"><i class="fas fa-bed"></i> Habitación <span id="det_hab"></span> · <span id="det_cliente"></span></h4>
            </div>
            <div class="modal-body">
                <div class="tiempo-info">
                    <div><small>Ingreso</small><strong id="det_inicio"></strong></div>
                    <div><small>Salida</small><strong id="det_fin"></strong></div>
                    <div><small id="det_lbl_restante">Restante</small><strong id="det_restante"></strong></div>
                </div>

                <div class="caja">
                    <h5><i class="fas fa-clock"></i> Agregar tiempo (horas extra)</h5>
                    <div class="row">
                        <div class="col-xs-12 col-sm-6"><select id="ext_servicio" class="form-control input-sm"></select></div>
                        <div class="col-xs-4 col-sm-2"><input type="number" id="ext_cantidad" class="form-control input-sm" value="1" min="1" step="1"></div>
                        <div class="col-xs-4 col-sm-2"><input type="number" id="ext_precio" class="form-control input-sm" min="0" step="0.10"></div>
                        <div class="col-xs-4 col-sm-2"><button type="button" class="btn btn-warning btn-sm btn-block" id="ext_guardar" style="font-weight:bold;">+ Tiempo</button></div>
                    </div>
                </div>

                <div class="caja">
                    <h5><i class="fas fa-wine-bottle"></i> Consumo a la habitación</h5>
                    <input type="text" id="con_buscar" class="form-control input-sm" placeholder="Buscar producto (gaseosa, cerveza, desayuno...)">
                    <div id="con_productos" class="grid-productos"></div>
                </div>

                <div class="caja" style="padding:0;">
                    <table class="table table-condensed tabla-items">
                        <thead><tr><th>Cant.</th><th>Descripción</th><th class="text-right">Importe</th></tr></thead>
                        <tbody id="det_items"></tbody>
                        <tfoot>
                            <tr><th colspan="2" class="text-right">Total</th><th class="text-right" id="det_total"></th></tr>
                            <tr><th colspan="2" class="text-right text-danger">Por cobrar</th><th class="text-right text-danger" id="det_pendiente"></th></tr>
                        </tfoot>
                    </table>
                </div>

                <div class="btns-hab">
                    @if ($puedeCobrar)
                        <a class="btn" style="background:#e74c3c;" id="det_cobrar"><i class="fas fa-money-bill"></i> Cobrar</a>
                    @endif
                    <button type="button" class="btn" style="background:#3498db;" id="det_precuenta"><i class="fas fa-file-invoice"></i> Precuenta</button>
                    <button type="button" class="btn" style="background:#8e44ad;" id="det_salida"><i class="fas fa-door-closed"></i> Dar salida</button>
                    @if ($esAdmin)
                        <button type="button" class="btn" style="background:#7f8c8d;" id="det_anular"><i class="fas fa-ban"></i> Anular ingreso</button>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ============ Limpieza / mantenimiento ============ --}}
<div class="modal fade" id="m_estado" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Habitación <span id="est_hab"></span></h4>
            </div>
            <div class="modal-body">
                <p id="est_texto" class="text-muted"></p>
                <button type="button" class="btn btn-success btn-block" data-estado="Libre" style="font-weight:bold;"><i class="fas fa-check"></i> Lista / Disponible</button>
                <button type="button" class="btn btn-default btn-block" data-estado="Limpieza"><i class="fas fa-broom"></i> En limpieza</button>
                <button type="button" class="btn btn-default btn-block" data-estado="Mantenimiento"><i class="fas fa-screwdriver-wrench"></i> Mantenimiento</button>
            </div>
        </div>
    </div>
</div>

@if ($esAdmin)
{{-- ============ Configuración ============ --}}
<div class="modal fade" id="m_config" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><i class="fas fa-gear"></i> Configurar hotel</h4>
            </div>
            <div class="modal-body">
                <ul class="nav nav-tabs" style="margin-bottom:12px;">
                    <li class="active"><a href="#tab_hab" data-toggle="tab">Habitaciones</a></li>
                    <li><a href="#tab_serv" data-toggle="tab">Servicios de tiempo</a></li>
                    <li><a href="#tab_aviso" data-toggle="tab">Aviso</a></li>
                </ul>
                <div class="tab-content">
                    <div class="tab-pane active" id="tab_hab">
                        <form id="f_hab" class="row" autocomplete="off">
                            <input type="hidden" name="hab_id">
                            <div class="col-sm-3 form-group"><label>N° / nombre</label><input name="hab_nom" class="form-control input-sm" required maxlength="50" placeholder="101"></div>
                            <div class="col-sm-3 form-group"><label>Tipo</label><input name="hab_tip" class="form-control input-sm" maxlength="40" placeholder="MATRIMONIAL" list="tipos_hab"></div>
                            <div class="col-sm-2 form-group"><label>Piso</label><input name="hab_piso" class="form-control input-sm" required maxlength="30" value="PISO 1"></div>
                            <div class="col-sm-2 form-group"><label>Nota</label><input name="hab_obs" class="form-control input-sm" maxlength="150" placeholder="TV, jacuzzi"></div>
                            <div class="col-sm-2 form-group"><label>&nbsp;</label><button class="btn btn-primary btn-sm btn-block">Guardar</button></div>
                        </form>
                        <datalist id="tipos_hab"><option>SIMPLE</option><option>DOBLE</option><option>MATRIMONIAL</option><option>TRIPLE</option><option>SUITE</option></datalist>
                        <table class="table table-condensed table-striped"><thead><tr><th>Habitación</th><th>Tipo</th><th>Piso</th><th>Estado</th><th></th></tr></thead><tbody id="cfg_habs"></tbody></table>
                    </div>
                    <div class="tab-pane" id="tab_serv">
                        <p class="text-muted" style="font-size:.9em;">Cada servicio da un tiempo: <b>HABITACIÓN 2 HORAS</b> (2 h), <b>HABITACIÓN 1 DÍA</b> (24 h), <b>HORA EXTRA</b> (1 h). Se guardan como productos (categoría HOSPEDAJE) y se cobran con el IGV de la sucursal.</p>
                        <form id="f_serv" class="row" autocomplete="off">
                            <input type="hidden" name="IdProducto">
                            <div class="col-sm-4 form-group"><label>Nombre</label><input name="pronom" class="form-control input-sm" required maxlength="150" placeholder="HABITACION 3 HORAS"></div>
                            <div class="col-sm-2 form-group"><label>Precio S/</label><input name="propun" type="number" step="0.10" min="0" class="form-control input-sm" required></div>
                            <div class="col-sm-2 form-group"><label>Horas</label><input name="horas" type="number" min="0" class="form-control input-sm" required value="1"></div>
                            <div class="col-sm-2 form-group"><label>Minutos</label><input name="min" type="number" min="0" max="59" class="form-control input-sm" value="0"></div>
                            <div class="col-sm-2 form-group"><label>&nbsp;</label><button class="btn btn-primary btn-sm btn-block">Guardar</button></div>
                        </form>
                        <table class="table table-condensed table-striped"><thead><tr><th>Servicio</th><th>Tiempo</th><th class="text-right">Precio</th><th></th></tr></thead><tbody id="cfg_servs"></tbody></table>
                    </div>
                    <div class="tab-pane" id="tab_aviso">
                        <div class="form-group" style="max-width:320px;">
                            <label>Avisar cuando falten (minutos)</label>
                            <input type="number" id="cfg_aviso" class="form-control" min="1" max="240">
                            <small class="text-muted">Se guarda en este equipo. La tarjeta se pone naranja y suena un aviso.</small>
                        </div>
                        <label><input type="checkbox" id="cfg_sonido"> Sonar alerta</label>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endif

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@3.4.1/dist/js/bootstrap.min.js"></script>
<script>
    const URL = {
        estado: "{{ route('hotel.estado') }}", detalle: "{{ url('hotel/estadia') }}/", ingresar: "{{ route('hotel.ingresar') }}",
        extender: "{{ route('hotel.extender') }}", consumo: "{{ route('hotel.consumo') }}", salida: "{{ route('hotel.salida') }}",
        anular: "{{ route('hotel.anular') }}", cambiarEstado: "{{ route('hotel.cambiar_estado') }}", habitacion: "{{ route('hotel.habitacion') }}",
        habitaciones: "{{ url('hotel/habitaciones') }}/", servicio: "{{ route('hotel.servicio') }}", servicios: "{{ url('hotel/servicios') }}/",
        cobrar: "{{ url('cobrarmesa') }}/", precuenta: "{{ url('comandas/precuenta') }}/", precuentaImp: "{{ url('impresion/precuenta') }}/",
        cliente: "{{ url('cobros/cliente') }}/", productos: "{{ route('comandas.search_products') }}",
    };
    const CSRF = document.querySelector('meta[name=csrf-token]').content;

    // Preferencias de este equipo (aviso y sonido)
    const pref = (k, def) => { try { const v = localStorage.getItem('hotel_' + k); return v === null ? def : JSON.parse(v); } catch (e) { return def; } };
    const guardarPref = (k, v) => { try { localStorage.setItem('hotel_' + k, JSON.stringify(v)); } catch (e) {} };
    let AVISO_MIN = pref('aviso', 15);
    let SONIDO = pref('sonido', true);
    document.getElementById('lbl_aviso').textContent = AVISO_MIN;

    let datos = { habitaciones: [], servicios: [] };
    let desfase = 0;               // hora del servidor - hora de este equipo
    let pisoActivo = pref('piso', 'TODOS');
    let actual = null;             // habitación abierta en un modal
    const avisados = {};           // hos_id => 'aviso' | 'vencido' (para avisar una sola vez)

    const el = id => document.getElementById(id);
    const esc = t => { const d = document.createElement('div'); d.textContent = t ?? ''; return d.innerHTML; };
    const money = n => 'S/ ' + Number(n || 0).toFixed(2);
    const fecha = s => new Date(s.replace(' ', 'T'));
    const ahora = () => Date.now() + desfase;
    const hm = d => d.toLocaleTimeString('es-PE', { hour: '2-digit', minute: '2-digit' });
    const dia = d => d.toLocaleDateString('es-PE', { day: '2-digit', month: '2-digit' });
    const cuando = d => (d.toDateString() === new Date(ahora()).toDateString() ? '' : dia(d) + ' ') + hm(d);
    function duracion(ms) {
        const s = Math.floor(Math.abs(ms) / 1000), h = Math.floor(s / 3600), m = Math.floor(s % 3600 / 60), x = s % 60;
        const dd = Math.floor(h / 24);
        const reloj = `${String(h % 24).padStart(2, '0')}:${String(m).padStart(2, '0')}:${String(x).padStart(2, '0')}`;
        return dd ? `${dd}d ${reloj}` : reloj;
    }
    const textoMin = min => min % 1440 === 0 ? (min / 1440) + ' día(s)' : (min >= 60 ? (Math.floor(min / 60) + ' h' + (min % 60 ? ' ' + (min % 60) + ' min' : '')) : min + ' min');

    function toast(texto, ms = 3500) {
        const t = el('toast'); t.textContent = texto; t.style.display = 'block';
        clearTimeout(t._timer); t._timer = setTimeout(() => t.style.display = 'none', ms);
    }
    setTimeout(() => document.querySelectorAll('.aviso-temporal').forEach(a => a.style.display = 'none'), 4000);

    function post(url, data, metodo = 'POST') {
        return fetch(url, { method: metodo, headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF }, body: JSON.stringify(data || {}) })
            .then(async r => {
                const j = await r.json().catch(() => ({}));
                if (r.status === 422) return { ok: false, mensaje: Object.values(j.errors || {}).flat()[0] || 'Revisa los datos.' };
                if (!r.ok) return { ok: false, mensaje: j.message || 'Error del servidor.' };
                return j;
            }).catch(() => ({ ok: false, mensaje: 'Sin conexión.' }));
    }

    // Sonido corto de aviso (sin archivos)
    let audio;
    function beep(veces = 2) {
        if (!SONIDO) return;
        try {
            audio = audio || new (window.AudioContext || window.webkitAudioContext)();
            for (let i = 0; i < veces; i++) {
                const o = audio.createOscillator(), g = audio.createGain();
                o.frequency.value = 880; o.connect(g); g.connect(audio.destination);
                const t0 = audio.currentTime + i * 0.35;
                g.gain.setValueAtTime(0.25, t0); g.gain.exponentialRampToValueAtTime(0.001, t0 + 0.3);
                o.start(t0); o.stop(t0 + 0.3);
            }
        } catch (e) {}
    }

    // ---------------- Carga y pintado ----------------
    function cargar() {
        return fetch(URL.estado, { headers: { 'Accept': 'application/json' } }).then(r => r.json()).then(d => {
            desfase = fecha(d.ahora).getTime() - Date.now();
            datos = d;
            pintarPisos();
            pintar();
            if (actual && el('m_hab').classList.contains('in')) {
                const h = datos.habitaciones.find(x => x.hab_id === actual.hab_id);
                if (h && h.estadia) { actual = h; pintarTiempoModal(); } else { $('#m_hab').modal('hide'); }
            }
            if (el('m_config') && el('m_config').classList.contains('in')) pintarConfig();
        }).catch(() => {});
    }

    function pintarPisos() {
        const pisos = [...new Set(datos.habitaciones.map(h => h.hab_piso))];
        if (pisoActivo !== 'TODOS' && !pisos.includes(pisoActivo)) pisoActivo = 'TODOS';
        el('pisos').style.display = pisos.length > 1 ? 'flex' : 'none';
        el('pisos').innerHTML = ['TODOS', ...pisos].map(p =>
            `<button type="button" class="btn ${p === pisoActivo ? 'active' : ''}" data-piso="${esc(p)}"><strong>${esc(p)}</strong></button>`).join('');
        el('pisos').querySelectorAll('button').forEach(b => b.onclick = () => { pisoActivo = b.dataset.piso; guardarPref('piso', pisoActivo); pintarPisos(); pintar(); });
    }

    function claseDe(h) {
        if (h.hab_est === 'Ocupado' && h.estadia) {
            const resta = fecha(h.estadia.fin).getTime() - ahora();
            return resta <= 0 ? 'vencido' : (resta <= AVISO_MIN * 60000 ? 'por-vencer' : 'ocupado');
        }
        return { Libre: 'libre', Limpieza: 'limpieza', Mantenimiento: 'mantenimiento' }[h.hab_est] || 'ocupado';
    }

    function pintar() {
        const lista = datos.habitaciones.filter(h => pisoActivo === 'TODOS' || h.hab_piso === pisoActivo);
        if (!datos.habitaciones.length) {
            el('grid').innerHTML = `<div class="text-center text-muted" style="padding:30px;"><i class="fas fa-bed fa-3x"></i><p style="margin-top:10px;">Aún no hay habitaciones.</p>
                {!! $esAdmin ? '<button class="btn btn-primary" onclick="abrirConfig()">Crear habitaciones y servicios</button>' : '<p>Pide al administrador que las cree.</p>' !!}</div>`;
        } else {
            el('grid').innerHTML = lista.map(h => {
                const e = h.estadia;
                let cuerpo = '';
                if (e) {
                    cuerpo = `<div class="timer" data-fin="${e.fin}"></div><div class="cli">${esc(e.cliente)}</div>`;
                } else {
                    const icono = { Libre: 'fa-door-open', Limpieza: 'fa-broom', Mantenimiento: 'fa-screwdriver-wrench' }[h.hab_est] || 'fa-door-closed';
                    cuerpo = `<div style="margin-top:6px;"><i class="fas ${icono}"></i> ${esc(h.hab_est.toUpperCase())}</div>`;
                }
                const pago = e ? (e.pendiente > 0.009 ? `<span class="pago" style="color:#e74c3c;">${money(e.pendiente)}</span>` : `<span class="pago" style="color:#27ae60;">PAGADO</span>`) : '';
                return `<button type="button" class="hab ${claseDe(h)}" data-id="${h.hab_id}">${pago}
                    <div class="nom">${esc(h.hab_nom)}</div><div class="tip">${esc(h.hab_tip || '')}</div>${cuerpo}</button>`;
            }).join('') || '<p class="text-muted">Sin habitaciones en este piso.</p>';
            el('grid').querySelectorAll('.hab').forEach(b => b.onclick = () => abrirHabitacion(Number(b.dataset.id)));
        }
        tick();
    }

    // Cada segundo: el tiempo BAJA; al llegar al aviso se pone naranja y al terminar rojo (y sigue contando el exceso)
    function tick() {
        const t = new Date(ahora());
        el('reloj').textContent = t.toLocaleDateString('es-PE', { weekday: 'long', day: '2-digit', month: 'long' }) + ' · ' + t.toLocaleTimeString('es-PE');
        const cuenta = { libre: 0, ocupado: 0, 'por-vencer': 0, vencido: 0, limpieza: 0 };
        const alertas = [];

        datos.habitaciones.forEach(h => {
            const clase = claseDe(h);
            if (clase in cuenta) cuenta[clase]++;
            const btn = el('grid').querySelector(`.hab[data-id="${h.hab_id}"]`);
            if (btn) {
                btn.classList.remove('libre', 'ocupado', 'por-vencer', 'vencido', 'limpieza', 'mantenimiento');
                btn.classList.add(clase);
            }
            if (!h.estadia) return;
            const resta = fecha(h.estadia.fin).getTime() - ahora();
            const timer = btn && btn.querySelector('.timer');
            if (timer) timer.textContent = resta > 0 ? duracion(resta) : '+' + duracion(resta);

            const hos = h.estadia.hos_id;
            if (clase === 'vencido') {
                alertas.push({ h, texto: `⏰ Hab. ${h.hab_nom}: tiempo cumplido hace ${duracion(resta)} — ${h.estadia.cliente}`, tipo: 'danger' });
                if (avisados[hos] !== 'vencido') { avisados[hos] = 'vencido'; beep(3); toast(`⏰ Habitación ${h.hab_nom}: se terminó el tiempo`, 6000); }
            } else if (clase === 'por-vencer') {
                alertas.push({ h, texto: `⚠ Hab. ${h.hab_nom}: faltan ${duracion(resta)} — ${h.estadia.cliente}`, tipo: 'warning' });
                if (!avisados[hos]) { avisados[hos] = 'aviso'; beep(2); toast(`⚠ Habitación ${h.hab_nom}: el tiempo está por terminar`, 6000); }
            } else {
                delete avisados[hos];   // le agregaron horas: vuelve a avisar cuando corresponda
            }
        });

        el('n_libre').textContent = cuenta.libre; el('n_ocupado').textContent = cuenta.ocupado;
        el('n_vencer').textContent = cuenta['por-vencer']; el('n_vencido').textContent = cuenta.vencido; el('n_limpieza').textContent = cuenta.limpieza;
        el('alertas').innerHTML = alertas.map(a => `<div class="alert alert-${a.tipo}" data-id="${a.h.hab_id}">${esc(a.texto)} <span class="pull-right">Agregar horas &raquo;</span></div>`).join('');
        el('alertas').querySelectorAll('.alert').forEach(a => a.onclick = () => abrirHabitacion(Number(a.dataset.id)));

        if (actual && actual.estadia && el('m_hab').classList.contains('in')) pintarTiempoModal();
    }

    // ---------------- Servicios (select) ----------------
    function opcionesServicios(select, preferir) {
        const s = datos.servicios;
        select.innerHTML = s.length
            ? s.map(x => `<option value="${x.IdProducto}" data-precio="${x.propun}" data-min="${x.minutos}">${esc(x.pronom)} · ${textoMin(x.minutos)} · ${money(x.propun)}</option>`).join('')
            : '<option value="">Primero crea los servicios de tiempo (Configurar)</option>';
        if (preferir) {
            const p = s.find(x => preferir(x));
            if (p) select.value = p.IdProducto;
        }
    }
    const servicioSel = id => datos.servicios.find(x => String(x.IdProducto) === String(el(id).value));

    // ---------------- Abrir habitación ----------------
    function abrirHabitacion(id) {
        const h = datos.habitaciones.find(x => x.hab_id === id);
        if (!h) return;
        actual = h;
        if (h.hab_est === 'Libre') return abrirIngreso(h);
        if (h.estadia) return abrirOcupada(h);
        el('est_hab').textContent = h.hab_nom;
        el('est_texto').textContent = h.hab_est === 'Limpieza' ? 'Cuando termine la limpieza márcala como disponible.' : 'Está fuera de servicio.';
        $('#m_estado').modal('show');
    }

    document.querySelectorAll('#m_estado [data-estado]').forEach(b => b.onclick = () => {
        post(URL.cambiarEstado, { hab_id: actual.hab_id, estado: b.dataset.estado }).then(r => {
            toast(r.ok ? '✔ ' + r.mensaje : r.mensaje);
            if (r.ok) { $('#m_estado').modal('hide'); cargar(); }
        });
    });

    // ---------------- Ingreso ----------------
    function abrirIngreso(h) {
        el('ing_hab').textContent = h.hab_nom + (h.hab_tip ? ' (' + h.hab_tip + ')' : '');
        opcionesServicios(el('ing_servicio'), x => !/extra/i.test(x.pronom));
        el('ing_cantidad').value = 1; el('ing_doc').value = ''; el('ing_cliente').value = ''; el('ing_personas').value = 1; el('ing_msg').textContent = '';
        precioIngreso();
        $('#m_ingreso').modal('show');
    }
    function precioIngreso() { const s = servicioSel('ing_servicio'); el('ing_precio').value = s ? Number(s.propun).toFixed(2) : ''; resumenIngreso(); }
    function resumenIngreso() {
        const s = servicioSel('ing_servicio');
        if (!s) { el('ing_resumen').textContent = 'No hay servicios de tiempo.'; return; }
        const cant = Math.max(1, Number(el('ing_cantidad').value) || 1);
        const fin = new Date(ahora() + s.minutos * cant * 60000);
        el('ing_resumen').innerHTML = `<b>${textoMin(s.minutos * cant)}</b> · sale a las <b>${cuando(fin)}</b> · Total <b>${money(cant * (Number(el('ing_precio').value) || 0))}</b>`;
    }
    el('ing_servicio').onchange = precioIngreso;
    el('ing_cantidad').oninput = resumenIngreso;
    el('ing_precio').oninput = resumenIngreso;

    function buscarDoc() {
        const doc = el('ing_doc').value.trim();
        if (!/^\d{8}$|^\d{11}$/.test(doc)) { el('ing_msg').textContent = 'Escribe un DNI (8) o RUC (11).'; return; }
        el('ing_msg').textContent = 'Buscando...';
        fetch(URL.cliente + encodeURIComponent(doc), { headers: { 'Accept': 'application/json' } }).then(r => r.json()).then(d => {
            if (d.error || !d.nom) { el('ing_msg').textContent = d.error || 'No encontrado: escribe el nombre.'; return; }
            el('ing_cliente').value = d.nom; el('ing_msg').textContent = '✔ Encontrado';
        }).catch(() => el('ing_msg').textContent = 'No se pudo consultar.');
    }
    el('ing_buscar').onclick = buscarDoc;
    el('ing_doc').addEventListener('keypress', e => { if (e.key === 'Enter') { e.preventDefault(); buscarDoc(); } });

    el('ing_guardar').onclick = () => {
        const btn = el('ing_guardar');
        if (!servicioSel('ing_servicio')) return toast('Primero crea los servicios de tiempo.');
        btn.disabled = true;
        post(URL.ingresar, {
            hab_id: actual.hab_id, servicio: el('ing_servicio').value, cantidad: el('ing_cantidad').value, precio: el('ing_precio').value,
            documento: el('ing_doc').value.trim(), cliente: el('ing_cliente').value.trim(), personas: el('ing_personas').value,
        }).then(r => {
            btn.disabled = false;
            if (!r.ok) return toast(r.mensaje);
            if (el('ing_cobrar') && el('ing_cobrar').checked) { window.location.href = URL.cobrar + r.ped_id; return; }
            $('#m_ingreso').modal('hide'); toast('✔ Ingreso registrado: empieza a correr el tiempo'); cargar();
        });
    };

    // ---------------- Habitación ocupada ----------------
    function abrirOcupada(h) {
        const e = h.estadia;
        el('det_hab').textContent = h.hab_nom;
        el('det_cliente').textContent = e.cliente;
        opcionesServicios(el('ext_servicio'), x => /extra/i.test(x.pronom));
        el('ext_cantidad').value = 1; precioExtra();
        el('con_buscar').value = ''; el('con_productos').innerHTML = '';
        if (el('det_cobrar')) el('det_cobrar').href = URL.cobrar + e.ped_id;
        pintarTiempoModal();
        cargarDetalle();
        $('#m_hab').modal('show');
    }
    function pintarTiempoModal() {
        const e = actual.estadia, resta = fecha(e.fin).getTime() - ahora();
        el('det_inicio').textContent = cuando(fecha(e.inicio));
        el('det_fin').textContent = cuando(fecha(e.fin));
        el('det_lbl_restante').textContent = resta > 0 ? 'Restante' : 'Excedido';
        el('det_restante').textContent = (resta > 0 ? '' : '+') + duracion(resta);
        el('det_restante').style.color = resta <= 0 ? '#e74c3c' : (resta <= AVISO_MIN * 60000 ? '#e67e22' : '#2980b9');
        el('det_cabecera').style.background = resta <= 0 ? '#e74c3c' : (resta <= AVISO_MIN * 60000 ? '#f39c12' : '#2980b9');
    }
    function cargarDetalle() {
        fetch(URL.detalle + actual.estadia.hos_id, { headers: { 'Accept': 'application/json' } }).then(r => r.json()).then(d => {
            let total = 0, pend = 0;
            el('det_items').innerHTML = d.items.map(i => {
                const imp = i.ped_det_can * i.ped_det_pre; total += imp; pend += (i.ped_det_can - i.item_facturado) * i.ped_det_pre;
                const cob = Number(i.item_facturado) >= Number(i.ped_det_can) ? ' <span class="cobrado">✔ cobrado</span>' : '';
                return `<tr><td>${Number(i.ped_det_can)}</td><td>${esc(i.descripcion)}${cob}</td><td class="text-right">${money(imp)}</td></tr>`;
            }).join('');
            el('det_total').textContent = money(total);
            el('det_pendiente').textContent = money(pend);
        });
    }

    function precioExtra() { const s = servicioSel('ext_servicio'); el('ext_precio').value = s ? Number(s.propun).toFixed(2) : ''; }
    el('ext_servicio').onchange = precioExtra;
    el('ext_guardar').onclick = () => {
        const s = servicioSel('ext_servicio');
        if (!s) return toast('Primero crea los servicios de tiempo.');
        const btn = el('ext_guardar'); btn.disabled = true;
        post(URL.extender, { hos_id: actual.estadia.hos_id, servicio: s.IdProducto, cantidad: el('ext_cantidad').value, precio: el('ext_precio').value }).then(r => {
            btn.disabled = false;
            if (!r.ok) return toast(r.mensaje);
            actual.estadia.fin = r.fin;
            delete avisados[actual.estadia.hos_id];
            toast(`✔ Se agregó ${textoMin(s.minutos * (Number(el('ext_cantidad').value) || 1))}. Nueva salida: ${cuando(fecha(r.fin))}`);
            cargarDetalle(); cargar();
        });
    };

    // Consumo: busca productos con el mismo buscador de Comandas
    let tBuscar;
    el('con_buscar').oninput = () => {
        clearTimeout(tBuscar);
        const q = el('con_buscar').value.trim();
        if (q.length < 2) { el('con_productos').innerHTML = ''; return; }
        tBuscar = setTimeout(() => {
            fetch(URL.productos + '?search_text=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } }).then(r => r.json()).then(d => {
                el('con_productos').innerHTML = d.vista;
                el('con_productos').querySelectorAll('.product-item-kiosko').forEach(p => p.onclick = () => agregarConsumo(p.dataset));
            });
        }, 250);
    };
    function agregarConsumo(p) {
        const cant = prompt(`Cantidad de ${p.nombre}:`, '1');
        if (cant === null) return;
        if (!(Number(cant) > 0)) return toast('Cantidad no válida.');
        post(URL.consumo, { hos_id: actual.estadia.hos_id, producto: p.id, cantidad: cant }).then(r => {
            if (!r.ok) return toast(r.mensaje);
            toast(`✔ ${cant} x ${p.nombre} agregado a la habitación`);
            cargarDetalle(); cargar();
        });
    }

    el('det_precuenta').onclick = () => {
        const id = actual.estadia.ped_id;
        post(URL.precuentaImp + id, {}).then(r => {
            if (r.directa) { toast('🖨 ' + r.mensaje); return; }
            window.open(URL.precuenta + id, '_blank');
        });
    };
    el('det_salida').onclick = () => {
        if (!confirm(`¿Dar salida a la habitación ${actual.hab_nom}? Quedará en limpieza.`)) return;
        post(URL.salida, { hos_id: actual.estadia.hos_id }).then(r => {
            toast(r.ok ? '✔ ' + r.mensaje : r.mensaje, 5000);
            if (r.ok) { $('#m_hab').modal('hide'); cargar(); }
        });
    };
    if (el('det_anular')) el('det_anular').onclick = () => {
        const motivo = prompt('Motivo de la anulación del ingreso:');
        if (!motivo) return;
        post(URL.anular, { hos_id: actual.estadia.hos_id, motivo }).then(r => {
            toast(r.ok ? '✔ ' + r.mensaje : r.mensaje, 5000);
            if (r.ok) { $('#m_hab').modal('hide'); cargar(); }
        });
    };

    // ---------------- Configuración ----------------
    function abrirConfig() {
        if (!el('m_config')) return;
        el('cfg_aviso').value = AVISO_MIN; el('cfg_sonido').checked = SONIDO;
        pintarConfig();
        $('#m_config').modal('show');
    }
    function pintarConfig() {
        el('cfg_habs').innerHTML = datos.habitaciones.map(h => `<tr><td><b>${esc(h.hab_nom)}</b> <small class="text-muted">${esc(h.hab_obs || '')}</small></td><td>${esc(h.hab_tip || '')}</td><td>${esc(h.hab_piso)}</td><td>${esc(h.hab_est)}</td>
            <td class="text-right"><button class="btn btn-xs btn-default" data-editar="${h.hab_id}"><i class="fas fa-pen"></i></button>
            <button class="btn btn-xs btn-danger" data-borrar="${h.hab_id}"><i class="fas fa-trash"></i></button></td></tr>`).join('')
            || '<tr><td colspan="5" class="text-muted">Sin habitaciones</td></tr>';
        el('cfg_habs').querySelectorAll('[data-editar]').forEach(b => b.onclick = () => {
            const h = datos.habitaciones.find(x => x.hab_id == b.dataset.editar), f = el('f_hab');
            ['hab_id', 'hab_nom', 'hab_tip', 'hab_piso', 'hab_obs'].forEach(k => f.elements[k].value = h[k] ?? '');
            f.elements.hab_nom.focus();
        });
        el('cfg_habs').querySelectorAll('[data-borrar]').forEach(b => b.onclick = () => {
            if (!confirm('¿Eliminar esta habitación?')) return;
            post(URL.habitaciones + b.dataset.borrar, {}, 'DELETE').then(r => { toast(r.mensaje); cargar(); });
        });

        el('cfg_servs').innerHTML = datos.servicios.map(s => `<tr><td>${esc(s.pronom)}</td><td>${textoMin(s.minutos)}</td><td class="text-right">${money(s.propun)}</td>
            <td class="text-right"><button class="btn btn-xs btn-default" data-editar="${s.IdProducto}"><i class="fas fa-pen"></i></button>
            <button class="btn btn-xs btn-danger" data-quitar="${s.IdProducto}"><i class="fas fa-times"></i></button></td></tr>`).join('')
            || '<tr><td colspan="4" class="text-muted">Crea por ejemplo: HABITACION 2 HORAS, HABITACION 1 DIA y HORA EXTRA</td></tr>';
        el('cfg_servs').querySelectorAll('[data-editar]').forEach(b => b.onclick = () => {
            const s = datos.servicios.find(x => x.IdProducto == b.dataset.editar), f = el('f_serv');
            f.elements.IdProducto.value = s.IdProducto; f.elements.pronom.value = s.pronom; f.elements.propun.value = s.propun;
            f.elements.horas.value = Math.floor(s.minutos / 60); f.elements.min.value = s.minutos % 60;
        });
        el('cfg_servs').querySelectorAll('[data-quitar]').forEach(b => b.onclick = () => {
            if (!confirm('¿Quitar este servicio de tiempo?')) return;
            post(URL.servicios + b.dataset.quitar, {}, 'DELETE').then(r => { toast(r.mensaje); cargar(); });
        });
    }
    function enviarForm(form, url) {
        form.onsubmit = ev => {
            ev.preventDefault();
            const data = Object.fromEntries(new FormData(form));
            post(url, data).then(r => {
                toast(r.ok ? '✔ ' + r.mensaje : r.mensaje);
                if (!r.ok) return;
                const piso = form.elements.hab_piso ? form.elements.hab_piso.value : null;
                form.reset();
                if (piso) form.elements.hab_piso.value = piso;   // para crear varias seguidas del mismo piso
                form.querySelector('[type=hidden]').value = '';
                cargar();
            });
        };
    }
    if (el('f_hab')) {
        enviarForm(el('f_hab'), URL.habitacion);
        enviarForm(el('f_serv'), URL.servicio);
        el('cfg_aviso').onchange = () => { AVISO_MIN = Math.max(1, Number(el('cfg_aviso').value) || 15); guardarPref('aviso', AVISO_MIN); el('lbl_aviso').textContent = AVISO_MIN; tick(); };
        el('cfg_sonido').onchange = () => { SONIDO = el('cfg_sonido').checked; guardarPref('sonido', SONIDO); if (SONIDO) beep(1); };
    }

    cargar();
    setInterval(tick, 1000);
    setInterval(cargar, 30000);
</script>
</body>
</html>
