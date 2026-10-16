<?php

namespace App\Support;

use App\Models\Producto;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Datos de ejemplo para la empresa demo (demos.tushpa.app): productos, clientes y lo propio de cada rubro
 * (mesas, farmacia con lotes, grifo, hotel, gimnasio, estacionamiento, club, clínica y comercio).
 * Se puede correr varias veces: lo que ya existe (por nombre) no se duplica. No emite comprobantes.
 */
class DemoDatos
{
    private int $suc;

    private string $ruc;

    private int $almacen;

    /** @var array<string, int> */
    private array $resumen = [];

    public function __construct(private User $admin)
    {
        $this->suc = (int) $admin->id_empresa_negocio;
        $this->ruc = (string) $admin->IdEmpresa;
        $this->almacen = (int) (DB::table('almacenes')->where('id_empresa_negocio', $this->suc)->orderByDesc('predeterminado')->value('id_almacen')
            ?? DB::table('almacenes')->insertGetId(['descripcion' => 'ALMACÉN PRINCIPAL', 'id_empresa_negocio' => $this->suc, 'predeterminado' => 1]));
    }

    /** @return array<string, int> Cuántos registros nuevos se crearon de cada cosa */
    public function cargar(): array
    {
        Auth::login($this->admin);
        $this->clientesYProveedores();
        $this->restaurante();
        $this->farmacia();
        $this->grifo();
        $this->comercio();
        $this->hotel();
        $this->gimnasio();
        $this->estacionamiento();
        $this->club();
        $this->clinica();

        return $this->resumen;
    }

    private function sumar(string $que, int $n = 1): void
    {
        $this->resumen[$que] = ($this->resumen[$que] ?? 0) + $n;
    }

    private function categoria(string $nombre, string $color = '#4f46e5'): int
    {
        $id = DB::table('categorias')->where('id_empresa_negocio', $this->suc)->where('cat_nom', $nombre)->value('cat_id');
        if ($id) {
            return (int) $id;
        }
        $this->sumar('categorías');

        return DB::table('categorias')->insertGetId(['cat_nom' => $nombre, 'color' => $color, 'visible' => 1,
            'IdEmpresa' => $this->ruc, 'id_empresa_negocio' => $this->suc]);
    }

    /** Crea el producto (si no existe por nombre) con su stock en el almacén principal */
    private function producto(int $cat, string $nombre, float $precio, float $costo = 0, int $tipo = 0, ?float $stock = 50, array $extra = []): int
    {
        $existe = Producto::where('id_empresa_negocio', $this->suc)->where('pronom', $nombre)->value('IdProducto');
        if ($existe) {
            return (int) $existe;
        }
        $p = Producto::create($extra + [
            'procod' => 'D'.strtoupper(Str::random(7)), 'pronom' => $nombre, 'umecod' => 'NIU', 'costo' => $costo, 'propun' => $precio,
            'promocion' => $tipo, 'cat_id' => $cat, 'stock_min' => $tipo === 0 ? 5 : 0, 'proest' => 'Activo',
            'IdEmpresa' => $this->ruc, 'id_empresa_negocio' => $this->suc,
        ]);
        if (in_array($tipo, [0, 4], true) && $stock !== null) {
            DB::table('producto_stock')->insert(['IdProducto' => $p->IdProducto, 'id_empresa_negocio' => $this->suc, 'id_almacen' => $this->almacen,
                'stock' => $stock, 'stock_inicial' => $stock]);
        }
        $this->sumar('productos');

        return (int) $p->IdProducto;
    }

    private function cliente(string $doc, string $nombre, ?string $telefono = null, string $direccion = '--'): int
    {
        $id = DB::table('cliente')->where('rucemp', $this->ruc)->where('clinum', $doc)->value('clicod');
        if ($id) {
            return (int) $id;
        }
        $this->sumar('clientes');

        return DB::table('cliente')->insertGetId(['tdicod' => strlen($doc) === 11 ? '6' : '1', 'clinum' => $doc, 'clinom' => $nombre,
            'rucemp' => $this->ruc, 'clidir' => $direccion, 'telefono' => $telefono, 'cliest' => 'Activo']);
    }

