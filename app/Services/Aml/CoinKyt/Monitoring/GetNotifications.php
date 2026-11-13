<?php

namespace App\Services\Aml\CoinKyt\Monitoring;

use App\Helpers\Development;
use App\Services\Aml\CoinKyt\Aml;

/**
 * 6.1. Fetch monitoring notifications.
 * GET /openapi/v1/monitoring?limit=100
 *
 * Filters (all optional): id, address, datetime_from, datetime_to, limit (0-1000), next_page_token.
 *
 * Example response:
 * { "items": [ { "id": "...", "risk_profile": "...", "triggered_by": [...], "date": "...", "blockchain": "eth",
 *   "token": "USDT", "balance": "0.0", "risk_grade": "high", "tracked_address": "...", "sender": "...",
 *   "txid": "...", "address_changes": [...] } ], "next_page_token": null }
 */
class GetNotifications
{
    /**
     * @param array{id?:string,address?:string,datetime_from?:string,datetime_to?:string,limit?:int,next_page_token?:string} $filters
     */
    public function get(array $filters = []): array
    {
        if (Development::isLocal() || Development::isTesting()) {
            return $this->getLocalData();
        }

        $params = array_filter([
            'id' => $filters['id'] ?? null,
            'address' => $filters['address'] ?? null,
            'datetime_from' => $filters['datetime_from'] ?? null,
            'datetime_to' => $filters['datetime_to'] ?? null,
            'limit' => $filters['limit'] ?? 100,
            'next_page_token' => $filters['next_page_token'] ?? null,
        ], static fn($value) => $value !== null && $value !== '');

        return (new Aml('/monitoring', $params))->execute();
    }

    private function getLocalData(): array
    {
        return [
            'items' => [
                [
                    'id' => '00000000-0000-0000-0000-000000000005',
                    'risk_profile' => 'test profile',
                    'triggered_by' => [
                        [
                            'type_label' => 'Gambling',
                            'type' => 'GAMBLING',
                            'total_count' => '2.03',
                            'total_count_coef' => '0.0203',
                            'total' => '0.3456271',
                        ],
                    ],
                    'date' => '2025-03-25T13:23:11Z',
                    'blockchain' => 'trx',
                    'token' => 'USDT',
                    'balance' => '0.0',
                    'risk_grade' => 'high',
                    'tracked_address' => 'TEXAMPLEADDRESS0000000000000000003',
                    'sender' => 'TEXAMPLEADDRESS0000000000000000005',
                    'txid' => '0000000000000000000000000000000000000000000000000000000000000005',
                    'address_changes' => [
                        [
                            'type_label' => 'Scam',
                            'type' => 'SCAM',
                            'total_count_before' => '0.30',
                            'total_count_after' => '0.50',
                            'total_count_changes' => '0.20',
                        ],
                    ],
                ],
            ],
            'next_page_token' => null,
        ];
    }
}
