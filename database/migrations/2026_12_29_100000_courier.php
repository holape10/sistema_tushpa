<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Courier / envíos de encomiendas y carga: agencias (propias o de aliados), tarifas por ruta (base, kilo adicional, sobre,
 * reparto a domicilio y seguro sobre el valor declarado), envíos con sus paquetes, clave de recojo, pago en origen o en
 * destino con su comprobante, la línea de tiempo de cada envío (rastreo público) y el menú "Courier".
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cou_config')) {
            Schema::create('cou_config', function (Blueprint $t) {
                $t->string('IdEmpresa', 11)->primary();
                $t->unsignedInteger('divisor_vol')->default(5000);     // peso volumétrico = largo x ancho x alto (cm) / divisor
                $t->decimal('seguro_pct', 5, 2)->default(1);           // % del valor declarado
                $t->decimal('seguro_min', 10, 2)->default(0);
                // Tarifa general (para las rutas que no tienen la suya)
                $t->decimal('base', 10, 2)->default(10);               // incluye los primeros kilos
                $t->decimal('kg_incluidos', 8, 2)->default(1);
                $t->decimal('por_kg', 10, 2)->default(2);              // cada kilo adicional
                $t->decimal('sobre', 10, 2)->default(8);               // documentos en sobre (cada uno)
                $t->decimal('domicilio', 10, 2)->default(5);           // reparto a domicilio en el destino
                $t->unsignedTinyInteger('dias')->default(2);           // tiempo estimado de llegada
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('cou_agencias')) {
            Schema::create('cou_agencias', function (Blueprint $t) {
                $t->increments('age_id');
                $t->string('nombre', 100);
                $t->string('ciudad', 60);
                $t->string('direccion', 200)->nullable();
                $t->string('telefono', 30)->nullable();
                $t->unsignedBigInteger('id_empresa_negocio')->nullable()->index(); // sucursal que la atiende (sin sucursal = aliado)
                $t->boolean('activo')->default(true);
                $t->string('IdEmpresa', 11)->index();
            });
        }
        if (! Schema::hasTable('cou_tarifas')) {
            Schema::create('cou_tarifas', function (Blueprint $t) {
                $t->increments('tar_id');
                $t->unsignedInteger('origen_id');
                $t->unsignedInteger('destino_id');
                $t->decimal('base', 10, 2);
                $t->decimal('kg_incluidos', 8, 2)->default(1);
                $t->decimal('por_kg', 10, 2);
                $t->decimal('sobre', 10, 2);
                $t->decimal('domicilio', 10, 2)->default(0);
                $t->unsignedTinyInteger('dias')->default(2);
                $t->string('IdEmpresa', 11)->index();
                $t->unique(['origen_id', 'destino_id']);
            });
        }
        if (! Schema::hasTable('cou_envios')) {
            Schema::create('cou_envios', function (Blueprint $t) {
                $t->increments('env_id');
                $t->string('codigo', 12)->unique();                    // número de rastreo
                $t->char('clave', 4);                                  // la pide la agencia al entregar
                $t->unsignedInteger('origen_id');
                $t->unsignedInteger('destino_id');
                $t->unsignedInteger('rem_clicod')->nullable();
                $t->string('rem_doc', 15);
                $t->string('rem_nombre', 150);
                $t->string('rem_telefono', 20)->nullable();
                $t->string('des_doc', 15)->nullable();
                $t->string('des_nombre', 150);
                $t->string('des_telefono', 20)->nullable();
                $t->string('entrega', 10)->default('AGENCIA');         // AGENCIA | DOMICILIO
                $t->string('direccion_entrega', 200)->nullable();
                $t->string('contenido', 200)->nullable();
                $t->decimal('valor_declarado', 12, 2)->default(0);
                $t->unsignedSmallInteger('piezas')->default(1);
                $t->decimal('peso_real', 10, 2)->default(0);
                $t->decimal('peso_cobrado', 10, 2)->default(0);
                $t->decimal('flete', 10, 2)->default(0);
                $t->decimal('seguro', 10, 2)->default(0);
                $t->decimal('domicilio', 10, 2)->default(0);
                $t->decimal('total', 10, 2)->default(0);
                $t->string('pago', 7)->default('ORIGEN');              // ORIGEN | DESTINO (paga quien recoge)
                $t->unsignedInteger('IdCpe_cabecera')->nullable()->index();
                $t->string('estado', 12)->default('REGISTRADO');       // REGISTRADO | EN_TRANSITO | EN_DESTINO | EN_REPARTO | ENTREGADO | ANULADO
                $t->date('llegada_estimada')->nullable();
                $t->string('observacion', 255)->nullable();
                $t->string('entregado_a', 150)->nullable();
                $t->string('entregado_doc', 15)->nullable();
                $t->dateTime('entregado')->nullable();
                $t->unsignedInteger('IdUsuario')->nullable();
                $t->unsignedBigInteger('id_empresa_negocio')->index();  // sucursal que lo registró
                $t->dateTime('creado')->nullable();
                $t->index(['origen_id', 'estado']);
                $t->index(['destino_id', 'estado']);
            });
        }
        if (! Schema::hasTable('cou_paquetes')) {
            Schema::create('cou_paquetes', function (Blueprint $t) {
                $t->increments('paq_id');
                $t->unsignedInteger('env_id')->index();
                $t->string('tipo', 8)->default('PAQUETE');             // SOBRE | PAQUETE
                $t->unsignedSmallInteger('cantidad')->default(1);
                $t->decimal('peso', 10, 2)->default(0);                // kg de cada uno
                $t->decimal('largo', 8, 1)->nullable();                // cm
                $t->decimal('ancho', 8, 1)->nullable();
                $t->decimal('alto', 8, 1)->nullable();
                $t->string('descripcion', 100)->nullable();
            });
        }
        if (! Schema::hasTable('cou_eventos')) {
            Schema::create('cou_eventos', function (Blueprint $t) {
                $t->increments('evt_id');
                $t->unsignedInteger('env_id')->index();
                $t->string('estado', 12);
                $t->string('detalle', 200)->nullable();
                $t->unsignedInteger('age_id')->nullable();
                $t->unsignedInteger('IdUsuario')->nullable();
                $t->dateTime('fecha');
            });
        }

        if (! Schema::hasTable('modulos')) {
            return;
        }
        $nuevos = [];
        foreach ([['Envíos', '/courier'], ['Tarifas y agencias', '/courier/tarifas']] as [$nom, $url]) {
            $nuevos[] = DB::table('modulos')->where('mod_url', $url)->value('mod_id')
                ?? DB::table('modulos')->insertGetId(['mod_nom' => $nom, 'mod_url' => $url, 'mod_gen' => 'Courier']);
        }
        // Administradores con el menú de varios rubros (la demo y "Todo el menú") reciben el nuevo menú
        $amplio = DB::table('modulos')->whereIn('mod_url', ['/gimnasio', '/clinica', '/hotel', '/estacionamiento', '/socios', '/pv-grifo', '/pv-farmacia', '/prestamos', '/colegio'])->pluck('mod_id');
        $admins = DB::table('role_user')->where('role_id', 2)->pluck('user_IdUsuario');
        $usuarios = DB::table('modulos_usuario')->whereIn('user_IdUsuario', $admins)->whereIn('mod_id', $amplio)
            ->groupBy('user_IdUsuario')->havingRaw('COUNT(DISTINCT mod_id) >= 3')->pluck('user_IdUsuario');
        foreach ($usuarios as $usuario) {
            foreach ($nuevos as $mod) {
                if (! DB::table('modulos_usuario')->where('user_IdUsuario', $usuario)->where('mod_id', $mod)->exists()) {
                    DB::table('modulos_usuario')->insert(['user_IdUsuario' => $usuario, 'mod_id' => $mod, 'created_at' => now(), 'updated_at' => now()]);
                }
            }
        }
    }

    public function down(): void
    {
        $mods = DB::table('modulos')->where('mod_gen', 'Courier')->pluck('mod_id');
        DB::table('modulos_usuario')->whereIn('mod_id', $mods)->delete();
        DB::table('modulos')->whereIn('mod_id', $mods)->delete();
        foreach (['cou_eventos', 'cou_paquetes', 'cou_envios', 'cou_tarifas', 'cou_agencias', 'cou_config'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
