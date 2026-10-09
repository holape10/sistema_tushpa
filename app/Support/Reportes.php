<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Reportes de ventas y compras. Cada reporte devuelve columnas, filas y totales; la misma definición sirve
 * para la pantalla, el Excel y el PDF.
 *
 * Ventas: documentos vigentes (sin anular) de la sucursal y el rango; las notas de crédito RESTAN
 * (en cantidades solo cuando devuelven producto). Compras: registradas, convertidas a soles.
 */
class Reportes
{
    /** clave => [título, grupo, descripción, ícono] */
    public const CATALOGO = [
        'ventas' => ['Reporte de Ventas', 'Ventas', 'Todos los comprobantes del periodo con sus importes.', 'fa-receipt'],
        'ventas-vendedor' => ['Ventas por Vendedor', 'Ventas', 'Cuánto vendió cada cajero o mozo.', 'fa-user-tie'],
        'ventas-delivery' => ['Ventas Delivery', 'Ventas', 'Pedidos delivery con dirección, teléfono y motorizado.', 'fa-motorcycle'],
        'sunat' => ['Reporte SUNAT', 'Ventas', 'Solo comprobantes tributarios (facturas, boletas y notas), sin notas de venta, con su estado en SUNAT.', 'fa-landmark'],
        'ventas-cliente' => ['Ventas por Cliente', 'Ventas', 'Tus mejores clientes y su frecuencia.', 'fa-users'],
        'ventas-producto' => ['Ventas por Producto', 'Ventas', 'Cantidades e importes por producto.', 'fa-box'],
        'productos-ranking' => ['Productos (+/-) Vendidos', 'Ventas', 'Los más y los menos vendidos (incluye los que no se vendieron).', 'fa-ranking-star'],
        'rentabilidad' => ['Rentabilidad', 'Ventas', 'Ventas, costo, utilidad y margen.', 'fa-chart-line'],
        'compras' => ['Reporte de Compras', 'Compras', 'Todas las compras registradas.', 'fa-cart-shopping'],
        'compras-proveedor' => ['Compras por Proveedor', 'Compras', 'Cuánto le compraste a cada proveedor.', 'fa-truck'],
        'compras-producto' => ['Compras por Producto', 'Compras', 'Cantidades y costos por producto comprado.', 'fa-boxes-stacked'],
    ];

    private const TIPOS = ['01' => 'FACTURA', '03' => 'BOLETA', '07' => 'N. CRÉDITO', '08' => 'N. DÉBITO', '13' => 'N. VENTA', '12' => 'TICKET', '00' => 'OTRO'];

    private const SIGNO = "CASE WHEN c.tdocod = '07' THEN -1 ELSE 1 END";

    private const SIGNO_CANT = "CASE WHEN c.tdocod = '07' AND c.tipnot IN ('01','02','06','07') THEN -1 WHEN c.tdocod IN ('07','08') THEN 0 ELSE 1 END";

    /** @param array $f desde, hasta, sucursal, y los filtros propios de cada reporte */
    public static function generar(string $clave, array $f): array
    {
        $r = match ($clave) {
            'ventas' => self::ventas($f),
            'ventas-vendedor' => self::porVendedor($f),
            'ventas-delivery' => self::delivery($f),
            'sunat' => self::sunat($f),
            'ventas-cliente' => self::porCliente($f),
            'ventas-producto' => self::porProducto($f),
            'productos-ranking' => self::ranking($f),
            'rentabilidad' => self::rentabilidad($f),
            'compras' => self::compras($f),
            'compras-proveedor' => self::comprasProveedor($f),
            'compras-producto' => self::comprasProducto($f),
        };
        [$r['titulo'], $r['grupo'], $r['descripcion'], $r['icono']] = self::CATALOGO[$clave];
        $r['totales'] ??= self::sumar($r['columnas'], $r['filas']);
        // Filtros comunes de todos los reportes de ventas
        if ($r['grupo'] === 'Ventas') {
            $r['usa'] = array_values(array_unique(array_merge(['vendedor', 'tipo', 'medio', 'cliente'], $r['usa'] ?? [])));
        }

        return $r;
    }

