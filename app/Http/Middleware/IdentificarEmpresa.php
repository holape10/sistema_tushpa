<?php

namespace App\Http\Middleware;

use App\Models\Central\Cliente;
use App\Models\Central\Marca;
use App\Support\MarcaBlanca;
use App\Support\SeoSitio;
use App\Support\Tenancy\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Elige la base de datos según el subdominio (va antes de la sesión, que también vive en esa base):
 *  - {RUC}.{dominio}   => bd_{RUC} del cliente (si está activo)
 *  - {subdominio}.{dominio} => el cliente con ese subdominio propio (ej. demo.{dominio})
 *  - admin.{dominio}   => base central, solo para la ruta del panel
 *  - subdominios principales (TENANCY_PRINCIPALES, ej. a.{dominio}) => la base del .env (la empresa dueña del sistema)
 *  - {subdominio}.{dominio de una marca} (ej. 2060….wilcatsystems.pe) => una empresa de ese revendedor (bd_ws_{RUC})
 *  - cualquier otro host (dominio principal, IP, local) => la base del .env, como siempre
 * Cada empresa solo abre en el dominio de su marca: una de TUSHPA no abre en wilcatsystems.pe, ni una de WILCAT en tushpa.app.
 */
class IdentificarEmpresa
{
    /** Páginas de la web pública que responde el dominio principal (routes/sitio.php); las de cada ciudad salen de config/ciudades.php */
    private function esPaginaDelSitio(string $ruta): bool
    {
        $ciudades = implode('|', array_map('preg_quote', SeoSitio::slugsCiudades()));
        $patron = '~^(/|tutorial(/[a-z0-9-]+)?|reseller|contacto|ingresar|privacidad|terminos|pagar/\d+(/preparar|/cobrar|/estado/\d+)?|culqi/webhook|contrato/[A-Za-z0-9]{40}(/pdf)?|registro(/.*)?|crea-tu-carta(/.*)?|c/[a-z0-9-]+|ofertas/[a-z0-9-]+|promo/\d+(/vista)?|google|up|sitemap\.xml'.($ciudades !== '' ? '|sistema-de-facturacion-electronica-('.$ciudades.')' : '').')$~';

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
            // ¿El dominio de una marca (revendedor)? Si no, como siempre
            $deMarca = Tenancy::marcaDeHost($host);

            if (! $deMarca) {
                return $next($request);
            }
            // Una marca desactivada: su dominio no abre nada
            abort_unless($deMarca['marca']['activa'], 404);

            return $this->deMarca($request, $next, $deMarca['subdominio'], $deMarca['marca']);
        }
        $subdominio = substr($host, 0, -strlen($sufijo));

        if ($subdominio === config('tenancy.subdominio_admin')) {
            return $this->panel($request, $next, null);
        }
        if (in_array($subdominio, config('tenancy.principales', []), true)) {
            return $next($request);
        }

