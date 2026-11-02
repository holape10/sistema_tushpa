<?php

namespace App\Http\Controllers;

use App\Models\Central\Cliente;
use App\Support\ClinicaFirmas;
use App\Support\Tenancy\Contratos;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Página del contrato para la empresa (tushpa.app/contrato/{enlace}): sin usuario ni contraseña, solo con el enlace que
 * le manda el panel o que ve dentro de su sistema. Ahí pone quién firma, sube su DNI, descarga el PDF y firma.
 */
class ContratoController extends Controller
{
    private function contrato(string $token): object
    {
        $contrato = Contratos::porToken($token);
        abort_unless($contrato, 404);

        return $contrato;
    }

    public function pagina(string $token): View
    {
        $contrato = $this->contrato($token);

        return view('sitio.contrato', Contratos::datos($contrato) + ['token' => $token, 'editable' => Contratos::editable($contrato)]);
    }

    /** El contrato en PDF: sin firmar para firmarlo a mano, o ya firmado con su constancia */
    public function pdf(string $token): Response
    {
        $contrato = $this->contrato($token);

        return response(Contratos::pdf($contrato), 200, ['Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.Contratos::nombrePdf($contrato).'"']);
    }

    public function enviar(Request $request, string $token): RedirectResponse
    {
        $contrato = $this->contrato($token);
        abort_unless(Contratos::editable($contrato), 422, 'Este contrato ya se envió.');
        $archivo = 'file|mimes:'.Contratos::MIMES.'|max:'.config('contrato.max_kb');
        $d = $request->validate([
            'firmante' => 'required|string|min:5|max:150',
            'firmante_dni' => ['required', 'regex:/^(\d{8}|[A-Za-z0-9]{9,12})$/'],
            'firmante_cargo' => 'nullable|string|max:60',
            'correo' => 'nullable|email|max:120',
            'telefono' => 'nullable|string|max:20',
            'dni_frente' => ($contrato->dni_frente ? 'nullable|' : 'required|').$archivo,
            'dni_reverso' => ($contrato->dni_reverso ? 'nullable|' : 'required|').$archivo,
            'forma' => 'required|in:pantalla,escaneado',
            'firma' => 'required_if:forma,pantalla|nullable|string|max:600000',
            'escaneado' => 'required_if:forma,escaneado|nullable|'.$archivo,
            'acepto' => 'accepted',
        ], [
            'firmante_dni.regex' => 'Escribe el DNI (8 dígitos) o el carné de extranjería.',
            'firma.required_if' => 'Firma dentro del recuadro.', 'escaneado.required_if' => 'Sube el contrato firmado (foto o PDF).',
            'acepto.accepted' => 'Marca que leíste y aceptas el contrato.',
        ], ['firmante' => 'nombre de quien firma', 'firmante_dni' => 'DNI', 'dni_frente' => 'DNI (cara delantera)', 'dni_reverso' => 'DNI (cara posterior)',
            'escaneado' => 'contrato firmado', 'firmante_cargo' => 'cargo']);

        try {
            $png = $d['forma'] === 'pantalla' ? ClinicaFirmas::png((string) $d['firma']) : null;
            Contratos::completar($contrato, $d, $request->file('dni_frente'), $request->file('dni_reverso'), $png,
                $d['forma'] === 'escaneado' ? $request->file('escaneado') : null, (string) $request->ip(), $request->userAgent());
        } catch (\RuntimeException $e) {
            return back()->withInput()->withErrors(['firma' => $e->getMessage()]);
        }

        return redirect()->route('sitio.contrato', $token)->with('ok', '¡Listo! Recibimos tu contrato firmado. Lo revisaremos y te avisaremos.');
    }

    /** La empresa desde su sistema: el enlace de su contrato pendiente (el aviso dentro del sistema lo usa) */
    public static function enlacePendiente(?Cliente $cliente): ?string
    {
        if (! $cliente) {
            return null;
        }
        $contrato = Contratos::deCliente($cliente->id);

        return $contrato && Contratos::editable($contrato) ? route('sitio.contrato', $contrato->token) : null;
    }
}
