<?php

namespace App\Services\Backup;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class BackupReadiness
{
    /** @return array{storage:bool,encryption:bool,queue:bool,scheduler:bool,locks:bool,ready:bool} */
    public function check(): array
    {
        $checks = [
            'storage' => $this->storageReady(),
            'encryption' => filled(config('backup.backup.password'))
                && config('backup.backup.encryption') === 'aes256',
            'queue' => ! in_array(config('queue.default'), ['sync', 'null'], true),
            'scheduler' => $this->schedulerReady(),
            'locks' => $this->locksReady(),
        ];

        return [...$checks, 'ready' => ! in_array(false, $checks, true)];
    }

    private function storageReady(): bool
    {
        $key = '_readiness/'.Str::uuid().'.txt';

        try {
            $disk = Storage::disk(config('nutriscope-backups.disk'));
            $written = $disk->put($key, 'ready');
            $ready = $written && $disk->exists($key) && $disk->size($key) === 5;
            $disk->delete($key);

            return $ready;
        } catch (Throwable) {
            return false;
        }
    }

    private function schedulerReady(): bool
    {
        $heartbeat = Cache::get(config('nutriscope-backups.scheduler_heartbeat_key'));

        return is_string($heartbeat)
            && Carbon::parse($heartbeat)->greaterThanOrEqualTo(now()->subMinutes(20));
    }

    private function locksReady(): bool
    {
        if (! config('nutriscope-backups.multiple_instances')) {
            return true;
        }

        return in_array(config('cache.default'), ['database', 'redis', 'memcached', 'dynamodb'], true);
    }
}
