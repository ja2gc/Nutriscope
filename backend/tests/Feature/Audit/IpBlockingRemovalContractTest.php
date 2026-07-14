<?php

namespace Tests\Feature\Audit;

use App\Enums\AuditAction;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class IpBlockingRemovalContractTest extends TestCase
{
    public function test_ip_blocking_scaffolding_is_absent_while_account_and_rate_limit_events_remain(): void
    {
        $roots = [
            app_path(),
            config_path(),
            database_path('migrations'),
            base_path('routes'),
            base_path('../frontend/app'),
            base_path('../frontend/components'),
            base_path('../frontend/hooks'),
            base_path('../frontend/lib'),
            base_path('../frontend/services'),
            base_path('../frontend/types'),
        ];
        $files = collect($roots)
            ->flatMap(fn (string $root) => File::exists($root) ? File::allFiles($root) : [])
            ->push(new \SplFileInfo(base_path('.env.example')));
        $forbidden = '/AUDIT_SECURITY_BLOCKS_ENABLED|IpBlock(?:ed|ing)?|BlockedIp|blocked_ip|ip[-_ ]block(?:ed|ing)?|temporary_ip_block|security-blocks|SecurityBlock|RejectSecurityBlocks/i';
        $matches = $files
            ->filter(fn (\SplFileInfo $file): bool => preg_match($forbidden, $file->getPathname()) === 1
                || preg_match($forbidden, File::get($file->getPathname())) === 1)
            ->map(fn (\SplFileInfo $file): string => $file->getPathname())
            ->values()
            ->all();

        $this->assertSame([], $matches, 'IP-blocking scaffolding remains: '.implode(', ', $matches));
        $this->assertContains(AuditAction::AccountBlocked, AuditAction::cases());
        $this->assertContains(AuditAction::AccountUnblocked, AuditAction::cases());
        $this->assertContains(AuditAction::RateLimitExceeded, AuditAction::cases());
    }
}
