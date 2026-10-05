<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

// Base central: planes de suscripción y subdominio propio por empresa (demo.tushpa.app en vez de 99999999999.tushpa.app)
return new class extends Migration {
    public function up(): void
    {
        Schema::create('planes', function (Blueprint $t) {
            $t->id();
            $t->string('nombre', 60);
            $t->decimal('precio', 10, 2)->default(0);              // mensual
            $t->string('descripcion', 255)->nullable();
            $t->text('caracteristicas')->nullable();               // una por línea, se muestran en el panel
            $t->boolean('destacado')->default(false);              // "Más popular"
            $t->unsignedInteger('max_usuarios')->nullable();       // null = ilimitado
            $t->boolean('tienda_virtual')->default(false);
            $t->unsignedInteger('orden')->default(0);
            $t->boolean('activo')->default(true);
            $t->timestamps();
        });

        $ahora = now();
        DB::table('planes')->insert([
            ['nombre' => 'BÁSICO', 'precio' => 50, 'descripcion' => 'Para empezar a facturar', 'destacado' => false, 'max_usuarios' => 2, 'tienda_virtual' => false, 'orden' => 1,
             'caracteristicas' => "Facturas, boletas y notas de venta\nPunto de venta\nProductos e inventario\nHasta 2 usuarios", 'activo' => true, 'created_at' => $ahora, 'updated_at' => $ahora],
            ['nombre' => 'MEDIO', 'precio' => 100, 'descripcion' => 'El más popular', 'destacado' => true, 'max_usuarios' => 5, 'tienda_virtual' => true, 'orden' => 2,
             'caracteristicas' => "Todo lo del plan Básico\nCompras, kardex y reportes\nTienda virtual\nHasta 5 usuarios", 'activo' => true, 'created_at' => $ahora, 'updated_at' => $ahora],
            ['nombre' => 'AVANZADO', 'precio' => 200, 'descripcion' => 'Para negocios que crecen', 'destacado' => false, 'max_usuarios' => null, 'tienda_virtual' => true, 'orden' => 3,
             'caracteristicas' => "Todo lo del plan Medio\nContabilidad, planilla y SIRE\nVarias sucursales\nUsuarios ilimitados", 'activo' => true, 'created_at' => $ahora, 'updated_at' => $ahora],
        ]);

        Schema::table('clientes', function (Blueprint $t) {
            $t->string('subdominio', 40)->nullable()->unique()->after('ruc');
            $t->foreignId('plan_id')->nullable()->after('plan');
        });
    }

    public function down(): void
    {
        Schema::table('clientes', fn(Blueprint $t) => $t->dropColumn(['subdominio', 'plan_id']));
        Schema::dropIfExists('planes');
    }
};
