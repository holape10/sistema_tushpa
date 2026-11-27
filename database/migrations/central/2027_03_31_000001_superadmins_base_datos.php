<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Permiso para usar la Base de datos del panel (explorador, consultas y edición con segunda clave) sin ser el dueño: por
 * ejemplo el experto de una marca. Solo ve las bases de las empresas de su marca (o las que creó); nunca la central.
 */
return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        if (! Schema::connection('central')->hasColumn('superadmins', 'puede_base_datos')) {
            Schema::connection('central')->table('superadmins', function (Blueprint $t) {
                $t->boolean('puede_base_datos')->default(false);
            });
        }
    }

    public function down(): void
    {
        if (Schema::connection('central')->hasColumn('superadmins', 'puede_base_datos')) {
            Schema::connection('central')->table('superadmins', function (Blueprint $t) {
                $t->dropColumn('puede_base_datos');
            });
        }
    }
};
