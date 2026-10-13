<?php

namespace App\Http\Middleware;

use App\Models\Central\Cliente;
use App\Support\SeoSitio;
use App\Support\Tenancy\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Elige la base de datos según el subdominio (va antes de la sesión, que también vive en esa base):
 *  - {RUC}.{dominio}   => bd_{RUC} del cliente (si está activo)
 *  - {subdominio}.{dominio} => el cliente con ese subdominio propio (ej. demo.{dominio})
 *  - admin.{dominio}   => base central, solo para la ruta del panel
 *  - subdominios principales (TENANCY_PRINCIPALES, ej. a.{dominio}) => la base del .env (la empresa dueña del sistema)
 *  - cualquier otro host (dominio principal, IP, local) => la base del .env, como siempre
 */
class IdentificarEmpresa
{
    /** Páginas de la web pública que responde el dominio principal (routes/sitio.php); las de cada ciudad salen de config/ciudades.php */
    private function esPaginaDelSitio(string $ruta): bool
    {
        $ciudades = implode('|', array_map('preg_quote', SeoSitio::slugsCiudades()));
        $patron = '~^(/|tutorial|contacto|ingresar|registro(/.*)?|up|sitemap\.xml'.($ciudades !== '' ? '|sistema-de-facturacion-electronica-('.$ciudades.')' : '').')$~';

        return (bool) preg_match($patron, $ruta);
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (! Tenancy::activa()) {
            return $next($request);
        }

        $dominio = strtolower(config('tenancy.dominio'));
        $sufijo = '.'.$dominio;
        $host = strtolower($request->getHost());
        // www.tushpa.app => tushpa.app
        if ($host === 'www.'.$dominio) {
            return redirect()->away(config('tenancy.esquema').'://'.$dominio.$request->getRequestUri(), 301);
        }
        // tushpa.app: solo la web pública; cualquier pantalla del sistema lleva a "Ingresar" (cada empresa entra por su subdominio)
        if ($host === $dominio) {
            return $this->esPaginaDelSitio($request->path()) ? $next($request) : redirect('/ingresar');
        }
        if (! str_ends_with($host, $sufijo)) {
            return $next($request);
        }
        $subdominio = substr($host, 0, -strlen($sufijo));

        if ($subdominio === config('tenancy.subdominio_admin')) {
            return $this->panel($request, $next);
        }
        if (in_array($subdominio, config('tenancy.principales', []), true)) {
            return $next($request);
        }

        // Los clientes entran por su RUC o por su subdominio propio; cualquier otro subdominio no existe
        abort_unless(preg_match('/^[a-z0-9][a-z0-9-]{0,39}$/', $subdominio), 404);
        $cliente = preg_match('/^\d{11}$/', $subdominio)
            ? Cliente::where('ruc', $subdominio)->first()
            : Cliente::where('subdominio', $subdominio)->first();
        abort_unless($cliente, 404);

        if (! $cliente->activo()) {
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
        $enRuta = $request->path() === $ruta || str_starts_with($request->path(), $ruta.'/');
        abort_unless($enRuta && (! $ips || in_array($request->ip(), $ips, true)), 404);

        Tenancy::conectar(Tenancy::baseCentral());
        $request->attributes->set('panel_admin', true);

        return $next($request);
    }
}
