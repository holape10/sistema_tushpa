<?php

namespace App\Support;

use App\Models\Mesa;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Plano del local por piso: dónde está cada mesa (o box VIP), su forma y tamaño, y lo que ayuda a ubicarse (barra,
 * paredes, DJ...). La sucursal elige ver las mesas en cuadrícula (como siempre) o en su plano. Lienzo de 1000 x 625.
 */
class PlanoMesas
{
    public const ANCHO = 1000;

    public const ALTO = 625;

    /** Formas de mesa: [nombre, ancho, alto] por defecto */
    public const FORMAS = [
        'CUADRADA' => ['Cuadrada', 90, 90],
        'REDONDA' => ['Redonda', 90, 90],
        'RECTANGULAR' => ['Larga', 160, 80],
        'BOX' => ['Box VIP', 200, 130],
    ];

    /** Lo que no es mesa: [ícono, nombre, color, ancho, alto] por defecto */
    public const ELEMENTOS = [
        'BARRA' => ['🍸', 'Barra', '#7c2d12', 300, 60],
        'PARED' => ['', 'Pared', '#475569', 300, 14],
        'PUERTA' => ['🚪', 'Entrada', '#16a34a', 90, 30],
        'DJ' => ['🎧', 'DJ / escenario', '#7e22ce', 160, 100],
        'BANO' => ['🚻', 'Baños', '#0369a1', 100, 70],
        'PISTA' => ['💃', 'Pista de baile', '#db2777', 240, 160],
        'COCINA' => ['🍳', 'Cocina', '#ea580c', 200, 120],
        'PLANTA' => ['🌿', 'Planta', '#15803d', 50, 50],
        'TEXTO' => ['', 'Texto', '#111827', 160, 40],
    ];

    public const MINIMO = 20;

    /** 1 unidad del plano = 2 cm: 50 unidades por metro (el plano de siempre, 1000 x 625, es un local de 20 x 12.5 m) */
    public const POR_METRO = 50;

    /** Medidas del local que se pueden poner: de 2 m a 60 m de ancho o de largo */
    public const MEDIDA_MIN = 100;

    public const MEDIDA_MAX = 3000;

    /** Largo del local de un piso, en unidades (625 si nunca se cambió) */
    public static function alto(int $piso): int
    {
        return (int) (DB::table('pisos')->where('pis_id', $piso)->value('plano_alto') ?: self::ALTO);
    }

    /** Ancho del local de un piso, en unidades (1000 si nunca se cambió) */
    public static function ancho(int $piso): int
    {
        return (int) (DB::table('pisos')->where('pis_id', $piso)->value('plano_ancho') ?: self::ANCHO);
    }

    /** ¿La sucursal ve sus mesas en el plano? */
    public static function usaPlano(int $sucursal): bool
    {
        return DB::table('empresa_negocios')->where('id_empresa_negocio', $sucursal)->value('vista_mesas') === 'PLANO';
    }

    public static function elementos(int $sucursal, int $piso): Collection
    {
        return DB::table('plano_elementos')->where('id_empresa_negocio', $sucursal)->where('pis_id', $piso)->orderBy('id')->get();
    }

    /** Una medida dentro del lienzo, en pasos de 5 */
    private static function medida(mixed $valor, int $min, int $max): int
    {
        return (int) max($min, min($max, round(((float) $valor) / 5) * 5));
    }

