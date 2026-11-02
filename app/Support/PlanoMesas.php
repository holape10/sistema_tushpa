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
        'PLANTA' => ['🌿', 'Planta', '#15803d', 50, 50],
        'TEXTO' => ['', 'Texto', '#111827', 160, 40],
    ];

    public const MINIMO = 20;

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
     * Guarda el plano de un piso: posición, tamaño, forma y nombre de las mesas (las nuevas se crean; las que se sacan
     * del plano quedan sin ubicar, no se borran) y los elementos (se reemplazan todos).
     *
     * @param  array<int, array{id: ?int, nombre: string, forma: string, x: int, y: int, w: int, h: int}>  $mesas
     * @param  array<int, array{tipo: string, texto: ?string, x: int, y: int, w: int, h: int, color: ?string}>  $elementos
     * @return int mesas en el plano
     */
    public static function guardar(int $sucursal, int $idEmpresa, int $piso, array $mesas, array $elementos): int
    {
        return DB::transaction(function () use ($sucursal, $idEmpresa, $piso, $mesas, $elementos) {
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
                if (isset($nombres[$nombre]) || $otras->has($nombre)) {
                    throw new \RuntimeException("Hay dos mesas llamadas {$nombre} en este piso: cambia el nombre de una.");
                }
                $nombres[$nombre] = true;
                $forma = array_key_exists($m['forma'] ?? '', self::FORMAS) ? $m['forma'] : 'CUADRADA';
                $w = self::medida($m['w'] ?? 90, self::MINIMO, self::ANCHO);
                $h = self::medida($m['h'] ?? 90, self::MINIMO, self::ALTO);
                $fila = ['mes_nom' => $nombre, 'plano_forma' => $forma, 'plano_w' => $w, 'plano_h' => $h,
                    'plano_x' => self::medida($m['x'] ?? 0, 0, self::ANCHO - $w), 'plano_y' => self::medida($m['y'] ?? 0, 0, self::ALTO - $h)];
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
                $w = self::medida($e['w'] ?? 100, 10, self::ANCHO);
                $h = self::medida($e['h'] ?? 40, 10, self::ALTO);
                DB::table('plano_elementos')->insert([
                    'id_empresa_negocio' => $sucursal, 'pis_id' => $piso, 'tipo' => $e['tipo'],
                    'texto' => trim(mb_substr((string) ($e['texto'] ?? ''), 0, 40)) ?: null,
                    'x' => self::medida($e['x'] ?? 0, 0, self::ANCHO - $w), 'y' => self::medida($e['y'] ?? 0, 0, self::ALTO - $h), 'w' => $w, 'h' => $h,
                    'color' => preg_match('/^#[0-9a-f]{6}$/i', (string) ($e['color'] ?? '')) ? strtolower($e['color']) : self::ELEMENTOS[$e['tipo']][2],
                ]);
            }

            return count($ubicadas);
        });
    }

    /** Estilo de posición de algo en el plano (en % del lienzo, así se ve igual en cualquier pantalla) */
    public static function estilo(int|float $x, int|float $y, int|float $w, int|float $h): string
    {
        $p = fn ($v, $total) => round($v / $total * 100, 3).'%';

        return 'left:'.$p($x, self::ANCHO).';top:'.$p($y, self::ALTO).';width:'.$p($w, self::ANCHO).';height:'.$p($h, self::ALTO);
    }
}
