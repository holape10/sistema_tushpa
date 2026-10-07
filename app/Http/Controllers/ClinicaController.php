<?php
namespace App\Http\Controllers;

use App\Models\{EmpresaNegocio, PedidoDetalle};
use App\Support\Clinica;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB};

/**
 * Historias clínicas: pacientes, ficha (antecedentes, alergias, vacunas, odontograma), atenciones con receta,
 * impresión de receta e historia, y especialidades. El doctor escribe; recepción solo registra pacientes y cobra.
 */
class ClinicaController extends Controller
{
    private function suc(): int
    {
        return (int) Auth::user()->id_empresa_negocio;
    }

    private function usa(): void
    {
        abort_unless(Clinica::usaClinica(Auth::user()), 403, 'No tienes acceso a la clínica.');
    }

    private function clinico(): void
    {
        abort_unless(Clinica::veClinico(Auth::user()), 403, 'Solo el doctor o el administrador pueden ver la historia clínica.');
    }

    private function historia(int $id): object
    {
        $h = DB::table('historia_clinica as h')->join('cliente as c', 'c.clicod', '=', 'h.clicod')
            ->where('h.his_cli_id', $id)->where('h.id_empresa_negocio', $this->suc())
            ->first(['h.*', 'c.clinom', 'c.clinum', 'c.tdicod', 'c.telefono', 'c.clidir']);
        abort_unless($h, 404);
        return $h;
    }

    private function atencion(int $id): object
    {
        $a = DB::table('atencion_clinica')->where('ate_cli_id', $id)->where('id_empresa_negocio', $this->suc())->first();
        abort_unless($a, 404);
        return $a;
    }

    private function json(callable $f)
    {
        try {
            return response()->json(['ok' => true] + (array) DB::transaction($f));
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
    }

    // ------------------------------------------------------------------ pacientes

    public function index()
    {
        $this->usa();
        $suc = $this->suc();
        $ultima = DB::table('atencion_clinica')->where('id_empresa_negocio', $suc)->groupBy('his_cli_id')
            ->select('his_cli_id', DB::raw('MAX(ate_cli_fec) as ultima'), DB::raw('COUNT(*) as atenciones'))->get()->keyBy('his_cli_id');

        $pacientes = DB::table('historia_clinica as h')->join('cliente as c', 'c.clicod', '=', 'h.clicod')
            ->where('h.id_empresa_negocio', $suc)->orderByDesc('h.his_cli_id')
            ->get(['h.his_cli_id', 'h.his_cli_cod', 'h.tipo', 'h.mascota', 'h.especie', 'h.sexo', 'h.fecha_nac', 'h.origen', 'c.clinom', 'c.clinum', 'c.telefono'])
            ->map(function ($p) use ($ultima) {
                $p->nombre = Clinica::nombre($p);
                $p->edad = Clinica::edad($p->fecha_nac);
                $p->ultima = $ultima[$p->his_cli_id]->ultima ?? null;
                $p->atenciones = (int) ($ultima[$p->his_cli_id]->atenciones ?? 0);
                return $p;
            });

        return view('empresas.clinica.pacientes', [
            'pacientes' => $pacientes,
            'especialidades' => DB::table('especialidad')->where('id_empresa_negocio', $suc)->orderBy('esp_des')->get(),
            'servicios' => DB::table('productos')->where('id_empresa_negocio', $suc)->where('proest', 'Activo')->orderBy('pronom')->get(['IdProducto', 'pronom', 'propun']),
            'veClinico' => Clinica::veClinico(Auth::user()),
            'esAdmin' => Auth::user()->esAdmin(),
            'hayVeterinaria' => DB::table('especialidad')->where('id_empresa_negocio', $suc)->where('veterinaria', 1)->exists(),
        ]);
    }

    public function guardarPaciente(Request $request)
    {
        $this->usa();
        $d = $request->validate(Clinica::REGLAS_PACIENTE, [], ['clinum' => 'DNI', 'clinom' => 'nombre', 'mascota' => 'nombre de la mascota']);
        return $this->json(fn() => ['his_cli_id' => Clinica::crearPaciente(Auth::user(), $d), 'mensaje' => 'Paciente registrado.']);
    }

    /** Autocompletar en la agenda */
    public function buscar(Request $request)
    {
        $this->usa();
        $q = trim((string) $request->q);
        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }
        return response()->json(DB::table('historia_clinica as h')->join('cliente as c', 'c.clicod', '=', 'h.clicod')
            ->where('h.id_empresa_negocio', $this->suc())
            ->where(fn($w) => $w->where('c.clinom', 'like', "%{$q}%")->orWhere('c.clinum', 'like', "{$q}%")->orWhere('h.mascota', 'like', "%{$q}%")->orWhere('h.his_cli_cod', $q))
            ->limit(12)->get(['h.his_cli_id', 'h.his_cli_cod', 'h.tipo', 'h.mascota', 'c.clinom', 'c.clinum', 'c.telefono'])
            ->map(fn($p) => ['id' => $p->his_cli_id, 'codigo' => $p->his_cli_cod, 'nombre' => Clinica::nombre($p), 'doc' => $p->clinum, 'telefono' => $p->telefono]));
    }

