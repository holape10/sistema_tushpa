<?php

namespace App\Http\Controllers;

use App\Support\Clinica;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Radiografías, fotos (antes / después) y documentos del paciente. Se guardan privados (storage/app/clinica/archivos)
 * y solo los ve el personal de la clínica con sesión iniciada.
 */
class ClinicaArchivoController extends Controller
{
    public const TIPOS = ['RADIOGRAFIA' => 'Radiografía', 'FOTO' => 'Foto', 'DOCUMENTO' => 'Documento'];

    private function suc(): int
    {
        return (int) Auth::user()->id_empresa_negocio;
    }

    private function historia(int $hisId): void
    {
        abort_unless(Clinica::veClinico(Auth::user()), 403, 'Solo el doctor o el administrador ven las imágenes del paciente.');
        abort_unless(DB::table('historia_clinica')->where('his_cli_id', $hisId)->where('id_empresa_negocio', $this->suc())->exists(), 404);
    }

    private function archivo(int $id): object
    {
        abort_unless(Clinica::veClinico(Auth::user()), 403);
        $a = DB::table('clinica_archivos')->where('arc_id', $id)->where('id_empresa_negocio', $this->suc())->first();
        abort_unless($a, 404);

        return $a;
    }

    public function lista(int $hisId): JsonResponse
    {
        $this->historia($hisId);

        return response()->json(DB::table('clinica_archivos')->where('his_cli_id', $hisId)->orderByDesc('creado')->orderByDesc('arc_id')->get()
            ->map(function ($a) {
                $a->url = route('clinica.archivos.ver', $a->arc_id);
                $a->imagen = str_starts_with($a->mime, 'image/');

                return $a;
            }));
    }

    public function subir(Request $request, int $hisId): JsonResponse
    {
        $this->historia($hisId);
        $d = $request->validate([
            'archivos' => 'required|array|max:10',
            'archivos.*' => 'file|max:20480|mimes:jpg,jpeg,png,webp,gif,bmp,pdf',
            'tipo' => 'required|in:'.implode(',', array_keys(self::TIPOS)),
            'descripcion' => 'nullable|string|max:200',
            'ate_cli_id' => 'nullable|integer',
        ], [], ['archivos.*' => 'archivo', 'archivos' => 'archivos']);
        $ateId = ! empty($d['ate_cli_id']) && DB::table('atencion_clinica')->where('ate_cli_id', $d['ate_cli_id'])->where('his_cli_id', $hisId)->exists()
            ? (int) $d['ate_cli_id'] : null;
        foreach ($request->file('archivos') as $f) {
            $ext = strtolower($f->getClientOriginalExtension() ?: $f->extension());
            $ruta = $f->storeAs('clinica/archivos/'.$this->suc().'/'.$hisId, Str::uuid().'.'.$ext, 'local');
            DB::table('clinica_archivos')->insert([
                'his_cli_id' => $hisId, 'ate_cli_id' => $ateId, 'tipo' => $d['tipo'], 'ruta' => $ruta,
                'nombre' => mb_substr($f->getClientOriginalName(), 0, 150), 'mime' => $f->getMimeType() ?: 'application/octet-stream',
                'tamano' => (int) $f->getSize(), 'descripcion' => trim((string) ($d['descripcion'] ?? '')) ?: null,
                'IdUsuario' => Auth::id(), 'id_empresa_negocio' => $this->suc(), 'creado' => now(),
            ]);
        }
        $n = count($request->file('archivos'));

        return response()->json(['ok' => true, 'mensaje' => $n === 1 ? 'Archivo guardado.' : "{$n} archivos guardados."]);
    }

    public function ver(int $id)
    {
        $a = $this->archivo($id);
        abort_unless(Storage::disk('local')->exists($a->ruta), 404);

        return response(Storage::disk('local')->get($a->ruta), 200, [
            'Content-Type' => $a->mime, 'Cache-Control' => 'private, max-age=86400',
            'Content-Disposition' => 'inline; filename="'.str_replace('"', '', $a->nombre).'"',
        ]);
    }

    public function editar(Request $request, int $id): JsonResponse
    {
        $a = $this->archivo($id);
        $d = $request->validate(['tipo' => 'required|in:'.implode(',', array_keys(self::TIPOS)), 'descripcion' => 'nullable|string|max:200']);
        DB::table('clinica_archivos')->where('arc_id', $a->arc_id)->update(['tipo' => $d['tipo'], 'descripcion' => trim((string) ($d['descripcion'] ?? '')) ?: null]);

        return response()->json(['ok' => true, 'mensaje' => 'Guardado.']);
    }

    public function eliminar(int $id): JsonResponse
    {
        $a = $this->archivo($id);
        $user = Auth::user();
        abort_unless($user->esAdmin() || (int) $a->IdUsuario === (int) $user->IdUsuario, 403, 'Solo quien lo subió o el administrador pueden borrarlo.');
        Storage::disk('local')->delete($a->ruta);
        DB::table('clinica_archivos')->where('arc_id', $a->arc_id)->delete();

        return response()->json(['ok' => true, 'mensaje' => 'Archivo eliminado.']);
    }
}
