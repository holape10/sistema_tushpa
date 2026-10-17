<?php

namespace App\Support;

/**
 * CIE-10 (Clasificación Internacional de Enfermedades, OPS/OMS) para el diagnóstico de la atención clínica.
 * Va completo el capítulo de la cavidad bucal (K00–K14) y los diagnósticos generales más frecuentes en consulta
 * (incluye dengue y malaria, comunes en la Amazonía). Se puede escribir cualquier otro código a mano.
 * Tipo de diagnóstico (norma MINSA): P = presuntivo, D = definitivo, R = repetido.
 */
class Cie10
{
    public const TIPOS = ['P' => 'Presuntivo', 'D' => 'Definitivo', 'R' => 'Repetido'];

    public const ODONTOLOGIA = [
        'K00.0' => 'Anodoncia', 'K00.1' => 'Dientes supernumerarios', 'K00.2' => 'Anomalías del tamaño y de la forma del diente',
        'K00.3' => 'Dientes moteados', 'K00.4' => 'Alteraciones en la formación dentaria', 'K00.5' => 'Alteraciones hereditarias de la estructura dentaria',
        'K00.6' => 'Alteraciones en la erupción dentaria', 'K00.7' => 'Síndrome de la erupción dentaria (dentición)', 'K00.8' => 'Otros trastornos del desarrollo de los dientes',
        'K00.9' => 'Trastorno del desarrollo de los dientes, no especificado',
        'K01.0' => 'Dientes incluidos', 'K01.1' => 'Dientes impactados',
        'K02.0' => 'Caries limitada al esmalte', 'K02.1' => 'Caries de la dentina', 'K02.2' => 'Caries del cemento', 'K02.3' => 'Caries dentaria detenida',
        'K02.4' => 'Odontoclasia', 'K02.5' => 'Caries con exposición pulpar', 'K02.8' => 'Otras caries dentales', 'K02.9' => 'Caries dental, no especificada',
        'K03.0' => 'Atrición excesiva de los dientes', 'K03.1' => 'Abrasión de los dientes', 'K03.2' => 'Erosión de los dientes',
        'K03.3' => 'Reabsorción patológica de los dientes', 'K03.4' => 'Hipercementosis', 'K03.5' => 'Anquilosis dental',
        'K03.6' => 'Depósitos (acreciones) en los dientes: placa, cálculo, pigmentación', 'K03.7' => 'Cambios posteruptivos del color de los tejidos dentales duros',
        'K03.8' => 'Otras enfermedades especificadas de los tejidos duros de los dientes', 'K03.9' => 'Enfermedad de los tejidos duros de los dientes, no especificada',
        'K04.0' => 'Pulpitis', 'K04.1' => 'Necrosis de la pulpa', 'K04.2' => 'Degeneración de la pulpa', 'K04.3' => 'Formación anormal de tejido duro en la pulpa',
        'K04.4' => 'Periodontitis apical aguda originada en la pulpa', 'K04.5' => 'Periodontitis apical crónica (granuloma apical)',
        'K04.6' => 'Absceso periapical con fístula', 'K04.7' => 'Absceso periapical sin fístula', 'K04.8' => 'Quiste radicular',
        'K04.9' => 'Otras enfermedades de la pulpa y del tejido periapical',
        'K05.0' => 'Gingivitis aguda', 'K05.1' => 'Gingivitis crónica', 'K05.2' => 'Periodontitis aguda', 'K05.3' => 'Periodontitis crónica',
        'K05.4' => 'Periodontosis', 'K05.5' => 'Otras enfermedades periodontales', 'K05.6' => 'Enfermedad del periodonto, no especificada',
        'K06.0' => 'Retracción gingival', 'K06.1' => 'Hiperplasia gingival', 'K06.2' => 'Lesiones de la encía y del reborde alveolar por traumatismo',
        'K06.8' => 'Otros trastornos especificados de la encía y del reborde alveolar', 'K06.9' => 'Trastorno de la encía y del reborde alveolar, no especificado',
        'K07.0' => 'Anomalías importantes del tamaño de los maxilares', 'K07.1' => 'Anomalías de la relación maxilobasilar',
        'K07.2' => 'Anomalías de la relación entre los arcos dentarios', 'K07.3' => 'Anomalías de la posición del diente (apiñamiento, giroversión, diastema)',
        'K07.4' => 'Maloclusión de tipo no especificado', 'K07.5' => 'Anomalías dentofaciales funcionales', 'K07.6' => 'Trastornos de la articulación temporomaxilar',
        'K07.8' => 'Otras anomalías dentofaciales', 'K07.9' => 'Anomalía dentofacial, no especificada',
        'K08.0' => 'Exfoliación de los dientes por causas sistémicas', 'K08.1' => 'Pérdida de dientes por accidente, extracción o enfermedad periodontal',
        'K08.2' => 'Atrofia del reborde alveolar desdentado', 'K08.3' => 'Raíz dental retenida', 'K08.8' => 'Otras afecciones de los dientes y sus estructuras de sostén',
        'K08.9' => 'Trastorno de los dientes y sus estructuras de sostén, no especificado',
        'K09.0' => 'Quistes originados por el desarrollo de los dientes', 'K09.1' => 'Quistes de las fisuras (no odontogénicos) de la región bucal',
        'K09.2' => 'Otros quistes de los maxilares', 'K09.8' => 'Otros quistes de la región bucal', 'K09.9' => 'Quiste de la región bucal, sin otra especificación',
        'K10.0' => 'Trastornos del desarrollo de los maxilares', 'K10.1' => 'Granuloma central de células gigantes', 'K10.2' => 'Afecciones inflamatorias de los maxilares',
        'K10.3' => 'Alveolitis del maxilar', 'K10.8' => 'Otras enfermedades especificadas de los maxilares', 'K10.9' => 'Enfermedad de los maxilares, no especificada',
        'K11.2' => 'Sialoadenitis', 'K11.5' => 'Sialolitiasis', 'K11.7' => 'Alteraciones de la secreción salival', 'K11.9' => 'Enfermedad de glándula salival, no especificada',
        'K12.0' => 'Estomatitis aftosa recurrente', 'K12.1' => 'Otras formas de estomatitis', 'K12.2' => 'Celulitis y absceso de boca',
        'K13.0' => 'Enfermedades de los labios', 'K13.2' => 'Leucoplasia y otras alteraciones del epitelio bucal', 'K13.7' => 'Otras lesiones de la mucosa bucal',
        'K14.0' => 'Glositis', 'K14.1' => 'Lengua geográfica', 'K14.6' => 'Glosodinia',
        'B37.0' => 'Estomatitis candidiásica (candidiasis bucal)', 'B00.2' => 'Gingivoestomatitis herpética',
        'S02.5' => 'Fractura de los dientes', 'S03.2' => 'Luxación de diente', 'S00.5' => 'Traumatismo superficial del labio y de la cavidad bucal',
        'C00.9' => 'Tumor maligno del labio, parte no especificada', 'C06.9' => 'Tumor maligno de la boca, parte no especificada',
        'D10.2' => 'Tumor benigno del piso de la boca', 'D10.3' => 'Tumor benigno de otras partes de la boca',
        'Z01.2' => 'Examen odontológico', 'Z46.3' => 'Prueba y ajuste de prótesis dental', 'Z97.2' => 'Presencia de prótesis dental',
    ];

