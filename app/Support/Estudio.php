<?php

namespace App\Support;

use App\Models\Almacen;
use App\Models\Empresa;
use App\Models\EmpresaNegocio;
use App\Models\User;
use App\Support\Tenancy\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Estudio contable: en una misma base, el estudio y las empresas de sus clientes. Cada usuario trabaja en una empresa a
 * la vez: "cambiar" le pone el RUC y la sucursal de esa empresa (como al cambiar de sucursal), así Contabilidad, SIRE,
 * Planilla y Asistencia trabajan con ella sin saber del estudio. El administrador ve todas; los demás, las asignadas.
 */
class Estudio
{
    /** @var array<string, ?string> RUC del estudio por base de datos (se lee una vez por petición) */
    private static array $rucs = [];

    /** RUC del estudio, o null si la base no es de un estudio contable */
    public static function ruc(): ?string
    {
        $base = (string) DB::connection()->getDatabaseName();
        if (! array_key_exists($base, self::$rucs)) {
            try {
                self::$rucs[$base] = DB::table('estudio_empresas')->where('propia', true)->value('IdEmpresa');
            } catch (QueryException) {
                // La base del panel o una base sin migrar
                self::$rucs[$base] = null;
            }
        }

        return self::$rucs[$base];
    }

    public static function activo(): bool
    {
        return self::ruc() !== null;
    }

    /** Cuántas empresas puede llevar el estudio según su plan (lo pone el panel TUSHPA); null = sin límite */
    public static function limite(): ?int
    {
        $max = Tenancy::cliente()?->max_empresas;

        return $max ? (int) $max : null;
    }

    public static function activas(): int
    {
        return DB::table('estudio_empresas')->where('propia', false)->where('activa', true)->count();
    }

    /** ¿Puede activar una empresa más sin pasarse de su plan? */
    public static function hayCupo(): bool
    {
        return self::limite() === null || self::activas() < self::limite();
    }

    /** Para las pruebas y después de activar: vuelve a leer el estudio */
    public static function olvidar(): void
    {
        self::$rucs = [];
    }

