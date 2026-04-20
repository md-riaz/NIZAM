<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Gateway extends Model
{
    use Auditable, HasFactory, HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'name',
        'vendor',
        'host',
        'port',
        'username',
        'password',
        'realm',
        'transport',
        'register',
        'proxy',
        'register_proxy',
        'from_domain',
        'extension',
        'inbound_codecs',
        'outbound_codecs',
        'preferred_codecs',
        'dtmf_mode',
        'srtp_mode',
        'allow_transcoding',
        'expire_seconds',
        'retry_seconds',
        'caller_id_in_from',
        'profile',
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
            'port' => 'integer',
            'register' => 'boolean',
            'inbound_codecs' => 'array',
            'outbound_codecs' => 'array',
            'preferred_codecs' => 'array',
            'allow_transcoding' => 'boolean',
            'expire_seconds' => 'integer',
            'retry_seconds' => 'integer',
            'caller_id_in_from' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function dids(): HasMany
    {
        return $this->hasMany(Did::class);
    }
}
