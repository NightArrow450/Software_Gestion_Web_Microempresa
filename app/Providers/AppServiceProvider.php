<?php

namespace App\Providers;

use App\Models\Cliente;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\User;
use App\Services\GestionarSurtidos;
use App\Services\LecturaPedido;
use App\Services\RegistroAuditoria;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(GestionarSurtidos::class);
        $this->app->scoped(LecturaPedido::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        foreach (['created' => 'usuario_creado', 'updated' => 'usuario_actualizado'] as $evento => $accion) {
            User::$evento(function (User $usuario) use ($accion): void {
                $campos = array_intersect(array_keys($usuario->getChanges()), ['first_name', 'last_name', 'email', 'role_id', 'status', 'debe_cambiar_password']);
                $etiquetas = ['first_name' => 'nombres', 'last_name' => 'apellidos', 'email' => 'correo', 'role_id' => 'rol', 'status' => 'estado', 'debe_cambiar_password' => 'cambio de contraseña obligatorio'];
                $campos = array_map(fn (string $campo): string => $etiquetas[$campo], array_values($campos));
                if ($usuario->wasChanged('password')) {
                    $campos[] = 'contraseña';
                }
                RegistroAuditoria::registrar($accion, 'usuario', $usuario->id, ['campos' => $campos, 'rol_anterior_id' => $usuario->getOriginal('role_id'), 'rol_id' => $usuario->role_id, 'estado' => $usuario->status]);
            });
        }
        foreach ([Pedido::class, Cliente::class, Producto::class] as $modelo) {
            $modelo::updating(function (Pedido|Cliente|Producto $registro): void {
                $registro->setAttribute('version', DB::raw('version + 1'));
            });
        }

        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
