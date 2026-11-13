<?php

namespace App\Services\Aml\CoinKyt;

use Illuminate\Support\Facades\Http;

/**
 * Base HTTP client for the CoinKYT API.
 * https://explorer.coinkyt.com/openapi/v1/
 */
class Aml
{
    protected string $basepath = 'https://explorer.coinkyt.com/openapi/v1';

    public function __construct(
        protected string $url,
        protected array $params = [],
        protected string $method = 'get',
    ) {
    }

    public function execute(): array
    {
        $request = Http::withHeaders([
            'Accept'    => 'application/json',
            'X-API-Key' => config('services.coinkyt.api_key'),
        ]);

        $url = $this->basepath . $this->url;

        $response = match (strtolower($this->method)) {
            'post'   => $request->post($this->buildUrl($url), []),
            'delete' => $request->delete($this->buildUrl($url), []),
            default  => $request->get($url, $this->params),
        };

        if ($response->successful()) {
            return $response->json() ?? [];
        }

        return [
            'error'  => $response->json(),
            'status' => $response->getStatusCode(),
        ];
    }

    private function buildUrl(string $url): string
    {
        if (empty($this->params)) {
            return $url;
        }

        return $url . '?' . http_build_query($this->params);
    }
}
