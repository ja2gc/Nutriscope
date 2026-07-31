<?php

namespace Tests\Feature\Backup;

use App\Enums\BackupSource;
use App\Enums\BackupState;
use App\Models\BackupRun;
use App\Models\RecoveryRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BackupModelTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function backup_runs_use_public_ids_cast_states_and_track_requesters(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);
        $backup = BackupRun::factory()->create([
            'requested_by' => $admin->id,
            'state' => BackupState::Completed,
            'source' => BackupSource::Manual,
            'verified_at' => now(),
        ]);

        $this->assertNotEmpty($backup->uuid);
        $this->assertSame('uuid', $backup->getRouteKeyName());
        $this->assertSame(BackupState::Completed, $backup->state);
        $this->assertSame(BackupSource::Manual, $backup->source);
        $this->assertTrue($backup->requestedBy->is($admin));
    }

    #[Test]
    public function a_pending_recovery_request_protects_its_backup(): void
    {
        $backup = BackupRun::factory()->completed()->create();
        RecoveryRequest::factory()->for($backup)->create(['state' => 'requested']);

        $this->assertTrue($backup->isProtectedFromDeletion());
        $this->assertCount(1, $backup->recoveryRequests);
    }
}
