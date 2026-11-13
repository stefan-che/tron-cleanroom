<?php

namespace App\Services\Ai\Contracts;

use App\Services\Ai\Dto\AiServiceBalance;
use Carbon\CarbonInterface;

interface AiAmlServiceInterface
{
    /**
     * Service code (matches AiService::*_CODE)
     */
    public function getCode(): string;

    /**
     * Service name
     */
    public function getName(): string;

    /**
     * Default model ID (can be overridden from the DB)
     */
    public function getDefaultModel(): string;

    /**
     * Analyzes AML results with AI.
     *
     * @param string $prompt System prompt (analysis rules)
     * @param array $data Input data (transactions, wallet statistics)
     * @param string|null $model Model ID (null - getDefaultModel() is used)
     * @param string|null $apiKey API key (null - taken from config/services)
     * @return array {
     *     success: bool,
     *     wallet_color: ?string,   // WHITE | YELLOW | GREY | BLACK
     *     risk: ?string,           // LOW | MEDIUM | HIGH
     *     confidence: ?int,        // 0-100
     *     summary: ?string,
     *     prompt: ?string,         // prompt sent to the provider (snapshot)
     *     request: ?array,         // full request log: { text, params, data }
     *     raw: array,              // full provider response
     *     message: ?string,        // error text when success=false
     * }
     */
    public function analyze(string $prompt, array $data, ?string $model = null, ?string $apiKey = null): array;

    /**
     * Provider spend since the given date via the provider's Admin API.
     *
     * Providers do not expose remaining credits via API, so the spent amount
     * is returned; AiServiceBalance computes the account balance as
     * "top-up minus spend". If there is no key or the API is unavailable -
     * AiServiceBalance::notConfigured() / ::failed().
     *
     * @param string|null $adminApiKey Admin API key (null - taken from config/services)
     * @param CarbonInterface|null $since Top-up date (null - start of the current month)
     */
    public function getSpentSince(?string $adminApiKey = null, ?CarbonInterface $since = null): AiServiceBalance;
}
