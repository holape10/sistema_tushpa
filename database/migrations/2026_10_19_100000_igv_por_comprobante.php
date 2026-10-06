<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tasa de IGV de cada comprobante: 18% en los puntos de venta, 10.5% en lo cobrado desde Comandas (restaurante).
 * Las notas de crédito/débito y el envío a SUNAT usan la tasa del comprobante.
 * Lo emitido antes de este cambio se calculó con 10.5%.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('cpe_cabecera', 'por_igv')) {
            Schema::table('cpe_cabecera', function (Blueprint $table) {
                $table->decimal('por_igv', 5, 2)->nullable();
            });
        }
        DB::table('cpe_cabecera')->whereNull('por_igv')->where('ccaigv', '>', 0)->update(['por_igv' => 10.5]);
    }

    public function down(): void
    {
        Schema::table('cpe_cabecera', function (Blueprint $table) {
            $table->dropColumn('por_igv');
        });
    }
};
