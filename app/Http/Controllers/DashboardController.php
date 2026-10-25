<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $usuario = $request->user();
        $usuario->loadMissing('role.permisos');
        $resumenUsuarios = null;
        if ($usuario->role?->nombre === 'Administrador') {
            $porRol = User::query()->leftJoin('roles', fn (JoinClause $join): JoinClause => $join->on('roles.id', '=', 'users.role_id')->whereNull('roles.deleted_at'))
                ->where('users.status', true)->selectRaw('roles.nombre as rol, COUNT(*) as total')
                ->groupBy('roles.nombre')->pluck('total', 'rol');
            $resumenUsuarios = [
                'total' => (int) $porRol->sum(),
                'administradores' => (int) ($porRol['Administrador'] ?? 0),
                'comerciales' => (int) ($porRol['Operaciones Comerciales'] ?? 0),
                'produccion' => (int) ($porRol['Producción/Reparto'] ?? 0),
            ];
        }

        return view('dashboard.index', compact('usuario', 'resumenUsuarios'));
    }
}