    // ------------------------------------------------------------------ historia

    public function ver(int $id)
    {
        $this->clinico();
        $h = $this->historia($id);
        $suc = $this->suc();
        $atenciones = DB::table('atencion_clinica as a')->leftJoin('users as u', 'u.IdUsuario', '=', 'a.doctor')
            ->leftJoin('especialidad as e', 'e.esp_id', '=', 'a.esp_id')->where('a.his_cli_id', $id)
            ->orderByDesc('a.ate_cli_fec')->orderByDesc('a.ate_cli_id')
            ->get(['a.*', 'e.esp_des', DB::raw("TRIM(CONCAT(u.name, ' ', u.apeusu)) as doctor_nom")]);
        $recetas = DB::table('receta_detalle')->whereIn('ate_cli_id', $atenciones->pluck('ate_cli_id'))->get()->groupBy('ate_cli_id');

        return view('empresas.clinica.historia', [
            'h' => $h, 'atenciones' => $atenciones, 'recetas' => $recetas,
            'vacunas' => DB::table('historia_vacunas')->where('his_cli_id', $id)->orderByDesc('fecha')->get(),
            'especialidades' => DB::table('especialidad')->where('id_empresa_negocio', $suc)->where('activo', 1)->orderBy('esp_des')->get(),
            'proximas' => DB::table('citas')->where('his_cli_id', $id)->where('fecha', '>=', now()->toDateString())
                ->whereIn('estado', ['PROGRAMADA', 'CONFIRMADA'])->orderBy('fecha')->orderBy('hora')->get(),
            'conOdontograma' => DB::table('especialidad')->where('id_empresa_negocio', $suc)->where('odontograma', 1)->exists(),
        ]);
    }

    public function guardarFicha(Request $request, int $id)
    {
        $this->clinico();
        $this->historia($id);
        $d = $request->validate([
            'antecedentes' => 'nullable|string|max:5000', 'alergias' => 'nullable|string|max:2000', 'grupo_sanguineo' => 'nullable|string|max:5',
            'ocupacion' => 'nullable|string|max:100', 'contacto_emergencia' => 'nullable|string|max:150', 'sexo' => 'nullable|in:M,F',
            'fecha_nac' => 'nullable|date|before_or_equal:today', 'especie' => 'nullable|string|max:40', 'raza' => 'nullable|string|max:60',
            'origen' => 'nullable|string|max:30',
        ]);
        DB::table('historia_clinica')->where('his_cli_id', $id)->update($d);
        return response()->json(['ok' => true, 'mensaje' => 'Ficha guardada.']);
    }

