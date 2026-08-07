<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BackupScheduleTest extends TestCase
{
    #[Test]
    public function automatic_backup_coordinator_health_and_purge_are_scheduled_safely(): void
    {
        $bootstrap = file_get_contents(base_path('bootstrap/app.php'));

        $this->assertIsString($bootstrap);
        $this->assertStringContainsString('DispatchDueBackups::class', $bootstrap);
        $this->assertStringContainsString('everyTenMinutes()', $bootstrap);
        $this->assertStringContainsString("name('backups:dispatch-due')", $bootstrap);
        $this->assertStringContainsString("command('backup:monitor')", $bootstrap);
        $this->assertStringContainsString("command('backups:purge-deleted')", $bootstrap);
        $this->assertGreaterThanOrEqual(3, substr_count($bootstrap, 'onOneServer()'));
        $this->assertGreaterThanOrEqual(3, substr_count($bootstrap, 'withoutOverlapping()'));
    }
}
