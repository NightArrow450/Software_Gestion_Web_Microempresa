<?php

use App\Http\Controllers\SurtidosController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'permiso:surtidos.configurar'])->group(function (): void {
    Route::get('/modalidades-surtidas', [SurtidosController::class, 'index'])->name('surtidos.index');
    Route::post('/modalidades-surtidas', [SurtidosController::class, 'guardar'])->defaults('idempotente', true)->name('surtidos.store');
    Route::put('/modalidades-surtidas/{formato}', [SurtidosController::class, 'guardar'])->whereNumber('formato')->defaults('idempotente', true)->name('surtidos.update');
});
Route::post('/pedidos/{pedido}/surtidos/{detalle}', [SurtidosController::class, 'componer'])->whereNumber(['pedido', 'detalle'])
    ->middleware(['auth', 'permiso:pedidos.ver', 'permiso:pedidos.surtidos.componer'])->defaults('idempotente', true)->name('surtidos.componer');
