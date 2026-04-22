<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemSetting extends Model
{
    use HasFactory, HasUuids;

    public const ORGANIZATION_DOMAIN_SUFFIX = 'organization_domain_suffix';
    public const EXTENSION_RANGE_START = 'extension_range_start';
    public const EXTENSION_RANGE_END = 'extension_range_end';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'key',
        'value',
        'value_type',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public static function platformString(string $key, ?string $default = null): ?string
    {
        $setting = static::query()
            ->whereNull('organization_id')
            ->where('key', $key)
            ->first();

        if (! $setting) {
            return $default;
        }

        $value = $setting->value['value'] ?? null;

        return is_string($value) ? $value : $default;
    }

    public static function upsertPlatformString(string $key, string $value): self
    {
        return static::query()->updateOrCreate(
            [
                'organization_id' => null,
                'key' => $key,
            ],
            [
                'value' => ['value' => $value],
                'value_type' => 'string',
            ],
        );
    }

    public static function platformInteger(string $key, ?int $default = null): ?int
    {
        $setting = static::query()
            ->whereNull('organization_id')
            ->where('key', $key)
            ->first();

        if (! $setting) {
            return $default;
        }

        $value = $setting->value['value'] ?? null;

        return is_numeric($value) ? (int) $value : $default;
    }

    public static function upsertPlatformInteger(string $key, int $value): self
    {
        return static::query()->updateOrCreate(
            [
                'organization_id' => null,
                'key' => $key,
            ],
            [
                'value' => ['value' => $value],
                'value_type' => 'integer',
            ],
        );
    }
}
