<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class MobileReleaseWorkflowSourceTest extends TestCase
{
    public function test_apk_publication_verifies_on_the_runner_then_uploads_atomically(): void
    {
        $workflow = file_get_contents(dirname(__DIR__, 3).'/.github/workflows/release-mobile.yml');

        $this->assertIsString($workflow);
        $this->assertStringContainsString('name: Download and verify signed APK', $workflow);
        $this->assertStringContainsString('--connect-timeout 30', $workflow);
        $this->assertStringContainsString('--max-time 900', $workflow);
        $this->assertStringContainsString('--retry 3', $workflow);
        $this->assertStringContainsString('--retry-all-errors', $workflow);
        $this->assertStringContainsString('-o release-upload/nutriscope-fss.apk -- "$APK_URL"', $workflow);
        $this->assertStringContainsString('uses: appleboy/scp-action@v1', $workflow);
        $this->assertStringContainsString('strip_components: 1', $workflow);
        $this->assertStringContainsString('echo "$SHA256  $upload_dir/nutriscope-fss.apk" | sha256sum -c -', $workflow);
        $this->assertStringContainsString('mv -f "$release_dir/.nutriscope-fss.apk.new" "$release_dir/nutriscope-fss.apk"', $workflow);
    }
}
