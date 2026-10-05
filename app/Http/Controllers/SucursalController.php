<?php
namespace App\Http\Controllers;

use App\Models\EmpresaNegocio;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB};

class SucursalController extends Controller
{
    // Serie y correlativo (último número usado) por tipo de comprobante
    public const SERIES = [
        '01' => ['serie' => 'FseEmpresa', 'numero' => 'FnuEmpresa', 'nombre' => 'Factura', 'regex' => '/^F[A-Z0-9]{3}$/'],
        '03' => ['serie' => 'BseEmpresa', 'numero' => 'BnuEmpresa', 'nombre' => 'Boleta', 'regex' => '/^B[A-Z0-9]{3}$/'],
        '13' => ['serie' => 'SerNota', 'numero' => 'NumNota', 'nombre' => 'Nota de venta', 'regex' => '/^[A-Z0-9]{4}$/'],
        // Notas electrónicas: una serie para las de facturas (F...) y otra para las de boletas (B...)
        '07F' => ['tdocod' => '07', 'serie' => 'SerNCF', 'numero' => 'NumNCF', 'nombre' => 'Nota de crédito de facturas', 'regex' => '/^F[A-Z0-9]{3}$/'],
        '07B' => ['tdocod' => '07', 'serie' => 'SerNCB', 'numero' => 'NumNCB', 'nombre' => 'Nota de crédito de boletas', 'regex' => '/^B[A-Z0-9]{3}$/'],
        '08F' => ['tdocod' => '08', 'serie' => 'SerNDF', 'numero' => 'NumNDF', 'nombre' => 'Nota de débito de facturas', 'regex' => '/^F[A-Z0-9]{3}$/'],
        '08B' => ['tdocod' => '08', 'serie' => 'SerNDB', 'numero' => 'NumNDB', 'nombre' => 'Nota de débito de boletas', 'regex' => '/^B[A-Z0-9]{3}$/'],
        // Proformas de los puntos de venta (no son comprobantes; su correlativo se cuenta en la tabla proformas)
        'PR' => ['tdocod' => 'PR', 'serie' => 'SerProforma', 'numero' => 'NumProforma', 'nombre' => 'Proforma', 'regex' => '/^[A-Z0-9]{4}$/'],
    ];

    private function autorizar(): void
    {
        abort_unless(Auth::user()->esAdmin(), 403, 'Solo el Administrador puede editar sucursales.');
    }

    private function sucursal($id): EmpresaNegocio
    {
        return EmpresaNegocio::where('id_empresa_negocio', $id)
            ->where('IdEmpresa', Auth::user()->IdEmpresa)
            ->firstOrFail();
    }

    /** Último número emitido de una serie en la sucursal */
    private function ultimoEmitido(int $idSucursal, string $tdocod, string $serie): int
    {
        if ($tdocod === 'PR') {
            return (int) DB::table('proformas')->where('id_empresa_negocio', $idSucursal)->where('serie', $serie)->max('numero');
        }
        return (int) DB::table('cpe_cabecera')
            ->where('id_empresa_negocio', $idSucursal)
            ->where('tdocod', $tdocod)->where('serdoc', $serie)
            ->max('numdoc');
    }

    public function index()
    {
        $this->autorizar();
        $sucursales = EmpresaNegocio::where('IdEmpresa', Auth::user()->IdEmpresa)->orderBy('id_empresa_negocio')->get();

        return view('empresas.sucursales.index', compact('sucursales'));
    }

    public function edit($id)
    {
        $this->autorizar();
        $sucursal = $this->sucursal($id);

        $ultimos = [];
        foreach (self::SERIES as $tdocod => $c) {
            $ultimos[$tdocod] = $this->ultimoEmitido($sucursal->id_empresa_negocio, $c['tdocod'] ?? $tdocod, (string) $sucursal->{$c['serie']});
        }

        return view('empresas.sucursales.edit', ['sucursal' => $sucursal, 'series' => self::SERIES, 'ultimos' => $ultimos]);
    }

