<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contrato de servicio de cada empresa: el panel lo genera (mensual o anual, con su monto) y la empresa, con un enlace,
 * sube la copia de su DNI (ambas caras), descarga el contrato en PDF y lo firma en la pantalla o sube el firmado.
 * El panel lo revisa y lo aprueba u observa (como un proveedor de certificados valida los documentos).
 */
return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        if (Schema::connection('central')->hasTable('contratos')) {
            return;
        }
        Schema::connection('central')->create('contratos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('cliente_id')->index();
            $t->char('token', 40)->unique();                 // el enlace que se le manda a la empresa
            $t->string('periodo', 10);                        // MENSUAL | ANUAL
            $t->decimal('monto', 10, 2);
            $t->string('plan', 60)->nullable();
            $t->string('estado', 12)->default('PENDIENTE');   // PENDIENTE | ENVIADO (firmó, falta revisar) | APROBADO | OBSERVADO | ANULADO
            $t->string('observacion', 255)->nullable();       // por qué se observó (lo ve la empresa)
            // Quién firma por la empresa
            $t->string('firmante', 150)->nullable();
            $t->string('firmante_dni', 15)->nullable();
            $t->string('firmante_cargo', 60)->nullable();
            $t->string('correo', 120)->nullable();
            $t->string('telefono', 20)->nullable();
            // Archivos (en storage, fuera de public)
            $t->string('dni_frente', 200)->nullable();
            $t->string('dni_reverso', 200)->nullable();
            $t->string('firma', 200)->nullable();             // firma hecha en la pantalla (PNG)
            $t->string('escaneado', 200)->nullable();         // o el contrato firmado a mano (foto o PDF)
            // Constancia de la firma electrónica
            $t->timestamp('firmado_el')->nullable();
            $t->string('firmado_ip', 45)->nullable();
            $t->string('firmado_equipo', 255)->nullable();
            $t->char('huella', 64)->nullable();               // sha256 del texto del contrato que se firmó
            $t->foreignId('creado_por')->nullable();
            $t->foreignId('revisado_por')->nullable();
            $t->timestamp('revisado_el')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('contratos');
    }
};
