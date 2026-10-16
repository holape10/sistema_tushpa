<?php

namespace App\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Tickets de soporte (base central): los crea el administrador de una empresa desde su sistema y los atiende en el panel
 * el usuario que creó esa empresa (y el dueño). Las fotos se guardan fuera de public: storage/app/soporte/{ticket}/.
 */
class Soporte
{
    public const TIPOS = ['FALLA' => '🐞 Falla o error', 'CONSULTA' => '❓ Consulta / cómo se hace', 'SUNAT' => '🧾 SUNAT / comprobantes', 'MEJORA' => '💡 Pedido de mejora'];

    public const ESTADOS = ['ABIERTO' => 'Abierto', 'EN_PROCESO' => 'En proceso', 'RESPONDIDO' => 'Respondido', 'CERRADO' => 'Cerrado'];

    /** Fotos por mensaje */
    public const MAX_FOTOS = 5;

    public static function db(): Builder
    {
        return DB::connection('central')->table('soporte_tickets');
    }

    /** ¿Están las tablas? (instalaciones de una sola empresa no tienen base central) */
    public static function disponible(): bool
    {
        try {
            return Schema::connection('central')->hasTable('soporte_tickets');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @param  array{asunto: string, tipo: string, prioridad: string, texto: string, telefono?: ?string}  $d
     * @param  array<int, UploadedFile>  $fotos
     */
    public static function crear(int $clienteId, object $usuario, string $sucursal, array $d, array $fotos): int
    {
        return DB::connection('central')->transaction(function () use ($clienteId, $usuario, $sucursal, $d, $fotos) {
            $id = self::db()->insertGetId([
                'cliente_id' => $clienteId, 'asunto' => $d['asunto'], 'tipo' => $d['tipo'], 'prioridad' => $d['prioridad'],
                'estado' => 'ABIERTO', 'usuario' => $usuario->nombre, 'usuario_id' => $usuario->id, 'sucursal' => $sucursal,
                'telefono' => $d['telefono'] ?? null, 'leido_soporte' => false, 'leido_cliente' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
            self::mensaje($id, 'CLIENTE', $usuario->nombre, $d['texto'], $fotos);

            return $id;
        });
    }

    /** Agrega un mensaje (con fotos) y marca quién tiene algo nuevo por leer */
    public static function mensaje(int $ticketId, string $autor, string $nombre, ?string $texto, array $fotos): void
    {
        $texto = trim((string) $texto);
        if ($texto === '' && ! $fotos) {
            throw new RuntimeException('Escribe un mensaje o adjunta una foto.');
        }
        $rutas = [];
        foreach (array_slice($fotos, 0, self::MAX_FOTOS) as $foto) {
            $rutas[] = $foto->storeAs("soporte/{$ticketId}", Str::random(24).'.'.strtolower($foto->guessExtension() ?: 'jpg'), 'local');
        }
        DB::connection('central')->table('soporte_mensajes')->insert([
            'ticket_id' => $ticketId, 'autor' => $autor, 'autor_nombre' => $nombre, 'texto' => $texto ?: null,
            'adjuntos' => $rutas ? json_encode($rutas) : null, 'created_at' => now(),
        ]);
        $ticket = self::db()->where('id', $ticketId)->first();
        self::db()->where('id', $ticketId)->update($autor === 'SOPORTE'
            ? ['leido_cliente' => false, 'leido_soporte' => true, 'estado' => $ticket->estado === 'CERRADO' ? 'CERRADO' : 'RESPONDIDO', 'updated_at' => now()]
            : ['leido_soporte' => false, 'leido_cliente' => true, 'estado' => 'ABIERTO', 'cerrado_at' => null, 'updated_at' => now()]);
    }

    public static function mensajes(int $ticketId): Collection
    {
        return DB::connection('central')->table('soporte_mensajes')->where('ticket_id', $ticketId)->orderBy('id')->get()
            ->map(function ($m) {
                $m->adjuntos = json_decode((string) $m->adjuntos, true) ?: [];

                return $m;
            });
    }

    /** Ruta del archivo de un mensaje del ticket (null si no le pertenece) */
    public static function archivo(int $ticketId, int $mensajeId, int $n): ?string
    {
        $m = DB::connection('central')->table('soporte_mensajes')->where('id', $mensajeId)->where('ticket_id', $ticketId)->first();
        $ruta = $m ? (json_decode((string) $m->adjuntos, true)[$n] ?? null) : null;

        return $ruta && str_starts_with($ruta, "soporte/{$ticketId}/") ? storage_path('app/private/'.$ruta) : null;
    }

    /** Tickets con respuesta sin leer de una empresa (aviso en su sistema) */
    public static function sinLeerCliente(int $clienteId): int
    {
        try {
            return self::db()->where('cliente_id', $clienteId)->where('leido_cliente', false)->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }
}
