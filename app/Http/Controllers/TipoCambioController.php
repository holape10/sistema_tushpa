<?php
namespace App\Http\Controllers;

use App\Support\ConsultaPeru;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Tipo de cambio SUNAT: consulta por fecha y moneda (apiperu.dev) e historial de lo ya consultado */
class TipoCambioController extends Controller
{
    public function index()
    {
        return view('empresas.tipo_cambio.index', [
            'historial' => DB::table('tipo_cambio')->orderByDesc('fecha')->orderBy('moneda')->limit(90)->get(),
            'conToken' => (bool) config('services.apiperu.token'),
        ]);
    }

    /** JSON para la pantalla y para el botón de Compras */
    public function consultar(Request $request)
    {
        $d = $request->validate(['fecha' => 'required|date|before_or_equal:today', 'moneda' => 'nullable|in:USD,EUR']);
        if (!config('services.apiperu.token')) {
            return response()->json(['ok' => false, 'mensaje' => 'Falta el token de apiperu.dev (APIPERU_TOKEN en el archivo .env).']);
        }
        $tc = ConsultaPeru::tipoCambio(date('Y-m-d', strtotime($d['fecha'])), $d['moneda'] ?? 'USD');
        return $tc
            ? response()->json(['ok' => true] + $tc)
            : response()->json(['ok' => false, 'mensaje' => 'No se encontró el tipo de cambio para esa fecha. Intenta de nuevo en unos segundos.']);
    }
}
