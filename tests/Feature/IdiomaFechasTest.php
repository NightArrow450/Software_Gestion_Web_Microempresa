<?php

namespace Tests\Feature;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Tests\TestCase;

class IdiomaFechasTest extends TestCase
{
    public function test_usa_espanol_y_el_dia_de_lima_cuando_utc_ya_cambio_de_dia(): void
    {
        $this->travelTo(Carbon::parse('2026-10-19 02:00:00', 'UTC'));

        $this->assertSame('es', app()->getLocale());
        $this->assertSame('es', config('app.fallback_locale'));
        $this->assertSame('America/Lima', config('app.timezone'));
        $this->assertSame('2026-10-18', today()->toDateString());
        $this->assertSame('21:00', now()->format('H:i'));
    }

    public function test_los_errores_incluyen_atributos_y_contrasenas_en_espanol(): void
    {
        $validator = Validator::make(['password' => 'abcdefghij'], [
            'first_name' => ['required'],
            'password' => [Password::min(10)->letters()->mixedCase()->numbers()],
        ]);

        $this->assertSame('El campo nombres es obligatorio.', $validator->errors()->first('first_name'));
        $this->assertContains('La contraseña debe contener mayúsculas y minúsculas.', $validator->errors()->get('password'));
        $this->assertContains('La contraseña debe contener al menos un número.', $validator->errors()->get('password'));
    }

    public function test_todas_las_reglas_del_framework_tienen_traduccion(): void
    {
        $ingles = require base_path('vendor/laravel/framework/src/Illuminate/Translation/lang/en/validation.php');
        $espanol = require lang_path('es/validation.php');
        unset($ingles['custom'], $ingles['attributes'], $espanol['attributes']);

        $this->assertSame(array_keys($ingles), array_keys($espanol));
    }
}
