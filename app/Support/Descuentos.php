<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Descuentos y cortesías del cobro (restaurante: cobrar mesa y PV Restaurante). Se activan por sucursal.
 * - Descuento a la cuenta, en soles o en %: el cajero puede hasta empresa_negocios.descuento_max_cajero; más que eso,
 *   el administrador (o la clave de un administrador). Se reparte entre las líneas cobradas: ante SUNAT cada línea va
 *   con su valor ya descontado y los totales cuadran al céntimo.
 * - Cortesía: la línea va gratis. Ante SUNAT es una operación gratuita (13 gravada / 21 exonerada) con su valor referencial.
 */
class Descuentos
{
    public const MOTIVO_CORTESIA = 'CORTESÍA DE LA CASA';

    /** @return object{descuentos: bool, cortesias: bool, max: float} */
    public static function config(int $sucursal): object
    {
        $n = DB::table('empresa_negocios')->where('id_empresa_negocio', $sucursal)->first();

        return (object) [
            'descuentos' => (bool) ($n->permite_descuentos ?? false),
            'cortesias' => (bool) ($n->permite_cortesias ?? false),
            'max' => (float) ($n->descuento_max_cajero ?? 10),
        ];
    }

    /** Afectación de una cortesía según la de la venta: gravada → 13 (retiro), exonerada → 21, inafecta → 31 */
    public static function afectacionGratuita(string $afectacion): string
    {
        return ['10' => '13', '20' => '21', '30' => '31'][$afectacion] ?? '21';
    }

    /**
     * Revisa los permisos y reparte el descuento entre las líneas que se cobran (las cortesías no llevan descuento).
     * A cada línea descontada le pone 'precio_lista', 'descuento' (con IGV) e 'importe' (lo que se cobra de esa línea).
     *
     * @param  array  $datos  descuento_tipo (S|P), descuento_valor, descuento_motivo, clave_admin
     * @return array{0: array, 1: array<string, mixed>} [líneas, columnas para cpe_cabecera]
     */
    public static function aplicar(User $user, array $lineas, array $datos): array
    {
        $cortesias = collect($lineas)->filter(fn ($l) => ! empty($l['cortesia']));
        $tipo = in_array($datos['descuento_tipo'] ?? null, ['S', 'P'], true) ? $datos['descuento_tipo'] : null;
        $valor = round((float) ($datos['descuento_valor'] ?? 0), 2);
        if ($cortesias->isEmpty() && (! $tipo || $valor <= 0)) {
            return [$lineas, []];
        }
        $config = self::config((int) $user->id_empresa_negocio);

        if ($cortesias->isNotEmpty() && ! $config->cortesias) {
            throw new \RuntimeException('Esta sucursal no tiene activadas las cortesías.');
        }
        foreach ($lineas as &$l) {
            if (! empty($l['cortesia'])) {
                $l['motivo'] = mb_strtoupper(mb_substr(trim((string) ($l['motivo'] ?? '')), 0, 80)) ?: self::MOTIVO_CORTESIA;
            }
        }
        unset($l);

        if (! $tipo || $valor <= 0) {
            return [$lineas, []];
        }
        if (! $config->descuentos) {
            throw new \RuntimeException('Esta sucursal no tiene activados los descuentos.');
        }
        $cobradas = array_keys(array_filter($lineas, fn ($l) => empty($l['cortesia'])));
        $totalDe = fn ($l) => isset($l['importe']) ? round((float) $l['importe'], 2) : round($l['cantidad'] * $l['precio'], 2);
        $bruto = round(array_sum(array_map(fn ($k) => $totalDe($lineas[$k]), $cobradas)), 2);
        if ($tipo === 'P' && $valor >= 100) {
            throw new \RuntimeException('El descuento no puede ser del 100%: para no cobrar un producto márcalo como cortesía.');
        }
        $monto = $tipo === 'P' ? round($bruto * $valor / 100, 2) : $valor;
        if ($monto >= $bruto) {
            throw new \RuntimeException('El descuento (S/ '.number_format($monto, 2).') no puede ser igual o mayor que la cuenta (S/ '.number_format($bruto, 2).').');
        }
        $porcentaje = $bruto > 0 ? $monto / $bruto * 100 : 0;

        // Más del máximo del cajero: solo el administrador, o con la clave de un administrador
        $autoriza = $user->esAdmin() ? (int) $user->IdUsuario : null;
        if (! $autoriza && $porcentaje > $config->max + 0.001) {
            $autoriza = self::claveAdministrador($user, (string) ($datos['clave_admin'] ?? ''), $porcentaje, $config->max);
        }

        // Reparto proporcional al total de cada línea; el redondeo lo absorbe la línea más grande
        $mayor = collect($cobradas)->sortByDesc(fn ($k) => $totalDe($lineas[$k]))->first();
        $repartido = 0.0;
        foreach ($cobradas as $k) {
            if ($k === $mayor) {
                continue;
            }
            $parte = round($monto * $totalDe($lineas[$k]) / $bruto, 2);
            $repartido += $parte;
            $lineas[$k] = self::descontar($lineas[$k], $parte, $totalDe($lineas[$k]));
        }
        $lineas[$mayor] = self::descontar($lineas[$mayor], round($monto - $repartido, 2), $totalDe($lineas[$mayor]));

        return [$lineas, [
            'descuento' => $monto, 'descuento_tipo' => $tipo, 'descuento_valor' => $valor,
            'descuento_motivo' => mb_strtoupper(mb_substr(trim((string) ($datos['descuento_motivo'] ?? '')), 0, 80)) ?: null,
            'IdUsuario_descuento' => $autoriza,
        ]];
    }

