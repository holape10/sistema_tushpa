<?php

namespace App\Http\Controllers;

use App\Support\Clinica;
use App\Support\ClinicaFirmas;
use App\Support\ClinicaRecordatorios;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Recordatorios de citas: la lista de mañana (con su envío por WhatsApp de un clic), lo que ya salió solo y, para el
 * administrador, la configuración (hora, a quién, cuenta de WhatsApp Business y el celular/correo de cada doctor).
 */
class ClinicaRecordatorioController extends Controller
{
    private function suc(): int
    {
        return (int) Auth::user()->id_empresa_negocio;
    }

    public function index(Request $request): View
    {
        abort_unless(Clinica::usaClinica(Auth::user()), 403, 'No tienes acceso a la clínica.');
        $suc = $this->suc();
        $fecha = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->fecha) ? $request->fecha : now()->addDay()->toDateString();
        $cfg = ClinicaRecordatorios::config($suc);
        $negocio = (string) DB::table('empresa_negocios')->where('id_empresa_negocio', $suc)->value('nombre_comercial');
        $citas = ClinicaRecordatorios::citas($suc, $fecha);
        $enviados = DB::table('clinica_recordatorios')->where('id_empresa_negocio', $suc)->where('fecha_cita', $fecha)->where('estado', 'ENVIADO')->get();
        $wa = fn ($tel, $texto) => ($n = ClinicaFirmas::celular($tel)) ? 'https://wa.me/'.$n.'?text='.rawurlencode($texto) : null;

