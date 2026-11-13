<?php
/**
 * Submit transactions from the static array for checking:
 * php artisan aml-amlbot-tr:batch
 *
 * Fetch results by check UIDs:
 * php artisan aml-amlbot-tr:batch --uids=UID1,UID2
 */

namespace App\Console\Commands\Aml\AmlBot;

use App\Helpers\Development;
use App\Models\Setting;
use App\Services\Aml\AmlBot\CheckTransaction;
use App\Services\Aml\AmlBot\ReCheck;
use Illuminate\Console\Command;

class TransactionBatch extends Command
{
    protected $signature = 'aml-amlbot-tr:batch
                            {--uids= : Comma-separated check UIDs to fetch results for}';

    protected $description = 'Batch-submit transactions for AmlBot AML checks and fetch results by UID';

    /**
     * List of transactions to submit for checking.
     * Fill in before running.
     */
    private array $transactions = [
        [
            'tx'        => '0000000000000000000000000000000000000000000000000000000000000002',
            'address'   => 'TEXAMPLEADDRESS0000000000000000006',
            'asset'     => 'TRX',
            'direction' => 'deposit',
        ],
        [
            'tx'        => '0000000000000000000000000000000000000000000000000000000000000009',
            'address'   => 'TEXAMPLEADDRESS0000000000000000018',
            'asset'     => 'TRX',
            'direction' => 'deposit',
        ],
        [
            'tx'        => '000000000000000000000000000000000000000000000000000000000000000a',
            'address'   => 'TEXAMPLEADDRESS0000000000000000019',
            'asset'     => 'TRX',
            'direction' => 'deposit',
        ],
    ];

    public function handle(): void
    {
        $timeStart = microtime(true);

        if ($this->option('uids')) {
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
        $uids = [];

        foreach ($this->transactions as $i => $item) {
            $shortTx = substr($item['tx'], 0, 16) . '...' . substr($item['tx'], -8);
            $this->line("  [{$i}] Submitting: {$shortTx}");

            $result = (new CheckTransaction())->check(
                $item['asset'],
                $item['address'],
                $item['tx'],
                $item['direction'],
                Setting::getAmlBotFlowMode()
            );

            $uid   = $result['data']['uid'] ?? null;
            $error = $result['error'] ?? null;

            if ($uid) {
                $uids[]  = $uid;
                $rows[]  = [
                    substr($item['tx'], 0, 20) . '...',
                    substr($item['address'], 0, 20) . '...',
                    $uid,
                    'OK',
                ];
            } else {
                $this->error('Error');
                return;
                $rows[] = [
                    substr($item['tx'], 0, 20) . '...',
                    substr($item['address'], 0, 20) . '...',
                    '-',
                    $error ? 'Error: ' . substr((string)$error, 0, 30) : 'No uid in the response',
                ];
            }
        }

        $this->newLine();
        $this->table(
            ['TX Hash', 'Addr', 'Check UID', 'Status'],
            $rows,
        );

        if ($uids) {
            $this->newLine();
            $this->info('Command to fetch the results:');
            $this->line('  php artisan aml-amlbot-tr:batch --uids=' . implode(',', $uids));
        }
    }

    private function fetchResults(): void
    {
        $uids = array_filter(array_map('trim', explode(',', $this->option('uids'))));

        $this->info('Fetching results for ' . count($uids) . ' checks...');
        $this->newLine();

        $rows = [];
        foreach ($uids as $i => $uid) {
            $this->line("  [{$i}] Request: {$uid}");

            $result = (new ReCheck())->reCheck($uid);

            $data  = $result['data'] ?? null;
            $error = $result['error'] ?? null;

            if ($data) {
                $status    = $data['status'] ?? '-';
                $riskscore = isset($data['riskscore'])
                    ? round($data['riskscore'] * 100, 2) . '%'
                    : '-';
                $pdfReport = $data['pdfReport'] ?? '-';
                $asset     = $data['asset'] ?? '-';
                $tx        = isset($data['tx'])
                    ? substr($data['tx'], 0, 16) . '...'
                    : '-';

                $rows[] = [$uid, $asset, $tx, $status, $riskscore, $pdfReport];
            } else {
                $rows[] = [
                    $uid, '-', '-', 'ERROR',
                    '-',
                    $error ? substr((string)$error, 0, 60) : 'No data',
                ];
            }
        }

        $this->newLine();
        $this->table(
            ['Check UID', 'Currency', 'TX Hash', 'Status', 'Risk Score', 'Report (PDF)'],
            $rows,
        );
    }
}
