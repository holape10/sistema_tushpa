<?php

namespace App\Support\Antiguo;

use App\Http\Controllers\SucursalController;
use App\Models\Producto;
use App\Models\User;
use App\Support\Cuentas;
use App\Support\Descuentos;
use App\Support\Kardex;
use App\Support\Unidades;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Historial del sistema antiguo: ventas (facturas, boletas, notas de venta, notas de crédito/débito) con su detalle,
 * medios de pago, estado SUNAT, vendedor y turno de caja; turnos; proformas (y notas de pedido sin cobrar); compras con
 * su detalle; cuentas por cobrar/pagar con sus pagos; guías; gastos; tipo de cambio y kardex histórico del almacén.
 *
 * Las tablas del sistema nuevo tienen casi las mismas columnas que las antiguas: se copia cada columna que existe en
 * ambas y se reenlazan los IDs (cliente, producto, proveedor, medio de pago, nota → comprobante que modifica).
 * - No mueve stock: el kardex histórico se copia tal cual y el stock final lo cuadra la sección "Stock".
 * - Lo ACEPTADO por SUNAT queda aceptado y no se reenvía.
 * - Los correlativos de la sucursal continúan después del último número importado.
 * - No duplica: un comprobante con la misma serie y número (o compra del mismo proveedor y documento) se salta.
 */
class Historial
{
    private const LOTE = 500;

    /** Serie de la sucursal en el sistema nuevo => [campo de la serie, campo del número] en el sistema antiguo */
    private const SERIES_ANTIGUAS = [
        'FseEmpresa' => ['FseEmpresa', 'FnuEmpresa'], 'BseEmpresa' => ['BseEmpresa', 'BnuEmpresa'], 'SerNota' => ['SerNota', 'NumNota'],
        'SerNCF' => ['FcseEmpresa', 'FcnuEmpresa'], 'SerNCB' => ['BcseEmpresa', 'BcnuEmpresa'],
        'SerNDF' => ['FdseEmpresa', 'FdnuEmpresa'], 'SerNDB' => ['BdseEmpresa', 'BdnuEmpresa'],
        'SerProforma' => ['ProSer', 'ProNum'], 'SerGuia' => ['serieguia', 'numeroguia'],
    ];

    /** Lo que es venta: factura, boleta, notas de crédito/débito y nota de venta (vales, proformas y guías no) */
    public const VENTAS = ['01', '03', '07', '08', '13'];

    /** Presentación antigua (fila de productos con tipo = 2) => su producto principal antiguo */
    private array $padreDe = [];

    /** "IdProducto|factor" => id_presentacion nueva */
    private array $presentaciones = [];

    private array $destino = [];

    /** IdCpe_cabecera antiguo => nuevo */
    private array $mapaCpe = [];

    private array $mapaCompra = [];

    private array $resumen = ['ventas' => 0, 'detalle' => 0, 'medios' => 0, 'compras' => 0, 'cuentas' => 0, 'guias' => 0, 'repetidos' => 0,
        'turnos' => 0, 'proformas' => 0, 'gastos' => 0, 'kardex' => 0];

    /** IdUsuario antiguo => nuevo (por su login) */
    private array $mapaUsuario = [];

    /** Ventas cuyo vendedor no existe aquí */
    private int $sinUsuario = 0;

    /** id_turno antiguo => nuevo */
    private array $mapaTurno = [];

    /** IdCpe_cabecera antiguo => id_turno antiguo */
    private array $turnoDeCpe = [];

    /** Productos nuevos cuyo stock salió del kardex histórico (la sección Stock los cuadra) */
    public array $productosKardex = [];

    /** Comprobantes electrónicos que el sistema antiguo nunca envió a SUNAT (fecha del más antiguo) */
    private array $sinEnviar = ['n' => 0, 'desde' => null];

    /** IdUsuario del sistema antiguo que existen en su tabla users */
    private array $usuariosAntiguos = [];

    /** Ventas de un vendedor que el sistema antiguo ya no tiene */
    private int $sinUsuarioBorrado = 0;

    /** Comprobantes rechazados por SUNAT que entraron descartados */
    private int $rechazados = 0;

    /** Comprobantes ya contados como no enviados */
    private array $contados = [];

    /**
     * @param  \Closure  $src  fn(string $tabla) => query builder de la base antigua
     * @param  \Closure  $hay  fn(string $tabla) => bool
     * @param  \Closure  $filtro  fn($query, string $tabla) => query filtrada por la sucursal antigua
     * @param  \Closure  $aviso  fn(string $texto)
     * @param  array  $mapaProductos  IdProducto antiguo => Producto nuevo
     * @param  array{almacen?: int|null, negocio?: object|null}  $opciones  almacén antiguo de la sucursal (para su kardex) y su
     *                                                                      fila de empresa_negocios (series que usaba)
     */
    public function __construct(
        private User $user,
        private \Closure $src,
        private \Closure $hay,
        private \Closure $filtro,
        private \Closure $aviso,
        private array $mapaProductos,
        private array $opciones = [],
    ) {}

    public function importar(): array
    {
        set_time_limit(0);
        // Datos viejos con fechas 0000-00-00 o textos más largos: se aceptan como en el sistema antiguo
        $modo = DB::selectOne('SELECT @@SESSION.sql_mode AS m')->m;
        DB::statement("SET SESSION sql_mode = 'NO_ENGINE_SUBSTITUTION'");
        try {
            $this->cargarPresentaciones();
            $this->cargarUsuarios();
            if (($this->hay)('turnos')) {
                $this->turnos();
            }
            if (($this->hay)('cpe_cabecera')) {
                $this->ventas();
                $this->correlativos();
                $this->proformas();
            }
            if (($this->hay)('compras_cabecera')) {
                $this->compras();
            }
            $this->cuentas('cobrar');
            $this->cuentas('pagar');
            if (($this->hay)('guias_remision') && Schema::hasTable('gre_cabecera')) {
                $this->guias();
            }
            if (($this->hay)('gastos_cabecera')) {
                $this->gastos();
            }
            $this->tipoCambio();
            $this->kardex();
        } finally {
            DB::statement('SET SESSION sql_mode = ?', [$modo]);
        }

        return $this->resumen;
    }

    /**
     * Estado SUNAT con los nombres del sistema nuevo: "ACEPTADO CON OBS." → OBSERVADO; enviado sin estado (boletas que
     * fueron en un resumen) → ACEPTADO; nunca enviado → PENDIENTE (se avisa). Así lo ya enviado no se puede editar ni reenviar.
     */
    private function estadoSunat(object $c): ?string
    {
        $estado = mb_strtoupper(trim((string) ($c->est_sunat ?? '')));
        if (! in_array($c->tdocod, ['01', '03', '07', '08'], true)) {
            return $estado ?: null;
        }
        // Algunos sistemas antiguos guardan el estado en "estado" y la respuesta de SUNAT en "ccacodsun"
        $otro = mb_strtoupper(trim((string) ($c->estado ?? '')));
        $codigo = trim((string) ($c->ccacodsun ?? ''));
        if ($estado === 'ANULADO' || $otro === 'ANULADO' || (! empty($c->ccabaj) && ! str_starts_with((string) $c->ccabaj, '0000'))) {
            return 'ANULADO';
        }
        if (str_starts_with($estado, 'ACEPTADO CO') || str_starts_with($estado, 'OBSERVADO')) {
            return 'OBSERVADO';
        }
        if ($estado !== '') {
            return $estado;
        }
        // 0 = aceptado; 1033 = SUNAT ya lo tenía registrado de un envío anterior (también está aceptado)
        if (in_array($codigo, ['0', '1033'], true) || $otro === 'ACEPTADO') {
            return 'ACEPTADO';
        }
        if ($codigo !== '' && ctype_digit($codigo) && (int) $codigo >= 1000) {
            return 'RECHAZADO';
        }
        if ((int) ($c->enviado ?? 0) === 1 || ! empty($c->ticket)) {
            return 'ACEPTADO';
        }
        if (empty($c->ccabaj) && ! isset($this->contados[$c->IdCpe_cabecera])) {
            $this->contados[$c->IdCpe_cabecera] = true;
            $this->sinEnviar['n']++;
            $fecha = (string) ($c->ccafem ?? '');
            $this->sinEnviar['desde'] = $this->sinEnviar['desde'] === null || ($fecha !== '' && $fecha < $this->sinEnviar['desde']) ? $fecha : $this->sinEnviar['desde'];
        }

        return 'PENDIENTE';
    }

    /** Las ventas y compras de una presentación (fardo, millar…) apuntan a su producto principal, como en el sistema nuevo */
    private function cargarPresentaciones(): void
    {
        try {
            $this->padreDe = ($this->src)('productos')->where('tipo', 2)->whereNotNull('pro_rel')->pluck('pro_rel', 'IdProducto')->all();
        } catch (\Throwable $e) {
            $this->padreDe = [];
        }
        $ids = collect($this->mapaProductos)->pluck('IdProducto')->unique()->all();
        foreach (array_chunk($ids, 1000) as $parte) {
            foreach (DB::table('producto_presentacion')->whereIn('IdProducto', $parte)->get(['id_presentacion', 'IdProducto', 'factor']) as $pp) {
                $this->presentaciones[$pp->IdProducto.'|'.round((float) $pp->factor, 3)] = $pp->id_presentacion;
            }
        }
    }

    /** Producto nuevo de una línea antigua: el mismo, o el principal si era una presentación */
    private function productoDe($idAntiguo, $idRel = null): ?Producto
    {
        return $this->mapaProductos[$idAntiguo ?? 0]
            ?? $this->mapaProductos[$this->padreDe[$idAntiguo ?? 0] ?? 0]
            ?? $this->mapaProductos[$idRel ?? 0]
            ?? null;
    }

