{{-- Estilos de la pantalla de cobro (va dentro de cobrar.blade.php) --}}
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
        .cat-btn { border: none; color: #fff; font-weight: bold; border-radius: 6px; padding: 6px 12px; margin: 0 4px 6px 0; font-size: 12px; box-shadow: 0 2px 4px rgba(0,0,0,.12); }
        .cat-btn.active { outline: 3px solid #f0ad4e; }
        .grid-productos { display: grid; grid-template-columns: repeat(auto-fill, minmax(115px, 1fr)); gap: 8px; max-height: 300px; overflow-y: auto; padding: 2px; }
        .product-item-kiosko { border: 1px solid #ddd; border-radius: 8px; padding: 8px 4px; background: #fff; text-align: center; cursor: pointer; min-height: 78px;
                               display: flex; flex-direction: column; justify-content: center; transition: transform .1s; }
        .product-item-kiosko:hover { transform: translateY(-2px); box-shadow: 0 4px 10px rgba(0,0,0,.12); border-color: #3498db; }
        .product-name-kiosko { font-size: 12px; font-weight: bold; color: #333; line-height: 1.2; }
        .product-price-kiosko { font-size: 13px; font-weight: bold; color: #2980b9; margin-top: 3px; }
        .product-stock-kiosko { font-size: 10px; color: #27ae60; }
        .product-img-kiosko { width: 40px; height: 40px; object-fit: cover; border-radius: 8px; margin: 0 auto 4px; background: #f1f5f9; }
        .cart-ctrl { display: flex; align-items: center; justify-content: center; gap: 3px; }
        .cart-ctrl button { width: 24px; height: 24px; padding: 0; border: none; border-radius: 4px; background: #3498db; color: #fff; font-weight: bold; }
        .cart-ctrl input { width: 46px; text-align: center; height: 24px; border: 1px solid #ccc; border-radius: 4px; }
        .cart-obs { width: 100%; border: 1px solid #eee; border-radius: 4px; font-size: 11px; padding: 2px 5px; margin-top: 3px; }
        @media (max-width: 991px) { .panel-pago { border-left: none; padding-left: 0; border-top: 2px dashed #ccc; padding-top: 15px; margin-top: 10px; } }
        /* Descuentos y cortesías */
        .btn-cortesia { border: 1px solid #e0c56e; background: #fff8e1; border-radius: 6px; padding: 2px 6px; font-size: 14px; line-height: 1.2; }
        .btn-cortesia.activa { background: #f39c12; border-color: #e67e22; }
        .cort-info { display: block; color: #d35400; font-weight: bold; }
        tr.es-cortesia td { background: #fff8e1; }
        .dscto-box { background: #eef6ff; border-left: 4px solid #3c8dbc; padding: 8px 10px; border-radius: 5px; margin-bottom: 10px; }
        .propina-box { background: #fdf2f8; border-left: 4px solid #db2777; padding: 8px 10px; border-radius: 5px; margin-bottom: 10px; }
        .btn-propina.activa { background: #db2777; border-color: #be185d; color: #fff; }
        #lbl_desglose { font-size: 9pt; color: #555; }
        .mc-fondo { position: fixed; inset: 0; z-index: 2000; background: rgba(15, 23, 42, .6); display: flex; align-items: center; justify-content: center; padding: 15px; }
        .mc-caja { background: #fff; border-radius: 14px; width: 100%; max-width: 420px; padding: 18px; box-shadow: 0 20px 50px rgba(0,0,0,.3); }
        .mc-caja h4 { margin: 0 0 4px; font-weight: bold; color: #d35400; }
        .mc-prod { font-weight: bold; font-size: 14px; margin-bottom: 10px; }
        .mc-motivos { display: flex; flex-wrap: wrap; gap: 6px; margin: 4px 0 8px; }
        .mc-motivo { border: 1px solid #ccc; background: #f7f7f7; border-radius: 15px; padding: 4px 10px; font-size: 11px; }
        .mc-motivo.activo { background: #f39c12; border-color: #e67e22; color: #fff; }
        .mc-botones { display: flex; gap: 8px; margin-top: 12px; }
        .mc-botones .btn { flex: 1; height: 42px; font-weight: bold; }
    </style>
