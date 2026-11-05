<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Central\Marca;
use App\Models\Central\Superadmin;
use App\Support\Tenancy\Tenancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Marcas (marca blanca): un revendedor con su propio dominio, el prefijo de las bases de sus empresas, su logo, sus
 * colores y sus datos para los contratos. Solo el dueño del sistema las crea y cambia. El dominio y el prefijo no se
 * cambian cuando la marca ya tiene empresas (sus direcciones y sus bases dependen de ellos).
 */
class MarcaController extends Controller
{
    public const CARPETA = 'imagenes/marcas';

    private function autorizar(): void
    {
        abort_unless((bool) Auth::guard('superadmin')->user()->es_dueno, 403, 'Solo el dueño del sistema administra las marcas.');
    }

    public function index(): View
    {
        $this->autorizar();

        return view('admin.marcas', [
            'marcas' => Marca::withCount('clientes')->orderBy('nombre')->get(),
            'usuarios' => Superadmin::whereNotNull('marca_id')->get(['id', 'nombre', 'email', 'marca_id'])->groupBy('marca_id'),
        ]);
    }

    /**
     * Reglas de una marca. El dominio no puede ser el de TUSHPA (ni uno suyo) y el prefijo lleva una letra, así una
     * base de una marca (bd_ws_2060…) nunca se confunde con una de TUSHPA (bd_2060…).
     *
     * @return array<string, mixed>
     */
    public static function reglas(?Marca $marca = null): array
    {
        $tushpa = strtolower((string) config('tenancy.dominio'));

        return [
            'nombre' => 'required|string|max:100',
            'razon_social' => 'nullable|string|max:200',
            'ruc' => 'nullable|digits:11',
            'dominio' => ['required', 'string', 'max:100', 'regex:/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/',
                Rule::unique('central.marcas', 'dominio')->ignore($marca?->id),
                fn ($campo, $valor, $falla) => ($valor === $tushpa || str_ends_with($valor, '.'.$tushpa)) && $falla('Ese es el dominio de TUSHPA.')],
            'prefijo_bd' => ['required', 'string', 'regex:/^bd_[a-z][a-z0-9]{0,9}_$/', Rule::unique('central.marcas', 'prefijo_bd')->ignore($marca?->id)],
            'color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'color_secundario' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'representante' => 'nullable|string|max:150',
            'representante_dni' => 'nullable|digits_between:8,12',
            'direccion' => 'nullable|string|max:250',
            'telefono' => 'nullable|string|max:30',
            'correo' => 'nullable|email|max:150',
            'logo' => 'nullable|image|mimes:png,jpg,jpeg,webp|max:4096',
        ];
    }

    public function guardar(Request $request, ?int $id = null): RedirectResponse
    {
        $this->autorizar();
        $marca = $id ? Marca::withCount('clientes')->findOrFail($id) : null;
        $request->merge(['dominio' => mb_strtolower(trim((string) $request->input('dominio'))), 'prefijo_bd' => mb_strtolower(trim((string) $request->input('prefijo_bd')))]);
        $d = $request->validate(self::reglas($marca), [
            'dominio.regex' => 'Escribe solo el dominio, sin https:// ni www (ej. wilcatsystems.pe).',
            'prefijo_bd.regex' => 'El prefijo va así: bd_ + letras o números + _ (ej. bd_ws_).',
        ], ['prefijo_bd' => 'prefijo de las bases', 'representante_dni' => 'DNI del representante', 'color_secundario' => 'color secundario']);
        // Con empresas creadas, su dominio y sus bases ya no se pueden mover
        if ($marca && $marca->clientes_count && ($d['dominio'] !== $marca->dominio || $d['prefijo_bd'] !== $marca->prefijo_bd)) {
            return back()->withInput()->with('error', 'Esta marca ya tiene empresas: su dominio y el prefijo de sus bases no se pueden cambiar.');
        }
        $logo = $request->file('logo');
        unset($d['logo']);
        $d['activa'] = $request->boolean('activa', true);
        $d['razon_social'] = isset($d['razon_social']) ? mb_strtoupper(trim($d['razon_social'])) : null;
        $d['representante'] = isset($d['representante']) ? mb_strtoupper(trim($d['representante'])) : null;

        $marca ? $marca->update($d) : $marca = Marca::create($d);
        if ($logo) {
            $marca->update(['logo' => self::guardarLogo($marca, $logo)]);
        }
        Tenancy::olvidarMarcas();

        return redirect()->route('admin.marcas.index')->with('ok', "Marca {$marca->nombre} guardada.");
    }

    /** El logo en PNG dentro de public/ (así sale igual en la pantalla y en los PDF de los contratos) */
    private static function guardarLogo(Marca $marca, UploadedFile $archivo): string
    {
        $carpeta = public_path(self::CARPETA);
        if (! is_dir($carpeta)) {
            mkdir($carpeta, 0775, true);
        }
        $ruta = self::CARPETA.'/marca-'.$marca->id.'.png';
        $imagen = @imagecreatefromstring((string) file_get_contents($archivo->getRealPath()));
        if ($imagen) {
            imagesavealpha($imagen, true);
            imagepng($imagen, public_path($ruta), 6);
            imagedestroy($imagen);
        } else {
            copy($archivo->getRealPath(), public_path($ruta));
        }

        return $ruta;
    }
}
