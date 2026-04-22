<?php

namespace App\Services;

use App\Models\Extension;
use App\Models\Organization;
use Illuminate\Support\Collection;

class DirectoryService
{
    /**
     * Search active organization extensions for the company directory.
     *
     * @return Collection<int, Extension>
     */
    public function search(Organization $organization, ?string $search = null, int $limit = 50): Collection
    {
        $query = Extension::query()
            ->where('organization_id', $organization->id)
            ->where('is_active', true)
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->orderBy('extension');

        $search = trim((string) $search);

        if ($search !== '') {
            $query->where(function ($query) use ($search) {
                $query->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('extension', 'like', "%{$search}%");
            });
        }

        return $query->limit($limit)->get();
    }
}
