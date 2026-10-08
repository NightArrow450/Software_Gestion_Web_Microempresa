<?php

namespace Database\Seeders;

use App\Models\Permiso;
use Illuminate\Database\Seeder;

class PermisoSeeder extends Seeder
{
    public function run(): void
    {
        $permisos = [

            /*
            |--------------------------------------------------------------------------
            | Dashboard
            |--------------------------------------------------------------------------
            */

            [
                'nombre' => 'dashboard.ver',
                'descripcion' => 'Permite acceder al panel de control.',
            ],


            /*
            |--------------------------------------------------------------------------
            | Gestión de Usuarios
            |--------------------------------------------------------------------------
            */

            [
                'nombre' => 'usuarios.ver',
                'descripcion' => 'Permite consultar los usuarios registrados.',
            ],

            [
                'nombre' => 'usuarios.crear',
                'descripcion' => 'Permite registrar nuevos usuarios.',
            ],

            [
                'nombre' => 'usuarios.editar',
                'descripcion' => 'Permite modificar los datos de los usuarios.',
            ],

            [
                'nombre' => 'usuarios.cambiar_estado',
                'descripcion' => 'Permite activar o desactivar cuentas de usuario.',
            ],


            /*
            |--------------------------------------------------------------------------
            | Roles y Permisos
            |--------------------------------------------------------------------------
            */

            [
                'nombre' => 'roles.ver',
                'descripcion' => 'Permite consultar los roles y permisos del sistema.',
            ],

            [
                'nombre' => 'roles.editar',
                'descripcion' => 'Permite modificar los permisos asignados a los roles.',
            ],


            /*
            |--------------------------------------------------------------------------
            | Productos
            |--------------------------------------------------------------------------
            */

            [
                'nombre' => 'productos.ver',
                'descripcion' => 'Permite consultar los productos registrados.',
            ],

            [
                'nombre' => 'productos.crear',
                'descripcion' => 'Permite registrar nuevos productos.',
            ],

            [
                'nombre' => 'productos.editar',
                'descripcion' => 'Permite modificar la información de los productos.',
            ],

            [
                'nombre' => 'productos.cambiar_estado',
                'descripcion' => 'Permite activar o desactivar productos.',
            ],


            /*
            |--------------------------------------------------------------------------
            | Pedidos
            |--------------------------------------------------------------------------
            */

            [
                'nombre' => 'pedidos.ver',
                'descripcion' => 'Permite consultar los pedidos registrados.',
            ],

            [
                'nombre' => 'pedidos.crear',
                'descripcion' => 'Permite registrar nuevos pedidos.',
            ],

            [
                'nombre' => 'pedidos.editar',
                'descripcion' => 'Permite modificar los pedidos registrados.',
            ],


            /*
            |--------------------------------------------------------------------------
            | Inventario
            |--------------------------------------------------------------------------
            */

            [
                'nombre' => 'inventario.ver',
                'descripcion' => 'Permite consultar las existencias del inventario.',
            ],

            [
                'nombre' => 'inventario.entrada',
                'descripcion' => 'Permite registrar entradas de materiales o productos.',
            ],

            [
                'nombre' => 'inventario.salida',
                'descripcion' => 'Permite registrar salidas de materiales o productos.',
            ],

            [
                'nombre' => 'inventario.ajustar',
                'descripcion' => 'Permite realizar ajustes manuales de inventario con trazabilidad.',
            ],


            /*
            |--------------------------------------------------------------------------
            | Cumplimiento de Pedidos
            |--------------------------------------------------------------------------
            */

            [
                'nombre' => 'cumplimiento.preparar',
                'descripcion' => 'Permite registrar la preparación de pedidos.',
            ],

            [
                'nombre' => 'cumplimiento.despachar',
                'descripcion' => 'Permite registrar el despacho de pedidos.',
            ],

            [
                'nombre' => 'cumplimiento.entregar',
                'descripcion' => 'Permite registrar la entrega de pedidos.',
            ],


            /*
            |--------------------------------------------------------------------------
            | Seguimiento
            |--------------------------------------------------------------------------
            */

            [
                'nombre' => 'seguimiento.ver',
                'descripcion' => 'Permite consultar el seguimiento y estado de los pedidos.',
            ],

        ];


        foreach ($permisos as $permiso) {

            Permiso::updateOrCreate(

                [
                    'nombre' => $permiso['nombre'],
                ],

                [
                    'descripcion' => $permiso['descripcion'],
                ]

            );

        }
    }
}