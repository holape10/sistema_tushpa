<?php

namespace App\Support\Asistente;

use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use App\Models\User;
use App\Support\Compras;
use App\Support\ComprasDetalle;
use App\Support\ImagenProducto;
use App\Support\Sunat\CompraXml;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Leer un comprobante de compra para llenar el formulario: el XML de la factura electrónica se lee directo (exacto y sin
 * costo); una foto o un PDF los lee la IA (cuenta como una pregunta del asistente). Las líneas se emparejan con los
 * productos de la sede (por lo aprendido de ese proveedor, por código o por nombre); las que no se reconocen quedan
 * para que el usuario elija o cree el producto, y el sistema aprende su elección. El usuario revisa todo y guarda.
 */
class LecturaComprobante
{
    public const EXTENSIONES = 'jpg,jpeg,png,webp,pdf,xml,zip';

    /** Lado mayor de la foto que se manda (más no mejora la lectura y cuesta más) */
    private const LADO_FOTO = 1600;

    private const MAX_PDF_BYTES = 4_500_000;

    /** Parecido mínimo (%) para tomar un producto por su nombre */
    private const PARECIDO = 72;

    private const ESQUEMA = [
        'type' => 'object', 'additionalProperties' => false,
        'required' => ['es_comprobante', 'tipo', 'serie', 'numero', 'fecha', 'ruc_emisor', 'razon_emisor', 'moneda', 'total', 'lineas'],
        'properties' => [
            'es_comprobante' => ['type' => 'boolean', 'description' => 'false si la imagen no es un comprobante de compra legible'],
            'tipo' => ['type' => 'string', 'enum' => ['01', '03', '12', '00'], 'description' => '01 factura, 03 boleta, 12 ticket, 00 otro (nota de venta, recibo simple)'],
            'serie' => ['type' => 'string', 'description' => 'Serie tal como figura (p. ej. F001, B002, E001). Vacío si no hay.'],
            'numero' => ['type' => 'string', 'description' => 'Solo el número correlativo, sin ceros a la izquierda. Vacío si no hay.'],
            'fecha' => ['type' => 'string', 'description' => 'Fecha de emisión AAAA-MM-DD. Vacío si no se lee.'],
            'ruc_emisor' => ['type' => 'string', 'description' => 'RUC (11 dígitos) o DNI del vendedor, solo dígitos. Vacío si no hay.'],
            'razon_emisor' => ['type' => 'string', 'description' => 'Nombre o razón social del vendedor.'],
            'moneda' => ['type' => 'string', 'enum' => ['PEN', 'USD']],
            'total' => ['type' => 'number', 'description' => 'Importe total a pagar del comprobante.'],
            'lineas' => ['type' => 'array', 'items' => ['type' => 'object', 'additionalProperties' => false,
                'required' => ['descripcion', 'cantidad', 'unidad', 'importe'],
                'properties' => [
                    'descripcion' => ['type' => 'string'],
                    'cantidad' => ['type' => 'number'],
                    'unidad' => ['type' => 'string', 'description' => 'Unidad tal como figura (KG, UND, CJA…). Vacío si no hay.'],
                    'importe' => ['type' => 'number', 'description' => 'Importe de la línea tal como figura en el comprobante.'],
                ]]],
        ],
    ];

    /**
     * Lee el archivo y lo deja listo para el formulario de compra.
     *
     * @return array<string, mixed> el formato de "desdeOrden" del formulario (con tipo "lectura", las líneas no reconocidas y avisos)
     */
    public static function leer(User $user, UploadedFile $archivo): array
    {
        $extension = strtolower($archivo->getClientOriginalExtension());
        $leido = in_array($extension, ['xml', 'zip'], true) ? self::deXml($archivo) : self::conIa($user, $archivo, $extension);

        return self::paraFormulario($user, $leido);
    }

