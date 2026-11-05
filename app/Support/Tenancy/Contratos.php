<?php

namespace App\Support\Tenancy;

use App\Models\Central\Cliente;
use App\Models\Central\Marca;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Contrato de servicio de cada empresa con el dueño del sistema:
 *  1. El panel lo genera (mensual o anual, con el monto de su plan) y le manda el enlace a la empresa.
 *  2. La empresa, con ese enlace (sin usuario ni contraseña), pone quién firma, sube su DNI por ambas caras, descarga
 *     el contrato en PDF y lo firma en la pantalla (celular, tablet o PC) o sube el contrato firmado a mano.
 *  3. El panel revisa los documentos y lo aprueba u observa (si lo observa, la empresa corrige con el mismo enlace).
 * La firma en pantalla queda con su constancia: fecha, IP, equipo y la huella (sha256) del texto firmado.
 */
class Contratos
{
    public const PERIODOS = ['MENSUAL' => 'Mensual', 'ANUAL' => 'Anual'];

    public const ESTADOS = ['PENDIENTE' => 'Por firmar', 'ENVIADO' => 'Firmado, por revisar', 'APROBADO' => 'Aprobado', 'OBSERVADO' => 'Observado', 'ANULADO' => 'Anulado'];

    /** Tipos de archivo que se aceptan para el DNI y el contrato escaneado */
    public const MIMES = 'jpg,jpeg,png,webp,pdf';

    private static function tabla()
    {
        return DB::connection('central')->table('contratos');
    }

    /** El contrato vigente de la empresa (el último que no se anuló) */
    public static function deCliente(int $clienteId): ?object
    {
        return self::tabla()->where('cliente_id', $clienteId)->where('estado', '!=', 'ANULADO')->latest('id')->first();
    }

    public static function porToken(string $token): ?object
    {
        return preg_match('/^[A-Za-z0-9]{40}$/', $token) ? self::tabla()->where('token', $token)->where('estado', '!=', 'ANULADO')->first() : null;
    }

    /** Monto sugerido según el plan: el mensual o el del año */
    public static function montoSugerido(Cliente $cliente, string $periodo): float
    {
        $plan = $cliente->planContratado;

        return $plan ? ($periodo === 'ANUAL' ? $plan->precioAnual() : round((float) $plan->precio, 2)) : 0.0;
    }

    /** Implementación sugerida según el plan (pago único al empezar) */
    public static function implementacionSugerida(Cliente $cliente): float
    {
        return round((float) ($cliente->planContratado?->implementacion ?? 0), 2);
    }

