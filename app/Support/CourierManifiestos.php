<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Manifiestos de carga: cada viaje (bus o camión, avión, lancha) con los envíos que lleva. En origen se cargan escaneando
 * la etiqueta de cada envío y al despachar todos quedan "en camino"; en destino se reciben escaneando bulto por bulto y el
 * sistema dice qué llegó completo, qué llegó incompleto y qué no llegó.
 */
class CourierManifiestos
{
    public const MEDIOS = [
        'TERRESTRE' => ['Terrestre (bus o camión)', 'fa-truck'],
        'AEREO' => ['Aéreo', 'fa-plane'],
        'FLUVIAL' => ['Fluvial (lancha o barco)', 'fa-ship'],
    ];

    public const ESTADOS = ['ABIERTO' => 'Cargando', 'EN_VIAJE' => 'En viaje', 'RECIBIDO' => 'Recibido', 'ANULADO' => 'Anulado'];

    /** @param  array<string, mixed>  $d  origen_id, destino_id, medio, transportista, vehiculo, conductor, salida, observacion */
    public static function crear(User $user, array $d): int
    {
        $ruc = (string) $user->IdEmpresa;
        if (! Courier::agenciasPropias($user)->pluck('age_id')->map(fn ($id) => (int) $id)->contains((int) $d['origen_id'])) {
            throw new \RuntimeException('El manifiesto sale de una agencia de tu sucursal.');
        }
        if (! empty($d['destino_id']) && (int) $d['destino_id'] === (int) $d['origen_id']) {
            throw new \RuntimeException('El destino debe ser otra agencia.');
        }

        return DB::transaction(fn () => DB::table('cou_manifiestos')->insertGetId([
            'numero' => (int) DB::table('cou_manifiestos')->where('IdEmpresa', $ruc)->lockForUpdate()->max('numero') + 1,
            'origen_id' => (int) $d['origen_id'], 'destino_id' => ($d['destino_id'] ?? null) ?: null,
            'medio' => array_key_exists($d['medio'] ?? '', self::MEDIOS) ? $d['medio'] : 'TERRESTRE',
            'transportista' => self::mayus($d['transportista'] ?? null), 'vehiculo' => self::mayus($d['vehiculo'] ?? null),
            'conductor' => self::mayus($d['conductor'] ?? null), 'salida' => ($d['salida'] ?? null) ?: null, 'observacion' => $d['observacion'] ?? null,
            'estado' => 'ABIERTO', 'IdUsuario' => $user->IdUsuario, 'IdEmpresa' => $ruc, 'creado' => now(),
        ]));
    }

    private static function mayus(?string $texto): ?string
    {
        return $texto !== null && trim($texto) !== '' ? mb_strtoupper(trim($texto)) : null;
    }

    /**
     * Lo que leyó el escáner o la cámara: el N° de guía solo, o el enlace del QR de la etiqueta (…/rastreo/CODIGO?b=2).
     *
     * @return array{codigo: string, bulto: ?int}|null
     */
    public static function leerCodigo(string $leido): ?array
    {
        $leido = strtoupper(trim($leido));
        if (! preg_match('~(?:^|/)([A-HJ-NP-Z2-9]{10})(?:[?&#]B=(\d{1,3}))?(?:$|[?&#/])~', $leido.'/', $m)) {
            return null;
        }

        return ['codigo' => $m[1], 'bulto' => isset($m[2]) && $m[2] !== '' ? (int) $m[2] : null];
    }

    private static function manifiesto(int $manId, string $ruc, ?string $estado = null): object
    {
        $m = DB::table('cou_manifiestos')->where('man_id', $manId)->where('IdEmpresa', $ruc)->lockForUpdate()->first();
        if (! $m) {
            throw new \RuntimeException('Manifiesto no encontrado.');
        }
        if ($estado && $m->estado !== $estado) {
            throw new \RuntimeException('El manifiesto N° '.$m->numero.' está '.mb_strtolower(self::ESTADOS[$m->estado]).'.');
        }

        return $m;
    }

