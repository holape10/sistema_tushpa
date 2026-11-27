<?php

namespace App\Support\Tenancy;

use App\Models\Central\Cliente;
use App\Models\Central\Marca;
use App\Support\EmpresaInicial;
use App\Support\Estudio;
use App\Support\Rubros;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Alta de una empresa cliente: crea bd_{RUC}, corre las migraciones (tablas y catálogos),
 * carga los datos iniciales de la empresa y la registra en la base central.
 * Si algo falla, borra la base recién creada para no dejar clientes a medias.
 */
class Provisionador
{
    /**
     * @param  array  $d  ruc, razon_social, nombre_comercial, direccion, ubigeo, usuario, password,
     *                    plan, vence_el, contacto_nombre, contacto_telefono, contacto_correo, notas,
     *                    marca_id (opcional: empresa de un revendedor con dominio propio; su base lleva el prefijo de la marca),
     *                    otra_sede (solo desde el panel: si el RUC ya tiene sistema, crea otro aparte: bd_{RUC}_2, _3…)
     */
    public static function crear(array $d): Cliente
    {
        $ruc = $d['ruc'];
        $marca = ! empty($d['marca_id']) ? Marca::where('activa', true)->find($d['marca_id']) : null;
        if (! empty($d['marca_id']) && ! $marca) {
            throw new \RuntimeException('La marca de esa empresa no existe o está desactivada.');
        }
        $central = DB::connection('central');

        // Un RUC que ya tiene sistema: desde el panel se le crea otro aparte (otra sede que trabaja por su lado)
        $sede = 1;
        if (Cliente::where('ruc', $ruc)->where('estado', '!=', 'ELIMINADO')->exists()) {
            if (empty($d['otra_sede'])) {
                throw new \RuntimeException("Ya existe un cliente con el RUC {$ruc}.");
            }
            if (empty($d['subdominio'])) {
                throw new \RuntimeException("El RUC {$ruc} ya tiene sistema: para crearle otro aparte ponle un subdominio propio (ej. miempresa2), así cada sede entra por su dirección.");
            }
            $sede = self::siguienteSede($ruc, $marca?->prefijo_bd);
        }
        $base = Tenancy::nombreBase($ruc, $marca?->prefijo_bd, $sede);
        if ($central->selectOne('SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?', [$base])) {
            throw new \RuntimeException("La base de datos {$base} ya existe en el servidor. Revísala antes de volver a crear este cliente.");
        }

        $central->statement("CREATE DATABASE `{$base}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        try {
            // Rápido: se copia la base plantilla (ya migrada). Si no se puede, se migra desde cero como siempre
            self::desdePlantilla($base);
            Tenancy::en($base, function () use ($d) {
                // Con la plantilla copiada no queda ninguna migración pendiente: esto solo confirma (y cubre el caso sin plantilla)
                $codigo = Artisan::call('migrate', ['--force' => true, '--database' => config('database.default')]);
                if ($codigo !== 0) {
                    throw new \RuntimeException('Fallaron las migraciones: '.trim(Artisan::output()));
                }
                DB::transaction(fn () => EmpresaInicial::crear($d));
                // Estudio contable: su propia empresa queda como el estudio y su administrador registra las de sus clientes
                if (! empty(Rubros::RUBROS[$d['rubro'] ?? '']['estudio'])) {
                    Estudio::activar($d['ruc']);
                }
            });

            // Una empresa eliminada antes (su base ya no existe) se vuelve a crear desde cero
            Cliente::where('ruc', $ruc)->where('base_datos', $base)->where('estado', 'ELIMINADO')->delete();

            return Cliente::create([
                'ruc' => $ruc,
                'subdominio' => ($d['subdominio'] ?? null) ?: null,
                'plan_id' => $d['plan_id'] ?? null,
                'razon_social' => $d['razon_social'],
                'rubro' => $d['rubro'] ?? null,
                'max_empresas' => $d['max_empresas'] ?? null,
                'nombre_comercial' => ($d['nombre_comercial'] ?? null) ?: null,
                'base_datos' => $base,
                'estado' => 'ACTIVO',
                'plan' => $d['plan'] ?? null,
                'vence_el' => $d['vence_el'] ?? null,
                'representante' => isset($d['representante']) ? mb_strtoupper(trim($d['representante'])) ?: null : null,
                'representante_dni' => $d['representante_dni'] ?? null,
                'contacto_nombre' => $d['contacto_nombre'] ?? null,
                'contacto_telefono' => $d['contacto_telefono'] ?? null,
                'contacto_correo' => $d['contacto_correo'] ?? null,
                'notas' => $d['notas'] ?? null,
            ] + ($marca ? ['marca_id' => $marca->id] : []));
        } catch (\Throwable $e) {
            // Solo se borra la base creada en esta misma llamada (arriba se verificó que no existía)
            $central->statement("DROP DATABASE IF EXISTS `{$base}`");
            throw $e;
        }
    }

