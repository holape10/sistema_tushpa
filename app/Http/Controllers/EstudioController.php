<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\ConsultaPeru;
use App\Support\Estudio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Estudio contable: registrar las empresas de los clientes, decidir qué usuario trabaja cada una y cambiar de empresa.
 * Solo el administrador del estudio administra; cualquiera de sus usuarios cambia entre las empresas que tiene.
 */
class EstudioController extends Controller
{
    private function administrar(): string
    {
        abort_unless(Estudio::activo(), 404);
        abort_unless(Auth::user()->esAdmin(), 403, 'Solo el administrador del estudio registra empresas y da accesos.');

        return Estudio::ruc();
    }

    public function index(Request $request): View
    {
        $estudio = $this->administrar();
        $q = trim((string) $request->get('q'));
        $empresas = DB::table('estudio_empresas as ee')->join('empresa as e', 'e.IdEmpresa', '=', 'ee.IdEmpresa')
            ->where('ee.propia', false)
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('e.NomEmpresa', 'like', "%{$q}%")->orWhere('ee.IdEmpresa', 'like', "{$q}%")))
            ->orderByDesc('ee.activa')->orderBy('e.NomEmpresa')
            ->get(['ee.IdEmpresa', 'e.NomEmpresa', 'e.DirEmpresa', 'ee.activa', 'e.client_id', 'e.sire_usuario']);
        $accesos = DB::table('estudio_accesos')->get()->groupBy('IdEmpresa')->map(fn ($a) => $a->pluck('IdUsuario')->map(fn ($id) => (int) $id)->all());
        // Los usuarios del estudio que no son administradores (el administrador ya ve todas)
        $usuarios = User::query()->whereNotIn('IdUsuario', DB::table('role_user')->where('role_id', 2)->select('user_IdUsuario'))
            ->where('estusu', 1)->orderBy('apeusu')->get(['IdUsuario', 'apeusu', 'email']);

        return view('empresas.estudio.index', [
            'estudio' => DB::table('empresa')->where('IdEmpresa', $estudio)->first(),
            'empresas' => $empresas, 'accesos' => $accesos, 'usuarios' => $usuarios, 'q' => $q,
            'activas' => DB::table('estudio_empresas')->where('propia', false)->where('activa', true)->count(),
        ]);
    }

    public function guardar(Request $request): RedirectResponse
    {
        $this->administrar();
        $d = $request->validate([
            'ruc' => ['required', 'digits:11', 'unique:empresa,IdEmpresa', fn ($campo, $valor, $falla) => ConsultaPeru::rucValido((string) $valor) || $falla('Ese RUC no es válido. Revisa los 11 dígitos.')],
            'razon_social' => 'required|string|max:200',
            'nombre_comercial' => 'nullable|string|max:150',
            'direccion' => 'nullable|string|max:250',
        ], ['ruc.unique' => 'Esa empresa ya está registrada en el estudio.'], ['ruc' => 'RUC', 'razon_social' => 'razón social']);
        Estudio::crearEmpresa(['ruc' => $d['ruc'], 'razon_social' => mb_strtoupper(trim($d['razon_social'])),
            'nombre_comercial' => ! empty($d['nombre_comercial']) ? mb_strtoupper(trim($d['nombre_comercial'])) : null, 'direccion' => $d['direccion'] ?? null]);

        return redirect()->route('estudio.index')->with('success', "Empresa {$d['ruc']} registrada. Entra a ella con \"Trabajar aquí\" para poner sus credenciales SIRE y su Clave SOL.");
    }

    /** Una empresa que el estudio ya no lleva: deja de verse en el selector (sus datos se conservan) */
    public function estado(string $ruc): RedirectResponse
    {
        $this->administrar();
        $fila = DB::table('estudio_empresas')->where('IdEmpresa', $ruc)->where('propia', false)->first();
        abort_unless($fila, 404);
        DB::table('estudio_empresas')->where('id', $fila->id)->update(['activa' => ! $fila->activa, 'updated_at' => now()]);

        return back()->with('success', $fila->activa ? "Empresa {$ruc} desactivada: ya nadie la ve en el selector. Sus datos se conservan." : "Empresa {$ruc} activada de nuevo.");
    }

    public function accesos(Request $request, string $ruc): RedirectResponse
    {
        $this->administrar();
        abort_unless(DB::table('estudio_empresas')->where('IdEmpresa', $ruc)->where('propia', false)->exists(), 404);
        $d = $request->validate(['usuarios' => 'nullable|array', 'usuarios.*' => 'integer|exists:users,IdUsuario']);
        Estudio::guardarAccesos($ruc, $d['usuarios'] ?? []);

        return back()->with('success', 'Accesos de la empresa '.$ruc.' guardados.');
    }

    /** Pasa a trabajar en otra empresa (el selector de arriba) */
    public function cambiar(Request $request): RedirectResponse
    {
        abort_unless(Estudio::activo(), 404);
        $ruc = (string) $request->validate(['ruc' => 'required|digits:11'])['ruc'];
        Estudio::cambiar(Auth::user(), $ruc);
        $nombre = DB::table('empresa')->where('IdEmpresa', $ruc)->value('NomEmpresa');

        return redirect()->route('inicio')->with('success', "Ahora trabajas en {$nombre} ({$ruc}).");
    }
}
