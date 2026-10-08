<?php

namespace App\Http\Controllers;

use App\Support\Asistencia;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;

/**
 * Kiosko de asistencia (pantalla en la entrada del local) y marcación desde el celular con QR.
 *  - Lector de código de barras / teclado: escanea o escribe el DNI y marca al instante.
 *  - QR: el trabajador toca su tarjeta, escanea el QR con su celular y marca desde ahí (enlace firmado de 90 s).
 *    El celular queda vinculado al trabajador: no se puede marcar por un compañero desde el mismo teléfono.
 *  - Tardanza fuera de tolerancia o día de descanso: autoriza el administrador con su usuario y clave.
 */
class AsistenciaController extends Controller
{
    private const COOKIE_DISPOSITIVO = 'tushpa_asistencia_dispositivo';

    private function sucursal(): int
    {
        return (int) Auth::user()->id_empresa_negocio;
    }

    private function empleado(int $empId, ?int $sucursal = null): ?object
    {
        return DB::table('empleado')->where('emp_id', $empId)->where('asistencia', 1)
            ->when($sucursal, fn ($w) => $w->where('id_empresa_negocio', $sucursal))->first();
    }

    private function porDni(string $dni, int $sucursal): ?object
    {
        return DB::table('empleado')->where('emp_num_doc', trim($dni))->where('asistencia', 1)
            ->where('id_empresa_negocio', $sucursal)->first();
    }

    /** URL de la foto del trabajador (se carga en Usuarios), o null si no tiene */
    private function foto(object $e): ?string
    {
        return ! empty($e->emp_foto) && is_file(public_path($e->emp_foto)) ? asset($e->emp_foto) : null;
    }

    /** Estado visual de un trabajador para su tarjeta del kiosko */
    private function tarjeta(object $e, ?Carbon $ahora = null): array
    {
        $ahora ??= now();
        $s = Asistencia::situacion($e->emp_id, $ahora);
        $r = $s['registro'];
        $t = $s['turno'];

        $estado = match (true) {
            $s['accion'] === 'completado' => 'salio',
            $s['accion'] === 'check_out_1' || $s['accion'] === 'check_out_2' => 'trabajando',
            $s['accion'] === 'check_in_2' => 'refrigerio',
            $t && $t->tipo === 'DESCANSO' => 'descanso',
            $t && $t->tipo === 'LEYENDA' => 'leyenda',
            $t && $t->tipo === 'TRABAJO' && $t->hora_entrada_1 && $ahora->gt(Asistencia::horaEsperada($t, $s['fecha'], 'check_in_1')->addMinutes((int) $t->tolerancia_minutos)) => 'falta',
            ! $t => 'sin_horario',
            default => 'pendiente',
        };

        $marcas = $r ? collect(Asistencia::ACCIONES)->filter(fn ($c) => $r->$c)->map(fn ($c) => [
            'nombre' => Asistencia::NOMBRES[$c], 'hora' => Carbon::parse($r->$c)->format('H:i'),
        ])->values() : collect();

        return [
            'id' => $e->emp_id, 'nombre' => $e->emp_nom, 'apellidos' => trim(($e->emp_ape_pat ?? '').' '.($e->emp_ape_mat ?? '')),
            'inicial' => mb_strtoupper(mb_substr(trim($e->emp_nom ?: '?'), 0, 1)),
            'foto' => $this->foto($e),
            'estado' => $estado, 'siguiente' => Asistencia::NOMBRES[$s['accion']],
            'turno' => $t ? ['codigo' => $t->codigo, 'descripcion' => $t->descripcion, 'color' => $t->color,
                'horario' => $t->hora_entrada_1 ? substr($t->hora_entrada_1, 0, 5).'–'.substr($t->hora_salida_2 ?: $t->hora_salida_1, 0, 5) : null] : null,
            'marcas' => $marcas, 'tardanza' => (int) ($r->tardanza_minutos ?? 0),
            'ultima' => $r ? collect(Asistencia::ACCIONES)->map(fn ($c) => $r->$c)->filter()->max() : null,
        ];
    }

    public function kiosko(Request $request)
    {
        $sucursal = $this->sucursal();
        if (! Asistencia::ipPermitida($sucursal, $request->ip())) {
            return response()->view('empresas.asistencia.mensaje', [
                'titulo' => 'Acceso restringido', 'tipo' => 'danger',
                'mensaje' => 'El sistema de asistencia solo funciona dentro del local. Tu IP: '.$request->ip(),
            ], 403);
        }
        Asistencia::asegurarTurnos($sucursal);

        return view('empresas.asistencia.kiosko', [
            'tarjetas' => Asistencia::empleados($sucursal)->map(fn ($e) => $this->tarjeta($e))->values(),
            'motivos' => DB::table('asistencia_motivos')->where('id_empresa_negocio', $sucursal)->where('estado', 'Activo')->orderBy('descripcion')->pluck('descripcion'),
            'negocio' => DB::table('empresa_negocios')->where('id_empresa_negocio', $sucursal)->first(),
        ]);
    }

