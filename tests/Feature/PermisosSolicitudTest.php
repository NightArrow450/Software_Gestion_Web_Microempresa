<?php

namespace Tests\Feature;

use App\Models\Permiso;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\EnvasadoEscenario;
use Tests\TestCase;

class PermisosSolicitudTest extends TestCase
{
    use EnvasadoEscenario, RefreshDatabase;

    public function test_comprobaciones_reutilizan_la_relacion_de_permisos_cargada(): void
    {
        $e = $this->escenarioEnvasado();
        $rol = $e['usuario']->role->load('permisos');
        DB::enableQueryLog();
        DB::flushQueryLog();
        try {
            for ($i = 0; $i < 30; $i++) {
                $this->assertTrue($rol->tienePermiso('envasado.ver'));
                $this->assertFalse($rol->tienePermiso('permiso.inexistente'));
            }
            $this->assertCount(0, DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
        }
    }

    public function test_cada_solicitud_actualiza_permisos_incluso_con_el_mismo_usuario_autenticado(): void
    {
        $e = $this->escenarioEnvasado();
        $permiso = Permiso::firstOrCreate(['nombre' => 'seguimiento.ver'], ['descripcion' => 'Consultar seguimiento']);
        $this->actingAs($e['usuario'])->get(route('seguimiento.index'))->assertForbidden();
        $e['usuario']->role->permisos()->attach($permiso->id);
        $this->get(route('seguimiento.index'))->assertOk();
        $e['usuario']->role->permisos()->detach($permiso->id);
        $this->get(route('seguimiento.index'))->assertForbidden();
    }
}
