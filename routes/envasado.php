<?php

use App\Http\Controllers\MaterialesEnvasadoController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'permiso:materiales.envasado.configurar'])->group(function () {
    Route::get('/presentaciones/{presentacion}/materiales-envasado', [MaterialesEnvasadoController::class, 'index'])->name('envasado.materiales');
    Route::post('/presentaciones/{presentacion}/materiales-envasado', [MaterialesEnvasadoController::class, 'store'])->defaults('idempotente', true)->name('envasado.materiales.store');
    Route::put('/presentaciones/{presentacion}/materiales-envasado/{configuracion}', [MaterialesEnvasadoController::class, 'update'])->defaults('idempotente', true)->name('envasado.materiales.update');
});
