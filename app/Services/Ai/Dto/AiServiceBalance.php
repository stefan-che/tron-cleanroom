<?php

declare(strict_types=1);

namespace App\Services\Ai\Dto;

use Carbon\CarbonInterface;

/**
 * Current account balance of an AI provider.
 *
 * Neither OpenAI nor Anthropic expose remaining credits via API - the Admin API
 * only reports spend for a period. So the balance is the last top-up
 * minus spend since that top-up; the amount and date are entered
 * in the provider card on each top-up.
 */
final class AiServiceBalance
{
    public function __construct(
        public readonly string $code,
        public readonly string $name,
        public readonly ?float $spent = null,
        public readonly ?float $topup = null,
        public readonly ?CarbonInterface $topupAt = null,
        public readonly ?float $threshold = null,
        public readonly string $currency = 'USD',
        public readonly ?string $error = null,
        public readonly bool $configured = true,
    ) {
    }

    /**
     * Spend could not be fetched: the key is set but the API returned an error. Needs attention.
     */
    public static function failed(string $code, string $name, string $error): self
    {
        return new self(code: $code, name: $name, error: $error);
    }

    /**
     * Balance monitoring is not configured yet (no admin key, no top-up entered,
     * no provider implementation). Not alerted - it is a missing setting, not a failure.
     */
    public static function notConfigured(string $code, string $name, string $reason): self
    {
        return new self(code: $code, name: $name, error: $reason, configured: false);
    }

    public function withTopup(?float $topup, ?CarbonInterface $topupAt, ?float $threshold): self
    {
        return new self(
            code: $this->code,
            name: $this->name,
            spent: $this->spent,
            topup: $topup,
            topupAt: $topupAt,
            threshold: $threshold,
            currency: $this->currency,
            error: $this->error,
            configured: $this->configured,
        );
    }

    public function isKnown(): bool
    {
        return $this->error === null && $this->spent !== null;
    }

    /**
     * Account balance. Null if no top-up is entered or spend is unknown.
     */
    public function amount(): ?float
    {
        if (!$this->isKnown() || $this->topup === null) {
            return null;
        }

        return round($this->topup - $this->spent, 2);
    }

    /**
     * Balance dropped below the threshold - time to top up.
     */
    public function isBelowThreshold(): bool
    {
        $amount = $this->amount();

        return $amount !== null
            && $this->threshold !== null
            && $amount < $this->threshold;
    }

    /**
     * Line for alerts and console output.
     */
    public function describe(): string
    {
        if ($this->error !== null) {
            return sprintf('%s: %s', $this->name, $this->error);
        }

        if ($this->topup === null) {
            return sprintf(
                '%s: spent %s %s (no top-up entered, balance unknown)',
                $this->name,
                $this->formatAmount($this->spent),
                $this->currency
            );
        }

        return sprintf(
            '%s: balance %s %s (topped up %s on %s, spent %s)',
            $this->name,
            $this->formatAmount($this->amount()),
            $this->currency,
            $this->formatAmount($this->topup),
            $this->topupAt?->format('d.m.Y') ?? '-',
            $this->formatAmount($this->spent)
        );
    }

    private function formatAmount(?float $value): string
    {
        return $value === null ? '-' : number_format($value, 2, '.', ' ');
    }
}
