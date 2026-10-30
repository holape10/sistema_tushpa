<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Fotos de productos livianas: al subirlas se achican (lado mayor 900 px) y se guardan en WEBP; para los botones de venta
 * se usa una miniatura de 200 px (unos pocos KB), así la comanda y el punto de venta cargan rápido aun con internet lento.
 * Solo usa GD (viene con PHP). Si la imagen no se puede procesar, se guarda tal cual.
 */
class ImagenProducto
{
    public const LADO = 900;

    public const LADO_MINI = 200;

    public const CALIDAD = 78;

    /** Miniaturas que se crean como máximo en una sola carga de pantalla (las demás se crean en las siguientes) */
    private const MINIS_POR_CARGA = 40;

    private static int $creadas = 0;

    /** ¿La sucursal muestra las fotos en los botones de venta (comandas y punto de venta)? */
    public static function enVentas(int $sucursal): bool
    {
        return (bool) DB::table('empresa_negocios')->where('id_empresa_negocio', $sucursal)->value('imagenes_venta');
    }

    /**
     * Guarda la foto subida ya optimizada ($lado: lado mayor en px; las capturas de los tutoriales usan más para leerse bien).
     *
     * @return string ruta relativa a public/
     */
    public static function guardar(UploadedFile $archivo, string $carpeta, string $base, int $lado = self::LADO): string
    {
        if (! is_dir(public_path($carpeta))) {
            @mkdir(public_path($carpeta), 0775, true);
        }
        $destino = $carpeta.'/'.$base.'.webp';
        if (self::redimensionar($archivo->getRealPath(), public_path($destino), $lado, self::orientacion($archivo->getRealPath()))) {
            self::redimensionar(public_path($destino), public_path(self::rutaMini($destino)), self::LADO_MINI);

            return $destino;
        }
        // No se pudo procesar (formato raro): se guarda tal cual
        $nombre = $base.'.'.strtolower($archivo->guessExtension() ?: 'jpg');
        $archivo->move(public_path($carpeta), $nombre);

        return $carpeta.'/'.$nombre;
    }

    /** URL de la miniatura para un botón (se crea la primera vez que se necesita; mientras tanto, la foto original) */
    public static function mini(?string $ruta): ?string
    {
        if (! $ruta || ! is_file(public_path($ruta))) {
            return null;
        }
        $mini = self::rutaMini($ruta);
        if (! is_file(public_path($mini))) {
            if (self::$creadas >= self::MINIS_POR_CARGA || ! self::redimensionar(public_path($ruta), public_path($mini), self::LADO_MINI, self::orientacion(public_path($ruta)))) {
                return asset($ruta);
            }
            self::$creadas++;
        }

        return asset($mini);
    }

    /** Borra la foto y su miniatura */
    public static function borrar(?string $ruta): void
    {
        foreach ($ruta ? [$ruta, self::rutaMini($ruta)] : [] as $r) {
            if (str_starts_with($r, 'imagenes/productos/') && is_file(public_path($r))) {
                @unlink(public_path($r));
            }
        }
    }

    public static function rutaMini(string $ruta): string
    {
        return preg_replace('/\.[a-z0-9]+$/i', '', $ruta).'_mini.webp';
    }

    /** Las fotos del celular vienen giradas en la marca EXIF: grados a girar para verlas derechas */
    private static function orientacion(string $archivo): int
    {
        $exif = function_exists('exif_read_data') ? @exif_read_data($archivo) : false;

        return [3 => 180, 6 => -90, 8 => 90][(int) ($exif['Orientation'] ?? 1)] ?? 0;
    }

    /** Achica la imagen (sin agrandarla) a $lado px como máximo y la guarda en WEBP (también la usan las promociones de la web) */
    public static function redimensionar(string $origen, string $destino, int $lado, int $girar = 0): bool
    {
        $info = @getimagesize($origen);
        if (! $info || ! function_exists('imagewebp')) {
            return false;
        }
        $img = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($origen),
            IMAGETYPE_PNG => @imagecreatefrompng($origen),
            IMAGETYPE_WEBP => @imagecreatefromwebp($origen),
            IMAGETYPE_GIF => @imagecreatefromgif($origen),
            default => false,
        };
        if (! $img) {
            return false;
        }
        if ($girar) {
            $img = imagerotate($img, $girar, 0) ?: $img;
        }
        [$ancho, $alto] = [imagesx($img), imagesy($img)];
        $escala = min(1, $lado / max($ancho, $alto));
        [$w, $h] = [max(1, (int) round($ancho * $escala)), max(1, (int) round($alto * $escala))];
        $nueva = imagecreatetruecolor($w, $h);
        imagealphablending($nueva, false);
        imagesavealpha($nueva, true);
        imagecopyresampled($nueva, $img, 0, 0, 0, 0, $w, $h, $ancho, $alto);
        $ok = imagewebp($nueva, $destino, self::CALIDAD);
        imagedestroy($img);
        imagedestroy($nueva);

        return $ok;
    }
}
