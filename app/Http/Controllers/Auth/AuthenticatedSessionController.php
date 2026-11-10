<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Controllers\SucursalController;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\Empresa;
use App\Models\EmpresaNegocio;
use App\Support\DemoDatos;
use App\Support\Estudio;
use App\Support\Rubros;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(Request $request): View|RedirectResponse
    {
        if (Empresa::count() === 0) {
            return redirect()->route('empresa.config');
        }

        // Celulares y tablets van al login de mozos, salvo que elijan expresamente el de escritorio
        // (la preferencia se guarda en una cookie para que sobreviva al cerrar sesión)
        if ($request->boolean('escritorio')) {
            Cookie::queue('login_escritorio', '1', 60 * 24 * 30);
        } elseif (LoginMovilController::esMovil($request) && ! $request->cookie('login_escritorio') && ! Rubros::eligeEnLogin() && Rubros::usaLoginMovil()) {
            return redirect()->route('login.movil');
        }

        // Empresa sin tipo de negocio (la demo): primero se elige el rubro y al entrar se ve solo su menú
        $rubros = Rubros::eligeEnLogin() ? collect(Rubros::RUBROS)->except('COMPLETO') : null;
        $sucursales = $rubros ? collect() : self::sucursalesDelLogin();

        return view('auth.login', ['rubros' => $rubros, 'sucursales' => $sucursales,
            // La de la última vez en este equipo (si sigue activa) o la principal
            'sucursalElegida' => (int) old('sucursal', $sucursales->has(LoginMovilController::sucursalRecordada($request))
                ? LoginMovilController::sucursalRecordada($request) : $sucursales->keys()->first())]);
    }

    /**
     * Sucursales activas para elegir dónde trabajar al entrar: solo si la empresa tiene más de una (el estudio contable y
     * la demo eligen de otra forma). id => nombre.
     *
     * @return Collection<int, string>
     */
    public static function sucursalesDelLogin(): Collection
    {
        if (Estudio::activo() || Empresa::count() !== 1) {
            return collect();
        }
        $sucursales = EmpresaNegocio::where('estado', 'Activo')->orderBy('id_empresa_negocio')->get(['id_empresa_negocio', 'nombre_comercial', 'tipo_negocio'])
            ->mapWithKeys(fn ($s) => [(int) $s->id_empresa_negocio => mb_strtoupper(trim((string) ($s->nombre_comercial ?: $s->tipo_negocio)) ?: 'SUCURSAL '.$s->id_empresa_negocio)]);

        return $sucursales->count() > 1 ? $sucursales : collect();
    }

    /**
     * Con varias sucursales: cada usuario entra solo a la suya; el administrador puede entrar a cualquiera y queda
     * trabajando en la que eligió (como al cambiar de sucursal).
     */
    private function entrarALaSucursal(LoginRequest $request): void
    {
        $sucursales = self::sucursalesDelLogin();
        $elegida = (int) $request->input('sucursal');
        if ($sucursales->isEmpty() || ! $elegida) {
            return;
        }
        $user = $request->user();
        if (! $sucursales->has($elegida)) {
            Auth::guard('web')->logout();
            throw ValidationException::withMessages(['email' => 'Elige una sucursal de la lista.']);
        }
        if ((int) $user->id_empresa_negocio === $elegida) {
            return;
        }
        if ($user->esAdmin()) {
            SucursalController::moverUsuario($user, $elegida);

            return;
        }
        $suya = $sucursales[(int) $user->id_empresa_negocio] ?? null;
        Auth::guard('web')->logout();
        throw ValidationException::withMessages(['email' => "Tu usuario no es de {$sucursales[$elegida]}."
            .($suya ? " Elige {$suya} para entrar." : ' Pide al administrador que te asigne esta sucursal.')]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();
        $this->entrarALaSucursal($request);

        $request->session()->regenerate();

        $rubro = (string) $request->input('rubro');
        if (Rubros::eligeEnLogin() && isset(Rubros::RUBROS[$rubro])) {
            $request->session()->put(Rubros::SESION, $rubro);
            // Demo: se entra a la sucursal de ese rubro, que solo tiene sus productos y datos
            if ($delRubro = DemoDatos::usuarioDelRubro($request->user(), $rubro)) {
                Auth::login($delRubro, $request->boolean('remember'));
            }
        }

        // Este equipo queda asociado a la sucursal: el login móvil mostrará a sus mozos
        LoginMovilController::recordarSucursal($request->user());

        // Estudio contable: con varias empresas, primero se elige con cuál trabajar
        if (Estudio::activo() && Estudio::empresas($request->user())->count() > 1) {
            $request->session()->forget('url.intended');

            return redirect()->route('estudio.elegir');
        }

        return redirect()->intended(route($request->user()->rutaInicio(), absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
