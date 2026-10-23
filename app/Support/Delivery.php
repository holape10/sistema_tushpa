<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Delivery con seguimiento: estados del reparto, enlace del cliente, enlace del motorizado y su recorrido (GPS).
 * Estados: PREPARANDO → LISTO (caja/cocina) → EN_LOCAL (el motorizado llegó a recoger) → EN_CAMINO → ENTREGADO.
 * El motorizado manda su ubicación desde el navegador de su celular mientras tiene un pedido en camino.
 */
class Delivery
{
    public const ESTADOS = [
        'PREPARANDO' => 'Preparando tu pedido',
        'LISTO' => 'Listo para salir',
        'EN_LOCAL' => 'El motorizado recoge tu pedido',
        'EN_CAMINO' => 'En camino',
        'ENTREGADO' => 'Entregado',
    ];

    /** Columna con la hora de cada paso */
    private const HORAS = ['LISTO' => 'entrega_listo', 'EN_LOCAL' => 'entrega_local', 'EN_CAMINO' => 'entrega_salida', 'ENTREGADO' => 'entrega_fin'];

    /** Lo que puede marcar el motorizado desde su celular */
    public const DEL_MOTORIZADO = ['EN_LOCAL', 'EN_CAMINO', 'ENTREGADO'];

    /** Cómo pagará el cliente al recibir (PAGADO = ya pagó antes, por ejemplo en caja o por transferencia) */
    public const MEDIOS_PAGO = [
        'EFECTIVO' => 'Efectivo', 'YAPE' => 'Yape', 'PLIN' => 'Plin', 'TARJETA' => 'Tarjeta (llevar POS)',
        'TRANSFERENCIA' => 'Transferencia', 'PAGADO' => 'Ya pagó',
    ];

    /** Pedidos que el motorizado aún tiene en mano (para saber si está libre) */
    public const EN_REPARTO = ['PREPARANDO', 'LISTO', 'EN_LOCAL', 'EN_CAMINO'];

    /** Código del producto que lleva el costo de envío en la cuenta (uno por sucursal, se crea solo) */
    public const PRODUCTO_ENVIO = 'DELIVERY-ENVIO';

    /** Un pedido delivery nuevo arranca "Preparando" y con su enlace de seguimiento */
    public static function iniciar(int $pedId): void
    {
        $p = DB::table('pedidos')->where('ped_id', $pedId)->first(['ped_tip', 'entrega_estado', 'entrega_token']);
        if (! $p || $p->ped_tip !== 'Delivery') {
            return;
        }
        DB::table('pedidos')->where('ped_id', $pedId)->update([
            'entrega_estado' => $p->entrega_estado ?: 'PREPARANDO',
            'entrega_token' => $p->entrega_token ?: self::nuevoToken('pedidos', 'entrega_token', 10),
        ]);
    }

    /** Cambia el estado y anota la hora del paso (y la de los pasos anteriores que se saltaron) */
    public static function cambiarEstado(int $pedId, string $estado): void
    {
        if (! isset(self::ESTADOS[$estado])) {
            throw new \RuntimeException('Estado no válido.');
        }
        $cambios = ['entrega_estado' => $estado];
        $orden = array_keys(self::ESTADOS);
        $p = DB::table('pedidos')->where('ped_id', $pedId)->first(array_values(self::HORAS));
        foreach (self::HORAS as $paso => $columna) {
            if (array_search($paso, $orden, true) <= array_search($estado, $orden, true) && empty($p->{$columna})) {
                $cambios[$columna] = now();
            }
        }
        DB::table('pedidos')->where('ped_id', $pedId)->update($cambios);
    }

