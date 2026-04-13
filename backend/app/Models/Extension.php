<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Extension extends Model
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
        'extension',
        'password',
        'directory_first_name',
        'directory_last_name',
        'effective_caller_id_name',
        'effective_caller_id_number',
        'outbound_caller_id_name',
        'outbound_caller_id_number',
        'voicemail_enabled',
        'follow_me_enabled',
        'follow_me_destination',
        'dnd_enabled',
        'voicemail_pin',
        'is_primary',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'voicemail_pin',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'encrypted',
            'voicemail_pin' => 'encrypted',
            'voicemail_enabled' => 'boolean',
            'follow_me_enabled' => 'boolean',
            'dnd_enabled' => 'boolean',
            'is_primary' => 'boolean',
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

    public function deviceProfiles(): HasMany
    {
        return $this->hasMany(DeviceProfile::class);
    }

    public function primaryDeviceProfile(): HasOne
    {
        return $this->hasOne(DeviceProfile::class)->where('is_active', true)->latestOfMany();
    }

    public function endpointBindings(): HasMany
    {
        return $this->hasMany(EndpointBinding::class);
    }

    public function deviceRegistrationSnapshots(): HasMany
    {
        return $this->hasMany(DeviceRegistrationSnapshot::class);
    }

    public function agent(): HasOne
    {
        return $this->hasOne(Agent::class);
    }
}
