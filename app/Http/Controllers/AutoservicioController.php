<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use App\Models\EmpresaNegocio;
use App\Support\Autoservicio;
use App\Support\Carta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Kiosko de autoservicio: el administrador o la caja lo abre en una tablet o pantalla táctil y la deja en la entrada.
 * El cliente arma su pedido, recibe su número y paga en caja.
 */
class AutoservicioController extends Controller
{
    public function index(): View
    {
        $usuario = Auth::user();
        abort_unless($usuario->esAdminOCaja(), 403, 'El kiosko lo abre el administrador o la caja en la tablet del local.');
        $negocio = EmpresaNegocio::findOrFail($usuario->id_empresa_negocio);
        $empresa = Empresa::find($negocio->IdEmpresa);

        return view('autoservicio.index', [
            'negocio' => $negocio,
            'nombre' => $negocio->nombre_comercial ?: ($empresa->NomEmpresa ?? 'Bienvenido'),
            'logo' => collect([$negocio->logo_suc, $empresa->LogEmpresa ?? null])->first(fn ($l) => $l && is_file(public_path($l))),
            'color' => Carta::color($negocio),
            'categorias' => Carta::categorias((int) $negocio->id_empresa_negocio),
        ]);
    }

    public function pedir(Request $request): JsonResponse
    {
        $usuario = Auth::user();
        abort_unless($usuario->esAdminOCaja(), 403);
        $d = $request->validate([
            'items' => 'required|array|min:1|max:40',
            'items.*.id' => 'required|integer',
            'items.*.cantidad' => 'required|integer|min:1|max:30',
            'items.*.presentacion' => 'nullable|integer',
            'items.*.nota' => 'nullable|string|max:80',
            'nombre' => 'required|string|min:2|max:40',
            'llevar' => 'required|boolean',
        ], ['nombre.required' => 'Escribe tu nombre para llamarte.', 'nombre.min' => 'Escribe tu nombre para llamarte.', 'items.required' => 'Agrega al menos un producto.']);

        try {
            return response()->json(Autoservicio::registrar($usuario, $d['items'], $d['nombre'], (bool) $d['llevar']));
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
