<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * Farmacia: lotes con fecha de vencimiento por producto y almacén.
 * - Al comprar se ingresa el lote y su vencimiento (obligatorio en productos con control de lote).
 * - Al vender se descuenta primero el lote que vence antes (FEFO).
 * - El stock que no pertenece a ningún lote (anterior a usar lotes) es: producto_stock.stock - SUM(producto_lote.stock).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->boolean('control_lote')->default(false)->after('stock_min');
        });

        Schema::create('producto_lote', function (Blueprint $table) {
            $table->increments('id_lote');
            $table->unsignedInteger('IdProducto');
            $table->unsignedInteger('id_almacen');
            $table->unsignedBigInteger('id_empresa_negocio')->nullable();
            $table->string('lote', 50);
            $table->date('vencimiento')->nullable();
            $table->decimal('stock', 15, 5)->default(0);
            $table->timestamps();
            $table->unique(['IdProducto', 'id_almacen', 'lote'], 'producto_lote_unico');
            $table->index(['id_empresa_negocio', 'vencimiento']);
        });

        // Lotes que salieron en cada línea de venta (para el ticket y consultas de trazabilidad)
        Schema::table('cpe_detalle', function (Blueprint $table) {
            $table->string('lotes')->nullable();
        });

        // Días de anticipación para avisar "pronto a vencer"
        Schema::table('empresa_negocios', function (Blueprint $table) {
            $table->unsignedSmallInteger('dias_alerta_vencimiento')->default(90);
        });

        // Lotes que ya se registraron en compras antes de este cambio: su saldo sale del kardex
        $saldos = DB::table('movimientos_productos')
            ->whereNotNull('mov_lote')->where('mov_lote', '!=', '')
            ->groupBy('IdProducto', 'id_almacen', 'mov_lote')
            ->select('IdProducto', 'id_almacen', 'mov_lote',
                DB::raw("SUM(CASE WHEN mov_tip = 'I' THEN cantidad ELSE -cantidad END) as saldo"),
                DB::raw('MAX(mov_vencimiento) as vencimiento'), DB::raw('MAX(id_empresa_negocio) as sucursal'))
            ->get();
        foreach ($saldos as $s) {
            if ($s->IdProducto && $s->id_almacen && $s->saldo > 0) {
                DB::table('producto_lote')->insert([
                    'IdProducto' => $s->IdProducto, 'id_almacen' => $s->id_almacen, 'id_empresa_negocio' => $s->sucursal,
                    'lote' => mb_substr($s->mov_lote, 0, 50), 'vencimiento' => $s->vencimiento, 'stock' => $s->saldo,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                DB::table('productos')->where('IdProducto', $s->IdProducto)->update(['control_lote' => 1]);
            }
        }

        // ---- Menú: PV Farmacia (Principal) y Lotes y Vencimientos (Almacén) ----
        $modulos = [
            ['mod_nom' => 'PV Farmacia', 'mod_url' => '/pv-farmacia', 'mod_gen' => 'Principal', 'roles' => [2, 4]],
            ['mod_nom' => 'Lotes y Vencimientos', 'mod_url' => '/lotes', 'mod_gen' => 'Almacén', 'roles' => [2, 4]],
        ];
        foreach ($modulos as $m) {
            if (DB::table('modulos')->where('mod_url', $m['mod_url'])->exists()) {
                continue;
            }
            $modId = DB::table('modulos')->insertGetId(['mod_nom' => $m['mod_nom'], 'mod_url' => $m['mod_url'], 'mod_gen' => $m['mod_gen']]);
            foreach (DB::table('role_user')->whereIn('role_id', $m['roles'])->pluck('user_IdUsuario')->unique() as $userId) {
                DB::table('modulos_usuario')->insertOrIgnore(['user_IdUsuario' => $userId, 'mod_id' => $modId]);
            }
        }
    }

    public function down(): void
    {
        foreach (['/pv-farmacia', '/lotes'] as $url) {
            $mod = DB::table('modulos')->where('mod_url', $url)->value('mod_id');
            if ($mod) {
                DB::table('modulos_usuario')->where('mod_id', $mod)->delete();
                DB::table('modulos')->where('mod_id', $mod)->delete();
            }
        }
        Schema::table('empresa_negocios', fn(Blueprint $t) => $t->dropColumn('dias_alerta_vencimiento'));
        Schema::table('cpe_detalle', fn(Blueprint $t) => $t->dropColumn('lotes'));
        Schema::dropIfExists('producto_lote');
        Schema::table('productos', fn(Blueprint $t) => $t->dropColumn('control_lote'));
    }
};
