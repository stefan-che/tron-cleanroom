<?php

declare(strict_types=1);

namespace App\Services\Aml\Export;

/**
 * Export counters: how many addresses and verdicts went into the file and what was
 * dropped. The report ensures a misdeclared scale or network
 * does not go unnoticed - a risk silently exported 10x too low
 * looks like a clean address on the receiving side.
 */
class AmlExportStats
{
    /** Addresses written to the file */
    public int $addresses = 0;

    /** Service verdicts written to the file */
    public int $checks = 0;

    /** Addresses skipped (no checks left after rejection) */
    public int $addressesSkipped = 0;

    /**
     * Rejected checks by reason: unknown_scale | scale_mismatch |
     * no_score | no_date
     *
     * @var array<string, int>
     */
    public array $checksSkipped = [];

    /**
     * Verdicts written, by provider.
     *
     * @var array<string, int>
     */
    public array $byProvider = [];

    /**
     * Verdicts written, by source (AmlExportSources).
     * Shows how much of the database comes from transactions vs. the registry.
     *
     * @var array<string, int>
     */
    public array $bySource = [];

    /**
     * Addresses seen on more than one network (usually EVM addresses
     * stored in the registry as a separate record per chain). Such an
     * address is written as one line - the receiver deduplicates by address.
     */
    public int $multiNetwork = 0;

    /**
     * Addresses written, by network.
     *
     * @var array<string, int>
     */
    public array $byNetwork = [];

    /**
     * Examples of values outside the declared scale: provider -> maximum
     * value seen. Use them to tune `aml_export.scales`.
     *
     * @var array<string, float>
     */
    public array $scaleMismatchMax = [];

    public function countAddress(?string $network): void
    {
        $this->addresses++;

        $code = $network ?? '—';
        $this->byNetwork[$code] = ($this->byNetwork[$code] ?? 0) + 1;
    }

    public function countCheck(string $provider, string $source): void
    {
        $this->checks++;
        $this->byProvider[$provider] = ($this->byProvider[$provider] ?? 0) + 1;
        $this->bySource[$source] = ($this->bySource[$source] ?? 0) + 1;
    }

    public function skipCheck(string $reason, ?string $provider = null, ?float $score = null): void
    {
        $this->checksSkipped[$reason] = ($this->checksSkipped[$reason] ?? 0) + 1;

        if ($reason === 'scale_mismatch' && $provider !== null && $score !== null) {
            $this->scaleMismatchMax[$provider] = max($this->scaleMismatchMax[$provider] ?? 0.0, $score);
        }
    }

    public function hasScaleMismatch(): bool
    {
        return ($this->checksSkipped['scale_mismatch'] ?? 0) > 0;
    }

    public function totalSkippedChecks(): int
    {
        return array_sum($this->checksSkipped);
    }

    public function toArray(): array
    {
        return [
            'addresses' => $this->addresses,
            'checks' => $this->checks,
            'addresses_skipped' => $this->addressesSkipped,
            'checks_skipped' => $this->checksSkipped,
            'by_provider' => $this->byProvider,
            'by_source' => $this->bySource,
            'by_network' => $this->byNetwork,
            'multi_network' => $this->multiNetwork,
            'scale_mismatch_max' => $this->scaleMismatchMax,
        ];
    }
}
