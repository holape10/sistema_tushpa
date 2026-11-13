<?php

namespace App\Support\Sunat;

use App\Support\ConsultaPeru;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

/**
 * Conductores y vehículos de las guías (transporte privado): su lista por sucursal, que se llena sola con cada guía, y las
 * consultas a json.pe (licencia de conducir por DNI y datos del vehículo por placa) para no escribirlos a mano.
 * Sin token de json.pe (o si no responde) se usa lo guardado y el nombre por el DNI, como siempre.
 */
class TransporteGuias
{
    /** Para las pruebas: reemplaza la llamada a json.pe (recibe la ruta y los datos, devuelve el "data" o null) */
    public static ?\Closure $falso = null;

    public static function hay(): bool
    {
        return Schema::hasTable('gre_conductores') && Schema::hasTable('gre_vehiculos');
    }

    public static function placa(string $placa): string
    {
        return strtoupper(str_replace([' ', '-'], '', trim($placa)));
    }

    /** Guarda (o actualiza) el conductor y el vehículo que se usaron en una guía */
    public static function guardarDeGuia(int $sucursal, array $d): void
    {
        if (! self::hay() || ($d['modalidad'] ?? null) !== '02' || ! empty($d['vehiculo_m1l'])) {
            return;
        }
        $num = trim((string) ($d['cond_num'] ?? ''));
        if ($num !== '' && trim((string) ($d['cond_nombres'] ?? '')) !== '') {
            DB::table('gre_conductores')->updateOrInsert(['id_empresa_negocio' => $sucursal, 'num' => $num], [
                'tdicod' => $d['cond_tdicod'] ?? '1', 'nombres' => mb_strtoupper(mb_substr(trim($d['cond_nombres']), 0, 100)),
                'apellidos' => mb_strtoupper(mb_substr(trim((string) ($d['cond_apellidos'] ?? '')), 0, 100)) ?: null,
                'licencia' => strtoupper(mb_substr(trim((string) ($d['cond_licencia'] ?? '')), 0, 15)) ?: null, 'updated_at' => now(), 'created_at' => now(),
            ]);
        }
        $placa = self::placa((string) ($d['placa'] ?? ''));
        // Solo la placa (sin consultar a json.pe: esto va dentro de la transacción de la guía; la marca se trae al escribirla)
        if ($placa !== '') {
            DB::table('gre_vehiculos')->insertOrIgnore(['id_empresa_negocio' => $sucursal, 'placa' => $placa, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    /**
     * El conductor por su DNI: el guardado; si no hay o le falta la licencia, json.pe (licencia) y RENIEC (nombre).
     *
     * @return array{nombres: string, apellidos: string, licencia: ?string, categoria: ?string, vence: ?string}|null
     */
    public static function conductor(int $sucursal, string $dni): ?array
    {
        $guardado = self::hay() ? DB::table('gre_conductores')->where('id_empresa_negocio', $sucursal)->where('num', $dni)->first() : null;
        if ($guardado && $guardado->licencia) {
            return ['nombres' => $guardado->nombres, 'apellidos' => (string) $guardado->apellidos, 'licencia' => $guardado->licencia,
                'categoria' => $guardado->licencia_categoria, 'vence' => $guardado->licencia_vence];
        }
        $lic = preg_match('/^\d{8}$/', $dni) ? self::licencia($dni) : null;
        $nombre = $lic['nombre'] ?? ConsultaPeru::dni($dni)['nombre'] ?? ($guardado ? trim($guardado->nombres.' '.$guardado->apellidos) : null);
        if (! $nombre && ! $lic) {
            return null;
        }
        // "PEZO ULLOA, HELGA KATHERINE" o "HELGA KATHERINE PEZO ULLOA": los dos últimos son los apellidos
        $nombre = trim(str_replace(',', ' ', (string) $nombre));
        if (str_contains((string) ($lic['nombre'] ?? ''), ',')) {
            [$ape, $nom] = array_map('trim', explode(',', $lic['nombre'], 2));
            [$nombres, $apellidos] = [$nom, $ape];
        } else {
            $p = preg_split('/\s+/', $nombre) ?: [];
            $corte = count($p) >= 3 ? count($p) - 2 : (count($p) === 2 ? 1 : count($p));
            [$nombres, $apellidos] = [implode(' ', array_slice($p, 0, $corte)), implode(' ', array_slice($p, $corte))];
        }
        $datos = ['nombres' => mb_strtoupper($nombres), 'apellidos' => mb_strtoupper($apellidos), 'licencia' => $lic['numero'] ?? $guardado?->licencia,
            'categoria' => $lic['categoria'] ?? null, 'vence' => $lic['vence'] ?? null];
        // Lo que trajo json.pe queda guardado: la próxima vez no se vuelve a consultar
        if ($lic && self::hay()) {
            DB::table('gre_conductores')->updateOrInsert(['id_empresa_negocio' => $sucursal, 'num' => $dni], [
                'tdicod' => '1', 'nombres' => mb_substr($datos['nombres'], 0, 100), 'apellidos' => mb_substr($datos['apellidos'], 0, 100) ?: null,
                'licencia' => $datos['licencia'], 'licencia_categoria' => $datos['categoria'], 'licencia_vence' => $datos['vence'],
                'updated_at' => now(), 'created_at' => now(),
            ]);
        }

        return $datos;
    }

    /**
     * Datos del vehículo por su placa: los guardados; si no tiene marca, json.pe (se guardan).
     *
     * @return array{marca?: ?string, modelo?: ?string, color?: ?string}
     */
    public static function vehiculo(int $sucursal, string $placa): array
    {
        $placa = self::placa($placa);
        $guardado = self::hay() ? DB::table('gre_vehiculos')->where('id_empresa_negocio', $sucursal)->where('placa', $placa)->first() : null;
        if ($guardado && $guardado->marca) {
            return ['marca' => $guardado->marca, 'modelo' => $guardado->modelo, 'color' => $guardado->color];
        }
        $datos = self::datosPlaca($placa);
        if ($datos && self::hay()) {
            DB::table('gre_vehiculos')->updateOrInsert(['id_empresa_negocio' => $sucursal, 'placa' => $placa], $datos + ['updated_at' => now(), 'created_at' => now()]);
        }

        return $datos;
    }

    /** @return array{marca?: ?string, modelo?: ?string, color?: ?string} */
    private static function datosPlaca(string $placa): array
    {
        if (! preg_match('/^[A-Z0-9]{5,8}$/', $placa) || ! ($d = self::jsonpe('placa', ['placa' => $placa]))) {
            return [];
        }

        return array_filter(['marca' => mb_substr((string) ($d['marca'] ?? ''), 0, 60), 'modelo' => mb_substr((string) ($d['modelo'] ?? ''), 0, 60),
            'color' => mb_substr((string) ($d['color'] ?? ''), 0, 60)], fn ($v) => $v !== '');
    }

    /** @return array{nombre: ?string, numero: ?string, categoria: ?string, vence: ?string}|null */
    private static function licencia(string $dni): ?array
    {
        $d = self::jsonpe('licencia', ['dni' => $dni]);
        if (! $d) {
            return null;
        }
        // json.pe documenta "licencia" como objeto y también como lista: se toma la primera
        $l = $d['licencia'] ?? [];
        $l = isset($l[0]) && is_array($l[0]) ? $l[0] : (is_array($l) ? $l : []);
        $nombre = $d['nombre_completo'] ?? trim(($d['apellido_paterno'] ?? '').' '.($d['apellido_materno'] ?? '').', '.($d['nombres'] ?? ''), ' ,');
        $vence = null;
        if (! empty($l['fecha_vencimiento'])) {
            try {
                $vence = Carbon::createFromFormat('d/m/Y', $l['fecha_vencimiento'])->toDateString();
            } catch (\Throwable) {
            }
        }

        return ['nombre' => $nombre ?: null, 'numero' => isset($l['numero']) ? strtoupper(mb_substr($l['numero'], 0, 15)) : null,
            'categoria' => isset($l['categoria']) ? mb_substr($l['categoria'], 0, 20) : null, 'vence' => $vence];
    }

    /** POST a json.pe con el token del servidor; devuelve "data" o null */
    private static function jsonpe(string $ruta, array $datos): ?array
    {
        if (self::$falso) {
            return (self::$falso)($ruta, $datos);
        }
        $token = (string) config('services.jsonpe.token');
        if ($token === '') {
            return null;
        }
        try {
            $r = Http::timeout(12)->acceptJson()->withToken($token)->post(rtrim((string) config('services.jsonpe.url'), '/').'/'.$ruta, $datos);
            $d = $r->json('data');

            return $r->successful() && is_array($d) && $d ? $d : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
