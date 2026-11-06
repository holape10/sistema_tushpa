<?php

namespace App\Support;

use App\Http\Controllers\EstudioController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Importar las empresas de un estudio contable desde Excel: el formato trae lo que pide el SIRE (Clave SOL principal,
 * ID y CLAVE del API SUNAT y, si tiene, el usuario SOL secundario) y las cuentas por defecto. Un RUC que ya está en el
 * estudio se omite; cada fila se revisa con las mismas reglas que "Nueva empresa".
 */
class EstudioImportar
{
    public const HOJA = 'Empresas';

    /** Columnas del formato, en orden: campo => [título, obligatorio, explicación] */
    public const COLUMNAS = [
        'ruc' => ['RUC *', true, '11 dígitos (10… persona natural o 20… empresa).'],
        'razon_social' => ['Razón social *', true, 'Como figura en SUNAT.'],
        'nombre_comercial' => ['Nombre comercial', false, 'Opcional.'],
        'direccion' => ['Dirección', false, 'Opcional.'],
        'sol_usuario' => ['Usuario SOL principal (SIRE)', false, 'Para el SIRE y para traer los XML de compras.'],
        'sol_clave' => ['Clave SOL principal (SIRE)', false, 'Se guarda cifrada.'],
        'client_id' => ['ID API SUNAT - client_id (SIRE)', false, 'Se genera en SUNAT SOL → Credenciales de API SUNAT.'],
        'client_secret' => ['CLAVE API SUNAT - client_secret (SIRE)', false, 'Se genera junto con el ID. Se guarda cifrada.'],
        'sire_usuario' => ['Usuario SOL secundario del SIRE (opcional)', false, 'Solo si la empresa creó un usuario secundario para el SIRE. Vacío = se usa la Clave SOL principal.'],
        'sire_clave' => ['Clave del usuario SOL secundario (opcional)', false, 'Solo con el usuario secundario.'],
        'cta_compras' => ['Cuenta compras (cargo)', false, 'Ej. 601111. Vacío = la del estudio.'],
        'cta_por_pagar' => ['Cuenta por pagar (abono)', false, 'Ej. 421201.'],
        'cta_por_cobrar' => ['Cuenta por cobrar (cargo)', false, 'Ej. 121201.'],
        'cta_ventas' => ['Cuenta ventas gravadas', false, 'Ej. 701111.'],
        'cta_ventas_exo' => ['Cuenta ventas exoneradas', false, 'Ej. 701111.'],
    ];

    /** El formato para llenar (.xlsx): la hoja Empresas vacía y la hoja Instrucciones con un ejemplo */
    public static function formato(): string
    {
        $titulos = array_column(self::COLUMNAS, 0);
        $ejemplo = ['20553510661', 'COMERCIAL EJEMPLO S.A.C.', 'EL EJEMPLO', 'AV. PRINCIPAL 123 IQUITOS', 'USUARIO1', 'su-clave-sol',
            'a1b2c3d4-0000-1111-2222-333344445555', 'su-clave-api', '', '', '601111', '421201', '121201', '701111', '701111'];
        $instrucciones = [];
        foreach (self::COLUMNAS as [$titulo, $obligatorio, $texto]) {
            $instrucciones[] = [$titulo, $obligatorio ? 'Obligatorio' : 'Opcional', $texto];
        }
        $instrucciones[] = ['', '', ''];
        $instrucciones[] = ['Para el SIRE', '', 'La empresa necesita: Usuario y Clave SOL principal + ID y CLAVE del API SUNAT. Si falta algo, la empresa se crea igual y luego lo completas con "Configurar".'];
        $instrucciones[] = ['RUC que ya existe', '', 'Se omite (no se cambia nada de esa empresa).'];
        $instrucciones[] = ['Seguridad', '', 'Este archivo tiene claves: bórralo después de importarlo.'];

        return (new Excel)
            ->hoja(self::HOJA, $titulos, [])
            ->hoja('Instrucciones', ['Columna', '¿Obligatorio?', 'Qué poner'], $instrucciones)
            ->hoja('Ejemplo', $titulos, [$ejemplo])
            ->guardar();
    }

    /**
     * Importa las filas del Excel (la hoja Empresas o la primera). Una fila vacía se salta; un RUC que ya existe se omite.
     *
     * @return array{nuevas: array<int, string>, omitidas: array<int, string>, errores: array<int, string>, sinSire: array<int, string>, sinCupo: int}
     */
    public static function importar(array $filas): array
    {
        $r = ['nuevas' => [], 'omitidas' => [], 'errores' => [], 'sinSire' => [], 'sinCupo' => 0];
        $campos = array_keys(self::COLUMNAS);
        $vistos = [];
        // La primera fila son los títulos del formato
        foreach (array_slice($filas, 1, null, true) as $i => $fila) {
            $n = $i + 1;   // número de fila en Excel
            $d = [];
            foreach ($campos as $k => $campo) {
                $d[$campo] = trim((string) ($fila[$k] ?? ''));
            }
            if (implode('', $d) === '') {
                continue;
            }
            $d['ruc'] = preg_replace('/\D/', '', $d['ruc']);
            if (isset($vistos[$d['ruc']]) || ($d['ruc'] !== '' && (DB::table('estudio_empresas')->where('IdEmpresa', $d['ruc'])->exists()
                || DB::table('empresa')->where('IdEmpresa', $d['ruc'])->exists()))) {
                $r['omitidas'][$n] = $d['ruc'];

                continue;
            }
            $v = Validator::make($d, [
                'ruc' => ['required', 'digits:11', fn ($c, $valor, $falla) => ConsultaPeru::rucValido((string) $valor) || $falla('el RUC no es válido (revisa los 11 dígitos)')],
                'razon_social' => 'required|string|max:200',
                'nombre_comercial' => 'nullable|string|max:150',
                'direccion' => 'nullable|string|max:250',
            ] + EstudioController::reglasConfiguracion(), ['regex' => 'el :attribute va solo con letras y números'],
                ['ruc' => 'RUC', 'razon_social' => 'razón social'] + EstudioController::NOMBRES);
            if ($v->fails()) {
                $r['errores'][$n] = ($d['ruc'] ?: 'sin RUC').': '.$v->errors()->first();

                continue;
            }
            if (! Estudio::hayCupo()) {
                $r['sinCupo']++;

                continue;
            }
            Estudio::crearEmpresa(['ruc' => $d['ruc'], 'razon_social' => mb_strtoupper($d['razon_social']),
                'nombre_comercial' => $d['nombre_comercial'] !== '' ? mb_strtoupper($d['nombre_comercial']) : null, 'direccion' => $d['direccion'] ?: null]);
            Estudio::configurar($d['ruc'], $d);
            $vistos[$d['ruc']] = true;
            $r['nuevas'][$n] = $d['ruc'];
            // Lo que pide el SIRE: el API (ID y CLAVE) y un usuario SOL con su clave
            $conSol = ($d['sol_usuario'] !== '' && $d['sol_clave'] !== '') || ($d['sire_usuario'] !== '' && $d['sire_clave'] !== '');
            if ($d['client_id'] === '' || $d['client_secret'] === '' || ! $conSol) {
                $r['sinSire'][$n] = $d['ruc'];
            }
        }

        return $r;
    }
}
