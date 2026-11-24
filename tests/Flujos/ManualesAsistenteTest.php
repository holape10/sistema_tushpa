<?php

namespace Tests\Flujos;

use App\Models\Central\Cliente;
use App\Models\Central\Superadmin;
use App\Support\Asistente\Asistente;
use App\Support\Asistente\Herramientas;
use App\Support\Asistente\Manuales;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/** Manuales del asistente: la IA lee el PDF una vez, lo deja en secciones y ayuda_sistema responde con ellas (sin la API real) */
class ManualesAsistenteTest extends FlujoTestCase
{
    /** @var list<array<string, mixed>> */
    private array $llamadas = [];

    protected function setUp(): void
    {
        parent::setUp();
        DB::connection('central')->beginTransaction();
        Storage::fake('local');
        config(['services.anthropic.key' => 'sk-prueba', 'services.anthropic.tope_mensual_usd' => 20]);
    }

    protected function tearDown(): void
    {
        Asistente::$falso = null;
        DB::connection('central')->rollBack();
        parent::tearDown();
    }

    private function leer(string $titulo, string $respuesta, ?int $clienteId = null, string $parada = 'end_turn'): int
    {
        Asistente::$falso = function (array $p) use ($respuesta, $parada) {
            $this->llamadas[] = $p;

            return (object) ['stopReason' => $parada, 'content' => [(object) ['type' => 'text', 'text' => $respuesta]],
                'usage' => (object) ['inputTokens' => 30000, 'outputTokens' => 4000, 'cacheReadInputTokens' => 0, 'cacheCreationInputTokens' => 0]];
        };
        $id = Manuales::subir(UploadedFile::fake()->createWithContent('tutorial.pdf', '%PDF-1.4 prueba'), $titulo, 'TODOS', $clienteId, 'Dueño');
        Manuales::procesar($id);

        return $id;
    }

    public function test_la_ia_lee_el_pdf_una_vez_y_el_asistente_responde_con_sus_secciones(): void
    {
        $id = $this->leer('Vender en Punto Venta', "### Emitir una factura con RUC\nPalabras: factura ruc empresa comprobante sunat\n"
            ."1. En Punto Venta escribe el RUC del cliente.\n2. Cambia solo a Factura.\n3. Pulsa IMPRIMIR.\n\n"
            ."### Cobrar con Yape y efectivo\nPalabras: pago mixto yape plin efectivo medios\n1. Elige YAPE en Medio de pago.\n2. Pon el monto y toca + Agregar.\n\n"
            .'### Sin cuerpo');

        $manual = Manuales::db()->where('id', $id)->first();
        $this->assertSame(['listo', 2], [$manual->estado, (int) $manual->secciones], 'La sección sin pasos no se guarda.');
        $this->assertSame('document', $this->llamadas[0]['messages'][0]['content'][0]['type'], 'Se le manda el PDF a la IA.');
        $this->assertSame(1, DB::connection('central')->table('asistente_uso')->where('pregunta', 'Leer manual: Vender en Punto Venta')->count(), 'El gasto se anota.');

        $r = json_decode(Herramientas::ejecutar($this->usuario, 'ayuda_sistema', ['buscar' => '¿cómo emito facturas a una empresa?']), true);
        $this->assertSame('Emitir una factura con RUC', $r['manuales'][0]['titulo']);
        $this->assertSame('Vender en Punto Venta', $r['manuales'][0]['manual']);
        $this->assertStringContainsString('Pulsa IMPRIMIR', $r['manuales'][0]['texto']);
        $this->assertCount(1, $this->llamadas, 'Preguntar no vuelve a leer el PDF.');
    }

    public function test_el_mismo_pdf_no_se_sube_dos_veces_aunque_tenga_otro_nombre_o_titulo(): void
    {
        URL::defaults(['panelDominio' => strtolower((string) config('tenancy.dominio'))]);
        $this->actingAs(Superadmin::create(['nombre' => 'DUEÑO', 'email' => uniqid().'@prueba.test', 'password' => 'clave-de-prueba', 'activo' => true, 'es_dueno' => true]), 'superadmin');
        $this->leer('Vender en Punto Venta', "### Emitir una factura\nPalabras: factura\n1. Escribe el RUC del cliente y pulsa IMPRIMIR.");
        $antes = Manuales::db()->count();

        $this->post(route('admin.asistente.manuales.subir'), ['titulo' => 'Otro título', 'rubro' => 'TODOS',
            'pdf' => UploadedFile::fake()->createWithContent('copia.pdf', '%PDF-1.4 prueba')])
            ->assertSessionHasErrors(['pdf' => 'Este PDF ya está subido como «Vender en Punto Venta» ('.now()->format('d/m/Y').'). Si quieres que la IA lo lea otra vez, usa «Volver a leer» en ese manual.']);

        $this->assertSame($antes, Manuales::db()->count());
        $this->assertCount(1, $this->llamadas, 'No se vuelve a pagar por leerlo.');
    }

    public function test_un_manual_hecho_para_otra_empresa_no_le_aparece_a_las_demas(): void
    {
        $otra = Cliente::create(['razon_social' => 'OTRA EMPRESA SAC', 'ruc' => '20999999991', 'base_datos' => 'bd_20999999991']);
        $this->leer('Las 3 cajas', "### Configurar las tres cajas y sus comandas\nPalabras: cajas comandas impresoras barra\n1. Pon el nombre a cada PC.", $otra->id);

        $r = json_decode(Herramientas::ejecutar($this->usuario, 'ayuda_sistema', ['buscar' => 'configurar cajas comandas']), true);

        $this->assertEmpty($r['manuales'] ?? []);
    }

    public function test_si_el_pdf_es_muy_largo_guarda_las_secciones_completas_y_lo_avisa(): void
    {
        $id = $this->leer('Restaurante completo', "### Abrir turno de caja\nPalabras: turno caja abrir\n1. Entra a Caja › Abrir turno.\n\n### Cerrar tur", null, 'max_tokens');

        $manual = Manuales::db()->where('id', $id)->first();
        $this->assertSame(['listo', 1], [$manual->estado, (int) $manual->secciones], 'La sección cortada no se guarda.');
        $this->assertStringContainsString('muy largo', (string) $manual->error);
    }
}