    /** Genera un contrato nuevo (el anterior sin aprobar queda anulado: vale el último) */
    public static function generar(Cliente $cliente, string $periodo, float $monto, ?int $superadmin, float $implementacion = 0): object
    {
        self::tabla()->where('cliente_id', $cliente->id)->whereIn('estado', ['PENDIENTE', 'OBSERVADO', 'ENVIADO'])
            ->update(['estado' => 'ANULADO', 'updated_at' => now()]);
        $id = self::tabla()->insertGetId([
            'cliente_id' => $cliente->id, 'token' => Str::random(40), 'periodo' => $periodo, 'monto' => round($monto, 2),
            'implementacion' => $implementacion > 0 ? round($implementacion, 2) : null,
            'plan' => $cliente->planContratado?->nombre ?? $cliente->plan, 'estado' => 'PENDIENTE',
            'firmante' => $cliente->representante ?: $cliente->contacto_nombre, 'firmante_dni' => $cliente->representante_dni,
            'correo' => $cliente->contacto_correo, 'telefono' => $cliente->contacto_telefono,
            'creado_por' => $superadmin, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return self::tabla()->find($id);
    }

    /** El enlace que se le manda a la empresa: en tushpa.app o, si es de una marca, en el dominio de su marca */
    public static function enlace(object $contrato): string
    {
        $marca = Cliente::find($contrato->cliente_id)?->marca;

        return $marca ? config('tenancy.esquema').'://'.$marca->dominio.'/contrato/'.$contrato->token : route('sitio.contrato', $contrato->token);
    }

    /** Número que sale en el contrato: CT-00012 */
    public static function numero(object $contrato): string
    {
        return 'CT-'.str_pad((string) $contrato->id, 5, '0', STR_PAD_LEFT);
    }

    /** Puede la empresa completar o corregir el contrato (no cuando ya se aprobó o está esperando revisión) */
    public static function editable(object $contrato): bool
    {
        return in_array($contrato->estado, ['PENDIENTE', 'OBSERVADO'], true);
    }

    /**
     * La empresa completa el contrato: datos de quién firma, DNI (ambas caras) y la firma en pantalla o el contrato
     * firmado escaneado. Los archivos que ya subió antes (si lo observaron) se conservan si no manda otros.
     *
     * @param  array{firmante: string, firmante_dni: string, firmante_cargo: ?string, correo: ?string, telefono: ?string}  $datos
     */
    public static function completar(object $contrato, array $datos, ?UploadedFile $dniFrente, ?UploadedFile $dniReverso,
        ?string $firmaPng, ?UploadedFile $escaneado, string $ip, ?string $equipo): object
    {
        $cambios = [
            'firmante' => mb_strtoupper(trim($datos['firmante'])), 'firmante_dni' => trim($datos['firmante_dni']),
            'firmante_cargo' => mb_strtoupper(trim((string) ($datos['firmante_cargo'] ?? ''))) ?: null,
            'correo' => $datos['correo'] ?? null, 'telefono' => $datos['telefono'] ?? null,
        ];
        foreach (['dni_frente' => $dniFrente, 'dni_reverso' => $dniReverso, 'escaneado' => $escaneado] as $campo => $archivo) {
            if ($archivo) {
                self::borrar($contrato->{$campo});
                $cambios[$campo] = self::guardarArchivo($contrato, $campo, $archivo);
            }
        }
        if ($firmaPng !== null) {
            self::borrar($contrato->firma);
            $ruta = config('contrato.carpeta').'/'.$contrato->id.'/firma-'.Str::random(8).'.png';
            Storage::disk('local')->put($ruta, $firmaPng);
            $cambios['firma'] = $ruta;
        }
        $final = (object) array_merge((array) $contrato, $cambios);
        if (! $final->dni_frente || ! $final->dni_reverso) {
            throw new \RuntimeException('Sube tu DNI por las dos caras.');
        }
        if (! $final->firma && ! $final->escaneado) {
            throw new \RuntimeException('Firma el contrato en el recuadro o sube el contrato firmado.');
        }
        // Constancia: quién, cuándo, desde dónde y qué texto firmó (si el contrato cambia, la huella ya no coincide)
        $cambios += ['estado' => 'ENVIADO', 'observacion' => null, 'firmado_el' => now(), 'firmado_ip' => $ip,
            'firmado_equipo' => mb_substr((string) $equipo, 0, 255) ?: null, 'updated_at' => now()];
        $final = (object) array_merge((array) $contrato, $cambios);
        $cambios['huella'] = hash('sha256', self::texto($final));
        self::tabla()->where('id', $contrato->id)->update($cambios);
        // La empresa sin representante registrado se queda con el de quien firmó
        Cliente::where('id', $contrato->cliente_id)->whereNull('representante')
            ->update(['representante' => $cambios['firmante'], 'representante_dni' => $cambios['firmante_dni']]);

        return self::tabla()->find($contrato->id);
    }

    /** El panel revisa: aprueba o lo devuelve con la observación (la empresa corrige con el mismo enlace) */
    public static function revisar(object $contrato, bool $aprobar, ?string $observacion, int $superadmin): void
    {
        self::tabla()->where('id', $contrato->id)->update([
            'estado' => $aprobar ? 'APROBADO' : 'OBSERVADO', 'observacion' => $aprobar ? null : mb_substr(trim((string) $observacion), 0, 255),
            'revisado_por' => $superadmin, 'revisado_el' => now(), 'updated_at' => now(),
        ]);
    }

    private static function guardarArchivo(object $contrato, string $campo, UploadedFile $archivo): string
    {
        $extension = strtolower($archivo->guessExtension() ?: 'bin');
        if (! in_array($extension, explode(',', self::MIMES), true)) {
            throw new \RuntimeException('Sube una foto (JPG, PNG) o un PDF.');
        }

        return $archivo->storeAs(config('contrato.carpeta').'/'.$contrato->id, $campo.'-'.Str::random(8).'.'.$extension, 'local');
    }

    private static function borrar(?string $ruta): void
    {
        if ($ruta && str_starts_with($ruta, config('contrato.carpeta').'/')) {
            Storage::disk('local')->delete($ruta);
        }
    }

    /** Ruta en disco de un archivo del contrato (para mostrarlo en el panel), o null */
    public static function archivo(object $contrato, string $campo): ?string
    {
        $ruta = in_array($campo, ['dni_frente', 'dni_reverso', 'firma', 'escaneado'], true) ? $contrato->{$campo} : null;

        return $ruta && Storage::disk('local')->exists($ruta) ? Storage::disk('local')->path($ruta) : null;
    }

    /** Lo que se llena en la plantilla del contrato */
    public static function datos(object $contrato): array
    {
        $cliente = Cliente::find($contrato->cliente_id);
        $firma = $contrato->firma && Storage::disk('local')->exists($contrato->firma)
            ? 'data:image/png;base64,'.base64_encode(Storage::disk('local')->get($contrato->firma)) : null;
        $marca = $cliente?->marca_id ? $cliente->marca : null;
        $logo = public_path($marca && $marca->logo ? $marca->logo : config('contrato.logo'));

        return [
            'contrato' => $contrato, 'cliente' => $cliente, 'numero' => self::numero($contrato), 'proveedor' => self::proveedor($marca),
            'periodo' => self::PERIODOS[$contrato->periodo] ?? $contrato->periodo, 'firma' => $firma,
            'logo' => is_file($logo) ? 'data:image/png;base64,'.base64_encode(file_get_contents($logo)) : null,
        ];
    }

    /**
     * Quién da el servicio: el dueño del sistema (config/contrato.php) o, en las empresas de una marca, el revendedor.
     *
     * @return array{razon_social: string, ruc: string, direccion: string, representante: string, dni_representante: string, marca: string, web: string}
     */
    public static function proveedor(?Marca $marca): array
    {
        if (! $marca) {
            return config('contrato.proveedor');
        }

        return [
            'razon_social' => (string) ($marca->razon_social ?: $marca->nombre), 'ruc' => (string) $marca->ruc, 'direccion' => (string) $marca->direccion,
            'representante' => (string) $marca->representante, 'dni_representante' => (string) $marca->representante_dni,
            'marca' => (string) $marca->nombre, 'web' => 'www.'.$marca->dominio,
        ];
    }

    /** El texto del contrato sin formato (para la huella de lo que se firmó) */
    public static function texto(object $contrato): string
    {
        $datos = self::datos($contrato);
        $datos['firma'] = null;
        $datos['logo'] = null;

        return trim(preg_replace('/\s+/', ' ', strip_tags(view('contratos.cuerpo', $datos)->render())));
    }

    /** El contrato en PDF: sin firmar (para descargar y firmar a mano) o con la firma en pantalla y su constancia */
    public static function pdf(object $contrato): string
    {
        $opciones = new Options;
        $opciones->set('defaultFont', 'DejaVu Sans');
        $opciones->set('isRemoteEnabled', false);
        $pdf = new Dompdf($opciones);
        $pdf->loadHtml(view('contratos.pdf', self::datos($contrato))->render(), 'UTF-8');
        $pdf->setPaper('A4');
        $pdf->render();

        return $pdf->output();
    }

    public static function nombrePdf(object $contrato): string
    {
        $cliente = Cliente::find($contrato->cliente_id);

        return 'Contrato '.self::numero($contrato).' '.Str::slug($cliente?->razon_social ?? 'TUSHPA').'.pdf';
    }
}