    private function clientesYProveedores(): void
    {
        foreach ([
            ['70000101', 'MARÍA FERNANDA RÍOS PANDURO', '965100101', 'JR. PRÓSPERO 410, IQUITOS'],
            ['70000102', 'JORGE LUIS TORRES VÁSQUEZ', '965100102', 'AV. QUIÑONES 1250, SAN JUAN'],
            ['70000103', 'ROSA ELENA DÍAZ GRÁNDEZ', '965100103', 'CALLE NAPO 220, IQUITOS'],
            ['70000104', 'CARLOS ALBERTO RUIZ PINEDO', '965100104', 'AV. LA MARINA 780, PUNCHANA'],
            ['20600000101', 'DISTRIBUIDORA AMAZÓNICA DEMO S.A.C.', '065231010', 'AV. FREYRE 1520, IQUITOS'],
        ] as [$doc, $nombre, $tel, $dir]) {
            $this->cliente($doc, $nombre, $tel, $dir);
        }
        foreach ([
            ['20600000201', 'BEBIDAS DEL ORIENTE DEMO S.A.C.', 'AV. PARTICIPACIÓN 300'],
            ['20600000202', 'DROGUERÍA SELVA SALUD DEMO E.I.R.L.', 'JR. ARICA 155'],
            ['20600000203', 'COMBUSTIBLES LORETO DEMO S.A.', 'CARRETERA IQUITOS - NAUTA KM 3'],
        ] as [$ruc, $razon, $dir]) {
            if (! DB::table('proveedor')->where('id_empresa_negocio', $this->suc)->where('prov_ruc', $ruc)->exists()) {
                DB::table('proveedor')->insert(['tdicod' => '6', 'prov_ruc' => $ruc, 'prov_raz' => $razon, 'prov_dir' => $dir, 'prov_est' => '1',
                    'IdEmpresa' => $this->ruc, 'id_empresa_negocio' => $this->suc]);
                $this->sumar('proveedores');
            }
        }
    }

