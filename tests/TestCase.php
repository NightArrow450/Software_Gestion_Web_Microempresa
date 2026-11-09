<?php

namespace Tests;

use App\Services\TokenOperacion;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        // Emula los tokens que ahora envían los formularios; null explícito permite probar su ausencia.
        if (! in_array(strtoupper($method), ['GET', 'HEAD'], true) && auth()->check()) {
            if (in_array(strtoupper($method), ['PUT', 'PATCH'], true)) {
                $ruta = parse_url($uri, PHP_URL_PATH);
                if (preg_match('~/(pedidos|clientes|productos)/(\\d+)$~', $ruta, $coincidencia)) {
                    $registro = DB::table($coincidencia[1])->where('id', $coincidencia[2])->first();
                    if ($registro && ! array_key_exists('_version', $parameters)) {
                        $parameters['_version'] = $registro->version;
                        $parameters['_updated_at'] = $registro->updated_at;
                    }
                }
            }
            if ($content !== null && str_contains($server['CONTENT_TYPE'] ?? '', 'json')) {
                $datos = json_decode($content, true);
                if (is_array($datos) && ! array_key_exists('_token_operacion', $datos)) {
                    $datos['_token_operacion'] = TokenOperacion::emitir(auth()->id());
                    $content = json_encode($datos);
                }
            } elseif (! array_key_exists('_token_operacion', $parameters)) {
                $parameters['_token_operacion'] = TokenOperacion::emitir(auth()->id());
            }
        }

        return parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
    }
}
