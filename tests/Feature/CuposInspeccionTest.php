<?php

namespace Tests\Feature;

use RuntimeException;
use Tests\Performance\AutorizacionRendimiento;
use Tests\TestCase;

class CuposInspeccionTest extends TestCase
{
    public function test_no_adelanta_fuera_de_la_ventana_de_turnos_mas_antiguos_aunque_existan_cupos_libres(): void
    {
        $directorio = sys_get_temp_dir().'/dhacer-turnos-'.bin2hex(random_bytes(8));
        mkdir($directorio);
        $directorio = realpath($directorio);
        for ($i = 0; $i < AutorizacionRendimiento::CUPOS_INSPECCION; $i++) {
            file_put_contents($directorio.'/inspector-espera-0000000000000000000'.$i.'.turno', '');
        }
        $ejecutada = false;
        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Tiempo de espera agotado');
            AutorizacionRendimiento::conCupoInspeccion($directorio, static function () use (&$ejecutada): void {
                $ejecutada = true;
            }, 0.03);
        } finally {
            $this->assertFalse($ejecutada);
            $this->assertCount(AutorizacionRendimiento::CUPOS_INSPECCION, glob($directorio.'/*.turno'));
            foreach (glob($directorio.'/*.turno') as $archivo) {
                unlink($archivo);
            }
            rmdir($directorio);
        }
    }

    public function test_cada_llamada_inspecciona_y_libera_su_cupo_incluso_si_falla(): void
    {
        $directorio = sys_get_temp_dir().'/dhacer-cupos-'.bin2hex(random_bytes(8));
        mkdir($directorio);
        $directorio = realpath($directorio);
        try {
            $llamadas = 0;
            $inspeccionar = static function () use (&$llamadas): int {
                return ++$llamadas;
            };
            $this->assertSame(1, AutorizacionRendimiento::conCupoInspeccion($directorio, $inspeccionar));
            $this->assertSame(2, AutorizacionRendimiento::conCupoInspeccion($directorio, $inspeccionar));
            try {
                AutorizacionRendimiento::conCupoInspeccion($directorio, static fn () => throw new RuntimeException('Identidad distinta'));
                $this->fail('No debe ignorar errores de identidad.');
            } catch (RuntimeException $error) {
                $this->assertSame('Identidad distinta', $error->getMessage());
            }
            $this->assertSame(3, AutorizacionRendimiento::conCupoInspeccion($directorio, $inspeccionar));
            $ocupados = [];
            for ($i = 0; $i < AutorizacionRendimiento::CUPOS_INSPECCION; $i++) {
                $archivo = fopen($directorio.'/inspector-'.$i.'.lock', 'c');
                flock($archivo, LOCK_EX);
                $ocupados[] = $archivo;
            }
            try {
                $this->expectException(RuntimeException::class);
                $this->expectExceptionMessage('Tiempo de espera agotado');
                AutorizacionRendimiento::conCupoInspeccion($directorio, $inspeccionar, 0.03);
            } finally {
                foreach ($ocupados as $archivo) {
                    fclose($archivo);
                }
                $this->assertSame(3, $llamadas);
                $this->assertSame([], glob($directorio.'/*.turno'));
            }
        } finally {
            foreach (glob($directorio.'/*.lock') as $ruta) {
                unlink($ruta);
            }
            rmdir($directorio);
        }
    }
}