    private function restaurante(): void
    {
        $entradas = $this->categoria('ENTRADAS', '#f59e0b');
        $ceviches = $this->categoria('CEVICHES', '#0ea5e9');
        $fondo = $this->categoria('PLATOS DE FONDO', '#ef4444');
        $bebidas = $this->categoria('BEBIDAS', '#22c55e');
        $tragos = $this->categoria('TRAGOS', '#a855f7');
        $insumos = $this->categoria('INSUMOS COCINA', '#64748b');

        foreach ([[$entradas, 'TEQUEÑOS CON GUACAMOLE', 12], [$entradas, 'YUCA FRITA CON SALSA HUANCAÍNA', 9], [$entradas, 'CHONTA EN ENSALADA', 14],
            [$ceviches, 'CEVICHE CLÁSICO', 25], [$ceviches, 'CEVICHE MIXTO', 32], [$ceviches, 'LECHE DE TIGRE', 15],
            [$fondo, 'TACACHO CON CECINA Y CHORIZO', 24], [$fondo, 'JUANE DE GALLINA', 18], [$fondo, 'PATARASHCA DE DONCELLA', 30],
            [$fondo, 'LOMO SALTADO', 28], [$fondo, 'ARROZ CHAUFA DE POLLO', 20], [$fondo, 'PAICHE A LA PLANCHA', 35],
            [$bebidas, 'CHICHA MORADA (JARRA)', 10], [$bebidas, 'REFRESCO DE CAMU CAMU (JARRA)', 12],
            [$tragos, 'PISCO SOUR', 18], [$tragos, 'CHILCANO DE PISCO', 15], [$tragos, 'CHUCHUHUASI (COPA)', 8]] as [$cat, $nombre, $precio]) {
            $this->producto($cat, $nombre, $precio, round($precio * 0.38, 2), 2, null);
        }
        foreach ([['INCA KOLA 500 ML', 3.5, 2.2], ['COCA COLA 500 ML', 3.5, 2.2], ['AGUA SAN LUIS 625 ML', 2.5, 1.2], ['CERVEZA PILSEN 630 ML', 8, 5.2],
            ['CERVEZA CUSQUEÑA 620 ML', 9, 6]] as [$nombre, $precio, $costo]) {
            $this->producto($bebidas, $nombre, $precio, $costo, 0, 48);
        }
        foreach ([['PESCADO DONCELLA (KG)', 'KGM', 18], ['LIMÓN (KG)', 'KGM', 4], ['CEBOLLA ROJA (KG)', 'KGM', 3.5], ['ARROZ (KG)', 'KGM', 4.2]] as [$nombre, $ume, $costo]) {
            $this->producto($insumos, $nombre, 0, $costo, 4, 25, ['umecod' => $ume]);
        }
        $combo = $this->producto($fondo, 'COMBO FAMILIAR (2 CEVICHES + JARRA)', 65, 0, 6, null);
        if (! DB::table('combos')->where('IdProducto_rel', $combo)->exists()) {
            $ids = Producto::where('id_empresa_negocio', $this->suc)->whereIn('pronom', ['CEVICHE CLÁSICO', 'CHICHA MORADA (JARRA)'])->pluck('IdProducto', 'pronom');
            DB::table('combos')->insert([['IdProducto_rel' => $combo, 'IdProducto_comb' => $ids['CEVICHE CLÁSICO'], 'prod_comb_cant' => 2],
                ['IdProducto_rel' => $combo, 'IdProducto_comb' => $ids['CHICHA MORADA (JARRA)'], 'prod_comb_cant' => 1]]);
        }

        foreach (['SALÓN PRINCIPAL' => ['M1', 'M2', 'M3', 'M4', 'M5', 'M6', 'M7', 'M8'], 'TERRAZA' => ['T1', 'T2', 'T3', 'T4']] as $piso => $mesas) {
            $pisId = DB::table('pisos')->where('id_empresa_negocio', $this->suc)->where('pis_nom', $piso)->value('pis_id')
                ?? DB::table('pisos')->insertGetId(['pis_nom' => $piso, 'emp_id' => $this->ruc, 'id_empresa_negocio' => $this->suc]);
            foreach ($mesas as $mesa) {
                if (! DB::table('mesas')->where('pis_id', $pisId)->where('mes_nom', $mesa)->exists()) {
                    DB::table('mesas')->insert(['mes_nom' => $mesa, 'mes_est' => 'Libre', 'pis_id' => $pisId, 'IdEmpresa' => $this->ruc, 'id_empresa_negocio' => $this->suc]);
                    $this->sumar('mesas');
                }
            }
        }
    }

