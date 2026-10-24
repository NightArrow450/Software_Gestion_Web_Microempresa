<?php

namespace Tests;

use App\Models\Inventario;
use App\Models\Permiso;
use App\Models\PresentacionProducto;
use App\Models\VarianteProducto;
use App\Services\ConfigurarFormatoSurtido;

trait SurtidosEscenario
{
    use EnvasadoEscenario;

    protected function escenarioSurtido(int $stock = 100): array
    {
        $e = $this->escenarioEnvasado(100, $stock);
        foreach (['surtidos.configurar', 'pedidos.surtidos.componer', 'pedidos.crear', 'pedidos.editar', 'cumplimiento.preparar', 'cumplimiento.entregar', 'cumplimiento.retornos.registrar', 'cumplimiento.retornos.aprobar', 'cumplimiento.incidencias.corregir', 'seguimiento.ver'] as $nombre) {
            $e['usuario']->role->permisos()->syncWithoutDetaching(Permiso::firstOrCreate(['nombre' => $nombre], ['descripcion' => $nombre])->id);
        }
        $e['skus'] = [];
        foreach (['Lavanda', 'Limón', 'Floral'] as $i => $aroma) {
            $variante = VarianteProducto::create(['producto_id' => $e['presentacion']->producto_id, 'tipo' => 'Aroma', 'valor' => $aroma, 'estado' => true]);
            if ($i === 0) {
                $sku = $e['presentacion'];
                $sku->update(['variante_producto_id' => $variante->id]);
            } else {
                $sku = PresentacionProducto::create(array_replace($e['presentacion']->getAttributes(), ['id' => null, 'codigo_sku' => $e['presentacion']->codigo_sku.'-'.$i, 'variante_producto_id' => $variante->id]));
                Inventario::create(['presentacion_producto_id' => $sku->id, 'cantidad_actual' => $stock]);
            }
            $e['skus'][] = $sku;
        }
        $this->actingAs($e['usuario']);
        $e['formato'] = app(ConfigurarFormatoSurtido::class)->guardar(['producto_id' => $e['presentacion']->producto_id, 'nombre' => 'Surtido de prueba 1 L', 'unidades_por_empaque' => 12, 'precio_empaque' => '24.00', 'estado' => true, 'skus' => array_column($e['skus'], 'id')], $e['usuario']);

        return $e;
    }

    protected function paquetesSurtidos(array $e): array
    {
        return array_map(fn (array $cantidades): array => ['estado' => 'definida', 'componentes' => array_map(fn (int $i): array => ['presentacion_producto_id' => $e['skus'][$i]->id, 'cantidad' => $cantidades[$i]], [0, 1, 2])], [[5, 4, 3], [2, 7, 3]]);
    }
}
