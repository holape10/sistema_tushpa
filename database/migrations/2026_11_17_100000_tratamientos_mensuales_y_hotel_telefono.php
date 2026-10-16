<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * - clinica_tratamientos: plan de pago mensual (ortodoncia): cuota inicial, número de cuotas, monto de cada cuota y la fecha
 *   de la primera. Las cuotas se arman de esos datos y los pagos se aplican en orden (ver Clinica::cuotasDe).
 * - hospedajes.telefono: celular del huésped (para avisarle por WhatsApp que su tiempo termina).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinica_tratamientos', function (Blueprint $t) {
            if (! Schema::hasColumn('clinica_tratamientos', 'modalidad')) {
                $t->string('modalidad', 10)->default('UNICO');           // UNICO (adelantos libres) | MENSUAL (cuotas)
                $t->decimal('cuota_inicial', 12, 2)->default(0);
                $t->unsignedSmallInteger('cuotas')->default(0);
                $t->decimal('cuota', 12, 2)->default(0);
                $t->date('primer_vencimiento')->nullable();
            }
        });
        if (Schema::hasTable('hospedajes')) {
            Schema::table('hospedajes', function (Blueprint $t) {
                if (! Schema::hasColumn('hospedajes', 'telefono')) {
                    $t->string('telefono', 20)->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('clinica_tratamientos', fn (Blueprint $t) => $t->dropColumn(['modalidad', 'cuota_inicial', 'cuotas', 'cuota', 'primer_vencimiento']));
        if (Schema::hasTable('hospedajes')) {
            Schema::table('hospedajes', fn (Blueprint $t) => $t->dropColumn('telefono'));
        }
    }
};
