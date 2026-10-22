<?php

namespace App\Console\Commands;

use App\Models\Pedido;
use Illuminate\Console\Command;

class RevisarDespachosPrevios extends Command
{
    protected $signature = 'inventario:revisar-despachos-previos';

    protected $description = 'Lista pedidos despachados o entregados sin movimientos de salida vinculados, sin modificar datos';

    public function handle(): int
    {
        $pedidos = Pedido::query()->whereIn('estado', ['despachado', 'entregado'])
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')->from('movimientos_inventario')->whereColumn('pedido_id', 'pedidos.id')->where('tipo', 'salida');
            })->orderBy('id')->get(['id', 'estado']);
        $this->table(['Pedido', 'Estado'], $pedidos->map(fn (Pedido $pedido): array => [$pedido->numero, $pedido->estado])->all());
        $this->info('Pedidos sin salida vinculada: '.$pedidos->count());

        return self::SUCCESS;
    }
}
