<?php

namespace App\Support;

use App\Http\Controllers\TurnoController;
use App\Models\Turno;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Arqueo de caja de un turno abierto, sin cerrarlo: se cuenta el efectivo (billete por billete o el total) y se compara
 * con lo que debería haber en ese momento (fondo + ventas en efectivo + ingresos − egresos). Si falta, avisa al dueño.
 */
class ArqueoCaja
{
    /** A partir de cuánto faltante se avisa al celular del dueño */
    public const AVISAR_DESDE = 5;

    /**
     * @param  array<string, mixed>  $billetes  campo de Turno::DENOMINACIONES => cantidad
     */
    public static function registrar(User $quien, Turno $turno, array $billetes, ?float $monto, ?string $observacion): int
    {
        if ($turno->estado !== 'ABIERTO') {
            throw new \RuntimeException('Ese turno ya se cerró: su arqueo es el del cierre.');
        }
        $cantidades = [];
        $suma = 0.0;
        foreach (Turno::DENOMINACIONES as $campo => $valor) {
            $n = max(0, (int) ($billetes[$campo] ?? 0));
            if ($n > 0) {
                $cantidades[$campo] = $n;
                $suma += $n * $valor;
            }
        }
        $contado = $cantidades ? round($suma, 2) : ($monto !== null ? round($monto, 2) : null);
        if ($contado === null) {
            throw new \RuntimeException('Cuenta el efectivo: los billetes y monedas, o escribe el total.');
        }
        $esperado = TurnoController::resumen($turno)['efectivoEsperado'];
        $diferencia = round($contado - $esperado, 2);

        $id = DB::table('arqueos_caja')->insertGetId([
            'id_turno' => $turno->id_turno, 'id_empresa_negocio' => $turno->id_empresa_negocio, 'IdUsuario' => $quien->IdUsuario,
            'cajero' => $turno->IdUsuario, 'esperado' => $esperado, 'contado' => $contado, 'diferencia' => $diferencia,
            'billetes' => $cantidades ? json_encode($cantidades) : null,
            'observacion' => mb_substr(trim((string) $observacion), 0, 300) ?: null, 'created_at' => now(),
        ]);

        if ($diferencia <= -self::AVISAR_DESDE) {
            AvisosCelular::alDueno((int) $turno->id_empresa_negocio, '💸 Arqueo: faltan S/ '.number_format(abs($diferencia), 2).' en caja',
                trim((string) ($quien->apeusu ?: $quien->name)).' contó la caja del turno N° '.($turno->turno ?? $turno->id_turno).': debía haber S/ '
                .number_format($esperado, 2).' y hay S/ '.number_format($contado, 2).'.', route('arqueos.show', $id));
        }

        return $id;
    }

    /** Turnos abiertos que este usuario puede arquear: el administrador, los de la sucursal; el cajero, el suyo */
    public static function turnosAbiertos(User $user)
    {
        return Turno::with('usuario')->where('id_empresa_negocio', $user->id_empresa_negocio)->where('estado', 'ABIERTO')
            ->when(! $user->esAdmin(), fn ($q) => $q->where('IdUsuario', $user->IdUsuario))
            ->orderBy('apertura')->get();
    }
}
