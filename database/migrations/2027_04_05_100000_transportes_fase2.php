<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Transportes, fase 2 (todo nuevo, nada de lo que existe cambia):
 *  - tra_pasajes: tramo (de qué parada a qué parada viaja), reservas (hasta cuándo), código para el QR del embarque,
 *    de dónde vino (caja o internet) y si ya subió
 *  - tra_gastos: los gastos de cada salida (combustible, peajes, viáticos) para la liquidación
 *  - tra_web_usuarios: los pasajeros que reservan por internet con su cuenta
 *  - tra_config: minutos de las reservas y la venta por internet de cada sucursal
 *  - menú: Embarque y Reporte de viajes
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tra_pasajes')) {
            Schema::table('tra_pasajes', function (Blueprint $t) {
                if (! Schema::hasColumn('tra_pasajes', 'tramo_desde')) {
                    $t->unsignedTinyInteger('tramo_desde')->default(0);
                    $t->unsignedTinyInteger('tramo_hasta')->default(99);
                    $t->string('codigo', 12)->nullable()->unique();
                    $t->string('grupo', 12)->nullable()->index();
                    $t->dateTime('reservado_hasta')->nullable();
                    $t->string('origen_venta', 6)->default('CAJA');
                    $t->unsignedInteger('web_usuario_id')->nullable()->index();
                    $t->string('medio_web', 40)->nullable();
                    $t->string('operacion_web', 40)->nullable();
                    $t->timestamp('embarcado_at')->nullable();
                    $t->unsignedInteger('embarcado_por')->nullable();
                    $t->index(['salida_id', 'estado']);
                }
            });
            // Los pasajes que ya había también tienen su código para el QR
            foreach (DB::table('tra_pasajes')->whereNull('codigo')->pluck('id') as $id) {
                DB::table('tra_pasajes')->where('id', $id)->update(['codigo' => strtoupper(Str::random(10)), 'grupo' => strtoupper(Str::random(10))]);
            }
        }
        if (! Schema::hasTable('tra_gastos')) {
            Schema::create('tra_gastos', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('salida_id')->index();
                $t->string('concepto', 80);
                $t->decimal('monto', 10, 2);
                $t->unsignedInteger('IdUsuario')->nullable();
                $t->timestamp('created_at')->useCurrent();
            });
        }
        if (! Schema::hasTable('tra_web_usuarios')) {
            Schema::create('tra_web_usuarios', function (Blueprint $t) {
                $t->id();
                $t->char('tdicod', 1)->default('1');
                $t->string('doc', 15)->unique();
                $t->string('nombre', 150);
                $t->string('celular', 20);
                $t->string('email', 120)->nullable();
                $t->string('password');
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('tra_config')) {
            Schema::create('tra_config', function (Blueprint $t) {
                $t->unsignedBigInteger('id_empresa_negocio')->primary();
                $t->unsignedSmallInteger('reserva_min')->default(120);
                $t->boolean('web_activo')->default(false);
                $t->unsignedSmallInteger('web_reserva_min')->default(60);
                $t->string('web_texto', 255)->nullable();
                $t->timestamps();
            });
        }

        if (! Schema::hasTable('modulos')) {
            return;
        }
        // Los que ya venden pasajes reciben el embarque y el reporte
        $conTransportes = DB::table('modulos_usuario')->whereIn('mod_id', DB::table('modulos')->where('mod_url', '/transportes')->pluck('mod_id'))->pluck('user_IdUsuario');
        foreach ([['Embarque', '/transportes/embarque'], ['Reporte de viajes', '/transportes/reporte']] as [$nom, $url]) {
            $mod = DB::table('modulos')->where('mod_url', $url)->value('mod_id')
                ?? DB::table('modulos')->insertGetId(['mod_nom' => $nom, 'mod_url' => $url, 'mod_gen' => 'Transportes']);
            foreach ($conTransportes as $usuario) {
                DB::table('modulos_usuario')->insertOrIgnore(['user_IdUsuario' => $usuario, 'mod_id' => $mod]);
            }
        }
    }

    public function down(): void
    {
        $mods = DB::table('modulos')->whereIn('mod_url', ['/transportes/embarque', '/transportes/reporte'])->pluck('mod_id');
        DB::table('modulos_usuario')->whereIn('mod_id', $mods)->delete();
        DB::table('modulos')->whereIn('mod_id', $mods)->delete();
        Schema::dropIfExists('tra_config');
        Schema::dropIfExists('tra_web_usuarios');
        Schema::dropIfExists('tra_gastos');
        if (Schema::hasColumn('tra_pasajes', 'tramo_desde')) {
            Schema::table('tra_pasajes', function (Blueprint $t) {
                $t->dropIndex(['salida_id', 'estado']);
                $t->dropUnique(['codigo']);
                $t->dropIndex(['grupo']);
                $t->dropIndex(['web_usuario_id']);
                $t->dropColumn(['tramo_desde', 'tramo_hasta', 'codigo', 'grupo', 'reservado_hasta', 'origen_venta', 'web_usuario_id', 'medio_web',
                    'operacion_web', 'embarcado_at', 'embarcado_por']);
            });
        }
    }
};
