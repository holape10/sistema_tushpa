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
        .mesa-timer { font-size: 0.7em; font-weight: normal; font-family: monospace; margin-top: 2px; }

        .button-group-bottom { text-align: center; margin-top: 30px; margin-bottom: 30px; display: flex; justify-content: center; gap: 20px; flex-wrap: wrap; }
        .btn-llevar-kiosko, .btn-delivery-kiosko { color: white; font-size: 2.0em; padding: 20px 40px; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.2); border: none; cursor: pointer; }
        .btn-llevar-kiosko { background-color: #007bff; }
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

        .modal-mesa-content { text-align: left; max-height: 250px; overflow-y: auto; }
        .modal-mesa-item { padding: 8px 0; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; }
        .modal-mesa-btns .btn { width: 100%; margin-bottom: 8px; font-weight: bold; }

        @media (max-width: 768px) {
            .header-kiosko-content h2 { font-size: 1.6em; }
            .btn-mesa-kiosko { width: 110px; height: 80px; font-size: 1.05em; }
            .button-group-bottom { flex-direction: column; }
            .btn-llevar-kiosko, .btn-delivery-kiosko { width: 100%; font-size: 1.5em; }
            .takeaway-table thead { display: none; }
            .takeaway-table, .takeaway-table tbody, .takeaway-table tr, .takeaway-table td { display: block; width: 100%; }
            .takeaway-table tr { margin-bottom: 15px; border: 1px solid #ddd; border-radius: 8px; }
            .takeaway-table td { text-align: right; padding-left: 50%; position: relative; }
            .takeaway-table td::before { content: attr(data-label); position: absolute; left: 10px; font-weight: bold; text-align: left; color: #555; }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="header-kiosko-container">
            <div class="logo-container-kiosko"><img src="{{ asset('imagenes/logo.png') }}" alt="Logo"></div>
            <div class="header-kiosko-content"><h2>Selecciona tu Mesa o Tipo de Pedido</h2></div>
            <a href="{{ route('dashboard') }}" class="btn btn-default" style="font-weight:bold; border-radius:20px;">
                <i class="fas fa-arrow-left"></i> Volver
            </a>
        </div>

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
                <div class="modal-header text-center">
                    <h4 class="modal-title" id="modal_mesa_titulo">Mesa</h4>
                </div>
                <div class="modal-body">
                    <div id="modal_mesa_detalle" class="modal-mesa-content">Cargando...</div>
                    <hr>
                    <div class="modal-mesa-btns">
                        <button type="button" class="btn btn-success" id="btn_modal_editar">EDITAR</button>
                        <button type="button" class="btn btn-primary btn-proximamente">PRECUENTA</button>
                        <button type="button" class="btn" style="background:#6f42c1; color:white;" onclick="alert('Ese módulo aún no está construido en el sistema nuevo.')">SEPARADAS</button>
                        <button type="button" class="btn btn-danger" id="btn_modal_cobrar">COBRAR</button>
                        <button type="button" class="btn btn-default" onclick="alert('Cambiar mesa aún no está construido en el sistema nuevo.')">Cambiar Mesa</button>
                        <button type="button" class="btn btn-info" onclick="alert('Unir mesa aún no está construido en el sistema nuevo.')">Unir Mesa</button>
                        <button type="button" class="btn btn-warning" data-dismiss="modal">Cerrar</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@3.4.1/dist/js/bootstrap.min.js"></script>
    <script>
        function irAServicio(orderType, mesaId, mesaNombre, pedidoId) {
            fetch("{{ route('comandas.set_servicio') }}", {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify({ order_type: orderType, mesa_id: mesaId, mesa_nombre: mesaNombre, pedido_id: pedidoId })
            }).then(() => window.location.href = "{{ route('comandas.menu') }}");
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
            fetch(`/comandas/mesas/${pisoId}`)
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

            document.getElementById('btn_modal_cobrar').onclick = function () {
                window.location.href = '/cobrarmesa/' + pedidoId;
            };

            fetch(`/comandas/pedido/${pedidoId}`)
                .then(r => r.json())
                .then(data => {
                    if (!data.success) { document.getElementById('modal_mesa_detalle').textContent = 'Error al cargar.'; return; }
                    let html = '';
                    data.detalles.forEach(d => {
                        html += `<div class="modal-mesa-item"><span>${d.ped_det_can}x ${d.descripcion}</span><strong>S/ ${(d.ped_det_can * d.ped_det_pre).toFixed(2)}</strong></div>`;
                    });
                    html += `<div style="text-align:right; margin-top:10px; font-size:1.2em;"><strong>Total: S/ ${data.total.toFixed(2)}</strong></div>`;
                    document.getElementById('modal_mesa_detalle').innerHTML = html || '<p class="text-muted">Sin ítems</p>';
                    document.getElementById('modal_mesa_titulo').textContent = `Mesa ${mesaNombre} (S/ ${data.total.toFixed(2)})`;
                });
        });

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
                            <td data-label="Cliente">${p.ped_cli_nom ?? '-'}</td>
                            <td data-label="Hora">${hora}</td>
                            <td data-label="Total">${parseFloat(p.ped_tot).toFixed(2)}</td>
                            <td data-label="Acciones">
                                <button class="btn-editar-directo" onclick="irAServicio('${tipoClase}', null, '${p.ped_tip.toUpperCase()}', ${p.ped_id})"><i class="fas fa-edit"></i> Editar</button>
                                <button class="btn-cobrar-directo" onclick="window.location.href='/cobrarmesa/${p.ped_id}'"><i class="fas fa-money-bill"></i> Cobrar</button>
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
</body>
</html>