    /** Columnas que son promedios o posiciones: no tiene sentido sumarlas */
    private const SIN_TOTAL = ['ticket', 'precio', 'promedio', 'minimo', 'maximo', 'puesto'];

    /** Suma las columnas de dinero y cantidades */
    private static function sumar(array $columnas, array $filas): array
    {
        $t = [];
        foreach ($columnas as $k => [$titulo, $tipo]) {
            if (in_array($tipo, ['money', 'num'], true) && ! in_array($k, self::SIN_TOTAL, true)) {
                $t[$k] = round(array_sum(array_column($filas, $k)), 2);
            }
        }

        return $t;
    }

    /**
     * Filtros que tienen todos los reportes de ventas: vendedor, tipo de comprobante ("sunat" = solo tributarios),
     * medio de pago y cliente (DNI/RUC o nombre).
     */
    private static function filtrosComunes($q, array $f)
    {
        $tipo = $f['tipo'] ?? '';

        return $q
            ->when(! empty($f['vendedor']), fn ($w) => $w->whereRaw('COALESCE(c.IdUsuario_ven, c.IdUsuario) = ?', [(int) $f['vendedor']]))
            ->when($tipo === 'sunat', fn ($w) => $w->whereIn('c.tdocod', ['01', '03', '07', '08']))
            ->when($tipo !== '' && $tipo !== 'sunat', fn ($w) => $w->where('c.tdocod', $tipo))
            ->when(! empty($f['medio']), fn ($w) => $w->whereExists(fn ($e) => $e->from('venta_medio_pago as vm')
                ->whereColumn('vm.IdCpe_cabecera', 'c.IdCpe_cabecera')->where('vm.id_med_pag', (int) $f['medio'])))
            ->when(trim((string) ($f['cliente'] ?? '')) !== '', function ($w) use ($f) {
                $c = trim($f['cliente']);
                $w->where(fn ($x) => $x->where('c.ccandi', 'like', $c.'%')->orWhere('c.ccanom', 'like', '%'.$c.'%'));
            });
    }

    private static function cpe(array $f)
    {
        return self::filtrosComunes(DB::table('cpe_cabecera as c')->where('c.id_empresa_negocio', $f['sucursal'])
            ->whereBetween('c.ccafem', [$f['desde'], $f['hasta']])->whereNull('c.ccabaj'), $f);
    }

    private static function detalle(array $f)
    {
        return self::filtrosComunes(DB::table('cpe_detalle as d')->join('cpe_cabecera as c', 'c.IdCpe_cabecera', '=', 'd.IdCpe_cabecera')
            ->where('c.id_empresa_negocio', $f['sucursal'])->whereBetween('c.ccafem', [$f['desde'], $f['hasta']])->whereNull('c.ccabaj'), $f);
    }

    // ================================================================== VENTAS

