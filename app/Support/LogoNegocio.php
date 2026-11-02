<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;

/**
 * Logos de la empresa y de cada sucursal (tickets, comprobantes, carta, tienda…). La sucursal que tiene logo propio usa
 * el suyo; la que no, el de la empresa. Se guardan en public/imagenes/logos con un nombre que arma el servidor.
 */
class LogoNegocio
{
    public const CARPETA = 'imagenes/logos';

    /** Logo que corresponde: el de la sucursal y, si no tiene (o el archivo ya no está), el de la empresa */
    public static function de(?string $logoSucursal, ?string $logoEmpresa): ?string
    {
        return collect([$logoSucursal, $logoEmpresa])->first(fn ($l) => $l && is_file(public_path($l)));
    }

    /** Guarda la imagen subida (nunca con el nombre ni la extensión que manda el navegador) y devuelve su ruta */
    public static function guardar(UploadedFile $imagen, string $prefijo): string
    {
        $dir = public_path(self::CARPETA);
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $nombre = preg_replace('/[^A-Za-z0-9_-]/', '', $prefijo).'_'.time().'.'.($imagen->guessExtension() ?: 'png');
        $imagen->move($dir, $nombre);

        return self::CARPETA.'/'.$nombre;
    }

    /** Borra un logo subido al sistema (solo de su carpeta) */
    public static function borrar(?string $ruta): void
    {
        if ($ruta && str_starts_with($ruta, self::CARPETA.'/') && is_file(public_path($ruta))) {
            @unlink(public_path($ruta));
        }
    }
}
