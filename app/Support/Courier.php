<?php

namespace App\Support;

use App\Models\Producto;
use App\Models\Turno;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Courier / envíos: el remitente deja su encomienda en una agencia, se cotiza por ruta (peso real o volumétrico, sobres,
 * reparto a domicilio y seguro), se cobra en origen o al recoger, viaja (en camino → llegó a destino → en reparto) y se
 * entrega pidiendo la clave de 4 dígitos que solo tiene el remitente. Cada cambio queda en la línea de tiempo que el
 * cliente ve en el rastreo público.
 *
 * Cada agencia la atiende una sucursal del sistema (la de destino recibe y entrega lo que le llega); la agencia de un
 * aliado (sin sucursal) la maneja la sucursal de origen.
 */
class Courier
{
    /** Estado => [texto para el cliente, ícono, color] */
    public const ESTADOS = [
        'REGISTRADO' => ['Recibido en agencia', 'fa-box', 'slate'],
        'EN_TRANSITO' => ['En camino', 'fa-truck-fast', 'sky'],
        'EN_DESTINO' => ['En agencia de destino', 'fa-warehouse', 'indigo'],
        'EN_REPARTO' => ['En reparto', 'fa-motorcycle', 'amber'],
        'ENTREGADO' => ['Entregado', 'fa-circle-check', 'emerald'],
        'DEVUELTO' => ['Devuelto al remitente', 'fa-rotate-left', 'rose'],
        'ANULADO' => ['Anulado', 'fa-ban', 'gray'],
    ];

    /** Formas de pago: el remitente al enviar, quien recoge, o el cliente con crédito en su estado de cuenta */
    public const PAGOS = ['ORIGEN' => 'Paga al enviar', 'DESTINO' => 'Paga al recoger', 'CREDITO' => 'Crédito (estado de cuenta)'];

    /** Pasos en bloque: estado nuevo => [desde qué estado, quién lo hace (origen | destino)] */
    public const PASOS = [
        'EN_TRANSITO' => ['REGISTRADO', 'origen'],
        'EN_DESTINO' => ['EN_TRANSITO', 'destino'],
        'EN_REPARTO' => ['EN_DESTINO', 'destino'],
    ];

    public const TIPOS_PAQUETE = ['PAQUETE', 'SOBRE'];

    /** Letras y números fáciles de dictar por teléfono (sin 0/O ni 1/I) */
    private const ALFABETO = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public static function config(string $ruc): object
    {
        $c = DB::table('cou_config')->where('IdEmpresa', $ruc)->first();
        if ($c) {
            return $c;
        }
        DB::table('cou_config')->insert(['IdEmpresa' => $ruc, 'created_at' => now(), 'updated_at' => now()]);

        return DB::table('cou_config')->where('IdEmpresa', $ruc)->first();
    }

    /** Agencias activas de la empresa con la sucursal que las atiende */
    public static function agencias(string $ruc): Collection
    {
        return DB::table('cou_agencias')->where('IdEmpresa', $ruc)->where('activo', 1)->orderBy('ciudad')->orderBy('nombre')->get();
    }

    /**
     * Agencias que atiende la sucursal del usuario; si no tiene ninguna, se crea con los datos de la sucursal
     * (así la primera vez ya se puede registrar envíos).
     */
    public static function agenciasPropias(User $user): Collection
    {
        $suc = (int) $user->id_empresa_negocio;
        $propias = DB::table('cou_agencias')->where('IdEmpresa', $user->IdEmpresa)->where('id_empresa_negocio', $suc)->where('activo', 1)->orderBy('nombre')->get();
        if ($propias->isNotEmpty()) {
            return $propias;
        }
        $n = DB::table('empresa_negocios')->where('id_empresa_negocio', $suc)->first();
        DB::table('cou_agencias')->insert([
            'nombre' => mb_strtoupper(mb_substr((string) ($n->nombre_comercial ?? 'AGENCIA PRINCIPAL'), 0, 100)),
            'ciudad' => mb_strtoupper(mb_substr((string) ($n->provincia ?: $n->distrito ?: 'PRINCIPAL'), 0, 60)),
            'direccion' => $n->direccion ?? null, 'telefono' => $n->telefono ?? null, 'id_empresa_negocio' => $suc, 'activo' => 1, 'IdEmpresa' => $user->IdEmpresa,
        ]);

        return DB::table('cou_agencias')->where('IdEmpresa', $user->IdEmpresa)->where('id_empresa_negocio', $suc)->get();
    }

