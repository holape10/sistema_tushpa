<?php

namespace App\Support\Asistente;

use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use App\Support\Tenancy\Tenancy;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Manuales del asistente: los tutoriales en PDF que el dueño sube en el panel. La IA lee cada PDF una sola vez y lo deja
 * en secciones de texto (título, palabras para encontrarla y los pasos). Cuando un usuario pregunta cómo se hace algo,
 * ayuda_sistema busca aquí sin volver a pagar por leer el PDF.
 */
class Manuales
{
    public const CARPETA = 'manuales';

    /** La API acepta PDFs de hasta 32 MB por petición (en base64 pesan un tercio más) */
    public const MAX_KB = 20 * 1024;

    /** Secciones que se le pasan al asistente por pregunta (y cuánto texto de cada una) */
    private const RESULTADOS = 3;

    private const LARGO_TEXTO = 4000;

    /** Palabras que no sirven para buscar */
    private const COMUNES = ['como', 'para', 'que', 'los', 'las', 'del', 'una', 'uno', 'por', 'con', 'mis', 'sus', 'hago', 'hacer', 'puedo',
        'quiero', 'donde', 'esta', 'este', 'esto', 'sistema', 'cual', 'cuando', 'tengo', 'necesito', 'sale', 'hay', 'mas', 'pero', 'le', 'se'];

    public static function db(): Builder
    {
        return DB::connection('central')->table('asistente_manuales');
    }

    public static function secciones(): Builder
    {
        return DB::connection('central')->table('asistente_manual_secciones');
    }

    public static function disponible(): bool
    {
        try {
            return Schema::connection('central')->hasTable('asistente_manual_secciones');
        } catch (\Throwable) {
            return false;
        }
    }

    public static function normalizar(string $texto): string
    {
        return Str::lower(Str::ascii($texto));
    }

    /** Huella del contenido del archivo: el mismo PDF da la misma huella aunque se llame distinto o tenga otro título */
    public static function huella(UploadedFile $pdf): string
    {
        return hash_file('sha256', $pdf->getRealPath());
    }

    /** El manual que ya tiene ese mismo PDF (null si es nuevo) */
    public static function yaSubido(string $huella): ?object
    {
        return Schema::connection('central')->hasColumn('asistente_manuales', 'huella')
            ? self::db()->where('huella', $huella)->first(['id', 'titulo', 'created_at']) : null;
    }

    /** Guarda el PDF y crea el manual "procesando" (la lectura va aparte: tarda) */
    public static function subir(UploadedFile $pdf, string $titulo, string $rubro, ?int $clienteId, string $quien): int
    {
        $conHuella = Schema::connection('central')->hasColumn('asistente_manuales', 'huella') ? ['huella' => self::huella($pdf)] : [];
        $id = self::db()->insertGetId(['titulo' => $titulo, 'rubro' => $rubro, 'cliente_id' => $clienteId, 'estado' => 'procesando',
            'subido_por' => mb_substr($quien, 0, 120), 'created_at' => now(), 'updated_at' => now()] + $conHuella);
        $pdf->storeAs(self::CARPETA, $id.'.pdf', 'local');
        self::db()->where('id', $id)->update(['archivo' => self::CARPETA.'/'.$id.'.pdf']);

        return $id;
    }

    public static function rutaPdf(object $manual): ?string
    {
        return $manual->archivo && Storage::disk('local')->exists($manual->archivo) ? Storage::disk('local')->path($manual->archivo) : null;
    }