    /**
     * Guarda el plano de un piso: el alto del ambiente, posición, tamaño, forma y nombre de las mesas (las nuevas se
     * crean; las que se sacan del plano quedan sin ubicar; las de $eliminar se borran si no están ocupadas) y los
     * elementos (se reemplazan todos).
     *
     * @param  array<int, int>  $eliminar
     * @param  array<int, array{id: ?int, nombre: string, forma: string, x: int, y: int, w: int, h: int}>  $mesas
     * @param  array<int, array{tipo: string, texto: ?string, x: int, y: int, w: int, h: int, color: ?string}>  $elementos
     * @return int mesas en el plano
     */
    public static function guardar(int $sucursal, int $idEmpresa, int $piso, array $mesas, array $elementos, int $alto = self::ALTO, array $eliminar = [], int $ancho = self::ANCHO): int
    {
        $alto = (int) max(self::MEDIDA_MIN, min(self::MEDIDA_MAX, round($alto / 5) * 5));
        $ancho = (int) max(self::MEDIDA_MIN, min(self::MEDIDA_MAX, round($ancho / 5) * 5));

        return DB::transaction(function () use ($sucursal, $idEmpresa, $piso, $mesas, $elementos, $alto, $eliminar, $ancho) {
            DB::table('pisos')->where('pis_id', $piso)->update(['plano_alto' => $alto, 'plano_ancho' => $ancho]);
            // Mesas que el dueño eliminó en el plano (una ocupada no: primero se cobra o se libera)
            foreach (Mesa::where('id_empresa_negocio', $sucursal)->where('pis_id', $piso)->whereIn('mes_id', array_map('intval', $eliminar) ?: [0])->get() as $mesa) {
                if (MesasUnidas::pedidoDe($mesa->mes_id)) {
                    throw new \RuntimeException("La {$mesa->mes_nom} está ocupada: cóbrala o libérala antes de eliminarla.");
                }
                $mesa->delete();
            }
            $propias = Mesa::where('id_empresa_negocio', $sucursal)->where('pis_id', $piso)->pluck('mes_nom', 'mes_id');
            // Las mesas del piso que no vienen en el plano conservan su nombre: no se puede repetir
            $enPlano = array_map('intval', array_filter(array_column($mesas, 'id')));
            $otras = $propias->except($enPlano)->map(fn ($n) => mb_strtoupper(trim((string) $n)))->flip();
            $ubicadas = [];
            $nombres = [];
            foreach ($mesas as $m) {
                $nombre = mb_strtoupper(trim(mb_substr((string) ($m['nombre'] ?? ''), 0, 40)));
                if ($nombre === '') {
                    throw new \RuntimeException('Hay una mesa sin nombre en el plano.');
                }
                if (isset($nombres[$nombre])) {
                    throw new \RuntimeException("Hay dos mesas llamadas {$nombre} en el plano: cambia el nombre de una.");
                }
                if ($otras->has($nombre)) {
                    throw new \RuntimeException("Ya hay otra {$nombre} en este piso (está en \"Sin ubicar\"): ponla en el plano, elimínala o cambia el nombre de la nueva.");
                }
                $nombres[$nombre] = true;
                $forma = array_key_exists($m['forma'] ?? '', self::FORMAS) ? $m['forma'] : 'CUADRADA';
                $w = self::medida($m['w'] ?? 90, self::MINIMO, $ancho);
                $h = self::medida($m['h'] ?? 90, self::MINIMO, $alto);
                $fila = ['mes_nom' => $nombre, 'plano_forma' => $forma, 'plano_w' => $w, 'plano_h' => $h,
                    'plano_x' => self::medida($m['x'] ?? 0, 0, $ancho - $w), 'plano_y' => self::medida($m['y'] ?? 0, 0, $alto - $h)];
                $id = (int) ($m['id'] ?? 0);
                if ($id && $propias->has($id)) {
                    Mesa::where('mes_id', $id)->update($fila);
                    $ubicadas[] = $id;
                } elseif (! $id) {
                    // Mesa o box nuevo dibujado en el plano
                    $ubicadas[] = Mesa::create($fila + ['pis_id' => $piso, 'mes_est' => 'Libre', 'IdEmpresa' => $idEmpresa, 'id_empresa_negocio' => $sucursal])->mes_id;
                }
            }
            // Las que se sacaron del plano quedan sin ubicar (siguen existiendo y se ven debajo del plano)
            Mesa::where('id_empresa_negocio', $sucursal)->where('pis_id', $piso)->whereNotIn('mes_id', $ubicadas ?: [0])
                ->update(['plano_x' => null, 'plano_y' => null, 'plano_w' => null, 'plano_h' => null, 'plano_forma' => null]);

            DB::table('plano_elementos')->where('id_empresa_negocio', $sucursal)->where('pis_id', $piso)->delete();
            foreach (array_slice($elementos, 0, 200) as $e) {
                if (! array_key_exists($e['tipo'] ?? '', self::ELEMENTOS)) {
                    continue;
                }
                $w = self::medida($e['w'] ?? 100, 10, $ancho);
                $h = self::medida($e['h'] ?? 40, 10, $alto);
                DB::table('plano_elementos')->insert([
                    'id_empresa_negocio' => $sucursal, 'pis_id' => $piso, 'tipo' => $e['tipo'],
                    'texto' => trim(mb_substr((string) ($e['texto'] ?? ''), 0, 40)) ?: null,
                    'x' => self::medida($e['x'] ?? 0, 0, $ancho - $w), 'y' => self::medida($e['y'] ?? 0, 0, $alto - $h), 'w' => $w, 'h' => $h,
                    'color' => preg_match('/^#[0-9a-f]{6}$/i', (string) ($e['color'] ?? '')) ? strtolower($e['color']) : self::ELEMENTOS[$e['tipo']][2],
                ]);
            }

            return count($ubicadas);
        });
    }

    /**
     * Píxeles por unidad para que la mesa más chica del plano se vea como un botón de la cuadrícula ($botonAncho x $botonAlto px).
     * Con un tope, para que una mesa diminuta no vuelva gigante todo el plano.
     *
     * @param  Collection<int, object{plano_w: int, plano_h: int}>  $mesas
     */
    public static function escalaMinima(Collection $mesas, int $botonAncho, int $botonAlto): float
    {
        if ($mesas->isEmpty()) {
            return 0;
        }

        return round(min(2.5, max($botonAncho / max(self::MINIMO, (int) $mesas->min('plano_w')), $botonAlto / max(self::MINIMO, (int) $mesas->min('plano_h')))), 3);
    }

    /**
     * Una mesa en Comandas: arranca en su lugar con su tamaño como mínimo, pero crece lo que necesite para que se lea
     * todo (nombre, total, tiempo y mozo), aunque en un celular se pase un poco de su lugar.
     */
    public static function estiloMesa(int|float $x, int|float $y, int|float $w, int|float $h, int $alto = self::ALTO, int $ancho = self::ANCHO): string
    {
        $p = fn ($v, $total) => round($v / $total * 100, 3).'%';

        return 'left:'.$p($x, $ancho).';top:'.$p($y, $alto).';min-width:'.$p($w, $ancho).';min-height:'.$p($h, $alto);
    }

    /** Estilo de posición de algo en el plano (en % del lienzo, así se ve igual en cualquier pantalla) */
    public static function estilo(int|float $x, int|float $y, int|float $w, int|float $h, int $alto = self::ALTO, int $ancho = self::ANCHO): string
    {
        $p = fn ($v, $total) => round($v / $total * 100, 3).'%';

        return 'left:'.$p($x, $ancho).';top:'.$p($y, $alto).';width:'.$p($w, $ancho).';height:'.$p($h, $alto);
    }
}
