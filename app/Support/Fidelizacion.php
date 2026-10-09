<?php

namespace App\Support;

use App\Models\Producto;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Fidelización por sucursal (empresa_negocios.fid_activo = Sí/No).
 *  - Reglas (fid_reglas): cada una da puntos por su cuenta (1 punto por cada S/ X, desde una compra mínima, en sus fechas).
 *    Los puntos de una compra son la suma de las reglas vigentes.
 *  - Premios (fid_premios): se canjean con los puntos; si son un producto, salen del almacén.
 *  - En caja, antes de cobrar, se ve cuántos puntos tendrá el cliente con esta compra y qué premios le alcanzan;
 *    el cajero reserva el premio y al emitir el comprobante se descuenta (y sale impreso en el comprobante).
 *  - Si la venta se anula o tiene nota de crédito total, se devuelven los puntos (lo ganado se resta, lo canjeado vuelve).
 */
class Fidelizacion
{
    /** Comprobantes que suman puntos (las notas de crédito/débito no) */
    private const TIPOS = ['01', '03', '13'];

    /** Clave de sesión del premio reservado en caja para la venta en curso */
    private const RESERVA = 'fid_canje';

    public static function activa(int $suc): bool
    {
        return (int) DB::table('empresa_negocios')->where('id_empresa_negocio', $suc)->value('fid_activo') === 1;
    }

    /** Reglas activas y vigentes hoy */
    public static function reglasVigentes(int $suc): Collection
    {
        $hoy = now()->toDateString();

        return DB::table('fid_reglas')->where('id_empresa_negocio', $suc)->where('activo', 1)->where('soles_por_punto', '>', 0)
            ->where(fn ($q) => $q->whereNull('desde')->orWhere('desde', '<=', $hoy))
            ->where(fn ($q) => $q->whereNull('hasta')->orWhere('hasta', '>=', $hoy))
            ->orderBy('regla_id')->get();
    }

    /**
     * Puntos que da un monto: suma de cada regla vigente que alcanza su compra mínima.
     *
     * @return array{puntos: int, detalle: array<int, string>}
     */
    public static function calcular(float $total, int $suc): array
    {
        if (! self::activa($suc)) {
            return ['puntos' => 0, 'detalle' => []];
        }
        $puntos = 0;
        $detalle = [];
        foreach (self::reglasVigentes($suc) as $r) {
            if ($total < (float) $r->compra_minima) {
                continue;
            }
            $p = (int) floor(round($total / (float) $r->soles_por_punto, 4));
            if ($p > 0) {
                $puntos += $p;
                $detalle[] = "{$r->nombre} +{$p}";
            }
        }

        return ['puntos' => $puntos, 'detalle' => $detalle];
    }

    /** Suma o resta puntos al cliente y deja el movimiento (con bloqueo, para que dos cajas no pisen el saldo) */
    private static function mover(int $clicod, int $suc, string $tipo, int $puntos, array $extra = []): int
    {
        return DB::transaction(function () use ($clicod, $suc, $tipo, $puntos, $extra) {
            $actual = (int) DB::table('cliente')->where('clicod', $clicod)->lockForUpdate()->value('puntos');
            $saldo = $actual + $puntos;
            if ($saldo < 0 && $tipo === 'CANJE') {
                throw new \RuntimeException("El cliente tiene {$actual} puntos; no le alcanza.");
            }
            DB::table('cliente')->where('clicod', $clicod)->update(['puntos' => $saldo]);
            DB::table('fid_movimientos')->insert($extra + [
                'clicod' => $clicod, 'tipo' => $tipo, 'puntos' => $puntos, 'saldo' => $saldo,
                'fecha' => now(), 'id_empresa_negocio' => $suc,
            ]);

            return $saldo;
        });
    }

    /** El documento puede acumular puntos (DNI de 8 o RUC de 11; no "venta al portador") */
    private static function docValido(?string $doc): bool
    {
        return (bool) preg_match('/^(\d{8}|\d{11})$/', (string) $doc) && $doc !== '00000000';
    }

