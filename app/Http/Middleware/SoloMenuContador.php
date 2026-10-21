<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * El contador solo entra a las secciones de su menú (contabilidad, SIRE, planilla, reportes, envío a SUNAT…),
 * aunque escriba otra dirección: ventas, caja, productos o comandas no los ve. Cada sección revisa además su permiso.
 */
class SoloMenuContador
{
    /** Siempre permitido: bienvenida, salir, soporte, la campanita de SUNAT y la app instalable */
    private const LIBRES = ['inicio', 'logout', 'soporte', 'sunat/campana', 'manifest.webmanifest', 'login'];

    public function handle(Request $request, Closure $next): Response
    {
        $usuario = Auth::user();
        if (! $usuario || ! method_exists($usuario, 'esContador') || ! $usuario->esContador()) {
            return $next($request);
        }

        $ruta = trim($request->path(), '/');
        // La sección de cada opción de su menú: /contabilidad/diario → contabilidad, /reportes/sunat → reportes
        $secciones = $usuario->modulos()->pluck('mod_url')->filter()
            ->map(fn ($url) => explode('/', trim((string) $url, '/'))[0])->filter()->unique()->all();
        foreach (array_merge(self::LIBRES, $secciones) as $permitida) {
            if ($ruta === $permitida || str_starts_with($ruta, $permitida.'/')) {
                return $next($request);
            }
        }

        abort_if(! $request->isMethod('GET') || $request->expectsJson(), 403, 'Tu usuario de contador no tiene acceso a esta sección.');

        return redirect()->route('inicio')->with('error', 'Tu usuario de contador no tiene acceso a esa sección.');
    }
}
