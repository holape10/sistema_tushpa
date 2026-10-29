<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo 25 de SUNAT (código de producto SUNAT, de los anexos de comprobantes electrónicos): 25.1 minería y combustibles,
 * 25.2 bienes sujetos a detracción y 25.3 a percepción; más códigos genéricos por rubro (restaurante, minimarket,
 * plásticos, panadería) que traía el sistema antiguo. Se busca al poner el código de existencia de un producto (Kardex SUNAT).
 */
return new class extends Migration
{
    /** @var array<int, array{0: string, 1: string, 2: string, 3: string}> [código, descripción, anexo, categoría] */
    private const CODIGOS = [
        ['11101616', 'Mineral de oro', '25.1', 'Oro'],
        ['11101801', 'Oro', '25.1', 'Oro'],
        ['12131500', 'Explosivos', '25.1', 'Explosivos'],
        ['12131501', 'Dinamita', '25.1', 'Explosivos'],
        ['12131502', 'Cartuchos explosivos', '25.1', 'Explosivos'],
        ['12131503', 'Explosivos propelentes', '25.1', 'Explosivos'],
        ['12131504', 'Cargas explosivas', '25.1', 'Explosivos'],
        ['12131505', 'Explosivos plásticos', '25.1', 'Explosivos'],
        ['12131506', 'Polvos de pólvora de nitro', '25.1', 'Explosivos'],
        ['12131507', 'Explosivos de nitrato de amonio', '25.1', 'Explosivos'],
        ['12131508', 'Explosivos de polvo de nitroglicerina', '25.1', 'Explosivos'],
        ['12131509', 'Nitrato de amonio y fuel oil (ANFO)', '25.1', 'Explosivos'],
        ['12141726', 'Mercurio Hg', '25.1', 'Insumos químicos'],
        ['12352117', 'Cianuros o isocianuros', '25.1', 'Insumos químicos'],
        ['15100000', 'Otros combustibles', '25.1', 'Combustible'],
        ['15101505', 'Combustible diésel', '25.1', 'Combustible'],
        ['15101506', 'Gasolina', '25.1', 'Combustible'],
        ['20101504', 'Cortadores de roca', '25.1', 'Maquinarias y equipos'],
        ['20101600', 'Cribas y equipos de alimentacion', '25.1', 'Maquinarias y equipos'],
        ['20111601', 'Maquinaria de sondeo o de perforación', '25.1', 'Maquinarias y equipos'],
        ['20111607', 'Maquinaria para hacer túneles', '25.1', 'Maquinarias y equipos'],
        ['22101501', 'Cargadores frontales', '25.1', 'Maquinarias y equipos'],
        ['22101502', 'Niveladoras', '25.1', 'Maquinarias y equipos'],
        ['22101505', 'Aplanadoras', '25.1', 'Maquinarias y equipos'],
        ['22101509', 'Retroexcavadoras', '25.1', 'Maquinarias y equipos'],
        ['22101511', 'Compactadores', '25.1', 'Maquinarias y equipos'],
        ['22101513', 'Dragalinas', '25.1', 'Maquinarias y equipos'],
        ['22101514', 'Dragas', '25.1', 'Maquinarias y equipos'],
        ['22101516', 'Excavadoras de fosos', '25.1', 'Maquinarias y equipos'],
        ['22101518', 'Raspadores elevadores', '25.1', 'Maquinarias y equipos'],
        ['22101519', 'Maquina giratoria con rastrillos abiertos', '25.1', 'Maquinarias y equipos'],
        ['22101521', 'Rastrilladora arrastrada', '25.1', 'Maquinarias y equipos'],
        ['22101522', 'Buldoceres de orugas', '25.1', 'Maquinarias y equipos'],
        ['22101523', 'Buldoceres de ruedas', '25.1', 'Maquinarias y equipos'],
        ['22101524', 'Excavadoras móviles', '25.1', 'Maquinarias y equipos'],
        ['22101525', 'Excavadoras de ruedas', '25.1', 'Maquinarias y equipos'],
        ['22101528', 'Excavadoras de orugas', '25.1', 'Maquinarias y equipos'],
        ['22101532', 'Cargadores de orugas', '25.1', 'Maquinarias y equipos'],
        ['22101534', 'Excavadoras de campaña', '25.1', 'Maquinarias y equipos'],
        ['22101602', 'Equipo de apisonamiento', '25.1', 'Maquinarias y equipos'],
        ['22101701', 'Palas excavadoras', '25.1', 'Maquinarias y equipos'],
        ['22101702', 'Palas mecánicas para el movimiento de tierra o sus piezas o accesorios', '25.1', 'Maquinarias y equipos'],
        ['22101713', 'Brazo de retroexcavadora / brazo del brazo', '25.1', 'Maquinarias y equipos'],
        ['22101714', 'Kits de reparación o repuesto de apisonadora', '25.1', 'Maquinarias y equipos'],
        ['25181709', 'Pala cargadora', '25.1', 'Maquinarias y equipos'],
        ['26111603', 'Generadores eólicos', '25.1', 'Maquinarias y equipos'],
        ['39121013', 'Convertidores rotativos eléctricos', '25.1', 'Maquinarias y equipos'],
        ['40151530', 'Bombas de dragado', '25.1', 'Maquinarias y equipos'],
        ['71101710', 'Servicio de alquiler o leasing de maquinaria para construcción', '25.1', 'Servicios'],
        ['72141701', 'Servicio de alquiler o leasing de equipo para minería', '25.1', 'Servicios'],
        ['73121613', 'Servicios de fundición de metales', '25.1', 'Servicios'],
        ['73121500', 'Procesos de fundición y formado de metales', '25.1', 'Servicios'],
        ['10171503', 'Harina, polvo y pellets de pescado, crustáceos, moluscos y demás invertebrados acuáticos', '25.2', 'Detracción'],
        ['11101600', 'Minerales metálicos no auríferos', '25.2', 'Detracción'],
        ['11101714', 'Plomo', '25.2', 'Detracción'],
        ['11111600', 'Piedra', '25.2', 'Detracción'],
        ['11111700', 'Arena', '25.2', 'Detracción'],
        ['11121600', 'Madera', '25.2', 'Detracción'],
        ['11140000', 'Chatarra y materiales de desecho', '25.2', 'Detracción'],
        ['50111500', 'Carnes y despojos comestibles', '25.2', 'Detracción'],
        ['50120000', 'Recursos hidrobiológicos', '25.2', 'Detracción'],
        ['50151600', 'Aceite de pescado', '25.2', 'Detracción'],
        ['50161509', 'Caña de Azúcar', '25.2', 'Detracción'],
        ['50171500', 'Páprika', '25.2', 'Detracción'],
        ['50203205', 'Lecha cruda entera', '25.2', 'Detracción'],
        ['50403200', 'Maíz amarillo', '25.2', 'Detracción'],
        ['11111111', 'Bienes gravados con el IGV por renuncia a la exoneración', '25.2', 'Detracción'],
        ['12142104', 'Dióxido de carbono', '25.3', 'Percepción'],
        ['13111039', 'Poli (tereftalato de etileno) sin adición de dióxido de titanio (PET)', '25.3', 'Percepción'],
        ['13102020', 'Envases o preformas de poli (tereftalato de etileno)', '25.3', 'Percepción'],
        ['15101502', 'Kerosene', '25.3', 'Percepción'],
        ['15101504', 'Combustible para aviación / Turbo combustible', '25.3', 'Percepción'],
        ['15101501', 'Gas licuado de petróleo (GLP)', '25.3', 'Percepción'],
        ['24122000', 'Envases de vidrio, botellas, frascos, ampollas y demás recipientes para transporte o envasado', '25.3', 'Percepción'],
        ['24122004', 'Tapones, tapas, cápsulas y demás dispositivos de cierre de metal común o plástico', '25.3', 'Percepción'],
        ['50202201', 'Cerveza de malta', '25.3', 'Percepción'],
        ['50202300', 'Aguas y bebidas no alcohólicas', '25.3', 'Percepción'],
        ['50221002', 'Harina de trigo o de morcajo (tranquillón)', '25.3', 'Percepción'],
        ['50221110', 'Trigo y morcajo (tranquillón)', '25.3', 'Percepción'],
        ['12352104', 'Alcoholes o sus sustitutos', '25.3', 'Percepción'],
        ['50151601', 'Aceite de pescado comestible o crudo', '25.3', 'Percepción'],
        ['50161500', 'Azúcares naturales y edulcorantes', '25.3', 'Percepción'],
        ['56221101', 'Grano de cereal / Cebada / Trigo de siembra', '25.3', 'Percepción'],
        ['15101511', 'Gas natural', '25.3', 'Combustible'],
        ['50191500', 'Sopas y guisos preparados', 'GENERICO', 'Restaurante'],
        ['50192100', 'Snacks y botanas', 'GENERICO', 'Minimarket'],
        ['50131700', 'Leche y mantequilla', 'GENERICO', 'Minimarket'],
        ['24121500', 'Cajas, bolsas y bolsitas de embalaje', 'GENERICO', 'Plásticos'],
        ['50192300', 'Postres y decoraciones de postres', 'GENERICO', 'Panadería'],
    ];

    /** Genéricos del sistema antiguo que no corresponden a su descripción en UNSPSC (segmento 23 es maquinaria, 501615 es azúcares) o no se pudieron confirmar */
    private const FALSOS = ['23101500', '23102100', '50161503', '50161504', '50202301', '50221100'];

    public function up(): void
    {
        if (! Schema::hasTable('sunat_catalogo_25')) {
            Schema::create('sunat_catalogo_25', function (Blueprint $t) {
                $t->increments('id');
                $t->string('codigo', 8)->unique();
                $t->string('descripcion', 255);
                $t->string('anexo', 10)->index();      // 25.1 | 25.2 | 25.3 | GENERICO
                $t->string('categoria', 100);
                $t->timestamps();
            });
        }
        DB::table('sunat_catalogo_25')->upsert(array_map(fn ($c) => ['codigo' => $c[0], 'descripcion' => $c[1], 'anexo' => $c[2],
            'categoria' => $c[3], 'created_at' => now(), 'updated_at' => now()], self::CODIGOS), ['codigo'], ['descripcion', 'anexo', 'categoria', 'updated_at']);
        DB::table('sunat_catalogo_25')->where('anexo', 'GENERICO')->whereIn('codigo', self::FALSOS)->delete();
    }

    public function down(): void
    {
        Schema::dropIfExists('sunat_catalogo_25');
    }
};
