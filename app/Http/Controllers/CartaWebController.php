<?php

namespace App\Http\Controllers;

use App\Support\Captcha;
use App\Support\Carta;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * "Crea tu carta" en tushpa.app: cualquier negocio, solo con su correo, sube la foto de su carta, elige un diseño y recibe
 * su enlace y su QR. Se edita con un enlace secreto; si lo pierde, se lo enviamos a su correo.
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

    public function crear(): View
    {
        return view('sitio.carta.formulario', ['carta' => null, 'disenos' => self::DISENOS, 'turnstile' => Captcha::clave()]);
    }

    public function guardar(Request $request): RedirectResponse
    {
        if ($request->filled('web')) {
            abort(422);
        }
        $d = $this->validar($request, true);
        if (! Captcha::valido($request)) {
            return back()->withInput()->withErrors(['captcha' => 'Confirma que no eres un robot.']);
        }
        $slug = $this->slugLibre($d['nombre']);
        $id = $this->db()->insertGetId([
            'slug' => $slug, 'token' => Str::random(40), 'nombre' => $d['nombre'], 'email' => mb_strtolower($d['email']),
            'whatsapp' => $d['whatsapp'] ?? null, 'direccion' => $d['direccion'] ?? null, 'horario' => $d['horario'] ?? null,
            'foto' => $this->guardarFoto($request->file('foto'), $slug), 'diseno' => $d['diseno'], 'ip' => $request->ip(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $carta = $this->db()->where('id', $id)->first();
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

        return view('sitio.carta.formulario', ['carta' => $carta, 'disenos' => self::DISENOS, 'turnstile' => null]);
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
        }
        $this->db()->where('id', $carta->id)->update($cambios);

        return redirect()->to(route('sitio.carta.lista', [$carta->slug, 't' => $carta->token]))->with('ok', 'Cambios guardados.');
    }

    /** La carta que ven los clientes al escanear el QR */
    public function ver(string $slug): View
    {
        $carta = $this->carta($slug);
        abort_if($carta->estado !== 'ACTIVA', 404);
        $this->db()->where('id', $carta->id)->increment('visitas');

        return view('sitio.carta.ver', ['carta' => $carta, 'd' => self::DISENOS[$carta->diseno] ?? self::DISENOS['clasico']]);
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
            'foto' => [$nueva ? 'required' : 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ], ['foto.required' => 'Sube la foto de tu carta.'], ['nombre' => 'nombre del negocio', 'email' => 'correo', 'foto' => 'foto de la carta', 'diseno' => 'diseño']);
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
