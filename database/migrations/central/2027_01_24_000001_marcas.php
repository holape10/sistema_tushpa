<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marcas (marca blanca): un revendedor con su propio dominio (ej. wilcatsystems.pe), el prefijo de las bases de sus
 * empresas (bd_ws_{RUC}), su logo, sus colores y sus datos para los contratos. Las empresas sin marca son de TUSHPA
 * (tushpa.app, bd_{RUC}), como siempre. El usuario del panel de un revendedor queda unido a su marca.
 */
return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        $central = Schema::connection('central');
        if (! $central->hasTable('marcas')) {
            $central->create('marcas', function (Blueprint $t) {
                $t->id();
                $t->string('nombre', 100);                       // WILCAT SYSTEMS (lo que se ve en pantalla)
                $t->string('razon_social', 200)->nullable();     // para los contratos
                $t->string('ruc', 11)->nullable();
                $t->string('dominio', 100)->unique();            // wilcatsystems.pe
                $t->string('prefijo_bd', 20)->unique();          // bd_ws_
                $t->string('logo', 255)->nullable();
                $t->string('color', 7)->nullable();              // #1565C0
                $t->string('color_secundario', 7)->nullable();
                $t->string('representante', 150)->nullable();
                $t->string('representante_dni', 15)->nullable();
                $t->string('direccion', 250)->nullable();
                $t->string('telefono', 30)->nullable();
                $t->string('correo', 150)->nullable();
                $t->boolean('activa')->default(true);
                $t->timestamps();
            });
        }
        if (! $central->hasColumn('clientes', 'marca_id')) {
            $central->table('clientes', fn (Blueprint $t) => $t->unsignedBigInteger('marca_id')->nullable()->index());
        }
        if (! $central->hasColumn('superadmins', 'marca_id')) {
            $central->table('superadmins', fn (Blueprint $t) => $t->unsignedBigInteger('marca_id')->nullable()->index());
        }
    }

    public function down(): void
    {
        $central = Schema::connection('central');
        $central->table('superadmins', fn (Blueprint $t) => $t->dropColumn('marca_id'));
        $central->table('clientes', fn (Blueprint $t) => $t->dropColumn('marca_id'));
        $central->dropIfExists('marcas');
    }
};
