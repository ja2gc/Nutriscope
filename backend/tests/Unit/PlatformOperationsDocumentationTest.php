<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PlatformOperationsDocumentationTest extends TestCase
{
    #[Test]
    public function operations_guides_link_the_runtime_recovery_and_turnover_authorities(): void
    {
        $requirements = $this->readDocument('platform-requirements.md');
        $recovery = $this->readDocument('backup-recovery.md');
        $handoff = $this->readDocument('phase-2-platform-handoff.md');

        foreach (['backend', 'frontend', 'worker', 'scheduler', 'release', '/up', 'mysql', 'redis'] as $requirement) {
            $this->assertStringContainsString($requirement, strtolower($requirements));
        }

        foreach (['nutriscope-backups.php', 'filesystems.php', 'isolated temporary target', 'BACKUP_RESTORE_ENABLED', 'APP_KEY', 'turnover.md'] as $requirement) {
            $this->assertStringContainsString($requirement, $recovery);
        }

        foreach (['README.md', 'turnover.md', 'vps-setup.md', 'environment.md', 'integrations.md', 'mobile-release.md'] as $requirement) {
            $this->assertStringContainsString($requirement, $handoff);
            $this->assertFileExists(base_path('../docs/operations/'.$requirement));
        }

        $this->assertStringNotContainsString('Technical Operator website role', $handoff);
        $this->assertStringNotContainsString('Public upload storage', $handoff);
    }

    private function readDocument(string $name): string
    {
        $contents = file_get_contents(base_path('../docs/operations/'.$name));

        $this->assertIsString($contents);

        return $contents;
    }
}
