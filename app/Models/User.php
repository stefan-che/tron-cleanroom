<?php

namespace App\Models;

use Orchid\Filters\Types\Like;
use Orchid\Filters\Types\Where;
use Orchid\Filters\Types\WhereDateStartEnd;
use Orchid\Platform\Models\User as Authenticatable;
/**
 * @property bool $is_two_factor_enabled
 * @property string $two_factor_secret
 * @property string $last_login_ip_address
 * @property string $allow_ips
 * @property string $telegram_id
 */
class User extends Authenticatable
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_two_factor_enabled',
        'two_factor_secret',
        'last_login_ip_address',
        'allow_ips',
        'telegram_id',
    ];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
        'permissions',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'permissions'          => 'array',
        'email_verified_at'    => 'datetime',
    ];

    /**
     * The attributes for which you can use filters in url.
     *
     * @var array
     */
    protected $allowedFilters = [
           'id'         => Where::class,
           'name'       => Like::class,
           'email'      => Like::class,
           'updated_at' => WhereDateStartEnd::class,
           'created_at' => WhereDateStartEnd::class,
    ];

    /**
     * The attributes for which can use sort in url.
     *
     * @var array
     */
    protected $allowedSorts = [
        'id',
        'name',
        'email',
        'updated_at',
        'created_at',
    ];

    public function isRoleAdmin(): bool
    {
        return $this->inRole(Role::ROLE_NAME_ADMIN); //  && count($this->getRoles()) === 1
    }

    public function isRoleUser(): bool
    {
        return $this->inRole(Role::ROLE_NAME_USER); //  && count($this->getRoles()) === 1
    }

    public function isRoleManager(): bool
    {
        return $this->inRole(Role::ROLE_NAME_MANAGER); //  && count($this->getRoles()) === 1
    }

    public function isPermSetWallet(): bool
    {
        return $this->hasAccess(Role::PERMISSION_OPERATIONS_SET_WALLET);
    }

    public function isPermTesting(): bool
    {
        return $this->hasAccess(Role::PERMISSION_TESTING);
    }

    public function isPermSetCustomWallet(): bool
    {
        return $this->hasAccess(Role::PERMISSION_OPERATIONS_SET_CUSTOM_WALLET);
    }
}
