<?php

namespace Tests\Flujos;

use App\Support\Contabilidad\CuentasCompras;
use App\Support\Excel;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/** Importar el plan contable desde el Excel del sistema contable en FoxPro (Pckey, Nomcta, Tipcta, Cargo, Abono) */
class PlanImportarTest extends FlujoTestCase
{
    private function excel(array $filas): UploadedFile
    {
        $ruta = (new Excel)->hoja('Plan', [['WILCAT SYSTEMS EIRL'], ['Pckey', 'Nomcta', 'Tipcta', 'Orden1', 'Cargo', 'Abono']], $filas)->guardar();

        return new UploadedFile($ruta, 'Plan de cuenta.xlsx', null, null, true);
    }

    public function test_importa_el_plan_de_foxpro_con_sus_niveles_amarres_y_tildes(): void
    {
        $ruc = (string) $this->usuario->IdEmpresa;
        // Una cuenta que ya estaba: se actualiza, no se duplica
        DB::table('conta_plan')->updateOrInsert(['IdEmpresa' => $ruc, 'cuenta' => '10101'], ['descripcion' => 'CAJA VIEJA', 'nivel' => 5, 'tipo' => 'ACTIVO',
            'naturaleza' => 'D', 'imputable' => 1, 'estado' => 'Activo', 'created_at' => now(), 'updated_at' => now()]);

        $this->post(route('contabilidad.plan.importar'), ['archivo' => $this->excel([
            ['10', 'EFECTIVO Y EQUIVALENTES DE EFECTIVO', '1', '', '', ''],
            ['101', 'CAJA', '2', '', '', ''],
            ['10101', 'CAJA OP MONEDA NACIONAL', '3', '1110', '', ''],
            ['1042', 'CUENTAS CORRIENTES PARA FINES ESPECÖFICOS', '3', '1110', '', ''],
            ['60', 'COMPRAS', '1', '', '', ''],
            ['60111', 'MERCADERÖAS MANUFACTURADAS', '3', '', '20111', '61111'],
            ['16211', 'Dep¢sitos en garant¡a', '3', '', '', ''],
            ['46', 'COMPA¥ÖAS ASEGURADORAS - INVERSIàN', '1', '', '', ''],
            ['X1', 'NO ES CUENTA', '3', '', '', ''],
        ])])->assertRedirect(route('contabilidad.plan'))->assertSessionHas('success', fn ($m) => str_contains($m, '8 cuentas leídas') && str_contains($m, '1 actualizadas') && str_contains($m, 'No se leyeron 1'));

        $plan = DB::table('conta_plan')->where('IdEmpresa', $ruc)->get()->keyBy('cuenta');
        $this->assertSame(['MERCADERÍAS MANUFACTURADAS', 3, '20111', '61111', 'GASTO', 'D'],
            [$plan['60111']->descripcion, (int) $plan['60111']->tipcta, $plan['60111']->amarre_cargo, $plan['60111']->amarre_abono, $plan['60111']->tipo, $plan['60111']->naturaleza]);
        $this->assertSame('CUENTAS CORRIENTES PARA FINES ESPECÍFICOS', $plan['1042']->descripcion);
        $this->assertSame('DEPÓSITOS EN GARANTÍA', $plan['16211']->descripcion);
        $this->assertSame('COMPAÑÍAS ASEGURADORAS - INVERSIÓN', $plan['46']->descripcion);
        $this->assertSame(['CAJA OP MONEDA NACIONAL', 1], [$plan['10101']->descripcion, (int) DB::table('conta_plan')->where('IdEmpresa', $ruc)->where('cuenta', '10101')->count()]);
        // De título las que tienen subcuentas; de movimiento las demás
        $this->assertSame([0, 0, 1], [(int) $plan['10']->imputable, (int) $plan['101']->imputable, (int) $plan['10101']->imputable]);
        $this->assertArrayNotHasKey('X1', $plan->all());

        // La ayuda de cuentas (Compras con detalle) muestra solo las de nivel 3
        $ayuda = CuentasCompras::plan($ruc);
        $this->assertArrayHasKey('60111', $ayuda);
        $this->assertArrayHasKey('1042', $ayuda);
        $this->assertArrayNotHasKey('60', $ayuda);
        $this->assertArrayNotHasKey('101', $ayuda);
        $this->assertArrayNotHasKey('6011', $ayuda, 'Sin el PCGE: solo el plan importado.');
    }

    public function test_sin_plan_importado_la_ayuda_muestra_solo_las_cuentas_de_movimiento(): void
    {
        $ayuda = CuentasCompras::plan((string) $this->usuario->IdEmpresa);

        $this->assertNotEmpty($ayuda);
        foreach (array_keys($ayuda) as $cuenta) {
            $cuenta = (string) $cuenta;
            $this->assertGreaterThan(2, strlen($cuenta));
            $this->assertEmpty(array_filter(array_keys($ayuda), fn ($otra) => (string) $otra !== $cuenta && str_starts_with((string) $otra, $cuenta)), "{$cuenta} tiene subcuentas");
        }
    }

    public function test_un_excel_sin_cuentas_no_importa_nada(): void
    {
        $ruta = (new Excel)->hoja('Hoja', [['Nombre', 'Precio']], [['ARROZ', '5']])->guardar();

        $this->post(route('contabilidad.plan.importar'), ['archivo' => new UploadedFile($ruta, 'otra.xlsx', null, null, true)])
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'No encontré cuentas'));
    }
}
