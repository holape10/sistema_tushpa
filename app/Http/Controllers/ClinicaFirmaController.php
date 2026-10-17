<?php

namespace App\Http\Controllers;

use App\Support\Clinica;
use App\Support\ClinicaFirmas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Firma del paciente en la clínica: en la misma pantalla (tablet, celular o PC con mouse) o en el celular del paciente
 * con un QR / enlace que vence en 30 minutos. La página del celular no pide usuario: la protege la firma del enlace.
 */
class ClinicaFirmaController extends Controller
{
    private function documento(string $tipo, int $id): object
    {
        abort_unless(Clinica::usaClinica(Auth::user()), 403, 'No tienes acceso a la clínica.');
        $doc = ClinicaFirmas::documento($tipo, $id, (int) Auth::user()->id_empresa_negocio);
        abort_unless($doc, 404);

        return $doc;
    }

    /** Firma hecha en esta pantalla */
    public function guardar(Request $request, string $tipo, int $id): JsonResponse
    {
        $doc = $this->documento($tipo, $id);
        $d = $request->validate(['firma' => 'required|string|max:600000']);
        try {
            ClinicaFirmas::guardar($doc, $d['firma']);
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()]);
        }

        return response()->json(['ok' => true, 'mensaje' => 'Firma guardada.']);
    }

    /** QR y enlace para que el paciente firme en su celular (también por WhatsApp) */
    public function enlace(string $tipo, int $id): JsonResponse
    {
        $doc = $this->documento($tipo, $id);
        $url = ClinicaFirmas::enlace($tipo, $id);
        $cel = ClinicaFirmas::celular($doc->telefono);
        $texto = 'Hola '.$doc->clinom.', por favor firme aquí su '.mb_strtolower(ClinicaFirmas::TIPOS[$tipo]['titulo']).': '.$url;

        return response()->json(['ok' => true, 'url' => $url, 'qr' => ClinicaFirmas::qr($url), 'minutos' => ClinicaFirmas::MINUTOS_ENLACE,
            'whatsapp' => $cel ? 'https://wa.me/'.$cel.'?text='.rawurlencode($texto) : null]);
    }

    /** ¿Ya firmó? (la pantalla del doctor pregunta mientras el paciente firma en su celular) */
    public function estado(string $tipo, int $id): JsonResponse
    {
        $doc = $this->documento($tipo, $id);

        return response()->json(['firmado' => (bool) $doc->firma, 'firma_url' => $doc->firma ? route('clinica.firma.imagen', [$tipo, $id]) : null]);
    }

    public function imagen(string $tipo, int $id)
    {
        $doc = $this->documento($tipo, $id);
        abort_unless($doc->firma && Storage::disk('local')->exists($doc->firma), 404);

        return response(Storage::disk('local')->get($doc->firma), 200, ['Content-Type' => 'image/png', 'Cache-Control' => 'private, max-age=60']);
    }

    // ------------------------------------------------------------------ celular del paciente (enlace firmado, sin usuario)

    private function documentoRemoto(string $tipo, int $id): object
    {
        $doc = ClinicaFirmas::documento($tipo, $id, null);
        abort_unless($doc, 404);

        return $doc;
    }

    public function remota(Request $request, string $tipo, int $id): View
    {
        $doc = $this->documentoRemoto($tipo, $id);
        $negocio = DB::table('empresa_negocios')->where('id_empresa_negocio', $doc->id_empresa_negocio)->first(['nombre_comercial', 'logo_suc']);

        return view('empresas.clinica.firma_remota', [
            'doc' => $doc, 'titulo' => ClinicaFirmas::TIPOS[$tipo]['titulo'], 'resumen' => ClinicaFirmas::resumen($doc),
            'negocio' => $negocio, 'accion' => $request->fullUrl(),
        ]);
    }

    public function guardarRemota(Request $request, string $tipo, int $id): JsonResponse
    {
        $doc = $this->documentoRemoto($tipo, $id);
        if ($doc->firma) {
            return response()->json(['ok' => false, 'mensaje' => 'Este documento ya fue firmado.']);
        }
        $d = $request->validate(['firma' => 'required|string|max:600000']);
        try {
            ClinicaFirmas::guardar($doc, $d['firma']);
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()]);
        }

        return response()->json(['ok' => true, 'mensaje' => '¡Gracias! Su firma quedó registrada.']);
    }
}