    private function farmacia(): void
    {
        $med = $this->categoria('MEDICAMENTOS', '#14b8a6');
        $cuidado = $this->categoria('CUIDADO PERSONAL', '#ec4899');
        $lotes = [['PARACETAMOL 500 MG (TABLETA)', 0.5, 0.15, 400, 'PA2401', 14], ['IBUPROFENO 400 MG (TABLETA)', 0.8, 0.25, 300, 'IB2402', 200],
            ['AMOXICILINA 500 MG (CÁPSULA)', 1.2, 0.4, 250, 'AM2403', 365], ['OMEPRAZOL 20 MG (CÁPSULA)', 1, 0.3, 200, 'OM2404', 25],
            ['LORATADINA 10 MG (TABLETA)', 0.7, 0.2, 180, 'LO2405', 420], ['SUERO ORAL 500 ML', 4.5, 2.6, 40, 'SU2406', 90],
            ['JARABE PARA LA TOS 120 ML', 12, 6.5, 30, 'JA2407', 300]];
        foreach ($lotes as [$nombre, $precio, $costo, $stock, $lote, $dias]) {
            $id = $this->producto($med, $nombre, $precio, $costo, 0, $stock, ['control_lote' => 1]);
            if (Schema::hasTable('producto_lote') && ! DB::table('producto_lote')->where('IdProducto', $id)->exists()) {
                DB::table('producto_lote')->insert(['IdProducto' => $id, 'id_almacen' => $this->almacen, 'id_empresa_negocio' => $this->suc, 'lote' => $lote,
                    'vencimiento' => now()->addDays($dias)->toDateString(), 'stock' => $stock, 'created_at' => now(), 'updated_at' => now()]);
                $this->sumar('lotes');
            }
        }
        foreach ([['ALCOHOL 70° 250 ML', 6, 3.2], ['BLOQUEADOR SOLAR SPF 50', 35, 21], ['REPELENTE DE INSECTOS 120 ML', 14, 8], ['PAÑALES TALLA M (UNIDAD)', 1.5, 0.9]] as [$nombre, $precio, $costo]) {
            $this->producto($cuidado, $nombre, $precio, $costo, 0, 60);
        }
    }

    private function grifo(): void
    {
        $comb = $this->categoria('COMBUSTIBLES', '#f97316');
        $lub = $this->categoria('LUBRICANTES', '#334155');
        foreach ([['GASOHOL 90', 16.5, 14.2], ['GASOHOL 95', 17.8, 15.3], ['DIÉSEL B5 S-50', 16.9, 14.6]] as [$nombre, $precio, $costo]) {
            $this->producto($comb, $nombre, $precio, $costo, 0, 3000, ['umecod' => 'GLL', 'es_combustible' => 1]);
        }
        foreach ([['ACEITE 20W-50 (GALÓN)', 85, 60], ['ACEITE 2T (1/4 GALÓN)', 18, 11], ['REFRIGERANTE (GALÓN)', 25, 15]] as [$nombre, $precio, $costo]) {
            $this->producto($lub, $nombre, $precio, $costo, 0, 24);
        }
    }

    private function comercio(): void
    {
        $abarrotes = $this->categoria('ABARROTES', '#eab308');
        $ferreteria = $this->categoria('FERRETERÍA', '#78716c');
        $tecno = $this->categoria('TECNOLOGÍA', '#2563eb');
        foreach ([['ARROZ COSTEÑO 5 KG', 22, 18], ['AZÚCAR RUBIA 1 KG', 4.2, 3.4], ['ACEITE PRIMOR 1 L', 9.5, 7.6], ['LECHE GLORIA 400 G', 4, 3.2],
            ['FIDEOS DON VITTORIO 500 G', 3.5, 2.6]] as [$nombre, $precio, $costo]) {
            $this->producto($abarrotes, $nombre, $precio, $costo, 0, 80);
        }
        foreach ([['MARTILLO DE UÑA 16 OZ', 25, 15], ['CLAVOS 2" (KG)', 8, 5], ['CEMENTO SOL 42.5 KG', 32, 27]] as [$nombre, $precio, $costo]) {
            $this->producto($ferreteria, $nombre, $precio, $costo, 0, 40);
        }
        $this->producto($tecno, 'CELULAR SAMSUNG GALAXY A15', 699, 560, 0, 0, ['control_serie' => 1, 'garantia_meses' => 12]);
        $this->producto($tecno, 'AUDÍFONOS BLUETOOTH', 59, 32, 0, 15);
    }

