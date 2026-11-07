<?php

namespace Tests\Feature;

use App\Support\Vencimientos;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Cronograma SUNAT: leer la página oficial, calcular el año que aún no se publica y bajarlo solo cuando sale */
class VencimientosSunatTest extends TestCase
{
    private string $pagina2025;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Vencimientos::olvidar();
        $this->pagina2025 = (string) file_get_contents(base_path('tests/fixtures/sunat-cronograma-2025.html'));
    }

    protected function tearDown(): void
    {
        Vencimientos::olvidar();
        parent::tearDown();
    }

    public function test_cada_ruc_cae_en_su_grupo_del_cronograma(): void
    {
        $this->assertSame(['0', '1', '2-3', '2-3', '4-5', '8-9', 'BC'], [
            Vencimientos::grupo('20100070970'), Vencimientos::grupo('20553510661'), Vencimientos::grupo('20000000002'),
            Vencimientos::grupo('10000000003'), Vencimientos::grupo('20000000005'), Vencimientos::grupo('20000000009'),
            Vencimientos::grupo('20000000009', buenContribuyente: true),
        ]);
    }

    public function test_lee_la_pagina_de_la_sunat_incluido_diciembre_que_vence_el_otro_anio(): void
    {
        $periodos = Vencimientos::leerPagina($this->pagina2025, 2025);

        $this->assertCount(12, $periodos);
        $this->assertSame(['0' => '2025-02-17', '1' => '2025-02-18', '2-3' => '2025-02-19', '4-5' => '2025-02-20', '6-7' => '2025-02-21', '8-9' => '2025-02-24', 'BC' => '2025-02-25'], $periodos['202501']);
        $this->assertSame('2026-01-16', $periodos['202512']['0']);
    }

    public function test_la_regla_de_dias_habiles_da_las_mismas_fechas_que_publico_la_sunat(): void
    {
        // Con Semana Santa (abril), la Fuerza Aérea (23 de julio) y los feriados de diciembre de por medio
        $this->assertSame(Vencimientos::leerPagina($this->pagina2025, 2025), Vencimientos::calcular(2025));
    }

    public function test_si_la_sunat_cambia_su_pagina_no_se_guardan_fechas_dudosas(): void
    {
        Http::fake(['*' => Http::response(str_replace('<td>Mar-2025</td>', '<td>Mar-2025</td><td>pronto</td>', $this->pagina2025))]);

        $this->assertSame(1, Artisan::call('sunat:cronograma', ['anio' => 2025]));
        $this->assertFalse(Vencimientos::tieneOficial(2025));
    }

    public function test_el_ano_que_aun_no_se_publica_se_calcula_y_se_cambia_por_el_oficial_cuando_sale(): void
    {
        // Noviembre 2024: desde octubre también se busca el cronograma del año que viene, que aún no sale
        $this->travelTo('2024-11-20 06:20');
        $publicado = false;
        Http::fake(function ($r) use (&$publicado) {
            return $publicado && str_ends_with($r->url(), 'cronogramas/2025/cObligacionMensual2025.html')
                ? Http::response($this->pagina2025) : Http::response('no existe', 404);
        });

        $this->assertSame(0, Artisan::call('sunat:cronograma'));
        $this->assertFalse(Vencimientos::oficial(2025), 'Sin publicar: las fechas son calculadas.');
        $this->assertSame('2025-02-17', Vencimientos::vence('202501', '0')->toDateString());

        // La SUNAT lo publica: en la siguiente vuelta se baja solo y ya no se vuelve a pedir
        $publicado = true;
        Artisan::call('sunat:cronograma');
        $this->assertTrue(Vencimientos::oficial(2025));
        $pedidos2025 = fn () => Http::recorded(fn ($r) => str_contains($r->url(), 'cronogramas/2025/'))->count();
        $antes = $pedidos2025();
        Artisan::call('sunat:cronograma');
        $this->assertSame($antes, $pedidos2025());
    }
}
