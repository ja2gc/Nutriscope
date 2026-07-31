<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PortableRuntimeContractTest extends TestCase
{
    #[Test]
    public function production_runtime_separates_web_worker_scheduler_and_release_tasks(): void
    {
        $entrypoint = file_get_contents(base_path('docker-entrypoint.sh'));
        $release = file_get_contents(base_path('docker-release.sh'));
        $compose = file_get_contents(base_path('../docker-compose.prod.yml'));
        $railway = file_get_contents(base_path('railway.toml'));

        $this->assertIsString($entrypoint);
        $this->assertIsString($release);
        $this->assertIsString($compose);
        $this->assertIsString($railway);

        $this->assertStringNotContainsString('migrate --force', $entrypoint);
        $this->assertStringContainsString('migrate --force', $release);
        $this->assertStringContainsString('backend_worker:', $compose);
        $this->assertStringContainsString('queue:work redis --queue=backups,default', $compose);
        $this->assertStringContainsString('backend_scheduler:', $compose);
        $this->assertStringContainsString('schedule:work', $compose);
        $this->assertStringNotContainsString('migrate --force', $railway);
    }

    #[Test]
    public function backend_image_contains_mysql_dump_client_and_release_script(): void
    {
        $dockerfile = file_get_contents(base_path('Dockerfile'));
        $attributes = file_get_contents(base_path('../.gitattributes'));

        $this->assertIsString($dockerfile);
        $this->assertIsString($attributes);
        $this->assertStringContainsString('default-mysql-client', $dockerfile);
        $this->assertStringContainsString('docker-release.sh', $dockerfile);
        $this->assertStringContainsString('*.sh text eol=lf', $attributes);
    }
}
