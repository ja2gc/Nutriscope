<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProductionDeploymentContractTest extends TestCase
{
    #[Test]
    public function production_env_template_keeps_audit_features_safe_and_https_sessions_secure(): void
    {
        $env = file_get_contents(base_path('.env.production.example'));

        $this->assertIsString($env);
        $this->assertStringContainsString('SESSION_SECURE_COOKIE=true', $env);
        $this->assertStringContainsString('AUDIT_DATABASE_DISK_USED_PERCENT=', $env);
        $this->assertStringContainsString('AUDIT_RETENTION_ENABLED=false', $env);
        $this->assertStringContainsString('AUDIT_EXPORT_ENABLED=false', $env);
    }

    #[Test]
    public function current_operations_guides_do_not_depend_on_the_legacy_deployment_runbook(): void
    {
        $requirements = file_get_contents(base_path('../docs/operations/platform-requirements.md'));
        $handoff = file_get_contents(base_path('../docs/operations/phase-2-platform-handoff.md'));

        $this->assertIsString($requirements);
        $this->assertIsString($handoff);
        $this->assertStringContainsString('release', $requirements);
        $this->assertStringContainsString('Phase 2', $handoff);
        $this->assertStringContainsString('private uploads', strtolower($requirements));
        $this->assertStringContainsString('temporary-database recovery', $handoff);
        $this->assertStringNotContainsString('migrate:fresh', $requirements.$handoff);
    }
}
