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
            Tenancy::en($base, function () use ($d) {
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

    /** El número del próximo sistema del RUC: el primero libre desde 2 (ni registrado en el panel ni como base en el servidor) */
    private static function siguienteSede(string $ruc, ?string $prefijo): int
    {
        $central = DB::connection('central');
        $usadas = Cliente::where('ruc', $ruc)->where('estado', '!=', 'ELIMINADO')->pluck('base_datos')->all();
        for ($sede = 2; $sede <= 99; $sede++) {
            $base = Tenancy::nombreBase($ruc, $prefijo, $sede);
            if (! in_array($base, $usadas, true) && ! $central->selectOne('SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?', [$base])) {
                return $sede;
            }
        }
        throw new \RuntimeException("El RUC {$ruc} ya tiene demasiados sistemas.");
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
