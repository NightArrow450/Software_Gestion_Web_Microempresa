<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\RolePermissionController;
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
| Invitados
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
| Usuarios autenticados
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
    | Administración
    |--------------------------------------------------------------------------
    */

    Route::middleware(
        'role:Administrador'
    )->group(function () {


        /*
        |--------------------------------------------------------------------------
        | Usuarios
        |--------------------------------------------------------------------------
        */

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



        /*
        |--------------------------------------------------------------------------
        | Roles
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/roles-permisos',
            [RolePermissionController::class, 'index']
        )->name('roles.index');


        Route::get(
            '/roles-permisos/nuevo',
            [RolePermissionController::class, 'create']
        )->name('roles.create');


        Route::post(
            '/roles-permisos',
            [RolePermissionController::class, 'store']
        )->name('roles.store');


        Route::get(
            '/roles-permisos/{role}/editar',
            [RolePermissionController::class, 'edit']
        )->name('roles.edit');


        Route::put(
            '/roles-permisos/{role}',
            [RolePermissionController::class, 'update']
        )->name('roles.update');


        Route::delete(
            '/roles-permisos/{role}',
            [RolePermissionController::class, 'destroy']
        )->name('roles.destroy');



        /*
        |--------------------------------------------------------------------------
        | Permisos
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/permisos/nuevo',
            [RolePermissionController::class, 'createPermiso']
        )->name('permisos.create');


        Route::post(
            '/permisos',
            [RolePermissionController::class, 'storePermiso']
        )->name('permisos.store');


        Route::get(
            '/permisos/{permiso}/editar',
            [RolePermissionController::class, 'editPermiso']
        )->name('permisos.edit');


        Route::put(
            '/permisos/{permiso}',
            [RolePermissionController::class, 'updatePermiso']
        )->name('permisos.update');


        Route::delete(
            '/permisos/{permiso}',
            [RolePermissionController::class, 'destroyPermiso']
        )->name('permisos.destroy');

    });

});