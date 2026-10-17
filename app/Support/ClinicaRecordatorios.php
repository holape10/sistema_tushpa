<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

/**
 * Recordatorios automáticos de las citas de mañana (corre solo cada 15 minutos; envía desde la hora elegida):
 *  - al paciente, por WhatsApp (plantilla aprobada por Meta en la API de WhatsApp Business)
 *  - al doctor tratante, su lista de citas por WhatsApp y/o correo
 * El WhatsApp sale con la cuenta de la clínica (Configuración → Recordatorios) o, si no tiene, con la del sistema
 * (WHATSAPP_TOKEN / WHATSAPP_PHONE_ID en el .env). Sin WhatsApp configurado, la pantalla de Recordatorios deja enviarlos
 * con un clic (wa.me). Cada envío queda registrado y no se repite.
 */
class ClinicaRecordatorios
{
    public const INTENTOS = 3;

    /** Configuración de la sucursal (con valores por defecto si aún no se guardó) */
    public static function config(int $suc): object
    {
        $c = Schema::hasTable('clinica_config') ? DB::table('clinica_config')->where('id_empresa_negocio', $suc)->first() : null;

        return (object) [
            'recordar_paciente' => (bool) ($c->recordar_paciente ?? true),
            'recordar_doctor' => (bool) ($c->recordar_doctor ?? true),
            'hora_envio' => $c->hora_envio ?? '18:00',
            'wa_token' => self::descifrar($c->wa_token ?? null),
            'wa_phone_id' => $c->wa_phone_id ?? null,
            'wa_plantilla_paciente' => $c->wa_plantilla_paciente ?? null,
            'wa_plantilla_doctor' => $c->wa_plantilla_doctor ?? null,
            'wa_idioma' => $c->wa_idioma ?? 'es',
            'propia' => (bool) ($c->wa_token ?? null),
        ];
    }

