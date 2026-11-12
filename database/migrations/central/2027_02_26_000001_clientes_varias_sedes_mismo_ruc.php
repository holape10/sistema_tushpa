<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Un mismo RUC con varios sistemas separados (una empresa con sedes en lugares distintos que trabaja cada una por su
 * lado): bd_{RUC}, bd_{RUC}_2, bd_{RUC}_3… Cada sistema entra por su propio subdominio. El RUC deja de ser único.
 */
return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        $esquema = Schema::connection('central');
        if (! $esquema->hasTable('clientes')) {
            return;
        }
        $indices = collect($esquema->getIndexes('clientes'));
        if ($indices->contains(fn ($i) => $i['name'] === 'clientes_ruc_unique')) {
            $esquema->table('clientes', function (Blueprint $t) {
                $t->dropUnique('clientes_ruc_unique');
                $t->index('ruc');
            });
        }
    }

    public function down(): void
    {
        // No se vuelve a poner único: podría haber ya varios sistemas con el mismo RUC
    }
};
