<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SipProfile extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'hostname',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function settings()
    {
        return $this->hasMany(SipProfileSetting::class, 'sip_profile_id');
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