    public function update(Request $request, $id)
    {
        $this->autorizar();
        $sucursal = $this->sucursal($id);

        // Series en mayúsculas antes de validar
        foreach (self::SERIES as $c) {
            $request->merge([$c['serie'] => strtoupper(trim((string) $request->input($c['serie'])))]);
        }

        $reglas = [
            'nombre_comercial' => 'required|string|max:255',
            'tipo_negocio'     => 'nullable|string|max:255',
            'estado'           => 'required|in:Activo,Inactivo',
            'direccion'        => 'required|string|max:255',
            'telefono'         => 'nullable|string|max:30',
            'correo'           => 'nullable|email|max:255',
            'web'              => 'nullable|string|max:255',
            'ubigeo'           => 'required|digits:6',
            'departamento'     => 'required|string|max:100',
            'provincia'        => 'required|string|max:100',
            'distrito'         => 'required|string|max:100',
            'codigofiscal'     => 'nullable|digits:4',
            'tip_igv_pred'     => 'required|in:10,20',
            'tdocod_pred'      => 'required|in:01,03,13',
            'formato_impresion' => 'required|in:TICKET,A4',
        ];
        $nombres = ['nombre_comercial' => 'Nombre comercial', 'codigofiscal' => 'Código de establecimiento',
            'tip_igv_pred' => 'Afectación IGV', 'tdocod_pred' => 'Comprobante predeterminado', 'formato_impresion' => 'Formato de impresión'];

        foreach (self::SERIES as $c) {
            $reglas[$c['serie']] = ['required', 'regex:' . $c['regex']];
            $reglas[$c['numero']] = 'required|integer|min:0|max:99999999';
            $nombres[$c['serie']] = 'Serie de ' . strtolower($c['nombre']);
            $nombres[$c['numero']] = 'Correlativo de ' . strtolower($c['nombre']);
        }

        $datos = $request->validate($reglas, [
            'FseEmpresa.regex' => 'La serie de factura debe empezar con F y tener 4 caracteres (ej. F001).',
            'BseEmpresa.regex' => 'La serie de boleta debe empezar con B y tener 4 caracteres (ej. B001).',
            'SerNota.regex'    => 'La serie de nota de venta debe tener 4 caracteres (ej. N001).',
            'SerNCF.regex'     => 'La serie de nota de crédito de facturas debe empezar con F (ej. FC01).',
            'SerNCB.regex'     => 'La serie de nota de crédito de boletas debe empezar con B (ej. BC01).',
            'SerNDF.regex'     => 'La serie de nota de débito de facturas debe empezar con F (ej. FD01).',
            'SerNDB.regex'     => 'La serie de nota de débito de boletas debe empezar con B (ej. BD01).',
            'SerProforma.regex' => 'La serie de proforma debe tener 4 caracteres (ej. PR01).',
        ], $nombres);

        // Reglas que dependen de lo ya emitido
        $errores = [];
        foreach (self::SERIES as $tdocod => $c) {
            $serie = $datos[$c['serie']];

            // El correlativo es el ÚLTIMO número usado: no puede quedar por debajo de lo emitido (se duplicaría)
            $ultimo = $this->ultimoEmitido($sucursal->id_empresa_negocio, $c['tdocod'] ?? $tdocod, $serie);
            if ((int) $datos[$c['numero']] < $ultimo) {
                $errores[$c['numero']] = "{$c['nombre']} {$serie}: ya se emitió hasta el número {$ultimo}; el correlativo no puede ser menor.";
            }

            // Otra sucursal de la misma empresa no puede usar la misma serie
            $ocupada = EmpresaNegocio::where('IdEmpresa', $sucursal->IdEmpresa)
                ->where('id_empresa_negocio', '!=', $sucursal->id_empresa_negocio)
                ->where($c['serie'], $serie)->value('nombre_comercial');
            if ($ocupada) {
                $errores[$c['serie']] = "La serie {$serie} ya la usa la sucursal {$ocupada}.";
            }
        }
        if ($errores) {
            return back()->withInput()->withErrors($errores);
        }

        $datos['codigofiscal'] = $datos['codigofiscal'] ?? null;
        foreach (['departamento', 'provincia', 'distrito'] as $campo) {
            $datos[$campo] = mb_strtoupper(trim($datos[$campo]));
        }

        DB::transaction(function () use ($sucursal, $datos) {
            // Bloqueo: si en este momento alguien está cobrando, se espera a que termine
            EmpresaNegocio::where('id_empresa_negocio', $sucursal->id_empresa_negocio)->lockForUpdate()->first();
            $sucursal->update($datos);
        });

        return redirect()->route('sucursales.index')->with('success', "Sucursal {$sucursal->nombre_comercial} actualizada.");
    }
}
