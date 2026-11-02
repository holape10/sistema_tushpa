<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Promociones automáticas del restaurante (2x1, 3x2, "3 por S/ 40"): se aplican solas al cobrar (mesa, llevar,
 * delivery y PV Restaurante) en los días y horas que diga cada una. Ante SUNAT van como descuento en cada línea.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('promociones_venta')) {
            Schema::create('promociones_venta', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('id_empresa_negocio')->index();
                $t->string('nombre', 80);
                $t->string('tipo', 12);                     // LLEVA_PAGA (2x1, 3x2) | POR_PRECIO (3 por S/ 40)
                $t->unsignedSmallInteger('lleva');          // cuántas unidades forman la promo
                $t->unsignedSmallInteger('paga')->nullable();
                $t->decimal('precio', 12, 2)->nullable();
                $t->string('dias', 7)->default('1234567');  // 1 = lunes ... 7 = domingo
                $t->time('hora_inicio')->nullable();        // sin horas = todo el día
                $t->time('hora_fin')->nullable();
                $t->date('desde')->nullable();
                $t->date('hasta')->nullable();
                $t->boolean('activo')->default(true);
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('promocion_venta_producto')) {
            Schema::create('promocion_venta_producto', function (Blueprint $t) {
                $t->unsignedBigInteger('promocion_id');
                $t->unsignedBigInteger('IdProducto');
                $t->primary(['promocion_id', 'IdProducto']);
            });
        }
        // Lo que cada comprobante ahorró por promociones (para el ticket y el reporte)
        if (! Schema::hasTable('cpe_promociones')) {
            Schema::create('cpe_promociones', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('IdCpe_cabecera')->index();
                $t->unsignedBigInteger('promocion_id')->index();
                $t->string('nombre', 80);
                $t->unsignedSmallInteger('veces');
                $t->decimal('ahorro', 12, 2);
            });
        }
        if (! Schema::hasColumn('cpe_cabecera', 'promo_descuento')) {
            Schema::table('cpe_cabecera', fn (Blueprint $t) => $t->decimal('promo_descuento', 12, 2)->default(0));
        }
        if (Schema::hasTable('modulos') && ! DB::table('modulos')->where('mod_url', '/promociones')->exists()) {
            $id = DB::table('modulos')->insertGetId(['mod_nom' => 'Promociones 2x1', 'mod_url' => '/promociones', 'mod_gen' => 'Restaurante']);
            // En el menú de los administradores y de quien ya maneja los cupones
            $cupones = DB::table('modulos')->where('mod_url', '/cupones')->value('mod_id');
            $usuarios = DB::table('role_user')->where('role_id', 2)->pluck('user_IdUsuario')
                ->merge($cupones ? DB::table('modulos_usuario')->where('mod_id', $cupones)->pluck('user_IdUsuario') : [])->unique();
            foreach ($usuarios as $u) {
                DB::table('modulos_usuario')->insertOrIgnore(['user_IdUsuario' => $u, 'mod_id' => $id, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('modulos')) {
            $id = DB::table('modulos')->where('mod_url', '/promociones')->value('mod_id');
            if ($id) {
                DB::table('modulos_usuario')->where('mod_id', $id)->delete();
                DB::table('modulos')->where('mod_id', $id)->delete();
            }
        }
        if (Schema::hasColumn('cpe_cabecera', 'promo_descuento')) {
            Schema::table('cpe_cabecera', fn (Blueprint $t) => $t->dropColumn('promo_descuento'));
        }
        Schema::dropIfExists('cpe_promociones');
        Schema::dropIfExists('promocion_venta_producto');
        Schema::dropIfExists('promociones_venta');
    }
};
