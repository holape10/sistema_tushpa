<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Clínica:
 * - clinica_tratamientos: el tratamiento del paciente (ej. ENDODONCIA PIEZA 14 + CORONA) con su costo total.
 * - clinica_tratamiento_registros: cada sesión: fecha y hora, lo realizado o las indicaciones, el adelanto (se cobra en
 *   caja como un pedido 'Clinica'), el saldo que queda y la firma del paciente en la tablet. Saldo 0 = tratamiento cancelado.
 * - Menú "Especialidades" (administrador) y "Registro de Tratamientos" (quienes usan la clínica).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('clinica_tratamientos')) {
            Schema::create('clinica_tratamientos', function (Blueprint $t) {
                $t->increments('tra_id');
                $t->unsignedInteger('his_cli_id')->index();
                $t->unsignedInteger('esp_id')->nullable()->index();
                $t->string('descripcion', 200);
                $t->decimal('costo', 12, 2)->default(0);
                $t->string('estado', 12)->default('EN CURSO');   // EN CURSO | CANCELADO
                $t->dateTime('cancelado')->nullable();            // cuándo quedó en saldo 0
                $t->unsignedInteger('doctor')->nullable();
                $t->unsignedInteger('IdUsuario')->nullable();
                $t->unsignedBigInteger('id_empresa_negocio')->index();
                $t->timestamp('creado')->nullable()->useCurrent();
            });
        }
        if (! Schema::hasTable('clinica_tratamiento_registros')) {
            Schema::create('clinica_tratamiento_registros', function (Blueprint $t) {
                $t->increments('reg_id');
                $t->unsignedInteger('tra_id')->index();
                $t->dateTime('fecha_hora');
                $t->text('detalle')->nullable();                 // tratamiento realizado o indicaciones
                $t->decimal('adelanto', 12, 2)->default(0);       // lo que paga en esta sesión
                $t->decimal('saldo', 12, 2)->default(0);          // lo que queda después de este pago
                $t->string('firma', 120)->nullable();             // archivo PNG de la firma del paciente
                $t->unsignedInteger('ped_id')->nullable();        // pedido 'Clinica' que recepción cobra en caja
                $t->unsignedInteger('IdUsuario')->nullable();
                $t->timestamp('creado')->nullable()->useCurrent();
            });
        }

        if (! Schema::hasTable('modulos')) {
            return;
        }
        $clinica = DB::table('modulos')->where('mod_url', '/clinica')->value('mod_id');
        $usuariosClinica = $clinica ? DB::table('modulos_usuario')->where('mod_id', $clinica)->pluck('user_IdUsuario')->unique() : collect();
        $admins = DB::table('role_user')->where('role_id', 2)->pluck('user_IdUsuario');
        foreach ([['Especialidades', '/clinica/especialidades', true], ['Registro de Tratamientos', '/clinica/tratamientos', false]] as [$nombre, $url, $soloAdmin]) {
            $id = DB::table('modulos')->where('mod_url', $url)->value('mod_id')
                ?? DB::table('modulos')->insertGetId(['mod_nom' => $nombre, 'mod_url' => $url, 'mod_gen' => 'Clínica']);
            foreach ($usuariosClinica as $usuario) {
                if ($soloAdmin && ! $admins->contains($usuario)) {
                    continue;
                }
                if (! DB::table('modulos_usuario')->where('user_IdUsuario', $usuario)->where('mod_id', $id)->exists()) {
                    DB::table('modulos_usuario')->insert(['user_IdUsuario' => $usuario, 'mod_id' => $id, 'created_at' => now(), 'updated_at' => now()]);
                }
            }
        }
    }

    public function down(): void
    {
        $mods = DB::table('modulos')->whereIn('mod_url', ['/clinica/especialidades', '/clinica/tratamientos'])->pluck('mod_id');
        DB::table('modulos_usuario')->whereIn('mod_id', $mods)->delete();
        DB::table('modulos')->whereIn('mod_id', $mods)->delete();
        Schema::dropIfExists('clinica_tratamiento_registros');
        Schema::dropIfExists('clinica_tratamientos');
    }
};
