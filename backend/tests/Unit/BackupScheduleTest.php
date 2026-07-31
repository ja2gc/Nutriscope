<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BackupScheduleTest extends TestCase
{
    #[Test]
    public function automatic_backup_health_and_purge_are_scheduled_safely(): void
    {
        $bootstrap = file_get_contents(base_path('bootstrap/app.php'));

        $this->assertIsString($bootstrap);
        $this->assertStringContainsString("dailyAt('01:30')", $bootstrap);
        $this->assertStringContainsString("name('backups:create-daily')", $bootstrap);
        $this->assertStringContainsString("command('backup:monitor')", $bootstrap);
        $this->assertStringContainsString("command('backups:purge-deleted')", $bootstrap);
        $this->assertGreaterThanOrEqual(3, substr_count($bootstrap, 'onOneServer()'));
        $this->assertGreaterThanOrEqual(3, substr_count($bootstrap, 'withoutOverlapping()'));
    }
}
