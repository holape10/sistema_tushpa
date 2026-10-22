<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Anuncio para empresas elegidas (ids de clientes): ej. recordarle a una sola empresa que su certificado vence */
return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        if (! Schema::connection('central')->hasColumn('anuncios_sistema', 'empresas')) {
            Schema::connection('central')->table('anuncios_sistema', fn (Blueprint $t) => $t->json('empresas')->nullable()->after('rubros'));
        }
    }

    public function down(): void
    {
        if (Schema::connection('central')->hasColumn('anuncios_sistema', 'empresas')) {
            Schema::connection('central')->table('anuncios_sistema', fn (Blueprint $t) => $t->dropColumn('empresas'));
        }
    }
};
