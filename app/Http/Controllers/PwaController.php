<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * App instalable (PWA): el manifiesto se arma por empresa (cada subdominio se instala con su nombre)
 * y por tipo de app: el sistema (vendedores, caja, mozos) o el portal del cliente/socio.
 */
class PwaController extends Controller
{
    public function manifest(Request $request)
    {
        $empresa = rescue(fn () => DB::table('empresa')->orderBy('IdEmpresa')->value('NomEmpresa'), null, false);
        $portal = $request->get('app') === 'socio';
        $nombre = $portal ? 'Mi cuenta · '.($empresa ?: 'TUSHPA') : ($empresa ? $empresa.' · TUSHPA' : 'TUSHPA');
        $inicio = $portal ? url('/socio') : url('/');
        // Nombre bajo el ícono: palabras completas hasta 12 letras ("HOLAPE", no "HOLAPE E.I.R")
        $corto = collect(explode(' ', trim((string) $empresa)))->reduce(fn ($c, $p) => mb_strlen(trim("$c $p")) <= 12 ? trim("$c $p") : $c, '') ?: 'TUSHPA';

        return response()->json([
            'name' => $nombre,
            'short_name' => $portal ? 'Mi cuenta' : $corto,
            'description' => $portal ? 'Tu estado de cuenta, pagos y carnet.' : 'Ventas, caja y facturación electrónica.',
            'id' => $portal ? url('/socio') : url('/'),
            'start_url' => $inicio,
            'scope' => url('/').'/',
            'display' => 'standalone',
            'orientation' => 'any',
            'background_color' => '#ffffff',
            'theme_color' => $portal ? '#065f46' : '#312e81',
            'lang' => 'es-PE',
            'icons' => [
                ['src' => asset('imagenes/192.png'), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => asset('imagenes/512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => asset('imagenes/512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
            // Accesos directos al mantener presionado el ícono
            'shortcuts' => $portal ? [] : [
                ['name' => 'Vender (PV Móvil)', 'url' => url('/pv-movil'), 'icons' => [['src' => asset('imagenes/96.png'), 'sizes' => '96x96']]],
                ['name' => 'Panel de ventas', 'url' => url('/ventas'), 'icons' => [['src' => asset('imagenes/96.png'), 'sizes' => '96x96']]],
            ],
        ], 200, ['Content-Type' => 'application/manifest+json', 'Cache-Control' => 'public, max-age=3600']);
    }
}