    private static function descifrar(?string $v): ?string
    {
        try {
            return $v ? Crypt::decryptString($v) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** Cuenta de WhatsApp que se usa: la de la clínica o la del sistema (.env) */
    public static function cuenta(object $cfg): ?array
    {
        $propia = $cfg->wa_token && $cfg->wa_phone_id;
        $token = $propia ? $cfg->wa_token : config('services.whatsapp.token');
        $phone = $propia ? $cfg->wa_phone_id : config('services.whatsapp.phone_id');
        if (! $token || ! $phone) {
            return null;
        }

        return ['token' => $token, 'phone_id' => $phone, 'idioma' => $propia ? ($cfg->wa_idioma ?: 'es') : (config('services.whatsapp.idioma') ?: 'es'),
            'plantilla_paciente' => $propia ? $cfg->wa_plantilla_paciente : (config('services.whatsapp.plantilla_paciente') ?: null),
            'plantilla_doctor' => $propia ? $cfg->wa_plantilla_doctor : (config('services.whatsapp.plantilla_doctor') ?: null)];
    }

    /** ¿Hay correo de verdad? (en "log" o "array" los correos no salen) */
    public static function hayCorreo(): bool
    {
        return ! in_array(config('mail.default'), ['log', 'array', null], true);
    }

    /** Envía una plantilla de WhatsApp con sus variables {{1}}, {{2}}… */
    public static function whatsapp(array $cuenta, string $numero, string $plantilla, array $variables): array
    {
        try {
            $r = Http::withToken($cuenta['token'])->timeout(20)->post('https://graph.facebook.com/v21.0/'.$cuenta['phone_id'].'/messages', [
                'messaging_product' => 'whatsapp', 'to' => $numero, 'type' => 'template',
                'template' => ['name' => $plantilla, 'language' => ['code' => $cuenta['idioma']],
                    'components' => [['type' => 'body', 'parameters' => array_map(fn ($v) => ['type' => 'text', 'text' => mb_substr(preg_replace('/\s+/', ' ', (string) $v), 0, 900)], array_values($variables))]]],
            ]);
        } catch (\Throwable $e) {
            return [false, mb_substr('Sin conexión con WhatsApp: '.$e->getMessage(), 0, 250)];
        }
        if ($r->successful() && $r->json('messages.0.id')) {
            return [true, 'id '.$r->json('messages.0.id')];
        }

        return [false, mb_substr('WhatsApp: '.($r->json('error.message') ?: 'error '.$r->status()), 0, 250)];
    }

    public static function fechaTexto(string $fecha): string
    {
        return Carbon::parse($fecha)->locale('es')->isoFormat('dddd D [de] MMMM');
    }

    public static function horaTexto(string $hora): string
    {
        return Carbon::parse($hora)->format('g:i a');
    }

    /** Citas de un día que aún se van a atender, con el doctor */
    public static function citas(int $suc, string $fecha)
    {
        return DB::table('citas as c')->leftJoin('users as u', 'u.IdUsuario', '=', 'c.doctor')->leftJoin('especialidad as e', 'e.esp_id', '=', 'c.esp_id')
            ->where('c.id_empresa_negocio', $suc)->where('c.fecha', $fecha)->whereIn('c.estado', ['PROGRAMADA', 'CONFIRMADA'])
            ->orderBy('c.hora')->get(['c.*', 'e.esp_des', 'u.telefono as doctor_telefono', 'u.correo as doctor_correo',
                DB::raw("COALESCE(NULLIF(TRIM(u.apeusu), ''), u.name) as doctor_nom")]);
    }

    /** Mensajes de texto (para el WhatsApp manual y el correo) */
    public static function textoPaciente(object $c, string $negocio): string
    {
        return "Hola {$c->paciente}, le recordamos su cita en {$negocio} el ".self::fechaTexto($c->fecha).' a las '.self::horaTexto($c->hora)
            .($c->doctor_nom ? " con {$c->doctor_nom}" : '').'. Por favor confírmenos su asistencia. ¡Le esperamos!';
    }

    public static function listaDoctor($citas): string
    {
        return $citas->map(fn ($c) => self::horaTexto($c->hora).' '.$c->paciente.($c->motivo ? ' ('.$c->motivo.')' : ''))->implode('; ');
    }

    public static function textoDoctor(string $doctor, $citas, string $negocio): string
    {
        return "Hola {$doctor}, el ".self::fechaTexto($citas->first()->fecha)." tienes {$citas->count()} cita(s) en {$negocio}: ".self::listaDoctor($citas).'.';
    }

    /** ¿Ya se envió? (o falló demasiadas veces) */
    private static function hecho(int $suc, string $para, string $fecha, ?int $citId, ?int $doctor, string $canal): bool
    {
        $q = DB::table('clinica_recordatorios')->where('id_empresa_negocio', $suc)->where('para', $para)->where('fecha_cita', $fecha)->where('canal', $canal)
            ->when($citId, fn ($q) => $q->where('cit_id', $citId))->when($doctor, fn ($q) => $q->where('doctor', $doctor));

        return (clone $q)->where('estado', 'ENVIADO')->exists() || (clone $q)->where('estado', 'ERROR')->count() >= self::INTENTOS;
    }

    private static function anotar(int $suc, string $para, string $fecha, ?int $citId, ?int $doctor, string $canal, ?string $destino, bool $ok, string $detalle): void
    {
        DB::table('clinica_recordatorios')->insert(['para' => $para, 'cit_id' => $citId, 'doctor' => $doctor, 'fecha_cita' => $fecha, 'canal' => $canal,
            'destino' => $destino, 'estado' => $ok ? 'ENVIADO' : 'ERROR', 'detalle' => mb_substr($detalle, 0, 255), 'id_empresa_negocio' => $suc, 'creado' => now()]);
    }

    /**
     * Envía lo pendiente de la empresa conectada (todas sus sucursales con citas mañana).
     *
     * @return array{pacientes: int, doctores: int, errores: int}
     */
    public static function ejecutar(?Carbon $ahora = null): array
    {
        $ahora ??= now();
        $res = ['pacientes' => 0, 'doctores' => 0, 'errores' => 0];
        if (! Schema::hasTable('clinica_recordatorios') || ! Schema::hasTable('citas')) {
            return $res;
        }
        $fecha = $ahora->copy()->addDay()->toDateString();
        $sucursales = DB::table('citas')->where('fecha', $fecha)->whereIn('estado', ['PROGRAMADA', 'CONFIRMADA'])->distinct()->pluck('id_empresa_negocio');
        foreach ($sucursales as $suc) {
            $cfg = self::config((int) $suc);
            if ($ahora->format('H:i') < $cfg->hora_envio) {
                continue;
            }
            $negocio = (string) DB::table('empresa_negocios')->where('id_empresa_negocio', $suc)->value('nombre_comercial');
            $cuenta = self::cuenta($cfg);
            $citas = self::citas((int) $suc, $fecha);

            // Pacientes
            if ($cfg->recordar_paciente && $cuenta && $cuenta['plantilla_paciente']) {
                foreach ($citas as $c) {
                    $numero = ClinicaFirmas::celular($c->telefono);
                    if (! $numero || self::hecho((int) $suc, 'PACIENTE', $fecha, $c->cit_id, null, 'WHATSAPP')) {
                        continue;
                    }
                    [$ok, $detalle] = self::whatsapp($cuenta, $numero, $cuenta['plantilla_paciente'],
                        [$c->paciente, $negocio, self::fechaTexto($c->fecha), self::horaTexto($c->hora)]);
                    self::anotar((int) $suc, 'PACIENTE', $fecha, $c->cit_id, null, 'WHATSAPP', $numero, $ok, $detalle);
                    $res[$ok ? 'pacientes' : 'errores']++;
                }
            }

            // Doctores: su lista del día siguiente
            if ($cfg->recordar_doctor) {
                foreach ($citas->whereNotNull('doctor')->groupBy('doctor') as $doctor => $suyas) {
                    $d = $suyas->first();
                    $nombre = $d->doctor_nom ?: 'doctor(a)';
                    $numero = ClinicaFirmas::celular($d->doctor_telefono);
                    if ($cuenta && $cuenta['plantilla_doctor'] && $numero && ! self::hecho((int) $suc, 'DOCTOR', $fecha, null, (int) $doctor, 'WHATSAPP')) {
                        [$ok, $detalle] = self::whatsapp($cuenta, $numero, $cuenta['plantilla_doctor'],
                            [$nombre, self::fechaTexto($fecha), (string) $suyas->count(), self::listaDoctor($suyas)]);
                        self::anotar((int) $suc, 'DOCTOR', $fecha, null, (int) $doctor, 'WHATSAPP', $numero, $ok, $detalle);
                        $res[$ok ? 'doctores' : 'errores']++;
                    }
                    if (self::hayCorreo() && filter_var($d->doctor_correo, FILTER_VALIDATE_EMAIL) && ! self::hecho((int) $suc, 'DOCTOR', $fecha, null, (int) $doctor, 'CORREO')) {
                        try {
                            Mail::raw(self::textoDoctor($nombre, $suyas, $negocio)."\n\n".$suyas->map(fn ($c) => '• '.self::horaTexto($c->hora).' — '.$c->paciente
                                .($c->motivo ? ' · '.$c->motivo : '').($c->telefono ? ' · Tel. '.$c->telefono : ''))->implode("\n"),
                                fn ($m) => $m->to($d->doctor_correo)->subject('Tus citas de mañana · '.$negocio));
                            [$ok, $detalle] = [true, 'correo enviado'];
                        } catch (\Throwable $e) {
                            [$ok, $detalle] = [false, 'Correo: '.$e->getMessage()];
                        }
                        self::anotar((int) $suc, 'DOCTOR', $fecha, null, (int) $doctor, 'CORREO', $d->doctor_correo, $ok, $detalle);
                        $res[$ok ? 'doctores' : 'errores']++;
                    }
                }
            }
        }

        return $res;
    }
}
