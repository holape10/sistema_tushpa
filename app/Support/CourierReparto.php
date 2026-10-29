<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Reparto a domicilio del courier con los motorizados de la sucursal (los mismos de Delivery, con su enlace personal):
 * la agencia les asigna los envíos, el motorizado los entrega con foto y/o firma (y anota lo que cobró si pagaban al
 * recibir) o marca que no se pudo entregar, y la caja liquida lo cobrado emitiendo los comprobantes.
 */
class CourierReparto
{
    public const CARPETA = 'imagenes/courier';

    public const MEDIOS = ['EFECTIVO', 'YAPE', 'PLIN', 'TARJETA'];

    public const MOTIVOS = ['NO HABÍA NADIE', 'DIRECCIÓN NO ENCONTRADA', 'RECHAZÓ EL ENVÍO', 'NO TENÍA PARA PAGAR', 'REPROGRAMÓ', 'OTRO'];

    public static function motorizados(int $suc): Collection
    {
        return DB::table('motorizados')->where('id_empresa_negocio', $suc)->where('activo', 1)->orderBy('nombre')->get();
    }

    /** Sale a reparto con el motorizado (o se pasa a otro): envíos a domicilio que esperan en la agencia de destino */
    public static function asignar(User $user, array $ids, int $motId): int
    {
        $mot = DB::table('motorizados')->where('mot_id', $motId)->where('id_empresa_negocio', $user->id_empresa_negocio)->where('activo', 1)->first();
        if (! $mot) {
            throw new \RuntimeException('Elige un motorizado activo de tu sucursal.');
        }

        return DB::transaction(function () use ($user, $ids, $mot) {
            $n = 0;
            foreach (DB::table('cou_envios')->whereIn('env_id', $ids)->whereIn('estado', ['EN_DESTINO', 'EN_REPARTO'])->where('entrega', 'DOMICILIO')->lockForUpdate()->get() as $e) {
                if (! Courier::lado($e, (int) $user->id_empresa_negocio)['destino'] || (int) $e->mot_id === (int) $mot->mot_id) {
                    continue;
                }
                DB::table('cou_envios')->where('env_id', $e->env_id)->update(['estado' => 'EN_REPARTO', 'mot_id' => $mot->mot_id]);
                Courier::evento($e->env_id, 'EN_REPARTO', 'Salió a reparto con '.$mot->nombre, (int) $e->destino_id, $user->IdUsuario);
                $n++;
            }

            return $n;
        });
    }

    /** Lo que el motorizado tiene por entregar y lo que entregó hoy */
    public static function ruta(object $mot): Collection
    {
        return DB::table('cou_envios')->where('mot_id', $mot->mot_id)
            ->where(fn ($q) => $q->where('estado', 'EN_REPARTO')->orWhere(fn ($w) => $w->where('estado', 'ENTREGADO')->where('entregado', '>=', now()->startOfDay())))
            ->orderByRaw("estado = 'ENTREGADO'")->orderBy('env_id')->get();
    }

    /** ¿Al entregar, el motorizado debe cobrar? (paga al recibir y aún no tiene comprobante) */
    public static function cobraAlEntregar(object $e): bool
    {
        return $e->pago === 'DESTINO' && ! $e->IdCpe_cabecera && (float) $e->total > 0;
    }

    /**
     * Entregado por el motorizado: quién recibió (con su documento), foto y/o firma, y lo que cobró.
     *
     * @param  array<string, mixed>  $d  recibe_nombre, recibe_doc, cobrado, medio, firma (imagen PNG en base64)
     */
    public static function entregar(object $mot, int $envId, array $d, ?UploadedFile $foto): void
    {
        DB::transaction(function () use ($mot, $envId, $d, $foto) {
            $e = DB::table('cou_envios')->where('env_id', $envId)->where('mot_id', $mot->mot_id)->lockForUpdate()->first();
            if (! $e || $e->estado !== 'EN_REPARTO') {
                throw new \RuntimeException('Este envío ya no está en tu reparto.');
            }
            $firma = self::guardarFirma((string) ($d['firma'] ?? ''), $envId);
            if (! $foto && ! $firma) {
                throw new \RuntimeException('Toma una foto de la entrega o pide la firma de quien recibe.');
            }
            $cobrado = null;
            if (self::cobraAlEntregar($e)) {
                $cobrado = round((float) ($d['cobrado'] ?? 0), 2);
                if ($cobrado + 0.009 < (float) $e->total) {
                    throw new \RuntimeException('Debes cobrar S/ '.number_format((float) $e->total, 2).' antes de entregar.');
                }
            }
            $rutaFoto = $foto ? ImagenProducto::guardar($foto, self::CARPETA, 'ent_'.$envId.'_'.uniqid()) : null;
            $recibe = mb_strtoupper(trim((string) $d['recibe_nombre']));
            DB::table('cou_envios')->where('env_id', $envId)->update([
                'estado' => 'ENTREGADO', 'entregado' => now(), 'entregado_a' => $recibe, 'entregado_doc' => preg_replace('/\D/', '', (string) ($d['recibe_doc'] ?? '')) ?: null,
                'foto_entrega' => $rutaFoto, 'firma_entrega' => $firma,
                'cobrado_rep' => $cobrado ? (float) $e->total : null, 'medio_rep' => $cobrado ? (in_array($d['medio'] ?? '', self::MEDIOS, true) ? $d['medio'] : 'EFECTIVO') : null,
            ]);
            Courier::evento($envId, 'ENTREGADO', 'Entregado a '.Courier::enmascarar($recibe).' (reparto: '.$mot->nombre.')', (int) $e->destino_id);
        });
    }

