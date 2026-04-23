<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
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
        'default_outbound_did_id',
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

    public function defaultOutboundDid(): BelongsTo
    {
        return $this->belongsTo(Did::class, 'default_outbound_did_id');
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

    public function directPhoneNumbers(): BelongsToMany
    {
        return $this->belongsToMany(Did::class, 'phone_number_user_access', 'user_id', 'did_id')->withTimestamps();
    }

    public function teams(): HasManyThrough
    {
        return $this->hasManyThrough(
            Team::class,
            TeamMember::class,
            'endpoint_id',
            'id',
            'id',
            'team_id'
        )->where('team_members.endpoint_type', 'extension')
            ->whereHas('members', fn ($query) => $query
                ->where('endpoint_type', 'extension')
                ->where('endpoint_id', $this->getKey())
            );
    }

    public function teamPhoneNumbers(): BelongsToMany
    {
        return $this->belongsToMany(Did::class, 'phone_number_team_access', 'team_id', 'did_id');
    }

    public function effectivePhoneNumbers(): Collection
    {
        $extensionIds = $this->extensions()->pluck('id')->filter();

        $teamIds = TeamMember::query()
            ->where('endpoint_type', 'extension')
            ->whereIn('endpoint_id', $extensionIds)
            ->pluck('team_id')
            ->unique()
            ->values();

        $direct = $this->directPhoneNumbers()->get();
        $teamGranted = $teamIds->isEmpty()
            ? collect()
            : Did::query()
                ->whereHas('teams', fn ($query) => $query->whereIn('teams.id', $teamIds))
                ->get();

        return $direct
            ->concat($teamGranted)
            ->unique(fn (Did $did) => (string) $did->id)
            ->values();
    }

    public function canUsePhoneNumber(?string $didId): bool
    {
        if (! $didId || ! Str::isUuid($didId)) {
            return false;
        }

        return $this->effectivePhoneNumbers()->contains(fn (Did $did) => (string) $did->id === $didId);
    }

    public function resolveOutboundDid(): ?Did
    {
        if ($this->default_outbound_did_id && $this->canUsePhoneNumber($this->default_outbound_did_id)) {
            return $this->effectivePhoneNumbers()->firstWhere('id', $this->default_outbound_did_id);
        }

        return $this->effectivePhoneNumbers()->first();
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