    private function hotel(): void
    {
        $hospedaje = $this->categoria('HOSPEDAJE', '#0891b2');
        foreach ([['HOSPEDAJE 3 HORAS', 30, 180], ['NOCHE (12 HORAS)', 60, 720], ['DÍA COMPLETO (24 HORAS)', 90, 1440], ['HORA EXTRA', 10, 60]] as [$nombre, $precio, $minutos]) {
            $this->producto($hospedaje, $nombre, $precio, 0, 2, null, ['minutos' => $minutos]);
        }
        foreach ([['101', 'SIMPLE', '1'], ['102', 'DOBLE', '1'], ['103', 'MATRIMONIAL', '1'], ['104', 'SIMPLE', '1'],
            ['201', 'DOBLE', '2'], ['202', 'MATRIMONIAL', '2'], ['203', 'SUITE', '2'], ['204', 'FAMILIAR', '2']] as [$nombre, $tipo, $piso]) {
            if (! DB::table('habitaciones')->where('id_empresa_negocio', $this->suc)->where('hab_nom', $nombre)->exists()) {
                DB::table('habitaciones')->insert(['hab_nom' => $nombre, 'hab_tip' => $tipo, 'hab_piso' => 'PISO '.$piso, 'hab_est' => 'Libre',
                    'IdEmpresa' => $this->ruc, 'id_empresa_negocio' => $this->suc]);
                $this->sumar('habitaciones');
            }
        }
    }