    /** El kiosko lo consulta cada pocos segundos para reflejar las marcaciones hechas desde el celular */
    public function estado()
    {
        $ahora = now();

        return response()->json([
            'hora' => $ahora->format('H:i:s'),
            'tarjetas' => Asistencia::empleados($this->sucursal())->map(fn ($e) => $this->tarjeta($e, $ahora))->values(),
        ]);
    }

    /** Lector de código de barras o DNI escrito en el kiosko */
    public function marcarLector(Request $request)
    {
        $request->validate(['dni' => 'required|string|max:15']);
        $sucursal = $this->sucursal();
        if (! Asistencia::ipPermitida($sucursal, $request->ip())) {
            return response()->json(['success' => false, 'message' => 'Equipo fuera del local (IP no permitida).']);
        }
        $e = $this->porDni($request->dni, $sucursal);
        if (! $e) {
            return response()->json(['success' => false, 'message' => "El documento {$request->dni} no pertenece a ningún trabajador con asistencia."]);
        }

        return $this->intentar($e, 'LECTOR', $request->ip());
    }

    private function intentar(object $e, string $origen, string $ip)
    {
        $s = Asistencia::situacion($e->emp_id);
        if ($s['accion'] === 'completado') {
            return response()->json(['success' => false, 'message' => "{$e->emp_nom}: ya completaste todas tus marcaciones de hoy."]);
        }
        if ($motivo = Asistencia::requiereAutorizacion($s)) {
            return response()->json(['success' => false, 'require_auth' => true, 'message' => $motivo,
                'empleado' => ['id' => $e->emp_id, 'nombre' => Asistencia::nombre($e)], 'accion' => Asistencia::NOMBRES[$s['accion']]]);
        }
        try {
            $r = Asistencia::marcar($e, $origen, ['ip' => $ip]);
        } catch (\RuntimeException $ex) {
            return response()->json(['success' => false, 'message' => $ex->getMessage()]);
        }

        return response()->json(['success' => true, 'message' => $r['mensaje'], 'accion' => $r['accion'], 'empleado' => Asistencia::nombre($e), 'foto' => $this->foto($e)]);
    }

    /** QR para marcar desde el celular: enlace firmado que vence en 90 segundos y que solo sirve para la marcación que toca */
    public function qr(Request $request, int $empId)
    {
        $e = $this->empleado($empId, $this->sucursal());
        abort_unless($e, 404);

        $s = Asistencia::situacion($e->emp_id);
        if ($s['accion'] === 'completado') {
            return response()->json(['success' => false, 'message' => "{$e->emp_nom}: ya completaste todas tus marcaciones de hoy."]);
        }
        if ($motivo = Asistencia::requiereAutorizacion($s)) {
            return response()->json(['success' => false, 'require_auth' => true, 'message' => $motivo,
                'empleado' => ['id' => $e->emp_id, 'nombre' => Asistencia::nombre($e)], 'accion' => Asistencia::NOMBRES[$s['accion']]]);
        }

        $url = URL::temporarySignedRoute('asistencia.celular', now()->addSeconds(90), ['emp' => $e->emp_id, 'accion' => $s['accion']]);
        $svg = (new Writer(new ImageRenderer(new RendererStyle(260, 1), new SvgImageBackEnd)))->writeString($url);

        return response()->json([
            'success' => true, 'url' => $url, 'svg' => preg_replace('/^<\?xml[^>]*>\s*/', '', $svg),
            'accion' => Asistencia::NOMBRES[$s['accion']], 'empleado' => Asistencia::nombre($e), 'segundos' => 90,
        ]);
    }