    /**
     * Guarda la ubicación del motorizado (máximo una cada 8 s; las imprecisas de más de 300 m se descartan).
     */
    public static function registrarPosicion(int $motId, float $lat, float $lng, ?int $precision): bool
    {
        if (abs($lat) > 90 || abs($lng) > 180 || ($precision !== null && $precision > 300)) {
            return false;
        }
        $ultima = DB::table('motorizados')->where('mot_id', $motId)->value('posicion_at');
        if ($ultima && Carbon::parse($ultima)->diffInSeconds(now()) < 8) {
            return false;
        }
        DB::table('motorizado_posiciones')->insert(['mot_id' => $motId, 'lat' => $lat, 'lng' => $lng, 'precision' => $precision, 'created_at' => now()]);
        DB::table('motorizados')->where('mot_id', $motId)->update(['lat' => $lat, 'lng' => $lng, 'posicion_at' => now()]);

        return true;
    }

    /**
     * Recorrido del motorizado durante el reparto de un pedido (desde que salió hasta que entregó).
     *
     * @return array<int, array{0: float, 1: float}>
     */
    public static function recorrido(object $pedido): array
    {
        if (! $pedido->mot_id || ! $pedido->entrega_salida) {
            return [];
        }

        return DB::table('motorizado_posiciones')->where('mot_id', $pedido->mot_id)
            ->where('created_at', '>=', $pedido->entrega_salida)
            ->when($pedido->entrega_fin, fn ($q) => $q->where('created_at', '<=', $pedido->entrega_fin))
            ->orderBy('id')->limit(2000)->get(['lat', 'lng'])
            ->map(fn ($p) => [(float) $p->lat, (float) $p->lng])->all();
    }

    /** Enlace personal del motorizado (al crearlo de nuevo, el anterior deja de servir) */
    public static function tokenMotorizado(int $motId, bool $nuevo = false): string
    {
        $actual = DB::table('motorizados')->where('mot_id', $motId)->value('token');
        if ($actual && ! $nuevo) {
            return $actual;
        }
        $token = self::nuevoToken('motorizados', 'token', 32);
        DB::table('motorizados')->where('mot_id', $motId)->update(['token' => $token]);

        return $token;
    }

    /**
     * Coordenadas de lo que se pega: "-3.7491, -73.2538", un enlace de Google Maps (también el corto maps.app.goo.gl)
     * o la ubicación compartida por WhatsApp.
     *
     * @return array{lat: float, lng: float}|null
     */
    public static function coordenadas(string $texto): ?array
    {
        $texto = trim($texto);
        if (preg_match('~^https?://(maps\.app\.goo\.gl|goo\.gl/maps)/~i', $texto)) {
            try {
                $final = (string) Http::timeout(8)->withOptions(['allow_redirects' => ['track_redirects' => true]])->get($texto)
                    ->header('X-Guzzle-Redirect-History');
                $texto = $final !== '' ? collect(explode(',', $final))->last() : $texto;
            } catch (\Throwable $e) {
                return null;
            }
        }
        $texto = urldecode($texto);
        foreach (['~@(-?\d{1,2}\.\d+),\s*(-?\d{1,3}\.\d+)~', '~[?&](?:q|ll|query|destination)=(-?\d{1,2}\.\d+),\s*(-?\d{1,3}\.\d+)~',
            '~!3d(-?\d{1,2}\.\d+)!4d(-?\d{1,3}\.\d+)~', '~^\s*(-?\d{1,2}\.\d+)\s*,\s*(-?\d{1,3}\.\d+)\s*$~'] as $patron) {
            if (preg_match($patron, $texto, $m) && abs((float) $m[1]) <= 90 && abs((float) $m[2]) <= 180) {
                return ['lat' => (float) $m[1], 'lng' => (float) $m[2]];
            }
        }

        return null;
    }

