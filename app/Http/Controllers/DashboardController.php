<?php

namespace App\Http\Controllers;

use App\Models\User;

class DashboardController extends Controller
{
    public function index()
    {
        /*
        |--------------------------------------------------------------------------
        | Total de usuarios activos
        |--------------------------------------------------------------------------
        */

        $usuariosActivos = User::where(
            'status',
            true
        )->count();


        /*
        |--------------------------------------------------------------------------
        | Administradores activos
        |--------------------------------------------------------------------------
        */

        $administradores = User::where(
            'status',
            true
        )
        ->whereHas('role', function ($query) {

            $query->where(
                'nombre',
                'Administrador'
            );

        })
        ->count();


        /*
        |--------------------------------------------------------------------------
        | Operaciones Comerciales activos
        |--------------------------------------------------------------------------
        */

        $operacionesComerciales = User::where(
            'status',
            true
        )
        ->whereHas('role', function ($query) {

            $query->where(
                'nombre',
                'Operaciones Comerciales'
            );

        })
        ->count();


        /*
        |--------------------------------------------------------------------------
        | Producción / Reparto activos
        |--------------------------------------------------------------------------
        */

        $produccionReparto = User::where(
            'status',
            true
        )
        ->whereHas('role', function ($query) {

            $query->where(
                'nombre',
                'Producción/Reparto'
            );

        })
        ->count();


        /*
        |--------------------------------------------------------------------------
        | Vista
        |--------------------------------------------------------------------------
        */

        return view(
            'dashboard.index',
            compact(
                'usuariosActivos',
                'administradores',
                'operacionesComerciales',
                'produccionReparto'
            )
        );
    }
}