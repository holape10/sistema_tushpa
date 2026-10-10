<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Selecciona tu Mesa - Sistema Tushpa</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@3.4.1/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body { font-family: Arial, sans-serif; background-color: #f8f9fa; }
        .container-fluid { padding-top: 15px; padding-bottom: 15px; }

        .header-kiosko-container { position: relative; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between; padding: 10px 20px; }
        .logo-container-kiosko img { height: 70px; object-fit: contain; }
        .header-kiosko-content { flex: 1; text-align: center; padding: 0 20px; }
        .header-kiosko-content h2 { text-align: center; color: #3498db; margin-bottom: 0; font-size: 2.0em; }

        .pisos-selector { display: flex; overflow-x: auto; white-space: nowrap; padding: 10px 0; margin-bottom: 20px; background-color: #e9ecef; border-radius: 8px; }
        .pisos-selector .btn { flex-shrink: 0; margin: 0 5px; font-size: 1.2em; padding: 12px 25px; border-radius: 8px; background-color: #6c757d; color: white; border: none; }
        .pisos-selector .btn.active, .pisos-selector .btn:hover { background-color: #007bff; }

        .mesas-grid { display: flex; flex-wrap: wrap; justify-content: center; gap: 10px; padding: 10px; }
        .btn-mesa-kiosko {
            width: 150px; height: 100px; font-size: 1.3em; font-weight: bold; border-radius: 12px;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            text-align: center; color: white; border: none; box-shadow: 0 4px 8px rgba(0,0,0,0.15);
            cursor: pointer; white-space: normal; word-wrap: break-word; line-height: 1.15; padding: 5px;
        }
        .btn-mesa-kiosko.libre { background-color: #52BE80; }
        .btn-mesa-kiosko.ocupado { background-color: #E74C3C; }
        .btn-mesa-kiosko:hover { transform: translateY(-3px); }
        .mesa-listo { position: absolute; top: -8px; right: -8px; background: #facc15; color: #000; font-size: .55em; font-weight: 900;
                      padding: 3px 7px; border-radius: 10px; box-shadow: 0 2px 6px rgba(0,0,0,.3); animation: parpadeo 1.2s infinite; }
        .mesa-reserva { position: absolute; bottom: -8px; left: 50%; transform: translateX(-50%); background: #fff; color: #8e44ad;
                        font-size: .55em; font-weight: 900; padding: 2px 7px; border-radius: 10px; border: 2px solid #8e44ad; white-space: nowrap; }
        @keyframes parpadeo { 50% { opacity: .55; } }
        .reserva-item { border: 1px solid #ddd; border-radius: 8px; padding: 10px; margin-bottom: 8px; }
        .reserva-item.proxima { border-color: #8e44ad; background: #faf5ff; }
        .mesa-timer { font-size: 0.7em; font-weight: normal; font-family: monospace; margin-top: 2px; }

        .button-group-bottom { text-align: center; margin-top: 30px; margin-bottom: 30px; display: flex; justify-content: center; gap: 20px; flex-wrap: wrap; }
        .btn-llevar-kiosko, .btn-delivery-kiosko { color: white; font-size: 2.0em; padding: 20px 40px; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.2); border: none; cursor: pointer; }
        .btn-llevar-kiosko { background-color: #007bff; }
        .btn-pv-kiosko { background-color: #e67e22; color: #fff; font-size: 2.0em; padding: 20px 40px; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.2); border: none; }
        .btn-pv-kiosko:hover, .btn-pv-kiosko:focus { color: #fff; background-color: #d35400; text-decoration: none; }
        .btn-delivery-kiosko { background-color: #28a745; }

        .takeaway-section { margin-top: 30px; background-color: #f2f2f2; padding: 20px; border-radius: 8px; }
        .takeaway-section h3 { text-align: center; color: #333; margin-bottom: 20px; font-size: 1.6em; font-weight: bold; }
        .takeaway-table { width: 100%; border-collapse: collapse; background: white; }
        .takeaway-table th, .takeaway-table td { border: 1px solid #ddd; padding: 10px; text-align: center; vertical-align: middle; }
        .takeaway-table th { background-color: #6c757d; color: white; }
        .label-tipo { display: inline-block; padding: 3px 10px; border-radius: 4px; font-weight: bold; color: white; }
        .label-tipo.llevar { background-color: #007bff; }
        .label-tipo.delivery { background-color: #066d0a; }
        .btn-editar-directo { background-color: #007bff; color: white; border: none; padding: 6px 12px; border-radius: 5px; font-size: 0.9em; margin-right: 5px; }
        .btn-cobrar-directo { background-color: #dc3545; color: white; border: none; padding: 6px 12px; border-radius: 5px; font-size: 0.9em; }

        /* MODAL MEJORADO - BOTONES EN GRID */
        .modal-mesa-content { 
            text-align: left; 
            max-height: 200px; 
            overflow-y: auto; 
            margin-bottom: 15px;
        }
        .modal-mesa-item { 
            padding: 6px 0; 
            border-bottom: 1px solid #eee; 
            display: flex; 
            justify-content: space-between;
            font-size: 0.95em;
        }
        
        /* Grid de botones compacto */
        .modal-mesa-btns {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            margin-top: 10px;
        }
        
        .modal-mesa-btns .btn {
            width: 100%;
            font-weight: 600;
            font-size: 0.9em;
            padding: 10px 8px;
            border-radius: 6px;
            border: none;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: all 0.2s;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .modal-mesa-btns .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 3px 6px rgba(0,0,0,0.15);
        }
        
        .modal-mesa-btns .btn:active {
            transform: translateY(0);
        }
        
        /* Botón Cerrar ocupa toda la fila */
        .modal-mesa-btns .btn-mesa-cerrar {
            grid-column: 1 / -1;
            margin-top: 5px;
        }
        
        .btn-mesa-editar { background: #52BE80; color: #fff; }
        .btn-mesa-precuenta { background: #3498db; color: #fff; }
        .btn-mesa-separadas { background: #8e44ad; color: #fff; }
        .btn-mesa-cobrar { background: #e74c3c; color: #fff; }
        .btn-mesa-cambiar { background: #95a5a6; color: #fff; }
        .btn-mesa-unir { background: #5dade2; color: #fff; }
        .btn-mesa-cerrar { background: #f39c12; color: #fff; }

        /* Modal más compacto */
        .modal-dialog.modal-sm {
            width: 350px;
        }
        
        .modal-header {
            padding: 15px;
        }
        
        .modal-body {
            padding: 15px;
        }

        @media (max-width: 768px) {
            .header-kiosko-content h2 { font-size: 1.6em; }
            .btn-mesa-kiosko { width: 110px; height: 80px; font-size: 1.05em; }
            .button-group-bottom { flex-direction: column; }
            .btn-llevar-kiosko, .btn-delivery-kiosko, .btn-pv-kiosko { width: 100%; font-size: 1.5em; }
            .takeaway-table thead { display: none; }
            .takeaway-table, .takeaway-table tbody, .takeaway-table tr, .takeaway-table td { display: block; width: 100%; }
            .takeaway-table tr { margin-bottom: 15px; border: 1px solid #ddd; border-radius: 8px; }
            .takeaway-table td { text-align: right; padding-left: 50%; position: relative; }
            .takeaway-table td::before { content: attr(data-label); position: absolute; left: 10px; font-weight: bold; text-align: left; color: #555; }
            
            /* En móviles el modal ocupa casi toda la pantalla */
            .modal-dialog.modal-sm {
                width: 95%;
                margin: 10px auto;
            }
            
            .modal-mesa-btns .btn {
                font-size: 0.95em;
                padding: 12px 8px;
            }
        }
    </style>
    @include('partials.pwa')
</head>
<body>
    <div class="container-fluid">
        <div class="header-kiosko-container">
            <div class="logo-container-kiosko"><img src="{{ asset('imagenes/logo.webp') }}" alt="Logo"></div>
            <div class="header-kiosko-content"><h2>Selecciona tu Mesa o Tipo de Pedido</h2></div>
            <div style="display:flex; gap:6px; flex-wrap:wrap; justify-content:flex-end;">
            <button type="button" id="btn_reservas" class="btn btn-default" style="font-weight:bold; border-radius:20px; color:#8e44ad;">
                <i class="fas fa-calendar-check"></i> Reservas <span class="badge" id="n_reservas" style="background:#8e44ad;">0</span>
            </button>
            @if ($puedeCobrar)
                <a href="{{ route('turnos.index') }}" class="btn btn-default" style="font-weight:bold; border-radius:20px;">
                    <i class="fas fa-cash-register"></i> Caja
                </a>
                <a href="{{ route('dashboard') }}" class="btn btn-default" style="font-weight:bold; border-radius:20px;">
                    <i class="fas fa-arrow-left"></i> Volver
                </a>
            @endif
            <form method="POST" action="{{ route('logout') }}" style="margin:0;">
                @csrf
                <button class="btn btn-danger" style="font-weight:bold; border-radius:20px;"><i class="fas fa-power-off"></i> Salir</button>
            </form>
            </div>
        </div>

        @if (session('error'))
            <div class="alert alert-warning text-center">{{ session('error') }}</div>
        @endif
        @if (session('success'))
            <div class="alert alert-success text-center aviso-temporal"><i class="fas fa-print"></i> {{ session('success') }}</div>
        @endif
        <div id="toast" style="display:none; position:fixed; left:50%; bottom:24px; transform:translateX(-50%); z-index:2000; background:#2c3e50; color:#fff; padding:12px 20px; border-radius:10px; box-shadow:0 6px 18px rgba(0,0,0,.25); font-weight:bold;"></div>
        <p class="text-center" style="font-size: 1.1em; color: #555; margin-bottom: 20px;">
            <strong>Usuario conectado:</strong> {{ auth()->user()->apeusu }}
        </p>

        <div class="pisos-selector">
            @foreach ($pisos as $piso)
                <button type="button" class="btn piso-btn {{ $piso->pis_id == $primerPisoId ? 'active' : '' }}" data-piso-id="{{ $piso->pis_id }}">
                    <strong>{{ $piso->pis_nom }}</strong>
                </button>
            @endforeach
        </div>

        <div id="mesas_container" class="mesas-grid">
            @include('empresas.comandas.partials.mesas_grid', ['mesas' => $mesas])
        </div>

        <div class="button-group-bottom">
            <button type="button" class="btn btn-llevar-kiosko" id="btn_llevar"><strong>PARA LLEVAR</strong></button>
            <button type="button" class="btn btn-delivery-kiosko" id="btn_delivery"><strong>DELIVERY</strong></button>
            @if ($puedeCobrar)
                <a href="{{ route('cobros.directa') }}" class="btn btn-pv-kiosko"><strong>PUNTO DE VENTA</strong></a>
            @endif
        </div>

        <div class="takeaway-section">
            <h3>Pedidos PARA LLEVAR y DELIVERY Activos</h3>
            <div id="takeaway_container"><p class="text-center text-muted">Cargando...</p></div>
        </div>
    </div>

    <!-- Modal de opciones de mesa (Bootstrap 3) -->
    <div class="modal fade" id="modal_mesa" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-sm" role="document">
            <div class="modal-content">
                <div class="modal-header text-center" style="padding: 12px 15px;">
                    <button type="button" class="close" data-dismiss="modal" style="position: absolute; right: 10px; top: 10px;">&times;</button>
                    <h4 class="modal-title" id="modal_mesa_titulo" style="margin: 0;">Mesa</h4>
                </div>
                <div class="modal-body" style="padding: 15px;">
                    <div id="modal_mesa_listo" style="display:none; background:#fef9c3; border:2px solid #facc15; border-radius:8px; padding:8px; margin-bottom:10px; text-align:center;">
                        <strong>🔔 Cocina tiene listo un pedido de esta mesa</strong><br>
                        <button type="button" class="btn btn-warning btn-sm" id="btn_modal_entregado" style="margin-top:6px; font-weight:bold;">✔ Ya lo llevé a la mesa</button>
                    </div>
                    <div id="modal_mesa_detalle" class="modal-mesa-content">Cargando...</div>
                    <div class="modal-mesa-btns">
                        <button type="button" class="btn btn-mesa-editar" id="btn_modal_editar">
                            <i class="fas fa-edit"></i> EDITAR
                        </button>
                        <button type="button" class="btn btn-mesa-precuenta" id="btn_modal_precuenta">
                            <i class="fas fa-file-invoice"></i> PRECUENTA
                        </button>
                        @if ($puedeCobrar)
                        <button type="button" class="btn btn-mesa-separadas" id="btn_modal_separadas">
                            <i class="fas fa-users"></i> SEPARADAS
                        </button>
                        <button type="button" class="btn btn-mesa-cobrar" id="btn_modal_cobrar">
                            <i class="fas fa-money-bill-wave"></i> COBRAR
                        </button>
                        @endif
                        <button type="button" class="btn btn-mesa-cambiar" id="btn_modal_cambiar">
                            <i class="fas fa-exchange-alt"></i> Cambiar Mesa
                        </button>
                        <button type="button" class="btn btn-mesa-unir" id="btn_modal_unir">
                            <i class="fas fa-link"></i> Unir Mesa
                        </button>
                        <button type="button" class="btn btn-mesa-cerrar" data-dismiss="modal">
                            <i class="fas fa-times"></i> Cerrar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modal_reservas" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header" style="background:#8e44ad; color:#fff;">
                    <button type="button" class="close" data-dismiss="modal" style="color:#fff; opacity:1;">&times;</button>
                    <h4 class="modal-title"><i class="fas fa-calendar-check"></i> Reservas de hoy</h4>
                </div>
                <div class="modal-body" style="max-height:70vh; overflow-y:auto;">
                    <div id="lista_reservas"><p class="text-muted">Cargando...</p></div>
                </div>
                <div class="modal-footer">
                    <a href="{{ route('reservas.index') }}" class="btn btn-default"><i class="fas fa-plus"></i> Nueva reserva / ver todas</a>
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modal_elegir_mesa" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-sm" role="document">
            <div class="modal-content">
                <div class="modal-header text-center" style="padding: 12px 15px;">
                    <button type="button" class="close" data-dismiss="modal" style="position: absolute; right: 10px; top: 10px;">&times;</button>
                    <h4 class="modal-title" id="elegir_titulo" style="margin: 0;">Elegir mesa</h4>
                </div>
                <div class="modal-body" style="padding: 15px;">
                    <p id="elegir_ayuda" style="font-size:0.9em; color:#666;"></p>
                    <div id="elegir_zonas"></div>
                    <div id="elegir_lista" style="display:grid; grid-template-columns:1fr 1fr; gap:8px; max-height:50vh; overflow-y:auto;"></div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@3.4.1/dist/js/bootstrap.min.js"></script>
    <script>
        const URL_COBRAR = "{{ url('cobrarmesa') }}/";
        const URL_MESAS = "{{ url('comandas/mesas') }}/";
        const URL_PEDIDO = "{{ url('comandas/pedido') }}/";
        const URL_PRECUENTA = "{{ url('comandas/precuenta') }}/";
        const PUEDE_COBRAR = {{ $puedeCobrar ? 'true' : 'false' }};

        function postJson(url, data) {
            return fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify(data)
            }).then(r => r.json().catch(() => ({ success: false, message: 'Error del servidor.' })));
        }

        function toast(texto, ms = 3000) {
            if (window.tushpaAviso) return window.tushpaAviso(texto, /no se|error|falta|debe|no hay|inv[aá]lid|ya est/i.test(texto) ? 'aviso' : 'ok');
            const t = document.getElementById('toast');
            t.textContent = texto;
            t.style.display = 'block';
            clearTimeout(t._timer);
            t._timer = setTimeout(() => t.style.display = 'none', ms);
        }
        // El aviso de "enviado a la impresora" desaparece solo
        setTimeout(() => document.querySelectorAll('.aviso-temporal').forEach(a => a.style.display = 'none'), 4000);

        // Precuenta: directo a la impresora; si el agente no está conectado, se abre para imprimir desde el navegador
        function imprimirPrecuenta(pedidoId) {
            postJson("{{ url('impresion/precuenta') }}/" + pedidoId, {}).then(res => {
                if (res.directa) { toast('🖨 ' + res.mensaje); return; }
                window.open(URL_PRECUENTA + pedidoId, '_blank');
            }).catch(() => window.open(URL_PRECUENTA + pedidoId, '_blank'));
        }

        // Abre el segundo modal recién cuando el primero terminó de cerrarse (Bootstrap 3 se descuadra si se solapan)
        function cambiarModal(desde, hacia) {
            const $desde = $(desde);
            if ($desde.hasClass('in')) {
                $desde.one('hidden.bs.modal', () => $(hacia).modal('show')).modal('hide');
            } else {
                $(hacia).modal('show');
            }
        }

        /**
         * Selector de mesas por zona (pestañas por piso). tipo: 'libres' | 'ocupadas'.
         * alElegir(mesa, zona, boton) hace la acción y devuelve una promesa {success, message}.
         */
        function selectorMesas({ tipo, pedidoId = '', titulo, ayuda, desde, confirmar, alElegir }) {
            const lista = document.getElementById('elegir_lista');
            const zonas = document.getElementById('elegir_zonas');
            document.getElementById('elegir_titulo').textContent = titulo;
            document.getElementById('elegir_ayuda').textContent = ayuda;
            lista.innerHTML = '<p class="text-muted">Cargando...</p>';
            zonas.innerHTML = '';
            cambiarModal(desde, '#modal_elegir_mesa');

            fetch(`{{ route('comandas.mesas_disponibles') }}?tipo=${tipo}&ped_id=${pedidoId}`)
                .then(r => r.json())
                .then(data => {
                    if (!data.mesas.length) {
                        lista.innerHTML = `<p class="text-muted" style="grid-column:1/-1;">${tipo === 'libres' ? 'No hay mesas libres.' : 'No hay otras mesas ocupadas.'}</p>`;
                        return;
                    }
                    const nombresZona = [...new Set(data.mesas.map(m => m.piso || 'SIN ZONA'))];
                    let zonaActiva = nombresZona[0];

                    const pintar = () => {
                        zonas.innerHTML = '';
                        nombresZona.forEach(z => {
                            const tab = document.createElement('button');
                            tab.type = 'button';
                            tab.className = 'btn btn-sm ' + (z === zonaActiva ? 'btn-primary' : 'btn-default');
                            tab.style.margin = '0 4px 6px 0';
                            tab.textContent = z + ' (' + data.mesas.filter(m => (m.piso || 'SIN ZONA') === z).length + ')';
                            tab.onclick = () => { zonaActiva = z; pintar(); };
                            zonas.appendChild(tab);
                        });
                        lista.innerHTML = '';
                        data.mesas.filter(m => (m.piso || 'SIN ZONA') === zonaActiva).forEach(m => {
                            const b = document.createElement('button');
                            b.type = 'button';
                            b.className = 'btn ' + (tipo === 'libres' ? 'btn-success' : 'btn-danger');
                            b.style.whiteSpace = 'normal';
                            b.style.padding = '14px 6px';
                            b.innerHTML = '<strong></strong>';
                            b.querySelector('strong').textContent = m.nombre;
                            b.onclick = () => {
                                if (!confirm(confirmar(m, zonaActiva))) return;
                                b.disabled = true;
                                alElegir(m, zonaActiva).then(res => {
                                    b.disabled = false;
                                    if (!res.success) { alert(res.message || 'No se pudo completar.'); return; }
                                    $('#modal_elegir_mesa').modal('hide');
                                });
                            };
                            lista.appendChild(b);
                        });
                    };
                    pintar();
                });
        }

        function refrescarTodo() {
            const activo = document.querySelector('.piso-btn.active');
            if (activo) refrescarMesas(activo.dataset.pisoId);
            cargarActivos();
        }

        // Cambiar mesa (a una libre) o unir mesa (traer el pedido de otra mesa ocupada)
        function elegirMesa(tipo, pedidoId, mesaNombre) {
            selectorMesas({
                tipo, pedidoId, desde: '#modal_mesa',
                titulo: tipo === 'libres' ? `Cambiar ${mesaNombre} a...` : `Unir a ${mesaNombre}`,
                ayuda: tipo === 'libres' ? 'Elige la zona y la mesa libre a la que se pasa el pedido.'
                                         : `Elige la mesa cuyo pedido se juntará en ${mesaNombre}. Esa mesa quedará libre.`,
                confirmar: (m, z) => tipo === 'libres' ? `¿Pasar el pedido de ${mesaNombre} a ${m.nombre} (${z})?`
                                                       : `¿Juntar el pedido de ${m.nombre} (${z}) en ${mesaNombre}?`,
                alElegir: m => (tipo === 'libres'
                        ? postJson("{{ route('comandas.cambiar_mesa') }}", { ped_id: pedidoId, mes_id: m.mes_id })
                        : postJson("{{ route('comandas.unir_mesa') }}", { ped_id: pedidoId, ped_id_origen: m.ped_id }))
                    .then(res => { if (res.success) { toast(tipo === 'libres' ? `✔ Pedido pasado a ${m.nombre}` : `✔ ${m.nombre} unida a ${mesaNombre}`); refrescarTodo(); } return res; }),
            });
        }

        // ---------------- Reservas de hoy
        let reservasHoy = [];

        function cargarReservas() {
            return fetch("{{ route('reservas.dia') }}", { headers: { Accept: 'application/json' } })
                .then(r => r.json())
                .then(d => {
                    reservasHoy = d.reservas;
                    document.getElementById('n_reservas').textContent = reservasHoy.length;
                    pintarReservas();
                }).catch(() => {});
        }

        function pintarReservas() {
            const cont = document.getElementById('lista_reservas');
            if (!reservasHoy.length) { cont.innerHTML = '<p class="text-muted text-center">No hay reservas pendientes para hoy.</p>'; return; }
            const ahora = new Date();
            cont.innerHTML = reservasHoy.map(r => {
                const [h, m] = r.hora_inicio.split(':');
                const llegada = new Date(); llegada.setHours(+h, +m, 0, 0);
                const minutos = Math.round((llegada - ahora) / 60000);
                const cuando = minutos > 0 ? `en ${minutos >= 60 ? Math.floor(minutos / 60) + ' h ' : ''}${minutos % 60} min` : (minutos > -30 ? 'ya debería estar' : 'atrasada');
                const mesa = r.mes_nom
                    ? `${esc(r.pis_nom || '')} / <b>${esc(r.mes_nom)}</b> <span class="label ${r.mesa_libre ? 'label-success' : 'label-danger'}">${r.mesa_libre ? 'libre' : 'ocupada'}</span>`
                    : '<span class="text-muted">sin mesa elegida</span>';
                return `<div class="reserva-item ${minutos <= 60 ? 'proxima' : ''}">
                    <div style="display:flex; justify-content:space-between; gap:8px; align-items:flex-start;">
                        <div>
                            <div style="font-size:1.15em;"><b>${r.hora_inicio.slice(0, 5)}</b> · <b>${esc(r.nombre_cliente)}</b> · ${r.cantidad_personas} pers.</div>
                            <div class="text-muted" style="font-size:.9em;">${cuando}${r.telefono ? ' · 📞 ' + esc(r.telefono) : ''} · ${esc(r.estado)}</div>
                            <div style="font-size:.9em; margin-top:3px;">Mesa: ${mesa}</div>
                            ${r.platos.length ? `<div style="font-size:.85em; margin-top:3px;">🍽 ${r.platos.map(esc).join(', ')}</div>` : ''}
                            ${r.observacion ? `<div style="font-size:.85em; color:#8a6d3b;">» ${esc(r.observacion)}</div>` : ''}
                        </div>
                        <button type="button" class="btn btn-success" style="font-weight:bold;" onclick="atenderReserva(${r.res_id})">LLEGÓ ✔</button>
                    </div>
                </div>`;
            }).join('');
        }

        // Llegó el cliente: mesa reservada si está libre; si no (o no eligió), se escoge otra por zona
        function atenderReserva(resId) {
            const r = reservasHoy.find(x => x.res_id === resId);
            const atender = mesId => postJson("{{ url('reservas') }}/" + resId + '/atender', { mes_id: mesId }).then(res => {
                if (res.success) window.location.href = res.redirect;
                return res;
            });

            if (r.mes_id && r.mesa_libre && confirm(`Asignar la ${r.mes_nom} reservada a ${r.nombre_cliente}?`)) {
                atender(r.mes_id).then(res => { if (!res.success) alert(res.message); });
                return;
            }
            selectorMesas({
                tipo: 'libres', desde: '#modal_reservas',
                titulo: `Mesa para ${r.nombre_cliente} (${r.cantidad_personas} pers.)`,
                ayuda: r.mes_id && !r.mesa_libre ? `La ${r.mes_nom} reservada está ocupada: elige otra mesa.` : 'Elige la mesa para la reserva.',
                confirmar: (m, z) => `¿Atender a ${r.nombre_cliente} en ${m.nombre} (${z})?`,
                alElegir: m => atender(m.mes_id),
            });
        }

        document.getElementById('btn_reservas').addEventListener('click', () => { cargarReservas(); $('#modal_reservas').modal('show'); });
        cargarReservas();
        setInterval(cargarReservas, 60000);

        function irAServicio(orderType, mesaId, mesaNombre, pedidoId) {
            fetch("{{ route('comandas.set_servicio') }}", {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify({ order_type: orderType, mesa_id: mesaId, mesa_nombre: mesaNombre, pedido_id: pedidoId })
            })
            .then(r => r.json().catch(() => ({ success: false, message: 'Error del servidor.' })))
            .then(res => {
                if (!res.success) {
                    alert(res.message || 'No se pudo abrir el pedido.');
                    const activo = document.querySelector('.piso-btn.active');
                    if (activo) refrescarMesas(activo.dataset.pisoId);
                    cargarActivos();
                    return;
                }
                window.location.href = "{{ route('comandas.menu') }}";
            })
            .catch(() => alert('Error de conexión.'));
        }

        document.getElementById('btn_llevar').addEventListener('click', () => irAServicio('llevar', null, 'PARA LLEVAR', null));
        document.getElementById('btn_delivery').addEventListener('click', () => irAServicio('delivery', null, 'DELIVERY', null));

        function activarPisos() {
            document.querySelectorAll('.piso-btn').forEach(btn => {
                btn.addEventListener('click', function () {
                    document.querySelectorAll('.piso-btn').forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    refrescarMesas(this.dataset.pisoId);
                });
            });
        }
        activarPisos();

        function refrescarMesas(pisoId) {
            fetch(URL_MESAS + pisoId)
                .then(r => r.json())
                .then(data => document.getElementById('mesas_container').innerHTML = data.vista);
        }

        setInterval(function () {
            const activo = document.querySelector('.piso-btn.active');
            if (activo) refrescarMesas(activo.dataset.pisoId);
        }, 10000);

        // TIMER en vivo de cada mesa ocupada (no se reinicia con el refresh de 10s, corre en el navegador)
        setInterval(function () {
            document.querySelectorAll('.mesa-timer').forEach(function (el) {
                const inicio = new Date(el.dataset.inicio).getTime();
                if (!inicio) return;
                const diff = Math.max(0, Math.floor((Date.now() - inicio) / 1000));
                const h = String(Math.floor(diff / 3600)).padStart(2, '0');
                const m = String(Math.floor((diff % 3600) / 60)).padStart(2, '0');
                const s = String(diff % 60).padStart(2, '0');
                el.textContent = `${h}:${m}:${s}`;
            });
        }, 1000);

        // Click en mesa
        document.addEventListener('click', function (e) {
            const mesa = e.target.closest('.btn-mesa-comanda');
            if (!mesa) return;
            const estado = mesa.dataset.estado;
            const mesaId = mesa.dataset.id, mesaNombre = mesa.dataset.nombre, pedidoId = mesa.dataset.pedidoId;

            if (estado === 'Libre') {
                irAServicio('salon', mesaId, mesaNombre, null);
                return;
            }

            if (!pedidoId) {
                alert('Mesa ocupada sin pedido asociado.');
                return;
            }

            document.getElementById('modal_mesa_titulo').textContent = `Mesa ${mesaNombre}`;
            document.getElementById('modal_mesa_detalle').innerHTML = 'Cargando detalles...';
            $('#modal_mesa').modal('show');

            document.getElementById('btn_modal_editar').onclick = function () {
                $('#modal_mesa').modal('hide');
                irAServicio('salon', mesaId, mesaNombre, pedidoId);
            };

            if (PUEDE_COBRAR) {
                document.getElementById('btn_modal_cobrar').onclick = () => window.location.href = URL_COBRAR + pedidoId;
                document.getElementById('btn_modal_separadas').onclick = () => window.location.href = URL_COBRAR + pedidoId + '/separadas';
            }
            document.getElementById('btn_modal_precuenta').onclick = () => imprimirPrecuenta(pedidoId);
            const hayListo = Number(mesa.dataset.listos) > 0;
            document.getElementById('modal_mesa_listo').style.display = hayListo ? 'block' : 'none';
            document.getElementById('btn_modal_entregado').onclick = () => postJson("{{ url('cocina/entregado') }}/" + pedidoId, {}).then(() => {
                document.getElementById('modal_mesa_listo').style.display = 'none';
                toast('✔ Entregado a la mesa');
                refrescarTodo();
            });
            document.getElementById('btn_modal_cambiar').onclick = () => elegirMesa('libres', pedidoId, mesaNombre);
            document.getElementById('btn_modal_unir').onclick = () => elegirMesa('ocupadas', pedidoId, mesaNombre);

            fetch(URL_PEDIDO + pedidoId)
                .then(r => r.json())
                .then(data => {
                    if (!data.success) { document.getElementById('modal_mesa_detalle').textContent = 'Error al cargar.'; return; }
                    let html = '';
                    data.detalles.forEach(d => {
                        html += `<div class="modal-mesa-item"><span>${parseFloat(d.ped_det_can)}x ${esc(d.descripcion)}</span><strong>S/ ${(d.ped_det_can * d.ped_det_pre).toFixed(2)}</strong></div>`;
                    });
                    html += `<div style="text-align:right; margin-top:10px; font-size:1.1em; font-weight:bold; color:#3498db;">Total: S/ ${data.total.toFixed(2)}</div>`;
                    document.getElementById('modal_mesa_detalle').innerHTML = html || '<p class="text-muted">Sin ítems</p>';
                    document.getElementById('modal_mesa_titulo').textContent = `Mesa ${mesaNombre} (S/ ${data.total.toFixed(2)})`;
                });
        });

        function esc(t) { const d = document.createElement('div'); d.textContent = t ?? ''; return d.innerHTML; }

        // Tabla de Llevar/Delivery activos
        function cargarActivos() {
            fetch("{{ route('comandas.activos_llevar_delivery') }}")
                .then(r => r.json())
                .then(data => {
                    if (!data.pedidos.length) {
                        document.getElementById('takeaway_container').innerHTML = '<p class="text-center text-muted">No hay pedidos activos.</p>';
                        return;
                    }
                    let html = `<table class="takeaway-table"><thead><tr>
                        <th>ID</th><th>Tipo</th><th>Cliente</th><th>Hora</th><th>Total S/.</th><th>Acciones</th>
                    </tr></thead><tbody>`;
                    data.pedidos.forEach(p => {
                        const hora = new Date(p.fecha_hora).toLocaleTimeString('es-PE', { hour: '2-digit', minute: '2-digit' });
                        const tipoClase = p.ped_tip.toLowerCase();
                        html += `<tr>
                            <td data-label="ID">${p.ped_id}</td>
                            <td data-label="Tipo"><span class="label-tipo ${tipoClase}">${p.ped_tip.toUpperCase()}</span></td>
                            <td data-label="Cliente">${esc(p.ped_cli_nom ?? '-')}</td>
                            <td data-label="Hora">${hora}</td>
                            <td data-label="Total">${parseFloat(p.ped_tot).toFixed(2)}</td>
                            <td data-label="Acciones">
                                <button class="btn-editar-directo" onclick="irAServicio('${tipoClase}', null, '${p.ped_tip.toUpperCase()}', ${p.ped_id})"><i class="fas fa-edit"></i> Editar</button>
                                ${PUEDE_COBRAR ? `<button class="btn-cobrar-directo" onclick="window.location.href=URL_COBRAR + ${p.ped_id}"><i class="fas fa-money-bill"></i> Cobrar</button>` : ''}
                                <button class="btn-editar-directo" style="background:#3498db;" onclick="imprimirPrecuenta(${p.ped_id})"><i class="fas fa-file-invoice"></i> Precuenta</button>
                            </td>
                        </tr>`;
                    });
                    html += '</tbody></table>';
                    document.getElementById('takeaway_container').innerHTML = html;
                });
        }
        cargarActivos();
        setInterval(cargarActivos, 15000);
    </script>
@include('partials.avisos')
@include('partials.aviso_servicio')
</body>
</html>