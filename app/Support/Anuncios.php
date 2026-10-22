<?php

namespace App\Support;

use App\Support\Tenancy\Tenancy;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Anuncios generales del panel para todas las empresas (franja arriba de las pantallas del sistema) */
class Anuncios
{
    /** @var array<string, array{0: string, 1: string, 2: string, 3: string}> tipo => [nombre, fondo, texto, ícono] */
    public const TIPOS = [
        'INFO' => ['Información', '#dbeafe', '#1e3a8a', 'ℹ️'],
        'AVISO' => ['Aviso', '#fef3c7', '#78350f', '⚠️'],
        'URGENTE' => ['Urgente', '#dc2626', '#ffffff', '🚨'],
        'OFERTA' => ['Novedad u oferta', '#dcfce7', '#14532d', '🎉'],
    ];

    private const CACHE = 'anuncios-sistema';

    public static function db(): Builder
    {
        return DB::connection('central')->table('anuncios_sistema');
    }

    /** Se llama al guardar desde el panel para que el cambio se vea al instante */
    public static function limpiarCache(): void
    {
        Cache::store('file')->forget(self::CACHE);
    }

    /** Anuncios vigentes para la empresa actual (se leen de la base central como máximo una vez por minuto) */
    public static function paraEmpresa(): Collection
    {
        $cliente = Tenancy::cliente();
        if (! $cliente) {
            return collect();
        }
        try {
            $todos = Cache::store('file')->remember(self::CACHE, 60, function () {
                if (! Schema::connection('central')->hasTable('anuncios_sistema')) {
                    return [];
                }
                $ahora = now();

                return self::db()->where('activo', 1)
                    ->where(fn ($q) => $q->whereNull('desde')->orWhere('desde', '<=', $ahora))
                    ->where(fn ($q) => $q->whereNull('hasta')->orWhere('hasta', '>=', $ahora))
                    ->orderByRaw("FIELD(tipo, 'URGENTE', 'AVISO', 'INFO', 'OFERTA')")->orderByDesc('id')
                    ->get()->map(fn ($a) => (array) $a)->all();
            });
        } catch (\Throwable) {
            return collect();
        }

        // La caché dura un minuto: se vuelve a revisar la fecha para que no se quede uno vencido
        $ahora = now()->toDateTimeString();

        return collect($todos)->map(fn ($a) => (object) $a)->filter(function ($a) use ($cliente, $ahora) {
            $rubros = $a->rubros ? json_decode($a->rubros, true) : null;
            $empresas = ! empty($a->empresas) ? json_decode($a->empresas, true) : null;
            // Si se eligieron empresas, solo ellas lo ven; si no, todas (o las de los rubros elegidos)
            $paraEsta = $empresas ? in_array((int) $cliente->id, array_map('intval', $empresas), true)
                : (empty($rubros) || in_array($cliente->rubro, $rubros, true));

            return (! $a->hasta || $a->hasta >= $ahora) && (! $a->desde || $a->desde <= $ahora) && $paraEsta;
        })->values();
    }
}
