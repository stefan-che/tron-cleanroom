<?php

declare(strict_types=1);

namespace App\Services\Aml\Export;

use App\Models\Aml\AmlAddress;
use App\Models\Aml\AmlAddressCheck;
use App\Models\Wallet\TransactionAmlCheck;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Export of the accumulated AML address database in `aml-import/v1` format (JSONL).
 *
 * The file is meant for import into an external AML database; this is the export side only.
 *
 * Addresses come from two sources (see AmlExportSources); the main one is
 * senders of incoming transactions. The same address may have sent
 * several transfers and appear in both sources: for each AML service
 * only the most recent verdict is kept (format rule §4.5).
 *
 * The class knows nothing about HTTP or the admin panel: it passes lines to the given
 * callback, so it works the same in streamDownload and in the CLI command.
 */
class AmlDatabaseExporter
{
    /**
     * Upper bound of each supported scale.
     */
    private const array SCALE_MAX = [
        '0-1' => 1.0,
        '0-10' => 10.0,
        '0-100' => 100.0,
    ];

    /**
     * Tolerance for exceeding the scale bound: providers round, and a risk of
     * 100.0001 is no reason to treat the scale as wrong.
     */
    private const float SCALE_TOLERANCE = 0.001;

    /**
     * Signal groups of form B (`Wallet::formatSignals`).
     */
    private const array SIGNAL_GROUPS = ['low', 'middle', 'high'];

    /**
     * Number of addresses the export will contain.
     */
    public function count(AmlExportOptions $options): int
    {
        return count($this->addressList($options));
    }

    /**
     * Breakdown of completed verdicts per service,
     * shown to the operator before export so they can see what the file contains.
     *
     * Counted per source and summed: the same
     * service for one address may appear in both, so the number
     * is an upper estimate, not the exact file size.
     *
     * @return array<string, int>
     */
    public function checksByProvider(AmlExportOptions $options): array
    {
        $result = [];

        foreach ($this->checkCountQueries($options) as $query) {
            foreach ($query->pluck('total', 'code') as $code => $total) {
                $result[$code] = ($result[$code] ?? 0) + (int) $total;
            }
        }

        ksort($result);

        return $result;
    }

    /**
     * Number of addresses per network, counting each address once.
     *
     * The `aml_addresses` registry covers 26 networks, transactions only the one
     * the transfer arrived in. The breakdown shows the operator what they
     * miss when narrowing the export to TRX.
     *
     * @return array<string, int>
     */
    public function addressesByNetwork(AmlExportOptions $options): array
    {
        $codes = AmlAddress::amlCodes();
        $result = [];

        foreach ($this->addressList($options) as $entry) {
            // Count each address once: it is also a single line in the file
            $code = $codes[reset($entry['blockchain_types'])] ?? '—';
            $result[$code] = ($result[$code] ?? 0) + 1;
        }

        arsort($result);

        return $result;
    }

    /**
     * The manifest is the first line of the file.
     */
    public function manifest(AmlExportOptions $options, int $count): array
    {
        $manifest = [
            'format' => (string) config('aml_export.format', 'aml-import/v1'),
            'source' => (string) config('aml_export.source', 'tron-telegram-api'),
            'exported_at' => $this->toRfc3339(Carbon::now()),
            'count' => $count,
            'networks' => $options->networks !== [] ? $options->networks : array_values(AmlAddress::amlCodes()),
        ];

        if ($options->since) {
            $manifest['since'] = $this->toRfc3339($options->since);
        }

        return $manifest;
    }

    /**
     * Streaming export: `$write` is called for each finished JSONL line.
     *
     * @param callable(string $line): void $write
     */
    public function stream(AmlExportOptions $options, callable $write): AmlExportStats
    {
        $stats = new AmlExportStats();

        $addresses = $this->addressList($options);
        $write($this->encode($this->manifest($options, count($addresses))));

        foreach (array_chunk($addresses, $options->chunk, true) as $batch) {
            $checks = $this->checksForBatch($batch, $options);

            foreach ($batch as $key => $address) {
                $line = $this->buildLine($address, $checks[$key] ?? [], $options, $stats);

                if ($line === null) {
                    $stats->addressesSkipped++;
                    continue;
                }

                $stats->countAddress($line['network']);
                $write($this->encode($line));
            }
        }

        return $stats;
    }

