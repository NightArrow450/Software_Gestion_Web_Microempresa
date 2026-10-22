<?php

namespace App\Http\Middleware;

use App\Models\Cliente;
use App\Models\Pedido;
use App\Models\Producto;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class EvitarEdicionObsoleta
{
    public function handle(Request $request, Closure $next): Response
    {
        $modelos = ['pedidos.update' => ['pedido', Pedido::class], 'clientes.update' => ['cliente', Cliente::class], 'productos.update' => ['producto', Producto::class]];
        $objetivo = $modelos[$request->route()?->getName()] ?? null;
        if ($objetivo === null || ! $request->user()) {
            return $next($request);
        }

        return DB::transaction(function () use ($request, $next, $objetivo): Response {
            [$parametro, $clase] = $objetivo;
            $referencia = $request->route($parametro);
            $modelo = $clase::whereKey($referencia instanceof Model ? $referencia->getKey() : $referencia)->lockForUpdate()->firstOrFail();
            $datos = $request->validate(['_version' => ['required', 'integer', 'min:0'], '_updated_at' => ['required', 'string']]);
            if ((int) $datos['_version'] !== (int) $modelo->version || $datos['_updated_at'] !== (string) $modelo->getRawOriginal('updated_at')) {
                throw ValidationException::withMessages(['edicion' => 'Otro usuario modificó este registro. Recarga la página y revisa los cambios antes de guardar.']);
            }
            $request->route()->setParameter($parametro, $modelo);
            $respuesta = $next($request);
            if (($respuesta->exception ?? null) !== null || $respuesta->getStatusCode() >= 400) {
                throw new HttpResponseException($respuesta);
            }

            return $respuesta;
        }, 3);
    }
}
