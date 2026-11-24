<?php

namespace App\Support\Sunat;

use App\Models\Empresa;
use App\Models\EmpresaNegocio;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Envío a SUNAT "Programado" (Empresas › Envío a SUNAT, tipo_envio = 3): una vez al día, pasada la hora elegida
 * (ej. 19:00), se envía lo pendiente de los últimos días: las facturas y sus notas una por una, y las boletas y sus notas
 * en resumen diario (uno por cada día de emisión). Si a esa hora no hay internet o el servidor estaba apagado, sale en la
 * siguiente vuelta del mismo día. Lo que siga pendiente lo salva igual el envío de rescate antes de que venza el plazo.
 */
class EnvioProgramado
{
    public const TIPO = 3;

    /** Hasta cuántos días atrás se envía (el plazo de las boletas es de 7 días; las facturas, 3) */
    private const DIAS = ['F' => 3, 'B' => 7];

    private const MAXIMO_FACTURAS = 300;

    /** ¿A la empresa le toca enviar ahora? (programada, ya pasó su hora y hoy todavía no se envió) */
    public static function toca(Empresa $empresa, ?Carbon $ahora = null): bool
    {
        $ahora ??= now();
        if ((int) $empresa->tipo_envio !== self::TIPO || ! preg_match('/^\d{2}:\d{2}$/', (string) $empresa->envio_hora)) {
            return false;
        }
        $hora = Carbon::parse($ahora->toDateString().' '.$empresa->envio_hora);

        return $ahora->gte($hora) && (! $empresa->envio_programado_el || Carbon::parse($empresa->envio_programado_el)->lt($ahora->copy()->startOfDay()));
    }

    /**
     * Envía lo de las empresas de la base actual a las que les toca.
     *
     * @return array{empresas: int, facturas: int, resumenes: int, fallidos: int, detalle: list<string>}
     */
    public static function ejecutar(?Carbon $ahora = null): array
    {
        $ahora ??= now();
        $r = ['empresas' => 0, 'facturas' => 0, 'resumenes' => 0, 'fallidos' => 0, 'detalle' => []];
        foreach (Empresa::where('tipo_envio', self::TIPO)->get() as $empresa) {
            if (! self::toca($empresa, $ahora)) {
                continue;
            }
            // Se marca antes de enviar: aunque algo falle, no se repite todo el día (el rescate cubre lo que quede)
            DB::table('empresa')->where('IdEmpresa', $empresa->IdEmpresa)->update(['envio_programado_el' => $ahora->toDateString()]);
            $r['empresas']++;
            foreach (EmpresaNegocio::where('IdEmpresa', $empresa->IdEmpresa)->get() as $negocio) {
                self::sucursal($empresa, $negocio, $ahora, $r);
            }
        }

        return $r;
    }

    private static function sucursal(Empresa $empresa, EmpresaNegocio $negocio, Carbon $ahora, array &$r): void
    {
        $sunat = new SunatService($empresa, $negocio);
        $hoy = $ahora->toDateString();
        $pendiente = fn ($q) => $q->where('id_empresa_negocio', $negocio->id_empresa_negocio)->whereNull('ccabaj')
            ->where(fn ($w) => $w->whereNull('est_sunat')->orWhere('est_sunat', '')->orWhereIn('est_sunat', ['PENDIENTE', 'ERROR']));

        // Facturas y sus notas (serie F): una por una
        $facturas = $pendiente(DB::table('cpe_cabecera'))
            ->where(fn ($w) => $w->where('tdocod', '01')->orWhere(fn ($n) => $n->whereIn('tdocod', ['07', '08'])->where('serdoc', 'like', 'F%')))
            ->whereBetween('ccafem', [$ahora->copy()->subDays(self::DIAS['F'])->toDateString(), $hoy])
            ->orderBy('ccafem')->orderBy('IdCpe_cabecera')->limit(self::MAXIMO_FACTURAS)->get(['IdCpe_cabecera', 'serdoc', 'numdoc']);
        foreach ($facturas as $c) {
            try {
                $res = $sunat->enviarComprobante((int) $c->IdCpe_cabecera);
                $r['facturas']++;
                if (! in_array($res['estado'], ['ACEPTADO', 'OBSERVADO'], true)) {
                    $r['fallidos']++;
                }
                $r['detalle'][] = "{$c->serdoc}-{$c->numdoc}: {$res['estado']}";
            } catch (\Throwable $e) {
                $r['fallidos']++;
                $r['detalle'][] = "{$c->serdoc}-{$c->numdoc}: ".mb_substr($e->getMessage(), 0, 120);
                Log::warning("Envío programado {$c->serdoc}-{$c->numdoc}: ".$e->getMessage());
            }
        }

        // Boletas y sus notas (serie B): un resumen diario por cada día de emisión
        $dias = $pendiente(DB::table('cpe_cabecera'))
            ->where(fn ($w) => $w->where('tdocod', '03')->orWhere(fn ($n) => $n->whereIn('tdocod', ['07', '08'])->where('serdoc', 'like', 'B%')))
            ->whereBetween('ccafem', [$ahora->copy()->subDays(self::DIAS['B'])->toDateString(), $hoy])
            ->distinct()->orderBy('ccafem')->pluck('ccafem');
        foreach ($dias as $dia) {
            $dia = substr((string) $dia, 0, 10);
            try {
                if ($sunat->pendientesResumen($dia)->isEmpty()) {
                    continue;
                }
                $creados = $sunat->enviarResumen($dia);
                $r['resumenes'] += count($creados);
                $r['detalle'][] = "Resumen de boletas del {$dia}: ".count($creados).' enviado(s)';
            } catch (\Throwable $e) {
                $r['fallidos']++;
                $r['detalle'][] = "Resumen del {$dia}: ".mb_substr($e->getMessage(), 0, 120);
                Log::warning("Envío programado, resumen del {$dia}: ".$e->getMessage());
            }
        }
    }
}
