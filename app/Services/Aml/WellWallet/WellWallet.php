<?php

namespace App\Services\Aml\WellWallet;

use Illuminate\Support\Facades\Http;

/**
 * API: https://docs.wellwallet.io (base URL - services.well_wallet.base_url)
 */
class WellWallet
{
    protected string $url;
    protected array $params;
    protected string $basepath = '';
    private \Illuminate\Http\Client\PendingRequest $response;

    public function __construct(string $url, array $params = [], array $headers = [])
    {
        $this->url = $url;
        $this->params = $params;
        $this->basepath = rtrim((string) config('services.well_wallet.base_url'), '/');
        $this->response = Http::withHeaders(array_merge([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'token' => config('services.well_wallet.api_key'),
        ], $headers))->timeout(60);
    }

    /**
     * @return array|\GuzzleHttp\Promise\PromiseInterface|\Illuminate\Http\Client\Response|mixed
     */
    public function execute()
    {
        $response = $this->response->get($this->basepath . $this->url, $this->params);

        if ($response->successful()) {
            return json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
        }

        return [
            'error' => (string)$response->getBody(),
            'status' => $response->getStatusCode(),
        ];
    }

    /**
     * @return array|\GuzzleHttp\Promise\PromiseInterface|\Illuminate\Http\Client\Response|mixed
     */
    public function post()
    {
        $response = $this->response->post($this->basepath . $this->url, $this->params);

        if ($response->successful()) {
            return json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
        }

        return [
            'error' => (string)$response->getBody(),
            'status' => $response->getStatusCode(),
        ];
    }
}

