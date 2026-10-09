<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Carta digital con QR: la sucursal la activa y los clientes la ven desde su celular (sin sesión).
 * Los productos llevan una descripción corta opcional (ingredientes) que se muestra en la carta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresa_negocios', function (Blueprint $t) {
            if (! Schema::hasColumn('empresa_negocios', 'carta_activa')) {
                $t->boolean('carta_activa')->default(false);
                $t->string('carta_mensaje', 300)->nullable();
                $t->string('carta_color', 10)->nullable();
            }
        });
        if (! Schema::hasColumn('productos', 'descripcion')) {
            Schema::table('productos', fn (Blueprint $t) => $t->string('descripcion', 255)->nullable()->after('pronom'));
        }
        if (! DB::table('modulos')->where('mod_url', '/carta/configuracion')->exists()) {
            DB::table('modulos')->insert(['mod_nom' => 'Carta Digital QR', 'mod_url' => '/carta/configuracion', 'mod_gen' => 'Restaurante']);
        }
    }

    public function down(): void
    {
        Schema::table('empresa_negocios', fn (Blueprint $t) => $t->dropColumn(['carta_activa', 'carta_mensaje', 'carta_color']));
        Schema::table('productos', fn (Blueprint $t) => $t->dropColumn('descripcion'));
        DB::table('modulos')->where('mod_url', '/carta/configuracion')->delete();
    }
};
