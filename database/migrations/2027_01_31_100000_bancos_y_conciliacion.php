<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bancos y conciliación bancaria:
 *  - bancos_cuentas: cada cuenta bancaria con su cuenta contable (104x). Los medios de pago que caen en ella
 *    (medios_pagos.cuenta_bancaria_id) usan esa cuenta al centralizar cobros y pagos.
 *  - bancos_movimientos: lo propio del banco (comisiones, ITF, intereses, depósitos, retiros, transferencias); cada uno
 *    con su asiento contable.
 *  - bancos_extracto: las líneas del estado de cuenta que se suben en Excel.
 *  - bancos_conciliados: qué líneas del banco se juntaron con qué movimientos del sistema (por grupo: uno con varios).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('bancos_cuentas')) {
            Schema::create('bancos_cuentas', function (Blueprint $t) {
                $t->id();
                $t->string('IdEmpresa', 11)->index();
                $t->string('banco', 60);
                $t->string('numero', 40);
                $t->string('moneda', 3)->default('PEN');
                $t->string('cuenta_contable', 12);
                // Desde cuándo se lleva: el saldo de ese día según el banco
                $t->decimal('saldo_inicial', 14, 2)->default(0);
                $t->date('fecha_inicio');
                $t->boolean('activo')->default(true);
                $t->timestamps();
            });
        }
        if (Schema::hasTable('medios_pagos') && ! Schema::hasColumn('medios_pagos', 'cuenta_bancaria_id')) {
            Schema::table('medios_pagos', fn (Blueprint $t) => $t->unsignedBigInteger('cuenta_bancaria_id')->nullable());
        }
        if (! Schema::hasTable('bancos_movimientos')) {
            Schema::create('bancos_movimientos', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('cuenta_id')->index();
                $t->date('fecha');
                $t->string('tipo', 20);
                $t->string('descripcion', 200);
                $t->string('operacion', 40)->nullable();
                // + entra al banco, − sale del banco
                $t->decimal('monto', 14, 2);
                $t->string('contrapartida', 12);
                $t->unsignedBigInteger('cuenta_destino_id')->nullable();
                $t->unsignedBigInteger('par_id')->nullable();
                $t->unsignedBigInteger('asiento_id')->nullable();
                $t->string('estado', 12)->default('REGISTRADO');
                $t->unsignedInteger('IdUsuario')->nullable();
                $t->timestamps();
                $t->index(['cuenta_id', 'fecha']);
            });
        }
        if (! Schema::hasTable('bancos_extracto')) {
            Schema::create('bancos_extracto', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('cuenta_id');
                $t->date('fecha');
                $t->string('descripcion', 255)->nullable();
                $t->string('operacion', 40)->nullable();
                $t->decimal('monto', 14, 2);
                $t->decimal('saldo', 14, 2)->nullable();
                // Para no cargar dos veces la misma línea si se vuelve a subir el archivo
                $t->string('huella', 40);
                $t->unsignedInteger('IdUsuario')->nullable();
                $t->timestamp('created_at')->nullable();
                $t->unique(['cuenta_id', 'huella']);
                $t->index(['cuenta_id', 'fecha']);
            });
        }
        if (! Schema::hasTable('bancos_conciliados')) {
            Schema::create('bancos_conciliados', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('cuenta_id')->index();
                $t->string('grupo', 36)->index();
                $t->unsignedBigInteger('extracto_id')->nullable()->unique();
                $t->string('origen', 10)->nullable();
                $t->unsignedBigInteger('origen_id')->nullable();
                $t->decimal('monto', 14, 2);
                $t->unsignedInteger('IdUsuario')->nullable();
                $t->timestamp('created_at')->nullable();
                $t->unique(['origen', 'origen_id']);
            });
        }

        if (Schema::hasTable('modulos') && ! DB::table('modulos')->where('mod_url', '/bancos')->exists()) {
            $grupo = DB::table('modulos')->where('mod_url', '/flujo-caja')->value('mod_gen')
                ?? DB::table('modulos')->where('mod_url', '/cuentas/cobrar')->value('mod_gen') ?? 'Cuentas';
            $id = DB::table('modulos')->insertGetId(['mod_nom' => 'Bancos y conciliación', 'mod_url' => '/bancos', 'mod_gen' => $grupo]);
            if (Schema::hasTable('role_user') && Schema::hasTable('modulos_usuario')) {
                foreach (DB::table('role_user')->where('role_id', 2)->pluck('user_IdUsuario')->unique() as $usuario) {
                    DB::table('modulos_usuario')->insertOrIgnore(['user_IdUsuario' => $usuario, 'mod_id' => $id, 'created_at' => now(), 'updated_at' => now()]);
                }
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('modulos') && ($id = DB::table('modulos')->where('mod_url', '/bancos')->value('mod_id'))) {
            DB::table('modulos_usuario')->where('mod_id', $id)->delete();
            DB::table('modulos')->where('mod_id', $id)->delete();
        }
        if (Schema::hasColumn('medios_pagos', 'cuenta_bancaria_id')) {
            Schema::table('medios_pagos', fn (Blueprint $t) => $t->dropColumn('cuenta_bancaria_id'));
        }
        Schema::dropIfExists('bancos_conciliados');
        Schema::dropIfExists('bancos_extracto');
        Schema::dropIfExists('bancos_movimientos');
        Schema::dropIfExists('bancos_cuentas');
    }
};
