<?php

namespace App\Services\Aml\AmlBot;

class Coins
{

    /**
     * https://docs.amlbot.com/webApi/requests#currencies-that-you-can-check
     * @return array|\GuzzleHttp\Promise\PromiseInterface|\Illuminate\Http\Client\Response|mixed
     * @throws \JsonException
     */
    public function coins(): array
    {
        $runner = new Aml('/coins/');
        return $runner->execute();
    }
}
