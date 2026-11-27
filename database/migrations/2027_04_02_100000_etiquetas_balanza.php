<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Etiquetas de la balanza etiquetadora (supermercados), por sucursal. Apagado por defecto: nada cambia hasta activarlo.
 *  - balanza_etiqueta: la caja lee las etiquetas (EAN-13 que empieza con 2: PLU + peso o precio)
 *  - balanza_prefijo: con qué empiezan ('2' = cualquiera del 20 al 29, o uno exacto como '20')
 *  - balanza_modo: PESO (gramos) o PRECIO (céntimos)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('empresa_negocios')) {
            return;
        }
        Schema::table('empresa_negocios', function (Blueprint $t) {
            if (! Schema::hasColumn('empresa_negocios', 'balanza_etiqueta')) {
                $t->boolean('balanza_etiqueta')->default(false);
            }
            if (! Schema::hasColumn('empresa_negocios', 'balanza_prefijo')) {
                $t->string('balanza_prefijo', 2)->default('2');
            }
            if (! Schema::hasColumn('empresa_negocios', 'balanza_modo')) {
                $t->string('balanza_modo', 6)->default('PESO');
            }
        });
    }

    public function down(): void
    {
        foreach (['balanza_etiqueta', 'balanza_prefijo', 'balanza_modo'] as $col) {
            if (Schema::hasColumn('empresa_negocios', $col)) {
                Schema::table('empresa_negocios', fn (Blueprint $t) => $t->dropColumn($col));
            }
        }
    }
};
