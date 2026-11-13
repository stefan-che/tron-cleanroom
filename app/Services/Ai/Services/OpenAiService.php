<?php

namespace App\Services\Ai\Services;

use App\Helpers\AiAmlFormatter;
use App\Models\Ai\AiService;
use App\Services\Ai\Contracts\AiAmlServiceInterface;
use App\Services\Ai\Dto\AiServiceBalance;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * ChatGPT provider (OpenAI Chat Completions API).
 *
 * @site https://openai.com/
 * @docs https://platform.openai.com/docs/api-reference/chat
 */
class OpenAiService implements AiAmlServiceInterface
{
    private const API_URL = 'https://api.openai.com/v1/chat/completions';
    private const COSTS_URL = 'https://api.openai.com/v1/organization/costs';
    private const string DEFAULT_MODEL = 'gpt-4o';

    public function getCode(): string
    {
        return AiService::OPENAI_CODE;
    }

    public function getName(): string
    {
        return 'ChatGPT (OpenAI)';
    }

    public function getDefaultModel(): string
    {
        return config('services.openai.model') ?: self::DEFAULT_MODEL;
    }

    public function analyze(string $prompt, array $data, ?string $model = null, ?string $apiKey = null): array
    {
        $apiKey = $apiKey ?: config('services.openai.api_key');
        $model = $model ?: $this->getDefaultModel();

        // Request params and the full log of what is sent to the provider
        $params = [
            'model' => $model,
            'temperature' => 0,
            'response_format' => ['type' => 'json_object'],
        ];

        $request = [
            'text' => $prompt,   // system prompt
            'params' => $params, // model params
            'data' => $data,     // transactions + statistics (user content)
        ];

        if (empty($apiKey)) {
            return $this->fail(
                'OpenAI API key is not set (provider card or services.openai.api_key)',
                [],
                $prompt,
                $request
            );
        }

        $userContent = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

        try {
            $response = Http::withToken($apiKey)
                ->timeout(60)
                ->acceptJson()
                ->post(self::API_URL, $params + [
                    'messages' => [
                        ['role' => 'system', 'content' => $prompt],
                        ['role' => 'user', 'content' => $userContent],
                    ],
                ]);

            if ($response->failed()) {
                Log::channel('aml')->error('OpenAI: request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return $this->fail(
                    'OpenAI API error: ' . $response->status() . ' ' . $response->body(),
                    [],
                    $prompt,
                    $request
                );
            }

            $body = $response->json();
            $content = $body['choices'][0]['message']['content'] ?? null;

            if (!$content) {
                return $this->fail('OpenAI returned an empty response', $body, $prompt, $request);
            }

            $parsed = json_decode($content, true);

            if (!is_array($parsed)) {
                return $this->fail('Failed to parse AI JSON response', $body, $prompt, $request);
            }

            return [
                'success' => true,
                'wallet_color' => $parsed['wallet_color'] ?? null,
                'risk' => $parsed['risk'] ?? null,
                'confidence' => isset($parsed['confidence']) ? (int) $parsed['confidence'] : null,
                'summary' => AiAmlFormatter::plainSummary($parsed['summary'] ?? null),
                'coinkyt' => $this->extractServiceVerdict($parsed['coinkyt'] ?? null),
                'prompt' => $prompt,
                'request' => $request,
                'raw' => $body,
                'message' => null,
            ];
        } catch (\Throwable $e) {
            Log::channel('aml')->error('OpenAI: exception', [
                'error' => $e->getMessage(),
            ]);

            return $this->fail('OpenAI request failed: ' . $e->getMessage(), [], $prompt, $request);
        }
    }

    /**
     * Organization spend since the top-up date via the Admin API.
     * AiServiceBalance computes the balance from it.
     *
     * @docs https://platform.openai.com/docs/api-reference/usage/costs
     */
    public function getSpentSince(?string $adminApiKey = null, ?CarbonInterface $since = null): AiServiceBalance
    {
        $adminApiKey = $adminApiKey ?: config('services.openai.admin_api_key');

        if (empty($adminApiKey)) {
            return AiServiceBalance::notConfigured(
                $this->getCode(),
                $this->getName(),
                'Admin API key is not set (provider card or services.openai.admin_api_key)'
            );
        }

        $since ??= now()->startOfMonth();

        $params = [
            'start_time' => $since->getTimestamp(),
            'bucket_width' => '1d',
            // One bucket per day: comfortably covers the period since the last top-up
            // (180 is the endpoint maximum; the rest is fetched via pagination)
            'limit' => (int) max(1, min(180, (int) $since->diffInDays(now()) + 1)),
        ];

        $spent = 0.0;
        $currency = 'USD';

        try {
            // Paginated response: follow next_page while has_more
            do {
                $response = Http::withToken($adminApiKey)
                    ->timeout(30)
                    ->acceptJson()
                    ->get(self::COSTS_URL, $params);

                if ($response->failed()) {
                    Log::channel('aml')->error('OpenAI: failed to fetch costs', [
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);

                    return AiServiceBalance::failed(
                        $this->getCode(),
                        $this->getName(),
                        'Admin API responded ' . $response->status()
                    );
                }

                $body = $response->json();

                foreach ($body['data'] ?? [] as $bucket) {
                    foreach ($bucket['results'] ?? [] as $result) {
                        $spent += (float) ($result['amount']['value'] ?? 0);
                        $currency = strtoupper($result['amount']['currency'] ?? $currency);
                    }
                }

                $nextPage = ($body['has_more'] ?? false) ? ($body['next_page'] ?? null) : null;
                $params['page'] = $nextPage;
            } while ($nextPage);
        } catch (\Throwable $e) {
            Log::channel('aml')->error('OpenAI: costs request error', [
                'error' => $e->getMessage(),
            ]);

            return AiServiceBalance::failed($this->getCode(), $this->getName(), $e->getMessage());
        }

        return new AiServiceBalance(
            code: $this->getCode(),
            name: $this->getName(),
            spent: round($spent, 2),
            currency: $currency,
        );
    }

    private function fail(string $message, array $raw = [], ?string $prompt = null, ?array $request = null): array
    {
        return [
            'success' => false,
            'wallet_color' => null,
            'risk' => null,
            'confidence' => null,
            'summary' => null,
            'coinkyt' => null,
            'prompt' => $prompt,
            'request' => $request,
            'raw' => $raw,
            'message' => $message,
        ];
    }

    /**
     * Normalizes the nested per-provider assessment block.
     * Returns null if the AI omitted the block (provider absent from input).
     */
    private function extractServiceVerdict(mixed $verdict): ?array
    {
        if (!is_array($verdict)) {
            return null;
        }

        return [
            'wallet_color' => $verdict['wallet_color'] ?? null,
            'risk' => $verdict['risk'] ?? null,
            'confidence' => isset($verdict['confidence']) ? (int) $verdict['confidence'] : null,
            'summary' => AiAmlFormatter::plainSummary($verdict['summary'] ?? null),
        ];
    }
}
