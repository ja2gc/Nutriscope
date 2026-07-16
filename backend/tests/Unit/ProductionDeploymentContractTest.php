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
    public function deployment_runbook_matches_the_approved_disposable_demo_reset(): void
    {
        $runbook = file_get_contents(base_path('../deployment.md'));

        $this->assertIsString($runbook);
        $this->assertStringContainsString('migrate:fresh --seed --force', $runbook);
        $this->assertStringContainsString('FoodItemsSeeder', $runbook);
        $this->assertStringContainsString('RecipeSeeder', $runbook);
        $this->assertStringContainsString('Elena Villanueva', $runbook);
        $this->assertStringContainsString('Rosa Mae Dela Cruz', $runbook);
        $this->assertStringContainsString('Maria Santos', $runbook);
    }
}
