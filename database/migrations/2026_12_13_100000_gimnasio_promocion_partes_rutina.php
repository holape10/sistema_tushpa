<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gimnasio:
 *  - Planes en promoción (gym_planes.promocion + condiciones): no se transfieren, no se congelan, no hay devolución.
 *    La membresía guarda las condiciones con que se vendió. Planes VIP (gym_planes.vip): el entrenador les arma su rutina.
 *  - gym_membresias.avisado: cuándo se le avisó por WhatsApp que su plan vence (para no avisar dos veces).
 *  - gym_fichas: evaluación del cliente (objetivo, antecedentes médicos, observaciones), como la hoja "Rutina de entrenamiento".
 *  - gym_medidas: medidas por fecha (edad, peso, estatura, tórax, cintura, cadera, muslo, pantorrilla, brazo, % grasa, IMC).
 *  - gym_rutinas: rutina del mes que arma el entrenador (calentamiento y ejercicios por día); el cliente la ve en su portal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gym_planes', function (Blueprint $t) {
            if (! Schema::hasColumn('gym_planes', 'promocion')) {
                $t->boolean('promocion')->default(false);
                $t->string('condiciones', 300)->nullable();
                $t->boolean('vip')->default(false);
            }
        });
        Schema::table('gym_membresias', function (Blueprint $t) {
            if (! Schema::hasColumn('gym_membresias', 'promocion')) {
                $t->boolean('promocion')->default(false);
                $t->string('condiciones', 300)->nullable();
                $t->boolean('vip')->default(false);
                $t->dateTime('avisado')->nullable();
            }
        });
        if (! Schema::hasTable('gym_fichas')) {
            Schema::create('gym_fichas', function (Blueprint $t) {
                $t->increments('fic_id');
                $t->unsignedInteger('soc_id')->unique();
                $t->string('objetivo', 40)->nullable();
                $t->json('antecedentes')->nullable();          // ["HIPERTENSION", "DIABETES", ...]
                $t->string('otros', 150)->nullable();
                $t->string('observaciones', 500)->nullable();
                $t->unsignedInteger('IdUsuario')->nullable();
                $t->dateTime('actualizado')->nullable();
            });
        }
        if (! Schema::hasTable('gym_medidas')) {
            Schema::create('gym_medidas', function (Blueprint $t) {
                $t->increments('med_id');
                $t->unsignedInteger('soc_id')->index();
                $t->date('fecha');
                $t->unsignedTinyInteger('edad')->nullable();
                $t->decimal('peso', 5, 2)->nullable();          // kg
                $t->decimal('estatura', 4, 2)->nullable();      // metros
                foreach (['torax', 'cintura', 'cadera', 'muslo', 'pantorrilla', 'brazo'] as $c) {
                    $t->decimal($c, 5, 1)->nullable();          // cm
                }
                $t->decimal('grasa', 4, 1)->nullable();
                $t->unsignedInteger('IdUsuario')->nullable();
                $t->dateTime('creado');
            });
        }
        if (! Schema::hasTable('gym_rutinas')) {
            Schema::create('gym_rutinas', function (Blueprint $t) {
                $t->increments('rut_id');
                $t->unsignedInteger('soc_id')->index();
                $t->date('desde');
                $t->date('hasta');
                $t->string('objetivo', 40)->nullable();
                $t->json('calentamiento')->nullable();          // {"CAMINADORA": 10, "ELIPTICA": 5, ...} minutos
                $t->json('dias');                               // [{"dia": "LUNES", "grupo": "PECHO", "ejercicios": [{"nombre", "series", "reps", "nota"}]}]
                $t->text('indicaciones')->nullable();
                $t->unsignedInteger('IdUsuario');
                $t->tinyInteger('activo')->default(1);
                $t->dateTime('creado');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('gym_rutinas');
        Schema::dropIfExists('gym_medidas');
        Schema::dropIfExists('gym_fichas');
        Schema::table('gym_membresias', fn (Blueprint $t) => $t->dropColumn(['promocion', 'condiciones', 'vip', 'avisado']));
        Schema::table('gym_planes', fn (Blueprint $t) => $t->dropColumn(['promocion', 'condiciones', 'vip']));
    }
};
