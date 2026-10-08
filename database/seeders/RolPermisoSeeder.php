<?php

namespace Database\Seeders;

use App\Models\Permiso;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolPermisoSeeder extends Seeder
{
    public function run(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Obtener roles
        |--------------------------------------------------------------------------
        */

        $administrador = Role::where(
            'nombre',
            'Administrador'
        )->firstOrFail();


        $operacionesComerciales = Role::where(
            'nombre',
            'Operaciones Comerciales'
        )->firstOrFail();


        $produccionReparto = Role::where(
            'nombre',
            'Producción/Reparto'
        )->firstOrFail();



        /*
        |--------------------------------------------------------------------------
        | ADMINISTRADOR
        |--------------------------------------------------------------------------
        |
        | El Administrador recibe todos los permisos disponibles.
        |
        */

        $administrador->permisos()->sync(

            Permiso::pluck('id')->toArray()

        );



        /*
        |--------------------------------------------------------------------------
        | OPERACIONES COMERCIALES
        |--------------------------------------------------------------------------
        */

        $permisosOperaciones = [

            // Dashboard
            'dashboard.ver',


            // Productos
            'productos.ver',
            'productos.crear',
            'productos.editar',
            'productos.cambiar_estado',


            // Pedidos
            'pedidos.ver',
            'pedidos.crear',
            'pedidos.editar',


            // Inventario
            'inventario.ver',
            'inventario.entrada',
            'inventario.salida',
            'inventario.ajustar',


            // Cumplimiento
            'cumplimiento.preparar',
            'cumplimiento.despachar',
            'cumplimiento.entregar',


            // Seguimiento
            'seguimiento.ver',

        ];


        $operacionesComerciales->permisos()->sync(

            Permiso::whereIn(
                'nombre',
                $permisosOperaciones
            )
            ->pluck('id')
            ->toArray()

        );



        /*
        |--------------------------------------------------------------------------
        | PRODUCCIÓN / REPARTO
        |--------------------------------------------------------------------------
        */

        $permisosProduccion = [

            // Dashboard
            'dashboard.ver',


            // Productos
            'productos.ver',


            // Pedidos
            'pedidos.ver',


            // Inventario
            'inventario.ver',
            'inventario.entrada',
            'inventario.salida',
            'inventario.ajustar',


            // Cumplimiento
            'cumplimiento.preparar',
            'cumplimiento.despachar',
            'cumplimiento.entregar',


            // Seguimiento
            'seguimiento.ver',

        ];


        $produccionReparto->permisos()->sync(

            Permiso::whereIn(
                'nombre',
                $permisosProduccion
            )
            ->pluck('id')
            ->toArray()

        );
    }
}