<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PlatformOperationsDocumentationTest extends TestCase
{
    #[Test]
    public function phase_one_operations_guides_cover_the_portable_runtime_and_backup_boundaries(): void
    {
        $requirements = $this->readDocument('platform-requirements.md');
        $recovery = $this->readDocument('backup-recovery.md');
        $handoff = $this->readDocument('phase-2-platform-handoff.md');

        foreach (['web', 'worker', 'scheduler', 'release', '/up', 'MySQL', 'Redis'] as $requirement) {
            $this->assertStringContainsString($requirement, $requirements);
        }

        foreach (['3 daily', '2 weekly', '3 monthly', '48 hours', 'Recently Deleted', 'does not include'] as $requirement) {
            $this->assertStringContainsString($requirement, $recovery);
        }

        foreach (['Name.com', 'BACKUP_DISK', 'client-owned', 'MFA', 'git rev-parse HEAD'] as $requirement) {
            $this->assertStringContainsString($requirement, $handoff);
        }
    }

    private function readDocument(string $name): string
    {
        $contents = file_get_contents(base_path('../docs/operations/'.$name));

        $this->assertIsString($contents);

        return $contents;
    }
}