    /** Sube un envío al manifiesto (escaneado o elegido): debe estar por despachar en la agencia de salida y ir a su destino */
    public static function cargar(User $user, int $manId, string $leido): array
    {
        $lectura = self::leerCodigo($leido);
        if (! $lectura) {
            throw new \RuntimeException('No es una guía de envío: '.mb_substr($leido, 0, 30));
        }

        return DB::transaction(function () use ($user, $manId, $lectura) {
            $m = self::manifiesto($manId, (string) $user->IdEmpresa, 'ABIERTO');
            $e = DB::table('cou_envios')->where('codigo', $lectura['codigo'])->lockForUpdate()->first();
            if (! $e) {
                throw new \RuntimeException("La guía {$lectura['codigo']} no existe.");
            }
            if ((int) $e->man_id === (int) $m->man_id) {
                return ['env_id' => $e->env_id, 'codigo' => $e->codigo, 'repetido' => true, 'mensaje' => "{$e->codigo} ya está en este manifiesto."];
            }
            if ($e->estado !== 'REGISTRADO' || (int) $e->origen_id !== (int) $m->origen_id) {
                throw new \RuntimeException("{$e->codigo}: no está por despachar en la agencia de salida de este manifiesto.");
            }
            if ($m->destino_id && (int) $e->destino_id !== (int) $m->destino_id) {
                throw new \RuntimeException("{$e->codigo} va a otra agencia (no a la de este manifiesto).");
            }
            if ($e->man_id) {
                throw new \RuntimeException("{$e->codigo} ya está en otro manifiesto.");
            }
            DB::table('cou_envios')->where('env_id', $e->env_id)->update(['man_id' => $m->man_id]);

            return ['env_id' => $e->env_id, 'codigo' => $e->codigo, 'repetido' => false, 'mensaje' => "{$e->codigo} cargado ({$e->piezas} pza)."];
        });
    }

    public static function quitar(User $user, int $manId, int $envId): void
    {
        DB::transaction(function () use ($user, $manId, $envId) {
            self::manifiesto($manId, (string) $user->IdEmpresa, 'ABIERTO');
            DB::table('cou_envios')->where('env_id', $envId)->where('man_id', $manId)->where('estado', 'REGISTRADO')->update(['man_id' => null]);
        });
    }

    /** Sale el viaje: todos sus envíos quedan en camino, con el transporte en su seguimiento */
    public static function despachar(User $user, int $manId): int
    {
        return DB::transaction(function () use ($user, $manId) {
            $m = self::manifiesto($manId, (string) $user->IdEmpresa, 'ABIERTO');
            $envios = DB::table('cou_envios')->where('man_id', $manId)->where('estado', 'REGISTRADO')->lockForUpdate()->get();
            if ($envios->isEmpty()) {
                throw new \RuntimeException('El manifiesto no tiene envíos cargados.');
            }
            $transporte = trim(self::MEDIOS[$m->medio][0].($m->transportista ? ' · '.$m->transportista : '').($m->vehiculo ? ' '.$m->vehiculo : ''));
            foreach ($envios as $e) {
                DB::table('cou_envios')->where('env_id', $e->env_id)->update(['estado' => 'EN_TRANSITO']);
                Courier::evento($e->env_id, 'EN_TRANSITO', "Salió de la agencia de origen · {$transporte}", (int) $m->origen_id, $user->IdUsuario);
            }
            DB::table('cou_manifiestos')->where('man_id', $manId)->update(['estado' => 'EN_VIAJE', 'salida' => $m->salida ?? now()]);

            return $envios->count();
        });
    }