    private static function ventas(array $f): array
    {
        $estado = $f['estado'] ?? 'vigentes';
        $q = DB::table('cpe_cabecera as c')->leftJoin('users as u', 'u.IdUsuario', '=', DB::raw('COALESCE(c.IdUsuario_ven, c.IdUsuario)'))
            ->where('c.id_empresa_negocio', $f['sucursal'])->whereBetween('c.ccafem', [$f['desde'], $f['hasta']])
            ->when($estado === 'vigentes', fn ($w) => $w->whereNull('c.ccabaj'))->when($estado === 'anuladas', fn ($w) => $w->whereNotNull('c.ccabaj'))
            ->tap(fn ($w) => self::filtrosComunes($w, $f))
            ->orderBy('c.fecha_hora')->select('c.*', DB::raw("COALESCE(NULLIF(u.apeusu, ''), u.name) as vendedor"))->get();

        $filas = $q->map(function ($c) {
            $s = $c->tdocod === '07' ? -1 : 1;

            return [
                'fecha' => $c->fecha_hora, 'tipo' => self::TIPOS[$c->tdocod] ?? $c->tdocod, 'numero' => $c->serdoc.'-'.str_pad($c->numdoc, 8, '0', STR_PAD_LEFT),
                'doc' => $c->ccandi, 'cliente' => $c->ccanom, 'gravada' => $s * (float) $c->ccatvg, 'exonerada' => $s * round((float) $c->ccatexo + (float) $c->ccatinaf, 2),
                'igv' => $s * (float) $c->ccaigv, 'total' => $s * (float) $c->ccaitv, 'condicion' => $c->estadopago,
                'sunat' => $c->ccabaj ? 'ANULADO' : (in_array($c->tdocod, ['01', '03', '07', '08'], true) ? ($c->est_sunat ?? 'PENDIENTE') : 'INTERNO'),
                'vendedor' => $c->vendedor,
            ];
        })->all();

        return ['columnas' => [
            'fecha' => ['Fecha', 'datetime'], 'tipo' => ['Tipo', 'text'], 'numero' => ['Número', 'text'], 'doc' => ['DNI/RUC', 'text'],
            'cliente' => ['Cliente', 'text'], 'gravada' => ['Gravada', 'money'], 'exonerada' => ['Exon./Inaf.', 'money'], 'igv' => ['IGV', 'money'],
            'total' => ['Total', 'money'], 'condicion' => ['Condición', 'text'], 'sunat' => ['SUNAT', 'text'], 'vendedor' => ['Vendedor', 'text'],
        ], 'filas' => $filas, 'resumen' => [
            ['Comprobantes', count($filas)], ['Total vendido', array_sum(array_column($filas, 'total')), 'money'],
            ['Ticket promedio', count($filas) ? array_sum(array_column($filas, 'total')) / max(1, collect($filas)->where('tipo', '!=', 'N. CRÉDITO')->count()) : 0, 'money'],
        ], 'usa' => ['tipo', 'estado']];
    }

    private static function porVendedor(array $f): array
    {
        $porMozo = ($f['agrupar'] ?? 'vendedor') === 'mozo';
        $campo = $porMozo ? 'c.mozo' : 'COALESCE(c.IdUsuario_ven, c.IdUsuario)';
        $rows = self::cpe($f)->leftJoin('users as u', 'u.IdUsuario', '=', DB::raw($campo))
            ->when($porMozo, fn ($w) => $w->whereNotNull('c.mozo'))
            ->groupBy(DB::raw($campo), 'u.name', 'u.apeusu')
            ->select('u.name', 'u.apeusu', DB::raw("SUM(c.tdocod NOT IN ('07','08')) as docs"), DB::raw('SUM('.self::SIGNO.' * c.ccaitv) as total'),
                DB::raw('MIN(c.ccafem) as primera'), DB::raw('MAX(c.ccafem) as ultima'))
            ->orderByDesc('total')->get();
        $total = max(0.01, (float) $rows->sum('total'));
        $filas = $rows->map(fn ($r) => ['vendedor' => ($r->apeusu ?: $r->name) ?: 'SIN ASIGNAR', 'docs' => (int) $r->docs,
            'total' => (float) $r->total, 'ticket' => $r->docs ? (float) $r->total / $r->docs : 0, 'participacion' => (float) $r->total / $total * 100])->all();

        return ['columnas' => ['vendedor' => [$porMozo ? 'Mozo' : 'Vendedor / cajero', 'text'], 'docs' => ['Ventas', 'num'], 'total' => ['Total vendido', 'money'],
            'ticket' => ['Ticket promedio', 'money'], 'participacion' => ['% del total', 'pct']], 'filas' => $filas, 'usa' => ['agrupar'],
            'grafico' => ['etiqueta' => 'vendedor', 'valor' => 'total']];
    }

