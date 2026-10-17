<?php

namespace App\Support\HoraLocal;

use Illuminate\Database\Query\Builder;

/**
 * Pone la hora de Perú (la de PHP, config app.timezone) en las columnas que antes tomaban la hora de MySQL
 * (DEFAULT CURRENT_TIMESTAMP). En el servidor MySQL trabaja en UTC y las ventas salían 5 horas adelantadas.
 * Si el insert ya trae la columna, se respeta.
 */
class ConsultaConHora extends Builder
{
    /** Tabla => columnas con DEFAULT CURRENT_TIMESTAMP */
    public const COLUMNAS = [
        'cpe_cabecera' => ['fecha_hora'],
        'resumenes' => ['fecha_hora'],
        'turnos' => ['apertura'],
        'movimientos_productos' => ['fecha_registro', 'fecha_hora'],
        'cola_impresion' => ['creado'],
        'cocina_tickets' => ['creado'],
        'socios' => ['creado'],
        'historia_clinica' => ['his_cli_fec'],
        'atencion_clinica' => ['creado'],
        'citas' => ['creado'],
        'clinica_tratamientos' => ['creado'],
        'clinica_tratamiento_registros' => ['creado'],
        'failed_jobs' => ['failed_at'],
    ];

    private function conHora(array $values): array
    {
        $tabla = is_string($this->from) ? strtolower(trim(preg_split('/\s+as\s+|\s+/i', trim($this->from, '` '))[0], '`')) : '';
        $columnas = self::COLUMNAS[$tabla] ?? null;
        if (! $columnas || $values === []) {
            return $values;
        }
        $ahora = now()->format('Y-m-d H:i:s');
        $completar = fn (array $fila) => $fila + array_fill_keys($columnas, $ahora);

        return is_array(reset($values)) ? array_map($completar, $values) : $completar($values);
    }

    public function insert(array $values)
    {
        return parent::insert($this->conHora($values));
    }

    public function insertGetId(array $values, $sequence = null)
    {
        return parent::insertGetId($this->conHora($values), $sequence);
    }

    public function insertOrIgnore(array $values)
    {
        return parent::insertOrIgnore($this->conHora($values));
    }
}
