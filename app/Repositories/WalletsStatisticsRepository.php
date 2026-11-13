<?php

namespace App\Repositories;

use App\Enums\WalletStatuses;
use App\Helpers\MathHelper;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class WalletsStatisticsRepository
{
    /**
     * Number of wallets created per day over the last month.
     * Wallets in NEW status are not counted.
     *
     * @return array{labels: array<string>, values: array<int>}
     */
    public function getCreatedWalletsByDay(): array
    {
        $dateFrom = now()->subMonth()->startOfDay();

        $counts = DB::table('wallets')
            ->where('status', '!=', WalletStatuses::NEW->value)
            ->where('created_at', '>=', $dateFrom)
            ->select([
                DB::raw('DATE(created_at) as day'),
                DB::raw('COUNT(id) as total'),
            ])
            ->groupBy(DB::raw('DATE(created_at)'))
            ->pluck('total', 'day');

        $labels = [];
        $values = [];

        for ($day = $dateFrom->clone(); $day <= now()->startOfDay(); $day->addDay()) {
            $date = $day->toDateString();

            $labels[] = $day->format('d.m');
            $values[] = (int) ($counts[$date] ?? 0);
        }

        return ['labels' => $labels, 'values' => $values];
    }

    public function getStatisticsWallets(): array
    {
        $completed = WalletStatuses::COMPLETED->value;

//        status IN($completed) AND  status IN($completed) AND
        $sql = <<<SQL
SELECT
    completed_today.value AS completed_today,
    completed_yesterday.value AS completed_yesterday,
    completed_today.value - completed_yesterday.value AS completed_today_diff,
    total.value AS total,
    usdt_balance_yesterday.value AS usdt_balance_yesterday,
    usdt_balance_today.value AS usdt_balance_today,
    usdt_balance.value AS usdt_balance,
    trx_balance_yesterday.value AS trx_balance_yesterday,
    trx_balance_today.value AS trx_balance_today,
    trx_balance.value AS trx_balance

FROM
    (
        SELECT COUNT(id) AS value
        FROM wallets
        WHERE DATE(created_at) = CURDATE()
    ) AS completed_today,
    (
        SELECT COUNT(id) AS value
        FROM wallets
        WHERE DATE(created_at) = CURDATE() - INTERVAL 1 DAY
    ) AS completed_yesterday,
    (
        SELECT SUM(amount) AS value
        FROM wallets
        WHERE DATE(created_at) = CURDATE() - INTERVAL 1 DAY AND amount > 0
    ) AS usdt_balance_yesterday,
    (
        SELECT SUM(amount) AS value
        FROM wallets
        WHERE DATE(created_at) = CURDATE() AND amount > 0
    ) AS usdt_balance_today,
    (
        SELECT SUM(amount) AS value
        FROM wallets
        WHERE amount > 0
    ) AS usdt_balance,
    (
        SELECT SUM(amount_trx) AS value
        FROM wallets
        WHERE DATE(created_at) = CURDATE() - INTERVAL 1 DAY AND amount > 0
    ) AS trx_balance_yesterday,
    (
        SELECT SUM(amount_trx) AS value
        FROM wallets
        WHERE DATE(created_at) = CURDATE() AND amount > 0
    ) AS trx_balance_today,
    (
        SELECT SUM(amount_trx) AS value
        FROM wallets
        WHERE amount > 0
    ) AS trx_balance,

    (
        SELECT COUNT(id) AS value
        FROM wallets
    ) AS total;
SQL;


        $data = Cache::remember('stats', 60, function () use ($sql) {
            return DB::selectOne($sql, [
            ]);
        });

        return [
            'completed_today' => $data->completed_today,
            'completed_yesterday' => $data->completed_yesterday,
            'completed_today_diff' => $data->completed_today_diff,
            'usdt_balance_yesterday' => MathHelper::roundingDown($data->usdt_balance_yesterday),
            'usdt_balance_today' => MathHelper::roundingDown($data->usdt_balance_today),
            'usdt_balance' => MathHelper::roundingDown($data->usdt_balance),
            'trx_balance_yesterday' => MathHelper::roundingDown($data->trx_balance_yesterday),
            'trx_balance_today' => MathHelper::roundingDown($data->trx_balance_today),
            'trx_balance' => MathHelper::roundingDown($data->trx_balance, 2),
            'total' => $data->total,
        ];
    }
}
