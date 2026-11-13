<?php

declare(strict_types=1);

namespace App\Services\Aml\Dto;

/**
 * Wallet color computed from a single AML service's data.
 *
 * Computed in PHP with the same rules as the AI prompt (docs/aml-prompt.md),
 * but per service - to show how each check rates the transaction
 * before the AI merges them into one verdict.
 */
final class AmlColorVerdict
{
    public function __construct(
        /** Null - the service returned no data the rules can be applied to. */
        public readonly ?string $color,
        public readonly string $reason,
        public readonly float $dangerShare,
        public readonly float $middleShare,
        public readonly float $sanctionShare,
        public readonly ?float $riskScore,
        public readonly ?float $dangerUsd = null,
        public readonly ?float $sanctionUsd = null,
    ) {
    }

    /**
     * Short tooltip breakdown: the metrics the color was chosen by.
     */
    public function describe(): string
    {
        $parts = [
            'D=' . $this->format($this->dangerShare) . '% (dangerous sources)',
            'M=' . $this->format($this->middleShare) . '% (suspicious)',
        ];

        if ($this->sanctionShare > 0) {
            $parts[] = 'S=' . $this->format($this->sanctionShare) . '% (sanctions)';
        }

        if ($this->riskScore !== null) {
            $parts[] = 'R=' . $this->format($this->riskScore) . ' (risk score)';
        }

        if ($this->dangerUsd !== null && $this->dangerUsd > 0) {
            $parts[] = 'D_usd=$' . $this->format($this->dangerUsd);
        }

        if ($this->color === null) {
            return $this->reason;
        }

        return implode(' · ', $parts) . '. ' . $this->reason;
    }

    private function format(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