    /** Lo abre el celular del trabajador al escanear el QR (no requiere sesión: la firma del enlace lo protege) */
    public function celular(Request $request, int $emp, string $accion)
    {
        $vista = fn ($titulo, $mensaje, $tipo) => response()->view('empresas.asistencia.mensaje', compact('titulo', 'mensaje', 'tipo'));

        if (! $request->hasValidSignature()) {
            return $vista('Código vencido', 'El código QR venció o no es válido. Vuelve a tocar tu nombre en la pantalla y escanea el nuevo código.', 'danger');
        }
        $e = $this->empleado($emp);
        if (! $e) {
            return $vista('No encontrado', 'El trabajador no existe o no tiene asistencia activa.', 'danger');
        }
        if (! Asistencia::ipPermitida((int) $e->id_empresa_negocio, $request->ip())) {
            return $vista('Fuera del local', 'Conéctate al Wi-Fi del local para marcar tu asistencia. Tu IP: '.$request->ip(), 'danger');
        }
        // Antifraude: un celular solo marca para un trabajador
        $vinculado = $request->cookie(self::COOKIE_DISPOSITIVO);
        if ($vinculado && (int) $vinculado !== $e->emp_id) {
            return $vista('🚫 Alerta antifraude', 'Este celular ya está registrado para otro trabajador. No se puede marcar la asistencia de un compañero.', 'danger');
        }

        $s = Asistencia::situacion($e->emp_id);
        // Si recargan la página o reusan el enlace, no se vuelve a marcar
        if ($s['accion'] !== $accion) {
            return $vista(Asistencia::nombre($e), 'Esta marcación ya fue registrada. Puedes cerrar esta pestaña.', 'warning');
        }
        if ($motivo = Asistencia::requiereAutorizacion($s)) {
            return $vista(Asistencia::nombre($e), $motivo, 'warning');
        }

        try {
            $r = Asistencia::marcar($e, 'QR', ['ip' => $request->ip()]);
        } catch (\RuntimeException $ex) {
            return $vista(Asistencia::nombre($e), $ex->getMessage(), 'warning');
        }

        return $vista(Asistencia::nombre($e), $r['mensaje'], in_array($r['accion'], ['check_in_1', 'check_in_2'], true) ? 'success' : 'info')
            ->cookie(self::COOKIE_DISPOSITIVO, (string) $e->emp_id, 60 * 24 * 365);
    }

    /** Autorización del administrador: tardanza fuera de tolerancia o ingreso en día de descanso */
    public function autorizar(Request $request)
    {
        $d = $request->validate([
            'emp_id' => 'required|integer',
            'usuario' => 'required|string|max:100',
            'password' => 'required|string|max:100',
            'motivo' => 'required|string|min:3|max:150',
            'hora' => 'nullable|date_format:H:i',
        ], [], ['usuario' => 'usuario del administrador', 'password' => 'clave']);

        $clave = 'asistencia-autorizar|'.$request->ip();
        if (RateLimiter::tooManyAttempts($clave, 5)) {
            return response()->json(['success' => false, 'message' => 'Demasiados intentos. Espera '.RateLimiter::availableIn($clave).' segundos.']);
        }

        $sucursal = $this->sucursal();
        $e = $this->empleado((int) $d['emp_id'], $sucursal);
        abort_unless($e, 404);

        $admin = DB::table('users')->where(fn ($w) => $w->where('email', $d['usuario'])->orWhere('name', $d['usuario']))
            ->where('IdEmpresa', Auth::user()->IdEmpresa)->where('estusu', 1)->first();
        if (! $admin || ! Hash::check($d['password'], $admin->password)) {
            RateLimiter::hit($clave, 300);

            return response()->json(['success' => false, 'message' => 'Usuario o clave del administrador incorrectos.']);
        }
        if (! DB::table('role_user')->where('user_IdUsuario', $admin->IdUsuario)->where('role_id', 2)->exists()) {
            RateLimiter::hit($clave, 300);

            return response()->json(['success' => false, 'message' => 'Ese usuario no es Administrador.']);
        }
        RateLimiter::clear($clave);

        // Hora corregida por el administrador (queda anotado en el motivo)
        $ahora = now();
        $hora = $ahora;
        $motivo = trim($d['motivo']);
        if (! empty($d['hora']) && $d['hora'] !== $ahora->format('H:i')) {
            $hora = Carbon::parse($ahora->toDateString().' '.$d['hora'].':00');
            if ($hora->gt($ahora)) {
                return response()->json(['success' => false, 'message' => 'La hora no puede ser posterior a la hora actual.']);
            }
            $motivo .= ' [hora corregida de '.$ahora->format('H:i').' a '.$d['hora'].']';
        }

        try {
            $r = Asistencia::marcar($e, 'LECTOR', ['autorizado_por' => trim($admin->name.' '.$admin->apeusu), 'motivo' => $motivo,
                'hora' => $hora, 'ip' => $request->ip()]);
        } catch (\RuntimeException $ex) {
            return response()->json(['success' => false, 'message' => $ex->getMessage()]);
        }

        return response()->json(['success' => true, 'message' => $r['mensaje'].' · Autorizado por '.$admin->name]);
    }
}
