<?php

use App\Http\Controllers\EntregaIncidenciaController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'permiso:pedidos.ver'])->prefix('cumplimiento/{pedido}')->whereNumber('pedido')->group(function () {
    Route::post('resultado-entrega', [EntregaIncidenciaController::class, 'resultado'])->middleware('permiso:cumplimiento.entregar')->defaults('idempotente', true)->name('cumplimiento.resultado-entrega');
    Route::post('rectificar-entrega', [EntregaIncidenciaController::class, 'rectificar'])->middleware('permiso:cumplimiento.incidencias.corregir')->defaults('idempotente', true)->name('cumplimiento.rectificar-entrega');
    Route::post('retornos', [EntregaIncidenciaController::class, 'retorno'])->middleware('permiso:cumplimiento.retornos.registrar')->defaults('idempotente', true)->name('cumplimiento.retornos');
    Route::post('aprobar-retorno', [EntregaIncidenciaController::class, 'aprobar'])->middleware('permiso:cumplimiento.retornos.aprobar')->defaults('idempotente', true)->name('cumplimiento.aprobar-retorno');
    foreach (['revisar' => 'revisar', 'clasificar' => 'revisar', 'resolver' => 'resolver', 'reabrir' => 'corregir'] as $accion => $permiso) {
        Route::post('incidencia/'.$accion, [EntregaIncidenciaController::class, 'gestionar'])->middleware('permiso:cumplimiento.incidencias.'.$permiso)->defaults('accion', $accion)->defaults('idempotente', true)->name('cumplimiento.incidencia.'.$accion);
    }
});