    /**
     * Busca una dirección escrita ("Jr. Próspero 450", "Plaza de Armas") en OpenStreetMap, cerca del local y en su ciudad.
     *
     * @return array<int, array{lat: float, lng: float, nombre: string}>
     */
    public static function buscarDireccion(int $suc, string $texto): array
    {
        $texto = trim(preg_replace('/\s+/', ' ', $texto));
        if (mb_strlen($texto) < 3) {
            return [];
        }
        $n = DB::table('empresa_negocios')->where('id_empresa_negocio', $suc)->first(['distrito', 'provincia', 'local_lat', 'local_lng']);
        $ciudades = collect([$n->distrito ?? null, $n->provincia ?? null])->map(fn ($c) => trim((string) $c))->filter()->unique();
        // OpenStreetMap no entiende las abreviaturas: "Jr." → Jirón, "Av." → Avenida…
        $completa = preg_replace_callback('/\b(jr|av|ca|cl|psje|pje|prol|urb|mz|lt|aa\.?hh|ah)\b\.?/iu', fn ($m) => [
            'jr' => 'Jirón', 'av' => 'Avenida', 'ca' => 'Calle', 'cl' => 'Calle', 'psje' => 'Pasaje', 'pje' => 'Pasaje', 'prol' => 'Prolongación',
            'urb' => 'Urbanización', 'mz' => 'Manzana', 'lt' => 'Lote', 'aa.hh' => 'Asentamiento Humano', 'aahh' => 'Asentamiento Humano', 'ah' => 'Asentamiento Humano',
        ][mb_strtolower(rtrim($m[1], '.'))] ?? $m[0], $texto);
        // Muchas casas no tienen su número en el mapa: si no sale, se busca la calle
        $sinNumero = trim(preg_replace('/\s*(n[°º]?\.?\s*)?\d+[a-z]?\s*$/iu', '', $completa));
        $conCiudad = fn (string $t) => $ciudades->isNotEmpty() && ! $ciudades->contains(fn ($c) => str_contains(mb_strtolower($t), mb_strtolower($c)))
            ? $t.', '.$ciudades->implode(', ') : $t;
        // Si escribió "Calle" y en el mapa es "Jirón" (o al revés), se prueba solo con el nombre: "Putumayo"
        $nucleo = trim(preg_replace('/^(Jirón|Avenida|Calle|Pasaje|Prolongación|Carretera)\s+(de\s+)?/iu', '', $sinNumero));
        $intentos = array_values(array_unique(array_filter([$conCiudad($completa), $conCiudad($sinNumero), $conCiudad($nucleo), $completa, $sinNumero],
            fn ($t) => mb_strlen($t) >= 3)));

        // Solo lo que está cerca del local (40 km) o, sin local, en su ciudad: las calles se repiten en todo el país
        $cerca = function (array $r) use ($n, $ciudades) {
            if ($n && $n->local_lat !== null) {
                return self::distanciaKm((float) $n->local_lat, (float) $n->local_lng, (float) $r['lat'], (float) $r['lon']) <= 40;
            }

            return $ciudades->isEmpty() || $ciudades->contains(fn ($c) => str_contains(mb_strtolower((string) ($r['display_name'] ?? '')), mb_strtolower($c)));
        };
        foreach ($intentos as $q) {
            $lista = collect(self::nominatim('search', ['q' => $q, 'countrycodes' => 'pe', 'limit' => 8]))
                ->filter(fn ($r) => isset($r['lat'], $r['lon']) && $cerca($r))
                ->map(fn ($r) => ['lat' => (float) $r['lat'], 'lng' => (float) $r['lon'], 'nombre' => (string) ($r['display_name'] ?? '')])
                ->values()->all();
            if ($lista) {
                return $lista;
            }
        }

        return [];
    }

    /** La dirección aproximada de un punto del mapa (para llenarla sola al marcar la entrega) */
    public static function direccionDe(float $lat, float $lng): ?string
    {
        $r = self::nominatim('reverse', ['lat' => round($lat, 5), 'lon' => round($lng, 5), 'zoom' => 18, 'addressdetails' => 1]);
        $a = $r['address'] ?? [];
        $calle = trim(($a['road'] ?? $a['pedestrian'] ?? $a['footway'] ?? '').' '.($a['house_number'] ?? ''));
        $partes = array_filter([$calle, $a['neighbourhood'] ?? $a['suburb'] ?? $a['quarter'] ?? null, $a['city'] ?? $a['town'] ?? $a['village'] ?? null]);

        return $partes ? mb_substr(implode(', ', $partes), 0, 255) : null;
    }