    /** Empieza una atención (desde la historia o desde la cita de la agenda) */
    public function nuevaAtencion(Request $request, int $id)
    {
        $this->clinico();
        $this->historia($id);
        $d = $request->validate(['esp_id' => 'nullable|integer', 'cit_id' => 'nullable|integer']);
        $ateId = self::crearAtencion($id, $d['esp_id'] ?? null, $d['cit_id'] ?? null);
        return redirect()->route('clinica.atencion', $ateId);
    }

    public static function crearAtencion(int $hisId, ?int $espId, ?int $citId): int
    {
        $user = Auth::user();
        $cita = $citId ? DB::table('citas')->where('cit_id', $citId)->where('id_empresa_negocio', $user->id_empresa_negocio)->first() : null;
        if ($cita && $cita->ate_cli_id) {
            return (int) $cita->ate_cli_id;        // ya se estaba atendiendo
        }
        $ateId = DB::table('atencion_clinica')->insertGetId([
            'his_cli_id' => $hisId, 'ate_cli_fec' => now()->toDateString(), 'ate_cli_hor' => now()->format('H:i:s'),
            'esp_id' => $espId ?: $cita?->esp_id, 'doctor' => Clinica::esDoctor($user) ? $user->IdUsuario : ($cita?->doctor ?: $user->IdUsuario),
            'mot_con' => $cita?->motivo, 'ate_cli_est' => 'PENDIENTE', 'cit_id' => $cita?->cit_id,
            'id_empresa_negocio' => $user->id_empresa_negocio, 'creado' => now(),
        ]);
        if ($cita) {
            DB::table('citas')->where('cit_id', $cita->cit_id)->update(['ate_cli_id' => $ateId, 'estado' => 'EN_ESPERA']);
        }
        return $ateId;
    }

    // ------------------------------------------------------------------ atención

    public function verAtencion(int $id)
    {
        $this->clinico();
        $a = $this->atencion($id);
        $h = $this->historia((int) $a->his_cli_id);
        $esp = $a->esp_id ? DB::table('especialidad')->where('esp_id', $a->esp_id)->first() : null;
        return view('empresas.clinica.atencion', [
            'a' => $a, 'h' => $h, 'esp' => $esp,
            'receta' => DB::table('receta_detalle')->where('ate_cli_id', $id)->orderBy('rec_id')->get(),
            'vacunas' => DB::table('historia_vacunas')->where('ate_cli_id', $id)->get(),
            'servicios' => $a->ped_id ? PedidoDetalle::where('ped_id', $a->ped_id)->where('estadoitem', '!=', 'Eliminado')->orderBy('ped_det_id')->get() : collect(),
            'productos' => DB::table('productos')->where('id_empresa_negocio', $this->suc())->where('proest', 'Activo')->orderBy('pronom')->get(['IdProducto', 'pronom', 'propun']),
            'especialidades' => DB::table('especialidad')->where('id_empresa_negocio', $this->suc())->where('activo', 1)->orderBy('esp_des')->get(),
            'anteriores' => DB::table('atencion_clinica')->where('his_cli_id', $a->his_cli_id)->where('ate_cli_id', '!=', $id)
                ->where('ate_cli_est', 'ATENDIDA')->orderByDesc('ate_cli_fec')->limit(5)->get(['ate_cli_id', 'ate_cli_fec', 'diagnostico', 'cie10']),
        ]);
    }

