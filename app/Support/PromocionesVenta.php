<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Promociones automáticas del restaurante: "lleva 3, paga 2" (2x1, 3x2) o "3 por S/ 40", de uno o varios productos
 * (se pueden mezclar: 2x1 en cervezas), en los días, horas y fechas que diga cada una. Se aplican solas al cobrar
 * (mesa, llevar, delivery y PV Restaurante); el mozo no tiene que acordarse.
 *
 * Ante SUNAT la promo es un descuento en las líneas (como el descuento a la cuenta): cada producto va con su precio
 * de lista y su descuento, y los totales cuadran al céntimo. La misma cuenta se hace en la pantalla de cobro (JS).
 */
class PromocionesVenta
{
    public const TIPOS = [
        'LLEVA_PAGA' => ['🎁', 'Lleva y paga menos', '2x1, 3x2: lleva 3 y paga 2'],
        'POR_PRECIO' => ['💰', 'Precio por cantidad', '3 por S/ 40'],
    ];

    /** Desde dónde se cobra con promociones (cpe_cabecera.ped_tip): restaurante, no hotel ni los otros puntos de venta */
    public const ORIGENES = ['SALON', 'LLEVAR', 'DELIVERY', 'PVCOMANDA'];

    public const DIAS = [1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb', 7 => 'Dom'];

    /** Las promociones de la sucursal con sus productos (todas o solo las activas) */
    public static function deSucursal(int $sucursal, bool $soloActivas = false): Collection
    {
        $promos = DB::table('promociones_venta')->where('id_empresa_negocio', $sucursal)
            ->when($soloActivas, fn ($q) => $q->where('activo', true))->orderByDesc('activo')->orderBy('nombre')->get();
        $productos = DB::table('promocion_venta_producto')->whereIn('promocion_id', $promos->pluck('id'))->get()->groupBy('promocion_id');

        return $promos->map(function ($p) use ($productos) {
            $p->productos = ($productos[$p->id] ?? collect())->pluck('IdProducto')->map(fn ($id) => (int) $id)->all();

            return $p;
        });
    }

    /**
     * ¿Vale la promo en ese momento? Fechas, día y horas. Un horario que pasa la medianoche (10 pm a 2 am) sigue siendo
     * del día en que empezó: la 1 am del sábado cuenta como el viernes.
     */
    public static function vale(object $p, Carbon $momento): bool
    {
        if (! $p->activo) {
            return false;
        }
        $dia = $momento->copy();
        if ($p->hora_inicio && $p->hora_fin && $p->hora_inicio !== $p->hora_fin) {
            $hora = $momento->format('H:i:s');
            $inicio = strlen($p->hora_inicio) === 5 ? $p->hora_inicio.':00' : $p->hora_inicio;
            $fin = strlen($p->hora_fin) === 5 ? $p->hora_fin.':00' : $p->hora_fin;
            if ($inicio < $fin) {
                if ($hora < $inicio || $hora >= $fin) {
                    return false;
                }
            } elseif ($hora >= $fin && $hora < $inicio) {
                return false;
            } elseif ($hora < $fin) {
                $dia->subDay();   // madrugada: es la promo del día anterior
            }
        }
        if (! str_contains((string) $p->dias, (string) $dia->isoWeekday())) {
            return false;
        }
        $fecha = $dia->toDateString();

        return (! $p->desde || $fecha >= $p->desde) && (! $p->hasta || $fecha <= $p->hasta);
    }

    /**
     * Promociones que valen para una cuenta: alcanza con que valgan en alguno de los momentos (cuando se abrió la mesa
     * o cuando se cobra: la mesa que pidió en la hora feliz conserva la promo aunque pague después).
     *
     * @param  array<int, Carbon>  $momentos
     */
    public static function vigentes(int $sucursal, array $momentos): Collection
    {
        return self::deSucursal($sucursal, true)
            ->filter(fn ($p) => $p->productos && collect($momentos)->contains(fn ($m) => self::vale($p, $m)))->values();
    }

    /**
     * Aplica las promociones a las líneas que se cobran. Cada unidad entra en una sola promo; las cortesías y las
     * presentaciones (jarra, botella) no entran. En "lleva y paga" van gratis las unidades más baratas de cada grupo.
     *
     * @param  array  $lineas  [['IdProducto', 'cantidad', 'precio', 'cortesia'?, 'factor'?], ...]
     * @return array{0: array, 1: array<int, array{id: int, nombre: string, veces: int, ahorro: float}>}
     */
    public static function aplicar(Collection $promos, array $lineas): array
    {
        $usadas = [];       // índice de línea => unidades que ya entraron en una promo
        $descuentos = [];   // índice de línea => descuento
        $aplicadas = [];
        foreach ($promos as $p) {
            $lleva = max(1, (int) $p->lleva);
            $unidades = [];
            foreach ($lineas as $i => $l) {
                $esPresentacion = ! empty($l['presentacion']) || ! empty($l['umecod']) || (float) ($l['factor'] ?? 1) != 1;
                if (! empty($l['cortesia']) || $esPresentacion || ! in_array((int) ($l['IdProducto'] ?? 0), $p->productos, true)) {
                    continue;
                }
                $libres = (int) floor((float) $l['cantidad'] + 0.0001) - ($usadas[$i] ?? 0);
                for ($u = 0; $u < $libres; $u++) {
                    $unidades[] = ['linea' => $i, 'precio' => (float) $l['precio']];
                }
            }
            $grupos = intdiv(count($unidades), $lleva);
            if ($grupos === 0) {
                continue;
            }
            // De la más cara a la más barata: cada grupo junta unidades parecidas
            usort($unidades, fn ($a, $b) => $b['precio'] <=> $a['precio']);
            $ahorro = 0.0;
            $veces = 0;
            foreach (array_chunk(array_slice($unidades, 0, $grupos * $lleva), $lleva) as $grupo) {
                $porUnidad = self::descuentoDelGrupo($p, $grupo);
                if (array_sum($porUnidad) <= 0) {
                    continue;
                }
                $veces++;
                foreach ($grupo as $k => $u) {
                    $usadas[$u['linea']] = ($usadas[$u['linea']] ?? 0) + 1;
                    $descuentos[$u['linea']] = round(($descuentos[$u['linea']] ?? 0) + $porUnidad[$k], 2);
                    $ahorro += $porUnidad[$k];
                }
            }
            if ($veces > 0) {
                $aplicadas[] = ['id' => (int) $p->id, 'nombre' => (string) $p->nombre, 'veces' => $veces, 'ahorro' => round($ahorro, 2)];
            }
        }
        foreach ($descuentos as $i => $d) {
            if ($d > 0) {
                $total = round((float) $lineas[$i]['cantidad'] * (float) $lineas[$i]['precio'], 2);
                $neto = round($total - $d, 2);
                $lineas[$i]['precio_lista'] = $lineas[$i]['precio_lista'] ?? $lineas[$i]['precio'];
                $lineas[$i]['descuento'] = $d;
                $lineas[$i]['importe'] = $neto;
                $lineas[$i]['precio'] = round($neto / (float) $lineas[$i]['cantidad'], 2);
            }
        }

        return [$lineas, $aplicadas];
    }

    /**
     * Cuánto se descuenta a cada unidad de un grupo (ya ordenado de la más cara a la más barata).
     * Lleva y paga: las más baratas van gratis. Por precio: lo que pasa del precio de la promo, repartido según el
     * precio de cada unidad (el redondeo lo absorbe la más cara).
     *
     * @param  array<int, array{linea: int, precio: float}>  $grupo
     * @return array<int, float>
     */
    private static function descuentoDelGrupo(object $p, array $grupo): array
    {
        $precios = array_column($grupo, 'precio');
        if ($p->tipo === 'LLEVA_PAGA') {
            $paga = max(0, min(count($grupo), (int) $p->paga));

            return array_map(fn ($precio, $k) => $k >= $paga ? $precio : 0.0, $precios, array_keys($precios));
        }
        $suma = array_sum($precios);
        $total = round($suma - (float) $p->precio, 2);
        if ($total <= 0 || $suma <= 0) {
            return array_fill(0, count($grupo), 0.0);
        }
        $partes = array_map(fn ($precio) => round($total * $precio / $suma, 2), $precios);
        $partes[0] = round($partes[0] + $total - array_sum($partes), 2);

        return $partes;
    }

    /**
     * Las promociones que le tocan a lo que falta cobrar de un pedido (para la precuenta: el cliente ve cuánto pagará)
     *
     * @return array<int, array{id: int, nombre: string, veces: int, ahorro: float}>
     */
    public static function delPedido(int $pedId): array
    {
        $pedido = DB::table('pedidos')->where('ped_id', $pedId)->first(['id_empresa_negocio', 'fecha_hora', 'ped_tip']);
        if (! $pedido || ! in_array(mb_strtoupper((string) $pedido->ped_tip), self::ORIGENES, true)) {
            return [];
        }
        $promos = self::vigentes((int) $pedido->id_empresa_negocio, array_filter([now(), $pedido->fecha_hora ? Carbon::parse($pedido->fecha_hora) : null]));
        if ($promos->isEmpty()) {
            return [];
        }
        $lineas = DB::table('pedidos_detalle')->where('ped_id', $pedId)->where('estadoitem', '!=', 'Eliminado')->get()
            ->map(fn ($d) => ['IdProducto' => (int) $d->IdProducto, 'cantidad' => round((float) $d->ped_det_can - (float) $d->item_facturado, 2),
                'precio' => (float) $d->ped_det_pre, 'factor' => $d->id_presentacion ? 0 : 1])
            ->filter(fn ($l) => $l['cantidad'] > 0)->values()->all();

        return self::aplicar($promos, $lineas)[1];
    }

    /** Lo que ahorró un comprobante por promociones (para imprimirlo) */
    public static function delComprobante(object $cab): Collection
    {
        return (float) ($cab->promo_descuento ?? 0) > 0
            ? DB::table('cpe_promociones')->where('IdCpe_cabecera', $cab->IdCpe_cabecera)->orderBy('id')->get(['nombre', 'veces', 'ahorro'])
            : collect();
    }

    /** "3x2", "2x1" o "3 por S/ 40.00": cómo se dice la promo */
    public static function etiqueta(object $p): string
    {
        return $p->tipo === 'LLEVA_PAGA' ? $p->lleva.'x'.$p->paga : $p->lleva.' por S/ '.number_format((float) $p->precio, 2);
    }

    /** "Vie y Sáb · 10 pm a 2 am · hasta el 30/11": cuándo vale */
    public static function cuando(object $p): string
    {
        $dias = (string) $p->dias === '1234567' ? 'Todos los días' : collect(str_split((string) $p->dias))->map(fn ($d) => self::DIAS[(int) $d] ?? '')->filter()->implode(', ');
        $horas = $p->hora_inicio && $p->hora_fin && $p->hora_inicio !== $p->hora_fin
            ? PanelDueno::hora((int) substr($p->hora_inicio, 0, 2)).(substr($p->hora_inicio, 3, 2) !== '00' ? ':'.substr($p->hora_inicio, 3, 2) : '')
                .' a '.PanelDueno::hora((int) substr($p->hora_fin, 0, 2)).(substr($p->hora_fin, 3, 2) !== '00' ? ':'.substr($p->hora_fin, 3, 2) : '')
            : 'todo el día';
        $fechas = trim(($p->desde ? 'desde el '.Carbon::parse($p->desde)->format('d/m') : '').' '.($p->hasta ? 'hasta el '.Carbon::parse($p->hasta)->format('d/m/Y') : ''));

        return $dias.' · '.$horas.($fechas !== '' ? ' · '.$fechas : '');
    }

    /** Lo que necesita la pantalla de cobro para mostrar la promo antes de cobrar (la misma cuenta, en JS) */
    public static function paraPantalla(Collection $promos): array
    {
        return $promos->map(fn ($p) => ['id' => (int) $p->id, 'nombre' => $p->nombre, 'tipo' => $p->tipo, 'lleva' => (int) $p->lleva,
            'paga' => (int) $p->paga, 'precio' => (float) $p->precio, 'productos' => $p->productos])->values()->all();
    }
}