    /**
     * La IA lee el PDF y lo deja en secciones. Se pide texto con marcas (no JSON): si la respuesta se corta por larga,
     * igual se guardan las secciones completas.
     */
    public static function procesar(int $id): void
    {
        @set_time_limit(900);
        $manual = self::db()->where('id', $id)->first();
        if (! $manual) {
            return;
        }
        $fallo = fn (string $error) => self::db()->where('id', $id)->update(['estado' => 'error', 'error' => mb_substr($error, 0, 255), 'updated_at' => now()]);
        if ($motivo = Soporte::bloqueo()) {
            $fallo($motivo);

            return;
        }
        $ruta = self::rutaPdf($manual);
        if (! $ruta) {
            $fallo('No se encontró el PDF. Súbelo de nuevo.');

            return;
        }

        $modelo = Soporte::modelo();
        $uso = ['entrada' => 0, 'salida' => 0, 'lectura' => 0, 'escritura' => 0];
        $error = null;
        $texto = '';
        $cortado = false;
        try {
            $r = Asistente::llamar([
                'model' => $modelo, 'maxTokens' => 32000, 'requestOptions' => ['timeout' => 600],
                'system' => [['type' => 'text', 'text' => self::INSTRUCCIONES]],
                'outputConfig' => null,
                'messages' => [['role' => 'user', 'content' => [
                    ['type' => 'document', 'source' => ['type' => 'base64', 'mediaType' => 'application/pdf', 'data' => base64_encode((string) file_get_contents($ruta))],
                        'title' => $manual->titulo],
                    ['type' => 'text', 'text' => "Convierte este tutorial («{$manual->titulo}») en secciones con el formato indicado."],
                ]]],
            ]);
            $uso = ['entrada' => (int) ($r->usage->inputTokens ?? 0), 'salida' => (int) ($r->usage->outputTokens ?? 0),
                'lectura' => (int) ($r->usage->cacheReadInputTokens ?? 0), 'escritura' => (int) ($r->usage->cacheCreationInputTokens ?? 0)];
            $texto = (string) collect($r->content)->filter(fn ($b) => $b->type === 'text')->map(fn ($b) => $b->text)->implode("\n");
            $cortado = $r->stopReason === 'max_tokens';
        } catch (APIStatusException $e) {
            $error = str_contains(Str::lower($e->getMessage()), 'page') ? 'El PDF tiene demasiadas páginas: pártelo en dos (máximo 100 páginas).'
                : 'La IA no pudo leer el PDF ('.mb_substr($e->getMessage(), 0, 120).').';
        } catch (APIConnectionException) {
            $error = 'No se pudo conectar con la IA. Intenta de nuevo en un momento.';
        }

        $secciones = $error ? [] : self::partir($texto);
        if (! $error && ! $secciones) {
            $error = 'La IA no encontró contenido en el PDF (¿es solo imágenes escaneadas?).';
        }
        $costo = Asistente::costo($modelo, $uso['entrada'], $uso['salida'], $uso['lectura'], $uso['escritura']);
        // Va al tope mensual del asistente, no al cupo de ninguna empresa
        DB::connection('central')->table('asistente_uso')->insert([
            'cliente_id' => null, 'base' => '', 'IdUsuario' => 0, 'usuario' => mb_substr('Manual: '.$manual->subido_por, 0, 120),
            'pregunta' => mb_substr('Leer manual: '.$manual->titulo, 0, 500), 'modelo' => $modelo, 'tokens_entrada' => $uso['entrada'], 'tokens_salida' => $uso['salida'],
            'tokens_cache_lectura' => $uso['lectura'], 'tokens_cache_escritura' => $uso['escritura'], 'consultas' => 0,
            'costo_usd' => $costo, 'error' => $error ? mb_substr($error, 0, 255) : null, 'created_at' => now(),
        ]);
        if ($error) {
            $fallo($error);

            return;
        }

        DB::connection('central')->transaction(function () use ($id, $secciones, $costo, $cortado) {
            self::secciones()->where('manual_id', $id)->delete();
            foreach ($secciones as $i => $s) {
                self::secciones()->insert(['manual_id' => $id, 'orden' => $i + 1, 'titulo' => $s['titulo'], 'palabras' => $s['palabras'], 'texto' => $s['texto'],
                    'buscar' => self::normalizar($s['titulo'].' '.$s['palabras'].' '.$s['texto'])]);
            }
            self::db()->where('id', $id)->update(['estado' => 'listo', 'secciones' => count($secciones), 'costo_usd' => $costo, 'updated_at' => now(),
                'error' => $cortado ? 'El PDF es muy largo: se guardó hasta donde alcanzó. Conviene partirlo en dos.' : null]);
        });
    }

    private const INSTRUCCIONES = <<<'TXT'
Recibes un tutorial en PDF de un sistema de gestión peruano (ventas, facturación electrónica SUNAT, restaurante, almacén…).
Conviértelo en secciones de texto para que un asistente pueda explicarle a un usuario cómo se hace cada cosa.

Formato EXACTO de cada sección (nada antes ni después):
### Título corto de lo que se aprende (ej. "Cobrar una mesa con Yape")
Palabras: 8 a 15 palabras sueltas con las que alguien lo preguntaría, incluidos sinónimos (ej. cobrar pagar yape plin mesa cuenta)
Los pasos y explicaciones, en texto simple: pasos numerados, nombres de menús y botones exactamente como aparecen
(Menú › Opción › "Botón"), avisos importantes y soluciones a problemas.

Reglas:
- Una sección por tema o tarea (normalmente 5 a 30 secciones). Que cada una se entienda sola, sin depender de otra.
- Copia fielmente lo que dice el PDF: no inventes pasos, pantallas ni botones. Lo que se ve solo en las imágenes, descríbelo.
- Las tablas pásalas a líneas de texto ("CAJA 01: comprobante en CPE, comanda en BAR01").
- No copies RUC, nombres de personas, teléfonos ni datos de un cliente concreto: escribe "tu RUC", "el cajero", etc.
- Sin markdown extra (nada de negritas ni tablas); solo "###" para empezar cada sección.
TXT;

