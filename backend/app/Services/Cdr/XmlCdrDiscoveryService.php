<?php

namespace App\Services\Cdr;

use App\Models\ProcessedCdrFile;
use Illuminate\Support\Facades\File;

class XmlCdrDiscoveryService
{
    public function __construct(
        protected ?string $directory = null,
    ) {
        $this->directory ??= config('telephony.xml_cdr.directory');
    }

    public function pendingFiles(): array
    {
        if (! $this->directory || ! File::isDirectory($this->directory)) {
            return [];
        }

        return collect(File::files($this->directory))
            ->filter(fn ($file) => str_ends_with(strtolower($file->getFilename()), '.xml'))
            ->sortBy(fn ($file) => $file->getFilename())
            ->reject(function ($file) {
                $path = $file->getPathname();
                $checksum = $this->checksumFor($path);

                return ProcessedCdrFile::query()
                    ->where('dedupe_key', ProcessedCdrFile::dedupeKeyFor($path, $checksum))
                    ->exists();
            })
            ->map(fn ($file) => $file->getPathname())
            ->values()
            ->all();
    }

    protected function checksumFor(string $path): ?string
    {
        $checksum = @hash_file('sha256', $path);

        return $checksum === false ? null : $checksum;
    }
}
