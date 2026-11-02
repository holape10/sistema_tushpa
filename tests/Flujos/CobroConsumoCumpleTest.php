<?php

namespace Tests\Flujos;

use App\Models\Empresa;
use App\Models\EmpresaNegocio;
use App\Support\Sunat\SunatService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Cobro "por consumo" (una sola línea POR CONSUMO, también ante SUNAT) y el cumpleaños del cliente con DNI
 * (se guarda al cobrar y da puntos dobles en su mes si la sucursal lo activó).
 */
class CobroConsumoCumpleTest extends FlujoTestCase
{
    private int $mesa;

    protected function setUp(): void
    {
        parent::setUp();
        $piso = DB::table('pisos')->insertGetId(['pis_nom' => 'SALON', 'id_empresa_negocio' => $this->sucursal]);
        $this->mesa = DB::table('mesas')->insertGetId(['mes_nom' => 'M1', 'pis_id' => $piso, 'mes_est' => 'Libre',
            'IdEmpresa' => $this->usuario->IdEmpresa, 'id_empresa_negocio' => $this->sucursal]);
    }

    private function pedir(array $platos): int
    {
        $this->postJson(route('comandas.set_servicio'), ['order_type' => 'salon', 'mesa_id' => $this->mesa, 'mesa_nombre' => 'M1'])->assertOk();
        foreach ($platos as [$producto, $veces]) {
            for ($i = 0; $i < $veces; $i++) {
                $this->postJson(route('comandas.add_to_cart'), ['id' => $producto->IdProducto])->assertOk();
            }
        }
        $this->postJson(route('comandas.enviar'))->assertOk();

        return (int) DB::table('pedidos')->where('mes_id', $this->mesa)->where('ped_est', 'Aperturado')->value('ped_id');
    }

    private function cobrar(int $pedId, float $monto, array $extra = []): array
    {
        return $this->postJson(route('cobros.registrar'), $extra + [
            'ped_id' => $pedId, 'tdocod' => '03', 'estadopago' => $this->contado, 'fecEmi' => now()->toDateString(),
            'tdicod' => '1', 'clinum' => '00000000', 'clinom' => 'VENTA AL PORTADOR',
            'id_med_pag' => [$this->efectivo], 'mon_med_pag' => [$monto], 'paga' => $monto,
        ])->assertOk()->json();
    }

    public function test_por_consumo_se_imprime_y_va_a_sunat_con_una_sola_linea(): void
    {
        $lomo = $this->producto('LOMO SALTADO', 28, 10);
        $cerveza = $this->producto('CERVEZA', 9, 10);
        $r = $this->cobrar($this->pedir([[$lomo, 3], [$cerveza, 2]]), 102, ['consumo' => 1]);
        $this->assertSame('success', $r['estado'] ?? null, $r['mensaje'] ?? '');

        $cab = DB::table('cpe_cabecera')->where('id_empresa_negocio', $this->sucursal)->latest('IdCpe_cabecera')->first();
        $this->assertSame(1, (int) $cab->consumo);
        $this->assertSame(2, DB::table('cpe_detalle')->where('IdCpe_cabecera', $cab->IdCpe_cabecera)->count(), 'El sistema guarda el detalle (stock, reportes).');
        $this->get(route('cobros.voucher', $cab->IdCpe_cabecera))->assertOk()->assertSee('POR CONSUMO')->assertDontSee('LOMO SALTADO');

        // SUNAT recibe lo mismo que sale impreso
        $sunat = new SunatService(Empresa::findOrFail($cab->IdEmpresa), EmpresaNegocio::findOrFail($this->sucursal));
        $armar = new \ReflectionMethod($sunat, 'construirComprobante');
        $detalles = $armar->invoke($sunat, $cab)->getDetails();
        $this->assertCount(1, $detalles);
        $this->assertSame('POR CONSUMO', $detalles[0]->getDescripcion());
        $this->assertEqualsWithDelta(102.0, $detalles[0]->getMtoValorVenta() + $detalles[0]->getIgv(), 0.01);
    }

