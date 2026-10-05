<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Impresión directa (sin vista previa): tablas del sistema antiguo (configuracion_impresoras, cola_impresion)
// + users.terminal (impresora del usuario) + token del agente de impresión por sucursal
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('configuracion_impresoras')) {
            Schema::create('configuracion_impresoras', function (Blueprint $table) {
                $table->increments('Id');
                $table->string('descripcion', 30)->nullable();          // CAJA, COCINA, BAR...
                $table->string('ruta', 100)->nullable();                // nombre compartido en Windows o IP de red
                $table->string('IdEmpresa', 11)->nullable();
                $table->unsignedBigInteger('id_empresa_negocio')->nullable();
                $table->tinyInteger('predeterminado')->default(0);      // impresora de caja por defecto
                $table->string('tip_conex_imp', 30)->default('COMPARTIDO'); // COMPARTIDO | RED
                // campos propios del sistema nuevo
                $table->unsignedTinyInteger('columnas')->default(42);   // caracteres por línea (80mm: 42 o 48, 58mm: 32)
                $table->tinyInteger('abrir_cajon')->default(0);         // abre el cajón al imprimir comprobantes
                $table->tinyInteger('activo')->default(1);
            });
        }

        if (!Schema::hasTable('cola_impresion')) {
            Schema::create('cola_impresion', function (Blueprint $table) {
                $table->increments('id');
                $table->longText('contenido')->nullable();               // bytes ESC/POS en base64
                $table->string('impresora', 30)->nullable();             // descripción (como el sistema antiguo)
                $table->tinyInteger('estado')->default(0)->index();      // 0 pendiente | 1 entregado al agente | 2 impreso | 9 error
                // campos propios del sistema nuevo
                $table->unsignedInteger('id_impresora')->nullable();
                $table->unsignedBigInteger('id_empresa_negocio')->nullable();
                $table->string('tipo', 20)->nullable();                  // COMPROBANTE | COMANDA | ANULACION | PRECUENTA | PRUEBA
                $table->string('referencia', 60)->nullable();
                $table->unsignedTinyInteger('intentos')->default(0);
                $table->string('error')->nullable();
                $table->timestamp('creado')->nullable()->useCurrent();
                $table->timestamp('entregado')->nullable();
                $table->timestamp('impreso')->nullable();
                $table->index(['id_empresa_negocio', 'estado']);
            });
        }

        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'terminal')) {
                $table->unsignedInteger('terminal')->nullable(); // impresora de este usuario (configuracion_impresoras.Id)
            }
        });

        Schema::table('empresa_negocios', function (Blueprint $table) {
            $table->string('token_impresion', 64)->nullable();       // hash del token del agente
            $table->timestamp('impresion_contacto')->nullable();     // última vez que el agente se conectó
        });

        // categorias.impresora ya existe (tinyint); se amplía para guardar el Id de configuracion_impresoras
        Schema::table('categorias', function (Blueprint $table) {
            $table->unsignedInteger('impresora')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('empresa_negocios', fn(Blueprint $t) => $t->dropColumn(['token_impresion', 'impresion_contacto']));
        Schema::table('users', fn(Blueprint $t) => $t->dropColumn('terminal'));
        Schema::dropIfExists('cola_impresion');
        Schema::dropIfExists('configuracion_impresoras');
    }
};
