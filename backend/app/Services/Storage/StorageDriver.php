<?php

namespace App\Services\Storage;

interface StorageDriver
{
    /**
     * Check whether a stored object exists.
     */
    public function exists(string $path): bool;

    /**
     * Ensure the parent directory structure exists for a relative path.
     */
    public function ensureDirectory(string $path): void;

    /**
     * Move a file into managed storage and return archive metadata.
     *
     * @return array{path:string,size:int|null,mime_type:string|null,last_modified:int|null}
     */
    public function archive(string $sourcePath, string $destinationPath): array;

    /**
     * Delete a stored object when present.
     */
    public function delete(string $path): bool;

    /**
     * Resolve a storage path to an absolute local path when available.
     */
    public function absolutePath(string $path): string;
}