    public static function etiquetaAgencia(?object $a): string
    {
        return $a ? $a->ciudad.' · '.$a->nombre : '—';
    }

    /**
     * Tarifa de la ruta (o la de la ruta al revés, o la general).
     *
     * @return array{base: float, kg_incluidos: float, por_kg: float, sobre: float, domicilio: float, dias: int, propia: bool}
     */
    public static function tarifa(object $cfg, int $origen, int $destino): array
    {
        $t = DB::table('cou_tarifas')->where('IdEmpresa', $cfg->IdEmpresa)
            ->where(fn ($q) => $q->where(fn ($w) => $w->where('origen_id', $origen)->where('destino_id', $destino))
                ->orWhere(fn ($w) => $w->where('origen_id', $destino)->where('destino_id', $origen)))
            ->orderByRaw('origen_id = ? DESC', [$origen])->first();
        $f = $t ?? $cfg;

        return ['base' => (float) $f->base, 'kg_incluidos' => (float) $f->kg_incluidos, 'por_kg' => (float) $f->por_kg, 'sobre' => (float) $f->sobre,
            'domicilio' => (float) $f->domicilio, 'dias' => (int) $f->dias, 'propia' => $t !== null];
    }

    /**
     * Precio del envío. Sobres: precio fijo cada uno. Paquetes: se cobra por el mayor entre el peso real y el volumétrico
     * (largo x ancho x alto / divisor); la base incluye los primeros kilos y cada kilo adicional (o fracción) se suma.
     *
     * @param  array{base: float, kg_incluidos: float, por_kg: float, sobre: float, domicilio: float}  $tarifa
     * @param  array<int, array{tipo?: string, cantidad?: int, peso?: float, largo?: float, ancho?: float, alto?: float}>  $paquetes
     * @return array{piezas: int, peso_real: float, peso_cobrado: float, flete: float, seguro: float, domicilio: float, total: float}
     */
    public static function cotizar(array $tarifa, array $paquetes, float $valorDeclarado, bool $aDomicilio, int $divisorVol = 5000, float $seguroPct = 0, float $seguroMin = 0): array
    {
        [$piezas, $sobres, $real, $cobrable] = [0, 0, 0.0, 0.0];
        foreach ($paquetes as $p) {
            $cant = max(1, (int) ($p['cantidad'] ?? 1));
            $piezas += $cant;
            if (($p['tipo'] ?? 'PAQUETE') === 'SOBRE') {
                $sobres += $cant;

                continue;
            }
            $peso = max(0, (float) ($p['peso'] ?? 0));
            $volumen = max(0, (float) ($p['largo'] ?? 0)) * max(0, (float) ($p['ancho'] ?? 0)) * max(0, (float) ($p['alto'] ?? 0));
            $real += $peso * $cant;
            $cobrable += max($peso, $divisorVol > 0 ? $volumen / $divisorVol : 0) * $cant;
        }
        $cobrable = round($cobrable, 2);
        $hayPaquetes = $piezas > $sobres;
        $adicionales = $hayPaquetes ? max(0, (int) ceil(round($cobrable - $tarifa['kg_incluidos'], 2))) : 0;
        $flete = round($sobres * $tarifa['sobre'] + ($hayPaquetes ? $tarifa['base'] + $adicionales * $tarifa['por_kg'] : 0), 2);
        $seguro = $valorDeclarado > 0 ? round(max($valorDeclarado * $seguroPct / 100, $seguroMin), 2) : 0.0;
        $domicilio = $aDomicilio ? round($tarifa['domicilio'], 2) : 0.0;

        return ['piezas' => $piezas, 'peso_real' => round($real, 2), 'peso_cobrado' => $cobrable, 'flete' => $flete, 'seguro' => $seguro,
            'domicilio' => $domicilio, 'total' => round($flete + $seguro + $domicilio, 2)];
    }

    /**
     * ¿La sucursal despacha (origen) o recibe y entrega (destino) este envío? Lo que pasa en la agencia de un aliado lo maneja
     * la sucursal del otro extremo (si el aliado recibe, lo marca el origen; si el aliado devuelve, lo despacha el destino).
     */
    public static function lado(object $envio, int $suc): array
    {
        $agencias = DB::table('cou_agencias')->whereIn('age_id', [$envio->origen_id, $envio->destino_id])->pluck('id_empresa_negocio', 'age_id');
        $sucOrigen = $agencias[$envio->origen_id] ?? null;
        $sucDestino = $agencias[$envio->destino_id] ?? null;
        $esMia = fn ($s) => $s !== null && (int) $s === $suc;

        return ['origen' => $esMia($sucOrigen) || ($sucOrigen === null && $esMia($sucDestino)),
            'destino' => $esMia($sucDestino) || ($sucDestino === null && $esMia($sucOrigen))];
    }

