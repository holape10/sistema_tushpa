<?php

namespace Tests\Flujos;

use App\Support\EtiquetaBalanza;
use Illuminate\Support\Facades\DB;

/** Etiquetas de la balanza etiquetadora: al escanearlas entra el producto con su peso o su precio */
class EtiquetaBalanzaTest extends FlujoTestCase
{
    /** Un EAN-13 con su dígito verificador correcto */
    private function ean(string $doce): string
    {
        $suma = 0;
        for ($i = 0; $i < 12; $i++) {
            $suma += (int) $doce[$i] * ($i % 2 === 0 ? 1 : 3);
        }

        return $doce.((10 - $suma % 10) % 10);
    }

    private function activar(string $modo = 'PESO', string $prefijo = '2'): void
    {
        DB::table('empresa_negocios')->where('id_empresa_negocio', $this->sucursal)
            ->update(['balanza_etiqueta' => 1, 'balanza_prefijo' => $prefijo, 'balanza_modo' => $modo]);
    }

    private function escanear(string $codigo): array
    {
        return $this->getJson(route('pos.productos', ['codigo' => $codigo]))->assertOk()->json();
    }

    public function test_lee_el_peso_y_rechaza_los_codigos_mal_leidos(): void
    {
        $cfg = ['prefijo' => '2', 'modo' => 'PESO'];

        $this->assertSame(['plu' => '00123', 'cantidad' => 1.25, 'importe' => null], EtiquetaBalanza::leer($this->ean('200012301250'), $cfg));
        $mal = substr($this->ean('200012301250'), 0, 12).(((int) substr($this->ean('200012301250'), 12) + 1) % 10);
        $this->assertNull(EtiquetaBalanza::leer($mal, $cfg), 'Dígito verificador incorrecto: lectura fallida');
        $this->assertNull(EtiquetaBalanza::leer($this->ean('210012301250'), ['prefijo' => '20', 'modo' => 'PESO']), 'Otro prefijo');
        $this->assertSame(['plu' => '00123', 'cantidad' => null, 'importe' => 12.5], EtiquetaBalanza::leer($this->ean('200012301250'), ['prefijo' => '2', 'modo' => 'PRECIO']));
    }

    public function test_al_escanear_la_etiqueta_entra_el_producto_con_su_peso(): void
    {
        $this->producto('TOMATE ITALIANO', 4.5)->update(['procod' => '123']);
        $this->activar();

        $r = $this->escanear($this->ean('200012301250'));

        $this->assertSame(['TOMATE ITALIANO', 1.25], [$r[0]['nombre'], $r[0]['etiqueta']['cantidad']]);
    }

    public function test_con_precio_la_etiqueta_trae_el_importe(): void
    {
        $this->producto('QUESO FRESCO', 20)->update(['procod' => '00456']);
        $this->activar('PRECIO');

        $this->assertSame(8.75, $this->escanear($this->ean('200045600875'))[0]['etiqueta']['importe']);
    }

    public function test_apagado_o_con_un_codigo_de_producto_nada_cambia(): void
    {
        $codigo = $this->ean('200012301250');
        $this->producto('TOMATE ITALIANO', 4.5)->update(['procod' => '123']);
        $this->assertSame([], $this->escanear($codigo), 'Apagado: la etiqueta no se lee');

        $this->activar();
        $this->producto('GALLETA IMPORTADA', 3)->update(['codigo_barra' => $codigo]);
        $r = $this->escanear($codigo);
        $this->assertSame('GALLETA IMPORTADA', $r[0]['nombre'], 'Un código que ya es de un producto se vende como siempre');
        $this->assertArrayNotHasKey('etiqueta', $r[0]);
    }
}
