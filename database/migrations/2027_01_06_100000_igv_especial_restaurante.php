<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * IGV especial de las MYPE de restaurantes, hoteles y alojamientos (Ley 31556 y 32219): por sucursal se puede apagar
 * cuando la empresa no cumple los requisitos (más de 1,700 UIT de ventas, menos del 70% de ingresos del rubro, o vinculada
 * a otras empresas) y entonces vende con el 18%. Encendido por defecto: así venían facturando.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('empresa_negocios', 'igv_restaurante')) {
            Schema::table('empresa_negocios', function (Blueprint $t) {
                $t->boolean('igv_restaurante')->default(true);
            });
        }
    }

    public function down(): void
    {
        Schema::table('empresa_negocios', function (Blueprint $t) {
            $t->dropColumn('igv_restaurante');
        });
    }
};
