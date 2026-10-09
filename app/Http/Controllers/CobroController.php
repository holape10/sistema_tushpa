<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\EmpresaNegocio;
use App\Models\MedioPago;
use App\Models\Mesa;
use App\Models\Pedido;
use App\Models\PedidoDetalle;
use App\Models\Piso;
use App\Models\Turno;
use App\Support\Cocina;
use App\Support\Comprobante;
use App\Support\ConsultaPeru;
use App\Support\Fidelizacion;
use App\Support\Impresion\Impresion;
use App\Support\VentaDirecta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CobroController extends Controller
{
    // Se mantiene aquí porque SunatService lo usa; el valor vive en Comprobante

    private function autorizar(): void
    {
        abort_unless(Auth::user()->esAdminOCaja(), 403, 'Solo Administrador o Caja pueden cobrar.');
    }

    private function itemsPendientes(int $pedId)
    {
        return PedidoDetalle::where('ped_id', $pedId)
            ->where('estadoitem', '!=', 'Eliminado')
            ->whereRaw('(ped_det_can - IFNULL(item_facturado, 0)) > 0')
            ->orderBy('ped_det_id')
            ->get();
    }

    /**
     * Agrupa las líneas pendientes por producto y precio: una sola línea por producto en el cobro y en el comprobante.
     * Cada grupo guarda sus líneas originales para repartir lo cobrado (item_facturado).
     */
    private function agrupar($items)
    {
        return $items->groupBy(fn ($d) => $d->IdProducto.'|'.number_format((float) $d->ped_det_pre, 2, '.', ''))
            ->map(function ($lineas, $clave) {
                $primera = $lineas->first();

                return (object) [
                    'clave' => $clave,
                    'IdProducto' => $primera->IdProducto,
                    'descripcion' => $primera->descripcion,
                    'ped_det_pre' => (float) $primera->ped_det_pre,
                    'item_obs' => $lineas->pluck('item_obs')->filter()->unique()->implode(' / '),
                    'cantidad_pendiente' => round($lineas->sum(fn ($d) => $d->ped_det_can - $d->item_facturado), 2),
                    'lineas' => $lineas,
                ];
            })->values();
    }

    /** A dónde vuelve el cajero: las habitaciones si es hotel, si no las mesas */
    private function volver(?Pedido $pedido): string
    {
        return match ($pedido?->ped_tip) {
            'Hotel' => route('hotel.index'),
            'Clinica' => route('clinica.agenda'),
            default => route('comandas.seleccion'),
        };
    }

    public function separadas($ped_id)
    {
        return $this->cobrar($ped_id, true);
    }

    public function cobrar($ped_id, bool $separadas = false)
    {
        $this->autorizar();
        $user = Auth::user();

        $turno = Turno::abiertoDe($user);
        if (! $turno) {
            return redirect()->route('turnos.index')->with('error', 'Debes aperturar tu turno antes de cobrar.');
        }

        $pedido = Pedido::where('ped_id', $ped_id)
            ->where('ped_est', 'Aperturado')
            ->where('id_empresa_negocio', $user->id_empresa_negocio)
            ->first();

        if (! $pedido) {
            return redirect()->route('comandas.seleccion');
        }
        $volver = $this->volver($pedido);

        $detalle = $this->agrupar($this->itemsPendientes($pedido->ped_id));
        if ($detalle->isEmpty()) {
            return redirect($volver)->with('error', 'El pedido no tiene productos pendientes de cobro.');
        }

        $total = round($detalle->sum(fn ($d) => $d->cantidad_pendiente * $d->ped_det_pre), 2);
        // Lo ya cobrado en cuentas separadas anteriores
        $cuentasPrevias = DB::table('cpe_cabecera')->where('ped_id', $pedido->ped_id)->whereNull('ccabaj')
            ->orderBy('IdCpe_cabecera')->get(['IdCpe_cabecera', 'serdoc', 'numdoc', 'ccanom', 'ccaitv']);

        $comprobantes = DB::table('tipo_documento')->where('caja', 1)->get();
        $documentos = DB::table('tipo_documento_identidad')->orderBy('orden')->get();
        $estadopagos = DB::table('credito_dias')->where('id_empresa_negocio', $user->id_empresa_negocio)->get();
        $mediospagos = MedioPago::where('id_empresa_negocio', $user->id_empresa_negocio)->get();
        $negocio = EmpresaNegocio::find($user->id_empresa_negocio);
        $mesa = $pedido->mes_id ? Mesa::find($pedido->mes_id) : null;
        $piso = $pedido->pis_id ? Piso::find($pedido->pis_id) : null;

        return view('empresas.cobros.cobrar', compact(
            'pedido', 'detalle', 'total', 'comprobantes', 'documentos',
            'estadopagos', 'mediospagos', 'negocio', 'mesa', 'piso', 'turno', 'separadas', 'cuentasPrevias', 'volver'
        ));
    }

    /**
     * Punto de venta desde Comandas: venta directa (sin mesa) con la misma pantalla del cobro de mesa.
     * Emite con VentaDirecta, igual que los otros puntos de venta, y usa la afectación de IGV de la sucursal.
     */
    public function directa()
    {
        $this->autorizar();
        $user = Auth::user();

        $turno = Turno::abiertoDe($user);
        if (! $turno) {
            return redirect()->route('turnos.index')->with('error', 'Debes aperturar tu turno antes de vender.');
        }

        return view('empresas.cobros.cobrar', [
            'directa' => true, 'separadas' => false,
            'volver' => request('desde') === 'hotel' ? route('hotel.index') : route('comandas.seleccion'), 'pedido' => null, 'detalle' => collect(), 'total' => 0,
            'cuentasPrevias' => collect(), 'mesa' => null, 'piso' => null, 'turno' => $turno,
            'comprobantes' => DB::table('tipo_documento')->where('caja', 1)->get(),
            'documentos' => DB::table('tipo_documento_identidad')->orderBy('orden')->get(),
            'estadopagos' => DB::table('credito_dias')->where('id_empresa_negocio', $user->id_empresa_negocio)->get(),
            'mediospagos' => MedioPago::where('id_empresa_negocio', $user->id_empresa_negocio)->get(),
            'negocio' => EmpresaNegocio::find($user->id_empresa_negocio),
            'categorias' => DB::table('categorias')->where('id_empresa_negocio', $user->id_empresa_negocio)->where('visible', 1)->get(),
        ]);
    }

    public function registrarDirecta(Request $request)
    {
        $this->autorizar();
        $user = Auth::user();
        $datos = $request->validate(VentaDirecta::REGLAS + [
            'items.*.observacion' => 'nullable|string|max:100',
            'enviar_cocina' => 'nullable|boolean',
            'imprimir' => 'nullable|boolean',
        ], VentaDirecta::MENSAJES, VentaDirecta::NOMBRES);

        try {
            $cabId = VentaDirecta::registrar($user, $datos, 'PVCOMANDA');
        } catch (\RuntimeException $e) {
            return response()->json(['estado' => 'error', 'mensaje' => $e->getMessage()]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['estado' => 'error', 'mensaje' => config('app.debug') ? $e->getMessage() : 'Error al registrar la venta.']);
        }

        $respuesta = VentaDirecta::respuesta($cabId);

        // Platos a cocina (pantalla y comanda impresa), con el número del comprobante como referencia
        if ($request->boolean('enviar_cocina')) {
            try {
                $nombres = DB::table('productos')->whereIn('IdProducto', collect($datos['items'])->pluck('id')->filter())->pluck('pronom', 'IdProducto');
                $lineas = collect($datos['items'])->filter(fn ($i) => ! empty($i['id']))->map(fn ($i) => [
                    'IdProducto' => (int) $i['id'], 'nombre' => $nombres[$i['id']] ?? '', 'cantidad' => (float) $i['cantidad'],
                    'observacion' => $i['observacion'] ?? null,
                ])->values()->all();
                $destino = 'PUNTO DE VENTA';
                Impresion::comandaPara($user->id_empresa_negocio, $destino, $respuesta['numero'], $lineas);
                Cocina::registrarDirecta($user->id_empresa_negocio, $destino.' '.$respuesta['numero'], $lineas);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        // Comprobante directo a la impresora; si el agente no está conectado, la pantalla lo imprime con el navegador
        $impreso = false;
        if ($request->boolean('imprimir')) {
            try {
                $impreso = Impresion::comprobante($cabId);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return response()->json($respuesta + ['impreso' => $impreso]);
    }

    /** Búsqueda predictiva de clientes por nombre o número de documento (solo la BD propia) */
    public function sugerirClientes(Request $request)
    {
        $q = trim((string) $request->get('q'));
        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        $clientes = Cliente::where('rucemp', Auth::user()->IdEmpresa)
            ->where('clinum', '!=', '00000000')
            ->where(fn ($w) => $w->where('clinom', 'like', '%'.$q.'%')->orWhere('clinum', 'like', $q.'%'))
            ->orderByRaw('clinom LIKE ? DESC', [$q.'%']) // primero los que empiezan con lo escrito
            ->orderBy('clinom')
            ->limit(10)
            ->get(['clinum', 'clinom', 'clidir', 'clicor', 'telefono', 'tdicod']);

        return response()->json($clientes->map(fn ($c) => [
            'num' => $c->clinum, 'nom' => $c->clinom, 'dir' => $c->clidir, 'cor' => $c->clicor,
            'tel' => $c->telefono, 'tdicod' => $c->tdicod,
        ]));
    }

    public function buscarCliente($doc)
    {
        $user = Auth::user();
        $doc = trim($doc);

        $cli = Cliente::where('clinum', $doc)->where('rucemp', $user->IdEmpresa)->first();
        if ($cli) {
            return response()->json([
                'nom' => $cli->clinom, 'dir' => $cli->clidir, 'tdicod' => $cli->tdicod,
                'cor' => $cli->clicor, 'tel' => $cli->telefono,
            ]);
        }

        // RUC: tu servicio consultas.holape.app · DNI: consultas.holape.app y, de respaldo, apiperu.dev
        if ($r = ConsultaPeru::ruc($doc)) {
            return response()->json(['nom' => $r['nombre'], 'dir' => $r['direccion'], 'tdicod' => '6']);
        }
        if ($r = ConsultaPeru::dni($doc)) {
            return response()->json(['nom' => $r['nombre'], 'dir' => '', 'tdicod' => '1']);
        }

        return response()->json(['error' => 'No se encontró el documento. Ingresa los datos manualmente.']);
    }

    public function registrar(Request $request)
    {
        $this->autorizar();

        $request->validate([
            'ped_id' => 'required|integer',
            'tdocod' => 'required|in:01,03,13',
            'estadopago' => 'required|integer',
            'fecEmi' => 'required|date',
            'tdicod' => 'required|string|size:1',
            'clinum' => 'required|string|max:15',
            'clinom' => 'required|string|max:120',
        ], [], [
            'clinum' => 'DNI / RUC', 'clinom' => 'Nombre o razón social', 'fecEmi' => 'Fecha de emisión',
        ]);

        $user = Auth::user();

        try {
            $cabId = DB::transaction(function () use ($request, $user) {
                $tdocod = $request->tdocod;

                // Sin turno abierto no se cobra: toda venta queda amarrada a un turno de caja
                $turno = Turno::where('IdUsuario', $user->IdUsuario)
                    ->where('id_empresa_negocio', $user->id_empresa_negocio)
                    ->where('estado', 'ABIERTO')->lockForUpdate()->first();
                if (! $turno) {
                    throw new \RuntimeException('Debes aperturar tu turno antes de cobrar.');
                }

                $pedido = Pedido::where('ped_id', $request->ped_id)
                    ->where('ped_est', 'Aperturado')
                    ->where('id_empresa_negocio', $user->id_empresa_negocio)
                    ->lockForUpdate()->first();

                if (! $pedido) {
                    throw new \RuntimeException('El pedido ya fue cobrado o no existe.');
                }

                $items = $this->itemsPendientes($pedido->ped_id);
                if ($items->isEmpty()) {
                    throw new \RuntimeException('El pedido no tiene ítems pendientes de cobro.');
                }

                // ---- Qué se cobra: todo lo pendiente, o solo lo elegido (cuentas separadas) ----
                $seleccion = $request->input('separadas'); // [clave => cantidad] o null = todo
                $aCobrar = collect();
                foreach ($this->agrupar($items) as $g) {
                    $cant = $seleccion === null ? $g->cantidad_pendiente : round((float) ($seleccion[$g->clave] ?? 0), 2);
                    if ($cant <= 0) {
                        continue;
                    }
                    if ($cant > $g->cantidad_pendiente + 0.001) {
                        throw new \RuntimeException("De {$g->descripcion} solo quedan {$g->cantidad_pendiente} por cobrar. Actualiza la pantalla.");
                    }
                    $g->cantidad_cobrar = $cant;
                    $aCobrar->push($g);
                }
                if ($aCobrar->isEmpty()) {
                    throw new \RuntimeException('Elige al menos un producto para esta cuenta.');
                }

                // ---- Comprobante: cliente, serie, totales, medios de pago, detalle y kardex ----
                $lineas = $aCobrar->map(fn ($g) => [
                    'IdProducto' => $g->IdProducto, 'descripcion' => $g->descripcion,
                    'cantidad' => (float) $g->cantidad_cobrar, 'precio' => (float) $g->ped_det_pre,
                ])->values()->all();

                $cabId = Comprobante::emitir($user, $turno, $request->all(), $lineas, [
                    'ped_id' => $pedido->ped_id, 'ped_tip' => $pedido->ped_tip,
                    'pis_id' => $pedido->pis_id, 'mes_id' => $pedido->mes_id, 'mozo' => $pedido->mozo,
                    'IdUsuario_ven' => $pedido->mozo,
                ]);

                // Lo cobrado se reparte entre las líneas originales de cada producto
                foreach ($aCobrar as $it) {
                    $resta = (float) $it->cantidad_cobrar;
                    foreach ($it->lineas as $linea) {
                        if ($resta <= 0) {
                            break;
                        }
                        $toma = min($resta, (float) $linea->ped_det_can - (float) $linea->item_facturado);
                        if ($toma > 0) {
                            PedidoDetalle::where('ped_det_id', $linea->ped_det_id)->increment('item_facturado', $toma);
                            $resta = round($resta - $toma, 2);
                        }
                    }
                }

                // ---- Cerrar pedido y liberar mesa (solo cuando ya no queda nada por cobrar) ----
                // En hotel el pedido sigue abierto hasta "Dar salida": el huésped puede pagar al entrar y seguir pidiendo
                if ($pedido->ped_tip !== 'Hotel' && $this->itemsPendientes($pedido->ped_id)->isEmpty()) {
                    $pedido->update(['ped_est' => 'Cerrado', 'fecha_hora_modificacion' => now()]);
                    if ($pedido->mes_id) {
                        Mesa::where('mes_id', $pedido->mes_id)->update(['mes_est' => 'Libre']);
                    }
                } else {
                    $pedido->update(['fecha_hora_modificacion' => now()]);
                }

                return $cabId;
            });
        } catch (\RuntimeException $e) {
            return response()->json(['estado' => 'error', 'mensaje' => $e->getMessage()]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'estado' => 'error',
                'mensaje' => config('app.debug') ? $e->getMessage() : 'Error al procesar el cobro.',
            ]);
        }

        // Puntos del cliente: la pantalla siguiente los muestra en un aviso grande
        if ($puntos = Fidelizacion::resumen($cabId)) {
            session()->flash('puntos', $puntos);
        }

        // Impresión directa (sin vista previa) si el agente de impresión está conectado
        $impreso = false;
        if ($request->imprimir) {
            try {
                $impreso = Impresion::comprobante($cabId);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        if ($impreso) {
            $cab = DB::table('cpe_cabecera')->where('IdCpe_cabecera', $cabId)->first(['serdoc', 'numdoc', 'ped_id', 'ped_tip']);
            $sigueAbierto = $cab->ped_id && $cab->ped_tip !== 'Hotel' && Pedido::where('ped_id', $cab->ped_id)->where('ped_est', 'Aperturado')->exists();
            session()->flash('success', 'Comprobante '.$cab->serdoc.'-'.str_pad($cab->numdoc, 8, '0', STR_PAD_LEFT).' enviado a la impresora.');

            return response()->json([
                'estado' => 'success',
                'mensaje' => 'Comprobante emitido e impreso',
                // Cuentas separadas con saldo: vuelve a separadas para cobrar a la siguiente persona
                'redirect' => $sigueAbierto ? route('cobros.separadas', $cab->ped_id)
                    : ['Hotel' => route('hotel.index'), 'Clinica' => route('clinica.agenda')][$cab->ped_tip] ?? route('comandas.seleccion'),
            ]);
        }

        return response()->json([
            'estado' => 'success',
            'mensaje' => 'Comprobante emitido',
            'redirect' => route('cobros.voucher', $cabId).'?imprimir='.($request->imprimir ? 1 : 0),
        ]);
    }

    public function voucher($id)
    {
        $user = Auth::user();

        $cab = DB::table('cpe_cabecera')
            ->where('IdCpe_cabecera', $id)
            ->where('id_empresa_negocio', $user->id_empresa_negocio)->first();
        abort_unless($cab, 404);

        $detalle = DB::table('cpe_detalle')->where('IdCpe_cabecera', $id)->get();
        $medios = DB::table('venta_medio_pago')
            ->join('medios_pagos', 'medios_pagos.id_med_pag', '=', 'venta_medio_pago.id_med_pag')
            ->where('IdCpe_cabecera', $id)->get();
        $empresa = Empresa::find($cab->IdEmpresa);
        $negocio = EmpresaNegocio::find($cab->id_empresa_negocio);
        $tdodes = DB::table('tipo_documento')->where('tdocod', $cab->tdocod)->value('tdodes');

        // Si fue una cuenta separada y aún queda algo por cobrar, se ofrece cobrar la siguiente
        $pedidoPendiente = $cab->ped_id && $cab->ped_tip !== 'Hotel' && Pedido::where('ped_id', $cab->ped_id)->where('ped_est', 'Aperturado')->exists()
            ? $cab->ped_id : null;

        // Formato: el de la sucursal (ticket o A4), salvo que se pida el otro con ?formato=
        $formato = strtoupper((string) request('formato', $negocio->formato_impresion ?: 'TICKET')) === 'A4' ? 'A4' : 'TICKET';
        $datos = compact('cab', 'detalle', 'medios', 'empresa', 'negocio', 'tdodes', 'pedidoPendiente');

        return view($formato === 'A4' ? 'empresas.cobros.comprobante_a4' : 'empresas.cobros.voucher', $datos);
    }
}