    private function columnas(string $tabla): array
    {
        return $this->destino[$tabla] ??= array_flip(Schema::getColumnListing($tabla));
    }

    /** Columnas de la fila antigua que también existen en la tabla nueva (sin la clave primaria ni las que se fijan aparte) */
    private function copiar(string $tabla, object $fila, array $fijos, array $excluir = []): array
    {
        $cols = $this->columnas($tabla);
        $datos = [];
        foreach ((array) $fila as $col => $valor) {
            // Las columnas que se fijan aparte nunca toman el valor antiguo (IDs de otra base)
            if (! isset($cols[$col]) || array_key_exists($col, $fijos) || in_array($col, $excluir, true) || $valor === null) {
                continue;
            }
            // Fechas vacías del sistema antiguo
            if (is_string($valor) && str_starts_with($valor, '0000-00-00')) {
                continue;
            }
            $datos[$col] = $valor;
        }

        // Un fijo en null = usar el valor por defecto de la columna
        return array_filter($fijos, fn ($v) => $v !== null) + $datos;
    }

    private function aviso(string $t): void
    {
        ($this->aviso)($t);
    }

    // ------------------------------------------------------------------ ventas

    private function ventas(): void
    {
        $user = $this->user;
        $suc = $user->id_empresa_negocio;
        $almacen = Kardex::almacenPredeterminado($suc)?->id_almacen;

        $clientes = DB::table('cliente')->where('rucemp', $user->IdEmpresa)->pluck('clicod', 'clinum');
        $medios = $this->mapaPorNombre('medios_pagos', 'id_med_pag', 'nom_med_pag', DB::table('medios_pagos')->where('id_empresa_negocio', $suc));
        // Pago con un medio que no existe aquí: queda con el medio predeterminado (se avisa)
        $medioPred = DB::table('medios_pagos')->where('id_empresa_negocio', $suc)->orderByDesc('predeterminado')->value('id_med_pag');
        $sinMedio = 0;
        $creditos = $this->mapaPorNombre('credito_dias', 'cre_dia_id', 'cre_dia_nom', DB::table('credito_dias')->where('id_empresa_negocio', $suc));
        $existentes = DB::table('cpe_cabecera')->where('id_empresa_negocio', $suc)
            ->get(['IdCpe_cabecera', 'tdocod', 'serdoc', 'numdoc'])
            ->mapWithKeys(fn ($c) => [$c->tdocod.'|'.$c->serdoc.'|'.(int) $c->numdoc => $c->IdCpe_cabecera])->all();
        $sinDetalle = 0;
        $referencias = [];
        $otros = ($this->filtro)(($this->src)('cpe_cabecera'), 'cpe_cabecera')->whereNotIn('tdocod', self::VENTAS)
            ->groupBy('tdocod')->selectRaw('tdocod, COUNT(*) AS n')->pluck('n', 'tdocod');
        if ($otros->isNotEmpty()) {
            $nombres = ['14' => 'vales', '15' => 'proformas', '16' => 'notas de pedido', '85' => 'vales de salida', '86' => 'vales de ingreso', '09' => 'guías'];
            $this->aviso('No se importaron como ventas (no son comprobantes de venta): '
                .$otros->map(fn ($n, $t) => $n.' '.($nombres[$t] ?? "documentos tipo {$t}"))->implode(', ').'.');
        }

        $pagoSupuesto = 0;
        // El mismo número dos veces en el sistema antiguo (uno anulado en 0 y otro aceptado): queda el válido
        $descartados = [];
        $repetidos = ($this->filtro)(($this->src)('cpe_cabecera'), 'cpe_cabecera')->whereIn('tdocod', self::VENTAS)
            ->groupBy('tdocod', 'serdoc', 'numdoc')->havingRaw('COUNT(*) > 1')->get(['tdocod', 'serdoc', 'numdoc']);
        foreach ($repetidos as $r) {
            $filas = ($this->filtro)(($this->src)('cpe_cabecera'), 'cpe_cabecera')->where('tdocod', $r->tdocod)->where('serdoc', $r->serdoc)->where('numdoc', $r->numdoc)->get()
                ->sortByDesc(fn ($c) => sprintf('%d|%015.3f|%012d', $this->estadoSunat($c) === 'ANULADO' ? 0 : 1, (float) $c->ccaitv, (int) $c->IdCpe_cabecera))->values();
            foreach ($filas->slice(1) as $perdedor) {
                $descartados[(int) $perdedor->IdCpe_cabecera] = (int) $filas[0]->IdCpe_cabecera;
            }
            $this->aviso("{$r->serdoc}-{$r->numdoc} estaba ".$filas->count().' veces en el sistema antiguo: se pasó el '
                .mb_strtolower((string) $this->estadoSunat($filas[0])).' de S/ '.number_format((float) $filas[0]->ccaitv, 2).'.');
        }
        ($this->filtro)(($this->src)('cpe_cabecera'), 'cpe_cabecera')->whereIn('tdocod', self::VENTAS)->orderBy('IdCpe_cabecera')
            ->chunk(self::LOTE, function ($lote) use ($user, $suc, $almacen, $clientes, $medios, $medioPred, &$sinMedio, $creditos, &$existentes, &$sinDetalle, &$referencias, &$pagoSupuesto, $descartados) {
                $nuevos = [];
                $cabeceras = [];
                foreach ($lote as $c) {
                    if (isset($descartados[(int) $c->IdCpe_cabecera])) {
                        continue;
                    }
                    $clave = $c->tdocod.'|'.$c->serdoc.'|'.(int) $c->numdoc;
                    if (isset($existentes[$clave])) {
                        $this->mapaCpe[$c->IdCpe_cabecera] = $existentes[$clave];
                        $this->resumen['repetidos']++;

                        continue;
                    }
                    $datos = $this->copiar('cpe_cabecera', $c, [
                        'IdEmpresa' => $user->IdEmpresa, 'id_empresa_negocio' => $suc,
                        // Quién la emitió y quién vendió (reportes por vendedor y por caja)
                        'IdUsuario' => $this->usuario($c->IdUsuario ?? 0) ?? $user->IdUsuario,
                        'IdUsuario_ven' => $this->vendedor($c),
                        'id_turno' => $this->turnoDe($c),
                        'id_almacen' => $almacen, 'ped_tip' => 'ANTIGUO',
                        'clicod' => $clientes[$c->ccandi ?? ''] ?? null,
                        'cre_dia_id' => isset($c->cre_dia_id) ? ($creditos[$c->cre_dia_id] ?? null) : null,
                    ], ['IdCpe_cabecera', 'id_turno', 'IdUsuario_ven', 'IdUsuarioCob', 'mozo', 'ped_id', 'pis_id', 'mes_id', 'IdCpe_cabecera_ref', 'res_id', 'cuen_ban_id']);
                    $datos['est_sunat'] = $this->estadoSunat($c);
                    // Rechazado por SUNAT: para SUNAT no existe (casi siempre se volvió a emitir con otro número). Entra
                    // descartado, como lo hace el panel: no cuenta como venta ni como deuda y no sale en pendientes
                    if ($datos['est_sunat'] === 'RECHAZADO' && empty($datos['ccabaj'])) {
                        $datos['ccabaj'] = 'DESCARTADO';
                        $datos['motivo_baja'] = 'RECHAZADO POR SUNAT (SISTEMA ANTIGUO)';
                        $this->rechazados++;
                    }
                    $id = DB::table('cpe_cabecera')->insertGetId(array_filter($datos, fn ($v) => $v !== null));
                    $this->mapaCpe[$c->IdCpe_cabecera] = $id;
                    $existentes[$clave] = $id;
                    $nuevos[$c->IdCpe_cabecera] = $id;
                    $cabeceras[$c->IdCpe_cabecera] = $c;
                    if (! empty($c->IdCpe_cabecera_ref)) {
                        $referencias[$id] = $c->IdCpe_cabecera_ref;
                    }
                    $this->resumen['ventas']++;
                }
                if (! $nuevos) {
                    return;
                }

                // Detalle de los comprobantes nuevos
                $filas = [];
                $conDetalle = [];
                $bonificaciones = 0;
                if (($this->hay)('cpe_detalle')) {
                    foreach (($this->src)('cpe_detalle')->whereIn('IdCpe_cabecera', array_keys($nuevos))->orderBy('IdCpe_detalle')->get() as $d) {
                        $prod = $this->productoDe($d->IdProducto ?? null, $d->IdProducto_rel ?? null);
                        // Costo por unidad vendida: el del sistema antiguo; si no lo guardó (casi siempre 0), el costo actual del
                        // producto. Sin esto esas ventas salían con 100% de utilidad en la rentabilidad.
                        $costo = (float) ($d->costo ?? 0);
                        if ($costo <= 0 && $prod && (float) $prod->costo > 0) {
                            $costo = round((float) $prod->costo * max(1, (float) ($d->cpe_det_factor ?? 1)), 2);
                        }
                        // Bonificación del sistema antiguo: el producto va con precio 0.001 (o 0) y la línea en S/ 0
                        $bonificada = (float) $d->cdecan > 0 && (float) ($d->cdevve ?? 0) <= 0.01 && (float) ($d->cdepuni ?? 0) <= 0.001 + 1e-9 && $prod;
                        $filas[] = $this->copiar('cpe_detalle', $d, [
                            'IdCpe_cabecera' => $nuevos[$d->IdCpe_cabecera],
                            'IdProducto' => $prod?->IdProducto, 'IdProducto_rel' => $prod?->IdProducto,
                            'id_almacen_pro' => $almacen, 'costo' => $costo > 0 ? $costo : null,
                        ] + ($bonificada ? ['cortesia' => 1, 'cortesia_motivo' => Descuentos::MOTIVO_BONIFICACION, 'cdevve' => 0, 'cdepve' => 0, 'cdeigv' => 0] : []),
                            ['IdCpe_detalle', 'IdCpe_detalle_ref']);
                        $bonificaciones += $bonificada ? 1 : 0;
                        $conDetalle[$d->IdCpe_cabecera] = true;
                    }
                }
                $this->insertar('cpe_detalle', $filas);
                $this->resumen['detalle'] += count($filas);
                // Daba bonificaciones: se le activan en el Punto de venta para que siga igual
                if ($bonificaciones && Schema::hasColumn('empresa_negocios', 'permite_bonificaciones')) {
                    DB::table('empresa_negocios')->where('id_empresa_negocio', $suc)->update(['permite_bonificaciones' => 1]);
                }
                $sinDetalle += count(array_diff_key($nuevos, $conDetalle));

                // Medios de pago
                if (($this->hay)('venta_medio_pago')) {
                    $vmp = [];
                    $pagadas = [];
                    foreach (($this->src)('venta_medio_pago')->whereIn('IdCpe_cabecera', array_keys($nuevos))->get() as $m) {
                        $medio = $medios[$m->id_med_pag ?? 0] ?? null;
                        if (! $medio) {
                            $sinMedio++;
                            $medio = $medioPred;
                        }
                        if (! $medio) {
                            continue;
                        }
                        $vmp[] = $this->copiar('venta_medio_pago', $m, [
                            'IdCpe_cabecera' => $nuevos[$m->IdCpe_cabecera], 'id_med_pag' => $medio, 'id_empresa_negocio' => $suc,
                            'id_turno' => $this->turnoDe($cabeceras[$m->IdCpe_cabecera]),
                        ], ['ven_med_pag_id']);
                        $pagadas[$m->IdCpe_cabecera] = true;
                    }
                    // Venta al contado sin medio de pago en el sistema antiguo: se cobró con el medio predeterminado
                    foreach (array_diff_key($cabeceras, $pagadas) as $idAntiguo => $c) {
                        if ($medioPred && in_array($c->tdocod, ['01', '03', '13'], true) && mb_strtoupper((string) ($c->estadopago ?? 'CONTADO')) !== 'CREDITO'
                            && (float) $c->ccaitv > 0 && $this->estadoSunat($c) !== 'ANULADO') {
                            $vmp[] = ['IdCpe_cabecera' => $nuevos[$idAntiguo], 'id_med_pag' => $medioPred, 'monto' => round((float) $c->ccaitv, 2),
                                // Sin turno en el sistema antiguo: 0 (la columna no admite vacío; con null fallaba TODO el historial)
                                'id_empresa_negocio' => $suc, 'id_turno' => $this->turnoDe($c) ?? 0];
                            $pagoSupuesto++;
                        }
                    }
                    $this->insertar('venta_medio_pago', $vmp);
                    $this->resumen['medios'] += count($vmp);
                }
            });

        foreach ($descartados as $perdedor => $ganador) {
            if (isset($this->mapaCpe[$ganador])) {
                $this->mapaCpe[$perdedor] = $this->mapaCpe[$ganador];
            }
        }
        if ($this->sinUsuarioBorrado) {
            $this->aviso("{$this->sinUsuarioBorrado} ventas son de un usuario que ya no existe en el sistema antiguo (lo borraron): quedaron sin vendedor.");
        }
        if ($this->sinUsuario) {
            $this->aviso("{$this->sinUsuario} ventas son de usuarios que aún no existen aquí (de otra sucursal): quedaron sin vendedor. "
                .'Importa primero los usuarios de todas las sucursales y luego el historial (el comando antiguo:importar --mapa lo hace solo).');
        }

        // Notas de crédito / débito: apuntan al comprobante que modifican
        foreach ($referencias as $nuevoId => $refAntigua) {
            if (isset($this->mapaCpe[$refAntigua])) {
                DB::table('cpe_cabecera')->where('IdCpe_cabecera', $nuevoId)->update(['IdCpe_cabecera_ref' => $this->mapaCpe[$refAntigua]]);
            }
        }
        if ($this->sinEnviar['n']) {
            $this->aviso("{$this->sinEnviar['n']} comprobantes electrónicos nunca se enviaron a SUNAT en el sistema antiguo (desde el {$this->sinEnviar['desde']}): "
                .'quedaron PENDIENTES en la campanita. Revisa con el cliente si deben enviarse.');
        }
        if ($this->rechazados) {
            $this->aviso("{$this->rechazados} comprobantes estaban RECHAZADOS por SUNAT: se importaron descartados (no cuentan como venta ni como deuda; "
                .'casi siempre se volvieron a emitir con otro número).');
        }
        if ($sinDetalle) {
            $this->aviso("{$sinDetalle} comprobantes no tenían detalle en el sistema antiguo: se importaron con su total, cliente y estado, sin líneas de productos.");
        }
        if ($sinMedio) {
            $this->aviso("{$sinMedio} pagos usaban un medio de pago que no existe en el sistema nuevo: quedaron con el medio predeterminado.");
        }
        if ($pagoSupuesto) {
            $this->aviso("{$pagoSupuesto} ventas al contado no tenían medio de pago en el sistema antiguo: quedaron cobradas con el medio predeterminado (efectivo).");
        }
    }