    /** Firma dibujada en el celular (PNG en base64, máx. ~300 KB) */
    private static function guardarFirma(string $base64, int $envId): ?string
    {
        if (! preg_match('#^data:image/png;base64,([A-Za-z0-9+/=]+)$#', $base64, $m) || strlen($m[1]) > 400000) {
            return null;
        }
        $png = base64_decode($m[1], true);
        if ($png === false || ! str_starts_with($png, "\x89PNG")) {
            return null;
        }
        if (! is_dir(public_path(self::CARPETA))) {
            @mkdir(public_path(self::CARPETA), 0775, true);
        }
        $ruta = self::CARPETA.'/firma_'.$envId.'_'.uniqid().'.png';
        file_put_contents(public_path($ruta), $png);

        return $ruta;
    }

    /** No se pudo entregar: vuelve a la agencia de destino (queda para otro intento o para devolverlo) */
    public static function noEntregado(object $mot, int $envId, string $motivo): void
    {
        DB::transaction(function () use ($mot, $envId, $motivo) {
            $e = DB::table('cou_envios')->where('env_id', $envId)->where('mot_id', $mot->mot_id)->lockForUpdate()->first();
            if (! $e || $e->estado !== 'EN_REPARTO') {
                throw new \RuntimeException('Este envío ya no está en tu reparto.');
            }
            DB::table('cou_envios')->where('env_id', $envId)->update(['estado' => 'EN_DESTINO', 'mot_id' => null, 'intentos' => (int) $e->intentos + 1]);
            Courier::evento($envId, 'EN_DESTINO', 'No se pudo entregar: '.mb_strtoupper($motivo).' (intento '.((int) $e->intentos + 1).')', (int) $e->destino_id);
        });
    }

    /** Lo que los motorizados cobraron al entregar y aún no se pasa por caja, por motorizado */
    public static function porLiquidar(User $user): Collection
    {
        return Courier::llegan($user, DB::table('cou_envios as e'))->where('e.estado', 'ENTREGADO')->whereNotNull('e.cobrado_rep')->whereNull('e.IdCpe_cabecera')
            ->leftJoin('motorizados as m', 'm.mot_id', '=', 'e.mot_id')->orderBy('e.mot_id')->orderBy('e.entregado')
            ->get(['e.*', 'm.nombre as motorizado'])->groupBy('mot_id');
    }

    /**
     * La caja recibe lo cobrado por el motorizado y emite el comprobante de cada envío (factura si el destinatario tiene RUC).
     *
     * @param  array<int, int>  $ids
     * @return array<int, int> IdCpe_cabecera emitidos
     */
    public static function liquidar(User $user, array $ids): array
    {
        $suc = (int) $user->id_empresa_negocio;
        $medios = DB::table('medios_pagos')->where('id_empresa_negocio', $suc)->orderByDesc('predeterminado')->get(['id_med_pag', 'nom_med_pag']);

        return DB::transaction(function () use ($user, $ids, $medios) {
            $cabs = [];
            foreach (DB::table('cou_envios')->whereIn('env_id', $ids)->where('estado', 'ENTREGADO')->whereNotNull('cobrado_rep')->whereNull('IdCpe_cabecera')->lockForUpdate()->get() as $e) {
                $medio = $medios->first(fn ($m) => str_contains(mb_strtoupper($m->nom_med_pag), (string) $e->medio_rep)) ?? $medios->first();
                $doc = $e->entregado_doc ?: ($e->des_doc ?: '00000000');
                $cabs[] = Courier::cobrar($user, $e, ['tdocod' => strlen($doc) === 11 ? '01' : '03', 'id_med_pag' => $medio?->id_med_pag],
                    ['clinum' => $doc, 'clinom' => $e->entregado_a ?: $e->des_nombre]);
            }
            if (! $cabs) {
                throw new \RuntimeException('No hay cobros pendientes de liquidar en lo elegido.');
            }

            return $cabs;
        });
    }
}
