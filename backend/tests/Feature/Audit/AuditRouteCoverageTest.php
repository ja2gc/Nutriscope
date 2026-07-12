<?php

namespace Tests\Feature\Audit;

use App\Models\Concerns\AuditsChanges;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\ExpectationFailedException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class AuditRouteCoverageTest extends TestCase
{
    public function test_every_unsafe_route_has_an_audit_classification_and_reason(): void
    {
        $routeList = new Process(
            [PHP_BINARY, base_path('artisan'), 'route:list', '--json'],
            base_path(),
            ['APP_ENV' => 'local'],
        );
        $routeList->mustRun();
        $routes = json_decode($routeList->getOutput(), true, flags: JSON_THROW_ON_ERROR);

        $actualUnsafeRoutes = collect($routes)
            ->filter(fn (array $route) => collect(explode('|', $route['method']))
                ->contains(fn (string $method) => ! in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)))
            ->map(fn (array $route) => "{$route['method']} {$route['uri']}")
            ->sort()
            ->values()
            ->all();

        $coverage = config('audit.route_coverage');

        $this->assertIsArray($coverage);
        $this->assertNotEmpty($coverage);
        $this->assertSame(
            'Laravel\\Boost\\BoostServiceProvider@registerRoutes',
            $coverage['POST _boost/browser-logs']['source'],
        );

        $expectedUnsafeRoutes = array_keys($coverage);
        sort($expectedUnsafeRoutes);
        $this->assertSame($expectedUnsafeRoutes, $actualUnsafeRoutes, 'Unsafe route inventory changed; classify every added or removed route.');

        collect($coverage)->each(fn (array $policy, string $route) => $this->assertCoveragePolicy($route, $policy));
    }

    #[DataProvider('invalidPhasePolicies')]
    public function test_phase_policy_schema_rejects_invalid_owner_state_combinations(array $policy): void
    {
        $this->expectException(ExpectationFailedException::class);
        $this->assertCoveragePolicy('TEST api/example', $policy);
    }

    public static function invalidPhasePolicies(): array
    {
        $base = [
            'classification' => 'explicit_event',
            'source' => 'App\\Http\\Controllers\\SopController@store',
            'reason' => 'test',
        ];

        return [
            'unknown owner' => [[...$base, 'owner_task' => 99, 'implementation_state' => 'planned']],
            'future implemented early' => [[...$base, 'owner_task' => 7, 'implementation_state' => 'implemented']],
            'current still planned' => [[...$base, 'owner_task' => 6, 'implementation_state' => 'planned']],
        ];
    }

    private function assertCoveragePolicy(string $route, array $policy): void
    {
        $this->assertContains($policy['classification'], ['explicit_event', 'model_event', 'intentionally_not_audited'], $route);
        $this->assertIsInt($policy['owner_task'] ?? null, $route);
        $this->assertContains($policy['owner_task'], [4, 5, 6, 7, 8], $route);
        $this->assertContains($policy['implementation_state'] ?? null, ['planned', 'implemented'], $route);
        $this->assertMatchesRegularExpression('/^[A-Za-z_\\\\][A-Za-z0-9_\\\\]+(?:@\w+|::AuditsChanges)$/D', $policy['source'] ?? '', $route);

        if ($policy['owner_task'] <= 6) {
            $this->assertSame('implemented', $policy['implementation_state'], $route);
            [$class, $method] = str_contains($policy['source'], '@')
                ? explode('@', $policy['source'], 2)
                : [strstr($policy['source'], '::', true), 'AuditsChanges'];
            $this->assertTrue(class_exists($class), $route.' source class');

            if ($method === 'AuditsChanges') {
                $this->assertContains(AuditsChanges::class, class_uses_recursive($class), $route);
            } else {
                $this->assertTrue(method_exists($class, $method), $route.' source method');
            }
        } else {
            $this->assertSame('planned', $policy['implementation_state'], $route);
        }

        if ($policy['classification'] === 'intentionally_not_audited') {
            $this->assertNotSame('', trim($policy['reason'] ?? ''), $route);
        } else {
            $this->assertNotSame('', trim($policy['source'] ?? ''), $route);
        }
    }
}
