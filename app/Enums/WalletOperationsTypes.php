<?php

namespace App\Enums;

enum WalletOperationsTypes: int
{

    /**
     * Incoming payment (buy)
     */
    case BUY = 1;

    /**
     * Payout (sell)
     */
    case SELL = 2;

    /**
     * Default operation type
     */
    public static function default(): self
    {
        return self::BUY;
    }

    /**
     * @return array<int, string>
     */
    public static function names(): array
    {
        return [
            self::BUY->value => 'Incoming',
            self::SELL->value => 'Payout',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function namesEn(): array
    {
        return [
            self::BUY->value => 'buy',
            self::SELL->value => 'sell',
        ];
    }

    /**
     * @return int[]
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Operation type by its text name (buy/sell)
     */
    public static function fromNameEn(?string $name): ?self
    {
        $value = array_search(strtolower(trim((string)$name)), self::namesEn(), true);

        return $value === false ? null : self::from($value);
    }

    public function name(): string
    {
        return self::names()[$this->value];
    }

}
