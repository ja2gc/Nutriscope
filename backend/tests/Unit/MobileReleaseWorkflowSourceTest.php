<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class MobileReleaseWorkflowSourceTest extends TestCase
{
    public function test_apk_publication_allows_a_bounded_retried_download(): void
    {
        $workflow = file_get_contents(dirname(__DIR__, 3).'/.github/workflows/release-mobile.yml');

        $this->assertIsString($workflow);
        $this->assertStringContainsString('command_timeout: 20m', $workflow);
        $this->assertStringContainsString('--connect-timeout 30', $workflow);
        $this->assertStringContainsString('--max-time 900', $workflow);
        $this->assertStringContainsString('--retry 3', $workflow);
        $this->assertStringContainsString('--retry-all-errors', $workflow);
    }
}
