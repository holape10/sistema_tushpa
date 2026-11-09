<?php

namespace Tests\Flujos;

use App\Models\Producto;
use App\Models\Turno;
use App\Models\User;
use App\Support\EmpresaInicial;
use App\Support\Estudio;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Pruebas de los flujos de dinero con la base de datos real (MySQL), no con SQLite: las ventas, el stock y SUNAT usan
 * funciones de MySQL. Cada prueba arma su propio negocio desde cero (como uno nuevo del panel) dentro de una
 * transacción que se deshace al terminar: la base de pruebas siempre queda vacía.
 *
 * Base: DB_PRUEBAS_DATABASE (por defecto bd_pruebas_tushpa). Se migra sola la primera vez. Si no hay MySQL, se saltan.
 */
abstract class FlujoTestCase extends TestCase
{
    private static bool $migrada = false;

    protected User $usuario;

    protected Turno $turno;

    protected int $sucursal;

    protected int $almacen;

    protected int $contado;

    protected int $credito;

    protected int $efectivo;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'mysql',
            'database.connections.mysql.database' => env('DB_PRUEBAS_DATABASE', 'bd_pruebas_tushpa'),
        ]);
        DB::purge('mysql');
        try {
            DB::connection('mysql')->getPdo();
        } catch (\Throwable $e) {
            $this->markTestSkipped('Sin MySQL para las pruebas de flujos: '.$e->getMessage());
        }
        if (! self::$migrada) {
            Artisan::call('migrate', ['--database' => 'mysql', '--path' => 'database/migrations', '--force' => true]);
            self::$migrada = true;
        }
        if (! Schema::hasTable('cpe_cabecera')) {
            $this->markTestSkipped('La base de pruebas no tiene las tablas del sistema.');
        }

        // Cada consulta ve lo ya creado (una base temporal recién cargada); con el aislamiento por defecto la transacción
        // de la prueba seguiría viendo la lista de bases de antes
        DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED');
        DB::beginTransaction();
        Carbon::setTestNow(Carbon::parse('2026-10-30 12:00:00'));
        $this->negocioNuevo();
    }

    protected function tearDown(): void
    {
        // El estudio contable se lee una vez por base: que no pase de una prueba a otra
        Estudio::olvidar();
        Carbon::setTestNow();
        Auth::logout();
        if (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        parent::tearDown();
    }

    /** Un negocio como el que crea el panel: empresa, sucursal con sus series, administrador y turno de caja abierto */
    private function negocioNuevo(): void
    {
        $ruc = '20'.random_int(100000000, 999999999);
        $this->usuario = EmpresaInicial::crear(['ruc' => $ruc, 'razon_social' => 'RESTAURANTE DE PRUEBA SAC', 'direccion' => 'JR. PRUEBA 123',
            'usuario' => 'admin'.$ruc.'@prueba.test', 'password' => 'clave-de-prueba', 'envio' => 0]);
        $this->sucursal = (int) $this->usuario->id_empresa_negocio;
        DB::table('empresa_negocios')->where('id_empresa_negocio', $this->sucursal)->update([
            'FseEmpresa' => 'F001', 'FnuEmpresa' => 0, 'BseEmpresa' => 'B001', 'BnuEmpresa' => 0, 'SerNota' => 'N001', 'NumNota' => 0,
            'SerNCF' => 'FC01', 'NumNCF' => 0, 'SerNCB' => 'BC01', 'NumNCB' => 0, 'tip_igv_pred' => '10', 'control_stock' => 'libre',
        ]);
        $this->almacen = (int) DB::table('almacenes')->where('id_empresa_negocio', $this->sucursal)->value('id_almacen');
        $this->contado = (int) DB::table('credito_dias')->where('id_empresa_negocio', $this->sucursal)->where('cre_dia_tip', 'CONTADO')->value('cre_dia_id');
        $this->credito = (int) DB::table('credito_dias')->where('id_empresa_negocio', $this->sucursal)->where('cre_dia_tip', '!=', 'CONTADO')->value('cre_dia_id');
        $this->efectivo = (int) DB::table('medios_pagos')->where('id_empresa_negocio', $this->sucursal)->value('id_med_pag');
        $this->turno = Turno::create(['turno' => 1, 'IdUsuario' => $this->usuario->IdUsuario, 'apertura' => now(), 'IdEmpresa' => $this->usuario->IdEmpresa,
            'id_empresa_negocio' => $this->sucursal, 'estado' => 'ABIERTO', 'monto' => 0]);
        $this->actingAs($this->usuario);
    }

    /** Afectación del IGV de la sucursal: '10' gravado, '20' exonerado (Amazonía) */
    protected function afectacion(string $tip): void
    {
        DB::table('empresa_negocios')->where('id_empresa_negocio', $this->sucursal)->update(['tip_igv_pred' => $tip]);
    }

    /** Producto con stock en el almacén principal */
    protected function producto(string $nombre, float $precio, float $stock = 0): Producto
    {
        $cat = DB::table('categorias')->where('id_empresa_negocio', $this->sucursal)->first();
        $id = DB::table('productos')->insertGetId([
            'procod' => strtoupper(substr(md5($nombre.microtime()), 0, 8)), 'pronom' => $nombre, 'propun' => $precio, 'proest' => 'Activo',
            'promocion' => 0, 'umecod' => 'NIU', 'cat_id' => $cat->cat_id, 'tip_pro_id' => $cat->tip_pro_id,
            'subcat_id' => DB::table('subcategorias')->where('cat_id', $cat->cat_id)->value('subcat_id'),
            'IdEmpresa' => $this->usuario->IdEmpresa, 'id_empresa_negocio' => $this->sucursal,
        ]);
        if ($stock > 0) {
            DB::table('producto_stock')->insert(['IdProducto' => $id, 'id_almacen' => $this->almacen, 'id_empresa_negocio' => $this->sucursal,
                'stock' => $stock, 'stock_inicial' => $stock]);
        }

        return Producto::find($id);
    }

    protected function stock(Producto $p): float
    {
        return (float) DB::table('producto_stock')->where('IdProducto', $p->IdProducto)->where('id_almacen', $this->almacen)->value('stock');
    }

    /** Datos de una venta al contado pagada en efectivo (los items: [[Producto, cantidad, precio?], ...]) */
    protected function venta(array $items, string $tdocod = '03', array $extra = []): array
    {
        $lineas = array_map(fn ($i) => ['id' => $i[0]->IdProducto, 'cantidad' => $i[1], 'precio' => $i[2] ?? (float) $i[0]->propun], $items);
        $total = round(array_sum(array_map(fn ($l) => $l['cantidad'] * $l['precio'], $lineas)), 2);

        return $extra + [
            'tdocod' => $tdocod, 'estadopago' => $this->contado, 'fecEmi' => now()->toDateString(),
            'tdicod' => $tdocod === '01' ? '6' : '1', 'clinum' => $tdocod === '01' ? '20100070970' : '00000000',
            'clinom' => $tdocod === '01' ? 'CLIENTE EMPRESA SAC' : 'VENTA AL PORTADOR', 'clidir' => 'LIMA',
            'items' => $lineas, 'id_med_pag' => [$this->efectivo], 'mon_med_pag' => [$total], 'paga' => $total,
        ];
    }

    protected function cabecera(int $id): object
    {
        return DB::table('cpe_cabecera')->where('IdCpe_cabecera', $id)->first();
    }
}
