<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

class DeviceProfile extends Model
{
    use Auditable, HasFactory, HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'user_id',
        'name',
        'vendor',
        'mac_address',
        'template',
        'extension_id',
        'is_active',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    protected function organizationId(): Attribute
    {
        return Attribute::make(
            get: fn ($value): ?string => $this->attributes['organization_id'] ?? $value,
            set: fn (?string $value): array => ['organization_id' => $value],
        );
    }


    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function extension(): BelongsTo
    {
        return $this->belongsTo(Extension::class);
    }

    public function ownedExtensions(): HasMany
    {
        return $this->hasMany(Extension::class, 'device_profile_id');
    }

    public function extensionUser(): HasOneThrough
    {
        return $this->hasOneThrough(
            User::class,
            Extension::class,
            'id',
            'id',
            'extension_id',
            'user_id'
        );
    }

    public function resolvedUser(): ?User
    {
        return $this->user ?? $this->extension?->user;
    }
}
