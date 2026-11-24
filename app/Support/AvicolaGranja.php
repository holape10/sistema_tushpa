<?php

namespace App\Support;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Granja avícola: lotes (galpones) de engorde o de postura. Cada día se anota la mortalidad, el alimento, otros gastos
 * (vacunas, cama, luz), el peso promedio de una muestra y los huevos. El resumen da las aves vivas, la mortalidad %, la
 * conversión alimenticia, el costo por ave y por kilo, y la producción de huevos. Las aves que salen a la venta entran
 * al stock como "Ingreso de pollo" desde el lote.
 */
class AvicolaGranja
{
    public const TIPOS = ['ENGORDE' => 'Engorde (pollo de carne)', 'POSTURA' => 'Postura (huevos)'];

    /** @param  array<string, mixed>  $d */
    public static function crear(User $user, array $d): int
    {
        $suc = (int) $user->id_empresa_negocio;
        $codigo = mb_strtoupper(trim((string) ($d['codigo'] ?? ''))) ?: 'L'.now()->format('ymd');
        if (DB::table('avi_lotes')->where('id_empresa_negocio', $suc)->where('codigo', $codigo)->where('estado', 'ABIERTO')->exists()) {
            throw new \RuntimeException("Ya hay un lote abierto con el código {$codigo}.");
        }

        return DB::table('avi_lotes')->insertGetId([
            'codigo' => mb_substr($codigo, 0, 30), 'galpon' => mb_substr(trim((string) ($d['galpon'] ?? '')), 0, 60) ?: null,
            'tipo' => isset(self::TIPOS[$d['tipo'] ?? '']) ? $d['tipo'] : 'ENGORDE', 'linea' => mb_substr(trim((string) ($d['linea'] ?? '')), 0, 60) ?: null,
            'fecha_ingreso' => $d['fecha_ingreso'] ?? now()->toDateString(), 'aves_iniciales' => max(1, (int) $d['aves_iniciales']),
            'costo_ave' => round(max(0, (float) ($d['costo_ave'] ?? 0)), 2), 'observacion' => mb_substr(trim((string) ($d['observacion'] ?? '')), 0, 200) ?: null,
            'id_empresa_negocio' => $suc, 'creado' => now(),
        ]);
    }

    public static function lote(User $user, int $id): object
    {
        $lote = DB::table('avi_lotes')->where('id', $id)->where('id_empresa_negocio', $user->id_empresa_negocio)->first();
        if (! $lote) {
            throw new \RuntimeException('El lote no existe.');
        }

        return $lote;
    }

    /**
     * Registro del día: mortalidad (también queda como merma de la granja, con el costo del ave), alimento, gastos,
     * peso promedio y huevos.
     *
     * @param  array<string, mixed>  $d
     */
    public static function registrar(User $user, int $loteId, array $d): int
    {
        $lote = self::lote($user, $loteId);
        if ($lote->estado !== 'ABIERTO') {
            throw new \RuntimeException('El lote ya está cerrado.');
        }
        $muertas = max(0, (int) ($d['mortalidad'] ?? 0));
        if ($muertas > self::resumen($lote)['aves_vivas']) {
            throw new \RuntimeException('Las aves muertas no pueden ser más que las aves vivas del lote.');
        }

        return DB::transaction(function () use ($user, $lote, $d, $muertas) {
            $id = DB::table('avi_lote_registros')->insertGetId([
                'lote_id' => $lote->id, 'fecha' => $d['fecha'] ?? now()->toDateString(), 'mortalidad' => $muertas,
                'alimento_kg' => round(max(0, (float) ($d['alimento_kg'] ?? 0)), 2), 'costo_alimento' => round(max(0, (float) ($d['costo_alimento'] ?? 0)), 2),
                'otros_gastos' => round(max(0, (float) ($d['otros_gastos'] ?? 0)), 2),
                'peso_promedio' => ($d['peso_promedio'] ?? '') !== '' && $d['peso_promedio'] !== null ? round((float) $d['peso_promedio'], 3) : null,
                'huevos' => max(0, (int) ($d['huevos'] ?? 0)), 'observacion' => mb_substr(trim((string) ($d['observacion'] ?? '')), 0, 150) ?: null,
                'IdUsuario' => $user->IdUsuario,
            ]);
            if ($muertas > 0) {
                Avicola::merma($user, ['tipo' => 'MORTALIDAD', 'origen' => 'GRANJA', 'ref_id' => $lote->id, 'aves' => $muertas, 'kg' => 0,
                    'sale_stock' => false, 'costo' => round($muertas * (float) $lote->costo_ave, 2), 'fecha' => $d['fecha'] ?? now()->toDateString(),
                    'observacion' => 'Mortalidad en el lote '.$lote->codigo]);
            }

            return $id;
        });
    }

