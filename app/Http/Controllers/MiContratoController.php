<?php

namespace App\Http\Controllers;

use App\Support\Tenancy\Contratos;
use App\Support\Tenancy\Tenancy;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * La empresa ve su contrato de servicio desde su sistema (Editar empresa → Contrato): el PDF y los documentos que
 * subió (DNI, firma o contrato escaneado). Solo el administrador.
 */
class MiContratoController extends Controller
{
    private function contrato(): object
    {
        abort_unless(Auth::user()->esAdmin(), 403, 'Solo el administrador ve el contrato.');
        $cliente = Tenancy::cliente();
        $contrato = $cliente ? Contratos::deCliente($cliente->id) : null;
        abort_unless($contrato, 404);

        return $contrato;
    }

    public function pdf(): Response
    {
        $contrato = $this->contrato();

        return response(Contratos::pdf($contrato), 200, ['Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.Contratos::nombrePdf($contrato).'"']);
    }

    public function archivo(string $campo): BinaryFileResponse
    {
        $ruta = Contratos::archivo($this->contrato(), $campo);
        abort_unless($ruta, 404);

        return response()->file($ruta, ['Cache-Control' => 'private, no-store']);
    }
}
