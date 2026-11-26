<?php

namespace Tests\Flujos;

use App\Models\Central\Cliente;
use App\Models\Central\Superadmin;
use App\Support\Sunat\ConsultaTickets;
use Carbon\CarbonInterval;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

/** Los resúmenes EN PROCESO se consultan solos y el panel muestra los resúmenes enviados de cada empresa */
class ResumenesTicketsTest extends FlujoTestCase
{
    private function resumen(string $estado, string $hace, ?string $ticket = '12345'): int
    {
        return DB::table('resumenes')->insertGetId(['res_fec_com' => now()->toDateString(), 'res_fec_gen' => now()->toDateString(), 'res_ticket' => $ticket,
            'res_est' => 'SUNAT aún está procesando el resumen.', 'id_empresa_negocio' => $this->sucursal, 'res_tip' => 'RC', 'nom_arch' => 'RC-'.uniqid(),
            'est_sunat' => $estado, 'IdEmpresa' => $this->usuario->IdEmpresa, 'res_cor' => random_int(1, 99999), 'res_cant' => 3, 'res_total' => 45,
            'fecha_hora' => now()->sub(CarbonInterval::fromString($hace))]);
    }

    public function test_solo_se_consultan_los_en_proceso_de_hace_mas_de_una_hora(): void
    {
        $enProceso = $this->resumen('EN PROCESO', '2 hours');
        $enviado = $this->resumen('ENVIADO', '90 minutes');
        $this->resumen('EN PROCESO', '20 minutes');           // muy reciente: SUNAT aún no responde
        $this->resumen('ACEPTADO', '3 hours');                // ya tiene respuesta
        $this->resumen('EN PROCESO', '20 days');              // muy viejo: se revisa a mano
        $this->resumen('ENVIADO', '3 hours', null);           // sin ticket no hay qué consultar

        $this->assertSame([$enProceso, $enviado], ConsultaTickets::pendientes()->pluck('res_id')->map(fn ($id) => (int) $id)->sort()->values()->all());
    }

    public function test_la_empresa_ve_que_se_consulta_solo(): void
    {
        $this->resumen('EN PROCESO', '2 hours');

        $this->get(route('sunat.resumenes'))->assertOk()->assertSeeText('El sistema lo consulta solo cada hora');
    }

    public function test_el_panel_tiene_la_pestana_de_resumenes_enviados(): void
    {
        DB::connection('central')->beginTransaction();
        try {
            URL::defaults(['panelDominio' => strtolower((string) config('tenancy.dominio'))]);
            $cliente = Cliente::create(['ruc' => (string) $this->usuario->IdEmpresa, 'base_datos' => DB::connection()->getDatabaseName(),
                'razon_social' => 'EMPRESA DE PRUEBA', 'estado' => 'ACTIVO']);
            $this->actingAs(Superadmin::create(['nombre' => 'DUEÑO', 'email' => uniqid().'@prueba.test', 'password' => 'clave-de-prueba', 'activo' => true, 'es_dueno' => true]), 'superadmin');

            $this->get(route('admin.clientes.comprobantes', [$cliente->id, 'filtro' => 'resumenes']))->assertOk()
                ->assertSeeText('Resúmenes enviados')->assertSeeText('se consultan solos en SUNAT cada hora');
        } finally {
            DB::connection('central')->rollBack();
        }
    }
}
