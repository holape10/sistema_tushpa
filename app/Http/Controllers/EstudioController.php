<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
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
            ->get(['ee.IdEmpresa', 'e.NomEmpresa', 'e.DirEmpresa', 'ee.activa', 'e.client_id', 'e.sire_usuario', 'e.sol_usuario']);
        $accesos = DB::table('estudio_accesos')->get()->groupBy('IdEmpresa')->map(fn ($a) => $a->pluck('IdUsuario')->map(fn ($id) => (int) $id)->all());
        // Los usuarios del estudio que no son administradores (el administrador ya ve todas)
        $usuarios = User::query()->whereNotIn('IdUsuario', DB::table('role_user')->where('role_id', 2)->select('user_IdUsuario'))
            ->where('estusu', 1)->orderBy('apeusu')->get(['IdUsuario', 'apeusu', 'email']);

        return view('empresas.estudio.index', [
            'estudio' => DB::table('empresa')->where('IdEmpresa', $estudio)->first(),
            'empresas' => $empresas, 'accesos' => $accesos, 'usuarios' => $usuarios, 'q' => $q,
            'activas' => Estudio::activas(), 'limite' => Estudio::limite(),
            'cuentas' => DB::table('concar_config')->whereIn('IdEmpresa', $empresas->pluck('IdEmpresa'))->get()->keyBy('IdEmpresa'),
        ]);
    }

    private function sinCupo(): RedirectResponse
    {
        return back()->withInput()->with('error', 'Tu plan permite hasta '.Estudio::limite().' empresas activas. Desactiva una que ya no lleves o pide a TUSHPA ampliar tu plan.');
    }

    public function guardar(Request $request): RedirectResponse
    {
        $this->administrar();
        if (! Estudio::hayCupo()) {
            return $this->sinCupo();
        }
        $d = $request->validate([
            'ruc' => ['required', 'digits:11', 'unique:empresa,IdEmpresa', fn ($campo, $valor, $falla) => ConsultaPeru::rucValido((string) $valor) || $falla('Ese RUC no es válido. Revisa los 11 dígitos.')],
            'razon_social' => 'required|string|max:200',
            'nombre_comercial' => 'nullable|string|max:150',
            'direccion' => 'nullable|string|max:250',
        ] + $this->reglasConfiguracion(), ['ruc.unique' => 'Esa empresa ya está registrada en el estudio.', 'regex' => 'Solo letras y números, sin espacios ni puntos.'],
            ['ruc' => 'RUC', 'razon_social' => 'razón social'] + self::NOMBRES);
        Estudio::crearEmpresa(['ruc' => $d['ruc'], 'razon_social' => mb_strtoupper(trim($d['razon_social'])),
            'nombre_comercial' => ! empty($d['nombre_comercial']) ? mb_strtoupper(trim($d['nombre_comercial'])) : null, 'direccion' => $d['direccion'] ?? null]);
        // Credenciales y cuentas de una vez (lo que no se escriba se pone luego con "Configurar")
        $this->aplicarConfiguracion($d['ruc'], $d);

        return redirect()->route('estudio.index')->with('success', "Empresa {$d['ruc']} registrada.".(empty($d['client_id']) ? ' Ponle sus credenciales SIRE con "Configurar" cuando las tengas.' : ''));
    }

    /** @return array<string, mixed> reglas de las credenciales y cuentas de una empresa (todas opcionales) */
    private function reglasConfiguracion(): array
    {
        $cuenta = ['nullable', 'string', 'max:12', 'regex:/^[0-9A-Za-z]+$/'];

        return [
            'client_id' => 'nullable|string|max:100', 'client_secret' => 'nullable|string|max:255',
            'sire_usuario' => ['nullable', 'string', 'max:30', 'regex:/^[A-Za-z0-9]+$/'], 'sire_clave' => 'nullable|string|max:100',
            'sol_usuario' => ['nullable', 'string', 'max:20', 'regex:/^[A-Za-z0-9]+$/'], 'sol_clave' => 'nullable|string|max:60',
            'cta_compras' => $cuenta, 'cta_por_pagar' => $cuenta, 'cta_por_cobrar' => $cuenta, 'cta_ventas' => $cuenta, 'cta_ventas_exo' => $cuenta,
        ];
    }

    private const NOMBRES = ['client_id' => 'ID del API SIRE', 'sire_usuario' => 'usuario SOL del SIRE', 'sol_usuario' => 'usuario SOL principal'];

    /** Guarda las credenciales (las claves solo si se escriben) y las cuentas por defecto de una empresa */
    private function aplicarConfiguracion(string $ruc, array $d): void
    {
        $empresa = Empresa::findOrFail($ruc);
        $empresa->client_id = trim((string) ($d['client_id'] ?? '')) ?: null;
        $empresa->sire_usuario = mb_strtoupper(trim((string) ($d['sire_usuario'] ?? ''))) ?: null;
        $empresa->sol_usuario = mb_strtoupper(trim((string) ($d['sol_usuario'] ?? ''))) ?: null;
        foreach (['client_secret', 'sire_clave', 'sol_clave'] as $secreto) {
            if (! empty($d[$secreto])) {
                $empresa->{$secreto} = $d[$secreto];
            }
        }
        $empresa->save();
        cache()->forget('sire_token_'.$ruc);

        $cuentas = array_filter(array_intersect_key($d, array_flip(['cta_compras', 'cta_por_pagar', 'cta_por_cobrar', 'cta_ventas', 'cta_ventas_exo'])), fn ($v) => trim((string) $v) !== '');
        if ($cuentas) {
            DB::table('concar_config')->updateOrInsert(['IdEmpresa' => $ruc], $cuentas + ['updated_at' => now()]);
        }
    }

    /**
     * Lo que necesita cada empresa para trabajar: sus credenciales del API SIRE, su Clave SOL principal (para traer los
     * XML de compras y, si no se pone otro, para entrar al SIRE) y las cuentas por defecto de sus asientos y del .dbf.
     */
    public function configurar(Request $request, string $ruc): RedirectResponse
    {
        $this->administrar();
        abort_unless(DB::table('estudio_empresas')->where('IdEmpresa', $ruc)->where('propia', false)->exists(), 404);
        $d = $request->validate($this->reglasConfiguracion(), ['regex' => 'Solo letras y números, sin espacios ni puntos.'], self::NOMBRES);
        $this->aplicarConfiguracion($ruc, $d);

        return back()->with('success', "Datos de la empresa {$ruc} guardados.");
    }

    /** Una empresa que el estudio ya no lleva: deja de verse en el selector (sus datos se conservan) */
    public function estado(string $ruc): RedirectResponse
    {
        $this->administrar();
        $fila = DB::table('estudio_empresas')->where('IdEmpresa', $ruc)->where('propia', false)->first();
        abort_unless($fila, 404);
        if (! $fila->activa && ! Estudio::hayCupo()) {
            return $this->sinCupo();
        }
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
        // ir: a qué pantalla del sistema entrar después (solo del SIRE o de contabilidad, nunca una dirección de afuera)
        $d = $request->validate(['ruc' => 'required|digits:11', 'ir' => ['nullable', 'string', 'max:200', 'regex:#^/(sire|contabilidad)(/[A-Za-z0-9/_-]*)?(\?[A-Za-z0-9=&_-]*)?$#']]);
        $ruc = (string) $d['ruc'];
        Estudio::cambiar(Auth::user(), $ruc);
        $nombre = DB::table('empresa')->where('IdEmpresa', $ruc)->value('NomEmpresa');

        return redirect()->to(! empty($d['ir']) ? url($d['ir']) : route('inicio'))->with('success', "Ahora trabajas en {$nombre} ({$ruc}).");
    }
}
