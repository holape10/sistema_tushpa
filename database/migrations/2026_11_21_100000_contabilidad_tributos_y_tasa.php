<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Contabilidad: cuentas de cada tributo (IGV de compras aparte del de ventas, ISC, ICBPER, otros), tasa del IGV
 * (18% o 10.5% de restaurantes y hoteles MYPE) y en el registro contable el ISC, el ICBPER y la retención de 4ta.
 */
return new class extends Migration
{
    /** Cuentas del PCGE que faltaban (se agregan a las empresas que ya tienen su plan) */
    private const CUENTAS = [
        '40112' => 'IGV - RÉGIMEN DE PERCEPCIONES', '40113' => 'IGV - RÉGIMEN DE RETENCIONES',
        '4012' => 'IMPUESTO SELECTIVO AL CONSUMO', '4018' => 'OTROS IMPUESTOS Y CONTRAPRESTACIONES',
        '40181' => 'IMPUESTO A LAS TRANSACCIONES FINANCIERAS', '40189' => 'OTROS IMPUESTOS (ICBPER Y OTROS)',
    ];

    public function up(): void
    {
        if (Schema::hasTable('conta_config')) {
            Schema::table('conta_config', function (Blueprint $t) {
                if (! Schema::hasColumn('conta_config', 'cta_igv_compras')) {
                    $t->string('cta_igv_compras', 12)->default('401111');
                    $t->string('cta_isc', 12)->default('4012');
                    $t->string('cta_icbper', 12)->default('40189');
                    $t->string('cta_otros_tributos', 12)->default('40189');
                    $t->decimal('tasa_igv', 5, 2)->default(18);
                }
            });
        }
        if (Schema::hasTable('conta_registros') && ! Schema::hasColumn('conta_registros', 'isc')) {
            Schema::table('conta_registros', function (Blueprint $t) {
                $t->decimal('isc', 14, 2)->default(0)->after('igv');
                $t->decimal('icbper', 14, 2)->default(0)->after('isc');
                $t->decimal('retencion', 14, 2)->default(0)->after('otros');   // renta de 4ta (recibos por honorarios)
            });
        }
        if (Schema::hasTable('conta_plan')) {
            foreach (DB::table('conta_plan')->distinct()->pluck('IdEmpresa') as $ruc) {
                foreach (self::CUENTAS as $cuenta => $nombre) {
                    if (DB::table('conta_plan')->where('IdEmpresa', $ruc)->where('cuenta', $cuenta)->exists()) {
                        continue;
                    }
                    DB::table('conta_plan')->insert(['IdEmpresa' => $ruc, 'cuenta' => $cuenta, 'descripcion' => $nombre, 'nivel' => strlen($cuenta),
                        'tipo' => 'PASIVO', 'naturaleza' => 'A', 'imputable' => 1, 'estado' => 'Activo', 'created_at' => now(), 'updated_at' => now()]);
                    // La cuenta madre deja de ser imputable si ahora tiene subcuentas
                    DB::table('conta_plan')->where('IdEmpresa', $ruc)->whereIn('cuenta', [substr($cuenta, 0, -1)])->where('cuenta', '!=', '4011')->update(['imputable' => 0]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::table('conta_config', fn (Blueprint $t) => $t->dropColumn(['cta_igv_compras', 'cta_isc', 'cta_icbper', 'cta_otros_tributos', 'tasa_igv']));
        Schema::table('conta_registros', fn (Blueprint $t) => $t->dropColumn(['isc', 'icbper', 'retencion']));
    }
};
