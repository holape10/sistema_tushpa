<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Guía de remisión electrónica remitente (GRE, tipo 09). Se envía a SUNAT por su API REST (token con client_id/client_secret
 * + usuario SOL), no por el servicio SOAP de facturas. Se emite desde una venta (trae cliente y productos) o sola.
 *  - gre_cabecera / gre_detalle: la guía y sus bienes.
 *  - empresa_negocios.SerGuia / NumGuia: serie y último número (T001).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('gre_cabecera')) {
            Schema::create('gre_cabecera', function (Blueprint $t) {
                $t->increments('gre_id');
                $t->string('serie', 4);
                $t->unsignedInteger('numero');
                $t->date('fecha_emision');
                $t->time('hora_emision');
                $t->date('fecha_traslado');
                $t->string('motivo', 2);                      // catálogo 20
                $t->string('motivo_desc', 100)->nullable();   // obligatorio si el motivo es 13 (otros)
                $t->string('modalidad', 2);                   // 01 público | 02 privado
                $t->decimal('peso', 12, 3);
                $t->string('unidad_peso', 3)->default('KGM');
                $t->unsignedInteger('bultos')->nullable();
                $t->string('dest_tdicod', 1);
                $t->string('dest_num', 15);
                $t->string('dest_nom', 150);
                $t->string('partida_ubigeo', 6);
                $t->string('partida_direccion', 200);
                $t->string('partida_codlocal', 4)->nullable();
                $t->string('llegada_ubigeo', 6);
                $t->string('llegada_direccion', 200);
                $t->string('llegada_codlocal', 4)->nullable();
                $t->string('transp_ruc', 11)->nullable();
                $t->string('transp_nom', 150)->nullable();
                $t->string('transp_mtc', 20)->nullable();
                $t->string('cond_tdicod', 1)->nullable();
                $t->string('cond_num', 15)->nullable();
                $t->string('cond_nombres', 100)->nullable();
                $t->string('cond_apellidos', 100)->nullable();
                $t->string('cond_licencia', 15)->nullable();
                $t->string('placa', 10)->nullable();
                $t->tinyInteger('vehiculo_m1l')->default(0);     // vehículo M1 o L: no lleva conductor ni placa
                $t->string('doc_tdocod', 2)->nullable();         // comprobante relacionado
                $t->string('doc_numero', 15)->nullable();        // F001-123
                $t->unsignedInteger('IdCpe_cabecera')->nullable()->index();
                $t->string('observacion', 250)->nullable();
                $t->string('est_sunat', 12)->default('PENDIENTE'); // PENDIENTE | ENVIADO | ACEPTADO | RECHAZADO | ERROR
                $t->string('ticket', 60)->nullable();
                $t->string('cod_respuesta', 10)->nullable();
                $t->string('mensaje', 500)->nullable();
                $t->string('qr', 300)->nullable();               // enlace de SUNAT para el QR de la representación impresa
                $t->string('hash', 100)->nullable();
                $t->unsignedInteger('IdUsuario')->nullable();
                $t->string('IdEmpresa', 11);
                $t->unsignedBigInteger('id_empresa_negocio')->index();
                $t->dateTime('creado');
                $t->unique(['id_empresa_negocio', 'serie', 'numero']);
            });
        }

        if (! Schema::hasTable('gre_detalle')) {
            Schema::create('gre_detalle', function (Blueprint $t) {
                $t->increments('det_id');
                $t->unsignedInteger('gre_id')->index();
                $t->unsignedInteger('IdProducto')->nullable();
                $t->string('codigo', 30)->nullable();
                $t->string('descripcion', 250);
                $t->string('umecod', 3)->default('NIU');
                $t->decimal('cantidad', 14, 3);
            });
        }

        if (! Schema::hasColumn('empresa_negocios', 'SerGuia')) {
            Schema::table('empresa_negocios', function (Blueprint $t) {
                $t->string('SerGuia', 4)->default('T001');
                $t->unsignedInteger('NumGuia')->default(0);
            });
        }

        // El menú "Guías Remisión" ya existía como "Pronto"
        DB::table('modulos')->where('mod_nom', 'like', 'Gu%as Remisi%n')->where(fn ($q) => $q->whereNull('mod_url')->orWhere('mod_url', '#'))
            ->update(['mod_url' => '/guias']);
        if (! DB::table('modulos')->where('mod_url', '/guias')->exists()) {
            DB::table('modulos')->insert(['mod_nom' => 'Guías Remisión', 'mod_url' => '/guias', 'mod_gen' => 'Ventas']);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('gre_detalle');
        Schema::dropIfExists('gre_cabecera');
        Schema::table('empresa_negocios', fn (Blueprint $t) => $t->dropColumn(['SerGuia', 'NumGuia']));
        DB::table('modulos')->where('mod_url', '/guias')->update(['mod_url' => '#']);
    }
};
