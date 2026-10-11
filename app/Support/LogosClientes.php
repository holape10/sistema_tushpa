<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;

/**
 * Logos de clientes de la web pública (sección "Empresas que confían en nosotros"). El dueño los sube y quita desde
 * admin.tushpa.app › Logos web. Se guardan livianos (webp, máx. 320×180) en public/web/img/logos, carpeta que no va
 * a git para que actualizar.sh no los borre. La primera vez se copian los logos que venían en el proyecto.
 */
class LogosClientes
{
    private const CARPETA = 'web/img/logos';

    private const INICIALES = 'web/img/clientes';

    /** @return array<int, string> nombres de archivo, en orden */
    public static function todos(): array
    {
        $dir = self::carpeta();
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
            foreach (glob(public_path(self::INICIALES).'/*.webp') ?: [] as $logo) {
                @copy($logo, $dir.'/'.basename($logo));
            }
        }
        $archivos = array_map('basename', glob($dir.'/*.webp') ?: []);
        natsort($archivos);

        return array_values($archivos);
    }

    public static function url(string $archivo): string
    {
        return asset(self::CARPETA.'/'.$archivo).'?v='.@filemtime(self::carpeta().'/'.$archivo);
    }

    /** Guarda la imagen como webp liviana; devuelve el nombre del archivo */
    public static function subir(UploadedFile $imagen): string
    {
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

        self::todos();   // crea la carpeta (y copia los iniciales) si aún no existe
        $base = trim(preg_replace('/[^a-z0-9]+/', '-', mb_strtolower(pathinfo($imagen->getClientOriginalName(), PATHINFO_FILENAME))), '-') ?: 'logo';
        $nombre = substr($base, 0, 40).'-'.substr(md5(uniqid('', true)), 0, 6).'.webp';
        imagewebp($lienzo, self::carpeta().'/'.$nombre, 85);

        return $nombre;
    }

    public static function eliminar(string $archivo): bool
    {
        // Solo nombres simples de esta carpeta (nada de rutas)
        if (! preg_match('/^[a-z0-9-]+\.webp$/', $archivo)) {
            return false;
        }

        return @unlink(self::carpeta().'/'.$archivo);
    }

    private static function carpeta(): string
    {
        return public_path(self::CARPETA);
    }
}
