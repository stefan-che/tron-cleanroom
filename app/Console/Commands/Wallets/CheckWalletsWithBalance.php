<?php

/**
 * php artisan wallets:check-balance
 * php artisan wallets:check-balance --from-date=01.03.2026
 * php artisan wallets:check-balance --from-date=01.01.2026 --to-date=28.02.2026 --debug
 * php artisan wallets:check-balance --from-date=01.03.2026 --to-date=01.09.2026 +
 */

namespace App\Console\Commands\Wallets;

use App\Helpers\Development;
use App\Models\Wallet\Wallet;
use App\Services\Wallet\WalletService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class CheckWalletsWithBalance extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wallets:check-balance
                            {--from-date= : Scan wallets created on or after this date (format: d.m.Y, e.g. 01.03.2026)}
                            {--to-date= : Scan wallets created up to this date (format: d.m.Y, e.g. 31.03.2026)}
                            {--debug : Show the query SQL and wallet distribution by creation month}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scan wallets from newest to oldest and show those with a balance';

    private WalletService $service;

    public function __construct(WalletService $service)
    {
        parent::__construct();
        $this->service = $service;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $timeStart = microtime(true);

        $fromDate = $this->parseDate('from-date');
        $toDate   = $this->parseDate('to-date');

        $query = Wallet::query()->orderByDesc('id');

        if ($fromDate) {
            $query->where('created_at', '>=', $fromDate->copy()->startOfDay());
        }

        if ($toDate) {
            $query->where('created_at', '<=', $toDate->copy()->endOfDay());
        }

        if ($fromDate || $toDate) {
            $range = ($fromDate ? $fromDate->format('d.m.Y') : '...') . ' - ' . ($toDate ? $toDate->format('d.m.Y') : '...');
            $this->info('Filter: wallets created ' . $range);
        }

        if ($this->option('debug')) {
            $this->showDebug($query);
        }

        $total = $query->count();
        $this->info("Total wallets to scan: {$total}");

        if ($total === 0) {
            $this->warn('No wallets to check');
            return 0;
        }

        $found = [];
        $counter = 0;

        $this->output->progressStart($total);

        $query->chunk(100, function ($wallets) use (&$found, &$counter) {
            /**
             * @var Wallet $wallet
             */
            foreach ($wallets as $wallet) {
                $this->output->progressAdvance();

                try {
                    $balance = $this->service->getBalance($wallet);
                } catch (\Exception $e) {
                    $balance = 0;
                }

                if ($balance > 0) {
                    $found[] = [
                        'id' => $wallet->id,
                        'external_id' => $wallet->external_id,
                        'address' => $wallet->address,
                        'balance' => $balance,
                        'status' => $wallet->status_name ?? $wallet->status,
                        'created_at' => $wallet->created_at?->format('d.m.Y H:i'),
                        'updated_at' => $wallet->updated_at?->format('d.m.Y H:i'),
                    ];
                }

                $counter++;
                if ($counter % 5 === 0) {
                    sleep(2);
                }
            }
        });

        $this->output->progressFinish();
        $this->newLine();

        if (empty($found)) {
            $this->warn('No wallets with a balance found');
        } else {
            $this->info('Wallets with a balance: ' . count($found));
            $this->newLine();

            $this->table(
                ['ID', 'External ID', 'Address', 'Balance', 'Status', 'Created', 'Updated'],
                $found
            );
        }

        $this->newLine();
        $this->info('Memory used: ' . Development::getPeakMemoryUsageInMb());
        $this->info('Execution time, seconds: ' . Development::getFormatDiffMicroTime($timeStart));

        return 0;
    }

    /**
     * Diagnostics: the actual DB query and wallet distribution by creation date.
     */
    private function showDebug(Builder $query): void
    {
        $bindings = array_map(
            fn ($b) => $b instanceof \DateTimeInterface ? $b->format('Y-m-d H:i:s') : $b,
            $query->getBindings()
        );

        $this->newLine();
        $this->line('SQL:      ' . $query->toSql());
        $this->line('Bindings: ' . json_encode($bindings, JSON_UNESCAPED_UNICODE));
        $this->line('Total wallets in table: ' . Wallet::query()->count());
        $this->line('Matching the filter:      ' . (clone $query)->count());

        $rows = Wallet::query()
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, count(*) as cnt, min(id) as min_id, max(id) as max_id")
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        $this->table(
            ['created_at month', 'Wallets', 'min id', 'max id'],
            $rows->map(fn ($r) => [$r->month, $r->cnt, $r->min_id, $r->max_id])->all()
        );
        $this->newLine();
    }

    private function parseDate(string $option): ?Carbon
    {
        $raw = $this->option($option);

        if (!$raw) {
            return null;
        }

        try {
            return Carbon::createFromFormat('d.m.Y', $raw)->startOfDay();
        } catch (\Exception $e) {
            $this->error("Invalid date format: {$raw}. Expected d.m.Y (e.g. 01.03.2026)");
            exit(1);
        }
    }
}
