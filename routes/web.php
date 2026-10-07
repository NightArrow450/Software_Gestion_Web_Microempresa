<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;


/*
|--------------------------------------------------------------------------
| Página inicial
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return redirect()->route('login');
});


/*
|--------------------------------------------------------------------------
| Rutas para invitados
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function () {

    Route::get(
        '/login',
        [LoginController::class, 'showLoginForm']
    )->name('login');


    Route::post(
        '/login',
        [LoginController::class, 'login']
    )->name('login.authenticate');

});


/*
|--------------------------------------------------------------------------
| Rutas autenticadas
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {

    Route::get(
        '/dashboard',
        [DashboardController::class, 'index']
    )->name('dashboard');


    Route::post(
        '/logout',
        [LoginController::class, 'logout']
    )->name('logout');


    /*
    |--------------------------------------------------------------------------
    | Gestión de usuarios - Solo Administrador
    |--------------------------------------------------------------------------
    */

    Route::middleware('role:Administrador')->group(function () {

        Route::get(
            '/usuarios',
            [UserController::class, 'index']
        )->name('usuarios.index');


        Route::get(
            '/usuarios/nuevo',
            [UserController::class, 'create']
        )->name('usuarios.create');


        Route::post(
            '/usuarios',
            [UserController::class, 'store']
        )->name('usuarios.store');


        Route::get(
            '/usuarios/{user}',
            [UserController::class, 'show']
        )->name('usuarios.show');


        Route::get(
            '/usuarios/{user}/editar',
            [UserController::class, 'edit']
        )->name('usuarios.edit');


        Route::put(
            '/usuarios/{user}',
            [UserController::class, 'update']
        )->name('usuarios.update');


        Route::patch(
            '/usuarios/{user}/estado',
            [UserController::class, 'toggleStatus']
        )->name('usuarios.cambiar-estado');

    });

});