    /** @return array{0: Collection, 1: Collection} agencias de la sucursal y de los aliados */
    private static function agenciasDe(User $user): array
    {
        $todas = DB::table('cou_agencias')->where('IdEmpresa', $user->IdEmpresa)->get(['age_id', 'id_empresa_negocio']);

        return [$todas->where('id_empresa_negocio', $user->id_empresa_negocio)->pluck('age_id'), $todas->whereNull('id_empresa_negocio')->pluck('age_id')];
    }

    /** Envíos que ve la sucursal: los que salen de sus agencias o llegan a ellas */
    public static function deLaSucursal(User $user)
    {
        [$propias] = self::agenciasDe($user);

        return DB::table('cou_envios as e')->where(fn ($q) => $q->whereIn('e.origen_id', $propias)->orWhereIn('e.destino_id', $propias));
    }

    /** Filtro de los envíos que la sucursal despacha (los suyos y las devoluciones que salen de un aliado hacia ella) */
    public static function salen(User $user, $consulta)
    {
        [$propias, $aliadas] = self::agenciasDe($user);

        return $consulta->where(fn ($q) => $q->whereIn('e.origen_id', $propias)
            ->orWhere(fn ($w) => $w->whereIn('e.origen_id', $aliadas)->whereIn('e.destino_id', $propias)));
    }

    /** Filtro de los envíos que la sucursal recibe y entrega */
    public static function llegan(User $user, $consulta)
    {
        [$propias, $aliadas] = self::agenciasDe($user);

        return $consulta->where(fn ($q) => $q->whereIn('e.destino_id', $propias)
            ->orWhere(fn ($w) => $w->whereIn('e.destino_id', $aliadas)->whereIn('e.origen_id', $propias)));
    }

    public static function urlRastreo(string $codigo): string
    {
        return route('rastreo.ver', $codigo);
    }

    /** "JUAN CARLOS PÉREZ" → "JUAN C. P." (el rastreo es público) */
    public static function enmascarar(string $nombre): string
    {
        $partes = preg_split('/\s+/', trim($nombre)) ?: [];

        return trim(($partes[0] ?? '').' '.collect(array_slice($partes, 1))->map(fn ($p) => mb_substr($p, 0, 1).'.')->implode(' '));
    }

    private static function nuevoCodigo(): string
    {
        do {
            $codigo = collect(range(1, 10))->map(fn () => self::ALFABETO[random_int(0, strlen(self::ALFABETO) - 1)])->implode('');
        } while (DB::table('cou_envios')->where('codigo', $codigo)->exists());

        return $codigo;
    }

    public static function evento(int $envId, string $estado, ?string $detalle = null, ?int $ageId = null, ?int $usuario = null): void
    {
        DB::table('cou_eventos')->insert(['env_id' => $envId, 'estado' => $estado, 'detalle' => $detalle ? mb_substr($detalle, 0, 200) : null,
            'age_id' => $ageId, 'IdUsuario' => $usuario, 'fecha' => now()]);
    }

    // ------------------------------------------------------------------ registro

