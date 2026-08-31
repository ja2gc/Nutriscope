<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProductionDeploymentContractTest extends TestCase
{
    #[Test]
    public function digitalocean_deployment_runs_the_explicit_release_before_starting_services(): void
    {
        $workflow = file_get_contents(base_path('../.github/workflows/deploy.yml'));

        $this->assertIsString($workflow);

        $build = 'docker compose -f docker-compose.yml -f docker-compose.prod.yml --profile release build';
        $release = 'docker compose -f docker-compose.yml -f docker-compose.prod.yml --profile release run --rm backend_release';
        $start = 'docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d';

        $this->assertStringContainsString($build, $workflow);
        $this->assertStringContainsString($release, $workflow);
        $this->assertStringContainsString($start, $workflow);
        $this->assertStringContainsString('set -eu', $workflow);
        $this->assertLessThan(strpos($workflow, $release), strpos($workflow, $build));
        $this->assertLessThan(strpos($workflow, $start), strpos($workflow, $release));
        $this->assertStringNotContainsString('up -d --build', $workflow);
    }

    #[Test]
    public function production_image_build_can_use_the_locked_source_when_dist_is_unavailable(): void
    {
        $dockerfile = file_get_contents(base_path('Dockerfile'));

        $this->assertIsString($dockerfile);
        $this->assertStringContainsString('COPY --from=composer:2.10', $dockerfile);
        $this->assertStringContainsString('composer config --global source-fallback true', $dockerfile);
        $this->assertStringContainsString('composer install --no-dev --optimize-autoloader --no-scripts --no-interaction', $dockerfile);
    }

    #[Test]
    public function named_routes_are_unique_for_production_route_caching(): void
    {
        $duplicates = collect(Route::getRoutes())
            ->map(fn ($route) => $route->getName())
            ->filter()
            ->duplicates()
            ->values()
            ->all();

        $this->assertSame([], $duplicates);
    }

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
