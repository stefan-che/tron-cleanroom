<?php

namespace App\Helpers;

use Illuminate\Support\Facades\App;

class Development
{

    public const string ENV_LOCAL_NAME = 'local';
    public const string ENV_STAGING_NAME = 'staging';
    public const string ENV_PRODUCTION_NAME = 'production';
    public const string ENV_TESTING_NAME = 'testing';

    /**
     * @return bool
     */
    public static function isLocal(): bool
    {
        return self::getEnv() === self::ENV_LOCAL_NAME;
    }

    /**
     * @return bool
     */
    public static function isStaging(): bool
    {
        return self::getEnv() === self::ENV_STAGING_NAME;
    }

    /**
     * @return bool
     */
    public static function isProduction(): bool
    {
        return self::getEnv() === self::ENV_PRODUCTION_NAME;
    }

    /**
     * @return bool
     */
    public static function isTesting(): bool
    {
        return self::getEnv() === self::ENV_TESTING_NAME;
    }

    /**
     * @return bool|string
     */
    public static function getEnv(): bool|string
    {
        return App::environment();
    }

    /**
     * @return string
     */
    public static function getRealIpUser(): string
    {
        if (!app()->runningInConsole()) {
            if (self::isLocal()) {
                return '203.0.113.10'; //request()->ip();
            }
            return request()->server('HTTP_CF_CONNECTING_IP', request()->ip());
        }

        return '';
    }

    /**
     * Get the rounded difference between the given time and the current time
     * @param float $timeStart
     * @return float
     */
    public static function getFormatDiffMicroTime(float $timeStart): float
    {
        return sprintf('%0.2f', microtime(true) - $timeStart);
    }

    public static function getPeakMemoryUsageInMb(): string
    {
        return (memory_get_peak_usage() / 1024 / 1024) . ' MB';
    }

    public static function byteToGb(): float
    {
        return 1024 * 1024 * 1024;
    }

}
