<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TestDatabaseIsolationTest extends TestCase
{
    public function test_only_separate_in_memory_game_and_lobby_databases_are_available(): void
    {
        $this->assertSame('testing', app()->environment());
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(['sqlite', 'lobby'], array_keys(config('database.connections')));

        foreach (['sqlite', 'lobby'] as $name) {
            $connection = DB::connection($name);
            $this->assertSame('sqlite', $connection->getDriverName());
            $this->assertSame(':memory:', $connection->getDatabaseName());
        }

        DB::connection('sqlite')->statement('CREATE TABLE isolation_probe (id INTEGER)');
        $this->assertFalse(DB::connection('lobby')->getSchemaBuilder()->hasTable('isolation_probe'));
    }
}