    public function guardarAtencion(Request $request, int $id)
    {
        $this->clinico();
        $a = $this->atencion($id);
        $d = $request->validate([
            'esp_id' => 'nullable|integer', 'pre_art' => 'nullable|string|max:10', 'fre_car' => 'nullable|string|max:10', 'fre_res' => 'nullable|string|max:10',
            'temperatura' => 'nullable|numeric|between:30,45', 'saturacion' => 'nullable|integer|between:50,100',
            'peso' => 'nullable|numeric|min:0|max:999', 'talla' => 'nullable|numeric|min:0|max:3',
            'mot_con' => 'nullable|string|max:5000', 'antecedente' => 'nullable|string|max:5000', 'alergia' => 'nullable|string|max:2000',
            'int_qui' => 'nullable|string|max:2000', 'exa_fis' => 'nullable|string|max:5000', 'cie10' => 'nullable|string|max:100',
            'diagnostico' => 'nullable|string|max:5000', 'tratamiento' => 'nullable|string|max:5000', 'examenes' => 'nullable|string|max:3000',
            'indicaciones' => 'nullable|string|max:3000', 'pro_cit' => 'nullable|date|after:today', 'odontograma' => 'nullable|array',
            'receta' => 'nullable|array|max:30', 'receta.*.medicamento' => 'required|string|max:150', 'receta.*.dosis' => 'nullable|string|max:80',
            'receta.*.frecuencia' => 'nullable|string|max:80', 'receta.*.duracion' => 'nullable|string|max:60', 'receta.*.cantidad' => 'nullable|string|max:30',
            'receta.*.indicaciones' => 'nullable|string|max:200',
            'vacunas' => 'nullable|array|max:20', 'vacunas.*.tipo' => 'required|in:VACUNA,DESPARASITACION', 'vacunas.*.nombre' => 'required|string|max:120',
            'vacunas.*.lote' => 'nullable|string|max:40', 'vacunas.*.proxima' => 'nullable|date',
            'finalizar' => 'nullable|boolean',
        ], [], ['receta.*.medicamento' => 'medicamento', 'vacunas.*.nombre' => 'vacuna', 'pro_cit' => 'próxima cita']);

        return $this->json(function () use ($a, $d) {
            $user = Auth::user();
            $piezas = Clinica::limpiarOdontograma($d['odontograma'] ?? []);
            $campos = array_intersect_key($d, array_flip(['esp_id', 'pre_art', 'fre_car', 'fre_res', 'temperatura', 'saturacion', 'peso', 'talla', 'mot_con',
                'antecedente', 'alergia', 'int_qui', 'exa_fis', 'cie10', 'diagnostico', 'tratamiento', 'examenes', 'indicaciones', 'pro_cit']));
            DB::table('atencion_clinica')->where('ate_cli_id', $a->ate_cli_id)->update($campos + ['odontograma' => $piezas ? json_encode($piezas) : null]);

            DB::table('receta_detalle')->where('ate_cli_id', $a->ate_cli_id)->delete();
            foreach ($d['receta'] ?? [] as $r) {
                DB::table('receta_detalle')->insert(array_map(fn($v) => is_string($v) ? trim($v) : $v, $r) + ['ate_cli_id' => $a->ate_cli_id]);
            }
            DB::table('historia_vacunas')->where('ate_cli_id', $a->ate_cli_id)->delete();
            foreach ($d['vacunas'] ?? [] as $v) {
                DB::table('historia_vacunas')->insert(['his_cli_id' => $a->his_cli_id, 'ate_cli_id' => $a->ate_cli_id, 'fecha' => $a->ate_cli_fec,
                    'tipo' => $v['tipo'], 'nombre' => mb_strtoupper(trim($v['nombre'])), 'lote' => $v['lote'] ?? null, 'proxima' => $v['proxima'] ?? null]);
            }

            if (empty($d['finalizar'])) {
                return ['mensaje' => 'Guardado.'];
            }

            // Terminar: estado, odontograma de la historia, cita atendida, próxima cita y el pedido para cobrar
            $a = DB::table('atencion_clinica')->where('ate_cli_id', $a->ate_cli_id)->first();
            DB::table('atencion_clinica')->where('ate_cli_id', $a->ate_cli_id)->update(['ate_cli_est' => 'ATENDIDA']);
            if ($piezas) {
                // El odontograma de la historia queda como el de esta atención (el doctor parte siempre del último)
                DB::table('historia_clinica')->where('his_cli_id', $a->his_cli_id)->update(['odontograma' => json_encode($piezas)]);
            }
            if ($a->cit_id) {
                DB::table('citas')->where('cit_id', $a->cit_id)->update(['estado' => 'ATENDIDA']);
            }
            if (!empty($d['pro_cit'])) {
                $cita = $a->cit_id ? DB::table('citas')->where('cit_id', $a->cit_id)->first() : null;
                $h = DB::table('historia_clinica as h')->join('cliente as c', 'c.clicod', '=', 'h.clicod')->where('h.his_cli_id', $a->his_cli_id)->first(['h.*', 'c.clinom', 'c.telefono']);
                $ya = DB::table('citas')->where('his_cli_id', $a->his_cli_id)->where('fecha', $d['pro_cit'])->whereNotIn('estado', ['CANCELADA'])->exists();
                if (!$ya) {
                    DB::table('citas')->insert(['his_cli_id' => $a->his_cli_id, 'clicod' => $h->clicod, 'paciente' => Clinica::nombre($h), 'telefono' => $h->telefono,
                        'doctor' => $a->doctor, 'esp_id' => $a->esp_id, 'fecha' => $d['pro_cit'], 'hora' => $cita->hora ?? '09:00:00',
                        'motivo' => 'CONTROL', 'estado' => 'PROGRAMADA', 'id_empresa_negocio' => $a->id_empresa_negocio, 'IdUsuario' => $user->IdUsuario, 'creado' => now()]);
                }
            }
            $pedido = Clinica::pedidoDe($a, $user);
            $porCobrar = PedidoDetalle::where('ped_id', $pedido->ped_id)->where('estadoitem', '!=', 'Eliminado')->exists();
            return ['mensaje' => 'Atención terminada.' . ($porCobrar ? ' Recepción ya la ve en "Por cobrar".' : ''), 'terminada' => true];
        });
    }