    /**
     * Los correlativos continúan después del último número importado. Si la sucursal aún no emitió con su serie
     * configurada, adopta la serie del sistema antiguo (así F001 sigue en 318 y no vuelve a 1).
     */
    private function correlativos(): void
    {
        $suc = $this->user->id_empresa_negocio;
        $importados = DB::table('cpe_cabecera')->where('id_empresa_negocio', $suc)->where('ped_tip', 'ANTIGUO')
            ->groupBy('tdocod', 'serdoc')->selectRaw('tdocod, serdoc, MAX(numdoc) AS ultimo, COUNT(*) AS n')->get();
        $legado = $this->opciones['negocio'] ?? null;
        if ($importados->isEmpty() && ! $legado) {
            return;
        }
        $negocio = DB::table('empresa_negocios')->where('id_empresa_negocio', $suc)->first();
        $cambios = [];

        foreach (SucursalController::SERIES as $clave => $c) {
            $tdocod = (string) ($c['tdocod'] ?? $clave); // la clave '13' llega como número
            $serieActual = $negocio->{$c['serie']};
            $deVenta = ! in_array($tdocod, ['PR', '09'], true);
            // ¿La serie configurada ya se usó con comprobantes del sistema nuevo?
            $usada = $deVenta && DB::table('cpe_cabecera')->where('id_empresa_negocio', $suc)->where('tdocod', $tdocod)
                ->where('serdoc', $serieActual)->where(fn ($w) => $w->whereNull('ped_tip')->orWhere('ped_tip', '!=', 'ANTIGUO'))->exists();

            // La serie que la sucursal usaba en el sistema antiguo (así cada sucursal sigue con la suya: F001, F002…)
            [$campoSerie, $campoNumero] = self::SERIES_ANTIGUAS[$c['serie']] ?? [null, null];
            $serieAntigua = $legado && $campoSerie ? strtoupper(trim((string) ($legado->$campoSerie ?? ''))) : '';
            if ($serieAntigua !== '' && preg_match($c['regex'], $serieAntigua)) {
                if ($usada && $serieAntigua !== $serieActual) {
                    $this->aviso("{$c['nombre']}: el sistema antiguo usaba la serie {$serieAntigua} y aquí ya se emitió con {$serieActual}; se mantiene {$serieActual}.");

                    continue;
                }
                $ultimo = max((int) ltrim((string) ($legado->$campoNumero ?? '0'), '0'),
                    (int) ($importados->first(fn ($i) => $i->tdocod === $tdocod && $i->serdoc === $serieAntigua)->ultimo ?? 0),
                    $serieAntigua === $serieActual ? (int) $negocio->{$c['numero']} : 0);
                if ($serieAntigua !== $serieActual || $ultimo !== (int) $negocio->{$c['numero']}) {
                    $cambios[$c['serie']] = $serieAntigua;
                    $cambios[$c['numero']] = $ultimo;
                }

                continue;
            }
            if (! $deVenta) {
                continue;
            }
            $candidatas = $importados->filter(fn ($i) => $i->tdocod === $tdocod && preg_match($c['regex'], $i->serdoc));
            if ($candidatas->isEmpty()) {
                continue;
            }
            $propia = $candidatas->firstWhere('serdoc', $serieActual);
            if ($propia) {
                if ((int) $propia->ultimo > (int) $negocio->{$c['numero']}) {
                    $cambios[$c['numero']] = (int) $propia->ultimo;
                }

                continue;
            }
            $mayor = $candidatas->sortByDesc('n')->first();
            if ($usada) {
                $this->aviso("{$c['nombre']}: el sistema antiguo usaba la serie {$mayor->serdoc} y aquí ya se emitió con {$serieActual}; se mantiene {$serieActual}.");

                continue;
            }
            $cambios[$c['serie']] = $mayor->serdoc;
            $cambios[$c['numero']] = (int) $mayor->ultimo;
        }

        if ($cambios) {
            DB::table('empresa_negocios')->where('id_empresa_negocio', $suc)->update($cambios);
            $texto = [];
            foreach (SucursalController::SERIES as $clave => $c) {
                if (isset($cambios[$c['numero']])) {
                    $serie = $cambios[$c['serie']] ?? $negocio->{$c['serie']};
                    $texto[] = "{$c['nombre']} sigue en {$serie}-".str_pad($cambios[$c['numero']] + 1, 8, '0', STR_PAD_LEFT);
                }
            }
            $this->aviso('Correlativos actualizados: '.implode(' · ', $texto).'.');
        }

        // Dos sucursales con la misma serie repetirían números ante SUNAT
        $negocio = DB::table('empresa_negocios')->where('id_empresa_negocio', $suc)->first();
        foreach (SucursalController::SERIES as $c) {
            $serie = $negocio->{$c['serie']};
            $tdocod = (string) ($c['tdocod'] ?? '');
            $otra = $serie && ! in_array($tdocod, ['PR', '09'], true)
                ? DB::table('empresa_negocios as n')->where('n.id_empresa_negocio', '!=', $suc)->where('n.'.$c['serie'], $serie)
                    ->whereExists(fn ($q) => $q->from('cpe_cabecera as x')->whereColumn('x.id_empresa_negocio', 'n.id_empresa_negocio')
                        ->where('x.serdoc', $serie)->where(fn ($w) => $w->whereNull('x.ped_tip')->orWhere('x.ped_tip', '!=', 'ANTIGUO')))
                    ->value('n.nombre_comercial')
                : null;
            if ($otra) {
                $this->aviso("¡OJO! {$c['nombre']}: la serie {$serie} también la tiene la sucursal {$otra}. Cambia una en Sucursales antes de emitir.");
            }
        }
    }

