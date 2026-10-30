<?php

namespace App\Support\Sunat;

use App\Models\Empresa;
use App\Models\EmpresaNegocio;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Envío de rescate: si nadie mandó a SUNAT un comprobante y se le acaba el plazo, el sistema lo envía solo.
 * El plazo se cuenta desde el día siguiente a la emisión (R.S. 000003-2023/SUNAT): una factura del 27 vale hasta el 30.
 *  - Factura y sus notas (serie F): 3 días calendario → se envía desde el día siguiente a la emisión.
 *  - Boleta y sus notas (serie B): 7 días → se envía desde el 4.º día después de la emisión.
 * No toca lo RECHAZADO (hay que corregirlo), ni lo anulado, ni lo que ya venció el plazo.
 */
class RescateEnvios
{
    /** [días de plazo después de la emisión, desde cuántos días después de la emisión se envía] */
    public const PLAZOS = ['F' => [3, 1], 'B' => [7, 4]];

    private const MAXIMO_POR_SUCURSAL = 60;

    /** Comprobantes de la sucursal que hay que enviar hoy */
    public static function porVencer(int $sucursal, ?Carbon $hoy = null): Collection
    {
        $hoy ??= now()->startOfDay();
        $tipo = fn ($q, string $letra) => $q->where(fn ($w) => $w->where('tdocod', $letra === 'F' ? '01' : '03')
            ->orWhere(fn ($n) => $n->whereIn('tdocod', ['07', '08'])->where('serdoc', 'like', $letra.'%')));

        return collect(self::PLAZOS)->flatMap(function ($p, $letra) use ($sucursal, $hoy, $tipo) {
            [$plazo, $desde] = $p;

            return DB::table('cpe_cabecera')->where('id_empresa_negocio', $sucursal)->whereNull('ccabaj')
                ->where(fn ($w) => $w->whereNull('est_sunat')->orWhereIn('est_sunat', ['PENDIENTE', 'ERROR']))
                ->where(fn ($q) => $tipo($q, $letra))
                // El plazo empieza al día siguiente de la emisión: hoy todavía vale lo emitido hace $plazo días
                ->whereBetween('ccafem', [$hoy->copy()->subDays($plazo)->toDateString(), $hoy->copy()->subDays($desde)->toDateString()])
                ->orderBy('ccafem')->orderBy('IdCpe_cabecera')->limit(self::MAXIMO_POR_SUCURSAL)
                ->get(['IdCpe_cabecera', 'tdocod', 'serdoc', 'numdoc', 'ccafem']);
        })->values();
    }

    /**
     * Envía lo que está por vencer en todas las sucursales de la base actual.
     *
     * @return array{enviados: int, aceptados: int, fallidos: int, detalle: array<int, string>}
     */
    public static function ejecutar(): array
    {
        $r = ['enviados' => 0, 'aceptados' => 0, 'fallidos' => 0, 'detalle' => []];
        foreach (EmpresaNegocio::all() as $negocio) {
            $pendientes = self::porVencer((int) $negocio->id_empresa_negocio);
            $empresa = $pendientes->isNotEmpty() ? Empresa::find($negocio->IdEmpresa) : null;
            if (! $empresa) {
                continue;
            }
            $sunat = new SunatService($empresa, $negocio);
            foreach ($pendientes as $c) {
                $numero = $c->serdoc.'-'.$c->numdoc;
                try {
                    $res = $sunat->enviarComprobante((int) $c->IdCpe_cabecera);
                    $r['enviados']++;
                    $r[in_array($res['estado'], ['ACEPTADO', 'OBSERVADO'], true) ? 'aceptados' : 'fallidos']++;
                    $r['detalle'][] = "{$numero}: {$res['estado']}";
                } catch (\Throwable $e) {
                    $r['fallidos']++;
                    $r['detalle'][] = "{$numero}: ".mb_substr($e->getMessage(), 0, 120);
                    Log::warning("Envío de rescate {$numero}: ".$e->getMessage());
                }
            }
        }

        return $r;
    }
}
