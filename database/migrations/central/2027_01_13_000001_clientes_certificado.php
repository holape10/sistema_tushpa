<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hasta cuándo vale el certificado digital de cada empresa (copia de la de su base, para verlo en el panel sin entrar a
 * cada una). Lo actualiza el comando diario certificados:revisar y el sistema al subir un certificado nuevo.
 */
return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        if (! Schema::connection('central')->hasColumn('clientes', 'cert_vence')) {
            Schema::connection('central')->table('clientes', fn (Blueprint $t) => $t->date('cert_vence')->nullable());
        }
    }

    public function down(): void
    {
        Schema::connection('central')->table('clientes', fn (Blueprint $t) => $t->dropColumn('cert_vence'));
    }
};
