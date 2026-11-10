<?php

namespace Tests\Feature\Database;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Laravel escribe las fechas sin desfase ('Y-m-d H:i:s') en la zona de la aplicación
 * (UTC). La sesión de PostgreSQL debe estar en la misma zona; si usa la del servidor
 * (p. ej. America/Guayaquil), las fechas quedan corridas cinco horas.
 */
class DatabaseTimezoneTest extends TestCase
{
    public function test_the_postgresql_connection_uses_the_application_timezone(): void
    {
        $this->assertSame('UTC', config('app.timezone'));
        $this->assertSame('UTC', config('database.connections.pgsql.timezone'));
    }

    public function test_the_postgresql_session_runs_in_utc(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('La zona horaria de la sesión solo aplica a PostgreSQL.');
        }

        $this->assertSame('UTC', DB::selectOne('SHOW timezone')->TimeZone);
    }
}
