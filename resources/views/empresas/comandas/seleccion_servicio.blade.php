<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Selecciona tu Mesa - Sistema Tushpa</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" href="{{ asset('imagenes/icono.png') }}" type="image/png">
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
        .btn-mesa-cambiar { background: #f59e0b; color: #fff; }
        .btn-mesa-unir { background: #0ea5e9; color: #fff; }

        /* Pasar / juntar mesas: paso 1 elegir, paso 2 confirmar con resumen */
        #modal_mover .modal-content { border-radius: 14px; overflow: hidden; border: none; }
        #modal_mover .mover-cabecera { padding: 14px 18px; color: #fff; position: relative; }
        #modal_mover .mover-cabecera.pasar { background: linear-gradient(135deg, #f59e0b, #d97706); }
        #modal_mover .mover-cabecera.juntar { background: linear-gradient(135deg, #0ea5e9, #0369a1); }
        #modal_mover .mover-cabecera h4 { margin: 0; font-weight: bold; font-size: 1.25em; }
        #modal_mover .mover-cabecera p { margin: 4px 0 0; opacity: .9; font-size: .92em; }
        #modal_mover .mover-cabecera .close { position: absolute; right: 14px; top: 10px; color: #fff; opacity: .9; font-size: 28px; }
        .mover-pasos { display: flex; gap: 6px; margin-bottom: 12px; font-size: .8em; font-weight: bold; color: #94a3b8; }
        .mover-pasos span { flex: 1; text-align: center; padding: 4px; border-bottom: 3px solid #e2e8f0; }
        .mover-pasos span.activo { color: #0f172a; border-color: currentColor; }
        .mover-zonas { display: flex; gap: 6px; overflow-x: auto; margin-bottom: 10px; }
        .mover-zonas button { flex-shrink: 0; border: 2px solid #cbd5e1; background: #fff; border-radius: 999px; padding: 5px 14px; font-weight: bold; color: #475569; }
        .mover-zonas button.activa { background: #1e293b; border-color: #1e293b; color: #fff; }
        .mover-lista { max-height: 50vh; overflow-y: auto; padding: 2px; }
        .mover-grilla { display: grid; grid-template-columns: repeat(auto-fill, minmax(118px, 1fr)); gap: 10px; }
        .mover-seccion + .mover-seccion { margin-top: 14px; padding-top: 12px; border-top: 1px dashed #cbd5e1; }
        .mover-seccion-titulo { font-weight: bold; color: #1e293b; }
        .mover-seccion-ayuda { font-size: .85em; color: #64748b; margin-bottom: 8px; }
        .mover-ya { background: #ede9fe; color: #5b21b6; border-radius: 8px; padding: 8px 10px; font-size: .9em; margin-bottom: 10px; }
        .mover-mesa.libre.elegida { background: #15803d; box-shadow: 0 0 0 4px #facc15; }
        .btn-mesa-kiosko.junta { background-color: #8e44ad; }
        .modal-mesa-unidas { background: #f5f3ff; border: 1px solid #ddd6fe; border-radius: 8px; padding: 8px 10px; margin-top: 10px; color: #5b21b6; font-size: .9em; }
        .mesa-unida-chip { display: inline-flex; align-items: center; gap: 6px; background: #8e44ad; color: #fff; border-radius: 999px; padding: 4px 4px 4px 12px; margin: 0 6px 6px 0; font-weight: bold; }
        .mesa-unida-chip button { border: none; border-radius: 999px; background: rgba(255,255,255,.25); color: #fff; font-size: .85em; padding: 2px 10px; }
        .mesa-unida-chip button.seguro { background: #facc15; color: #1e293b; }
        .mover-mesa { border: none; border-radius: 12px; padding: 14px 6px; color: #fff; font-weight: bold; font-size: 1.1em; line-height: 1.2;
            box-shadow: 0 3px 6px rgba(0,0,0,.15); transition: transform .1s; }
        .mover-mesa:active { transform: scale(.96); }
        .mover-mesa.libre { background: #52BE80; }
        .mover-mesa.ocupada { background: #E74C3C; }
        .mover-mesa small { display: block; font-weight: normal; font-size: .75em; opacity: .95; margin-top: 3px; }
        .mover-resumen { display: flex; align-items: center; justify-content: center; gap: 10px; margin: 6px 0 14px; flex-wrap: wrap; }
        .mover-ficha { min-width: 110px; border-radius: 12px; padding: 12px 10px; text-align: center; color: #fff; font-weight: bold; font-size: 1.1em; line-height: 1.25; }
        .mover-ficha small { display: block; font-weight: normal; font-size: .75em; }
        .mover-ficha.ocupada { background: #E74C3C; } .mover-ficha.libre { background: #52BE80; } .mover-ficha.total { background: #1e293b; }
        .mover-ficha.queda { box-shadow: 0 0 0 4px #facc15; }
        .mover-signo { font-size: 1.8em; font-weight: bold; color: #64748b; }
        .mover-texto { background: #f1f5f9; border-radius: 10px; padding: 10px 12px; font-size: .95em; color: #334155; text-align: center; }
        .mover-quedar { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin: 8px 0 10px; }
        .mover-quedar button { border: 2px solid #cbd5e1; background: #fff; border-radius: 10px; padding: 10px 6px; font-weight: bold; color: #334155; white-space: normal; }
        .mover-quedar button.activa { border-color: #0ea5e9; background: #e0f2fe; color: #0369a1; }
        .mover-acciones { display: grid; grid-template-columns: 1fr 2fr; gap: 8px; margin-top: 14px; }
        .mover-acciones .btn { padding: 12px; font-weight: bold; border-radius: 10px; font-size: 1em; white-space: normal; }
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
                    <div id="modal_mesa_unidas" class="modal-mesa-unidas" style="display:none;"></div>
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
                            <i class="fas fa-arrow-right-arrow-left"></i> Pasar a otra mesa
                        </button>
                        <button type="button" class="btn btn-mesa-unir" id="btn_modal_unir">
                            <i class="fas fa-object-group"></i> Juntar mesas
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

    {{-- Pasar el pedido a otra mesa / juntar dos mesas: 1) elegir la mesa 2) ver el resumen y confirmar --}}
    <div class="modal fade" id="modal_mover" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document" style="max-width:520px; width:95%; margin:30px auto;">
            <div class="modal-content">
                <div class="mover-cabecera" id="mover_cabecera">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 id="mover_titulo"></h4>
                    <p id="mover_sub"></p>
                </div>
                <div class="modal-body" style="padding:14px 16px;">
                    <div class="mover-pasos"><span id="mover_paso1">1. Elige la mesa</span><span id="mover_paso2">2. Confirma</span></div>
                    <div id="mover_elegir">
                        <div class="mover-zonas" id="mover_zonas"></div>
                        <div class="mover-lista" id="mover_lista"></div>
                        <button type="button" class="btn btn-primary btn-block" id="mover_siguiente" style="display:none; margin-top:12px; padding:12px; font-weight:bold; border-radius:10px;"></button>
                    </div>
                    <div id="mover_confirmar" style="display:none;">
                        <div class="mover-resumen" id="mover_resumen"></div>
                        <div id="mover_quedar_caja" style="display:none;">
                            <div style="font-weight:bold; text-align:center; color:#334155;">¿A nombre de qué mesa queda la cuenta?</div>
                            <div class="mover-quedar" id="mover_quedar"></div>
                        </div>
                        <div class="mover-texto" id="mover_texto"></div>
                        <div class="mover-acciones">
                            <button type="button" class="btn btn-default" id="mover_volver"><i class="fas fa-arrow-left"></i> Otra mesa</button>
                            <button type="button" class="btn btn-success" id="mover_ok"></button>
                        </div>
                    </div>
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

        // Recargas automáticas: se pausan con la pestaña oculta o la tablet bloqueada (no gastan servidor ni batería)
        // y se ponen al día apenas el usuario vuelve
        function cadaSiVisible(fn, ms) {
            setInterval(() => { if (!document.hidden) fn(); }, ms);
            document.addEventListener('visibilitychange', () => { if (!document.hidden) fn(); });
        }

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

        // Pasar el pedido a una mesa libre ('libres') o juntar mesas ('juntar'):
        //  - mesas libres: el grupo grande ocupa varias mesas con una sola cuenta (se pueden elegir varias)
        //  - mesa ocupada: se juntan las dos cuentas en una
        const soles = n => 'S/ ' + Number(n || 0).toFixed(2);
        function elegirMesa(tipo, pedidoId, mesaNombre) {
            const pasar = tipo === 'libres';
            const el = id => document.getElementById(id);
            const actual = { nombre: mesaNombre, ped_id: pedidoId, total: 0 };
            const elegidas = new Map(); // mesas libres marcadas para el grupo
            el('mover_cabecera').className = 'mover-cabecera ' + (pasar ? 'pasar' : 'juntar');
            el('mover_titulo').innerHTML = pasar ? '<i class="fas fa-arrow-right-arrow-left"></i> Pasar a otra mesa' : '<i class="fas fa-object-group"></i> Juntar mesas';
            el('mover_sub').textContent = pasar ? `Los clientes de ${mesaNombre} se cambian a una mesa libre y se llevan todo su pedido.`
                                                : `¿Un grupo grande o un cumpleaños? Suma mesas a ${mesaNombre}: todo va en una sola cuenta.`;
            el('mover_lista').innerHTML = '<p class="text-muted">Cargando mesas...</p>';
            el('mover_zonas').innerHTML = '';
            el('mover_siguiente').style.display = 'none';
            paso(1);
            cambiarModal('#modal_mesa', '#modal_mover');

            function paso(n) {
                el('mover_elegir').style.display = n === 1 ? '' : 'none';
                el('mover_confirmar').style.display = n === 2 ? '' : 'none';
                el('mover_paso1').className = n === 1 ? 'activo' : '';
                el('mover_paso2').className = n === 2 ? 'activo' : '';
            }

            function boton(m, clase, detalle, alTocar) {
                const b = document.createElement('button');
                b.type = 'button';
                b.className = 'mover-mesa ' + clase;
                b.innerHTML = `${esc(m.nombre)}<small>${detalle}</small>`;
                b.onclick = alTocar;
                return b;
            }

            function seccion(titulo, ayuda) {
                const d = document.createElement('div');
                d.className = 'mover-seccion';
                d.innerHTML = `<div class="mover-seccion-titulo">${titulo}</div><div class="mover-seccion-ayuda">${ayuda}</div>`;
                const lista = document.createElement('div');
                lista.className = 'mover-grilla';
                d.appendChild(lista);
                el('mover_lista').appendChild(d);
                return lista;
            }

            function pintarSiguiente() {
                const n = elegidas.size;
                el('mover_siguiente').style.display = n ? '' : 'none';
                el('mover_siguiente').innerHTML = `Siguiente: juntar ${n} mesa${n > 1 ? 's' : ''} a ${esc(actual.nombre)} <i class="fas fa-arrow-right"></i>`;
            }
            el('mover_siguiente').onclick = () => confirmarLibres();

            fetch(`{{ route('comandas.mesas_disponibles') }}?tipo=${tipo}&ped_id=${pedidoId}`)
                .then(r => r.json())
                .then(data => {
                    actual.total = data.total_actual;
                    actual.unidas = data.unidas || [];
                    if (!data.mesas.length) {
                        el('mover_lista').innerHTML = `<p class="text-muted" style="text-align:center; padding:20px;">${pasar ? 'No hay mesas libres en este momento.' : 'No hay otras mesas para juntar.'}</p>`;
                        return;
                    }
                    const zonaDe = m => m.piso || 'SIN ZONA';
                    const zonas = [...new Set(data.mesas.map(zonaDe))];
                    let zona = zonas[0];
                    const pintar = () => {
                        el('mover_zonas').innerHTML = '';
                        if (zonas.length > 1) zonas.forEach(z => {
                            const b = document.createElement('button');
                            b.type = 'button';
                            b.className = z === zona ? 'activa' : '';
                            b.textContent = `${z} (${data.mesas.filter(m => zonaDe(m) === z).length})`;
                            b.onclick = () => { zona = z; pintar(); };
                            el('mover_zonas').appendChild(b);
                        });
                        el('mover_lista').innerHTML = '';
                        const enZona = data.mesas.filter(m => zonaDe(m) === zona);
                        if (pasar) {
                            const lista = seccion('', '');
                            enZona.forEach(m => lista.appendChild(boton(m, 'libre', 'Libre', () => confirmarPasar(m))));
                            return;
                        }
                        if (actual.unidas.length) {
                            el('mover_lista').insertAdjacentHTML('beforeend', `<div class="mover-ya">🔗 Ya juntas con ${esc(actual.nombre)}: <b>${actual.unidas.map(esc).join(', ')}</b></div>`);
                        }
                        const libres = enZona.filter(m => m.estado === 'libre');
                        const ocupadas = enZona.filter(m => m.estado === 'ocupada');
                        if (libres.length) {
                            const lista = seccion('🟢 Mesas libres para el grupo', 'Toca todas las mesas que va a ocupar el grupo (puedes marcar varias).');
                            libres.forEach(m => {
                                const marcada = elegidas.has(m.mes_id);
                                lista.appendChild(boton(m, 'libre' + (marcada ? ' elegida' : ''), marcada ? '✔ Elegida' : 'Libre', () => {
                                    marcada ? elegidas.delete(m.mes_id) : elegidas.set(m.mes_id, m);
                                    pintar();
                                    pintarSiguiente();
                                }));
                            });
                        }
                        if (ocupadas.length) {
                            const lista = seccion('🔴 Mesas ocupadas: juntar su cuenta', 'Si otra mesa ya está consumiendo y pagará junto con esta.');
                            ocupadas.forEach(m => lista.appendChild(boton(m, 'ocupada', soles(m.total) + (m.desde ? ' · desde ' + m.desde : ''), () => confirmarCuenta(m))));
                        }
                        if (!libres.length && !ocupadas.length) {
                            el('mover_lista').insertAdjacentHTML('beforeend', '<p class="text-muted" style="text-align:center;">No hay mesas en esta zona.</p>');
                        }
                    };
                    pintar();
                });

            function ficha(clase, nombre, detalle) {
                return `<div class="mover-ficha ${clase}">${esc(nombre)}<small>${detalle}</small></div>`;
            }

            function confirmarPasar(m) {
                paso(2);
                el('mover_volver').onclick = () => paso(1);
                el('mover_quedar_caja').style.display = 'none';
                el('mover_resumen').innerHTML = ficha('ocupada', actual.nombre, soles(actual.total))
                    + '<div class="mover-signo">➜</div>' + ficha('libre queda', m.etiqueta, 'nueva mesa');
                el('mover_texto').innerHTML = `Todo el pedido (<b>${soles(actual.total)}</b>) pasa a <b>${esc(m.etiqueta)}</b>.<br><b>${esc(actual.nombre)}</b> quedará libre.`;
                el('mover_ok').innerHTML = `<i class="fas fa-check"></i> Sí, pasar a ${esc(m.etiqueta)}`;
                el('mover_ok').onclick = () => ejecutar(
                    postJson("{{ route('comandas.cambiar_mesa') }}", { ped_id: actual.ped_id, mes_id: m.mes_id }),
                    `✔ Listo: el pedido ahora está en ${m.etiqueta}`);
            }

            // Grupo grande: las mesas libres elegidas se suman a la cuenta de esta mesa
            function confirmarLibres() {
                const mesas = [...elegidas.values()];
                paso(2);
                el('mover_volver').onclick = () => paso(1);
                el('mover_quedar_caja').style.display = 'none';
                el('mover_resumen').innerHTML = ficha('ocupada queda', actual.nombre, soles(actual.total))
                    + mesas.map(m => '<div class="mover-signo">+</div>' + ficha('libre', m.etiqueta, 'se junta')).join('');
                const total = mesas.length + 1 + actual.unidas.length;
                el('mover_texto').innerHTML = `El grupo ocupará <b>${total} mesas</b> con <b>una sola cuenta</b> en <b>${esc(actual.nombre)}</b>.<br>`
                    + `Al cobrar, todas quedan libres solas. Si una se desocupa antes, la liberas desde la mesa.`;
                el('mover_ok').innerHTML = `<i class="fas fa-check"></i> Sí, juntar ${mesas.length} mesa${mesas.length > 1 ? 's' : ''}`;
                el('mover_ok').onclick = () => ejecutar(
                    postJson("{{ route('comandas.juntar_libres') }}", { ped_id: actual.ped_id, mesas: mesas.map(m => m.mes_id) }),
                    `✔ Listo: ${mesas.map(m => m.etiqueta).join(', ')} juntas con ${actual.nombre}`);
            }

            // Dos mesas ocupadas: se juntan las cuentas; las dos siguen ocupadas como un solo grupo
            function confirmarCuenta(m) {
                paso(2);
                el('mover_volver').onclick = () => paso(1);
                const otra = { nombre: m.etiqueta, ped_id: m.ped_id, total: m.total };
                let queda = actual;
                const pintarJuntar = () => {
                    const sale = queda === actual ? otra : actual;
                    el('mover_resumen').innerHTML = ficha('ocupada' + (queda === actual ? ' queda' : ''), actual.nombre, soles(actual.total))
                        + '<div class="mover-signo">+</div>' + ficha('ocupada' + (queda === otra ? ' queda' : ''), otra.nombre, soles(otra.total))
                        + '<div class="mover-signo">=</div>' + ficha('total', soles(Number(actual.total) + Number(otra.total)), 'una sola cuenta');
                    el('mover_quedar').innerHTML = '';
                    [actual, otra].forEach(op => {
                        const b = document.createElement('button');
                        b.type = 'button';
                        b.className = op === queda ? 'activa' : '';
                        b.innerHTML = `${op === queda ? '✔ ' : ''}${esc(op.nombre)}`;
                        b.onclick = () => { queda = op; pintarJuntar(); };
                        el('mover_quedar').appendChild(b);
                    });
                    el('mover_texto').innerHTML = `Lo pedido en las dos mesas queda en <b>una sola cuenta</b> a nombre de <b>${esc(queda.nombre)}</b>.<br>`
                        + `<b>${esc(sale.nombre)}</b> sigue ocupada como parte del grupo; si se desocupa, la liberas desde la mesa.`;
                    el('mover_ok').innerHTML = `<i class="fas fa-check"></i> Sí, juntar cuentas en ${esc(queda.nombre)}`;
                    el('mover_ok').onclick = () => ejecutar(
                        postJson("{{ route('comandas.unir_mesa') }}", { ped_id: queda.ped_id, ped_id_origen: sale.ped_id }),
                        `✔ Listo: cuentas de ${actual.nombre} y ${otra.nombre} juntas en ${queda.nombre}`);
                };
                el('mover_quedar_caja').style.display = '';
                pintarJuntar();
            }

            function ejecutar(promesa, mensaje) {
                const ok = el('mover_ok');
                ok.disabled = true;
                promesa.then(res => {
                    ok.disabled = false;
                    if (!res.success) { alert(res.message || 'No se pudo completar.'); return; }
                    $('#modal_mover').modal('hide');
                    toast(mensaje);
                    refrescarTodo();
                }).catch(() => { ok.disabled = false; alert('Sin conexión. Intenta de nuevo.'); });
            }
        }

        // Mesas juntas en el modal de la mesa: se liberan con dos toques (por si se desocupan antes de cobrar)
        function pintarUnidas(pedidoId, unidas) {
            const cont = document.getElementById('modal_mesa_unidas');
            if (!unidas.length) { cont.style.display = 'none'; cont.innerHTML = ''; return; }
            cont.style.display = '';
            cont.innerHTML = '<div style="font-weight:bold; margin-bottom:6px;">🔗 Mesas juntas en esta cuenta</div>';
            unidas.forEach(m => {
                const chip = document.createElement('span');
                chip.className = 'mesa-unida-chip';
                chip.innerHTML = `${esc(m.nombre)} <button type="button" title="Liberar">Liberar</button>`;
                const b = chip.querySelector('button');
                b.onclick = () => {
                    if (!b.classList.contains('seguro')) { b.classList.add('seguro'); b.textContent = '¿Seguro? Toca otra vez'; return; }
                    b.disabled = true;
                    postJson("{{ route('comandas.separar_mesa') }}", { ped_id: pedidoId, mes_id: m.mes_id }).then(res => {
                        if (!res.success) { alert(res.message); b.disabled = false; return; }
                        toast(`✔ ${m.nombre} quedó libre`);
                        pintarUnidas(pedidoId, unidas.filter(x => x.mes_id !== m.mes_id));
                        refrescarTodo();
                    });
                };
                cont.appendChild(chip);
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
        cadaSiVisible(cargarReservas, 60000);

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

        // Solo se redibuja si algo cambió: así no se pierde el toque del mozo justo cuando llega la recarga
        let ultimaVistaMesas = null;
        function refrescarMesas(pisoId) {
            fetch(URL_MESAS + pisoId)
                .then(r => r.json())
                .then(data => {
                    const clave = pisoId + '|' + data.vista;
                    if (clave === ultimaVistaMesas) return;
                    ultimaVistaMesas = clave;
                    document.getElementById('mesas_container').innerHTML = data.vista;
                    actualizarTimers();
                });
        }

        cadaSiVisible(function () {
            const activo = document.querySelector('.piso-btn.active');
            if (activo) refrescarMesas(activo.dataset.pisoId);
        }, 10000);

        // TIMER en vivo de cada mesa ocupada (no se reinicia con el refresh de 10s, corre en el navegador)
        setInterval(actualizarTimers, 1000);
        function actualizarTimers() {
            document.querySelectorAll('.mesa-timer').forEach(function (el) {
                const inicio = new Date(el.dataset.inicio).getTime();
                if (!inicio) return;
                const diff = Math.max(0, Math.floor((Date.now() - inicio) / 1000));
                const h = String(Math.floor(diff / 3600)).padStart(2, '0');
                const m = String(Math.floor((diff % 3600) / 60)).padStart(2, '0');
                const s = String(diff % 60).padStart(2, '0');
                el.textContent = `${h}:${m}:${s}`;
            });
        }

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

            const tituloMesa = mesaNombre;
            document.getElementById('modal_mesa_titulo').textContent = tituloMesa;
            document.getElementById('modal_mesa_detalle').innerHTML = 'Cargando detalles...';
            pintarUnidas(pedidoId, []);
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
            document.getElementById('btn_modal_unir').onclick = () => elegirMesa('juntar', pedidoId, mesaNombre);

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
                    document.getElementById('modal_mesa_titulo').textContent = `${tituloMesa} (S/ ${data.total.toFixed(2)})`;
                    pintarUnidas(pedidoId, data.unidas || []);
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
        cadaSiVisible(cargarActivos, 15000);
    </script>
@include('partials.avisos')
@include('partials.aviso_servicio')
</body>
</html>