    private function gimnasio(): void
    {
        if (! Schema::hasTable('gym_planes')) {
            return;
        }
        $suple = $this->categoria('SUPLEMENTOS Y BEBIDAS', '#f97316');
        foreach ([['PROTEÍNA WHEY (SCOOP)', 8, 4], ['BEBIDA ENERGIZANTE', 6, 3.5], ['AGUA CIELO 625 ML', 2, 1]] as [$nombre, $precio, $costo]) {
            $this->producto($suple, $nombre, $precio, $costo, 0, 40);
        }
        $planes = [];
        foreach ([['MENSUAL', 30, 80, 7], ['TRIMESTRAL', 90, 210, 15], ['RUTINA DEL DÍA', 1, 10, 0]] as [$nombre, $dias, $precio, $congelar]) {
            $plan = DB::table('gym_planes')->where('id_empresa_negocio', $this->suc)->where('nombre', $nombre)->first();
            if (! $plan) {
                $id = DB::table('gym_planes')->insertGetId(['nombre' => $nombre, 'dias' => $dias, 'precio' => $precio, 'congelar_max' => $congelar,
                    'activo' => 1, 'id_empresa_negocio' => $this->suc]);
                $plan = DB::table('gym_planes')->where('plan_id', $id)->first();
                DB::table('gym_planes')->where('plan_id', $id)->update(['IdProducto' => Gimnasio::productoDelPlan($this->admin, $plan)]);
                $this->sumar('planes de gimnasio');
            }
            $planes[$nombre] = $plan;
        }
        // Clientes con membresía vigente, por vencer y vencida (para ver los colores de la recepción)
        foreach ([['71000101', 'LUIS ÁNGEL PEZO MOZOMBITE', -5, 'MENSUAL'], ['71000102', 'KAREN SOFÍA AREVALO TELLO', -26, 'MENSUAL'],
            ['71000103', 'DIEGO ARMANDO SALAS VELA', -40, 'MENSUAL'], ['71000104', 'ANDREA LUCÍA FLORES CHUNG', -10, 'TRIMESTRAL'],
            ['71000105', 'MIGUEL ÁNGEL TUESTA ROJAS', -2, 'TRIMESTRAL']] as $i => [$dni, $nombre, $inicio, $planNombre]) {
            $clicod = $this->cliente($dni, $nombre, '96520010'.($i + 1));
            if (DB::table('socios')->where('id_empresa_negocio', $this->suc)->where('clicod', $clicod)->exists()) {
                continue;
            }
            $socId = DB::table('socios')->insertGetId(['codigo' => 'G'.str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT), 'clicod' => $clicod,
                'fecha_ingreso' => now()->addDays($inicio)->toDateString(), 'fecha_nac' => now()->subYears(20 + $i * 4)->subDays($i * 37)->toDateString(),
                'estado' => 'ACTIVO', 'token' => Str::random(32), 'id_empresa_negocio' => $this->suc, 'creado' => now()]);
            $plan = $planes[$planNombre];
            $desde = now()->addDays($inicio)->startOfDay();
            DB::table('gym_membresias')->insert(['soc_id' => $socId, 'plan_id' => $plan->plan_id, 'plan' => $plan->nombre, 'dias' => $plan->dias,
                'congelar_max' => $plan->congelar_max, 'inicio' => $desde->toDateString(), 'fin' => $desde->copy()->addDays($plan->dias - 1)->toDateString(),
                'precio' => $plan->precio, 'estado' => 'ACTIVA', 'IdUsuario' => $this->admin->IdUsuario, 'creado' => $desde, 'id_empresa_negocio' => $this->suc]);
            $this->sumar('clientes de gimnasio');
        }
    }

    private function estacionamiento(): void
    {
        if (! Schema::hasTable('est_tarifas')) {
            return;
        }
        $tarifas = [];
        foreach ([['AUTO', 'auto', 'FRACCION', 3, 15, 10, 25], ['MOTO', 'moto', 'FRACCION', 1.5, 30, 10, 12], ['CAMIONETA', 'camioneta', 'HORA', 4, 60, 10, 30]] as $orden => [$nombre, $icono, $modo, $precio, $fraccion, $tolerancia, $tope]) {
            $tarifa = DB::table('est_tarifas')->where('id_empresa_negocio', $this->suc)->where('nombre', $nombre)->first();
            if (! $tarifa) {
                $id = DB::table('est_tarifas')->insertGetId(['nombre' => $nombre, 'icono' => $icono, 'modo' => $modo, 'precio' => $precio,
                    'fraccion_min' => $fraccion, 'tolerancia_min' => $tolerancia, 'tope_dia' => $tope, 'perdido' => 20, 'pension' => $precio * 40,
                    'activo' => 1, 'orden' => $orden + 1, 'id_empresa_negocio' => $this->suc]);
                $tarifa = DB::table('est_tarifas')->where('tar_id', $id)->first();
                Estacionamiento::productoDe($this->admin, $tarifa);
                $this->sumar('tarifas de estacionamiento');
            }
            $tarifas[$nombre] = $tarifa->tar_id;
        }
        foreach (range(1, 16) as $n) {
            $codigo = ($n <= 12 ? 'A' : 'M').str_pad((string) ($n <= 12 ? $n : $n - 12), 2, '0', STR_PAD_LEFT);
            if (! DB::table('est_espacios')->where('id_empresa_negocio', $this->suc)->where('codigo', $codigo)->exists()) {
                DB::table('est_espacios')->insert(['codigo' => $codigo, 'zona' => $n <= 12 ? 'AUTOS' : 'MOTOS', 'tar_id' => $tarifas[$n <= 12 ? 'AUTO' : 'MOTO'],
                    'estado' => 'ACTIVO', 'orden' => $n, 'id_empresa_negocio' => $this->suc]);
                $this->sumar('espacios de estacionamiento');
            }
        }
    }

    private function club(): void
    {
        if (! Schema::hasTable('socio_categorias')) {
            return;
        }
        $categorias = [];
        foreach ([['ACTIVO', 50], ['JUVENIL', 25], ['VITALICIO', 0]] as [$nombre, $cuota]) {
            $categorias[$nombre] = DB::table('socio_categorias')->where('id_empresa_negocio', $this->suc)->where('nombre', $nombre)->value('cat_soc_id')
                ?? DB::table('socio_categorias')->insertGetId(['nombre' => $nombre, 'cuota' => $cuota, 'activo' => 1, 'id_empresa_negocio' => $this->suc]);
        }
        foreach ([['72000101', 'PEDRO PABLO GUERRA LOZANO', 'ACTIVO'], ['72000102', 'LUCÍA ISABEL CARDENAS PINEDO', 'ACTIVO'],
            ['72000103', 'RENZO ALONSO PANDURO RÍOS', 'JUVENIL'], ['72000104', 'ELENA MARGARITA SOTO DEL ÁGUILA', 'VITALICIO']] as $i => [$dni, $nombre, $cat]) {
            $clicod = $this->cliente($dni, $nombre, '96530010'.($i + 1));
            if (! DB::table('socios')->where('id_empresa_negocio', $this->suc)->where('clicod', $clicod)->exists()) {
                DB::table('socios')->insert(['codigo' => 'S'.str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT), 'clicod' => $clicod, 'cat_soc_id' => $categorias[$cat],
                    'fecha_ingreso' => now()->subYears($i + 1)->toDateString(), 'estado' => 'ACTIVO', 'token' => Str::random(32),
                    'id_empresa_negocio' => $this->suc, 'creado' => now()]);
                $this->sumar('socios del club');
            }
        }
    }

    private function clinica(): void
    {
        if (! Schema::hasTable('especialidad')) {
            return;
        }
        $consultas = $this->categoria('CONSULTAS Y TRATAMIENTOS', '#0d9488');
        foreach ([['MEDICINA GENERAL', 'CONSULTA MEDICINA GENERAL', 40, 0, 0], ['ODONTOLOGÍA', 'CONSULTA ODONTOLÓGICA', 50, 1, 0],
            ['PEDIATRÍA', 'CONSULTA PEDIATRÍA', 60, 0, 0], ['VETERINARIA', 'CONSULTA VETERINARIA', 35, 0, 1]] as [$esp, $servicio, $precio, $odonto, $vet]) {
            $producto = $this->producto($consultas, $servicio, $precio, 0, 2, null);
            if (! DB::table('especialidad')->where('id_empresa_negocio', $this->suc)->where('esp_des', $esp)->exists()) {
                DB::table('especialidad')->insert(['esp_des' => $esp, 'IdProducto' => $producto, 'odontograma' => $odonto, 'veterinaria' => $vet,
                    'activo' => 1, 'id_empresa_negocio' => $this->suc]);
                $this->sumar('especialidades');
            }
        }
        foreach ([['PROFILAXIS DENTAL', 80], ['CURACIÓN CON RESINA', 120], ['EXTRACCIÓN SIMPLE', 90], ['ORTODONCIA (CUOTA MENSUAL)', 150]] as [$nombre, $precio]) {
            $this->producto($consultas, $nombre, $precio, 0, 2, null);
        }
        foreach ([['73000101', 'ANA MARÍA VELA SANGAMA', 'F', 34], ['73000102', 'JOSÉ MANUEL ACOSTA PIZANGO', 'M', 52], ['73000103', 'VALERIA NICOLE HUAYMACARI', 'F', 8]] as $i => [$dni, $nombre, $sexo, $edad]) {
            if (! DB::table('cliente')->where('rucemp', $this->ruc)->where('clinum', $dni)->exists()) {
                Clinica::crearPaciente($this->admin, ['clinum' => $dni, 'clinom' => $nombre, 'telefono' => '96540010'.($i + 1), 'sexo' => $sexo,
                    'fecha_nac' => now()->subYears($edad)->toDateString()]);
                $this->sumar('pacientes');
            }
        }
        if (! DB::table('historia_clinica')->where('id_empresa_negocio', $this->suc)->where('mascota', 'FIRULAIS')->exists()) {
            Clinica::crearPaciente($this->admin, ['tipo' => 'MASCOTA', 'clinum' => '70000101', 'clinom' => 'MARÍA FERNANDA RÍOS PANDURO', 'mascota' => 'FIRULAIS',
                'especie' => 'CANINO', 'raza' => 'MESTIZO', 'sexo' => 'M']);
            $this->sumar('pacientes');
        }
    }
}
