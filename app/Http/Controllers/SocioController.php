<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\MedioPago;
use App\Models\Turno;
use App\Support\Gimnasio;
use App\Support\Impresion\Impresion;
use App\Support\Socios;
use App\Support\VentaDirecta;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Socios de un club o asociación: padrón con familiares, cuotas del mes con un clic, cargos extraordinarios,
 * cobro (completo o a cuenta) que emite el comprobante, morosidad y carnet con QR para portería.
 */
class SocioController extends Controller
{
    private function sucursal(): int
    {
        return (int) Auth::user()->id_empresa_negocio;
    }

    private function puedeUsar(): void
    {
        abort_unless(Auth::user()->esAdminOCaja(), 403, 'Solo Administrador o Caja.');
    }

    private function soloAdmin(): void
    {
        abort_unless(Auth::user()->esAdmin(), 403, 'Solo el Administrador puede hacer esto.');
    }

    private function json(callable $accion)
    {
        try {
            return response()->json(['ok' => true] + (array) $accion());
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()]);
        } catch (QueryException $e) {
            report($e);

            return response()->json(['ok' => false, 'mensaje' => str_contains($e->getMessage(), 'Duplicate') ? 'Ese código de socio ya existe.' : 'No se pudo guardar.']);
        }
    }

    public function index()
    {
        $this->puedeUsar();
        $suc = $this->sucursal();
        $cfg = Socios::config($suc);
        Socios::actualizarMorosidad($suc);

        $deuda = DB::table('socio_cargos')->where('id_empresa_negocio', $suc)->where('estado', 'PENDIENTE')
            ->groupBy('soc_id')->select('soc_id', DB::raw('SUM(monto - pagado) as saldo'))->pluck('saldo', 'soc_id');
        $meses = Socios::mesesDebe($suc);
        $familiares = DB::table('socio_familiares as f')->join('socios as s', 's.soc_id', '=', 'f.soc_id')
            ->where('s.id_empresa_negocio', $suc)->where('f.activo', 1)->groupBy('f.soc_id')->select('f.soc_id', DB::raw('COUNT(*) as n'))->pluck('n', 'f.soc_id');

        $socios = DB::table('socios as s')->join('cliente as c', 'c.clicod', '=', 's.clicod')
            ->leftJoin('socio_categorias as k', 'k.cat_soc_id', '=', 's.cat_soc_id')
            ->where('s.id_empresa_negocio', $suc)->orderBy('c.clinom')
            ->get(['s.soc_id', 's.codigo', 's.estado', 's.suspendido_auto', 's.cat_soc_id', 'k.nombre as categoria', 'c.clinum', 'c.clinom', 'c.telefono'])
            ->map(function ($s) use ($deuda, $meses, $familiares) {
                $s->saldo = round((float) ($deuda[$s->soc_id] ?? 0), 2);
                $s->meses = (int) ($meses[$s->soc_id] ?? 0);
                $s->familiares = (int) ($familiares[$s->soc_id] ?? 0);

                return $s;
            });

        return view('empresas.socios.index', [
            'socios' => $socios,
            'cfg' => $cfg,
            'categorias' => DB::table('socio_categorias')->where('id_empresa_negocio', $suc)->orderBy('nombre')->get(),
            // Los conceptos que se llaman CUOTA/MULTA/APORTE van primero (son los que se usan aquí)
            'conceptos' => DB::table('productos')->where('id_empresa_negocio', $suc)->where('proest', 'Activo')
                ->orderByRaw("CASE WHEN pronom = 'CUOTA ORDINARIA' THEN 0 WHEN pronom LIKE 'CUOTA%' OR pronom LIKE 'MULTA%' OR pronom LIKE 'APORTE%' THEN 1 ELSE 2 END")
                ->orderBy('pronom')->get(['IdProducto', 'pronom', 'propun', 'debe', 'haber']),
            'mediospagos' => MedioPago::where('id_empresa_negocio', $suc)->orderByDesc('predeterminado')->get(['id_med_pag', 'nom_med_pag']),
            'turno' => Turno::abiertoDe(Auth::user()),
            'esAdmin' => Auth::user()->esAdmin(),
            'periodo' => now()->format('Ym'),
        ]);
    }

    /** Ficha: datos, familiares, deuda pendiente e historial de pagos */
    public function ver(int $id)
    {
        $this->puedeUsar();
        $s = DB::table('socios as s')->join('cliente as c', 'c.clicod', '=', 's.clicod')
            ->where('s.soc_id', $id)->where('s.id_empresa_negocio', $this->sucursal())
            ->first(['s.*', 'c.tdicod', 'c.clinum', 'c.clinom', 'c.clidir', 'c.clicor', 'c.telefono']);
        abort_unless($s, 404);

        return response()->json([
            'socio' => $s,
            'familiares' => DB::table('socio_familiares')->where('soc_id', $id)->orderBy('fam_id')->get(),
            'pendientes' => DB::table('socio_cargos')->where('soc_id', $id)->where('estado', 'PENDIENTE')->orderBy('periodo')->orderBy('car_id')
                ->get(['car_id', 'descripcion', 'periodo', 'monto', 'pagado', 'creado']),
            'pagos' => DB::table('socio_pagos as p')->join('socio_cargos as c', 'c.car_id', '=', 'p.car_id')
                ->join('cpe_cabecera as cab', 'cab.IdCpe_cabecera', '=', 'p.IdCpe_cabecera')
                ->where('c.soc_id', $id)->orderByDesc('p.pag_id')->limit(60)
                ->get(['p.fecha', 'p.monto', 'p.anulado', 'c.descripcion', 'cab.IdCpe_cabecera', DB::raw("CONCAT(cab.serdoc, '-', LPAD(cab.numdoc, 8, '0')) as comprobante")]),
        ]);
    }

    public function guardar(Request $request)
    {
        $this->puedeUsar();
        $suc = $this->sucursal();
        $cfg = Socios::config($suc);
        $d = $request->validate([
            'soc_id' => 'nullable|integer', 'codigo' => 'nullable|string|max:20',
            'tdicod' => 'required|string|size:1', 'clinum' => 'required|string|max:15', 'clinom' => 'required|string|max:150',
            'clidir' => 'nullable|string|max:150', 'telefono' => 'nullable|string|max:20', 'clicor' => 'nullable|email|max:100',
            'cat_soc_id' => 'nullable|integer', 'fecha_ingreso' => 'nullable|date', 'fecha_nac' => 'nullable|date',
            'obs' => 'nullable|string|max:255',
            'familiares' => 'nullable|array|max:30',
            'familiares.*.fam_id' => 'nullable|integer', 'familiares.*.nombre' => 'required|string|max:150',
            'familiares.*.dni' => 'nullable|string|max:15', 'familiares.*.parentesco' => 'required|string|max:30',
            'familiares.*.fecha_nac' => 'nullable|date', 'familiares.*.activo' => 'nullable|boolean',
        ], [], ['clinum' => 'DNI / RUC', 'clinom' => 'Nombre', 'familiares.*.nombre' => 'nombre del familiar', 'familiares.*.parentesco' => 'parentesco']);

        return $this->json(function () use ($d, $suc, $cfg) {
            $permitidos = Socios::parentescos($cfg);
            foreach ($d['familiares'] ?? [] as $f) {
                if ($permitidos && ! in_array(mb_strtoupper($f['parentesco']), $permitidos, true)) {
                    throw new \RuntimeException("Parentesco no permitido: {$f['parentesco']}. Revisa la configuración.");
                }
            }

            return DB::transaction(function () use ($d, $suc) {
                $user = Auth::user();
                $cliente = Cliente::updateOrCreate(['clinum' => trim($d['clinum']), 'rucemp' => $user->IdEmpresa], [
                    'clinom' => mb_strtoupper(trim($d['clinom'])), 'tdicod' => $d['tdicod'], 'clidir' => ($d['clidir'] ?? null) ?: '--',
                    'telefono' => $d['telefono'] ?? null, 'clicor' => $d['clicor'] ?? null,
                ]);
                $actual = ! empty($d['soc_id']) ? DB::table('socios')->where('soc_id', $d['soc_id'])->where('id_empresa_negocio', $suc)->first() : null;
                if (! empty($d['soc_id']) && ! $actual) {
                    throw new \RuntimeException('Socio no encontrado.');
                }
                $otro = DB::table('socios')->where('id_empresa_negocio', $suc)->where('clicod', $cliente->clicod)
                    ->when($actual, fn ($q) => $q->where('soc_id', '!=', $actual->soc_id))->value('codigo');
                if ($otro) {
                    throw new \RuntimeException("Esa persona ya está registrada como socio N° {$otro}.");
                }

                // Código: el que escriban o el siguiente número
                $codigo = trim((string) ($d['codigo'] ?? '')) ?: ($actual->codigo ?? null);
                if (! $codigo) {
                    $ultimo = DB::table('socios')->where('id_empresa_negocio', $suc)->whereRaw("codigo REGEXP '^[0-9]+$'")->max(DB::raw('CAST(codigo AS UNSIGNED)'));
                    $codigo = str_pad((string) ((int) $ultimo + 1), 4, '0', STR_PAD_LEFT);
                }
                $catValida = ! empty($d['cat_soc_id']) && DB::table('socio_categorias')->where('cat_soc_id', $d['cat_soc_id'])->where('id_empresa_negocio', $suc)->exists();
                $fila = ['codigo' => mb_strtoupper($codigo), 'clicod' => $cliente->clicod, 'cat_soc_id' => $catValida ? $d['cat_soc_id'] : null,
                    'fecha_ingreso' => $d['fecha_ingreso'] ?? null, 'fecha_nac' => $d['fecha_nac'] ?? null, 'obs' => $d['obs'] ?? null];

                if ($actual) {
                    DB::table('socios')->where('soc_id', $actual->soc_id)->update($fila);
                    $socId = $actual->soc_id;
                } else {
                    $socId = DB::table('socios')->insertGetId($fila + ['estado' => 'ACTIVO', 'token' => Socios::token(), 'id_empresa_negocio' => $suc, 'creado' => now()]);
                }

                // Familiares: los que no vienen en la lista se dan de baja (no se borran: pueden tener historial de ingresos)
                $vistos = [];
                foreach ($d['familiares'] ?? [] as $f) {
                    $fam = ['nombre' => mb_strtoupper(trim($f['nombre'])), 'dni' => trim((string) ($f['dni'] ?? '')) ?: null,
                        'parentesco' => mb_strtoupper(trim($f['parentesco'])), 'fecha_nac' => $f['fecha_nac'] ?? null, 'activo' => (int) ($f['activo'] ?? 1)];
                    if (! empty($f['fam_id']) && DB::table('socio_familiares')->where('fam_id', $f['fam_id'])->where('soc_id', $socId)->exists()) {
                        DB::table('socio_familiares')->where('fam_id', $f['fam_id'])->update($fam);
                        $vistos[] = (int) $f['fam_id'];
                    } else {
                        $vistos[] = DB::table('socio_familiares')->insertGetId($fam + ['soc_id' => $socId, 'token' => Socios::token()]);
                    }
                }
                DB::table('socio_familiares')->where('soc_id', $socId)->whereNotIn('fam_id', $vistos ?: [0])->update(['activo' => 0]);

                return ['mensaje' => 'Socio N° '.mb_strtoupper($codigo).' guardado.', 'soc_id' => $socId];
            });
        });
    }

    /** Estado manual: suspender, retirar, reactivar (la suspensión manual no la levanta el pago) */
    public function estado(Request $request, int $id)
    {
        $this->soloAdmin();
        $d = $request->validate(['estado' => 'required|in:'.implode(',', Socios::ESTADOS)]);
        DB::table('socios')->where('soc_id', $id)->where('id_empresa_negocio', $this->sucursal())->update(['estado' => $d['estado'], 'suspendido_auto' => 0]);

        return response()->json(['ok' => true, 'mensaje' => 'Estado actualizado.']);
    }

    /**
     * Quitar del padrón a los que no son socios (por ejemplo, clientes que vinieron una sola vez).
     * Sin cuotas ni pagos se borran; con historial quedan RETIRADO para no perder sus pagos.
     */
    public function eliminar(Request $request)
    {
        $this->soloAdmin();
        $d = $request->validate(['ids' => 'required|array|min:1|max:5000', 'ids.*' => 'integer']);
        $suc = $this->sucursal();
        $borrados = $retirados = 0;
        DB::transaction(function () use ($d, $suc, &$borrados, &$retirados) {
            $ids = DB::table('socios')->where('id_empresa_negocio', $suc)->whereIn('soc_id', $d['ids'])->pluck('soc_id');
            $conHistorial = DB::table('socio_cargos')->whereIn('soc_id', $ids)->where('estado', '!=', 'ANULADO')->distinct()->pluck('soc_id');
            $retirados = DB::table('socios')->whereIn('soc_id', $conHistorial)->update(['estado' => 'RETIRADO', 'suspendido_auto' => 0]);
            $libres = $ids->diff($conHistorial)->values();
            DB::table('socio_familiares')->whereIn('soc_id', $libres)->delete();
            DB::table('socio_cargos')->whereIn('soc_id', $libres)->delete();
            $borrados = DB::table('socios')->whereIn('soc_id', $libres)->delete();
        });
        $msg = $borrados ? "Se quitaron {$borrados} del padrón." : '';
        if ($retirados) {
            $msg .= " {$retirados} tenían cuotas o pagos: quedaron como RETIRADO.";
        }

        return response()->json(['ok' => true, 'mensaje' => trim($msg) ?: 'Nada que quitar.']);
    }

    /** Poner la misma categoría a varios socios: los marcados o todos los que no tienen */
    public function ponerCategoria(Request $request)
    {
        $this->soloAdmin();
        $d = $request->validate(['cat_soc_id' => 'required|integer', 'ids' => 'nullable|array|max:5000', 'ids.*' => 'integer', 'sin_categoria' => 'nullable|boolean']);
        $suc = $this->sucursal();
        $cat = DB::table('socio_categorias')->where('cat_soc_id', $d['cat_soc_id'])->where('id_empresa_negocio', $suc)->first();
        if (! $cat) {
            return response()->json(['ok' => false, 'mensaje' => 'Elige una categoría.']);
        }
        $q = DB::table('socios')->where('id_empresa_negocio', $suc);
        if (! empty($d['ids'])) {
            $q->whereIn('soc_id', $d['ids']);
        } elseif (! empty($d['sin_categoria'])) {
            $q->whereNull('cat_soc_id')->whereIn('estado', ['ACTIVO', 'SUSPENDIDO']);
        } else {
            return response()->json(['ok' => false, 'mensaje' => 'Marca al menos un socio.']);
        }
        $n = $q->update(['cat_soc_id' => $cat->cat_soc_id]);

        return response()->json(['ok' => true, 'mensaje' => "{$n} socio(s) ahora son {$cat->nombre} (S/ ".number_format($cat->cuota, 2).' al mes).']);
    }

    /** Olvidó su contraseña del portal: vuelve a ser su DNI/RUC y se le pedirá crear otra */
    public function restablecerClave(int $id)
    {
        $this->puedeUsar();
        DB::table('socios')->where('soc_id', $id)->where('id_empresa_negocio', $this->sucursal())->update(['clave' => null]);

        return response()->json(['ok' => true, 'mensaje' => 'Listo: su contraseña del portal vuelve a ser su DNI/RUC.']);
    }

    // ------------------------------------------------------------------ cuotas y cargos

    public function previa(Request $request)
    {
        $this->soloAdmin();
        $periodo = preg_match('/^\d{6}$/', (string) $request->periodo) ? $request->periodo : now()->format('Ym');

        return response()->json(Socios::previsualizar($this->sucursal(), $periodo) + ['mes' => Socios::nombreMes($periodo)]);
    }

    public function generar(Request $request)
    {
        $this->soloAdmin();
        $d = $request->validate(['periodo' => 'required|digits:6']);

        return $this->json(function () use ($d) {
            $r = Socios::generarCuotas(Auth::user(), $d['periodo']);

            return $r + ['mensaje' => "Se generaron {$r['cargos']} cuotas de ".Socios::nombreMes($d['periodo']).' por S/ '.number_format($r['total'], 2).'.'
                .($r['suspendidos'] ? " {$r['suspendidos']} socio(s) pasaron a SUSPENDIDO por deuda." : '')];
        });
    }

    public function cargar(Request $request)
    {
        $this->soloAdmin();
        $d = $request->validate([
            'IdProducto' => 'required|integer', 'descripcion' => 'nullable|string|max:150', 'monto' => 'required|numeric|min:0.01|max:999999',
            'cat_soc_id' => 'nullable|integer', 'soc_ids' => 'nullable|array|max:5000', 'soc_ids.*' => 'integer', 'periodo' => 'nullable|digits:6',
        ]);

        return $this->json(function () use ($d) {
            $r = Socios::cargar(Auth::user(), $d);

            return $r + ['mensaje' => "Cargo aplicado a {$r['cargos']} socio(s) por S/ ".number_format($r['total'], 2).'.'];
        });
    }

    public function anularCargo(int $id)
    {
        $this->soloAdmin();
        $c = DB::table('socio_cargos')->where('car_id', $id)->where('id_empresa_negocio', $this->sucursal())->first();
        if (! $c || $c->estado !== 'PENDIENTE') {
            return response()->json(['ok' => false, 'mensaje' => 'Ese cargo ya no está pendiente.']);
        }
        if ($c->pagado > 0) {
            return response()->json(['ok' => false, 'mensaje' => 'Ya tiene pagos a cuenta: anula primero esos comprobantes.']);
        }
        DB::table('socio_cargos')->where('car_id', $id)->update(['estado' => 'ANULADO']);
        Socios::actualizarMorosidad($this->sucursal(), (int) $c->soc_id);

        return response()->json(['ok' => true, 'mensaje' => 'Cargo anulado.']);
    }

    // ------------------------------------------------------------------ cobro

    public function cobrar(Request $request, int $id)
    {
        $this->puedeUsar();
        $d = $request->validate([
            'montos' => 'required|array|min:1', 'montos.*' => 'nullable|numeric|min:0|max:999999',
            'tdocod' => 'required|in:01,03,13', 'clinum' => 'nullable|string|max:15', 'clinom' => 'nullable|string|max:150',
            'tdicod' => 'nullable|string|size:1', 'clidir' => 'nullable|string|max:150',
            'id_med_pag' => 'nullable|array|max:5', 'mon_med_pag' => 'nullable|array|max:5', 'paga' => 'nullable|numeric|min:0',
            'imprimir' => 'nullable|boolean',
        ]);
        try {
            $cabId = Socios::cobrar(Auth::user(), $id, $d['montos'], $d);
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['ok' => false, 'mensaje' => config('app.debug') ? $e->getMessage() : 'No se pudo registrar el cobro.']);
        }

        $impreso = false;
        if (! empty($d['imprimir'])) {
            try {
                $impreso = Impresion::comprobante($cabId);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return response()->json(['ok' => true, 'impreso' => $impreso] + VentaDirecta::respuesta($cabId));
    }

    // ------------------------------------------------------------------ configuración

    public function guardarConfig(Request $request)
    {
        $this->soloAdmin();
        $d = $request->validate([
            'meses_suspension' => 'required|integer|min:0|max:36', 'edad_max_hijos' => 'required|integer|min:0|max:99',
            'parentescos' => 'required|string|max:255', 'IdProducto_ordinaria' => 'nullable|integer',
        ]);
        $suc = $this->sucursal();
        if (! empty($d['IdProducto_ordinaria']) && ! DB::table('productos')->where('IdProducto', $d['IdProducto_ordinaria'])->where('id_empresa_negocio', $suc)->exists()) {
            return response()->json(['ok' => false, 'mensaje' => 'Concepto no válido.']);
        }
        $d['parentescos'] = implode(',', array_unique(array_filter(array_map(fn ($p) => mb_strtoupper(trim($p)), explode(',', $d['parentescos'])))));
        DB::table('socio_config')->updateOrInsert(['id_empresa_negocio' => $suc], $d);
        Socios::actualizarMorosidad($suc);

        return response()->json(['ok' => true, 'mensaje' => 'Configuración guardada.']);
    }

    /** Todos los clientes con DNI pasan a ser socios (el usuario retira luego a los que no lo son) */
    public function desdeClientes(Request $request)
    {
        $this->soloAdmin();
        $d = $request->validate(['cat_soc_id' => 'nullable|integer']);
        $cat = ! empty($d['cat_soc_id']) && DB::table('socio_categorias')->where('cat_soc_id', $d['cat_soc_id'])->where('id_empresa_negocio', $this->sucursal())->exists()
            ? (int) $d['cat_soc_id'] : null;
        $n = Socios::desdeClientes(Auth::user(), $cat);

        return response()->json(['ok' => true, 'mensaje' => $n ? "Se agregaron {$n} socios desde los clientes con DNI." : 'Todos los clientes con DNI ya son socios.']);
    }

    public function guardarCategoria(Request $request)
    {
        $this->soloAdmin();
        $d = $request->validate(['cat_soc_id' => 'nullable|integer', 'nombre' => 'required|string|max:60', 'cuota' => 'required|numeric|min:0|max:99999',
            'activo' => 'nullable|boolean']);
        $fila = ['nombre' => mb_strtoupper(trim($d['nombre'])), 'cuota' => $d['cuota'], 'activo' => (int) ($d['activo'] ?? 1)];
        if (! empty($d['cat_soc_id'])) {
            DB::table('socio_categorias')->where('cat_soc_id', $d['cat_soc_id'])->where('id_empresa_negocio', $this->sucursal())->update($fila);
        } else {
            DB::table('socio_categorias')->insert($fila + ['id_empresa_negocio' => $this->sucursal()]);
        }

        return response()->json(['ok' => true, 'mensaje' => 'Categoría guardada.']);
    }

    // ------------------------------------------------------------------ carnet y portería

    private function qr(string $texto, int $tam = 180): string
    {
        $svg = (new Writer(new ImageRenderer(new RendererStyle($tam, 1), new SvgImageBackEnd)))->writeString($texto);

        return preg_replace('/^<\?xml[^>]*>\s*/', '', $svg);
    }

    /** Carnets del titular y sus familiares activos (para imprimir) */
    public function carnet(int $id)
    {
        $this->puedeUsar();
        $suc = $this->sucursal();
        $s = DB::table('socios as s')->join('cliente as c', 'c.clicod', '=', 's.clicod')
            ->leftJoin('socio_categorias as k', 'k.cat_soc_id', '=', 's.cat_soc_id')
            ->where('s.soc_id', $id)->where('s.id_empresa_negocio', $suc)->first(['s.*', 'c.clinum', 'c.clinom', 'k.nombre as categoria']);
        abort_unless($s, 404);

        $carnets = [['nombre' => $s->clinom, 'dni' => $s->clinum, 'tipo' => 'TITULAR', 'qr' => $this->qr(route('socios.verificar', $s->token))]];
        foreach (DB::table('socio_familiares')->where('soc_id', $id)->where('activo', 1)->get() as $f) {
            $carnets[] = ['nombre' => $f->nombre, 'dni' => $f->dni, 'tipo' => $f->parentesco, 'qr' => $this->qr(route('socios.verificar', $f->token))];
        }
        $negocio = DB::table('empresa_negocios as n')->join('empresa as e', 'e.IdEmpresa', '=', 'n.IdEmpresa')
            ->where('n.id_empresa_negocio', $suc)->first(['n.nombre_comercial', 'n.logo_suc', 'e.NomEmpresa', 'e.LogEmpresa']);

        return view('empresas.socios.carnet', ['socio' => $s, 'carnets' => $carnets, 'negocio' => $negocio]);
    }

    /** Lo abre el celular de portería al escanear el carnet (sin sesión): si está al día, con deuda o suspendido */
    public function verificar(string $token)
    {
        $fam = null;
        $s = DB::table('socios')->where('token', $token)->first();
        if (! $s) {
            $fam = DB::table('socio_familiares')->where('token', $token)->first();
            $s = $fam ? DB::table('socios')->where('soc_id', $fam->soc_id)->first() : null;
        }
        abort_unless($s, 404);

        $cfg = Socios::config((int) $s->id_empresa_negocio);
        $meses = (int) (Socios::mesesDebe((int) $s->id_empresa_negocio)[$s->soc_id] ?? 0);
        $deuda = (float) DB::table('socio_cargos')->where('soc_id', $s->soc_id)->where('estado', 'PENDIENTE')->sum(DB::raw('monto - pagado'));
        [$texto, $color] = Socios::situacion($s, $meses, $deuda);
        // Gimnasio: lo que importa es si su plan está vigente o congelado
        if (Gimnasio::usa((int) $s->id_empresa_negocio)) {
            $sit = Gimnasio::situacionDe((int) $s->soc_id, (int) $s->id_empresa_negocio);
            [$texto, $color] = [$sit['texto'], ['green' => 'green', 'amber' => 'amber', 'red' => 'red'][$sit['color']] ?? 'gray'];
        }
        $titular = DB::table('cliente')->where('clicod', $s->clicod)->value('clinom');
        $aviso = null;
        if ($fam) {
            if (! $fam->activo) {
                [$texto, $color] = ['DADO DE BAJA', 'gray'];
            }
            $edad = $fam->fecha_nac ? Carbon::parse($fam->fecha_nac)->age : null;
            if ($edad !== null && str_starts_with($fam->parentesco, 'HIJO') && $cfg->edad_max_hijos > 0 && $edad > $cfg->edad_max_hijos) {
                $aviso = "Tiene {$edad} años: pasa la edad máxima para hijos ({$cfg->edad_max_hijos}).";
            }
        }
        $negocio = DB::table('empresa_negocios as n')->join('empresa as e', 'e.IdEmpresa', '=', 'n.IdEmpresa')
            ->where('n.id_empresa_negocio', $s->id_empresa_negocio)->first(['n.nombre_comercial', 'n.logo_suc', 'e.NomEmpresa', 'e.LogEmpresa']);

        return view('empresas.socios.verificar', [
            'nombre' => $fam ? $fam->nombre : $titular, 'tipo' => $fam ? $fam->parentesco.' DE '.$titular : 'TITULAR',
            'codigo' => $s->codigo, 'texto' => $texto, 'color' => $color, 'aviso' => $aviso, 'negocio' => $negocio,
        ]);
    }
}
