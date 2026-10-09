<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fidelización con varias reglas: cada una da puntos por su cuenta (ej. GENERAL 1 punto por S/ 1 todo el año,
 * NAVIDAD 1 punto extra por S/ 2 del 1 al 31 de diciembre). Los puntos de una compra son la suma de las reglas vigentes.
 * La regla única que tenía la sucursal pasa a ser la regla "GENERAL".
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fid_reglas')) {
            Schema::create('fid_reglas', function (Blueprint $t) {
                $t->increments('regla_id');
                $t->string('nombre', 100);
                $t->decimal('soles_por_punto', 8, 2);
                $t->decimal('compra_minima', 10, 2)->default(0);
                $t->date('desde')->nullable();
                $t->date('hasta')->nullable();
                $t->tinyInteger('activo')->default(1);
                $t->unsignedBigInteger('id_empresa_negocio')->index();
            });
            foreach (DB::table('empresa_negocios')->get(['id_empresa_negocio', 'fid_soles_por_punto', 'fid_compra_minima']) as $s) {
                DB::table('fid_reglas')->insert(['nombre' => 'GENERAL', 'soles_por_punto' => max(0.01, (float) $s->fid_soles_por_punto),
                    'compra_minima' => (float) $s->fid_compra_minima, 'activo' => 1, 'id_empresa_negocio' => $s->id_empresa_negocio]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fid_reglas');
    }
};
