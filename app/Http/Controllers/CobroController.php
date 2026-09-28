<?php
namespace App\Http\Controllers;

use App\Models\{Pedido, PedidoDetalle, Mesa, Piso, Producto, Cliente, Almacen, MedioPago, EmpresaNegocio, Empresa};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB, Http};

class CobroController extends Controller
{
    // Igual que tu registrar_cobro. OJO: el IGV estándar es 1.18; confirma si 1.105 es intencional.
    private const FACTOR_IGV = 1.105;

    private function autorizar(): void
    {
        $permitido = DB::table('role_user')
            ->where('user_IdUsuario', Auth::user()->IdUsuario)
            ->whereIn('role_id', [2, 4]) // admin y caja
            ->exists();

        abort_unless($permitido, 403, 'Solo Administrador o Caja pueden cobrar.');
    }

    private function itemsPendientes(int $pedId)
    {
        return PedidoDetalle::where('ped_id', $pedId)
            ->where('estadoitem', '!=', 'Eliminado')
            ->whereRaw('(ped_det_can - IFNULL(item_facturado, 0)) > 0')
            ->get();
    }

    public function cobrar($ped_id)
    {
        $this->autorizar();
        $user = Auth::user();

        $pedido = Pedido::where('ped_id', $ped_id)
            ->where('ped_est', 'Aperturado')
            ->where('id_empresa_negocio', $user->id_empresa_negocio)
            ->first();

        if (!$pedido) {
            return redirect()->route('comandas.seleccion');
        }

        $detalle = $this->itemsPendientes($pedido->ped_id)->each(function ($d) {
            $d->cantidad_pendiente = $d->ped_det_can - $d->item_facturado;
        });

        $total = round($detalle->sum(fn($d) => $d->cantidad_pendiente * $d->ped_det_pre), 2);

        $comprobantes = DB::table('tipo_documento')->where('caja', 1)->get();
        $documentos   = DB::table('tipo_documento_identidad')->orderBy('orden')->get();
        $estadopagos  = DB::table('credito_dias')->where('id_empresa_negocio', $user->id_empresa_negocio)->get();
        $mediospagos  = MedioPago::where('id_empresa_negocio', $user->id_empresa_negocio)->get();
        $negocio      = EmpresaNegocio::find($user->id_empresa_negocio);
        $mesa         = $pedido->mes_id ? Mesa::find($pedido->mes_id) : null;
        $piso         = $pedido->pis_id ? Piso::find($pedido->pis_id) : null;

        return view('empresas.cobros.cobrar', compact(
            'pedido', 'detalle', 'total', 'comprobantes', 'documentos',
            'estadopagos', 'mediospagos', 'negocio', 'mesa', 'piso'
        ));
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

        // RUC: tu microservicio propio (sin token). El DNI lo dejamos para después (necesita token en .env)
        if (strlen($doc) === 11 && ctype_digit($doc)) {
            try {
                $r = Http::timeout(8)->withOptions(['verify' => false])
                    ->get("https://consultas.holape.app/api/v1/ruc/{$doc}")->json();

                if (!empty($r['success'])) {
                    return response()->json([
                        'nom' => $r['data']['razon_social'], 'dir' => $r['data']['direccion'], 'tdicod' => '6',
                    ]);
                }
            } catch (\Throwable $e) {
                // si el servicio no responde, seguimos con el mensaje de "no encontrado"
            }
        }

        return response()->json(['error' => 'No se encontró el documento. Ingresa los datos manualmente.']);
    }

