<?php
namespace App\Http\Controllers;

use App\Support\Clinica;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB};

/** Agenda de citas por doctor y día; recepción programa y cobra, el doctor atiende */
class AgendaController extends Controller
{
    private function suc(): int
    {
        return (int) Auth::user()->id_empresa_negocio;
    }

    private function usa(): void
    {
        abort_unless(Clinica::usaClinica(Auth::user()), 403, 'No tienes acceso a la clínica.');
    }

    public function index(Request $request)
    {
        $this->usa();
        $suc = $this->suc();
        $user = Auth::user();
        return view('empresas.clinica.agenda', [
            'fecha' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->fecha) ? $request->fecha : now()->toDateString(),
            'doctores' => Clinica::doctores($suc),
            'especialidades' => DB::table('especialidad')->where('id_empresa_negocio', $suc)->where('activo', 1)->orderBy('esp_des')->get(),
            'porCobrar' => $user->esAdminOCaja() ? Clinica::porCobrar($suc) : collect(),
            'veClinico' => Clinica::veClinico($user),
            'puedeCobrar' => $user->esAdminOCaja(),
            'yo' => Clinica::esDoctor($user) ? $user->IdUsuario : null,
            'hayVeterinaria' => DB::table('especialidad')->where('id_empresa_negocio', $suc)->where('veterinaria', 1)->exists(),
        ]);
    }

    public function datos(Request $request)
    {
        $this->usa();
        $fecha = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->fecha) ? $request->fecha : now()->toDateString();
        $citas = DB::table('citas as c')->leftJoin('users as u', 'u.IdUsuario', '=', 'c.doctor')->leftJoin('especialidad as e', 'e.esp_id', '=', 'c.esp_id')
            ->leftJoin('historia_clinica as h', 'h.his_cli_id', '=', 'c.his_cli_id')
            ->where('c.id_empresa_negocio', $this->suc())->where('c.fecha', $fecha)->orderBy('c.hora')
            ->get(['c.*', 'e.esp_des', 'h.his_cli_cod', DB::raw("TRIM(CONCAT(u.name, ' ', u.apeusu)) as doctor_nom")]);
        return response()->json(['fecha' => $fecha, 'citas' => $citas]);
    }

    public function guardar(Request $request)
    {
        $this->usa();
        $d = $request->validate([
            'cit_id' => 'nullable|integer', 'his_cli_id' => 'nullable|integer', 'doctor' => 'nullable|integer', 'esp_id' => 'nullable|integer',
            'fecha' => 'required|date', 'hora' => 'required|date_format:H:i', 'duracion' => 'required|integer|min:5|max:480',
            'motivo' => 'nullable|string|max:200', 'telefono' => 'nullable|string|max:20',
            'nuevo' => 'nullable|array',
        ], [], ['hora' => 'hora']);
        $suc = $this->suc();
        $user = Auth::user();

        try {
            return DB::transaction(function () use ($d, $suc, $user, $request) {
                // Paciente: uno que ya tiene historia, o uno nuevo (se le abre la historia aquí mismo)
                $hisId = $d['his_cli_id'] ?? null;
                if (!$hisId) {
                    $nuevo = validator($d['nuevo'] ?? [], Clinica::REGLAS_PACIENTE, [], ['clinum' => 'DNI', 'clinom' => 'nombre', 'mascota' => 'nombre de la mascota'])->validate();
                    $hisId = Clinica::crearPaciente($user, $nuevo);
                }
                $h = DB::table('historia_clinica as h')->join('cliente as c', 'c.clicod', '=', 'h.clicod')
                    ->where('h.his_cli_id', $hisId)->where('h.id_empresa_negocio', $suc)->first(['h.*', 'c.clinom', 'c.telefono']);
                if (!$h) {
                    throw new \RuntimeException('Paciente no válido.');
                }

                // El doctor no puede tener dos citas que se crucen
                if (!empty($d['doctor'])) {
                    $ini = strtotime($d['fecha'] . ' ' . $d['hora']);
                    $fin = $ini + $d['duracion'] * 60;
                    $cruce = DB::table('citas')->where('id_empresa_negocio', $suc)->where('doctor', $d['doctor'])->where('fecha', $d['fecha'])
                        ->whereNotIn('estado', ['CANCELADA', 'NO_ASISTIO'])->when($d['cit_id'] ?? null, fn($q, $id) => $q->where('cit_id', '!=', $id))->get()
                        ->first(fn($c) => strtotime($c->fecha . ' ' . $c->hora) < $fin && strtotime($c->fecha . ' ' . $c->hora) + $c->duracion * 60 > $ini);
                    if ($cruce) {
                        throw new \RuntimeException('El doctor ya tiene una cita a las ' . substr($cruce->hora, 0, 5) . ' con ' . $cruce->paciente . '.');
                    }
                }

                $fila = ['his_cli_id' => $hisId, 'clicod' => $h->clicod, 'paciente' => Clinica::nombre($h), 'telefono' => ($d['telefono'] ?? null) ?: $h->telefono,
                    'doctor' => $d['doctor'] ?? null, 'esp_id' => $d['esp_id'] ?? null, 'fecha' => $d['fecha'], 'hora' => $d['hora'] . ':00',
                    'duracion' => $d['duracion'], 'motivo' => $d['motivo'] ?? null];
                if (!empty($d['cit_id'])) {
                    DB::table('citas')->where('cit_id', $d['cit_id'])->where('id_empresa_negocio', $suc)->update($fila);
                } else {
                    DB::table('citas')->insert($fila + ['estado' => 'PROGRAMADA', 'id_empresa_negocio' => $suc, 'IdUsuario' => $user->IdUsuario, 'creado' => now()]);
                }
                return response()->json(['ok' => true, 'mensaje' => 'Cita guardada.']);
            });
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
    }

    public function estado(Request $request, int $id)
    {
        $this->usa();
        $d = $request->validate(['estado' => 'required|in:PROGRAMADA,CONFIRMADA,EN_ESPERA,NO_ASISTIO,CANCELADA']);
        $c = DB::table('citas')->where('cit_id', $id)->where('id_empresa_negocio', $this->suc())->first();
        if (!$c || $c->estado === 'ATENDIDA') {
            return response()->json(['ok' => false, 'mensaje' => 'Esa cita ya fue atendida.']);
        }
        DB::table('citas')->where('cit_id', $id)->update(['estado' => $d['estado']]);
        return response()->json(['ok' => true, 'mensaje' => 'Cita actualizada.']);
    }

    /** El doctor abre la atención de la cita */
    public function atender(int $id)
    {
        abort_unless(Clinica::veClinico(Auth::user()), 403, 'Solo el doctor atiende.');
        $c = DB::table('citas')->where('cit_id', $id)->where('id_empresa_negocio', $this->suc())->first();
        abort_unless($c && $c->his_cli_id, 404);
        return redirect()->route('clinica.atencion', ClinicaController::crearAtencion((int) $c->his_cli_id, $c->esp_id, $c->cit_id));
    }
}