    /** Procedimientos o productos que se cobran junto con la consulta */
    public function agregarServicio(Request $request, int $id)
    {
        $this->clinico();
        $a = $this->atencion($id);
        $d = $request->validate(['IdProducto' => 'required|integer', 'cantidad' => 'required|numeric|min:0.01|max:999']);
        return $this->json(function () use ($a, $d) {
            $prod = DB::table('productos')->where('IdProducto', $d['IdProducto'])->where('id_empresa_negocio', $this->suc())->first();
            if (!$prod) {
                throw new \RuntimeException('Producto no válido.');
            }
            $pedido = Clinica::pedidoDe($a, Auth::user());
            if ($pedido->ped_est !== 'Aperturado') {
                throw new \RuntimeException('Esta atención ya se cobró.');
            }
            Clinica::agregarLinea($pedido->ped_id, $prod, (float) $d['cantidad'], (float) $prod->propun, Auth::user());
            return ['mensaje' => $prod->pronom . ' agregado.'];
        });
    }

    public function quitarServicio(int $id, int $det)
    {
        $this->clinico();
        $a = $this->atencion($id);
        $linea = PedidoDetalle::where('ped_det_id', $det)->where('ped_id', $a->ped_id)->first();
        if (!$linea || $linea->item_facturado > 0) {
            return response()->json(['ok' => false, 'mensaje' => 'Ya se cobró: no se puede quitar.']);
        }
        $linea->update(['estadoitem' => 'Eliminado']);
        Clinica::recalcular((int) $a->ped_id);
        return response()->json(['ok' => true, 'mensaje' => 'Quitado.']);
    }

    // ------------------------------------------------------------------ impresión

    private function negocio(): object
    {
        $n = EmpresaNegocio::find($this->suc());
        $e = DB::table('empresa')->where('IdEmpresa', $n->IdEmpresa)->first();
        $n->logo = collect([$n->logo_suc, $e->LogEmpresa ?? null])->first(fn($l) => $l && is_file(public_path($l)));
        $n->razon = $e->NomEmpresa ?? '';
        return $n;
    }

