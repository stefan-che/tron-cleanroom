<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Operations;

use App\Helpers\DateHelper;
use App\Models\Wallet\WalletWebhook;
use Orchid\Screen\Layouts\Table;
use Orchid\Screen\TD;

class WalletWebhookList extends Table
{
    public $target = 'webhooks';

    public function columns(): array
    {
        return [
            TD::make('id', 'ID')
                ->width('60px')
                ->render(fn(WalletWebhook $wh) => $wh->id),

            TD::make('created_at', 'Received')
                ->render(fn(WalletWebhook $wh) => DateHelper::toDisplayTime($wh->created_at)?->format('Y-m-d H:i:s') ?? '-'),

            TD::make('event_ts', 'Event time')
                ->render(fn(WalletWebhook $wh) => $wh->event_ts
                    ? DateHelper::toDisplayTime($wh->event_ts)->format('Y-m-d H:i:s')
                    : '-'),

            TD::make('direction', 'Direction')
                ->render(fn(WalletWebhook $wh) => $wh->direction ?? '-'),

            TD::make('network_token', 'Network / Token')
                ->render(fn(WalletWebhook $wh) => ($wh->network ?? '-') . ' / ' . ($wh->token ?? '-')),

            TD::make('amount', 'Amount')
                ->render(fn(WalletWebhook $wh) => $wh->amount ?? '-'),

            TD::make('txid', 'TXID')
                ->render(fn(WalletWebhook $wh) => $wh->txid
                    ? '<span title="' . e($wh->txid) . '">' . substr($wh->txid, 0, 12) . '...</span>'
                    : '-'),

            TD::make('from_address', 'From')
                ->render(fn(WalletWebhook $wh) => $wh->from_address ?? '-'),

            TD::make('block', 'Block')
                ->defaultHidden()
                ->render(fn(WalletWebhook $wh) => $wh->block ?? '-'),

            TD::make('ip_address', 'IP')
                ->defaultHidden()
                ->render(fn(WalletWebhook $wh) => $wh->ip_address ?? '-'),
        ];
    }
}
