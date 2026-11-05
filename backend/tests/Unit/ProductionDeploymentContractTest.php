<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProductionDeploymentContractTest extends TestCase
{
    public function test_full_backend_suite_passes_before_deployment_can_start(): void
    {
        $workflow = file_get_contents(base_path('../.github/workflows/deploy.yml'));

        $this->assertStringContainsString('backend_tests:', $workflow);
        $this->assertStringContainsString('needs: [frontend_typecheck, backend_tests]', $workflow);
        $this->assertStringContainsString('image: mysql:8.4', $workflow);
        $this->assertStringContainsString('MYSQL_DATABASE: nutriscope_test', $workflow);
        $this->assertStringContainsString('TZ: UTC', $workflow);
        $this->assertStringContainsString('APP_ENV: testing', $workflow);
        $this->assertStringContainsString('vendor/bin/phpunit', $workflow);
    }

    public function test_all_production_backend_processes_share_the_configured_ai_model(): void
    {
        $compose = file_get_contents(base_path('../docker-compose.prod.yml'));
        $common = explode('services:', $compose)[0];
        $this->assertStringContainsString('ANTHROPIC_MODEL: "claude-sonnet-5-5"', $common);
        $this->assertSame(4, substr_count($compose, '<<: *backend-common'));
        $this->assertStringContainsString("'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-5-5')", file_get_contents(config_path('services.php')));
        $this->assertStringContainsString('ANTHROPIC_MODEL', file_get_contents(base_path('.env.production.example')));
    }

    #[Test]
    public function production_email_uses_resends_https_api_when_smtp_egress_is_blocked(): void
    {
        $compose = file_get_contents(base_path('../docker-compose.prod.yml'));
        $environment = file_get_contents(base_path('.env.production.example'));

        $this->assertIsString($compose);
        $this->assertIsString($environment);
        $this->assertStringContainsString('MAIL_MAILER: "resend-api"', $compose);
        $this->assertStringContainsString('MAIL_MAILER', $environment);
        $this->assertStringContainsString("'resend-api' => [", file_get_contents(config_path('mail.php')));
        $this->assertStringContainsString("'transport' => 'resend-api'", file_get_contents(config_path('mail.php')));
    }

    public function test_production_environment_exposes_private_database_dump_tls_controls(): void
    {
        $database = file_get_contents(config_path('database.php'));
        $environment = file_get_contents(base_path('.env.production.example'));

        $this->assertStringContainsString("'skip_ssl' => env('DB_DUMP_SKIP_SSL', false)", $database);
        $this->assertStringContainsString("'ssl_flag' => env('DB_DUMP_SSL_FLAG', 'skip-ssl')", $database);
        $this->assertStringContainsString('DB_DUMP_SKIP_SSL=false', $environment);
        $this->assertStringContainsString('DB_DUMP_SSL_FLAG=skip-ssl', $environment);
    }

    #[Test]
    public function deployment_builds_on_the_runner_and_releases_before_starting_services(): void
    {
        $workflow = file_get_contents(base_path('../.github/workflows/deploy.yml'));
        $script = file_get_contents(base_path('../ops/deploy.sh'));
        $compose = file_get_contents(base_path('../docker-compose.prod.yml'));

        $this->assertStringContainsString('image: nutriscope-backend', $compose);
        $this->assertStringContainsString('--tag nutriscope-backend:latest backend', $workflow);
        $this->assertStringContainsString('--tag nutriscope-frontend:latest frontend', $workflow);
        $this->assertStringContainsString('bash ops/deploy.sh "$RELEASE_SHA"', $workflow);
        $load = 'gzip -dc "$archive" | docker image load';
        $release = 'dc --profile release run --rm --pull never backend_release';
        $start = 'dc up -d --no-build --pull never backend backend_worker backend_scheduler frontend';
        foreach ([$load, $release, $start] as $command) {
            $this->assertStringContainsString($command, $script);
        }
        $this->assertLessThan(strpos($script, $release), strpos($script, $load));
        $this->assertLessThan(strpos($script, $start), strpos($script, $release));
        $this->assertStringNotContainsString('up -d --build', $script);
    }

    #[Test]
    public function frontend_is_type_checked_on_github_before_the_resource_limited_server_build(): void
    {
        $workflow = file_get_contents(base_path('../.github/workflows/deploy.yml'));
        $nextConfig = file_get_contents(base_path('../frontend/next.config.ts'));

        $this->assertIsString($workflow);
        $this->assertIsString($nextConfig);
        $this->assertStringContainsString('frontend_typecheck:', $workflow);
        $this->assertStringContainsString('needs: [frontend_typecheck, backend_tests]', $workflow);
        $this->assertStringContainsString('node-version: 20', $workflow);
        $this->assertStringContainsString('working-directory: frontend', $workflow);
        $this->assertStringContainsString('npm ci --no-audit --no-fund', $workflow);
        $this->assertStringContainsString('npx tsc --noEmit', $workflow);
        $this->assertStringContainsString('ignoreBuildErrors: true', $nextConfig);
    }

    #[Test]
    public function production_deployments_are_serialized_without_cancelling_the_active_release(): void
    {
        $workflow = file_get_contents(base_path('../.github/workflows/deploy.yml'));

        $this->assertIsString($workflow);
        $this->assertMatchesRegularExpression(
            '/deploy:\s+needs: \[frontend_typecheck, backend_tests\]\s+runs-on: ubuntu-latest\s+concurrency:\s+group: nutriscope-production-deploy\s+cancel-in-progress: false/',
            $workflow,
        );
    }

    #[Test]
    public function frontend_image_build_retries_an_incomplete_npm_install(): void
    {
        $dockerfile = file_get_contents(base_path('../frontend/Dockerfile'));

        $this->assertIsString($dockerfile);
        $this->assertStringContainsString('while [ "$attempt" -le 2 ]', $dockerfile);
        $this->assertStringContainsString('npm ci --no-audit --no-fund', $dockerfile);
        $this->assertStringContainsString('test -x node_modules/.bin/next', $dockerfile);
    }

    #[Test]
    public function production_image_builds_use_the_standard_docker_bridge(): void
    {
        $compose = file_get_contents(base_path('../docker-compose.prod.yml'));

        $this->assertIsString($compose);
        $this->assertSame(2, substr_count($compose, 'build:'));
        $this->assertStringContainsString('context: ./backend', $compose);
        $this->assertStringContainsString('context: ./frontend', $compose);
        $this->assertStringNotContainsString('network: host', $compose);
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
    public function deployment_pins_the_source_revision_and_keeps_the_serving_images_for_rollback(): void
    {
        $workflow = file_get_contents(base_path('../.github/workflows/deploy.yml'));
        $script = file_get_contents(base_path('../ops/deploy.sh'));

        $this->assertStringContainsString('git merge --ff-only "$RELEASE_SHA"', $workflow);
        $this->assertStringContainsString('git rev-parse origin/main', $workflow);
        $this->assertStringContainsString('envs: DB_ROOT_PASSWORD,RELEASE_SHA', $workflow);
        $this->assertStringContainsString('command_timeout: 30m', $workflow);
        $this->assertStringContainsString('"nutriscope-$service:rollback"', $script);
        $this->assertStringContainsString('docker container inspect', $script);
        $this->assertStringContainsString('curl --fail --silent --show-error', $script);
        $this->assertStringContainsString('--retry-all-errors', $script);
        $this->assertStringNotContainsString('git reset --hard', $workflow.$script);
        $this->assertStringNotContainsString('docker volume prune', $script);
        $this->assertStringContainsString('docker image prune --force --filter until=24h', $script);
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
        $requirements = file_get_contents(base_path('../docs/operations/platform-requirements.md'));

        $this->assertIsString($compose);
        $this->assertIsString($dockerignore);
        $this->assertIsString($env);
        $this->assertIsString($entrypoint);
        $this->assertIsString($requirements);
        $this->assertStringContainsString('nutriscope_private_uploads:/var/www/html/storage/app/private-uploads', $compose);
        $this->assertStringContainsString('nutriscope_private_uploads:', $compose);
        $this->assertStringContainsString('/storage/app/*', $dockerignore);
        $this->assertStringContainsString('PRIVATE_UPLOADS_DRIVER=local', $env);
        $this->assertStringContainsString('chown -R www-data:www-data "$private_uploads_path"', $entrypoint);
        $this->assertStringContainsString('report_cache_path=/var/www/html/storage/app/report-cache', $entrypoint);
        $this->assertStringContainsString('mkdir -p "$report_cache_path"', $entrypoint);
        $this->assertStringContainsString('chown -R www-data:www-data "$report_cache_path"', $entrypoint);
        $this->assertStringContainsString('exec gosu www-data "$@"', $entrypoint);
        $this->assertStringContainsString('default-mysql-client git curl zip unzip gosu', file_get_contents(base_path('Dockerfile')));
        $this->assertStringContainsString('Persistent named volume', $requirements);
    }

    #[Test]
    public function production_recovery_uses_shared_maintenance_and_stays_explicitly_disabled_by_default(): void
    {
        $compose = file_get_contents(base_path('../docker-compose.prod.yml'));
        $env = file_get_contents(base_path('.env.production.example'));

        $this->assertIsString($compose);
        $this->assertIsString($env);
        $this->assertStringContainsString('APP_MAINTENANCE_DRIVER=cache', $env);
        $this->assertStringContainsString('APP_MAINTENANCE_STORE=redis', $env);
        $this->assertStringContainsString('BACKUP_RESTORE_ENABLED=false', $env);
        $this->assertStringContainsString('--timeout=1200', $compose);
        $this->assertStringContainsString('REDIS_QUEUE_RETRY_AFTER=1260', $env);
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
    public function upload_request_limits_support_the_largest_valid_batch(): void
    {
        $php = file_get_contents(base_path('Dockerfile'));
        $nginx = file_get_contents(base_path('../nginx/default.conf'));
        $mobileNginx = file_get_contents(base_path('../nginx/mobile-api.locations.conf'));

        $this->assertIsString($php);
        $this->assertIsString($nginx);
        $this->assertIsString($mobileNginx);
        $this->assertStringContainsString("echo 'post_max_size=80M'", $php);
        $this->assertStringContainsString('client_max_body_size 80M;', $nginx);
        $this->assertStringContainsString('client_max_body_size 80M;', $mobileNginx);
    }

    #[Test]
    public function current_operations_guides_do_not_depend_on_the_legacy_deployment_runbook(): void
    {
        $requirements = file_get_contents(base_path('../docs/operations/platform-requirements.md'));
        $handoff = file_get_contents(base_path('../docs/operations/phase-2-platform-handoff.md'));

        $this->assertIsString($requirements);
        $this->assertIsString($handoff);
        $this->assertStringContainsString('release', $requirements);
        $this->assertStringContainsString('turnover.md', $handoff);
        $this->assertStringContainsString('private files', strtolower($requirements));
        $this->assertStringContainsString('README.md', $handoff);
        $this->assertStringNotContainsString('deployment.md', $requirements.$handoff);
    }
}
