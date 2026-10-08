<?php

namespace App\Http\Controllers;

use App\Models\Permiso;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RolePermissionController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Listado general
    |--------------------------------------------------------------------------
    */

    public function index(): View
    {
        $roles = Role::withCount([
                'users',
                'permisos',
            ])
            ->orderByDesc('es_sistema')
            ->orderBy('id')
            ->get();

        $permisos = Permiso::withCount('roles')
            ->orderBy('nombre')
            ->get();

        return view('roles.index', compact(
            'roles',
            'permisos'
        ));
    }


    /*
    |--------------------------------------------------------------------------
    | Crear Rol
    |--------------------------------------------------------------------------
    */

    public function create(): View
    {
        $permisos = Permiso::orderBy('nombre')->get();

        $permisosAgrupados = $this->agruparPermisos(
            $permisos
        );

        return view('roles.create', compact(
            'permisosAgrupados'
        ));
    }


    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'nombre' => [
                'required',
                'string',
                'max:80',
                'unique:roles,nombre',
            ],

            'permisos' => [
                'nullable',
                'array',
            ],

            'permisos.*' => [
                'integer',
                'exists:permisos,id',
            ],
        ]);

        DB::transaction(function () use ($validated) {

            $role = Role::create([
                'nombre' => $validated['nombre'],
                'es_sistema' => false,
            ]);

            $permisos = $this->filtrarPermisosReservados(
                $validated['permisos'] ?? []
            );

            $role->permisos()->sync($permisos);
        });

        return redirect()
            ->route('roles.index')
            ->with(
                'success',
                'El nuevo rol fue creado correctamente.'
            );
    }


    /*
    |--------------------------------------------------------------------------
    | Editar Rol y sus permisos
    |--------------------------------------------------------------------------
    */

    public function edit(Role $role): View
    {
        $role->load('permisos');

        $permisos = Permiso::orderBy('nombre')->get();

        $permisosAgrupados = $this->agruparPermisos(
            $permisos
        );

        $permisosAsignados = $role
            ->permisos
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->toArray();

        return view('roles.edit', compact(
            'role',
            'permisosAgrupados',
            'permisosAsignados'
        ));
    }


    public function update(
        Request $request,
        Role $role
    ): RedirectResponse {

        $rules = [
            'permisos' => [
                'nullable',
                'array',
            ],

            'permisos.*' => [
                'integer',
                'exists:permisos,id',
            ],
        ];

        /*
        | Los roles base no pueden cambiar de nombre.
        */

        if (!$role->es_sistema) {

            $rules['nombre'] = [
                'required',
                'string',
                'max:80',

                Rule::unique(
                    'roles',
                    'nombre'
                )->ignore($role->id),
            ];
        }

        $validated = $request->validate($rules);

        DB::transaction(function () use (
            $validated,
            $role
        ) {

            /*
            | Los roles personalizados sí pueden
            | cambiar de nombre.
            */

            if (!$role->es_sistema) {

                $role->update([
                    'nombre' => $validated['nombre'],
                ]);
            }


            /*
            |--------------------------------------------------------------------------
            | Administrador
            |--------------------------------------------------------------------------
            |
            | Siempre conserva TODOS los permisos.
            |
            */

            if ($role->nombre === 'Administrador') {

                $role->permisos()->sync(
                    Permiso::pluck('id')->toArray()
                );

                return;
            }


            /*
            |--------------------------------------------------------------------------
            | Otros roles
            |--------------------------------------------------------------------------
            */

            $permisos = $this->filtrarPermisosReservados(
                $validated['permisos'] ?? []
            );

            $role->permisos()->sync($permisos);
        });

        return redirect()
            ->route('roles.index')
            ->with(
                'success',
                'El rol y sus permisos fueron actualizados correctamente.'
            );
    }


    /*
    |--------------------------------------------------------------------------
    | Eliminar Rol
    |--------------------------------------------------------------------------
    */

    public function destroy(Role $role): RedirectResponse
    {
        if ($role->es_sistema) {

            return redirect()
                ->route('roles.index')
                ->with(
                    'error',
                    'Los roles base del sistema no pueden eliminarse.'
                );
        }

        if ($role->users()->exists()) {

            return redirect()
                ->route('roles.index')
                ->with(
                    'error',
                    'No se puede eliminar el rol porque tiene usuarios asignados.'
                );
        }

        DB::transaction(function () use ($role) {

            $role->permisos()->detach();

            $role->delete();
        });

        return redirect()
            ->route('roles.index')
            ->with(
                'success',
                'El rol fue eliminado correctamente.'
            );
    }


    /*
    |--------------------------------------------------------------------------
    | Crear Permiso
    |--------------------------------------------------------------------------
    */

    public function createPermiso(): View
    {
        return view('permisos.create');
    }


    public function storePermiso(
        Request $request
    ): RedirectResponse {

        $validated = $request->validate([
            'nombre' => [
                'required',
                'string',
                'max:120',
                'regex:/^[a-z0-9_]+(?:\.[a-z0-9_]+)+$/',
                'unique:permisos,nombre',
            ],

            'descripcion' => [
                'nullable',
                'string',
                'max:255',
            ],
        ]);

        DB::transaction(function () use ($validated) {

            $permiso = Permiso::create([
                'nombre' => $validated['nombre'],
                'descripcion' =>
                    $validated['descripcion'] ?? null,

                'es_sistema' => false,
            ]);

            /*
            | Todo nuevo permiso se asigna automáticamente
            | al Administrador.
            */

            $administrador = Role::where(
                'nombre',
                'Administrador'
            )->first();

            if ($administrador) {

                $administrador
                    ->permisos()
                    ->syncWithoutDetaching([
                        $permiso->id
                    ]);
            }
        });

        return redirect()
            ->route('roles.index')
            ->with(
                'success',
                'El permiso fue creado correctamente.'
            );
    }


    /*
    |--------------------------------------------------------------------------
    | Editar Permiso
    |--------------------------------------------------------------------------
    */

    public function editPermiso(
        Permiso $permiso
    ): View {

        return view(
            'permisos.edit',
            compact('permiso')
        );
    }


    public function updatePermiso(
        Request $request,
        Permiso $permiso
    ): RedirectResponse {

        $rules = [
            'descripcion' => [
                'nullable',
                'string',
                'max:255',
            ],
        ];

        /*
        | Los permisos base conservan su clave técnica.
        */

        if (!$permiso->es_sistema) {

            $rules['nombre'] = [
                'required',
                'string',
                'max:120',
                'regex:/^[a-z0-9_]+(?:\.[a-z0-9_]+)+$/',

                Rule::unique(
                    'permisos',
                    'nombre'
                )->ignore($permiso->id),
            ];
        }

        $validated = $request->validate($rules);

        if ($permiso->es_sistema) {

            $permiso->update([
                'descripcion' =>
                    $validated['descripcion'] ?? null,
            ]);

        } else {

            $permiso->update([
                'nombre' => $validated['nombre'],

                'descripcion' =>
                    $validated['descripcion'] ?? null,
            ]);
        }

        return redirect()
            ->route('roles.index')
            ->with(
                'success',
                'El permiso fue actualizado correctamente.'
            );
    }


    /*
    |--------------------------------------------------------------------------
    | Eliminar Permiso
    |--------------------------------------------------------------------------
    */

    public function destroyPermiso(
        Permiso $permiso
    ): RedirectResponse {

        if ($permiso->es_sistema) {

            return redirect()
                ->route('roles.index')
                ->with(
                    'error',
                    'Los permisos base del sistema no pueden eliminarse.'
                );
        }

        /*
        | Permitimos que esté asociado al Administrador,
        | pero no a otros roles.
        */

        $otrosRoles = $permiso
            ->roles()
            ->where(
                'roles.nombre',
                '!=',
                'Administrador'
            )
            ->count();

        if ($otrosRoles > 0) {

            return redirect()
                ->route('roles.index')
                ->with(
                    'error',
                    'Antes de eliminar este permiso debes quitarlo de los demás roles.'
                );
        }

        DB::transaction(function () use ($permiso) {

            $permiso->roles()->detach();

            $permiso->delete();
        });

        return redirect()
            ->route('roles.index')
            ->with(
                'success',
                'El permiso fue eliminado correctamente.'
            );
    }


    /*
    |--------------------------------------------------------------------------
    | Métodos auxiliares
    |--------------------------------------------------------------------------
    */

    private function agruparPermisos(
        $permisos
    ) {
        return $permisos
            ->groupBy(function ($permiso) {

                return Str::before(
                    $permiso->nombre,
                    '.'
                );
            })
            ->mapWithKeys(function (
                $items,
                $clave
            ) {

                return [
                    $this->nombreGrupo($clave)
                        => $items
                ];
            });
    }


    private function nombreGrupo(
        string $clave
    ): string {

        $nombres = [
            'dashboard' => 'Dashboard',
            'usuarios' => 'Gestión de Usuarios',
            'roles' => 'Roles y Permisos',
            'productos' => 'Productos',
            'pedidos' => 'Pedidos',
            'inventario' => 'Inventario',
            'cumplimiento' => 'Cumplimiento de Pedidos',
            'seguimiento' => 'Seguimiento',
        ];

        return $nombres[$clave]
            ?? Str::headline($clave);
    }


    /*
    | usuarios.* y roles.* continúan siendo
    | exclusivamente del Administrador.
    */

    private function filtrarPermisosReservados(
        array $permisos
    ): array {

        return Permiso::whereIn(
                'id',
                $permisos
            )
            ->where(
                'nombre',
                'not like',
                'usuarios.%'
            )
            ->where(
                'nombre',
                'not like',
                'roles.%'
            )
            ->pluck('id')
            ->toArray();
    }
}