<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Tablas de turnos, caja y kardex con los mismos nombres y campos del sistema antiguo (bd_sistema_holape)
return new class extends Migration {
    public function up(): void
    {
        // ---------------- TURNOS ----------------
        Schema::create('turnos', function (Blueprint $table) {
            $table->increments('id_turno');
            $table->integer('turno')->nullable();                 // n° de turno del día en la sucursal
            $table->unsignedInteger('IdUsuario')->nullable();
            $table->timestamp('apertura')->nullable()->useCurrent();
            $table->timestamp('cierre')->nullable();
            $table->string('IdEmpresa', 11)->nullable();
            $table->unsignedBigInteger('id_empresa_negocio')->nullable();
            $table->string('estado', 30)->nullable();             // ABIERTO | CERRADO
            $table->decimal('monto', 15, 2)->nullable();          // fondo inicial de caja
            $table->decimal('montocierre', 15, 2)->nullable();    // efectivo contado al cerrar
            $table->decimal('totalocupados', 15, 2)->nullable();  // mesas ocupadas al cerrar
            $table->decimal('totallibres', 15, 2)->nullable();    // mesas libres al cerrar
            $table->decimal('total_gastos', 10, 2)->nullable();
            $table->decimal('total_ingresos', 10, 2)->nullable();
            foreach (['m_10_centimos', 'm_20_centimos', 'm_50_centimos', 'm_1_sol', 'm_2_soles', 'm_5_soles',
                      'c_10_soles', 'c_20_soles', 'c_50_soles', 'c_100_soles', 'c_200_soles'] as $den) {
                $table->integer('cant_' . $den)->default(0);
            }
            $table->index(['id_empresa_negocio', 'IdUsuario', 'estado']);
        });

        Schema::create('turno_medio_pago', function (Blueprint $table) {
            $table->increments('id_tur_med_pag');
            $table->unsignedInteger('id_turno')->nullable()->index();
            $table->unsignedInteger('id_med_pag')->nullable();
            $table->decimal('monto', 10, 2)->nullable();
            $table->unsignedBigInteger('id_empresa_negocio')->nullable();
        });

        // ---------------- CAJA (ingresos / egresos del turno) ----------------
        Schema::create('tiposcaja', function (Blueprint $table) {
            $table->string('tip_caj_id', 4)->primary();
            $table->string('tip_caj_nom')->nullable();
            $table->string('IdEmpresa', 11)->nullable();
            $table->unsignedBigInteger('id_empresa_negocio')->nullable();
            $table->string('tipo')->nullable();   // ENTRADA | SALIDA
            $table->string('gasto')->nullable();  // SI = cuenta como gasto
        });

        DB::table('tiposcaja')->insert([
            ['tip_caj_id' => '001', 'tip_caj_nom' => 'FONDO DE CAJA',      'tipo' => 'ENTRADA', 'gasto' => null],
            ['tip_caj_id' => '002', 'tip_caj_nom' => 'VENTA DEL DÍA',      'tipo' => 'ENTRADA', 'gasto' => null],
            ['tip_caj_id' => '003', 'tip_caj_nom' => 'GASTOS VARIOS',      'tipo' => 'SALIDA',  'gasto' => 'SI'],
            ['tip_caj_id' => '004', 'tip_caj_nom' => 'DEPOSITO BANCARIO',  'tipo' => 'SALIDA',  'gasto' => null],
            ['tip_caj_id' => '005', 'tip_caj_nom' => 'OTROS',              'tipo' => 'SALIDA',  'gasto' => null],
            ['tip_caj_id' => '006', 'tip_caj_nom' => 'SALIDA FONDO CAJA',  'tipo' => 'SALIDA',  'gasto' => null],
            ['tip_caj_id' => '007', 'tip_caj_nom' => 'CUENTAS POR COBRAR', 'tipo' => 'ENTRADA', 'gasto' => null],
            ['tip_caj_id' => '008', 'tip_caj_nom' => 'OTROS INGRESOS',     'tipo' => 'ENTRADA', 'gasto' => null],
        ]);

        Schema::create('movimientoscaja', function (Blueprint $table) {
            $table->increments('mov_caj_id');
            $table->string('tip_caj_id', 4)->nullable();
            $table->text('mov_com')->nullable();                 // comentario / descripción
            $table->unsignedInteger('cuen_ban_id')->nullable();
            $table->string('mov_num_oper')->nullable();
            $table->decimal('importe', 10, 2)->nullable();
            $table->string('estado')->nullable();                // ACTIVO | ANULADO
            $table->date('mov_fecha')->nullable();
            $table->string('IdEmpresa', 11)->nullable();
            $table->unsignedBigInteger('id_empresa_negocio')->nullable();
            $table->string('registro')->nullable();              // fecha-hora de registro
            $table->decimal('saldo', 10, 2)->nullable();
            $table->unsignedInteger('concepto_id')->nullable();
            $table->unsignedInteger('mov_ban_id')->nullable();
            $table->unsignedInteger('id_turno')->nullable()->index();
            $table->unsignedInteger('IdUsuario')->nullable();
            $table->unsignedInteger('doc_id')->nullable();
            $table->string('mov_num_doc')->nullable();
        });

        Schema::create('cat_movimiento', function (Blueprint $table) {
            $table->string('cat_mov_id', 2)->primary();
            $table->string('cat_mov_des', 25)->nullable();
            $table->string('cat_mov_col', 15)->nullable();
        });
        DB::table('cat_movimiento')->insert([
            ['cat_mov_id' => 'E', 'cat_mov_des' => 'EGRESO', 'cat_mov_col' => 'btn-danger'],
            ['cat_mov_id' => 'I', 'cat_mov_des' => 'INGRESO', 'cat_mov_col' => 'btn-success'],
        ]);

        // ---------------- KARDEX ----------------
        Schema::create('tipo_operacion_sunat', function (Blueprint $table) {
            $table->string('cod_tip_ope', 4)->primary();
            $table->string('des_tip_ope', 100)->nullable();
        });
        $ops = ['01' => 'VENTA NACIONAL', '02' => 'COMPRA NACIONAL', '05' => 'DEVOLUCIÓN RECIBIDA',
            '06' => 'DEVOLUCIÓN ENTREGADA', '07' => 'BONIFICACIÓN', '09' => 'DONACIÓN', '10' => 'SALIDA A PRODUCCIÓN',
            '11' => 'SALIDA POR TRANSFERENCIA ENTRE ALMACENES', '12' => 'RETIRO', '13' => 'MERMAS', '14' => 'DESMEDROS',
            '15' => 'DESTRUCCIÓN', '16' => 'SALDO INICIAL', '19' => 'ENTRADA DE PRODUCCIÓN',
            '21' => 'ENTRADA POR TRANSFERENCIA ENTRE ALMACENES', '24' => 'ENTRADA POR DEVOLUCIÓN DEL CLIENTE',
            '25' => 'SALIDA POR DEVOLUCIÓN AL PROVEEDOR', '28' => 'AJUSTE POR DIFERENCIA DE INVENTARIO', '99' => 'OTROS'];
        DB::table('tipo_operacion_sunat')->insert(
            collect($ops)->map(fn($des, $cod) => ['cod_tip_ope' => $cod, 'des_tip_ope' => $des])->values()->all()
        );

        Schema::create('movimientos_cabecera', function (Blueprint $table) {
            $table->increments('mov_cab_id');
            $table->string('tdocod', 2)->nullable();
            $table->string('serdoc', 4)->nullable();
            $table->integer('numdoc')->nullable();
            $table->unsignedInteger('part_suc')->nullable();
            $table->unsignedInteger('des_suc')->nullable();
            $table->unsignedInteger('part_alm')->nullable();
            $table->unsignedInteger('des_alm')->nullable();
            $table->string('observaciones')->nullable();
            $table->date('fecha')->nullable();
            $table->string('estado')->nullable();
            $table->unsignedInteger('mov_cab_ref')->nullable();
            $table->date('fecha_recep')->nullable();
            $table->unsignedInteger('IdCpe_guia')->nullable();
            $table->unsignedInteger('usu_ent')->nullable();      // usuario entrega / registra
            $table->unsignedInteger('usu_rec')->nullable();
            $table->string('clicod')->nullable();
            $table->unsignedBigInteger('id_empresa_negocio')->nullable();
            // campos propios del sistema nuevo
            $table->char('mov_tip', 1)->nullable();              // I | E
            $table->string('cod_tip_ope', 4)->nullable();
        });

        // Kardex: cada fila es una entrada o salida de un producto con su stock antes y después
        Schema::create('movimientos_productos', function (Blueprint $table) {
            $table->increments('mov_pro_id');
            $table->unsignedInteger('IdProducto')->nullable()->index();
            $table->unsignedInteger('IdProducto_rel')->nullable();   // combo que originó la salida
            $table->decimal('precio', 10, 2)->default(0);
            $table->decimal('cantidad', 20, 5)->nullable();
            $table->decimal('cantidad_equivalente', 10, 2)->nullable();
            $table->decimal('costo', 10, 2)->default(0);
            $table->unsignedInteger('mov_cab_id')->nullable();
            $table->decimal('stock', 15, 5)->default(0);             // stock DESPUÉS del movimiento
            $table->decimal('stock_equivalente', 10, 2)->nullable();
            $table->unsignedInteger('IdCpe_cabecera')->nullable();
            $table->unsignedInteger('com_cab_id')->nullable();
            $table->decimal('stock_inicial', 15, 5)->default(0);     // stock ANTES del movimiento
            $table->string('serie', 4)->nullable();
            $table->string('numero', 11)->nullable();
            $table->string('tdocod', 2)->nullable();
            $table->tinyInteger('tipo')->nullable();                 // 1 = ingreso, 2 = egreso (orden del kardex)
            $table->unsignedBigInteger('id_empresa_negocio')->nullable();
            $table->unsignedInteger('id_almacen')->nullable();
            $table->date('fecha_mov')->nullable();
            $table->timestamp('fecha_registro')->nullable()->useCurrent();
            $table->unsignedInteger('id_almacen_destino')->nullable();
            $table->string('descripcion', 100)->nullable();
            $table->string('cliente', 150)->nullable();
            $table->timestamp('fecha_hora')->nullable()->useCurrent();
            $table->char('mov_tip', 1)->nullable();                  // I | E
            $table->unsignedInteger('id_almacen_origen')->nullable();
            $table->unsignedInteger('inv_cab_id')->nullable();
            $table->string('cod_tip_ope', 4)->nullable();
            $table->string('mov_lote', 50)->nullable();
            $table->date('mov_vencimiento')->nullable();
            $table->unsignedInteger('IdCpe_guia')->nullable();
            $table->unsignedInteger('IdUsuario')->nullable();
            $table->index(['IdProducto', 'id_almacen', 'mov_pro_id']);
        });

        // Un solo registro de stock por producto y almacén
        Schema::table('producto_stock', function (Blueprint $table) {
            $table->unique(['IdProducto', 'id_almacen'], 'producto_almacen_unico');
        });

        // ---------------- MENÚ ----------------
        $urls = [
            'Caja'              => '/turnos',
            'Listar Cajas'      => '/turnos/listado',
            'Kardex'            => '/kardex',
            'Stock Productos'   => '/kardex/stock',
            'Ingresos Productos'=> '/kardex/movimiento?tipo=I',
            'Salidas Productos' => '/kardex/movimiento?tipo=E',
        ];
        foreach ($urls as $nom => $url) {
            DB::table('modulos')->where('mod_nom', $nom)->update(['mod_url' => $url]);
        }
    }

    public function down(): void
    {
        foreach (['Caja', 'Listar Cajas', 'Kardex', 'Stock Productos', 'Ingresos Productos', 'Salidas Productos'] as $nom) {
            DB::table('modulos')->where('mod_nom', $nom)->update(['mod_url' => '#']);
        }
        Schema::table('producto_stock', fn(Blueprint $t) => $t->dropUnique('producto_almacen_unico'));
        foreach (['movimientos_productos', 'movimientos_cabecera', 'tipo_operacion_sunat', 'cat_movimiento',
                  'movimientoscaja', 'tiposcaja', 'turno_medio_pago', 'turnos'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
