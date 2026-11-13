<?php

namespace App\Jobs;

use App\Support\Antiguo\Importador;
use App\Support\Procesos;
use Illuminate\Support\Facades\Auth;

/**
 * Importar el sistema antiguo en segundo plano: con mucho historial (miles de ventas, kardex, guías) toma varios minutos y
 * la página se cortaba por tiempo a la mitad (quedaban solo los productos y clientes). El resultado (lo creado y los
 * avisos de cada sucursal) queda en un archivo para verlo en la pantalla del paso 3.
 */
class ImportarSistemaAntiguo extends ProcesoEmpresa
{
    /**
     * @param  array<int, int>  $mapa  sucursal antigua => sucursal nueva
     * @param  array{secciones: list<string>, dia0: string, imagenes: ?string}  $opciones
     */
    public function __construct(string $base, int $procesoId, int $usuarioId, public string $bd, public array $mapa, public array $opciones)
    {
        parent::__construct($base, $procesoId, $usuarioId);
    }

    protected function ejecutar(object $proceso): array
    {
        Importador::conectar($this->bd);
        try {
            $resultado = Importador::porSucursales(Auth::user(), $this->mapa, $this->opciones);
            $nombres = collect((new Importador)->sucursales())->pluck('nombre', 'id')->all();
        } finally {
            // El ZIP de imágenes se copió solo para esta importación
            if (! empty($this->opciones['imagenes'])) {
                @unlink($this->opciones['imagenes']);
            }
        }
        $archivo = Procesos::carpeta($this->base).'/importacion-'.$this->procesoId.'.json';
        file_put_contents($archivo, json_encode(['bd' => $this->bd, 'resultado' => $resultado, 'nombres' => $nombres], JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR));

        $ventas = collect($resultado)->sum(fn ($r) => (int) ($r['secciones']['historial']['ventas'] ?? 0));
        $avisos = collect($resultado)->sum(fn ($r) => count($r['avisos'] ?? []));

        return ['archivo' => $archivo, 'nombre' => 'importacion.json',
            'mensaje' => 'Importación terminada'.($ventas ? ': '.number_format($ventas).' ventas' : '').($avisos ? " ({$avisos} avisos)" : '').'.'];
    }
}
