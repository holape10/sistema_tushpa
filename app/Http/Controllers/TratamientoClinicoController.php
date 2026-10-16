<?php

namespace App\Http\Controllers;

use App\Models\EmpresaNegocio;
use App\Support\Clinica;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Registro de tratamientos de la clínica: el tratamiento del paciente con su costo y cada sesión con fecha y hora,
 * lo realizado o las indicaciones, el adelanto (se cobra en caja), el saldo, la cancelación y la firma del paciente.
 * Lo usan el doctor, recepción y el administrador.
 */
class TratamientoClinicoController extends Controller
{
    private function suc(): int
    {
        return (int) Auth::user()->id_empresa_negocio;
    }

    private function usa(): void
    {
        abort_unless(Clinica::usaClinica(Auth::user()), 403, 'No tienes acceso a la clínica.');
    }

    private function tratamiento(int $id): object
    {
        $t = DB::table('clinica_tratamientos')->where('tra_id', $id)->where('id_empresa_negocio', $this->suc())->first();
        abort_unless($t, 404);

        return $t;
    }

    private function json(callable $f): JsonResponse
    {
        try {
            return response()->json(['ok' => true] + (array) DB::transaction($f));
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
    }

    /** Todos los tratamientos: en curso (con saldo) primero; filtros por especialidad, estado y paciente */
    public function index(Request $request): View
    {
        $this->usa();
        $suc = $this->suc();
        $tratamientos = DB::table('clinica_tratamientos as t')
            ->join('historia_clinica as h', 'h.his_cli_id', '=', 't.his_cli_id')->join('cliente as c', 'c.clicod', '=', 'h.clicod')
            ->leftJoin('especialidad as e', 'e.esp_id', '=', 't.esp_id')
            ->leftJoinSub(DB::table('clinica_tratamiento_registros')->groupBy('tra_id')
                ->select('tra_id', DB::raw('SUM(adelanto) as pagado'), DB::raw('MAX(fecha_hora) as ultima'), DB::raw('COUNT(*) as sesiones')), 'r', 'r.tra_id', '=', 't.tra_id')
            ->where('t.id_empresa_negocio', $suc)
            ->orderByRaw("t.estado = 'CANCELADO'")->orderByDesc(DB::raw('COALESCE(r.ultima, t.creado)'))
            ->get(['t.*', 'e.esp_des', 'h.his_cli_cod', 'h.tipo', 'h.mascota', 'c.clinom', 'c.clinum', 'r.pagado', 'r.ultima', 'r.sesiones'])
            ->map(function ($t) {
                $t->paciente = Clinica::nombre($t);
                $t->pagado = round((float) $t->pagado, 2);
                $t->saldo = round((float) $t->costo - $t->pagado, 2);
                $t->sesiones = (int) $t->sesiones;

                return $t;
            });

        return view('empresas.clinica.tratamientos', [
            'tratamientos' => $tratamientos,
            'especialidades' => DB::table('especialidad')->where('id_empresa_negocio', $suc)->where('activo', 1)->orderBy('esp_des')->get(['esp_id', 'esp_des']),
        ]);
    }

    /** Tratamientos de un paciente con sus sesiones (para la historia y el registro) */
    public function delPaciente(int $hisId): JsonResponse
    {
        $this->usa();
        abort_unless(DB::table('historia_clinica')->where('his_cli_id', $hisId)->where('id_empresa_negocio', $this->suc())->exists(), 404);

        return response()->json(Clinica::tratamientosDe($hisId)->map(function ($t) {
            $t->registros = $t->registros->map(function ($r) {
                $r->firma_url = $r->firma ? route('clinica.tratamientos.firma', $r->reg_id) : null;
                $r->cobrado = $r->ped_id ? $r->ped_est === 'Cerrado' : null;

                return $r;
            });

            return $t;
        }));
    }

    /** Nuevo tratamiento (con su primera sesión, si se llena) */
    public function guardar(Request $request, int $hisId): JsonResponse
    {
        $this->usa();
        abort_unless(DB::table('historia_clinica')->where('his_cli_id', $hisId)->where('id_empresa_negocio', $this->suc())->exists(), 404);
        $d = $request->validate([
            'descripcion' => 'required|string|max:200', 'esp_id' => 'nullable|integer', 'costo' => 'required|numeric|min:0|max:999999',
            'detalle' => 'nullable|string|max:3000', 'adelanto' => 'nullable|numeric|min:0|max:999999', 'firma' => 'nullable|string|max:600000',
        ], [], ['descripcion' => 'tratamiento', 'costo' => 'costo total']);

        return $this->json(function () use ($hisId, $d) {
            $user = Auth::user();
            $espId = ! empty($d['esp_id']) && DB::table('especialidad')->where('esp_id', $d['esp_id'])->where('id_empresa_negocio', $this->suc())->exists()
                ? (int) $d['esp_id'] : null;
            $id = DB::table('clinica_tratamientos')->insertGetId([
                'his_cli_id' => $hisId, 'esp_id' => $espId, 'descripcion' => mb_strtoupper(trim($d['descripcion'])), 'costo' => round((float) $d['costo'], 2),
                'doctor' => Clinica::esDoctor($user) ? $user->IdUsuario : null, 'IdUsuario' => $user->IdUsuario,
                'id_empresa_negocio' => $this->suc(), 'creado' => now(),
            ]);
            $tra = DB::table('clinica_tratamientos')->where('tra_id', $id)->first();
            if (trim((string) ($d['detalle'] ?? '')) !== '' || (float) ($d['adelanto'] ?? 0) > 0) {
                $reg = Clinica::registrarSesion($tra, $d, $user);
                if (! empty($d['firma'])) {
                    Clinica::guardarFirma($reg, $this->suc(), $d['firma']);
                }
            }

            return ['mensaje' => 'Tratamiento registrado.'.((float) ($d['adelanto'] ?? 0) > 0 ? ' El adelanto ya aparece en "Por cobrar" de recepción.' : ''), 'tra_id' => $id];
        });
    }

    /** Corregir el nombre, la especialidad o el costo (el costo no puede quedar por debajo de lo ya pagado) */
    public function editar(Request $request, int $id): JsonResponse
    {
        $this->usa();
        $tra = $this->tratamiento($id);
        $d = $request->validate(['descripcion' => 'required|string|max:200', 'esp_id' => 'nullable|integer', 'costo' => 'required|numeric|min:0|max:999999'],
            [], ['descripcion' => 'tratamiento', 'costo' => 'costo total']);

        return $this->json(function () use ($tra, $d) {
            $pagado = (float) DB::table('clinica_tratamiento_registros')->where('tra_id', $tra->tra_id)->sum('adelanto');
            $costo = round((float) $d['costo'], 2);
            if ($costo < $pagado) {
                throw new \RuntimeException('El costo no puede ser menor que lo ya pagado (S/ '.number_format($pagado, 2).').');
            }
            $saldo = round($costo - $pagado, 2);
            DB::table('clinica_tratamientos')->where('tra_id', $tra->tra_id)->update([
                'descripcion' => mb_strtoupper(trim($d['descripcion'])), 'costo' => $costo,
                'esp_id' => ! empty($d['esp_id']) && DB::table('especialidad')->where('esp_id', $d['esp_id'])->where('id_empresa_negocio', $this->suc())->exists() ? (int) $d['esp_id'] : null,
            ] + ($saldo <= 0 && $costo > 0 ? ['estado' => 'CANCELADO', 'cancelado' => $tra->cancelado ?? now()] : ['estado' => 'EN CURSO', 'cancelado' => null]));

            return ['mensaje' => 'Tratamiento actualizado.'];
        });
    }

    /** Nueva sesión: lo realizado o las indicaciones, el adelanto y la firma del paciente */
    public function sesion(Request $request, int $id): JsonResponse
    {
        $this->usa();
        $tra = $this->tratamiento($id);
        $d = $request->validate(['detalle' => 'nullable|string|max:3000', 'adelanto' => 'nullable|numeric|min:0|max:999999',
            'firma' => 'nullable|string|max:600000']);

        return $this->json(function () use ($tra, $d) {
            $reg = Clinica::registrarSesion($tra, $d, Auth::user());
            if (! empty($d['firma'])) {
                Clinica::guardarFirma($reg, $this->suc(), $d['firma']);
            }

            return ['mensaje' => 'Sesión registrada.'.((float) ($d['adelanto'] ?? 0) > 0 ? ' El pago ya aparece en "Por cobrar" de recepción.' : '')];
        });
    }

    /** Firmar una sesión que quedó sin firma */
    public function firmar(Request $request, int $reg): JsonResponse
    {
        $this->usa();
        $r = $this->registro($reg);
        $d = $request->validate(['firma' => 'required|string|max:600000']);

        return $this->json(function () use ($r, $d) {
            Clinica::guardarFirma((int) $r->reg_id, $this->suc(), $d['firma']);

            return ['mensaje' => 'Firma guardada.'];
        });
    }

    public function firma(int $reg)
    {
        $this->usa();
        $r = $this->registro($reg);
        abort_unless($r->firma && Storage::disk('local')->exists($r->firma), 404);

        return response(Storage::disk('local')->get($r->firma), 200, ['Content-Type' => 'image/png', 'Cache-Control' => 'private, max-age=86400']);
    }

    /** Hoja del registro de tratamientos para imprimir (con las firmas) */
    public function imprimir(int $hisId): View
    {
        $this->usa();
        $h = DB::table('historia_clinica as h')->join('cliente as c', 'c.clicod', '=', 'h.clicod')
            ->where('h.his_cli_id', $hisId)->where('h.id_empresa_negocio', $this->suc())->first(['h.*', 'c.clinom', 'c.clinum', 'c.telefono']);
        abort_unless($h, 404);
        $n = EmpresaNegocio::find($this->suc());
        $e = DB::table('empresa')->where('IdEmpresa', $n->IdEmpresa)->first();
        $n->logo = collect([$n->logo_suc, $e->LogEmpresa ?? null])->first(fn ($l) => $l && is_file(public_path($l)));
        $n->razon = $e->NomEmpresa ?? '';
        $tratamientos = Clinica::tratamientosDe($hisId);
        // Las firmas van dentro de la hoja (así se imprimen aunque la sesión expire)
        foreach ($tratamientos as $t) {
            foreach ($t->registros as $r) {
                $r->firma_img = $r->firma && Storage::disk('local')->exists($r->firma) ? 'data:image/png;base64,'.base64_encode(Storage::disk('local')->get($r->firma)) : null;
            }
        }

        return view('empresas.clinica.tratamientos_imprimir', ['h' => $h, 'negocio' => $n, 'tratamientos' => $tratamientos]);
    }

    private function registro(int $reg): object
    {
        $r = DB::table('clinica_tratamiento_registros as r')->join('clinica_tratamientos as t', 't.tra_id', '=', 'r.tra_id')
            ->where('r.reg_id', $reg)->where('t.id_empresa_negocio', $this->suc())->first(['r.*']);
        abort_unless($r, 404);

        return $r;
    }
}