    public function registrar(Request $request)
    {
        $this->autorizar();

        $request->validate([
            'ped_id'     => 'required|integer',
            'tdocod'     => 'required|in:01,03,13',
            'estadopago' => 'required|integer',
            'fecEmi'     => 'required|date',
            'tdicod'     => 'required|string|size:1',
            'clinum'     => 'required|string|max:15',
            'clinom'     => 'required|string|max:120',
        ], [], [
            'clinum' => 'DNI / RUC', 'clinom' => 'Nombre o razón social', 'fecEmi' => 'Fecha de emisión',
        ]);

        $user = Auth::user();

        try {
            $cabId = DB::transaction(function () use ($request, $user) {
                $tdocod = $request->tdocod;

                $pedido = Pedido::where('ped_id', $request->ped_id)
                    ->where('ped_est', 'Aperturado')
                    ->where('id_empresa_negocio', $user->id_empresa_negocio)
                    ->lockForUpdate()->first();

                if (!$pedido) {
                    throw new \RuntimeException('El pedido ya fue cobrado o no existe.');
                }

                $items = $this->itemsPendientes($pedido->ped_id);
                if ($items->isEmpty()) {
                    throw new \RuntimeException('El pedido no tiene ítems pendientes de cobro.');
                }

                $cre = DB::table('credito_dias')
                    ->where('cre_dia_id', $request->estadopago)
                    ->where('id_empresa_negocio', $user->id_empresa_negocio)->first();
                if (!$cre) {
                    throw new \RuntimeException('Estado de pago no válido.');
                }
                $esContado = $cre->cre_dia_tip === 'CONTADO';

                // ---- Cliente ----
                $clinum = trim($request->clinum);

                if ($tdocod === '01') {
                    $okRuc = strlen($clinum) === 11
                        && in_array(substr($clinum, 0, 2), ['10', '20', '15', '17'])
                        && $request->tdicod === '6';
                    if (!$okRuc) {
                        throw new \RuntimeException('TIPO DE DOCUMENTO NO PERMITIDO PARA EMITIR UNA FACTURA (requiere RUC válido).');
                    }
                }
                if (!$esContado && $clinum === '00000000') {
                    throw new \RuntimeException('Para vender a crédito debes identificar al cliente.');
                }

                if ($clinum === '00000000') {
                    $cliente = Cliente::firstOrCreate(
                        ['clinum' => $clinum, 'rucemp' => $user->IdEmpresa],
                        ['clinom' => 'VENTA AL PORTADOR', 'tdicod' => '1', 'clidir' => '--']
                    );
                } else {
                    $cliente = Cliente::updateOrCreate(
                        ['clinum' => $clinum, 'rucemp' => $user->IdEmpresa],
                        [
                            'clinom'   => strtoupper(trim($request->clinom)),
                            'clidir'   => $request->clidir ?: '--',
                            'clicor'   => $request->clicor,
                            'tdicod'   => $request->tdicod,
                            'telefono' => $request->telefono,
                        ]
                    );
                }

                // ---- Serie y correlativo (bloqueado para que dos cajas no repitan número) ----
                $cols = [
                    '01' => ['FseEmpresa', 'FnuEmpresa'],
                    '03' => ['BseEmpresa', 'BnuEmpresa'],
                    '13' => ['SerNota', 'NumNota'],
                ];
                [$colSerie, $colNum] = $cols[$tdocod];

                $sucursal = EmpresaNegocio::where('id_empresa_negocio', $user->id_empresa_negocio)->lockForUpdate()->first();
                $numero = $sucursal->$colNum + 1;
                $serie  = $sucursal->$colSerie;
                $sucursal->$colNum = $numero;
                $sucursal->save();

                // ---- Totales ----
                $gravado = $sucursal->tip_igv_pred === '10';
                $total = round($items->sum(fn($i) => ($i->ped_det_can - $i->item_facturado) * $i->ped_det_pre), 2);

                $ccatvg = $gravado ? round($total / self::FACTOR_IGV, 2) : 0;
                $ccaigv = $gravado ? round($total - $ccatvg, 2) : 0;
                $ccatexo = $gravado ? 0 : $total;

                $fecEmi = $request->fecEmi;
                $fecVen = $esContado ? $fecEmi : $request->fecVen;
                if (!$esContado && (!$fecVen || $fecVen <= $fecEmi)) {
                    throw new \RuntimeException('La fecha de vencimiento debe ser posterior a la de emisión.');
                }

                $paga = (float) $request->input('paga', 0);
                $almacen = Almacen::where('id_empresa_negocio', $user->id_empresa_negocio)->where('predeterminado', 1)->first();

                $cabId = DB::table('cpe_cabecera')->insertGetId([
                    'tdocod' => $tdocod, 'serdoc' => $serie, 'numdoc' => $numero,
                    'ccafem' => $fecEmi, 'ccafve' => $fecVen,
                    'tdicod' => $request->tdicod, 'ccandi' => $clinum, 'ccanom' => strtoupper(trim($request->clinom)),
                    'direccion' => $request->clidir ?: '--', 'clicod' => $cliente->clicod,
                    'clicorcli' => $request->clicor, 'telefono_cliente' => $request->telefono,
                    'ccatvg' => $ccatvg, 'ccaigv' => $ccaigv, 'ccatexo' => $ccatexo, 'ccaitv' => $total,
                    'totalcontado' => $esContado ? $total : 0, 'totalcredito' => $esContado ? 0 : $total,
                    'paga' => $paga, 'vuelto' => $esContado ? max(0, round($paga - $total, 2)) : 0,
                    'estadopago' => $esContado ? 'CONTADO' : 'CREDITO', 'cre_dia_id' => $cre->cre_dia_id,
                    'ccaobs' => $request->observaciones, 'consumo' => (int) $request->consumo,
                    'ped_id' => $pedido->ped_id, 'ped_tip' => $pedido->ped_tip,
                    'pis_id' => $pedido->pis_id, 'mes_id' => $pedido->mes_id, 'mozo' => $pedido->mozo,
                    'IdUsuario' => $user->IdUsuario, 'IdUsuario_ven' => $pedido->mozo,
                    'IdEmpresa' => $user->IdEmpresa, 'id_empresa_negocio' => $user->id_empresa_negocio,
                    'id_almacen' => $almacen?->id_almacen, 'id_turno' => (int) ($user->id_turno ?? 0),
                    'est_sunat' => $tdocod === '13' ? null : 'PENDIENTE',
                ]);

                // ---- Medios de pago (solo contado) ----
                if ($esContado) {
                    $ids = (array) $request->input('id_med_pag', []);
                    $montos = (array) $request->input('mon_med_pag', []);

                    if (count($ids)) {
                        $suma = round(array_sum(array_map('floatval', $montos)), 2);
                        if (abs($suma - $total) > 0.01) {
                            throw new \RuntimeException('Los medios de pago suman S/ ' . number_format($suma, 2)
                                . ' y el total es S/ ' . number_format($total, 2) . '.');
                        }
                        foreach ($ids as $k => $idMedio) {
                            DB::table('venta_medio_pago')->insert([
                                'IdCpe_cabecera' => $cabId, 'id_med_pag' => $idMedio, 'monto' => $montos[$k],
                                'id_turno' => (int) ($user->id_turno ?? 0), 'id_empresa_negocio' => $user->id_empresa_negocio,
                            ]);
                        }
                    } else {
                        $medio = MedioPago::where('id_empresa_negocio', $user->id_empresa_negocio)->orderByDesc('predeterminado')->first();
                        if (!$medio) {
                            throw new \RuntimeException('No hay medios de pago configurados.');
                        }
                        DB::table('venta_medio_pago')->insert([
                            'IdCpe_cabecera' => $cabId, 'id_med_pag' => $medio->id_med_pag, 'monto' => $total,
                            'id_turno' => (int) ($user->id_turno ?? 0), 'id_empresa_negocio' => $user->id_empresa_negocio,
                        ]);
                    }
                }

                // ---- Detalle ----
                $productos = Producto::whereIn('IdProducto', $items->pluck('IdProducto'))->get()->keyBy('IdProducto');

                foreach ($items as $it) {
                    $prod = $productos[$it->IdProducto] ?? null;
                    $cant = (float) ($it->ped_det_can - $it->item_facturado);
                    $precio = (float) $it->ped_det_pre;
                    $totalLinea = round($cant * $precio, 2);

                    $subtotal = $gravado ? round($totalLinea / self::FACTOR_IGV, 2) : $totalLinea;
                    $valorUni = $gravado ? round($precio / self::FACTOR_IGV, 2) : $precio;

                    DB::table('cpe_detalle')->insert([
                        'IdCpe_cabecera' => $cabId, 'IdProducto' => $it->IdProducto, 'IdProducto_rel' => $it->IdProducto,
                        'procod' => $prod->procod ?? '', 'umecod' => $prod->umecod ?? 'NIU',
                        'cdecan' => $cant, 'cdedes' => $it->descripcion,
                        'cdevun' => $valorUni, 'cdepuni' => $precio, 'cdepve' => $subtotal,
                        'cdeigv' => round($totalLinea - $subtotal, 2), 'cdevve' => $totalLinea,
                        'tigcod' => $sucursal->tip_igv_pred, 'costo' => $prod->costo ?? 0,
                        'cpe_det_factor' => 1, 'id_almacen_pro' => $almacen?->id_almacen,
                    ]);

                    PedidoDetalle::where('ped_det_id', $it->ped_det_id)->update(['item_facturado' => $it->ped_det_can]);

                    // Stock: solo productos simples. Preparados y combos (recetas) quedan para el módulo de almacén.
                    if ($prod && (int) $prod->promocion === 0 && $almacen) {
                        DB::table('producto_stock')
                            ->where('IdProducto', $it->IdProducto)
                            ->where('id_almacen', $almacen->id_almacen)
                            ->decrement('stock', $cant);
                    }
                }

                // ---- Cerrar pedido y liberar mesa ----
                $pedido->update(['ped_est' => 'Cerrado', 'fecha_hora_modificacion' => now()]);
                if ($pedido->mes_id) {
                    Mesa::where('mes_id', $pedido->mes_id)->update(['mes_est' => 'Libre']);
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

        return response()->json([
            'estado' => 'success',
            'mensaje' => 'Comprobante emitido',
            'redirect' => route('cobros.voucher', $cabId) . '?imprimir=' . ($request->imprimir ? 1 : 0),
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

        return view('empresas.cobros.voucher', compact('cab', 'detalle', 'medios', 'empresa', 'negocio', 'tdodes'));
    }
}