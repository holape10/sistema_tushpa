<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Central\Cliente;
use App\Support\LineaTiempo;
use App\Support\Tenancy\HerramientasSoporte;
use App\Support\Tenancy\ModoSoporte;
use App\Support\Tenancy\SaludCliente;
use App\Support\Tenancy\Tenancy;
use App\Support\Tenancy\VisibilidadPanel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * 🩺 Soporte de una empresa en una sola pantalla: su diagnóstico con semáforo y arreglos de un clic, entrar a su
 * sistema como uno de sus usuarios (modo soporte) y la línea de tiempo de su día.
 */
class DiagnosticoController extends Controller
{
    private function autorizar(Cliente $cliente): void
    {
        $yo = Auth::guard('superadmin')->user();
        abort_unless(VisibilidadPanel::puedeVer($yo, $cliente), 404);
        abort_if($cliente->estado === 'ELIMINADO', 404);
    }

    private function quien(): string
    {
        return 'Soporte: '.mb_substr((string) Auth::guard('superadmin')->user()->nombre, 0, 100);
    }

    public function index(Cliente $cliente): View
    {
        $this->autorizar($cliente);
        $salud = SaludCliente::revisar($cliente);
        $usuarios = Tenancy::en($cliente->base_datos, fn () => DB::table('users as u')
            ->leftJoin('role_user as ru', 'ru.user_IdUsuario', '=', 'u.IdUsuario')->where('u.estusu', 1)
            ->orderByRaw('ru.role_id = 2 DESC')->orderBy('u.IdUsuario')->get(['u.IdUsuario', 'u.name', 'u.apeusu', 'ru.role_id'])->unique('IdUsuario')->values());
        $visitas = ModoSoporte::disponible() ? DB::connection('central')->table('soporte_ingresos')->where('cliente_id', $cliente->id)
            ->whereNotNull('usado_at')->orderByDesc('id')->limit(5)->get() : collect();

        return view('admin.clientes.diagnostico', ['cliente' => $cliente, 'problemas' => $salud['problemas'], 'info' => $salud['info'],
            'usuarios' => $usuarios, 'visitas' => $visitas, 'modos' => ModoSoporte::MODOS]);
    }

    /** Un arreglo de un clic (cerrar un turno trabado, igualar un correlativo); queda en la bitácora de la empresa */
    public function herramienta(Request $request, Cliente $cliente): RedirectResponse
    {
        $this->autorizar($cliente);
        $d = $request->validate(['accion' => ['required', Rule::in(HerramientasSoporte::ACCIONES)], 'datos' => 'nullable|array']);
        $quien = $this->quien();
        try {
            $texto = Tenancy::en($cliente->base_datos, fn () => HerramientasSoporte::ejecutar($d['accion'], $d['datos'] ?? [], $quien));
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }
        Log::info('Panel: arreglo de soporte', ['cliente' => $cliente->ruc, 'accion' => $d['accion'], 'por' => Auth::guard('superadmin')->user()->email]);

        return back()->with('ok', '✔ '.$texto);
    }

    /** Ver como el cliente: genera el enlace de un solo uso y lleva a su sistema ya dentro, como ese usuario */
    public function soporte(Request $request, Cliente $cliente, int $usuario): RedirectResponse
    {
        $this->autorizar($cliente);
        abort_unless($cliente->activo(), 404);
        abort_unless(ModoSoporte::disponible(), 503, 'Falta actualizar el sistema (tabla soporte_ingresos).');
        $modo = $request->validate(['modo' => ['required', Rule::in(array_keys(ModoSoporte::MODOS))]])['modo'];
        $fila = Tenancy::en($cliente->base_datos, fn () => DB::table('users')->where('IdUsuario', $usuario)->where('estusu', 1)->first(['IdUsuario', 'name', 'apeusu']));
        abort_unless($fila, 404);
        $yo = Auth::guard('superadmin')->user();
        Log::info('Panel: modo soporte', ['cliente' => $cliente->ruc, 'usuario' => $usuario, 'modo' => $modo, 'por' => $yo->email]);

        return redirect()->away(ModoSoporte::enlace($cliente, $yo, $fila, $modo));
    }

    /** Qué pasó en su sistema un día, hora por hora (ventas, caja, anulaciones, cambios) y los errores de ese día */
    public function lineaTiempo(Request $request, Cliente $cliente): View
    {
        $this->autorizar($cliente);
        $fecha = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->get('fecha')) ? $request->get('fecha') : now()->toDateString();
        [$sucursales, $sucursal, $eventos, $usuarios] = Tenancy::en($cliente->base_datos, function () use ($request, $fecha) {
            $sucursales = DB::table('empresa_negocios')->where('estado', 'Activo')->orderBy('id_empresa_negocio')->pluck('nombre_comercial', 'id_empresa_negocio');
            $sucursal = $sucursales->has((int) $request->get('sucursal')) ? (int) $request->get('sucursal') : (int) $sucursales->keys()->first();
            $eventos = $sucursal ? LineaTiempo::eventos($sucursal, $fecha) : collect();
            $usuarios = DB::table('users')->whereIn('IdUsuario', $eventos->pluck('usuario')->filter()->unique())->get(['IdUsuario', 'apeusu', 'name'])
                ->mapWithKeys(fn ($u) => [(int) $u->IdUsuario => trim((string) ($u->apeusu ?: $u->name))]);

            return [$sucursales, $sucursal, $eventos, $usuarios];
        });
        $errores = DB::connection('central')->table('errores_sistema')->where('base_datos', $cliente->base_datos)
            ->where(fn ($q) => $q->whereDate('ultima_vez', $fecha)->orWhereDate('primera_vez', $fecha))->orderByDesc('ultima_vez')->limit(30)->get();

        return view('admin.clientes.linea_tiempo', compact('cliente', 'fecha', 'sucursales', 'sucursal', 'eventos', 'usuarios', 'errores') + ['tipos' => LineaTiempo::TIPOS]);
    }
}
