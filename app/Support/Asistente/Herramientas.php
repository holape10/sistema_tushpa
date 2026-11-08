<?php

namespace App\Support\Asistente;

use App\Models\User;
use App\Support\Contabilidad\Contabilidad;
use App\Support\ControlInsumos;
use App\Support\Kardex;
use App\Support\PanelDueno;
use App\Support\Reportes;
use App\Support\Requerimientos;
use App\Support\SucursalesVisibles;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Lo que el asistente puede consultar del sistema. TODO es de solo lectura y de la empresa del usuario: la sede la
 * elige el asistente pero solo entre las que el usuario puede ver (nunca otra empresa). Cada herramienta reutiliza los
 * mismos cálculos de los reportes y paneles, así el asistente ve los mismos números que la pantalla.
 */
class Herramientas
{
    /** Cuántas filas como mucho le llegan de un reporte (lo demás se resume en los totales) */
    private const FILAS = 40;

    private const RANGO_MAXIMO_DIAS = 400;

    /** @return list<array<string, mixed>> definiciones para la API (las claves en camelCase del SDK) */
    public static function definiciones(): array
    {
        $sucursal = ['type' => 'integer', 'description' => 'Id de la sede (ver la lista de sedes del contexto). Si no se pone, la sede actual del usuario.'];
        $desde = ['type' => 'string', 'description' => 'Fecha inicial, AAAA-MM-DD.'];
        $hasta = ['type' => 'string', 'description' => 'Fecha final, AAAA-MM-DD (inclusive).'];
        $reportes = collect(Reportes::CATALOGO)->map(fn ($r, $clave) => "{$clave}: {$r[2]}")->implode(' | ');

        return [
            [
                'name' => 'reporte',
                'description' => 'Reportes de ventas y compras de una sede en un rango de fechas (los mismos de la pantalla Reportes). Devuelve columnas, hasta '.self::FILAS
                    ." filas y los totales del periodo completo. Las notas de crédito ya restan. Disponibles: {$reportes}.",
                'inputSchema' => ['type' => 'object', 'properties' => [
                    'clave' => ['type' => 'string', 'enum' => array_keys(Reportes::CATALOGO)],
                    'desde' => $desde, 'hasta' => $hasta, 'sucursal' => $sucursal,
                    'agrupar' => ['type' => 'string', 'enum' => ['producto', 'categoria', 'dia', 'mes'], 'description' => 'Solo para rentabilidad (por defecto producto).'],
                    'orden' => ['type' => 'string', 'enum' => ['mas', 'menos'], 'description' => 'Solo para productos-ranking.'],
                ], 'required' => ['clave', 'desde', 'hasta']],
            ],
            [
                'name' => 'resumen_negocio',
                'description' => 'Cómo va la sede ahora: ventas de hoy contra el mismo día de la semana pasada, el mes contra el mismo tramo del mes pasado, la proyección de cierre, la meta, el margen, los gastos fijos y el punto de equilibrio.',
                'inputSchema' => ['type' => 'object', 'properties' => ['sucursal' => $sucursal]],
            ],
            [
                'name' => 'alertas',
                'description' => 'Lo que el dueño debería revisar en un periodo: faltantes de caja, anulaciones, descuentos y cortesías fuera de lo normal, platos quitados, mesas abiertas hace horas, comprobantes sin aceptar por SUNAT y stock negativo.',
                'inputSchema' => ['type' => 'object', 'properties' => ['desde' => $desde, 'hasta' => $hasta, 'sucursal' => $sucursal], 'required' => ['desde', 'hasta']],
            ],
            [
                'name' => 'stock',
                'description' => 'Stock del almacén principal de la sede: cuánto hay de cada producto e insumo, su mínimo, cuánto se gasta por día (última semana), para cuántos días alcanza y si se acabó, llegó a su mínimo o se acabaría pronto.',
                'inputSchema' => ['type' => 'object', 'properties' => [
                    'sucursal' => $sucursal,
                    'filtro' => ['type' => 'string', 'enum' => ['faltantes', 'todos'], 'description' => 'faltantes = solo lo que se acabó, llegó a su mínimo o se acabaría (por defecto).'],
                    'buscar' => ['type' => 'string', 'description' => 'Parte del nombre del producto (opcional).'],
                ]],
            ],
            [
                'name' => 'control_insumos',
                'description' => 'Por insumo: lo que se debió gastar según las recetas de lo vendido, las mermas registradas y lo que faltó o sobró en los conteos de inventario, con la desviación en % y la pérdida en soles.',
                'inputSchema' => ['type' => 'object', 'properties' => ['desde' => $desde, 'hasta' => $hasta, 'sucursal' => $sucursal], 'required' => ['desde', 'hasta']],
            ],
            [
                'name' => 'cuentas_pendientes',
                'description' => 'Cuentas por cobrar (ventas al crédito) o por pagar (compras al crédito) con saldo pendiente, agrupadas por cliente o proveedor, con lo vencido y el vencimiento más antiguo.',
                'inputSchema' => ['type' => 'object', 'properties' => [
                    'tipo' => ['type' => 'string', 'enum' => ['cobrar', 'pagar']], 'sucursal' => $sucursal,
                    'solo_vencidas' => ['type' => 'boolean', 'description' => 'Solo lo que ya venció.'],
                ], 'required' => ['tipo']],
            ],
            [
                'name' => 'saldos_contables',
                'description' => 'Contabilidad de la empresa (todas sus sedes): saldos por cuenta del plan contable (PCGE) en un rango, a partir de los asientos registrados. Sin prefijo devuelve el resumen por cuenta de 2 dígitos; con prefijo, el detalle de esas cuentas.',
                'inputSchema' => ['type' => 'object', 'properties' => [
                    'desde' => $desde, 'hasta' => $hasta,
                    'prefijo' => ['type' => 'string', 'description' => 'Cuentas que empiezan con esto, p. ej. 70, 60, 42, 1041 (opcional).'],
                ], 'required' => ['desde', 'hasta']],
            ],
        ];
    }

