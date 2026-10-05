<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

// CONCAR: cuentas y subdiarios para exportar ventas y compras como asientos; menú de CONCAR y Soporte
return new class extends Migration {
    public function up(): void
    {
        Schema::create('concar_config', function (Blueprint $table) {
            $table->string('IdEmpresa', 11)->primary();
            $table->string('subdiario_ventas', 4)->default('05');
            $table->string('subdiario_compras', 4)->default('11');
            $table->string('cta_por_cobrar', 12)->default('121201');    // facturas por cobrar emitidas en cartera
            $table->string('cta_igv', 12)->default('401111');           // IGV - cuenta propia
            $table->string('cta_ventas', 12)->default('701111');        // ventas gravadas
            $table->string('cta_ventas_exo', 12)->default('701111');    // ventas exoneradas / inafectas
            $table->string('cta_compras', 12)->default('601111');       // compras de mercaderías
            $table->string('cta_por_pagar', 12)->default('421201');     // facturas por pagar
            $table->string('anexo_varios', 18)->default('00000000');    // anexo para boletas sin cliente identificado
            $table->string('tipo_conversion', 1)->default('V');         // V venta | M compra | C especial | F según fecha
            $table->timestamps();
        });

        DB::table('modulos')->where('mod_gen', 'Otros')->where('mod_nom', 'CONCAR')->update(['mod_url' => '/concar']);
        DB::table('modulos')->where('mod_gen', 'Otros')->where('mod_nom', 'Soporte')->update(['mod_url' => '/soporte']);
    }

    public function down(): void
    {
        DB::table('modulos')->where('mod_gen', 'Otros')->whereIn('mod_nom', ['CONCAR', 'Soporte'])->update(['mod_url' => '#']);
        Schema::dropIfExists('concar_config');
    }
};
