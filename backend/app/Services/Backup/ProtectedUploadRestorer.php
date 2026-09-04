<?php

namespace App\Services\Backup;

use App\Models\BackupRun;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class ProtectedUploadRestorer
{
    private const STAGING = '.recovery-staging';

    private const ROLLBACK = '.recovery-rollback';

    private const STATE = '.recovery-state';

    public function available(): bool
    {
        $disk = (string) config('filesystems.private_uploads');

        return config("filesystems.disks.{$disk}.driver") === 'local';
    }

    public function stage(BackupRun $backup, string $candidateConnection, string $token): void
    {
        $this->assertToken($token);
        if (! $this->available() || $backup->manifest === null) {
            throw new RuntimeException('Local protected-upload recovery is unavailable.');
        }

        $private = Storage::disk(config('filesystems.private_uploads'));
        $protected = Storage::disk($backup->manifest->storage_disk);
        $prefix = $this->prefix(self::STAGING, $token);
        $private->deleteDirectory($prefix);
        $candidateObjects = DB::connection($candidateConnection)
            ->table('stored_objects')
            ->whereIn('uuid', $backup->manifest->objects->pluck('stored_object_uuid'))
            ->get(['uuid', 'object_key'])
            ->keyBy('uuid');

        if ($candidateObjects->count() !== $backup->manifest->object_count) {
            throw new RuntimeException('Recovery upload manifest does not match the candidate database.');
        }

        try {
            foreach ($backup->manifest->objects as $object) {
                $target = (string) $candidateObjects->get($object->stored_object_uuid)?->object_key;
                $this->assertObjectKey($target);
                $stream = $protected->readStream($object->protected_key);
                if (! is_resource($stream)) {
                    throw new RuntimeException('Protected upload could not be read.');
                }
                try {
                    $staged = $prefix.'/'.$target;
                    $private->writeStream($staged, $stream, ['visibility' => 'private']);
                } finally {
                    fclose($stream);
                }
                if ($private->size($staged) !== $object->bytes
                    || hash('sha256', $private->get($staged)) !== $object->sha256) {
                    throw new RuntimeException('Staged protected upload failed verification.');
                }
            }
        } catch (Throwable $exception) {
            $private->deleteDirectory($prefix);
            throw $exception;
        }
    }

    public function activate(string $token): void
    {
        $this->assertToken($token);
        $disk = Storage::disk(config('filesystems.private_uploads'));
        $stagePrefix = $this->prefix(self::STAGING, $token);
        $rollbackPrefix = $this->prefix(self::ROLLBACK, $token);
        $stateKey = $this->prefix(self::STATE, $token).'.json';
        $original = $this->activeFiles();
        $targets = collect($disk->allFiles($stagePrefix))
            ->map(fn (string $path): string => substr($path, strlen($stagePrefix) + 1))
            ->values()
            ->all();
        if (! $disk->put($stateKey, json_encode([
            'original' => $original,
            'targets' => $targets,
        ], JSON_THROW_ON_ERROR), ['visibility' => 'private'])) {
            throw new RuntimeException('Protected upload recovery journal could not be stored.');
        }

        try {
            foreach ($original as $path) {
                if (! $disk->move($path, $rollbackPrefix.'/'.$path)) {
                    throw new RuntimeException('Current protected upload could not be staged for rollback.');
                }
            }
            foreach ($targets as $path) {
                if (! $disk->move($stagePrefix.'/'.$path, $path)) {
                    throw new RuntimeException('Recovered protected upload could not be activated.');
                }
            }
        } catch (Throwable $exception) {
            $this->rollback($token);
            throw $exception;
        }
    }

    public function rollback(string $token): void
    {
        $this->assertToken($token);
        $disk = Storage::disk(config('filesystems.private_uploads'));
        $stateKey = $this->prefix(self::STATE, $token).'.json';
        if (! $disk->exists($stateKey)) {
            $this->finalize($token);

            return;
        }
        $state = json_decode($disk->get($stateKey), true, flags: JSON_THROW_ON_ERROR);
        $original = collect($state['original'] ?? []);
        $targets = collect($state['targets'] ?? []);
        $rollbackPrefix = $this->prefix(self::ROLLBACK, $token);

        foreach ($targets->diff($original) as $path) {
            $disk->delete($path);
        }
        foreach ($original as $path) {
            $rollback = $rollbackPrefix.'/'.$path;
            if ($disk->exists($rollback)) {
                $disk->delete($path);
                if (! $disk->move($rollback, $path)) {
                    throw new RuntimeException('Protected upload rollback failed.');
                }
            }
        }
        $this->finalize($token);
    }

    public function finalize(string $token): void
    {
        $this->assertToken($token);
        $disk = Storage::disk(config('filesystems.private_uploads'));
        $disk->deleteDirectory($this->prefix(self::STAGING, $token));
        $disk->deleteDirectory($this->prefix(self::ROLLBACK, $token));
        $disk->delete($this->prefix(self::STATE, $token).'.json');
    }

    /** @return list<string> */
    private function activeFiles(): array
    {
        return collect(Storage::disk(config('filesystems.private_uploads'))->allFiles())
            ->reject(fn (string $path): bool => str_starts_with($path, '.recovery-'))
            ->values()
            ->all();
    }

    private function prefix(string $directory, string $token): string
    {
        return $directory.'/'.$token;
    }

    private function assertToken(string $token): void
    {
        if (preg_match('/^[a-f0-9]{32}$/D', $token) !== 1) {
            throw new RuntimeException('Recovery file token is invalid.');
        }
    }

    private function assertObjectKey(string $path): void
    {
        if ($path === '' || str_starts_with($path, '/') || str_contains($path, '..')
            || preg_match('#^[a-z0-9_-]+/[a-f0-9-]+\.[a-z0-9]+$#D', $path) !== 1) {
            throw new RuntimeException('Recovery upload path is invalid.');
        }
    }
}
