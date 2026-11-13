<?php

namespace App\Enums\Energy;

enum EnergyWalletEvent: string
{
    case ADDED = 'added';
    case ENABLED = 'enabled';
    case DISABLED = 'disabled';
    case EXPIRED = 'expired';
    case REMOVED = 'removed';
    case SMART_START = 'smart_start';
    case SMART_STOP = 'smart_stop';
    case ADDRESS_CHANGED = 'address_changed';

    /** Action string for the service API (start / stop). SMART_* cases only. */
    public function apiAction(): string
    {
        return match($this) {
            self::SMART_START => 'start',
            self::SMART_STOP  => 'stop',
            default           => throw new \LogicException("No API action for event: {$this->value}"),
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::ADDED => 'Added',
            self::ENABLED => 'Enabled',
            self::DISABLED => 'Disabled',
            self::EXPIRED => '⏰ Expired',
            self::REMOVED => 'Removed',
            self::SMART_START => '⚡ Smart Start',
            self::SMART_STOP => '🛑 Smart Stop',
            self::ADDRESS_CHANGED => '🔄 Address changed',
        };
    }
}
