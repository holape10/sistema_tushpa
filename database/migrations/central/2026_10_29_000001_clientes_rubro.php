<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Tipo de negocio del cliente (RESTOBAR, GENERAL, FARMACIA...): define el menú con el que arranca su administrador */
return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        if (! Schema::connection('central')->hasColumn('clientes', 'rubro')) {
            Schema::connection('central')->table('clientes', fn (Blueprint $t) => $t->string('rubro', 20)->nullable());
        }
    }

    public function down(): void
    {
        Schema::connection('central')->table('clientes', fn (Blueprint $t) => $t->dropColumn('rubro'));
    }
};
