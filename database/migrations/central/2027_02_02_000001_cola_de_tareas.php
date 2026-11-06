<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cola de tareas en segundo plano (base central): copias de seguridad, ZIP de comprobantes… Cada tarea lleva la base de
 * su empresa. La atiende el trabajador de colas (deploy/colas-instalar.sh); si no está corriendo, el sistema hace la
 * tarea en el momento, como siempre.
 */
return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        if (! Schema::connection('central')->hasTable('jobs')) {
            Schema::connection('central')->create('jobs', function (Blueprint $t) {
                $t->id();
                $t->string('queue')->index();
                $t->longText('payload');
                $t->unsignedTinyInteger('attempts');
                $t->unsignedInteger('reserved_at')->nullable();
                $t->unsignedInteger('available_at');
                $t->unsignedInteger('created_at');
            });
        }
        if (! Schema::connection('central')->hasTable('failed_jobs')) {
            Schema::connection('central')->create('failed_jobs', function (Blueprint $t) {
                $t->id();
                $t->string('uuid')->unique();
                $t->text('connection');
                $t->text('queue');
                $t->longText('payload');
                $t->longText('exception');
                $t->timestamp('failed_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('failed_jobs');
        Schema::connection('central')->dropIfExists('jobs');
    }
};
