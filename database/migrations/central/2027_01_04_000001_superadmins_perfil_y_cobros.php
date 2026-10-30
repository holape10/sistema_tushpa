<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Panel: perfil de cada usuario (foto, celular, fecha de nacimiento y cuándo cambió su contraseña: una vez por semana)
 * y varias cuentas y QR de cobro por usuario, también para el dueño. Las cuentas y el QR que ya tenían pasan a la tabla nueva.
 */
return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        $central = Schema::connection('central');
        $central->table('superadmins', function (Blueprint $t) use ($central) {
            if (! $central->hasColumn('superadmins', 'foto')) {
                $t->string('foto', 200)->nullable();
                $t->string('celular', 20)->nullable();
                $t->date('fecha_nacimiento')->nullable();
                $t->timestamp('clave_cambiada_el')->nullable();
            }
        });

        if (! $central->hasTable('superadmin_cobros')) {
            $central->create('superadmin_cobros', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('superadmin_id')->index();
                $t->string('tipo', 10);                  // CUENTA | QR
                $t->string('banco', 60)->nullable();     // banco de la cuenta o nombre del QR (Yape, Plin…)
                $t->string('titular', 120)->nullable();
                $t->string('cuenta', 40)->nullable();
                $t->string('cci', 40)->nullable();
                $t->string('qr', 200)->nullable();
                $t->unsignedInteger('orden')->default(0);
                $t->timestamps();
            });

            // La cuenta y el QR que cada usuario ya tenía
            foreach (DB::connection('central')->table('superadmins')->get() as $u) {
                if ($u->cobro_cuenta || $u->cobro_cci) {
                    DB::connection('central')->table('superadmin_cobros')->insert(['superadmin_id' => $u->id, 'tipo' => 'CUENTA', 'banco' => $u->cobro_banco,
                        'titular' => $u->cobro_titular, 'cuenta' => $u->cobro_cuenta, 'cci' => $u->cobro_cci, 'created_at' => now(), 'updated_at' => now()]);
                }
                if ($u->cobro_qr) {
                    DB::connection('central')->table('superadmin_cobros')->insert(['superadmin_id' => $u->id, 'tipo' => 'QR', 'banco' => 'QR de pago',
                        'titular' => $u->cobro_titular, 'qr' => $u->cobro_qr, 'orden' => 1, 'created_at' => now(), 'updated_at' => now()]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('superadmin_cobros');
        Schema::connection('central')->table('superadmins', fn (Blueprint $t) => $t->dropColumn(['foto', 'celular', 'fecha_nacimiento', 'clave_cambiada_el']));
    }
};