    private static function delivery(array $f): array
    {
        $rows = self::cpe($f)->where('c.ped_tip', 'Delivery')->leftJoin('pedidos as p', 'p.ped_id', '=', 'c.ped_id')
            ->leftJoin('users as u', 'u.IdUsuario', '=', 'c.IdUsuario')->leftJoin('motorizados as mo', 'mo.mot_id', '=', 'p.mot_id')
            ->when(! empty($f['motorizado']), fn ($w) => $f['motorizado'] === 'sin' ? $w->whereNull('p.mot_id') : $w->where('p.mot_id', (int) $f['motorizado']))
            ->orderBy('c.fecha_hora')->select('c.*', 'p.ped_dir', 'p.ped_tel', 'p.ped_cli_nom', 'mo.nombre as motorizado', DB::raw("COALESCE(NULLIF(u.apeusu, ''), u.name) as usuario"))->get();
        $medios = DB::table('venta_medio_pago as v')->join('medios_pagos as m', 'm.id_med_pag', '=', 'v.id_med_pag')
            ->whereIn('v.IdCpe_cabecera', $rows->pluck('IdCpe_cabecera'))->get()->groupBy('IdCpe_cabecera');
        $filas = $rows->map(fn ($c) => [
            'fecha' => $c->fecha_hora, 'numero' => $c->serdoc.'-'.$c->numdoc, 'cliente' => $c->ped_cli_nom ?: $c->ccanom,
            'direccion' => $c->ped_dir ?: $c->direccion, 'telefono' => $c->ped_tel ?: $c->telefono_cliente,
            'pago' => ($medios[$c->IdCpe_cabecera] ?? collect())->pluck('nom_med_pag')->unique()->implode(', ') ?: $c->estadopago,
            'total' => (float) $c->ccaitv, 'motorizado' => $c->motorizado ?: '—', 'usuario' => $c->usuario,
        ])->all();

        return ['columnas' => ['fecha' => ['Fecha', 'datetime'], 'numero' => ['Comprobante', 'text'], 'cliente' => ['Cliente', 'text'],
            'direccion' => ['Dirección', 'text'], 'telefono' => ['Teléfono', 'text'], 'pago' => ['Pago', 'text'], 'total' => ['Total', 'money'],
            'motorizado' => ['Motorizado', 'text'], 'usuario' => ['Registró', 'text']],
            'filas' => $filas, 'usa' => ['motorizado'], 'resumen' => [['Pedidos delivery', count($filas)], ['Total', array_sum(array_column($filas, 'total')), 'money'],
                ['Promedio por pedido', count($filas) ? array_sum(array_column($filas, 'total')) / count($filas) : 0, 'money']]];
    }

    /** Solo comprobantes tributarios (sin notas de venta), con su estado en SUNAT; las notas de crédito restan */
    private static function sunat(array $f): array
    {
        $estado = $f['estado_sunat'] ?? '';
        $rows = self::cpe($f)->whereIn('c.tdocod', ['01', '03', '07', '08'])
            ->when($estado === 'pendientes', fn ($w) => $w->whereIn('c.est_sunat', ['PENDIENTE', 'ERROR', 'RECHAZADO', 'EN RESUMEN']))
            ->when($estado !== '' && $estado !== 'pendientes', fn ($w) => $w->where('c.est_sunat', $estado))
            ->orderBy('c.ccafem')->orderBy('c.tdocod')->orderBy('c.serdoc')->orderBy('c.numdoc')->get();
        $filas = $rows->map(function ($c) {
            $s = $c->tdocod === '07' ? -1 : 1;

            return ['fecha' => $c->ccafem, 'tipo' => self::TIPOS[$c->tdocod] ?? $c->tdocod, 'numero' => $c->serdoc.'-'.str_pad($c->numdoc, 8, '0', STR_PAD_LEFT),
                'doc' => $c->ccandi, 'cliente' => $c->ccanom, 'gravada' => $s * (float) $c->ccatvg, 'exonerada' => $s * round((float) $c->ccatexo + (float) $c->ccatinaf, 2),
                'igv' => $s * (float) $c->ccaigv, 'total' => $s * (float) $c->ccaitv,
                'referencia' => $c->serie_ref ? $c->serie_ref.'-'.$c->num_ref : '', 'sunat' => $c->est_sunat ?? 'PENDIENTE'];
        })->all();
        $porEstado = collect($filas)->countBy('sunat');

        return ['columnas' => ['fecha' => ['Fecha', 'date'], 'tipo' => ['Tipo', 'text'], 'numero' => ['Número', 'text'], 'doc' => ['DNI/RUC', 'text'],
            'cliente' => ['Cliente', 'text'], 'gravada' => ['Gravada', 'money'], 'exonerada' => ['Exon./Inaf.', 'money'], 'igv' => ['IGV', 'money'],
            'total' => ['Total', 'money'], 'referencia' => ['Doc. ref.', 'text'], 'sunat' => ['SUNAT', 'text']],
            'filas' => $filas, 'usa' => ['estado_sunat'], 'resumen' => [
                ['Comprobantes', count($filas)], ['Total tributario', array_sum(array_column($filas, 'total')), 'money'],
                ['IGV', array_sum(array_column($filas, 'igv')), 'money'],
                ['Aceptados', (int) ($porEstado['ACEPTADO'] ?? 0) + (int) ($porEstado['OBSERVADO'] ?? 0)],
                ['Pendientes / con error', (int) ($porEstado['PENDIENTE'] ?? 0) + (int) ($porEstado['ERROR'] ?? 0) + (int) ($porEstado['RECHAZADO'] ?? 0) + (int) ($porEstado['EN RESUMEN'] ?? 0)],
            ]];
    }

