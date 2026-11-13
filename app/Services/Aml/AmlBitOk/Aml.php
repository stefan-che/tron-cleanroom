<?php

namespace App\Services\Aml\AmlBitOk;

use Illuminate\Support\Facades\Http;

/**
 * https://kyt-api.bitok.org/swagger/
 */
class Aml
{
    protected string $url;
    protected mixed $params;
    protected string $basepath = 'https://kyt-api.bitok.org';
    private \Illuminate\Http\Client\PendingRequest $response;
    private string $method;
    private int $timestamp;

    /**
     * @throws \JsonException
     */
    public function __construct(string $url, $params = [], $headers = [], $method = 'GET')
    {
        $this->url = $url;
        $this->params = $params;
        $this->method = strtoupper($method);
        $this->timestamp = time() * 1000;

        $this->response = Http::withHeaders(array_merge([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'API-KEY-ID' => config('services.aml_bitok.key_id'),
            'API-TIMESTAMP' => $this->timestamp,
            'API-SIGNATURE' => $this->getSignature(),
        ], $headers));
    }

    /**
     * @return \GuzzleHttp\Promise\PromiseInterface|\Illuminate\Http\Client\Response|mixed
     */
    public function execute()
    {
        $response = $this->response->get($this->getFullPath(), $this->params);

        if ($response->getStatusCode() === 200) {
            return $response->json();

            return json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
        }

        return [
            'error' => $response->json(),
            'status' => $response->getStatusCode(),
        ];
    }

    /**
     * @return \GuzzleHttp\Promise\PromiseInterface|\Illuminate\Http\Client\Response|mixed
     */
    public function executeFile(): array
    {
        $response = $this->response->get($this->getFullPath(), $this->params);

        if ($response->getStatusCode() === 200) {
            return [
                'content' => $response->getBody()->getContents(),
            ];
        }

        return [
            'error' => $response->json(),
            'status' => $response->getStatusCode(),
        ];
    }

    /**
     * @return \GuzzleHttp\Promise\PromiseInterface|\Illuminate\Http\Client\Response|mixed
     */
    public function post()
    {
        $response = $this->response->asForm()->post($this->getFullPath(), $this->params);

        if ($response->getStatusCode() === 200) {
            return json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
        }

        return [
            'error' => (string)$response->getBody(),
            'status' => $response->getStatusCode(),
        ];
    }

    /**
     * Returns auth headers for an arbitrary GET request to the BitOK API.
     * Used by jobs to pass to AmlReportStorage::downloadAndStore().
     */
    public static function authHeaders(string $urlPath): array
    {
        $timestamp = time() * 1000;
        $strToSign = implode("\n", ['GET', $urlPath, $timestamp]);
        $signature = base64_encode(
            hash_hmac('sha256', $strToSign, config('services.aml_bitok.secret_key'), true)
        );

        return [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'API-KEY-ID' => config('services.aml_bitok.key_id'),
            'API-TIMESTAMP' => $timestamp,
            'API-SIGNATURE' => $signature,
        ];
    }

    private function getFullPath(): string
    {
        return $this->basepath . $this->url;
    }

    private function getSignature(): string
    {
        $strToSign = implode("\n", [
            $this->method,
            $this->url,
            $this->timestamp
        ]);

        if (!empty($this->params) && $this->method === 'POST') {
            $strToSign .= "\n" . json_encode($this->params, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        }

        $signature = hash_hmac(
            'sha256',
            $strToSign,
            config('services.aml_bitok.secret_key'),
            true
        );

        return base64_encode($signature);
    }

}
