<?php

namespace App\Support\Sunat;

use App\Models\Empresa;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Pedir la propuesta del SIRE a SUNAT (genera un ticket) y revisar ese ticket hasta descargar el .zip. Lo usan la
 * pantalla SIRE de una empresa y la bandeja del estudio contable (muchas empresas a la vez).
 */
class SireSolicitudes
{
    /** El último pedido de la propuesta de un periodo (null si nunca se pidió) */
    public static function ultima(string $ruc, string $codLibro, string $periodo): ?object
    {
        return DB::table('sire_solicitudes')->where('IdEmpresa', $ruc)->where('libro', $codLibro)->where('periodo', $periodo)->orderByDesc('id')->first();
    }

    /**
     * Pide la propuesta a SUNAT y anota el ticket.
     *
     * @return array{estado: string, mensaje?: string, ticket?: string, id?: int}
     */
    public static function pedir(Empresa $empresa, string $codLibro, string $periodo): array
    {
        try {
            $ticket = (new Sire($empresa))->solicitarPropuesta($codLibro, $periodo);
        } catch (\RuntimeException $e) {
            return ['estado' => 'error', 'mensaje' => $e->getMessage()];
        } catch (\Throwable $e) {
            report($e);

            return ['estado' => 'error', 'mensaje' => 'No se pudo conectar con SUNAT. Intenta en unos minutos.'];
        }

        $id = DB::table('sire_solicitudes')->insertGetId([
            'IdEmpresa' => $empresa->IdEmpresa, 'libro' => $codLibro, 'periodo' => $periodo,
            'num_ticket' => $ticket, 'cod_estado' => '01', 'des_estado' => Sire::ESTADOS['01'],
            'IdUsuario' => Auth::id(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        return ['estado' => 'ok', 'ticket' => $ticket, 'id' => $id];
    }

    /**
     * Revisa el ticket una vez; si SUNAT terminó, descarga y guarda el .zip.
     *
     * @return array{estado: string, descripcion?: string, mensaje?: string, filas?: int} listo | proceso | error
     */
    public static function revisar(Empresa $empresa, object $s): array
    {
        if ($s->archivo) {
            return ['estado' => 'listo', 'filas' => (int) $s->filas];
        }
        try {
            $sire = new Sire($empresa);
            $registro = $sire->estadoTicket($s->libro, $s->periodo, $s->num_ticket);
            if (! $registro) {
                return ['estado' => 'proceso', 'descripcion' => 'SUNAT aún está registrando el pedido…'];
            }

            $cod = str_pad((string) ($registro['codEstadoProceso'] ?? ''), 2, '0', STR_PAD_LEFT);
            $des = $registro['desEstadoProceso'] ?? (Sire::ESTADOS[$cod] ?? 'En proceso');
            DB::table('sire_solicitudes')->where('id', $s->id)->update(['cod_estado' => $cod, 'des_estado' => mb_substr($des, 0, 60), 'updated_at' => now()]);

            if ($cod === '03') {
                return ['estado' => 'error', 'mensaje' => 'SUNAT procesó el pedido con errores: '.$des];
            }
            if (! in_array($cod, ['04', '06'], true)) {
                return ['estado' => 'proceso', 'descripcion' => $des];
            }

            $zip = $sire->descargarArchivo($s->libro, $registro);
            $ruta = "sire/{$empresa->IdEmpresa}/{$s->libro}/{$s->periodo}-{$s->num_ticket}.zip";
            Storage::put($ruta, $zip);
            $filas = count(Sire::leerZip(Storage::path($ruta))['filas']);
            DB::table('sire_solicitudes')->where('id', $s->id)->update(['archivo' => $ruta, 'filas' => $filas, 'updated_at' => now()]);

            return ['estado' => 'listo', 'filas' => $filas];
        } catch (\RuntimeException $e) {
            DB::table('sire_solicitudes')->where('id', $s->id)->update(['mensaje' => mb_substr($e->getMessage(), 0, 250), 'updated_at' => now()]);

            return ['estado' => 'error', 'mensaje' => $e->getMessage()];
        } catch (\Throwable $e) {
            report($e);

            return ['estado' => 'error', 'mensaje' => 'No se pudo consultar a SUNAT. Intenta en unos minutos.'];
        }
    }
}