    public function test_sin_por_consumo_sunat_recibe_el_detalle(): void
    {
        $lomo = $this->producto('LOMO SALTADO', 28, 10);
        $this->cobrar($this->pedir([[$lomo, 1]]), 28);
        $cab = DB::table('cpe_cabecera')->where('id_empresa_negocio', $this->sucursal)->latest('IdCpe_cabecera')->first();

        $sunat = new SunatService(Empresa::findOrFail($cab->IdEmpresa), EmpresaNegocio::findOrFail($this->sucursal));
        $detalles = (new \ReflectionMethod($sunat, 'construirComprobante'))->invoke($sunat, $cab)->getDetails();
        $this->assertSame('LOMO SALTADO', $detalles[0]->getDescripcion());
    }

    public function test_el_punto_de_venta_respeta_por_consumo_y_el_telefono(): void
    {
        $lomo = $this->producto('LOMO SALTADO', 28, 10);
        $r = $this->postJson(route('cobros.directa.registrar'), [
            'tdocod' => '13', 'estadopago' => $this->contado, 'fecEmi' => now()->toDateString(), 'tdicod' => '1', 'clinum' => '45678912', 'clinom' => 'ANA RUIZ',
            'telefono' => '987654321', 'fecha_nac' => '1990-05-20', 'consumo' => 1,
            'items' => [['id' => $lomo->IdProducto, 'descripcion' => 'LOMO SALTADO', 'cantidad' => 1, 'precio' => 28]],
        ])->assertOk()->json();
        $this->assertNotSame('error', $r['estado'] ?? null, $r['mensaje'] ?? '');

        $cab = DB::table('cpe_cabecera')->where('id_empresa_negocio', $this->sucursal)->latest('IdCpe_cabecera')->first();
        $this->assertSame(1, (int) $cab->consumo);
        $cliente = DB::table('cliente')->where('clinum', '45678912')->first();
        $this->assertSame(['987654321', '1990-05-20'], [$cliente->telefono, $cliente->fecha_nac]);
    }

    public function test_el_cumpleanos_se_guarda_con_dni_y_da_puntos_dobles_en_su_mes(): void
    {
        DB::table('empresa_negocios')->where('id_empresa_negocio', $this->sucursal)->update(['fid_activo' => 1, 'fid_cumple_dobles' => 1]);
        DB::table('fid_reglas')->insert(['id_empresa_negocio' => $this->sucursal, 'nombre' => 'GENERAL', 'soles_por_punto' => 1, 'compra_minima' => 0, 'activo' => 1]);
        $lomo = $this->producto('LOMO SALTADO', 28, 10);
        $cliente = ['tdicod' => '1', 'clinum' => '40000001', 'clinom' => 'LUIS SOTO'];

        // Una fecha imposible (un bebé de 1 año) no se guarda
        $this->cobrar($this->pedir([[$lomo, 1]]), 28, $cliente + ['fecha_nac' => now()->subYear()->toDateString()]);
        $this->assertNull(DB::table('cliente')->where('clinum', '40000001')->value('fecha_nac'));
        $this->assertSame(28, (int) DB::table('cliente')->where('clinum', '40000001')->value('puntos'), 'Sin cumpleaños: 1 punto por sol.');

        // Cumple este mes: la compra da el doble
        $nacio = Carbon::now()->subYears(30)->startOfMonth()->toDateString();
        $this->cobrar($this->pedir([[$lomo, 1]]), 28, $cliente + ['fecha_nac' => $nacio]);
        $this->assertSame($nacio, DB::table('cliente')->where('clinum', '40000001')->value('fecha_nac'));
        $this->assertSame(28 + 56, (int) DB::table('cliente')->where('clinum', '40000001')->value('puntos'));

        // Sin escribir la fecha otra vez, no se borra
        $this->cobrar($this->pedir([[$lomo, 1]]), 28, $cliente);
        $this->assertSame($nacio, DB::table('cliente')->where('clinum', '40000001')->value('fecha_nac'));
        $this->get(route('fidelizacion.index'))->assertOk()->assertSee('LUIS SOTO')->assertSee('Puntos dobles en su mes de cumpleaños');
    }
}
