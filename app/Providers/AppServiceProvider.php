<?php

namespace App\Providers;

use App\Models\Cliente;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\User;
use App\Services\RegistroAuditoria;
use Illuminate\Database\Eloquent\Model;
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
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        foreach (['created' => 'usuario_creado', 'updated' => 'usuario_actualizado'] as $evento => $accion) {
            User::$evento(function (User $usuario) use ($accion): void {
                $campos = array_intersect(array_keys($usuario->getChanges()), ['first_name', 'last_name', 'email', 'role_id', 'status', 'debe_cambiar_password']);
                RegistroAuditoria::registrar($accion, 'usuario', $usuario->id, ['campos' => array_values($campos)]);
            });
        }
        foreach ([Pedido::class, Cliente::class, Producto::class] as $modelo) {
            $modelo::updating(function (Model $registro): void {
                $registro->version = DB::raw('version + 1');
            });
        }

        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
