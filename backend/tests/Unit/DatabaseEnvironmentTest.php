<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class DatabaseEnvironmentTest extends TestCase
{
    /**
     * Ensure the test environment is strictly isolated to an in-memory SQLite database.
     * This protects the Docker Postgres instance from accidental RefreshDatabase truncation.
     */
    public function test_tests_are_running_against_in_memory_sqlite(): void
    {
        // 1. App environment must be testing
        $this->assertEquals('testing', app()->environment());

        // 2. Default connection must be sqlite
        $this->assertEquals('sqlite', Config::get('database.default'));

        // 3. SQLite connection must use :memory:
        $this->assertEquals(':memory:', Config::get('database.connections.sqlite.database'));
    }
}
