<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
        'tenant_id',
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

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Check if the user has a specific permission by slug.
     * Admins always have all permissions.
     * If no permissions have been assigned to the user yet, allow all (default-open).
     * Once any permission is explicitly granted, only those permissions are allowed.
     */
    public function hasPermission(string $slug): bool
    {
        if ($this->isAdmin()) {
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