    // ------------------------------------------------------------------ guías de remisión

    /**
     * Guías de remisión del sistema antiguo (guias_remision) con sus productos, estado en SUNAT y la venta que las originó.
     * Las ya aceptadas quedan como aceptadas (no se reenvían); el correlativo de guías sigue después de la última.
     */
    private function guias(): void
    {
        $suc = (int) $this->user->id_empresa_negocio;
        $motivos = ($this->hay)('motivo_traslado') ? ($this->src)('motivo_traslado')->pluck('motivo', 'IdMotivo')->map(fn ($m) => trim((string) $m)) : collect();
        $existentes = DB::table('gre_cabecera')->where('id_empresa_negocio', $suc)->get(['serie', 'numero'])->map(fn ($g) => $g->serie.'-'.(int) $g->numero)->flip();
        $hayDetalle = ($this->hay)('guias_remision_detalle');
        $sinEnviar = 0;

        // Guía repetida en el sistema antiguo: queda la que tiene mejor estado (aceptada antes que pendiente)
        $rango = ['ACEPTADO' => 5, 'ENVIADO' => 4, 'PENDIENTE' => 3, 'RECHAZADO' => 2, 'ANULADO' => 1];
        $mejor = [];
        foreach (($this->filtro)(($this->src)('guias_remision'), 'guias_remision')->get() as $g) {
            $clave = strtoupper(trim((string) $g->serieguia)).'-'.(int) $g->numeroguia;
            $puntos = ($rango[self::estadoGuia($g)] ?? 0) * 10000000 + (int) $g->IdCpe_guia;
            if (! isset($mejor[$clave]) || $puntos > $mejor[$clave][0]) {
                $mejor[$clave] = [$puntos, (int) $g->IdCpe_guia];
            }
        }
        $elegidas = array_flip(array_column($mejor, 1));
        ($this->filtro)(($this->src)('guias_remision'), 'guias_remision')->orderBy('IdCpe_guia')
            ->chunk(self::LOTE, function ($lote) use ($suc, $motivos, &$existentes, $hayDetalle, &$sinEnviar, $elegidas) {
                $detalles = $hayDetalle ? ($this->src)('guias_remision_detalle')->whereIn('IdCpe_guia', $lote->pluck('IdCpe_guia'))->get()->groupBy('IdCpe_guia') : collect();
                foreach ($lote as $g) {
                    if (! isset($elegidas[(int) $g->IdCpe_guia])) {
                        continue;
                    }
                    $serie = strtoupper(trim((string) $g->serieguia));
                    $clave = $serie.'-'.(int) $g->numeroguia;
                    if (isset($existentes[$clave])) {
                        $this->resumen['repetidos']++;

                        continue;
                    }
                    if ($serie === '' || ! (int) $g->numeroguia) {
                        continue;
                    }
                    $estado = self::estadoGuia($g);
                    $sinEnviar += $estado === 'PENDIENTE' ? 1 : 0;
                    $id = DB::table('gre_cabecera')->insertGetId(self::filaGuia($g, $estado, $motivos) + [
                        'IdCpe_cabecera' => $this->mapaCpe[$g->IdCpe_cabecera ?? 0] ?? null, 'token' => Str::random(32),
                        'IdUsuario' => $this->user->IdUsuario, 'IdEmpresa' => $this->user->IdEmpresa, 'id_empresa_negocio' => $suc,
                    ]);
                    $existentes[$clave] = true;
                    $filas = [];
                    foreach ($detalles[$g->IdCpe_guia] ?? [] as $d) {
                        $filas[] = ['gre_id' => $id, 'IdProducto' => $this->productoDe($d->IdProducto, $d->IdProducto_rel ?? null)?->IdProducto,
                            'codigo' => self::texto($d->procod, 30), 'descripcion' => mb_strtoupper(mb_substr((string) ($d->pronom ?: '-'), 0, 250)),
                            'umecod' => Unidades::codigo($d->umecod) ?? 'NIU', 'cantidad' => round((float) $d->cantidad, 3)];
                    }
                    $this->insertar('gre_detalle', $filas);
                    $this->resumen['guias']++;
                }
            });

        $this->correlativoGuias($suc);
        if ($sinEnviar) {
            $this->aviso("{$sinEnviar} guía(s) de remisión del sistema antiguo no figuran como enviadas a SUNAT: revísalas en Guías de remisión.");
        }
    }

    private static function texto($v, int $largo): ?string
    {
        $v = trim((string) $v);

        return $v === '' ? null : mb_substr($v, 0, $largo);
    }

    /** Columnas de gre_cabecera a partir de la fila antigua */
    private static function filaGuia(object $g, string $estado, $motivos): array
    {
        $fecha = $g->fechaemision && $g->fechaemision !== '0000-00-00' ? $g->fechaemision : now()->toDateString();
        $hora = preg_match('/^\d{2}:\d{2}(:\d{2})?$/', (string) ($g->hora ?? '')) ? $g->hora : '00:00:00';
        $referencia = self::texto($g->serie_num_ref ?? null, 15)
            ?? (trim((string) ($g->serieref ?? '')) !== '' ? strtoupper(trim($g->serieref)).'-'.(int) $g->numeroref : null);
        $peso = Unidades::sunat($g->umecod ?: 'KGM');

        return [
            'serie' => strtoupper(trim((string) $g->serieguia)), 'numero' => (int) $g->numeroguia, 'fecha_emision' => $fecha, 'hora_emision' => $hora,
            'fecha_traslado' => $g->fechatraslado && $g->fechatraslado !== '0000-00-00' ? $g->fechatraslado : $fecha,
            'motivo' => str_pad((string) ($g->IdMotivo ?: '01'), 2, '0', STR_PAD_LEFT), 'motivo_desc' => self::texto($motivos[$g->IdMotivo] ?? null, 100),
            'modalidad' => str_pad((string) ($g->IdModalidad ?: '01'), 2, '0', STR_PAD_LEFT),
            'peso' => round((float) $g->pesobruto, 3), 'unidad_peso' => $peso === 'NIU' ? 'KGM' : $peso, 'bultos' => $g->bultos !== null ? (int) $g->bultos : null,
            'dest_tdicod' => (string) ($g->tdicod ?: (strlen((string) $g->ruccliente) === 11 ? '6' : '1')), 'dest_num' => (string) ($g->ruccliente ?: '-'),
            'dest_nom' => mb_strtoupper(mb_substr((string) ($g->nomcliente ?: '-'), 0, 150)),
            'partida_ubigeo' => substr((string) $g->ubigeopartida, 0, 6), 'partida_direccion' => mb_strtoupper(mb_substr((string) ($g->direccionpartida ?: '-'), 0, 200)),
            'partida_codlocal' => self::texto($g->cod_local_part ?? null, 4),
            'llegada_ubigeo' => substr((string) $g->ubigeollegada, 0, 6), 'llegada_direccion' => mb_strtoupper(mb_substr((string) ($g->direccionllegada ?: '-'), 0, 200)),
            'llegada_codlocal' => self::texto($g->cod_local_dest ?? null, 4),
            'transp_ruc' => self::texto($g->ructransportista, 11), 'transp_nom' => self::texto($g->nombretransportista, 150), 'transp_mtc' => self::texto($g->mtc ?? null, 20),
            'cond_tdicod' => self::texto($g->tdicodconductor, 1), 'cond_num' => self::texto($g->rucconductor, 15), 'cond_nombres' => self::texto($g->nomconductor, 100),
            'cond_apellidos' => self::texto($g->apeconductor ?? null, 100), 'cond_licencia' => self::texto($g->licencia, 15),
            'placa' => self::texto(str_replace([' ', '-'], '', strtoupper((string) $g->placa)), 10), 'vehiculo_m1l' => 0,
            'doc_tdocod' => self::texto($g->tdocodref ?? null, 2), 'doc_numero' => $referencia, 'observacion' => self::texto($g->observacion ?? null, 250),
            'est_sunat' => $estado, 'ticket' => self::texto($g->numTicket ?? null, 60), 'cod_respuesta' => self::texto($g->codRespuesta ?? $g->ccasunrescod ?? null, 10),
            'mensaje' => self::texto(($g->desError ?? null) ?: ($g->ccadessun ?? null), 500), 'qr' => self::texto(($g->cadena_qr ?? null) ?: ($g->ccaqr ?? null), 300),
            'hash' => self::texto(($g->codigo_hash ?? null) ?: ($g->codhash ?? null), 100), 'creado' => $fecha.' '.$hora,
        ];
    }

