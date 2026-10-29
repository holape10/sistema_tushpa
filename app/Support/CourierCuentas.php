<?php

namespace App\Support;

use App\Models\Turno;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Cuentas del courier:
 *  - clientes empresa con crédito: envían sin pagar (hasta su límite) y a fin de mes se les emite una sola factura al
 *    crédito por todos sus envíos (queda en Cuentas por cobrar);
 *  - liquidación con las agencias aliadas: lo que cobraron al entregar (nos lo deben) menos su comisión.
 */
class CourierCuentas
{
    public static function credito(string $ruc, ?int $clicod): ?object
    {
        return $clicod ? DB::table('cou_creditos')->where('IdEmpresa', $ruc)->where('clicod', $clicod)->where('activo', 1)->first() : null;
    }

    /** Envíos a crédito del cliente que aún no se facturan */
    public static function sinFacturar(int $clicod)
    {
        return DB::table('cou_envios')->where('rem_clicod', $clicod)->where('pago', 'CREDITO')->whereNull('IdCpe_cabecera')->where('estado', '!=', 'ANULADO');
    }

    /** El remitente debe tener crédito activo y no pasarse de su límite con este envío */
    public static function validarCredito(string $ruc, ?int $clicod, float $monto): void
    {
        $c = self::credito($ruc, $clicod);
        if (! $c) {
            throw new \RuntimeException('Este remitente no tiene crédito. Regístralo en Courier → Clientes con crédito o cobra el envío.');
        }
        $deuda = (float) self::sinFacturar($clicod)->sum('total');
        if ((float) $c->limite > 0 && $deuda + $monto > (float) $c->limite + 0.009) {
            throw new \RuntimeException('Se pasa de su límite de crédito: debe S/ '.number_format($deuda, 2).' de S/ '.number_format((float) $c->limite, 2).'.');
        }
    }

    /**
     * Una factura (o boleta, si no tiene RUC) al crédito por los envíos elegidos del cliente, que vence según su plazo.
     *
     * @param  array<int, int>  $envIds
     */
    public static function facturar(User $user, int $clicod, array $envIds): int
    {
        $ruc = (string) $user->IdEmpresa;
        $c = DB::table('cou_creditos')->where('IdEmpresa', $ruc)->where('clicod', $clicod)->first();
        $cli = DB::table('cliente')->where('clicod', $clicod)->first();
        if (! $c || ! $cli) {
            throw new \RuntimeException('Cliente de crédito no encontrado.');
        }

        return DB::transaction(function () use ($user, $c, $cli, $clicod, $envIds) {
            $turno = Turno::where('IdUsuario', $user->IdUsuario)->where('id_empresa_negocio', $user->id_empresa_negocio)->where('estado', 'ABIERTO')->lockForUpdate()->first();
            if (! $turno) {
                throw new \RuntimeException('Debes aperturar tu turno de caja antes de facturar.');
            }
            $envios = self::sinFacturar($clicod)->whereIn('env_id', $envIds)->orderBy('env_id')->lockForUpdate()->get();
            if ($envios->isEmpty()) {
                throw new \RuntimeException('Elige envíos que aún no estén facturados.');
            }
            $producto = Courier::productoFlete($user);
            $lineas = $envios->flatMap(fn ($e) => Courier::lineas($e, $producto, true))->values()->all();
            $doc = (string) $cli->clinum;
            $desde = substr((string) $envios->min('creado'), 0, 10);
            $hasta = substr((string) $envios->max('creado'), 0, 10);

            $cabId = Comprobante::emitir($user, $turno, [
                'tdocod' => strlen($doc) === 11 ? '01' : '03', 'estadopago' => Comprobante::creditoDe($user),
                'fecEmi' => now()->toDateString(), 'fecVen' => now()->addDays(max(1, (int) $c->dias))->toDateString(),
                'tdicod' => strlen($doc) === 11 ? '6' : '1', 'clinum' => $doc, 'clinom' => $cli->clinom, 'clidir' => $cli->clidir ?: '--',
                'observaciones' => mb_substr("ENVÍOS DEL {$desde} AL {$hasta} ({$envios->count()} GUÍAS)", 0, 100),
            ], $lineas, ['ped_tip' => 'COURIER', 'IdUsuario_ven' => $user->IdUsuario]);
            DB::table('cou_envios')->whereIn('env_id', $envios->pluck('env_id'))->update(['IdCpe_cabecera' => $cabId]);

            return $cabId;
        });
    }

    // ------------------------------------------------------------------ aliados

    /**
     * Por cada agencia aliada, lo entregado que aún no se liquida: cuánto cobró el aliado (envíos que pagaban al recoger),
     * su comisión sobre todo lo que entregó y el saldo (+ nos paga, - le pagamos).
     */
    public static function porLiquidarAliados(string $ruc): Collection
    {
        $aliados = DB::table('cou_agencias')->where('IdEmpresa', $ruc)->whereNull('id_empresa_negocio')->orderBy('ciudad')->get()->keyBy('age_id');
        $envios = DB::table('cou_envios')->whereIn('destino_id', $aliados->keys())->where('estado', 'ENTREGADO')->whereNull('liq_id')->get()->groupBy('destino_id');

        return $aliados->filter(fn ($a) => isset($envios[$a->age_id]))->map(function ($a) use ($envios) {
            $lista = $envios[$a->age_id];
            $cobrado = round((float) $lista->where('pago', 'DESTINO')->sum('total'), 2);
            $comision = round((float) $lista->sum('total') * (float) $a->comision_pct / 100, 2);

            return (object) ['agencia' => $a, 'envios' => $lista, 'cobrado' => $cobrado, 'comision' => $comision, 'saldo' => round($cobrado - $comision, 2)];
        })->values();
    }

    public static function liquidarAliado(User $user, int $ageId, ?string $observacion): int
    {
        $ruc = (string) $user->IdEmpresa;

        return DB::transaction(function () use ($user, $ruc, $ageId, $observacion) {
            $p = self::porLiquidarAliados($ruc)->first(fn ($x) => (int) $x->agencia->age_id === $ageId);
            if (! $p) {
                throw new \RuntimeException('Ese aliado no tiene envíos por liquidar.');
            }
            $liq = DB::table('cou_liquidaciones')->insertGetId(['age_id' => $ageId, 'envios' => $p->envios->count(), 'cobrado' => $p->cobrado,
                'comision' => $p->comision, 'saldo' => $p->saldo, 'observacion' => $observacion ? mb_substr($observacion, 0, 255) : null,
                'IdUsuario' => $user->IdUsuario, 'IdEmpresa' => $ruc, 'creado' => now()]);
            DB::table('cou_envios')->whereIn('env_id', $p->envios->pluck('env_id'))->whereNull('liq_id')->update(['liq_id' => $liq]);

            return $liq;
        });
    }
}
