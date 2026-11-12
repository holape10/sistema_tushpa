<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Cuotas de una venta al crédito (fecha y monto de cada una): van a SUNAT y salen impresas en el comprobante */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cpe_cuotas')) {
            Schema::create('cpe_cuotas', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('IdCpe_cabecera')->index();
                $t->unsignedSmallInteger('numero');
                $t->date('fecha');
                $t->decimal('monto', 12, 2);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cpe_cuotas');
    }
};
