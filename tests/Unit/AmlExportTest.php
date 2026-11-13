<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Aml\AmlAddress;
use App\Services\Aml\Export\AmlDatabaseExporter;
use App\Services\Aml\Export\AmlExportSources;
use App\Services\Aml\Export\AmlExportStats;
use App\Services\Aml\Export\AmlExportOptions;
use App\Services\Aml\Export\AmlExportWriter;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * AML database export tests that need no database: option parsing,
 * normalizing signals to form A of aml-import/v1, the gzip writer.
 */
class AmlExportTest extends TestCase
{
    private AmlDatabaseExporter $exporter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->exporter = new AmlDatabaseExporter();
    }

    public function test_signals_form_b_flattened_to_shares(): void
    {
        $result = $this->exporter->normalizeSignals([
            'high' => ['Mixer' => 4.2],
            'low' => ['Exchange' => 1.1],
            'middle' => [],
        ]);

        $this->assertSame(['exchange' => 0.11, 'mixer' => 0.42], $result);
    }

    public function test_signals_with_machine_keys_normalized_the_same_way(): void
    {
        $human = $this->exporter->normalizeSignals(['high' => ['Dark Market' => 4.2]]);
        $machine = $this->exporter->normalizeSignals(['high' => ['dark_market' => 4.2]]);

        $this->assertSame($human, $machine);
        $this->assertSame(['dark_market' => 0.42], $human);
    }

    public function test_flat_signals_are_accepted_as_is(): void
    {
        $result = $this->exporter->normalizeSignals(['mixer' => 0.42]);

        $this->assertSame(['mixer' => 0.42], $result);
    }

    public function test_zero_signals_are_dropped(): void
    {
        $result = $this->exporter->normalizeSignals([
            'high' => ['Mixer' => 0],
            'low' => ['Exchange' => 0.0],
        ]);

        $this->assertSame([], $result);
    }

    public function test_share_is_clamped_to_one(): void
    {
        $result = $this->exporter->normalizeSignals(['high' => ['Mixer' => 15]]);

        $this->assertSame(['mixer' => 1.0], $result);
    }

    public function test_same_signal_in_two_groups_keeps_the_larger_share(): void
    {
        $result = $this->exporter->normalizeSignals([
            'low' => ['Mixer' => 1.0],
            'high' => ['Mixer' => 4.2],
        ]);

        $this->assertSame(['mixer' => 0.42], $result);
    }

    public function test_empty_signals_give_empty_map(): void
    {
        $this->assertSame([], $this->exporter->normalizeSignals(null));
        $this->assertSame([], $this->exporter->normalizeSignals([]));
    }

    public function test_line_matches_aml_import_v1(): void
    {
        $line = $this->buildLine([
            'amlbot' => $this->check(0.85, ['high' => ['Mixer' => 4.2]], uid: 'b3f1'),
            'coinkyt' => $this->check(72.0, ['low' => ['Exchange' => 1.1]]),
        ]);

        $this->assertSame('TXk8vTestAddress', $line['address']);
        $this->assertSame('TRX', $line['network']);
        $this->assertSame('comment', $line['note']);

        $this->assertSame([
            'provider' => 'amlbot',
            'score' => 0.85,
            'scale' => '0-1',
            'checked_at' => '2026-08-01T10:00:00Z',
            'signals' => ['mixer' => 0.42],
            'external_uid' => 'b3f1',
            'report_url' => 'https://example.test/report.pdf',
        ], $line['checks'][0]);

        $this->assertSame('coinkyt', $line['checks'][1]['provider']);
        $this->assertSame('0-100', $line['checks'][1]['scale']);
        $this->assertSame(72.0, $line['checks'][1]['score']);

        // raw is not exported by default: it inflates the file by an order of magnitude
        $this->assertArrayNotHasKey('raw', $line['checks'][0]);
    }

    public function test_check_beyond_declared_scale_is_dropped(): void
    {
        // amlbot is declared as 0-1: a value of 8.5 means the scale is off,
        // and it must not be exported silently: risk 85 would go out as 8.5
        $stats = new AmlExportStats();

        $line = $this->buildLine(['amlbot' => $this->check(8.5)], stats: $stats);

        $this->assertNull($line);
        $this->assertTrue($stats->hasScaleMismatch());
        $this->assertSame(8.5, $stats->scaleMismatchMax['amlbot']);
    }

    public function test_scale_mismatch_can_be_forced_to_fitting_scale(): void
    {
        $line = $this->buildLine(
            ['amlbot' => $this->check(8.5)],
            AmlExportOptions::fromDefaults(['allowScaleMismatch' => true])
        );

        $this->assertSame('0-10', $line['checks'][0]['scale']);
        $this->assertSame(8.5, $line['checks'][0]['score']);
    }

    public function test_service_without_declared_scale_is_dropped(): void
    {
        $stats = new AmlExportStats();

        $this->assertNull($this->buildLine(['unknown_service' => $this->check(42.0)], stats: $stats));
        $this->assertSame(1, $stats->checksSkipped['unknown_scale']);
    }

    public function test_check_without_score_is_dropped(): void
    {
        $stats = new AmlExportStats();

        $this->assertNull($this->buildLine(['amlbot' => $this->check(null)], stats: $stats));
        $this->assertSame(1, $stats->checksSkipped['no_score']);
    }

    public function test_merge_keeps_the_freshest_verdict_per_service(): void
    {
        // one address may have sent several transfers and be in both
        // sources; one, most recent verdict per service is kept
        $merged = $this->merge([
            $this->row('TXk8vTestAddress', 'amlbot', 0.10, '2026-08-01 10:00:00'),
            $this->row('TXk8vTestAddress', 'amlbot', 0.90, '2026-08-05 10:00:00'),
            $this->row('TXk8vTestAddress', 'amlbot', 0.50, '2026-08-03 10:00:00'),
        ]);

        $checks = $merged['TXk8vTestAddress'];

        $this->assertCount(1, $checks);
        $this->assertSame(0.90, $checks['amlbot']['score']);
        $this->assertTrue($checks['amlbot']['checked_at']->equalTo(Carbon::parse('2026-08-05 10:00:00')));
    }

    public function test_registry_verdict_wins_over_older_transaction_one(): void
    {
        $merged = $this->merge(
            [$this->row('TXk8vTestAddress', 'amlbot', 0.10, '2026-08-01 10:00:00')],
            AmlExportSources::TRANSACTIONS
        );

        $this->mergeInto(
            $merged,
            [$this->row('TXk8vTestAddress', 'amlbot', 0.90, '2026-08-05 10:00:00')],
            AmlExportSources::REGISTRY
        );

        $check = $merged['TXk8vTestAddress']['amlbot'];

        $this->assertSame(0.90, $check['score']);
        $this->assertSame(AmlExportSources::REGISTRY, $check['source']);
    }

    public function test_verdicts_of_different_services_are_kept_side_by_side(): void
    {
        $merged = $this->merge([
            $this->row('TXk8vTestAddress', 'amlbot', 0.85, '2026-08-01 10:00:00'),
            $this->row('TXk8vTestAddress', 'coinkyt', 72.0, '2026-08-02 10:00:00'),
        ]);

        $this->assertSame(['amlbot', 'coinkyt'], array_keys($merged['TXk8vTestAddress']));
    }

    public function test_source_of_each_verdict_is_counted(): void
    {
        $stats = new AmlExportStats();

        $this->buildLine([
            'amlbot' => $this->check(0.85, source: AmlExportSources::TRANSACTIONS),
            'coinkyt' => $this->check(72.0, source: AmlExportSources::REGISTRY),
        ], stats: $stats);

        $this->assertSame(
            [AmlExportSources::TRANSACTIONS => 1, AmlExportSources::REGISTRY => 1],
            $stats->bySource
        );
    }

    public function test_raw_is_included_only_above_the_threshold(): void
    {
        $options = AmlExportOptions::fromDefaults(['withRaw' => true, 'rawMinScore' => 40]);

        $risky = $this->buildLine(['amlbot' => $this->check(0.85)], $options);
        $this->assertSame(['ok' => true], $risky['checks'][0]['raw']);

        $clean = $this->buildLine(['amlbot' => $this->check(0.10)], $options);
        $this->assertArrayNotHasKey('raw', $clean['checks'][0]);
    }

    public function test_same_address_from_both_tables_gives_one_line(): void
    {
        // the address sent a transfer (transactions) and is also in the registry;
        // in the file it is one line, otherwise the receiver would collapse it itself,
        // dropping half of the verdicts
        $merged = $this->merge(
            [$this->row('TXk8vTestAddress', 'amlbot', 0.85, '2026-08-01 10:00:00')],
            AmlExportSources::TRANSACTIONS
        );

        $this->mergeInto(
            $merged,
            [$this->row('TXk8vTestAddress', 'coinkyt', 72.0, '2026-08-02 10:00:00')],
            AmlExportSources::REGISTRY
        );

        $this->assertSame(['TXk8vTestAddress'], array_keys($merged));
        $this->assertSame(['amlbot', 'coinkyt'], array_keys($merged['TXk8vTestAddress']));

        $line = $this->buildLine($merged['TXk8vTestAddress']);
        $this->assertCount(2, $line['checks']);
    }

    public function test_evm_address_in_several_networks_gives_one_line(): void
    {
        // the registry is unique on (blockchain_type, address): one 0x address
        // is stored as a separate record per EVM chain
        $merged = $this->merge([
            $this->row('0xTest', 'amlbot', 0.10, '2026-08-01 10:00:00', AmlAddress::BLOCKCHAIN_TYPE_ETH),
            $this->row('0xTest', 'coinkyt', 72.0, '2026-08-05 10:00:00', AmlAddress::BLOCKCHAIN_TYPE_BSC),
        ]);

        $this->assertSame(['0xTest'], array_keys($merged));

        $stats = new AmlExportStats();
        $line = $this->buildLine(
            $merged['0xTest'],
            stats: $stats,
            address: [
                'address' => '0xTest',
                'blockchain_types' => [AmlAddress::BLOCKCHAIN_TYPE_ETH, AmlAddress::BLOCKCHAIN_TYPE_BSC],
            ],
        );

        // the network comes from the most recent verdict; verdicts of both are kept
        $this->assertSame('BSC', $line['network']);
        $this->assertCount(2, $line['checks']);
        $this->assertSame(1, $stats->multiNetwork);
    }

    public function test_addresses_are_counted_per_network(): void
    {
        $stats = new AmlExportStats();

        $stats->countAddress('TRX');
        $stats->countAddress('TRX');
        $stats->countAddress('ETH');

        $this->assertSame(3, $stats->addresses);
        $this->assertSame(['TRX' => 2, 'ETH' => 1], $stats->byNetwork);
    }

    public function test_junk_from_address_is_rejected(): void
    {
        $isValid = new \ReflectionMethod($this->exporter, 'isValidAddress');
        $trc20 = AmlAddress::BLOCKCHAIN_TYPE_TRC_20;

        // test runs put placeholders into transactions.from_address
        $this->assertFalse($isValid->invoke($this->exporter, 'fake_from_address_555', $trc20));
        $this->assertFalse($isValid->invoke($this->exporter, '', $trc20));
        $this->assertFalse($isValid->invoke($this->exporter, '0xdAC17F958D2ee523a2206206994597C13D831ec7', $trc20));
        $this->assertTrue($isValid->invoke($this->exporter, 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t', $trc20));
    }

    public function test_sources_default_to_both(): void
    {
        $options = AmlExportOptions::fromDefaults();

        $this->assertSame(AmlExportSources::all(), $options->sources);
        $this->assertTrue($options->usesTransactions());
        $this->assertTrue($options->usesRegistry());
    }

    public function test_sources_can_be_narrowed_to_transactions(): void
    {
        $options = AmlExportOptions::fromDefaults(['sources' => 'transactions']);

        $this->assertTrue($options->usesTransactions());
        $this->assertFalse($options->usesRegistry());
    }

    public function test_unknown_source_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        AmlExportOptions::fromDefaults(['sources' => 'wallets']);
    }

    public function test_empty_source_list_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        AmlExportOptions::fromDefaults(['sources' => ['']]);
    }

    public function test_networks_are_resolved_to_blockchain_types(): void
    {
        $options = AmlExportOptions::fromDefaults(['networks' => 'TRX,BTC']);

        $this->assertSame(['TRX', 'BTC'], $options->networks);
        $this->assertSame([1, 2], $options->blockchainTypes());
    }

    public function test_all_networks_mean_no_filter(): void
    {
        $options = AmlExportOptions::fromDefaults(['networks' => 'all']);

        $this->assertSame([], $options->networks);
        $this->assertSame([], $options->blockchainTypes());
    }

    public function test_unknown_network_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        AmlExportOptions::fromDefaults(['networks' => 'DOGECOIN']);
    }

    public function test_file_name_marks_delta_and_gzip(): void
    {
        $full = AmlExportOptions::fromDefaults();
        $this->assertStringContainsString('_full_', $full->fileName());
        $this->assertStringEndsWith('.jsonl', $full->fileName());

        $delta = AmlExportOptions::fromDefaults([
            'since' => '2026-08-01 10:00:00',
            'gzip' => true,
        ]);
        $this->assertStringContainsString('delta_20260801_100000', $delta->fileName());
        $this->assertStringEndsWith('.jsonl.gz', $delta->fileName());
    }

    public function test_writer_produces_readable_gzip(): void
    {
        $buffer = '';
        $writer = new AmlExportWriter(
            sink: static function (string $chunk) use (&$buffer) {
                $buffer .= $chunk;
            },
            gzip: true,
        );

        $writer->write("{\"a\":1}\n");
        $writer->write("{\"a\":2}\n");
        $writer->finish();

        $this->assertSame("{\"a\":1}\n{\"a\":2}\n", gzdecode($buffer));
    }

    /**
     * Export address. The format line can be checked without a database:
     * `buildLine` works with already collected verdicts.
     */
    private function address(array $blockchainTypes = [AmlAddress::BLOCKCHAIN_TYPE_TRC_20]): array
    {
        return [
            'address' => 'TXk8vTestAddress',
            'blockchain_types' => $blockchainTypes,
        ];
    }

    /**
     * A verdict in the shape produced by the source merge.
     */
    private function check(
        ?float $score,
        ?array $signals = null,
        ?string $uid = null,
        string $checkedAt = '2026-08-01 10:00:00',
        string $source = AmlExportSources::TRANSACTIONS,
        ?string $note = 'comment',
    ): array {
        return [
            'source' => $source,
            'blockchain_type' => AmlAddress::BLOCKCHAIN_TYPE_TRC_20,
            'score' => $score,
            'checked_at' => Carbon::parse($checkedAt),
            'signals' => $signals,
            'external_uid' => $uid,
            'report_url' => 'https://example.test/report.pdf',
            'note' => $note,
            'raw' => ['ok' => true],
        ];
    }

    /**
     * A result row in the shape returned by the verdicts query.
     */
    private function row(
        string $address,
        string $provider,
        ?float $score,
        string $checkedAt,
        int $blockchainType = AmlAddress::BLOCKCHAIN_TYPE_TRC_20,
    ): object {
        return (object) [
            'address' => $address,
            'blockchain_type' => $blockchainType,
            'provider' => $provider,
            'score' => $score,
            'check_finish_at' => $checkedAt,
            'updated_at' => null,
            'created_at' => null,
            'signals' => null,
            'uid' => null,
            'report_url' => null,
            'note' => null,
            'raw' => null,
        ];
    }

    /**
     * @param object[] $rows
     * @return array<string, array<string, array>>
     */
    private function merge(array $rows, string $source = AmlExportSources::TRANSACTIONS): array
    {
        $result = [];
        $this->mergeInto($result, $rows, $source);

        return $result;
    }

    /**
     * @param object[] $rows
     */
    private function mergeInto(array &$result, array $rows, string $source): void
    {
        $method = new \ReflectionMethod($this->exporter, 'mergeRows');
        $method->invokeArgs($this->exporter, [&$result, $rows, $source]);
    }

    /**
     * @param array<string, array> $checks Verdicts per provider
     */
    private function buildLine(
        array $checks,
        ?AmlExportOptions $options = null,
        ?AmlExportStats $stats = null,
        ?array $address = null,
    ): ?array {
        $method = new \ReflectionMethod($this->exporter, 'buildLine');

        return $method->invoke(
            $this->exporter,
            $address ?? $this->address(),
            $checks,
            $options ?? AmlExportOptions::fromDefaults(),
            $stats ?? new AmlExportStats()
        );
    }

    public function test_writer_without_gzip_passes_lines_through(): void
    {
        $buffer = '';
        $writer = new AmlExportWriter(
            sink: static function (string $chunk) use (&$buffer) {
                $buffer .= $chunk;
            },
        );

        $writer->write("line\n");
        $writer->finish();

        $this->assertSame("line\n", $buffer);
    }
}
