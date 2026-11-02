<?php

namespace Tests\Unit;

use App\Models\Central\Cliente;
use App\Support\Tenancy\Contratos;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Contrato de servicio: la empresa sube su DNI, firma (en pantalla o a mano) y el panel lo aprueba u observa */
class ContratosTest extends TestCase
{
    private Cliente $cliente;

    /** Firma de mentira: un PNG pequeño de verdad */
    private function firmaPng(): string
    {
        $img = imagecreatetruecolor(60, 20);
        imageline($img, 2, 10, 58, 12, imagecolorallocate($img, 255, 255, 255));
        ob_start();
        imagepng($img);

        return (string) ob_get_clean();
    }

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        // Base central de mentira en memoria: la prueba no toca la base central de verdad
        config(['database.connections.central' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''], 'tenancy.dominio' => 'tushpa.app']);
        DB::purge('central');
        $central = Schema::connection('central');
        $central->create('planes', function (Blueprint $t) {
            $t->id();
            $t->string('nombre');
            $t->decimal('precio', 10, 2);
            $t->decimal('precio_anual', 10, 2)->nullable();
            $t->timestamps();
        });
        $central->create('clientes', function (Blueprint $t) {
            $t->id();
            $t->string('ruc');
            $t->string('razon_social');
            $t->string('nombre_comercial')->nullable();
            $t->string('direccion')->nullable();
            $t->string('subdominio')->nullable();
            $t->string('plan')->nullable();
            $t->foreignId('plan_id')->nullable();
            $t->string('contacto_nombre')->nullable();
            $t->string('contacto_telefono')->nullable();
            $t->string('contacto_correo')->nullable();
            $t->timestamps();
        });
        (include database_path('migrations/central/2027_01_12_000001_contratos.php'))->up();
        $plan = DB::connection('central')->table('planes')->insertGetId(['nombre' => 'RESTAURANTE', 'precio' => 100]);
        $this->cliente = Cliente::create(['ruc' => '20609838427', 'razon_social' => 'COCOROCO E.I.R.L.', 'plan_id' => $plan, 'contacto_nombre' => 'JUAN PEREZ']);
    }

    private function completar(object $contrato, ?string $firma, ?UploadedFile $escaneado = null): object
    {
        return Contratos::completar($contrato, ['firmante' => 'Juan Pérez', 'firmante_dni' => '12345678', 'firmante_cargo' => 'gerente', 'correo' => null, 'telefono' => null],
            UploadedFile::fake()->image('frente.jpg'), UploadedFile::fake()->image('reverso.jpg'), $firma, $escaneado, '190.12.34.56', 'Pruebas');
    }

    public function test_el_monto_sale_del_plan_mensual_o_anual(): void
    {
        $this->assertSame(100.0, Contratos::montoSugerido($this->cliente, 'MENSUAL'));
        $this->assertSame(1000.0, Contratos::montoSugerido($this->cliente, 'ANUAL'), 'El año cobra 10 meses.');
    }

    public function test_firmar_en_pantalla_deja_el_contrato_por_revisar_con_su_constancia(): void
    {
        $contrato = Contratos::generar($this->cliente, 'ANUAL', 1000, 1);
        $this->assertTrue(Contratos::editable($contrato));

        $firmado = $this->completar($contrato, $this->firmaPng());

        $this->assertSame('ENVIADO', $firmado->estado);
        $this->assertSame('JUAN PÉREZ', $firmado->firmante);
        $this->assertSame('190.12.34.56', $firmado->firmado_ip);
        $this->assertSame(64, strlen((string) $firmado->huella));
        Storage::disk('local')->assertExists([$firmado->dni_frente, $firmado->dni_reverso, $firmado->firma]);
        $this->assertFalse(Contratos::editable($firmado), 'Mientras se revisa ya no se puede cambiar.');

        $pdf = Contratos::pdf($firmado);
        $this->assertStringStartsWith('%PDF', $pdf);
    }

    public function test_sin_dni_o_sin_firma_no_se_puede_enviar(): void
    {
        $contrato = Contratos::generar($this->cliente, 'MENSUAL', 100, 1);

        $this->expectException(\RuntimeException::class);
        $this->completar($contrato, null);
    }

    public function test_se_puede_subir_el_contrato_firmado_a_mano(): void
    {
        $contrato = Contratos::generar($this->cliente, 'MENSUAL', 100, 1);

        $firmado = $this->completar($contrato, null, UploadedFile::fake()->create('contrato.pdf', 300, 'application/pdf'));

        $this->assertSame('ENVIADO', $firmado->estado);
        Storage::disk('local')->assertExists($firmado->escaneado);
    }

    public function test_observado_vuelve_a_la_empresa_y_un_contrato_nuevo_anula_el_anterior(): void
    {
        $contrato = $this->completar(Contratos::generar($this->cliente, 'MENSUAL', 100, 1), $this->firmaPng());

        Contratos::revisar($contrato, false, 'La foto del DNI no se lee', 1);
        $observado = Contratos::porToken($contrato->token);
        $this->assertSame('OBSERVADO', $observado->estado);
        $this->assertTrue(Contratos::editable($observado), 'La empresa corrige con el mismo enlace.');

        $nuevo = Contratos::generar($this->cliente, 'ANUAL', 900, 1);
        $this->assertNull(Contratos::porToken($contrato->token), 'El enlace del contrato anterior ya no sirve.');
        $this->assertSame($nuevo->id, Contratos::deCliente($this->cliente->id)->id);
    }
}
