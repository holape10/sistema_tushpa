<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cuenta las visitas a la web pública (tushpa.app) para el panel. No cuenta robots, ni pedidos de datos (JSON),
 * ni al dueño navegando con el panel abierto. Si falla, la página sale igual.
 */
class RegistrarVisita
{
    private const ROBOTS = '/bot|crawl|spider|slurp|facebookexternalhit|whatsapp|telegram|preview|curl|wget|python|headless|lighthouse|monitor|uptime/i';

    public function handle(Request $request, Closure $next): Response
    {
        $respuesta = $next($request);

        try {
            $agente = (string) $request->userAgent();
            if ($request->isMethod('GET') && ! $request->expectsJson() && $respuesta->getStatusCode() === 200
                && ! str_ends_with($request->path(), '.xml') && $agente !== '' && ! preg_match(self::ROBOTS, $agente) && ! auth('superadmin')->check()) {
                DB::connection('central')->table('web_visitas')->insert([
                    'fecha' => now(),
                    'ruta' => Str::limit('/'.ltrim($request->path(), '/'), 115, ''),
                    // Código anónimo del día: la misma persona cuenta una vez por día, sin guardar su IP
                    'visitante' => substr(hash('sha256', $request->ip().'|'.$agente.'|'.now()->toDateString().'|'.config('app.key')), 0, 16),
                    'origen' => $this->origen($request),
                    'dispositivo' => preg_match('/tablet|ipad/i', $agente) ? 'Tablet' : (preg_match('/mobi|android|iphone/i', $agente) ? 'Celular' : 'PC'),
                ]);
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return $respuesta;
    }

    /** De dónde llegó: ?utm_source=, o el sitio que lo trajo (Google, Facebook...); null = escribió la dirección */
    private function origen(Request $request): ?string
    {
        if ($utm = $request->query('utm_source')) {
            return Str::limit(mb_strtolower((string) $utm), 75, '');
        }
        $host = mb_strtolower((string) parse_url((string) $request->headers->get('referer'), PHP_URL_HOST));
        if ($host === '' || str_ends_with($host, (string) config('tenancy.dominio'))) {
            return null;
        }
        foreach (['google' => 'google', 'facebook' => 'facebook', 'fb.' => 'facebook', 'instagram' => 'instagram', 'tiktok' => 'tiktok',
            'bing' => 'bing', 'youtube' => 'youtube', 'whatsapp' => 'whatsapp', 'wa.me' => 'whatsapp', 't.co' => 'twitter / x', 'linkedin' => 'linkedin'] as $clave => $nombre) {
            if (str_contains($host, $clave)) {
                return $nombre;
            }
        }

        return Str::limit(preg_replace('/^www\./', '', $host), 75, '');
    }
}
