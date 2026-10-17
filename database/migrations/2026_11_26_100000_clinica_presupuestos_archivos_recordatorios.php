<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Clínica / odontología:
 *  - presupuestos (plan de tratamiento) que el paciente firma y que se convierten en tratamiento
 *  - radiografías, fotos y documentos de la historia
 *  - consentimientos informados guardados con la firma del paciente
 *  - periodontograma de la atención
 *  - recordatorios automáticos de citas (paciente y doctor) por WhatsApp y correo, con su configuración y registro
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('clinica_presupuestos')) {
            Schema::create('clinica_presupuestos', function (Blueprint $t) {
                $t->increments('pre_id');
                $t->unsignedInteger('his_cli_id')->index();
                $t->unsignedInteger('numero');
                $t->date('fecha');
                $t->longText('items');
                $t->decimal('subtotal', 12, 2)->default(0);
                $t->decimal('descuento', 12, 2)->default(0);
                $t->decimal('total', 12, 2)->default(0);
                $t->unsignedSmallInteger('validez_dias')->default(30);
                $t->text('observaciones')->nullable();
                $t->string('estado', 15)->default('PENDIENTE');
                $t->string('firma')->nullable();
                $t->dateTime('firmado')->nullable();
                $t->unsignedInteger('tra_id')->nullable();
                $t->unsignedInteger('doctor')->nullable();
                $t->unsignedInteger('IdUsuario')->nullable();
                $t->unsignedBigInteger('id_empresa_negocio')->index();
                $t->dateTime('creado')->nullable();
            });
        }
        if (! Schema::hasTable('clinica_archivos')) {
            Schema::create('clinica_archivos', function (Blueprint $t) {
                $t->increments('arc_id');
                $t->unsignedInteger('his_cli_id')->index();
                $t->unsignedInteger('ate_cli_id')->nullable();
                $t->string('tipo', 15)->default('FOTO');
                $t->string('ruta');
                $t->string('nombre', 150);
                $t->string('mime', 80);
                $t->unsignedInteger('tamano')->default(0);
                $t->string('descripcion', 200)->nullable();
                $t->unsignedInteger('IdUsuario')->nullable();
                $t->unsignedBigInteger('id_empresa_negocio')->index();
                $t->dateTime('creado')->nullable();
            });
        }
        if (! Schema::hasTable('clinica_consentimientos')) {
            Schema::create('clinica_consentimientos', function (Blueprint $t) {
                $t->increments('con_id');
                $t->unsignedInteger('his_cli_id')->index();
                $t->string('tratamiento', 300);
                $t->text('riesgos')->nullable();
                $t->unsignedInteger('doctor')->nullable();
                $t->string('firma')->nullable();
                $t->dateTime('firmado')->nullable();
                $t->unsignedInteger('IdUsuario')->nullable();
                $t->unsignedBigInteger('id_empresa_negocio')->index();
                $t->dateTime('creado')->nullable();
            });
        }
        if (Schema::hasTable('atencion_clinica') && ! Schema::hasColumn('atencion_clinica', 'periodontograma')) {
            Schema::table('atencion_clinica', fn (Blueprint $t) => $t->longText('periodontograma')->nullable());
        }
        // Celular y correo del doctor para avisarle sus citas
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $t) {
                if (! Schema::hasColumn('users', 'telefono')) {
                    $t->string('telefono', 20)->nullable();
                }
                if (! Schema::hasColumn('users', 'correo')) {
                    $t->string('correo', 120)->nullable();
                }
            });
        }
        if (! Schema::hasTable('clinica_config')) {
            Schema::create('clinica_config', function (Blueprint $t) {
                $t->unsignedBigInteger('id_empresa_negocio')->primary();
                $t->boolean('recordar_paciente')->default(true);
                $t->boolean('recordar_doctor')->default(true);
                $t->string('hora_envio', 5)->default('18:00');
                $t->text('wa_token')->nullable();
                $t->string('wa_phone_id', 40)->nullable();
                $t->string('wa_plantilla_paciente', 80)->nullable();
                $t->string('wa_plantilla_doctor', 80)->nullable();
                $t->string('wa_idioma', 10)->default('es');
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('clinica_recordatorios')) {
            Schema::create('clinica_recordatorios', function (Blueprint $t) {
                $t->increments('rec_id');
                $t->string('para', 10);
                $t->unsignedInteger('cit_id')->nullable()->index();
                $t->unsignedInteger('doctor')->nullable();
                $t->date('fecha_cita')->index();
                $t->string('canal', 10);
                $t->string('destino', 120)->nullable();
                $t->string('estado', 10);
                $t->string('detalle', 255)->nullable();
                $t->unsignedBigInteger('id_empresa_negocio')->index();
                $t->dateTime('creado')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('clinica_recordatorios');
        Schema::dropIfExists('clinica_config');
        Schema::dropIfExists('clinica_consentimientos');
        Schema::dropIfExists('clinica_archivos');
        Schema::dropIfExists('clinica_presupuestos');
        if (Schema::hasColumn('atencion_clinica', 'periodontograma')) {
            Schema::table('atencion_clinica', fn (Blueprint $t) => $t->dropColumn('periodontograma'));
        }
    }
};
