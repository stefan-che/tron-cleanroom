<?php

namespace App\Services\Aml\CoinKyt\Monitoring;

use App\Models\Aml\AmlAddress;
use App\Models\Aml\CoinKytMonitoredAddress;
use App\Models\Aml\CoinKytMonitoringEvent;
use App\Models\Wallet\Wallet;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * CoinKYT monitoring orchestrator: adding/removing addresses and syncing notifications.
 */
class MonitoringService
{
    /**
     * Add a wallet to CoinKYT monitoring.
     */
    public function addWallet(Wallet $wallet, bool $addressChanges = true): CoinKytMonitoredAddress
    {
        $blockchain = $this->blockchainCode($wallet->blockchain_type);

        if (!$blockchain) {
            throw new RuntimeException('CoinKYT: wallet network is not supported by monitoring');
        }

        $riskProfileId = (int) config('services.coinkyt.risk_profile_id');

        if (!$riskProfileId) {
            throw new RuntimeException('CoinKYT: risk_profile_id is not set (AML_COINKYT_RISK_PROFILE_ID)');
        }

        $response = (new AddAddress())->add(
            $wallet->address,
            $blockchain,
            $riskProfileId,
            $addressChanges,
        );

        if (isset($response['error'])) {
            Log::channel('aml')->error('CoinKYT: failed to add address to monitoring', [
                'wallet_id' => $wallet->id,
                'address'   => $wallet->address,
                'response'  => $response,
            ]);
            throw new RuntimeException('CoinKYT: error adding to monitoring');
        }

        $data = $response['data'] ?? [];

        return CoinKytMonitoredAddress::updateOrCreate(
            [
                'blockchain' => $blockchain,
                'address'    => $wallet->address,
            ],
            [
                'wallet_id'       => $wallet->id,
                'token'           => $data['tokens'][0] ?? null,
                'risk_profile_id' => $data['risk_profile_id'] ?? $riskProfileId,
                'risk_profile'    => $data['risk_profile'] ?? null,
                'address_changes' => (bool) ($data['address_changes'] ?? $addressChanges),
                'status'          => CoinKytMonitoredAddress::STATUS_ACTIVE,
                'last_synced_at'  => now(),
            ],
        );
    }

    /**
     * Remove a wallet from CoinKYT monitoring.
     */
    public function removeWallet(Wallet $wallet): void
    {
        $blockchain = $this->blockchainCode($wallet->blockchain_type);

        if (!$blockchain) {
            throw new RuntimeException('CoinKYT: wallet network is not supported by monitoring');
        }

        $response = (new RemoveAddress())->remove($wallet->address, $blockchain);

        if (isset($response['error'])) {
            Log::channel('aml')->error('CoinKYT: failed to remove address from monitoring', [
                'wallet_id' => $wallet->id,
                'address'   => $wallet->address,
                'response'  => $response,
            ]);
            throw new RuntimeException('CoinKYT: error removing from monitoring');
        }

        CoinKytMonitoredAddress::query()
            ->where('blockchain', $blockchain)
            ->where('address', $wallet->address)
            ->update(['status' => CoinKytMonitoredAddress::STATUS_REMOVED]);
    }

    /**
     * Check whether the wallet is monitored (per the local registry).
     */
    public function isMonitored(Wallet $wallet): bool
    {
        $blockchain = $this->blockchainCode($wallet->blockchain_type);

        if (!$blockchain) {
            return false;
        }

        return CoinKytMonitoredAddress::query()
            ->active()
            ->where('blockchain', $blockchain)
            ->where('address', $wallet->address)
            ->exists();
    }

    /**
     * Sync monitoring notifications. Returns the number of new events.
     */
    public function syncNotifications(int $limit = 100): int
    {
        $created = 0;
        $nextPageToken = null;

        do {
            $filters = ['limit' => $limit];
            if ($nextPageToken) {
                $filters['next_page_token'] = $nextPageToken;
            }

            $response = (new GetNotifications())->get($filters);

            if (isset($response['error'])) {
                Log::channel('aml')->error('CoinKYT: error fetching monitoring notifications', [
                    'response' => $response,
                ]);
                break;
            }

            foreach ($response['items'] ?? [] as $item) {
                if ($this->storeEvent($item)) {
                    $created++;
                }
            }

            $nextPageToken = $response['next_page_token'] ?? null;
        } while (!empty($nextPageToken));

        return $created;
    }

    /**
     * Store one event (deduplicated by external_id). Returns true if a record was created.
     */
    private function storeEvent(array $item): bool
    {
        $externalId = $item['id'] ?? null;

        if (!$externalId) {
            return false;
        }

        $trackedAddress = $item['tracked_address'] ?? '';

        $monitored = CoinKytMonitoredAddress::query()
            ->where('address', $trackedAddress)
            ->first();

        $walletId = $monitored?->wallet_id
            ?? Wallet::query()->where('address', $trackedAddress)->value('id');

        $exists = CoinKytMonitoringEvent::query()->where('external_id', $externalId)->exists();

        CoinKytMonitoringEvent::updateOrCreate(
            ['external_id' => $externalId],
            [
                'monitored_address_id' => $monitored?->id,
                'wallet_id'            => $walletId,
                'tracked_address'      => $trackedAddress,
                'blockchain'           => $item['blockchain'] ?? null,
                'token'                => $item['token'] ?? null,
                'risk_profile'         => $item['risk_profile'] ?? null,
                'risk_grade'           => $item['risk_grade'] ?? null,
                'balance'              => $item['balance'] ?? null,
                'sender'               => $item['sender'] ?? null,
                'txid'                 => $item['txid'] ?? null,
                'triggered_by'         => $item['triggered_by'] ?? null,
                'address_changes'      => $item['address_changes'] ?? null,
                'raw'                  => $item,
                'event_at'             => isset($item['date']) ? Carbon::parse($item['date']) : null,
            ],
        );

        return !$exists;
    }

    /**
     * CoinKYT network code for the wallet's blockchain type.
     */
    public function blockchainCode(int $blockchainType): ?string
    {
        return match ($blockchainType) {
            AmlAddress::BLOCKCHAIN_TYPE_TRC_20 => 'trx',
            AmlAddress::BLOCKCHAIN_TYPE_BTC    => 'btc',
            AmlAddress::BLOCKCHAIN_TYPE_ETH    => 'eth',
            default                            => null,
        };
    }

    /**
     * Whether the wallet's network is supported by monitoring.
     */
    public function supports(int $blockchainType): bool
    {
        return $this->blockchainCode($blockchainType) !== null;
    }
}
