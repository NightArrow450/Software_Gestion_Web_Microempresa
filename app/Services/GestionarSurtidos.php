<?php

namespace App\Services;

use App\Models\Pedido;
use App\Models\PedidoDetalle;
use App\Models\PresentacionProducto;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class GestionarSurtidos
{
    private ?bool $habilitado = null;

    public function disponible(): bool
    {
        return $this->habilitado ??= Schema::hasTable('pedido_despacho_componentes');
    }

    public function autorizar(User $usuario): void
    {
        abort_unless($usuario->status && $usuario->role?->tienePermiso('pedidos.surtidos.componer'), 403);
    }

    public function formatos(): array
    {
        if (! $this->disponible()) {
            return [];
        }

        return DB::table('formatos_surtidos as f')->join('productos as p', 'p.id', '=', 'f.producto_id')
            ->where('f.estado', true)->where('p.estado', true)->orderBy('f.nombre')->select('f.*')->get()->map(function ($f): array {
                $ids = DB::table('formato_surtido_skus')->where('formato_surtido_id', $f->id)->pluck('presentacion_producto_id');
                $skus = PresentacionProducto::with(['producto', 'variante'])->whereIn('id', $ids)->where('estado', true)->get()
                    ->filter(fn (PresentacionProducto $p): bool => ! $p->variante || $p->variante->estado)
                    ->map(fn (PresentacionProducto $p): array => ['id' => $p->id, 'nombre' => $this->nombre($p), 'sku' => $p->codigo_sku])->values()->all();

                return (array) $f + ['skus' => $skus];
            })->all();
    }

    public function nombre(PresentacionProducto $p): string
    {
        return $p->producto->nombre.($p->variante ? ' · '.$p->variante->valor : '').' · '.$p->contenido.' '.$p->unidad_medida;
    }

    public function leerPaquetes(mixed $valor): array
    {
        if ($valor === null || $valor === '') {
            return [];
        }
        if (is_string($valor)) {
            try {
                $valor = json_decode($valor, true, 32, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                throw ValidationException::withMessages(['paquetes' => 'La composición enviada no es válida.']);
            }
        }
        if (! is_array($valor) || ! array_is_list($valor) || count($valor) > 10000) {
            throw ValidationException::withMessages(['paquetes' => 'Envía una lista válida de paquetes, sin superar 10 000.']);
        }

        return $valor;
    }

    public function prepararLinea(array $fila, User $usuario, ?PedidoDetalle $actual = null): array
    {
        $this->autorizar($usuario);
        abort_unless($this->disponible(), 503, 'La modalidad surtida requiere sus migraciones autorizadas.');
        $f = DB::table('formatos_surtidos')->where('id', $fila['formato_surtido_id'] ?? 0)->lockForUpdate()->first();
        if (! $f || ! $f->estado || ! DB::table('productos')->where('id', $f->producto_id)->where('estado', true)->exists()) {
            throw ValidationException::withMessages(['detalles' => 'Selecciona una modalidad surtida activa.']);
        }
        $historica = $actual && $actual->tipo_linea === 'surtido' && $actual->formato_surtido_id === $f->id;
        $tamano = $historica ? $actual->unidades_por_empaque : $f->unidades_por_empaque;
        $precio = $historica ? $actual->precio_unitario : $f->precio_empaque;
        if (($fila['modalidad'] ?? '') !== 'empaque' || (isset($fila['unidades_por_empaque']) && (int) $fila['unidades_por_empaque'] !== (int) $tamano)) {
            throw ValidationException::withMessages(['detalles' => 'El tamaño comercial del surtido debe coincidir con el formato seleccionado.']);
        }
        $importe = app(CalculadoraPedido::class)->calcularImporte((int) $fila['cantidad'], number_format((float) $precio, 2, '.', ''));
        if ((float) $importe > 999999.99) {
            throw ValidationException::withMessages(['detalles' => 'El importe de una línea no puede superar S/ 999 999,99.']);
        }

        return ['tipo_linea' => 'surtido', 'formato_surtido_id' => $f->id, 'presentacion_producto_id' => null,
            'codigo_sku' => '', 'descripcion_producto' => ($historica ? preg_replace('/ · Surtido de \d+$/', '', $actual->descripcion_producto) : $f->nombre).' · Surtido de '.$tamano,
            'modalidad' => 'empaque', 'unidades_por_empaque' => $tamano, 'cantidad' => $fila['cantidad'],
            'precio_unitario' => $precio, 'importe' => $importe];
    }

    /** Conserva cada revisión; nunca modifica componentes de una versión anterior. */
    public function guardar(PedidoDetalle $detalle, array $entrada, User $usuario, string $motivo): void
    {
        $this->autorizar($usuario);
        DB::transaction(function () use ($detalle, $entrada, $usuario, $motivo): void {
            $pedido = Pedido::whereKey($detalle->pedido_id)->lockForUpdate()->firstOrFail();
            $detalle = PedidoDetalle::whereKey($detalle->id)->lockForUpdate()->firstOrFail();
            if ($detalle->tipo_linea !== 'surtido' || $detalle->retirado_en !== null || ! in_array($pedido->estado, ['registrado', 'observado', 'confirmado', 'en_preparacion'], true)) {
                throw ValidationException::withMessages(['estado' => 'La composición solo puede modificarse antes de finalizar la preparación.']);
            }
            if (count($entrada) > $detalle->cantidad) {
                throw ValidationException::withMessages(['paquetes' => 'La composición contiene más paquetes que la línea comercial.']);
            }
            Validator::make(['paquetes' => $entrada], [
                'paquetes.*.estado' => ['required', 'in:pendiente,definida'],
                'paquetes.*.componentes' => ['present', 'array', 'max:1000'],
                'paquetes.*.componentes.*.presentacion_producto_id' => ['required', 'integer'],
                'paquetes.*.componentes.*.cantidad' => ['required', 'integer', 'min:1', 'max:1000'],
            ])->validate();
            $formato = DB::table('formatos_surtidos')->where('id', $detalle->formato_surtido_id)->lockForUpdate()->first();
            if (! $formato || ! $formato->estado) {
                throw ValidationException::withMessages(['paquetes' => 'La modalidad surtida está inactiva. Solicita su revisión comercial.']);
            }
            $permitidos = DB::table('formato_surtido_skus')->where('formato_surtido_id', $formato->id)->pluck('presentacion_producto_id')->all();
            $skus = PresentacionProducto::with(['producto', 'variante'])->whereIn('id', $permitidos)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            for ($numero = 1; $numero <= $detalle->cantidad; $numero++) {
                $datos = $entrada[$numero - 1] ?? ['estado' => 'pendiente', 'componentes' => []];
                $componentes = [];
                foreach ($datos['componentes'] as $c) {
                    $p = $skus->get((int) $c['presentacion_producto_id']);
                    if (! $p || ! $p->estado || ! $p->producto->estado || ($p->variante && ! $p->variante->estado)
                        || $p->producto_id !== $formato->producto_id || (float) $p->contenido !== (float) $formato->contenido
                        || $p->unidad_medida !== $formato->unidad_medida || ($p->tipo_envase ?? '') !== $formato->tipo_envase
                        || isset($componentes[$p->id])) {
                        throw ValidationException::withMessages(['paquetes' => 'El paquete '.$numero.' contiene un SKU repetido, inactivo o incompatible.']);
                    }
                    $componentes[$p->id] = ['presentacion_producto_id' => $p->id, 'cantidad' => (int) $c['cantidad'], 'sku' => $p->codigo_sku, 'descripcion' => mb_substr($this->nombre($p), 0, 255)];
                }
                ksort($componentes);
                $suma = array_sum(array_column($componentes, 'cantidad'));
                if ($suma > $detalle->unidades_por_empaque || ($datos['estado'] === 'definida' && $suma !== $detalle->unidades_por_empaque)) {
                    throw ValidationException::withMessages(['paquetes' => 'El paquete '.$numero.' debe sumar exactamente '.$detalle->unidades_por_empaque.' unidades para quedar definido.']);
                }
                $paquete = DB::table('pedido_paquetes_surtidos')->where('pedido_detalle_id', $detalle->id)->where('numero', $numero)->lockForUpdate()->first();
                if (! $paquete) {
                    $id = DB::table('pedido_paquetes_surtidos')->insertGetId(['pedido_detalle_id' => $detalle->id, 'numero' => $numero, 'version' => 0, 'activo' => true]);
                    $paquete = DB::table('pedido_paquetes_surtidos')->find($id);
                }
                $anterior = DB::table('paquete_surtido_composiciones')->where('paquete_surtido_id', $paquete->id)->where('version', $paquete->version)->first();
                $previos = $anterior ? DB::table('paquete_surtido_componentes')->where('composicion_id', $anterior->id)->orderBy('presentacion_producto_id')->get(['presentacion_producto_id', 'cantidad'])->map(fn ($r): array => ['presentacion_producto_id' => (int) $r->presentacion_producto_id, 'cantidad' => (int) $r->cantidad])->all() : null;
                $nuevos = array_map(fn (array $c): array => array_intersect_key($c, array_flip(['presentacion_producto_id', 'cantidad'])), array_values($componentes));
                if ($paquete->activo && $anterior && $anterior->estado === $datos['estado'] && $previos === $nuevos) {
                    continue;
                }
                $version = $paquete->version + 1;
                $revision = DB::table('paquete_surtido_composiciones')->insertGetId(['paquete_surtido_id' => $paquete->id, 'version' => $version, 'estado' => $datos['estado'], 'usuario_id' => $usuario->id, 'motivo' => $motivo, 'created_at' => now()]);
                foreach ($componentes as $c) {
                    DB::table('paquete_surtido_componentes')->insert($c + ['composicion_id' => $revision]);
                }
                DB::table('pedido_paquetes_surtidos')->where('id', $paquete->id)->update(['version' => $version, 'activo' => true]);
                RegistroAuditoria::registrar('composicion_surtido_modificada', 'paquete_surtido', $paquete->id, ['pedido_id' => $pedido->id, 'composicion_id' => $revision, 'version_anterior' => $paquete->version, 'version_nueva' => $version, 'motivo' => $motivo]);
            }
            $retirados = DB::table('pedido_paquetes_surtidos')->where('pedido_detalle_id', $detalle->id)->where('numero', '>', $detalle->cantidad)->where('activo', true)->pluck('id');
            foreach ($retirados as $id) {
                DB::table('pedido_paquetes_surtidos')->where('id', $id)->update(['activo' => false]);
                RegistroAuditoria::registrar('paquete_surtido_retirado', 'paquete_surtido', $id, ['pedido_id' => $pedido->id, 'motivo' => $motivo]);
            }
        }, 3);
    }

    public function paquetes(PedidoDetalle $detalle): array
    {
        if ($detalle->tipo_linea !== 'surtido' || ! $this->disponible()) {
            return [];
        }
        $paquetes = DB::table('pedido_paquetes_surtidos as p')->join('paquete_surtido_composiciones as c', function ($join): void {
            $join->on('c.paquete_surtido_id', '=', 'p.id')->on('c.version', '=', 'p.version');
        })->where('p.pedido_detalle_id', $detalle->id)->where('p.activo', true)->orderBy('p.numero')->get(['p.id', 'p.numero', 'p.version', 'c.id as composicion_id', 'c.estado']);
        $componentes = DB::table('paquete_surtido_componentes')->whereIn('composicion_id', $paquetes->pluck('composicion_id'))->orderBy('presentacion_producto_id')->get()->groupBy('composicion_id');

        return $paquetes->map(fn ($p): array => (array) $p + ['componentes' => $componentes->get($p->composicion_id, collect())->map(fn ($c): array => (array) $c)->all()])->all();
    }

    public function pendientes(Pedido $pedido): array
    {
        $resultado = [];
        foreach ($pedido->detalles as $d) {
            if ($d->tipo_linea !== 'surtido') {
                continue;
            }
            $definidos = count(array_filter($this->paquetes($d), fn (array $p): bool => $p['estado'] === 'definida'));
            if ($definidos < $d->cantidad) {
                $resultado[] = ['pedido' => $pedido->numero, 'detalle_id' => $d->id, 'descripcion' => $d->descripcion_producto, 'paquetes' => $d->cantidad - $definidos, 'unidades' => ($d->cantidad - $definidos) * $d->unidades_por_empaque];
            }
        }

        return $resultado;
    }

    public function validarCierre(Pedido $pedido): void
    {
        if ($this->pendientes($pedido) !== []) {
            throw ValidationException::withMessages(['composicion' => 'Completa la composición de cada paquete surtido antes de finalizar preparación o despachar.']);
        }
        foreach ($this->lineasFisicas($pedido) as $d) {
            if ($d->getAttribute('componente_surtido') && (! $d->presentacion || ! $d->presentacion->estado || ! $d->presentacion->producto->estado || ($d->presentacion->variante && ! $d->presentacion->variante->estado))) {
                throw ValidationException::withMessages(['composicion' => 'Hay un SKU surtido inactivo. Solicita revisión de la composición.']);
            }
        }
    }

    /** Proyecciones solo de lectura: no se persisten como líneas comerciales ni se suman sus precios. @return Collection<int, PedidoDetalle> */
    public function lineasFisicas(Pedido $pedido): Collection
    {
        $pedido->loadMissing('detalles.presentacion.producto', 'detalles.presentacion.variante');
        $salida = collect();
        $componentes = [];
        foreach ($pedido->detalles as $d) {
            if ($d->tipo_linea !== 'surtido') {
                $salida->push($d);

                continue;
            }
            foreach ($this->paquetes($d) as $p) {
                if ($p['estado'] !== 'definida') {
                    continue;
                }
                foreach ($p['componentes'] as $c) {
                    $componentes[] = $c;
                }
            }
        }
        $skus = $componentes === [] ? collect() : PresentacionProducto::with(['producto', 'variante'])->whereIn('id', array_column($componentes, 'presentacion_producto_id'))->get()->keyBy('id');
        foreach ($componentes as $c) {
            $linea = new PedidoDetalle(['presentacion_producto_id' => $c['presentacion_producto_id'], 'codigo_sku' => $c['sku'], 'descripcion_producto' => $c['descripcion'], 'modalidad' => 'unidad', 'cantidad' => $c['cantidad']]);
            $linea->setAttribute('componente_surtido', true);
            $linea->setRelation('presentacion', $skus->get($c['presentacion_producto_id']));
            $salida->push($linea);
        }

        return $salida;
    }

    public function fotografiar(PedidoDetalle $detalle): array
    {
        $existentes = DB::table('pedido_despacho_componentes')->where('pedido_detalle_id', $detalle->id)->orderBy('id')->get();
        if ($existentes->isNotEmpty()) {
            return $existentes->all();
        }
        foreach ($this->paquetes($detalle) as $paquete) {
            $precio = (int) round((float) $detalle->precio_unitario * 100);
            $acumulado = 0;
            foreach ($paquete['componentes'] as $c) {
                $antes = intdiv($precio * $acumulado, $detalle->unidades_por_empaque);
                $acumulado += $c['cantidad'];
                $importe = intdiv($precio * $acumulado, $detalle->unidades_por_empaque) - $antes;
                DB::table('pedido_despacho_componentes')->insert(['pedido_detalle_id' => $detalle->id, 'paquete_surtido_id' => $paquete['id'], 'componente_id' => $c['id'], 'presentacion_producto_id' => $c['presentacion_producto_id'], 'cantidad' => $c['cantidad'], 'importe_centimos' => $importe, 'sku' => $c['sku'], 'descripcion' => $c['descripcion'], 'numero_paquete' => $paquete['numero'], 'created_at' => now()]);
            }
        }

        return DB::table('pedido_despacho_componentes')->where('pedido_detalle_id', $detalle->id)->orderBy('id')->get()->all();
    }
}
