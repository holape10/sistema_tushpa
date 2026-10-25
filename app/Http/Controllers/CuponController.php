<?php

namespace App\Http\Controllers;

use App\Support\Cupones;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Cupones de descuento (solo el administrador): el código que el cliente muestra al pagar (VERANO10, CUMPLE20...)
 * con su descuento, consumo mínimo, vigencia y cuántas veces se puede usar. Se ve cuántas veces se usó y cuánto descontó.
 */
class CuponController extends Controller
{
    private function sucursal(): int
    {
        abort_unless(Auth::user()->esAdmin(), 403, 'Solo el administrador maneja los cupones.');

        return (int) Auth::user()->id_empresa_negocio;
    }

    public function index(Request $request): View
    {
        $suc = $this->sucursal();
        $cupones = DB::table('cupones as u')->where('u.id_empresa_negocio', $suc)
            ->leftJoin('cpe_cabecera as c', fn ($j) => $j->on('c.cupon_id', '=', 'u.id')->whereNull('c.ccabaj'))
            ->groupBy('u.id')->orderByDesc('u.activo')->orderByDesc('u.id')
            ->get(['u.*', DB::raw('COALESCE(SUM(c.descuento), 0) as descontado'), DB::raw('COUNT(c.IdCpe_cabecera) as ventas')]);
        $editar = $request->integer('editar') ? $cupones->firstWhere('id', $request->integer('editar')) : null;

        return view('empresas.cupones.index', compact('cupones', 'editar'));
    }

    public function guardar(Request $request, ?int $id = null): RedirectResponse
    {
        $suc = $this->sucursal();
        $request->merge(['codigo' => Cupones::normalizar((string) $request->input('codigo'))]);
        $datos = $request->validate([
            'codigo' => ['required', 'string', 'min:3', 'max:30', 'regex:/^[A-Z0-9\-_]+$/',
                Rule::unique('cupones', 'codigo')->where('id_empresa_negocio', $suc)->ignore($id)],
            'descripcion' => 'nullable|string|max:120',
            'tipo' => 'required|in:P,S',
            'valor' => ['required', 'numeric', 'min:0.01', $request->input('tipo') === 'P' ? 'max:99' : 'max:99999'],
            'minimo' => 'nullable|numeric|min:0|max:99999',
            'desde' => 'nullable|date',
            'hasta' => 'nullable|date|after_or_equal:desde',
            'usos_max' => 'nullable|integer|min:1|max:1000000',
        ], [
            'codigo.regex' => 'El código solo lleva letras, números y guiones (sin espacios).',
            'codigo.unique' => 'Ya tienes un cupón con ese código.',
            'valor.max' => $request->input('tipo') === 'P' ? 'El descuento en % puede ser hasta 99%.' : 'El descuento es muy alto.',
        ], ['codigo' => 'código', 'valor' => 'descuento', 'minimo' => 'consumo mínimo', 'usos_max' => 'usos máximos']);
        $datos['descripcion'] = mb_strtoupper(trim((string) ($datos['descripcion'] ?? ''))) ?: null;
        $datos['minimo'] = (float) ($datos['minimo'] ?? 0);

        if ($id) {
            DB::table('cupones')->where('id', $id)->where('id_empresa_negocio', $suc)->update($datos + ['updated_at' => now()]);
        } else {
            DB::table('cupones')->insert($datos + ['id_empresa_negocio' => $suc, 'activo' => 1, 'created_at' => now(), 'updated_at' => now()]);
        }

        return redirect()->route('cupones.index')->with('success', 'Cupón '.$datos['codigo'].($id ? ' actualizado.' : ' creado. Ya se puede usar al cobrar.'));
    }

    public function estado(int $id): RedirectResponse
    {
        $suc = $this->sucursal();
        $cupon = DB::table('cupones')->where('id', $id)->where('id_empresa_negocio', $suc)->first();
        abort_unless($cupon, 404);
        DB::table('cupones')->where('id', $id)->update(['activo' => ! $cupon->activo, 'updated_at' => now()]);

        return back()->with('success', 'Cupón '.$cupon->codigo.($cupon->activo ? ' desactivado.' : ' activado.'));
    }
}
