<?php

use App\Http\Controllers\DisponibilidadInventarioController;
use App\Http\Controllers\InsumoController;
use App\Http\Controllers\MovimientoInventarioController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'permiso:inventario.ver'])->group(function () {
    Route::get('/inventario', [DisponibilidadInventarioController::class, 'index'])
        ->name('inventario.index');
    Route::get('/inventario/movimientos', [MovimientoInventarioController::class, 'index'])
        ->name('inventario.movimientos.index');
    Route::get('/inventario/movimientos/nuevo', [MovimientoInventarioController::class, 'create'])
        ->name('inventario.movimientos.create');
    Route::post('/inventario/movimientos', [MovimientoInventarioController::class, 'store'])
        ->name('inventario.movimientos.store');
});

Route::middleware(['auth', 'permiso:inventario.ajustar'])->group(function () {
    Route::get('/inventario/insumos/nuevo', [InsumoController::class, 'create'])
        ->name('inventario.insumos.create');
    Route::post('/inventario/insumos', [InsumoController::class, 'store'])
        ->name('inventario.insumos.store');
});
