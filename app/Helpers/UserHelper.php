<?php

namespace App\Helpers;

use App\Models\User;

class UserHelper
{

    /**
     * @return \Illuminate\Contracts\Auth\Authenticatable|null|User
     */
    public static function get()
    {
        try {
            return auth()->user();
        } catch (\Exception $exception) {
            return null;
        }
    }

    /**
     * @return int|null
     */
    public static function getId(): ?int
    {
        return self::get() ? self::get()->id : null;
    }

    /**
     * @return bool
     */
    public static function isOne(): bool
    {
        return self::getId() === 1;
    }

    /**
     * @return bool
     */
    public static function isAdmin(): bool
    {
        return self::get() ? self::get()->isRoleAdmin() : false;
    }

    /**
     * @return bool
     */
    public static function isManager(): bool
    {
        return self::get() ? self::get()->isRoleManager() : false;
    }

    /**
     * @return bool
     */
    public static function isRoleUser(): bool
    {
        return self::get() ? self::get()->isRoleUser() : false;
    }


    /**
     * @return bool
     */
    public static function isPermSetWallet(): bool
    {
        return self::get() ? self::get()->isPermSetWallet() : false;
    }

    /**
     * @return bool
     */
    public static function isPermTesting(): bool
    {
        return self::get() ? self::get()->isPermTesting() : false;
    }

    /**
     * @return bool
     */
    public static function isPermSetCustomWallet(): bool
    {
        return self::get() ? self::get()->isPermSetCustomWallet() : false;
    }

    /**
     * @return bool
     */
    public static function isTwoFactorEnabled(): bool
    {
        return self::get() ? self::get()->is_two_factor_enabled : false;
    }

}
