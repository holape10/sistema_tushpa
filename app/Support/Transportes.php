<?php

namespace App\Support;

use App\Models\Producto;
use App\Models\Turno;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Transportes: venta de pasajes con número de asiento sobre el plano de cada unidad (bus, lancha, avioneta, vagón).
 *
 * El plano de una unidad: {"pisos": [{"nombre": "Piso 1", "filas": 12, "columnas": 5, "celdas": [[celda, …], …]}]}
 * Cada celda es null (pasillo o espacio vacío) o {"t": tipo, "n": número, "pos": "V"|"P", "cat": categoría}, con tipo
 * A (asiento), H (hamaca), CHOFER, BANO, ESCALERA o PUERTA. Solo A y H se venden.
 *
 * Un pasaje vendido es una línea del comprobante (boleta o factura) con su ruta, asiento, fecha y pasajero. El asiento
 * queda libre otra vez si se anula el comprobante.
 */
class Transportes
{
    public const TIPOS = ['TERRESTRE' => ['Terrestre', 'fa-bus'], 'FLUVIAL' => ['Fluvial', 'fa-ship'], 'AEREO' => ['Aéreo', 'fa-plane'],
        'FERROVIARIO' => ['Ferroviario', 'fa-train']];

    public const CATEGORIAS = ['SEMICAMA' => 'Semicama', 'CAMA' => 'Cama', 'CAMA 180' => 'Cama 180°', 'ECONOMICO' => 'Económico',
        'EJECUTIVO' => 'Ejecutivo', 'HAMACA' => 'Hamaca', 'CAMAROTE' => 'Camarote'];

    /** Lo que se dibuja en el plano además de los asientos */
    public const ELEMENTOS = ['A' => 'Asiento', 'H' => 'Hamaca', 'CHOFER' => 'Chofer', 'BANO' => 'Baño', 'ESCALERA' => 'Escalera', 'PUERTA' => 'Puerta'];

    /** Plantillas para empezar un plano (luego se ajusta en el editor) */
    public const PLANTILLAS = [
        'BUS_2P' => 'Bus 2 pisos (12 cama + 40 semicama)',
        'BUS_1P' => 'Bus 1 piso (44 semicama)',
        'VAN' => 'Van / combi (15 asientos)',
        'LANCHA' => 'Lancha (40 asientos + 20 hamacas)',
        'AVIONETA' => 'Avioneta (12 asientos)',
    ];

    // ------------------------------------------------------------------ planos

    /** Un piso vacío de filas x columnas */
    private static function piso(string $nombre, int $filas, int $columnas): array
    {
        return ['nombre' => $nombre, 'filas' => $filas, 'columnas' => $columnas, 'celdas' => array_fill(0, $filas, array_fill(0, $columnas, null))];
    }

    /** Llena las filas indicadas con asientos en las columnas dadas (la del pasillo queda vacía) */
    private static function asientos(array $piso, int $desde, int $hasta, array $columnas, string $cat, string $tipo = 'A'): array
    {
        for ($f = $desde; $f <= $hasta; $f++) {
            foreach ($columnas as $c) {
                $piso['celdas'][$f][$c] = ['t' => $tipo, 'cat' => $cat];
            }
        }

        return $piso;
    }

    public static function plantilla(string $clave): array
    {
        $pisos = match ($clave) {
            'BUS_2P' => [
                self::asientos(array_replace(self::piso('Piso 1', 5, 4), []), 1, 4, [0, 2, 3], 'CAMA'),
                self::asientos(self::piso('Piso 2', 10, 5), 0, 9, [0, 1, 3, 4], 'SEMICAMA'),
            ],
            'BUS_1P' => [self::asientos(self::piso('Piso 1', 12, 5), 1, 11, [0, 1, 3, 4], 'SEMICAMA')],
            'VAN' => [self::asientos(self::piso('Van', 5, 4), 1, 4, [0, 1, 3], 'ECONOMICO')],
            'LANCHA' => [
                self::asientos(self::piso('Cubierta 1', 10, 5), 0, 9, [0, 1, 3, 4], 'ECONOMICO'),
                self::asientos(self::piso('Cubierta 2', 5, 5), 0, 4, [0, 1, 3, 4], 'HAMACA', 'H'),
            ],
            'AVIONETA' => [self::asientos(self::piso('Cabina', 7, 3), 1, 6, [0, 2], 'ECONOMICO')],
            default => [self::piso('Piso 1', 10, 5)],
        };
        // El chofer adelante (en el primer piso, si hay espacio)
        if (in_array($clave, ['BUS_2P', 'BUS_1P', 'VAN'], true)) {
            $pisos[0]['celdas'][0][0] = ['t' => 'CHOFER'];
        }
        if ($clave === 'BUS_2P') {
            $pisos[0]['celdas'][0][3] = ['t' => 'ESCALERA'];
            $pisos[0]['celdas'][0][2] = ['t' => 'BANO'];
        }

        return self::numerar(['pisos' => $pisos]);
    }

