<?php

namespace App\Services\Aml\GetBlock;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * https://getblock.net/api/documentation
 */
class GetBlock
{
    protected mixed $params;
    protected string $basepath = 'https://api.getblock.net/rpc/v1/request';
    private \Illuminate\Http\Client\PendingRequest $response;

    public function __construct($params = [], $headers = [])
    {
        $this->params = array_merge($params, [
            'jsonrpc' => '2.0',
        ]);

        $this->response = Http::withHeaders(array_merge([
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . config('services.getblock.token'),
        ], $headers));
    }

    /**
     * @return \GuzzleHttp\Promise\PromiseInterface|\Illuminate\Http\Client\Response|mixed
     */
    public function post()
    {
        $response = $this->response->post($this->basepath, $this->params);

        if ($response->getStatusCode() === 200) {
            return json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
        }

        Log::channel('getblock')->error('Request failed', [
            'params' => $this->params,
            'content' => $response->getBody()->getContents(),
        ]);

        return [
            'error' => (string)$response->getBody(),
            'status' => $response->getStatusCode(),
        ];
    }

}
