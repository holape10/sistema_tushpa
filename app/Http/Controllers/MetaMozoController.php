<?php

namespace App\Http\Controllers;

use App\Support\MetasMozo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Metas de venta de los mozos (solo el administrador): la meta de todos y la de cada uno, y cómo va cada mozo
 * hoy, esta semana y este mes. Cada mozo ve su propio avance en su pantalla de mesas.
 */
class MetaMozoController extends Controller
{
    private function sucursal(): int
    {
        abort_unless(Auth::user()->esAdmin(), 403, 'Solo el administrador pone las metas de los mozos.');

        return (int) Auth::user()->id_empresa_negocio;
    }

    public function index(): View
    {
        $suc = $this->sucursal();

        return view('empresas.metas.index', [
            'tablero' => MetasMozo::tablero($suc),
            'metas' => MetasMozo::metas($suc),
        ]);
    }

    public function guardar(Request $request): RedirectResponse
    {
        $suc = $this->sucursal();
        $datos = $request->validate([
            'metas' => 'array|max:200',
            'metas.*.diaria' => 'nullable|numeric|min:0|max:9999999',
            'metas.*.semanal' => 'nullable|numeric|min:0|max:9999999',
            'metas.*.mensual' => 'nullable|numeric|min:0|max:99999999',
        ], [], ['metas.*.diaria' => 'meta diaria', 'metas.*.semanal' => 'meta semanal', 'metas.*.mensual' => 'meta mensual']);

        // Solo la meta general (0) y la de usuarios de esta sucursal
        $validos = DB::table('users')->where('id_empresa_negocio', $suc)->pluck('IdUsuario')->map(fn ($id) => (int) $id)->push(0);
        DB::transaction(function () use ($datos, $suc, $validos) {
            foreach ($datos['metas'] ?? [] as $usuario => $m) {
                $usuario = (int) $usuario;
                if (! $validos->contains($usuario)) {
                    continue;
                }
                $valores = collect(['diaria', 'semanal', 'mensual'])->mapWithKeys(fn ($c) => [$c => (float) ($m[$c] ?? 0) > 0 ? round((float) $m[$c], 2) : null])->all();
                // Un mozo sin metas propias usa la general: no se guarda fila vacía
                if ($usuario !== 0 && ! array_filter($valores)) {
                    DB::table('metas_mozo')->where('id_empresa_negocio', $suc)->where('IdUsuario', $usuario)->delete();

                    continue;
                }
                DB::table('metas_mozo')->updateOrInsert(['id_empresa_negocio' => $suc, 'IdUsuario' => $usuario], $valores + ['updated_at' => now()]);
            }
        });

        return redirect()->route('metas_mozo.index')->with('success', 'Metas guardadas. Cada mozo ya ve su avance en su pantalla de mesas.');
    }
}
