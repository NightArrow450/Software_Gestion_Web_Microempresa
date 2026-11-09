<?php

namespace App\Console\Commands;

use App\Services\EntornoDemostracion;
use Illuminate\Console\Command;
use Throwable;

class VerificarDemostracion extends Command
{
    protected $signature = 'demostracion:verificar';

    protected $description = 'Comprueba por lectura el aislamiento de la demostración, sin inicializar datos';

    public function handle(EntornoDemostracion $entorno): int
    {
        try {
            $resultado = $entorno->verificar();
        } catch (Throwable) {
            $this->error('Verificación rechazada. Revisa la configuración y la identidad del servidor independiente; no se inicializó ningún dato.');

            return self::FAILURE;
        }
        $this->info('Conexión independiente verificada: '.$resultado['base'].'; puerto '.$resultado['puerto'].'.');
        $this->warn('Esta comprobación no autoriza migraciones, seeders ni certifica la procedencia de los datos.');

        return self::SUCCESS;
    }
}
