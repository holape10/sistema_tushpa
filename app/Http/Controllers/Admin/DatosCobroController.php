<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Central\Superadmin;
use App\Support\Tenancy\DatosCobro;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * "Mis datos de cobro": cada usuario del panel (que no es el dueño) pone su QR y su cuenta. Sus empresas los ven al
 * quedar suspendidas; si no pone nada, ven los del dueño del sistema.
 */
class DatosCobroController extends Controller
{
    private function yo(): Superadmin
    {
        $yo = Auth::guard('superadmin')->user();
        abort_if($yo->es_dueno, 403, 'Tus datos de cobro salen de la configuración del sistema.');

        return $yo;
    }

    public function index(): View
    {
        return view('admin.cobros', ['yo' => $this->yo(), 'propios' => DatosCobro::tienePropios($this->yo())]);
    }

    public function guardar(Request $request): RedirectResponse
    {
        $yo = $this->yo();
        $d = $request->validate([
            'cobro_titular' => 'nullable|string|max:120',
            'cobro_banco' => 'nullable|string|max:60',
            'cobro_cuenta' => 'nullable|string|max:40|regex:/^[0-9 -]+$/',
            'cobro_cci' => 'nullable|string|max:40|regex:/^[0-9 -]+$/',
            'cobro_whatsapp' => 'nullable|string|max:20|regex:/^\+?[0-9 ]{9,16}$/',
            'qr' => 'nullable|image|mimes:png,jpg,jpeg,webp|max:5120',
        ], [
            'cobro_cuenta.regex' => 'La cuenta solo lleva números y guiones.', 'cobro_cci.regex' => 'El CCI solo lleva números y guiones.',
            'cobro_whatsapp.regex' => 'Escribe el WhatsApp con números (ej. 51987654321).', 'qr.image' => 'El QR debe ser una imagen PNG, JPG o WEBP.',
        ], ['cobro_titular' => 'titular', 'cobro_banco' => 'banco', 'cobro_cuenta' => 'cuenta', 'cobro_cci' => 'CCI', 'cobro_whatsapp' => 'WhatsApp']);

        $datos = collect($d)->except('qr')->map(fn ($v) => trim((string) $v) ?: null)->all();
        // WhatsApp con código de país (Perú si escriben solo los 9 dígitos), sin "+" ni espacios
        if ($datos['cobro_whatsapp'] ?? null) {
            $numero = preg_replace('/\D/', '', $datos['cobro_whatsapp']);
            $datos['cobro_whatsapp'] = strlen($numero) === 9 ? '51'.$numero : $numero;
            $datos['cobro_telefono'] = substr($datos['cobro_whatsapp'], -9);
        } else {
            $datos['cobro_telefono'] = null;
        }
        if ($request->hasFile('qr')) {
            $datos['cobro_qr'] = DatosCobro::guardarQr($yo, $request->file('qr'));
        }
        $yo->update($datos);

        return back()->with('ok', DatosCobro::tienePropios($yo->fresh())
            ? 'Datos de cobro guardados: tus empresas los verán si quedan suspendidas.'
            : 'Guardado. Falta la cuenta o el QR: mientras tanto tus empresas ven los datos de TUSHPA.');
    }

    public function quitarQr(): RedirectResponse
    {
        $yo = $this->yo();
        DatosCobro::quitarQr($yo);
        $yo->update(['cobro_qr' => null]);

        return back()->with('ok', 'QR quitado.');
    }
}
