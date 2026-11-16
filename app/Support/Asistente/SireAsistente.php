<?php

namespace App\Support\Asistente;

use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Exceptions\AuthenticationException;
use Anthropic\Core\Exceptions\RateLimitException;
use App\Models\User;
use App\Support\Contabilidad\SireAuditoria;
use App\Support\Contabilidad\SireDbf;

/**
 * Asistente IA de «Compras con detalle»: el contador pregunta en caliente sobre las compras del mes ("¿por qué no cuadra
 * la nota de crédito FC02-168?", "¿cuánto suman las facturas de PETROPERÚ?") y responde con los datos del mes: cada
 * comprobante tal como va al .dbf, lo que dice SUNAT, lo que dice su XML y la auditoría. No cambia nada.
 */
class SireAsistente
{
    private const HISTORIAL = 8;

    private const SISTEMA = <<<'TXT'
Eres un contador peruano experto en el SIRE (Registro de Compras Electrónico, RCE) que ayuda a cuadrar las compras de UN
mes de una empresa antes de llevarlas a su sistema contable en FoxPro (archivo almaingr.dbf). Respondes en español,
directo y breve, con los números exactos de los datos que te doy. No inventes datos que no estén abajo.

Cómo funcionan los datos:
- Cada comprobante tiene: lo que se lleva al .dbf (montos en la moneda del comprobante), el total de la propuesta de SUNAT,
  el total de su XML (lo que dice el proveedor) y si se lleva o no.
- Las notas de crédito (tipo 07) restan: en la pantalla y en estos datos van en NEGATIVO, como las da SUNAT; su XML está
  en positivo. En el almaingr.dbf se escriben en POSITIVO porque el sistema contable las resta por su tipo 07.
- En dólares: los montos van en dólares y el .dbf lleva su tipo de cambio (el sistema contable los pasa a soles). Si
  SUNAT los trajo ya en soles, el sistema los pasó a dólares como su XML ("venía en S/").
- "No cuadra" = las partes (base + IGV + no gravado + otros) no suman el total, o el IGV no está junto a su base.
- La auditoría suma por tipo de documento, en soles, lo que se lleva, SUNAT y los XML, y lista por qué no cuadra.

Cuando expliques una diferencia, di qué comprobante(s) la causan, cuánto y qué hacer en la pantalla (por ejemplo:
"desmarca Llevar", "usa Volver a los montos de SUNAT", "corrige el total a 36,689.50"). Usa tablas cortas si ayudan.
TXT;

    /** Por qué no se puede preguntar ahora (null = sí se puede) */
    public static function bloqueo(): ?string
    {
        return Asistente::bloqueoComun();
    }

    /**
     * @param  list<array{role: string, content: string}>  $historial
     * @return array{respuesta: string, costo: float}
     *
     * @throws \RuntimeException si la IA no está disponible
     */
    public static function preguntar(User $user, string $ruc, string $periodo, array $historial, string $pregunta): array
    {
        if ($motivo = self::bloqueo()) {
            throw new \RuntimeException($motivo);
        }
        $comprobantes = SireDbf::delPeriodo($ruc, 'compras', $periodo)['comprobantes'];
        $datos = self::datos($periodo, $comprobantes);
        $modelo = Asistente::modelo();
        $mensajes = [...array_map(fn ($m) => ['role' => $m['role'] === 'assistant' ? 'assistant' : 'user', 'content' => mb_substr((string) $m['content'], 0, 4000)],
            array_slice($historial, -self::HISTORIAL)), ['role' => 'user', 'content' => $pregunta]];
        $uso = ['entrada' => 0, 'salida' => 0, 'lectura' => 0, 'escritura' => 0];
        $respuesta = '';
        $error = null;
        try {
            $r = Asistente::llamar(['model' => $modelo, 'maxTokens' => 6000, 'outputConfig' => ['effort' => 'medium'], 'messages' => $mensajes,
                'system' => [['type' => 'text', 'text' => self::SISTEMA], ['type' => 'text', 'cacheControl' => ['type' => 'ephemeral'], 'text' => $datos]]]);
            $uso = ['entrada' => (int) ($r->usage->inputTokens ?? 0), 'salida' => (int) ($r->usage->outputTokens ?? 0),
                'lectura' => (int) ($r->usage->cacheReadInputTokens ?? 0), 'escritura' => (int) ($r->usage->cacheCreationInputTokens ?? 0)];
            $respuesta = trim(collect($r->content)->filter(fn ($b) => $b->type === 'text')->map(fn ($b) => $b->text)->implode("\n\n"))
                ?: 'No encontré una respuesta. ¿Puedes preguntarlo de otra forma?';
            if (($r->stopReason ?? null) === 'max_tokens') {
                $respuesta .= "\n\n_(La respuesta quedó cortada: pide una versión más corta.)_";
            }
        } catch (AuthenticationException) {
            $error = 'La clave del asistente no es válida.';
        } catch (RateLimitException) {
            $error = 'El asistente está ocupado. Intenta en un minuto.';
        } catch (APIStatusException $e) {
            $error = 'El asistente no respondió ('.$e->getMessage().').';
        } catch (APIConnectionException) {
            $error = 'No se pudo conectar con el asistente. Revisa la conexión e intenta de nuevo.';
        }
        $costo = Asistente::anotar($user, 'SIRE compras '.$periodo.': '.$pregunta, $modelo, $uso, 0, $error);
        if ($error) {
            throw new \RuntimeException($error);
        }

        return ['respuesta' => $respuesta, 'costo' => $costo];
    }

