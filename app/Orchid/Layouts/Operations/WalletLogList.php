<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Operations;

use App\Helpers\DateHelper;
use App\Helpers\LogFormatter;
use App\Models\Wallet\WalletLog;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Components\Cells\DateTimeSplit;
use Orchid\Screen\Layouts\Table;
use Orchid\Screen\TD;

class WalletLogList extends Table
{
    /**
     * @var string
     */
    public $target = 'logs';

    /**
     * @return TD[]
     */
    public function columns(): array
    {
        return [
            TD::make('id', 'ID')
                ->defaultHidden()
                ->render(fn(WalletLog $log) => Link::make((string)$log->id)
                    ->rawClick()
                    ->icon('arrow-right')
                    ->route('platform.operation.info', $log->wallet_id)
                ),
            TD::make('created_at', 'Date/Time')
                ->render(function (WalletLog $log) {
                    return DateHelper::toDisplayTime($log->created_at)->format('Y-m-d H:i:s');
                }),
            TD::make('action_name', 'Action')
                ->render(function (WalletLog $log) {
                    return $log->action_name;
                }),
            TD::make('payload', 'Data')
                ->defaultHidden()
                ->render(function (WalletLog $log) {
                    if (!$log->payload) {
                        return '<span class="text-muted">-</span>';
                    }
                    return LogFormatter::formatJson($log->payload);
                }),
            TD::make('user_id', 'User')
                ->defaultHidden()
                ->render(function (WalletLog $log) {
                    return $log->user?->name;
                }),

        ];
    }
}
