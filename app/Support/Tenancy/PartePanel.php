<?php

namespace App\Support\Tenancy;

use App\Models\Central\Cliente;
use App\Models\Central\Marca;
use App\Models\Central\Superadmin;
use App\Support\MonitorErrores;
use App\Support\TelegramCitas;

/**
 * Parte de cada mañana al Telegram de soporte: las empresas con algo urgente o importante (el mismo diagnóstico del
 * panel › 🩺), para enterarse antes de que el cliente llame. Al dueño le llegan todas; a cada usuario del panel, las suyas.
 */
class PartePanel
{
    /** Problemas por empresa que entran al mensaje (el resto se ve en el diagnóstico) */
    private const POR_EMPRESA = 3;

    private const MAX_TEXTO = 3900;

    /**
     * Revisa cada empresa activa y se queda con las que tienen algo rojo o naranja.
     *
     * @return list<array{cliente: Cliente, problemas: list<array<string, mixed>>}>
     */
    public static function revisar(?string $ruc = null): array
    {
        $conProblemas = [];
        foreach (Cliente::where('estado', 'ACTIVO')->when($ruc, fn ($q) => $q->where('ruc', $ruc))->orderBy('razon_social')->get() as $cliente) {
            try {
                $problemas = array_values(array_filter(SaludCliente::revisar($cliente)['problemas'], fn ($p) => $p['nivel'] !== 'amarillo'));
            } catch (\Throwable $e) {
                report($e);

                continue;
            }
            if ($problemas) {
                $conProblemas[] = ['cliente' => $cliente, 'problemas' => $problemas];
            }
        }

        return $conProblemas;
    }

    /**
     * El texto del parte (null = no hay nada que avisar).
     *
     * @param  list<array{cliente: Cliente, problemas: list<array<string, mixed>>}>  $empresas
     */
    public static function mensaje(array $empresas, ?string $panelDominio = null): ?string
    {
        if (! $empresas) {
            return null;
        }
        $urgentes = count(array_filter($empresas, fn ($e) => $e['problemas'][0]['nivel'] === 'rojo'));
        $texto = '🌅 Parte de soporte · '.now()->format('d/m/Y')."\n"
            .count($empresas).' empresa(s) necesitan atención'.($urgentes ? " ({$urgentes} urgente(s))" : '').":\n";
        foreach ($empresas as $i => $e) {
            $c = $e['cliente'];
            $bloque = "\n".($e['problemas'][0]['nivel'] === 'rojo' ? '🔴 ' : '🟠 ').($c->nombre_comercial ?: $c->razon_social)."\n";
            foreach (array_slice($e['problemas'], 0, self::POR_EMPRESA) as $p) {
                $bloque .= '  • '.$p['titulo']."\n";
            }
            if (count($e['problemas']) > self::POR_EMPRESA) {
                $bloque .= '  • y '.(count($e['problemas']) - self::POR_EMPRESA)." más\n";
            }
            try {
                $bloque .= '  '.route('admin.clientes.diagnostico', ['panelDominio' => $panelDominio ?? strtolower((string) config('tenancy.dominio')), 'cliente' => $c->id])."\n";
            } catch (\Throwable) {
            }
            if (mb_strlen($texto.$bloque) > self::MAX_TEXTO) {
                $texto .= "\n… y ".(count($empresas) - $i).' empresa(s) más: revísalas en el panel.';
                break;
            }
            $texto .= $bloque;
        }

        return $texto;
    }

    /** Envía el parte: el Telegram del .env (dueño) y los vinculados en la Agenda del panel. Devuelve a cuántos llegó */
    public static function enviar(?string $ruc = null): int
    {
        $empresas = self::revisar($ruc);
        $enviados = 0;
        if (($texto = self::mensaje($empresas)) && MonitorErrores::avisar($texto)) {
            $enviados++;
        }
        if (! TelegramCitas::configurado()) {
            return $enviados;
        }
        $yaAvisado = (string) config('services.telegram.chat_id');
        foreach (Superadmin::where('activo', true)->get() as $u) {
            $suyas = $u->es_dueno ? $empresas : array_values(array_filter($empresas, fn ($e) => (int) $e['cliente']->creado_por === (int) $u->id));
            $dominio = strtolower((string) (($u->marca_id ? Marca::find($u->marca_id)?->dominio : null) ?: config('tenancy.dominio')));
            if (! ($suyo = self::mensaje($suyas, $dominio))) {
                continue;
            }
            foreach (TelegramCitas::chatsPanel((int) $u->id) as $chat) {
                if ($chat !== $yaAvisado) {
                    TelegramCitas::enviar($chat, $suyo);
                    $enviados++;
                }
            }
        }

        return $enviados;
    }
}
