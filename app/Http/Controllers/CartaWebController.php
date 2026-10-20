<?php

namespace App\Http\Controllers;

use App\Support\Captcha;
use App\Support\Carta;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * "Crea tu carta" en tushpa.app: cualquier negocio, solo con su correo, escribe sus productos (con una foto cada uno) y/o
 * sube la foto de su carta, elige un diseño y recibe su enlace y su QR. Se edita con un enlace secreto; si lo pierde,
 * se lo enviamos a su correo.
 *
 * Las fotos de los productos se suben una por una apenas se eligen (subirFoto), ya achicadas en el navegador: así una
 * carta con muchos platos no choca con el límite de archivos por envío del servidor.
 */
class CartaWebController extends Controller
{
    /** Diseños: [nombre, fondo, color de acento, texto, tipo de letra del título] */
    public const DISENOS = [
        'clasico' => ['Clásico', '#fdf6e3', '#7c2d12', '#292524', 'Georgia, serif'],
        'moderno' => ['Moderno noche', '#0f172a', '#facc15', '#f8fafc', 'Poppins, sans-serif'],
        'selva' => ['Selva', '#064e3b', '#fbbf24', '#ecfdf5', 'Poppins, sans-serif'],
        'marino' => ['Marino', '#0c4a6e', '#38bdf8', '#f0f9ff', 'Poppins, sans-serif'],
        'fiesta' => ['Brasa', '#7f1d1d', '#fb923c', '#fff7ed', 'Georgia, serif'],
    ];

    /** Productos como máximo por carta */
    public const MAX_PRODUCTOS = 200;

    /** Carpeta de las fotos de producto recién subidas (aún sin carta); pasan a imagenes/cartas al guardar */
    private const TEMPORAL = 'imagenes/cartas/tmp';

    private const RESERVADOS = ['crear', 'editar', 'admin', 'tushpa', 'demo', 'demos', 'registro', 'ingresar', 'soporte'];

    private function db(): Builder
    {
        return DB::connection('central')->table('cartas_web');
    }

    private function carta(string $slug, ?string $token = null): object
    {
        $carta = $this->db()->where('slug', $slug)->first();
        abort_unless($carta && ($token === null || hash_equals($carta->token, $token)), 404);

        return $carta;
    }

    public static function url(object $carta): string
    {
        return route('sitio.carta.ver', $carta->slug);
    }

    public static function urlEditar(object $carta): string
    {
        return route('sitio.carta.editar', [$carta->slug, 't' => $carta->token]);
    }

    private function productosDb(): Builder
    {
        return DB::connection('central')->table('cartas_web_productos');
    }

    public function crear(): View
    {
        return view('sitio.carta.formulario', ['carta' => null, 'productos' => collect(), 'disenos' => self::DISENOS, 'turnstile' => Captcha::clave()]);
    }

    /** Foto de un producto (ya achicada en el navegador): queda en la carpeta temporal hasta que se guarde la carta */
    public function subirFoto(Request $request): JsonResponse
    {
        $request->validate(['foto' => 'required|image|mimes:jpg,jpeg,png,webp|max:4096'], [], ['foto' => 'foto del producto']);
        $foto = $request->file('foto');
        $nombre = Str::random(24).'.'.strtolower($foto->guessExtension() ?: 'jpg');
        $foto->move(public_path(self::TEMPORAL), $nombre);
        // Fotos que se subieron y nunca se guardaron en una carta (más de un día): se borran
        foreach (glob(public_path(self::TEMPORAL).'/*') ?: [] as $viejo) {
            if (is_file($viejo) && filemtime($viejo) < time() - 86400) {
                @unlink($viejo);
            }
        }

        return response()->json(['foto' => $nombre, 'url' => asset(self::TEMPORAL.'/'.$nombre)]);
    }