    /** Estado de la guía en el sistema nuevo según lo que guardó el antiguo */
    private static function estadoGuia(object $g): string
    {
        $cod = trim((string) (($g->codRespuesta ?? null) ?? ($g->ccasunrescod ?? '')));
        $baja = trim((string) ($g->ccabaj ?? ''));
        if (($baja !== '' && ! str_starts_with($baja, '0000')) || trim((string) ($g->ccacodsun ?? '')) === '8') {
            return 'ANULADO';
        }
        if ($cod === '0' || (int) ($g->indCdrGenerado ?? 0) === 1) {
            return 'ACEPTADO';
        }
        // 98: SUNAT aún procesaba el ticket (el sistema nuevo lo vuelve a consultar); 99: procesado con errores
        if ($cod === '98' && ! empty($g->numTicket)) {
            return 'ENVIADO';
        }
        if ($cod !== '' || trim((string) ($g->desError ?? '')) !== '') {
            return 'RECHAZADO';
        }

        return ! empty($g->numTicket) ? 'ENVIADO' : 'PENDIENTE';
    }

    /** El correlativo de guías sigue después de la última importada (la serie configurada o, si no se usó, la del sistema antiguo) */
    private function correlativoGuias(int $suc): void
    {
        $series = DB::table('gre_cabecera')->where('id_empresa_negocio', $suc)->groupBy('serie')->selectRaw('serie, MAX(numero) AS ultimo, COUNT(*) AS n')->get();
        if ($series->isEmpty()) {
            return;
        }
        $negocio = DB::table('empresa_negocios')->where('id_empresa_negocio', $suc)->first();
        $propia = $series->firstWhere('serie', strtoupper((string) $negocio->SerGuia));
        $elegida = $propia ?? $series->sortByDesc('n')->first();
        if (! preg_match('/^T[A-Z0-9]{3}$/', $elegida->serie) || ($propia && (int) $propia->ultimo <= (int) $negocio->NumGuia)) {
            return;
        }
        DB::table('empresa_negocios')->where('id_empresa_negocio', $suc)->update(['SerGuia' => $elegida->serie, 'NumGuia' => (int) $elegida->ultimo]);
        $this->aviso("Guías de remisión: siguen en {$elegida->serie}-".str_pad((string) ((int) $elegida->ultimo + 1), 8, '0', STR_PAD_LEFT).'.');
    }

    // ------------------------------------------------------------------ compras

    private function compras(): void
    {
        $user = $this->user;
        $suc = $user->id_empresa_negocio;
        $almacen = Kardex::almacenPredeterminado($suc)?->id_almacen;
        $proveedores = DB::table('proveedor')->where('IdEmpresa', $user->IdEmpresa)->pluck('prov_id', 'prov_ruc');
        $provAntiguos = ($this->hay)('proveedor') ? ($this->src)('proveedor')->pluck('prov_ruc', 'prov_id') : collect();
        $creditos = $this->mapaPorNombre('credito_dias', 'cre_dia_id', 'cre_dia_nom', DB::table('credito_dias')->where('id_empresa_negocio', $suc));
        $existentes = DB::table('compras_cabecera')->where('id_empresa_negocio', $suc)
            ->get(['com_cab_id', 'prov_id', 'tdocod', 'com_doc_ser', 'com_doc_num', 'com_fec', 'total_com'])
            ->mapWithKeys(fn ($c) => [self::claveCompra($c, $c->prov_id) => $c->com_cab_id])->all();

        ($this->filtro)(($this->src)('compras_cabecera'), 'compras_cabecera')->orderBy('com_cab_id')
            ->chunk(self::LOTE, function ($lote) use ($user, $suc, $almacen, $proveedores, $provAntiguos, $creditos, &$existentes) {
                $nuevos = [];
                foreach ($lote as $c) {
                    $ruc = $c->prov_num ?? ($provAntiguos[$c->prov_id ?? 0] ?? null);
                    $prov = $ruc ? ($proveedores[$ruc] ?? null) : null;
                    $clave = self::claveCompra($c, $prov);
                    if (isset($existentes[$clave])) {
                        $this->mapaCompra[$c->com_cab_id] = $existentes[$clave];
                        $this->resumen['repetidos']++;

                        continue;
                    }
                    if (! $prov) {
                        $this->aviso("Compra {$c->com_doc_ser}-{$c->com_doc_num}: el proveedor {$ruc} no existe en el sistema nuevo (importa también \"Proveedores\").");
                    }
                    $id = DB::table('compras_cabecera')->insertGetId($this->copiar('compras_cabecera', $c, [
                        'IdEmpresa' => $user->IdEmpresa, 'id_empresa_negocio' => $suc, 'IdUsuario' => $user->IdUsuario,
                        'prov_id' => $prov, 'id_almacen' => $almacen, 'id_turno' => null,
                        'cre_dia_id' => isset($c->cre_dia_id) ? ($creditos[$c->cre_dia_id] ?? null) : null,
                        'est_compra' => $c->est_compra ?? 'Registrado',
                    ], ['com_cab_id', 'usu_elimino']));
                    $this->mapaCompra[$c->com_cab_id] = $id;
                    $existentes[$clave] = $id;
                    $nuevos[$c->com_cab_id] = $id;
                    $this->resumen['compras']++;
                }
                if (! $nuevos || ! ($this->hay)('compras_detalle')) {
                    return;
                }
                $filas = [];
                foreach (($this->src)('compras_detalle')->whereIn('com_cab_id', array_keys($nuevos))->get() as $d) {
                    $prod = $this->productoDe($d->pro_id ?? null, $d->IdProducto_rel ?? null);
                    $factor = isset($this->padreDe[$d->pro_id ?? 0]) ? max(0.001, (float) ($d->com_det_factor ?? 1)) : 1;
                    $filas[] = $this->copiar('compras_detalle', $d, [
                        'com_cab_id' => $nuevos[$d->com_cab_id], 'pro_id' => $prod?->IdProducto,
                        'id_almacen_pro' => $almacen, 'IdEmpresa' => $user->IdEmpresa, 'factor' => $factor,
                        'id_presentacion' => $prod && $factor != 1 ? ($this->presentaciones[$prod->IdProducto.'|'.round($factor, 3)] ?? null) : null,
                    ], ['com_det_id']);
                }
                $this->insertar('compras_detalle', $filas);
            });
    }

    /** Proveedor + documento; sin serie ni número (compras internas antiguas) también cuentan la fecha y el total para no confundirlas */
    private static function claveCompra(object $c, $prov): string
    {
        $numero = ltrim((string) ($c->com_doc_num ?? ''), '0');
        $clave = $prov.'|'.$c->tdocod.'|'.($c->com_doc_ser ?? '').'|'.$numero;

        return trim((string) ($c->com_doc_ser ?? '')) === '' && $numero === ''
            ? $clave.'|'.substr((string) ($c->com_fec ?? ''), 0, 10).'|'.number_format((float) ($c->total_com ?? 0), 2, '.', '')
            : $clave;
    }

    // ------------------------------------------------------------------ cuentas por cobrar / pagar

    private function cuentas(string $tipo): void
    {
        $tabla = "cuentas_{$tipo}";
        $detalle = "{$tabla}_detalle";
        $pk = $tipo === 'cobrar' ? 'cue_cob_id' : 'cue_pag_id';
        $fk = $tipo === 'cobrar' ? 'IdCpe_cabecera' : 'com_cab_id';
        $mapa = $tipo === 'cobrar' ? $this->mapaCpe : $this->mapaCompra;
        if (! ($this->hay)($tabla) || ! $mapa) {
            return;
        }
        $suc = $this->user->id_empresa_negocio;
        $ya = DB::table($tabla)->whereIn($fk, array_values($mapa))->pluck($fk)->flip();

        // Deudas sobre documentos que no se importan (ej. proformas al crédito): se listan para registrarlas a mano
        if ($tipo === 'cobrar' && ($this->hay)('cpe_cabecera')) {
            $fuera = ($this->src)('cuentas_cobrar as k')->join('cpe_cabecera as c', 'c.IdCpe_cabecera', '=', 'k.IdCpe_cabecera')
                ->whereNotIn('c.tdocod', self::VENTAS)->where('k.saldo', '>', 0.009)
                ->get(['c.serdoc', 'c.numdoc', 'c.ccanom', 'c.ccafem', 'k.saldo']);
            if ($fuera->isNotEmpty()) {
                $this->aviso($fuera->count().' deudas de clientes estaban sobre proformas o vales (no se importan) por S/ '.number_format((float) $fuera->sum('saldo'), 2)
                    .'. Regístralas a mano: '.$fuera->map(fn ($f) => "{$f->ccanom} ({$f->serdoc}-{$f->numdoc} del {$f->ccafem}) S/ ".number_format((float) $f->saldo, 2))->implode(' · '));
            }
        }

        foreach (($this->src)($tabla)->whereIn($fk, array_keys($mapa))->get() as $cta) {
            $nuevoDoc = $mapa[$cta->$fk];
            if (isset($ya[$nuevoDoc])) {
                continue;
            }
            // clicod: cliente del comprobante (cobrar) o proveedor de la compra (pagar)
            $clicod = $tipo === 'cobrar'
                ? DB::table('cpe_cabecera')->where('IdCpe_cabecera', $nuevoDoc)->value('clicod')
                : DB::table('compras_cabecera')->where('com_cab_id', $nuevoDoc)->value('prov_id');
            // El estado se saca de los montos: el antiguo lo guardaba como texto ("pendiente", "cancelado" = pagada)
            // ("eliminado" = anulada); la deuda de un comprobante descartado (rechazado por SUNAT) tampoco existe
            $descartado = $tipo === 'cobrar' && str_starts_with((string) DB::table('cpe_cabecera')->where('IdCpe_cabecera', $nuevoDoc)->value('ccabaj'), 'DESCARTADO');
            $estado = $descartado || preg_match('/^(ANUL|ELIMIN)/', mb_strtoupper(trim((string) $cta->estado_cob))) ? 'ANULADO'
                : Cuentas::estado((float) $cta->abono, (float) $cta->saldo);
            $id = DB::table($tabla)->insertGetId($this->copiar($tabla, $cta, [
                $fk => $nuevoDoc, 'clicod' => $clicod, 'id_empresa_negocio' => $suc, 'IdEmpresa' => $this->user->IdEmpresa, 'estado_cob' => $estado,
            ] + ($estado === 'ANULADO' ? ['saldo' => 0] : []), [$pk]));
            $this->resumen['cuentas']++;

            if (($this->hay)($detalle)) {
                $pagos = [];
                foreach (($this->src)($detalle)->where($pk, $cta->$pk)->get() as $p) {
                    $pagos[] = $this->copiar($detalle, $p, [$pk => $id], [$tipo === 'cobrar' ? 'cue_cob_det_id' : 'cue_pag_det_id', 'mov_caj_id', 'id_turno']);
                }
                $this->insertar($detalle, $pagos);
            }
        }
    }

