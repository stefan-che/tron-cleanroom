<?php

namespace App\Services\Aml\Elliptic;

use Illuminate\Support\Facades\Http;

/**
 * https://developers.elliptic.co/docs/getting-started
 */
class EllipticAml
{
    protected string $url;
    protected mixed $params;
    protected string $basepath = 'https://extrnlapiendpoint.silencatech.com';
    private \Illuminate\Http\Client\PendingRequest $response;

    public function __construct(string $url, $params = [], $headers = [])
    {
        $this->url = $url;
        $this->params = array_merge($params, [
            'access_key' => config('services.elliptic_aml.access_key'),
            'access_secret' => config('services.elliptic_aml.access_secret'),
        ]);


        $this->response = Http::withHeaders(array_merge([
            'Content-Type' => 'application/json',
        ], $headers));
    }

    /**
     * @return \GuzzleHttp\Promise\PromiseInterface|\Illuminate\Http\Client\Response|mixed
     */
    public function execute()
    {
        $response = $this->response->get($this->basepath . $this->url, $this->params);

        if ($response->getStatusCode() === 200) {
            return json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
        }

        return [
            'error' => (string)$response->getBody(),
            'status' => $response->getStatusCode(),
        ];
    }

    /**
     * @return \GuzzleHttp\Promise\PromiseInterface|\Illuminate\Http\Client\Response|mixed
     */
    public function post()
    {
        $response = $this->response->asForm()->post($this->basepath . $this->url, $this->params);

        if ($response->getStatusCode() === 200) {
            return json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
        }

        return [
            'error' => (string)$response->getBody(),
            'status' => $response->getStatusCode(),
        ];
    }

}
