<?php

namespace App\Auth;

use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\Access\Authorizable;

/** Read model for the existing users table; never use it for account writes. */
final class LegacyUser extends Model implements AuthenticatableContract, AuthorizableContract
{
    use Authenticatable, Authorizable;

    // FW-03/FW-04 own future runtime connection activation. No default fallback,
    // environment-derived path, PDO creation, migration, factory or seed here.
    protected $connection = 'chrono';

    protected $table = 'users';

    public $timestamps = false;

    protected $guarded = ['*'];

    protected $hidden = ['password', 'token'];

    // No remember_token column exists; the local session action never remembers.
    public function getRememberTokenName()
    {
        return null;
    }

    public function scopeVisibleTo(Builder $query, self $actor): void
    {
        $query->where(function (Builder $scope) use ($actor): void {
            if ($actor->role === 'admin') {
                return;
            }

            $scope->where('id', $actor->getKey());
            if ($actor->role === 'supervisor') {
                $scope->orWhere('supervisor_id', $actor->getKey());
            }
        });
    }
}