    /** Convierte esta base en un estudio contable cuya empresa propia es $ruc; su administrador ve "Empresas del estudio" */
    public static function activar(string $ruc): void
    {
        abort_unless(Empresa::where('IdEmpresa', $ruc)->exists(), 404, "No existe la empresa {$ruc} en esta base.");
        DB::table('estudio_empresas')->updateOrInsert(['IdEmpresa' => $ruc], ['propia' => true, 'activa' => true, 'updated_at' => now(), 'created_at' => now()]);
        if ($modulo = DB::table('modulos')->where('mod_url', '/estudio')->value('mod_id')) {
            foreach (DB::table('role_user')->where('role_id', 2)->pluck('user_IdUsuario')->unique() as $usuario) {
                DB::table('modulos_usuario')->insertOrIgnore(['user_IdUsuario' => $usuario, 'mod_id' => $modulo, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
        self::olvidar();
    }

    /**
     * Las empresas en las que puede trabajar el usuario. El administrador: el propio estudio y todos sus clientes
     * activos; los demás, solo los clientes que se les asignaron (la empresa del estudio no es para ellos).
     *
     * @return Collection<int, object{IdEmpresa: string, NomEmpresa: string, propia: int}>
     */
    public static function empresas(User $usuario): Collection
    {
        if (! self::activo()) {
            return collect();
        }

        return DB::table('estudio_empresas as ee')->join('empresa as e', 'e.IdEmpresa', '=', 'ee.IdEmpresa')
            ->where(fn ($q) => $q->where('ee.propia', true)->orWhere('ee.activa', true))
            ->when(! $usuario->esAdmin(), fn ($q) => $q->where('ee.propia', false)
                ->whereIn('ee.IdEmpresa', DB::table('estudio_accesos')->where('IdUsuario', $usuario->IdUsuario)->select('IdEmpresa')))
            ->orderByDesc('ee.propia')->orderBy('e.NomEmpresa')
            ->get(['ee.IdEmpresa', 'e.NomEmpresa', 'ee.propia', 'ee.buen_contribuyente']);
    }

    /**
     * Las empresas del usuario con su vencimiento SUNAT del periodo y si ya se declaró, de la que vence primero a la última.
     *
     * @return Collection<int, object{IdEmpresa: string, NomEmpresa: string, propia: int, buen_contribuyente: int, grupo: string, vence: CarbonImmutable, dias: int, declarado: ?object}>
     */
    public static function vencimientos(User $usuario, string $periodo): Collection
    {
        $declaradas = DB::table('estudio_declaraciones as d')->leftJoin('users as u', 'u.IdUsuario', '=', 'd.IdUsuario')
            ->where('d.periodo', $periodo)->get(['d.IdEmpresa', 'd.created_at', 'u.apeusu'])->keyBy('IdEmpresa');
        $hoy = CarbonImmutable::today();

        return self::empresas($usuario)->map(function (object $e) use ($periodo, $declaradas, $hoy) {
            $e->grupo = Vencimientos::grupo((string) $e->IdEmpresa, (bool) $e->buen_contribuyente);
            $e->vence = Vencimientos::vence($periodo, $e->grupo);
            $e->dias = (int) $hoy->diffInDays($e->vence, false);
            $e->declarado = $declaradas->get($e->IdEmpresa);

            return $e;
        })->sortBy([fn ($a, $b) => $a->vence <=> $b->vence, fn ($a, $b) => strcmp($a->NomEmpresa, $b->NomEmpresa)])->values();
    }

    /** Marca (o desmarca) que una empresa ya declaró el periodo */
    public static function declarar(User $usuario, string $ruc, string $periodo, bool $declarado): void
    {
        abort_unless(self::puede($usuario, $ruc), 403, 'No tienes acceso a esa empresa.');
        if (! $declarado) {
            DB::table('estudio_declaraciones')->where('IdEmpresa', $ruc)->where('periodo', $periodo)->delete();

            return;
        }
        DB::table('estudio_declaraciones')->insertOrIgnore(['IdEmpresa' => $ruc, 'periodo' => $periodo, 'IdUsuario' => $usuario->IdUsuario,
            'created_at' => now(), 'updated_at' => now()]);
    }

    public static function puede(User $usuario, string $ruc): bool
    {
        return self::empresas($usuario)->contains('IdEmpresa', $ruc);
    }

    /** Dónde trabaja el usuario si está en una empresa que no le toca: el estudio (administrador) o su primer cliente */
    public static function inicioDe(User $usuario): ?string
    {
        return $usuario->esAdmin() ? self::ruc() : self::empresas($usuario)->first()?->IdEmpresa;
    }

    /** La sucursal en la que se trabaja una empresa (la primera que se creó) */
    public static function sucursalDe(string $ruc): ?int
    {
        $id = DB::table('empresa_negocios')->where('IdEmpresa', $ruc)->orderBy('id_empresa_negocio')->value('id_empresa_negocio');

        return $id ? (int) $id : null;
    }

    /** ¿El usuario está bien parado? En una empresa que puede ver y en una sucursal de esa misma empresa */
    public static function enOrden(User $usuario): bool
    {
        return self::puede($usuario, (string) $usuario->IdEmpresa)
            && DB::table('empresa_negocios')->where('id_empresa_negocio', $usuario->id_empresa_negocio)->value('IdEmpresa') === (string) $usuario->IdEmpresa;
    }

    /** El usuario pasa a trabajar en otra empresa del estudio (queda guardado: en todas sus pestañas es la misma) */
    public static function cambiar(User $usuario, string $ruc): void
    {
        abort_unless(self::puede($usuario, $ruc), 403, 'No tienes acceso a esa empresa.');
        $sucursal = self::sucursalDe($ruc);
        abort_unless($sucursal, 404, 'Esa empresa no tiene sucursal.');
        DB::transaction(function () use ($usuario, $ruc, $sucursal) {
            DB::table('users')->where('IdUsuario', $usuario->IdUsuario)->update(['IdEmpresa' => $ruc, 'id_empresa_negocio' => $sucursal]);
            DB::table('role_user')->where('user_IdUsuario', $usuario->IdUsuario)->update(['id_empresa_negocio' => $sucursal]);
        });
        $usuario->IdEmpresa = $ruc;
        $usuario->id_empresa_negocio = $sucursal;
        $usuario->syncOriginal();
    }

    /**
     * Registra la empresa de un cliente del estudio con su sucursal y almacén (lo que necesitan los módulos).
     *
     * @param  array{ruc: string, razon_social: string, nombre_comercial?: ?string, direccion?: ?string, ubigeo?: ?string}  $d
     */
    public static function crearEmpresa(array $d): void
    {
        DB::transaction(function () use ($d) {
            $direccion = trim((string) ($d['direccion'] ?? '')) ?: '-';
            Empresa::create(['IdEmpresa' => $d['ruc'], 'NomEmpresa' => $d['razon_social'], 'DirEmpresa' => $direccion, 'EstEmpresa' => 'Activo', 'tipo_envio' => 1]);
            $sucursal = EmpresaNegocio::create([
                'IdEmpresa' => $d['ruc'], 'tipo_negocio' => 'Oficina Principal - '.$d['ruc'],
                'nombre_comercial' => ($d['nombre_comercial'] ?? null) ?: $d['razon_social'], 'direccion' => $direccion,
                'ubigeo' => $d['ubigeo'] ?? null, 'estado' => 'Activo',
            ]);
            Almacen::create(['descripcion' => 'ALMACEN PRINCIPAL', 'predeterminado' => 1, 'id_empresa_negocio' => $sucursal->id_empresa_negocio,
                'direccion' => $direccion, 'ubigeo' => ($d['ubigeo'] ?? null) ?: '']);
            DB::table('estudio_empresas')->insert(['IdEmpresa' => $d['ruc'], 'propia' => false, 'activa' => true, 'created_at' => now(), 'updated_at' => now()]);
        });
    }

    /** Guarda las credenciales (las claves solo si se escriben) y las cuentas por defecto de una empresa del estudio */
    public static function configurar(string $ruc, array $d): void
    {
        $empresa = Empresa::findOrFail($ruc);
        $empresa->client_id = trim((string) ($d['client_id'] ?? '')) ?: null;
        $empresa->sire_usuario = mb_strtoupper(trim((string) ($d['sire_usuario'] ?? ''))) ?: null;
        $empresa->sol_usuario = mb_strtoupper(trim((string) ($d['sol_usuario'] ?? ''))) ?: null;
        foreach (['client_secret', 'sire_clave', 'sol_clave'] as $secreto) {
            if (! empty($d[$secreto])) {
                $empresa->{$secreto} = $d[$secreto];
            }
        }
        $empresa->save();
        cache()->forget('sire_token_'.$ruc);

        $cuentas = array_filter(array_intersect_key($d, array_flip(['cta_compras', 'cta_por_pagar', 'cta_por_cobrar', 'cta_ventas', 'cta_ventas_exo'])), fn ($v) => trim((string) $v) !== '');
        if ($cuentas) {
            DB::table('concar_config')->updateOrInsert(['IdEmpresa' => $ruc], $cuentas + ['updated_at' => now()]);
        }
    }

    /** Quiénes (además del administrador) trabajan una empresa */
    public static function guardarAccesos(string $ruc, array $usuarios): void
    {
        DB::transaction(function () use ($ruc, $usuarios) {
            DB::table('estudio_accesos')->where('IdEmpresa', $ruc)->delete();
            foreach (array_unique(array_map('intval', $usuarios)) as $id) {
                DB::table('estudio_accesos')->insert(['IdUsuario' => $id, 'IdEmpresa' => $ruc, 'created_at' => now(), 'updated_at' => now()]);
            }
        });
    }
}