    /**
     * El número del próximo sistema del RUC: el primero libre desde 2, ni registrado en el panel (de cualquier marca) ni
     * como base en el servidor. El número es único por RUC aunque el otro sistema sea de otra marca: de él sale el rango
     * de los resúmenes en SUNAT (Tenancy::desfaseResumenes), y dos sistemas del mismo RUC nunca deben repetirlo.
     */
    public static function siguienteSede(string $ruc, ?string $prefijo): int
    {
        $central = DB::connection('central');
        $usadas = Cliente::where('ruc', $ruc)->where('estado', '!=', 'ELIMINADO')->pluck('base_datos')->all();
        $sedesUsadas = array_map(fn ($b) => Tenancy::sedeDeBase((string) $b), $usadas);
        for ($sede = 2; $sede <= 99; $sede++) {
            $base = Tenancy::nombreBase($ruc, $prefijo, $sede);
            if (! in_array($sede, $sedesUsadas, true) && ! in_array($base, $usadas, true) && ! $central->selectOne('SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?', [$base])) {
                return $sede;
            }
        }
        throw new \RuntimeException("El RUC {$ruc} ya tiene demasiados sistemas.");
    }

    /** Base plantilla: una base ya migrada y sin datos de ninguna empresa, que se copia al crear cada empresa */
    public static function plantilla(): string
    {
        return (string) config('tenancy.prefijo_bd').'plantilla';
    }

    /**
     * Llena la base nueva copiando la plantilla: 3 veces más rápido que correr todas las migraciones desde cero (y
     * cada mejora del sistema suma una migración). Antes se pone la plantilla al día (solo las migraciones nuevas). Si
     * algo falla, la base nueva queda vacía y la empresa se crea como siempre (migrando): nunca queda a medias.
     *
     * @return bool true = se copió la plantilla
     */
    public static function desdePlantilla(string $base): bool
    {
        if (! config('tenancy.usar_plantilla', true)) {
            return false;
        }
        $central = DB::connection('central');
        $plantilla = self::plantilla();
        // Una empresa a la vez pone al día la plantilla y la copia
        if (! (int) $central->selectOne('SELECT GET_LOCK(?, 120) AS l', ['tushpa_plantilla'])->l) {
            return false;
        }
        try {
            self::alDia();
            self::copiarTablas($plantilla, $base);

            return true;
        } catch (\Throwable $e) {
            report($e);
            // La base nueva vuelve a quedar vacía: se migra desde cero
            $central->statement("DROP DATABASE IF EXISTS `{$base}`");
            $central->statement("CREATE DATABASE `{$base}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

            return false;
        } finally {
            $central->selectOne('SELECT RELEASE_LOCK(?) AS l', ['tushpa_plantilla']);
        }
    }

    /**
     * Crea la plantilla si no existe y le corre las migraciones nuevas. Lo llama actualizar.sh (con el sistema ya
     * atendiendo) para que crear una empresa no tenga que esperar; y cada creación, por si acaso.
     *
     * @throws \RuntimeException si las migraciones fallan
     */
    public static function alDia(): void
    {
        $plantilla = self::plantilla();
        DB::connection('central')->statement("CREATE DATABASE IF NOT EXISTS `{$plantilla}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $codigo = Tenancy::en($plantilla, fn () => Artisan::call('migrate', ['--force' => true, '--database' => config('database.default')]));
        if ($codigo !== 0) {
            throw new \RuntimeException('La plantilla no se pudo poner al día: '.trim(Artisan::output()));
        }
    }

    /** Copia todas las tablas de una base a otra (estructura, índices y filas) en el mismo servidor */
    public static function copiarTablas(string $origen, string $destino): int
    {
        foreach ([$origen, $destino] as $b) {
            if (! preg_match('/^[a-z0-9_]{1,64}$/i', $b)) {
                throw new \InvalidArgumentException('Nombre de base de datos no válido.');
            }
        }
        $central = DB::connection('central');
        $tablas = collect($central->select("SELECT TABLE_NAME AS t FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = 'BASE TABLE'", [$origen]))->pluck('t');
        if ($tablas->isEmpty()) {
            throw new \RuntimeException("La base {$origen} no tiene tablas.");
        }
        foreach ($tablas as $t) {
            $central->statement("CREATE TABLE `{$destino}`.`{$t}` LIKE `{$origen}`.`{$t}`");
            $central->statement("INSERT INTO `{$destino}`.`{$t}` SELECT * FROM `{$origen}`.`{$t}`");
        }

        return $tablas->count();
    }

    /** Corre las migraciones pendientes en la base de un cliente; devuelve la salida de artisan */
    public static function migrar(Cliente $cliente): string
    {
        return Tenancy::en($cliente->base_datos, function () {
            $codigo = Artisan::call('migrate', ['--force' => true, '--database' => config('database.default')]);
            $salida = trim(Artisan::output());
            if ($codigo !== 0) {
                throw new \RuntimeException($salida ?: 'Fallaron las migraciones.');
            }

            return $salida;
        });
    }
}