    public function receta(int $id)
    {
        $this->clinico();
        $a = $this->atencion($id);
        return view('empresas.clinica.receta', [
            'a' => $a, 'h' => $this->historia((int) $a->his_cli_id), 'negocio' => $this->negocio(),
            'receta' => DB::table('receta_detalle')->where('ate_cli_id', $id)->orderBy('rec_id')->get(),
            'doctor' => DB::table('users as u')->leftJoin('empleado as e', 'e.emp_id', '=', 'u.emp_id')->where('u.IdUsuario', $a->doctor)
                ->first([DB::raw("TRIM(CONCAT(u.name, ' ', u.apeusu)) as nombre"), 'e.emp_num_doc']),
            'esp' => $a->esp_id ? DB::table('especialidad')->where('esp_id', $a->esp_id)->value('esp_des') : null,
        ]);
    }

    public function imprimirHistoria(int $id)
    {
        $this->clinico();
        $h = $this->historia($id);
        $atenciones = DB::table('atencion_clinica as a')->leftJoin('users as u', 'u.IdUsuario', '=', 'a.doctor')
            ->leftJoin('especialidad as e', 'e.esp_id', '=', 'a.esp_id')->where('a.his_cli_id', $id)->where('a.ate_cli_est', 'ATENDIDA')
            ->orderBy('a.ate_cli_fec')->get(['a.*', 'e.esp_des', DB::raw("TRIM(CONCAT(u.name, ' ', u.apeusu)) as doctor_nom")]);
        return view('empresas.clinica.historia_imprimir', [
            'h' => $h, 'atenciones' => $atenciones, 'negocio' => $this->negocio(),
            'recetas' => DB::table('receta_detalle')->whereIn('ate_cli_id', $atenciones->pluck('ate_cli_id'))->get()->groupBy('ate_cli_id'),
            'vacunas' => DB::table('historia_vacunas')->where('his_cli_id', $id)->orderBy('fecha')->get(),
        ]);
    }

    /** Consentimiento informado para imprimir y firmar (tratamiento y riesgos los escribe el doctor antes de imprimir) */
    public function consentimiento(Request $request, int $id)
    {
        $this->clinico();
        $h = $this->historia($id);
        $d = $request->validate(['tratamiento' => 'nullable|string|max:300', 'riesgos' => 'nullable|string|max:1500', 'doctor' => 'nullable|integer']);
        $doctor = DB::table('users')->where('IdUsuario', $d['doctor'] ?? Auth::id())->first();
        return view('empresas.clinica.consentimiento', [
            'h' => $h, 'negocio' => $this->negocio(), 'tratamiento' => $d['tratamiento'] ?? null, 'riesgos' => $d['riesgos'] ?? null,
            'doctor' => $doctor ? trim($doctor->name . ' ' . $doctor->apeusu) : '',
        ]);
    }

    // ------------------------------------------------------------------ especialidades

    public function guardarEspecialidad(Request $request)
    {
        abort_unless(Auth::user()->esAdmin(), 403);
        $d = $request->validate(['esp_id' => 'nullable|integer', 'esp_des' => 'required|string|max:100', 'IdProducto' => 'nullable|integer',
            'odontograma' => 'nullable|boolean', 'veterinaria' => 'nullable|boolean', 'activo' => 'nullable|boolean']);
        $fila = ['esp_des' => mb_strtoupper(trim($d['esp_des'])), 'IdProducto' => $d['IdProducto'] ?? null,
            'odontograma' => (int) ($d['odontograma'] ?? 0), 'veterinaria' => (int) ($d['veterinaria'] ?? 0), 'activo' => (int) ($d['activo'] ?? 1)];
        if (!empty($d['esp_id'])) {
            DB::table('especialidad')->where('esp_id', $d['esp_id'])->where('id_empresa_negocio', $this->suc())->update($fila);
        } else {
            DB::table('especialidad')->insert($fila + ['id_empresa_negocio' => $this->suc()]);
        }
        return response()->json(['ok' => true, 'mensaje' => 'Especialidad guardada.']);
    }
}