    private static function porCliente(array $f): array
    {
        $rows = self::cpe($f)->groupBy('c.ccandi', 'c.ccanom')
            ->select('c.ccandi', 'c.ccanom', DB::raw("SUM(c.tdocod NOT IN ('07','08')) as compras"), DB::raw('SUM('.self::SIGNO.' * c.ccaitv) as total'),
                DB::raw('MAX(c.ccafem) as ultima'), DB::raw("SUM(c.estadopago = 'CREDITO') as credito"))
            ->orderByDesc('total')->get();
        $filas = $rows->map(fn ($r) => ['doc' => $r->ccandi, 'cliente' => $r->ccandi === '00000000' ? 'CLIENTES VARIOS (SIN DOCUMENTO)' : $r->ccanom,
            'compras' => (int) $r->compras, 'total' => (float) $r->total, 'ticket' => $r->compras ? (float) $r->total / $r->compras : 0,
            'credito' => (int) $r->credito, 'ultima' => $r->ultima])->all();

        return ['columnas' => ['doc' => ['DNI/RUC', 'text'], 'cliente' => ['Cliente', 'text'], 'compras' => ['Compras', 'num'], 'total' => ['Total', 'money'],
            'ticket' => ['Ticket promedio', 'money'], 'credito' => ['Al crédito', 'num'], 'ultima' => ['Última compra', 'date']], 'filas' => $filas,
            'grafico' => ['etiqueta' => 'cliente', 'valor' => 'total']];
    }

    private static function porProducto(array $f): array
    {
        $rows = self::detalle($f)->leftJoin('productos as p', 'p.IdProducto', '=', 'd.IdProducto')->leftJoin('categorias as cat', 'cat.cat_id', '=', 'p.cat_id')
            ->when(! empty($f['categoria']), fn ($w) => $w->where('p.cat_id', $f['categoria']))
            ->groupBy('d.IdProducto', 'd.cdedes', 'd.procod', 'cat.cat_nom')
            ->select('d.procod', 'd.cdedes', 'cat.cat_nom', DB::raw('SUM('.self::SIGNO_CANT.' * d.cdecan) as cantidad'),
                DB::raw('SUM('.self::SIGNO.' * d.cdevve) as total'), DB::raw('COUNT(DISTINCT c.IdCpe_cabecera) as tickets'))
            ->orderByDesc('total')->get();
        $total = max(0.01, (float) $rows->sum('total'));
        $filas = $rows->map(fn ($r) => ['codigo' => $r->procod, 'producto' => $r->cdedes, 'categoria' => $r->cat_nom ?? '—', 'cantidad' => (float) $r->cantidad,
            'precio' => $r->cantidad != 0 ? (float) $r->total / (float) $r->cantidad : 0, 'total' => (float) $r->total, 'tickets' => (int) $r->tickets,
            'participacion' => (float) $r->total / $total * 100])->all();

        return ['columnas' => ['codigo' => ['Código', 'text'], 'producto' => ['Producto', 'text'], 'categoria' => ['Categoría', 'text'], 'cantidad' => ['Cantidad', 'num'],
            'precio' => ['Precio prom.', 'money'], 'total' => ['Total', 'money'], 'tickets' => ['En ventas', 'num'], 'participacion' => ['% del total', 'pct']],
            'filas' => $filas, 'usa' => ['categoria'], 'grafico' => ['etiqueta' => 'producto', 'valor' => 'total']];
    }

