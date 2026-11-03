<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Tests\PruebasCierreSurtidos;
use Tests\TestCase;

class CierreEsquemaSurtidosTest extends TestCase
{
    use PruebasCierreSurtidos;

    private function prepararBaseCierre(string $caso): void
    {
        $this->assertTrue(app()->environment('testing'));
        $this->assertSame('sqlite', DB::getDriverName());
        $this->assertSame(':memory:', DB::getDatabaseName());
        RefreshDatabaseState::$migrated = false;
        RefreshDatabaseState::$inMemoryConnections = [];
        DB::purge();
    }
}
