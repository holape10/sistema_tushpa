<?php
namespace App\Support\Impresion;

/**
 * Arma los bytes ESC/POS de un ticket para impresoras térmicas (Epson y compatibles).
 * Sin dependencias: texto con tildes (página de códigos PC850), alineación, negrita, tamaños,
 * columnas, QR nativo de la impresora, logo (raster), corte y apertura de cajón.
 */
class Escpos
{
    private const ESC = "\x1B";
    private const GS = "\x1D";

    private string $buffer = '';

    public function __construct(private int $columnas = 42)
    {
        // Inicializa y elige la página de códigos PC850 (á é í ó ú ñ Ñ ° ...)
        $this->buffer = self::ESC . '@' . self::ESC . 't' . "\x02";
    }

    public function columnas(): int
    {
        return $this->columnas;
    }

    private function codificar(string $texto): string
    {
        $t = @iconv('UTF-8', 'CP850//TRANSLIT//IGNORE', $texto);
        return $t === false ? $texto : $t;
    }

    public function alinear(string $a): self
    {
        $this->buffer .= self::ESC . 'a' . chr(['izq' => 0, 'centro' => 1, 'der' => 2][$a] ?? 0);
        return $this;
    }

    public function negrita(bool $on = true): self
    {
        $this->buffer .= self::ESC . 'E' . chr($on ? 1 : 0);
        return $this;
    }

    /** Tamaño 1..8 de ancho y alto */
    public function tamano(int $ancho = 1, int $alto = 1): self
    {
        $this->buffer .= self::GS . '!' . chr((($ancho - 1) << 4) | ($alto - 1));
        return $this;
    }

    public function texto(string $texto = ''): self
    {
        $this->buffer .= $this->codificar($texto) . "\n";
        return $this;
    }

    /** Texto largo partido en líneas del ancho del papel */
    public function parrafo(string $texto, ?int $ancho = null): self
    {
        foreach (explode("\n", wordwrap($texto, $ancho ?? $this->columnas, "\n", true)) as $l) {
            $this->texto($l);
        }
        return $this;
    }

    public function linea(string $caracter = '-'): self
    {
        return $this->texto(str_repeat($caracter, $this->columnas));
    }

    /** Texto a la izquierda y valor a la derecha en la misma línea */
    public function dosColumnas(string $izq, string $der): self
    {
        $espacio = $this->columnas - mb_strlen($der) - 1;
        $izq = mb_strlen($izq) > $espacio ? mb_substr($izq, 0, $espacio) : $izq;
        return $this->texto($izq . str_repeat(' ', max(1, $this->columnas - mb_strlen($izq) - mb_strlen($der))) . $der);
    }

    /** Fila de producto: descripción (partida si es larga) y debajo cantidad x precio = total */
    public function item(string $descripcion, float $cantidad, float $precio, float $total): self
    {
        $this->parrafo($descripcion);
        $cant = rtrim(rtrim(number_format($cantidad, 2, '.', ''), '0'), '.');
        return $this->dosColumnas('  ' . $cant . ' x ' . number_format($precio, 2), number_format($total, 2));
    }

    public function avanzar(int $lineas = 1): self
    {
        $this->buffer .= self::ESC . 'd' . chr(max(0, min(255, $lineas)));
        return $this;
    }

    /** QR generado por la propia impresora (modelo 2) */
    public function qr(string $datos, int $tamano = 5): self
    {
        $datos = $this->codificar($datos);
        $len = strlen($datos) + 3;
        $this->buffer .= self::GS . "(k\x04\x00\x31\x41\x32\x00"                       // modelo 2
            . self::GS . "(k\x03\x00\x31\x43" . chr(max(1, min(16, $tamano)))            // tamaño del módulo
            . self::GS . "(k\x03\x00\x31\x45\x31"                                        // corrección de error M
            . self::GS . '(k' . chr($len % 256) . chr(intdiv($len, 256)) . "\x31\x50\x30" . $datos // datos
            . self::GS . "(k\x03\x00\x31\x51\x30";                                       // imprimir
        return $this;
    }

    /** Logo PNG/JPG convertido a raster de 1 bit (GS v 0). Se ignora si no se puede leer. */
    public function logo(?string $ruta, int $anchoMax = 384): self
    {
        if (!$ruta || !is_file($ruta) || !function_exists('imagecreatefromstring')) {
            return $this;
        }
        $img = @imagecreatefromstring((string) file_get_contents($ruta));
        if (!$img) {
            return $this;
        }
        $w = imagesx($img);
        $h = imagesy($img);
        if ($w > $anchoMax) {
            $nh = (int) round($h * $anchoMax / $w);
            $esc = imagecreatetruecolor($anchoMax, $nh);
            imagefill($esc, 0, 0, imagecolorallocate($esc, 255, 255, 255));
            imagecopyresampled($esc, $img, 0, 0, 0, 0, $anchoMax, $nh, $w, $h);
            $img = $esc;
            [$w, $h] = [$anchoMax, $nh];
        }
        $bytesFila = (int) ceil($w / 8);
        $datos = '';
        for ($y = 0; $y < $h; $y++) {
            for ($bx = 0; $bx < $bytesFila; $bx++) {
                $byte = 0;
                for ($b = 0; $b < 8; $b++) {
                    $x = $bx * 8 + $b;
                    if ($x < $w) {
                        $c = imagecolorat($img, $x, $y);
                        $a = ($c >> 24) & 0x7F;                       // transparencia = blanco
                        $gris = ((($c >> 16) & 0xFF) * 0.3 + (($c >> 8) & 0xFF) * 0.59 + ($c & 0xFF) * 0.11);
                        if ($a < 64 && $gris < 128) {
                            $byte |= (0x80 >> $b);
                        }
                    }
                }
                $datos .= chr($byte);
            }
        }
        $this->alinear('centro');
        $this->buffer .= self::GS . "v0\x00" . chr($bytesFila % 256) . chr(intdiv($bytesFila, 256)) . chr($h % 256) . chr(intdiv($h, 256)) . $datos;
        return $this;
    }

    public function cortar(): self
    {
        $this->buffer .= self::GS . 'V' . "\x42\x00"; // avanza y corta
        return $this;
    }

    /** Pulso al cajón de dinero */
    public function abrirCajon(): self
    {
        $this->buffer .= self::ESC . 'p' . "\x00\x19\xFA";
        return $this;
    }

    public function bytes(): string
    {
        return $this->buffer;
    }
}