    private static function ranking(array $f): array
    {
        $orden = ($f['orden'] ?? 'mas') === 'menos' ? 'asc' : 'desc';
        $limite = (int) ($f['limite'] ?? 20);
        $limite = in_array($limite, [10, 20, 50, 100], true) ? $limite : 20;
        $vendidos = self::detalle($f)->whereNotNull('d.IdProducto')->groupBy('d.IdProducto')
            ->select('d.IdProducto', DB::raw('SUM('.self::SIGNO_CANT.' * d.cdecan) as cantidad'), DB::raw('SUM('.self::SIGNO.' * d.cdevve) as total'));
        // Todos los productos que se venden (activos), con o sin ventas en el periodo
        $rows = DB::table('productos as p')->leftJoinSub($vendidos, 'v', 'v.IdProducto', '=', 'p.IdProducto')
            ->leftJoin('categorias as cat', 'cat.cat_id', '=', 'p.cat_id')
            ->where('p.id_empresa_negocio', $f['sucursal'])->where('p.proest', 'Activo')->where('p.promocion', '!=', 4)
            ->when(! empty($f['categoria']), fn ($w) => $w->where('p.cat_id', $f['categoria']))
            ->orderBy(DB::raw('COALESCE(v.cantidad, 0)'), $orden)->orderBy('p.pronom')->limit($limite)
            ->get(['p.procod', 'p.pronom', 'cat.cat_nom', 'p.propun', DB::raw('COALESCE(v.cantidad, 0) as cantidad'), DB::raw('COALESCE(v.total, 0) as total')]);
        $filas = $rows->values()->map(fn ($r, $i) => ['puesto' => $i + 1, 'codigo' => $r->procod, 'producto' => $r->pronom, 'categoria' => $r->cat_nom ?? '—',
            'precio' => (float) $r->propun, 'cantidad' => (float) $r->cantidad, 'total' => (float) $r->total])->all();

        return ['columnas' => ['puesto' => ['#', 'text'], 'codigo' => ['Código', 'text'], 'producto' => ['Producto', 'text'], 'categoria' => ['Categoría', 'text'],
            'precio' => ['Precio', 'money'], 'cantidad' => ['Cantidad vendida', 'num'], 'total' => ['Total', 'money']], 'filas' => $filas,
            'usa' => ['orden', 'limite', 'categoria'], 'grafico' => ['etiqueta' => 'producto', 'valor' => 'cantidad']];
    }

    private static function rentabilidad(array $f): array
    {
        $agrupar = in_array($f['agrupar'] ?? '', ['producto', 'categoria', 'dia', 'mes'], true) ? $f['agrupar'] : 'producto';
        [$campo, $etiqueta, $titulo] = match ($agrupar) {
            'categoria' => ['cat.cat_nom', DB::raw("COALESCE(cat.cat_nom, 'SIN CATEGORÍA') as nombre"), 'Categoría'],
            'dia' => ['c.ccafem', DB::raw('c.ccafem as nombre'), 'Día'],
            'mes' => [DB::raw("DATE_FORMAT(c.ccafem, '%Y-%m')"), DB::raw("DATE_FORMAT(c.ccafem, '%Y-%m') as nombre"), 'Mes'],
            default => ['d.cdedes', DB::raw('d.cdedes as nombre'), 'Producto'],
        };
        $rows = self::detalle($f)->leftJoin('productos as p', 'p.IdProducto', '=', 'd.IdProducto')->leftJoin('categorias as cat', 'cat.cat_id', '=', 'p.cat_id')
            ->groupBy($campo)->select($etiqueta, DB::raw('SUM('.self::SIGNO_CANT.' * d.cdecan) as cantidad'), DB::raw('SUM('.self::SIGNO.' * d.cdevve) as ventas'),
                DB::raw('SUM('.self::SIGNO_CANT.' * d.costo * d.cdecan) as costo'))
            ->orderBy(in_array($agrupar, ['dia', 'mes'], true) ? 'nombre' : 'ventas', in_array($agrupar, ['dia', 'mes'], true) ? 'asc' : 'desc')->get();
        $filas = $rows->map(function ($r) {
            $ut = (float) $r->ventas - (float) $r->costo;

            return ['nombre' => $r->nombre, 'cantidad' => (float) $r->cantidad, 'ventas' => (float) $r->ventas, 'costo' => (float) $r->costo,
                'utilidad' => $ut, 'margen' => (float) $r->ventas != 0 ? $ut / (float) $r->ventas * 100 : 0];
        })->all();
        $t = self::sumar(['cantidad' => [0, 'num'], 'ventas' => [0, 'money'], 'costo' => [0, 'money'], 'utilidad' => [0, 'money']], $filas);
        $t['margen'] = $t['ventas'] ? round($t['utilidad'] / $t['ventas'] * 100, 2) : 0;
        $sinCosto = $rows->filter(fn ($r) => (float) $r->costo == 0 && (float) $r->ventas > 0)->count();

        return ['columnas' => ['nombre' => [$titulo, $agrupar === 'dia' ? 'date' : 'text'], 'cantidad' => ['Cantidad', 'num'], 'ventas' => ['Ventas', 'money'],
            'costo' => ['Costo', 'money'], 'utilidad' => ['Utilidad', 'money'], 'margen' => ['Margen', 'pct']], 'filas' => $filas, 'totales' => $t,
            'usa' => ['agrupar_rent'], 'grafico' => ['etiqueta' => 'nombre', 'valor' => 'utilidad'],
            'nota' => $sinCosto ? "{$sinCosto} fila(s) no tienen costo registrado: su utilidad sale igual a la venta. Registra el costo en Productos o en Compras." : null];
    }