    /** Ejecuta una herramienta y devuelve el resultado en JSON (o un error que el asistente puede corregir) */
    public static function ejecutar(User $user, string $nombre, array $input): string
    {
        try {
            $datos = match ($nombre) {
                'reporte' => self::reporte($user, $input),
                'resumen_negocio' => PanelDueno::resumen(self::sede($user, $input)),
                'alertas' => self::alertas($user, $input),
                'stock' => self::stock($user, $input),
                'control_insumos' => self::controlInsumos($user, $input),
                'cuentas_pendientes' => self::cuentas($user, $input),
                'saldos_contables' => self::saldos($user, $input),
                default => throw new \InvalidArgumentException("No existe la herramienta {$nombre}."),
            };
        } catch (\InvalidArgumentException $e) {
            return json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }

        return json_encode(self::limpiar($datos), JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
    }

    /** La sede pedida, solo si el usuario la puede ver (si no, error) */
    private static function sede(User $user, array $input): int
    {
        $pedida = (int) ($input['sucursal'] ?? 0) ?: (int) $user->id_empresa_negocio;
        if (! SucursalesVisibles::de($user)->contains('id_empresa_negocio', $pedida)) {
            throw new \InvalidArgumentException("La sede {$pedida} no existe o el usuario no la puede ver.");
        }

        return $pedida;
    }

    /** @return array{0: string, 1: string} */
    private static function fechas(array $input): array
    {
        try {
            $desde = CarbonImmutable::createFromFormat('!Y-m-d', (string) ($input['desde'] ?? ''));
            $hasta = CarbonImmutable::createFromFormat('!Y-m-d', (string) ($input['hasta'] ?? ''));
        } catch (\Throwable) {
            $desde = $hasta = false;
        }
        if (! $desde || ! $hasta || $hasta->lt($desde)) {
            throw new \InvalidArgumentException('Fechas no válidas: usa AAAA-MM-DD y que "hasta" no sea anterior a "desde".');
        }
        if ($desde->diffInDays($hasta) > self::RANGO_MAXIMO_DIAS) {
            throw new \InvalidArgumentException('El rango es muy largo: como mucho '.self::RANGO_MAXIMO_DIAS.' días por consulta.');
        }

        return [$desde->toDateString(), $hasta->toDateString()];
    }

    private static function reporte(User $user, array $input): array
    {
        $clave = (string) ($input['clave'] ?? '');
        if (! isset(Reportes::CATALOGO[$clave])) {
            throw new \InvalidArgumentException("No existe el reporte {$clave}.");
        }
        [$desde, $hasta] = self::fechas($input);
        $r = Reportes::generar($clave, ['desde' => $desde, 'hasta' => $hasta, 'sucursal' => self::sede($user, $input),
            'agrupar' => $input['agrupar'] ?? null, 'orden' => $input['orden'] ?? null]);

        return [
            'reporte' => $r['titulo'], 'desde' => $desde, 'hasta' => $hasta,
            'columnas' => collect($r['columnas'])->map(fn ($c) => $c[0])->all(),
            'filas_en_total' => count($r['filas']), 'filas' => array_slice($r['filas'], 0, self::FILAS),
            'totales' => $r['totales'] ?? null, 'nota' => $r['nota'] ?? null,
        ];
    }

    private static function alertas(User $user, array $input): array
    {
        [$desde, $hasta] = self::fechas($input);

        return PanelDueno::alertas(self::sede($user, $input), $desde, $hasta)
            ->map(fn ($a) => ['nivel' => $a->nivel, 'titulo' => $a->titulo, 'detalle' => $a->detalle])->values()->all();
    }

    private static function stock(User $user, array $input): array
    {
        $sede = self::sede($user, $input);
        $almacen = Kardex::almacenPredeterminado($sede);
        if (! $almacen) {
            throw new \InvalidArgumentException('La sede no tiene almacén.');
        }
        $buscar = mb_strtolower(trim((string) ($input['buscar'] ?? '')));
        $lista = Requerimientos::sugerencias($sede, (int) $almacen->id_almacen)
            ->when(($input['filtro'] ?? 'faltantes') !== 'todos', fn ($c) => $c->where('estado', '!=', 'ok'))
            ->when($buscar !== '', fn ($c) => $c->filter(fn ($p) => str_contains(mb_strtolower($p->nombre), $buscar)));

        return ['almacen' => $almacen->descripcion, 'productos_en_total' => $lista->count(),
            'productos' => $lista->take(60)->map(fn ($p) => ['nombre' => $p->nombre, 'unidad' => $p->unidad, 'stock' => $p->stock, 'minimo' => $p->minimo,
                'gasto_por_dia' => $p->consumo_dia, 'alcanza_dias' => $p->dias, 'estado' => $p->estado, 'costo' => $p->costo])->values()->all()];
    }

    private static function controlInsumos(User $user, array $input): array
    {
        [$desde, $hasta] = self::fechas($input);
        $sede = self::sede($user, $input);
        $almacen = Kardex::almacenPredeterminado($sede);
        if (! $almacen) {
            throw new \InvalidArgumentException('La sede no tiene almacén.');
        }
        $filas = ControlInsumos::reporte($sede, (int) $almacen->id_almacen, $desde, $hasta);

        return ['almacen' => $almacen->descripcion, 'perdida_total_soles' => round($filas->where('valor', '>', 0)->sum('valor'), 2),
            'insumos' => $filas->take(self::FILAS)->map(fn ($f) => (array) $f)->values()->all(),
            'nota' => 'teorico = debió gastarse según recetas; neto = faltó menos sobró en conteos; desviacion = neto/teorico en %.'];
    }

    private static function cuentas(User $user, array $input): array
    {
        $cobrar = ($input['tipo'] ?? '') === 'cobrar';
        $hoy = now()->toDateString();
        $q = $cobrar
            ? DB::table('cuentas_cobrar as c')->leftJoin('cliente as t', 't.clicod', '=', 'c.clicod')->select(DB::raw("COALESCE(t.clinom, 'SIN NOMBRE') as nombre"), 't.clinum as documento')
            : DB::table('cuentas_pagar as c')->leftJoin('proveedor as t', 't.prov_id', '=', 'c.clicod')->select(DB::raw("COALESCE(t.prov_raz, 'SIN NOMBRE') as nombre"), 't.prov_ruc as documento');
        $filas = $q->where('c.id_empresa_negocio', self::sede($user, $input))->where('c.saldo', '>', 0.004)
            ->when(! empty($input['solo_vencidas']), fn ($w) => $w->where('c.fec_ven', '<', $hoy))
            ->addSelect(DB::raw('COUNT(*) as documentos'), DB::raw('SUM(c.saldo) as saldo'),
                DB::raw("SUM(CASE WHEN c.fec_ven < '{$hoy}' THEN c.saldo ELSE 0 END) as vencido"), DB::raw('MIN(c.fec_ven) as vence_mas_antiguo'))
            ->groupBy('c.clicod', 'nombre', 'documento')->orderByDesc('saldo')->get();

        return ['tipo' => $cobrar ? 'por cobrar' : 'por pagar', 'total_saldo' => round((float) $filas->sum('saldo'), 2),
            'total_vencido' => round((float) $filas->sum('vencido'), 2), 'terceros_en_total' => $filas->count(),
            'detalle' => $filas->take(self::FILAS)->map(fn ($f) => ['nombre' => $f->nombre, 'documento' => $f->documento, 'documentos' => (int) $f->documentos,
                'saldo' => round((float) $f->saldo, 2), 'vencido' => round((float) $f->vencido, 2), 'vence_mas_antiguo' => $f->vence_mas_antiguo])->all()];
    }

    private static function saldos(User $user, array $input): array
    {
        [$desde, $hasta] = self::fechas($input);
        $ruc = (string) $user->IdEmpresa;
        $prefijo = preg_replace('/\D/', '', (string) ($input['prefijo'] ?? ''));
        $sumas = Contabilidad::sumas($ruc, $desde, $hasta);
        if ($sumas->isEmpty()) {
            return ['aviso' => 'No hay asientos contables en ese rango (puede que aún no se hayan centralizado las ventas y compras).'];
        }
        $nombres = DB::table('conta_plan')->where('IdEmpresa', $ruc)->pluck('descripcion', 'cuenta');
        $largo = $prefijo === '' ? 2 : null;
        $agrupado = [];
        foreach ($sumas as $cuenta => $s) {
            $cuenta = (string) $cuenta;
            if ($prefijo !== '' && ! str_starts_with($cuenta, $prefijo)) {
                continue;
            }
            $clave = $largo ? substr($cuenta, 0, $largo) : $cuenta;
            $agrupado[$clave]['debe'] = ($agrupado[$clave]['debe'] ?? 0) + (float) $s->debe;
            $agrupado[$clave]['haber'] = ($agrupado[$clave]['haber'] ?? 0) + (float) $s->haber;
        }
        ksort($agrupado, SORT_STRING);

        return ['empresa' => $ruc, 'desde' => $desde, 'hasta' => $hasta, 'cuentas' => collect($agrupado)->take(80)->map(fn ($v, $cuenta) => [
            'cuenta' => (string) $cuenta, 'nombre' => $nombres[$cuenta] ?? null, 'debe' => round($v['debe'], 2), 'haber' => round($v['haber'], 2),
            'saldo_deudor' => round($v['debe'] - $v['haber'], 2)])->values()->all()];
    }

    /** Objetos y colecciones a arreglos simples (y montos redondeados) para mandarlos como JSON */
    private static function limpiar(mixed $v): mixed
    {
        if ($v instanceof Collection) {
            $v = $v->all();
        }
        if ($v instanceof \DateTimeInterface) {
            return $v->format('Y-m-d H:i');
        }
        if (is_object($v)) {
            $v = get_object_vars($v);
        }
        if (is_array($v)) {
            return array_map(fn ($x) => self::limpiar($x), $v);
        }

        return is_float($v) ? round($v, 2) : $v;
    }
}
