<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Central\Superadmin;
use App\Support\Tenancy\ClavePanel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/**
 * "Mi perfil" de cada usuario del panel (también el dueño): foto, nombre, celular, fecha de nacimiento y su contraseña
 * (una vez por semana). El correo con el que entra no se cambia aquí.
 */
class PerfilController extends Controller
{
    private const CARPETA = 'imagenes/panel-usuarios';

    private function yo(): Superadmin
    {
        return Auth::guard('superadmin')->user();
    }

    public function index(): View
    {
        $yo = $this->yo();

        return view('admin.perfil', ['yo' => $yo, 'proximoCambio' => ClavePanel::proximoCambio($yo->clave_cambiada_el, now())]);
    }

    public function guardar(Request $request): RedirectResponse
    {
        $yo = $this->yo();
        $d = $request->validate([
            'nombre' => 'required|string|max:120',
            'celular' => 'nullable|string|max:20|regex:/^\+?[0-9 ]{6,16}$/',
            'fecha_nacimiento' => 'nullable|date|before:today|after:1900-01-01',
            'foto' => 'nullable|image|mimes:png,jpg,jpeg,webp|max:4096',
        ], ['celular.regex' => 'El celular solo lleva números (ej. 987654321).', 'foto.image' => 'La foto debe ser una imagen PNG, JPG o WEBP.'],
            ['fecha_nacimiento' => 'fecha de nacimiento']);

        $datos = ['nombre' => trim($d['nombre']), 'celular' => trim((string) ($d['celular'] ?? '')) ?: null, 'fecha_nacimiento' => $d['fecha_nacimiento'] ?? null];
        if ($request->hasFile('foto')) {
            $dir = public_path(self::CARPETA);
            if (! is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
            $nombre = $yo->id.'-'.bin2hex(random_bytes(6)).'.'.strtolower($request->file('foto')->extension() ?: 'jpg');
            $request->file('foto')->move($dir, $nombre);
            $this->borrarFoto($yo);
            $datos['foto'] = self::CARPETA.'/'.$nombre;
        }
        $yo->update($datos);

        return back()->with('ok', 'Tu perfil quedó guardado.');
    }

    public function quitarFoto(): RedirectResponse
    {
        $yo = $this->yo();
        $this->borrarFoto($yo);
        $yo->update(['foto' => null]);

        return back()->with('ok', 'Foto quitada.');
    }

    public function clave(Request $request): RedirectResponse
    {
        $yo = $this->yo();
        if ($proximo = ClavePanel::proximoCambio($yo->clave_cambiada_el, now())) {
            return back()->with('error', 'Ya cambiaste tu contraseña esta semana. Podrás cambiarla de nuevo el '.$proximo->format('d/m/Y H:i').'.'
                .($yo->es_dueno ? '' : ' Si la olvidaste, pide que te la restablezcan.'));
        }
        $request->validate([
            'actual' => 'required|string',
            'password' => 'required|string|min:10|confirmed|different:actual',
        ], ['password.confirmed' => 'La nueva contraseña y su repetición no son iguales.', 'password.different' => 'La nueva contraseña debe ser distinta de la actual.'],
            ['actual' => 'contraseña actual', 'password' => 'nueva contraseña']);
        if (! Hash::check($request->actual, $yo->password)) {
            return back()->withErrors(['actual' => 'La contraseña actual no es correcta.']);
        }
        $yo->update(['password' => $request->password, 'clave_cambiada_el' => now()]);

        return back()->with('ok', 'Contraseña cambiada. La próxima vez entra con la nueva.');
    }

    private function borrarFoto(Superadmin $yo): void
    {
        if ($yo->foto && str_starts_with($yo->foto, self::CARPETA.'/')) {
            @unlink(public_path($yo->foto));
        }
    }
}
