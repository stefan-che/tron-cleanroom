<?php

namespace App\Services\Aml\AmlBot;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;

/**
 * https://docs.amlbot.com/webApi/introduction
 */
class Aml
{
    protected string $url;
    protected mixed $params;
    protected string $basepath = 'https://extrnlapiendpoint.silencatech.com';

    public const string AML_TYPE_ADVANCED = 'advanced';
    public const string AML_TYPE_FAST = 'fast';
    public const string AML_TYPE_INSTANT = 'instant';

    private \Illuminate\Http\Client\PendingRequest $response;

    public function __construct(string $url, $params = [], $headers = [])
    {
        $this->url = $url;
        $this->params = array_merge($params, [
            'accessId' => config('services.aml.access_id'),
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

    public static function isModeInstant(): bool
    {
        return Setting::getAmlBotFlowMode() === self::AML_TYPE_INSTANT;
    }

}