    public const GENERAL = [
        'Z00.0' => 'Examen médico general', 'Z00.1' => 'Control de salud de rutina del niño', 'Z34.9' => 'Supervisión de embarazo normal',
        'J00' => 'Rinofaringitis aguda (resfriado común)', 'J02.9' => 'Faringitis aguda', 'J03.9' => 'Amigdalitis aguda', 'J06.9' => 'Infección aguda de las vías respiratorias superiores',
        'J20.9' => 'Bronquitis aguda', 'J18.9' => 'Neumonía, no especificada', 'J45.9' => 'Asma', 'J30.4' => 'Rinitis alérgica',
        'A09' => 'Diarrea y gastroenteritis de presunto origen infeccioso', 'K29.7' => 'Gastritis, no especificada', 'K30' => 'Dispepsia',
        'K59.0' => 'Estreñimiento', 'B82.9' => 'Parasitosis intestinal, sin otra especificación', 'N39.0' => 'Infección de vías urinarias',
        'I10' => 'Hipertensión esencial (primaria)', 'E11.9' => 'Diabetes mellitus tipo 2 sin complicaciones', 'E66.9' => 'Obesidad', 'E78.5' => 'Hiperlipidemia',
        'D50.9' => 'Anemia por deficiencia de hierro', 'E44.1' => 'Desnutrición proteicocalórica leve',
        'R50.9' => 'Fiebre, no especificada', 'R51' => 'Cefalea', 'R10.4' => 'Dolor abdominal', 'M54.5' => 'Lumbago', 'M79.1' => 'Mialgia',
        'L30.9' => 'Dermatitis', 'L50.9' => 'Urticaria', 'B35.9' => 'Dermatofitosis (micosis de la piel)', 'H10.9' => 'Conjuntivitis', 'H66.9' => 'Otitis media',
        'A97.0' => 'Dengue sin signos de alarma', 'A97.1' => 'Dengue con signos de alarma', 'A97.2' => 'Dengue grave',
        'B50.9' => 'Paludismo (malaria) por Plasmodium falciparum', 'B51.9' => 'Paludismo (malaria) por Plasmodium vivax', 'A27.9' => 'Leptospirosis',
        'F41.9' => 'Trastorno de ansiedad', 'F32.9' => 'Episodio depresivo', 'G43.9' => 'Migraña',
    ];

    /** @return array<int, array{c: string, d: string, g: string}> Para el buscador */
    public static function lista(bool $odontologiaPrimero = false): array
    {
        $odo = array_map(fn ($c, $d) => ['c' => $c, 'd' => $d, 'g' => 'Odontología'], array_keys(self::ODONTOLOGIA), self::ODONTOLOGIA);
        $gen = array_map(fn ($c, $d) => ['c' => $c, 'd' => $d, 'g' => 'General'], array_keys(self::GENERAL), self::GENERAL);

        return $odontologiaPrimero ? array_merge($odo, $gen) : array_merge($gen, $odo);
    }

    public static function descripcion(string $codigo): ?string
    {
        $c = strtoupper(trim($codigo));

        return self::ODONTOLOGIA[$c] ?? self::GENERAL[$c] ?? null;
    }

    /** Códigos de un texto "K02.1 (D), K04.0 (P)" → ['K02.1', 'K04.0'] */
    public static function codigos(?string $texto): array
    {
        preg_match_all('/\b([A-Z]\d{2}(?:\.\d{1,2})?)\b/i', (string) $texto, $m);

        return array_values(array_unique(array_map('strtoupper', $m[1])));
    }
}