    /**
     * Ordena el plano: quita lo que no corresponde, numera los asientos (y hamacas) de adelante hacia atrás piso por piso
     * (el número escrito a mano se respeta si no se repite) y marca ventana o pasillo según dónde quedó cada uno.
     */
    public static function numerar(array $plano): array
    {
        $pisos = [];
        $usados = [];
        $siguiente = 1;
        foreach (array_slice(array_values($plano['pisos'] ?? []), 0, 4) as $i => $p) {
            $filas = max(1, min(30, (int) ($p['filas'] ?? 10)));
            $columnas = max(1, min(8, (int) ($p['columnas'] ?? 5)));
            $piso = ['nombre' => mb_substr(trim((string) ($p['nombre'] ?? 'Piso '.($i + 1))), 0, 30) ?: 'Piso '.($i + 1), 'filas' => $filas, 'columnas' => $columnas, 'celdas' => []];
            for ($f = 0; $f < $filas; $f++) {
                for ($c = 0; $c < $columnas; $c++) {
                    $celda = $p['celdas'][$f][$c] ?? null;
                    $tipo = is_array($celda) ? (string) ($celda['t'] ?? '') : '';
                    if (! isset(self::ELEMENTOS[$tipo])) {
                        $piso['celdas'][$f][$c] = null;

                        continue;
                    }
                    if ($tipo !== 'A' && $tipo !== 'H') {
                        $piso['celdas'][$f][$c] = ['t' => $tipo];

                        continue;
                    }
                    $numero = strtoupper(trim((string) ($celda['n'] ?? '')));
                    if ($numero === '' || ! preg_match('/^[0-9A-Z]{1,6}$/', $numero) || isset($usados[$numero])) {
                        while (isset($usados[(string) $siguiente])) {
                            $siguiente++;
                        }
                        $numero = (string) $siguiente++;
                    }
                    $usados[$numero] = true;
                    $cat = isset(self::CATEGORIAS[$celda['cat'] ?? '']) ? $celda['cat'] : ($tipo === 'H' ? 'HAMACA' : 'SEMICAMA');
                    $pos = in_array($celda['pos'] ?? null, ['V', 'P'], true) && ! empty($celda['manual'])
                        ? $celda['pos'] : ($c === 0 || $c === $columnas - 1 ? 'V' : 'P');
                    $piso['celdas'][$f][$c] = ['t' => $tipo, 'n' => $numero, 'pos' => $pos, 'cat' => $cat] + (! empty($celda['manual']) ? ['manual' => true] : []);
                }
            }
            $pisos[] = $piso;
        }

        return ['pisos' => $pisos ?: [self::piso('Piso 1', 10, 5)]];
    }

    /**
     * Los asientos que se venden, por número.
     *
     * @return array<string, array{n: string, piso: int, pos: string, cat: string, t: string}>
     */
    public static function asientosDe(array $plano): array
    {
        $lista = [];
        foreach ($plano['pisos'] ?? [] as $i => $p) {
            foreach ($p['celdas'] ?? [] as $fila) {
                foreach ($fila as $celda) {
                    if (is_array($celda) && in_array($celda['t'] ?? '', ['A', 'H'], true) && isset($celda['n'])) {
                        $lista[(string) $celda['n']] = ['n' => (string) $celda['n'], 'piso' => $i + 1, 'pos' => $celda['pos'] ?? 'P', 'cat' => $celda['cat'] ?? 'SEMICAMA', 't' => $celda['t']];
                    }
                }
            }
        }

        return $lista;
    }

    public static function plano(object $unidad): array
    {
        return json_decode((string) $unidad->plano, true) ?: ['pisos' => []];
    }

    // ------------------------------------------------------------------ salidas

