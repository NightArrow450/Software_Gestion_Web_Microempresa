<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\RolePermissionController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Ruta principal
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return redirect()->route('login');
});

/*
|--------------------------------------------------------------------------
| Autenticación de usuarios invitados
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function () {

    Route::get('/login', [LoginController::class, 'showLoginForm'])
        ->name('login');

    Route::post('/login', [LoginController::class, 'login'])
        ->name('login.authenticate');

});

/*
|--------------------------------------------------------------------------
| Rutas protegidas por autenticación
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->name('dashboard');

    // Cerrar sesión
    Route::post('/logout', [LoginController::class, 'logout'])
        ->name('logout');

    /*
    |--------------------------------------------------------------------------
    | Administración: solo Administrador
    |--------------------------------------------------------------------------
    */

    Route::middleware('role:Administrador')->group(function () {

        // Usuarios
        Route::get('/usuarios', [UserController::class, 'index'])
            ->name('usuarios.index');

        Route::get('/usuarios/nuevo', [UserController::class, 'create'])
            ->name('usuarios.create');

        Route::post('/usuarios', [UserController::class, 'store'])
            ->name('usuarios.store');

        Route::get('/usuarios/{user}', [UserController::class, 'show'])
            ->name('usuarios.show');

        Route::get('/usuarios/{user}/editar', [UserController::class, 'edit'])
            ->name('usuarios.edit');

        Route::put('/usuarios/{user}', [UserController::class, 'update'])
            ->name('usuarios.update');

        Route::patch('/usuarios/{user}/estado', [UserController::class, 'toggleStatus'])
            ->name('usuarios.cambiar-estado');

        // Roles
        Route::get('/roles-permisos', [RolePermissionController::class, 'index'])
            ->name('roles.index');

        Route::get('/roles-permisos/nuevo', [RolePermissionController::class, 'create'])
            ->name('roles.create');

        Route::post('/roles-permisos', [RolePermissionController::class, 'store'])
            ->name('roles.store');

        Route::get('/roles-permisos/{role}/editar', [RolePermissionController::class, 'edit'])
            ->name('roles.edit');

        Route::put('/roles-permisos/{role}', [RolePermissionController::class, 'update'])
            ->name('roles.update');

        Route::delete('/roles-permisos/{role}', [RolePermissionController::class, 'destroy'])
            ->name('roles.destroy');

        // Permisos
        Route::get('/permisos/nuevo', [RolePermissionController::class, 'createPermiso'])
            ->name('permisos.create');

        Route::post('/permisos', [RolePermissionController::class, 'storePermiso'])
            ->name('permisos.store');

        Route::get('/permisos/{permiso}/editar', [RolePermissionController::class, 'editPermiso'])
            ->name('permisos.edit');

        Route::put('/permisos/{permiso}', [RolePermissionController::class, 'updatePermiso'])
            ->name('permisos.update');

        Route::delete('/permisos/{permiso}', [RolePermissionController::class, 'destroyPermiso'])
            ->name('permisos.destroy');

    });

    /*
    |--------------------------------------------------------------------------
    | Catálogo de Productos
    |--------------------------------------------------------------------------
    */

    // Consultar productos y categorías
    Route::middleware('permiso:productos.ver')->group(function () {

        Route::get('/productos', [ProductoController::class, 'index'])
            ->name('productos.index');

        Route::get('/categorias-productos', [CategoriaController::class, 'index'])
            ->name('categorias.index');

        Route::get('/categorias-productos/{categoria}', [CategoriaController::class, 'show'])
            ->whereNumber('categoria')
            ->name('categorias.show');

    });

    // Registrar productos
    Route::middleware('permiso:productos.crear')->group(function () {

        Route::get('/productos/nuevo', [ProductoController::class, 'create'])
            ->name('productos.create');

        Route::post('/productos', [ProductoController::class, 'store'])
            ->name('productos.store');

    });

    // Editar productos y categorías
    Route::middleware('permiso:productos.editar')->group(function () {

        Route::get('/productos/{producto}/editar', [ProductoController::class, 'edit'])
            ->name('productos.edit');

        Route::put('/productos/{producto}', [ProductoController::class, 'update'])
            ->name('productos.update');

        Route::get('/categorias-productos/nueva', [CategoriaController::class, 'create'])
            ->name('categorias.create');

        Route::post('/categorias-productos', [CategoriaController::class, 'store'])
            ->name('categorias.store');

        Route::get('/categorias-productos/{categoria}/editar', [CategoriaController::class, 'edit'])
            ->name('categorias.edit');

        Route::put('/categorias-productos/{categoria}', [CategoriaController::class, 'update'])
            ->name('categorias.update');

    });

    // Ver detalle de producto
    Route::get('/productos/{producto}', [ProductoController::class, 'show'])
        ->middleware('permiso:productos.ver')
        ->name('productos.show');

    // Activar o desactivar productos y categorías
    Route::middleware('permiso:productos.cambiar_estado')->group(function () {

        Route::patch('/productos/{producto}/estado', [ProductoController::class, 'toggleStatus'])
            ->name('productos.cambiar-estado');

        Route::patch('/categorias-productos/{categoria}/estado', [CategoriaController::class, 'toggleStatus'])
            ->name('categorias.cambiar-estado');

    });

    /*
    |--------------------------------------------------------------------------
    | Gestión de Clientes - EN-05
    |--------------------------------------------------------------------------
    */

    // Consultar clientes
    Route::middleware('permiso:clientes.ver')->group(function () {

        Route::get('/clientes', [ClienteController::class, 'index'])
            ->name('clientes.index');

        Route::get('/clientes/{cliente}', [ClienteController::class, 'show'])
            ->whereNumber('cliente')
            ->name('clientes.show');

    });

    // Registrar clientes
    Route::middleware('permiso:clientes.crear')->group(function () {

        Route::get('/clientes/nuevo', [ClienteController::class, 'create'])
            ->name('clientes.create');

        Route::post('/clientes', [ClienteController::class, 'store'])
            ->name('clientes.store');

    });

    // Editar clientes
    Route::middleware('permiso:clientes.editar')->group(function () {

        Route::get('/clientes/{cliente}/editar', [ClienteController::class, 'edit'])
            ->whereNumber('cliente')
            ->name('clientes.edit');

        Route::put('/clientes/{cliente}', [ClienteController::class, 'update'])
            ->whereNumber('cliente')
            ->name('clientes.update');

    });

    // Activar o desactivar clientes
    Route::patch('/clientes/{cliente}/estado', [
        ClienteController::class,
        'toggleStatus'
    ])
        ->whereNumber('cliente')
        ->middleware('permiso:clientes.cambiar_estado')
        ->name('clientes.cambiar-estado');

});
