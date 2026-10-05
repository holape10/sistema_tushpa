<?php
namespace App\Support\Tenancy;

use App\Models\Central\Cliente;
use App\Support\EmpresaInicial;
use Illuminate\Support\Facades\{Artisan, DB};

/**
 * Alta de una empresa cliente: crea bd_{RUC}, corre las migraciones (tablas y catálogos),
 * carga los datos iniciales de la empresa y la registra en la base central.
 * Si algo falla, borra la base recién creada para no dejar clientes a medias.
 */
class Provisionador
{
    /**
     * @param array $d ruc, razon_social, nombre_comercial, direccion, ubigeo, usuario, password,
     *                 plan, vence_el, contacto_nombre, contacto_telefono, contacto_correo, notas
     */
    public static function crear(array $d): Cliente
    {
        $ruc = $d['ruc'];
        $base = Tenancy::nombreBase($ruc);
        $central = DB::connection('central');

        if (Cliente::where('ruc', $ruc)->exists()) {
            throw new \RuntimeException("Ya existe un cliente con el RUC {$ruc}.");
        }
        if ($central->selectOne('SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?', [$base])) {
            throw new \RuntimeException("La base de datos {$base} ya existe en el servidor. Revísala antes de volver a crear este cliente.");
        }

        $central->statement("CREATE DATABASE `{$base}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        try {
            Tenancy::en($base, function () use ($d) {
                $codigo = Artisan::call('migrate', ['--force' => true, '--database' => config('database.default')]);
                if ($codigo !== 0) {
                    throw new \RuntimeException('Fallaron las migraciones: ' . trim(Artisan::output()));
                }
                DB::transaction(fn() => EmpresaInicial::crear($d));
            });

            return Cliente::create([
                'ruc'               => $ruc,
                'subdominio'        => ($d['subdominio'] ?? null) ?: null,
                'plan_id'           => $d['plan_id'] ?? null,
                'razon_social'      => $d['razon_social'],
                'nombre_comercial'  => ($d['nombre_comercial'] ?? null) ?: null,
                'base_datos'        => $base,
                'estado'            => 'ACTIVO',
                'plan'              => $d['plan'] ?? null,
                'vence_el'          => $d['vence_el'] ?? null,
                'contacto_nombre'   => $d['contacto_nombre'] ?? null,
                'contacto_telefono' => $d['contacto_telefono'] ?? null,
                'contacto_correo'   => $d['contacto_correo'] ?? null,
                'notas'             => $d['notas'] ?? null,
            ]);
        } catch (\Throwable $e) {
            // Solo se borra la base creada en esta misma llamada (arriba se verificó que no existía)
            $central->statement("DROP DATABASE IF EXISTS `{$base}`");
            throw $e;
        }
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
