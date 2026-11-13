<?php

declare(strict_types=1);

namespace App\Reports;

use App\Enums\WalletStatuses;
use App\Models\Recipient\Recipient;
use App\Models\Recipient\Wallet as RecipientWallet;
use App\Models\Wallet\Transaction;
use App\Models\Wallet\Wallet;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class WalletsStatisticsReport
{
    public function __construct(private readonly Carbon $dateFrom) {}

    public static function forPeriod(string $period): self
    {
        return new self(self::dateFromPeriod($period));
    }

    public function getData(): array
    {
        $completedWallets = $this->countByRecipient([
            WalletStatuses::COMPLETED->value,
            WalletStatuses::TRANSFER_MONEY_PROCESS_FINISH->value,
        ]);

        $timedOutWallets = $this->countByRecipient([
            WalletStatuses::TIME_OUT->value,
        ]);

        $recipients = Recipient::all()->keyBy('id');
        $allRecipientIds = $completedWallets->keys()
            ->merge($timedOutWallets->keys())
            ->unique()->sort()->values();

        if ($allRecipientIds->isEmpty()) {
            return $this->emptyData();
        }

        $walletsByType = $this->countByRecipientAndType();
        $typesTotals = $this->countByType();

        $completedData = $newData = $conversionData = $whiteData = $grayData = $blackData = $yellowData = $labels = [];
        $recipientsStatistics = [];

        foreach ($allRecipientIds as $recipientId) {
            $recipientName = $recipients->get($recipientId)?->name ?? "Recipient #{$recipientId}";
            $completed = $completedWallets->get($recipientId, 0);
            $timedOut = $timedOutWallets->get($recipientId, 0);
            $total = $completed + $timedOut;
            $conversion = $total > 0 ? round(($completed / $total) * 100, 2) : 0;

            $labels[] = $recipientName;
            $completedData[] = $completed;
            $newData[] = $timedOut;
            $conversionData[] = $conversion;

            $typeMap = $walletsByType->get($recipientId, collect());
            $whiteData[] = $typeMap->get(RecipientWallet::TYPE_WHITE, 0);
            $grayData[] = $typeMap->get(RecipientWallet::TYPE_GRAY, 0);
            $blackData[] = $typeMap->get(RecipientWallet::TYPE_BLACK, 0);
            $yellowData[] = $typeMap->get(RecipientWallet::TYPE_YELLOW, 0);

            $recipientsStatistics[] = [
                'recipient' => $recipientName,
                'new' => $timedOut,
                'total' => $total,
                'completed' => $completed,
                'conversion' => $conversion,
            ];
        }

        [$avgSeries, $minSeries, $maxSeries, $tableRows] = $this->processingTimeByRecipientAndDay();
        $processingTimePie = $this->processingTimeAvgPieByRecipient();

        return [
            'completedWalletsChart' => [
                ['name' => 'Distribution by recipient', 'values' => $completedData, 'labels' => $labels],
            ],
            'newWalletsChart' => [
                ['name' => 'Distribution by recipient', 'values' => $newData, 'labels' => $labels],
            ],
            'conversionRateChart' => [
                ['name' => 'Conversion', 'values' => $conversionData, 'labels' => $labels],
            ],
            'walletTypesByRecipientChart' => [
                ['name' => 'White', 'values' => $whiteData, 'labels' => $labels],
                ['name' => 'Gray', 'values' => $grayData, 'labels' => $labels],
                ['name' => 'Black', 'values' => $blackData, 'labels' => $labels],
                ['name' => 'Yellow', 'values' => $yellowData, 'labels' => $labels],
            ],
            'walletTypesChart' => [
                [
                    'name'   => 'Distribution by type',
                    'values' => [
                        $typesTotals->get(RecipientWallet::TYPE_WHITE, 0),
                        $typesTotals->get(RecipientWallet::TYPE_GRAY, 0),
                        $typesTotals->get(RecipientWallet::TYPE_BLACK, 0),
                        $typesTotals->get(RecipientWallet::TYPE_YELLOW, 0),
                    ],
                    'labels' => ['White', 'Gray', 'Black', 'Yellow'],
                ],
            ],
            'recipientsStatistics' => $recipientsStatistics,
            'processingTimeAvgChart' => $avgSeries,
            'processingTimeMinChart' => $minSeries,
            'processingTimeMaxChart' => $maxSeries,
            'processingTimeTable' => $tableRows,
            'processingTimePieChart' => $processingTimePie,
        ];
    }

    private function countByRecipient(array $statuses): Collection
    {
        return Wallet::whereIn('status', $statuses)
            ->whereNotNull('recipient_id')
            ->where('created_at', '>=', $this->dateFrom)
            ->get(['recipient_id'])
            ->groupBy('recipient_id')
            ->map->count();
    }

    private function countByRecipientAndType(): Collection
    {
        return Wallet::whereNotNull('recipient_id')
            ->whereNotNull('type')
            ->where('created_at', '>=', $this->dateFrom)
            ->get(['recipient_id', 'type'])
            ->groupBy('recipient_id')
            ->map(fn(Collection $wallets) => $wallets->groupBy('type')->map->count());
    }

    private function countByType(): Collection
    {
        return Wallet::whereNotNull('type')
            ->where('created_at', '>=', $this->dateFrom)
            ->get(['type'])
            ->groupBy('type')
            ->map->count();
    }

    private function emptyData(): array
    {
        $empty = [['name' => 'No data', 'values' => [0], 'labels' => ['No data']]];

        return [
            'completedWalletsChart' => $empty,
            'newWalletsChart' => $empty,
            'conversionRateChart' => $empty,
            'walletTypesByRecipientChart' => $empty,
            'walletTypesChart' => $empty,
            'recipientsStatistics' => [],
            'processingTimeAvgChart' => $empty,
            'processingTimeMinChart' => $empty,
            'processingTimeMaxChart' => $empty,
            'processingTimeTable' => [],
            'processingTimePieChart' => $empty,
        ];
    }

    private function processingTimeAvgPieByRecipient(): array
    {
        $rows = Transaction::whereNotNull('send_at')
            ->where('send_at', '>', DB::raw('created_at'))
            ->where('created_at', '>=', $this->dateFrom)
            ->select([
                'recipient_id',
                DB::raw('ROUND(AVG(TIMESTAMPDIFF(MINUTE, created_at, send_at)), 1) as avg_minutes'),
            ])
            ->groupBy('recipient_id')
            ->get();

        if ($rows->isEmpty()) {
            return [['name' => 'No data', 'values' => [0], 'labels' => ['No data']]];
        }

        $recipients = Recipient::whereIn('id', $rows->pluck('recipient_id'))->get()->keyBy('id');

        $values = [];
        $labels = [];

        foreach ($rows as $row) {
            $labels[] = $recipients->get($row->recipient_id)?->name ?? "Recipient #{$row->recipient_id}";
            $values[] = (float) $row->avg_minutes;
        }

        return [['name' => 'Average processing time (min)', 'values' => $values, 'labels' => $labels]];
    }

    private function processingTimeByRecipientAndDay(): array
    {
        $rows = Transaction::whereNotNull('send_at')
            ->where('send_at', '>', DB::raw('created_at'))
            ->where('created_at', '>=', $this->dateFrom)
            ->select([
                'recipient_id',
                DB::raw('DATE(created_at) as day'),
                DB::raw('ROUND(AVG(TIMESTAMPDIFF(MINUTE, created_at, send_at)), 1) as avg_minutes'),
                DB::raw('MIN(TIMESTAMPDIFF(MINUTE, created_at, send_at)) as min_minutes'),
                DB::raw('MAX(TIMESTAMPDIFF(MINUTE, created_at, send_at)) as max_minutes'),
                DB::raw('COUNT(*) as total_count'),
            ])
            ->groupBy('recipient_id', DB::raw('DATE(created_at)'))
            ->orderBy('day')
            ->get();

        if ($rows->isEmpty()) {
            $empty = [['name' => 'No data', 'values' => [0], 'labels' => ['No data']]];
            return [$empty, $empty, $empty, []];
        }

        $allDays = $rows->pluck('day')->unique()->sort()->values()->toArray();
        $recipients = Recipient::whereIn('id', $rows->pluck('recipient_id')->unique())->get()->keyBy('id');

        $avgSeries = $minSeries = $maxSeries = $tableRows = [];

        foreach ($rows->groupBy('recipient_id') as $recipientId => $recipientRows) {
            $recipientName = $recipients->get($recipientId)?->name ?? "Recipient #{$recipientId}";
            $rowsByDay = $recipientRows->keyBy('day');

            $avgValues = $minValues = $maxValues = [];
            foreach ($allDays as $day) {
                $row = $rowsByDay->get($day);
                $avgValues[] = $row ? (float) $row->avg_minutes : null;
                $minValues[] = $row ? (int) $row->min_minutes : null;
                $maxValues[] = $row ? (int) $row->max_minutes : null;
            }

            $avgSeries[] = ['name' => $recipientName, 'values' => $avgValues, 'labels' => $allDays];
            $minSeries[] = ['name' => $recipientName, 'values' => $minValues, 'labels' => $allDays];
            $maxSeries[] = ['name' => $recipientName, 'values' => $maxValues, 'labels' => $allDays];
        }

        // Table: sort by date (newest first), then by recipient
        foreach ($rows->sortByDesc('day')->groupBy('day') as $day => $dayRows) {
            foreach ($dayRows->sortBy(fn($r) => $recipients->get($r->recipient_id)?->name) as $row) {
                $tableRows[] = [
                    'day'    => $row->day,
                    'recipient' => $recipients->get($row->recipient_id)?->name ?? "Recipient #{$row->recipient_id}",
                    'avg'    => (float) $row->avg_minutes,
                    'min'    => (int) $row->min_minutes,
                    'max'    => (int) $row->max_minutes,
                    'count'  => (int) $row->total_count,
                ];
            }
        }

        return [$avgSeries, $minSeries, $maxSeries, $tableRows];
    }

    private static function dateFromPeriod(string $period): Carbon
    {
        return match ($period) {
            'today' => now()->startOfDay(),
            'week' => now()->subWeek(),
            '3months' => now()->subMonths(3),
            'year' => now()->subYear(),
            default => now()->subMonth(),
        };
    }
}