    // ================================================================== COMPRAS

    private static function compras(array $f): array
    {
        $rows = DB::table('compras_cabecera as c')->leftJoin('proveedor as p', 'p.prov_id', '=', 'c.prov_id')
            ->where('c.id_empresa_negocio', $f['sucursal'])->whereBetween('c.com_fec', [$f['desde'], $f['hasta']])
            ->when(($f['estado'] ?? 'vigentes') === 'vigentes', fn ($w) => $w->where('c.est_compra', 'Registrado'))
            ->when(($f['estado'] ?? '') === 'anuladas', fn ($w) => $w->where('c.est_compra', 'Anulado'))
            ->orderBy('c.com_fec')->select('c.*', 'p.prov_ruc', 'p.prov_raz')->get();
        $filas = $rows->map(function ($c) {
            $tc = $c->mon_id === 'USD' ? (float) $c->tip_cam : 1;

            return ['fecha' => $c->com_fec, 'tipo' => self::TIPOS[$c->tdocod] ?? $c->tdocod, 'numero' => $c->com_doc_ser.'-'.$c->com_doc_num,
                'ruc' => $c->prov_ruc, 'proveedor' => $c->prov_raz, 'moneda' => $c->mon_id, 'subtotal' => round((float) $c->subtot_com * $tc, 2),
                'igv' => round((float) $c->igv_com * $tc, 2), 'total' => round((float) $c->total_com * $tc, 2), 'condicion' => (float) $c->tot_cre > 0 ? 'CRÉDITO' : 'CONTADO',
                'saldo' => round((float) $c->saldofactura * $tc, 2), 'estado' => $c->est_compra];
        })->all();

        return ['columnas' => ['fecha' => ['Fecha', 'date'], 'tipo' => ['Tipo', 'text'], 'numero' => ['Número', 'text'], 'ruc' => ['RUC', 'text'],
            'proveedor' => ['Proveedor', 'text'], 'moneda' => ['Mon.', 'text'], 'subtotal' => ['Subtotal S/', 'money'], 'igv' => ['IGV S/', 'money'],
            'total' => ['Total S/', 'money'], 'condicion' => ['Condición', 'text'], 'saldo' => ['Por pagar S/', 'money'], 'estado' => ['Estado', 'text']],
            'filas' => $filas, 'usa' => ['estado'], 'resumen' => [['Compras', count($filas)], ['Total comprado', array_sum(array_column($filas, 'total')), 'money'],
                ['Por pagar', array_sum(array_column($filas, 'saldo')), 'money']]];
    }

