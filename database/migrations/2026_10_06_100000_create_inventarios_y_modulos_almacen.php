<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

// Almacén: inventarios (conteo físico / saldo inicial) y enlace de los módulos Almacenes, Inventarios y Transferencias
return new class extends Migration {
    private array $urls = [
        'Almacenes'      => '/almacenes',
        'Inventarios'    => '/inventarios',
        'Transferencias' => '/transferencias',
    ];

    public function up(): void
    {
        Schema::create('inventario_cabecera', function (Blueprint $table) {
            $table->increments('inv_cab_id');
            $table->unsignedInteger('id_almacen');
            $table->unsignedBigInteger('id_empresa_negocio')->nullable()->index();
            $table->date('fecha');
            $table->string('observaciones')->nullable();
            $table->string('origen', 10)->default('MANUAL');      // MANUAL | EXCEL | PRODUCTOS (importación de productos)
            $table->string('estado', 15)->default('PROCESADO');
            $table->unsignedInteger('IdUsuario')->nullable();
            $table->timestamps();
        });

        // Una fila por producto contado: stock del sistema al momento del conteo, lo contado y el ajuste aplicado
        Schema::create('inventario_detalle', function (Blueprint $table) {
            $table->increments('inv_det_id');
            $table->unsignedInteger('inv_cab_id')->index();
            $table->unsignedInteger('IdProducto');
            $table->decimal('stock_sistema', 15, 5)->default(0);
            $table->decimal('stock_fisico', 15, 5)->default(0);
            $table->decimal('diferencia', 15, 5)->default(0);
            $table->decimal('costo', 12, 2)->default(0);
            $table->string('cod_tip_ope', 4)->nullable();          // 16 saldo inicial | 28 ajuste por diferencia
        });

        foreach ($this->urls as $nom => $url) {
            DB::table('modulos')->where('mod_nom', $nom)->where('mod_gen', 'Almacén')->update(['mod_url' => $url]);
        }
    }

    public function down(): void
    {
        foreach (array_keys($this->urls) as $nom) {
            DB::table('modulos')->where('mod_nom', $nom)->where('mod_gen', 'Almacén')->update(['mod_url' => '#']);
        }
        Schema::dropIfExists('inventario_detalle');
        Schema::dropIfExists('inventario_cabecera');
    }
};
