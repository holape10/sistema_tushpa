<?php

namespace App\Support;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/**
 * Firmas del paciente en la clínica: la sesión del registro de tratamientos, el presupuesto (plan de tratamiento) y el
 * consentimiento informado. Se firma con el dedo, lápiz o mouse en la misma pantalla, o en el celular del paciente con
 * un enlace firmado (QR) que vence en 30 minutos y no pide usuario ni contraseña.
 */
class ClinicaFirmas
{
    /** @var array<string, array{tabla: string, clave: string, titulo: string}> */
    public const TIPOS = [
        'sesion' => ['tabla' => 'clinica_tratamiento_registros', 'clave' => 'reg_id', 'titulo' => 'Sesión del tratamiento'],
        'presupuesto' => ['tabla' => 'clinica_presupuestos', 'clave' => 'pre_id', 'titulo' => 'Presupuesto / plan de tratamiento'],
        'consentimiento' => ['tabla' => 'clinica_consentimientos', 'clave' => 'con_id', 'titulo' => 'Consentimiento informado'],
    ];

    public const MINUTOS_ENLACE = 30;

    /** El documento a firmar con su paciente, o null si no existe en esa sucursal (null = cualquiera: lo pide el enlace firmado) */
    public static function documento(string $tipo, int $id, ?int $suc): ?object
    {
        if (! isset(self::TIPOS[$tipo])) {
            return null;
        }
        $q = $tipo === 'sesion'
            ? DB::table('clinica_tratamiento_registros as x')->join('clinica_tratamientos as t', 't.tra_id', '=', 'x.tra_id')
                ->join('historia_clinica as h', 'h.his_cli_id', '=', 't.his_cli_id')->where('x.reg_id', $id)
                ->select('x.*', 't.descripcion as tratamiento', 't.id_empresa_negocio', 't.costo')
            : DB::table(self::TIPOS[$tipo]['tabla'].' as x')->join('historia_clinica as h', 'h.his_cli_id', '=', 'x.his_cli_id')
                ->where('x.'.self::TIPOS[$tipo]['clave'], $id)->select('x.*');
        $doc = $q->join('cliente as c', 'c.clicod', '=', 'h.clicod')
            ->addSelect('h.his_cli_cod', 'h.tipo', 'h.mascota', 'c.clinom', 'c.clinum', 'c.telefono')
            ->when($suc, fn ($q) => $q->where('h.id_empresa_negocio', $suc))->first();
        if ($doc) {
            $doc->tipo_doc = $tipo;
            $doc->id_doc = $id;
        }

        return $doc;
    }

    /** Lo que el paciente ve antes de firmar en su celular */
    public static function resumen(object $doc): array
    {
        $soles = fn ($n) => 'S/ '.number_format((float) $n, 2);

        return match ($doc->tipo_doc) {
            'sesion' => array_filter([
                'Tratamiento' => $doc->tratamiento,
                'Fecha' => Carbon::parse($doc->fecha_hora)->format('d/m/Y H:i'),
                'Realizado / indicaciones' => $doc->detalle,
                'Pago de hoy' => (float) $doc->adelanto > 0 ? $soles($doc->adelanto) : null,
                'Saldo' => $soles($doc->saldo),
            ]),
            'presupuesto' => ['Presupuesto N°' => str_pad((string) $doc->numero, 5, '0', STR_PAD_LEFT), 'Fecha' => Carbon::parse($doc->fecha)->format('d/m/Y'),
                'Tratamientos' => collect(json_decode((string) $doc->items, true) ?: [])->map(fn ($i) => trim(($i['pieza'] ? 'Pieza '.$i['pieza'].': ' : '').$i['descripcion']).' · '.$soles($i['cantidad'] * $i['precio']))->implode("\n"),
                'Total' => $soles($doc->total), 'Válido por' => $doc->validez_dias.' días'],
            'consentimiento' => array_filter(['Tratamiento' => $doc->tratamiento, 'Riesgos explicados' => $doc->riesgos]),
        };
    }

    /** Guarda la imagen PNG de la firma y la anota en el documento */
    public static function guardar(object $doc, string $dataUrl): string
    {
        if ($doc->tipo_doc === 'sesion') {
            return Clinica::guardarFirma((int) $doc->reg_id, (int) $doc->id_empresa_negocio, $dataUrl);
        }
        $png = self::png($dataUrl);
        $ruta = 'clinica/firmas/'.$doc->id_empresa_negocio.'/'.$doc->tipo_doc.'-'.$doc->id_doc.'.png';
        Storage::disk('local')->put($ruta, $png);
        DB::table(self::TIPOS[$doc->tipo_doc]['tabla'])->where(self::TIPOS[$doc->tipo_doc]['clave'], $doc->id_doc)
            ->update(['firma' => $ruta, 'firmado' => now()]);

        return $ruta;
    }

    public static function png(string $dataUrl): string
    {
        if (! preg_match('#^data:image/png;base64,([A-Za-z0-9+/=]+)$#', $dataUrl, $m)) {
            throw new \RuntimeException('La firma no es una imagen válida.');
        }
        $png = base64_decode($m[1], true);
        if ($png === false || ! str_starts_with($png, "\x89PNG") || strlen($png) > 400_000) {
            throw new \RuntimeException('La firma no es una imagen válida.');
        }

        return $png;
    }

    /** La firma lista para incrustar en una hoja impresa */
    public static function imagen(?string $ruta): ?string
    {
        return $ruta && Storage::disk('local')->exists($ruta) ? 'data:image/png;base64,'.base64_encode(Storage::disk('local')->get($ruta)) : null;
    }

    /** Enlace para firmar en el celular del paciente (vence en 30 minutos) */
    public static function enlace(string $tipo, int $id): string
    {
        return URL::temporarySignedRoute('clinica.firma.remota', now()->addMinutes(self::MINUTOS_ENLACE), ['tipo' => $tipo, 'id' => $id]);
    }

    public static function qr(string $texto, int $tamano = 220): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle($tamano, 1), new SvgImageBackEnd));

        return preg_replace('/^<\?xml[^>]*>\s*/', '', $writer->writeString($texto));
    }

    /** 987654321 / +51 987 654 321 → 51987654321 (null si no parece un celular) */
    public static function celular(?string $telefono): ?string
    {
        $n = preg_replace('/\D/', '', (string) $telefono);
        if (strlen($n) === 9 && $n[0] === '9') {
            return '51'.$n;
        }

        return strlen($n) >= 10 && strlen($n) <= 15 ? $n : null;
    }
}
