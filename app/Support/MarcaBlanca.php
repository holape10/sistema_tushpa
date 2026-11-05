<?php

namespace App\Support;

use App\Models\Central\Marca;
use App\Support\Tenancy\DatosCobro;

/**
 * Marca blanca: al entrar por el dominio de una marca (wilcatsystems.pe), el sistema se ve con su nombre, su logo, sus
 * colores y su soporte. Cambia config('marca.*') y config('soporte.*') para esa petición: las pantallas, los tickets y
 * los PDF ya leen de ahí, así que no saben de marcas. Sin marca, todo sigue como TUSHPA.
 */
class MarcaBlanca
{
    private static ?Marca $actual = null;

    public static function aplicar(Marca $marca): void
    {
        self::$actual = $marca;
        $logo = $marca->logo && is_file(public_path($marca->logo)) ? $marca->logo : null;
        $telefono = trim((string) $marca->telefono);
        $color = $marca->color ?: '#1565c0';

        config([
            'marca.nombre' => (string) $marca->nombre, 'marca.es_tushpa' => false, 'marca.dominio' => (string) $marca->dominio,
            'marca.color' => $color, 'marca.color_secundario' => $marca->color_secundario ?: $color,
            // Su soporte: quien atiende a sus empresas es el revendedor
            'soporte.nombre' => (string) ($marca->razon_social ?: $marca->nombre), 'soporte.telefono' => $telefono,
            'soporte.whatsapp' => DatosCobro::whatsapp($telefono), 'soporte.correo' => (string) $marca->correo,
            'soporte.correos_web' => array_values(array_filter([(string) $marca->correo])), 'soporte.direccion' => (string) $marca->direccion,
            'soporte.horario' => '',
            'soporte.firma_comprobante' => trim('Sistema '.$marca->nombre.' - '.$marca->dominio.($telefono !== '' ? ' - '.$telefono : '')),
        ] + ($logo ? ['marca.logo' => $logo, 'marca.icono' => $logo, 'marca.icono180' => $logo, 'marca.icono192' => $logo, 'marca.icono512' => $logo] : []));
    }

    /** La marca con la que se ve el sistema en esta petición (null = TUSHPA) */
    public static function actual(): ?Marca
    {
        return self::$actual;
    }

    /** Para las pruebas */
    public static function olvidar(): void
    {
        self::$actual = null;
    }
}
