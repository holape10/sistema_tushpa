{{-- Estilos de la pantalla de cobro (va dentro de cobrar.blade.php) --}}
    <style>
        :root { --tinta: #0f172a; --gris: #64748b; --borde: #e2e8f0; --fondo: #f1f5f9; --marca: #4f46e5; --ok: #16a34a; --peligro: #dc2626; }
        body { background: var(--fondo); font-family: system-ui, -apple-system, 'Segoe UI', Roboto, Arial, sans-serif; color: var(--tinta); padding: 0 0 20px; }
        .container-fluid { max-width: 1680px; }
        label { font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 3px; }
        .form-group { margin-bottom: 10px; }
        .form-control { border-radius: 10px; border-color: #cbd5e1; box-shadow: none; }
        .form-control:focus { border-color: var(--marca); box-shadow: 0 0 0 3px rgba(79, 70, 229, .15); }
        .input-sm, select.input-sm { height: 36px; font-size: 13px; }
        .input-group .form-control:first-child { border-radius: 10px 0 0 10px; }
        .input-group-btn:last-child > .btn { border-radius: 0 10px 10px 0; height: 36px; }
        .btn { border-radius: 10px; font-weight: 600; }
        .btn-primary { background: var(--marca); border-color: var(--marca); }
        .btn-primary:hover, .btn-primary:focus { background: #4338ca; border-color: #4338ca; }

        /* Barra de arriba: dónde se cobra y las acciones */
        .cobro-top { position: sticky; top: 0; z-index: 30; background: #1e1b4b; color: #fff; padding: 10px 16px; margin: 0 -15px 14px;
                     display: flex; flex-wrap: wrap; align-items: center; gap: 10px; box-shadow: 0 4px 14px rgba(15, 23, 42, .25); }
        .cobro-top h1 { font-size: 17px; font-weight: 800; margin: 0; flex: 1; min-width: 200px; }
        .cobro-top h1 small { color: #c7d2fe; font-weight: 600; font-size: 12px; margin-left: 6px; }
        .chip { display: inline-flex; align-items: center; gap: 5px; border-radius: 999px; padding: 4px 11px; font-size: 12px; font-weight: 700; white-space: nowrap; }
        .chip-verde { background: #dcfce7; color: #166534; }
        .chip-morado { background: #7c3aed; color: #fff; }
        .chip-naranja { background: #f97316; color: #fff; }
        .top-btn { display: inline-flex; align-items: center; gap: 6px; height: 34px; padding: 0 14px; border-radius: 10px; font-size: 13px; font-weight: 700;
                   background: rgba(255, 255, 255, .12); color: #fff; border: 1px solid rgba(255, 255, 255, .2); cursor: pointer; }
        .top-btn:hover, .top-btn:focus { background: rgba(255, 255, 255, .22); color: #fff; text-decoration: none; }
        .top-btn input { margin: 0; }
        .top-btn.morado { background: #7c3aed; border-color: #7c3aed; }

        /* Tres columnas: la cuenta, el cliente y el cobro (en celular, una debajo de otra) */
        .cobro-grid { display: grid; gap: 14px; grid-template-columns: minmax(0, 1.2fr) minmax(0, 1fr) minmax(300px, .9fr); align-items: start; }
        .cobro-grid.es-pv { grid-template-columns: minmax(0, 1.45fr) minmax(0, 1fr) minmax(300px, .9fr); }
        @media (max-width: 1199px) { .cobro-grid, .cobro-grid.es-pv { grid-template-columns: minmax(0, 1fr) minmax(300px, .85fr); } .col-cliente { grid-column: 1; } .col-pago { grid-row: 1 / span 2; grid-column: 2; } }
        @media (max-width: 767px) { .cobro-grid, .cobro-grid.es-pv { grid-template-columns: 1fr; } .col-cliente, .col-pago { grid-column: auto; grid-row: auto; }
            .cobro-top, .col-pago { position: static; } .cobro-top h1 { min-width: 0; } }
        .tarjeta { background: #fff; border-radius: 16px; box-shadow: 0 1px 3px rgba(15, 23, 42, .08), 0 1px 2px rgba(15, 23, 42, .04); border: 1px solid var(--borde); overflow: hidden; }
        .tarjeta + .tarjeta { margin-top: 14px; }
        .tarjeta-h { padding: 12px 16px; border-bottom: 1px solid var(--borde); display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .tarjeta-h h2 { font-size: 14px; font-weight: 800; margin: 0; flex: 1; text-transform: uppercase; letter-spacing: .02em; }
        .tarjeta-b { padding: 14px 16px; }
        .col-pago { position: sticky; top: 64px; }
        .fila-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 0 10px; }
        .fila-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 0 10px; }
        @media (max-width: 480px) { .fila-3 { grid-template-columns: 1fr 1fr; } }
        .ayuda { font-size: 11px; color: var(--gris); }

        /* Detalle de la cuenta */
        .tbl-det { margin: 0; }
        .tbl-det > thead > tr > th { background: #f8fafc; color: var(--gris); font-size: 11px; text-transform: uppercase; letter-spacing: .03em; border-bottom: 1px solid var(--borde) !important; border-top: 0 !important; padding: 8px 10px; }
        .tbl-det > tbody > tr > td { font-size: 13px; vertical-align: middle !important; border-top: 1px solid #f1f5f9 !important; padding: 9px 10px; }
        .tbl-det > tbody > tr:hover > td { background: #fafaff; }
        .tbl-det .cant { display: inline-block; min-width: 30px; text-align: center; border-radius: 999px; background: #eef2ff; color: #3730a3; font-weight: 800; padding: 2px 8px; }
        .tbl-det .linea-total { font-size: 14px; }
        .cuenta-pie { display: flex; justify-content: space-between; align-items: center; padding: 10px 16px; background: #f8fafc; border-top: 1px solid var(--borde); font-size: 13px; }
        .cuenta-pie b { font-size: 16px; }
        .previas { font-size: 12px; color: #475569; background: #f8fafc; padding: 8px 12px; border-radius: 10px; margin: 10px 16px 0; }
        .sep-ctrl { display: flex; align-items: center; justify-content: center; gap: 4px; }
        .sep-ctrl button { width: 28px; height: 28px; padding: 0; border: none; border-radius: 8px; background: #7c3aed; color: #fff; font-weight: bold; font-size: 15px; }
        .sep-ctrl input { width: 46px; text-align: center; height: 28px; border: 1px solid #cbd5e1; border-radius: 8px; font-weight: 700; }
        tr.sep-elegido td { background: #f5f3ff !important; }

        /* Cliente */
        .sug-wrap { position: relative; }
        .sug-list { position: absolute; z-index: 50; left: 0; right: 0; top: 100%; background: #fff; border: 1px solid #cbd5e1; border-radius: 0 0 10px 10px;
                    box-shadow: 0 10px 24px rgba(15, 23, 42, .15); max-height: 260px; overflow-y: auto; list-style: none; margin: 0; padding: 0; display: none; }
        .sug-list li { padding: 7px 10px; cursor: pointer; font-size: 12px; border-bottom: 1px solid #f1f5f9; }
        .sug-list li small { color: #94a3b8; display: block; }
        .sug-list li.activo, .sug-list li:hover { background: #eef2ff; }
        .cumple { display: none; margin-top: 4px; border-radius: 8px; background: #fdf2f8; color: #be185d; font-size: 12px; font-weight: 800; padding: 4px 8px; }

        /* Cobro */
        .total-box { background: linear-gradient(135deg, #312e81, #4f46e5); color: #fff; border-radius: 14px; padding: 14px 16px; margin-bottom: 12px; }
        .total-box .etq { font-size: 12px; font-weight: 700; color: #c7d2fe; text-transform: uppercase; letter-spacing: .05em; }
        .total-box .monto { font-size: 34px; font-weight: 900; line-height: 1.1; }
        .total-box .pendiente { font-size: 12px; color: #c7d2fe; }
        #lbl_desglose { font-size: 12px; color: #e0e7ff; margin-top: 4px; }
        #lbl_desglose:empty { display: none; }
        .big { height: 46px; font-size: 20px; font-weight: 800; text-align: center; border-radius: 12px; }
        .vuelto { color: var(--peligro) !important; background: #fef2f2 !important; border-color: #fecaca !important; }
        .extra { border: 1px solid var(--borde); border-radius: 12px; margin-bottom: 10px; }
        .extra > summary { list-style: none; cursor: pointer; padding: 9px 12px; font-size: 13px; font-weight: 700; display: flex; align-items: center; gap: 6px; }
        .extra > summary::-webkit-details-marker { display: none; }
        .extra > summary::after { content: '▸'; margin-left: auto; color: var(--gris); }
        .extra[open] > summary::after { content: '▾'; }
        .extra > div { padding: 0 12px 12px; }
        .extra.dscto { background: #eff6ff; border-color: #bfdbfe; }
        .extra.propina { background: #fdf2f8; border-color: #fbcfe8; }
        .btn-propina.activa { background: #db2777; border-color: #be185d; color: #fff; }
        .btn-cobrar { height: 54px; font-size: 16px; font-weight: 900; letter-spacing: .02em; border-radius: 14px; background: var(--ok); border-color: var(--ok); box-shadow: 0 6px 16px rgba(22, 163, 74, .3); }
        .btn-cobrar:hover, .btn-cobrar:focus { background: #15803d; border-color: #15803d; }
        .btn-salir { border-radius: 12px; font-weight: 700; color: #475569; }
        #tbody_med_pag td { border-top: 1px solid #f1f5f9; padding: 6px 4px; }

        /* Punto de venta (venta directa) */
        .cat-btn { border: none; color: #fff; font-weight: 700; border-radius: 999px; padding: 6px 14px; margin: 0 4px 6px 0; font-size: 12px; box-shadow: 0 2px 4px rgba(0,0,0,.12); }
        .cat-btn.active { outline: 3px solid #f59e0b; outline-offset: 1px; }
        .grid-productos { display: grid; grid-template-columns: repeat(auto-fill, minmax(118px, 1fr)); gap: 8px; max-height: 340px; overflow-y: auto; padding: 2px; }
        .product-item-kiosko { border: 1px solid var(--borde); border-radius: 12px; padding: 8px 4px; background: #fff; text-align: center; cursor: pointer; min-height: 80px;
                               display: flex; flex-direction: column; justify-content: center; transition: transform .1s, box-shadow .1s; }
        .product-item-kiosko:hover { transform: translateY(-2px); box-shadow: 0 6px 14px rgba(15, 23, 42, .12); border-color: var(--marca); }
        .product-name-kiosko { font-size: 12px; font-weight: 700; color: #1e293b; line-height: 1.2; }
        .product-price-kiosko { font-size: 13px; font-weight: 800; color: var(--marca); margin-top: 3px; }
        .product-stock-kiosko { font-size: 10px; color: var(--ok); }
        .product-img-kiosko { width: 40px; height: 40px; object-fit: cover; border-radius: 10px; margin: 0 auto 4px; background: #f1f5f9; }
        .cart-ctrl { display: flex; align-items: center; justify-content: center; gap: 4px; }
        .cart-ctrl button { width: 28px; height: 28px; padding: 0; border: none; border-radius: 8px; background: var(--marca); color: #fff; font-weight: bold; }
        .cart-ctrl input { width: 46px; text-align: center; height: 28px; border: 1px solid #cbd5e1; border-radius: 8px; }
        .cart-obs { width: 100%; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 11px; padding: 3px 6px; margin-top: 4px; }

        /* Descuentos y cortesías */
        .btn-cortesia { border: 1px solid #fcd34d; background: #fffbeb; border-radius: 8px; padding: 3px 7px; font-size: 14px; line-height: 1.2; }
        .btn-cortesia.activa { background: #f59e0b; border-color: #d97706; }
        .cort-info { display: block; color: #c2410c; font-weight: 700; }
        tr.es-cortesia td { background: #fffbeb; }
        .mc-fondo { position: fixed; inset: 0; z-index: 2000; background: rgba(15, 23, 42, .6); display: flex; align-items: center; justify-content: center; padding: 15px; }
        .mc-caja { background: #fff; border-radius: 16px; width: 100%; max-width: 420px; padding: 18px; box-shadow: 0 20px 50px rgba(0,0,0,.3); }
        .mc-caja h4 { margin: 0 0 4px; font-weight: 800; color: #c2410c; }
        .mc-prod { font-weight: bold; font-size: 14px; margin-bottom: 10px; }
        .mc-motivos { display: flex; flex-wrap: wrap; gap: 6px; margin: 4px 0 8px; }
        .mc-motivo { border: 1px solid #cbd5e1; background: #f8fafc; border-radius: 15px; padding: 4px 10px; font-size: 11px; }
        .mc-motivo.activo { background: #f59e0b; border-color: #d97706; color: #fff; }
        .mc-botones { display: flex; gap: 8px; margin-top: 12px; }
        .mc-botones .btn { flex: 1; height: 42px; font-weight: bold; }
    </style>
