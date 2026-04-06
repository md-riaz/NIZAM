<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SipProfileSetting extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'sip_profile_id',
        'name',
        'value',
        'description',
        'is_enabled',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
        ];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(SipProfile::class, 'sip_profile_id');
    }

    protected static function booted(): void
    {
        $compilerHook = function () {
            app(\App\Services\SipProfileCompiler::class)->compileAllToDisk();
            try {
                app(\App\Services\EslConnectionManager::class)->bgapi('reloadxml');
            } catch (\Exception $e) {
                // Ignore if ESL is down
            }
        };

        static::saved($compilerHook);
        static::deleted($compilerHook);
    }
}
