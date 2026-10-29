<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Courier, etapas 2 y 3:
 *  - manifiestos de carga (cada viaje con sus envíos; se cargan y se reciben escaneando bulto por bulto);
 *  - reparto a domicilio con los motorizados (foto y firma de la entrega, lo que cobró y su liquidación en caja);
 *  - liquidación con las agencias aliadas (su comisión);
 *  - clientes empresa con crédito (envíos a crédito y una factura por el estado de cuenta);
 *  - pre-registro y cotización desde la web, devoluciones al remitente y reclamos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cou_envios', function (Blueprint $t) {
            if (! Schema::hasColumn('cou_envios', 'man_id')) {
                $t->unsignedInteger('man_id')->nullable()->index();          // manifiesto en el que viaja
                $t->unsignedSmallInteger('bultos_recibidos')->nullable();    // escaneados al llegar
                $t->unsignedInteger('mot_id')->nullable()->index();          // motorizado que lo reparte
                $t->unsignedTinyInteger('intentos')->default(0);             // visitas sin poder entregar
                $t->decimal('cobrado_rep', 10, 2)->nullable();               // lo que cobró el motorizado (por liquidar en caja)
                $t->string('medio_rep', 20)->nullable();
                $t->string('foto_entrega', 150)->nullable();
                $t->string('firma_entrega', 150)->nullable();
                $t->unsignedInteger('liq_id')->nullable()->index();          // liquidación con el aliado
                $t->unsignedInteger('devuelto_de')->nullable();              // envío original que se devuelve
            }
        });
        Schema::table('cou_agencias', function (Blueprint $t) {
            if (! Schema::hasColumn('cou_agencias', 'comision_pct')) {
                $t->decimal('comision_pct', 5, 2)->default(0);              // lo que gana el aliado por cada envío que entrega
            }
        });
        Schema::table('cou_config', function (Blueprint $t) {
            if (! Schema::hasColumn('cou_config', 'dias_almacen')) {
                $t->unsignedSmallInteger('dias_almacen')->default(15);      // desde cuántos días sin recoger es "rezagado"
                $t->boolean('web_publico')->default(true);                 // cotizar, pre-registrar y reclamar desde la web
            }
        });

        if (! Schema::hasTable('cou_manifiestos')) {
            Schema::create('cou_manifiestos', function (Blueprint $t) {
                $t->increments('man_id');
                $t->unsignedInteger('numero');
                $t->unsignedInteger('origen_id');
                $t->unsignedInteger('destino_id')->nullable();               // sin destino = el viaje deja carga en varias agencias
                $t->string('medio', 10)->default('TERRESTRE');               // TERRESTRE | AEREO | FLUVIAL
                $t->string('transportista', 150)->nullable();                // empresa de transporte, aerolínea o embarcación
                $t->string('vehiculo', 60)->nullable();                      // placa, vuelo o nombre de la nave
                $t->string('conductor', 150)->nullable();
                $t->dateTime('salida')->nullable();
                $t->dateTime('llegada')->nullable();
                $t->string('estado', 10)->default('ABIERTO');                // ABIERTO | EN_VIAJE | RECIBIDO | ANULADO
                $t->string('observacion', 255)->nullable();
                $t->unsignedInteger('IdUsuario')->nullable();
                $t->string('IdEmpresa', 11)->index();
                $t->dateTime('creado')->nullable();
            });
        }
        if (! Schema::hasTable('cou_liquidaciones')) {
            Schema::create('cou_liquidaciones', function (Blueprint $t) {
                $t->increments('liq_id');
                $t->unsignedInteger('age_id')->index();
                $t->unsignedInteger('envios');
                $t->decimal('cobrado', 12, 2)->default(0);                   // lo que el aliado cobró al entregar (es nuestro)
                $t->decimal('comision', 12, 2)->default(0);                  // lo que ganó el aliado
                $t->decimal('saldo', 12, 2)->default(0);                     // + nos paga el aliado, - le pagamos
                $t->string('observacion', 255)->nullable();
                $t->unsignedInteger('IdUsuario')->nullable();
                $t->string('IdEmpresa', 11)->index();
                $t->dateTime('creado');
            });
        }
        if (! Schema::hasTable('cou_creditos')) {
            Schema::create('cou_creditos', function (Blueprint $t) {
                $t->increments('cre_id');
                $t->unsignedInteger('clicod')->unique();
                $t->decimal('limite', 12, 2)->default(0);                    // 0 = sin límite
                $t->unsignedSmallInteger('dias')->default(30);               // plazo de la factura
                $t->boolean('activo')->default(true);
                $t->string('IdEmpresa', 11)->index();
                $t->dateTime('creado')->nullable();
            });
        }
        if (! Schema::hasTable('cou_prerregistros')) {
            Schema::create('cou_prerregistros', function (Blueprint $t) {
                $t->increments('pre_id');
                $t->char('codigo', 6)->unique();
                $t->text('datos');                                           // JSON con la ruta, remitente, destinatario y paquetes
                $t->string('estado', 10)->default('PENDIENTE');              // PENDIENTE | USADO
                $t->unsignedInteger('env_id')->nullable();
                $t->string('ip', 45)->nullable();
                $t->dateTime('creado');
            });
        }
        if (! Schema::hasTable('cou_reclamos')) {
            Schema::create('cou_reclamos', function (Blueprint $t) {
                $t->increments('rec_id');
                $t->unsignedInteger('numero');
                $t->unsignedInteger('env_id')->index();
                $t->string('tipo', 10);                                      // DANO | PERDIDA | DEMORA | ATENCION | OTRO
                $t->text('detalle');
                $t->string('pedido', 255)->nullable();                       // lo que pide el cliente
                $t->string('nombre', 150);
                $t->string('documento', 15)->nullable();
                $t->string('telefono', 20)->nullable();
                $t->string('correo', 120)->nullable();
                $t->string('origen', 8)->default('WEB');                     // WEB | AGENCIA
                $t->string('estado', 10)->default('ABIERTO');                // ABIERTO | EN_PROCESO | RESUELTO | RECHAZADO
                $t->text('respuesta')->nullable();
                $t->decimal('compensacion', 10, 2)->default(0);
                $t->unsignedInteger('IdUsuario')->nullable();
                $t->string('IdEmpresa', 11)->index();
                $t->dateTime('creado');
                $t->dateTime('resuelto')->nullable();
            });
        }

        if (! Schema::hasTable('modulos')) {
            return;
        }
        $nuevos = [];
        foreach ([['Manifiestos de carga', '/courier/manifiestos'], ['Reparto a domicilio', '/courier/reparto'], ['Reportes de envíos', '/courier/reportes'],
            ['Clientes con crédito', '/courier/creditos'], ['Reclamos', '/courier/reclamos']] as [$nom, $url]) {
            $nuevos[] = DB::table('modulos')->where('mod_url', $url)->value('mod_id')
                ?? DB::table('modulos')->insertGetId(['mod_nom' => $nom, 'mod_url' => $url, 'mod_gen' => 'Courier']);
        }
        // Quien ya tiene "Envíos" recibe lo nuevo del courier
        $envios = DB::table('modulos')->where('mod_url', '/courier')->value('mod_id');
        foreach (DB::table('modulos_usuario')->where('mod_id', $envios)->pluck('user_IdUsuario') as $usuario) {
            foreach ($nuevos as $mod) {
                if (! DB::table('modulos_usuario')->where('user_IdUsuario', $usuario)->where('mod_id', $mod)->exists()) {
                    DB::table('modulos_usuario')->insert(['user_IdUsuario' => $usuario, 'mod_id' => $mod, 'created_at' => now(), 'updated_at' => now()]);
                }
            }
        }
    }

    public function down(): void
    {
        $mods = DB::table('modulos')->whereIn('mod_url', ['/courier/manifiestos', '/courier/reparto', '/courier/reportes', '/courier/creditos', '/courier/reclamos'])->pluck('mod_id');
        DB::table('modulos_usuario')->whereIn('mod_id', $mods)->delete();
        DB::table('modulos')->whereIn('mod_id', $mods)->delete();
        foreach (['cou_reclamos', 'cou_prerregistros', 'cou_creditos', 'cou_liquidaciones', 'cou_manifiestos'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::table('cou_config', fn (Blueprint $t) => $t->dropColumn(['dias_almacen', 'web_publico']));
        Schema::table('cou_agencias', fn (Blueprint $t) => $t->dropColumn('comision_pct'));
        Schema::table('cou_envios', fn (Blueprint $t) => $t->dropColumn(['man_id', 'bultos_recibidos', 'mot_id', 'intentos', 'cobrado_rep', 'medio_rep',
            'foto_entrega', 'firma_entrega', 'liq_id', 'devuelto_de']));
    }
};
