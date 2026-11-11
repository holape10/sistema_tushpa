<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Arqueo de caja: contar el efectivo de un turno abierto en cualquier momento (arqueo sorpresa, cambio de cajero o a
 * media jornada) sin cerrarlo. Queda lo que debía haber, lo contado, la diferencia y el detalle de billetes y monedas.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('arqueos_caja')) {
            Schema::create('arqueos_caja', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('id_turno')->index();
                $t->unsignedInteger('id_empresa_negocio')->index();
                $t->unsignedInteger('IdUsuario');              // quien contó
                $t->unsignedInteger('cajero')->nullable();     // dueño del turno
                $t->decimal('esperado', 12, 2);
                $t->decimal('contado', 12, 2);
                $t->decimal('diferencia', 12, 2);
                $t->text('billetes')->nullable();              // {campo: cantidad}
                $t->string('observacion', 300)->nullable();
                $t->timestamp('created_at')->nullable();
            });
        }

        if (Schema::hasTable('modulos') && ! DB::table('modulos')->where('mod_url', '/arqueos')->exists()) {
            $grupo = DB::table('modulos')->where('mod_url', '/turnos/listado')->value('mod_gen') ?? 'Ventas';
            $id = DB::table('modulos')->insertGetId(['mod_nom' => 'Arqueo de caja', 'mod_url' => '/arqueos', 'mod_gen' => $grupo]);
            // Lo reciben quienes ya manejan la caja (con "Caja" en su menú)
            $caja = DB::table('modulos')->where('mod_url', '/turnos')->value('mod_id');
            if ($caja && Schema::hasTable('modulos_usuario')) {
                foreach (DB::table('modulos_usuario')->where('mod_id', $caja)->pluck('user_IdUsuario')->unique() as $usuario) {
                    DB::table('modulos_usuario')->insertOrIgnore(['user_IdUsuario' => $usuario, 'mod_id' => $id, 'created_at' => now(), 'updated_at' => now()]);
                }
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('modulos') && ($id = DB::table('modulos')->where('mod_url', '/arqueos')->value('mod_id'))) {
            DB::table('modulos_usuario')->where('mod_id', $id)->delete();
            DB::table('modulos')->where('mod_id', $id)->delete();
        }
        Schema::dropIfExists('arqueos_caja');
    }
};
