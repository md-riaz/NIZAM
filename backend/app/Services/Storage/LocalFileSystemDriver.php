<?php

namespace App\Services\Storage;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class LocalFileSystemDriver implements StorageDriver
{
    public function __construct(
        protected ?FilesystemAdapter $disk = null,
        protected string $diskName = 'recordings',
    ) {
        $this->disk ??= Storage::disk($this->diskName);
    }

    public function exists(string $path): bool
    {
        return $this->disk->exists($path);
    }

    public function ensureDirectory(string $path): void
    {
        $directory = trim(dirname(str_replace('\\', '/', $path)), '.');

        if ($directory === '' || $directory === '/') {
            return;
        }

        $this->disk->makeDirectory($directory);
    }

    public function archive(string $sourcePath, string $destinationPath): array
    {
        $this->ensureDirectory($destinationPath);

        if (! is_file($sourcePath)) {
            throw new RuntimeException("Recording source file not found: {$sourcePath}");
        }

        $stream = fopen($sourcePath, 'rb');

        if ($stream === false) {
            throw new RuntimeException("Unable to open recording source file: {$sourcePath}");
        }

        try {
            $written = $this->disk->put($destinationPath, $stream);
        } finally {
            fclose($stream);
        }

        if ($written === false) {
            throw new RuntimeException("Unable to archive recording to {$destinationPath}");
        }

        if (realpath($sourcePath) !== realpath($this->absolutePath($destinationPath)) && is_file($sourcePath)) {
            @unlink($sourcePath);
        }

        return [
            'path' => $destinationPath,
            'size' => $this->safeFileSize($destinationPath),
            'mime_type' => $this->safeMimeType($destinationPath),
            'last_modified' => $this->safeLastModified($destinationPath),
        ];
    }

    public function delete(string $path): bool
    {
        if ($path === '' || ! $this->exists($path)) {
            return false;
        }

        return $this->disk->delete($path);
    }

    public function absolutePath(string $path): string
    {
        return $this->disk->path($path);
    }

    protected function safeFileSize(string $path): ?int
    {
        try {
            return $this->disk->size($path);
        } catch (\Throwable) {
            return null;
        }
    }

    protected function safeMimeType(string $path): ?string
    {
        try {
            return $this->disk->mimeType($path);
        } catch (\Throwable) {
            return null;
        }
    }

    protected function safeLastModified(string $path): ?int
    {
        try {
            return $this->disk->lastModified($path);
        } catch (\Throwable) {
            return null;
        }
    }
}
