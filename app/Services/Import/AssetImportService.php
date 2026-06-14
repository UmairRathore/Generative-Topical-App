<?php

namespace App\Services\Import;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AssetImportService
{
    public const PUBLIC_ROOT = 'cambpast-assets';

    /**
     * Copy a single extractor-produced asset to the public disk.
     *
     * @param  string  $sourceAbsolutePath  Absolute path on local filesystem.
     * @param  string  $paperStem           e.g. "9702_m24_qp_12".
     * @return string|null                  Disk-relative public path, or null if source missing.
     */
    public function copyPublicAsset(string $sourceAbsolutePath, string $paperStem): ?string
    {
        if (! is_file($sourceAbsolutePath)) {
            return null;
        }

        $filename = basename($sourceAbsolutePath);
        $filename = Str::of($filename)->replace(['\\', '/'], '_')->__toString();
        $relative = self::PUBLIC_ROOT.'/'.$paperStem.'/'.$filename;

        $disk = Storage::disk('public');

        if (! $disk->exists($relative) || $disk->size($relative) !== filesize($sourceAbsolutePath)) {
            $disk->put($relative, (string) file_get_contents($sourceAbsolutePath));
        }

        return $relative;
    }

    /**
     * Resolve an extractor-relative asset reference to an absolute filesystem path.
     *
     * Handles:
     *  - absolute paths (returned as-is)
     *  - paths relative to the paper folder
     *  - paths relative to storage/output
     */
    public function resolveSourcePath(string $reference, string $paperFolderAbsolute, string $outputRootAbsolute): ?string
    {
        if ($reference === '') {
            return null;
        }

        $candidates = [];

        if ($this->isAbsolute($reference)) {
            $candidates[] = $reference;
        } else {
            $candidates[] = rtrim($paperFolderAbsolute, "/\\").DIRECTORY_SEPARATOR.ltrim($reference, "/\\");
            $candidates[] = rtrim($outputRootAbsolute, "/\\").DIRECTORY_SEPARATOR.ltrim($reference, "/\\");
        }

        foreach ($candidates as $candidate) {
            $normalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $candidate);
            if (is_file($normalized)) {
                return $normalized;
            }
        }

        return null;
    }

    private function isAbsolute(string $path): bool
    {
        return preg_match('#^([a-zA-Z]:[\\\\/]|/)#', $path) === 1;
    }
}