    /** El precio de un asiento en una salida: el de su categoría o, si no tiene, el de la salida */
    public static function precio(object $salida, string $categoria): float
    {
        $precios = json_decode((string) $salida->precios, true) ?: [];

        return round((float) (($precios[$categoria] ?? null) ?: $salida->precio), 2);
    }

    /**
     * Asientos vendidos de una salida (el pasaje cuyo comprobante se anuló vuelve a estar libre).
     *
     * @return array<string, object> [asiento => pasaje]
     */
    public static function ocupados(int $salidaId): array
    {
        return DB::table('tra_pasajes as p')->leftJoin('cpe_cabecera as c', 'c.IdCpe_cabecera', '=', 'p.IdCpe_cabecera')
            ->where('p.salida_id', $salidaId)->where('p.estado', 'VENDIDO')->whereNull('c.ccabaj')
            ->get(['p.*', 'c.serdoc', 'c.numdoc'])->keyBy('asiento')->all();
    }

    /** Producto del comprobante (servicio, sin stock) */
    public static function productoPasaje(User $user): int
    {
        $suc = (int) $user->id_empresa_negocio;
        $procod = 'TRA-PASAJE-'.$suc;
        if ($id = DB::table('productos')->where('id_empresa_negocio', $suc)->where('procod', $procod)->value('IdProducto')) {
            return (int) $id;
        }
        $cat = DB::table('categorias')->where('id_empresa_negocio', $suc)->where('cat_nom', 'TRANSPORTES')->value('cat_id')
            ?? DB::table('categorias')->insertGetId(['cat_nom' => 'TRANSPORTES', 'IdEmpresa' => $user->IdEmpresa, 'id_empresa_negocio' => $suc,
                'tip_pro_id' => DB::table('tipo_producto')->where('id_empresa_negocio', $suc)->value('tip_pro_id') ?? 1,
                'cat_acom' => 0, 'visible' => 0, 'predeterminado' => 0]);

        return (int) Producto::create(['procod' => $procod, 'pronom' => 'PASAJE', 'umecod' => 'ZZ', 'costo' => 0, 'propun' => 0,
            'promocion' => 2, 'cat_id' => $cat, 'stock_min' => 0, 'proest' => 'Activo', 'IdEmpresa' => $user->IdEmpresa, 'id_empresa_negocio' => $suc])->IdProducto;
    }

