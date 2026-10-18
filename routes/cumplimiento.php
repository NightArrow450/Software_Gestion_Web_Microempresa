<?php

use App\Http\Controllers\CumplimientoPedidoController;
use Illuminate\Support\Facades\Route;

/* Rutas independientes del modulo: conservan las rutas de Sprint 2 y Sprint 3. */
Route::middleware(['auth', 'permiso:pedidos.ver'])->group(function () {
    Route::get('/cumplimiento', [CumplimientoPedidoController::class, 'index'])
        ->name('cumplimiento.index');
    Route::get('/cumplimiento/{pedido}', [CumplimientoPedidoController::class, 'show'])
        ->whereNumber('pedido')->name('cumplimiento.show');
});

Route::middleware(['auth', 'permiso:cumplimiento.preparar'])->group(function () {
    Route::post('/cumplimiento/{pedido}/iniciar-preparacion',
        [CumplimientoPedidoController::class, 'iniciarPreparacion'])
        ->whereNumber('pedido')->name('cumplimiento.iniciar-preparacion');
    Route::post('/cumplimiento/{pedido}/finalizar-preparacion',
        [CumplimientoPedidoController::class, 'finalizarPreparacion'])
        ->whereNumber('pedido')->name('cumplimiento.finalizar-preparacion');
});


Route::post('/cumplimiento/{pedido}/despachar',
    [CumplimientoPedidoController::class, 'despachar'])
    ->middleware(['auth','permiso:cumplimiento.despachar'])
    ->whereNumber('pedido')->name('cumplimiento.despachar');


Route::post('/cumplimiento/{pedido}/entregar',
    [CumplimientoPedidoController::class, 'entregar'])
    ->middleware(['auth','permiso:cumplimiento.entregar'])
    ->whereNumber('pedido')->name('cumplimiento.entregar');
