<?php

namespace Tests\Flujos;

use App\Models\Producto;
use Illuminate\Support\Facades\DB;

/** Transferencias entre almacenes: inmediatas (como siempre) o en tránsito (entran al destino cuando las reciben) */
class TransferenciasTest extends FlujoTestCase
{
    private int $tienda;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tienda = (int) DB::table('almacenes')->insertGetId(['descripcion' => 'TIENDA NAUTA', 'id_empresa_negocio' => $this->sucursal, 'predeterminado' => 0]);
    }

    private function stockEn(Producto $p, int $almacen): float
    {
        return (float) DB::table('producto_stock')->where('IdProducto', $p->IdProducto)->where('id_almacen', $almacen)->value('stock');
    }

    private function transferir(Producto $p, float $cantidad, ?string $modo = null): int
    {
        $this->post(route('transferencias.store'), array_filter(['part_alm' => $this->almacen, 'des_alm' => $this->tienda, 'fecha' => '2026-10-28',
            'items' => [['IdProducto' => $p->IdProducto, 'cantidad' => $cantidad]], 'modo' => $modo]))->assertSessionHasNoErrors();

        return (int) DB::table('movimientos_cabecera')->where('mov_tip', 'T')->where('id_empresa_negocio', $this->sucursal)->max('mov_cab_id');
    }

    public function test_por_defecto_es_inmediata(): void
    {
        $arroz = $this->producto('ARROZ', 4, 50);

        $id = $this->transferir($arroz, 20);

        $this->assertSame([30.0, 20.0], [$this->stockEn($arroz, $this->almacen), $this->stockEn($arroz, $this->tienda)]);
        $this->assertSame('REGISTRADO', DB::table('movimientos_cabecera')->where('mov_cab_id', $id)->value('estado'));
    }

    public function test_en_transito_sale_del_origen_y_entra_al_destino_al_recibirla(): void
    {
        $arroz = $this->producto('ARROZ', 4, 50);

        $id = $this->transferir($arroz, 20, 'transito');
        $this->assertSame([30.0, 0.0], [$this->stockEn($arroz, $this->almacen), $this->stockEn($arroz, $this->tienda)], 'Va en camino: no está en ningún almacén.');
        $this->assertSame('EN TRANSITO', DB::table('movimientos_cabecera')->where('mov_cab_id', $id)->value('estado'));

        $this->post(route('transferencias.recibir', $id), ['fecha' => '2026-10-29'])->assertSessionHas('success');

        $this->assertSame([30.0, 20.0], [$this->stockEn($arroz, $this->almacen), $this->stockEn($arroz, $this->tienda)]);
        $t = DB::table('movimientos_cabecera')->where('mov_cab_id', $id)->first();
        $this->assertSame(['REGISTRADO', '2026-10-29'], [$t->estado, $t->fecha_recep]);
        $this->assertSame('2026-10-29', DB::table('movimientos_productos')->where('mov_cab_id', $id)->where('mov_tip', 'I')->value('fecha_mov'));

        $this->post(route('transferencias.recibir', $id), ['fecha' => '2026-10-30'])->assertSessionHasErrors('estado');
        $this->assertSame(20.0, $this->stockEn($arroz, $this->tienda), 'No entra dos veces.');
    }

    public function test_anular_una_en_transito_la_devuelve_al_origen(): void
    {
        $arroz = $this->producto('ARROZ', 4, 50);
        $id = $this->transferir($arroz, 20, 'transito');

        $this->post(route('transferencias.anular', $id))->assertSessionHas('success');

        $this->assertSame([50.0, 0.0], [$this->stockEn($arroz, $this->almacen), $this->stockEn($arroz, $this->tienda)]);
        $this->post(route('transferencias.recibir', $id), ['fecha' => '2026-10-30'])->assertSessionHasErrors('estado');
    }

    public function test_anular_una_ya_recibida_la_devuelve_desde_el_destino(): void
    {
        $arroz = $this->producto('ARROZ', 4, 50);
        $id = $this->transferir($arroz, 20);

        $this->post(route('transferencias.anular', $id))->assertSessionHas('success');

        $this->assertSame([50.0, 0.0], [$this->stockEn($arroz, $this->almacen), $this->stockEn($arroz, $this->tienda)]);
    }
}
