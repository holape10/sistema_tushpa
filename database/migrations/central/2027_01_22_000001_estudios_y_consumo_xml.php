<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Panel TUSHPA: cuántas empresas puede llevar un estudio contable (vacío = sin límite) y cuántos XML de compras trae
 * cada cliente por mes de json.pe (cada consulta usa créditos de la cuenta de TUSHPA).
 */
return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        $central = Schema::connection('central');
        if (! $central->hasColumn('clientes', 'max_empresas')) {
            $central->table('clientes', fn (Blueprint $t) => $t->unsignedSmallInteger('max_empresas')->nullable());
        }
        if (! $central->hasTable('consumo_xml')) {
            $central->create('consumo_xml', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('cliente_id');
                $t->char('mes', 6);
                $t->unsignedInteger('consultas')->default(0);
                $t->unsignedInteger('traidos')->default(0);
                $t->timestamp('ultima_vez')->nullable();
                $t->unique(['cliente_id', 'mes']);
            });
        }
    }

    public function down(): void
    {
        $central = Schema::connection('central');
        $central->dropIfExists('consumo_xml');
        if ($central->hasColumn('clientes', 'max_empresas')) {
            $central->table('clientes', fn (Blueprint $t) => $t->dropColumn('max_empresas'));
        }
    }
};
