<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        'tenant_id',
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

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function extension(): BelongsTo
    {
        return $this->belongsTo(Extension::class);
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