    // ------------------------------------------------------------------ usuarios y turnos

    /** Usuario nuevo de un IdUsuario antiguo (por su login, que se importa igual); sin él, null */
    private function usuario($idAntiguo): ?int
    {
        return $this->mapaUsuario[(int) $idAntiguo] ?? null;
    }

    /** Vendedor de la venta antigua; cuenta las que no se pudieron enlazar (usuario de otra sucursal aún no importado) */
    private function vendedor(object $c): ?int
    {
        if (empty($c->IdUsuario_ven)) {
            return null;
        }
        $id = $this->usuario($c->IdUsuario_ven);
        if (! $id) {
            // Usuario que ya no existe en el sistema antiguo (lo borraron) o que es de otra sucursal aún no importada
            isset($this->usuariosAntiguos[(int) $c->IdUsuario_ven]) ? $this->sinUsuario++ : $this->sinUsuarioBorrado++;
        }

        return $id;
    }

    private function cargarUsuarios(): void
    {
        if (! ($this->hay)('users')) {
            return;
        }
        $nuevos = DB::table('users')->pluck('IdUsuario', 'email')->mapWithKeys(fn ($id, $email) => [mb_strtolower(trim((string) $email)) => (int) $id]);
        foreach (($this->src)('users')->get(['IdUsuario', 'email']) as $u) {
            $this->usuariosAntiguos[(int) $u->IdUsuario] = true;
            if ($id = $nuevos[mb_strtolower(trim((string) $u->email))] ?? null) {
                $this->mapaUsuario[(int) $u->IdUsuario] = $id;
            }
        }
    }

    /**
     * Turnos de caja (cerrados) con lo que se cobró por medio de pago. Los que el sistema antiguo dejó abiertos entran
     * cerrados a la hora de su última venta: aquí cada cajero abre su propio turno nuevo.
     */
    private function turnos(): void
    {
        $user = $this->user;
        $suc = (int) $user->id_empresa_negocio;
        $medios = $this->mapaPorNombre('medios_pagos', 'id_med_pag', 'nom_med_pag', DB::table('medios_pagos')->where('id_empresa_negocio', $suc));
        $ya = DB::table('turnos')->where('id_empresa_negocio', $suc)->whereNotNull('apertura')->get(['id_turno', 'apertura', 'IdUsuario'])
            ->mapWithKeys(fn ($t) => [$t->apertura.'|'.$t->IdUsuario => (int) $t->id_turno])->all();
        $numero = (int) DB::table('turnos')->where('id_empresa_negocio', $suc)->max('turno');
        $ultimaVenta = ($this->hay)('usuario_facturacion')
            ? ($this->src)('usuario_facturacion as f')->join('cpe_cabecera as c', 'c.IdCpe_cabecera', '=', 'f.IdCpe_cabecera')
                ->groupBy('f.id_turno')->selectRaw('f.id_turno, MAX(c.fecha_hora) AS ultima')->pluck('ultima', 'id_turno')
            : collect();

        foreach (($this->filtro)(($this->src)('turnos'), 'turnos')->orderBy('apertura')->orderBy('id_turno')->get() as $t) {
            $cajero = $this->usuario($t->IdUsuario) ?? $user->IdUsuario;
            $clave = $t->apertura.'|'.$cajero;
            if (isset($ya[$clave])) {
                $this->mapaTurno[(int) $t->id_turno] = $ya[$clave];

                continue;
            }
            $cierre = $t->cierre ?? null;
            if (! $cierre || str_starts_with((string) $cierre, '0000')) {
                $cierre = max((string) $t->apertura, (string) ($ultimaVenta[$t->id_turno] ?? $t->apertura));
            }
            $id = DB::table('turnos')->insertGetId($this->copiar('turnos', $t, [
                'turno' => ++$numero, 'IdUsuario' => $cajero, 'IdEmpresa' => $user->IdEmpresa, 'id_empresa_negocio' => $suc,
                'estado' => 'CERRADO', 'cierre' => $cierre,
            ], ['id_turno']));
            $this->mapaTurno[(int) $t->id_turno] = $id;
            $ya[$clave] = $id;
            $this->resumen['turnos']++;

            if (($this->hay)('turno_medio_pago')) {
                $filas = [];
                foreach (($this->src)('turno_medio_pago')->where('id_turno', $t->id_turno)->get() as $m) {
                    if ($medio = $medios[$m->id_med_pag ?? 0] ?? null) {
                        $filas[] = ['id_turno' => $id, 'id_med_pag' => $medio, 'monto' => round((float) $m->monto, 2), 'id_empresa_negocio' => $suc];
                    }
                }
                $this->insertar('turno_medio_pago', $filas);
            }
        }

        // Comprobante antiguo => su turno (el sistema antiguo lo anotaba en usuario_facturacion)
        if (($this->hay)('usuario_facturacion')) {
            $this->turnoDeCpe = ($this->src)('usuario_facturacion')->whereNotNull('id_turno')->pluck('id_turno', 'IdCpe_cabecera')
                ->map(fn ($t) => (int) $t)->all();
        }
        if ($this->resumen['turnos']) {
            $this->aviso("{$this->resumen['turnos']} turnos de caja importados (cerrados), con sus ventas enlazadas.");
        }
    }

    /** Turno nuevo de un comprobante antiguo */
    private function turnoDe(object $c): ?int
    {
        $antiguo = $this->turnoDeCpe[(int) $c->IdCpe_cabecera] ?? (int) ($c->id_turno ?? 0);

        return $this->mapaTurno[$antiguo] ?? null;
    }

    // ------------------------------------------------------------------ proformas