    public static function cerrar(User $user, int $loteId): void
    {
        $lote = self::lote($user, $loteId);
        if ($lote->estado !== 'ABIERTO') {
            throw new \RuntimeException('El lote ya está cerrado.');
        }
        DB::table('avi_lotes')->where('id', $lote->id)->update(['estado' => 'CERRADO', 'fecha_cierre' => now()->toDateString()]);
    }

    /**
     * Cómo va el lote.
     *
     * @return array<string, mixed>
     */
    public static function resumen(object $lote): array
    {
        $r = DB::table('avi_lote_registros')->where('lote_id', $lote->id)
            ->selectRaw('COALESCE(SUM(mortalidad),0) as muertas, COALESCE(SUM(alimento_kg),0) as alimento, COALESCE(SUM(costo_alimento),0) as costo_alimento,
                COALESCE(SUM(otros_gastos),0) as otros, COALESCE(SUM(huevos),0) as huevos')->first();
        $peso = DB::table('avi_lote_registros')->where('lote_id', $lote->id)->whereNotNull('peso_promedio')->orderByDesc('fecha')->orderByDesc('id')->value('peso_promedio');
        $salidas = DB::table('avi_ingresos')->where('lote_id', $lote->id)->where('estado', 'ACTIVO')
            ->selectRaw('COALESCE(SUM(aves),0) as aves, COALESCE(SUM(peso_neto),0) as kg')->first();
        $vivas = max(0, (int) $lote->aves_iniciales - (int) $r->muertas - (int) $salidas->aves);
        $costo = round((int) $lote->aves_iniciales * (float) $lote->costo_ave + (float) $r->costo_alimento + (float) $r->otros, 2);
        // Kilos producidos: lo que ya salió más lo que hay en pie (aves vivas por el último peso promedio)
        $kgEnPie = $peso ? round($vivas * (float) $peso, 3) : 0.0;
        $kgProducidos = round((float) $salidas->kg + $kgEnPie, 3);
        $fin = $lote->fecha_cierre ? Carbon::parse($lote->fecha_cierre) : now();

        return [
            'edad_dias' => (int) Carbon::parse($lote->fecha_ingreso)->startOfDay()->diffInDays($fin->copy()->startOfDay()),
            'aves_vivas' => $vivas, 'muertas' => (int) $r->muertas,
            'mortalidad_pct' => $lote->aves_iniciales > 0 ? round((int) $r->muertas / (int) $lote->aves_iniciales * 100, 2) : 0,
            'alimento_kg' => (float) $r->alimento, 'costo_alimento' => (float) $r->costo_alimento, 'otros_gastos' => (float) $r->otros,
            'costo_total' => $costo, 'costo_ave' => $vivas + (int) $salidas->aves > 0 ? round($costo / ($vivas + (int) $salidas->aves), 2) : 0,
            'peso_promedio' => $peso !== null ? (float) $peso : null, 'kg_en_pie' => $kgEnPie,
            'aves_salidas' => (int) $salidas->aves, 'kg_salidos' => (float) $salidas->kg, 'kg_producidos' => $kgProducidos,
            // Conversión alimenticia: kilos de alimento por kilo de pollo producido (mientras más bajo, mejor)
            'conversion' => $kgProducidos > 0 ? round((float) $r->alimento / $kgProducidos, 2) : null,
            'costo_kg' => $kgProducidos > 0 ? round($costo / $kgProducidos, 4) : 0,
            'huevos' => (int) $r->huevos,
            // Postura: huevos por ave viva por día (de los días registrados)
            'postura_pct' => self::postura($lote, $vivas),
        ];
    }

    private static function postura(object $lote, int $vivas): ?float
    {
        if ($lote->tipo !== 'POSTURA' || $vivas <= 0) {
            return null;
        }
        $dias = DB::table('avi_lote_registros')->where('lote_id', $lote->id)->where('huevos', '>', 0)->count();
        $huevos = (int) DB::table('avi_lote_registros')->where('lote_id', $lote->id)->sum('huevos');

        return $dias > 0 ? round($huevos / ($vivas * $dias) * 100, 1) : null;
    }
}