    /** XML (o ZIP con el XML) de la factura electrónica: exacto y sin costo */
    private static function deXml(UploadedFile $archivo): array
    {
        $c = CompraXml::desdeArchivos([['nombre' => $archivo->getClientOriginalName(), 'ruta' => $archivo->getRealPath()]])['comprobantes'][0] ?? null;
        if (! $c) {
            throw new \RuntimeException('No se pudo leer ese XML: sube el XML de la factura electrónica (o el ZIP que lo trae).');
        }

        return [
            'origen' => 'XML', 'tipo' => $c['tipo'], 'serie' => $c['serie'], 'numero' => $c['numero'], 'fecha' => $c['fecha'],
            'ruc_emisor' => $c['ruc_emisor'], 'razon_emisor' => $c['razon_emisor'], 'ruc_comprador' => $c['ruc_comprador'],
            'moneda' => $c['moneda'], 'total' => $c['total'],
            'lineas' => array_map(fn ($l) => ['descripcion' => $l['descripcion'], 'codigo' => $l['codigo'], 'cantidad' => $l['cantidad'],
                'unidad' => $l['unidad'], 'importe' => round($l['base'] + $l['igv'], 2)], $c['lineas']),
        ];
    }

    /** Foto o PDF: lo lee la IA y responde con los datos en un formato fijo */
    private static function conIa(User $user, UploadedFile $archivo, string $extension): array
    {
        if ($motivo = Asistente::bloqueoComun()) {
            throw new \RuntimeException($motivo.' (Sin la IA puedes subir el XML de la factura: se lee igual y no cuesta nada.)');
        }
        if ($extension === 'pdf') {
            if ($archivo->getSize() > self::MAX_PDF_BYTES) {
                throw new \RuntimeException('El PDF es muy pesado (máximo 4.5 MB). Sube una foto o el XML.');
            }
            $bloque = ['type' => 'document', 'source' => ['type' => 'base64', 'mediaType' => 'application/pdf', 'data' => base64_encode((string) file_get_contents($archivo->getRealPath()))]];
        } else {
            $tmp = tempnam(sys_get_temp_dir(), 'cpe').'.webp';
            if (! ImagenProducto::redimensionar($archivo->getRealPath(), $tmp, self::LADO_FOTO, ImagenProducto::orientacion($archivo->getRealPath()))) {
                @unlink($tmp);
                throw new \RuntimeException('No se pudo abrir la foto. Sube una foto JPG o PNG.');
            }
            $bloque = ['type' => 'image', 'source' => ['type' => 'base64', 'mediaType' => 'image/webp', 'data' => base64_encode((string) file_get_contents($tmp))]];
            @unlink($tmp);
        }

        $modelo = Asistente::modelo();
        $uso = ['entrada' => 0, 'salida' => 0, 'lectura' => 0, 'escritura' => 0];
        $error = null;
        $datos = null;
        try {
            $r = Asistente::llamar([
                'model' => $modelo, 'maxTokens' => 8000,
                'system' => [['type' => 'text', 'text' => 'Lees comprobantes de compra peruanos (facturas, boletas, tickets, notas de venta) y copias sus datos con exactitud. '
                    .'No inventes: si un dato no se ve, déjalo vacío (o 0 si es un número). Copia cada línea de productos o servicios con su cantidad y el importe de la línea tal como figura.']],
                'outputConfig' => ['format' => ['type' => 'json_schema', 'schema' => self::ESQUEMA]],
                'messages' => [['role' => 'user', 'content' => [$bloque, ['type' => 'text', 'text' => 'Lee este comprobante de compra.']]]],
            ]);
            $uso = ['entrada' => (int) ($r->usage->inputTokens ?? 0), 'salida' => (int) ($r->usage->outputTokens ?? 0),
                'lectura' => (int) ($r->usage->cacheReadInputTokens ?? 0), 'escritura' => (int) ($r->usage->cacheCreationInputTokens ?? 0)];
            $texto = collect($r->content)->first(fn ($b) => $b->type === 'text')?->text;
            $datos = $r->stopReason === 'end_turn' && $texto ? json_decode($texto, true) : null;
            if (! is_array($datos) || empty($datos['es_comprobante'])) {
                $error = 'No se pudo leer un comprobante en ese archivo. Toma la foto de frente, con buena luz y que se vea completo.';
            }
        } catch (APIStatusException|APIConnectionException) {
            $error = 'No se pudo conectar con la IA. Intenta de nuevo en un momento.';
        }
        Asistente::anotar($user, 'Lectura de comprobante ('.($extension === 'pdf' ? 'PDF' : 'foto').')', $modelo, $uso, 0, $error);
        if ($error) {
            throw new \RuntimeException($error);
        }

        return ['origen' => $extension === 'pdf' ? 'PDF' : 'foto', 'ruc_comprador' => null] + $datos;
    }