    /**
     * Proformas (cotizaciones) del sistema antiguo con su detalle; y las notas de pedido que nunca se cobraron, para que la
     * caja las cobre. Las notas de pedido ya convertidas en boleta o factura NO se pasan (esa venta ya está importada).
     */
    private function proformas(): void
    {
        if (! Schema::hasTable('proformas')) {
            return;
        }
        $user = $this->user;
        $suc = (int) $user->id_empresa_negocio;
        $existentes = DB::table('proformas')->where('id_empresa_negocio', $suc)->get(['serie', 'numero'])
            ->map(fn ($p) => $p->serie.'-'.(int) $p->numero)->flip()->all();
        // La nota de pedido antigua reiniciaba su número cada día: la pendiente entra con un número nuevo de su serie y el
        // original queda en la observación (para no pasarla dos veces se busca ese texto)
        $pedidosYa = DB::table('proformas')->where('id_empresa_negocio', $suc)->where('observaciones', 'like', 'Nota de pedido %')
            ->pluck('observaciones')->flip()->all();
        $siguiente = DB::table('proformas')->where('id_empresa_negocio', $suc)->groupBy('serie')->selectRaw('serie, MAX(numero) AS n')->pluck('n', 'serie')
            ->map(fn ($n) => (int) $n)->all();
        $unidades = DB::table('unidad_medida')->pluck('umecod')->flip();
        $hayFacturado = Schema::connection(Importador::CONEXION)->hasColumn('cpe_cabecera', 'facturado');
        $pedidosOmitidos = 0;

        ($this->filtro)(($this->src)('cpe_cabecera'), 'cpe_cabecera')->whereIn('tdocod', ['15', '16'])->orderBy('IdCpe_cabecera')
            ->chunk(self::LOTE, function ($lote) use ($user, $suc, &$existentes, $unidades, $hayFacturado, &$pedidosOmitidos, &$pedidosYa, &$siguiente) {
                $nuevos = [];
                foreach ($lote as $c) {
                    $facturado = $hayFacturado ? (int) ($c->facturado ?? 0) : 0;
                    $anulado = ! empty($c->ccabaj);
                    // Nota de pedido: solo la que sigue pendiente de cobro
                    if ($c->tdocod === '16' && ($facturado > 0 || $anulado)) {
                        $pedidosOmitidos++;

                        continue;
                    }
                    $serie = mb_substr(strtoupper(trim((string) $c->serdoc)), 0, 4);
                    $fecha = $c->ccafem && ! str_starts_with((string) $c->ccafem, '0000') ? substr((string) $c->ccafem, 0, 10) : now()->toDateString();
                    $numero = (int) $c->numdoc;
                    $observacion = self::texto($c->ccaobs ?? null, 100);
                    if ($c->tdocod === '16') {
                        $observacion = mb_substr("Nota de pedido {$serie}-{$numero} del ".date('d/m/Y', strtotime($fecha)), 0, 100);
                        if (isset($pedidosYa[$observacion])) {
                            $this->resumen['repetidos']++;

                            continue;
                        }
                        $numero = ($siguiente[$serie] ?? 0) + 1;
                        $pedidosYa[$observacion] = true;
                    }
                    $clave = $serie.'-'.$numero;
                    if ($serie === '' || isset($existentes[$clave])) {
                        $this->resumen['repetidos'] += $serie === '' ? 0 : 1;

                        continue;
                    }
                    $siguiente[$serie] = max($siguiente[$serie] ?? 0, $numero);
                    $id = DB::table('proformas')->insertGetId([
                        'serie' => $serie, 'numero' => $numero, 'fecha' => $fecha,
                        'tdicod' => mb_substr((string) ($c->tdicod ?: '1'), 0, 1), 'clinum' => mb_substr(trim((string) ($c->ccandi ?: '00000000')), 0, 15),
                        'clinom' => mb_strtoupper(mb_substr(trim((string) ($c->ccanom ?: 'VENTA AL PORTADOR')), 0, 120)),
                        'clidir' => self::texto($c->direccion ?? null, 150), 'observaciones' => $observacion,
                        'total' => round((float) $c->ccaitv, 2),
                        'estado' => $anulado ? 'ANULADA' : ($facturado > 0 ? 'FACTURADA' : 'PENDIENTE'), 'origen' => 'PV',
                        'IdUsuario' => $this->usuario($c->IdUsuario ?? 0) ?? $user->IdUsuario, 'IdEmpresa' => $user->IdEmpresa, 'id_empresa_negocio' => $suc,
                        'created_at' => $c->fecha_hora ?? $fecha, 'updated_at' => $c->fecha_hora ?? $fecha,
                    ]);
                    $existentes[$clave] = true;
                    $nuevos[$c->IdCpe_cabecera] = $id;
                    $this->resumen['proformas']++;
                }
                if (! $nuevos || ! ($this->hay)('cpe_detalle')) {
                    return;
                }
                $filas = [];
                foreach (($this->src)('cpe_detalle')->whereIn('IdCpe_cabecera', array_keys($nuevos))->orderBy('IdCpe_detalle')->get() as $d) {
                    $prod = $this->productoDe($d->IdProducto ?? null, $d->IdProducto_rel ?? null);
                    $cantidad = round((float) $d->cdecan, 3);
                    $precio = round((float) $d->cdepuni, 2);
                    $ume = strtoupper((string) ($d->umecod ?? '')) ?: ($prod->umecod ?? 'NIU');
                    $filas[] = ['id_proforma' => $nuevos[$d->IdCpe_cabecera], 'IdProducto' => $prod?->IdProducto,
                        'descripcion' => mb_substr(trim((string) ($d->cdedes ?: ($prod->pronom ?? '-'))), 0, 150),
                        'umecod' => isset($unidades[$ume]) ? $ume : ($prod->umecod ?? 'NIU'), 'factor' => max(0.001, (float) ($d->cpe_det_factor ?? 1) ?: 1),
                        'cantidad' => $cantidad, 'precio' => $precio, 'total' => round($cantidad * $precio, 2)];
                }
                $this->insertar('proforma_detalle', $filas);
            });

        $this->correlativoProformas($suc);
        if ($this->resumen['proformas'] || $pedidosOmitidos) {
            $this->aviso("Proformas: {$this->resumen['proformas']} importadas (las notas de pedido sin cobrar quedaron como proformas PENDIENTES para la caja)"
                .($pedidosOmitidos ? "; {$pedidosOmitidos} notas de pedido ya cobradas (su boleta o factura ya está en el historial) no se pasaron." : '.'));
        }
    }

    /** Las proformas siguen después de la última importada (solo series de proforma, no las de nota de pedido) */
    private function correlativoProformas(int $suc): void
    {
        $series = DB::table('proformas')->where('id_empresa_negocio', $suc)->where('serie', 'like', 'P%')
            ->groupBy('serie')->selectRaw('serie, MAX(numero) AS ultimo, COUNT(*) AS n')->get();
        if ($series->isEmpty()) {
            return;
        }
        $negocio = DB::table('empresa_negocios')->where('id_empresa_negocio', $suc)->first(['SerProforma', 'NumProforma']);
        $actual = strtoupper((string) ($negocio->SerProforma ?: 'PR01'));
        $elegida = $series->firstWhere('serie', $actual) ?? $series->sortByDesc('n')->first();
        if ((int) $elegida->ultimo > ($elegida->serie === $actual ? (int) $negocio->NumProforma : 0)) {
            DB::table('empresa_negocios')->where('id_empresa_negocio', $suc)->update(['SerProforma' => $elegida->serie, 'NumProforma' => (int) $elegida->ultimo]);
        }
    }

    // ------------------------------------------------------------------ gastos y tipo de cambio

    /** Gastos del sistema antiguo (con su categoría) y, si salieron de una caja, su egreso en ese turno */
    private function gastos(): void
    {
        if (! Schema::hasTable('gastos')) {
            return;
        }
        $user = $this->user;
        $suc = (int) $user->id_empresa_negocio;
        $proveedores = ($this->hay)('proveedor') ? ($this->src)('proveedor')->get(['prov_id', 'prov_ruc', 'prov_raz'])->keyBy('prov_id') : collect();
        $tipos = ($this->hay)('tipo_gastos') ? ($this->src)('tipo_gastos')->pluck('tip_gas_nom', 'tip_gas_id') : collect();
        $detalles = ($this->hay)('gastos_detalle') ? ($this->src)('gastos_detalle')->orderBy('gast_det_id')->get()->groupBy('gast_cab_id') : collect();
        $turnoDe = ($this->hay)('usuario_gastos') ? ($this->src)('usuario_gastos')->whereNotNull('id_turno')->pluck('id_turno', 'gast_cab_id') : collect();
        $categorias = DB::table('gasto_categorias')->where('IdEmpresa', $user->IdEmpresa)->pluck('id', 'nombre')->mapWithKeys(fn ($id, $n) => [mb_strtoupper($n) => $id])->all();
        $ya = DB::table('gastos')->where('IdEmpresa', $user->IdEmpresa)->where('origen', 'ANTIGUO')->get(['fecha', 'serie', 'numero', 'total'])
            ->map(fn ($g) => $g->fecha.'|'.$g->serie.'|'.$g->numero.'|'.number_format((float) $g->total, 2, '.', ''))->flip()->all();
        $categoria = function (?string $nombre) use (&$categorias, $user) {
            $nombre = mb_strtoupper(mb_substr(trim((string) $nombre), 0, 60)) ?: 'OTROS GASTOS';
            if (! isset($categorias[$nombre])) {
                $categorias[$nombre] = DB::table('gasto_categorias')->insertGetId(['IdEmpresa' => $user->IdEmpresa, 'nombre' => $nombre,
                    'cuenta_contable' => '659', 'created_at' => now(), 'updated_at' => now()]);
            }

            return $categorias[$nombre];
        };

        foreach (($this->filtro)(($this->src)('gastos_cabecera'), 'gastos_cabecera')->orderBy('gast_cab_id')->get() as $g) {
            $total = round((float) $g->total_gast, 2);
            $fecha = $g->gast_fec && ! str_starts_with((string) $g->gast_fec, '0000') ? substr((string) $g->gast_fec, 0, 10) : null;
            if ($total <= 0 || ! $fecha) {
                continue;
            }
            $serie = self::texto(mb_strtoupper((string) $g->gast_doc_ser), 6);
            $numero = self::texto(ltrim((string) $g->gast_doc_num, '0'), 20);
            $clave = $fecha.'|'.$serie.'|'.$numero.'|'.number_format($total, 2, '.', '');
            if (isset($ya[$clave])) {
                $this->resumen['repetidos']++;

                continue;
            }
            $lineas = $detalles[$g->gast_cab_id] ?? collect();
            $prov = $proveedores[$g->prov_id ?? 0] ?? null;
            $descripcion = trim((string) ($g->gast_obs ?? '')) ?: $lineas->pluck('det_gasto')->filter()->implode(', ');
            $anulado = mb_strtoupper((string) ($g->est_gasto ?? '')) === 'ELIMINADO';
            $cajero = $this->usuario($g->IdUsuario ?? 0) ?? $user->IdUsuario;
            $id = DB::table('gastos')->insertGetId([
                'IdEmpresa' => $user->IdEmpresa, 'id_empresa_negocio' => $suc, 'fecha' => $fecha,
                'tdocod' => in_array((string) $g->tdocod, ['01', '03', '07', '02', '14', '12'], true) ? $g->tdocod : '00',
                'serie' => $serie, 'numero' => $numero, 'prov_doc' => self::texto($prov->prov_ruc ?? null, 15), 'prov_nombre' => self::texto($prov->prov_raz ?? null, 200),
                'categoria_id' => $categoria($tipos[$lineas->first()->tip_gas_id ?? 0] ?? null), 'descripcion' => self::texto($descripcion, 255),
                'moneda' => ($g->mon_id ?? 'PEN') === 'USD' ? 'USD' : 'PEN', 'tipo_cambio' => (float) ($g->tip_cam ?? 0) ?: null,
                // El sistema antiguo guardaba solo el total: sin IGV separado no se toma crédito fiscal
                'base' => 0, 'igv' => 0, 'no_gravado' => $total, 'retencion' => 0, 'total' => $total, 'credito_fiscal' => 0,
                'forma_pago' => 'CONTADO', 'estado' => $anulado ? 'Anulado' : 'Registrado', 'origen' => 'ANTIGUO',
                'IdUsuario' => $cajero, 'created_at' => $fecha, 'updated_at' => $fecha,
            ]);
            $ya[$clave] = $id;
            $this->resumen['gastos']++;

            // Gasto pagado con la caja de un turno: su egreso queda en ese turno
            $turno = $this->mapaTurno[(int) ($turnoDe[$g->gast_cab_id] ?? 0)] ?? null;
            if ($turno && ! $anulado) {
                DB::table('movimientoscaja')->insert([
                    'tip_caj_id' => '003', 'mov_com' => mb_substr($descripcion ?: 'GASTO', 0, 1000), 'importe' => $total, 'estado' => 'ACTIVO',
                    'mov_fecha' => $fecha, 'registro' => $fecha, 'IdEmpresa' => $user->IdEmpresa, 'id_empresa_negocio' => $suc,
                    'id_turno' => $turno, 'IdUsuario' => $cajero,
                ]);
            }
        }
        if ($this->resumen['gastos']) {
            $this->aviso("{$this->resumen['gastos']} gastos importados (con su categoría y, si salieron de caja, el egreso en su turno). "
                .'El sistema antiguo guardaba solo el total: entran sin IGV separado (sin crédito fiscal); corrígelo en Gastos si alguno lo tenía.');
        }
    }

