<?php

namespace App\Services\Organization;

use App\Models\Organization;
use App\Models\SystemSetting;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class OrganizationDomainSuggestionService
{
    /**
     * @return array{prefix: string, suffix: string, domain: string}
     */
    public function suggest(string $organizationName): array
    {
        $suffix = $this->suffix();
        $basePrefix = $this->basePrefix($organizationName);
        $prefix = $this->uniquePrefix($basePrefix, $suffix);

        return [
            'prefix' => $prefix,
            'suffix' => $suffix,
            'domain' => $suffix === '' ? $prefix : $prefix.'.'.$suffix,
        ];
    }

    public function suffix(): string
    {
        return SystemSetting::platformString(SystemSetting::ORGANIZATION_DOMAIN_SUFFIX, '');
    }

    private function basePrefix(string $organizationName): string
    {
        $words = collect(preg_split('/[^a-z0-9]+/i', Str::lower($organizationName)) ?: [])
            ->filter(fn (?string $word) => filled($word))
            ->values();

        if ($words->isEmpty()) {
            return 'orgn';
        }

        $prefix = $this->takeFirstCharacters($words);
        $position = 1;

        while (strlen($prefix) < 4 && $words->isNotEmpty()) {
            $expanded = '';

            foreach ($words as $word) {
                $expanded .= substr($word, 0, min(strlen($word), $position + 1));

                if (strlen($expanded) >= 4) {
                    break;
                }
            }

            if ($expanded === $prefix) {
                break;
            }

            $prefix = $expanded;
            $position++;
        }

        $normalized = preg_replace('/[^a-z0-9]/', '', $prefix) ?? '';

        if ($normalized === '') {
            return 'orgn';
        }

        return substr($normalized, 0, max(4, strlen($normalized)));
    }

    /**
     * @param  Collection<int, string>  $words
     */
    private function takeFirstCharacters(Collection $words): string
    {
        return $words
            ->map(fn (string $word) => substr($word, 0, 1))
            ->implode('');
    }

    private function uniquePrefix(string $prefix, string $suffix): string
    {
        $candidate = $prefix;
        $counter = 2;

        while ($this->domainExists($candidate, $suffix)) {
            $candidate = $prefix.$counter;
            $counter++;
        }

        return $candidate;
    }

    private function domainExists(string $prefix, string $suffix): bool
    {
        $domain = $suffix === '' ? $prefix : $prefix.'.'.$suffix;

        return Organization::query()->where('domain', $domain)->exists();
    }
}
