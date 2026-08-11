<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Backfill role baseline permissions for users who hold no explicit grants.
 *
 * User::hasPermission() used to be default-open: a user with zero permission
 * rows was allowed every permission. It is now deny-by-default, which would
 * otherwise leave every pre-existing agent with no access at all. This grants
 * such users their role baseline so the change is not silently destructive.
 *
 * Users who already hold explicit grants are left untouched — their permission
 * set was already authoritative under the old behavior.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (User::ROLE_BASELINE_PERMISSIONS as $role => $slugs) {
            $permissionIds = DB::table('permissions')
                ->whereIn('slug', $slugs)
                ->pluck('id');

            if ($permissionIds->isEmpty()) {
                continue;
            }

            $userIds = DB::table('users')
                ->where('role', $role)
                ->whereNotExists(function ($query) {
                    $query->select(DB::raw(1))
                        ->from('permission_user')
                        ->whereColumn('permission_user.user_id', 'users.id');
                })
                ->pluck('id');

            foreach ($userIds as $userId) {
                foreach ($permissionIds as $permissionId) {
                    DB::table('permission_user')->insertOrIgnore([
                        'user_id' => $userId,
                        'permission_id' => $permissionId,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        // Not reversible: the backfilled grants are indistinguishable from
        // grants an administrator made deliberately, so removing them could
        // revoke intended access. Left in place on rollback.
    }
};