    /** Tipo de cambio del dólar que guardó el sistema antiguo (los días que aquí no existen) */
    private function tipoCambio(): void
    {
        if (! ($this->hay)('tipocambio') || ! Schema::hasTable('tipo_cambio')) {
            return;
        }
        $ya = DB::table('tipo_cambio')->where('moneda', 'USD')->pluck('fecha')->map(fn ($f) => substr((string) $f, 0, 10))->flip()->all();
        $filas = [];
        foreach (($this->src)('tipocambio')->orderBy('FecTipCambio')->get() as $t) {
            $fecha = substr((string) $t->FecTipCambio, 0, 10);
            $venta = (float) ($t->CamVenta ?? 0);
            if ($venta <= 0 || $fecha === '' || str_starts_with($fecha, '0000') || isset($ya[$fecha])) {
                continue;
            }
            $ya[$fecha] = true;
            $filas[] = ['fecha' => $fecha, 'moneda' => 'USD', 'compra' => (float) ($t->CamCompra ?? 0) ?: $venta, 'venta' => $venta, 'creado' => now()];
        }
        $this->insertar('tipo_cambio', $filas);
    }

    // ------------------------------------------------------------------ kardex

    /**
     * Kardex histórico del almacén: cada movimiento antiguo (ventas, compras, notas de ingreso/salida, traslados entre
     * sucursales, inventarios) con su saldo, enlazado a la venta o compra importada. Solo si el almacén aún no tiene
     * movimientos aquí. Al final el stock queda igual al último saldo del kardex; la sección "Stock" lo cuadra con el
     * stock que tenía el sistema antiguo (también en negativo).
     */
    private function kardex(): void
    {
        $almacenAntiguo = (int) ($this->opciones['almacen'] ?? 0);
        $almacen = Kardex::almacenPredeterminado((int) $this->user->id_empresa_negocio);
        if (! $almacenAntiguo || ! $almacen || ! ($this->hay)('movimientos_productos')) {
            return;
        }
        if (DB::table('movimientos_productos')->where('id_almacen', $almacen->id_almacen)->exists()) {
            $this->aviso("Kardex histórico NO importado: el almacén {$almacen->descripcion} ya tiene movimientos en el sistema nuevo.");

            return;
        }
        $suc = (int) $this->user->id_empresa_negocio;
        $nombresAlmacen = ($this->hay)('almacenes') ? ($this->src)('almacenes')->pluck('descripcion', 'id_almacen') : collect();
        $mapaCab = $this->cabecerasMovimiento($almacenAntiguo, $almacen->id_almacen, $nombresAlmacen);
        $sinProducto = 0;
        $ultimo = [];

        ($this->src)('movimientos_productos')->where('id_almacen', $almacenAntiguo)->orderBy('mov_pro_id')
            ->chunk(self::LOTE * 2, function ($lote) use ($almacen, $suc, $mapaCab, &$sinProducto, &$ultimo) {
                $filas = [];
                foreach ($lote as $m) {
                    $prod = $this->productoDe($m->IdProducto ?? null, $m->IdProducto_rel ?? null);
                    if (! $prod) {
                        $sinProducto++;

                        continue;
                    }
                    // Los servicios no llevan kardex (en el antiguo salían del stock y quedaban en negativo)
                    if ((int) $prod->promocion === Producto::SERVICIO) {
                        continue;
                    }
                    $filas[] = $this->copiar('movimientos_productos', $m, [
                        'IdProducto' => $prod->IdProducto, 'IdProducto_rel' => $prod->IdProducto, 'id_almacen' => $almacen->id_almacen,
                        'id_empresa_negocio' => $suc,
                        'IdCpe_cabecera' => $this->mapaCpe[(int) ($m->IdCpe_cabecera ?? 0)] ?? null,
                        'com_cab_id' => $this->mapaCompra[(int) ($m->com_cab_id ?? 0)] ?? null,
                        'mov_cab_id' => $mapaCab[(int) ($m->mov_cab_id ?? 0)] ?? null,
                        'mov_tip' => strtoupper((string) ($m->mov_tip ?? '')) === 'E' ? 'E' : 'I',
                    ], ['mov_pro_id', 'inv_cab_id', 'id_almacen_destino', 'id_almacen_origen', 'IdCpe_guia', 'merma_id', 'IdUsuario']);
                    $ultimo[$prod->IdProducto] = (float) ($m->stock ?? 0);
                }
                $this->insertar('movimientos_productos', $filas);
                $this->resumen['kardex'] += count($filas);
            });

        // El stock arranca en el último saldo del kardex (la sección Stock lo cuadra con el del sistema antiguo)
        foreach ($ultimo as $idProducto => $saldo) {
            DB::table('producto_stock')->updateOrInsert(['IdProducto' => $idProducto, 'id_almacen' => $almacen->id_almacen],
                ['stock' => round($saldo, 3), 'id_empresa_negocio' => $suc]);
            $this->productosKardex[$idProducto] = true;
        }
        $this->aviso("Kardex histórico: {$this->resumen['kardex']} movimientos del almacén {$almacen->descripcion}"
            .(count($mapaCab) ? ' y '.count($mapaCab).' notas de ingreso/salida (traslados entre sucursales)' : '')
            .($sinProducto ? "; {$sinProducto} movimientos de productos borrados en el sistema antiguo no se pasaron." : '.'));
    }

    /** Notas de ingreso (82) y salida (81) del almacén antiguo: quedan en Ingresos / Salidas de productos */
    private function cabecerasMovimiento(int $almacenAntiguo, int $almacenNuevo, $nombresAlmacen): array
    {
        if (! ($this->hay)('movimientos_cabecera')) {
            return [];
        }
        $mapa = [];
        // La nota de salida (81) es del almacén que despacha y la de ingreso (82) del que recibe
        $q = ($this->src)('movimientos_cabecera')->where(fn ($w) => $w->where(fn ($x) => $x->where('tdocod', '81')->where('part_alm', $almacenAntiguo))
            ->orWhere(fn ($x) => $x->where('tdocod', '82')->where('des_alm', $almacenAntiguo))
            ->orWhere(fn ($x) => $x->whereNotIn('tdocod', ['81', '82'])->where(fn ($y) => $y->where('part_alm', $almacenAntiguo)->orWhere('des_alm', $almacenAntiguo))))
            ->orderBy('mov_cab_id');
        foreach ($q->get() as $c) {
            $entra = (string) $c->tdocod === '82' || ((string) $c->tdocod !== '81' && (int) $c->des_alm === $almacenAntiguo);
            $otro = $nombresAlmacen[$entra ? $c->part_alm : $c->des_alm] ?? null;
            $mapa[(int) $c->mov_cab_id] = DB::table('movimientos_cabecera')->insertGetId([
                'tdocod' => self::texto($c->tdocod, 2), 'serdoc' => self::texto($c->serdoc, 4), 'numdoc' => (int) $c->numdoc ?: null,
                'mov_tip' => $entra ? 'I' : 'E', 'cod_tip_ope' => '11',
                'part_alm' => $entra ? null : $almacenNuevo, 'des_alm' => $entra ? $almacenNuevo : null,
                'observaciones' => self::texto(trim(($c->observaciones ?? '').' '.($otro ? ($entra ? 'Traslado desde ' : 'Traslado a ').$otro.' (sistema anterior)' : '')), 250),
                'fecha' => $c->fecha && ! str_starts_with((string) $c->fecha, '0000') ? $c->fecha : null,
                'estado' => mb_strtoupper((string) ($c->estado ?: 'REGISTRADO')),
                'usu_ent' => $this->usuario($c->usu_ent ?? 0), 'usu_rec' => $this->usuario($c->usu_rec ?? 0),
                'id_empresa_negocio' => (int) $this->user->id_empresa_negocio,
            ]);
        }

        return $mapa;
    }

    // ------------------------------------------------------------------ apoyo

    /** Inserta en lotes; cada fila trae solo sus columnas con valor, así que se agrupan por columnas */
    private function insertar(string $tabla, array $filas): void
    {
        $grupos = [];
        foreach ($filas as $f) {
            ksort($f);
            $grupos[implode(',', array_keys($f))][] = $f;
        }
        foreach ($grupos as $grupo) {
            foreach (array_chunk($grupo, self::LOTE) as $parte) {
                DB::table($tabla)->insert($parte);
            }
        }
    }

    /** IDs antiguos => nuevos de un catálogo pequeño, relacionados por nombre (medios de pago, formas de pago) */
    private function mapaPorNombre(string $tabla, string $id, string $nombre, $destino): array
    {
        if (! ($this->hay)($tabla)) {
            return [];
        }
        $nuevos = $destino->get([$id, $nombre])->mapWithKeys(fn ($r) => [mb_strtoupper(trim((string) $r->$nombre)) => $r->$id]);

        return ($this->src)($tabla)->get([$id, $nombre])
            ->mapWithKeys(fn ($r) => [$r->$id => $nuevos[mb_strtoupper(trim((string) $r->$nombre))] ?? null])->filter()->all();
    }
}