    /**
     * Al emitir un comprobante: suma los puntos de la compra y, si en caja se reservó un premio para este cliente,
     * lo canjea (queda enlazado al comprobante para imprimirlo).
     */
    public static function acumular(int $cabId): void
    {
        $cab = DB::table('cpe_cabecera')->where('IdCpe_cabecera', $cabId)->first(['IdCpe_cabecera', 'tdocod', 'serdoc', 'numdoc', 'ccandi', 'clicod', 'ccaitv', 'id_empresa_negocio', 'IdUsuario']);
        if (! $cab || ! in_array($cab->tdocod, self::TIPOS, true) || ! $cab->clicod || ! self::docValido($cab->ccandi)) {
            return;
        }
        $suc = (int) $cab->id_empresa_negocio;
        $calc = self::calcular((float) $cab->ccaitv, $suc);
        if ($calc['puntos'] > 0 && ! DB::table('fid_movimientos')->where('IdCpe_cabecera', $cabId)->where('tipo', 'VENTA')->exists()) {
            self::mover((int) $cab->clicod, $suc, 'VENTA', $calc['puntos'], [
                'IdCpe_cabecera' => $cabId, 'IdUsuario' => $cab->IdUsuario,
                'detalle' => mb_substr('Compra '.$cab->serdoc.'-'.$cab->numdoc.($calc['detalle'] ? ' ('.implode(', ', $calc['detalle']).')' : ''), 0, 200),
            ]);
        }

        // Premio reservado en caja para este mismo cliente
        $reserva = self::reserva();
        if ($reserva && $reserva['doc'] === $cab->ccandi && self::activa($suc)) {
            self::olvidarReserva();
            $user = Auth::user();
            if ($user) {
                // Si el canje no procede (ej. otro cajero gastó los puntos), la venta igual se emite
                try {
                    DB::transaction(fn () => self::canjear($user, (int) $cab->clicod, (int) $reserva['premio_id'], $cabId));
                } catch (\RuntimeException $e) {
                    report($e);
                }
            }
        }
    }

    /** Venta anulada o con nota de crédito total: se resta lo ganado y se devuelve lo canjeado con ella (una sola vez) */
    public static function revertir(int $cabId): void
    {
        if (DB::table('fid_movimientos')->where('IdCpe_cabecera', $cabId)->where('tipo', 'ANULACION')->exists()) {
            return;
        }
        $movs = DB::table('fid_movimientos')->where('IdCpe_cabecera', $cabId)->whereIn('tipo', ['VENTA', 'CANJE'])->get();
        if ($movs->isEmpty()) {
            return;
        }
        $neto = -(int) $movs->sum('puntos');   // ganó +22 y canjeó -20 → se le quitan 2
        $m = $movs->first();
        self::mover((int) $m->clicod, (int) $m->id_empresa_negocio, 'ANULACION', $neto, [
            'IdCpe_cabecera' => $cabId, 'detalle' => 'Anulación del comprobante'.($movs->contains('tipo', 'CANJE') ? ' (vuelven los puntos del premio)' : ''),
        ]);
    }

    /** Premios que hoy se pueden canjear (activos y sin vencer) */
    public static function premiosVigentes(int $suc)
    {
        return DB::table('fid_premios')->where('id_empresa_negocio', $suc)->where('activo', 1)
            ->where(fn ($q) => $q->whereNull('vence')->orWhere('vence', '>=', now()->toDateString()))->orderBy('puntos');
    }

