<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DatabaseAndLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_sqlite_pragmas_are_configured_on_file_connection(): void
    {
        $tempDb = tempnam(sys_get_temp_dir(), 'inkwell_test_');
        config([
            'database.connections.sqlite_test' => [
                'driver' => 'sqlite',
                'database' => $tempDb,
                'prefix' => '',
            ],
        ]);

        $connection = DB::connection('sqlite_test');

        $journalMode = $connection->select('PRAGMA journal_mode;')[0]->journal_mode ?? null;
        $this->assertSame('wal', strtolower((string) $journalMode));

        $synchronous = $connection->select('PRAGMA synchronous;')[0]->synchronous ?? null;
        // In SQLite: 0 = OFF, 1 = NORMAL, 2 = FULL, 3 = EXTRA
        $this->assertEquals(1, $synchronous);

        $foreignKeys = $connection->select('PRAGMA foreign_keys;')[0]->foreign_keys ?? null;
        $this->assertEquals(1, $foreignKeys);

        $busyTimeout = $connection->select('PRAGMA busy_timeout;')[0]->timeout ?? null;
        $this->assertEquals(5000, (int) $busyTimeout);

        $connection->disconnect();
        @unlink($tempDb);
    }

    public function test_foreign_keys_are_strictly_enforced(): void
    {
        DB::statement('CREATE TABLE test_parents (id INTEGER PRIMARY KEY);');
        DB::statement('CREATE TABLE test_children (id INTEGER PRIMARY KEY, parent_id INTEGER REFERENCES test_parents(id));');

        $this->expectException(QueryException::class);

        try {
            DB::statement('INSERT INTO test_children (id, parent_id) VALUES (1, 9999);');
        } finally {
            DB::statement('DROP TABLE IF EXISTS test_children;');
            DB::statement('DROP TABLE IF EXISTS test_parents;');
        }
    }

    public function test_layout_renders_successfully_for_guests(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Welcome')
            ->where('auth.user', null)
            ->has('canLogin')
            ->has('canRegister')
        );
    }

    public function test_layout_renders_successfully_for_authenticated_users(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Welcome')
            ->where('auth.user.id', $user->id)
            ->where('auth.user.email', $user->email)
        );
    }

    public function test_database_queue_cache_and_session_configurations(): void
    {
        $databaseConfig = require config_path('database.php');
        $this->assertArrayHasKey('sqlite', $databaseConfig['connections']);
        $this->assertArrayNotHasKey('mysql', $databaseConfig['connections']);
        $this->assertArrayNotHasKey('pgsql', $databaseConfig['connections']);
        $this->assertArrayNotHasKey('sqlsrv', $databaseConfig['connections']);

        $envFile = file_get_contents(base_path('.env'));
        $this->assertStringContainsString('DB_CONNECTION=sqlite', $envFile);
        $this->assertStringContainsString('QUEUE_CONNECTION=database', $envFile);
        $this->assertStringContainsString('CACHE_STORE=database', $envFile);
        $this->assertStringContainsString('SESSION_DRIVER=database', $envFile);

        $envExample = file_get_contents(base_path('.env.example'));
        $this->assertStringContainsString('DB_CONNECTION=sqlite', $envExample);
        $this->assertStringContainsString('QUEUE_CONNECTION=database', $envExample);
        $this->assertStringContainsString('CACHE_STORE=database', $envExample);
        $this->assertStringContainsString('SESSION_DRIVER=database', $envExample);
    }
}