    public function guardar(Request $request): RedirectResponse
    {
        if ($request->filled('web')) {
            abort(422);
        }
        $d = $this->validar($request, true);
        if (! $request->hasFile('foto') && ! $this->filasProductos($request)) {
            return back()->withInput()->withErrors(['productos' => 'Agrega al menos un producto o sube la foto de tu carta.']);
        }
        if (! Captcha::valido($request)) {
            return back()->withInput()->withErrors(['captcha' => 'Confirma que no eres un robot.']);
        }
        $slug = $this->slugLibre($d['nombre']);
        $id = $this->db()->insertGetId([
            'slug' => $slug, 'token' => Str::random(40), 'nombre' => $d['nombre'], 'email' => mb_strtolower($d['email']),
            'whatsapp' => $d['whatsapp'] ?? null, 'direccion' => $d['direccion'] ?? null, 'horario' => $d['horario'] ?? null,
            'foto' => $request->hasFile('foto') ? $this->guardarFoto($request->file('foto'), $slug) : null, 'diseno' => $d['diseno'],
            'ip' => $request->ip(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $carta = $this->db()->where('id', $id)->first();
        $this->guardarProductos($carta, $this->filasProductos($request));
        $this->avisarPorCorreo([$carta], '¡Tu carta digital está lista!');

        return redirect()->to(route('sitio.carta.lista', [$carta->slug, 't' => $carta->token]));
    }

    /** Su enlace, su QR y el enlace para editar */
    public function lista(Request $request, string $slug): View
    {
        $carta = $this->carta($slug, (string) $request->query('t'));

        return view('sitio.carta.lista', ['carta' => $carta, 'url' => self::url($carta), 'urlEditar' => self::urlEditar($carta),
            'qr' => Carta::qr(self::url($carta), 320), 'disenos' => self::DISENOS]);
    }

    public function editar(Request $request, string $slug): View
    {
        $carta = $this->carta($slug, (string) $request->query('t'));

        return view('sitio.carta.formulario', ['carta' => $carta, 'productos' => $this->productosDe($carta), 'disenos' => self::DISENOS, 'turnstile' => null]);
    }

    public function actualizar(Request $request, string $slug): RedirectResponse
    {
        $carta = $this->carta($slug, (string) $request->input('t'));
        $d = $this->validar($request, false);
        $cambios = ['nombre' => $d['nombre'], 'whatsapp' => $d['whatsapp'] ?? null, 'direccion' => $d['direccion'] ?? null,
            'horario' => $d['horario'] ?? null, 'diseno' => $d['diseno'], 'updated_at' => now()];
        if ($request->hasFile('foto')) {
            $cambios['foto'] = $this->guardarFoto($request->file('foto'), $carta->slug);
            $this->borrarFoto($carta->foto);
        } elseif ($request->boolean('quitar_foto')) {
            $cambios['foto'] = null;
            $this->borrarFoto($carta->foto);
        }
        $filas = $this->filasProductos($request);
        if (! $filas && ! ($cambios['foto'] ?? ($request->boolean('quitar_foto') ? null : $carta->foto))) {
            return back()->withInput()->withErrors(['productos' => 'Tu carta quedaría vacía: deja al menos un producto o la foto de tu carta.']);
        }
        $this->db()->where('id', $carta->id)->update($cambios);
        $this->guardarProductos($carta, $filas);

        return redirect()->to(route('sitio.carta.lista', [$carta->slug, 't' => $carta->token]))->with('ok', 'Cambios guardados.');
    }

    /** La carta que ven los clientes al escanear el QR */
    public function ver(string $slug): View
    {
        $carta = $this->carta($slug);
        abort_if($carta->estado !== 'ACTIVA', 404);
        $this->db()->where('id', $carta->id)->increment('visitas');

        return view('sitio.carta.ver', ['carta' => $carta, 'productos' => $this->productosDe($carta),
            'd' => self::DISENOS[$carta->diseno] ?? self::DISENOS['clasico']]);
    }

    /** ¿Perdiste el enlace para editar? Se envía al correo con el que se creó (la respuesta es la misma exista o no) */
    public function recuperar(Request $request): RedirectResponse
    {
        $email = mb_strtolower(trim((string) $request->validate(['email' => 'required|email|max:120'], [], ['email' => 'correo'])['email']));
        $cartas = $this->db()->where('email', $email)->get()->all();
        if ($cartas) {
            $this->avisarPorCorreo($cartas, 'Enlaces para editar tu carta digital');
        }

        return back()->with('ok', 'Si ese correo tiene cartas, te enviamos los enlaces para editarlas. Revisa también tu bandeja de spam.');
    }

    private function validar(Request $request, bool $nueva): array
    {
        return $request->validate([
            'nombre' => 'required|string|max:120', 'email' => [$nueva ? 'required' : 'nullable', 'email', 'max:120'],
            'whatsapp' => 'nullable|string|max:20', 'direccion' => 'nullable|string|max:200', 'horario' => 'nullable|string|max:120',
            'diseno' => 'required|in:'.implode(',', array_keys(self::DISENOS)),
            'foto' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'productos' => 'nullable|array|max:'.self::MAX_PRODUCTOS,
            'productos.*.id' => 'nullable|integer',
            'productos.*.categoria' => 'nullable|string|max:60',
            'productos.*.nombre' => 'nullable|string|max:120',
            'productos.*.precio' => 'nullable|numeric|min:0|max:99999',
            'productos.*.descripcion' => 'nullable|string|max:255',
            'productos.*.foto' => ['nullable', 'string', 'regex:/^[A-Za-z0-9]{24}\.(jpg|jpeg|png|webp)$/'],
        ], ['productos.max' => 'Tu carta puede tener hasta '.self::MAX_PRODUCTOS.' productos.'], ['nombre' => 'nombre del negocio', 'email' => 'correo',
            'foto' => 'foto de la carta', 'diseno' => 'diseño', 'productos.*.nombre' => 'nombre del producto', 'productos.*.precio' => 'precio',
            'productos.*.categoria' => 'categoría', 'productos.*.descripcion' => 'descripción']);
    }

    /**
     * Las filas de productos que llenó (las que no tienen nombre se ignoran).
     *
     * @return array<int, array{id: ?int, categoria: ?string, nombre: string, precio: ?float, descripcion: ?string, foto: ?string, foto_actual: ?string}>
     */
    private function filasProductos(Request $request): array
    {
        $filas = [];
        foreach ((array) $request->input('productos', []) as $p) {
            $nombre = trim((string) ($p['nombre'] ?? ''));
            if ($nombre === '') {
                continue;
            }
            $filas[] = ['id' => ! empty($p['id']) ? (int) $p['id'] : null, 'categoria' => mb_strtoupper(trim((string) ($p['categoria'] ?? ''))) ?: null,
                'nombre' => mb_substr($nombre, 0, 120), 'precio' => isset($p['precio']) && $p['precio'] !== '' ? round((float) $p['precio'], 2) : null,
                'descripcion' => trim((string) ($p['descripcion'] ?? '')) ?: null, 'foto' => $p['foto'] ?? null, 'foto_actual' => $p['foto_actual'] ?? null];
        }

        return $filas;
    }

    /** Reemplaza los productos de la carta por los del formulario, en ese orden, conservando las fotos que no cambiaron */
    private function guardarProductos(object $carta, array $filas): void
    {
        $antes = $this->productosDb()->where('carta_id', $carta->id)->get()->keyBy('id');
        $quedan = [];
        foreach ($filas as $orden => $f) {
            $previo = $f['id'] ? $antes->get($f['id']) : null;
            $foto = $previo && $f['foto_actual'] === $previo->foto ? $previo->foto : null;
            if ($f['foto'] && is_file(public_path(self::TEMPORAL.'/'.$f['foto']))) {
                $nombre = $carta->slug.'_p_'.$f['foto'];
                @rename(public_path(self::TEMPORAL.'/'.$f['foto']), public_path('imagenes/cartas/'.$nombre));
                $foto = 'imagenes/cartas/'.$nombre;
            }
            if ($previo && $previo->foto && $previo->foto !== $foto) {
                $this->borrarFoto($previo->foto);
            }
            $datos = ['categoria' => $f['categoria'], 'nombre' => $f['nombre'], 'precio' => $f['precio'], 'descripcion' => $f['descripcion'] ? mb_substr($f['descripcion'], 0, 255) : null,
                'foto' => $foto, 'orden' => $orden, 'updated_at' => now()];
            if ($previo) {
                $this->productosDb()->where('id', $previo->id)->update($datos);
                $quedan[] = $previo->id;
            } else {
                $quedan[] = $this->productosDb()->insertGetId($datos + ['carta_id' => $carta->id, 'created_at' => now()]);
            }
        }
        foreach ($antes->except($quedan) as $borrado) {
            $this->borrarFoto($borrado->foto);
        }
        $this->productosDb()->where('carta_id', $carta->id)->whereNotIn('id', $quedan ?: [0])->delete();
    }

    private function productosDe(object $carta)
    {
        return $this->productosDb()->where('carta_id', $carta->id)->orderBy('orden')->get();
    }

    /** mi-restaurante, mi-restaurante-2... */
    private function slugLibre(string $nombre): string
    {
        $base = Str::limit(Str::slug($nombre), 50, '') ?: 'carta';
        $slug = in_array($base, self::RESERVADOS, true) ? $base.'-carta' : $base;
        for ($n = 2; $this->db()->where('slug', $slug)->exists(); $n++) {
            $slug = $base.'-'.$n;
        }

        return $slug;
    }

    private function guardarFoto(UploadedFile $foto, string $slug): string
    {
        $nombre = $slug.'_'.Str::random(8).'.'.strtolower($foto->guessExtension() ?: 'jpg');
        $foto->move(public_path('imagenes/cartas'), $nombre);

        return 'imagenes/cartas/'.$nombre;
    }

    private function borrarFoto(?string $ruta): void
    {
        if ($ruta && str_starts_with($ruta, 'imagenes/cartas/') && is_file(public_path($ruta))) {
            @unlink(public_path($ruta));
        }
    }

    /** @param  array<int, object>  $cartas */
    private function avisarPorCorreo(array $cartas, string $asunto): void
    {
        $lineas = collect($cartas)->map(fn ($c) => "{$c->nombre}\n  Tu carta: ".self::url($c)."\n  Para editarla: ".self::urlEditar($c))->implode("\n\n");
        try {
            Mail::raw("Hola,\n\n{$lineas}\n\nGuarda este correo: el enlace para editar es solo tuyo.\n\nTUSHPA · ".route('sitio.inicio'),
                fn ($m) => $m->to($cartas[0]->email)->subject($asunto.' · TUSHPA'));
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
