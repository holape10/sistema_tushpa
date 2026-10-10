<?php

namespace App\Http\Controllers;

use App\Support\ConsultaCpe;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Página pública {subdominio}/cpe: el cliente de la empresa valida su comprobante y descarga PDF, XML y CDR.
 */
class ConsultaCpeController extends Controller
{
    public function index(): View
    {
        return view('cpe.consulta', $this->empresa() + ['tipos' => ConsultaCpe::TIPOS, 'resultado' => null, 'datos' => []]);
    }

    public function consultar(Request $request): View
    {
        $d = $request->validate([
            'tipo' => 'required|in:'.implode(',', array_keys(ConsultaCpe::TIPOS)),
            'serie' => ['required', 'regex:/^[A-Za-z0-9]{4}$/'],
            'numero' => 'required|integer|min:1|max:99999999',
            'fecha' => 'required|date|before_or_equal:today',
            'total' => 'required|numeric|min:0|max:99999999',
        ], ['serie.regex' => 'La serie tiene 4 caracteres (ej. B001).'],
            ['tipo' => 'tipo de comprobante', 'serie' => 'serie', 'numero' => 'número', 'fecha' => 'fecha de emisión', 'total' => 'monto total']);

        return view('cpe.consulta', $this->empresa() + ['tipos' => ConsultaCpe::TIPOS, 'resultado' => ConsultaCpe::consultar($d), 'datos' => $d]);
    }

    /** XML o CDR con enlace firmado que entregó la consulta */
    public function archivo(int $id, string $tipo): BinaryFileResponse
    {
        $cpe = DB::table('cpe_cabecera')->where('IdCpe_cabecera', $id)->first(['IdEmpresa', 'tdocod', 'serdoc', 'numdoc']);
        abort_unless($cpe, 404);
        $ruta = ConsultaCpe::archivo($cpe, $tipo);
        abort_unless(is_file($ruta), 404, 'El archivo aún no está disponible.');

        return response()->download($ruta, basename($ruta));
    }

    /**
     * @return array{empresa: ?object, logo: ?string}
     */
    private function empresa(): array
    {
        $empresa = DB::table('empresa')->first(['IdEmpresa', 'NomEmpresa', 'DirEmpresa', 'LogEmpresa', 'TelEmpresa']);
        $logo = $empresa && $empresa->LogEmpresa && is_file(public_path($empresa->LogEmpresa)) ? asset($empresa->LogEmpresa) : null;

        return ['empresa' => $empresa, 'logo' => $logo];
    }
}
