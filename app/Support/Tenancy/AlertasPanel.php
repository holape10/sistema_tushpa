<?php

namespace App\Support\Tenancy;

use App\Models\Central\Cliente;
use App\Models\Central\Marca;
use App\Models\Central\Superadmin;
use App\Support\Bitacora;
use App\Support\MonitorErrores;
use App\Support\TelegramCitas;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cambios delicados de una empresa que se avisan en el panel (lista de clientes con ⚠️) para revisarlos pronto:
 * editar la empresa o una sucursal (correlativos, certificado, envío a SUNAT…), importar productos (precios) y crear,
 * editar o eliminar usuarios. Se guardan en la base central al registrarse en la bitácora de la empresa; quedan hasta
 * que alguien del panel las descarta.
 */
class AlertasPanel
{
    /** Acciones de la bitácora que se avisan en el panel */
    public const VIGILADAS = [
        'config.empresa' => 'Editó la empresa',
        'config.sucursal' => 'Editó una sucursal',
        'producto.importar' => 'Importó productos (precios)',
        'usuario.crear' => 'Creó un usuario',
        'usuario.editar' => 'Editó un usuario',
        'usuario.eliminar' => 'Eliminó un usuario',
        // Seguridad de equipos: una acción delicada frenada por venir de un equipo no autorizado, o un "no fui yo"
        'sesion.bloqueo_accion' => 'Acción delicada frenada (equipo no autorizado)',
        'sesion.no_fui_yo' => 'Bloquearon un equipo («no fui yo»)',
        'sesion.autorizo_sin_confianza' => 'Autorizó un equipo sin tener uno de confianza',
    ];

