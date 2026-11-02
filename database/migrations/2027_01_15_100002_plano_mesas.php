<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plano del local: las mesas (y los boxes VIP) donde están de verdad, con su forma y tamaño, y lo que ayuda a ubicarse
 * (barra, paredes, puerta, DJ, baños). Cada sucursal elige ver las mesas en cuadrícula (como siempre) o en su plano.
 * Medidas en un lienzo de 1000 x 625 por piso.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mesas', function (Blueprint $t) {
            foreach (['plano_x', 'plano_y', 'plano_w', 'plano_h'] as $columna) {
                if (! Schema::hasColumn('mesas', $columna)) {
                    $t->unsignedSmallInteger($columna)->nullable();
                }
            }
            if (! Schema::hasColumn('mesas', 'plano_forma')) {
                $t->string('plano_forma', 12)->nullable();
            }
        });
        if (! Schema::hasTable('plano_elementos')) {
            Schema::create('plano_elementos', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('id_empresa_negocio')->index();
                $t->unsignedBigInteger('pis_id')->index();
                $t->string('tipo', 12);
                $t->string('texto', 40)->nullable();
                $t->unsignedSmallInteger('x');
                $t->unsignedSmallInteger('y');
                $t->unsignedSmallInteger('w');
                $t->unsignedSmallInteger('h');
                $t->string('color', 7)->nullable();
            });
        }
        if (! Schema::hasColumn('empresa_negocios', 'vista_mesas')) {
            Schema::table('empresa_negocios', fn (Blueprint $t) => $t->string('vista_mesas', 6)->default('GRILLA'));
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('empresa_negocios', 'vista_mesas')) {
            Schema::table('empresa_negocios', fn (Blueprint $t) => $t->dropColumn('vista_mesas'));
        }
        Schema::dropIfExists('plano_elementos');
        foreach (['plano_x', 'plano_y', 'plano_w', 'plano_h', 'plano_forma'] as $columna) {
            if (Schema::hasColumn('mesas', $columna)) {
                Schema::table('mesas', fn (Blueprint $t) => $t->dropColumn($columna));
            }
        }
    }
};
