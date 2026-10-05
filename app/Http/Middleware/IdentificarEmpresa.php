<?php
namespace App\Http\Middleware;

use App\Models\Central\Cliente;
use App\Support\Tenancy\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Elige la base de datos según el subdominio (va antes de la sesión, que también vive en esa base):
 *  - {RUC}.{dominio}   => bd_{RUC} del cliente (si está activo)
 *  - admin.{dominio}   => base central, solo para la ruta del panel
 *  - subdominios principales (TENANCY_PRINCIPALES, ej. a.{dominio}) => la base del .env (la empresa dueña del sistema)
 *  - cualquier otro host (dominio principal, IP, local) => la base del .env, como siempre
 */
class IdentificarEmpresa
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!Tenancy::activa()) {
            return $next($request);
        }

        $sufijo = '.' . strtolower(config('tenancy.dominio'));
        $host = strtolower($request->getHost());
        if (!str_ends_with($host, $sufijo)) {
            return $next($request);
        }
        $subdominio = substr($host, 0, -strlen($sufijo));

        if ($subdominio === config('tenancy.subdominio_admin')) {
            return $this->panel($request, $next);
        }
        if (in_array($subdominio, config('tenancy.principales', []), true)) {
            return $next($request);
        }

        // Los clientes entran solo por su RUC; cualquier otro subdominio no existe
        abort_unless(preg_match('/^\d{11}$/', $subdominio), 404);

        $cliente = Cliente::where('ruc', $subdominio)->first();
        abort_unless($cliente, 404);

        if (!$cliente->activo()) {
            return response()->view('tenancy.suspendido', ['cliente' => $cliente], 503);
        }

        Tenancy::conectar($cliente->base_datos);
        app()->instance('tenancy.cliente', $cliente);

        return $next($request);
    }

    private function panel(Request $request, Closure $next): Response
    {
        // Fuera de la ruta del panel o desde una IP no permitida, el subdominio admin "no existe"
        $ruta = config('tenancy.ruta_admin');
        $ips = config('tenancy.admin_ips');
        $enRuta = $request->path() === $ruta || str_starts_with($request->path(), $ruta . '/');
        abort_unless($enRuta && (!$ips || in_array($request->ip(), $ips, true)), 404);

        Tenancy::conectar(Tenancy::baseCentral());
        $request->attributes->set('panel_admin', true);

        return $next($request);
    }
}