    /** Lo anota en el panel si es una acción vigilada y la empresa es un cliente del panel (nunca rompe lo que se estaba haciendo) */
    public static function avisar(string $accion, string $descripcion, ?string $usuario, ?string $ip, ?int $bitacoraId, ?array $cambios = null): void
    {
        if (! isset(self::VIGILADAS[$accion]) || ! Tenancy::activa()) {
            return;
        }
        try {
            $cliente = Tenancy::cliente() ?? Cliente::where('base_datos', Tenancy::baseActual())->first();
            if (! $cliente || ! Schema::connection('central')->hasTable('panel_alertas')) {
                return;
            }
            DB::connection('central')->table('panel_alertas')->insert([
                'cliente_id' => $cliente->id, 'bitacora_id' => $bitacoraId, 'fecha' => now(), 'accion' => $accion,
                'descripcion' => mb_substr($descripcion, 0, 255), 'usuario' => $usuario !== null ? mb_substr($usuario, 0, 120) : null,
                'ip' => $ip !== null ? mb_substr($ip, 0, 45) : null,
            ]);
            // También al Telegram del dueño (el mismo bot del monitor de errores), después de responder: no demora a la empresa
            $texto = self::textoTelegram($cliente, $accion, $descripcion, $usuario, $ip, $cambios);
            dispatch(fn () => MonitorErrores::avisar($texto))->afterResponse();
            // Y a los Telegram vinculados en la Agenda: los del dueño y los del usuario del panel que tiene la empresa
            // (cada usuario de una marca ve solo las suyas). El chat del .env no se repite.
            foreach (self::destinatarios($cliente) as [$chat, $dominio]) {
                $suyo = self::textoTelegram($cliente, $accion, $descripcion, $usuario, $ip, $cambios, $dominio);
                dispatch(fn () => TelegramCitas::enviar($chat, $suyo))->afterResponse();
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** El mensaje de Telegram: qué empresa, quién, desde qué IP y qué cambió exactamente (las claves solo dicen "cambió") */
    /**
     * Los Telegram vinculados que reciben las alertas de esta empresa: los del dueño (todas) y los del usuario del panel
     * que la creó, cada uno con el dominio de su panel.
     *
     * @return list<array{0: string, 1: string}> [chat, dominio del panel]
     */
    public static function destinatarios(Cliente $cliente): array
    {
        if (! TelegramCitas::configurado()) {
            return [];
        }
        $usuarios = Superadmin::where('activo', true)
            ->where(fn ($q) => $q->where('es_dueno', true)->when($cliente->creado_por, fn ($w) => $w->orWhere('id', $cliente->creado_por)))->get();
        $yaAvisado = (string) config('services.telegram.chat_id');
        $destinos = [];
        foreach ($usuarios as $u) {
            foreach (TelegramCitas::chatsPanel((int) $u->id) as $chat) {
                if ($chat !== $yaAvisado && ! isset($destinos[$chat])) {
                    $destinos[$chat] = [$chat, self::dominioPanel($u)];
                }
            }
        }

        return array_values($destinos);
    }

    /** Dominio del panel de ese usuario: el de su marca o el de TUSHPA */
    private static function dominioPanel(?Superadmin $usuario): string
    {
        $marca = $usuario?->marca_id ? Marca::find($usuario->marca_id) : null;

        return strtolower((string) ($marca?->dominio ?: config('tenancy.dominio')));
    }

    public static function textoTelegram(Cliente $cliente, string $accion, string $descripcion, ?string $usuario, ?string $ip, ?array $cambios, ?string $panelDominio = null): string
    {
        $lineas = [];
        foreach (array_slice(array_values($cambios ?? []), 0, 12) as $c) {
            // Una clave nunca viaja a Telegram: solo se dice que cambió
            $lineas[] = '• '.(($c['despues'] ?? null) === '(cambió)' ? ($c['etiqueta'] ?? '').': cambió' : Bitacora::resumen([$c], 1));
        }
        if (count($cambios ?? []) > 12) {
            $lineas[] = '… y '.(count($cambios) - 12).' cambio(s) más';
        }
        try {
            $enlace = route('admin.clientes.alertas', ['panelDominio' => $panelDominio ?? self::dominioPanel(null), 'cliente' => $cliente->id]);
        } catch (\Throwable) {
            $enlace = null;
        }

        return '⚠️ '.(self::VIGILADAS[$accion] ?? $accion)."\n\n"
            .'🏢 '.($cliente->razon_social ?: $cliente->nombre_comercial).' · RUC '.$cliente->ruc."\n"
            .'👤 '.($usuario ?: '—').($ip ? ' · IP '.$ip : '')."\n"
            .'🕒 '.now()->format('d/m/Y H:i')."\n\n"
            .'📝 '.$descripcion
            .($lineas ? "\n\nCambios:\n".implode("\n", $lineas) : '')
            .($enlace ? "\n\nRevísalo: ".$enlace : '');
    }

    /**
     * Alertas sin descartar de cada cliente.
     *
     * @param  iterable<int>  $clienteIds
     * @return array<int, int> [cliente_id => cantidad]
     */
    public static function pendientes(iterable $clienteIds): array
    {
        if (! Schema::connection('central')->hasTable('panel_alertas')) {
            return [];
        }

        return DB::connection('central')->table('panel_alertas')->whereIn('cliente_id', collect($clienteIds)->all())->whereNull('descartada_el')
            ->groupBy('cliente_id')->selectRaw('cliente_id, COUNT(*) AS n')->pluck('n', 'cliente_id')->map(fn ($n) => (int) $n)->all();
    }

    /**
     * Qué cambió exactamente en cada alerta (campo, antes y después), leído de la bitácora de la empresa.
     *
     * @param  Collection<int, int|null>  $ids
     * @return array<int, list<array<string, mixed>>> [bitacora_id => cambios]
     */
    public static function detalles(Cliente $cliente, $ids): array
    {
        $ids = $ids->filter()->map(fn ($id) => (int) $id)->unique()->values();
        if ($ids->isEmpty() || ! $cliente->base_datos) {
            return [];
        }
        try {
            return Tenancy::en($cliente->base_datos, fn () => Schema::hasTable('bitacora')
                ? DB::table('bitacora')->whereIn('id', $ids)->get(['id', 'cambios'])
                    ->mapWithKeys(fn ($b) => [(int) $b->id => array_values(json_decode((string) $b->cambios, true) ?: [])])->all()
                : []);
        } catch (\Throwable $e) {
            report($e);

            return [];
        }
    }

    /**
     * El equipo de cada alerta de "acción frenada" (para autorizarlo con un clic desde el panel): el que quedó anotado en la
     * bitácora o, en las alertas antiguas, el equipo que tenía esa IP. También cuántos equipos están por revisar.
     *
     * @param  Collection<int, object>  $alertas
     * @return array{equipos: array<int, object>, porRevisar: int}
     */
    public static function equipos(Cliente $cliente, $alertas): array
    {
        $vacio = ['equipos' => [], 'porRevisar' => 0];
        if (! $cliente->base_datos) {
            return $vacio;
        }
        try {
            return Tenancy::en($cliente->base_datos, function () use ($alertas, $vacio) {
                if (! Schema::hasTable('dispositivos')) {
                    return $vacio;
                }
                $frenadas = $alertas->where('accion', 'sesion.bloqueo_accion');
                $refs = DB::table('bitacora')->whereIn('id', $frenadas->pluck('bitacora_id')->filter())->where('referencia_tipo', 'dispositivo')
                    ->pluck('referencia_id', 'id');
                $equipos = [];
                foreach ($frenadas as $a) {
                    $equipo = isset($refs[$a->bitacora_id]) ? DB::table('dispositivos')->where('id', (int) $refs[$a->bitacora_id])->first()
                        : ($a->ip ? DB::table('dispositivos')->where('ip', $a->ip)->orderByDesc('ultima_vez')->first() : null);
                    if ($equipo) {
                        $equipos[$a->id] = $equipo;
                    }
                }

                return ['equipos' => $equipos,
                    'porRevisar' => DB::table('dispositivos')->where('por_revisar', true)->whereNull('bloqueado_el')->count()];
            });
        } catch (\Throwable $e) {
            report($e);

            return $vacio;
        }
    }
}