    /** Los datos del mes en texto (cada comprobante y la auditoría), para que el asistente los lea */
    public static function datos(string $periodo, array $comprobantes): string
    {
        $n = fn ($v) => number_format((float) $v, 2, '.', '');
        $lineas = ["PERIODO {$periodo} — COMPRAS (".count($comprobantes).' comprobantes)', '',
            'tipo|comprobante|fecha|ruc|proveedor|moneda|tc|se_lleva|editado_a_mano|base|igv|no_gravado|otros_isc|total_dbf|total_sunat|total_xml|moneda_xml|no_cuadra_por|referencia|cta_cargo|cta_abono|aviso'];
        foreach ($comprobantes as $c) {
            $m = $c['montos'];
            $lineas[] = implode('|', [$c['tipo'], $c['serie'].'-'.$c['numero'], $c['fecha'], $c['ruc'], mb_substr($c['nombre'], 0, 50), $c['moneda'], $c['tc'],
                $c['excluido'] ? 'NO' : 'SI', $c['editado'] ? 'SI' : 'NO',
                $n(($m['base_dg'] ?? 0) + ($m['base_dgng'] ?? 0) + ($m['base_dng'] ?? 0)), $n(($m['igv_dg'] ?? 0) + ($m['igv_dgng'] ?? 0) + ($m['igv_dng'] ?? 0)),
                $n($m['no_gravado'] ?? 0), $n(($m['isc'] ?? 0) + ($m['otros'] ?? 0)), $n($m['total']),
                $c['manual'] ? 'agregado a mano' : $n($c['total_sunat'] ?? $m['total']), ($c['xml_total'] ?? null) === null ? 'sin XML' : $n($c['xml_total']), $c['xml_moneda'] ?? '',
                abs((float) $c['diferencia']) >= 0.01 ? $n($c['diferencia']) : '', $c['ref'] ? $c['ref']['tipo'].' '.$c['ref']['serie'].'-'.$c['ref']['numero'] : '',
                $c['ctacargo'], $c['ctaabono'], (string) ($c['alerta'] ?? '')]);
        }
        $a = SireAuditoria::calcular('compras', $comprobantes);
        $lineas[] = '';
        $lineas[] = 'AUDITORÍA POR TIPO (en soles): tipo|cantidad|se_llevan|base|igv|no_gravado|otros|total_dbf|total_sunat|dif_sunat|total_xml|dif_xml';
        foreach ([...array_values($a['filas']), $a['todos']] as $f) {
            $lineas[] = implode('|', [$f['tipo'], $f['cantidad'], $f['llevan'], $n($f['base']), $n($f['igv']), $n($f['no_gravado']), $n($f['otros']),
                $n($f['total']), $n($f['sunat']), $n($f['dif_sunat']), $n($f['xml_de_llevan']), $n($f['dif_xml'])]);
        }
        $lineas[] = '';
        $lineas[] = 'POR QUÉ NO CUADRA (comprobante|grave|explicación|efecto en soles):';
        foreach ($a['motivos'] as $m) {
            $lineas[] = implode('|', [$m['tipo'].' '.$m['comprobante'], $m['grave'] ? 'SI' : 'no', $m['texto'], $n($m['efecto'])]);
        }

        return implode("\n", $lineas);
    }
}