    private static function descontar(array $l, float $parte, float $total): array
    {
        $neto = round($total - $parte, 2);
        $l['precio_lista'] = $l['precio_lista'] ?? $l['precio'];
        $l['descuento'] = $parte;
        $l['importe'] = $neto;
        $l['precio'] = round($neto / (float) $l['cantidad'], 2);

        return $l;
    }

    /** Clave de un administrador de la empresa (5 intentos por minuto). Devuelve quién autorizó */
    private static function claveAdministrador(User $user, string $clave, float $porcentaje, float $max): int
    {
        $pedido = 'El descuento es de '.number_format($porcentaje, 1).'% y el máximo para caja es '.rtrim(rtrim(number_format($max, 2), '0'), '.').'%';
        if ($clave === '') {
            throw new \RuntimeException($pedido.': escribe la clave de un administrador para autorizarlo.');
        }
        $llave = 'clave-admin-descuento:'.$user->IdUsuario;
        if (RateLimiter::tooManyAttempts($llave, 5)) {
            throw new \RuntimeException('Demasiados intentos con la clave del administrador. Espera un minuto.');
        }
        $admins = User::where('IdEmpresa', $user->IdEmpresa)->where('estusu', 1)
            ->whereIn('IdUsuario', DB::table('role_user')->where('role_id', 2)->pluck('user_IdUsuario'))->get(['IdUsuario', 'password']);
        foreach ($admins as $admin) {
            if (Hash::check($clave, $admin->password)) {
                RateLimiter::clear($llave);

                return (int) $admin->IdUsuario;
            }
        }
        RateLimiter::hit($llave, 60);

        throw new \RuntimeException('La clave del administrador no es correcta.');
    }

    /**
     * Cómo se imprime una línea: el precio de lista y su total sin descuento (el descuento va aparte en el ticket),
     * o "CORTESÍA" en S/ 0.00.
     *
     * @return object{precio: float, total: float, cortesia: bool}
     */
    public static function linea(object $d): object
    {
        $cortesia = (bool) ($d->cortesia ?? false);
        $precio = (float) ($d->precio_lista ?? $d->cdepuni);

        return (object) [
            'precio' => $precio,
            'total' => $cortesia ? 0.0 : round((float) $d->cdevve + (float) ($d->descuento ?? 0), 2),
            'cortesia' => $cortesia,
        ];
    }
}
