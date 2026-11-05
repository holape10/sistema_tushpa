<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * Clave guardada cifrada con la APP_KEY. Si encuentra una guardada antes en texto plano (bases que aún no pasaron por la
 * migración que las cifra) la devuelve tal cual, para que nunca se corte el envío a SUNAT.
 */
class Cifrado implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }
        try {
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            return (string) $value;
        }
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value === null || $value === '' ? null : Crypt::encryptString((string) $value);
    }

    /** ¿Ya está cifrado? (para la migración que cifra las de antes) */
    public static function estaCifrado(?string $valor): bool
    {
        if (! $valor) {
            return false;
        }
        try {
            Crypt::decryptString($valor);

            return true;
        } catch (DecryptException) {
            return false;
        }
    }
}
