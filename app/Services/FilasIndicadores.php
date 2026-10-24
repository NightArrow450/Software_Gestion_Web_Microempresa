<?php

namespace App\Services;

class FilasIndicadores
{
    public const REPORTES = [
        'materiales_resumen' => ['Consumo neto por insumo', 'Consumo bruto menos devolución real, por insumo, código histórico y unidad. Las pérdidas ya forman parte del consumo: no se restan otra vez.'],
        'saldos_periodo' => ['Saldos verificables del período', 'Primer saldo anterior, variación y último saldo nuevo de las cadenas con movimientos en el período. No se inventa un saldo para artículos sin historial.'],
        'situacion_entregas' => ['Situación actual de entregas y retornos', 'Pedidos seleccionados por fecha del pedido; cantidades acumuladas actuales. Los históricos sin desglose no se convierten en aceptación automática.'],
        'comercial' => ['Líneas comerciales', 'Situación actual de pedidos seleccionados por fecha del pedido. Cancelados separados; importes no equivalen a cobros.'],
        'preparacion' => ['Necesidades por SKU', 'Demanda actual de confirmados y en preparación, por fecha prevista de entrega. Incluye atrasados si no se fija Desde. El stock no es producción ni reserva.'],
        'fisicas' => ['Desglose físico de la demanda', 'Solo unidades normales y composiciones vigentes definidas de pedidos pendientes.'],
        'pendientes' => ['Composiciones pendientes', 'Las unidades sin distribuir están incluidas en las unidades sin composición definitiva: no se suman entre sí. No hay demanda por aroma para paquetes pendientes.'],
        'composiciones' => ['Composiciones e historial', 'Selección por fecha del pedido. Las versiones históricas son trazabilidad y no se suman como demanda.'],
        'entregas' => ['Resultados y rectificaciones de entrega', 'Actividad por fecha del hecho. Incluye cambios con signo y valoración informativa; no modifica cobros.'],
        'retornos' => ['Recepciones y clasificaciones', 'Recepciones por fecha del hecho; clasificaciones por fecha de registro. Clasificar no es recibir otra vez.'],
        'aprobaciones' => ['Reincorporaciones verificadas', 'Actividad por fecha de aprobación; solo se cuantifican entradas concordantes con el movimiento autorizado.'],
        'envasados' => ['Envasados y correcciones', 'Actividad por fecha de registro. Las correcciones pueden afectar envasados de períodos anteriores. La producción ya está incluida en stock.'],
        'materiales' => ['Consumos y devoluciones de materiales', 'Actividad por fecha de registro, separada por insumo y unidad de medida. No se estiman recetas ni mangas.'],
        'movimientos' => ['Movimientos de inventario', 'Actividad por fecha de registro. Cada movimiento aparece una vez; la variación usa stock nuevo menos anterior.'],
        'inventario' => ['Saldos actuales', 'Existencias actuales a la hora de consulta; no son saldos históricos del período. No se suman unidades de medida diferentes.'],
    ];

    public const CAMPOS = [
        'materiales_resumen' => 'insumo_id,codigo,unidad_medida,consumo_bruto,devuelto,perdido,salida_neta',
        'saldos_periodo' => 'presentacion_id,insumo_id,sku,unidad_medida,saldo_inicial,variacion,saldo_final,primer_movimiento_id,ultimo_movimiento_id,conciliacion',
        'situacion_entregas' => 'pedido_id,estado_actual,linea_id,despacho_componente_id,presentacion_id,sku,despachadas,aceptadas,rechazadas,pendientes,recibidas,aptas,danadas,por_revisar,no_recuperadas,reincorporadas,por_retornar,por_aprobar,aceptado_centimos,rechazado_centimos,pendiente_centimos,verificable',
        'comercial' => 'pedido_id,linea_id,fecha_pedido,fecha_entrega,estado_actual,tipo_linea,modalidad,descripcion,sku,cantidad_comercial,tamano,paquetes_definidos,paquetes_pendientes,importe_centimos',
        'preparacion' => 'presentacion_id,sku,solicitadas,stock_actual,faltante_conocido,cobertura',
        'fisicas' => 'pedido_id,linea_id,paquete_id,composicion_id,componente_id,presentacion_id,sku,descripcion,unidades_fisicas,fuente',
        'pendientes' => 'pedido_id,linea_id,descripcion,paquetes_pendientes,unidades_sin_composicion,unidades_sin_distribuir',
        'composiciones' => 'pedido_id,linea_id,paquete_id,numero_paquete,composicion_id,version,version_vigente,estado_composicion,fecha_registro,componente_id,presentacion_id,sku,descripcion,unidades_fisicas,demanda_definitiva',
        'entregas' => 'entrega_detalle_id,pedido_id,linea_id,despacho_componente_id,presentacion_id,sku,tipo,fecha_hecho,fecha_registro,aceptadas,rechazadas,aceptado_centimos,rechazado_centimos,fuente',
        'retornos' => 'pedido_id,linea_id,despacho_componente_id,retorno_id,retorno_detalle_id,evento_id,sku,tipo_operacion,fecha_hecho,fecha_registro,recibidas,aptas,danadas,en_revision,no_recuperadas',
        'aprobaciones' => 'evento_id,pedido_id,linea_id,despacho_componente_id,retorno_detalle_id,sku,fecha_registro,movimiento_id,reincorporadas,verificable',
        'envasados' => 'envasado_id,correccion_id,fecha_registro,pedido_id,presentacion_id,sku,clasificacion,envasadas_brutas,correcciones_administrativas,perdidas_fisicas,efecto_neto,neto_actual_envasado',
        'materiales' => 'consumo_id,correccion_material_id,envasado_id,pedido_id,insumo_id,codigo,unidad_medida,funcion,fecha_registro,consumo_bruto,devuelto,perdido,salida_neta',
        'movimientos' => 'movimiento_id,fecha_registro,pedido_id,presentacion_id,insumo_id,sku,unidad_medida,tipo,origen_tipo,origen_id,cantidad,stock_anterior,stock_nuevo,variacion',
        'inventario' => 'tipo_item,presentacion_id,insumo_id,sku,unidad_medida,stock_actual',
    ];

    public function __construct(private readonly PedidosIndicadores $pedidos, private readonly ActividadIndicadores $actividad) {}

    public function campos(array $f): array
    {
        return array_values(array_filter(explode(',', self::CAMPOS[$f['reporte']]), static fn (string $c): bool => $f['economico'] || ! str_ends_with($c, '_centimos')));
    }

    public function filas(array $f): \Generator
    {
        if ($f['reporte'] === 'situacion_entregas') {
            foreach (app(SituacionEntregaIndicadores::class)->filas($f) as $fila) {
                yield array_intersect_key($fila, array_flip($this->campos($f)));
            }

            return;
        }
        $servicio = in_array($f['reporte'], ['comercial', 'preparacion', 'fisicas', 'pendientes', 'composiciones'], true) ? $this->pedidos : $this->actividad;
        foreach ($servicio->{$f['reporte']}($f) as $fila) {
            yield array_intersect_key($fila, array_flip($this->campos($f)));
        }
    }

    public static function titulo(string $campo): string
    {
        return ucfirst(str_replace(['_centimos', '_id', '_'], [' (céntimos PEN)', ' ID', ' '], $campo));
    }
}
