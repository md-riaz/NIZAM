<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'organization_id',
        'schedule_id',
        'holiday_calendar_id',
        'role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }

    public function holidayCalendar(): BelongsTo
    {
        return $this->belongsTo(HolidayCalendar::class);
    }

    public function effectiveSchedule(): ?Schedule
    {
        return $this->schedule ?: $this->organization?->defaultSchedule;
    }

    public function effectiveHolidayCalendar(): ?HolidayCalendar
    {
        return $this->holidayCalendar ?: $this->effectiveSchedule()?->holidayCalendar ?: $this->organization?->defaultHolidayCalendar;
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }

    public function extensions(): HasMany
    {
        return $this->hasMany(Extension::class);
    }

    public function primaryExtension(): HasOne
    {
        return $this->hasOne(Extension::class)->where('is_primary', true);
    }

    public function deviceProfiles(): HasMany
    {
        return $this->hasMany(DeviceProfile::class);
    }

    public function isSuperadmin(): bool
    {
        return $this->role === 'superadmin';
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isAgent(): bool
    {
        return $this->role === 'agent';
    }

    /**
     * Check if the user has a specific permission by slug.
     * Admins always have all permissions.
     * If no permissions have been assigned to the user yet, allow all (default-open).
     * Once any permission is explicitly granted, only those permissions are allowed.
     */
    public function hasPermission(string $slug): bool
    {
        if ($this->isSuperadmin() || $this->isAdmin()) {
            return true;
        }

        // If no permissions have been assigned to this user, default to allow
        if ($this->permissions()->count() === 0) {
            return true;
        }

        return $this->permissions()->where('slug', $slug)->exists();
    }

    /**
     * Grant one or more permissions to the user.
     *
     * @param  array<string>  $slugs
     */
    public function grantPermissions(array $slugs): void
    {
        $ids = Permission::whereIn('slug', $slugs)->pluck('id');
        $this->permissions()->syncWithoutDetaching($ids);
    }

    /**
     * Revoke one or more permissions from the user.
     *
     * @param  array<string>  $slugs
     */
    public function revokePermissions(array $slugs): void
    {
        $ids = Permission::whereIn('slug', $slugs)->pluck('id');
        $this->permissions()->detach($ids);
    }
}
