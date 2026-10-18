<?php

use App\Http\Controllers\SeguimientoPedidoController;
use Illuminate\Support\Facades\Route;

// Solo usuarios autenticados con permiso de consulta.
Route::middleware(['auth', 'permiso:seguimiento.ver'])->group(function () {
    Route::get('/seguimiento', [SeguimientoPedidoController::class, 'index'])
        ->name('seguimiento.index');

    Route::get('/seguimiento/{pedido}/trazabilidad', [SeguimientoPedidoController::class, 'trazabilidad'])
        ->whereNumber('pedido')->name('seguimiento.trazabilidad');

    Route::get('/seguimiento/{pedido}', [SeguimientoPedidoController::class, 'show'])
        ->whereNumber('pedido')->name('seguimiento.show');
});