    private static function comprasProveedor(array $f): array
    {
        $tc = "CASE WHEN c.mon_id = 'USD' THEN c.tip_cam ELSE 1 END";
        $rows = DB::table('compras_cabecera as c')->leftJoin('proveedor as p', 'p.prov_id', '=', 'c.prov_id')
            ->where('c.id_empresa_negocio', $f['sucursal'])->whereBetween('c.com_fec', [$f['desde'], $f['hasta']])->where('c.est_compra', 'Registrado')
            ->groupBy('c.prov_id', 'p.prov_ruc', 'p.prov_raz')
            ->select('p.prov_ruc', 'p.prov_raz', DB::raw('COUNT(*) as docs'), DB::raw("SUM(c.total_com * $tc) as total"), DB::raw("SUM(c.igv_com * $tc) as igv"),
                DB::raw("SUM(c.saldofactura * $tc) as saldo"), DB::raw('MAX(c.com_fec) as ultima'))
            ->orderByDesc('total')->get();
        $total = max(0.01, (float) $rows->sum('total'));
        $filas = $rows->map(fn ($r) => ['ruc' => $r->prov_ruc, 'proveedor' => $r->prov_raz, 'docs' => (int) $r->docs, 'igv' => (float) $r->igv, 'total' => (float) $r->total,
            'saldo' => (float) $r->saldo, 'participacion' => (float) $r->total / $total * 100, 'ultima' => $r->ultima])->all();

        return ['columnas' => ['ruc' => ['RUC', 'text'], 'proveedor' => ['Proveedor', 'text'], 'docs' => ['Compras', 'num'], 'igv' => ['IGV S/', 'money'],
            'total' => ['Total S/', 'money'], 'saldo' => ['Por pagar S/', 'money'], 'participacion' => ['% del total', 'pct'], 'ultima' => ['Última compra', 'date']],
            'filas' => $filas, 'grafico' => ['etiqueta' => 'proveedor', 'valor' => 'total']];
    }

    private static function comprasProducto(array $f): array
    {
        $tc = "CASE WHEN c.mon_id = 'USD' THEN c.tip_cam ELSE 1 END";
        $rows = DB::table('compras_detalle as d')->join('compras_cabecera as c', 'c.com_cab_id', '=', 'd.com_cab_id')
            ->leftJoin('productos as p', 'p.IdProducto', '=', 'd.pro_id')
            ->where('c.id_empresa_negocio', $f['sucursal'])->whereBetween('c.com_fec', [$f['desde'], $f['hasta']])->where('c.est_compra', 'Registrado')
            ->groupBy('d.pro_id', 'p.procod', 'p.pronom', 'd.ume_cod')
            ->select('p.procod', 'p.pronom', 'd.ume_cod', DB::raw('SUM(d.cantidad) as cantidad'), DB::raw("SUM(d.total * $tc) as total"),
                DB::raw('COUNT(DISTINCT c.com_cab_id) as compras'), DB::raw('MIN(d.precio_costo) as minimo'), DB::raw('MAX(d.precio_costo) as maximo'),
                DB::raw('MAX(c.com_fec) as ultima'))
            ->orderByDesc('total')->get();
        $filas = $rows->map(fn ($r) => ['codigo' => $r->procod, 'producto' => $r->pronom ?? '—', 'unidad' => $r->ume_cod, 'cantidad' => (float) $r->cantidad,
            'promedio' => $r->cantidad > 0 ? (float) $r->total / (float) $r->cantidad : 0, 'minimo' => (float) $r->minimo, 'maximo' => (float) $r->maximo,
            'total' => (float) $r->total, 'compras' => (int) $r->compras, 'ultima' => $r->ultima])->all();

        return ['columnas' => ['codigo' => ['Código', 'text'], 'producto' => ['Producto', 'text'], 'unidad' => ['Und.', 'text'], 'cantidad' => ['Cantidad', 'num'],
            'promedio' => ['Costo prom.', 'money'], 'minimo' => ['Costo mín.', 'money'], 'maximo' => ['Costo máx.', 'money'], 'total' => ['Total S/', 'money'],
            'compras' => ['Compras', 'num'], 'ultima' => ['Última compra', 'date']], 'filas' => $filas, 'grafico' => ['etiqueta' => 'producto', 'valor' => 'total']];
    }
}
