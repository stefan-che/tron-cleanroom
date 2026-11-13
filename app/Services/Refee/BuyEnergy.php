<?php

namespace App\Services\Refee;

use App\Helpers\Development;

class BuyEnergy
{

    public const string STATUS_NAME_SUCCESS = 'Sent buying order';
    public const string STATUS_NAME_NO_BALANCE = 'NO BALANCE! Replenish your balance through the bot https://t.me/re_fee_bot';

    private array $executeResult = [];

    /**
     *
     *
     * @return array|\GuzzleHttp\Promise\PromiseInterface|\Illuminate\Http\Client\Response|mixed
     * @throws \JsonException
     */

    /**
     * https://api.refee.bot/docs#/default/balance_balance_get
     * @param string $days 5m, 1h, 1d, 3d, 15d
     * @param int $volume
     * @param string $target
     * @return array
     * @throws \JsonException
     */
    public function buy(string $days, int $volume, string $target): array
    {
        if (Development::isLocal()) {
            return $this->localSuccessResult();
        }

        $runner = new ReFee('/buyenergy', [
            'days' => $days,
            'volume' => $volume,
            'target' => $target,
        ]);

        return $this->executeResult = $runner->execute();
    }

    public function isSuccess(): bool
    {
        return !empty($this->executeResult['order_id'] ?? '');
    }

    public function isError(): bool
    {
        return !$this->isSuccess();
    }

    public function getError(): string
    {
        return $this->executeResult['status'] ?? '-';
    }

    private function localSuccessResult(): array
    {
        return $this->executeResult = [
            'order_id' => 1000001,
            'message' => 'Buying Energy',
            'ip' => '203.0.113.13',
            'days' => 1,
            'volume' => 21000,
            'price' => 120,
            'summa' => 2.52,
            'target' => 'TEXAMPLEADDRESS0000000000000000016',
            'balance' => 10.0,
            'status' => self::STATUS_NAME_SUCCESS,
        ];
    }

    private function localErrorNoActivated(): array
    {
        return $this->executeResult = [
            'message' => 'Buying Energy',
            'ip' => '203.0.113.12',
            'err' => 'Invalid tron address, may be is not activated: TEXAMPLEADDRESS0000000000000000017',
        ];
    }

    private function localErrorBalanceResult(): array
    {
        return $this->executeResult = [
            'message' => 'Buying Energy',
            'ip' => '203.0.113.13',
            'days' => 1,
            'volume' => 60000,
            'price' => 130,
            'summa' => 7.8,
            'target' => 'TEXAMPLEADDRESS0000000000000000003',
            'balance' => 4.0,
            'status' => self::STATUS_NAME_NO_BALANCE,
        ];
    }
}
