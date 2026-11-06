<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Centros de costo y activos fijos:
 *  - centros_costo: local, área o proyecto, con su cuenta de destino de la clase 9 (94 administración, 95 ventas…).
 *    Los gastos llevan su centro (gastos.centro_costo_id); por defecto, el del local donde se registran.
 *  - activos_fijos: el registro de cada bien con su categoría SUNAT, tasa, cuentas (33x, 3913, 681) y centro de costo.
 *  - activos_depreciacion: la depreciación de cada mes por activo (con el asiento del mes).
 * Las opciones del menú van con Gastos y se les dan a los administradores.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('centros_costo')) {
            Schema::create('centros_costo', function (Blueprint $t) {
                $t->id();
                $t->string('IdEmpresa', 11);
                $t->string('codigo', 10);
                $t->string('nombre', 100);
                // LOCAL (una sucursal: sus ventas y costo de ventas también cuentan) · AREA · PROYECTO
                $t->string('tipo', 10)->default('AREA');
                $t->unsignedInteger('id_empresa_negocio')->nullable();
                $t->string('cuenta_destino', 12)->default('94');
                $t->boolean('activo')->default(true);
                $t->timestamps();
                $t->unique(['IdEmpresa', 'codigo']);
            });
        }
        if (Schema::hasTable('gastos') && ! Schema::hasColumn('gastos', 'centro_costo_id')) {
            Schema::table('gastos', fn (Blueprint $t) => $t->unsignedBigInteger('centro_costo_id')->nullable()->index());
        }
        if (! Schema::hasTable('activos_fijos')) {
            Schema::create('activos_fijos', function (Blueprint $t) {
                $t->id();
                $t->string('IdEmpresa', 11);
                $t->unsignedInteger('id_empresa_negocio')->nullable();
                $t->string('codigo', 20);
                $t->string('descripcion', 200);
                $t->string('categoria', 20);
                $t->string('marca_modelo', 100)->nullable();
                $t->string('serie', 60)->nullable();
                $t->string('ubicacion', 100)->nullable();
                $t->string('responsable', 100)->nullable();
                $t->date('fecha_adquisicion');
                // Se deprecia desde el mes en que se empieza a usar
                $t->date('fecha_uso');
                $t->decimal('costo', 14, 2);
                $t->decimal('valor_residual', 14, 2)->default(0);
                $t->decimal('tasa', 5, 2);
                // Lo que ya estaba depreciado antes de llevarlo en el sistema
                $t->decimal('depreciacion_inicial', 14, 2)->default(0);
                $t->string('cuenta_activo', 12);
                $t->string('cuenta_depreciacion', 12)->default('3913');
                $t->string('cuenta_gasto', 12)->default('681');
                $t->unsignedBigInteger('centro_costo_id')->nullable();
                $t->string('documento', 60)->nullable();
                $t->string('estado', 10)->default('ACTIVO');
                $t->date('fecha_baja')->nullable();
                $t->string('motivo_baja', 255)->nullable();
                $t->unsignedBigInteger('asiento_alta_id')->nullable();
                $t->unsignedBigInteger('asiento_baja_id')->nullable();
                $t->unsignedInteger('IdUsuario')->nullable();
                $t->timestamps();
                $t->unique(['IdEmpresa', 'codigo']);
            });
        }
        if (! Schema::hasTable('activos_depreciacion')) {
            Schema::create('activos_depreciacion', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('activo_id');
                $t->string('IdEmpresa', 11);
                $t->char('periodo', 6);
                $t->decimal('monto', 14, 2);
                $t->unsignedBigInteger('asiento_id')->nullable();
                $t->timestamp('created_at')->nullable();
                $t->unique(['activo_id', 'periodo']);
                $t->index(['IdEmpresa', 'periodo']);
            });
        }

        if (Schema::hasTable('modulos')) {
            $grupo = DB::table('modulos')->where('mod_url', '/gastos')->value('mod_gen') ?? 'Compras';
            foreach (['/centros-costo' => 'Centros de costo', '/activos-fijos' => 'Activos fijos'] as $url => $nombre) {
                if (DB::table('modulos')->where('mod_url', $url)->exists()) {
                    continue;
                }
                $id = DB::table('modulos')->insertGetId(['mod_nom' => $nombre, 'mod_url' => $url, 'mod_gen' => $grupo]);
                if (Schema::hasTable('role_user') && Schema::hasTable('modulos_usuario')) {
                    foreach (DB::table('role_user')->where('role_id', 2)->pluck('user_IdUsuario')->unique() as $usuario) {
                        DB::table('modulos_usuario')->insertOrIgnore(['user_IdUsuario' => $usuario, 'mod_id' => $id, 'created_at' => now(), 'updated_at' => now()]);
                    }
                }
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('modulos')) {
            $ids = DB::table('modulos')->whereIn('mod_url', ['/centros-costo', '/activos-fijos'])->pluck('mod_id');
            DB::table('modulos_usuario')->whereIn('mod_id', $ids)->delete();
            DB::table('modulos')->whereIn('mod_id', $ids)->delete();
        }
        if (Schema::hasColumn('gastos', 'centro_costo_id')) {
            Schema::table('gastos', fn (Blueprint $t) => $t->dropColumn('centro_costo_id'));
        }
        Schema::dropIfExists('activos_depreciacion');
        Schema::dropIfExists('activos_fijos');
        Schema::dropIfExists('centros_costo');
    }
};