    /**
     * Consulta a OpenStreetMap. Nominatim pide identificarse y pocas consultas: lo encontrado se guarda una semana
     * (lo que falla no se guarda, para volver a intentar).
     */
    private static function nominatim(string $tipo, array $parametros): array
    {
        $clave = 'nominatim:'.$tipo.':'.md5(json_encode($parametros));
        if (is_array($guardado = Cache::get($clave))) {
            return $guardado;
        }
        try {
            $r = Http::timeout(8)->withHeaders(['User-Agent' => 'TushpaSistema/1.0 ('.config('app.url').')', 'Accept-Language' => 'es'])
                ->get('https://nominatim.openstreetmap.org/'.$tipo, $parametros + ['format' => 'jsonv2']);
            $datos = $r->successful() ? (array) $r->json() : [];
        } catch (\Throwable $e) {
            return [];
        }
        if ($datos && ! isset($datos['error'])) {
            Cache::put($clave, $datos, now()->addDays(7));
        }

        return $datos;
    }

    /**
     * Motorizado del usuario con rol Motorizado: lo enlaza con el de su sucursal que tenga su mismo nombre o lo crea,
     * y mantiene nombre, celular y estado al día con el usuario.
     */
    public static function motorizadoDeUsuario(int $usuarioId): ?int
    {
        $u = DB::table('users as u')->leftJoin('empleado as e', 'e.emp_id', '=', 'u.emp_id')->where('u.IdUsuario', $usuarioId)
            ->first(['u.IdUsuario', 'u.apeusu', 'u.estusu', 'u.id_empresa_negocio', 'e.emp_cel', 'e.emp_tel']);
        if (! $u) {
            return null;
        }
        $datos = ['nombre' => mb_substr(mb_strtoupper((string) $u->apeusu), 0, 100), 'activo' => (int) $u->estusu === 1 ? 1 : 0,
            'id_empresa_negocio' => $u->id_empresa_negocio, 'IdUsuario' => $u->IdUsuario];
        if ($telefono = $u->emp_cel ?: $u->emp_tel) {
            $datos['telefono'] = mb_substr($telefono, 0, 20);
        }
        $motId = DB::table('motorizados')->where('IdUsuario', $u->IdUsuario)->value('mot_id')
            ?? DB::table('motorizados')->whereNull('IdUsuario')->where('id_empresa_negocio', $u->id_empresa_negocio)->where('nombre', $datos['nombre'])->value('mot_id');
        if ($motId) {
            DB::table('motorizados')->where('mot_id', $motId)->update($datos);

            return (int) $motId;
        }

        return (int) DB::table('motorizados')->insertGetId($datos);
    }

    /** Distancia en línea recta (km) entre dos puntos */
    public static function distanciaKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 6371 * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * Costo de envío según la distancia del local a la entrega y las tarifas de la sucursal ("hasta 2 km: S/ 3").
     * Sin ubicación del local, de la entrega o sin tarifas: no se calcula (costo null).
     *
     * @return array{km: ?float, costo: ?float, fuera: bool}
     */
    public static function costoEnvio(int $sucursal, ?float $lat, ?float $lng): array
    {
        $local = DB::table('empresa_negocios')->where('id_empresa_negocio', $sucursal)->first(['local_lat', 'local_lng']);
        if ($lat === null || $lng === null || ! $local || $local->local_lat === null) {
            return ['km' => null, 'costo' => null, 'fuera' => false];
        }
        $km = round(self::distanciaKm((float) $local->local_lat, (float) $local->local_lng, $lat, $lng), 2);
        $tarifas = self::tarifas($sucursal);
        if (! $tarifas) {
            return ['km' => $km, 'costo' => null, 'fuera' => false];
        }
        $tarifa = collect($tarifas)->first(fn ($t) => $km <= $t['hasta_km']);

        return ['km' => $km, 'costo' => $tarifa ? $tarifa['costo'] : null, 'fuera' => ! $tarifa];
    }

    /** @return array<int, array{hasta_km: float, costo: float}> */
    public static function tarifas(int $sucursal): array
    {
        return DB::table('delivery_tarifas')->where('id_empresa_negocio', $sucursal)->orderBy('hasta_km')->get()
            ->map(fn ($t) => ['hasta_km' => (float) $t->hasta_km, 'costo' => (float) $t->costo])->all();
    }

