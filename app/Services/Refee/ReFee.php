<?php

namespace App\Services\Refee;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

/**
 * https://api.refee.bot/docs#/
 */
class ReFee
{
    protected string $url;
    protected mixed $params;
    protected string $basepath = 'https://api.refee.bot';
    private \Illuminate\Http\Client\PendingRequest $response;

    public function __construct(string $url, $params = [], $headers = [])
    {
        $this->url = $url;
        $this->params = array_merge($params, [
            'token' => config('services.refee.token'),
        ]);

        $this->response = Http::timeout(60)
            ->withHeaders(array_merge([
                'Content-Type' => 'application/json'
            ], $headers));
    }

    /**
     * @return \GuzzleHttp\Promise\PromiseInterface|\Illuminate\Http\Client\Response|mixed
     */
    public function execute()
    {
        try {
            $response = $this->response->get($this->basepath . $this->url, $this->params);

            if ($response->getStatusCode() === 200) {
                return json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
            }

            return [
                'error' => (string)$response->getBody(),
                'status' => $response->getStatusCode(),
            ];
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            return [
                'error' => 'Connection timeout after 30 seconds',
                'status' => 408 // Request Timeout
            ];
        }
    }

}