        return $this->abrirCliente($request, $next, $subdominio, null);
    }

    /**
     * El dominio de una marca: su panel (admin.wilcatsystems.pe) y sus empresas; su página de inicio llega después.
     *
     * @param  array{id: int, dominio: string, prefijo_bd: string, activa: bool}  $marca
     */
    private function deMarca(Request $request, Closure $next, string $subdominio, array $marca): Response
    {
        // Su sesión vive solo en su dirección (nunca con el dominio de cookies de tushpa.app, que el navegador rechazaría)
        config(['session.domain' => null]);
        // Todo se ve con su nombre, su logo, sus colores y su soporte
        if ($modelo = Marca::find($marca['id'])) {
            MarcaBlanca::aplicar($modelo);
        }
        if ($subdominio === 'www') {
            return redirect()->away(config('tenancy.esquema').'://'.$marca['dominio'].$request->getRequestUri(), 301);
        }
        if ($subdominio === '') {
            return $this->raizDeMarca($request, $next, $marca);
        }
        if ($subdominio === config('tenancy.subdominio_admin')) {
            return $this->panel($request, $next, $marca);
        }

        return $this->abrirCliente($request, $next, $subdominio, $marca['id']);
    }

    /**
     * wilcatsystems.pe: la portada para ingresar (el RUC o el nombre del sistema lleva a la dirección de la empresa) y el
     * contrato de servicio de sus empresas. Nada más.
     *
     * @param  array{id: int, dominio: string}  $marca
     */
    private function raizDeMarca(Request $request, Closure $next, array $marca): Response
    {
        $ruta = $request->path();
        if (preg_match('~^contrato/[A-Za-z0-9]{40}(/pdf)?$~', $ruta)) {
            $request->attributes->set('marca_raiz', $marca['id']);

            return $next($request);
        }
        if ($ruta !== '/') {
            return redirect()->away(config('tenancy.esquema').'://'.$marca['dominio'].'/');
        }
        $cuenta = mb_strtolower(trim((string) $request->query('cuenta', '')));
        // Si pegan la dirección completa (https://miempresa.wilcatsystems.pe/login) queda solo "miempresa"
        $cuenta = (string) preg_replace('~^(https?://)?([a-z0-9-]+)(\.'.preg_quote($marca['dominio'], '~').')?(/.*)?$~', '$2', $cuenta);
        $error = null;
        if ($cuenta !== '') {
            $cliente = null;
            if (preg_match('/^\d{11}$/', $cuenta)) {
                $cliente = Cliente::where('ruc', $cuenta)->where('marca_id', $marca['id'])->where('estado', '!=', 'ELIMINADO')->first();
            } elseif (preg_match('/^[a-z0-9][a-z0-9-]{0,39}$/', $cuenta)) {
                $cliente = Cliente::where('subdominio', $cuenta)->where('marca_id', $marca['id'])->where('estado', '!=', 'ELIMINADO')->first();
            }
            if ($cliente) {
                return redirect()->away($cliente->url().'/login');
            }
            $error = 'No encontramos un sistema con ese RUC o nombre. Revisa lo que escribiste.';
        }

        return response()->view('marca.ingresar', ['cuenta' => $cuenta, 'error' => $error]);
    }

    /**
     * Los clientes entran por su RUC o por su subdominio propio; cualquier otro subdominio no existe.
     *
     * @param  int|null  $marcaId  la marca del dominio por el que entró (null = tushpa)
     */
    private function abrirCliente(Request $request, Closure $next, string $subdominio, ?int $marcaId): Response
    {
        abort_unless(preg_match('/^[a-z0-9][a-z0-9-]{0,39}$/', $subdominio), 404);
        $cliente = preg_match('/^\d{11}$/', $subdominio)
            ? Cliente::where('ruc', $subdominio)->first()
            : Cliente::where('subdominio', $subdominio)->first();
        // Solo en el dominio de su marca (si la columna aún no existe mientras se actualiza, todas son de TUSHPA)
        abort_unless($cliente && (int) ($cliente->marca_id ?? 0) === (int) ($marcaId ?? 0), 404);

        if (! $cliente->activo()) {
            return response()->view('tenancy.suspendido', ['cliente' => $cliente], 503);
        }

        Tenancy::conectar($cliente->base_datos);
        app()->instance('tenancy.cliente', $cliente);

        return $next($request);
    }

    /** @param  array{id: int, dominio: string}|null  $marca  la del dominio (null = el panel de TUSHPA) */
    private function panel(Request $request, Closure $next, ?array $marca): Response
    {
        // Fuera de la ruta del panel o desde una IP no permitida, el subdominio admin "no existe"
        $ruta = config('tenancy.ruta_admin');
        $ips = config('tenancy.admin_ips');
        $enRuta = $request->path() === $ruta || str_starts_with($request->path(), $ruta.'/');
        abort_unless($enRuta && (! $ips || in_array($request->ip(), $ips, true)), 404);

        Tenancy::conectar(Tenancy::baseCentral());
        $request->attributes->set('panel_admin', true);
        // De qué marca es este panel: solo entran sus usuarios y solo ven lo suyo
        $request->attributes->set('panel_marca', $marca ? (int) $marca['id'] : null);
        // Los enlaces del panel salen con su propio dominio
        URL::defaults(['panelDominio' => $marca['dominio'] ?? strtolower((string) config('tenancy.dominio'))]);

        return $next($request);
    }
}
