<?php

namespace Tests\Flujos;

use App\Support\Antiguo\CargadorSql;
use App\Support\Colas;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/** Paso 1 del importador: el respaldo (también en .zip) se sube y se carga en segundo plano; luego sigue al paso 2 */
class CargarRespaldoTest extends FlujoTestCase
{
    private string $bd;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bd = 'antiguo_'.$this->usuario->IdEmpresa.'_'.now()->format('Ymd_His');
        Colas::$fingir = false;
    }

    protected function tearDown(): void
    {
        CargadorSql::admin()->statement('DROP DATABASE IF EXISTS `'.$this->bd.'`');
        Colas::$fingir = null;
        parent::tearDown();
    }

    private function zipCon(string $sql): UploadedFile
    {
        $ruta = tempnam(sys_get_temp_dir(), 'rz').'.zip';
        $z = new \ZipArchive;
        $z->open($ruta, \ZipArchive::CREATE);
        $z->addFromString('WILCAT_TIENDA.sql', $sql);
        $z->close();

        return new UploadedFile($ruta, 'WILCAT_TIENDA.zip', 'application/zip', null, true);
    }

    public function test_el_zip_se_carga_en_segundo_plano_y_sigue_al_paso_dos(): void
    {
        $avance = $this->post(route('importar.cargar'), ['archivo' => $this->zipCon("CREATE TABLE productos (IdProducto INT, pronom VARCHAR(50), pro_rel INT NULL);\nINSERT INTO productos VALUES (1,'ARROZ',NULL);\n")])
            ->assertRedirect();
        $proceso = DB::table('procesos')->where('tipo', 'importar_carga')->latest('id')->first();
        $this->assertSame('LISTO', $proceso->estado, (string) $proceso->mensaje);
        $this->get($avance->headers->get('Location'))->assertOk()->assertSee(route('importar.cargado', $proceso->id), false);

        $this->get(route('importar.cargado', $proceso->id))->assertRedirect(route('importar.revisar', ['bd' => $this->bd]));
        $this->assertSame(1, DB::connection('antiguo_admin')->table($this->bd.'.productos')->count());
    }

    public function test_un_respaldo_sin_productos_avisa_y_no_deja_la_base(): void
    {
        $this->post(route('importar.cargar'), ['archivo' => $this->zipCon("CREATE TABLE cliente (clicod INT);\n")])->assertRedirect();

        $proceso = DB::table('procesos')->where('tipo', 'importar_carga')->latest('id')->first();
        $this->assertSame('ERROR', $proceso->estado);
        $this->assertStringContainsString('no trae la tabla productos', (string) $proceso->mensaje);
        $this->assertNull(DB::connection('antiguo_admin')->selectOne('SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?', [$this->bd]));
    }
}
