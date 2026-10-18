<?php

use App\Http\Controllers\DisponibilidadInventarioController;
use App\Http\Controllers\InsumoController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'permiso:inventario.ver'])->group(function () {
    Route::get('/inventario', [DisponibilidadInventarioController::class, 'index'])
        ->name('inventario.index');
});

Route::middleware(['auth', 'permiso:inventario.ajustar'])->group(function () {
    Route::get('/inventario/insumos/nuevo', [InsumoController::class, 'create'])
        ->name('inventario.insumos.create');
    Route::post('/inventario/insumos', [InsumoController::class, 'store'])
        ->name('inventario.insumos.store');
});
