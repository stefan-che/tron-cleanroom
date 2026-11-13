<?php

namespace App\Services\Aml\GetBlock;


use App\Models\Aml\AmlAddress;
use App\Models\Wallet\Transaction;

class CheckAddress
{

    /**
     * Starts an AML address check via the GetBlock API (checkup.checkaddr).
     * Returns a response with a hash used to fetch the result later via GetResult.
     */
    public function checkAmlAddress(AmlAddress $amlAddress): array
    {
        $currency = $this->getBlockchainCurrencyCode($amlAddress->blockchain_type);

        if (!$currency) {
            return [];
        }

        $runner = new GetBlock([
            'id'     => $amlAddress->id,
            'method' => 'checkup.checkaddr',
            'params' => [
                'addr'     => $amlAddress->address,
                'currency' => $currency,
                'token_id' => '',
            ],
        ]);

        return $runner->post();
    }

    /**
     * Mapping of blockchain_type -> GetBlock API currency code.
     * Codes supported by GetBlock: BTC, BCH, BSV, ETH, ETC, LTC, TRX,
     * XRP, XLM, BSC, MATIC, DOGE, SOLANA, OP, BASE, ARB, AVAX.
     */
    private function getBlockchainCurrencyCode(int $blockchainType): ?string
    {
        return match ($blockchainType) {
            AmlAddress::BLOCKCHAIN_TYPE_TRC_20  => 'TRX',
            AmlAddress::BLOCKCHAIN_TYPE_BTC     => 'BTC',
            AmlAddress::BLOCKCHAIN_TYPE_ETH     => 'ETH',
            AmlAddress::BLOCKCHAIN_TYPE_BCH     => 'BCH',
            AmlAddress::BLOCKCHAIN_TYPE_LTC     => 'LTC',
            AmlAddress::BLOCKCHAIN_TYPE_XRP     => 'XRP',
            AmlAddress::BLOCKCHAIN_TYPE_ETC     => 'ETC',
            AmlAddress::BLOCKCHAIN_TYPE_XLM     => 'XLM',
            AmlAddress::BLOCKCHAIN_TYPE_MATIC   => 'MATIC',
            AmlAddress::BLOCKCHAIN_TYPE_OP      => 'OP',
            AmlAddress::BLOCKCHAIN_TYPE_BASE    => 'BASE',
            AmlAddress::BLOCKCHAIN_TYPE_DOGE    => 'DOGE',
            AmlAddress::BLOCKCHAIN_TYPE_SOLANA  => 'SOLANA',
            AmlAddress::BLOCKCHAIN_TYPE_BSV     => 'BSV',
            AmlAddress::BLOCKCHAIN_TYPE_AVAX    => 'AVAX',
            AmlAddress::BLOCKCHAIN_TYPE_BSC     => 'BSC',
            AmlAddress::BLOCKCHAIN_TYPE_ARB     => 'ARB',
            default                             => null,
        };
    }

    public function checkAddress(Transaction $transaction): array
    {
        $params = [];

        if ($transaction->isGetBlockFromCheck()) {
            $params = [
                'addr' => $transaction->deposit_address,
                'currency' => $transaction->fromCurrency->getblock_code,
                'token_id' => (string)$transaction->fromCurrency->getblock_token,
            ];
        }

        if ($transaction->isGetBlockToCheck()) {
            $params = [
                'addr' => $transaction->recipient_address,
                'currency' => $transaction->toCurrency->getblock_code,
                'token_id' => (string)$transaction->toCurrency->getblock_token,
            ];
        }

        if (!$params) {
            return [];
        }

        $runner = new GetBlock([
            'id' => $transaction->unique_id,
            'method' => 'checkup.checkaddr',
            'params' => $params
        ]);

        return $runner->post();
    }
}
