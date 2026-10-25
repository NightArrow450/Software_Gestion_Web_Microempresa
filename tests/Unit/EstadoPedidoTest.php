<?php

namespace Tests\Unit;

use App\EstadoPedido;
use PHPUnit\Framework\TestCase;

class EstadoPedidoTest extends TestCase
{
    public function test_matriz_completa_conserva_transiciones_y_no_permite_reabrir_preparacion(): void
    {
        $permitidas = [
            'registrado' => ['confirmado', 'observado', 'cancelado'],
            'observado' => ['confirmado', 'cancelado'],
            'confirmado' => ['en_preparacion', 'cancelado'],
            'en_preparacion' => ['listo_despacho'],
            'listo_despacho' => ['despachado'],
            'despachado' => ['entrega_pendiente', 'entrega_parcial', 'rechazado', 'entregado'],
            'entrega_pendiente' => ['entrega_pendiente', 'entrega_parcial', 'rechazado', 'entregado'],
            'entrega_parcial' => ['entrega_pendiente', 'entrega_parcial', 'rechazado', 'entregado'],
            'rechazado' => ['entrega_pendiente', 'entrega_parcial', 'rechazado', 'entregado'],
            'entregado' => ['entrega_pendiente', 'entrega_parcial', 'rechazado'],
            'cancelado' => [],
        ];
        foreach (EstadoPedido::cases() as $origen) {
            foreach (EstadoPedido::cases() as $destino) {
                $this->assertSame(in_array($destino->value, $permitidas[$origen->value], true), $origen->permite($destino), $origen->value.' → '.$destino->value);
            }
        }
    }

    public function test_etiquetas_conservan_claves_y_textos_visibles_en_espanol(): void
    {
        $this->assertSame([
            'registrado' => 'Registrado', 'observado' => 'Observado', 'confirmado' => 'Confirmado',
            'en_preparacion' => 'En preparación', 'listo_despacho' => 'Listo para despacho',
            'despachado' => 'Despachado', 'entrega_pendiente' => 'Entrega pendiente',
            'entrega_parcial' => 'Entrega parcial', 'rechazado' => 'Rechazado',
            'entregado' => 'Entregado', 'cancelado' => 'Cancelado',
        ], EstadoPedido::etiquetas());
    }
}
