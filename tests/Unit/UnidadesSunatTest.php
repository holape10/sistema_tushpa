<?php

namespace Tests\Unit;

use App\Support\Unidades;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class UnidadesSunatTest extends TestCase
{
    /** @return array<string, array{?string, string}> */
    public static function casos(): array
    {
        return [
            'vigente se manda igual' => ['KGM', 'KGM'],
            'paquete NMP sigue vigente' => ['NMP', 'NMP'],
            'servicio' => ['ZZ', 'ZZ'],
            'par obsoleto pasa a PR' => ['NPR', 'PR'],
            'caja (envase eliminado) va como unidad' => ['BX', 'NIU'],
            'código inventado va como unidad' => ['TAP', 'NIU'],
            'frasco mal usado (FR es pie por minuto) va como unidad' => ['FR', 'NIU'],
            'minúsculas y espacios' => [' kgm ', 'KGM'],
            'vacío' => [null, 'NIU'],
        ];
    }

    #[DataProvider('casos')]
    public function test_codigo_que_va_a_sunat(?string $umecod, string $esperado): void
    {
        $this->assertSame($esperado, Unidades::sunat($umecod));
    }

    /** @return array<string, array{string, string}> */
    public static function plurales(): array
    {
        return [
            'vocal' => ['Botella', 'Botellas'], 'termina en ón' => ['Galón', 'Galones'], 'termina en d' => ['Unidad', 'Unidades'],
            'termina en l' => ['Barril', 'Barriles'], 'préstamo del inglés' => ['Pack', 'Packs'], 'táper' => ['Taper', 'Tapers'],
            'dos palabras' => ['Metro cúbico', 'Metros cúbicos'], 'con conector' => ['Kit o Juego', 'Kits o Juegos'],
            'ya en plural' => ['Litros', 'Litros'], 'mes' => ['Mes', 'Meses'], 'mayúsculas' => ['CAJA', 'CAJAS'],
        ];
    }

    #[DataProvider('plurales')]
    public function test_plural_del_nombre_de_la_unidad(string $nombre, string $esperado): void
    {
        $this->assertSame($esperado, Unidades::plural($nombre));
    }

    public function test_las_unidades_del_catalogo_que_son_vigentes_se_mandan_tal_cual(): void
    {
        foreach (array_keys(Unidades::CATALOGO) as $cod) {
            $this->assertContains(Unidades::sunat($cod), Unidades::VIGENTES_SUNAT, "La unidad {$cod} manda un código que no es vigente");
        }
    }
}