    /**
     * Vende uno o varios asientos de una salida: emite la boleta o factura (una línea por pasaje) con el turno de quien
     * cobra y guarda cada pasaje. Dos cajas nunca venden el mismo asiento (la salida se bloquea mientras se vende).
     *
     * @param  array{pasajeros: list<array{asiento: string, tdicod: string, doc: string, nombre: string, telefono?: ?string}>, tdocod: string,
     *     clinum?: ?string, clinom?: ?string, clidir?: ?string, id_med_pag?: ?int, paga?: ?float}  $d
     * @return int el comprobante emitido
     */
    public static function vender(User $user, int $salidaId, array $d): int
    {
        return DB::transaction(function () use ($user, $salidaId, $d) {
            $suc = (int) $user->id_empresa_negocio;
            $salida = DB::table('tra_salidas')->where('id', $salidaId)->where('id_empresa_negocio', $suc)->lockForUpdate()->first();
            if (! $salida) {
                throw new \RuntimeException('Esa salida no existe.');
            }
            if ($salida->estado !== 'PROGRAMADA') {
                throw new \RuntimeException('Esa salida ya no vende pasajes ('.mb_strtolower($salida->estado).').');
            }
            $turno = Turno::where('IdUsuario', $user->IdUsuario)->where('id_empresa_negocio', $suc)->where('estado', 'ABIERTO')->first();
            if (! $turno) {
                throw new \RuntimeException('Debes aperturar tu turno de caja antes de vender.');
            }
            $ruta = DB::table('tra_rutas')->where('id', $salida->ruta_id)->first();
            $unidad = DB::table('tra_unidades')->where('id', $salida->unidad_id)->first();
            $asientos = self::asientosDe(self::plano($unidad));
            $ocupados = self::ocupados($salidaId);

            $pasajeros = array_values($d['pasajeros'] ?? []);
            if (! $pasajeros) {
                throw new \RuntimeException('Elige al menos un asiento.');
            }
            $vistos = [];
            foreach ($pasajeros as $p) {
                $n = (string) $p['asiento'];
                if (! isset($asientos[$n])) {
                    throw new \RuntimeException("El asiento {$n} no existe en esta unidad.");
                }
                if (isset($ocupados[$n]) || isset($vistos[$n])) {
                    throw new \RuntimeException("El asiento {$n} ya está vendido: elige otro.");
                }
                $vistos[$n] = true;
                if (trim((string) $p['nombre']) === '' || trim((string) $p['doc']) === '') {
                    throw new \RuntimeException("Falta el documento o el nombre del pasajero del asiento {$n}.");
                }
            }

            $producto = self::productoPasaje($user);
            $fecha = now()->parse($salida->salida);
            $lineas = [];
            foreach ($pasajeros as $p) {
                $a = $asientos[(string) $p['asiento']];
                $lineas[] = ['IdProducto' => $producto, 'cantidad' => 1, 'precio' => self::precio($salida, $a['cat']),
                    'descripcion' => mb_substr('PASAJE '.mb_strtoupper($ruta->origen.' - '.$ruta->destino).' · '.$fecha->format('d/m/Y H:i')
                        .' · ASIENTO '.$a['n'].' ('.($a['pos'] === 'V' ? 'VENTANA' : 'PASILLO').')'
                        .' · '.mb_strtoupper(trim($p['nombre'])).' '.trim($p['doc']), 0, 150)];
            }
            $total = round(array_sum(array_column($lineas, 'precio')), 2);
            $tdocod = in_array($d['tdocod'] ?? null, ['01', '03', '13'], true) ? $d['tdocod'] : '03';
            // A nombre de quien paga; si no se escribe, del primer pasajero
            $doc = preg_replace('/\D/', '', (string) (($d['clinum'] ?? null) ?: $pasajeros[0]['doc']));
            if ($tdocod === '01' && strlen($doc) !== 11) {
                throw new \RuntimeException('Para factura escribe el RUC (11 dígitos) de quien paga.');
            }
            $contado = DB::table('credito_dias')->where('id_empresa_negocio', $suc)->where('cre_dia_tip', 'CONTADO')->value('cre_dia_id');
            $medio = $d['id_med_pag'] ?? null;

            $cabId = Comprobante::emitir($user, $turno, [
                'tdocod' => $tdocod, 'estadopago' => $contado, 'fecEmi' => now()->toDateString(),
                'tdicod' => strlen($doc) === 11 ? '6' : ($pasajeros[0]['tdicod'] ?? '1'), 'clinum' => $doc ?: '00000000',
                'clinom' => mb_strtoupper(trim((string) (($d['clinom'] ?? null) ?: $pasajeros[0]['nombre']))), 'clidir' => ($d['clidir'] ?? null) ?: '--',
                'observaciones' => mb_substr('SALIDA '.$fecha->format('d/m/Y H:i').' · '.$unidad->nombre, 0, 100),
                'id_med_pag' => $medio ? [$medio] : [], 'mon_med_pag' => $medio ? [$total] : [], 'paga' => $d['paga'] ?? 0,
            ], $lineas, ['ped_tip' => 'TRANSPORTE', 'IdUsuario_ven' => $user->IdUsuario]);

            foreach ($pasajeros as $i => $p) {
                $a = $asientos[(string) $p['asiento']];
                DB::table('tra_pasajes')->insert([
                    'salida_id' => $salidaId, 'asiento' => $a['n'], 'piso' => $a['piso'], 'categoria' => $a['cat'], 'posicion' => $a['pos'],
                    'precio' => $lineas[$i]['precio'], 'tdicod' => (string) ($p['tdicod'] ?? '1'), 'doc' => trim((string) $p['doc']),
                    'nombre' => mb_strtoupper(trim((string) $p['nombre'])), 'telefono' => ($p['telefono'] ?? null) ?: null,
                    'IdCpe_cabecera' => $cabId, 'estado' => 'VENDIDO', 'IdUsuario' => $user->IdUsuario, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }

            return $cabId;
        });
    }

    /**
     * Manifiesto de pasajeros de una salida (lista para presentar antes de partir).
     *
     * @return list<object>
     */
    public static function manifiesto(int $salidaId): array
    {
        return collect(self::ocupados($salidaId))->sortBy(fn ($p) => [(int) $p->piso, is_numeric($p->asiento) ? (int) $p->asiento : PHP_INT_MAX, $p->asiento])
            ->values()->all();
    }
}
