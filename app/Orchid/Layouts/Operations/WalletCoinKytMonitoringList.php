<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Operations;

use App\Helpers\DateHelper;
use App\Helpers\LogFormatter;
use App\Models\Aml\CoinKytMonitoringEvent;
use Orchid\Screen\Layouts\Table;
use Orchid\Screen\TD;

class WalletCoinKytMonitoringList extends Table
{
    /**
     * @var string
     */
    public $target = 'coinkyt_events';

    /**
     * @return TD[]
     */
    public function columns(): array
    {
        return [
            TD::make('event_at', 'Event date')
                ->width('150')
                ->render(fn(CoinKytMonitoringEvent $event) => $event->event_at
                    ? DateHelper::toDisplayTime($event->event_at)->format('Y-m-d H:i:s')
                    : '-'),

            TD::make('risk_grade', 'Risk')
                ->width('90')
                ->align(TD::ALIGN_CENTER)
                ->render(fn(CoinKytMonitoringEvent $event) => $this->gradeBadge($event->risk_grade)),

            TD::make('risk_profile', 'Risk profile')
                ->width('140')
                ->render(fn(CoinKytMonitoringEvent $event) => e((string) ($event->risk_profile ?? '-'))),

            TD::make('triggered_by', 'Triggered by')
                ->render(function (CoinKytMonitoringEvent $event) {
                    $items = $event->triggered_by ?? [];

                    if (empty($items)) {
                        return '<span class="text-muted">-</span>';
                    }

                    $rows = array_map(static function (array $item): string {
                        $label = e((string) ($item['type_label'] ?? $item['type'] ?? '?'));
                        $count = isset($item['total_count']) ? ' — ' . e((string) $item['total_count']) . '%' : '';
                        return $label . $count;
                    }, $items);

                    return '<div class="small">' . implode('<br>', $rows) . '</div>';
                }),

            TD::make('balance', 'Balance')
                ->width('100')
                ->align(TD::ALIGN_RIGHT)
                ->render(fn(CoinKytMonitoringEvent $event) => e((string) ($event->balance ?? '-'))
                    . ' ' . e((string) ($event->token ?? ''))),

            TD::make('txid', 'Transaction')
                ->width('160')
                ->render(function (CoinKytMonitoringEvent $event) {
                    if (empty($event->txid)) {
                        return '<span class="text-muted">-</span>';
                    }
                    $short = mb_substr($event->txid, 0, 10) . '…' . mb_substr($event->txid, -8);
                    return '<span title="' . e($event->txid) . '">' . e($short) . '</span>';
                }),

            TD::make('sender', 'Sender')
                ->width('160')
                ->render(function (CoinKytMonitoringEvent $event) {
                    if (empty($event->sender)) {
                        return '<span class="text-muted">-</span>';
                    }
                    $short = mb_substr($event->sender, 0, 8) . '…' . mb_substr($event->sender, -6);
                    return '<span title="' . e($event->sender) . '">' . e($short) . '</span>';
                }),

            TD::make('raw', 'Full response')
                ->render(function (CoinKytMonitoringEvent $event) {
                    if (empty($event->raw)) {
                        return '<span class="text-muted">-</span>';
                    }
                    return LogFormatter::formatJson($event->raw);
                }),
        ];
    }

    private function gradeBadge(?string $grade): string
    {
        return match ($grade) {
            CoinKytMonitoringEvent::GRADE_HIGH     => '<span class="badge bg-danger">HIGH</span>',
            CoinKytMonitoringEvent::GRADE_MODERATE => '<span class="badge bg-warning text-dark">MODERATE</span>',
            CoinKytMonitoringEvent::GRADE_LOW      => '<span class="badge bg-success">LOW</span>',
            CoinKytMonitoringEvent::GRADE_UNDEFINED => '<span class="badge bg-secondary">UNDEFINED</span>',
            default => '<span class="text-muted">-</span>',
        };
    }
}
