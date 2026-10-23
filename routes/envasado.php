<?php

use App\Http\Controllers\CorreccionEnvasadoController;
use App\Http\Controllers\EnvasadoController;
use App\Http\Controllers\MaterialesEnvasadoController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'permiso:materiales.envasado.configurar'])->group(function () {
    Route::get('/presentaciones/{presentacion}/materiales-envasado', [MaterialesEnvasadoController::class, 'index'])->name('envasado.materiales');
    Route::post('/presentaciones/{presentacion}/materiales-envasado', [MaterialesEnvasadoController::class, 'store'])->defaults('idempotente', true)->name('envasado.materiales.store');
    Route::put('/presentaciones/{presentacion}/materiales-envasado/{configuracion}', [MaterialesEnvasadoController::class, 'update'])->defaults('idempotente', true)->name('envasado.materiales.update');
});

Route::middleware(['auth', 'permiso:envasado.ver'])->group(function () {
    Route::get('/envasados', [EnvasadoController::class, 'index'])->name('envasado.index');
    Route::get('/envasados/nuevo', [EnvasadoController::class, 'create'])->middleware('permiso:envasado.registrar')->name('envasado.create');
    Route::post('/envasados/previsualizar', [EnvasadoController::class, 'previsualizar'])->middleware('permiso:envasado.registrar')->name('envasado.previsualizar');
    Route::post('/envasados', [EnvasadoController::class, 'store'])->middleware('permiso:envasado.registrar')->defaults('idempotente', true)->name('envasado.store');
    Route::get('/envasados/{envasado}', [EnvasadoController::class, 'show'])->whereNumber('envasado')->name('envasado.show');
});

Route::middleware(['auth', 'permiso:envasado.ver'])->group(function () {
    Route::post('/envasados/{envasado}/correcciones/previsualizar', [CorreccionEnvasadoController::class, 'previsualizar'])->middleware('permiso:envasado.corregir')->name('envasado.correcciones.previsualizar');
    Route::post('/envasados/{envasado}/correcciones', [CorreccionEnvasadoController::class, 'store'])->middleware('permiso:envasado.corregir')->defaults('idempotente', true)->name('envasado.correcciones.store');
    Route::post('/envasados/{envasado}/reportes', [CorreccionEnvasadoController::class, 'reportar'])->middleware('permiso:envasado.reportar')->defaults('idempotente', true)->name('envasado.reportar');
});
