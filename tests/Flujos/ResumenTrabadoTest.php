<?php

namespace Tests\Flujos;

use App\Support\VentaDirecta;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/** Resumen diario que SUNAT deja "en proceso" por días: se revisa boleta por boleta y se liberan las que SUNAT no tiene */
class ResumenTrabadoTest extends FlujoTestCase
{
    private int $correlativo = 0;

    private function resumenTrabado(array $boletas, string $enviado): int
    {
        $cor = ++$this->correlativo;
        $id = DB::table('resumenes')->insertGetId(['res_fec_com' => now()->toDateString(), 'res_fec_gen' => now()->toDateString(), 'res_tip' => 'RC',
            'tip_res_com' => '03', 'res_cor' => $cor, 'IdEmpresa' => $this->usuario->IdEmpresa, 'id_empresa_negocio' => $this->sucursal,
            'nom_arch' => $this->usuario->IdEmpresa.'-RC-20261110-00'.$cor, 'res_cant' => count($boletas), 'res_total' => 0, 'res_ticket' => null,
            'est_sunat' => 'EN PROCESO', 'res_est' => 'SUNAT aún está procesando el resumen.', 'IdUsuario' => $this->usuario->IdUsuario, 'fecha_hora' => $enviado]);
        DB::table('cpe_cabecera')->whereIn('IdCpe_cabecera', $boletas)->update(['res_id' => $id, 'est_sunat' => 'EN RESUMEN']);

        return $id;
    }

    public function test_las_que_sunat_tiene_quedan_aceptadas_y_las_demas_se_liberan(): void
    {
        $plato = $this->producto('JUANE', 15, 20);
        $tiene = VentaDirecta::registrar($this->usuario, $this->venta([[$plato, 1]], '03'), 'PV');
        $noTiene = VentaDirecta::registrar($this->usuario, $this->venta([[$plato, 2]], '03'), 'PV');
        $numTiene = (string) (int) DB::table('cpe_cabecera')->where('IdCpe_cabecera', $tiene)->value('numdoc');
        config(['services.apiperu.token' => 'token', 'services.apiperu.url' => 'https://apiperu.test/api']);
        Http::fake(['apiperu.test/*' => fn ($r) => Http::response(['success' => true, 'data' => [
            'comprobante_estado_descripcion' => $r['numero_documento'] === $numTiene ? 'ACEPTADO' : 'NO EXISTE', 'comprobante_estado_codigo' => '1']])]);

        $reciente = $this->resumenTrabado([], now()->subMinutes(30)->toDateTimeString());
        $this->post(route('sunat.resumen_liberar', $reciente))->assertSessionHasErrors('resumen');

        $res = $this->resumenTrabado([$tiene, $noTiene], now()->subDays(2)->toDateTimeString());
        $this->get(route('sunat.resumenes'))->assertOk()->assertSee('Revisar y liberar');
        $this->post(route('sunat.resumen_liberar', $res))->assertSessionHas('success');

        $this->assertSame(['ACEPTADO', $res], [DB::table('cpe_cabecera')->where('IdCpe_cabecera', $tiene)->value('est_sunat'),
            (int) DB::table('cpe_cabecera')->where('IdCpe_cabecera', $tiene)->value('res_id')]);
        $libre = DB::table('cpe_cabecera')->where('IdCpe_cabecera', $noTiene)->first();
        $this->assertSame(['PENDIENTE', null], [$libre->est_sunat, $libre->res_id], 'Queda para reenviar.');
        $this->assertSame('LIBERADO', DB::table('resumenes')->where('res_id', $res)->value('est_sunat'));
    }
}
