<?php

namespace App\Providers;

use App\Models\Cliente;
use App\Models\Pedido;
use App\Models\Producto;
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
