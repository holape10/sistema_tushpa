<?php

namespace App\Support\Tenancy;

use App\Models\Central\Cliente;
use App\Models\Central\Superadmin;
use Illuminate\Http\UploadedFile;

/**
 * A quién le paga una empresa suspendida: al usuario del panel que la creó, si puso sus cuentas o QR; si no (o si ese
 * usuario está desactivado), al dueño del sistema: sus cuentas y QR del panel o, si no puso ninguno, config/soporte.php.
 * Cada usuario puede tener varias cuentas (BCP, BBVA…) y varios QR (Yape, Plin…).
 */
class DatosCobro
{
    public const CARPETA = 'imagenes/cobros';

    /** ¿Este usuario del panel tiene cuentas o QR propios? */
    public static function tienePropios(?Superadmin $usuario): bool
    {
        return $usuario && $usuario->activo && $usuario->cobros()->exists();
    }

    /** ¿La empresa le paga al dueño del sistema? (las del revendedor que puso sus propias cuentas le pagan a él) */
    public static function cobraElDueno(Cliente $cliente): bool
    {
        $creador = $cliente->creado_por ? Superadmin::find($cliente->creado_por) : null;

        return ! self::tienePropios($creador) || (bool) $creador->es_dueno;
    }

    /**
     * @return array{titular: string, banco: string, cuenta: ?string, cci: ?string, qr: ?string, whatsapp: string, telefono: string, horario: ?string,
     *     cuentas: array<int, array{banco: string, titular: string, cuenta: ?string, cci: ?string}>, qrs: array<int, array{nombre: string, titular: string, url: string}>}
     */
    public static function para(Cliente $cliente): array
    {
        $soporte = config('soporte');
        $creador = $cliente->creado_por ? Superadmin::find($cliente->creado_por) : null;
        $usuario = self::tienePropios($creador) ? $creador : Superadmin::where('es_dueno', 1)->where('activo', 1)->orderBy('id')->get()
            ->first(fn ($u) => self::tienePropios($u));

        if ($usuario) {
            $titular = (string) ($usuario->cobro_titular ?: $usuario->nombre);
            $cuentas = $usuario->cobros->where('tipo', 'CUENTA')->map(fn ($c) => [
                'banco' => (string) $c->banco, 'titular' => (string) ($c->titular ?: $titular), 'cuenta' => $c->cuenta, 'cci' => $c->cci,
            ])->values()->all();
            $qrs = $usuario->cobros->where('tipo', 'QR')->filter(fn ($c) => $c->qr)->map(fn ($c) => [
                'nombre' => (string) ($c->banco ?: 'QR de pago'), 'titular' => (string) ($c->titular ?: $titular),
                'url' => asset($c->qr).'?v='.@filemtime(public_path($c->qr)),
            ])->values()->all();
            // El dueño sin WhatsApp propio recibe en el de soporte
            $whatsapp = (string) ($usuario->cobro_whatsapp ?: $soporte['whatsapp']);

            return [
                'titular' => $cuentas[0]['titular'] ?? $qrs[0]['titular'] ?? $titular, 'banco' => $cuentas[0]['banco'] ?? '',
                'cuenta' => $cuentas[0]['cuenta'] ?? null, 'cci' => $cuentas[0]['cci'] ?? null, 'qr' => $qrs[0]['url'] ?? null,
                'whatsapp' => $whatsapp, 'telefono' => (string) ($usuario->cobro_telefono ?: $soporte['telefono']),
                'horario' => $usuario->es_dueno ? $soporte['horario'] : null, 'cuentas' => $cuentas, 'qrs' => $qrs,
            ];
        }

        $p = $soporte['pago'];

        return [
            'titular' => $p['titular'], 'banco' => $p['banco'], 'cuenta' => $p['cuenta'], 'cci' => $p['cci'], 'qr' => asset($p['qr']),
            'whatsapp' => $soporte['whatsapp'], 'telefono' => $soporte['telefono'], 'horario' => $soporte['horario'],
            'cuentas' => [['banco' => $p['banco'], 'titular' => $p['titular'], 'cuenta' => $p['cuenta'], 'cci' => $p['cci']]],
            'qrs' => [['nombre' => 'QR de pago', 'titular' => $p['titular'], 'url' => asset($p['qr'])]],
        ];
    }

    /** Guarda la imagen de un QR (en public/: la ve la pantalla de suspendido sin sesión) */
    public static function guardarQr(Superadmin $usuario, UploadedFile $imagen): string
    {
        $dir = public_path(self::CARPETA);
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $nombre = $usuario->id.'-'.bin2hex(random_bytes(6)).'.'.strtolower($imagen->extension() ?: 'png');
        $imagen->move($dir, $nombre);

        return self::optimizar(self::CARPETA.'/'.$nombre);
    }

    /**
     * QR liviano: la pantalla de suspendido lo muestra al instante. Se achica a 720 px de ancho y se guarda en WebP
     * (un QR de 1 MB queda en unas decenas de KB y se sigue leyendo bien). Si no se puede, queda la imagen original.
     */
    public static function optimizar(string $ruta): string
    {
        $archivo = public_path($ruta);
        $info = @getimagesize($archivo);
        if (! $info || ! function_exists('imagewebp') || ! in_array($info[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG, IMAGETYPE_WEBP], true)) {
            return $ruta;
        }
        $origen = match ($info[2]) {
            IMAGETYPE_PNG => @imagecreatefrompng($archivo), IMAGETYPE_JPEG => @imagecreatefromjpeg($archivo), default => @imagecreatefromwebp($archivo),
        };
        if (! $origen) {
            return $ruta;
        }
        $ancho = min(720, $info[0]);
        $imagen = $ancho < $info[0] ? imagescale($origen, $ancho, -1, IMG_BICUBIC) : $origen;
        $nueva = preg_replace('/\.\w+$/', '', $ruta).'.webp';
        $ok = imagewebp($imagen, public_path($nueva), 88);
        if (! $ok || filesize(public_path($nueva)) >= filesize($archivo)) {
            if ($nueva !== $ruta) {
                @unlink(public_path($nueva));
            }

            return $ruta;
        }
        if ($nueva !== $ruta) {
            @unlink($archivo);
        }

        return $nueva;
    }

    public static function borrarImagen(?string $ruta): void
    {
        if ($ruta && str_starts_with($ruta, self::CARPETA.'/')) {
            @unlink(public_path($ruta));
        }
    }
}
