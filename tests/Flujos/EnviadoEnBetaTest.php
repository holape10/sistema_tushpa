<?php

namespace Tests\Flujos;

use App\Support\Sunat\EnviadoEnBeta;
use App\Support\VentaDirecta;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Comprobantes enviados con la empresa en BETA (pruebas): el sistema los tiene aceptados pero SUNAT no. Se consultan en
 * SUNAT (también desde Ventas) y, solo si SUNAT no los tiene, vuelven a pendiente para enviarlos en producción.
 */
class EnviadoEnBetaTest extends FlujoTestCase
{
    /** SUNAT tiene los de $tiene (serie-número); los demás, NO EXISTE */
    private function sunat(array $tiene): void
    {
        config(['services.apiperu.token' => 'token', 'services.apiperu.url' => 'https://apiperu.test/api']);
        Http::fake(['apiperu.test/*' => fn ($r) => Http::response(['success' => true, 'data' => [
            'comprobante_estado_descripcion' => in_array($r['serie_documento'].'-'.$r['numero_documento'], $tiene, true) ? 'ACEPTADO' : 'NO EXISTE', 'comprobante_estado_codigo' => '1']])]);
    }

    private function aceptada(string $tdocod): object
    {
        $id = VentaDirecta::registrar($this->usuario, $this->venta([[$this->producto('JUANE '.uniqid(), 15, 10), 1]], $tdocod), 'PV');
        DB::table('cpe_cabecera')->where('IdCpe_cabecera', $id)->update(['est_sunat' => 'ACEPTADO', 'enviado' => 1, 'ccasunrescod' => '0', 'res_id' => $tdocod === '03' ? 77 : null]);

        return DB::table('cpe_cabecera')->where('IdCpe_cabecera', $id)->first();
    }

    public function test_la_que_sunat_no_tiene_vuelve_a_pendiente_y_la_que_tiene_no_se_toca(): void
    {
        $beta = $this->aceptada('01');
        $real = $this->aceptada('03');
        $this->sunat([$real->serdoc.'-'.(int) $real->numdoc]);

        EnviadoEnBeta::devolverAPendiente($beta, 'SOPORTE');
        $c = DB::table('cpe_cabecera')->where('IdCpe_cabecera', $beta->IdCpe_cabecera)->first();
        $this->assertSame(['PENDIENTE', 0, null], [$c->est_sunat, (int) $c->enviado, $c->ccasunrescod]);
        $this->assertStringContainsString('Se había enviado en BETA', $c->ccadessun);

        try {
            EnviadoEnBeta::devolverAPendiente($real, 'SOPORTE');
            $this->fail('Tocó uno que SUNAT sí tiene.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('sí lo tiene', $e->getMessage());
        }
        $this->assertSame(['ACEPTADO', 77], [DB::table('cpe_cabecera')->where('IdCpe_cabecera', $real->IdCpe_cabecera)->value('est_sunat'),
            (int) DB::table('cpe_cabecera')->where('IdCpe_cabecera', $real->IdCpe_cabecera)->value('res_id')]);

        // Ya pendiente: no hay nada que devolver
        $this->expectExceptionMessage('ya está pendiente');
        EnviadoEnBeta::devolverAPendiente(DB::table('cpe_cabecera')->where('IdCpe_cabecera', $beta->IdCpe_cabecera)->first(), 'SOPORTE');
    }

    public function test_desde_ventas_se_consulta_en_sunat_y_avisa_si_parece_enviado_en_beta(): void
    {
        $beta = $this->aceptada('01');
        $this->sunat([]);

        $this->get(route('ventas.index'))->assertOk()->assertSee('title="Consultar en SUNAT"', false);
        $this->postJson(route('ventas.consultar_sunat', $beta->IdCpe_cabecera))->assertOk()
            ->assertJson(['ok' => true, 'estado' => 'NO EXISTE', 'color' => 'rose', 'numero' => $beta->serdoc.'-'.$beta->numdoc])
            ->assertJsonFragment(['mensaje' => 'SUNAT NO lo tiene, aunque aquí figura ACEPTADO: es probable que se haya enviado en BETA (pruebas). Pide a soporte que lo regrese a pendiente para enviarlo en producción.']);
    }
}
