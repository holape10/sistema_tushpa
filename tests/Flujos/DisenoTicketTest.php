<?php

namespace Tests\Flujos;

use App\Support\Impresion\Impresion;
use App\Support\Impresion\PlantillaTicket;
use App\Support\VentaDirecta;
use Illuminate\Support\Facades\DB;

/** Diseño del ticket: vista previa en pantalla (sin imprimir) y el diseño "Personalizado" que arma cada empresa */
class DisenoTicketTest extends FlujoTestCase
{
    private function impresora(): int
    {
        return DB::table('configuracion_impresoras')->insertGetId(['descripcion' => 'CAJA', 'ruta' => 'CAJA', 'IdEmpresa' => $this->usuario->IdEmpresa,
            'id_empresa_negocio' => $this->sucursal, 'tip_conex_imp' => 'COMPARTIDO', 'columnas' => 42, 'diseno' => 'CLASICO', 'activo' => 1]);
    }

    /** El diseño de partida, con la razón social oculta y un pie propio */
    private function plantilla(): array
    {
        $partes = PlantillaTicket::inicial();
        foreach ($partes as &$p) {
            if ($p['tipo'] === 'razon_social') {
                $p['visible'] = false;
            }
            if ($p['tipo'] === 'texto') {
                $p['texto'] = "GRACIAS AMIGO\nVUELVA PRONTO";
            }
        }

        return $partes;
    }

    public function test_la_vista_previa_muestra_el_ticket_sin_imprimir_nada(): void
    {
        $imp = $this->impresora();
        VentaDirecta::registrar($this->usuario, $this->venta([[$this->producto('JUANE ESPECIAL', 15, 10), 2]], '03'), 'PV');
        $razon = (string) DB::table('empresa')->where('IdEmpresa', $this->usuario->IdEmpresa)->value('NomEmpresa');

        $this->get(route('impresion.index'))->assertOk()->assertSee('Diseño y vista previa');
        $this->get(route('impresion.diseno', $imp))->assertOk()->assertSee('Vista previa')->assertSee('Partes del ticket');

        // Cualquier diseño se ve con un comprobante real
        $clasico = $this->postJson(route('impresion.vista', $imp), ['diseno' => 'CLASICO', 'columnas' => 42])->assertOk()->json('html');
        $this->assertStringContainsString('JUANE ESPECIAL', $clasico);
        $this->assertStringContainsString(e(mb_substr($razon, 0, 12)), $clasico, 'El nombre de la empresa (en letra grande se parte en dos líneas).');

        // Personalizado: lo que se oculta no sale y el texto propio sí
        $propio = $this->postJson(route('impresion.vista', $imp), ['diseno' => 'PERSONALIZADO', 'columnas' => 32, 'plantilla' => $this->plantilla()])->assertOk()->json('html');
        $this->assertStringContainsString('GRACIAS AMIGO', $propio);
        $this->assertStringContainsString('VUELVA PRONTO', $propio);
        $this->assertStringContainsString('JUANE ESPECIAL', $propio);
        $this->assertStringContainsString('width: calc(32ch', $propio);
        $this->assertSame(0, DB::table('cola_impresion')->count(), 'La vista previa no manda nada a la ticketera.');
    }

    public function test_el_diseno_personalizado_se_guarda_y_sale_asi_en_la_ticketera(): void
    {
        $imp = $this->impresora();
        $id = VentaDirecta::registrar($this->usuario, $this->venta([[$this->producto('JUANE ESPECIAL', 15, 10), 1]], '03'), 'PV');

        $this->post(route('impresion.diseno.guardar', $imp), ['diseno' => 'PERSONALIZADO', 'columnas' => 42, 'plantilla' => json_encode($this->plantilla())])
            ->assertRedirect(route('impresion.diseno', $imp));
        $guardada = DB::table('configuracion_impresoras')->where('Id', $imp)->first();
        $this->assertSame('PERSONALIZADO', $guardada->diseno);

        $bytes = Impresion::ticketComprobante(DB::table('cpe_cabecera')->where('IdCpe_cabecera', $id)->first(), $guardada);
        $this->assertStringContainsString('GRACIAS AMIGO', $bytes);
        $this->assertStringContainsString('JUANE ESPECIAL', $bytes);

        // Lo que no se reconoce no se guarda; si falta una parte, va al final oculta
        $leida = PlantillaTicket::leer([['tipo' => 'virus', 'visible' => true], ['tipo' => 'total', 'tamano' => 'gigante']]);
        $this->assertSame(['total', 'normal'], [$leida[0]['tipo'], $leida[0]['tamano']]);
        $this->assertFalse(collect($leida)->firstWhere('tipo', 'productos')['visible']);
    }
}
