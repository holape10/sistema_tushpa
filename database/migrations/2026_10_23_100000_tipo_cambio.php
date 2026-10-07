<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tipo de cambio SUNAT consultado (apiperu.dev): una fila por fecha y moneda, así la misma fecha no se vuelve a consultar.
 * El menú "Tipo Cambio" (que estaba como "Pronto") pasa a abrir la consulta.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('tipo_cambio')) {
            Schema::create('tipo_cambio', function (Blueprint $t) {
                $t->increments('id');
                $t->date('fecha');
                $t->string('moneda', 3)->default('USD');
                $t->decimal('compra', 8, 3);
                $t->decimal('venta', 8, 3);
                $t->date('fecha_sunat')->nullable();      // día hábil publicado que rige para esa fecha
                $t->timestamp('creado')->nullable();
                $t->unique(['fecha', 'moneda']);
            });
        }
        DB::table('modulos')->where('mod_nom', 'Tipo Cambio')->where('mod_url', '#')->update(['mod_url' => '/tipo-cambio']);
    }

    public function down(): void
    {
        DB::table('modulos')->where('mod_url', '/tipo-cambio')->update(['mod_url' => '#']);
        Schema::dropIfExists('tipo_cambio');
    }
};