        return view('empresas.clinica.recordatorios', [
            'fecha' => $fecha, 'cfg' => $cfg, 'cuenta' => ClinicaRecordatorios::cuenta($cfg), 'hayCorreo' => ClinicaRecordatorios::hayCorreo(),
            'esAdmin' => Auth::user()->esAdmin(), 'negocio' => $negocio,
            'citas' => $citas->map(function ($c) use ($enviados, $wa, $negocio) {
                $c->enviado = $enviados->where('para', 'PACIENTE')->where('cit_id', $c->cit_id)->first();
                $c->wa = $wa($c->telefono, ClinicaRecordatorios::textoPaciente($c, $negocio));

                return $c;
            }),
            'doctores' => $citas->whereNotNull('doctor')->groupBy('doctor')->map(function ($suyas, $doctor) use ($enviados, $wa, $negocio) {
                $d = $suyas->first();

                return (object) ['id' => $doctor, 'nombre' => $d->doctor_nom, 'citas' => $suyas->count(), 'telefono' => $d->doctor_telefono, 'correo' => $d->doctor_correo,
                    'enviado' => $enviados->where('para', 'DOCTOR')->where('doctor', $doctor)->pluck('canal')->unique()->implode(' y '),
                    'wa' => $wa($d->doctor_telefono, ClinicaRecordatorios::textoDoctor($d->doctor_nom ?: 'doctor(a)', $suyas, $negocio))];
            })->values(),
            'personal' => DB::table('users as u')->join('role_user as r', 'r.user_IdUsuario', '=', 'u.IdUsuario')->where('u.id_empresa_negocio', $suc)->where('u.estusu', 1)
                ->whereIn('r.role_id', [2, 10])->select('u.IdUsuario', 'u.name', 'u.apeusu', 'u.telefono', 'u.correo')->distinct()->orderBy('u.apeusu')->get(),
            'registro' => DB::table('clinica_recordatorios as r')->leftJoin('citas as c', 'c.cit_id', '=', 'r.cit_id')->leftJoin('users as u', 'u.IdUsuario', '=', 'r.doctor')
                ->where('r.id_empresa_negocio', $suc)->orderByDesc('r.rec_id')->limit(40)
                ->get(['r.*', 'c.paciente', DB::raw("COALESCE(NULLIF(TRIM(u.apeusu), ''), u.name) as doctor_nom")]),
        ]);
    }

    public function guardar(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->esAdmin(), 403, 'Solo el administrador configura los recordatorios.');
        $d = $request->validate([
            'recordar_paciente' => 'boolean', 'recordar_doctor' => 'boolean', 'hora_envio' => 'required|date_format:H:i',
            'wa_token' => 'nullable|string|max:1000', 'wa_phone_id' => 'nullable|regex:/^\d{5,30}$/', 'wa_plantilla_paciente' => 'nullable|regex:/^[a-z0-9_]{1,80}$/',
            'wa_plantilla_doctor' => 'nullable|regex:/^[a-z0-9_]{1,80}$/', 'wa_idioma' => 'nullable|string|max:10', 'quitar_token' => 'boolean',
            'personal' => 'array', 'personal.*.IdUsuario' => 'required|integer', 'personal.*.telefono' => 'nullable|string|max:20', 'personal.*.correo' => 'nullable|email|max:120',
        ], [], ['wa_phone_id' => 'ID del número', 'wa_plantilla_paciente' => 'plantilla del paciente', 'wa_plantilla_doctor' => 'plantilla del doctor', 'personal.*.correo' => 'correo']);
        $suc = $this->suc();
        $fila = ['recordar_paciente' => (int) ($d['recordar_paciente'] ?? 0), 'recordar_doctor' => (int) ($d['recordar_doctor'] ?? 0), 'hora_envio' => $d['hora_envio'],
            'wa_phone_id' => $d['wa_phone_id'] ?? null, 'wa_plantilla_paciente' => $d['wa_plantilla_paciente'] ?? null,
            'wa_plantilla_doctor' => $d['wa_plantilla_doctor'] ?? null, 'wa_idioma' => ($d['wa_idioma'] ?? '') ?: 'es', 'updated_at' => now()];
        // El token no se muestra nunca: solo se reemplaza si escriben uno nuevo
        if (! empty($d['quitar_token'])) {
            $fila['wa_token'] = null;
        } elseif (trim((string) ($d['wa_token'] ?? '')) !== '') {
            $fila['wa_token'] = Crypt::encryptString(trim($d['wa_token']));
        }
        DB::table('clinica_config')->updateOrInsert(['id_empresa_negocio' => $suc], $fila + ['created_at' => now()]);
        foreach ($d['personal'] ?? [] as $p) {
            DB::table('users')->where('IdUsuario', $p['IdUsuario'])->where('id_empresa_negocio', $suc)
                ->update(['telefono' => trim((string) ($p['telefono'] ?? '')) ?: null, 'correo' => trim((string) ($p['correo'] ?? '')) ?: null]);
        }

        return response()->json(['ok' => true, 'mensaje' => 'Recordatorios guardados.']);
    }

    /** Prueba: manda la plantilla del paciente a un celular (para revisar que la cuenta de WhatsApp funciona) */
    public function probar(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->esAdmin(), 403);
        $d = $request->validate(['celular' => 'required|string|max:20']);
        $cuenta = ClinicaRecordatorios::cuenta(ClinicaRecordatorios::config($this->suc()));
        $numero = ClinicaFirmas::celular($d['celular']);
        if (! $cuenta || ! $cuenta['plantilla_paciente']) {
            return response()->json(['ok' => false, 'mensaje' => 'Primero guarda la cuenta de WhatsApp y la plantilla del paciente.']);
        }
        if (! $numero) {
            return response()->json(['ok' => false, 'mensaje' => 'Escribe un celular válido (9 dígitos).']);
        }
        $negocio = (string) DB::table('empresa_negocios')->where('id_empresa_negocio', $this->suc())->value('nombre_comercial');
        [$ok, $detalle] = ClinicaRecordatorios::whatsapp($cuenta, $numero, $cuenta['plantilla_paciente'],
            ['Paciente de prueba', $negocio, ClinicaRecordatorios::fechaTexto(now()->addDay()->toDateString()), '10:00 am']);

        return response()->json(['ok' => $ok, 'mensaje' => $ok ? '✔ Mensaje de prueba enviado. Revisa ese WhatsApp.' : $detalle]);
    }

    /** Anota un recordatorio enviado a mano (botón de WhatsApp) para que no se repita */
    public function manual(Request $request): JsonResponse
    {
        abort_unless(Clinica::usaClinica(Auth::user()), 403);
        $d = $request->validate(['para' => 'required|in:PACIENTE,DOCTOR', 'cit_id' => 'nullable|integer', 'doctor' => 'nullable|integer', 'fecha' => 'required|date']);
        DB::table('clinica_recordatorios')->insert(['para' => $d['para'], 'cit_id' => $d['cit_id'] ?? null, 'doctor' => $d['doctor'] ?? null, 'fecha_cita' => $d['fecha'],
            'canal' => 'MANUAL', 'estado' => 'ENVIADO', 'detalle' => 'Enviado a mano por '.(Auth::user()->apeusu ?: Auth::user()->name),
            'id_empresa_negocio' => $this->suc(), 'creado' => now()]);

        return response()->json(['ok' => true]);
    }
}
