<?php

namespace App\Support;

use App\Support\Tenancy\Tenancy;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

/**
 * Consulta pública de comprobantes ({subdominio}/cpe): el cliente escribe tipo, serie, número, fecha y total;
 * se busca en el sistema y se valida en SUNAT (apiperu.dev). Si existe, descarga el PDF A4, el XML y el CDR.
 * Pedir la fecha y el total evita que alguien recorra los comprobantes de otros adivinando números.
 */
class ConsultaCpe
{
    public const TIPOS = ['03' => 'Boleta de Venta Electrónica', '01' => 'Factura Electrónica', '07' => 'Nota de Crédito Electrónica', '08' => 'Nota de Débito Electrónica'];

    /** Qué ve el cliente según el estado: [texto, color, explicación] */
    private const ESTADOS = [
        'ACEPTADO' => ['ACEPTADO', 'emerald', 'El comprobante es válido y fue aceptado por SUNAT.'],
        'ANULADO' => ['ANULADO', 'rose', 'El comprobante fue anulado (comunicado de baja) y ya no tiene validez.'],
        'RECHAZADO' => ['RECHAZADO', 'rose', 'SUNAT rechazó este comprobante: no tiene validez. Comunícate con la empresa.'],
        'EN_PROCESO' => ['EN PROCESO', 'amber', 'La empresa aún está enviando este comprobante a SUNAT. Vuelve a consultar en unas horas.'],
        'NO_EXISTE' => ['NO EXISTE', 'slate', 'No encontramos un comprobante con esos datos. Revisa la serie, el número, la fecha y el monto.'],
    ];

    /** Dirección pública de la consulta, la que va impresa en el comprobante (sin https://) */
    public static function direccion(): string
    {
        $cliente = Tenancy::cliente();
        $url = $cliente ? Tenancy::urlCliente($cliente->subdominio ?: $cliente->ruc).'/cpe' : url('/cpe');

        return preg_replace('~^https?://~', '', $url);
    }

    /**
     * @param  array{tipo: string, serie: string, numero: string|int, fecha: string, total: float|string}  $d
     * @return array{estado: string, texto: string, color: string, explicacion: string, cpe: ?object, sunat: ?array, descargas: array<string, string>}
     */
    public static function consultar(array $d): array
    {
        $serie = mb_strtoupper(trim($d['serie']));
        $numero = (int) $d['numero'];
        $total = round((float) $d['total'], 2);

        $cpe = DB::table('cpe_cabecera as c')->leftJoin('tipo_documento as t', 't.tdocod', '=', 'c.tdocod')
            ->where('c.tdocod', $d['tipo'])->where('c.serdoc', $serie)->whereRaw('CAST(c.numdoc AS UNSIGNED) = ?', [$numero])
            ->where('c.ccafem', $d['fecha'])->whereBetween('c.ccaitv', [$total - 0.05, $total + 0.05])
            ->first(['c.IdCpe_cabecera', 'c.IdEmpresa', 'c.tdocod', 'c.serdoc', 'c.numdoc', 'c.ccafem', 'c.ccaitv', 'c.ccanom', 'c.est_sunat', 'c.ccabaj', 't.tdodes']);

        // SUNAT (si hay token de apiperu): se guarda 10 min para no gastar consultas si el cliente vuelve a pulsar
        $ruc = $cpe->IdEmpresa ?? self::rucEmpresa();
        $sunat = $ruc ? Cache::remember('cpe-sunat:'.md5(implode('|', [$ruc, $d['tipo'], $serie, $numero, $d['fecha'], $total])), 600,
            fn () => ConsultaPeru::estadoCpe($ruc, $d['tipo'], $serie, (string) $numero, $d['fecha'], $total)) : null;

        $estado = self::estado($cpe, $sunat);
        [$texto, $color, $explicacion] = self::ESTADOS[$estado] ?? [$sunat['estado'] ?? $estado, 'slate', ''];
        if (! isset(self::ESTADOS[$estado]) && $sunat) {
            $explicacion = 'Estado informado por SUNAT.';
        }

        return ['estado' => $estado, 'texto' => $texto, 'color' => $color, 'explicacion' => $explicacion, 'cpe' => $cpe, 'sunat' => $sunat,
            'descargas' => $cpe && in_array($estado, ['ACEPTADO', 'ANULADO', 'RECHAZADO', 'EN_PROCESO'], true) ? self::descargas($cpe, $estado) : []];
    }

    /** Lo que dice SUNAT manda; sin SUNAT, el estado que guarda el sistema */
    private static function estado(?object $cpe, ?array $sunat): string
    {
        if ($sunat) {
            return match (true) {
                str_contains($sunat['estado'], 'ACEPTADO') => 'ACEPTADO',
                str_contains($sunat['estado'], 'ANULADO') || str_contains($sunat['estado'], 'BAJA') => 'ANULADO',
                str_contains($sunat['estado'], 'NO EXISTE') => $cpe && ! in_array($cpe->est_sunat, ['RECHAZADO', 'ANULADO'], true) ? 'EN_PROCESO' : 'NO_EXISTE',
                default => $sunat['estado'],
            };
        }
        if (! $cpe) {
            return 'NO_EXISTE';
        }

        return match (true) {
            $cpe->est_sunat === 'ANULADO' || $cpe->ccabaj !== null => 'ANULADO',
            in_array($cpe->est_sunat, ['ACEPTADO', 'OBSERVADO'], true) => 'ACEPTADO',
            $cpe->est_sunat === 'RECHAZADO' => 'RECHAZADO',
            default => 'EN_PROCESO',
        };
    }

    /**
     * Enlaces firmados (30 min) a los archivos que existen.
     *
     * @return array<string, string>
     */
    private static function descargas(object $cpe, string $estado): array
    {
        $vence = now()->addMinutes(30);
        $enlaces = ['pdf' => URL::temporarySignedRoute('comprobante.pdf', $vence, ['id' => $cpe->IdCpe_cabecera])];
        foreach (['xml', 'cdr'] as $tipo) {
            if (is_file(self::archivo($cpe, $tipo)) && ($tipo === 'xml' || $estado !== 'EN_PROCESO')) {
                $enlaces[$tipo] = URL::temporarySignedRoute('cpe.archivo', $vence, ['id' => $cpe->IdCpe_cabecera, 'tipo' => $tipo]);
            }
        }

        return $enlaces;
    }

    /** Ruta del XML firmado o del CDR (respuesta de SUNAT) de un comprobante */
    public static function archivo(object $cpe, string $tipo): string
    {
        $nombre = $cpe->IdEmpresa.'-'.$cpe->tdocod.'-'.$cpe->serdoc.'-'.$cpe->numdoc;

        return storage_path('app/sunat/'.$cpe->IdEmpresa.'/'.($tipo === 'cdr' ? 'R-'.$nombre.'.zip' : $nombre.'.xml'));
    }

    public static function rucEmpresa(): ?string
    {
        return DB::table('empresa')->value('IdEmpresa');
    }
}
