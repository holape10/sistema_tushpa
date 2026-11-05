<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Central\Cliente;
use App\Support\Tenancy\Contratos;
use App\Support\Tenancy\DatosCobro;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Contrato de servicio de una empresa desde el panel: generarlo (mensual o anual con su monto), mandarle el enlace,
 * ver su DNI y el contrato firmado, y aprobarlo u observarlo. Solo las empresas que le pagan al dueño del sistema.
 */
class ContratoController extends Controller
{
    private function autorizar(Cliente $cliente): void
    {
        $yo = Auth::guard('superadmin')->user();
        abort_unless($yo->es_dueno || (int) $cliente->creado_por === (int) $yo->id, 404);
    }

    /** El contrato lo hace quien cobra: el dueño o, en las empresas de una marca, el revendedor (sale a su nombre) */
    private function haceContrato(Cliente $cliente): bool
    {
        return (bool) $cliente->marca_id || DatosCobro::cobraElDueno($cliente);
    }

    private function delCliente(Cliente $cliente, int $id): object
    {
        $contrato = DB::connection('central')->table('contratos')->where('id', $id)->where('cliente_id', $cliente->id)->first();
        abort_unless($contrato, 404);

        return $contrato;
    }

    public function index(Cliente $cliente): View
    {
        $this->autorizar($cliente);

        return view('admin.clientes.contrato', [
            'cliente' => $cliente, 'contrato' => Contratos::deCliente($cliente->id), 'cobraElDueno' => $this->haceContrato($cliente),
            'sugeridos' => collect(Contratos::PERIODOS)->keys()->mapWithKeys(fn ($p) => [$p => Contratos::montoSugerido($cliente, $p)])->all(),
            'implementacion' => Contratos::implementacionSugerida($cliente),
            'anteriores' => DB::connection('central')->table('contratos')->where('cliente_id', $cliente->id)->where('estado', 'ANULADO')->latest('id')->limit(5)->get(),
        ]);
    }

    public function generar(Request $request, Cliente $cliente): RedirectResponse
    {
        $this->autorizar($cliente);
        abort_unless($this->haceContrato($cliente), 403, 'Esta empresa le paga a su revendedor.');
        $d = $request->validate(['periodo' => 'required|in:'.implode(',', array_keys(Contratos::PERIODOS)), 'monto' => 'required|numeric|min:1|max:999999',
            'implementacion' => 'nullable|numeric|min:0|max:999999'], [], ['implementacion' => 'implementación']);
        Contratos::generar($cliente, $d['periodo'], (float) $d['monto'], Auth::guard('superadmin')->id(), (float) ($d['implementacion'] ?? 0));

        return back()->with('ok', 'Contrato generado. Copia el enlace o mándalo por WhatsApp a la empresa.');
    }

    public function revisar(Request $request, Cliente $cliente, int $id): RedirectResponse
    {
        $this->autorizar($cliente);
        $contrato = $this->delCliente($cliente, $id);
        abort_unless(in_array($contrato->estado, ['ENVIADO', 'APROBADO'], true), 422);
        $d = $request->validate(['accion' => 'required|in:aprobar,observar', 'observacion' => 'required_if:accion,observar|nullable|string|max:255'],
            ['observacion.required_if' => 'Escribe qué tiene que corregir la empresa.']);
        Contratos::revisar($contrato, $d['accion'] === 'aprobar', $d['observacion'] ?? null, (int) Auth::guard('superadmin')->id());

        return back()->with('ok', $d['accion'] === 'aprobar' ? 'Contrato aprobado.' : 'Contrato observado: la empresa verá lo que debe corregir con el mismo enlace.');
    }

    /** DNI, firma o contrato escaneado (privados: solo el panel los ve) */
    public function archivo(Cliente $cliente, int $id, string $campo): BinaryFileResponse
    {
        $this->autorizar($cliente);
        $ruta = Contratos::archivo($this->delCliente($cliente, $id), $campo);
        abort_unless($ruta, 404);

        return response()->file($ruta, ['Cache-Control' => 'private, no-store']);
    }

    public function pdf(Cliente $cliente, int $id): Response
    {
        $this->autorizar($cliente);
        $contrato = $this->delCliente($cliente, $id);

        return response(Contratos::pdf($contrato), 200, ['Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.Contratos::nombrePdf($contrato).'"']);
    }
}
