<?php

namespace App\Support\Tenancy;

use App\Models\Central\Cliente;
use App\Models\Central\Superadmin;
use Illuminate\Http\UploadedFile;

/**
 * A quién le paga una empresa suspendida: al usuario del panel que la creó, si puso sus datos de cobro (cuenta o QR);
 * si no (o si la creó el dueño, o ese usuario está desactivado), al dueño del sistema (config/soporte.php).
 */
class DatosCobro
{
    public const CARPETA = 'imagenes/cobros';

    /** ¿Este usuario del panel tiene datos de cobro propios? */
    public static function tienePropios(?Superadmin $usuario): bool
    {
        return $usuario && ! $usuario->es_dueno && $usuario->activo && ($usuario->cobro_cuenta || $usuario->cobro_qr);
    }

    /**
     * @return array{titular: string, banco: string, cuenta: ?string, cci: ?string, qr: ?string, whatsapp: string, telefono: string, horario: ?string}
     */
    public static function para(Cliente $cliente): array
    {
        $usuario = $cliente->creado_por ? Superadmin::find($cliente->creado_por) : null;
        $soporte = config('soporte');

        if (self::tienePropios($usuario)) {
            return [
                'titular' => (string) ($usuario->cobro_titular ?: $usuario->nombre), 'banco' => (string) $usuario->cobro_banco,
                'cuenta' => $usuario->cobro_cuenta, 'cci' => $usuario->cobro_cci,
                'qr' => $usuario->cobro_qr ? asset($usuario->cobro_qr).'?v='.@filemtime(public_path($usuario->cobro_qr)) : null,
                // Sin WhatsApp propio, el comprobante igual llega a soporte
                'whatsapp' => (string) ($usuario->cobro_whatsapp ?: $soporte['whatsapp']),
                'telefono' => (string) ($usuario->cobro_telefono ?: $usuario->cobro_whatsapp ?: $soporte['telefono']),
                'horario' => null,
            ];
        }

        return [
            'titular' => $soporte['pago']['titular'], 'banco' => $soporte['pago']['banco'], 'cuenta' => $soporte['pago']['cuenta'],
            'cci' => $soporte['pago']['cci'], 'qr' => asset($soporte['pago']['qr']),
            'whatsapp' => $soporte['whatsapp'], 'telefono' => $soporte['telefono'], 'horario' => $soporte['horario'],
        ];
    }

    /** Guarda la imagen del QR (en public/, la ve la pantalla de suspendido sin sesión) y borra la anterior */
    public static function guardarQr(Superadmin $usuario, UploadedFile $imagen): string
    {
        $dir = public_path(self::CARPETA);
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $nombre = $usuario->id.'-'.bin2hex(random_bytes(6)).'.'.strtolower($imagen->extension() ?: 'png');
        $imagen->move($dir, $nombre);
        self::quitarQr($usuario);

        return self::CARPETA.'/'.$nombre;
    }

    public static function quitarQr(Superadmin $usuario): void
    {
        if ($usuario->cobro_qr && str_starts_with($usuario->cobro_qr, self::CARPETA.'/')) {
            @unlink(public_path($usuario->cobro_qr));
        }
    }
}
