<?php

namespace Tests\Unit\Backup;

use App\Data\BackupArchiveResult;
use App\Exceptions\BackupVerificationFailed;
use App\Services\Backup\BackupVerifier;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BackupVerifierTest extends TestCase
{
    #[Test]
    public function it_verifies_a_non_empty_encrypted_object(): void
    {
        Storage::fake('backups');
        Storage::disk('backups')->put('database.zip', 'encrypted-content');

        $result = app(BackupVerifier::class)->verify(
            new BackupArchiveResult('database.zip', 0, null, true),
        );

        $this->assertSame(17, $result->bytes);
        $this->assertTrue($result->encrypted);
    }

    #[Test]
    public function it_rejects_missing_empty_or_unencrypted_objects(): void
    {
        Storage::fake('backups');

        foreach ([
            new BackupArchiveResult('missing.zip', 1, null, true),
            new BackupArchiveResult('empty.zip', 0, null, true),
            new BackupArchiveResult('plain.zip', 1, null, false),
        ] as $result) {
            if ($result->objectKey !== 'missing.zip') {
                Storage::disk('backups')->put($result->objectKey, '');
            }

            try {
                app(BackupVerifier::class)->verify($result);
                $this->fail('Verification should have failed.');
            } catch (BackupVerificationFailed) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
