<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Cuántas empresas lleva cada estudio contable (para verlo en el panel sin entrar a la base de cada uno) */
return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        if (Schema::connection('central')->hasTable('clientes') && ! Schema::connection('central')->hasColumn('clientes', 'estudio_empresas')) {
            Schema::connection('central')->table('clientes', function (Blueprint $t) {
                $t->unsignedInteger('estudio_empresas')->nullable();
                $t->unsignedInteger('estudio_inactivas')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::connection('central')->hasColumn('clientes', 'estudio_empresas')) {
            Schema::connection('central')->table('clientes', fn (Blueprint $t) => $t->dropColumn(['estudio_empresas', 'estudio_inactivas']));
        }
    }
};
