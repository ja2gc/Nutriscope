<?php

namespace Tests\Unit\Backup;

use App\Services\Backup\RecoveryVerifier;
use Illuminate\Support\Facades\DB;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RecoveryVerifierTest extends TestCase
{
    #[Test]
    public function it_accepts_mysql_show_tables_rows_returned_as_objects(): void
    {
        $passwords = Mockery::mock();
        $passwords->shouldReceive('whereNotNull')->with('password')->andReturnSelf();
        $passwords->shouldReceive('limit')->with(100)->andReturnSelf();
        $passwords->shouldReceive('pluck')->with('password')->andReturn(collect([
            '$2y$12$'.str_repeat('a', 53),
        ]));

        $connection = Mockery::mock();
        $connection->shouldReceive('select')->with('SHOW TABLES')->andReturn([
            (object) ['Tables_in_recovery' => 'users'],
            (object) ['Tables_in_recovery' => 'migrations'],
            (object) ['Tables_in_recovery' => 'backup_runs'],
        ]);
        $connection->shouldReceive('select')->with('SHOW COLUMNS FROM users')->andReturn([
            (object) ['Field' => 'email'],
            (object) ['Field' => 'password'],
            (object) ['Field' => 'role'],
            (object) ['Field' => 'is_active'],
        ]);
        $connection->shouldReceive('select')->with("SHOW COLUMNS FROM users WHERE Field = 'role'")->andReturn([
            (object) ['Field' => 'role', 'Type' => "enum('RND','FSS','Admin')"],
        ]);
        $connection->shouldReceive('table')->with('users')->andReturn($passwords);
        $connection->shouldReceive('selectOne')->andReturn((object) ['failures' => 0]);

        DB::shouldReceive('purge')->once()->with('recovery_candidate');
        DB::shouldReceive('connection')->once()->with('recovery_candidate')->andReturn($connection);

        $result = app(RecoveryVerifier::class)->verify([
            'name' => 'nutriscope_recovery_0123456789ab',
            'disposable' => true,
            'promotable' => false,
            'connection' => 'recovery_candidate',
        ]);

        $this->assertTrue($result['passed']);
        $this->assertNotContains(false, $result['checks'], true);
    }
}
