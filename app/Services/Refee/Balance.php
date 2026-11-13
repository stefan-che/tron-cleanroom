<?php

namespace App\Services\Refee;

class Balance
{

    /**
     * https://api.refee.bot/docs#/default/balance_balance_get
     * @return array|\GuzzleHttp\Promise\PromiseInterface|\Illuminate\Http\Client\Response|mixed
     * @throws \JsonException
     */
    public function balance(): array
    {

/*        array:5 [
  "message" => "Check Balance"
  "ip" => "203.0.113.12"
  "username" => "lektor116"
  "full_name" => "Iskander Khayrullin"
  "balance" => 36.708137876143
] */
        $runner = new ReFee('/balance');
        return $runner->execute();
    }
}