    /**
     * Registra el envío con sus paquetes y, si paga el remitente, emite su comprobante.
     *
     * @param  array<string, mixed>  $d  origen_id, destino_id, rem_doc, rem_nombre, rem_telefono, des_doc, des_nombre, des_telefono, entrega,
     *                                   direccion_entrega, contenido, valor_declarado, pago, observacion, paquetes[], cobro{tdocod, clinum, clinom, clidir, id_med_pag, paga}
     * @return array{env_id: int, cab_id: ?int}
     */
    public static function registrar(User $user, array $d): array
    {
        $ruc = (string) $user->IdEmpresa;
        $cfg = self::config($ruc);
        $propias = self::agenciasPropias($user)->pluck('age_id')->map(fn ($id) => (int) $id);
        if (! $propias->contains((int) $d['origen_id'])) {
            throw new \RuntimeException('La agencia de origen debe ser una de tu sucursal.');
        }
        $destino = DB::table('cou_agencias')->where('age_id', $d['destino_id'])->where('IdEmpresa', $ruc)->where('activo', 1)->first();
        if (! $destino || (int) $destino->age_id === (int) $d['origen_id']) {
            throw new \RuntimeException('Elige una agencia de destino distinta a la de origen.');
        }
        $domicilio = ($d['entrega'] ?? 'AGENCIA') === 'DOMICILIO';
        if ($domicilio && trim((string) ($d['direccion_entrega'] ?? '')) === '') {
            throw new \RuntimeException('Escribe la dirección de entrega a domicilio.');
        }
        $tarifa = self::tarifa($cfg, (int) $d['origen_id'], (int) $destino->age_id);
        $precio = self::cotizar($tarifa, $d['paquetes'], (float) ($d['valor_declarado'] ?? 0), $domicilio,
            (int) $cfg->divisor_vol, (float) $cfg->seguro_pct, (float) $cfg->seguro_min);
        if ($precio['total'] <= 0) {
            throw new \RuntimeException('El envío no tiene precio: revisa el peso de los paquetes y la tarifa de la ruta.');
        }

        $pago = array_key_exists($d['pago'] ?? '', self::PAGOS) ? $d['pago'] : 'ORIGEN';

        return DB::transaction(function () use ($user, $d, $precio, $tarifa, $domicilio, $destino, $pago) {
            $remDoc = preg_replace('/\D/', '', (string) $d['rem_doc']) ?: '00000000';
            $remNombre = mb_strtoupper(trim($d['rem_nombre']));
            $clicod = self::cliente($user, $remDoc, $remNombre, $d['rem_telefono'] ?? null);
            if ($pago === 'CREDITO') {
                CourierCuentas::validarCredito((string) $user->IdEmpresa, $clicod, $precio['total']);
            }

            $envId = DB::table('cou_envios')->insertGetId([
                'codigo' => self::nuevoCodigo(), 'clave' => str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT),
                'origen_id' => (int) $d['origen_id'], 'destino_id' => (int) $destino->age_id,
                'rem_clicod' => $clicod, 'rem_doc' => $remDoc, 'rem_nombre' => $remNombre, 'rem_telefono' => $d['rem_telefono'] ?? null,
                'des_doc' => (preg_replace('/\D/', '', (string) ($d['des_doc'] ?? '')) ?: null), 'des_nombre' => mb_strtoupper(trim($d['des_nombre'])),
                'des_telefono' => $d['des_telefono'] ?? null, 'entrega' => $domicilio ? 'DOMICILIO' : 'AGENCIA',
                'direccion_entrega' => $domicilio ? mb_strtoupper(trim($d['direccion_entrega'])) : null,
                'contenido' => isset($d['contenido']) ? mb_strtoupper(trim($d['contenido'])) : null, 'valor_declarado' => round((float) ($d['valor_declarado'] ?? 0), 2),
                'piezas' => $precio['piezas'], 'peso_real' => $precio['peso_real'], 'peso_cobrado' => $precio['peso_cobrado'],
                'flete' => $precio['flete'], 'seguro' => $precio['seguro'], 'domicilio' => $precio['domicilio'], 'total' => $precio['total'],
                'pago' => $pago, 'estado' => 'REGISTRADO',
                'llegada_estimada' => now()->addDays(max(0, $tarifa['dias']))->toDateString(), 'observacion' => $d['observacion'] ?? null,
                'IdUsuario' => $user->IdUsuario, 'id_empresa_negocio' => $user->id_empresa_negocio, 'creado' => now(),
            ]);
            foreach ($d['paquetes'] as $p) {
                $sobre = ($p['tipo'] ?? 'PAQUETE') === 'SOBRE';
                // Medida en cm (vacía o en cero = sin medir); los sobres no llevan peso ni medidas
                $medida = fn (string $k) => $sobre || empty($p[$k]) ? null : round((float) $p[$k], 1);
                DB::table('cou_paquetes')->insert(['env_id' => $envId, 'tipo' => $sobre ? 'SOBRE' : 'PAQUETE', 'cantidad' => max(1, (int) ($p['cantidad'] ?? 1)),
                    'peso' => $sobre ? 0 : round((float) ($p['peso'] ?? 0), 2), 'largo' => $medida('largo'), 'ancho' => $medida('ancho'), 'alto' => $medida('alto'),
                    'descripcion' => isset($p['descripcion']) ? mb_strtoupper(mb_substr(trim((string) $p['descripcion']), 0, 100)) : null]);
            }
            self::evento($envId, 'REGISTRADO', 'Recibido en agencia', (int) $d['origen_id'], $user->IdUsuario);
            // Venía pre-registrado desde la web: el código ya no sirve para otro envío
            if (! empty($d['prerregistro'])) {
                DB::table('cou_prerregistros')->where('codigo', strtoupper($d['prerregistro']))->where('estado', 'PENDIENTE')
                    ->update(['estado' => 'USADO', 'env_id' => $envId]);
            }

            $cabId = null;
            if ($pago === 'ORIGEN') {
                $envio = DB::table('cou_envios')->where('env_id', $envId)->first();
                $cabId = self::cobrar($user, $envio, (array) ($d['cobro'] ?? []), ['clinum' => $remDoc, 'clinom' => $remNombre]);
            }

            return ['env_id' => $envId, 'cab_id' => $cabId];
        });
    }

    /** Cliente con su documento (para la boleta o factura); el que no tiene documento va con el genérico */
    private static function cliente(User $user, string $doc, string $nombre, ?string $telefono): ?int
    {
        if ($doc === '00000000') {
            return null;
        }
        $clicod = DB::table('cliente')->where('rucemp', $user->IdEmpresa)->where('clinum', $doc)->value('clicod');
        if ($clicod) {
            if ($telefono) {
                DB::table('cliente')->where('clicod', $clicod)->update(['telefono' => $telefono]);
            }

            return (int) $clicod;
        }

        return DB::table('cliente')->insertGetId(['tdicod' => strlen($doc) === 11 ? '6' : '1', 'clinum' => $doc, 'clinom' => $nombre,
            'telefono' => $telefono, 'clidir' => '--', 'rucemp' => $user->IdEmpresa, 'cliest' => 'Activo']);
    }

    // ------------------------------------------------------------------ cobro

    /** Producto del comprobante (servicio, sin stock) */
    public static function productoFlete(User $user): int
    {
        $suc = (int) $user->id_empresa_negocio;
        $procod = 'COU-FLETE-'.$suc;
        $id = DB::table('productos')->where('id_empresa_negocio', $suc)->where('procod', $procod)->value('IdProducto');
        if ($id) {
            return (int) $id;
        }
        $cat = DB::table('categorias')->where('id_empresa_negocio', $suc)->where('cat_nom', 'COURIER')->value('cat_id')
            ?? DB::table('categorias')->insertGetId(['cat_nom' => 'COURIER', 'IdEmpresa' => $user->IdEmpresa, 'id_empresa_negocio' => $suc,
                'tip_pro_id' => DB::table('tipo_producto')->where('id_empresa_negocio', $suc)->value('tip_pro_id') ?? 1,
                'cat_acom' => 0, 'visible' => 0, 'predeterminado' => 0]);
        $id = (int) Producto::create(['procod' => $procod, 'pronom' => 'SERVICIO DE ENVÍO', 'umecod' => 'ZZ', 'costo' => 0, 'propun' => 0,
            'promocion' => 2, 'cat_id' => $cat, 'stock_min' => 0, 'proest' => 'Activo', 'IdEmpresa' => $user->IdEmpresa, 'id_empresa_negocio' => $suc])->IdProducto;

        return $id;
    }

    /**
     * Emite la boleta, factura o nota de venta del envío (en origen o al recoger) con el turno de caja de quien cobra.
     *
     * @param  array<string, mixed>  $cobro  tdocod, clinum, clinom, clidir, id_med_pag, paga
     * @param  array{clinum: string, clinom: string}  $porDefecto  a nombre de quién sale si no se escribe otro
     */
    public static function cobrar(User $user, object $envio, array $cobro, array $porDefecto): int
    {
        if ($envio->IdCpe_cabecera) {
            throw new \RuntimeException('Este envío ya está cobrado.');
        }
        $suc = (int) $user->id_empresa_negocio;
        $turno = Turno::where('IdUsuario', $user->IdUsuario)->where('id_empresa_negocio', $suc)->where('estado', 'ABIERTO')->lockForUpdate()->first();
        if (! $turno) {
            throw new \RuntimeException('Debes aperturar tu turno de caja antes de cobrar.');
        }
        $tdocod = in_array($cobro['tdocod'] ?? null, ['01', '03', '13'], true) ? $cobro['tdocod'] : '03';
        $doc = preg_replace('/\D/', '', (string) ($cobro['clinum'] ?? '')) ?: $porDefecto['clinum'];
        if ($tdocod === '01' && strlen($doc) !== 11) {
            throw new \RuntimeException('Para factura escribe el RUC (11 dígitos) de quien paga.');
        }
        if ((float) $envio->total <= 0) {
            throw new \RuntimeException('Este envío no tiene nada que cobrar.');
        }
        $lineas = self::lineas($envio, self::productoFlete($user));
        $contado = DB::table('credito_dias')->where('id_empresa_negocio', $suc)->where('cre_dia_tip', 'CONTADO')->value('cre_dia_id');
        $medio = $cobro['id_med_pag'] ?? null;

        $cabId = Comprobante::emitir($user, $turno, [
            'tdocod' => $tdocod, 'estadopago' => $contado, 'fecEmi' => now()->toDateString(),
            'tdicod' => strlen($doc) === 11 ? '6' : '1', 'clinum' => $doc,
            'clinom' => mb_strtoupper(trim((string) (($cobro['clinom'] ?? null) ?: $porDefecto['clinom']))), 'clidir' => ($cobro['clidir'] ?? null) ?: '--',
            'observaciones' => mb_substr('GUÍA DE ENVÍO '.$envio->codigo, 0, 100),
            'id_med_pag' => $medio ? [$medio] : [], 'mon_med_pag' => $medio ? [(float) $envio->total] : [], 'paga' => $cobro['paga'] ?? 0,
        ], $lineas, ['ped_tip' => 'COURIER', 'IdUsuario_ven' => $user->IdUsuario]);

        DB::table('cou_envios')->where('env_id', $envio->env_id)->update(['IdCpe_cabecera' => $cabId]);

        return $cabId;
    }

    /**
     * Líneas del comprobante de un envío: el flete con su ruta, el reparto a domicilio y el seguro.
     *
     * @param  bool  $conGuia  con el N° de guía en la descripción (factura de varios envíos)
     * @return array<int, array{IdProducto: int, cantidad: int, precio: float, descripcion: string}>
     */
    public static function lineas(object $envio, int $producto, bool $conGuia = false): array
    {
        $agencias = DB::table('cou_agencias')->whereIn('age_id', [$envio->origen_id, $envio->destino_id])->get()->keyBy('age_id');
        $ruta = ($agencias[$envio->origen_id]->ciudad ?? '').' - '.($agencias[$envio->destino_id]->ciudad ?? '');
        $guia = $conGuia ? 'GUÍA '.$envio->codigo.' · ' : '';
        $lineas = [['IdProducto' => $producto, 'cantidad' => 1, 'precio' => (float) $envio->flete,
            'descripcion' => mb_substr("{$guia}SERVICIO DE ENVÍO {$ruta} · {$envio->piezas} PZA(S) · ".rtrim(rtrim(number_format((float) $envio->peso_cobrado, 2), '0'), '.').' KG', 0, 150)]];
        if ((float) $envio->domicilio > 0) {
            $lineas[] = ['IdProducto' => $producto, 'cantidad' => 1, 'precio' => (float) $envio->domicilio, 'descripcion' => $guia.'REPARTO A DOMICILIO'];
        }
        if ((float) $envio->seguro > 0) {
            $lineas[] = ['IdProducto' => $producto, 'cantidad' => 1, 'precio' => (float) $envio->seguro,
                'descripcion' => mb_substr($guia.'SEGURO DE ENCOMIENDA (VALOR DECLARADO S/ '.number_format((float) $envio->valor_declarado, 2).')', 0, 150)];
        }

        return array_values(array_filter($lineas, fn ($l) => $l['precio'] > 0));
    }

    /** Si el comprobante del envío se anula (o tiene nota de crédito total), el envío vuelve a quedar por cobrar */
    public static function revertirComprobante(int $cabId): void
    {
        if (! Schema::hasTable('cou_envios')) {
            return;
        }
        foreach (DB::table('cou_envios')->where('IdCpe_cabecera', $cabId)->get(['env_id', 'estado']) as $e) {
            DB::table('cou_envios')->where('env_id', $e->env_id)->update(['IdCpe_cabecera' => null]);
            self::evento($e->env_id, $e->estado, 'Se anuló su comprobante: queda por cobrar');
        }
    }

    // ------------------------------------------------------------------ movimiento y entrega

    /**
     * Despachar (origen), recibir en destino o salir a reparto (destino) varios envíos a la vez.
     *
     * @param  array<int, int>  $ids
     * @return int cuántos cambiaron
     */
    public static function avanzar(User $user, array $ids, string $nuevo): int
    {
        if (! isset(self::PASOS[$nuevo])) {
            throw new \RuntimeException('Paso no válido.');
        }
        [$desde, $quien] = self::PASOS[$nuevo];
        $suc = (int) $user->id_empresa_negocio;

        return DB::transaction(function () use ($user, $ids, $nuevo, $desde, $quien, $suc) {
            $n = 0;
            foreach (DB::table('cou_envios')->whereIn('env_id', $ids)->where('estado', $desde)->lockForUpdate()->get() as $e) {
                if ($nuevo === 'EN_REPARTO' && $e->entrega !== 'DOMICILIO') {
                    continue;
                }
                if (! self::lado($e, $suc)[$quien]) {
                    continue;
                }
                DB::table('cou_envios')->where('env_id', $e->env_id)->update(['estado' => $nuevo]);
                self::evento($e->env_id, $nuevo, [
                    'EN_TRANSITO' => 'Salió de la agencia de origen',
                    'EN_DESTINO' => $e->entrega === 'DOMICILIO' ? 'Llegó a la agencia de destino' : 'Llegó a la agencia de destino: listo para recoger',
                    'EN_REPARTO' => 'Salió a reparto a domicilio',
                ][$nuevo], $quien === 'origen' ? (int) $e->origen_id : (int) $e->destino_id, $user->IdUsuario);
                $n++;
            }

            return $n;
        });
    }

    /**
     * Entrega al destinatario (o a quien recoge): pide la clave del envío (el administrador puede entregar sin ella dando
     * el motivo) y, si paga al recoger, emite su comprobante.
     *
     * @param  array<string, mixed>  $d  clave, motivo_sin_clave, recibe_nombre, recibe_doc, cobro{}
     */
    public static function entregar(User $user, int $envId, array $d): ?int
    {
        return DB::transaction(function () use ($user, $envId, $d) {
            $e = DB::table('cou_envios')->where('env_id', $envId)->lockForUpdate()->first();
            if (! $e || ! in_array($e->estado, ['EN_DESTINO', 'EN_REPARTO'], true)) {
                throw new \RuntimeException('Solo se entrega lo que ya llegó a la agencia de destino.');
            }
            if (! self::lado($e, (int) $user->id_empresa_negocio)['destino']) {
                throw new \RuntimeException('Este envío lo entrega la agencia de destino.');
            }
            $sinClave = trim((string) ($d['motivo_sin_clave'] ?? ''));
            if (! hash_equals($e->clave, (string) ($d['clave'] ?? ''))) {
                if (! $user->esAdmin() || $sinClave === '') {
                    throw new \RuntimeException('La clave no coincide. Pídesela a quien recoge (la tiene el remitente).');
                }
            }
            $recibe = mb_strtoupper(trim((string) (($d['recibe_nombre'] ?? null) ?: $e->des_nombre)));
            $recibeDoc = preg_replace('/\D/', '', (string) ($d['recibe_doc'] ?? '')) ?: null;

            $cabId = null;
            if ($e->pago === 'DESTINO' && ! $e->IdCpe_cabecera && (float) $e->total > 0) {
                $cabId = self::cobrar($user, $e, (array) ($d['cobro'] ?? []), ['clinum' => $recibeDoc ?: ($e->des_doc ?: '00000000'), 'clinom' => $recibe]);
            }
            DB::table('cou_envios')->where('env_id', $envId)->update(['estado' => 'ENTREGADO', 'entregado' => now(), 'entregado_a' => $recibe, 'entregado_doc' => $recibeDoc]);
            self::evento($envId, 'ENTREGADO', 'Entregado a '.self::enmascarar($recibe).($sinClave !== '' && ! hash_equals($e->clave, (string) ($d['clave'] ?? '')) ? ' (SIN CLAVE: '.mb_strtoupper($sinClave).')' : ''),
                (int) $e->destino_id, $user->IdUsuario);

            return $cabId;
        });
    }

    /** Anula un envío que aún no sale (si ya se cobró, primero se anula su comprobante en Ventas) */
    public static function anular(User $user, int $envId, string $motivo): void
    {
        DB::transaction(function () use ($user, $envId, $motivo) {
            $e = DB::table('cou_envios')->where('env_id', $envId)->lockForUpdate()->first();
            if (! $e || $e->estado !== 'REGISTRADO' || ! self::lado($e, (int) $user->id_empresa_negocio)['origen']) {
                throw new \RuntimeException('Solo se anula un envío que aún no sale de tu agencia.');
            }
            if ($e->IdCpe_cabecera) {
                $cab = DB::table('cpe_cabecera')->where('IdCpe_cabecera', $e->IdCpe_cabecera)->first(['serdoc', 'numdoc']);
                throw new \RuntimeException('Primero anula su comprobante '.($cab ? $cab->serdoc.'-'.$cab->numdoc : '').' en Ventas; luego anulas el envío.');
            }
            DB::table('cou_envios')->where('env_id', $envId)->update(['estado' => 'ANULADO', 'observacion' => mb_substr('ANULADO: '.mb_strtoupper($motivo), 0, 255)]);
            self::evento($envId, 'ANULADO', 'Envío anulado', (int) $e->origen_id, $user->IdUsuario);
        });
    }

    /**
     * Devuelve al remitente lo que nadie recogió (o rechazaron): el envío queda DEVUELTO y sale uno nuevo de vuelta a la
     * agencia de origen, con los mismos paquetes, para entregárselo al remitente (con la misma clave). Si se cobra, el
     * remitente paga la vuelta al recogerla; si no, va sin costo.
     *
     * @return int env_id del envío de vuelta
     */
    public static function devolver(User $user, int $envId, string $motivo, bool $cobrar): int
    {
        return DB::transaction(function () use ($user, $envId, $motivo, $cobrar) {
            $e = DB::table('cou_envios')->where('env_id', $envId)->lockForUpdate()->first();
            if (! $e || $e->estado !== 'EN_DESTINO' || ! self::lado($e, (int) $user->id_empresa_negocio)['destino']) {
                throw new \RuntimeException('Solo se devuelve lo que está en tu agencia de destino esperando ser recogido.');
            }
            $paquetes = DB::table('cou_paquetes')->where('env_id', $envId)->get();
            $precio = ['flete' => 0, 'seguro' => 0, 'total' => 0];
            if ($cobrar) {
                $cfg = self::config((string) $user->IdEmpresa);
                $precio = self::cotizar(self::tarifa($cfg, (int) $e->destino_id, (int) $e->origen_id), $paquetes->map(fn ($p) => (array) $p)->all(),
                    (float) $e->valor_declarado, false, (int) $cfg->divisor_vol, (float) $cfg->seguro_pct, (float) $cfg->seguro_min);
            }
            $vuelta = DB::table('cou_envios')->insertGetId([
                // Misma clave: el remitente ya la tiene en su copia y la usa para recoger su devolución
                'codigo' => self::nuevoCodigo(), 'clave' => $e->clave,
                'origen_id' => $e->destino_id, 'destino_id' => $e->origen_id,
                'rem_doc' => $e->des_doc ?: '00000000', 'rem_nombre' => $e->des_nombre, 'rem_telefono' => $e->des_telefono,
                'des_doc' => $e->rem_doc !== '00000000' ? $e->rem_doc : null, 'des_nombre' => $e->rem_nombre, 'des_telefono' => $e->rem_telefono, 'rem_clicod' => null,
                'entrega' => 'AGENCIA', 'contenido' => $e->contenido, 'valor_declarado' => $e->valor_declarado,
                'piezas' => $e->piezas, 'peso_real' => $e->peso_real, 'peso_cobrado' => $e->peso_cobrado,
                'flete' => $precio['flete'], 'seguro' => $precio['seguro'], 'domicilio' => 0, 'total' => $precio['total'],
                'pago' => 'DESTINO', 'estado' => 'REGISTRADO', 'llegada_estimada' => now()->addDays(2)->toDateString(),
                'observacion' => mb_substr('DEVOLUCIÓN DE LA GUÍA '.$e->codigo.': '.mb_strtoupper($motivo), 0, 255), 'devuelto_de' => $e->env_id,
                'IdUsuario' => $user->IdUsuario, 'id_empresa_negocio' => $user->id_empresa_negocio, 'creado' => now(),
            ]);
            foreach ($paquetes as $p) {
                $fila = (array) $p;
                unset($fila['paq_id']);
                DB::table('cou_paquetes')->insert(['env_id' => $vuelta] + $fila);
            }
            DB::table('cou_envios')->where('env_id', $envId)->update(['estado' => 'DEVUELTO', 'mot_id' => null]);
            $codigo = DB::table('cou_envios')->where('env_id', $vuelta)->value('codigo');
            self::evento($envId, 'DEVUELTO', 'Se devuelve al remitente con la guía '.$codigo.' ('.mb_strtoupper($motivo).')', (int) $e->destino_id, $user->IdUsuario);
            self::evento($vuelta, 'REGISTRADO', 'Devolución de la guía '.$e->codigo.': vuelve al remitente', (int) $e->destino_id, $user->IdUsuario);

            return $vuelta;
        });
    }

    /** ¿Tiene la forma de un código de rastreo? */
    public static function esCodigo(string $codigo): bool
    {
        return (bool) preg_match('/^['.self::ALFABETO.']{10}$/', $codigo);
    }
}