    /**
     * Unique export addresses, keyed by the address itself.
     *
     * Deduplicated by address, not by the (address, network) pair. In the registry
     * uniqueness is `(blockchain_type, address)`, so one address is stored
     * once per network, which for an EVM address means six rows for six
     * chains. The receiver deduplicates by address alone (format §4.5) -
     * if we sent six lines, it would drop five together with their verdicts.
     * So the address appears once, with the verdicts of all its networks merged.
     *
     * The list is loaded into memory with one query. At expected volumes
     * (tens of thousands of addresses) that is a few megabytes, and in return
     * verdicts are fetched cheaply with `IN (...)` instead of rescanning the
     * transactions table for every batch.
     *
     * @return array<string, array{address: string, blockchain_types: int[]}>
     */
    public function addressList(AmlExportOptions $options): array
    {
        $queries = $this->addressQueries($options);

        if ($queries === []) {
            return [];
        }

        $query = array_shift($queries);
        foreach ($queries as $next) {
            $query->union($next);
        }

        $result = [];

        foreach ($query->get() as $row) {
            $address = trim((string) $row->address);
            $blockchainType = (int) $row->blockchain_type;

            if (!$this->isValidAddress($address, $blockchainType)) {
                continue;
            }

            if (!isset($result[$address])) {
                $result[$address] = ['address' => $address, 'blockchain_types' => []];
            }

            $result[$address]['blockchain_types'][$blockchainType] = $blockchainType;
        }

        foreach ($result as &$entry) {
            $entry['blockchain_types'] = array_values($entry['blockchain_types']);
        }
        unset($entry);

        ksort($result);

        return $result;
    }

    /**
     * Address queries for the selected sources.
     *
     * @return Builder[]
     */
    private function addressQueries(AmlExportOptions $options): array
    {
        $queries = [];

        if ($options->usesTransactions()) {
            $queries[] = $this->transactionAddressesQuery($options);
        }

        if ($options->usesRegistry()) {
            $queries[] = $this->registryAddressesQuery($options);
        }

        return $queries;
    }

    /**
     * Sender addresses of incoming transactions.
     *
     * The network is taken from the receiving wallet: `from_address` is an address on the same
     * network the transfer arrived on. Test wallets are excluded, otherwise
     * addresses from our own test runs would end up in someone else's risk database.
     */
    private function transactionAddressesQuery(AmlExportOptions $options): Builder
    {
        $query = DB::table('transactions as t')
            ->join('wallets as w', 'w.id', '=', 't.wallet_id')
            ->where(function (Builder $query) {
                $query->where('w.is_test', false)->orWhereNull('w.is_test');
            })
            ->where('t.from_address', '<>', '')
            ->whereNotNull('t.from_address')
            ->whereExists(function (Builder $query) use ($options) {
                $query->select(DB::raw(1))
                    ->from('transactions_aml_checks as c')
                    ->whereColumn('c.transaction_id', 't.id')
                    ->where('c.status', TransactionAmlCheck::STATUS_COMPLETED)
                    ->whereNull('c.deleted_at');

                if ($options->since) {
                    $query->where('c.updated_at', '>', $options->since);
                }
            })
            ->distinct()
            ->select('t.from_address as address', 'w.blockchain_type as blockchain_type');

        $types = $options->blockchainTypes();
        if ($types !== []) {
            $query->whereIn('w.blockchain_type', $types);
        }

        return $query;
    }

