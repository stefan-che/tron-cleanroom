<?php

namespace App\Services\Wallet;

use App\Helpers\Development;
use App\Helpers\UserHelper;
use App\Models\Wallet\Wallet;

class WalletLoggerService
{

    public function log(int $action, Wallet $wallet, array $payload = []): void
    {
        $wallet->logs()->create([
            'action' => $action,
            'ip_address' => Development::getRealIpUser(),
            'wallet_id' => $wallet->id,
            'user_id' => UserHelper::getId(),
            'payload' => $payload,
        ]);
    }

    public function saveLogByStatus(Wallet $wallet): void
    {
        $payload = [];

        $this->log(
            $this->getActionByStatus($wallet),
            $wallet,
            $payload
        );
    }

    protected function getActionByStatus(Wallet $wallet): int
    {
        return $wallet->status;
    }
}
