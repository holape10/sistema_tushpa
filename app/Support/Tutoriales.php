<?php

namespace App\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Tutoriales de tushpa.app/tutorial por rubro: pasos con texto e imagen (y video opcional), editables en el panel */
class Tutoriales
{
    public const CARPETA = 'imagenes/tutoriales';

    public static function db(): Builder
    {
        return DB::connection('central')->table('web_tutoriales');
    }

    /** @return array<string, array{nombre: string, icono: string}> "Para todos" y los rubros del sistema */
    public static function rubros(): array
    {
        return ['TODOS' => ['nombre' => 'Para todos', 'icono' => 'fa-star']]
            + collect(Rubros::RUBROS)->except('COMPLETO')->map(fn ($r) => ['nombre' => $r['nombre'], 'icono' => $r['icono']])->all();
    }

    /** Los que se ven en la web (vacío si aún no existe la tabla) */
    public static function activos(): Collection
    {
        try {
            if (! Schema::connection('central')->hasTable('web_tutoriales')) {
                return collect();
            }

            return self::db()->where('activo', true)->orderBy('orden')->orderBy('id')->get()->map(fn ($t) => self::preparar($t));
        } catch (\Throwable) {
            return collect();
        }
    }

    public static function preparar(object $t): object
    {
        $t->pasos = json_decode((string) $t->pasos, true) ?: [];

        return $t;
    }

    /** Código del video de YouTube (watch?v=, youtu.be/, shorts/ o embed/), o null */
    public static function youtube(?string $url): ?string
    {
        return $url && preg_match('~(?:youtu\.be/|v=|embed/|shorts/)([\w-]{11})~', $url, $m) ? $m[1] : null;
    }

    /** Imagen de un paso: se guarda optimizada (capturas de pantalla legibles pero livianas) */
    public static function guardarImagen(UploadedFile $archivo): string
    {
        return ImagenProducto::guardar($archivo, self::CARPETA, 'tut_'.uniqid(), 1400);
    }

    public static function borrarImagen(?string $ruta): void
    {
        foreach ($ruta && str_starts_with($ruta, self::CARPETA.'/') ? [$ruta, ImagenProducto::rutaMini($ruta)] : [] as $r) {
            if (is_file(public_path($r))) {
                @unlink(public_path($r));
            }
        }
    }
}
