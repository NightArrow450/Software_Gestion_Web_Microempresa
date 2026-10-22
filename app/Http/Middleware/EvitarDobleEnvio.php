<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class EvitarDobleEnvio
{
    private const RUTAS = ['pedidos.store', 'pedidos.update', 'pedidos.procesar', 'pedidos.cancelar', 'inventario.movimientos.store', 'cumplimiento.iniciar-preparacion', 'cumplimiento.finalizar-preparacion', 'cumplimiento.despachar', 'cumplimiento.entregar'];

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethodSafe() || ! $request->user() || (! in_array($request->route()?->getName(), self::RUTAS, true) && ! ($request->route()?->defaults['idempotente'] ?? false))) {
            return $next($request);
        }
        try {
            $token = json_decode(Crypt::decryptString((string) $request->input('_token_operacion')), true, 512, JSON_THROW_ON_ERROR);
            if (($token['usuario_id'] ?? null) !== $request->user()->id || ($token['vence'] ?? 0) < now()->timestamp || ! is_string($token['nonce'] ?? null)) {
                throw new \RuntimeException;
            }
        } catch (\Throwable) {
            throw ValidationException::withMessages(['operacion' => 'El formulario venció o no es válido. Recarga la página y vuelve a intentarlo.']);
        }
        $clave = hash('sha256', $token['nonce']);
        $datos = $request->except(['_token', '_token_operacion']);
        $ordenar = function (array &$valores) use (&$ordenar): void {
            ksort($valores);
            foreach ($valores as &$valor) {
                if (is_array($valor)) {
                    $ordenar($valor);
                }
            }
        };
        $ordenar($datos);
        $huella = hash('sha256', json_encode($datos, JSON_THROW_ON_ERROR));
        $operacion = $request->method().' '.$request->path();

        try {
            return DB::transaction(function () use ($request, $next, $clave, $huella, $operacion): Response {
                DB::table('solicitudes_idempotentes')->insertOrIgnore(['usuario_id' => $request->user()->id, 'token' => $clave, 'huella' => $huella, 'operacion' => $operacion, 'created_at' => now()]);
                $registro = DB::table('solicitudes_idempotentes')->where('usuario_id', $request->user()->id)->where('token', $clave)->lockForUpdate()->first();
                if ($registro->huella !== $huella || $registro->operacion !== $operacion) {
                    throw ValidationException::withMessages(['operacion' => 'Este formulario ya fue utilizado con otros datos. Recarga la página.']);
                }
                if ($registro->redireccion !== null) {
                    return redirect($registro->redireccion)->with('success', 'La solicitud ya fue procesada.');
                }
                $respuesta = $next($request);
                if (($respuesta->exception ?? null) !== null || ! $respuesta->isRedirection() || ! $respuesta->headers->has('Location')) {
                    throw new HttpResponseException($respuesta);
                }
                DB::table('solicitudes_idempotentes')->where('id', $registro->id)->update(['redireccion' => $respuesta->headers->get('Location')]);

                return $respuesta;
            }, 3);
        } catch (HttpResponseException $exception) {
            return $exception->getResponse();
        }
    }
}
