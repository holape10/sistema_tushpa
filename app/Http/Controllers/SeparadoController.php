<?php

namespace App\Http\Controllers;

use App\Models\MedioPago;
use App\Support\Comprobante;
use App\Support\Separados;
use App\Support\VentaDirecta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Separados (apartados): se crean desde el Punto Venta (botón SEPARAR); aquí se ven, se cobran los abonos (con el cobro
 * de Cuentas por cobrar), se avisa por WhatsApp y se entregan.
 */
class SeparadoController extends Controller
{
    private function autorizar(): void
    {
        abort_unless(Auth::user()->puedeCobrar(), 403, 'Solo Administrador o Caja manejan los separados.');
        abort_unless(Separados::disponible(), 404);
    }

    public function index(Request $request): View
    {
        $this->autorizar();
        $suc = (int) Auth::user()->id_empresa_negocio;
        $filtro = in_array($request->get('ver'), ['pendientes', 'entregados', 'todos'], true) ? $request->get('ver') : 'pendientes';

        return view('empresas.separados.index', [
            'filtro' => $filtro, 'q' => trim((string) $request->get('q')),
            'separados' => Separados::lista($suc, $filtro, (string) $request->get('q')),
            'medios' => MedioPago::where('id_empresa_negocio', $suc)->orderByDesc('predeterminado')->get(['id_med_pag', 'nom_med_pag']),
        ]);
    }

    /** Desde el Punto Venta: los mismos datos de la venta más el adelanto, el medio, la fecha para recoger y el celular */
    public function crear(Request $request): JsonResponse
    {
        $this->autorizar();
        // Un separado siempre es una venta por partes (al crédito): la forma de pago no la elige la caja
        $request->merge(['estadopago' => Comprobante::creditoDe(Auth::user())]);
        $request->validate(VentaDirecta::REGLAS + [
            'adelanto' => 'required|numeric|min:0.01|max:999999',
            'adelanto_medio' => 'nullable|integer',
            'vence' => 'required|date',
            'telefono' => ['nullable', 'regex:/^\+?[\d\s]{9,15}$/'],
        ], VentaDirecta::MENSAJES + ['telefono.regex' => 'Escribe el celular del cliente (9 dígitos).'], VentaDirecta::NOMBRES + ['vence' => 'fecha para recogerlo']);

        try {
            $id = Separados::crear(Auth::user(), $request->except(['adelanto', 'adelanto_medio', 'vence', 'telefono']), [
                'adelanto' => (float) $request->adelanto, 'medio' => $request->integer('adelanto_medio') ?: null,
                'vence' => (string) $request->vence, 'telefono' => $request->telefono,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['estado' => 'error', 'mensaje' => $e->getMessage()]);
        }
        $s = Separados::lista((int) Auth::user()->id_empresa_negocio, 'todos')->firstWhere('id', $id);

        return response()->json(['estado' => 'success', 'id' => $id, 'codigo' => $s->codigo, 'saldo' => $s->saldo,
            'venta' => VentaDirecta::respuesta((int) $s->IdCpe_cabecera), 'lista' => route('separados.index')]);
    }

    public function entregar(Request $request, int $id): RedirectResponse
    {
        $this->autorizar();
        try {
            $s = Separados::entregar(Auth::user(), $id, $request->boolean('aunque_debe'));
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "✓ {$s->codigo} entregado a {$s->clinom}.");
    }

    /** Abre WhatsApp con el estado del separado (lo pagado, lo que falta y hasta cuándo) */
    public function whatsapp(int $id): RedirectResponse
    {
        $this->autorizar();
        $suc = (int) Auth::user()->id_empresa_negocio;
        $s = Separados::lista($suc, 'todos')->firstWhere('id', $id);
        abort_unless($s, 404);
        $digitos = preg_replace('/\D/', '', (string) $s->telefono);
        $numero = strlen($digitos) === 9 ? '51'.$digitos : $digitos;
        $negocio = (string) DB::table('empresa_negocios')->where('id_empresa_negocio', $suc)->value('nombre_comercial');

        return redirect()->away('https://wa.me/'.$numero.'?text='.rawurlencode(Separados::mensaje($s, $negocio)));
    }
}