    /** Producto "COSTO DE ENVÍO" de la sucursal (sin categoría: no se imprime en cocina ni sale en la carta) */
    public static function productoEnvio(int $sucursal): int
    {
        $id = DB::table('productos')->where('id_empresa_negocio', $sucursal)->where('procod', self::PRODUCTO_ENVIO)->value('IdProducto');
        if ($id) {
            return (int) $id;
        }

        return (int) DB::table('productos')->insertGetId([
            'procod' => self::PRODUCTO_ENVIO, 'pronom' => 'COSTO DE ENVÍO', 'umecod' => 'ZZ', 'costo' => 0, 'propun' => 0, 'promocion' => 2,
            'proest' => 'Activo', 'id_empresa_negocio' => $sucursal,
            'IdEmpresa' => (string) DB::table('empresa_negocios')->where('id_empresa_negocio', $sucursal)->value('IdEmpresa'),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /**
     * Pone (o cambia, o quita con 0) el costo de envío como una línea de la cuenta del pedido y recalcula el total.
     * Si el pedido ya se cobró, o esa línea ya se cobró, no se toca.
     */
    public static function ponerEnvio(int $pedId, float $costo): void
    {
        $pedido = DB::table('pedidos')->where('ped_id', $pedId)->first(['id_empresa_negocio', 'IdEmpresa', 'ped_est']);
        if (! $pedido || $pedido->ped_est !== 'Aperturado') {
            return;
        }
        $producto = self::productoEnvio((int) $pedido->id_empresa_negocio);
        $linea = DB::table('pedidos_detalle')->where('ped_id', $pedId)->where('IdProducto', $producto)->where('estadoitem', '!=', 'Eliminado')->first();
        if ($linea && (float) $linea->item_facturado > 0) {
            return;
        }
        if ($costo <= 0) {
            if ($linea) {
                DB::table('pedidos_detalle')->where('ped_det_id', $linea->ped_det_id)->delete();
            }
        } elseif ($linea) {
            DB::table('pedidos_detalle')->where('ped_det_id', $linea->ped_det_id)->update(['ped_det_pre' => $costo, 'ped_det_can' => 1]);
        } else {
            DB::table('pedidos_detalle')->insert([
                'ped_id' => $pedId, 'IdProducto' => $producto, 'IdEmpresa' => $pedido->IdEmpresa, 'descripcion' => 'COSTO DE ENVÍO', 'detalle' => 'COSTO DE ENVÍO',
                'ped_det_can' => 1, 'ped_det_pre' => $costo, 'estadoitem' => 'Ingresado', 'impreso' => 'impreso', 'item_facturado' => 0, 'fecha_hora' => now(),
            ]);
        }
        $total = (float) DB::table('pedidos_detalle')->where('ped_id', $pedId)->where('estadoitem', '!=', 'Eliminado')->sum(DB::raw('ped_det_can * ped_det_pre'));
        DB::table('pedidos')->where('ped_id', $pedId)->update(['ped_tot' => $total, 'entrega_envio' => $costo > 0 ? $costo : null]);
    }

    /**
     * Motorizados para un pedido, el más conveniente primero: con señal reciente, libres (sin pedidos en mano) y más
     * cerca del local (ahí recogen el pedido; sin ubicación del local se mide a la entrega). Sin señal hace más de
     * 10 minutos van al final.
     *
     * @return array<int, array{id: int, nombre: string, libre: bool, en_mano: int, km: ?float, hace: ?int}>
     */
    public static function sugerirMotorizados(int $sucursal, object $pedido): array
    {
        $local = DB::table('empresa_negocios')->where('id_empresa_negocio', $sucursal)->first(['local_lat', 'local_lng']);
        $punto = $local && $local->local_lat !== null ? [(float) $local->local_lat, (float) $local->local_lng]
            : ($pedido->entrega_lat !== null ? [(float) $pedido->entrega_lat, (float) $pedido->entrega_lng] : null);
        $enMano = DB::table('pedidos')->where('id_empresa_negocio', $sucursal)->where('ped_tip', 'Delivery')->whereIn('entrega_estado', self::EN_REPARTO)
            ->whereNotIn('ped_est', ['Anulado', 'Unido'])->where('ped_id', '!=', $pedido->ped_id)->where('fecha_hora', '>=', now()->subDay())
            ->whereNotNull('mot_id')->groupBy('mot_id')->pluck(DB::raw('count(*)'), 'mot_id');

        return DB::table('motorizados')->where('id_empresa_negocio', $sucursal)->where('activo', 1)->get()
            ->map(function ($m) use ($punto, $enMano) {
                $hace = $m->posicion_at ? (int) Carbon::parse($m->posicion_at)->diffInSeconds(now()) : null;
                $conSenal = $hace !== null && $hace < 600 && $m->lat !== null;

                return ['id' => (int) $m->mot_id, 'nombre' => $m->nombre, 'en_mano' => (int) ($enMano[$m->mot_id] ?? 0),
                    'libre' => ! isset($enMano[$m->mot_id]), 'hace' => $hace,
                    'km' => $conSenal && $punto ? round(self::distanciaKm($punto[0], $punto[1], (float) $m->lat, (float) $m->lng), 2) : null];
            })
            ->sortBy(fn ($m) => [$m['km'] === null ? 1 : 0, $m['libre'] ? 0 : 1, $m['en_mano'], $m['km'] ?? 0])
            ->values()->all();
    }

    /** "EFECTIVO · paga con S/ 100.00 (vuelto S/ 54.50)" */
    public static function textoPago(object $pedido): string
    {
        if (! $pedido->entrega_pago) {
            return '';
        }
        $texto = mb_strtoupper(self::MEDIOS_PAGO[$pedido->entrega_pago] ?? $pedido->entrega_pago);
        if ($pedido->entrega_pago === 'EFECTIVO' && (float) $pedido->entrega_paga_con > 0) {
            $texto .= ' · paga con S/ '.number_format((float) $pedido->entrega_paga_con, 2);
            $vuelto = (float) $pedido->entrega_paga_con - (float) $pedido->ped_tot;
            if ($vuelto > 0) {
                $texto .= ' (vuelto S/ '.number_format($vuelto, 2).')';
            }
        }

        return $texto;
    }

    /**
     * Datos de entrega que salen en la comanda de un pedido delivery.
     *
     * @return array<int, string>
     */
    public static function lineasComanda(object $pedido): array
    {
        return array_values(array_filter([
            $pedido->ped_cli_nom && $pedido->ped_cli_nom !== 'DELIVERY' ? 'CLIENTE: '.$pedido->ped_cli_nom : null,
            $pedido->ped_dir ? 'DIRECCIÓN: '.$pedido->ped_dir : null,
            $pedido->entrega_ref ? 'REFERENCIA: '.$pedido->entrega_ref : null,
            $pedido->ped_tel ? 'WHATSAPP: '.$pedido->ped_tel : null,
            $pedido->entrega_pago ? 'PAGO: '.self::textoPago($pedido) : null,
            $pedido->ped_obs && $pedido->entrega_origen === 'CARTA' ? 'NOTA: '.$pedido->ped_obs : null,
        ]));
    }

    /** Mensaje de WhatsApp para el cliente con su enlace de seguimiento */
    public static function mensajeCliente(object $pedido, string $negocio, string $url): string
    {
        return 'Hola'.($pedido->ped_cli_nom && $pedido->ped_cli_nom !== 'DELIVERY' ? ' '.Str::title(mb_strtolower($pedido->ped_cli_nom)) : '')
            .", gracias por tu pedido en {$negocio}. Sigue tu delivery en tiempo real aquí: {$url}";
    }

    private static function nuevoToken(string $tabla, string $columna, int $largo): string
    {
        do {
            $token = $largo <= 12 ? strtoupper(Str::random($largo)) : Str::random($largo);
        } while (DB::table($tabla)->where($columna, $token)->exists());

        return $token;
    }
}