    /**
     * Compara lo escaneado con las piezas de cada envío.
     *
     * @param  array<int, int>  $piezas  env_id => piezas
     * @param  array<int, int>  $escaneados  env_id => bultos leídos
     * @return array{completos: array<int, int>, incompletos: array<int, array{0: int, 1: int}>, faltantes: array<int, int>}
     */
    public static function resumenRecepcion(array $piezas, array $escaneados): array
    {
        $r = ['completos' => [], 'incompletos' => [], 'faltantes' => []];
        foreach ($piezas as $id => $total) {
            $leidos = min((int) ($escaneados[$id] ?? 0), (int) $total);
            match (true) {
                $leidos >= $total => $r['completos'][] = $id,
                $leidos > 0 => $r['incompletos'][$id] = [$leidos, (int) $total],
                default => $r['faltantes'][] = $id,
            };
        }

        return $r;
    }

    /**
     * Recibe en la agencia de destino los envíos del manifiesto que le tocan. Con escaneo: lo completo llega, lo incompleto
     * llega con su aviso y lo que no se escaneó sigue en camino (con un aviso). Sin escanear: llega todo.
     *
     * @param  array<int, int>|null  $escaneados  env_id => bultos leídos (null = recibir todo sin escanear)
     * @return array{completos: array<int, int>, incompletos: array<int, array{0: int, 1: int}>, faltantes: array<int, int>}
     */
    public static function recibir(User $user, int $manId, ?array $escaneados): array
    {
        return DB::transaction(function () use ($user, $manId, $escaneados) {
            $m = self::manifiesto($manId, (string) $user->IdEmpresa, 'EN_VIAJE');
            $suc = (int) $user->id_empresa_negocio;
            $envios = DB::table('cou_envios')->where('man_id', $manId)->where('estado', 'EN_TRANSITO')->lockForUpdate()->get()
                ->filter(fn ($e) => Courier::lado($e, $suc)['destino'])->keyBy('env_id');
            if ($envios->isEmpty()) {
                throw new \RuntimeException('En este manifiesto no hay envíos en camino hacia tu agencia.');
            }
            $piezas = $envios->map(fn ($e) => (int) $e->piezas)->all();
            $r = self::resumenRecepcion($piezas, $escaneados ?? $piezas);
            foreach ($r['completos'] as $id) {
                self::llego($envios[$id], (int) $envios[$id]->piezas, null, $user);
            }
            foreach ($r['incompletos'] as $id => [$leidos, $total]) {
                self::llego($envios[$id], $leidos, "Llegó incompleto: {$leidos} de {$total} bultos", $user);
            }
            foreach ($r['faltantes'] as $id) {
                Courier::evento($id, 'EN_TRANSITO', "No llegó con el manifiesto N° {$m->numero}", (int) $envios[$id]->destino_id, $user->IdUsuario);
            }
            if (! DB::table('cou_envios')->where('man_id', $manId)->where('estado', 'EN_TRANSITO')->exists()) {
                DB::table('cou_manifiestos')->where('man_id', $manId)->update(['estado' => 'RECIBIDO', 'llegada' => now()]);
            }

            return $r;
        });
    }

    private static function llego(object $e, int $bultos, ?string $aviso, User $user): void
    {
        DB::table('cou_envios')->where('env_id', $e->env_id)->update(['estado' => 'EN_DESTINO', 'bultos_recibidos' => $bultos]);
        Courier::evento($e->env_id, 'EN_DESTINO', $aviso ?? ($e->entrega === 'DOMICILIO' ? 'Llegó a la agencia de destino' : 'Llegó a la agencia de destino: listo para recoger'),
            (int) $e->destino_id, $user->IdUsuario);
    }

    public static function anular(User $user, int $manId): void
    {
        DB::transaction(function () use ($user, $manId) {
            self::manifiesto($manId, (string) $user->IdEmpresa, 'ABIERTO');
            DB::table('cou_envios')->where('man_id', $manId)->where('estado', 'REGISTRADO')->update(['man_id' => null]);
            DB::table('cou_manifiestos')->where('man_id', $manId)->update(['estado' => 'ANULADO']);
        });
    }
}
