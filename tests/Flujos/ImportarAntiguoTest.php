<?php

namespace Tests\Flujos;

use App\Models\Producto;
use App\Support\Antiguo\Importador;
use App\Support\MenusUsuarios;
use Illuminate\Support\Facades\DB;

/**
 * Migración desde el sistema anterior (como WILCAT): un respaldo en miniatura con lo que trae uno real — productos de una
 * sola lista con precio por sucursal, stock negativo, unidades inventadas, un número de boleta repetido, respuestas de
 * SUNAT por código, notas de pedido que reinician su número cada día, turnos y kardex.
 */
class ImportarAntiguoTest extends FlujoTestCase
{
    /** Como las que carga la pantalla: antiguo_{RUC}_{fecha} (solo esas se ven desde la empresa) */
    private string $base;

    protected function setUp(): void
    {
        parent::setUp();
        $this->base = 'antiguo_'.$this->usuario->IdEmpresa.'_20261030_120000';
        // La base antigua se crea por otra conexión: un CREATE DATABASE en la de la prueba cerraría su transacción
        config(['database.connections.antiguo_admin' => array_merge(config('database.connections.mysql'), ['database' => null])]);
        DB::connection('antiguo_admin')->statement('DROP DATABASE IF EXISTS `'.$this->base.'`');
        DB::connection('antiguo_admin')->statement('CREATE DATABASE `'.$this->base.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        Importador::conectar($this->base);
        $this->respaldoAntiguo();
    }

    protected function tearDown(): void
    {
        DB::connection('antiguo_admin')->statement('DROP DATABASE IF EXISTS `'.$this->base.'`');
        DB::purge(Importador::CONEXION);
        parent::tearDown();
    }

    private function respaldoAntiguo(): void
    {
        $a = DB::connection(Importador::CONEXION);
        $ruc = $this->usuario->IdEmpresa;
        $tablas = [
            'empresa_negocios' => 'id_empresa_negocio INT, IdEmpresa VARCHAR(11), nombre_comercial VARCHAR(100), FseEmpresa VARCHAR(4), FnuEmpresa VARCHAR(8),
                BseEmpresa VARCHAR(4), BnuEmpresa VARCHAR(8), FcseEmpresa VARCHAR(4), FcnuEmpresa VARCHAR(8), ProSer VARCHAR(4), ProNum VARCHAR(8)',
            'almacenes' => 'id_almacen INT, descripcion VARCHAR(50), id_empresa_negocio INT, predeterminado INT',
            'unidad_medida' => 'umecod VARCHAR(3), umenom VARCHAR(50), umecin VARCHAR(5)',
            'productos' => 'IdProducto INT, procod VARCHAR(20), pronom VARCHAR(150), umecod VARCHAR(3), propun DECIMAL(10,2), propun1 DECIMAL(10,2),
                costo DECIMAL(10,2), promocion INT, tipo INT, pro_rel INT NULL, id_empresa_negocio INT',
            'producto_empresa' => 'IdProducto INT, id_empresa_negocio INT, precio DECIMAL(10,2), precio2 DECIMAL(10,2), precio3 DECIMAL(10,2)',
            'producto_stock' => 'IdProducto INT, id_empresa_negocio INT, id_almacen INT, stock DECIMAL(10,2)',
            'medios_pagos' => 'id_med_pag INT, nom_med_pag VARCHAR(50), id_empresa_negocio INT',
            'users' => 'IdUsuario INT, name VARCHAR(50), email VARCHAR(50), password VARCHAR(100), estusu INT, id_empresa_negocio INT',
            'role_user' => 'role_id INT, user_IdUsuario INT',
            'turnos' => 'id_turno INT, IdUsuario INT, apertura DATETIME, cierre DATETIME NULL, estado VARCHAR(20), monto DECIMAL(10,2), id_empresa_negocio INT',
            'usuario_facturacion' => 'IdCpe_cabecera INT, id_turno INT',
            'cpe_cabecera' => 'IdCpe_cabecera INT, tdocod VARCHAR(2), serdoc VARCHAR(4), numdoc INT, ccafem DATE, fecha_hora DATETIME, tdicod VARCHAR(1),
                ccandi VARCHAR(15), ccanom VARCHAR(100), ccaitv DECIMAL(12,2), ccatexo DECIMAL(12,2), moncod VARCHAR(3), estadopago VARCHAR(10),
                est_sunat VARCHAR(11), estado VARCHAR(20), ccacodsun VARCHAR(5), ccabaj DATE NULL, enviado INT, facturado INT,
                IdUsuario INT, IdUsuario_ven INT, id_empresa_negocio INT',
            'cpe_detalle' => 'IdCpe_detalle INT, IdCpe_cabecera INT, IdProducto INT, cdedes VARCHAR(150), cdecan DECIMAL(12,3), cdepuni DECIMAL(12,2), umecod VARCHAR(3)',
            'venta_medio_pago' => 'IdCpe_cabecera INT, id_med_pag INT, monto DECIMAL(12,2)',
            'movimientos_productos' => 'mov_pro_id INT, IdProducto INT, cantidad DECIMAL(12,3), stock DECIMAL(12,3), mov_tip VARCHAR(1), tdocod VARCHAR(2),
                IdCpe_cabecera INT, id_almacen INT, id_empresa_negocio INT, fecha_mov DATE, fecha_hora DATETIME NULL, fecha_registro DATETIME NULL',
        ];
        foreach ($tablas as $tabla => $columnas) {
            $a->statement("CREATE TABLE {$tabla} ({$columnas})");
        }

        $a->table('empresa_negocios')->insert(['id_empresa_negocio' => 20, 'IdEmpresa' => $ruc, 'nombre_comercial' => 'QUIÑONES',
            'FseEmpresa' => 'F002', 'FnuEmpresa' => '00000050', 'BseEmpresa' => 'B002', 'BnuEmpresa' => '00000100',
            'FcseEmpresa' => 'FN01', 'FcnuEmpresa' => '00000005', 'ProSer' => 'PR02', 'ProNum' => '00000007']);
        $a->table('empresa_negocios')->insert(['id_empresa_negocio' => 22, 'IdEmpresa' => $ruc, 'nombre_comercial' => 'TIENDA 2']);
        $a->table('almacenes')->insert(['id_almacen' => 20, 'descripcion' => 'TIENDA', 'id_empresa_negocio' => 20, 'predeterminado' => 1]);
        $a->table('unidad_medida')->insert([['umecod' => 'NIU', 'umenom' => 'Unidad', 'umecin' => 'UNI'],
            ['umecod' => '3H', 'umenom' => 'Kilogramo', 'umecin' => 'KG'], ['umecod' => 'PLA', 'umenom' => 'PLANCHA', 'umecin' => 'PLA']]);
        // Una sola lista de productos (de otra sucursal): esta sucursal los tiene por su precio en producto_empresa
        $a->table('productos')->insert([
            ['IdProducto' => 1, 'procod' => '001', 'pronom' => 'CEMENTO SOL', 'umecod' => '3H', 'propun' => 30, 'propun1' => 28, 'costo' => 25, 'promocion' => 0, 'tipo' => 1, 'id_empresa_negocio' => 99],
            ['IdProducto' => 2, 'procod' => '002', 'pronom' => 'TRIPLAY', 'umecod' => 'PLA', 'propun' => 5, 'propun1' => 0, 'costo' => 3, 'promocion' => 0, 'tipo' => 1, 'id_empresa_negocio' => 99],
        ]);
        $a->table('producto_empresa')->insert([['IdProducto' => 1, 'id_empresa_negocio' => 20, 'precio' => 32, 'precio2' => 29, 'precio3' => 0],
            ['IdProducto' => 2, 'id_empresa_negocio' => 20, 'precio' => 0, 'precio2' => 0, 'precio3' => 0]]);
        $a->table('producto_stock')->insert([['IdProducto' => 1, 'id_empresa_negocio' => 20, 'id_almacen' => 20, 'stock' => -7],
            ['IdProducto' => 2, 'id_empresa_negocio' => 20, 'id_almacen' => 20, 'stock' => 15]]);
        $a->table('medios_pagos')->insert(['id_med_pag' => 12, 'nom_med_pag' => 'EFECTIVO', 'id_empresa_negocio' => null]);
        $a->table('users')->insert(['IdUsuario' => 71, 'name' => 'REYNA', 'email' => 'reyna'.$ruc, 'password' => bcrypt('clave-antigua'), 'estusu' => 1, 'id_empresa_negocio' => 20]);
        // Vendedor de la otra sucursal (22) que también vendió en esta
        $a->table('users')->insert(['IdUsuario' => 72, 'name' => 'NICOLAS', 'email' => 'nicolas'.$ruc, 'password' => bcrypt('clave-antigua'), 'estusu' => 1, 'id_empresa_negocio' => 22]);
        $a->table('role_user')->insert([['role_id' => 5, 'user_IdUsuario' => 71], ['role_id' => 5, 'user_IdUsuario' => 72]]);
        // El sistema antiguo dejó este turno abierto
        $a->table('turnos')->insert(['id_turno' => 500, 'IdUsuario' => 71, 'apertura' => '2026-10-01 08:00:00', 'cierre' => null, 'estado' => 'Aperturado',
            'monto' => 100, 'id_empresa_negocio' => 20]);

        $cpe = fn (int $id, string $td, string $serie, int $num, float $total, array $extra = []) => $extra + ['IdCpe_cabecera' => $id, 'tdocod' => $td,
            'serdoc' => $serie, 'numdoc' => $num, 'ccafem' => '2026-10-01', 'fecha_hora' => '2026-10-01 10:00:00', 'tdicod' => '1', 'ccandi' => '00000000',
            'ccanom' => 'VARIOS', 'ccaitv' => $total, 'ccatexo' => $total, 'moncod' => 'PEN', 'estadopago' => 'CONTADO', 'est_sunat' => null, 'estado' => null,
            'ccacodsun' => null, 'ccabaj' => null, 'enviado' => 1, 'facturado' => 0, 'IdUsuario' => 71, 'IdUsuario_ven' => 71, 'id_empresa_negocio' => 20];
        $a->table('cpe_cabecera')->insert([
            // La misma boleta dos veces: una anulada en 0 y la buena
            $cpe(1001, '03', 'B002', 10, 0, ['estado' => 'ANULADO', 'ccabaj' => '2026-10-01', 'ccacodsun' => '8']),
            $cpe(1002, '03', 'B002', 10, 64, ['ccacodsun' => '0']),
            // 1033: SUNAT ya la tenía registrada de un envío anterior (está aceptada); sin medio de pago guardado
            $cpe(1003, '01', 'F002', 20, 30, ['ccacodsun' => '1033', 'enviado' => 0]),
            $cpe(1004, '15', 'PR02', 7, 90),
            // Notas de pedido: una ya cobrada (su boleta ya está) y dos pendientes con el mismo número en días distintos
            $cpe(1005, '16', 'NP01', 1, 64, ['facturado' => 1]),
            $cpe(1006, '16', 'NP01', 1, 32, ['ccafem' => '2026-10-02']),
            $cpe(1007, '16', 'NP01', 1, 5, ['ccafem' => '2026-10-03']),
            $cpe(1008, '03', 'B002', 11, 32, ['ccacodsun' => '0', 'IdUsuario_ven' => 72]),
        ]);
        $a->table('cpe_detalle')->insert([
            ['IdCpe_detalle' => 1, 'IdCpe_cabecera' => 1002, 'IdProducto' => 1, 'cdedes' => 'CEMENTO SOL', 'cdecan' => 2, 'cdepuni' => 32, 'umecod' => '3H'],
            ['IdCpe_detalle' => 2, 'IdCpe_cabecera' => 1003, 'IdProducto' => 1, 'cdedes' => 'CEMENTO SOL', 'cdecan' => 1, 'cdepuni' => 30, 'umecod' => '3H'],
            ['IdCpe_detalle' => 3, 'IdCpe_cabecera' => 1006, 'IdProducto' => 1, 'cdedes' => 'CEMENTO SOL', 'cdecan' => 1, 'cdepuni' => 32, 'umecod' => '3H'],
        ]);
        $a->table('venta_medio_pago')->insert(['IdCpe_cabecera' => 1002, 'id_med_pag' => 12, 'monto' => 64]);
        $a->table('usuario_facturacion')->insert([['IdCpe_cabecera' => 1002, 'id_turno' => 500], ['IdCpe_cabecera' => 1003, 'id_turno' => 500]]);
        $a->table('movimientos_productos')->insert([
            ['mov_pro_id' => 1, 'IdProducto' => 1, 'cantidad' => 4, 'stock' => 4, 'mov_tip' => 'I', 'tdocod' => '82', 'IdCpe_cabecera' => null, 'id_almacen' => 20, 'id_empresa_negocio' => 20, 'fecha_mov' => '2026-09-30'],
            ['mov_pro_id' => 2, 'IdProducto' => 1, 'cantidad' => 9, 'stock' => -5, 'mov_tip' => 'E', 'tdocod' => '03', 'IdCpe_cabecera' => 1002, 'id_almacen' => 20, 'id_empresa_negocio' => 20, 'fecha_mov' => '2026-10-01'],
        ]);
    }

    private function importar(): array
    {
        return (new Importador)->importar($this->usuario, 20, ['secciones' => array_keys(Importador::SECCIONES), 'dia0' => 'domingo']);
    }

    private function importado(string $nombre): object
    {
        return DB::table('productos')->where('id_empresa_negocio', $this->sucursal)->where('pronom', $nombre)->first();
    }

    public function test_cada_sucursal_recibe_sus_productos_con_su_precio_y_unidades_oficiales_de_sunat(): void
    {
        $this->importar();

        $cemento = $this->importado('CEMENTO SOL');
        $this->assertEquals(32.0, (float) $cemento->propun, 'El precio de esta sucursal, no el general.');
        $this->assertEquals(29.0, (float) $cemento->precio2);
        $this->assertNull($cemento->precio3);
        $this->assertSame('KGM', $cemento->umecod, '3H "Kilogramo" se cambia por el código oficial.');
        $this->assertSame('NIU', $this->importado('TRIPLAY')->umecod, 'PLA no existe en SUNAT: queda como Unidad.');
        $this->assertEquals(5.0, (float) $this->importado('TRIPLAY')->propun, 'Sin precio de la sucursal, el general.');
        $this->assertFalse(DB::table('unidad_medida')->whereIn('umecod', ['3H', 'PLA'])->exists());
    }

    public function test_el_stock_queda_igual_al_antiguo_aunque_sea_negativo_con_su_kardex_historico(): void
    {
        $this->importar();

        $this->assertEquals(-7.0, $this->stock(Producto::find($this->importado('CEMENTO SOL')->IdProducto)));
        $this->assertEquals(15.0, $this->stock(Producto::find($this->importado('TRIPLAY')->IdProducto)));
        $venta = DB::table('cpe_cabecera')->where('serdoc', 'B002')->where('numdoc', 10)->value('IdCpe_cabecera');
        $this->assertSame(1, DB::table('movimientos_productos')->where('IdCpe_cabecera', $venta)->where('mov_tip', 'E')->count(),
            'El kardex antiguo queda enlazado a la venta importada.');
    }

    public function test_las_ventas_quedan_con_su_estado_sunat_vendedor_turno_y_sin_duplicados(): void
    {
        $this->importar();

        $boletas = DB::table('cpe_cabecera')->where('id_empresa_negocio', $this->sucursal)->where('serdoc', 'B002')->where('numdoc', 10)->get();
        $this->assertCount(1, $boletas);
        $this->assertEquals(64.0, (float) $boletas[0]->ccaitv, 'De la boleta repetida queda la aceptada, no la anulada en 0.');
        $this->assertSame('ACEPTADO', $boletas[0]->est_sunat);

        $factura = DB::table('cpe_cabecera')->where('serdoc', 'F002')->where('numdoc', 20)->first();
        $this->assertSame('ACEPTADO', $factura->est_sunat, '1033 = ya registrada en SUNAT antes: está aceptada.');
        $vendedora = DB::table('users')->where('email', 'reyna'.$this->usuario->IdEmpresa)->first();
        $this->assertEquals($vendedora->IdUsuario, $factura->IdUsuario_ven);
        $this->assertSame([5], DB::table('role_user')->where('user_IdUsuario', $vendedora->IdUsuario)->pluck('role_id')->map(fn ($r) => (int) $r)->all());
        $this->assertEquals(30.0, (float) DB::table('venta_medio_pago')->where('IdCpe_cabecera', $factura->IdCpe_cabecera)->sum('monto'),
            'Al contado sin medio de pago antiguo: queda cobrada en efectivo.');

        $turno = DB::table('turnos')->where('id_turno', $factura->id_turno)->first();
        $this->assertSame('CERRADO', $turno->estado, 'El turno que el sistema antiguo dejó abierto entra cerrado.');
        $this->assertEquals($vendedora->IdUsuario, $turno->IdUsuario);
    }

    public function test_las_proformas_y_solo_las_notas_de_pedido_sin_cobrar_quedan_para_la_caja(): void
    {
        $this->importar();

        $proformas = DB::table('proformas')->where('id_empresa_negocio', $this->sucursal)->orderBy('id_proforma')->get();
        $this->assertSame(['PR02-7', 'NP01-1', 'NP01-2'], $proformas->map(fn ($p) => $p->serie.'-'.$p->numero)->all(),
            'La nota de pedido cobrada no pasa; las pendientes con el mismo número reciben uno nuevo.');
        $this->assertSame(['PENDIENTE'], $proformas->pluck('estado')->unique()->values()->all());
        $this->assertSame('Nota de pedido NP01-1 del 03/10/2026', $proformas[2]->observaciones);
        $this->assertSame(1, DB::table('proforma_detalle')->where('id_proforma', $proformas[1]->id_proforma)->count());
    }

    public function test_cada_correlativo_sigue_la_serie_que_usaba_la_sucursal_antigua(): void
    {
        $this->importar();

        $s = DB::table('empresa_negocios')->where('id_empresa_negocio', $this->sucursal)->first();
        $this->assertSame(['F002', 50, 'B002', 100, 'FN01', 5, 'PR02', 7],
            [$s->FseEmpresa, (int) $s->FnuEmpresa, $s->BseEmpresa, (int) $s->BnuEmpresa, $s->SerNCF, (int) $s->NumNCF, $s->SerProforma, (int) $s->NumProforma]);
    }

    public function test_importar_dos_veces_no_duplica(): void
    {
        $this->importar();
        $this->importar();

        $this->assertSame(3, DB::table('cpe_cabecera')->where('id_empresa_negocio', $this->sucursal)->count());
        $this->assertSame(3, DB::table('proformas')->where('id_empresa_negocio', $this->sucursal)->count());
        $this->assertSame(1, DB::table('turnos')->where('id_empresa_negocio', $this->sucursal)->where('estado', 'CERRADO')->count());
    }

    public function test_desde_la_pantalla_se_importan_todas_las_sucursales_y_cada_venta_queda_con_su_vendedor(): void
    {
        $otra = DB::table('empresa_negocios')->insertGetId(['IdEmpresa' => $this->usuario->IdEmpresa, 'tipo_negocio' => 'TIENDA 2',
            'nombre_comercial' => 'TIENDA 2', 'direccion' => 'JR. PRUEBA 2', 'estado' => 'Activo']);
        DB::table('almacenes')->insert(['descripcion' => 'TIENDA 2', 'predeterminado' => 1, 'id_empresa_negocio' => $otra, 'direccion' => '-', 'ubigeo' => '']);

        $this->post(route('importar.ejecutar'), ['bd' => $this->base, 'mapa' => [20 => $this->sucursal, 22 => $otra],
            'secciones' => array_keys(Importador::SECCIONES), 'dia0' => 'domingo'])
            ->assertOk()->assertSee('Importación terminada')->assertSee('QUIÑONES')->assertSee('TIENDA 2');

        $nicolas = DB::table('users')->where('email', 'nicolas'.$this->usuario->IdEmpresa)->first();
        $this->assertEquals($otra, $nicolas->id_empresa_negocio, 'Cada usuario queda en su sucursal.');
        $this->assertEquals($nicolas->IdUsuario, DB::table('cpe_cabecera')->where('serdoc', 'B002')->where('numdoc', 11)->value('IdUsuario_ven'),
            'La venta de la principal hecha por el vendedor de la otra sucursal queda con su vendedor.');
    }

    public function test_la_pantalla_propone_a_que_sucursal_va_cada_sucursal_antigua(): void
    {
        $this->get(route('importar.revisar', ['bd' => $this->base]))->assertOk()
            ->assertSee('¿A qué sucursal va cada una?')->assertSee('name="mapa[20]"', false)->assertSee('name="mapa[22]"', false);
    }

    public function test_dos_sucursales_antiguas_no_pueden_ir_a_la_misma(): void
    {
        $this->post(route('importar.ejecutar'), ['bd' => $this->base, 'mapa' => [20 => $this->sucursal, 22 => $this->sucursal],
            'secciones' => ['clientes'], 'dia0' => 'domingo'])->assertSessionHasErrors('mapa');
    }

    public function test_los_usuarios_importados_solo_reciben_el_menu_de_la_empresa(): void
    {
        // El panel le dio a la empresa solo estas opciones (el menú de su administrador principal)
        $menu = DB::table('modulos')->whereIn('mod_nom', ['Inicio', 'Punto Venta', 'Proformas'])->pluck('mod_id')->all();
        DB::table('modulos_usuario')->where('user_IdUsuario', $this->usuario->IdUsuario)->delete();
        foreach ($menu as $m) {
            DB::table('modulos_usuario')->insert(['user_IdUsuario' => $this->usuario->IdUsuario, 'mod_id' => $m]);
        }

        $this->importar();

        $vendedora = DB::table('users')->where('email', 'reyna'.$this->usuario->IdEmpresa)->value('IdUsuario');
        $suyos = DB::table('modulos_usuario as mu')->join('modulos as m', 'm.mod_id', '=', 'mu.mod_id')
            ->where('mu.user_IdUsuario', $vendedora)->orderBy('m.mod_nom')->pluck('m.mod_nom')->all();
        $this->assertSame(['Inicio', 'Proformas', 'Punto Venta'], $suyos, 'Lo de su rol (vendedor) que la empresa tiene; nada más.');
    }

    public function test_recortar_quita_solo_las_opciones_que_la_empresa_no_tiene(): void
    {
        $menu = DB::table('modulos')->whereIn('mod_nom', ['Inicio', 'Punto Venta'])->pluck('mod_id')->all();
        $extra = DB::table('modulos')->where('mod_nom', 'Planillas y Boletas')->value('mod_id');
        DB::table('modulos_usuario')->where('user_IdUsuario', $this->usuario->IdUsuario)->delete();
        foreach ($menu as $m) {
            DB::table('modulos_usuario')->insert(['user_IdUsuario' => $this->usuario->IdUsuario, 'mod_id' => $m]);
        }
        $otro = $this->usuario->replicate();
        $otro->email = 'importado'.uniqid();
        $otro->save();
        DB::table('role_user')->insert(['role_id' => 2, 'user_IdUsuario' => $otro->IdUsuario, 'id_empresa_negocio' => $this->sucursal]);
        foreach (array_merge($menu, [$extra]) as $m) {
            DB::table('modulos_usuario')->insert(['user_IdUsuario' => $otro->IdUsuario, 'mod_id' => $m]);
        }

        $this->assertSame([['usuario' => $otro->email, 'quitar' => 1]], MenusUsuarios::recortar(false));
        $this->assertSame(3, DB::table('modulos_usuario')->where('user_IdUsuario', $otro->IdUsuario)->count(), 'Sin aplicar no se toca nada.');

        MenusUsuarios::recortar(true);
        $this->assertEqualsCanonicalizing($menu, DB::table('modulos_usuario')->where('user_IdUsuario', $otro->IdUsuario)->pluck('mod_id')->map(fn ($m) => (int) $m)->all());
        $this->assertSame(2, DB::table('modulos_usuario')->where('user_IdUsuario', $this->usuario->IdUsuario)->count(), 'El administrador principal no se toca.');
    }
}