    /**
     * Addresses from the `aml_addresses` registry.
     */
    private function registryAddressesQuery(AmlExportOptions $options): Builder
    {
        $query = DB::table('aml_addresses as a')
            ->where(function (Builder $query) {
                $query->where('a.is_test', false)->orWhereNull('a.is_test');
            })
            ->whereExists(function (Builder $query) use ($options) {
                $query->select(DB::raw(1))
                    ->from('aml_addresses_checks as c')
                    ->whereColumn('c.aml_address_id', 'a.id')
                    ->where('c.status', AmlAddressCheck::STATUS_COMPLETED)
                    ->whereNull('c.deleted_at');

                if ($options->since) {
                    $query->where('c.updated_at', '>', $options->since);
                }
            })
            ->distinct()
            ->select('a.address as address', 'a.blockchain_type as blockchain_type');

        $types = $options->blockchainTypes();
        if ($types !== []) {
            $query->whereIn('a.blockchain_type', $types);
        }

        return $query;
    }

    /**
     * Per-source queries counting completed verdicts per service.
     *
     * @return Builder[]
     */
    private function checkCountQueries(AmlExportOptions $options): array
    {
        $queries = [];
        $types = $options->blockchainTypes();

        if ($options->usesTransactions()) {
            $query = DB::table('transactions_aml_checks as c')
                ->join('transactions as t', 't.id', '=', 'c.transaction_id')
                ->join('wallets as w', 'w.id', '=', 't.wallet_id')
                ->join('aml_services as s', 's.id', '=', 'c.service_id')
                ->where('c.status', TransactionAmlCheck::STATUS_COMPLETED)
                ->whereNull('c.deleted_at')
                ->where(function (Builder $query) {
                    $query->where('w.is_test', false)->orWhereNull('w.is_test');
                })
                ->groupBy('s.code')
                ->selectRaw('s.code as code, count(distinct t.from_address) as total');

            if ($types !== []) {
                $query->whereIn('w.blockchain_type', $types);
            }

            if ($options->since) {
                $query->where('c.updated_at', '>', $options->since);
            }

            $queries[] = $query;
        }

        if ($options->usesRegistry()) {
            $query = DB::table('aml_addresses_checks as c')
                ->join('aml_addresses as a', 'a.id', '=', 'c.aml_address_id')
                ->join('aml_services as s', 's.id', '=', 'c.aml_service_id')
                ->where('c.status', AmlAddressCheck::STATUS_COMPLETED)
                ->whereNull('c.deleted_at')
                ->where(function (Builder $query) {
                    $query->where('a.is_test', false)->orWhereNull('a.is_test');
                })
                ->groupBy('s.code')
                ->selectRaw('s.code as code, count(distinct a.address) as total');

            if ($types !== []) {
                $query->whereIn('a.blockchain_type', $types);
            }

            if ($options->since) {
                $query->where('c.updated_at', '>', $options->since);
            }

            $queries[] = $query;
        }

        return $queries;
    }

    /**
     * Verdicts for a batch of addresses from all selected sources, already merged
     * by the "latest checked_at wins" rule.
     *
     * @param array<string, array{address: string, blockchain_types: int[]}> $batch
     * @return array<string, array<string, array>> address -> provider -> verdict
     */
    private function checksForBatch(array $batch, AmlExportOptions $options): array
    {
        $addresses = array_values(array_unique(array_column($batch, 'address')));

        if ($addresses === []) {
            return [];
        }

        $result = [];

        if ($options->usesTransactions()) {
            $this->mergeRows($result, $this->transactionChecks($addresses, $options), AmlExportSources::TRANSACTIONS);
        }

        if ($options->usesRegistry()) {
            $this->mergeRows($result, $this->registryChecks($addresses, $options), AmlExportSources::REGISTRY);
        }

        return $result;
    }

    /**
     * AML check verdicts of transactions sent from these addresses.
     */
    private function transactionChecks(array $addresses, AmlExportOptions $options): iterable
    {
        $columns = [
            't.from_address as address',
            'w.blockchain_type as blockchain_type',
            's.code as provider',
            'c.risk_score as score',
            'c.check_finish_at',
            'c.updated_at',
            'c.created_at',
            'c.signals',
            'c.uid',
            'c.file_report_link as report_url',
            DB::raw('null as note'),
        ];

        if ($options->withRaw) {
            $columns[] = 'c.response as raw';
        }

        return DB::table('transactions_aml_checks as c')
            ->join('transactions as t', 't.id', '=', 'c.transaction_id')
            ->join('wallets as w', 'w.id', '=', 't.wallet_id')
            ->join('aml_services as s', 's.id', '=', 'c.service_id')
            ->whereIn('t.from_address', $addresses)
            ->where('c.status', TransactionAmlCheck::STATUS_COMPLETED)
            ->whereNull('c.deleted_at')
            ->where(function (Builder $query) {
                $query->where('w.is_test', false)->orWhereNull('w.is_test');
            })
            ->select($columns)
            ->cursor();
    }

