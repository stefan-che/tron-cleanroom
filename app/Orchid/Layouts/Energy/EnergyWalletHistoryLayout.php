<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Energy;

use App\Helpers\LogFormatter;
use App\Models\Energy\EnergyWalletHistory;
use Orchid\Screen\Layouts\Table;
use Orchid\Screen\TD;

class EnergyWalletHistoryLayout extends Table
{
    public $target = 'history';

    public function columns(): array
    {
        return [
            TD::make('created_at', 'Date')
                ->width('160px')
                ->render(fn(EnergyWalletHistory $h) => $h->created_at?->format('d.m.Y H:i:s')),

            TD::make('event_label', 'Event')
                ->width('160px')
                ->render(fn(EnergyWalletHistory $h) => $h->event_label),

            TD::make('changed_by', 'Changed by')
                ->width('180px'),

            TD::make('note', 'Note'),

            TD::make('api_response', 'API response')
                ->render(function (EnergyWalletHistory $h) {
                    if (!$h->api_response) {
                        return '-';
                    }

                    $decoded = json_decode($h->api_response, true);
                    $pretty  = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                    return '<pre style="max-height:120px;overflow:auto;font-size:11px;margin:0">'
                        . e($pretty)
                        . '</pre>';
                }),
        ];
    }
}