    /**
     * Canje de un premio. Si el premio es un producto del catálogo, sale del almacén predeterminado
     * (kardex con operación 08 "Premio"); los combos descuentan sus componentes.
     */
    public static function canjear(User $user, int $clicod, int $premioId, ?int $cabId = null): int
    {
        $suc = (int) $user->id_empresa_negocio;
        $premio = DB::table('fid_premios')->where('premio_id', $premioId)->where('id_empresa_negocio', $suc)->where('activo', 1)->first();
        if (! $premio) {
            throw new \RuntimeException('Premio no encontrado.');
        }
        if ($premio->vence && $premio->vence < now()->toDateString()) {
            throw new \RuntimeException('Ese premio venció el '.Carbon::parse($premio->vence)->format('d/m/Y').'.');
        }

        return DB::transaction(function () use ($user, $suc, $clicod, $premio, $cabId) {
            $saldo = self::mover($clicod, $suc, 'CANJE', -((int) $premio->puntos), [
                'premio_id' => $premio->premio_id, 'IdUsuario' => $user->IdUsuario, 'detalle' => 'Canje: '.$premio->nombre,
                'cantidad' => $premio->IdProducto ? $premio->cantidad : null, 'IdCpe_cabecera' => $cabId,
            ]);
            if ($premio->IdProducto && ($producto = Producto::where('IdProducto', $premio->IdProducto)->where('id_empresa_negocio', $suc)->first())) {
                $almacen = Kardex::almacenPredeterminado($suc);
                if (! $almacen) {
                    throw new \RuntimeException('La sucursal no tiene almacén predeterminado para sacar el premio.');
                }
                $cliente = DB::table('cliente')->where('clicod', $clicod)->value('clinom');
                Kardex::salidaPorVenta($producto, (int) $almacen->id_almacen, (float) $premio->cantidad, [
                    'cod_tip_ope' => '08', 'cliente' => $cliente, 'precio' => 0, 'fecha_mov' => now()->toDateString(),
                    'descripcion' => mb_substr('CANJE DE PUNTOS: '.$premio->nombre, 0, 150), 'numero' => 'CANJE', 'IdCpe_cabecera' => $cabId,
                ]);
            }

            return $saldo;
        });
    }

    /** Ajuste manual del administrador (+ o -) con su motivo */
    public static function ajustar(User $user, int $clicod, int $puntos, string $motivo): int
    {
        $actual = (int) DB::table('cliente')->where('clicod', $clicod)->value('puntos');
        if ($actual + $puntos < 0) {
            throw new \RuntimeException("El cliente tiene {$actual} puntos; no puede quedar en negativo.");
        }

        return self::mover($clicod, (int) $user->id_empresa_negocio, 'AJUSTE', $puntos, [
            'IdUsuario' => $user->IdUsuario, 'detalle' => mb_substr('Ajuste: '.trim($motivo), 0, 200),
        ]);
    }

    // ------------------------------------------------------------------ en caja, antes de cobrar

    /** @return array{doc: string, premio_id: int}|null */
    public static function reserva(): ?array
    {
        return app()->bound('session') && request()->hasSession() ? session(self::RESERVA) : null;
    }

    public static function olvidarReserva(): void
    {
        if (app()->bound('session') && request()->hasSession()) {
            session()->forget(self::RESERVA);
        }
    }

    /**
     * Lo que ve el cajero mientras arma la venta: puntos actuales, los que ganará con esta compra
     * y los premios que le alcanzan (con lo de hoy incluido). Se puede reservar uno para canjearlo al cobrar.
     */
    public static function previa(int $suc, string $doc, float $total): array
    {
        if (! self::activa($suc)) {
            return ['activo' => false];
        }
        $doc = trim($doc);
        $cliente = self::docValido($doc)
            ? DB::table('cliente')->where('clinum', $doc)->where('rucemp', DB::table('empresa_negocios')->where('id_empresa_negocio', $suc)->value('IdEmpresa'))->first(['clicod', 'clinom', 'puntos'])
            : null;
        $calc = self::calcular($total, $suc);
        $actual = (int) ($cliente->puntos ?? 0);
        $conCompra = $actual + $calc['puntos'];
        $reserva = self::reserva();
        $reservado = $reserva && $reserva['doc'] === $doc ? (int) $reserva['premio_id'] : null;
        $premios = self::premiosVigentes($suc)->get(['premio_id', 'nombre', 'puntos', 'vence', 'IdProducto']);
        $usados = $reservado ? (int) ($premios->firstWhere('premio_id', $reservado)->puntos ?? 0) : 0;

        return [
            'activo' => true, 'valido' => self::docValido($doc), 'cliente' => $cliente->clinom ?? null, 'nuevo' => self::docValido($doc) && ! $cliente,
            'actual' => $actual, 'ganara' => $calc['puntos'], 'detalle' => $calc['detalle'], 'con_compra' => $conCompra,
            'reservado' => $reservado, 'quedaria' => $conCompra - $usados,
            'premios' => $premios->map(fn ($p) => ['premio_id' => $p->premio_id, 'nombre' => $p->nombre, 'puntos' => (int) $p->puntos,
                'vence' => $p->vence ? Carbon::parse($p->vence)->format('d/m/Y') : null, 'producto' => (bool) $p->IdProducto,
                'alcanza' => $cliente && $conCompra >= (int) $p->puntos, 'falta' => max(0, (int) $p->puntos - $conCompra)])->values()->all(),
        ];
    }

