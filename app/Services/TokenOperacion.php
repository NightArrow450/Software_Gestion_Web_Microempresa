<?php

namespace App\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class TokenOperacion
{
    public static function emitir(int $usuarioId): string
    {
        return Crypt::encryptString(json_encode(['usuario_id' => $usuarioId, 'nonce' => (string) Str::uuid(), 'vence' => now()->addDay()->timestamp], JSON_THROW_ON_ERROR));
    }
}
