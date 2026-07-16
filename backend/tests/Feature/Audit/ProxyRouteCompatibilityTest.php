<?php

namespace Tests\Feature\Audit;

use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ProxyRouteCompatibilityTest extends TestCase
{
    public function test_every_laravel_proxy_target_matches_a_backend_route(): void
    {
        $routeList = new Process(
            [PHP_BINARY, base_path('artisan'), 'route:list', '--json'],
            base_path(),
            ['APP_ENV' => 'local'],
        );
        $routeList->mustRun();

        $backendRoutes = collect(json_decode($routeList->getOutput(), true, flags: JSON_THROW_ON_ERROR))
            ->flatMap(fn (array $route): array => collect(explode('|', $route['method']))
                ->mapWithKeys(fn (string $method): array => [
                    $method.' '.$this->canonicalPath(preg_replace('/^api\//', '/', $route['uri'])) => true,
                ])->all());

        $mismatches = [];
        foreach (File::allFiles(base_path('../frontend/app/api')) as $file) {
            if ($file->getFilename() !== 'route.ts') {
                continue;
            }

            $source = File::get($file->getPathname());
            preg_match_all(
                '/export\s+async\s+function\s+(GET|POST|PUT|PATCH|DELETE)\b(.*?)(?=export\s+async\s+function|\z)/s',
                $source,
                $handlers,
                PREG_SET_ORDER,
            );

            foreach ($handlers as $handler) {
                preg_match_all('/\bproxy\(\s*(["`])(.+?)\1/s', $handler[2], $targets, PREG_SET_ORDER);
                foreach ($targets as $target) {
                    $path = preg_replace('/\$\{query\s*\?.*$/s', '', $target[2]);
                    $key = $handler[1].' '.$this->canonicalPath((string) $path);
                    if (! $backendRoutes->has($key)) {
                        $mismatches[] = $file->getRelativePathname().': '.$key;
                    }
                }
            }
        }

        $this->assertSame([], $mismatches, "Next.js laravelProxy targets without matching Laravel routes:\n".implode("\n", $mismatches));
    }

    private function canonicalPath(string $path): string
    {
        $path = preg_replace('/\$\{encodeURIComponent\([A-Za-z_][A-Za-z0-9_]*\)\}/', '{}', $path);
        $path = preg_replace('/\$\{[A-Za-z_][A-Za-z0-9_]*\}/', '{}', $path);
        $path = preg_replace('/\{[^}]+\}/', '{}', (string) $path);

        return rtrim((string) $path, '/');
    }
}
