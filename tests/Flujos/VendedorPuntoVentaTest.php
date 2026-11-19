<?php

namespace Tests\Flujos;

use App\Models\User;
use App\Support\Impresion\Impresion;
use App\Support\VentaDirecta;
use Illuminate\Support\Facades\DB;

/** Sucursales › «Elegir el vendedor en el Punto Venta»: varios venden en la misma PC y se elige quién vendió */
class VendedorPuntoVentaTest extends FlujoTestCase
{
    private function vendedora(bool $activa = true, int $rol = VentaDirecta::ROL_VENDEDOR, string $nombre = 'ROSA VENDEDORA'): User
    {
        $u = $this->usuario->replicate();
        $u->email = 'vendedora'.uniqid().'@prueba.test';
        $u->apeusu = $nombre;
        $u->estusu = $activa ? 1 : 0;
        $u->save();
        DB::table('role_user')->insert(['role_id' => $rol, 'user_IdUsuario' => $u->IdUsuario, 'id_empresa_negocio' => $this->sucursal]);

        return $u;
    }

    private function vendedorDe(int $id): int
    {
        return (int) DB::table('cpe_cabecera')->where('IdCpe_cabecera', $id)->value('IdUsuario_ven');
    }

    public function test_apagado_la_venta_es_de_quien_cobra(): void
    {
        $rosa = $this->vendedora();
        $id = VentaDirecta::registrar($this->usuario, $this->venta([[$this->producto('JUANE', 15, 10), 1]], '13', ['vendedor' => $rosa->IdUsuario]), 'PV');

        $this->assertSame((int) $this->usuario->IdUsuario, $this->vendedorDe($id));
        $this->get(route('pv.index'))->assertOk()->assertSee('vendedores: []', false);
    }

    public function test_encendido_se_elige_quien_vendio_y_sale_en_el_ticket(): void
    {
        DB::table('empresa_negocios')->where('id_empresa_negocio', $this->sucursal)->update(['pv_elegir_vendedor' => 1]);
        $rosa = $this->vendedora();
        $inactiva = $this->vendedora(false);
        $cajero = $this->vendedora(true, 4, 'PEDRO CAJERO');

        // Solo los de rol Vendedor
        $this->get(route('pv.index'))->assertOk()->assertSee('ROSA VENDEDORA')->assertSee('¿Quién vendió?')->assertDontSee('PEDRO CAJERO');

        $id = VentaDirecta::registrar($this->usuario, $this->venta([[$this->producto('JUANE', 15, 10), 1]], '13', ['vendedor' => $rosa->IdUsuario]), 'PV');
        $this->assertSame((int) $rosa->IdUsuario, $this->vendedorDe($id));
        $this->assertSame((int) $this->usuario->IdUsuario, (int) DB::table('cpe_cabecera')->where('IdCpe_cabecera', $id)->value('IdUsuario'), 'El cajero sigue siendo quien cobró.');
        $ticket = Impresion::ticketComprobante(DB::table('cpe_cabecera')->where('IdCpe_cabecera', $id)->first(), (object) ['columnas' => 42, 'diseno' => 'CLASICO', 'abrir_cajon' => 0]);
        $this->assertStringContainsString('ROSA VENDEDORA', $ticket);

        // Un usuario inactivo o que no es Vendedor no puede figurar: queda quien cobra
        foreach ([$inactiva, $cajero] as $nadie) {
            $otra = VentaDirecta::registrar($this->usuario, $this->venta([[$this->producto('CECINA '.uniqid(), 20, 10), 1]], '13', ['vendedor' => $nadie->IdUsuario]), 'PV');
            $this->assertSame((int) $this->usuario->IdUsuario, $this->vendedorDe($otra));
        }
    }
}
