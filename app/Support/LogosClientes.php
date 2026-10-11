<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;

/**
 * Logos de clientes de la web pública (sección "Empresas que confían en nosotros"). El dueño los sube y quita desde
 * admin.tushpa.app › Logos web. Se guardan livianos (webp, máx. 320×180) en public/imagenes/web-clientes: dentro de
 * public/imagenes, que es donde el servidor puede escribir (permisos y SELinux) y que no va a git.
 * La primera vez se copian los logos que vienen en el proyecto; si la carpeta no se puede crear, la web muestra esos.
 */
class LogosClientes
{
    private const CARPETA = 'imagenes/web-clientes';

    private const INICIALES = 'web/img/clientes';

    /** @return array<int, string> rutas dentro de public/, en orden */
    public static function todos(): array
    {
        $dir = public_path(self::CARPETA);
        if (! is_dir($dir) && @mkdir($dir, 0775, true)) {
            foreach (glob(public_path(self::INICIALES).'/*.webp') ?: [] as $logo) {
                @copy($logo, $dir.'/'.basename($logo));
            }
        }
        $carpeta = is_dir($dir) ? self::CARPETA : self::INICIALES;
        $archivos = array_map('basename', glob(public_path($carpeta).'/*.{webp,png}', GLOB_BRACE) ?: []);
        natsort($archivos);

        return array_map(fn ($a) => $carpeta.'/'.$a, array_values($archivos));
    }

    public static function url(string $ruta): string
    {
        return asset($ruta).'?v='.@filemtime(public_path($ruta));
    }

    /** Guarda la imagen achicada (webp, o png si el servidor no tiene webp); devuelve su ruta */
    public static function subir(UploadedFile $imagen): string
    {
        self::todos();   // crea la carpeta (y copia los iniciales) si aún no existe
        $dir = public_path(self::CARPETA);
        if (! is_dir($dir) || ! is_writable($dir)) {
            throw new \RuntimeException('El servidor no permite guardar en public/'.self::CARPETA.'. Revisa los permisos de esa carpeta.');
        }

        $origen = @imagecreatefromstring((string) file_get_contents($imagen->getRealPath()));
        if (! $origen) {
            throw new \RuntimeException("No se pudo leer la imagen {$imagen->getClientOriginalName()}.");
        }
        [$ancho, $alto] = [imagesx($origen), imagesy($origen)];
        $factor = min(1, 320 / $ancho, 180 / $alto);
        $nuevoAncho = max(1, (int) round($ancho * $factor));
        $nuevoAlto = max(1, (int) round($alto * $factor));
        $lienzo = imagecreatetruecolor($nuevoAncho, $nuevoAlto);
        imagealphablending($lienzo, false);
        imagesavealpha($lienzo, true);
        imagefill($lienzo, 0, 0, imagecolorallocatealpha($lienzo, 0, 0, 0, 127));
        imagecopyresampled($lienzo, $origen, 0, 0, 0, 0, $nuevoAncho, $nuevoAlto, $ancho, $alto);

        $base = trim(preg_replace('/[^a-z0-9]+/', '-', mb_strtolower(pathinfo($imagen->getClientOriginalName(), PATHINFO_FILENAME))), '-') ?: 'logo';
        $webp = function_exists('imagewebp');
        $nombre = substr($base, 0, 40).'-'.substr(md5(uniqid('', true)), 0, 6).($webp ? '.webp' : '.png');
        $guardado = $webp ? @imagewebp($lienzo, $dir.'/'.$nombre, 85) : @imagepng($lienzo, $dir.'/'.$nombre, 9);
        if (! $guardado) {
            throw new \RuntimeException("No se pudo guardar {$imagen->getClientOriginalName()} en el servidor.");
        }

        return self::CARPETA.'/'.$nombre;
    }

    /** Quita un logo subido (solo archivos de la carpeta de logos, nada de rutas) */
    public static function eliminar(string $ruta): bool
    {
        $archivo = basename($ruta);
        if ($ruta !== self::CARPETA.'/'.$archivo || ! preg_match('/^[a-z0-9-]+\.(webp|png)$/', $archivo)) {
            return false;
        }

        return @unlink(public_path($ruta));
    }
}
