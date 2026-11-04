<?php

namespace App\Support\Contabilidad;

/**
 * Escribe tablas .dbf de Visual FoxPro con la estructura exacta de una plantilla (resources/dbf): la cabecera se copia
 * tal cual y solo se agregan los registros, así el sistema contable del cliente las abre o importa sin cambios.
 * Los campos que no vienen en una fila quedan en blanco.
 */
class Dbf
{
    /** @var array<string, array{tipo: string, desde: int, largo: int, decimales: int}> */
    private array $campos = [];

    private string $cabecera;

    private int $largoRegistro;

    public function __construct(string $plantilla)
    {
        $bytes = (string) file_get_contents($plantilla);
        $largoCabecera = unpack('v', substr($bytes, 8, 2))[1];
        $this->largoRegistro = unpack('v', substr($bytes, 10, 2))[1];
        $this->cabecera = substr($bytes, 0, $largoCabecera);
        for ($pos = 32; ord($this->cabecera[$pos]) !== 0x0D; $pos += 32) {
            $nombre = rtrim(substr($this->cabecera, $pos, 11), "\0");
            $this->campos[$nombre] = [
                'tipo' => $this->cabecera[$pos + 11],
                'desde' => unpack('V', substr($this->cabecera, $pos + 12, 4))[1],
                'largo' => ord($this->cabecera[$pos + 16]),
                'decimales' => ord($this->cabecera[$pos + 17]),
            ];
        }
    }

    /** @return array<int, string> */
    public function nombres(): array
    {
        return array_keys($this->campos);
    }

    /**
     * El archivo .dbf completo.
     *
     * @param  array<int, array<string, mixed>>  $filas  campo => valor (texto, número, fecha "Y-m-d" o null)
     */
    public function generar(array $filas): string
    {
        $cabecera = $this->cabecera;
        $hoy = getdate();
        $cabecera[1] = chr($hoy['year'] % 100);
        $cabecera[2] = chr($hoy['mon']);
        $cabecera[3] = chr($hoy['mday']);
        $cabecera = substr_replace($cabecera, pack('V', count($filas)), 4, 4);

        $salida = $cabecera;
        foreach ($filas as $fila) {
            $registro = ' '.str_repeat("\0", $this->largoRegistro - 1);
            foreach ($this->campos as $nombre => $c) {
                $registro = substr_replace($registro, $this->valor($c, $fila[$nombre] ?? null), $c['desde'], $c['largo']);
            }
            $salida .= $registro;
        }

        return $salida."\x1A";
    }

    /**
     * Los registros de un .dbf (texto, números y fechas; los demás campos se omiten), para revisar lo generado.
     *
     * @return array<int, array<string, string|float|null>>
     */
    public static function leer(string $bytes): array
    {
        $registros = unpack('V', substr($bytes, 4, 4))[1];
        $largoCabecera = unpack('v', substr($bytes, 8, 2))[1];
        $largoRegistro = unpack('v', substr($bytes, 10, 2))[1];
        $campos = [];
        for ($pos = 32; ord($bytes[$pos]) !== 0x0D; $pos += 32) {
            $campos[rtrim(substr($bytes, $pos, 11), "\0")] = [$bytes[$pos + 11], unpack('V', substr($bytes, $pos + 12, 4))[1], ord($bytes[$pos + 16])];
        }
        $filas = [];
        for ($r = 0; $r < $registros; $r++) {
            $registro = substr($bytes, $largoCabecera + $r * $largoRegistro, $largoRegistro);
            $fila = [];
            foreach ($campos as $nombre => [$tipo, $desde, $largo]) {
                $v = trim(substr($registro, $desde, $largo));
                $fila[$nombre] = match ($tipo) {
                    'C' => mb_convert_encoding($v, 'UTF-8', 'Windows-1252'),
                    'N', 'F' => $v === '' ? null : (float) $v,
                    'D' => $v === '' ? null : substr($v, 0, 4).'-'.substr($v, 4, 2).'-'.substr($v, 6, 2),
                    default => null,
                };
            }
            $filas[] = $fila;
        }

        return $filas;
    }

    /** Un valor con el formato de FoxPro y exactamente el largo del campo */
    private function valor(array $c, mixed $v): string
    {
        $largo = $c['largo'];

        return match ($c['tipo']) {
            'C' => str_pad(substr(self::texto((string) ($v ?? '')), 0, $largo), $largo),
            'N', 'F' => $v === null || $v === '' ? str_repeat(' ', $largo)
                : substr(str_pad(number_format((float) $v, $c['decimales'], '.', ''), $largo, ' ', STR_PAD_LEFT), -$largo),
            'D' => $v ? str_pad(date('Ymd', strtotime((string) $v)), 8) : str_repeat(' ', 8),
            'L' => $v === null ? ' ' : ($v ? 'T' : 'F'),
            'I' => pack('V', (int) $v),
            // Fecha-hora, binarios y la marca de nulos (_NullFlags) van en cero
            default => str_repeat("\0", $largo),
        };
    }

    /** Texto en la página de códigos de la tabla (Windows-1252), sin saltos de línea */
    private static function texto(string $s): string
    {
        $s = preg_replace('/[\r\n\t]+/', ' ', $s);

        return (string) (@iconv('UTF-8', 'Windows-1252//TRANSLIT', $s) ?: mb_convert_encoding($s, 'Windows-1252', 'UTF-8'));
    }
}
