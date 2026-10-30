<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Central\Superadmin;
use App\Models\Central\SuperadminCobro;
use App\Support\Tenancy\DatosCobro;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * "Mis cuentas y QR de cobro": cada usuario del panel, también el dueño, pone las cuentas bancarias y los QR (Yape, Plin…)
 * que quiera. Sus empresas los ven al quedar suspendidas; si no pone ninguno, ven los del dueño.
 */
class DatosCobroController extends Controller
{
    private function yo(): Superadmin
    {
        return Auth::guard('superadmin')->user();
    }

    private function mio(int $id): SuperadminCobro
    {
        return SuperadminCobro::where('id', $id)->where('superadmin_id', $this->yo()->id)->firstOrFail();
    }

    private function siguienteOrden(): int
    {
        return (int) SuperadminCobro::where('superadmin_id', $this->yo()->id)->max('orden') + 1;
    }

    public function index(): View
    {
        $yo = $this->yo();

        return view('admin.cobros', ['yo' => $yo, 'cuentas' => $yo->cobros()->where('tipo', 'CUENTA')->get(),
            'qrs' => $yo->cobros()->where('tipo', 'QR')->get(), 'propios' => DatosCobro::tienePropios($yo)]);
    }

    /** Agregar o corregir una cuenta bancaria */
    public function guardarCuenta(Request $request, ?int $id = null): RedirectResponse
    {
        $d = $request->validate([
            'banco' => 'required|string|max:60',
            'titular' => 'nullable|string|max:120',
            'cuenta' => 'required_without:cci|nullable|string|max:40|regex:/^[0-9 -]+$/',
            'cci' => 'nullable|string|max:40|regex:/^[0-9 -]+$/',
        ], ['cuenta.regex' => 'La cuenta solo lleva números y guiones.', 'cci.regex' => 'El CCI solo lleva números y guiones.',
            'cuenta.required_without' => 'Escribe el número de cuenta o el CCI.'], ['banco' => 'banco', 'titular' => 'titular']);
        $datos = collect($d)->map(fn ($v) => trim((string) $v) ?: null)->all();

        if ($id) {
            $this->mio($id)->update($datos);
        } else {
            SuperadminCobro::create($datos + ['superadmin_id' => $this->yo()->id, 'tipo' => 'CUENTA', 'orden' => $this->siguienteOrden()]);
        }

        return back()->with('ok', $id ? 'Cuenta corregida.' : 'Cuenta agregada: tus empresas suspendidas ya la ven.');
    }

    /** Agregar un QR (o cambiar la imagen o el nombre de uno) */
    public function guardarQr(Request $request, ?int $id = null): RedirectResponse
    {
        $d = $request->validate([
            'banco' => 'required|string|max:60',
            'titular' => 'nullable|string|max:120',
            'qr' => ($id ? 'nullable' : 'required').'|image|mimes:png,jpg,jpeg,webp|max:5120',
        ], ['qr.image' => 'El QR debe ser una imagen PNG, JPG o WEBP.', 'qr.required' => 'Elige la imagen del QR.'],
            ['banco' => 'nombre del QR', 'titular' => 'titular']);
        $datos = ['banco' => trim($d['banco']), 'titular' => trim((string) ($d['titular'] ?? '')) ?: null];
        if ($request->hasFile('qr')) {
            $datos['qr'] = DatosCobro::guardarQr($this->yo(), $request->file('qr'));
        }

        if ($id) {
            $qr = $this->mio($id);
            if (isset($datos['qr'])) {
                DatosCobro::borrarImagen($qr->qr);
            }
            $qr->update($datos);
        } else {
            SuperadminCobro::create($datos + ['superadmin_id' => $this->yo()->id, 'tipo' => 'QR', 'orden' => $this->siguienteOrden()]);
        }

        return back()->with('ok', $id ? 'QR actualizado.' : 'QR agregado: tus empresas suspendidas ya lo ven.');
    }

    public function eliminar(int $id): RedirectResponse
    {
        $cobro = $this->mio($id);
        DatosCobro::borrarImagen($cobro->qr);
        $cobro->delete();

        return back()->with('ok', $cobro->tipo === 'QR' ? 'QR quitado.' : 'Cuenta quitada.');
    }

    /** WhatsApp donde llegan los comprobantes de pago */
    public function guardarWhatsapp(Request $request): RedirectResponse
    {
        $d = $request->validate(['cobro_whatsapp' => 'nullable|string|max:20|regex:/^\+?[0-9 ]{9,16}$/'],
            ['cobro_whatsapp.regex' => 'Escribe el WhatsApp con números (ej. 987654321).']);
        $numero = preg_replace('/\D/', '', (string) ($d['cobro_whatsapp'] ?? ''));
        // Con código de país (Perú si escriben solo los 9 dígitos)
        $whatsapp = $numero === '' ? null : (strlen($numero) === 9 ? '51'.$numero : $numero);
        $this->yo()->update(['cobro_whatsapp' => $whatsapp, 'cobro_telefono' => $whatsapp ? substr($whatsapp, -9) : null]);

        return back()->with('ok', 'WhatsApp guardado.');
    }
}
