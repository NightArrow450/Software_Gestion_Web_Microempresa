<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;

/** Esquema anterior al cierre, exclusivo de SQLite en memoria. */
abstract class TestCaseEsquemaHistorico extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (! app()->environment('testing') || DB::getDriverName() !== 'sqlite' || DB::getDatabaseName() !== ':memory:') {
            throw new \RuntimeException('El esquema histórico exige SQLite en memoria.');
        }
        RefreshDatabaseState::$migrated = false;
        RefreshDatabaseState::$inMemoryConnections = [];
        DB::purge();
        $this->beforeApplicationDestroyed(static function (): void {
            RefreshDatabaseState::$migrated = false;
            RefreshDatabaseState::$inMemoryConnections = [];
        });
    }

    protected function migrateFreshUsing(): array
    {
        return ['--path' => EsquemaSurtidosHistorico::rutas(), '--realpath' => true];
    }
}
