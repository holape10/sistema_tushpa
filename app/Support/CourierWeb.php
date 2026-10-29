<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Lo que el cliente hace desde la web del courier, sin cuenta: pre-registrar su envío (en la agencia solo dicta el código
 * de 6 letras, se pesa y se cobra) y presentar un reclamo sobre su envío.
 */
class CourierWeb
{
    /** El pre-registro sirve estos días */
    public const DIAS_PRERREGISTRO = 15;

    public const TIPOS_RECLAMO = ['DANO' => 'Llegó dañado', 'PERDIDA' => 'Se perdió o falta algo', 'DEMORA' => 'Demora en la entrega',
        'ATENCION' => 'Mala atención', 'OTRO' => 'Otro'];

    public const ESTADOS_RECLAMO = ['ABIERTO' => 'Recibido', 'EN_PROCESO' => 'En revisión', 'RESUELTO' => 'Resuelto', 'RECHAZADO' => 'No procede'];

    private const ALFABETO = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    /** RUC de la empresa de esta base (la web pública no tiene sesión) */
    public static function ruc(): string
    {
        return (string) DB::table('empresa')->value('IdEmpresa');
    }

    /**
     * @param  array<string, mixed>  $d  origen_id, destino_id, entrega, direccion_entrega, rem_doc, rem_nombre, rem_telefono, des_doc, des_nombre, des_telefono,
     *                                   contenido, valor_declarado, paquetes[]
     */
    public static function prerregistrar(array $d, ?string $ip): string
    {
        do {
            $codigo = collect(range(1, 6))->map(fn () => self::ALFABETO[random_int(0, strlen(self::ALFABETO) - 1)])->implode('');
        } while (DB::table('cou_prerregistros')->where('codigo', $codigo)->exists());
        DB::table('cou_prerregistros')->insert(['codigo' => $codigo, 'datos' => json_encode($d, JSON_UNESCAPED_UNICODE), 'estado' => 'PENDIENTE', 'ip' => $ip, 'creado' => now()]);

        return $codigo;
    }

    /** Datos de un pre-registro vigente (para llenar el formulario de la agencia) */
    public static function prerregistro(string $codigo): ?array
    {
        $p = DB::table('cou_prerregistros')->where('codigo', strtoupper(trim($codigo)))->where('estado', 'PENDIENTE')
            ->where('creado', '>=', now()->subDays(self::DIAS_PRERREGISTRO))->first();

        return $p ? (array) json_decode($p->datos, true) : null;
    }

    /** El cliente demuestra que el envío es suyo: el DNI/RUC o el celular del remitente o del destinatario */
    public static function esDelCliente(object $envio, string $dato): bool
    {
        $dato = preg_replace('/\D/', '', $dato);
        if (strlen($dato) < 8) {
            return false;
        }
        foreach ([$envio->rem_doc, $envio->des_doc, $envio->rem_telefono, $envio->des_telefono] as $valor) {
            $valor = preg_replace('/\D/', '', (string) $valor);
            if ($valor !== '' && $valor !== '00000000' && (str_ends_with($valor, $dato) || str_ends_with($dato, $valor))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $d  tipo, detalle, pedido, nombre, documento, telefono, correo
     * @return int número de reclamo
     */
    public static function reclamar(object $envio, array $d, string $origen = 'WEB', ?int $usuario = null): int
    {
        return DB::transaction(function () use ($envio, $d, $origen, $usuario) {
            $ruc = self::ruc();
            $numero = (int) DB::table('cou_reclamos')->where('IdEmpresa', $ruc)->lockForUpdate()->max('numero') + 1;
            DB::table('cou_reclamos')->insert([
                'numero' => $numero, 'env_id' => $envio->env_id, 'tipo' => array_key_exists($d['tipo'] ?? '', self::TIPOS_RECLAMO) ? $d['tipo'] : 'OTRO',
                'detalle' => trim((string) $d['detalle']), 'pedido' => $d['pedido'] ?? null, 'nombre' => mb_strtoupper(trim((string) $d['nombre'])),
                'documento' => $d['documento'] ?? null, 'telefono' => $d['telefono'] ?? null, 'correo' => $d['correo'] ?? null,
                'origen' => $origen, 'estado' => 'ABIERTO', 'IdUsuario' => $usuario, 'IdEmpresa' => $ruc, 'creado' => now(),
            ]);
            Courier::evento($envio->env_id, $envio->estado, "Reclamo N° {$numero} registrado");

            return $numero;
        });
    }
}
