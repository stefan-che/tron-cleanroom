<?php
/**
 * Submit transactions from the static array for checking:
 * php artisan aml-get-block-tr:batch
 *
 * Fetch results by check UUIDs:
 * php artisan aml-get-block-tr:batch --hashes=00000000-0000-0000-0000-000000000012,00000000-0000-0000-0000-000000000013,00000000-0000-0000-0000-000000000014
 * php artisan aml-get-block-tr:batch --hashes=00000000-0000-0000-0000-000000000015,00000000-0000-0000-0000-000000000016,00000000-0000-0000-0000-000000000017,00000000-0000-0000-0000-000000000018
 */

//00000000-0000-0000-0000-000000000012
//00000000-0000-0000-0000-000000000013
//00000000-0000-0000-0000-000000000014

//00000000-0000-0000-0000-000000000015
//00000000-0000-0000-0000-000000000016
//00000000-0000-0000-0000-000000000017
//00000000-0000-0000-0000-000000000018

namespace App\Console\Commands\Aml\GetBlock;

use App\Helpers\Development;
use App\Services\Aml\GetBlock\CheckTx;
use App\Services\Aml\GetBlock\GetResult;
use Illuminate\Console\Command;

class TransactionBatch extends Command
{
    protected $signature = 'aml-get-block-tr:batch
                            {--hashes= : Comma-separated check UUIDs to fetch results for}';

    protected $description = 'Batch-submit transactions for GetBlock AML checks and fetch the results';

    /**
     * List of transactions to submit for checking.
     * Fill in addr for each transaction.
     */
    private array $transactions = [
        [
            'tx' => '0000000000000000000000000000000000000000000000000000000000000002',
            'addr' => 'TEXAMPLEADDRESS0000000000000000006',
            'currency' => 'TRX'
        ],
        [
            'tx' => '0000000000000000000000000000000000000000000000000000000000000009',
            'addr' => 'TEXAMPLEADDRESS0000000000000000018',
            'currency' => 'TRX'
        ],
        [
            'tx' => '000000000000000000000000000000000000000000000000000000000000000a',
            'addr' => 'TEXAMPLEADDRESS0000000000000000019',
            'currency' => 'TRX'
        ],
    ];

    public function handle(): void
    {
        $timeStart = microtime(true);

        if ($this->option('hashes')) {
            $this->fetchResults();
        } else {
            $this->submitTransactions();
        }

        $this->newLine();
        $this->line('Memory: ' . Development::getPeakMemoryUsageInMb());
        $this->line('Time: ' . Development::getFormatDiffMicroTime($timeStart) . ' s');
    }

    private function submitTransactions(): void
    {
        $this->info('Submitting ' . count($this->transactions) . ' transactions for checking...');
        $this->newLine();

        $rows = [];
        $uuids = [];

        foreach ($this->transactions as $i => $item) {
            $shortTx = substr($item['tx'], 0, 16) . '...' . substr($item['tx'], -8);
            $this->line("  [{$i}] Submitting: {$shortTx}");

            $result = (new CheckTx())->checkTx(
                mt_rand(1,1000),
                $item['tx'],
                $item['addr'],
                $item['currency'],
                '',
            );

            $checkHash = $result['result']['check']['hash'] ?? null;
            $error = $result['error'] ?? null;

            if ($checkHash) {
                $uuids[] = $checkHash;
                $rows[] = [
                    substr($item['tx'], 0, 20) . '...',
                    $item['addr'] ? substr($item['addr'], 0, 20) . '...' : '-',
                    $checkHash,
                    'OK',
                ];
            } else {

                $this->error('Error');
                return;
                $rows[] = [
                    substr($item['tx'], 0, 20) . '...',
                    $item['addr'] ? substr($item['addr'], 0, 20) . '...' : '-',
                    '-',
                    $error ? 'Error: ' . substr((string)$error, 0, 30) : 'No hash in the response',
                ];
            }
        }

        $this->newLine();
        $this->table(
            ['TX Hash', 'Addr', 'Check UUID', 'Status'],
            $rows,
        );

        if ($uuids) {
            $this->newLine();
            $this->info('Command to fetch the results:');
            $this->line('  php artisan aml-get-block-tr:batch --hashes=' . implode(',', $uuids));
        }
    }

    private function fetchResults(): void
    {
        $hashes = array_filter(array_map('trim', explode(',', $this->option('hashes'))));

        $this->info('Fetching results for ' . count($hashes) . ' checks...');
        $this->newLine();

        $rows = [];
        foreach ($hashes as $i => $hash) {
            $this->line("  [{$i}] Request: {$hash}");

            $result = (new GetResult())->getResult($i + 1, $hash);

            $check = $result['result']['check'] ?? null;
            $error = $result['error'] ?? null;

            if ($check) {
                $status = $check['status'] ?? '-';
                $riskscore = isset($check['report']['riskscore'])
                    ? round($check['report']['riskscore'] * 100, 2) . '%'
                    : '-';
                $pdfLink = $check['pdfLink'] ?? ('https://getblock.net/en/report-preview/' . $hash);
                $currency = $check['currency'] ?? '-';
                $tx = isset($check['transaction'])
                    ? substr($check['transaction'], 0, 16) . '...'
                    : '-';

                $rows[] = [$hash, $currency, $tx, $status, $riskscore, $pdfLink];
            } else {
                $rows[] = [
                    $hash, '-', '-', 'ERROR',
                    '-',
                    $error ? substr((string)$error, 0, 60) : 'No data',
                ];
            }
        }

        $this->newLine();
        $this->table(
            ['Check UUID', 'Currency', 'TX Hash', 'Status', 'Risk Score', 'Report (PDF)'],
            $rows,
        );
    }
}