    /**
     * @return list<array{titulo: string, palabras: string, texto: string}>
     */
    public static function partir(string $texto): array
    {
        $secciones = [];
        foreach (preg_split('/^###\s*/m', $texto) as $bloque) {
            $lineas = preg_split('/\R/', trim($bloque));
            $titulo = trim((string) array_shift($lineas));
            $palabras = '';
            if ($lineas && preg_match('/^\s*Palabras:\s*(.*)$/iu', $lineas[0], $m)) {
                $palabras = trim($m[1]);
                array_shift($lineas);
            }
            $cuerpo = trim(implode("\n", $lineas));
            if ($titulo === '' || mb_strlen($cuerpo) < 20) {
                continue;
            }
            $secciones[] = ['titulo' => mb_substr($titulo, 0, 200), 'palabras' => mb_substr($palabras, 0, 500), 'texto' => $cuerpo];
        }

        return $secciones;
    }

    /**
     * Las secciones de los manuales que mejor responden lo buscado: las de todas las empresas y las hechas para esta;
     * primero las del rubro de la empresa.
     *
     * @return Collection<int, array{manual: string, titulo: string, texto: string}>
     */
    public static function buscar(string $buscar, ?string $rubro = null): Collection
    {
        if (! self::disponible()) {
            return collect();
        }
        $palabras = collect(preg_split('/[^a-z0-9ñ]+/', self::normalizar($buscar), -1, PREG_SPLIT_NO_EMPTY))
            ->filter(fn ($p) => mb_strlen($p) > 2 && ! in_array($p, self::COMUNES, true))
            // Plurales y variantes: "boletas" encuentra "boleta"
            ->map(fn ($p) => mb_strlen($p) > 5 ? rtrim($p, 's') : $p)->unique()->values();
        if ($palabras->isEmpty()) {
            return collect();
        }
        $cliente = Tenancy::cliente()?->id;

        $filas = self::secciones()->join('asistente_manuales as m', 'm.id', '=', 'asistente_manual_secciones.manual_id')
            ->where('m.estado', 'listo')
            ->where(fn ($q) => $q->whereNull('m.cliente_id')->when($cliente, fn ($w) => $w->orWhere('m.cliente_id', $cliente)))
            ->where(fn ($q) => $palabras->each(fn ($p) => $q->orWhere('asistente_manual_secciones.buscar', 'like', '%'.$p.'%')))
            ->limit(300)
            ->get(['asistente_manual_secciones.titulo', 'asistente_manual_secciones.palabras', 'asistente_manual_secciones.texto',
                'asistente_manual_secciones.buscar', 'm.titulo as manual', 'm.rubro', 'm.cliente_id']);

        return $filas->map(function ($s) use ($palabras, $rubro) {
            $cabeza = self::normalizar($s->titulo.' '.$s->palabras);
            $puntos = $palabras->sum(fn ($p) => (str_contains($cabeza, $p) ? 3 : 0) + (str_contains($s->buscar, $p) ? 1 : 0));
            // Lo hecho para esta empresa o su rubro va primero cuando empatan
            $puntos += ($s->cliente_id ? 1.5 : 0) + ($rubro && $s->rubro === $rubro ? 1 : 0);

            return ['puntos' => $puntos, 'manual' => $s->manual, 'titulo' => $s->titulo, 'texto' => Str::limit($s->texto, self::LARGO_TEXTO)];
        })->sortByDesc('puntos')->take(self::RESULTADOS)->map(fn ($s) => collect($s)->except('puntos')->all())->values();
    }

    public static function eliminar(int $id): void
    {
        $manual = self::db()->where('id', $id)->first();
        if ($manual && ($ruta = self::rutaPdf($manual))) {
            @unlink($ruta);
        }
        self::secciones()->where('manual_id', $id)->delete();
        self::db()->where('id', $id)->delete();
    }
}
