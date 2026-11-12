<?php

namespace Tests\Flujos;

use App\Support\Reportes;
use App\Support\VentaDirecta;
use Illuminate\Support\Facades\DB;

/** Todos los reportes salen en PDF, también con el logo de la empresa en la cabecera */
class ReportesPdfTest extends FlujoTestCase
{
    public function test_cada_reporte_sale_en_pdf_con_el_logo(): void
    {
        DB::table('empresa')->where('IdEmpresa', $this->usuario->IdEmpresa)->update(['LogEmpresa' => 'imagenes/96.png']);
        VentaDirecta::registrar($this->usuario, $this->venta([[$this->producto('JUANE', 15, 10), 2]], '03'), 'PV');

        foreach (array_keys(Reportes::CATALOGO) as $clave) {
            $this->get(route('reportes.ver', [$clave, 'formato' => 'pdf']))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        }
    }
}
