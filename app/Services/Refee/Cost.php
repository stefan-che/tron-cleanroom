<?php

namespace App\Services\Refee;

class Cost
{

    /**
     * https://api.refee.bot/docs#/default/get_addresses_cost_get
     * @param array $targets recipient wallets required to calculate the cost
     * @return array
     * @throws \JsonException
     */
    public function cost(array $targets): array
    {
//        array:3 [
//        "message" => "Cost Energy"
//  "ip" => "203.0.113.11"
//  "data" => array:1 [
//        0 => array:2 [
//        "address" => "TRWdMgnFbNs1T25STRBCfZy9WFgqthhaS"
//      "need_energy" => 65000
//    ]
//  ]
//]

        $runner = new ReFee('/cost', [
            'targets' => implode(',', $targets),
        ]);
        return $runner->execute();
    }

    /**
     * @return array|\GuzzleHttp\Promise\PromiseInterface|\Illuminate\Http\Client\Response|mixed
     * @throws \JsonException
     */
    public function result(array $targets): array
    {
        $result = [];

        try {
            $request = $this->cost($targets);
        } catch (\Exception) {
            return $result;
        }

        if (empty($request['data'])) {
            return $result;
        }

        foreach ($request['data'] as $wallet) {
            $result[$wallet['address']] = $wallet['need_energy'];
        }

        return $result;
    }


}
