<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/** Cloudflare Turnstile de la web pública (registro y "Crea tu carta"). Sin clave configurada no se exige. */
class Captcha
{
    public static function clave(): ?string
    {
        return config('sitio.turnstile.sitio');
    }

    public static function valido(Request $request): bool
    {
        if (! config('sitio.turnstile.secreto')) {
            return true;
        }
        try {
            return (bool) Http::asForm()->timeout(8)->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                'secret' => config('sitio.turnstile.secreto'), 'response' => (string) $request->input('cf-turnstile-response'), 'remoteip' => $request->ip(),
            ])->json('success');
        } catch (\Throwable $e) {
            return false;
        }
    }
}
