<?php

namespace App\Services\Backup;

use App\Exceptions\BackupVerificationFailed;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class BackupArchiveInspector
{
    /** @return array{bytes:int,sha256:string,sql_entry:string} */
    public function inspect(string $disk, string $key, string $password): array
    {
        $bytes = Storage::disk($disk)->get($key);
        if ($bytes === '' || $password === '') {
            throw new BackupVerificationFailed('Backup verification failed.');
        }
        $path = tempnam(sys_get_temp_dir(), 'nutriscope-backup-');
        if (! is_string($path)) {
            throw new BackupVerificationFailed('Backup verification failed.');
        }
        file_put_contents($path, $bytes);
        $zip = new ZipArchive;
        $opened = false;
        try {
            $opened = $zip->open($path) === true;
            if (! $opened || ! $zip->setPassword($password)) {
                throw new BackupVerificationFailed('Backup verification failed.');
            }
            $sqlEntry = null;
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $name = $zip->getNameIndex($index);
                if (! is_string($name) || str_contains($name, '..') || str_starts_with($name, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $name)) {
                    throw new BackupVerificationFailed('Backup verification failed.');
                }
                if (str_ends_with(strtolower($name), '.sql')) {
                    if ($zip->getFromIndex($index) === false) {
                        throw new BackupVerificationFailed('Backup verification failed.');
                    }
                    $sqlEntry = $name;
                }
            }
            if ($sqlEntry === null) {
                throw new BackupVerificationFailed('Backup verification failed.');
            }

            return ['bytes' => strlen($bytes), 'sha256' => hash('sha256', $bytes), 'sql_entry' => $sqlEntry];
        } finally {
            if ($opened) {
                $zip->close();
            }
            @unlink($path);
        }
    }
}
