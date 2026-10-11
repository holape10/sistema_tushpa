<?php

namespace App\Support\Tenancy;

use App\Models\Central\Cliente;
use App\Models\Central\Plan;
use App\Support\Rubros;

/**
 * Registro desde la web (tushpa.app/registro): crea la empresa con el Provisionador (igual que el panel),
 * la deja en prueba gratis por config('sitio.dias_prueba') días y pide su https al servidor.
 * La facturación arranca en modo beta de SUNAT (no emite comprobantes reales hasta que paga y se activa).
 */
class RegistroWeb
{
    /**
     * @param  array{ruc: string, razon_social: string, direccion: string, ubigeo: ?string, nombre_comercial: ?string,
     *     rubro: string, subdominio: ?string, plan_id: int, contacto_nombre: string, contacto_telefono: string,
     *     contacto_correo: string, password: string}  $d
     */
    public static function crear(array $d, string $ip): Cliente
    {
        $hasta = now()->addDays(config('sitio.dias_prueba'))->toDateString();
        $plan = Plan::find($d['plan_id']);

        $cliente = Provisionador::crear([
            'ruc' => $d['ruc'],
            'razon_social' => $d['razon_social'],
            'nombre_comercial' => $d['nombre_comercial'] ?? null,
            'direccion' => $d['direccion'],
            'ubigeo' => $d['ubigeo'] ?? null,
            'subdominio' => $d['subdominio'] ?? null,
            'rubro' => $d['rubro'],
            'modulos' => Rubros::urls($d['rubro']),
            'plan_id' => $plan?->id,
            'plan' => $plan?->nombre,
            'usuario' => $d['ruc'],
            'password' => $d['password'],
            'produccion' => null,   // SUNAT beta durante la prueba
            'vence_el' => $hasta,
            'contacto_nombre' => $d['contacto_nombre'],
            'contacto_telefono' => $d['contacto_telefono'],
            'contacto_correo' => $d['contacto_correo'],
            'notas' => 'Registrado desde la web el '.now()->format('d/m/Y H:i').'.',
        ]);
        $cliente->update(['origen' => 'web', 'prueba_hasta' => $hasta, 'registro_ip' => $ip]);

        // El servidor (root) atiende esta marca y saca el certificado https del subdominio nuevo
        @mkdir(storage_path('app'), 0775, true);
        @file_put_contents(storage_path('app/ssl-solicitud'), $cliente->host().' '.now()->toDateTimeString()."\n", FILE_APPEND);

        return $cliente;
    }
}
