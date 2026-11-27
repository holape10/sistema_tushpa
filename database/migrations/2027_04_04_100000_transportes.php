<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Transportes (terrestre, fluvial, aéreo, ferroviario): venta de pasajes con número de asiento sobre el plano de cada
 * unidad. Tablas nuevas (no toca nada de lo que ya existe):
 *  - tra_rutas: origen → destino con sus paradas y el precio de siempre
 *  - tra_unidades: bus, lancha, avioneta o vagón con su plano de asientos (pisos o cubiertas; ventana o pasillo; categoría)
 *  - tra_salidas: una ruta en una unidad, a una fecha y hora, con su precio por categoría
 *  - tra_pasajes: cada asiento vendido, con su pasajero y su comprobante
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tra_rutas')) {
            Schema::create('tra_rutas', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('id_empresa_negocio')->index();
                $t->string('origen', 80);
                $t->string('destino', 80);
                $t->string('paradas', 255)->nullable();
                $t->unsignedSmallInteger('duracion_min')->nullable();
                $t->decimal('precio', 10, 2)->default(0);
                $t->boolean('activo')->default(true);
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('tra_unidades')) {
            Schema::create('tra_unidades', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('id_empresa_negocio')->index();
                $t->string('tipo', 12)->default('TERRESTRE');
                $t->string('nombre', 80);
                $t->string('placa', 20)->nullable();
                $t->longText('plano');
                $t->unsignedSmallInteger('asientos')->default(0);
                $t->boolean('activo')->default(true);
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('tra_salidas')) {
            Schema::create('tra_salidas', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('id_empresa_negocio');
                $t->unsignedBigInteger('ruta_id');
                $t->unsignedBigInteger('unidad_id');
                $t->dateTime('salida');
                $t->string('conductor', 120)->nullable();
                $t->decimal('precio', 10, 2)->default(0);
                $t->text('precios')->nullable();
                $t->string('estado', 12)->default('PROGRAMADA');
                $t->string('observacion', 200)->nullable();
                $t->unsignedInteger('IdUsuario')->nullable();
                $t->timestamps();
                $t->index(['id_empresa_negocio', 'salida']);
            });
        }
        if (! Schema::hasTable('tra_pasajes')) {
            Schema::create('tra_pasajes', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('salida_id')->index();
                $t->string('asiento', 8);
                $t->unsignedTinyInteger('piso')->default(1);
                $t->string('categoria', 20)->nullable();
                $t->string('posicion', 1)->nullable();
                $t->decimal('precio', 10, 2);
                $t->char('tdicod', 1)->default('1');
                $t->string('doc', 15);
                $t->string('nombre', 150);
                $t->string('telefono', 20)->nullable();
                $t->unsignedInteger('IdCpe_cabecera')->nullable()->index();
                $t->string('estado', 10)->default('VENDIDO');
                $t->unsignedInteger('IdUsuario')->nullable();
                $t->timestamps();
            });
        }

        if (! Schema::hasTable('modulos')) {
            return;
        }
        $nuevos = [];
        foreach ([['Venta de pasajes', '/transportes'], ['Salidas', '/transportes/salidas'], ['Unidades y planos', '/transportes/unidades'],
            ['Rutas', '/transportes/rutas']] as [$nom, $url]) {
            $nuevos[] = DB::table('modulos')->where('mod_url', $url)->value('mod_id')
                ?? DB::table('modulos')->insertGetId(['mod_nom' => $nom, 'mod_url' => $url, 'mod_gen' => 'Transportes']);
        }
        // Administradores con el menú de varios rubros (la demo y "Todo el menú") reciben el nuevo menú
        $amplio = DB::table('modulos')->whereIn('mod_url', ['/gimnasio', '/clinica', '/hotel', '/estacionamiento', '/socios', '/pv-grifo', '/pv-farmacia', '/prestamos', '/colegio', '/courier'])->pluck('mod_id');
        $admins = DB::table('role_user')->where('role_id', 2)->pluck('user_IdUsuario');
        $usuarios = DB::table('modulos_usuario')->whereIn('user_IdUsuario', $admins)->whereIn('mod_id', $amplio)
            ->groupBy('user_IdUsuario')->havingRaw('COUNT(DISTINCT mod_id) >= 3')->pluck('user_IdUsuario');
        foreach ($usuarios as $usuario) {
            foreach ($nuevos as $mod) {
                DB::table('modulos_usuario')->insertOrIgnore(['user_IdUsuario' => $usuario, 'mod_id' => $mod]);
            }
        }
    }

    public function down(): void
    {
        $mods = DB::table('modulos')->where('mod_gen', 'Transportes')->pluck('mod_id');
        DB::table('modulos_usuario')->whereIn('mod_id', $mods)->delete();
        DB::table('modulos')->whereIn('mod_id', $mods)->delete();
        foreach (['tra_pasajes', 'tra_salidas', 'tra_unidades', 'tra_rutas'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