    /** El cajero reserva (o quita) el premio que el cliente se lleva con esta compra */
    public static function reservar(int $suc, string $doc, ?int $premioId, float $total): array
    {
        if (! $premioId) {
            self::olvidarReserva();

            return self::previa($suc, $doc, $total);
        }
        $p = self::previa($suc, $doc, $total);
        $premio = collect($p['premios'] ?? [])->firstWhere('premio_id', $premioId);
        if (! $premio || ! $p['cliente']) {
            throw new \RuntimeException('Ese premio no está disponible para este cliente.');
        }
        if ($p['con_compra'] < $premio['puntos']) {
            throw new \RuntimeException("Con esta compra tendrá {$p['con_compra']} puntos; el premio cuesta {$premio['puntos']}.");
        }
        session()->put(self::RESERVA, ['doc' => trim($doc), 'premio_id' => $premioId]);

        return self::previa($suc, $doc, $total);
    }

    // ------------------------------------------------------------------ después de cobrar

    /**
     * Puntos del comprobante: los que tenía, los que ganó, el premio que canjeó y cómo quedó.
     *
     * @return array{cliente: string, antes: int, ganados: int, canjeados: int, premio: ?string, cantidad: ?float, saldo: int, mensaje: string}|null
     */
    public static function delComprobante(int $cabId): ?array
    {
        $movs = DB::table('fid_movimientos')->where('IdCpe_cabecera', $cabId)->whereIn('tipo', ['VENTA', 'CANJE'])->orderBy('mov_id')->get();
        if ($movs->isEmpty()) {
            return null;
        }
        $venta = $movs->firstWhere('tipo', 'VENTA');
        $canje = $movs->firstWhere('tipo', 'CANJE');
        $ganados = (int) ($venta->puntos ?? 0);
        $canjeados = (int) -($canje->puntos ?? 0);
        $saldo = (int) $movs->last()->saldo;
        $antes = $saldo - $ganados + $canjeados;
        $cliente = DB::table('cliente')->where('clicod', $movs->first()->clicod)->value('clinom');
        $premio = $canje ? str_replace('Canje: ', '', (string) $canje->detalle) : null;
        $mensaje = "Tenía {$antes} · ganó +{$ganados}".($premio ? " · canjeó {$premio} (−{$canjeados})" : '')." · ahora tiene {$saldo}.";

        return ['cliente' => $cliente, 'antes' => $antes, 'ganados' => $ganados, 'canjeados' => $canjeados, 'premio' => $premio,
            'cantidad' => $canje && $canje->cantidad ? (float) $canje->cantidad : null, 'saldo' => $saldo, 'mensaje' => $mensaje];
    }

    /**
     * Aviso al terminar la venta (todas las pantallas lo muestran).
     *
     * @return array{cliente: string, ganados: int, saldo: int, mensaje: string}|null
     */
    public static function resumen(int $cabId): ?array
    {
        $d = self::delComprobante($cabId);
        if (! $d) {
            return null;
        }
        $suc = (int) DB::table('cpe_cabecera')->where('IdCpe_cabecera', $cabId)->value('id_empresa_negocio');
        $siguiente = self::premiosVigentes($suc)->where('puntos', '>', $d['saldo'])->first(['nombre', 'puntos']);
        $alcanza = self::premiosVigentes($suc)->where('puntos', '<=', $d['saldo'])->orderByDesc('puntos')->first(['nombre']);
        $mensaje = $d['mensaje'];
        if ($alcanza) {
            $mensaje .= " Ya le alcanza para: {$alcanza->nombre}.";
        } elseif ($siguiente) {
            $mensaje .= ' Le faltan '.($siguiente->puntos - $d['saldo'])." para: {$siguiente->nombre}.";
        }

        return ['cliente' => $d['cliente'], 'ganados' => $d['ganados'], 'saldo' => $d['saldo'], 'mensaje' => $mensaje];
    }
}