    /**
     * Verdicts from the checked-address registry.
     */
    private function registryChecks(array $addresses, AmlExportOptions $options): iterable
    {
        $columns = [
            'a.address as address',
            'a.blockchain_type as blockchain_type',
            's.code as provider',
            'c.aml_risk_score as score',
            'c.check_finish_at',
            'c.updated_at',
            'c.created_at',
            'c.signals',
            'c.uid',
            'c.aml_file_report_link as report_url',
            'a.comment as note',
        ];

        if ($options->withRaw) {
            $columns[] = 'c.response as raw';
        }

        return DB::table('aml_addresses_checks as c')
            ->join('aml_addresses as a', 'a.id', '=', 'c.aml_address_id')
            ->join('aml_services as s', 's.id', '=', 'c.aml_service_id')
            ->whereIn('a.address', $addresses)
            ->where('c.status', AmlAddressCheck::STATUS_COMPLETED)
            ->whereNull('c.deleted_at')
            ->where(function (Builder $query) {
                $query->where('a.is_test', false)->orWhereNull('a.is_test');
            })
            ->select($columns)
            ->cursor();
    }

    /**
     * Merges verdicts into a shared map, keeping the most recent one per
     * (address, network, service).
     *
     * @param array<string, array<string, array>> $result
     */
    private function mergeRows(array &$result, iterable $rows, string $source): void
    {
        foreach ($rows as $row) {
            $provider = strtolower(trim((string) $row->provider));

            if ($provider === '') {
                continue;
            }

            $key = trim((string) $row->address);
            $checkedAt = $this->rowCheckedAt($row);
            $current = $result[$key][$provider] ?? null;

            // The verdict with the latest checked_at wins, regardless of
            // its source or network (format §4.5)
            if ($current !== null && $checkedAt <= $current['checked_at']) {
                continue;
            }

            $result[$key][$provider] = [
                'provider' => $provider,
                'source' => $source,
                'blockchain_type' => (int) $row->blockchain_type,
                'score' => $row->score,
                'checked_at' => $checkedAt,
                'signals' => $this->decodeJson($row->signals ?? null),
                'external_uid' => $row->uid ?? null,
                'report_url' => $row->report_url ?? null,
                'note' => $row->note ?? null,
                'raw' => $this->decodeJson($row->raw ?? null),
            ];
        }
    }

    /**
     * Address line in `aml-import/v1` format.
     *
     * @param array{address: string, blockchain_types: int[]} $address
     * @param array<string, array> $checks Verdicts by provider
     * @return array|null null - no usable verdict, the address is skipped
     */
    private function buildLine(
        array $address,
        array $checks,
        AmlExportOptions $options,
        AmlExportStats $stats
    ): ?array {
        $exported = [];
        $maxNormalized = 0.0;
        $note = '';

        ksort($checks);

        foreach ($checks as $provider => $check) {
            if ($note === '') {
                $note = trim((string) ($check['note'] ?? ''));
            }

            $result = $this->buildCheck($provider, $check, $options, $stats);

            if ($result === null) {
                continue;
            }

            $maxNormalized = max($maxNormalized, $result['normalized']);
            $exported[] = $result;
        }

        if ($exported === []) {
            return null;
        }

        $blockchainType = $this->resolveNetwork($address, $exported, $stats);
        $withRaw = $options->withRaw && $maxNormalized >= $options->rawMinScore;

        $line = [
            'address' => $address['address'],
            'network' => AmlAddress::amlCodes()[$blockchainType] ?? null,
            'checks' => array_map(
                fn(array $check) => $this->formatCheck($check, $withRaw),
                $exported
            ),
        ];

        if ($note !== '') {
            $line['note'] = $note;
        }

        foreach ($exported as $check) {
            $stats->countCheck($check['provider'], $check['source']);
        }

        return $line;
    }

