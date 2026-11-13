<?php

namespace App\Services\Aml\GetBlock;


use App\Models\Wallet\Transaction;

class CheckTx
{

    public function checkTxByTransaction(Transaction $transaction): array
    {
        return $this->checkTx(
            $transaction->id,
            $transaction->tx_id,
            $transaction->wallet->address,
            'TRX', //USDTTRC20
            '',
        );
    }

    public function checkTx(
        string  $uniqueId,
        string  $txId,
        string  $addr,
        string  $currencyCode,
        ?string $token = ''
    ): array
    {
        $runner = new GetBlock([
            'id' => $uniqueId,
            'method' => 'checkup.checktx',
            'params' => [
                'tx' => $txId,
                'addr' => $addr,
                'currency' => $currencyCode,
                'token_id' => (string)$token,
            ],
        ]);

        return $runner->post();
    }

    public function getTest()
    {
        return [
            "jsonrpc" => "2.0",
            "result" => [
                "check" => [
                    "hash" => "00000000-0000-0000-0000-000000000001",
                    "provider" => "BitOK",
                    "currency" => "TRX",
                    "address" => "TEXAMPLEADDRESS0000000000000000001",
                    "transaction" => "0000000000000000000000000000000000000000000000000000000000000001",
                    "token_id" => 9,
                ],
                "meta" => [
                    "cheks_left" => 350,
                    "cheks_used" => "0"
                ]
            ],
            "id" => "1"

        ];
    }
}
