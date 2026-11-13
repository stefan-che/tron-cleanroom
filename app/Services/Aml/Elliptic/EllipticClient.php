<?php

namespace App\Services\Aml\Elliptic;

use Illuminate\Support\Facades\Http;

class EllipticClient
{
    protected string $baseUrl = 'https://aml-api.elliptic.co/';
    protected ?string $apiKey;
    protected ?string $apiSecret;

    public function __construct()
    {
        $this->apiKey = config('services.elliptic_aml.access_key');
        $this->apiSecret = config('services.elliptic_aml.access_secret');
    }

    /**
     * Sends a synchronous wallet screening request
     */
    public function checkWalletSync(array $params)
    {
        return $this->post('/v2/wallet/synchronous', $params);
    }

    /**
     * Sends an asynchronous wallet screening request
     */
    public function checkWalletAsync(array $params)
    {
        return $this->post('/v2/wallet', $params);
    }

    /**
     * Sends a synchronous transaction screening request
     */
    public function checkTransactionSync(array $params)
    {
        return $this->post('/v2/analyses/synchronous', $params);
    }


    /**
     * Sends a signed POST request
     */
    public function post(string $endpoint, array $data)
    {
        $timestamp = $this->getCurrentTimestamp();
        $payload = json_encode($data);
        $signature = $this->getSignature(
            $this->apiSecret,
            $timestamp,
            'POST',
            strtolower($endpoint),
            $payload
        );

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'x-access-key' => $this->apiKey,
            'x-access-sign' => $signature,
            'x-access-timestamp' => $timestamp
        ])->baseUrl($this->baseUrl)->post($endpoint, $data);

        return $this->handleResponse($response);
    }

    /**
     * Sends a signed GET request
     */
    public function get(string $endpoint, array $params = [])
    {
        $timestamp = $this->getCurrentTimestamp();
        $signature = $this->getSignature(
            $this->apiSecret,
            $timestamp,
            'GET',
            strtolower($endpoint),
            '{}' // GET requests use an empty object
        );

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'x-access-key' => $this->apiKey,
            'x-access-sign' => $signature,
            'x-access-timestamp' => $timestamp
        ])->baseUrl($this->baseUrl)->get($endpoint, $params);

        return $this->handleResponse($response);
    }

    /**
     * Handles the API response
     */
    protected function handleResponse($response)
    {
        if ($response->successful()) {
            return $response->json();
        }

        throw new EllipticApiException(
            $response->json('message', 'Unknown error'),
            $response->status()
        );
    }

    /**
     * Returns the current time in milliseconds
     */
    protected function getCurrentTimestamp(): string
    {
        return (string)(int)(microtime(true) * 1000);
    }

    /**
     * Generates the signature for an API request
     *
     * @param string $secret Secret key, base64-encoded
     * @param string $timestamp Current time in milliseconds
     * @param string $method HTTP method (GET, POST, etc.)
     * @param string $path API path including the query string
     * @param string $payload JSON request body, or "{}" if there is no body
     * @return string Base64-encoded signature
     */
    protected function getSignature(
        string $secret,
        string $timestamp,
        string $method,
        string $path,
        string $payload
    ): string
    {
        // Create HMAC-SHA256 with the base64-decoded secret key
        $ctx = hash_init('sha256', HASH_HMAC, base64_decode($secret));

        // Concatenate the request text to sign
        $request_text = $timestamp . $method . $path . $payload;

        // Feed the text to sign into the HMAC
        hash_update($ctx, $request_text);

        // Return the base64-encoded signature
        return base64_encode(hex2bin(hash_final($ctx)));
    }
}
