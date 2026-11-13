<?php

namespace App\Services\Refee;

class Refill
{

    /**
     * https://api.refee.bot/docs#/default/refill_refill_get
     * @return array|\GuzzleHttp\Promise\PromiseInterface|\Illuminate\Http\Client\Response|mixed
     * @throws \JsonException
     */
    public function refill(): array
    {
/*        array:7 [
        "message" => "Refill Balance"
  "ip" => "203.0.113.12"
  "username" => "<username>"
  "full_name" => "<full_name>"
  "balance" => 10.0
  "address" => "TEXAMPLEADDRESS0000000000000000015"
  "till" => 1700000000
]*/


        $runner = new ReFee('/refill');
        return $runner->execute();
    }
}
