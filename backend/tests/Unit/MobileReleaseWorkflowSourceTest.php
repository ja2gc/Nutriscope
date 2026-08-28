<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class MobileReleaseWorkflowSourceTest extends TestCase
{
    public function test_apk_publication_verifies_then_retries_small_chunks_before_atomic_install(): void
    {
        $workflow = file_get_contents(dirname(__DIR__, 3).'/.github/workflows/release-mobile.yml');

        $this->assertIsString($workflow);
        $this->assertStringContainsString('name: Download and verify signed APK', $workflow);
        $this->assertStringContainsString('--connect-timeout 30', $workflow);
        $this->assertStringContainsString('--max-time 900', $workflow);
        $this->assertStringContainsString('--retry 3', $workflow);
        $this->assertStringContainsString('--retry-all-errors', $workflow);
        $this->assertStringContainsString('-o release-upload/nutriscope-fss.apk -- "$APK_URL"', $workflow);
        $this->assertStringContainsString('split -b 8M -d -a 3', $workflow);
        $this->assertStringContainsString('for attempt in 1 2 3 4 5', $workflow);
        $this->assertStringContainsString('scp', $workflow);
        $this->assertStringContainsString('cat "$upload_dir"/nutriscope-fss.apk.part-* > "$upload_dir/nutriscope-fss.apk"', $workflow);
        $this->assertStringContainsString('echo "$SHA256  $upload_dir/nutriscope-fss.apk" | sha256sum -c -', $workflow);
        $this->assertStringContainsString('mv -f "$release_dir/.nutriscope-fss.apk.new" "$release_dir/nutriscope-fss.apk"', $workflow);
    }
}