    /** Lo leído en el formato del formulario de compra (emparejando las líneas con los productos de la sede) */
    public static function paraFormulario(User $user, array $c): array
    {
        $sucursal = (int) $user->id_empresa_negocio;
        $ruc = preg_replace('/\D/', '', (string) ($c['ruc_emisor'] ?? ''));
        $lineas = array_values(array_filter($c['lineas'] ?? [], fn ($l) => (float) ($l['cantidad'] ?? 0) > 0 && trim((string) ($l['descripcion'] ?? '')) !== ''));
        // Los importes de las líneas se ajustan al total (así cuadra el IGV si las líneas venían sin IGV, y el redondeo)
        $suma = array_sum(array_map(fn ($l) => (float) $l['importe'], $lineas));
        $total = (float) ($c['total'] ?? 0);
        $factor = $suma > 0 && $total > 0 && abs($total - $suma) / $suma <= 0.25 ? $total / $suma : 1;
        $pares = self::emparejar($sucursal, $ruc, $lineas);
        $igv = (string) (DB::table('empresa_negocios')->where('id_empresa_negocio', $sucursal)->value('tip_igv_pred') ?: '10');

        $items = [];
        $pendientes = [];
        foreach ($lineas as $i => $l) {
            $costo = round((float) $l['importe'] * $factor / (float) $l['cantidad'], 4);
            $p = $pares[$i] ?? null;
            if ($p) {
                $items[] = ['id' => (int) $p->IdProducto, 'codigo' => $p->procod, 'nombre' => $p->pronom, 'tip_igv' => $igv, 'presentacion' => null, 'factor' => 1,
                    'presentacion_nombre' => null, 'cantidad' => (float) $l['cantidad'], 'costo' => $costo, 'flete' => 0, 'lote' => null, 'vencimiento' => null,
                    'control_lote' => (bool) $p->control_lote, 'control_serie' => (bool) $p->control_serie, 'series' => '', 'leido' => $l['descripcion']];
            } else {
                $pendientes[] = ['descripcion' => mb_substr((string) $l['descripcion'], 0, 150), 'cantidad' => (float) $l['cantidad'], 'costo' => $costo,
                    'unidad' => $l['unidad'] ?? null, 'tip_igv' => $igv];
            }
        }

        $avisos = [];
        if (! empty($c['ruc_comprador']) && $c['ruc_comprador'] !== (string) $user->IdEmpresa) {
            $avisos[] = 'Este comprobante está emitido a otro RUC ('.$c['ruc_comprador'].'), no a tu empresa.';
        }
        if ($c['origen'] !== 'XML') {
            $avisos[] = 'Leído de '.($c['origen'] === 'PDF' ? 'un PDF' : 'una foto').' con IA: revisa los números antes de guardar.';
        }

        return [
            'tipo' => 'lectura', 'id' => null, 'codigo' => 'lectura', 'moneda' => ($c['moneda'] ?? 'PEN') === 'USD' ? 'USD' : 'PEN', 'tip_cam' => null,
            'tdocod' => array_key_exists((string) ($c['tipo'] ?? ''), Compras::DOCUMENTOS) ? (string) $c['tipo'] : '01',
            'serie' => mb_strtoupper(mb_substr(trim((string) ($c['serie'] ?? '')), 0, 4)), 'numero' => mb_substr(ltrim(preg_replace('/\D/', '', (string) ($c['numero'] ?? '')), '0'), 0, 8),
            'fecha' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($c['fecha'] ?? '')) ? $c['fecha'] : null,
            'id_almacen' => null, 'observaciones' => '',
            'proveedor' => ['tdicod' => strlen($ruc) === 11 ? '6' : (strlen($ruc) === 8 ? '1' : '0'), 'num' => $ruc, 'nom' => mb_strtoupper(trim((string) ($c['razon_emisor'] ?? ''))), 'dir' => ''],
            'items' => $items, 'pendientes' => $pendientes, 'avisos' => $avisos, 'total_leido' => round($total, 2), 'origen' => $c['origen'],
        ];
    }

    /**
     * El producto de la sede para cada línea: lo aprendido de ese proveedor, el mismo código, o el nombre parecido.
     *
     * @return array<int, ?object> por índice de línea
     */
    private static function emparejar(int $sucursal, string $ruc, array $lineas): array
    {
        $productos = DB::table('productos')->where('id_empresa_negocio', $sucursal)->where('proest', 'Activo')->whereIn('promocion', Compras::TIPOS_CON_STOCK)
            ->get(['IdProducto', 'procod', 'codigo_barra', 'pronom', 'control_lote', 'control_serie'])->keyBy('IdProducto');
        $porClave = $productos->groupBy(fn ($p) => ComprasDetalle::clave($p->pronom))->map(fn (Collection $g) => $g->first());
        $aprendido = DB::table('compras_lectura_productos')->where('id_empresa_negocio', $sucursal)->whereIn('ruc_emisor', [$ruc, ''])
            ->orderByRaw('ruc_emisor = ? DESC', [$ruc])->get()->unique('clave')->pluck('IdProducto', 'clave');

        $pares = [];
        foreach ($lineas as $i => $l) {
            $clave = ComprasDetalle::clave((string) $l['descripcion']);
            $codigo = trim((string) ($l['codigo'] ?? ''));
            $pares[$i] = $productos->get($aprendido[$clave] ?? 0)
                ?? ($codigo !== '' ? $productos->first(fn ($p) => $p->procod === $codigo || $p->codigo_barra === $codigo) : null)
                ?? $porClave->get($clave)
                ?? self::parecido($porClave, $clave);
        }

        return $pares;
    }

    private static function parecido(Collection $porClave, string $clave): ?object
    {
        $mejor = null;
        $puntaje = 0;
        foreach ($porClave as $claveProducto => $p) {
            similar_text($clave, (string) $claveProducto, $porcentaje);
            if ($porcentaje > $puntaje) {
                [$mejor, $puntaje] = [$p, $porcentaje];
            }
        }

        return $puntaje >= self::PARECIDO ? $mejor : null;
    }

    /**
     * Aprende lo que el usuario eligió para las líneas no reconocidas (al guardar la compra).
     *
     * @param  array<int, array{descripcion?: mixed, id?: mixed}>  $pares
     */
    public static function aprender(User $user, string $rucEmisor, array $pares): void
    {
        $sucursal = (int) $user->id_empresa_negocio;
        $validos = DB::table('productos')->where('id_empresa_negocio', $sucursal)->whereIn('IdProducto', array_map(fn ($p) => (int) ($p['id'] ?? 0), $pares))->pluck('IdProducto')->all();
        foreach (array_slice($pares, 0, 100) as $p) {
            if (! in_array((int) ($p['id'] ?? 0), $validos) || trim((string) ($p['descripcion'] ?? '')) === '') {
                continue;
            }
            DB::table('compras_lectura_productos')->updateOrInsert(
                ['id_empresa_negocio' => $sucursal, 'ruc_emisor' => preg_replace('/\D/', '', $rucEmisor), 'clave' => ComprasDetalle::clave((string) $p['descripcion'])],
                ['IdProducto' => (int) $p['id'], 'updated_at' => now(), 'created_at' => now()]);
        }
    }
}
