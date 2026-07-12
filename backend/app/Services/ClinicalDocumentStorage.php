<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class ClinicalDocumentStorage
{
    public function resolve(string $storedPath): string
    {
        $storedPath = trim($storedPath);
        abort_if($storedPath === '' || str_contains($storedPath, "\0"), 404, 'File not found.');

        if ($this->isAbsolute($storedPath)) {
            $candidate = realpath($storedPath);
        } else {
            $segments = preg_split('~[\\\\/]+~', $storedPath) ?: [];
            abort_if(in_array('..', $segments, true), 404, 'File not found.');
            $candidate = realpath(Storage::path($storedPath));
        }

        abort_unless(is_string($candidate) && is_file($candidate) && $this->insideApprovedRoot($candidate), 404, 'File not found.');

        return $candidate;
    }

    /** @return array{original:string, quarantine:string} */
    public function quarantine(string $storedPath): array
    {
        $original = $this->resolve($storedPath);
        $root = $this->approvedRootFor($original);
        abort_unless($root !== null, 404, 'File not found.');
        $directory = $root.DIRECTORY_SEPARATOR.'.clinical-quarantine';
        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException('Clinical file quarantine is unavailable.');
        }

        $quarantine = $directory.DIRECTORY_SEPARATOR.Str::uuid()->toString();
        if (! rename($original, $quarantine)) {
            throw new RuntimeException('Clinical file could not be quarantined.');
        }

        return ['original' => $original, 'quarantine' => $quarantine];
    }

    /** @return array{original:string, quarantine:string}|null */
    public function quarantineIfPresent(string $storedPath): ?array
    {
        if ($this->missingButContained($storedPath)) {
            return null;
        }

        return $this->quarantine($storedPath);
    }

    public function restore(array $move): void
    {
        if (! isset($move['original'], $move['quarantine'])
            || ! is_string($move['original'])
            || ! is_string($move['quarantine'])) {
            throw new RuntimeException('Clinical file restore payload is invalid.');
        }

        $original = $this->restoreCandidate($move['original']);
        $quarantine = $this->restoreCandidate($move['quarantine']);
        $root = $this->approvedRootFor($original);
        if ($root === null
            || $this->inside($original, $root.DIRECTORY_SEPARATOR.'.clinical-quarantine')
            || ! $this->inside($quarantine, $root.DIRECTORY_SEPARATOR.'.clinical-quarantine')) {
            throw new RuntimeException('Clinical file restore paths are outside the approved boundary.');
        }

        $originalExists = file_exists($original) || is_link($original);
        $quarantineExists = file_exists($quarantine) || is_link($quarantine);

        if ($originalExists) {
            if (! is_file($original) || $quarantineExists) {
                throw new RuntimeException('Clinical file restore has conflicting filesystem state.');
            }

            return;
        }

        if (! $quarantineExists || ! is_file($quarantine)) {
            throw new RuntimeException('Clinical file restore source is missing.');
        }

        if (! rename($quarantine, $original)
            || ! is_file($original)
            || file_exists($quarantine)
            || is_link($quarantine)) {
            throw new RuntimeException('Clinical file quarantine compensation failed.');
        }
    }

    public function purgeQuarantine(string $path): void
    {
        $real = realpath($path);
        if ($real === false) {
            return;
        }

        $allowed = collect($this->approvedRoots())->contains(
            fn (string $root): bool => $this->inside($real, $root.DIRECTORY_SEPARATOR.'.clinical-quarantine'),
        );
        if (! $allowed || ! is_file($real) || ! unlink($real)) {
            throw new RuntimeException('Clinical file quarantine cleanup failed.');
        }
    }

    public function deleteIfPresent(string $storedPath): void
    {
        if ($this->missingButContained($storedPath)) {
            return;
        }

        $path = $this->resolve($storedPath);
        if (! unlink($path)) {
            throw new RuntimeException('Clinical upload cleanup failed.');
        }
    }

    private function insideApprovedRoot(string $path): bool
    {
        return $this->approvedRootFor($path) !== null;
    }

    private function approvedRootFor(string $path): ?string
    {
        foreach ($this->approvedRoots() as $root) {
            if ($this->inside($path, $root)) {
                return $root;
            }
        }

        return null;
    }

    /** @return array<int, string> */
    private function approvedRoots(): array
    {
        return collect([
            realpath(Storage::path('')),
            realpath(storage_path('app/private')),
            realpath(storage_path('app/public')),
        ])->filter(fn (mixed $root): bool => is_string($root))
            ->map(fn (string $root): string => $root.DIRECTORY_SEPARATOR.'documents'.DIRECTORY_SEPARATOR.'ncp')
            ->unique()
            ->values()
            ->all();
    }

    private function inside(string $path, string $root): bool
    {
        $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        $root = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $root);
        $path = DIRECTORY_SEPARATOR === '\\' ? strtolower($path) : $path;
        $root = DIRECTORY_SEPARATOR === '\\' ? strtolower($root) : $root;

        return $path === $root || str_starts_with($path, rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR);
    }

    private function restoreCandidate(string $path): string
    {
        $path = trim($path);
        $segments = preg_split('~[\\\\/]+~', $path) ?: [];
        if ($path === ''
            || str_contains($path, "\0")
            || ! $this->isAbsolute($path)
            || in_array('..', $segments, true)) {
            throw new RuntimeException('Clinical file restore path is malformed.');
        }

        $real = realpath($path);
        if (is_string($real)) {
            return $real;
        }

        $parent = realpath(dirname($path));
        if (! is_string($parent)) {
            throw new RuntimeException('Clinical file restore parent is unavailable.');
        }

        return $parent.DIRECTORY_SEPARATOR.basename($path);
    }

    private function isAbsolute(string $path): bool
    {
        return str_starts_with($path, '/')
            || str_starts_with($path, '\\\\')
            || preg_match('~^[a-z]:[\\\\/]~iD', $path) === 1;
    }

    private function missingButContained(string $storedPath): bool
    {
        $storedPath = trim($storedPath);
        abort_if($storedPath === '' || str_contains($storedPath, "\0"), 404, 'File not found.');
        $segments = preg_split('~[\\\\/]+~', $storedPath) ?: [];
        abort_if(in_array('..', $segments, true), 404, 'File not found.');
        if ($this->isAbsolute($storedPath)) {
            if (is_file($storedPath)) {
                return false;
            }

            $parent = realpath(dirname($storedPath));

            return is_string($parent)
                ? $this->insideApprovedRoot($parent)
                : $this->insideApprovedRoot($storedPath);
        }

        $absolute = Storage::path($storedPath);
        if (is_file($absolute)) {
            return false;
        }

        $parent = realpath(dirname($absolute));

        return is_string($parent)
            ? $this->insideApprovedRoot($parent)
            : $this->insideApprovedRoot($absolute);
    }
}