    /**
     * Network of the line when the address appears on several chains.
     *
     * This happens with EVM addresses: the same `0x...` lives on ETH, BSC,
     * Polygon and others, with a separate registry record for each.
     * The file has one line, so the network comes from the most recent verdict -
     * the chain we have the freshest data on. Verdicts
     * of the other networks are kept: there is no reason to lose them for a network label,
     * and risk is taken from the worst one.
     *
     * @param array{address: string, blockchain_types: int[]} $address
     * @param array[] $checks Parsed verdicts
     */
    private function resolveNetwork(array $address, array $checks, AmlExportStats $stats): int
    {
        $networks = $address['blockchain_types'];

        if (count($networks) > 1) {
            $stats->multiNetwork++;
        }

        $best = null;
        $bestAt = null;

        foreach ($checks as $check) {
            if ($bestAt === null || $check['checked_at'] > $bestAt) {
                $bestAt = $check['checked_at'];
                $best = $check['blockchain_type'];
            }
        }

        return $best ?? (int) reset($networks);
    }

    /**
     * Parses one verdict. Returns null if the verdict cannot be exported
     * without distortion; the reason goes to the counters.
     */
    private function buildCheck(
        string $provider,
        array $check,
        AmlExportOptions $options,
        AmlExportStats $stats
    ): ?array {
        $score = $check['score'];

        if (is_null($score) || !is_numeric($score) || (float) $score < 0) {
            $stats->skipCheck('no_score', $provider);
            return null;
        }

        $score = (float) $score;
        $scale = $this->scaleFor($provider);

        if ($scale === null) {
            $stats->skipCheck('unknown_scale', $provider, $score);
            return null;
        }

        if ($score > self::SCALE_MAX[$scale] + self::SCALE_TOLERANCE) {
            $stats->skipCheck('scale_mismatch', $provider, $score);

            if (!$options->allowScaleMismatch) {
                return null;
            }

            // Operator force: instead of rejecting, use the smallest scale
            // that covers the value. `risk_score_normalize` in the models makes
            // the same guess, but it is still a guess - the proper fix is
            // to correct `aml_export.scales`.
            $scale = $this->fitScale($score);

            if ($scale === null) {
                return null;
            }
        }

        if ($check['checked_at'] === null) {
            $stats->skipCheck('no_date', $provider);
            return null;
        }

        return [
            'provider' => $provider,
            'source' => $check['source'],
            'blockchain_type' => $check['blockchain_type'],
            'score' => round($score, 3),
            'scale' => $scale,
            'normalized' => $this->normalize($score, $scale),
            'checked_at' => $this->toRfc3339($check['checked_at']),
            'signals' => $this->normalizeSignals($check['signals']),
            'external_uid' => $check['external_uid'],
            'report_url' => $check['report_url'],
            'raw' => $check['raw'],
        ];
    }

    /**
     * Converts a parsed verdict to a format line: empty fields
     * are omitted, `raw` only when explicitly requested.
     */
    private function formatCheck(array $check, bool $withRaw): array
    {
        $result = [
            'provider' => $check['provider'],
            'score' => $check['score'],
            'scale' => $check['scale'],
            'checked_at' => $check['checked_at'],
        ];

        if ($check['signals'] !== []) {
            $result['signals'] = $check['signals'];
        }

        if (!empty($check['external_uid'])) {
            $result['external_uid'] = $check['external_uid'];
        }

        if (!empty($check['report_url'])) {
            $result['report_url'] = $check['report_url'];
        }

        if ($withRaw && !empty($check['raw'])) {
            $result['raw'] = $check['raw'];
        }

        return $result;
    }

