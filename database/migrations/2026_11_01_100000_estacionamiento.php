<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Estacionamiento / valet parking.
 *  - est_tarifas: una por tipo de vehículo (AUTO, MOTO, CAMIONETA...). Cobra por hora, por fracción o precio fijo,
 *    con minutos de tolerancia, tope por día, penalidad por ticket perdido y precio de la pensión mensual.
 *    Cada tarifa es un producto para el comprobante.
 *  - est_espacios: los espacios del local (A-01, A-02...) con su zona y el tipo de vehículo que admiten.
 *  - est_tickets: cada vehículo que entra. DENTRO → (SOLICITADO, si es valet y el cliente pidió su auto) → SALIO.
 *    El código es el del QR del ticket: con él el cliente pide su auto desde el celular.
 *  - est_abonados: pensiones (mensualidades). Mientras está vigente, la placa entra y sale sin pagar.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('est_tarifas')) {
            Schema::create('est_tarifas', function (Blueprint $t) {
                $t->increments('tar_id');
                $t->string('nombre', 40);                                   // AUTO, MOTO, CAMIONETA
                $t->string('icono', 20)->default('auto');                  // auto | moto | camioneta | bus | bici
                $t->string('modo', 10)->default('FRACCION');               // HORA | FRACCION | FIJO
                $t->decimal('precio', 10, 2);                               // por hora, o el precio fijo por día
                $t->unsignedSmallInteger('fraccion_min')->default(15);      // pasada la 1.ª hora se cobra por fracciones de estos minutos
                $t->unsignedSmallInteger('tolerancia_min')->default(10);    // si sale antes, no paga
                $t->decimal('tope_dia', 10, 2)->default(0);                 // máximo por cada 24 horas (0 = sin tope)
                $t->decimal('perdido', 10, 2)->default(0);                  // penalidad por ticket perdido
                $t->decimal('pension', 10, 2)->default(0);                  // precio sugerido de la pensión mensual
                $t->unsignedInteger('IdProducto')->nullable();
                $t->tinyInteger('activo')->default(1);
                $t->unsignedSmallInteger('orden')->default(0);
                $t->unsignedBigInteger('id_empresa_negocio')->index();
            });
        }

        if (! Schema::hasTable('est_espacios')) {
            Schema::create('est_espacios', function (Blueprint $t) {
                $t->increments('esp_id');
                $t->string('codigo', 10);
                $t->string('zona', 30)->nullable();
                $t->unsignedInteger('tar_id')->nullable();                  // tipo de vehículo que admite (null = cualquiera)
                $t->string('estado', 15)->default('ACTIVO');               // ACTIVO | MANTENIMIENTO
                $t->unsignedSmallInteger('orden')->default(0);
                $t->unsignedBigInteger('id_empresa_negocio')->index();
                $t->unique(['id_empresa_negocio', 'codigo']);
            });
        }

        if (! Schema::hasTable('est_abonados')) {
            Schema::create('est_abonados', function (Blueprint $t) {
                $t->increments('abo_id');
                $t->string('placa', 10)->index();
                $t->string('placa2', 10)->nullable()->index();             // segundo vehículo del mismo abonado
                $t->string('clinum', 15)->nullable();
                $t->string('clinom', 150);
                $t->string('telefono', 20)->nullable();
                $t->unsignedInteger('tar_id')->nullable();
                $t->unsignedInteger('esp_id')->nullable();                 // espacio reservado
                $t->date('inicio');
                $t->date('fin');
                $t->decimal('precio', 10, 2)->default(0);
                $t->unsignedInteger('IdCpe_cabecera')->nullable()->index();
                $t->string('estado', 10)->default('ACTIVO');               // ACTIVO | ANULADO
                $t->string('obs', 200)->nullable();
                $t->unsignedInteger('IdUsuario')->nullable();
                $t->dateTime('creado');
                $t->unsignedBigInteger('id_empresa_negocio')->index();
            });
        }

        if (! Schema::hasTable('est_tickets')) {
            Schema::create('est_tickets', function (Blueprint $t) {
                $t->increments('tic_id');
                $t->unsignedInteger('numero');
                $t->string('codigo', 12)->unique();                        // el del QR del ticket
                $t->string('placa', 10)->index();
                $t->unsignedInteger('tar_id')->nullable();
                $t->string('tipo', 40);
                $t->unsignedInteger('esp_id')->nullable();
                $t->string('espacio', 10)->nullable();
                $t->unsignedInteger('abo_id')->nullable();
                $t->dateTime('entrada');
                $t->dateTime('salida')->nullable();
                $t->unsignedInteger('minutos')->nullable();
                $t->decimal('importe', 10, 2)->default(0);                 // lo que marca la tarifa
                $t->decimal('penalidad', 10, 2)->default(0);               // ticket perdido
                $t->decimal('descuento', 10, 2)->default(0);
                $t->decimal('total', 10, 2)->default(0);
                $t->string('estado', 12)->default('DENTRO');               // DENTRO | SOLICITADO | SALIO | ANULADO
                $t->tinyInteger('valet')->default(0);
                $t->string('llavero', 10)->nullable();
                $t->string('marca', 40)->nullable();
                $t->string('color', 20)->nullable();
                $t->string('observaciones', 200)->nullable();              // daños, objetos de valor
                $t->string('cliente', 120)->nullable();
                $t->string('telefono', 20)->nullable();
                $t->dateTime('solicitado')->nullable();                    // cuándo pidió su auto (valet)
                $t->string('motivo', 200)->nullable();                     // descuento, cortesía o anulación
                $t->unsignedInteger('IdCpe_cabecera')->nullable()->index();
                $t->unsignedInteger('IdUsuario_entrada')->nullable();
                $t->unsignedInteger('IdUsuario_salida')->nullable();
                $t->unsignedInteger('IdUsuario_valet')->nullable();        // quién estacionó el auto
                $t->unsignedBigInteger('id_empresa_negocio');
                $t->index(['id_empresa_negocio', 'estado']);
                $t->index(['id_empresa_negocio', 'entrada']);
                $t->unique(['id_empresa_negocio', 'numero']);
            });
        }

        foreach (['/estacionamiento' => 'Estacionamiento', '/estacionamiento/abonados' => 'Abonados Estacionamiento',
            '/estacionamiento/reporte' => 'Reporte Estacionamiento'] as $url => $nom) {
            if (! DB::table('modulos')->where('mod_url', $url)->exists()) {
                DB::table('modulos')->insert(['mod_nom' => $nom, 'mod_url' => $url, 'mod_gen' => 'Estacionamiento']);
            }
        }
    }

    public function down(): void
    {
        $mods = DB::table('modulos')->whereIn('mod_url', ['/estacionamiento', '/estacionamiento/abonados', '/estacionamiento/reporte'])->pluck('mod_id');
        DB::table('modulos_usuario')->whereIn('mod_id', $mods)->delete();
        DB::table('modulos')->whereIn('mod_id', $mods)->delete();
        foreach (['est_tickets', 'est_abonados', 'est_espacios', 'est_tarifas'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
