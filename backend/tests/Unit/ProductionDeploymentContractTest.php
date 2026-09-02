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
    public function production_mysql_requires_a_root_password_and_disables_empty_password_initialization(): void
    {
        $compose = file_get_contents(base_path('../docker-compose.prod.yml'));

        $this->assertIsString($compose);
        $this->assertStringContainsString('MYSQL_ROOT_PASSWORD: "${DB_ROOT_PASSWORD:?', $compose);
        $this->assertStringContainsString('MYSQL_ALLOW_EMPTY_PASSWORD: !reset null', $compose);
    }

    #[Test]
    public function digitalocean_deployment_is_fast_forward_only_and_keeps_a_rollback_image(): void
    {
        $workflow = file_get_contents(base_path('../.github/workflows/deploy.yml'));

        $this->assertIsString($workflow);
        $this->assertStringContainsString('git merge --ff-only origin/main', $workflow);
        $this->assertStringContainsString('nutriscope-backend:rollback', $workflow);
        $this->assertStringContainsString('curl --fail --silent --show-error', $workflow);
        $this->assertStringContainsString('envs: DB_ROOT_PASSWORD', $workflow);
        $this->assertStringNotContainsString('export DB_ROOT_PASSWORD=${{ secrets.DB_ROOT_PASSWORD }}', $workflow);
        $this->assertStringNotContainsString('git reset --hard', $workflow);
        $this->assertStringNotContainsString('docker image prune', $workflow);
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
    public function production_keeps_private_uploads_on_persistent_local_storage(): void
    {
        $compose = file_get_contents(base_path('../docker-compose.prod.yml'));
        $dockerignore = file_get_contents(base_path('.dockerignore'));
        $env = file_get_contents(base_path('.env.production.example'));
        $entrypoint = file_get_contents(base_path('docker-entrypoint.sh'));

        $this->assertIsString($compose);
        $this->assertIsString($dockerignore);
        $this->assertIsString($env);
        $this->assertIsString($entrypoint);
        $this->assertStringContainsString('nutriscope_private_uploads:/var/www/html/storage/app/private-uploads', $compose);
        $this->assertStringContainsString('nutriscope_private_uploads:', $compose);
        $this->assertStringContainsString('/storage/app/*', $dockerignore);
        $this->assertStringContainsString('PRIVATE_UPLOADS_DRIVER=local', $env);
        $this->assertStringContainsString('chown www-data:www-data "$private_uploads_path"', $entrypoint);
    }

    #[Test]
    public function public_health_check_is_proxied_directly_to_laravel(): void
    {
        $nginx = file_get_contents(base_path('../nginx/mobile-api.locations.conf'));

        $this->assertIsString($nginx);
        $this->assertStringContainsString('location = /up', $nginx);
        $this->assertStringContainsString('proxy_pass         http://127.0.0.1:8080/up;', $nginx);
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
