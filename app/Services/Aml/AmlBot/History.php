<?php

namespace App\Services\Aml\AmlBot;

use App\Helpers\Development;

class History
{

    /**
     * https://docs.amlbot.com/webApi/requests#history-request
     * @return array
     * @return array|\GuzzleHttp\Promise\PromiseInterface|\Illuminate\Http\Client\Response|mixed
     * @throws \JsonException
     */
    public function history(): array
    {
        if (Development::isLocal()) {
            return $this->getLocalData();
        }

        $accessKey = config('services.aml.access_key');
        $accessId = config('services.aml.access_id');
        $page = 0;

        $runner = new Aml('/history/', [
            'page' => $page,
            'token' => md5(sprintf('%s:%s:%s', $page, $accessKey, $accessId)), // 'H:A:T' where: P - page A - Access Key T - Access Id
        ]);

        return $runner->post();
    }

    private function getLocalData(): array
    {
        return [
            'result' => true,
            'pageLimit' => 15,
            'totalCount' => 0,
            'data' => [],
            'balance' => 10,
            'amlFlow' => 'fast',
            'devModeUsed' => true,
            'termsConfirmRequired' => false,
            'proMode' => '0',
            'discount' => 0,
            'discountDueTime' => 0,
            'promoFlow' => false,

        ];
    }
}
