<?php

namespace App\Http\Requests;

use App\Models\Organization;
use App\Models\SystemSetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateOrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $domainPrefix = $this->input('domain_prefix');

        if (! is_string($domainPrefix)) {
            return;
        }

        $this->merge([
            'domain_prefix' => $this->normalizePrefix($domainPrefix),
            'domain' => $this->composeDomain($domainPrefix),
        ]);
    }

    public function rules(): array
    {
        $organizationId = $this->route('organization')->id;

        return [
            'name' => 'required|string|max:255',
            'domain_prefix' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:[a-z0-9-]*[a-z0-9])?$/'],
            'domain' => 'required|string|unique:organizations,domain,'.$organizationId,
            'max_extensions' => 'integer|min:0',
            'max_concurrent_calls' => 'integer|min:0',
            'max_dids' => 'integer|min:0',
            'max_teams' => 'integer|min:0',
            'is_active' => 'boolean',
            'status' => ['string', Rule::in(Organization::VALID_STATUSES)],
            'recording_policy' => ['string', Rule::in(['inherit', 'off', 'all', 'incoming', 'outgoing'])],
            'recording_retention_days' => 'nullable|integer|min:0',
        ];
    }

    public function validated($key = null, $default = null): array
    {
        $validated = parent::validated();

        unset($validated['domain_prefix']);

        return $validated;
    }

    private function composeDomain(string $prefix): string
    {
        $normalizedPrefix = $this->normalizePrefix($prefix);
        $suffix = $this->suffix();

        return $suffix === '' ? $normalizedPrefix : $normalizedPrefix.'.'.$suffix;
    }

    private function normalizePrefix(string $prefix): string
    {
        return Str::of($prefix)
            ->trim()
            ->lower()
            ->replaceMatches('/[^a-z0-9-]/', '')
            ->trim('-')
            ->value();
    }

    private function suffix(): string
    {
        return Str::of(SystemSetting::platformString(SystemSetting::ORGANIZATION_DOMAIN_SUFFIX, ''))
            ->trim()
            ->lower()
            ->replaceMatches('/^\.+/', '')
            ->replaceMatches('/\.+$/', '')
            ->value();
    }
}
