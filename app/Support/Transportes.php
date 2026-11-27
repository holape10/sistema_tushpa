<?php

namespace App\Support;

use App\Models\Producto;
use App\Models\Turno;
use App\Models\User;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Transportes: venta de pasajes con número de asiento sobre el plano de cada unidad (bus, lancha, avioneta, vagón).
 *
 * El plano de una unidad: {"pisos": [{"nombre": "Piso 1", "filas": 12, "columnas": 5, "celdas": [[celda, …], …]}]}
 * Cada celda es null (pasillo o espacio vacío) o {"t": tipo, "n": número, "pos": "V"|"P", "cat": categoría}, con tipo
 * A (asiento), H (hamaca), CHOFER, BANO, ESCALERA o PUERTA. Solo A y H se venden.
 *
 * Un pasaje vendido es una línea del comprobante (boleta o factura) con su ruta, asiento, fecha y pasajero. El asiento
 * queda libre otra vez si se anula el comprobante. Un pasaje puede ser de un tramo (de una parada a otra): el asiento
 * queda libre para el resto del viaje. Una reserva toma el asiento sin cobrar hasta que vence (en caja o por internet).
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

    // ------------------------------------------------------------------ salidas y paradas

    /** El precio de un asiento en una salida: el de su categoría o, si no tiene, el de la salida */
    public static function precio(object $salida, string $categoria): float
    {
        $precios = json_decode((string) $salida->precios, true) ?: [];

        return round((float) (($precios[$categoria] ?? null) ?: $salida->precio), 2);
    }

    /**
     * Las paradas de una ruta, en orden: el origen, las paradas intermedias y el destino.
     *
     * @return list<string>
     */
    public static function paradas(object $ruta): array
    {
        $medio = array_values(array_filter(array_map(fn ($p) => mb_strtoupper(trim($p)), explode(',', (string) $ruta->paradas))));

        return array_merge([mb_strtoupper($ruta->origen)], $medio, [mb_strtoupper($ruta->destino)]);
    }

    /** El tramo pedido (índices de parada), validado: sube antes de bajar; sin tramo, todo el viaje */
    public static function tramo(object $ruta, ?int $desde, ?int $hasta): array
    {
        $ultima = count(self::paradas($ruta)) - 1;
        $desde = max(0, min($ultima - 1, (int) ($desde ?? 0)));
        $hasta = $hasta === null ? $ultima : max($desde + 1, min($ultima, $hasta));

        return [$desde, $hasta === $ultima ? 99 : $hasta];
    }

    /** "LIMA - HUANCAYO" o, si es un tramo, "LA OROYA - HUANCAYO" */
    public static function textoTramo(object $ruta, int $desde, int $hasta): string
    {
        $paradas = self::paradas($ruta);

        return $paradas[$desde].' - '.$paradas[$hasta >= 99 ? count($paradas) - 1 : $hasta];
    }

    /**
     * Pasajes vigentes de una salida: los vendidos (con su comprobante sin anular) y las reservas que no han vencido.
     *
     * @return list<object>
     */
    public static function activos(int $salidaId): array
    {
        return DB::table('tra_pasajes as p')->leftJoin('cpe_cabecera as c', 'c.IdCpe_cabecera', '=', 'p.IdCpe_cabecera')
            ->where('p.salida_id', $salidaId)
            ->where(fn ($q) => $q->where(fn ($v) => $v->where('p.estado', 'VENDIDO')->whereNull('c.ccabaj'))
                ->orWhere(fn ($r) => $r->where('p.estado', 'RESERVADO')->where('p.reservado_hasta', '>', now())))
            ->orderBy('p.id')->get(['p.*', 'c.serdoc', 'c.numdoc'])->all();
    }

    /**
     * Asientos tomados en un tramo (un asiento vendido de Lima a La Oroya queda libre de La Oroya a Huancayo).
     *
     * @return array<string, object> [asiento => pasaje]
     */
    public static function ocupados(int $salidaId, int $desde = 0, int $hasta = 99): array
    {
        $tomados = [];
        foreach (self::activos($salidaId) as $p) {
            if ((int) $p->tramo_desde < $hasta && $desde < (int) $p->tramo_hasta) {
                $tomados[(string) $p->asiento] ??= $p;
            }
        }

        return $tomados;
    }

    public static function config(int $sucursal): object
    {
        return DB::table('tra_config')->where('id_empresa_negocio', $sucursal)->first()
            ?? (object) ['id_empresa_negocio' => $sucursal, 'reserva_min' => 120, 'web_activo' => false, 'web_reserva_min' => 60, 'web_texto' => null];
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

    // ------------------------------------------------------------------ vender y reservar

    /**
     * Revisa y prepara los pasajes pedidos (la salida ya bloqueada): asiento que existe y libre en ese tramo, pasajero con
     * documento y nombre, y su precio (el de su categoría; en un tramo, el que se escribió).
     *
     * @return array{salida: object, ruta: object, unidad: object, filas: list<array<string, mixed>>}
     */
    private static function preparar(int $sucursal, int $salidaId, array $pasajeros, ?int $desde, ?int $hasta): array
    {
        $salida = DB::table('tra_salidas')->where('id', $salidaId)->where('id_empresa_negocio', $sucursal)->lockForUpdate()->first();
        if (! $salida) {
            throw new \RuntimeException('Esa salida no existe.');
        }
        if ($salida->estado !== 'PROGRAMADA') {
            throw new \RuntimeException('Esa salida ya no vende pasajes ('.mb_strtolower($salida->estado).').');
        }
        $ruta = DB::table('tra_rutas')->where('id', $salida->ruta_id)->first();
        $unidad = DB::table('tra_unidades')->where('id', $salida->unidad_id)->first();
        [$desde, $hasta] = self::tramo($ruta, $desde, $hasta);
        $completo = $desde === 0 && $hasta === 99;
        $asientos = self::asientosDe(self::plano($unidad));
        $ocupados = self::ocupados($salidaId, $desde, $hasta);
        $pasajeros = array_values($pasajeros);
        if (! $pasajeros) {
            throw new \RuntimeException('Elige al menos un asiento.');
        }
        $filas = [];
        foreach ($pasajeros as $p) {
            $n = (string) $p['asiento'];
            if (! isset($asientos[$n])) {
                throw new \RuntimeException("El asiento {$n} no existe en esta unidad.");
            }
            if (isset($ocupados[$n]) || isset($filas[$n])) {
                throw new \RuntimeException("El asiento {$n} ya está ocupado en ese tramo: elige otro.");
            }
            if (trim((string) ($p['nombre'] ?? '')) === '' || trim((string) ($p['doc'] ?? '')) === '') {
                throw new \RuntimeException("Falta el documento o el nombre del pasajero del asiento {$n}.");
            }
            $a = $asientos[$n];
            // Precio: el de su categoría; en un tramo (no todo el viaje) el que se escribió
            $precio = ! $completo && isset($p['precio']) && (float) $p['precio'] > 0 ? round((float) $p['precio'], 2) : self::precio($salida, $a['cat']);
            $filas[$n] = ['asiento' => $a, 'precio' => $precio, 'tdicod' => (string) ($p['tdicod'] ?? '1'), 'doc' => trim((string) $p['doc']),
                'nombre' => mb_strtoupper(trim((string) $p['nombre'])), 'telefono' => ($p['telefono'] ?? null) ?: null];
        }

        return ['salida' => $salida, 'ruta' => $ruta, 'unidad' => $unidad, 'filas' => array_values($filas), 'desde' => $desde, 'hasta' => $hasta];
    }

    /** Guarda los pasajes preparados; devuelve sus id */
    private static function guardar(array $prep, string $estado, array $extra, ?int $usuario): array
    {
        $grupo = $extra['grupo'] ?? strtoupper(Str::random(10));
        $ids = [];
        foreach ($prep['filas'] as $f) {
            $ids[] = DB::table('tra_pasajes')->insertGetId([
                'salida_id' => $prep['salida']->id, 'asiento' => $f['asiento']['n'], 'piso' => $f['asiento']['piso'], 'categoria' => $f['asiento']['cat'],
                'posicion' => $f['asiento']['pos'], 'precio' => $f['precio'], 'tdicod' => $f['tdicod'], 'doc' => $f['doc'], 'nombre' => $f['nombre'],
                'telefono' => $f['telefono'], 'estado' => $estado, 'IdUsuario' => $usuario, 'tramo_desde' => $prep['desde'], 'tramo_hasta' => $prep['hasta'],
                'codigo' => strtoupper(Str::random(10)), 'grupo' => $grupo, 'created_at' => now(), 'updated_at' => now(),
            ] + array_intersect_key($extra, array_flip(['IdCpe_cabecera', 'reservado_hasta', 'origen_venta', 'web_usuario_id', 'medio_web', 'operacion_web'])));
        }

        return $ids;
    }

    /**
     * Emite la boleta, factura o nota de venta de unos pasajes (una línea por pasaje) con el turno de quien cobra.
     *
     * @param  list<object>  $pasajes  con salida_id, asiento, posicion, precio, doc, nombre, tdicod, tramo_desde, tramo_hasta
     */
    private static function emitir(User $user, object $salida, object $ruta, object $unidad, array $pasajes, array $d): int
    {
        $suc = (int) $user->id_empresa_negocio;
        $turno = Turno::where('IdUsuario', $user->IdUsuario)->where('id_empresa_negocio', $suc)->where('estado', 'ABIERTO')->first();
        if (! $turno) {
            throw new \RuntimeException('Debes aperturar tu turno de caja antes de cobrar.');
        }
        $producto = self::productoPasaje($user);
        $fecha = now()->parse($salida->salida);
        $lineas = array_map(fn ($p) => ['IdProducto' => $producto, 'cantidad' => 1, 'precio' => (float) $p->precio,
            'descripcion' => mb_substr('PASAJE '.self::textoTramo($ruta, (int) $p->tramo_desde, (int) $p->tramo_hasta).' · '.$fecha->format('d/m/Y H:i')
                .' · ASIENTO '.$p->asiento.' ('.($p->posicion === 'V' ? 'VENTANA' : 'PASILLO').') · '.$p->nombre.' '.$p->doc, 0, 150)], $pasajes);
        $total = round(array_sum(array_column($lineas, 'precio')), 2);
        $tdocod = in_array($d['tdocod'] ?? null, ['01', '03', '13'], true) ? $d['tdocod'] : '03';
        $doc = preg_replace('/\D/', '', (string) (($d['clinum'] ?? null) ?: $pasajes[0]->doc));
        if ($tdocod === '01' && strlen($doc) !== 11) {
            throw new \RuntimeException('Para factura escribe el RUC (11 dígitos) de quien paga.');
        }
        $contado = DB::table('credito_dias')->where('id_empresa_negocio', $suc)->where('cre_dia_tip', 'CONTADO')->value('cre_dia_id');
        $medio = $d['id_med_pag'] ?? null;

        return Comprobante::emitir($user, $turno, [
            'tdocod' => $tdocod, 'estadopago' => $contado, 'fecEmi' => now()->toDateString(),
            'tdicod' => strlen($doc) === 11 ? '6' : ($pasajes[0]->tdicod ?: '1'), 'clinum' => $doc ?: '00000000',
            'clinom' => mb_strtoupper(trim((string) (($d['clinom'] ?? null) ?: $pasajes[0]->nombre))), 'clidir' => ($d['clidir'] ?? null) ?: '--',
            'observaciones' => mb_substr('SALIDA '.$fecha->format('d/m/Y H:i').' · '.$unidad->nombre, 0, 100),
            'id_med_pag' => $medio ? [$medio] : [], 'mon_med_pag' => $medio ? [$total] : [], 'paga' => $d['paga'] ?? 0,
            'op_med_pag' => $medio && ! empty($d['operacion']) ? [$d['operacion']] : [],
        ], $lineas, ['ped_tip' => 'TRANSPORTE', 'IdUsuario_ven' => $user->IdUsuario]);
    }

    /**
     * Vende uno o varios asientos de una salida: emite el comprobante (una línea por pasaje) y guarda cada pasaje. Dos
     * cajas nunca venden el mismo asiento en el mismo tramo (la salida se bloquea mientras se vende).
     *
     * @param  array{pasajeros: list<array<string, mixed>>, tdocod: string, desde?: ?int, hasta?: ?int, clinum?: ?string, clinom?: ?string,
     *     clidir?: ?string, id_med_pag?: ?int, paga?: ?float}  $d
     * @return int el comprobante emitido
     */
    public static function vender(User $user, int $salidaId, array $d): int
    {
        return DB::transaction(function () use ($user, $salidaId, $d) {
            $prep = self::preparar((int) $user->id_empresa_negocio, $salidaId, $d['pasajeros'] ?? [], $d['desde'] ?? null, $d['hasta'] ?? null);
            $pasajes = array_map(fn ($f) => (object) ['asiento' => $f['asiento']['n'], 'posicion' => $f['asiento']['pos'], 'precio' => $f['precio'],
                'doc' => $f['doc'], 'nombre' => $f['nombre'], 'tdicod' => $f['tdicod'], 'tramo_desde' => $prep['desde'], 'tramo_hasta' => $prep['hasta']], $prep['filas']);
            $cabId = self::emitir($user, $prep['salida'], $prep['ruta'], $prep['unidad'], $pasajes, $d);
            self::guardar($prep, 'VENDIDO', ['IdCpe_cabecera' => $cabId], (int) $user->IdUsuario);

            return $cabId;
        });
    }

    /**
     * Reserva asientos sin cobrar (en caja o por internet): quedan tomados hasta que vence la reserva; si no se cobra a
     * tiempo, se liberan solos. Devuelve el código del grupo (para cobrarla o liberarla).
     *
     * @param  array{origen?: string, minutos?: int, web_usuario_id?: ?int, medio_web?: ?string, operacion_web?: ?string}  $opciones
     */
    public static function reservar(int $sucursal, ?int $usuario, int $salidaId, array $d, array $opciones = []): string
    {
        return DB::transaction(function () use ($sucursal, $usuario, $salidaId, $d, $opciones) {
            $prep = self::preparar($sucursal, $salidaId, $d['pasajeros'] ?? [], $d['desde'] ?? null, $d['hasta'] ?? null);
            $minutos = max(5, (int) ($opciones['minutos'] ?? self::config($sucursal)->reserva_min));
            // Nunca después de la hora de salida
            $hasta = min(now()->addMinutes($minutos), now()->parse($prep['salida']->salida));
            if ($hasta->lte(now())) {
                throw new \RuntimeException('La salida ya es: no se puede reservar, véndelo.');
            }
            $grupo = strtoupper(Str::random(10));
            self::guardar($prep, 'RESERVADO', ['grupo' => $grupo, 'reservado_hasta' => $hasta, 'origen_venta' => $opciones['origen'] ?? 'CAJA',
                'web_usuario_id' => $opciones['web_usuario_id'] ?? null, 'medio_web' => $opciones['medio_web'] ?? null,
                'operacion_web' => $opciones['operacion_web'] ?? null], $usuario);

            return $grupo;
        });
    }

    /** Los pasajes de una reserva, aún vigentes (bloqueados para cobrarlos o liberarlos) */
    private static function reserva(int $sucursal, string $grupo): array
    {
        $pasajes = DB::table('tra_pasajes as p')->join('tra_salidas as s', 's.id', '=', 'p.salida_id')
            ->where('s.id_empresa_negocio', $sucursal)->where('p.grupo', $grupo)->where('p.estado', 'RESERVADO')
            ->lockForUpdate()->get(['p.*'])->all();
        if (! $pasajes) {
            throw new \RuntimeException('Esa reserva ya no existe (se cobró o se liberó).');
        }

        return $pasajes;
    }

    /**
     * Cobra una reserva: emite el comprobante y los pasajes quedan vendidos. Si la reserva venció pero el asiento sigue
     * libre, se cobra igual; si alguien ya lo tomó, avisa.
     */
    public static function cobrarReserva(User $user, string $grupo, array $d): int
    {
        return DB::transaction(function () use ($user, $grupo, $d) {
            $suc = (int) $user->id_empresa_negocio;
            $pasajes = self::reserva($suc, $grupo);
            $salida = DB::table('tra_salidas')->where('id', $pasajes[0]->salida_id)->lockForUpdate()->first();
            if ($salida->estado !== 'PROGRAMADA') {
                throw new \RuntimeException('Esa salida ya no vende pasajes ('.mb_strtolower($salida->estado).').');
            }
            foreach ($pasajes as $p) {
                $choca = collect(self::ocupados((int) $p->salida_id, (int) $p->tramo_desde, (int) $p->tramo_hasta))
                    ->first(fn ($o) => (string) $o->asiento === (string) $p->asiento && (int) $o->id !== (int) $p->id);
                if ($choca) {
                    throw new \RuntimeException("La reserva venció y el asiento {$p->asiento} ya lo tomó otra persona.");
                }
            }
            $ruta = DB::table('tra_rutas')->where('id', $salida->ruta_id)->first();
            $unidad = DB::table('tra_unidades')->where('id', $salida->unidad_id)->first();
            $cabId = self::emitir($user, $salida, $ruta, $unidad, $pasajes, $d);
            DB::table('tra_pasajes')->whereIn('id', array_column($pasajes, 'id'))
                ->update(['estado' => 'VENDIDO', 'IdCpe_cabecera' => $cabId, 'reservado_hasta' => null, 'updated_at' => now()]);

            return $cabId;
        });
    }

    /** Libera una reserva (el cliente ya no viaja o no pagó) */
    public static function liberarReserva(int $sucursal, string $grupo): int
    {
        return DB::transaction(function () use ($sucursal, $grupo) {
            $pasajes = self::reserva($sucursal, $grupo);
            DB::table('tra_pasajes')->whereIn('id', array_column($pasajes, 'id'))->update(['estado' => 'ANULADO', 'updated_at' => now()]);

            return count($pasajes);
        });
    }

    /**
     * Reservas por internet que esperan que la caja confirme el pago (vigentes).
     *
     * @return Collection<int, object>
     */
    public static function reservasWeb(int $sucursal)
    {
        return DB::table('tra_pasajes as p')->join('tra_salidas as s', 's.id', '=', 'p.salida_id')->join('tra_rutas as r', 'r.id', '=', 's.ruta_id')
            ->where('s.id_empresa_negocio', $sucursal)->where('p.estado', 'RESERVADO')->where('p.origen_venta', 'WEB')->where('p.reservado_hasta', '>', now())
            ->groupBy('p.grupo', 's.id', 's.salida', 'r.origen', 'r.destino')
            ->selectRaw('p.grupo, s.id AS salida_id, s.salida, r.origen, r.destino, COUNT(*) AS pasajes, SUM(p.precio) AS total, MIN(p.nombre) AS nombre,
                MIN(p.telefono) AS telefono, MIN(p.medio_web) AS medio, MIN(p.operacion_web) AS operacion, MIN(p.reservado_hasta) AS vence, MIN(p.created_at) AS creada')
            ->orderBy('creada')->get();
    }

    // ------------------------------------------------------------------ cambios y embarque

    /**
     * Cambia un pasaje de asiento, o de salida (otra fecha u hora de la misma ruta), sin anular su comprobante.
     *
     * @return string qué se hizo
     */
    public static function cambiar(User $user, int $pasajeId, int $salidaNueva, string $asientoNuevo): string
    {
        return DB::transaction(function () use ($user, $pasajeId, $salidaNueva, $asientoNuevo) {
            $suc = (int) $user->id_empresa_negocio;
            $p = DB::table('tra_pasajes as p')->join('tra_salidas as s', 's.id', '=', 'p.salida_id')->where('p.id', $pasajeId)
                ->where('s.id_empresa_negocio', $suc)->whereIn('p.estado', ['VENDIDO', 'RESERVADO'])->lockForUpdate()->first(['p.*', 's.ruta_id', 's.salida']);
            if (! $p) {
                throw new \RuntimeException('Ese pasaje ya no existe.');
            }
            if ($p->embarcado_at) {
                throw new \RuntimeException('Ese pasajero ya subió: no se puede cambiar.');
            }
            $destino = DB::table('tra_salidas')->where('id', $salidaNueva)->where('id_empresa_negocio', $suc)->lockForUpdate()->first();
            if (! $destino || $destino->estado !== 'PROGRAMADA') {
                throw new \RuntimeException('Esa salida no vende pasajes.');
            }
            if ((int) $destino->ruta_id !== (int) $p->ruta_id) {
                throw new \RuntimeException('Solo se cambia a una salida de la misma ruta.');
            }
            $unidad = DB::table('tra_unidades')->where('id', $destino->unidad_id)->first();
            $asientos = self::asientosDe(self::plano($unidad));
            $n = strtoupper(trim($asientoNuevo));
            if (! isset($asientos[$n])) {
                throw new \RuntimeException("El asiento {$n} no existe en esa unidad.");
            }
            $choca = collect(self::ocupados($salidaNueva, (int) $p->tramo_desde, (int) $p->tramo_hasta))
                ->first(fn ($o) => (string) $o->asiento === $n && (int) $o->id !== (int) $p->id);
            if ($choca) {
                throw new \RuntimeException("El asiento {$n} ya está ocupado en esa salida.");
            }
            $a = $asientos[$n];
            DB::table('tra_pasajes')->where('id', $p->id)->update(['salida_id' => $salidaNueva, 'asiento' => $a['n'], 'piso' => $a['piso'],
                'categoria' => $a['cat'], 'posicion' => $a['pos'], 'updated_at' => now()]);
            $texto = "Cambió el pasaje de {$p->nombre}: asiento {$p->asiento} del ".now()->parse($p->salida)->format('d/m H:i')
                ." → asiento {$a['n']} del ".now()->parse($destino->salida)->format('d/m H:i');
            Bitacora::registrar('venta.pasaje_cambio', $texto, null, ['referencia' => ['pasaje', $p->id]]);

            return $texto;
        });
    }

    /**
     * Embarque: lee el código del QR del boleto (o lo escrito) y marca que el pasajero subió.
     *
     * @return array{ok: bool, mensaje: string, pasajero?: array<string, mixed>}
     */
    public static function embarcar(User $user, string $leido, ?int $salidaId = null): array
    {
        $codigo = self::codigoDe($leido);
        $p = $codigo ? DB::table('tra_pasajes as p')->join('tra_salidas as s', 's.id', '=', 'p.salida_id')
            ->leftJoin('cpe_cabecera as c', 'c.IdCpe_cabecera', '=', 'p.IdCpe_cabecera')
            ->where('s.id_empresa_negocio', $user->id_empresa_negocio)->where('p.codigo', $codigo)
            ->first(['p.*', 's.salida', 's.estado as salida_estado', 'c.ccabaj']) : null;
        if (! $p) {
            return ['ok' => false, 'mensaje' => 'Ese boleto no existe en esta empresa.'];
        }
        $pasajero = ['nombre' => $p->nombre, 'doc' => $p->doc, 'asiento' => $p->asiento, 'salida' => now()->parse($p->salida)->format('d/m H:i')];
        if ($p->estado !== 'VENDIDO' || $p->ccabaj) {
            return ['ok' => false, 'mensaje' => $p->estado === 'RESERVADO' ? 'Es una reserva sin pagar: que pase por caja.' : 'Ese pasaje fue anulado.', 'pasajero' => $pasajero];
        }
        if ($salidaId && (int) $p->salida_id !== $salidaId) {
            return ['ok' => false, 'mensaje' => 'Ese pasaje es de otra salida ('.$pasajero['salida'].').', 'pasajero' => $pasajero];
        }
        if ($p->embarcado_at) {
            return ['ok' => false, 'mensaje' => 'Ya subió a las '.now()->parse($p->embarcado_at)->format('H:i').'.', 'pasajero' => $pasajero];
        }
        DB::table('tra_pasajes')->where('id', $p->id)->update(['embarcado_at' => now(), 'embarcado_por' => $user->IdUsuario]);

        return ['ok' => true, 'mensaje' => 'Puede subir: asiento '.$p->asiento.'.', 'pasajero' => $pasajero];
    }

    /** El código del pasaje en lo leído: el código solo o el que viene en el enlace del QR */
    public static function codigoDe(string $leido): ?string
    {
        return preg_match('/([A-Z0-9]{10})\s*$/i', trim($leido), $m) ? strtoupper($m[1]) : null;
    }

    // ------------------------------------------------------------------ plano y boleto

    /**
     * Lo tomado en una salida, para dibujar el plano: cada pasaje con su tramo, si es reserva y quién es.
     *
     * @return list<array<string, mixed>>
     */
    public static function paraPlano(int $salidaId): array
    {
        return array_map(fn ($p) => ['id' => (int) $p->id, 'asiento' => (string) $p->asiento, 'nombre' => $p->nombre, 'doc' => $p->doc,
            'telefono' => $p->telefono, 'estado' => $p->estado, 'grupo' => $p->grupo, 'desde' => (int) $p->tramo_desde, 'hasta' => (int) $p->tramo_hasta,
            'origen' => $p->origen_venta, 'vence' => $p->reservado_hasta ? now()->parse($p->reservado_hasta)->format('d/m H:i') : null,
            'subio' => (bool) $p->embarcado_at, 'comprobante' => $p->serdoc ? $p->serdoc.'-'.str_pad((string) $p->numdoc, 8, '0', STR_PAD_LEFT) : null], self::activos($salidaId));
    }

    /**
     * Los pasajes de una venta o reserva (su grupo) con todo lo que va en el boleto.
     *
     * @return list<object>
     */
    public static function boletos(int $sucursal, string $grupo): array
    {
        $pasajes = DB::table('tra_pasajes as p')->join('tra_salidas as s', 's.id', '=', 'p.salida_id')->join('tra_rutas as r', 'r.id', '=', 's.ruta_id')
            ->join('tra_unidades as u', 'u.id', '=', 's.unidad_id')->leftJoin('cpe_cabecera as c', 'c.IdCpe_cabecera', '=', 'p.IdCpe_cabecera')
            ->where('s.id_empresa_negocio', $sucursal)->where('p.grupo', $grupo)->whereIn('p.estado', ['VENDIDO', 'RESERVADO'])->orderBy('p.id')
            ->get(['p.*', 's.salida', 's.conductor', 'r.origen', 'r.destino', 'r.paradas', 'u.nombre as unidad', 'u.placa', 'u.tipo', 'c.serdoc', 'c.numdoc', 'c.ccabaj'])->all();
        foreach ($pasajes as $p) {
            $p->tramo = self::textoTramo($p, (int) $p->tramo_desde, (int) $p->tramo_hasta);
            $p->pagado = $p->estado === 'VENDIDO' && ! $p->ccabaj;
            $p->qr = 'data:image/svg+xml;base64,'.base64_encode(Carta::qr($p->codigo, 180));
        }

        return $pasajes;
    }

    /** El boleto en PDF (una hoja por pasajero) o null si ese grupo no existe */
    public static function boletoPdf(int $sucursal, string $grupo): ?string
    {
        $pasajes = self::boletos($sucursal, $grupo);
        if (! $pasajes) {
            return null;
        }
        $negocio = DB::table('empresa_negocios')->where('id_empresa_negocio', $sucursal)->first();
        $opciones = new Options;
        $opciones->set('defaultFont', 'DejaVu Sans');
        $opciones->set('isRemoteEnabled', false);
        $pdf = new Dompdf($opciones);
        $pdf->loadHtml(view('empresas.transportes.boleto', ['pasajes' => $pasajes, 'negocio' => $negocio,
            'empresa' => DB::table('empresa')->where('IdEmpresa', $negocio->IdEmpresa ?? null)->first()])->render(), 'UTF-8');
        $pdf->setPaper('A5');
        $pdf->render();

        return $pdf->output();
    }

    // ------------------------------------------------------------------ manifiesto y reporte

    /**
     * Manifiesto de pasajeros de una salida (lista para presentar antes de partir): solo los vendidos.
     *
     * @return list<object>
     */
    public static function manifiesto(int $salidaId): array
    {
        return collect(self::activos($salidaId))->where('estado', 'VENDIDO')
            ->sortBy(fn ($p) => [(int) $p->piso, is_numeric($p->asiento) ? (int) $p->asiento : PHP_INT_MAX, (string) $p->asiento, (int) $p->tramo_desde])
            ->values()->all();
    }

    /**
     * Lo cobrado, los gastos y la ganancia de cada salida en un rango de fechas.
     *
     * @return Collection<int, object>
     */
    public static function reporte(int $sucursal, string $desde, string $hasta)
    {
        $cobrado = DB::table('tra_pasajes as p')->leftJoin('cpe_cabecera as c', 'c.IdCpe_cabecera', '=', 'p.IdCpe_cabecera')
            ->where('p.estado', 'VENDIDO')->whereNull('c.ccabaj')->groupBy('p.salida_id')
            ->selectRaw('p.salida_id, COUNT(*) AS vendidos, SUM(p.precio) AS cobrado, SUM(p.embarcado_at IS NOT NULL) AS subieron');
        $gastos = DB::table('tra_gastos')->groupBy('salida_id')->selectRaw('salida_id, SUM(monto) AS gastos');

        return DB::table('tra_salidas as s')->join('tra_rutas as r', 'r.id', '=', 's.ruta_id')->join('tra_unidades as u', 'u.id', '=', 's.unidad_id')
            ->leftJoinSub($cobrado, 'v', 'v.salida_id', '=', 's.id')->leftJoinSub($gastos, 'g', 'g.salida_id', '=', 's.id')
            ->where('s.id_empresa_negocio', $sucursal)->where('s.estado', '!=', 'CANCELADA')
            ->whereBetween('s.salida', [$desde.' 00:00:00', $hasta.' 23:59:59'])->orderBy('s.salida')
            ->get(['s.id', 's.salida', 's.estado', 'r.origen', 'r.destino', 'u.nombre as unidad', 'u.asientos',
                DB::raw('COALESCE(v.vendidos, 0) AS vendidos'), DB::raw('COALESCE(v.cobrado, 0) AS cobrado'), DB::raw('COALESCE(v.subieron, 0) AS subieron'),
                DB::raw('COALESCE(g.gastos, 0) AS gastos')]);
    }
}