    /**
     * Signals are converted to format form A - a flat map `code => share 0..1`.
     *
     * Our database stores them in form B (`Wallet::formatSignals`): groups
     * low/middle/high, values multiplied by 10, and some services use
     * human-readable keys. The receiver accepts form B but discards the grouping
     * and computes its own - so we flatten it on our side rather than
     * send over data we have muddled ourselves.
     *
     * @return array<string, float>
     */
    public function normalizeSignals(?array $signals): array
    {
        if (empty($signals)) {
            return [];
        }

        $flat = [];

        foreach ($signals as $key => $value) {
            if (is_array($value) && in_array(strtolower((string) $key), self::SIGNAL_GROUPS, true)) {
                foreach ($value as $signal => $share) {
                    $this->putSignal($flat, (string) $signal, $share, scaled: true);
                }
                continue;
            }

            $this->putSignal($flat, (string) $key, $value);
        }

        ksort($flat);

        return $flat;
    }

    /**
     * @param array<string, float> $flat
     * @param bool $scaled Value from form B - a share multiplied by 10
     *                     (`Wallet::formatSignals`). Determined by structure,
     *                     not by magnitude: 1.0 inside a group is 10%,
     *                     while in a flat map it is 100%.
     */
    private function putSignal(array &$flat, string $signal, mixed $value, bool $scaled = false): void
    {
        if (!is_numeric($value)) {
            return;
        }

        $share = (float) $value;

        if ($scaled) {
            $share /= 10;
        }

        $share = round(min(1.0, max(0.0, $share)), 4);

        if ($share <= 0) {
            return;
        }

        $code = $this->signalCode($signal);

        if ($code === '') {
            return;
        }

        // The same signal may appear in several groups - take the larger weight.
        $flat[$code] = max($flat[$code] ?? 0.0, $share);
    }

    /**
     * Machine code of a signal: `Dark Market` and `dark_market` are the same.
     */
    private function signalCode(string $signal): string
    {
        $code = mb_strtolower(trim($signal));
        $code = preg_replace('/[\s\-]+/u', '_', $code);
        $code = preg_replace('/[^a-z0-9_]/u', '', (string) $code);

        return trim((string) $code, '_');
    }

    /**
     * The address must match its network's format.
     *
     * `transactions.from_address` comes from the node response and in test
     * runs contains placeholders - such junk does not belong in someone else's risk database.
     */
    private function isValidAddress(string $address, int $blockchainType): bool
    {
        if ($address === '') {
            return false;
        }

        $regexp = AmlAddress::blockChainTypesRegexp()[$blockchainType] ?? null;

        return $regexp === null || (bool) preg_match($regexp, $address);
    }

    /**
     * Service scale from config. Null - scale not declared.
     */
    private function scaleFor(string $provider): ?string
    {
        $scale = config("aml_export.scales.$provider")
            ?? config('aml_export.default_scale');

        return isset(self::SCALE_MAX[$scale]) ? $scale : null;
    }

    /**
     * Smallest scale that fits the value.
     */
    private function fitScale(float $score): ?string
    {
        foreach (self::SCALE_MAX as $scale => $max) {
            if ($score <= $max + self::SCALE_TOLERANCE) {
                return $scale;
            }
        }

        return null;
    }

    /**
     * Normalization to 0..100 using the same rules the receiver applies (spec §4.3).
     * Used only on our side: for the `raw_min_score` threshold and the summary.
     */
    private function normalize(float $score, string $scale): float
    {
        $normalized = match ($scale) {
            '0-1' => $score * 100,
            '0-10' => $score * 10,
            default => $score,
        };

        return min(100.0, max(0.0, round($normalized)));
    }

    private function rowCheckedAt(object $row): ?Carbon
    {
        foreach (['check_finish_at', 'updated_at', 'created_at'] as $field) {
            $value = $row->{$field} ?? null;

            if (!empty($value)) {
                return Carbon::parse($value);
            }
        }

        return null;
    }

    private function decodeJson(mixed $value): ?array
    {
        if (is_array($value)) {
            return $value;
        }

        if (!is_string($value) || $value === '') {
            return null;
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function toRfc3339(\DateTimeInterface $date): string
    {
        return Carbon::instance($date)->utc()->format('Y-m-d\TH:i:s\Z');
    }

    /**
     * JSONL line: one object, without escaping slashes or unicode -
     * addresses and venue names must stay human-readable.
     */
    private function encode(array $data): string
    {
        return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    }
}
