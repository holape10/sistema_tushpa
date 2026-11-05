<?php

namespace App\Support\Tenancy;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Cuántas veces cada cliente pide XML a json.pe (cada consulta usa créditos de la cuenta de TUSHPA), por mes. Se anota
 * en la base central y se ve en el panel admin. Si no se puede anotar, la descarga del XML sigue igual.
 */
class ConsumoXml
{
    public static function registrar(bool $traido): void
    {
        $cliente = Tenancy::cliente();
        if (! $cliente?->id) {
            return;
        }
        try {
            $central = DB::connection('central');
            $llave = ['cliente_id' => $cliente->id, 'mes' => now()->format('Ym')];
            $central->table('consumo_xml')->insertOrIgnore($llave + ['consultas' => 0, 'traidos' => 0]);
            $central->table('consumo_xml')->where($llave)->update([
                'consultas' => DB::raw('consultas + 1'), 'traidos' => DB::raw('traidos + '.($traido ? 1 : 0)), 'ultima_vez' => now(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** @return Collection<int, object{consultas: int, traidos: int}> consultas del mes por cliente_id */
    public static function delMes(?string $mes = null): Collection
    {
        try {
            return DB::connection('central')->table('consumo_xml')->where('mes', $mes ?? now()->format('Ym'))->get()->keyBy('cliente_id');
        } catch (\Throwable) {
            return collect();
        }
    }

    /** @return Collection<int, object{mes: string, consultas: int, traidos: int}> últimos meses de un cliente */
    public static function historial(int $clienteId, int $meses = 6): Collection
    {
        try {
            return DB::connection('central')->table('consumo_xml')->where('cliente_id', $clienteId)->orderByDesc('mes')->limit($meses)->get();
        } catch (\Throwable) {
            return collect();
        }
    }
}
