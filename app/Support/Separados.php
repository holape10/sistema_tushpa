<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Separados (apartados): "me separas esta cocina, te dejo S/ 100 y vengo el sábado".
 * Es una venta por partes (al crédito, con el adelanto como primer abono) cuyo producto se queda en la tienda:
 *  - el stock sale al separar, así ninguna caja vende lo que ya tiene dueño;
 *  - el adelanto y cada abono son pagos de Cuentas por cobrar (recibo y, si es efectivo, a la caja del turno);
 *  - al terminar de pagar se ENTREGA; si el cliente no vuelve, se anula la venta como cualquier otra (Panel de Ventas).
 */
class Separados
{
    /** Días por defecto para recogerlo */
    public const DIAS = 15;

    /** Lo más largo que se puede separar */
    public const MAX_DIAS = 180;

    public static function disponible(): bool
    {
        return Schema::hasTable('separados');
    }

    /** "SP-000012" */
    public static function codigo(object $s): string
    {
        return 'SP-'.str_pad((string) $s->numero, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Separa: registra la venta por partes (con los mismos datos del Punto Venta) y anota el separado. Devuelve su id.
     *
     * @param  array<string, mixed>  $venta  los datos de VentaDirecta (items, tdocod, cliente…)
     * @param  array{adelanto: float, medio: ?int, vence: string, telefono?: ?string}  $sep
     */
    public static function crear(User $user, array $venta, array $sep): int
    {
        if (trim((string) ($venta['clinum'] ?? '')) === '00000000' || trim((string) ($venta['clinom'] ?? '')) === '') {
            throw new \RuntimeException('Para separar pon el DNI y el nombre del cliente (así se sabe de quién es).');
        }
        $adelanto = round((float) $sep['adelanto'], 2);
        if ($adelanto <= 0) {
            throw new \RuntimeException('Escribe el adelanto que deja el cliente.');
        }
        $vence = (string) $sep['vence'];
        if ($vence <= now()->toDateString() || $vence > now()->addDays(self::MAX_DIAS)->toDateString()) {
            throw new \RuntimeException('La fecha para recogerlo debe ser después de hoy (hasta '.self::MAX_DIAS.' días).');
        }

        return DB::transaction(function () use ($user, $venta, $sep, $adelanto, $vence) {
            $cabId = VentaDirecta::registrar($user, array_merge($venta, [
                'partes' => true, 'a_cuenta' => $adelanto, 'a_cuenta_medio' => $sep['medio'] ?? null, 'fecVen' => $vence,
                'observaciones' => mb_substr(trim('SEPARADO · recoge hasta el '.date('d/m/Y', strtotime($vence)).'. '.($venta['observaciones'] ?? '')), 0, 100),
            ]), 'PV');
            $numero = (int) DB::table('separados')->where('id_empresa_negocio', $user->id_empresa_negocio)->lockForUpdate()->max('numero') + 1;
            $id = DB::table('separados')->insertGetId([
                'numero' => $numero, 'id_empresa_negocio' => $user->id_empresa_negocio, 'IdEmpresa' => $user->IdEmpresa,
                'IdCpe_cabecera' => $cabId, 'clinum' => trim((string) $venta['clinum']), 'clinom' => mb_strtoupper(trim((string) $venta['clinom'])),
                'telefono' => preg_replace('/\D/', '', (string) ($sep['telefono'] ?? '')) ?: null,
                'fecha' => now()->toDateString(), 'vence' => $vence, 'estado' => 'SEPARADO', 'IdUsuario' => $user->IdUsuario,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $s = DB::table('separados')->where('id', $id)->first();
            Bitacora::registrar('separado.crear', 'Separó '.self::codigo($s).' a '.$s->clinom.' con S/ '.number_format($adelanto, 2).' de adelanto', null,
                ['referencia' => ['venta', $cabId]]);

            return $id;
        });
    }

    /**
     * Separados de la sucursal con su total, lo pagado y el saldo (de su cuenta por cobrar).
     * Estado: SEPARADO, VENCIDO (pasó la fecha), ENTREGADO o ANULADO (se anuló la venta).
     *
     * @return Collection<int, object>
     */
    public static function lista(int $sucursal, string $filtro = 'pendientes', string $buscar = ''): Collection
    {
        $hoy = now()->toDateString();
        $filas = DB::table('separados as s')->join('cpe_cabecera as c', 'c.IdCpe_cabecera', '=', 's.IdCpe_cabecera')
            ->leftJoin('cuentas_cobrar as cc', 'cc.IdCpe_cabecera', '=', 's.IdCpe_cabecera')
            ->where('s.id_empresa_negocio', $sucursal)
            ->when($filtro === 'pendientes', fn ($q) => $q->where('s.estado', 'SEPARADO')->whereNull('c.ccabaj'))
            ->when($filtro === 'entregados', fn ($q) => $q->where('s.estado', 'ENTREGADO'))
            ->when(trim($buscar) !== '', fn ($q) => $q->where(fn ($w) => $w->where('s.clinom', 'like', '%'.trim($buscar).'%')
                ->orWhere('s.clinum', trim($buscar))->orWhere('s.telefono', 'like', '%'.preg_replace('/\D/', '', $buscar).'%')))
            ->orderByRaw("s.estado = 'SEPARADO' DESC")->orderBy('s.vence')->limit(300)
            ->get(['s.*', 'c.tdocod', 'c.serdoc', 'c.numdoc', 'c.ccaitv as total', 'c.ccabaj', 'cc.cue_cob_id', 'cc.abono', 'cc.saldo']);
        $productos = DB::table('cpe_detalle')->whereIn('IdCpe_cabecera', $filas->pluck('IdCpe_cabecera')->all() ?: [0])
            ->get(['IdCpe_cabecera', 'cdedes', 'cdecan'])->groupBy('IdCpe_cabecera');

        return $filas->map(function ($s) use ($productos, $hoy) {
            $s->codigo = self::codigo($s);
            $s->documento = $s->serdoc.'-'.$s->numdoc;
            $s->total = (float) $s->total;
            $s->saldo = $s->cue_cob_id ? max((float) $s->saldo, 0) : 0.0;
            $s->pagado = round($s->total - $s->saldo, 2);
            $s->productos = collect($productos[$s->IdCpe_cabecera] ?? [])->map(fn ($d) => rtrim(rtrim(number_format((float) $d->cdecan, 3), '0'), '.').' × '.$d->cdedes)->all();
            $s->situacion = $s->ccabaj ? 'ANULADO' : ($s->estado === 'ENTREGADO' ? 'ENTREGADO' : ($s->vence < $hoy ? 'VENCIDO' : 'SEPARADO'));

            return $s;
        });
    }

    /** Resumen para el Inicio: cuántos hay, cuánto falta cobrar y cuántos vencieron */
    public static function resumen(int $sucursal): ?array
    {
        if (! self::disponible()) {
            return null;
        }
        $lista = self::lista($sucursal);
        if ($lista->isEmpty()) {
            return null;
        }

        return ['cantidad' => $lista->count(), 'por_cobrar' => round($lista->sum('saldo'), 2),
            'vencidos' => $lista->where('situacion', 'VENCIDO')->count(), 'listos' => $lista->where('saldo', '<=', 0)->count()];
    }

    /** Entrega el separado. Con saldo pendiente solo si se confirma (queda como deuda en Cuentas por cobrar). */
    public static function entregar(User $user, int $id, bool $aunqueDebe = false): object
    {
        $s = self::lista((int) $user->id_empresa_negocio, 'todos')->firstWhere('id', $id);
        if (! $s) {
            throw new \RuntimeException('Ese separado no existe.');
        }
        if ($s->situacion === 'ANULADO') {
            throw new \RuntimeException('Ese separado se anuló.');
        }
        if ($s->situacion === 'ENTREGADO') {
            throw new \RuntimeException('Ese separado ya se entregó.');
        }
        if ($s->saldo > 0.009 && ! $aunqueDebe) {
            throw new \RuntimeException('Aún falta cobrar S/ '.number_format($s->saldo, 2).'. Cobra el saldo antes de entregarlo.');
        }
        DB::table('separados')->where('id', $id)->update(['estado' => 'ENTREGADO', 'entregado_at' => now(), 'IdUsuario_entrega' => $user->IdUsuario, 'updated_at' => now()]);
        Bitacora::registrar('separado.entregar', 'Entregó el separado '.$s->codigo.' a '.$s->clinom.($s->saldo > 0.009 ? ' debiendo S/ '.number_format($s->saldo, 2) : ''), null,
            ['referencia' => ['venta', $s->IdCpe_cabecera], 'alerta' => $s->saldo > 0.009]);

        return $s;
    }

    /** Mensaje de WhatsApp para el cliente: lo que separó, lo que pagó, lo que falta y hasta cuándo */
    public static function mensaje(object $s, string $negocio): string
    {
        $soles = fn ($n) => 'S/ '.number_format((float) $n, 2);
        $nombre = mb_convert_case(strtok(trim((string) $s->clinom), ' ') ?: '', MB_CASE_TITLE);
        $lineas = ['Hola'.($nombre ? " {$nombre}" : '').' 👋, te escribimos de '.$negocio.'.', '', 'Tu separado '.$s->codigo.':'];
        foreach ($s->productos as $p) {
            $lineas[] = '• '.$p;
        }
        $lineas[] = '';
        $lineas[] = 'Total: '.$soles($s->total).' · Pagaste: '.$soles($s->pagado);
        $lineas[] = $s->saldo > 0.009 ? '*Te falta: '.$soles($s->saldo).'*' : '*¡Ya está pagado! Puedes pasar a recogerlo* 🎉';
        if ($s->saldo > 0.009) {
            $lineas[] = ($s->situacion === 'VENCIDO' ? 'Venció el ' : 'Puedes recogerlo hasta el ').date('d/m/Y', strtotime($s->vence)).'.';
        }
        $lineas[] = '';
        $lineas[] = '¡Te esperamos! 🙌';

        return implode("\n", $lineas);
    }
}
