<?php

declare(strict_types=1);

namespace App\Services\Aml\Export;

use App\Models\Aml\AmlAddress;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * Parameters of a single AML database export.
 *
 * Built from the admin form or CLI command arguments, then
 * immutable - the exporter must not change conditions mid-run.
 */
class AmlExportOptions
{
    /**
     * @param string[] $networks Network codes (AmlAddress::amlCodes()), empty array - all
     * @param string[] $sources Address sources, see AmlExportSources
     */
    public function __construct(
        public readonly array $networks = ['TRX'],
        public readonly array $sources = [AmlExportSources::TRANSACTIONS, AmlExportSources::REGISTRY],
        public readonly ?CarbonInterface $since = null,
        public readonly bool $withRaw = false,
        public readonly int $rawMinScore = 40,
        public readonly bool $gzip = false,
        public readonly int $chunk = 500,
        public readonly bool $allowScaleMismatch = false,
    ) {
    }

    public static function fromDefaults(array $overrides = []): self
    {
        $defaults = [
            'networks' => config('aml_export.networks', ['TRX']),
            'sources' => config('aml_export.sources', AmlExportSources::all()),
            'since' => null,
            'withRaw' => (bool) config('aml_export.with_raw', false),
            'rawMinScore' => (int) config('aml_export.raw_min_score', 40),
            'gzip' => false,
            'chunk' => (int) config('aml_export.chunk', 500),
            'allowScaleMismatch' => false,
        ];

        $values = array_merge($defaults, array_filter(
            $overrides,
            static fn($value) => !is_null($value)
        ));

        return new self(
            networks: self::normalizeNetworks($values['networks']),
            sources: self::normalizeSources($values['sources']),
            since: self::normalizeSince($values['since']),
            withRaw: (bool) $values['withRaw'],
            rawMinScore: (int) $values['rawMinScore'],
            gzip: (bool) $values['gzip'],
            chunk: max(1, (int) $values['chunk']),
            allowScaleMismatch: (bool) $values['allowScaleMismatch'],
        );
    }

    public function usesTransactions(): bool
    {
        return in_array(AmlExportSources::TRANSACTIONS, $this->sources, true);
    }

    public function usesRegistry(): bool
    {
        return in_array(AmlExportSources::REGISTRY, $this->sources, true);
    }

    /**
     * Blockchain types matching the selected networks.
     *
     * @return int[] Empty array - no network filter
     */
    public function blockchainTypes(): array
    {
        if ($this->networks === []) {
            return [];
        }

        $byCode = array_flip(AmlAddress::amlCodes());

        return array_values(array_filter(array_map(
            static fn(string $network) => $byCode[$network] ?? null,
            $this->networks
        )));
    }

    public function fileName(): string
    {
        $suffix = $this->since ? 'delta_' . $this->since->format('Ymd_His') : 'full';
        $name = sprintf('aml-export_%s_%s.jsonl', $suffix, now()->format('Y-m-d_H-i-s'));

        return $this->gzip ? $name . '.gz' : $name;
    }

    /**
     * @param string[]|string $networks
     * @return string[]
     */
    private static function normalizeNetworks(array|string $networks): array
    {
        if (is_string($networks)) {
            $networks = $networks === '' ? [] : explode(',', $networks);
        }

        $known = AmlAddress::amlCodes();

        $result = [];
        foreach ($networks as $network) {
            $code = strtoupper(trim((string) $network));

            if ($code === '' || $code === 'ALL') {
                return [];
            }

            if (!in_array($code, $known, true)) {
                throw new InvalidArgumentException("Unknown network: $code");
            }

            $result[$code] = $code;
        }

        return array_values($result);
    }

    /**
     * @param string[]|string $sources
     * @return string[]
     */
    private static function normalizeSources(array|string $sources): array
    {
        if (is_string($sources)) {
            $sources = $sources === '' ? [] : explode(',', $sources);
        }

        $known = AmlExportSources::all();

        $result = [];
        foreach ($sources as $source) {
            $code = strtolower(trim((string) $source));

            if ($code === '' ) {
                continue;
            }

            if (!in_array($code, $known, true)) {
                throw new InvalidArgumentException("Unknown address source: $code");
            }

            $result[$code] = $code;
        }

        if ($result === []) {
            throw new InvalidArgumentException('No address source selected');
        }

        // Fix the order so the export does not depend on checkbox order in the form
        return array_values(array_intersect($known, $result));
    }

    private static function normalizeSince(mixed $since): ?CarbonInterface
    {
        if ($since instanceof CarbonInterface) {
            return $since;
        }

        if (is_string($since) && trim($since) !== '') {
            return Carbon::parse($since);
        }

        return null;
    }
}
